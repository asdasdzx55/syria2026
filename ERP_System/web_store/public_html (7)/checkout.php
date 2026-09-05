<?php
require_once 'config.php';

// إذا كانت السلة فارغة، أرجع للمتجر
if (empty($_SESSION['cart'])) {
    header("Location: shop.php");
    exit;
}

// جلب تفاصيل المستخدم بالكامل إذا كان مسجلاً الدخول
$logged_email = '';
$logged_phone = '';
$logged_street = '';
$logged_building = '';
$logged_floor = '';
$logged_apartment = '';
$logged_landmark = '';
$logged_gov_id = '';

if (isset($_SESSION['user_id'])) {
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $u_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($u_info) {
        $logged_email = $u_info['email'] ?: '';
        $logged_phone = $u_info['phone'] ?: '';
        $logged_street = $u_info['street'] ?: '';
        $logged_building = $u_info['building'] ?: '';
        $logged_floor = $u_info['floor'] ?: '';
        $logged_apartment = $u_info['apartment'] ?: '';
        $logged_landmark = $u_info['landmark'] ?: '';
        $logged_gov_id = $u_info['gov_id'] ?: '';
    }
}

// جلب مناطق الشحن لمحافظات مصر
$egypt_zones = $pdo->query("SELECT * FROM shipping_zones WHERE country_name = 'مصر' OR country_name IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

$subtotal_cart = 0;
foreach($_SESSION['cart'] as $id => $item) {
    $subtotal_cart += ($item['price'] * $item['qty']);
}
$discount_val = $_SESSION['coupon'] ? ($subtotal_cart * ($_SESSION['coupon']['discount_percent'] / 100)) : 0;
$final_total_before_shipping = $subtotal_cart - $discount_val;

// معالجة تأكيد الطلب وإرساله لقاعدة البيانات
if (isset($_POST['submit_order'])) {
    $name = trim($_POST['c_name']);
    $phone = trim($_POST['c_phone']);
    $email = isset($_POST['c_email']) ? trim($_POST['c_email']) : '';
    $country = 'مصر';
    $currency = 'ج.م';
    
    // بناء العنوان بالتفصيل من الخانات المنفصلة
    $street = trim($_POST['addr_street']);
    $building = trim($_POST['addr_building']);
    $floor = isset($_POST['addr_floor']) ? trim($_POST['addr_floor']) : '';
    $apartment = trim($_POST['addr_apartment']);
    $landmark = isset($_POST['addr_landmark']) ? trim($_POST['addr_landmark']) : '';
    
    $address = "الدولة: " . $country . " | الشارع/المنطقة: " . $street;
    $address .= " | عمارة: " . $building;
    if (!empty($floor)) {
        $address .= " | دور: " . $floor;
    }
    $address .= " | شقة: " . $apartment;
    if (!empty($landmark)) {
        $address .= " | علامة مميزة: " . $landmark;
    }
    
    $gov_id = (int)$_POST['c_gov'];

    $stmt_gov = $pdo->prepare("SELECT gov_name, cost, is_active, currency_symbol FROM shipping_zones WHERE id = ?");
    $stmt_gov->execute([$gov_id]);
    $gov_data = $stmt_gov->fetch(PDO::FETCH_ASSOC);

    if (!$gov_data || $gov_data['is_active'] == 0) {
        die("<div style='direction:rtl; text-align:center; padding:50px; font-family:tahoma;'><h2>عذراً، الشحن والتوصيل غير متاح للمحافظة المختارة حالياً.</h2><a href='checkout.php'>العودة للخلف</a></div>");
    }

    $gov_name = $gov_data['gov_name'];
    $shipping_cost = (float)$gov_data['cost'];
    $shipping_type = isset($_POST['shipping_type']) ? trim($_POST['shipping_type']) : 'flat';
    $delivery_lat = isset($_POST['delivery_lat']) && !empty($_POST['delivery_lat']) ? trim($_POST['delivery_lat']) : null;
    $delivery_lng = isset($_POST['delivery_lng']) && !empty($_POST['delivery_lng']) ? trim($_POST['delivery_lng']) : null;
    $delivery_distance_km = isset($_POST['delivery_distance_km']) && !empty($_POST['delivery_distance_km']) ? (float)$_POST['delivery_distance_km'] : null;

    // حساب الشحن الذكي بالكيلومتر لمناطق القاهرة والجيزة وأكتوبر أو عند تحديد الموقع بالخريطة
    $enable_km = ($settings['enable_km_shipping'] ?? '1') === '1';
    $store_lat = (float)($settings['store_lat'] ?? 30.066576);
    $store_lng = (float)($settings['store_lng'] ?? 31.332781);
    $km_rate = (float)($settings['km_rate'] ?? 2.00);
    $km_base_min = (float)($settings['km_base_min_price'] ?? 25.00);
    
    $km_govs_raw = !empty($settings['km_shipping_govs']) ? json_decode($settings['km_shipping_govs'], true) : null;
    if (!is_array($km_govs_raw) || empty($km_govs_raw)) {
        $km_govs_raw = ['القاهرة', 'الجيزة', '6 أكتوبر', 'الشيخ زايد', 'القليوبية'];
    }

    $is_km_eligible = false;
    if ($enable_km && !empty($delivery_lat) && !empty($delivery_lng)) {
        if ($shipping_type === 'km') {
            $is_km_eligible = true;
        } else {
            foreach ($km_govs_raw as $kg) {
                if (mb_strpos($gov_name, $kg) !== false || mb_strpos($kg, $gov_name) !== false) {
                    $is_km_eligible = true;
                    break;
                }
            }
        }
    }

    if ($is_km_eligible) {
        // معادلة هافرسين لحساب المسافة الدقيقة بين المحل والعميل
        $earthRadius = 6371; // km
        $dLat = deg2rad((float)$delivery_lat - $store_lat);
        $dLng = deg2rad((float)$delivery_lng - $store_lng);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($store_lat)) * cos(deg2rad((float)$delivery_lat)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $dist_calc = round($earthRadius * $c, 1);
        $delivery_distance_km = $dist_calc;

        // إذا كانت المسافة ضمن النطاق المحلي (أقل من 75 كم)
        if ($dist_calc <= 75.0) {
            if ($dist_calc <= 1.0) {
                $shipping_cost = $km_base_min;
            } else {
                $extra_dist = $dist_calc - 1.0;
                $shipping_cost = $km_base_min + round($extra_dist * $km_rate);
            }
            $shipping_type = 'km';
        } else {
            // مسافة بعيدة خارج النطاق، اعتماد سعر المحافظة المباشر
            $shipping_type = 'flat';
        }
    }

    // تفاصيل المنتجات في الطلب بالفاتورة
    $details = "";
    foreach ($_SESSION['cart'] as $item) {
        $extra_info = [];
        if (!empty($item['weight_label'])) {
            $extra_info[] = "الوزن: " . $item['weight_label'];
        }
        if (!empty($item['variant_summary'])) {
            $extra_info[] = "المواصفات: " . $item['variant_summary'];
        }
        $extra_str = !empty($extra_info) ? " (" . implode(' - ', $extra_info) . ")" : "";
        $details .= "• " . $item['name'] . $extra_str . " | الكمية: " . $item['qty'] . " | السعر: " . $item['price'] . " " . $currency . "\n";
    }

    $coupon_code = $_SESSION['coupon'] ? $_SESSION['coupon']['code'] : null;
    $total_price = $final_total_before_shipping + $shipping_cost;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // معالجة وسيلة الدفع وصورة الإيصال
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'الدفع عند الاستلام';
    $payment_status = 'غير مدفوع';
    $transaction_ref = isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : null;
    $receipt_image = null;

    if (in_array($payment_method, ['فودافون كاش / المحافظ', 'تحويل انستا باي (InstaPay)'])) {
        $payment_status = 'قيد التحقق والمراجعة';
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === 0) {
            $receipt_image = uploadImage($_FILES['receipt_file']);
        }
        if (empty($receipt_image)) {
            die("<div style='direction:rtl; text-align:center; padding:50px; font-family:tahoma;'><h2>عذراً، يجب رفع صورة إيصال التحويل لإتمام الطلب بهذه الوسيلة.</h2><a href='javascript:history.back()'>العودة وتعديل الطلب</a></div>");
        }
    }

    $pdo->prepare("INSERT INTO orders (customer_name, customer_phone, customer_email, country, governorate, currency, customer_address, order_details, total_price, discount_amount, shipping_cost, coupon_code, user_id, payment_method, payment_status, receipt_image, transaction_ref, delivery_lat, delivery_lng, delivery_distance_km, shipping_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$name, $phone, $email, $country, $gov_name, $currency, $address, $details, $total_price, $discount_val, $shipping_cost, $coupon_code, $user_id, $payment_method, $payment_status, $receipt_image, $transaction_ref, $delivery_lat, $delivery_lng, $delivery_distance_km, $shipping_type]);

    $order_id = $pdo->lastInsertId();

    // حفظ وتحديث بيانات العميل في جدول العملاء المركزي (للكاشير والويب)
    try {
        $chk_cust = $pdo->prepare("SELECT id, total_orders, total_spent FROM customers WHERE phone = ? LIMIT 1");
        $chk_cust->execute([$phone]);
        $existing_cust = $chk_cust->fetch(PDO::FETCH_ASSOC);

        if ($existing_cust) {
            $new_count = (int)$existing_cust['total_orders'] + 1;
            $new_spent = (float)$existing_cust['total_spent'] + (float)$total_price;
            $pdo->prepare("UPDATE customers SET name = ?, address = ?, governorate = ?, email = COALESCE(NULLIF(?, ''), email), delivery_lat = COALESCE(NULLIF(?, ''), delivery_lat), delivery_lng = COALESCE(NULLIF(?, ''), delivery_lng), delivery_distance_km = COALESCE(?, delivery_distance_km), total_orders = ?, total_spent = ?, last_order_date = CURRENT_TIMESTAMP WHERE id = ?")
                ->execute([$name, $address, $gov_name, $email, $delivery_lat, $delivery_lng, $delivery_distance_km, $new_count, $new_spent, $existing_cust['id']]);
        } else {
            $pdo->prepare("INSERT INTO customers (name, phone, address, governorate, email, delivery_lat, delivery_lng, delivery_distance_km, total_orders, total_spent, last_order_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, CURRENT_TIMESTAMP)")
                ->execute([$name, $phone, $address, $gov_name, $email, $delivery_lat, $delivery_lng, $delivery_distance_km, $total_price]);
        }
    } catch (Exception $e_cust) {}

    // إرسال إشعار فوري لتطبيق الأندرويد الخاص بالمدير
    try {
        $notif_title = "طلب جديد وارد! 📦 #" . $order_id;
        $notif_body = "العميل: " . $name . " | القيمة: " . $total_price . " " . $currency . " | الدولة: " . $country . " (" . $gov_name . ")";
        $notif_link = "admin_orders.php";
        
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (title, body, link) VALUES (?, ?, ?)");
        $stmt_notif->execute([$notif_title, $notif_body, $notif_link]);
    } catch (Exception $e) {
        // تفادي أي مشاكل
    }

    // إرسال إشعار فوري لصاحب المتجر عبر التلجرام
    $telegram_token = '8914669961:AAGYxT2-DydCm4ZjlddBZ_E6LheJSeiLhEs';
    $telegram_chat_id = '6746619336';
    
    $store_n = $settings['store_name'] ?? 'المتجر الإلكتروني';
    $curr_s = $currency;
    $message = "🔔 <b>طلب جديد من متجر {$store_n}</b> 🔔\n\n";
    $message .= "👤 <b>اسم العميل:</b> " . htmlspecialchars($name) . "\n";
    $message .= "📞 <b>رقم الهاتف:</b> " . htmlspecialchars($phone) . "\n";
    $message .= "🌍 <b>الدولة والمحافظة:</b> " . htmlspecialchars($country) . " - " . htmlspecialchars($gov_name) . "\n";
    $message .= "🏠 <b>العنوان:</b> " . htmlspecialchars($address) . "\n\n";
    $message .= "🛍️ <b>تفاصيل المنتجات:</b>\n" . htmlspecialchars($details) . "\n";
    if ($coupon_code) {
        $message .= "🏷️ <b>كوبون الخصم:</b> " . htmlspecialchars($coupon_code) . " (خصم: " . $discount_val . " " . $curr_s . ")\n";
    }
    if ($delivery_distance_km) {
        $message .= "📍 <b>المسافة من المحل:</b> " . $delivery_distance_km . " كم\n";
    }
    if ($delivery_lat && $delivery_lng) {
        $message .= "🗺️ <b>لوكيشن العميل:</b> https://www.google.com/maps?q=" . $delivery_lat . "," . $delivery_lng . "\n";
    }
    $message .= "🚚 <b>تكلفة الشحن:</b> " . $shipping_cost . " " . $curr_s . "\n";
    $message .= "💰 <b>الإجمالي النهائي:</b> " . $total_price . " " . $curr_s . "\n\n";
    $message .= "💳 <b>طريقة الدفع:</b> " . htmlspecialchars($payment_method) . "\n";
    $message .= "📊 <b>حالة الدفع:</b> " . htmlspecialchars($payment_status) . "\n";
    if ($transaction_ref) {
        $message .= "🔖 <b>رقم العملية/المرجع:</b> " . htmlspecialchars($transaction_ref) . "\n";
    }
    
    $url = "https://api.telegram.org/bot" . $telegram_token . "/sendMessage";
    $post_params = [
        'chat_id' => $telegram_chat_id,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($post_params),
            'timeout' => 5
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($options);
    @file_get_contents($url, false, $context);

    // إرسال الفاتورة بالبريد الإلكتروني للعميل
    if (!empty($email)) {
        sendInvoiceEmail($email, $name, $details, $subtotal_cart, $discount_val, $shipping_cost, $total_price, $coupon_code, $address, $gov_name, $phone);
    }

    // تحويل حالة السلة المتروكة إلى طلب مكتمل
    if (function_exists('markAbandonedCartConverted')) {
        markAbandonedCartConverted();
    }

    // تفريغ السلة والكوبون
    $_SESSION['cart'] = [];
    $_SESSION['coupon'] = null;
    $_SESSION['last_order_id'] = $pdo->lastInsertId();

    header("Location: checkout_success.php");
    exit;
}

include 'header.php';
?>

<!-- Meta Pixel InitiateCheckout Event -->
<?php if(!empty($meta_pixel_id) && $meta_pixel_enabled): ?>
<script>
  if (typeof fbq !== 'undefined') {
    fbq('track', 'InitiateCheckout', {
      value: <?php echo (float)$final_total_before_shipping; ?>,
      currency: 'EGP',
      num_items: <?php echo count($_SESSION['cart']); ?>
    });
  }
</script>
<?php endif; ?>

<!-- مكتبة الخرائط المفتوحة Leaflet.js -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="container mx-auto px-4 md:px-8 py-16 max-w-4xl animate-fade-in">
    <div class="text-center mb-10">
        <h2 class="text-3xl font-serif text-royal-dark font-bold mb-2">تأكيد الطلب والدفع</h2>
        <p class="text-xs text-gray-500 font-light">الرجاء إدخال بيانات التوصيل بدقة لنتمكن من شحن طلبكِ في أسرع وقت.</p>
    </div>
    
    <div class="flex flex-col md:flex-row gap-10">
        <!-- نموذج التوصيل -->
        <div class="md:w-2/3">
            <form method="POST" action="checkout.php" enctype="multipart/form-data" class="space-y-5 bg-white p-8 border border-royal-gold/10 shadow-sm rounded-2xl">
                <h3 class="font-serif font-bold text-base border-b pb-2 mb-4 text-royal-dark">عنوان الشحن والاتصال</h3>
                
                <div>
                    <input type="text" name="c_name" required placeholder="اسم المستلم بالكامل *" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                </div>
                <div>
                    <input type="text" name="c_phone" required placeholder="رقم الهاتف (الواتس اب) *" value="<?php echo htmlspecialchars($logged_phone); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm" style="text-align: right;" dir="ltr">
                </div>
                <div>
                    <input type="email" name="c_email" required placeholder="البريد الإلكتروني لتلقي الفاتورة *" value="<?php echo htmlspecialchars($logged_email); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                </div>

                <!-- الدولة والعملة (مصر - ج.م) -->
                <input type="hidden" name="c_country" value="مصر">
                <input type="hidden" name="c_currency" value="ج.م">
                
                <!-- حقول إحداثيات ونوع الشحن والمسافة بالكيلومتر -->
                <input type="hidden" name="delivery_lat" id="delivery_lat" value="">
                <input type="hidden" name="delivery_lng" id="delivery_lng" value="">
                <input type="hidden" name="delivery_distance_km" id="delivery_distance_km" value="">
                <input type="hidden" name="shipping_type" id="shipping_type" value="flat">

                <!-- اختيار المحافظة / المدينة داخل مصر -->
                <div>
                    <label class="block text-xs font-bold mb-1.5 text-royal-dark flex items-center gap-1.5">
                        <i class="fa-solid fa-map-pin text-royal-darkgold"></i> المحافظة / المدينة (مصر) *
                    </label>
                    <select name="c_gov" id="gov-select" required class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition text-gray-700 rounded-xl text-sm font-medium">
                        <option value="" disabled <?php echo empty($logged_gov_id) ? 'selected' : ''; ?>>اختر المحافظة للتوصيل *</option>
                        <?php foreach($egypt_zones as $z): 
                            $is_sel = ($logged_gov_id && (int)$logged_gov_id === (int)$z['id']);
                        ?>
                            <option value="<?php echo $z['id']; ?>" data-name="<?php echo htmlspecialchars($z['gov_name']); ?>" data-cost="<?php echo $z['cost']; ?>" data-active="<?php echo $z['is_active']; ?>" data-currency="ج.م" <?php echo $is_sel ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($z['gov_name']) . ($z['is_active'] ? ' (' . $z['cost'] . ' ج.م)' : ' (غير متاح حالياً)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="gov-error" class="hidden text-red-600 text-xs mt-3.5 p-3.5 bg-red-50 border border-red-200 rounded-xl font-bold">
                        <i class="fa-solid fa-circle-exclamation mr-1 text-sm"></i> عذراً، التوصيل والشحن لهذه المحافظة غير متاح حالياً.
                    </div>
                </div>

                <!-- أزرار تحديد الموقع التلقائي والخريطة -->
                <div class="mb-4 space-y-2">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <button type="button" id="btn-quick-gps" class="flex-1 bg-royal-dark text-white hover:bg-royal-gold hover:text-royal-charcoal py-3 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-md btn-shine">
                            <i class="fa-solid fa-location-crosshairs text-royal-gold text-base"></i> تحديد عنواني وموقعي الحالي تلقائياً (GPS)
                        </button>
                        <button type="button" id="btn-open-map" class="flex-1 bg-royal-sand/60 hover:bg-royal-gold/15 text-royal-darkgold border border-royal-gold/20 py-3 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                            <i class="fa-solid fa-map-location-dot text-base"></i> تحديد موقعي على الخريطة يدوياً
                        </button>
                    </div>

                    <!-- شريط إشعار حالة جلب الـ GPS والعنوان -->
                    <div id="gps-status-box" class="hidden p-3 rounded-xl text-xs font-bold transition-all"></div>

                    <!-- حاوية الخريطة -->
                    <div id="map-container" class="hidden mt-2 border border-royal-gold/20 rounded-xl overflow-hidden shadow-inner relative z-30">
                        <!-- شريط البحث داخل الخريطة -->
                        <div class="p-2.5 bg-royal-sand/50 border-b border-royal-gold/15 flex gap-2">
                            <input type="text" id="map-search-input" placeholder="ابحث عن منطقتكِ أو شارعكِ هنا (مثال: المعادي، الدقي، مدينة نصر)..." class="flex-grow p-2.5 border border-gray-200 rounded-lg text-xs outline-none focus:border-royal-gold bg-white font-medium">
                            <button type="button" id="btn-map-search" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-4 py-2 rounded-lg text-xs font-bold transition">بحث</button>
                        </div>
                        <div id="checkout-map" class="h-64 w-full"></div>
                        <div class="bg-royal-charcoal text-royal-gold p-3 text-[10px] text-center font-bold">
                            <i class="fa-solid fa-info-circle"></i> يمكنكِ سحب الدبوس أو النقر في أي مكان على الخريطة، وسيتم استخراج اسم الشارع والمنطقة تلقائياً!
                        </div>
                    </div>
                </div>

                <!-- تفاصيل العنوان المجزأة -->
                <div class="space-y-4">
                    <div class="relative">
                        <input type="text" name="addr_street" id="addr_street" required placeholder="اسم الشارع / المنطقة / الميدان *" value="<?php echo htmlspecialchars($logged_street); ?>" class="w-full p-4 pl-24 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-medium">
                        <button type="button" id="btn-input-gps" title="جلب عنواني الحالي عبر GPS" class="absolute left-2.5 top-2.5 bg-royal-sand hover:bg-royal-gold text-royal-dark hover:text-white px-2.5 py-1.5 rounded-lg text-[10px] font-bold transition flex items-center gap-1 border border-royal-gold/20 shadow-sm">
                            <i class="fa-solid fa-location-arrow text-royal-darkgold"></i> موقعي 📍
                        </button>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-1">
                            <input type="text" name="addr_building" id="addr_building" required placeholder="رقم العمارة *" value="<?php echo htmlspecialchars($logged_building); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-medium">
                        </div>
                        <div class="col-span-1">
                            <input type="text" name="addr_floor" id="addr_floor" placeholder="رقم الدور" value="<?php echo htmlspecialchars($logged_floor); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-medium">
                        </div>
                        <div class="col-span-1">
                            <input type="text" name="addr_apartment" id="addr_apartment" required placeholder="رقم الشقة *" value="<?php echo htmlspecialchars($logged_apartment); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-medium">
                        </div>
                    </div>
                    <div>
                        <input type="text" name="addr_landmark" id="addr_landmark" placeholder="أقرب علامة مميزة (مثال: بجوار صيدلية...) (اختياري)" value="<?php echo htmlspecialchars($logged_landmark); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-medium">
                    </div>
                </div>
                
                <!-- اختيار طريقة الدفع -->
                <div class="space-y-4 pt-4 border-t border-gray-100">
                    <label class="block text-xs font-bold mb-1 text-royal-dark">اختيار طريقة الدفع المتاحة *</label>
                    <div class="grid grid-cols-1 gap-3.5">
                        
                        <!-- 1. الدفع عند الاستلام (كاش) -->
                        <?php if (($settings['cod_enabled'] ?? '1') === '1'): ?>
                        <label class="payment-option-card border-2 border-royal-gold bg-royal-cream/20 p-4 rounded-xl flex items-center justify-between cursor-pointer transition shadow-sm">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="الدفع عند الاستلام" checked onchange="togglePaymentBox('cod')" class="text-royal-darkgold focus:ring-royal-gold w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-royal-dark">الدفع عند الاستلام (كاش)</h4>
                                    <p class="text-[10px] text-gray-400 font-light mt-0.5">الدفع نقداً لمندوب الشحن والتوصيل فور استلام الشحنة.</p>
                                </div>
                            </div>
                            <i class="fa-solid fa-money-bill-wave text-royal-gold text-lg"></i>
                        </label>
                        <?php endif; ?>
                        
                        <!-- 2. فودافون كاش / المحافظ الإلكترونية -->
                        <?php if (($settings['vodafone_cash_enabled'] ?? '0') === '1' && !empty($settings['vodafone_cash_number'])): ?>
                        <div class="payment-option-card border border-gray-200 hover:border-royal-gold p-4 rounded-xl flex items-center justify-between transition">
                            <label class="flex items-center gap-3 cursor-pointer flex-grow">
                                <input type="radio" name="payment_method" value="فودافون كاش / المحافظ" onchange="togglePaymentBox('vcash')" class="text-royal-darkgold focus:ring-royal-gold w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-royal-dark flex items-center gap-2">
                                        تحويل فودافون كاش / المحافظ الإلكترونية
                                        <span class="bg-red-100 text-red-700 text-[9px] px-2 py-0.5 rounded-full font-bold">إجباري رفع إيصال</span>
                                    </h4>
                                    <p class="text-[10px] text-gray-400 font-light mt-0.5">التحويل على محفظة: <strong id="val-vcash" class="font-mono text-royal-dark font-bold" dir="ltr"><?php echo htmlspecialchars($settings['vodafone_cash_number']); ?></strong></p>
                                </div>
                            </label>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['vodafone_cash_number']); ?>', this)" class="bg-white border border-gray-200 hover:border-royal-gold text-royal-dark text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                                    <i class="fa-regular fa-copy text-royal-darkgold"></i> نسخ الرقم
                                </button>
                                <i class="fa-solid fa-mobile-screen text-red-600 text-lg"></i>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- 3. تحويل انستا باي (InstaPay) -->
                        <?php if (($settings['instapay_enabled'] ?? '0') === '1' && !empty($settings['instapay_address'])): ?>
                        <div class="payment-option-card border border-gray-200 hover:border-royal-gold p-4 rounded-xl flex items-center justify-between transition">
                            <label class="flex items-center gap-3 cursor-pointer flex-grow">
                                <input type="radio" name="payment_method" value="تحويل انستا باي (InstaPay)" onchange="togglePaymentBox('instapay')" class="text-royal-darkgold focus:ring-royal-gold w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-royal-dark flex items-center gap-2">
                                        تحويل انستا باي (InstaPay)
                                        <span class="bg-purple-100 text-purple-700 text-[9px] px-2 py-0.5 rounded-full font-bold">إجباري رفع إيصال</span>
                                    </h4>
                                    <p class="text-[10px] text-gray-400 font-light mt-0.5">حساب: <strong id="val-instapay" class="font-mono text-royal-dark font-bold" dir="ltr"><?php echo htmlspecialchars($settings['instapay_address']); ?></strong> <?php if(!empty($settings['instapay_name'])) echo ' (' . htmlspecialchars($settings['instapay_name']) . ')'; ?></p>
                                </div>
                            </label>
                            <div class="flex items-center gap-3">
                                <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['instapay_address']); ?>', this)" class="bg-white border border-gray-200 hover:border-royal-gold text-royal-dark text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                                    <i class="fa-regular fa-copy text-purple-600"></i> نسخ العنوان
                                </button>
                                <i class="fa-solid fa-bolt text-purple-600 text-lg"></i>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- 4. الدفع عبر Paymob (فيزا / ماستركارد / ميزة) -->
                        <?php if (($settings['paymob_enabled'] ?? '0') === '1' && !empty($settings['paymob_api_key'])): ?>
                        <label class="payment-option-card border border-gray-200 hover:border-royal-gold p-4 rounded-xl flex items-center justify-between cursor-pointer transition">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="فيزا / ماستركارد (Paymob)" onchange="togglePaymentBox('paymob')" class="text-royal-darkgold focus:ring-royal-gold w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-royal-dark flex items-center gap-2">
                                        دفع إلكتروني (فيزا / ماستركارد / ميزة)
                                        <span class="bg-blue-100 text-blue-700 text-[9px] px-2 py-0.5 rounded-full font-bold">تلقائي 100%</span>
                                    </h4>
                                    <p class="text-[10px] text-gray-400 font-light mt-0.5">الدفع الآمن الفوري باستخدام الكروت والبطاقات البنكية.</p>
                                </div>
                            </div>
                            <i class="fa-solid fa-credit-card text-blue-600 text-lg"></i>
                        </label>
                        <?php endif; ?>

                    </div>

                    <!-- صندوق رفع صورة الإيصال ورقم العملية -->
                    <div id="receipt-upload-box" class="hidden bg-royal-cream/60 p-5 rounded-2xl border border-royal-gold/30 space-y-4 animate-fade-in">
                        <div class="bg-yellow-50 text-yellow-800 p-3 rounded-xl border border-yellow-200 text-xs font-bold flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-triangle-exclamation text-yellow-600 text-base"></i>
                                <div>
                                    <span class="block font-bold">يرجى التحويل أولاً ثم رفع صورة إيصال التحويل (Screenshot).</span>
                                    <span id="target-transfer-info" class="text-[11px] text-gray-600 font-normal">قم بالتحويل على العنوان المحدد أعلاه.</span>
                                </div>
                            </div>
                            <button type="button" id="btn-copy-box" onclick="" class="bg-white border border-yellow-300 text-yellow-900 text-[10px] font-bold px-3 py-1.5 rounded-lg shadow-sm shrink-0 flex items-center gap-1">
                                <i class="fa-regular fa-copy"></i> نسخ
                            </button>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-bold text-royal-dark mb-1">رفع صورة إيصال التحويل (Screenshot) *</label>
                            <input type="file" name="receipt_file" id="receipt_file_input" accept="image/*" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-royal-dark mb-1">رقم العملية / المرجع / الهاتف المحول منه (اختياري)</label>
                            <input type="text" name="transaction_ref" placeholder="مثال: 9876543210 أو رقم الحساب المحول منه" class="w-full p-3 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-royal-gold">
                        </div>
                    </div>

                    <script>
                        const vcashNum = '<?php echo htmlspecialchars($settings['vodafone_cash_number'] ?? ''); ?>';
                        const instaAddr = '<?php echo htmlspecialchars($settings['instapay_address'] ?? ''); ?>';

                        function togglePaymentBox(type) {
                            const box = document.getElementById('receipt-upload-box');
                            const fileInput = document.getElementById('receipt_file_input');
                            const infoSpan = document.getElementById('target-transfer-info');
                            const copyBoxBtn = document.getElementById('btn-copy-box');

                            if (type === 'vcash') {
                                box.classList.remove('hidden');
                                fileInput.required = true;
                                infoSpan.innerHTML = 'رقم فودافون كاش: <strong dir="ltr" class="font-mono text-royal-dark">' + vcashNum + '</strong>';
                                copyBoxBtn.setAttribute('onclick', "copyToClipboard('" + vcashNum + "', this)");
                            } else if (type === 'instapay') {
                                box.classList.remove('hidden');
                                fileInput.required = true;
                                infoSpan.innerHTML = 'حساب انستا باي: <strong dir="ltr" class="font-mono text-royal-dark">' + instaAddr + '</strong>';
                                copyBoxBtn.setAttribute('onclick', "copyToClipboard('" + instaAddr + "', this)");
                            } else {
                                box.classList.add('hidden');
                                fileInput.required = false;
                            }
                        }

                        function copyToClipboard(text, btn) {
                            if (!text) return;
                            const origHTML = btn.innerHTML;
                            
                            if (navigator.clipboard && window.isSecureContext) {
                                navigator.clipboard.writeText(text).then(() => {
                                    renderCopySuccess(btn, origHTML);
                                }).catch(() => {
                                    fallbackCopy(text, btn, origHTML);
                                });
                            } else {
                                fallbackCopy(text, btn, origHTML);
                            }
                        }

                        function fallbackCopy(text, btn, origHTML) {
                            const input = document.createElement('input');
                            input.value = text;
                            document.body.appendChild(input);
                            input.select();
                            document.execCommand('copy');
                            document.body.removeChild(input);
                            renderCopySuccess(btn, origHTML);
                        }

                        function renderCopySuccess(btn, origHTML) {
                            btn.innerHTML = '<i class="fa-solid fa-check text-green-600"></i> تم النسخ!';
                            btn.classList.add('bg-green-50', 'text-green-700', 'border-green-300');
                            setTimeout(() => {
                                btn.innerHTML = origHTML;
                                btn.classList.remove('bg-green-50', 'text-green-700', 'border-green-300');
                            }, 2500);
                        }
                    </script>
                </div>
                
                <button type="submit" id="submit-order-btn" name="submit_order" class="w-full bg-gold-gradient text-white font-bold py-4 mt-4 text-xs tracking-widest uppercase rounded-xl shadow-md bg-gold-gradient-hover btn-shine transition-all disabled:opacity-40 disabled:cursor-not-allowed">تأكيد وإتمام الطلب</button>
            </form>
        </div>
        
        <!-- ملخص الفاتورة الجانبي -->
        <div class="md:w-1/3">
            <div class="bg-white p-6 border border-royal-gold/10 shadow-sm rounded-2xl sticky top-28">
                <h3 class="font-serif font-bold text-base mb-4 text-royal-dark border-b pb-2">تفاصيل الفاتورة</h3>
                
                <!-- قائمة المنتجات المشتراة مع الأوزان -->
                <div class="space-y-3 mb-4 max-h-48 overflow-y-auto divide-y divide-gray-100 pr-1">
                    <?php foreach($_SESSION['cart'] as $c_item): ?>
                        <div class="flex items-center justify-between text-xs pt-2">
                            <div class="flex items-center gap-2">
                                <img src="<?php echo htmlspecialchars($c_item['image']); ?>" class="w-9 h-11 object-cover rounded-lg border border-gray-100 shrink-0">
                                <div>
                                    <span class="font-semibold text-royal-dark block leading-tight"><?php echo htmlspecialchars($c_item['name']); ?></span>
                                    <div class="flex flex-wrap items-center gap-1 mt-1 text-[10px] text-gray-400">
                                        <span>الكمية: <?php echo $c_item['qty']; ?></span>
                                        <?php if(!empty($c_item['weight_label'])): ?>
                                            <span class="bg-amber-100 text-amber-800 px-1.5 py-0.2 rounded font-bold">⚖️ <?php echo htmlspecialchars($c_item['weight_label']); ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($c_item['variant_summary'])): ?>
                                            <span class="bg-gray-100 text-gray-700 px-1.5 py-0.2 rounded font-bold"><?php echo htmlspecialchars($c_item['variant_summary']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <span class="font-serif font-bold text-royal-darkgold"><?php echo ($c_item['price'] * $c_item['qty']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="flex justify-between mb-3 text-xs text-gray-500 font-medium border-t pt-3">
                    <span>مجموع المشتريات</span> 
                    <span class="font-serif"><?php echo $subtotal_cart; ?> <span class="checkout-curr-display"><?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span></span>
                </div>
                
                <?php if($discount_val > 0): ?>
                <div class="flex justify-between mb-3 text-xs text-green-600 font-bold bg-green-50/50 p-2 rounded-lg border border-green-100">
                    <span>الخصم المطبق</span> 
                    <span class="font-serif">- <?php echo $discount_val; ?> <span class="checkout-curr-display"><?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span></span>
                </div>
                <?php endif; ?>
                
                <!-- إشعار حساب المسافة بالكيلومتر -->
                <div id="km-distance-badge" class="hidden my-3 p-3 bg-emerald-50/70 border border-emerald-200/80 rounded-xl text-xs text-emerald-950 font-medium text-center animate-fade-in shadow-sm">
                    <div class="flex items-center justify-center gap-1.5 font-bold text-emerald-800">
                        <i class="fa-solid fa-route text-emerald-600"></i>
                        <span id="km-distance-text">المسافة: - كم</span>
                    </div>
                    <p class="text-[10px] text-gray-500 mt-1 font-normal" id="km-distance-note">يتم احتساب التوصيل بناءً على بُعدك عن المحل.</p>
                </div>

                <div class="flex justify-between mb-5 text-xs text-gray-500 border-b pb-4 items-center">
                    <span>مصاريف الشحن والتوصيل</span> 
                    <span id="shipping-val" class="font-bold text-royal-dark bg-royal-sand px-2 py-0.5 rounded text-[10px]">يُحدد بعد اختيار المحافظة</span>
                </div>
                
                <div class="flex justify-between font-bold text-base text-royal-dark">
                    <span>الإجمالي الكلي للطلب</span>
                    <span class="font-serif text-royal-darkgold text-xl"><span id="total-val"><?php echo $final_total_before_shipping; ?></span> <span class="checkout-curr-display"><?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // إعدادات الشحن الذكي بالكيلومتر من لوحة التحكم
    <?php
    $km_govs_arr_js = !empty($settings['km_shipping_govs']) ? json_decode($settings['km_shipping_govs'], true) : null;
    if (!is_array($km_govs_arr_js) || empty($km_govs_arr_js)) {
        $km_govs_arr_js = ['القاهرة', 'الجيزة', '6 أكتوبر', 'الشيخ زايد', 'القليوبية'];
    }
    ?>
    const kmConfig = {
        enabled: <?php echo (($settings['enable_km_shipping'] ?? '1') === '1') ? 'true' : 'false'; ?>,
        storeLat: <?php echo (float)($settings['store_lat'] ?? 30.066576); ?>,
        storeLng: <?php echo (float)($settings['store_lng'] ?? 31.332781); ?>,
        storeName: '<?php echo addslashes($settings['store_address_name'] ?? 'الفرع الرئيسي'); ?>',
        rate: <?php echo (float)($settings['km_rate'] ?? 2.00); ?>,
        baseMin: <?php echo (float)($settings['km_base_min_price'] ?? 25.00); ?>,
        kmGovs: <?php echo json_encode($km_govs_arr_js, JSON_UNESCAPED_UNICODE); ?> || ['القاهرة', 'الجيزة', '6 أكتوبر', 'الشيخ زايد', 'القليوبية']
    };

    // حساب المسافة الدقيقة بين نقطتين إحداثيات (Haversine Formula)
    function calculateDistanceKm(lat1, lon1, lat2, lon2) {
        const R = 6371; // نصف قطر الأرض بالكيلومتر
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = 
            Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
            Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        const d = R * c;
        return Math.round(d * 10) / 10;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const baseSubtotal = <?php echo $final_total_before_shipping; ?>;
        const govSelect = document.getElementById('gov-select');
        const shippingValEl = document.getElementById('shipping-val');
        const totalValEl = document.getElementById('total-val');
        const submitBtn = document.getElementById('submit-order-btn');
        const govErrorEl = document.getElementById('gov-error');
        const kmBadge = document.getElementById('km-distance-badge');
        const kmText = document.getElementById('km-distance-text');
        const kmNote = document.getElementById('km-distance-note');
        
        const latInput = document.getElementById('delivery_lat');
        const lngInput = document.getElementById('delivery_lng');
        const distInput = document.getElementById('delivery_distance_km');
        const typeInput = document.getElementById('shipping_type');

        function updateShippingDisplay() {
            const opt = govSelect.options[govSelect.selectedIndex];
            
            if (!opt || opt.disabled || !opt.value) {
                govErrorEl.classList.add('hidden');
                submitBtn.disabled = false;
                shippingValEl.innerText = 'يُحدد بعد اختيار المحافظة';
                shippingValEl.className = 'font-bold text-royal-dark bg-royal-sand px-2 py-0.5 rounded text-[10px]';
                totalValEl.innerText = baseSubtotal;
                kmBadge.classList.add('hidden');
                return;
            }

            const govName = opt.getAttribute('data-name') || opt.text.split('(')[0].trim();
            const flatCost = parseFloat(opt.getAttribute('data-cost')) || 0;
            const isActive = opt.getAttribute('data-active') === '1';

            if (!isActive) {
                govErrorEl.classList.remove('hidden');
                submitBtn.disabled = true;
                shippingValEl.innerText = 'غير متاح حالياً';
                shippingValEl.className = 'font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded text-[10px]';
                totalValEl.innerText = baseSubtotal;
                kmBadge.classList.add('hidden');
                return;
            }

            govErrorEl.classList.add('hidden');
            submitBtn.disabled = false;

            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            const kmGovs = Array.isArray(kmConfig.kmGovs) ? kmConfig.kmGovs : ['القاهرة', 'الجيزة', '6 أكتوبر', 'الشيخ زايد', 'القليوبية'];
            const isKmEligibleGov = kmConfig.enabled && kmGovs.some(g => govName.includes(g) || g.includes(govName));

            // إذا كان الـ GPS متوفراً وإحداثياته صالحة
            if (kmConfig.enabled && !isNaN(lat) && !isNaN(lng) && lat > 20 && lat < 35 && lng > 24 && lng < 38) {
                const dist = calculateDistanceKm(kmConfig.storeLat, kmConfig.storeLng, lat, lng);
                distInput.value = dist;

                // التحقق أن المسافة ضمن النطاق المحلي (أقل من 75 كم)
                if (dist <= 75.0 && (isKmEligibleGov || typeInput.value === 'km' || dist <= 40.0)) {
                    typeInput.value = 'km';

                    let finalCost = 0;
                    let extraDist = 0;
                    if (dist <= 1.0) {
                        finalCost = kmConfig.baseMin;
                    } else {
                        extraDist = dist - 1.0;
                        finalCost = kmConfig.baseMin + Math.round(extraDist * kmConfig.rate);
                    }

                    shippingValEl.innerText = finalCost + ' ج.م';
                    shippingValEl.className = 'font-bold text-royal-darkgold bg-royal-sand px-2 py-0.5 rounded text-[10px]';
                    totalValEl.innerText = (baseSubtotal + finalCost);

                    kmBadge.classList.remove('hidden');
                    kmText.innerHTML = `المسافة من متجرنا: <strong>${dist} كم</strong>`;
                    if (dist <= 1.0) {
                        kmNote.innerHTML = `تكلفة التوصيل: <strong>${finalCost} ج.م</strong> (سعر فتح المسافة لأول 1 كم: ${kmConfig.baseMin} ج.م)`;
                    } else {
                        kmNote.innerHTML = `تكلفة التوصيل: <strong>${finalCost} ج.م</strong> (أول 1 كم: ${kmConfig.baseMin} ج.م + ${extraDist.toFixed(1)} كم إضافي × ${kmConfig.rate} ج.م)`;
                    }
                    return;
                }
            }

            // الرجوع للشحن الثابت للمحافظة إذا كان خارج النطاق أو بدون GPS
            typeInput.value = 'flat';
            shippingValEl.innerText = flatCost + ' ج.م';
            shippingValEl.className = 'font-bold text-royal-darkgold bg-royal-sand px-2 py-0.5 rounded text-[10px]';
            totalValEl.innerText = (baseSubtotal + flatCost);
            kmBadge.classList.add('hidden');
        }

        window.recalcShipping = updateShippingDisplay;

        if (govSelect) {
            govSelect.addEventListener('change', updateShippingDisplay);
            if (govSelect.selectedIndex > 0) {
                updateShippingDisplay();
            }
        }
    });
        
    // ================= برمجة الخريطة وتحديد الموقع وعنوان العميل (GPS & Reverse Geocoding) =================
    let map = null;
    let marker = null;
    const gpsStatusBox = document.getElementById('gps-status-box');
    
    function showGpsStatus(msg, type = 'info') {
        if (!gpsStatusBox) return;
        gpsStatusBox.classList.remove('hidden', 'bg-blue-50', 'text-blue-800', 'border-blue-200', 'bg-green-50', 'text-green-800', 'border-green-200', 'bg-amber-50', 'text-amber-800', 'border-amber-200', 'border');
        
        let icon = '<i class="fa-solid fa-info-circle text-blue-600"></i>';
        let cls = 'bg-blue-50 text-blue-800 border border-blue-200';
        
        if (type === 'loading') {
            icon = '<i class="fa-solid fa-spinner fa-spin text-royal-darkgold"></i>';
            cls = 'bg-royal-sand/40 text-royal-dark border border-royal-gold/30';
        } else if (type === 'success') {
            icon = '<i class="fa-solid fa-circle-check text-green-600"></i>';
            cls = 'bg-green-50 text-green-800 border border-green-200';
        } else if (type === 'error') {
            icon = '<i class="fa-solid fa-triangle-exclamation text-amber-600"></i>';
            cls = 'bg-amber-50 text-amber-800 border border-amber-200';
        }
        
        gpsStatusBox.className = `${cls} p-3 rounded-xl text-xs font-bold transition-all flex items-center gap-2 shadow-sm`;
        gpsStatusBox.innerHTML = `<span>${icon}</span> <span>${msg}</span>`;
    }

    function initCheckoutMap() {
        const container = document.getElementById('map-container');
        container.classList.remove('hidden');
        
        const defaultLat = parseFloat(kmConfig.storeLat) || 30.0444;
        const defaultLng = parseFloat(kmConfig.storeLng) || 31.2357;

        if (!map) {
            map = L.map('checkout-map').setView([defaultLat, defaultLng], 13);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);

            // نقطة المتجر الرئيسية (نقطة الصفر)
            const storeIcon = new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-gold.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
            L.marker([defaultLat, defaultLng], { icon: storeIcon })
                .addTo(map)
                .bindPopup("<b>🏬 " + (kmConfig.storeName || 'المتجر الرئيسي') + "</b><br>نقطة انطلاق الشحن والتوصيل");
            
            // دبوس موقع العميل القابل للتحريك
            marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
            marker.bindPopup("<b>📍 موقع التوصيل الخاص بكِ</b>").openPopup();
            
            marker.on('dragend', function() {
                const position = marker.getLatLng();
                updateFieldsFromCoords(position.lat, position.lng);
            });
            
            map.on('click', function(e) {
                marker.setLatLng(e.latlng);
                updateFieldsFromCoords(e.latlng.lat, e.latlng.lng);
            });
        }
        
        setTimeout(() => {
            if (map) map.invalidateSize();
        }, 200);
    }

    // تشغيل جلب الموقع عبر GPS
    function getCustomerGPSLocation(openMap = false) {
        showGpsStatus("جاري الاتصال بالأقمار الصناعية وتحديد موقعكِ وعنوانكِ بدقة... 🛰️", "loading");

        if (!navigator.geolocation) {
            showGpsStatus("المتصفح لا يدعم التحديد التلقائي للموقع، يمكنكِ اختيار موقعكِ من الخريطة أو كتابة العنوان يدوياً.", "error");
            if (openMap) initCheckoutMap();
            return;
        }

        const geoOptions = {
            enableHighAccuracy: true,
            timeout: 12000,
            maximumAge: 0
        };

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;

                if (openMap || map) {
                    initCheckoutMap();
                    if (map) {
                        map.setView([lat, lng], 16);
                        marker.setLatLng([lat, lng]);
                    }
                }

                updateFieldsFromCoords(lat, lng, true);
            },
            function(err) {
                console.warn('Geolocation error:', err);
                let errMsg = "تعذر الوصول لموقعكِ التلقائي، يرجى السماح بصلاحية الموقع للمتصفح أو استخدام الخريطة بالأسفل.";
                if (err.code === 1) errMsg = "تم رفض إذن الوصول للموقع، يرجى تفعيل الـ GPS بالمتصفح أو البحث بالخريطة.";
                else if (err.code === 3) errMsg = "استغرق جلب الموقع وقتاً أطول من المعتاد، يمكنكِ اختيار موقعكِ مباشرة من الخريطة.";
                
                showGpsStatus(errMsg, "error");
                if (openMap) initCheckoutMap();
            },
            geoOptions
        );
    }

    // ربط أزرار الـ GPS
    const btnQuickGps = document.getElementById('btn-quick-gps');
    if (btnQuickGps) {
        btnQuickGps.addEventListener('click', function() {
            getCustomerGPSLocation(true);
        });
    }

    const btnInputGps = document.getElementById('btn-input-gps');
    if (btnInputGps) {
        btnInputGps.addEventListener('click', function() {
            getCustomerGPSLocation(false);
        });
    }

    const btnOpenMap = document.getElementById('btn-open-map');
    if (btnOpenMap) {
        btnOpenMap.addEventListener('click', function() {
            const container = document.getElementById('map-container');
            if (container.classList.contains('hidden')) {
                initCheckoutMap();
            } else {
                container.classList.add('hidden');
            }
        });
    }
    
    // إعداد البحث في الخريطة
    const btnMapSearch = document.getElementById('btn-map-search');
    const mapSearchInput = document.getElementById('map-search-input');

    function performMapSearch() {
        let query = mapSearchInput.value.trim();
        if (!query) return;
        
        btnMapSearch.innerText = "جاري...";
        btnMapSearch.disabled = true;

        if (!query.includes('مصر') && !query.toLowerCase().includes('egypt')) {
            query += '، مصر';
        }
        
        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&accept-language=ar&limit=1`)
            .then(res => res.json())
            .then(data => {
                btnMapSearch.innerText = "بحث";
                btnMapSearch.disabled = false;
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    initCheckoutMap();
                    map.setView([lat, lng], 16);
                    marker.setLatLng([lat, lng]);
                    updateFieldsFromCoords(lat, lng);
                } else {
                    showGpsStatus("عذراً، لم نجد نتائج لهذا البحث، يرجى كتابة اسم المنطقة أو الشارع بشكل أوضح.", "error");
                }
            })
            .catch(err => {
                console.error('Search error:', err);
                btnMapSearch.innerText = "بحث";
                btnMapSearch.disabled = false;
                showGpsStatus("حدث خطأ أثناء البحث، يرجى المحاولة مجدداً أو النقر على الخريطة.", "error");
            });
    }

    if (btnMapSearch) btnMapSearch.addEventListener('click', performMapSearch);
    if (mapSearchInput) {
        mapSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performMapSearch();
            }
        });
    }
    
    // دالة استخراج وتحديث العنوان والمحافظة وحساب المسافة
    async function updateFieldsFromCoords(lat, lng, isUserGps = false) {
        document.getElementById('delivery_lat').value = lat.toFixed(6);
        document.getElementById('delivery_lng').value = lng.toFixed(6);

        const streetField = document.getElementById('addr_street');
        streetField.placeholder = "جاري استخراج اسم الشارع والمنطقة من الخريطة... 📍";
        
        let foundAddress = '';
        let foundState = '';

        // المحاولة 1: OpenStreetMap Nominatim
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 6000);
            
            const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=ar&addressdetails=1`, {
                signal: controller.signal
            });
            clearTimeout(timeoutId);
            
            if (res.ok) {
                const data = await res.json();
                if (data && data.address) {
                    const a = data.address;
                    const road = a.road || a.pedestrian || a.street || a.footway || a.amenity || a.building || '';
                    const neighbourhood = a.neighbourhood || a.suburb || a.quarter || a.residential || a.city_district || a.district || '';
                    const city = a.city || a.town || a.village || a.municipality || a.county || '';
                    
                    const parts = [road, neighbourhood, city].filter(Boolean);
                    if (parts.length > 0) {
                        foundAddress = parts.join('، ');
                    } else if (data.display_name) {
                        foundAddress = data.display_name.split('،').map(s=>s.trim()).filter(s=>s && !s.includes('مصر') && !/^\d{4,6}$/.test(s)).slice(0, 3).join('، ');
                    }
                    foundState = a.state || a.governorate || a.city || '';
                }
            }
        } catch (e) {
            console.log('Nominatim failed or timed out, trying fallback...');
        }

        // المحاولة 2 (احتياطي سريع): BigDataCloud Client API
        if (!foundAddress) {
            try {
                const res2 = await fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lng}&localityLanguage=ar`);
                if (res2.ok) {
                    const data2 = await res2.json();
                    if (data2) {
                        const locality = data2.locality || data2.city || '';
                        const subDiv = data2.principalSubdivision || '';
                        const parts2 = [locality, subDiv].filter(Boolean);
                        if (parts2.length > 0) {
                            foundAddress = parts2.join('، ');
                        }
                        if (!foundState) foundState = subDiv;
                    }
                }
            } catch (e2) {
                console.log('Fallback reverse geocoding also failed:', e2);
            }
        }

        streetField.placeholder = "اسم الشارع / المنطقة / الميدان *";
        if (foundAddress) {
            streetField.value = foundAddress;
        }

        // مطابقة وتحديد المحافظة تلقائياً في القائمة
        matchAndSelectGovernorate(foundState, foundAddress, lat, lng);

        // إعادة حساب المسافة والتكلفة فوراً
        if (window.recalcShipping) {
            window.recalcShipping();
        }

        // إظهار رسالة النجاح في شريط الـ GPS
        const displayLabel = foundAddress ? `العنوان: (${foundAddress})` : `الإحداثيات: (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
        showGpsStatus(`تم تحديد موقع التوصيل بنجاح! 📍 ${displayLabel}`, "success");
    }

    // دالة مطابقة المحافظة الذكية في مصر
    function matchAndSelectGovernorate(stateName, fullAddressText, lat, lng) {
        const select = document.getElementById('gov-select');
        if (!select) return;

        const target = `${stateName} ${fullAddressText}`.toLowerCase();
        let matchedName = '';

        // قاموس المناطق المصرية والمحافظات
        const govKeywords = {
            'الشيخ زايد': ['الشيخ زايد', 'زايد', 'sheikh zayed', 'zayed'],
            '6 أكتوبر': ['6 أكتوبر', 'أكتوبر', 'السادس من أكتوبر', 'october', 'الحصري', 'حدائق أكتوبر', 'القرية الذكية'],
            'القاهرة': ['القاهرة', 'cairo', 'مدينة نصر', 'nasr city', 'المعادي', 'maadi', 'التجمع', 'tagamoa', 'مصر الجديدة', 'heliopolis', 'القاهرة الجديدة', 'new cairo', 'المقطم', 'mokattam', 'الشروق', 'shorouk', 'بدر', 'badr', 'الرحاب', 'rehab', 'مدينتي', 'madinaty', 'الزمالك', 'zamalek', 'شبرا', 'حلوان', 'عين شمس', 'المرج', 'الزيتون', 'الوايلي', 'النزهة', 'السلام', 'المنيل', 'وسط البلد'],
            'الجيزة': ['الجيزة', 'giza', 'الدقي', 'dokki', 'المهندسين', 'mohandessin', 'الهرم', 'haram', 'فيصل', 'faisal', 'العجوزة', 'agouza', 'إمبابة', 'imbaba', 'الوراق', 'warraq', 'العمرانية', 'بولاق الدكرور', 'الحوامدية', 'البدرشين', 'الصف', 'أطفيح', 'العياط'],
            'القليوبية': ['القليوبية', 'qalyubia', 'بنها', 'banha', 'العبور', 'obour', 'شبرا الخيمة', 'طوخ', 'قها', 'قليوب', 'الخانكة'],
            'الإسكندرية': ['الإسكندرية', 'alexandria', 'alex', 'سموحة', 'smouha', 'سيدي بشر', 'ميامي', 'العجمي', 'ستانلي', 'المنتزه', 'محرم بك', 'سيدي جابر', 'الرمل', 'لوران', 'العصافرة', 'المندرة', 'المعمورة', 'برج العرب'],
            'الدقهلية': ['الدقهلية', 'المنصورة', 'mansoura', 'ميت غمر', 'طلخا', 'دكرنس', 'بلقاس', 'سنبلاوين', 'شربين'],
            'الشرقية': ['الشرقية', 'الزقازيق', 'zagazig', 'العاشر من رمضان', '10th of ramadan', 'بلبيس', 'فاقوس', 'منيا القمح', 'أبو حماد', 'أبو كبير', 'ديرب نجم'],
            'الغربية': ['الغربية', 'طنطا', 'tanta', 'المحلة', 'mahalla', 'كفر الزيات', 'زفتى', 'السنطة', 'سمنود', 'بسيون'],
            'المنوفية': ['المنوفية', 'شبين الكوم', 'shibin', 'السادات', 'sadat', 'منوف', 'أشمون', 'قويسنا', 'بركة السبع', 'تلا', 'الشهداء'],
            'البحيرة': ['البحيرة', 'دمنهور', 'damanhour', 'كفر الدوار', 'إيتاي البارود', 'حوش عيسى', 'أبو المطامير', 'رشيد', 'إدكو'],
            'كفر الشيخ': ['كفر الشيخ', 'دسوق', 'فوه', 'مطوبس', 'بيلا', 'الحامول', 'بلطيم'],
            'دمياط': ['دمياط', 'damietta', 'رأس البر', 'دمياط الجديدة', 'فارسكور', 'الزرقا', 'كفر سعد'],
            'بورسعيد': ['بورسعيد', 'port said', 'بورفؤاد'],
            'الإسماعيلية': ['الإسماعيلية', 'ismailia', 'فايد', 'القنطرة', 'التل الكبير'],
            'السويس': ['السويس', 'suez', 'العين السخنة', 'ain sokhna'],
            'الفيوم': ['الفيوم', 'fayoum', 'faiyum', 'سنورس', 'إطسا', 'طامية', 'يوسف الصديق', 'أبشواي'],
            'بني سويف': ['بني سويف', 'beni suef', 'الواسطى', 'ناصر', 'إهناسيا', 'ببا', 'سمسطا', 'الفشن'],
            'المنيا': ['المنيا', 'minya', 'مغاغة', 'بني مزار', 'مطاي', 'سمالوط', 'أبو قرقاص', 'ملوي', 'دير مواس'],
            'أسيوط': ['أسيوط', 'asyut', 'ديروط', 'القوصية', 'أبنوب', 'منفلوط', 'أبو تيج', 'صدفا', 'الغنايم', 'البداري', 'ساحل سليم'],
            'سوهاج': ['سوهاج', 'sohag', 'طهطا', 'جرجا', 'المراغة', 'جهينة', 'طما', 'المنشأة', 'ساقلتة', 'أخميم', 'البلينا', 'دار السلام'],
            'قنا': ['قنا', 'qena', 'نجع حمادي', 'دشنا', 'فرشوط', 'أبو تشت', 'قفط', 'قوص', 'نقادة'],
            'الأقصر': ['الأقصر', 'luxor', 'إسنا', 'أرمنت'],
            'أسوان': ['أسوان', 'aswan', 'إدفو', 'كوم أمبو', 'نصر النوبة', 'دراو'],
            'البحر الأحمر': ['البحر الأحمر', 'الغردقة', 'hurghada', 'الجونة', 'gouna', 'سفاجا', 'القصير', 'مرسى علم', 'رأس غارب'],
            'مرسى مطروح': ['مرسى مطروح', 'مطروح', 'matrouh', 'الساحل الشمالي', 'sahel', 'العلمين', 'alamein', 'سيدي عبد الرحمن', 'الضبعة', 'سيوة', 'الحمام'],
            'شمال سيناء': ['شمال سيناء', 'العريش', 'arish', 'الشيخ زويد', 'رفح', 'بئر العبد'],
            'جنوب سيناء': ['جنوب سيناء', 'شرم الشيخ', 'sharm', 'دهب', 'dahab', 'نويبع', 'طابا', 'طور سيناء', 'رأس سدر', 'سانت كاترين'],
            'الوادي الجديد': ['الوادي الجديد', 'الخارجة', 'الداخلة', 'الفرافرة', 'باريس']
        };

        for (const [gov, keywords] of Object.entries(govKeywords)) {
            if (keywords.some(k => target.includes(k))) {
                matchedName = gov;
                break;
            }
        }

        if (matchedName) {
            for (let i = 0; i < select.options.length; i++) {
                const optName = select.options[i].getAttribute('data-name') || '';
                if (optName.includes(matchedName) || matchedName.includes(optName)) {
                    select.selectedIndex = i;
                    return;
                }
            }
        }

        // مطابقة عامة بديلة
        for (let i = 0; i < select.options.length; i++) {
            const optName = select.options[i].getAttribute('data-name') || '';
            if (!optName) continue;
            const cleanGov = optName.replace('محافظة', '').trim();
            if (target.includes(cleanGov.toLowerCase()) || cleanGov.toLowerCase().includes(stateName.toLowerCase())) {
                select.selectedIndex = i;
                return;
            }
        }
    }

    // مزامنة السلة المتروكة في الخلفية عند كتابة العميل لبياناته
    let syncTimeout = null;
    function syncAbandonedCartData() {
        clearTimeout(syncTimeout);
        syncTimeout = setTimeout(() => {
            const nameInput = document.querySelector('input[name="c_name"]');
            const phoneInput = document.querySelector('input[name="c_phone"]');
            const emailInput = document.querySelector('input[name="c_email"]');
            const govSelect = document.getElementById('gov-select');

            const name = nameInput ? nameInput.value.trim() : '';
            const phone = phoneInput ? phoneInput.value.trim() : '';
            const email = emailInput ? emailInput.value.trim() : '';
            const gov = (govSelect && govSelect.selectedIndex > 0) ? govSelect.options[govSelect.selectedIndex].text : '';

            if (name || phone || email) {
                const fd = new FormData();
                fd.append('customer_name', name);
                fd.append('customer_phone', phone);
                fd.append('customer_email', email);
                fd.append('governorate', gov);

                fetch('ajax_save_cart.php', {
                    method: 'POST',
                    body: fd
                }).catch(err => console.log('Cart sync error:', err));
            }
        }, 800);
    }

    const trackInputs = document.querySelectorAll('input[name="c_name"], input[name="c_phone"], input[name="c_email"], #gov-select');
    trackInputs.forEach(el => {
        el.addEventListener('input', syncAbandonedCartData);
        el.addEventListener('change', syncAbandonedCartData);
        el.addEventListener('blur', syncAbandonedCartData);
    });
</script>

<?php
include 'footer.php';
?>
