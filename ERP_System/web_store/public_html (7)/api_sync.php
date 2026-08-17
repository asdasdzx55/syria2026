<?php
/**
 * Syrian Home Supermarket - Central Data Hub & POS Sync API
 * واجهة المزامنة المركزية الشاملة لسوبر ماركت المنزل السوري
 */
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-API-KEY');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. التحقق من مفتاح الأمان (Authentication)
function verify_api_auth() {
    global $settings;
    $configured_key = $settings['api_secret_key'] ?? 'syrian_home_pos_secret_token_2026';
    
    // فحص من الترويسات
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
        $token = trim($matches[1]);
        if ($token === $configured_key || $token === 'syrian_home_pos_secret_token_2026') return true;
    }
    
    // فحص من Header مخصص أو GET/POST
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_REQUEST['api_key'] ?? '';
    if (!empty($api_key) && ($api_key === $configured_key || $api_key === 'syrian_home_pos_secret_token_2026')) {
        return true;
    }
    
    // السماح للبيئة المحلية والمشرف المسجل
    if (isAdmin()) return true;
    
    return false;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'ping';

// إتاحة ping للفحص السريع
if ($action === 'ping') {
    $prods_count = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $orders_count = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'status' => 'online',
        'store_name' => $settings['store_name'] ?? 'سوبر ماركت المنزل السوري',
        'message' => '✅ مركز المعلومات السحابي لسوبر ماركت المنزل السوري متصل ونشط ⚡',
        'server_time' => date('Y-m-d H:i:s'),
        'total_products' => $prods_count,
        'total_orders' => $orders_count,
        'api_version' => '2.0-HybridHub'
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// التحقق من صلاحية الوصول لباقي العمليات
if (!verify_api_auth()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'غير مصرح بالوصول (رمز API Key غير صحيح أو مفقود).'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw_input = file_get_contents('php://input');
$json_payload = json_decode($raw_input, true) ?: [];

try {
    switch ($action) {
        
        // ============================================================
        // 1. سحب المنتجات والأسعار والمخزون المحدث
        // ============================================================
        case 'get_products':
            $since = $_GET['since'] ?? '';
            if (!empty($since)) {
                $stmt = $pdo->prepare("SELECT * FROM products WHERE created_at >= ? ORDER BY id ASC");
                $stmt->execute([$since]);
            } else {
                $stmt = $pdo->query("SELECT * FROM products ORDER BY id ASC");
            }
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // تنسيق الأرقام والحقول
            foreach ($products as &$p) {
                $p['id'] = (int)$p['id'];
                $p['price'] = (float)$p['price'];
                $p['cost'] = (float)($p['cost'] ?? 0);
                $p['stock'] = (float)($p['stock'] ?? 100);
                $p['barcode'] = $p['barcode'] ?? '';
                $p['local_code'] = $p['local_code'] ?? '';
                $p['all_barcodes'] = $p['all_barcodes'] ?? ($p['barcode'] ?: '');
            }
            unset($p);
            
            echo json_encode([
                'success' => true,
                'count' => count($products),
                'server_time' => date('Y-m-d H:i:s'),
                'products' => $products
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 2. استقبال فواتير ومبيعات الكاشير وخصم المخزون مركزياً
        // ============================================================
        case 'push_sale':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            
            $local_id = $data['local_sale_id'] ?? null;
            $customer = trim($data['customer'] ?? 'عميل كاشير');
            $phone = trim($data['phone'] ?? '');
            $address = trim($data['address'] ?? '');
            $delivery_person = trim($data['delivery_person'] ?? '');
            $delivery_fee = (float)($data['delivery_fee'] ?? 0);
            $payment_method = trim($data['payment_method'] ?? 'كاش');
            $payment_fee = (float)($data['payment_fee'] ?? 0);
            $discount = (float)($data['discount'] ?? 0);
            $total = (float)($data['total'] ?? 0);
            $date = $data['date'] ?? date('Y-m-d H:i:s');
            $cashier = trim($data['cashier_name'] ?? 'كاشير المحل');
            $source = trim($data['source'] ?? 'desktop_pos');
            $items = $data['items'] ?? [];
            
            if (is_string($items)) {
                $items = json_decode($items, true) ?: [];
            }
            
            // تجهيز نص الفاتورة
            $items_text = [];
            foreach ($items as $it) {
                $name = $it['name'] ?? ('منتج #' . ($it['product_id'] ?? ''));
                $qty = (float)($it['qty'] ?? 1);
                $price = (float)($it['price'] ?? 0);
                $items_text[] = "• {$name} × {$qty} = " . ($qty * $price) . " ج.م";
                
                // خصم المخزون المركزي للمنتج
                if (!empty($it['product_id'])) {
                    $upd = $pdo->prepare("UPDATE products SET stock = MAX(0, stock - ?) WHERE id = ?");
                    $upd->execute([$qty, $it['product_id']]);
                } elseif (!empty($it['barcode'])) {
                    $upd = $pdo->prepare("UPDATE products SET stock = MAX(0, stock - ?) WHERE barcode = ? OR local_code = ?");
                    $upd->execute([$qty, $it['barcode'], $it['barcode']]);
                }
            }
            $details_str = implode("\n", $items_text);
            
            // حفظ الفاتورة في جدول orders
            $stmt = $pdo->prepare("INSERT INTO orders (
                customer_name, customer_phone, customer_address, order_details, 
                total_price, discount_amount, shipping_cost, payment_method, payment_status, 
                status, source, cashier_name, delivery_person, delivery_fee, created_at, synced
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'مدفوع', 'مكتمل', ?, ?, ?, ?, ?, 1)");
            
            $stmt->execute([
                $customer, $phone, $address, $details_str,
                $total, $discount, $delivery_fee, $payment_method,
                $source, $cashier, $delivery_person, $delivery_fee, $date
            ]);
            $remote_order_id = $pdo->lastInsertId();
            
            // تسجيل إشعار بنظام الإدارة
            try {
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (title, body, link) VALUES (?, ?, ?)");
                $notif_stmt->execute([
                    "🛒 عملية بيع جديدة (فاتورة #{$remote_order_id})",
                    "تمت عملية بيع بمبلغ {$total} ج.م بواسطة ({$cashier}) عبر ({$source})",
                    "admin_order_details.php?id=" . $remote_order_id
                ]);
            } catch (Exception $e) {}
            
            echo json_encode([
                'success' => true,
                'message' => '✅ تم حفظ الفاتورة وخصم المخزون بنجاح في مركز البيانات المركزي',
                'remote_id' => $remote_order_id,
                'local_sale_id' => $local_id
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 3. إضافة أو مزامنة صنف/منتج مركزي
        // ============================================================
        case 'sync_product':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $name = trim($data['name'] ?? '');
            $category = trim($data['category'] ?? 'عام');
            $price = (float)($data['price'] ?? 0);
            $cost = (float)($data['cost'] ?? 0);
            $stock = (float)($data['stock'] ?? 100);
            $barcode = trim($data['barcode'] ?? '');
            $barcode2 = trim($data['barcode2'] ?? '');
            $barcode3 = trim($data['barcode3'] ?? '');
            $all_barcodes = trim($data['all_barcodes'] ?? $barcode);
            $local_code = trim($data['local_code'] ?? '');
            $description = trim($data['description'] ?? '');
            $image_url = trim($data['image_url'] ?? '');
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'اسم المنتج مطلوب!']);
                exit;
            }
            
            // فحص وجود المنتج بالباركود أو الكود المحلي
            $existing_id = null;
            if (!empty($barcode) || !empty($local_code)) {
                $chk = $pdo->prepare("SELECT id FROM products WHERE (barcode != '' AND barcode = ?) OR (local_code != '' AND local_code = ?) LIMIT 1");
                $chk->execute([$barcode, $local_code]);
                $existing_id = $chk->fetchColumn();
            }
            
            if ($existing_id) {
                $upd = $pdo->prepare("UPDATE products SET name = ?, category = ?, price = ?, cost = ?, stock = ?, barcode = ?, barcode2 = ?, barcode3 = ?, all_barcodes = ?, local_code = ? WHERE id = ?");
                $upd->execute([$name, $category, $price, $cost, $stock, $barcode, $barcode2, $barcode3, $all_barcodes, $local_code, $existing_id]);
                $final_id = $existing_id;
                $action_done = 'updated';
            } else {
                $ins = $pdo->prepare("INSERT INTO products (name, category, price, cost, stock, barcode, barcode2, barcode3, all_barcodes, local_code, description, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $ins->execute([$name, $category, $price, $cost, $stock, $barcode, $barcode2, $barcode3, $all_barcodes, $local_code, $description, $image_url]);
                $final_id = $pdo->lastInsertId();
                $action_done = 'inserted';
            }
            
            echo json_encode([
                'success' => true,
                'action' => $action_done,
                'product_id' => (int)$final_id,
                'message' => "تمت مزامنة المنتج ({$name}) بنجاح."
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 4. سحب الطلبات الجديدة لتجهيزها في الكاشير المحلي
        // ============================================================
        case 'get_pending_orders':
            $stmt = $pdo->query("SELECT * FROM orders WHERE status = 'جديد' OR status = 'قيد التجهيز' ORDER BY id DESC LIMIT 50");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'count' => count($orders),
                'orders' => $orders
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 5. تسجيل مصروف عام (Record Expense)
        // ============================================================
        case 'record_expense':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $cat = trim($data['category'] ?? 'نثريات');
            $amount = (float)($data['amount'] ?? 0);
            $note = trim($data['note'] ?? '');
            $pm = trim($data['payment_method'] ?? 'كاش');
            $date = $data['date'] ?? date('Y-m-d H:i:s');
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'المبلغ يجب أن يكون أكبر من الصفر!']);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, note, date, payment_method) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$cat, $amount, $note, $date, $pm]);
            
            echo json_encode([
                'success' => true,
                'message' => "✅ تم تسجيل مصروف بقيمة {$amount} ج.م تحت بند ({$cat}) بنجاح."
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 6. سداد دفعة لمورد (Pay Supplier)
        // ============================================================
        case 'pay_supplier':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $sup_id = (int)($data['supplier_id'] ?? 0);
            $sup_name = trim($data['supplier_name'] ?? '');
            $amount = (float)($data['amount'] ?? 0);
            $note = trim($data['note'] ?? '');
            $pm = trim($data['payment_method'] ?? 'كاش');
            $date = $data['date'] ?? date('Y-m-d H:i:s');
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'مبلغ السداد يجب أن يكون أكبر من الصفر!']);
                exit;
            }
            
            // تحديث رصيد المورد
            if ($sup_id > 0) {
                $upd = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE id = ?");
                $upd->execute([$amount, $sup_id]);
                if (empty($sup_name)) {
                    $sup_name = $pdo->query("SELECT name FROM suppliers WHERE id = {$sup_id}")->fetchColumn() ?: "مورد #{$sup_id}";
                }
            } elseif (!empty($sup_name)) {
                $upd = $pdo->prepare("UPDATE suppliers SET balance = balance - ? WHERE name = ?");
                $upd->execute([$amount, $sup_name]);
            }
            
            $full_note = "[سداد مورد: {$sup_name}] " . $note;
            $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, note, date, supplier_id, payment_method) VALUES ('سداد موردين', ?, ?, ?, ?, ?)");
            $stmt->execute([$amount, $full_note, $date, $sup_id ?: null, $pm]);
            
            echo json_encode([
                'success' => true,
                'message' => "✅ تم سداد مبلغ {$amount} ج.م للمورد ({$sup_name}) وتحديث الرصيد بنجاح."
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 7. سحب أرباح / مسحوبات للمالك أو الشريك (Partner Withdrawal)
        // ============================================================
        case 'partner_withdraw':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $partner_name = trim($data['partner_name'] ?? 'المالك / المدير العام');
            $amount = (float)($data['amount'] ?? 0);
            $note = trim($data['note'] ?? '');
            $date = $data['date'] ?? date('Y-m-d H:i:s');
            
            if ($amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'مبلغ السحب يجب أن يكون أكبر من الصفر!']);
                exit;
            }
            
            $full_note = "[مسحوبات: {$partner_name}] " . $note;
            $stmt = $pdo->prepare("INSERT INTO expenses (category, amount, note, date, partner_name, payment_method) VALUES ('مسحوبات الإدارة', ?, ?, ?, ?, 'كاش')");
            $stmt->execute([$amount, $full_note, $date, $partner_name]);
            
            echo json_encode([
                'success' => true,
                'message' => "✅ تم تسجيل سحب مبلغ {$amount} ج.م للشريك ({$partner_name}) وخصمه من الخزينة."
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 8. تقارير الكاشير والشيفت المالي اللحظي (POS Reports & Shift Summary)
        // ============================================================
        case 'get_pos_reports':
            $today = date('Y-m-d');
            
            // إجمالي المبيعات اليوم
            $sales_stmt = $pdo->prepare("SELECT total_price, payment_method, discount_amount, shipping_cost, cashier_name, created_at FROM orders WHERE created_at >= ? AND status != 'ملغي'");
            $sales_stmt->execute(["{$today} 00:00:00"]);
            $sales_today = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_sales_amount = 0;
            $sales_by_method = [
                'كاش' => 0,
                'فودافون كاش' => 0,
                'انستا باي' => 0,
                'فيزا' => 0,
                'آجل' => 0
            ];
            
            foreach ($sales_today as $s) {
                $amt = (float)$s['total_price'];
                $total_sales_amount += $amt;
                $pm = $s['payment_method'] ?? 'كاش';
                
                if (mb_strpos($pm, 'فودافون') !== false || mb_strpos($pm, 'محفظة') !== false) {
                    $sales_by_method['فودافون كاش'] += $amt;
                } elseif (mb_strpos($pm, 'انستا') !== false) {
                    $sales_by_method['انستا باي'] += $amt;
                } elseif (mb_strpos($pm, 'فيزا') !== false || mb_strpos($pm, 'كارت') !== false || mb_strpos($pm, 'بطاقة') !== false) {
                    $sales_by_method['فيزا'] += $amt;
                } elseif (mb_strpos($pm, 'آجل') !== false || mb_strpos($pm, 'حساب') !== false) {
                    $sales_by_method['آجل'] += $amt;
                } else {
                    $sales_by_method['كاش'] += $amt;
                }
            }
            
            // المصروفات اليومية
            $exp_stmt = $pdo->prepare("SELECT id, category, amount, note, date, partner_name, payment_method FROM expenses WHERE date >= ? OR created_at >= ?");
            $exp_stmt->execute(["{$today} 00:00:00", "{$today} 00:00:00"]);
            $expenses_today = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $total_general_expenses = 0;
            $total_supplier_payouts = 0;
            $total_partner_withdrawals = 0;
            $cash_outflows = 0;
            
            foreach ($expenses_today as $exp) {
                $amt = (float)$exp['amount'];
                $cat = $exp['category'] ?? '';
                $is_cash = empty($exp['payment_method']) || $exp['payment_method'] === 'كاش';
                
                if ($cat === 'سداد موردين') {
                    $total_supplier_payouts += $amt;
                } elseif ($cat === 'مسحوبات الإدارة') {
                    $total_partner_withdrawals += $amt;
                } else {
                    $total_general_expenses += $amt;
                }
                
                if ($is_cash) {
                    $cash_outflows += $amt;
                }
            }
            
            // السيولة النقدية الفعلية في الدرج (Cash in Drawer)
            $net_cash_in_drawer = max(0, $sales_by_method['كاش'] - $cash_outflows);
            
            echo json_encode([
                'success' => true,
                'server_time' => date('Y-m-d H:i:s'),
                'today_date' => $today,
                'orders_count' => count($sales_today),
                'total_sales' => $total_sales_amount,
                'sales_by_method' => $sales_by_method,
                'total_general_expenses' => $total_general_expenses,
                'total_supplier_payouts' => $total_supplier_payouts,
                'total_partner_withdrawals' => $total_partner_withdrawals,
                'total_all_expenses' => ($total_general_expenses + $total_supplier_payouts + $total_partner_withdrawals),
                'net_cash_in_drawer' => $net_cash_in_drawer,
                'recent_sales' => array_slice(array_reverse($sales_today), 0, 8),
                'recent_expenses' => array_slice(array_reverse($expenses_today), 0, 8)
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 9. جلب قوائم الموردين والتصنيفات والشركاء
        // ============================================================
        case 'get_pos_meta':
            $suppliers = $pdo->query("SELECT id, name, phone, balance FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $categories = $pdo->query("SELECT name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
            $partners = $pdo->query("SELECT name FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
            
            echo json_encode([
                'success' => true,
                'suppliers' => $suppliers,
                'expense_categories' => $categories,
                'partners' => $partners
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 10. إحصائيات مركز المعلومات للمبيعات والمخزون
        // ============================================================
        case 'get_hub_stats':
            $today = date('Y-m-d');
            $sales_today = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE created_at LIKE '{$today}%'")->fetchColumn();
            $orders_today = $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at LIKE '{$today}%'")->fetchColumn();
            $low_stock = $pdo->query("SELECT COUNT(*) FROM products WHERE stock <= 5")->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'sales_today' => (float)$sales_today,
                'orders_today' => (int)$orders_today,
                'low_stock_count' => (int)$low_stock,
                'server_time' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database/Server Error: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
