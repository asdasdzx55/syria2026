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

$active_countries = !empty($settings['active_countries']) ? json_decode($settings['active_countries'], true) : array_keys($supported_countries_data);
if (!is_array($active_countries) || empty($active_countries)) {
    $active_countries = array_keys($supported_countries_data);
}
$default_country = $settings['default_country'] ?? 'مصر';
$currency_mode = $settings['preferred_currency_mode'] ?? 'local';

// جلب مناطق الشحن مجمعة لكل دولة
$all_zones_raw = $pdo->query("SELECT * FROM shipping_zones ORDER BY country_name ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
$country_zones_map = [];
foreach ($all_zones_raw as $z) {
    $c = $z['country_name'] ?: 'مصر';
    $country_zones_map[$c][] = [
        'id' => (int)$z['id'],
        'name' => $z['gov_name'],
        'cost' => (float)$z['cost'],
        'is_active' => (int)$z['is_active'],
        'currency' => $z['currency_symbol'] ?: ($supported_countries_data[$c]['currency'] ?? 'ج.م')
    ];
}

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
    $country = trim($_POST['c_country'] ?? $default_country);
    
    // تحديد العملة المطلوبة
    $selected_curr = trim($_POST['c_currency'] ?? 'local');
    if ($selected_curr === '$' || $selected_curr === 'USD') {
        $currency = '$';
    } else {
        $currency = $supported_countries_data[$country]['currency'] ?? ($settings['store_currency'] ?? 'ج.م');
    }
    
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
    $shipping_cost = $gov_data['cost'];

    // تفاصيل المنتجات في الطلب بالفاتورة
    $details = "";
    foreach ($_SESSION['cart'] as $item) {
        $details .= "• " . $item['name'] . " | الكمية: " . $item['qty'] . " | السعر: " . $item['price'] . " " . $currency . "\n";
    }

    $coupon_code = $_SESSION['coupon'] ? $_SESSION['coupon']['code'] : null;
    $total_price = $final_total_before_shipping + $shipping_cost;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // معالجة وسيلة الدفع وصورة الإيصال
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : 'الدفع عند الاستلام';
    $payment_status = 'غير مدفوع';
    $transaction_ref = isset($_POST['transaction_ref']) ? trim($_POST['transaction_ref']) : null;
    $receipt_image = null;

    if (in_array($payment_method, ['فودافون كاش / المحافظ', 'تحويل انستا باي (InstaPay)', 'محفظة شام كاش (Cham Cash)'])) {
        $payment_status = 'قيد التحقق والمراجعة';
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === 0) {
            $receipt_image = uploadImage($_FILES['receipt_file']);
        }
        if (empty($receipt_image)) {
            die("<div style='direction:rtl; text-align:center; padding:50px; font-family:tahoma;'><h2>عذراً، يجب رفع صورة إيصال التحويل لإتمام الطلب بهذه الوسيلة.</h2><a href='javascript:history.back()'>العودة وتعديل الطلب</a></div>");
        }
    } elseif ($payment_method === 'باي بال (PayPal)') {
        $payment_status = 'قيد التحقق والمراجعة';
        if (isset($_FILES['receipt_file']) && $_FILES['receipt_file']['error'] === 0) {
            $receipt_image = uploadImage($_FILES['receipt_file']);
        }
    }

    $pdo->prepare("INSERT INTO orders (customer_name, customer_phone, customer_email, country, governorate, currency, customer_address, order_details, total_price, discount_amount, shipping_cost, coupon_code, user_id, payment_method, payment_status, receipt_image, transaction_ref) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$name, $phone, $email, $country, $gov_name, $currency, $address, $details, $total_price, $discount_val, $shipping_cost, $coupon_code, $user_id, $payment_method, $payment_status, $receipt_image, $transaction_ref]);

    $order_id = $pdo->lastInsertId();

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

                <!-- اختيار الدولة والعملة -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold mb-1.5 text-royal-dark flex items-center gap-1.5">
                            <i class="fa-solid fa-earth-americas text-royal-darkgold"></i> الدولة للتوصيل *
                        </label>
                        <select name="c_country" id="country-select" required class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-bold text-royal-dark">
                            <?php foreach($active_countries as $ac): 
                                $c_meta = $supported_countries_data[$ac] ?? ['flag'=>'🌐', 'currency'=>'ج.م'];
                            ?>
                                <option value="<?php echo htmlspecialchars($ac); ?>" data-currency="<?php echo htmlspecialchars($c_meta['currency']); ?>" <?php echo ($default_country === $ac) ? 'selected' : ''; ?>>
                                    <?php echo $c_meta['flag'] . ' ' . htmlspecialchars($ac); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold mb-1.5 text-royal-dark flex items-center gap-1.5">
                            <i class="fa-solid fa-money-bill-wave text-royal-darkgold"></i> عملة الفاتورة والدفع
                        </label>
                        <select name="c_currency" id="currency-select" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-bold text-royal-dark">
                            <option value="local" id="opt-local-currency">عملة البلد المحددة (<?php echo htmlspecialchars($supported_countries_data[$default_country]['currency'] ?? 'ج.م'); ?>)</option>
                            <option value="$" <?php echo ($currency_mode === 'usd') ? 'selected' : ''; ?>>الدولار الأمريكي ($ - USD)</option>
                        </select>
                    </div>
                </div>

                <!-- اختيار المحافظة / المنطقة التابعة للدولة -->
                <div>
                    <label class="block text-xs font-bold mb-1.5 text-royal-dark flex items-center gap-1.5">
                        <i class="fa-solid fa-map-pin text-royal-darkgold"></i> المحافظة / المدينة *
                    </label>
                    <select name="c_gov" id="gov-select" required class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition text-gray-700 rounded-xl text-sm font-medium">
                        <option value="" disabled selected>اختر المحافظة / المدينة للتسليم *</option>
                    </select>

                    <div id="gov-error" class="hidden text-red-600 text-xs mt-3.5 p-3.5 bg-red-50 border border-red-200 rounded-xl font-bold">
                        <i class="fa-solid fa-circle-exclamation mr-1 text-sm"></i> عذراً، التوصيل والشحن لهذه المنطقة غير متاح حالياً.
                    </div>
                </div>
                <!-- زر تحديد الموقع عبر الخريطة المدمجة -->
                <div class="mb-4">
                    <button type="button" id="btn-open-map" class="w-full bg-royal-sand/60 hover:bg-royal-gold/15 text-royal-darkgold border border-royal-gold/20 py-3.5 px-4 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 shadow-sm">
                        <i class="fa-solid fa-map-location-dot text-base animate-bounce"></i> تحديد موقع التوصيل على الخريطة تلقائياً
                    </button>
                    <!-- حاوية الخريطة -->
                    <div id="map-container" class="hidden mt-3.5 border border-royal-gold/20 rounded-xl overflow-hidden shadow-inner relative z-30">
                        <!-- شريط البحث داخل الخريطة -->
                        <div class="p-2 bg-royal-sand/50 border-b border-royal-gold/15 flex gap-2">
                            <input type="text" id="map-search-input" placeholder="ابحثي عن منطقتكِ أو شارعكِ هنا..." class="flex-grow p-2.5 border border-gray-200 rounded-lg text-xs outline-none focus:border-royal-gold bg-white">
                            <button type="button" id="btn-map-search" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-4 py-2 rounded-lg text-xs font-bold transition">بحث</button>
                        </div>
                        <div id="checkout-map" class="h-64 w-full"></div>
                        <div class="bg-royal-charcoal text-royal-gold p-3.5 text-[10px] text-center font-bold">
                            <i class="fa-solid fa-info-circle"></i> يمكنكِ سحب الدبوس الأحمر أو البحث بالأعلى لتحديد موقعكِ بدقة، وسيتم جلب اسم المنطقة والشارع تلقائياً!
                        </div>
                    </div>
                </div>

                <!-- تفاصيل العنوان المجزأة -->
                <div class="space-y-4">
                    <div>
                        <input type="text" name="addr_street" id="addr_street" required placeholder="اسم الشارع / المنطقة / الميدان *" value="<?php echo htmlspecialchars($logged_street); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-1">
                            <input type="text" name="addr_building" id="addr_building" required placeholder="رقم العمارة *" value="<?php echo htmlspecialchars($logged_building); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                        </div>
                        <div class="col-span-1">
                            <input type="text" name="addr_floor" id="addr_floor" placeholder="رقم الدور" value="<?php echo htmlspecialchars($logged_floor); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                        </div>
                        <div class="col-span-1">
                            <input type="text" name="addr_apartment" id="addr_apartment" required placeholder="رقم الشقة *" value="<?php echo htmlspecialchars($logged_apartment); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                        </div>
                    </div>
                    <div>
                        <input type="text" name="addr_landmark" id="addr_landmark" placeholder="أقرب علامة مميزة (مثال: بجوار صيدلية...) (اختياري)" value="<?php echo htmlspecialchars($logged_landmark); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm">
                    </div>
                </div>
                
                <!-- اختيار طريقة الدفع -->
                <div class="space-y-4 pt-4 border-t border-gray-100">
                    <label class="block text-xs font-bold mb-1 text-royal-dark">اختيار طريقة الدفع المتاحة *</label>
                    <div class="grid grid-cols-1 gap-3.5">
                        
                        <!-- 1. الدفع عند الاستلام (كاش - لجميع الدول) -->
                        <?php if (($settings['cod_enabled'] ?? '1') === '1'): ?>
                        <label data-payment-country="all" class="payment-option-card border-2 border-royal-gold bg-royal-cream/20 p-4 rounded-xl flex items-center justify-between cursor-pointer transition shadow-sm">
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

                        <!-- 2. محفظة شام كاش / سيريتل كاش (خاصة بسوريا 🇸🇾) -->
                        <?php if (($settings['cham_cash_enabled'] ?? '0') === '1' && (!empty($settings['cham_cash_number']) || !empty($settings['syriatel_cash_number']))): ?>
                        <div data-payment-country="سوريا" class="payment-option-card border border-emerald-300 bg-emerald-50/30 hover:border-emerald-500 p-4 rounded-xl flex items-center justify-between transition">
                            <label class="flex items-center gap-3 cursor-pointer flex-grow">
                                <input type="radio" name="payment_method" value="محفظة شام كاش (Cham Cash)" onchange="togglePaymentBox('cham')" class="text-emerald-600 focus:ring-emerald-500 w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-emerald-950 flex items-center gap-2">
                                        🇸🇾 محفظة شام كاش / سيريتل
                                        <span class="bg-emerald-100 text-emerald-800 text-[9px] px-2 py-0.5 rounded-full font-bold">إجباري رفع إيصال</span>
                                    </h4>
                                    <p class="text-[10px] text-emerald-700 font-light mt-0.5">
                                        حساب شام كاش: <strong class="font-mono font-bold text-emerald-900" dir="ltr"><?php echo htmlspecialchars($settings['cham_cash_number'] ?: $settings['syriatel_cash_number']); ?></strong>
                                        <?php if (!empty($settings['cham_cash_name'])): ?> (<?php echo htmlspecialchars($settings['cham_cash_name']); ?>)<?php endif; ?>
                                    </p>
                                </div>
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['cham_cash_number'] ?: $settings['syriatel_cash_number']); ?>', this)" class="bg-white border border-emerald-300 hover:border-emerald-500 text-emerald-900 text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                                    <i class="fa-regular fa-copy text-emerald-700"></i> نسخ
                                </button>
                                <span class="text-xl">🇸🇾</span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- 3. باي بال (PayPal - للدول العربية والدولية 🌐) -->
                        <?php if (($settings['paypal_enabled'] ?? '0') === '1' && !empty($settings['paypal_email'])): ?>
                        <div data-payment-country="all" class="payment-option-card border border-blue-200 bg-blue-50/20 hover:border-blue-500 p-4 rounded-xl flex items-center justify-between transition">
                            <label class="flex items-center gap-3 cursor-pointer flex-grow">
                                <input type="radio" name="payment_method" value="باي بال (PayPal)" onchange="togglePaymentBox('paypal')" class="text-blue-600 focus:ring-blue-500 w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-blue-950 flex items-center gap-2">
                                        <i class="fa-brands fa-paypal text-blue-600"></i> باي بال (PayPal)
                                        <span class="bg-blue-100 text-blue-800 text-[9px] px-2 py-0.5 rounded-full font-bold">دفع دولي وآمن</span>
                                    </h4>
                                    <p class="text-[10px] text-blue-700 font-light mt-0.5">
                                        حساب باي بال: <strong class="font-mono font-bold text-blue-900" dir="ltr"><?php echo htmlspecialchars($settings['paypal_email']); ?></strong>
                                    </p>
                                </div>
                            </label>
                            <div class="flex items-center gap-2">
                                <button type="button" onclick="copyToClipboard('<?php echo htmlspecialchars($settings['paypal_email']); ?>', this)" class="bg-white border border-blue-300 hover:border-blue-500 text-blue-900 text-[10px] font-bold px-3 py-1.5 rounded-lg transition-all flex items-center gap-1.5 shadow-sm active:scale-95">
                                    <i class="fa-regular fa-copy text-blue-600"></i> نسخ
                                </button>
                                <i class="fa-brands fa-paypal text-blue-600 text-xl"></i>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- 4. فودافون كاش / المحافظ الإلكترونية (خاص بمصر 🇪🇬) -->
                        <?php if (($settings['vodafone_cash_enabled'] ?? '0') === '1' && !empty($settings['vodafone_cash_number'])): ?>
                        <div data-payment-country="مصر" class="payment-option-card border border-gray-200 hover:border-royal-gold p-4 rounded-xl flex items-center justify-between transition">
                            <label class="flex items-center gap-3 cursor-pointer flex-grow">
                                <input type="radio" name="payment_method" value="فودافون كاش / المحافظ" onchange="togglePaymentBox('vcash')" class="text-royal-darkgold focus:ring-royal-gold w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-royal-dark flex items-center gap-2">
                                        تحويل فودافون كاش / المحافظ
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

                        <!-- 5. تحويل انستا باي (InstaPay - خاص بمصر 🇪🇬) -->
                        <?php if (($settings['instapay_enabled'] ?? '0') === '1' && !empty($settings['instapay_address'])): ?>
                        <div data-payment-country="مصر" class="payment-option-card border border-gray-200 hover:border-royal-gold p-4 rounded-xl flex items-center justify-between transition">
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

                        <!-- 6. الدفع عبر Paymob (مصر، السعودية، الإمارات، سلطنة عمان) -->
                        <?php if (($settings['paymob_enabled'] ?? '0') === '1' && !empty($settings['paymob_api_key'])): ?>
                        <label data-payment-country="مصر,السعودية,الإمارات,سلطنة عمان" class="payment-option-card border border-gray-200 hover:border-royal-gold p-4 rounded-xl flex items-center justify-between cursor-pointer transition">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="payment_method" value="فيزا / ماستركارد (Paymob)" onchange="togglePaymentBox('paymob')" class="text-royal-darkgold focus:ring-royal-gold w-4 h-4">
                                <div class="text-right">
                                    <h4 class="text-xs font-bold text-royal-dark flex items-center gap-2">
                                        دفع إلكتروني (فيزا / ماستركارد / مدى)
                                        <span class="bg-blue-100 text-blue-700 text-[9px] px-2 py-0.5 rounded-full font-bold">تلقائي 100%</span>
                                    </h4>
                                    <p class="text-[10px] text-gray-400 font-light mt-0.5">الدفع الآمن الفوري باستخدام الكروت والبطاقات البنكية.</p>
                                </div>
                            </div>
                            <i class="fa-solid fa-credit-card text-blue-600 text-lg"></i>
                        </label>
                        <?php endif; ?>

                    </div>

                    <!-- صندوق رفع صورة الإيصال ورقم العملية (يظهر لشام كاش وفودافون كاش وانستا باي وباي بال) -->
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
                        const chamNum = '<?php echo htmlspecialchars($settings['cham_cash_number'] ?: ($settings['syriatel_cash_number'] ?? '')); ?>';
                        const paypalEmail = '<?php echo htmlspecialchars($settings['paypal_email'] ?? ''); ?>';

                        function togglePaymentBox(type) {
                            const box = document.getElementById('receipt-upload-box');
                            const fileInput = document.getElementById('receipt_file_input');
                            const infoSpan = document.getElementById('target-transfer-info');
                            const copyBoxBtn = document.getElementById('btn-copy-box');

                            if (type === 'cham') {
                                box.classList.remove('hidden');
                                fileInput.required = true;
                                infoSpan.innerHTML = 'رقم شام كاش / سيريتل: <strong dir="ltr" class="font-mono text-royal-dark">' + chamNum + '</strong>';
                                copyBoxBtn.setAttribute('onclick', "copyToClipboard('" + chamNum + "', this)");
                            } else if (type === 'vcash') {
                                box.classList.remove('hidden');
                                fileInput.required = true;
                                infoSpan.innerHTML = 'رقم فودافون كاش: <strong dir="ltr" class="font-mono text-royal-dark">' + vcashNum + '</strong>';
                                copyBoxBtn.setAttribute('onclick', "copyToClipboard('" + vcashNum + "', this)");
                            } else if (type === 'instapay') {
                                box.classList.remove('hidden');
                                fileInput.required = true;
                                infoSpan.innerHTML = 'حساب انستا باي: <strong dir="ltr" class="font-mono text-royal-dark">' + instaAddr + '</strong>';
                                copyBoxBtn.setAttribute('onclick', "copyToClipboard('" + instaAddr + "', this)");
                            } else if (type === 'paypal') {
                                box.classList.remove('hidden');
                                fileInput.required = false;
                                infoSpan.innerHTML = 'حساب باي بال: <strong dir="ltr" class="font-mono text-royal-dark">' + paypalEmail + '</strong>';
                                copyBoxBtn.setAttribute('onclick', "copyToClipboard('" + paypalEmail + "', this)");
                            } else {
                                box.classList.add('hidden');
                                fileInput.required = false;
                            }
                        }

                        function updatePaymentMethodsForCountry(country) {
                            const cards = document.querySelectorAll('.payment-option-card');
                            let hasSelectedVisible = false;
                            let firstVisibleRadio = null;

                            cards.forEach(card => {
                                const allowedCountries = card.getAttribute('data-payment-country');
                                let isVisible = false;

                                if (allowedCountries === 'all') {
                                    isVisible = true;
                                } else {
                                    const list = allowedCountries.split(',').map(s => s.trim());
                                    isVisible = list.includes(country);
                                }

                                if (isVisible) {
                                    card.classList.remove('hidden');
                                    const radio = card.querySelector('input[type="radio"]');
                                    if (radio) {
                                        if (!firstVisibleRadio) firstVisibleRadio = radio;
                                        if (radio.checked) hasSelectedVisible = true;
                                    }
                                } else {
                                    card.classList.add('hidden');
                                    const radio = card.querySelector('input[type="radio"]');
                                    if (radio && radio.checked) {
                                        radio.checked = false;
                                    }
                                }
                            });

                            // إذا كانت الطريقة المختارة أصبحت مخفية، نحدد أول خيار متاح
                            if (!hasSelectedVisible && firstVisibleRadio) {
                                firstVisibleRadio.checked = true;
                                firstVisibleRadio.dispatchEvent(new Event('change'));
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
                
                <div class="flex justify-between mb-3 text-xs text-gray-500 font-medium">
                    <span>مجموع المشتريات</span> 
                    <span class="font-serif"><?php echo $subtotal_cart; ?> <span class="checkout-curr-display"><?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span></span>
                </div>
                
                <?php if($discount_val > 0): ?>
                <div class="flex justify-between mb-3 text-xs text-green-600 font-bold bg-green-50/50 p-2 rounded-lg border border-green-100">
                    <span>الخصم المطبق</span> 
                    <span class="font-serif">- <?php echo $discount_val; ?> <span class="checkout-curr-display"><?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span></span>
                </div>
                <?php endif; ?>
                
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
    document.addEventListener('DOMContentLoaded', function() {
        const countryZonesMap = <?php echo json_encode($country_zones_map, JSON_UNESCAPED_UNICODE); ?>;
        const supportedCountries = <?php echo json_encode($supported_countries_data, JSON_UNESCAPED_UNICODE); ?>;
        const baseSubtotal = <?php echo $final_total_before_shipping; ?>;
        const loggedGovId = <?php echo json_encode($logged_gov_id); ?>;

        const countrySelect = document.getElementById('country-select');
        const currencySelect = document.getElementById('currency-select');
        const govSelect = document.getElementById('gov-select');
        const optLocalCurr = document.getElementById('opt-local-currency');
        const shippingValEl = document.getElementById('shipping-val');
        const totalValEl = document.getElementById('total-val');
        const submitBtn = document.getElementById('submit-order-btn');
        const govErrorEl = document.getElementById('gov-error');
        const currencyDisplays = document.querySelectorAll('.checkout-curr-display');

        function getCurrentCurrency() {
            const country = countrySelect.value;
            const countryMeta = supportedCountries[country] || { currency: 'ج.م' };
            if (currencySelect && currencySelect.value === '$') {
                return '$';
            }
            return countryMeta.currency || 'ج.م';
        }

        function updateCurrencyLabels() {
            const curr = getCurrentCurrency();
            const country = countrySelect.value;
            const countryMeta = supportedCountries[country] || { currency: 'ج.م' };
            
            if (optLocalCurr) {
                optLocalCurr.textContent = 'عملة ' + country + ' (' + (countryMeta.currency || 'ج.م') + ')';
            }
            
            currencyDisplays.forEach(el => {
                el.textContent = curr;
            });
            
            // إعادة حساب الشحن إذا كانت المحافظة مختارة
            handleGovChange();
        }

        function populateGovernorates(country, selectedId = null) {
            govSelect.innerHTML = '<option value="" disabled selected>اختر المحافظة / المدينة للتسليم *</option>';
            const zones = countryZonesMap[country] || [];
            
            if (zones.length === 0) {
                const opt = document.createElement('option');
                opt.value = "";
                opt.textContent = "لا توجد مناطق شحن مسجلة لهذه الدولة";
                opt.disabled = true;
                govSelect.appendChild(opt);
                return;
            }

            zones.forEach(z => {
                const opt = document.createElement('option');
                opt.value = z.id;
                opt.textContent = z.name + (z.is_active ? ' (' + z.cost + ' ' + (z.currency || getCurrentCurrency()) + ')' : ' (غير متاح حالياً)');
                opt.setAttribute('data-cost', z.cost);
                opt.setAttribute('data-active', z.is_active);
                opt.setAttribute('data-currency', z.currency || getCurrentCurrency());
                if (selectedId && parseInt(selectedId) === parseInt(z.id)) {
                    opt.selected = true;
                }
                govSelect.appendChild(opt);
            });

            handleGovChange();
        }

        function handleGovChange() {
            const opt = govSelect.options[govSelect.selectedIndex];
            const curr = getCurrentCurrency();
            
            if (!opt || opt.disabled || !opt.value) {
                govErrorEl.classList.add('hidden');
                submitBtn.disabled = false;
                shippingValEl.innerText = 'يُحدد بعد اختيار المحافظة';
                shippingValEl.className = 'font-bold text-royal-dark bg-royal-sand px-2 py-0.5 rounded text-[10px]';
                totalValEl.innerText = baseSubtotal;
                return;
            }

            const cost = parseFloat(opt.getAttribute('data-cost')) || 0;
            const isActive = opt.getAttribute('data-active') === '1';

            if (!isActive) {
                govErrorEl.classList.remove('hidden');
                submitBtn.disabled = true;
                shippingValEl.innerText = 'غير متاح حالياً';
                shippingValEl.className = 'font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded text-[10px]';
                totalValEl.innerText = baseSubtotal;
            } else {
                govErrorEl.classList.add('hidden');
                submitBtn.disabled = false;
                shippingValEl.innerText = cost + ' ' + curr;
                shippingValEl.className = 'font-bold text-royal-darkgold bg-royal-sand px-2 py-0.5 rounded text-[10px]';
                totalValEl.innerText = (baseSubtotal + cost);
            }
        }

        // مستمعات الأحداث
        if (countrySelect) {
            countrySelect.addEventListener('change', function() {
                updateCurrencyLabels();
                populateGovernorates(this.value);
                if (typeof updatePaymentMethodsForCountry === 'function') {
                    updatePaymentMethodsForCountry(this.value);
                }
            });
        }

        if (currencySelect) {
            currencySelect.addEventListener('change', function() {
                updateCurrencyLabels();
            });
        }

        if (govSelect) {
            govSelect.addEventListener('change', handleGovChange);
        }

        // التهيئة الأولية
        if (countrySelect) {
            updateCurrencyLabels();
            populateGovernorates(countrySelect.value, loggedGovId);
            if (typeof updatePaymentMethodsForCountry === 'function') {
                updatePaymentMethodsForCountry(countrySelect.value);
            }
        }
    });
        
        // ================= برمجة الخريطة وجلب الإحداثيات =================
        let map = null;
        let marker = null;
        
        document.getElementById('btn-open-map').addEventListener('click', function() {
            const container = document.getElementById('map-container');
            container.classList.toggle('hidden');
            
            if (container.classList.contains('hidden')) return;
            
            if (!map) {
                // الإحداثيات الافتراضية: وسط القاهرة
                const defaultLat = 30.0444;
                const defaultLng = 31.2357;
                
                map = L.map('checkout-map').setView([defaultLat, defaultLng], 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);
                
                marker = L.marker([defaultLat, defaultLng], { draggable: true }).addTo(map);
                
                marker.on('dragend', function() {
                    const position = marker.getLatLng();
                    updateFieldsFromCoords(position.lat, position.lng);
                });
                
                map.on('click', function(e) {
                    marker.setLatLng(e.latlng);
                    updateFieldsFromCoords(e.latlng.lat, e.latlng.lng);
                });
                
                // جلب الموقع التلقائي للمستخدم
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        map.setView([lat, lng], 16);
                        marker.setLatLng([lat, lng]);
                        updateFieldsFromCoords(lat, lng);
                    }, function() {
                        updateFieldsFromCoords(defaultLat, defaultLng);
                    });
                } else {
                    updateFieldsFromCoords(defaultLat, defaultLng);
                }
            } else {
                setTimeout(() => {
                    map.invalidateSize();
                }, 100);
            }
        });
        
        // إعداد البحث في الخريطة
        document.getElementById('btn-map-search').addEventListener('click', function() {
            performMapSearch();
        });
        document.getElementById('map-search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                performMapSearch();
            }
        });
        
        function performMapSearch() {
            const query = document.getElementById('map-search-input').value.trim();
            if (!query) return;
            
            const btn = document.getElementById('btn-map-search');
            btn.innerText = "جاري...";
            btn.disabled = true;
            
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&accept-language=ar&limit=1`)
                .then(res => res.json())
                .then(data => {
                    btn.innerText = "بحث";
                    btn.disabled = false;
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);
                        map.setView([lat, lng], 16);
                        marker.setLatLng([lat, lng]);
                        updateFieldsFromCoords(lat, lng);
                    } else {
                        alert("عذراً، لم نجد نتائج لهذا البحث. جربي كتابة اسم الشارع أو المنطقة بشكل أوضح.");
                    }
                })
                .catch(err => {
                    console.error('Search error:', err);
                    btn.innerText = "بحث";
                    btn.disabled = false;
                });
        }
        
        function updateFieldsFromCoords(lat, lng) {
            const streetField = document.getElementById('addr_street');
            streetField.placeholder = "جاري جلب اسم الشارع والمنطقة من الخريطة... 📍";
            
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&accept-language=ar`)
                .then(res => res.json())
                .then(data => {
                    streetField.placeholder = "اسم الشارع / المنطقة / الميدان *";
                    if (data && data.address) {
                        const road = data.address.road || data.address.suburb || data.address.neighbourhood || '';
                        const city = data.address.city || data.address.town || data.address.village || '';
                        const county = data.address.county || '';
                        
                        streetField.value = [road, city, county].filter(Boolean).join('، ');
                        
                        // محاولة مطابقة المحافظة تلقائياً
                        const state = data.address.state || data.address.governorate || '';
                        if (state) {
                            const select = document.getElementById('gov-select');
                            const cleanState = state.replace('محافظة', '').trim();
                            for (let i = 0; i < select.options.length; i++) {
                                const optText = select.options[i].text;
                                if (cleanState.includes(optText) || optText.includes(cleanState)) {
                                    select.selectedIndex = i;
                                    select.dispatchEvent(new Event('change'));
                                    break;
                                }
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Geocoding error:', err);
                    streetField.placeholder = "اسم الشارع / المنطقة / الميدان *";
        // مزامنة السلة المتروكة في الخلفية عند كتابة العميل لبياناته
        let syncTimeout = null;
        function syncAbandonedCartData() {
            clearTimeout(syncTimeout);
            syncTimeout = setTimeout(() => {
                const nameInput = document.querySelector('input[name="name"]');
                const phoneInput = document.querySelector('input[name="phone"]');
                const emailInput = document.querySelector('input[name="email"]');
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

        const trackInputs = document.querySelectorAll('input[name="name"], input[name="phone"], input[name="email"], #gov-select');
        trackInputs.forEach(el => {
            el.addEventListener('input', syncAbandonedCartData);
            el.addEventListener('change', syncAbandonedCartData);
            el.addEventListener('blur', syncAbandonedCartData);
        });
    });
</script>

<?php
include 'footer.php';
?>
