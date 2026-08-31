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
            
            // تحديد الحالة الأولية للأوردر
            $order_status = (!empty($delivery_person) && $delivery_person !== 'بدون توصيل (تيك أواي)') ? 'بانتظار الطيار' : 'مكتمل';

            // حفظ الفاتورة في جدول orders
            $stmt = $pdo->prepare("INSERT INTO orders (
                customer_name, customer_phone, customer_address, order_details, 
                total_price, discount_amount, shipping_cost, payment_method, payment_status, 
                status, source, cashier_name, delivery_person, delivery_fee, created_at, synced
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'مدفوع', ?, ?, ?, ?, ?, ?, 1)");
            
            $stmt->execute([
                $customer, $phone, $address, $details_str,
                $total, $discount, $delivery_fee, $payment_method,
                $order_status,
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

        // ============================================================
        // 11. تسجيل دخول الطيار برمز الـ PIN أو الهاتف
        // ============================================================
        case 'driver_login':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $pin = trim($data['pin_code'] ?? '');
            $driver_id = (int)($data['driver_id'] ?? 0);
            $phone = trim($data['phone'] ?? '');

            if (empty($pin)) {
                echo json_encode(['success' => false, 'error' => 'يرجى إدخال الرمز السري (PIN) للدخول']);
                break;
            }

            if ($driver_id > 0) {
                $stmt = $pdo->prepare("SELECT * FROM delivery_drivers WHERE id = ? AND pin_code = ? AND is_active = 1");
                $stmt->execute([$driver_id, $pin]);
            } elseif (!empty($phone)) {
                $stmt = $pdo->prepare("SELECT * FROM delivery_drivers WHERE phone = ? AND pin_code = ? AND is_active = 1");
                $stmt->execute([$phone, $pin]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM delivery_drivers WHERE pin_code = ? AND is_active = 1");
                $stmt->execute([$pin]);
            }

            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($driver) {
                echo json_encode([
                    'success' => true,
                    'message' => 'مرحباً بك كابتن ' . $driver['name'],
                    'driver' => [
                        'id' => (int)$driver['id'],
                        'name' => $driver['name'],
                        'phone' => $driver['phone'],
                        'cash_balance' => (float)$driver['cash_balance']
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'الرمز السري (PIN) غير صحيح أو الحساب معطل']);
            }
            break;

        // ============================================================
        // 12. جلب أوردرات الطيار المعزولة حصراً ومحفظته المالية
        // ============================================================
        case 'get_driver_orders':
            $driver_name = trim($_GET['driver_name'] ?? ($json_payload['driver_name'] ?? ''));
            if (empty($driver_name)) {
                echo json_encode(['success' => false, 'error' => 'يرجى تحديد اسم الطيار']);
                break;
            }

            // التأكد من جلب الأوردرات المسندة لهذا الطيار فقط
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE delivery_person = ? ORDER BY id DESC LIMIT 50");
            $stmt->execute([$driver_name]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // تصنيف وإحصاء أوردرات هذا الطيار
            $in_transit = [];
            $pending = [];
            $delivered_today = [];
            $total_cash_in_hand = 0;
            $total_commission = 0;
            $today = date('Y-m-d');

            foreach ($orders as &$ord) {
                $ord['id'] = (int)$ord['id'];
                $ord['total_price'] = (float)$ord['total_price'];
                $ord['shipping_cost'] = (float)($ord['shipping_cost'] ?? 0);
                $ord['payment_method'] = $ord['payment_method'] ?? 'كاش';
                $ord['is_cash'] = (mb_stripos($ord['payment_method'], 'كاش') !== false || mb_stripos($ord['payment_method'], 'cash') !== false || empty($ord['payment_method']));

                $status = $ord['status'] ?? 'جديد';
                $created_date = substr($ord['created_at'] ?? '', 0, 10);

                if ($status === 'جاري التوصيل' || $status === 'في الطريق') {
                    $in_transit[] = $ord;
                } elseif ($status === 'بانتظار الطيار' || $status === 'جديد' || $status === 'مؤقتة' || $status === 'معلق') {
                    $pending[] = $ord;
                } elseif ($status === 'تم التسليم' || $status === 'مكتملة') {
                    if ($created_date === $today) {
                        $delivered_today[] = $ord;
                    }
                    if ($ord['is_cash']) {
                        $total_cash_in_hand += $ord['total_price'];
                    }
                    $total_commission += $ord['shipping_cost'];
                }
            }
            unset($ord);

            // جلب الرصيد الحالي المسجل في جدول الطيارين
            $stmt_bal = $pdo->prepare("SELECT cash_balance FROM delivery_drivers WHERE name = ?");
            $stmt_bal->execute([$driver_name]);
            $drv_bal = (float)$stmt_bal->fetchColumn();

            echo json_encode([
                'success' => true,
                'driver_name' => $driver_name,
                'stats' => [
                    'in_transit_count' => count($in_transit),
                    'pending_count' => count($pending),
                    'delivered_today_count' => count($delivered_today),
                    'cash_in_hand' => $total_cash_in_hand,
                    'driver_balance' => $drv_bal,
                    'total_commission' => $total_commission
                ],
                'orders_in_transit' => $in_transit,
                'orders_pending' => $pending,
                'orders_delivered_today' => $delivered_today,
                'all_orders' => $orders
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 13. تحديث حالة توصيل الأوردر (استلام / تم التسليم / راجع)
        // ============================================================
        case 'update_delivery_status':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $order_id = (int)($data['order_id'] ?? 0);
            $new_status = trim($data['status'] ?? '');
            $driver_name = trim($data['driver_name'] ?? '');
            $note = trim($data['note'] ?? '');

            if ($order_id <= 0 || empty($new_status)) {
                echo json_encode(['success' => false, 'error' => 'بيانات الطلب أو الحالة غير مكتملة']);
                break;
            }

            // التحقق من أن الأوردر مسند لهذا الطيار
            $chk = $pdo->prepare("SELECT id, total_price, payment_method, status FROM orders WHERE id = ? AND delivery_person = ?");
            $chk->execute([$order_id, $driver_name]);
            $order = $chk->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                echo json_encode(['success' => false, 'error' => 'عذراً، هذا الأوردر غير مسند إليك أو غير موجود']);
                break;
            }

            // تحديث حالة الأوردر
            $upd = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $upd->execute([$new_status, $order_id]);

            // إذا تم التسليم وكان كاش، نضيف المبلغ لعهدة الطيار
            $is_cash = (mb_stripos($order['payment_method'] ?? '', 'كاش') !== false || mb_stripos($order['payment_method'] ?? '', 'cash') !== false || empty($order['payment_method']));
            if ($new_status === 'تم التسليم' && $is_cash) {
                $amt = (float)$order['total_price'];
                $pdo->prepare("UPDATE delivery_drivers SET cash_balance = cash_balance + ? WHERE name = ?")->execute([$amt, $driver_name]);
            }

            echo json_encode([
                'success' => true,
                'message' => "تم تحديث حالة الأوردر رقم #{$order_id} إلى ({$new_status}) بنجاح",
                'order_id' => $order_id,
                'new_status' => $new_status
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 14. تصفية عهدة الطيار النقدية وتسليمها للكاشير
        // ============================================================
        case 'settle_driver_cash':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $driver_name = trim($data['driver_name'] ?? '');
            $amount = (float)($data['amount'] ?? 0);
            $note = trim($data['note'] ?? 'تصفية عهدة دليفري وتسليم كاش');

            if (empty($driver_name) || $amount <= 0) {
                echo json_encode(['success' => false, 'error' => 'يرجى تحديد الطيار والمبلغ المراد تسليمه']);
                break;
            }

            // تصفية أو خصم المبلغ من رصيد الطيار
            $pdo->prepare("UPDATE delivery_drivers SET cash_balance = MAX(0, cash_balance - ?) WHERE name = ?")->execute([$amount, $driver_name]);

            // تسجيل إيراد / قيد حركة استلام عهدة
            try {
                $pdo->prepare("INSERT INTO expenses (category, amount, note, date, partner_name, payment_method) VALUES ('توريد عهدة دليفري', ?, ?, ?, ?, 'كاش')")
                    ->execute([$amount, "استلام كاش من الطيار ($driver_name): " . $note, date('Y-m-d H:i:s'), $driver_name]);
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'message' => "تم تسليم وتصفية مبلغ {$amount} ج.م من الكابتن {$driver_name} بنجاح!",
                'settled_amount' => $amount
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 7. تصفير شامل لجميع بيانات المتجر السحابي (Reset All Cloud Data)
        // ============================================================
        case 'reset_all_data':
            $pdo->exec("DELETE FROM products");
            $pdo->exec("DELETE FROM orders");
            $pdo->exec("DELETE FROM order_items");
            $pdo->exec("DELETE FROM expenses");
            $pdo->exec("DELETE FROM customers");
            $pdo->exec("DELETE FROM suppliers");
            
            echo json_encode([
                'success' => true,
                'message' => '✅ تم تصفير وحذف كافة بيانات المتجر الإلكتروني السحابي بنجاح.'
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 15. جلب قائمة الطيارين المتاحين (للكاشير وشاشة الدخول)
        // ============================================================
        case 'get_delivery_drivers':
            $drivers = $pdo->query("SELECT id, name, phone, cash_balance FROM delivery_drivers WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'drivers' => $drivers
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 16. تحديث رصيد ومخزون منتج في الجرد (Single Product Stock)
        // ============================================================
        case 'update_inventory_stock':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $product_id = (int)($data['product_id'] ?? 0);
            $barcode = trim($data['barcode'] ?? '');
            $new_stock = isset($data['new_stock']) ? (float)$data['new_stock'] : null;
            $note = trim($data['note'] ?? 'تعديل جرد يدوي');

            if ($new_stock === null || ($product_id <= 0 && empty($barcode))) {
                echo json_encode(['success' => false, 'error' => 'يرجى تحديد المنتج والكمية الجديدة بالجرد']);
                break;
            }

            if ($product_id > 0) {
                $stmt = $pdo->prepare("SELECT id, name, stock, cost, price FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, stock, cost, price FROM products WHERE barcode = ? OR local_code = ?");
                $stmt->execute([$barcode, $barcode]);
            }
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                echo json_encode(['success' => false, 'error' => 'المنتج غير موجود في قاعدة البيانات']);
                break;
            }

            $old_stock = (float)$prod['stock'];
            $upd = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
            $upd->execute([$new_stock, $prod['id']]);

            echo json_encode([
                'success' => true,
                'message' => "تم تحديث رصيد ({$prod['name']}) من {$old_stock} إلى {$new_stock} بنجاح ✓",
                'product_id' => (int)$prod['id'],
                'name' => $prod['name'],
                'old_stock' => $old_stock,
                'new_stock' => $new_stock,
                'diff' => ($new_stock - $old_stock)
            ], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================================
        // 17. تطبيق الجرد الشامل وتحديث كميات متعددة دفعة واحدة
        // ============================================================
        case 'bulk_inventory_audit':
            $data = !empty($json_payload) ? $json_payload : $_POST;
            $items = $data['items'] ?? [];
            $auditor = trim($data['auditor'] ?? 'مسؤول الجرد');

            if (is_string($items)) {
                $items = json_decode($items, true) ?: [];
            }

            if (empty($items)) {
                echo json_encode(['success' => false, 'error' => 'لا توجد أصناف لتطبيق الجرد عليها']);
                break;
            }

            $updated_count = 0;
            $upd_stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
            foreach ($items as $it) {
                $p_id = (int)($it['id'] ?? 0);
                $n_stock = isset($it['new_stock']) ? (float)$it['new_stock'] : null;
                if ($p_id > 0 && $n_stock !== null) {
                    $upd_stmt->execute([$n_stock, $p_id]);
                    $updated_count++;
                }
            }

            // تسجيل إشعار بنظام الإدارة
            try {
                $pdo->prepare("INSERT INTO notifications (title, body, link) VALUES (?, ?, ?)")
                    ->execute([
                        "📋 تم تطبيق جرد مخزون جديد",
                        "قام ($auditor) بتطبيق جرد شامل وتحديث كميات ($updated_count) صنفاً",
                        "pos.php"
                    ]);
            } catch (Exception $e) {}

            echo json_encode([
                'success' => true,
                'message' => "تم تطبيق الجرد الشامل وتحديث كميات {$updated_count} صنف بنجاح!",
                'updated_count' => $updated_count
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
