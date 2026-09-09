<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

include 'header.php';
include 'admin_nav.php';

// 1. حساب الإحصائيات الأساسية
// إجمالي المبيعات (باستثناء الملغي)
$total_sales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status != 'ملغي'")->fetchColumn() ?: 0;

// إجمالي الطلبات
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() ?: 0;

// طلبات قيد التنفيذ أو تم التوصيل أو شحن
$active_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('جديد', 'قيد التنفيذ', 'شُحن')")->fetchColumn() ?: 0;

// متوسط قيمة السلة المطلوبة
$avg_order_value = $pdo->query("SELECT AVG(total_price) FROM orders WHERE status != 'ملغي'")->fetchColumn() ?: 0;
$avg_order_value = round($avg_order_value, 1);

// عدد المنتجات الكلي
$total_products = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() ?: 0;

// عدد كوبونات الخصم الفعالة
$total_coupons = $pdo->query("SELECT COUNT(*) FROM coupons WHERE is_active = 1")->fetchColumn() ?: 0;

// 2. إحصائيات المبيعات حسب المحافظات (أعلى 5)
$gov_stats = $pdo->query("
    SELECT governorate, COUNT(*) as cnt, SUM(total_price) as total 
    FROM orders 
    WHERE status != 'ملغي' 
    GROUP BY governorate 
    ORDER BY total DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// حساب أقصى قيمة مبيعات للمحافظات لاستخدامها كنسبة مئوية في البار تشارت
$max_gov_total = 0;
foreach ($gov_stats as $gs) {
    if ($gs['total'] > $max_gov_total) {
        $max_gov_total = $gs['total'];
    }
}

// 3. إحصائيات المبيعات حسب حالة الطلب
$status_stats = $pdo->query("
    SELECT status, COUNT(*) as cnt, SUM(total_price) as total 
    FROM orders 
    GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);

// حساب أقصى عدد طلبات للحالة لاستخدامه كنسبة مئوية في البار تشارت
$max_status_count = 0;
foreach ($status_stats as $ss) {
    if ($ss['cnt'] > $max_status_count) {
        $max_status_count = $ss['cnt'];
    }
}

// 4. إحصائيات وتتبع العملاء والزيارات (Visitor & Customer Tracking)
$today_date = date('Y-m-d');
$total_visits = $pdo->query("SELECT COUNT(*) FROM visitor_logs")->fetchColumn() ?: 0;
$today_visits = $pdo->query("SELECT COUNT(*) FROM visitor_logs WHERE DATE(created_at) = '$today_date'")->fetchColumn() ?: 0;

// عدد الزوار الفريدين (Unique Visitors)
$unique_visitors = $pdo->query("SELECT COUNT(DISTINCT ip_address) FROM visitor_logs")->fetchColumn() ?: 0;

// إجمالي العملاء المسجلين بالمتجر
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn() ?: 0;

// أحدث 10 زيارات ونشاط مباشر للعملاء بالمتجر
$recent_visits = $pdo->query("
    SELECT v.*, u.username 
    FROM visitor_logs v 
    LEFT JOIN users u ON v.user_id = u.id 
    ORDER BY v.id DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// أعلى العملاء شِراءً (Top VIP Customers)
$top_customers = $pdo->query("
    SELECT customer_name, customer_phone, COUNT(*) as order_count, SUM(total_price) as total_spent 
    FROM orders 
    WHERE status != 'ملغي' 
    GROUP BY customer_phone 
    ORDER BY total_spent DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">لوحة الإحصائيات ومركز البيانات</h2>
            <p class="text-xs text-gray-500 mt-1 font-medium">مؤشرات الأداء والمبيعات والمخزون المركزي لـ <?php echo htmlspecialchars($settings['store_name'] ?? 'سوبر ماركت المنزل السوري'); ?>.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="https://asdasdzx55.github.io/urban-octo-chainsaw/pos/" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-3 rounded-2xl font-black text-sm flex items-center gap-2 shadow-lg shadow-emerald-900/20 transition active:scale-95 border border-emerald-500 animate-pulse">
                <i class="fa-solid fa-cash-register text-amber-300 text-lg"></i>
                <span>فتح كاشير الويب (Web POS) ⚡</span>
            </a>
        </div>
    </div>

    <!-- كارت إعلان وبوابة كاشير الويب الشامل -->
    <div class="bg-gradient-to-r from-emerald-900 via-slate-900 to-slate-950 rounded-3xl p-6 mb-8 text-white shadow-xl border border-emerald-500/30 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="space-y-2 text-center md:text-right">
            <div class="inline-flex items-center gap-2 bg-emerald-500/20 border border-emerald-500/30 px-3 py-1 rounded-full text-xs font-bold text-emerald-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <span>متوافق 100% مع الهواتف الذكية والأجهزة اللوحية واللابتوب</span>
            </div>
            <h3 class="text-xl md:text-2xl font-black text-white">نظام الكاشير المباشر مع ماسح الباركود بالكاميرا 📷</h3>
            <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
                يمكنك الآن تشغيل الكاشير والبيع المباشر من هاتفك المحمول أو اللابتوب مع دعم مسح الباركود بالكاميرا أو أجهزة الباركود، خصم المخزون اللحظي، وطباعة الفواتير الحرارية مع المزامنة التلقائية مع أجهزة الكاشير المحلية.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row items-center gap-3 flex-shrink-0">
            <a href="https://asdasdzx55.github.io/urban-octo-chainsaw/pos/" target="_blank" class="w-full sm:w-auto bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black px-6 py-3.5 rounded-2xl text-sm transition shadow-lg flex items-center justify-center gap-2">
                <i class="fa-solid fa-play"></i>
                <span>بدء البيع الآن</span>
            </a>
            <a href="api_sync.php?action=ping" target="_blank" class="w-full sm:w-auto bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold px-4 py-3.5 rounded-2xl text-xs transition border border-slate-700 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-satellite-dish text-emerald-400"></i>
                <span>فحص مركز المزامنة (API)</span>
            </a>
        </div>
    </div>

    <!-- كروت الإحصائيات (KPI Cards - المبيعات والأرباح) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <!-- كارت الأرباح -->
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-gray-400 font-bold block">إجمالي الأرباح</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo number_format($total_sales); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
            </div>
            <div class="w-10 h-10 bg-green-50 text-green-600 rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-money-bill-trend-up"></i>
            </div>
        </div>

        <!-- كارت الطلبات -->
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-gray-400 font-bold block">عدد الطلبات الإجمالي</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo $total_orders; ?> طلب</span>
            </div>
            <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-cart-shopping"></i>
            </div>
        </div>

        <!-- كارت قيمة السلة -->
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-gray-400 font-bold block">متوسط قيمة السلة</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo number_format($avg_order_value); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
            </div>
            <div class="w-10 h-10 bg-yellow-50 text-royal-darkgold rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-chart-bar"></i>
            </div>
        </div>

        <!-- كارت المنتجات -->
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-gray-400 font-bold block">الأصناف بالمتجر</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo $total_products; ?> صنف</span>
            </div>
            <div class="w-10 h-10 bg-purple-50 text-purple-600 rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-box-open"></i>
            </div>
        </div>
    </div>

    <!-- كروت إحصائيات تتبع الزوار والعملاء (Visitor & Customer Analytics Cards) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <!-- زيارات اليوم -->
        <div class="bg-royal-cream/60 p-5 rounded-2xl border border-royal-gold/15 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-royal-darkgold font-bold block">زيارات اليوم</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo number_format($today_visits); ?> زيارة</span>
            </div>
            <div class="w-10 h-10 bg-royal-gold/20 text-royal-darkgold rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-eye"></i>
            </div>
        </div>

        <!-- إجمالي الزيارات -->
        <div class="bg-royal-cream/60 p-5 rounded-2xl border border-royal-gold/15 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-royal-darkgold font-bold block">إجمالي تصفح المتجر</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo number_format($total_visits); ?> صفحة</span>
            </div>
            <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-chart-line"></i>
            </div>
        </div>

        <!-- الزوار الفريدون -->
        <div class="bg-royal-cream/60 p-5 rounded-2xl border border-royal-gold/15 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-royal-darkgold font-bold block">الزوار الفريدون (Unique IPs)</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo number_format($unique_visitors); ?> زائر</span>
            </div>
            <div class="w-10 h-10 bg-indigo-50 text-indigo-600 rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <!-- العملاء المسجلون -->
        <div class="bg-royal-cream/60 p-5 rounded-2xl border border-royal-gold/15 shadow-sm flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-[10px] text-royal-darkgold font-bold block">العملاء المسجلون</span>
                <span class="text-lg md:text-xl font-serif font-bold text-royal-dark block"><?php echo number_format($total_users); ?> حساب</span>
            </div>
            <div class="w-10 h-10 bg-amber-50 text-amber-600 rounded-xl flex items-center justify-center text-lg">
                <i class="fa-solid fa-user-check"></i>
            </div>
        </div>
    </div>

    <!-- الرسوم البيانية والجداول الإحصائية -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        
        <!-- المحافظات الأكثر شراءً (CSS Horizontal Bar Chart) -->
        <div class="bg-white p-6 rounded-2xl border border-royal-gold/10 shadow-sm">
            <h3 class="font-serif font-bold text-royal-dark text-base mb-6 border-b pb-3 flex items-center gap-2">
                <i class="fa-solid fa-map-location-dot text-royal-darkgold"></i> أعلى 5 محافظات مبيعاً
            </h3>
            
            <?php if (empty($gov_stats)): ?>
                <div class="text-center py-10 text-gray-450 text-xs">لا توجد بيانات كافية حالياً.</div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($gov_stats as $gs): 
                        $pct = $max_gov_total > 0 ? round(($gs['total'] / $max_gov_total) * 100) : 0;
                    ?>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-royal-dark"><?php echo htmlspecialchars($gs['governorate']); ?></span>
                                <span class="font-serif text-gray-400"><?php echo number_format($gs['total']); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?> (<?php echo $gs['cnt']; ?> طلب)</span>
                            </div>
                            <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gold-gradient rounded-full" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- توزيع الحالات والطلبات النشطة (CSS Horizontal Bar Chart) -->
        <div class="bg-white p-6 rounded-2xl border border-royal-gold/10 shadow-sm">
            <h3 class="font-serif font-bold text-royal-dark text-base mb-6 border-b pb-3 flex items-center gap-2">
                <i class="fa-solid fa-bars-progress text-royal-darkgold"></i> توزيع حالات طلبات الشراء
            </h3>
            
            <?php if (empty($status_stats)): ?>
                <div class="text-center py-10 text-gray-450 text-xs">لا توجد بيانات كافية حالياً.</div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($status_stats as $ss): 
                        $pct = $max_status_count > 0 ? round(($ss['cnt'] / $max_status_count) * 100) : 0;
                        
                        // اختيار لون البار حسب الحالة
                        $bar_color = 'bg-royal-charcoal';
                        if ($ss['status'] == 'جديد') {
                            $bar_color = 'bg-red-500';
                        } elseif ($ss['status'] == 'تم التوصيل') {
                            $bar_color = 'bg-green-500';
                        } elseif ($ss['status'] == 'ملغي') {
                            $bar_color = 'bg-gray-300';
                        } elseif ($ss['status'] == 'قيد التنفيذ') {
                            $bar_color = 'bg-yellow-500';
                        } elseif ($ss['status'] == 'شُحن') {
                            $bar_color = 'bg-blue-500';
                        }
                    ?>
                        <div class="space-y-1">
                            <div class="flex justify-between text-xs font-semibold">
                                <span class="text-royal-dark"><?php echo htmlspecialchars($ss['status']); ?></span>
                                <span class="font-serif text-gray-400"><?php echo $ss['cnt']; ?> طلب (<?php echo number_format($ss['total']); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?>)</span>
                            </div>
                            <div class="w-full h-3 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full <?php echo $bar_color; ?> rounded-full" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- جدول الطلبات النشطة الأخيرة -->
    <div class="bg-white border border-royal-gold/10 shadow-sm rounded-2xl mt-8 overflow-hidden">
        <div class="p-5 border-b border-royal-gold/10 bg-royal-sand/15">
            <h3 class="font-serif font-bold text-royal-dark text-base">الطلبات الجارية الأخيرة (جديد / تنفيذ / شُحن)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-gray-55/30 text-gray-500 border-b border-royal-gold/10 font-bold">
                    <tr>
                        <th class="p-4 font-bold">رقم الطلب</th>
                        <th class="p-4 font-bold">العميل</th>
                        <th class="p-4 font-bold">المحافظة</th>
                        <th class="p-4 font-bold">المبلغ</th>
                        <th class="p-4 font-bold text-center">الحالة</th>
                        <th class="p-4 font-bold text-center">تاريخ الطلب</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    <?php 
                    $recent_active = $pdo->query("
                        SELECT * FROM orders 
                        WHERE status IN ('جديد', 'قيد التنفيذ', 'شُحن') 
                        ORDER BY id DESC 
                        LIMIT 5
                    ")->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($recent_active)): 
                    ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-400 font-serif">لا توجد طلبات جارية حالياً.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($recent_active as $ra): ?>
                            <tr class="hover:bg-royal-cream/35 transition-colors">
                                <td class="p-4 font-serif font-bold text-gray-400">#<?php echo $ra['id']; ?></td>
                                <td class="p-4 font-bold text-royal-dark"><a href="admin_order_details.php?id=<?php echo $ra['id']; ?>" class="hover:text-royal-gold"><?php echo htmlspecialchars($ra['customer_name']); ?></a></td>
                                <td class="p-4">
                                    <span class="bg-royal-sand text-royal-darkgold px-2.5 py-0.5 rounded border border-royal-gold/5 font-bold text-[10px]"><?php echo htmlspecialchars($ra['governorate']); ?></span>
                                </td>
                                <td class="p-4 font-serif text-royal-darkgold font-bold text-sm"><?php echo $ra['total_price']; ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></td>
                                <td class="p-4 text-center">
                                    <?php 
                                    $c_class = 'bg-yellow-50 text-yellow-700 border border-yellow-100';
                                    if ($ra['status'] == 'جديد') $c_class = 'bg-red-50 text-red-700 border border-red-100';
                                    if ($ra['status'] == 'شُحن') $c_class = 'bg-blue-50 text-blue-700 border border-blue-100';
                                    ?>
                                    <span class="px-2.5 py-0.5 rounded-full font-bold text-[9px] <?php echo $c_class; ?>"><?php echo $ra['status']; ?></span>
                                </td>
                                <td class="p-4 text-center text-gray-400 font-serif" dir="ltr"><?php echo date('Y-m-d h:i A', strtotime($ra['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- جدول تتبع نشاط العملاء المباشر وكبار العملاء -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mt-8">
        
        <!-- سجل تصفح الزوار والعملاء (Live Customer Activity Log - 2 Cols) -->
        <div class="lg:col-span-2 bg-white border border-royal-gold/10 shadow-sm rounded-2xl overflow-hidden">
            <div class="p-5 border-b border-royal-gold/10 bg-royal-sand/20 flex justify-between items-center">
                <h3 class="font-serif font-bold text-royal-dark text-base flex items-center gap-2">
                    <i class="fa-solid fa-person-walking-arrow-right text-royal-darkgold"></i> سجل نشاط وتصفح العملاء المباشر
                </h3>
                <span class="text-[10px] bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full font-bold animate-pulse">تحديث مباشر ●</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-royal-sand/10 text-gray-500 border-b border-royal-gold/10 font-bold">
                        <tr>
                            <th class="p-4 font-bold">العميل / الزائر</th>
                            <th class="p-4 font-bold">الصفحة التي تصفحها</th>
                            <th class="p-4 font-bold text-center">الجهاز</th>
                            <th class="p-4 font-bold text-center">تاريخ الزيارة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        <?php if (empty($recent_visits)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-gray-400">لا توجد زيارات مسجلة حديثاً.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($recent_visits as $rv): ?>
                                <tr class="hover:bg-royal-cream/35 transition-colors">
                                    <td class="p-4 font-bold text-royal-dark">
                                        <?php if (!empty($rv['username'])): ?>
                                            <span class="text-royal-darkgold font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-user-check text-xs"></i> <?php echo htmlspecialchars($rv['username']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400 font-normal flex items-center gap-1">
                                                <i class="fa-regular fa-circle-user text-xs"></i> زائر (<?php echo htmlspecialchars($rv['ip_address']); ?>)
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 font-mono text-[11px] text-gray-600">
                                        <a href="<?php echo htmlspecialchars($rv['page_url']); ?>" target="_blank" class="hover:text-royal-gold hover:underline">
                                            /<?php echo htmlspecialchars($rv['page_url']); ?>
                                        </a>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if ($rv['device_type'] == 'جوال'): ?>
                                            <span class="bg-purple-50 text-purple-700 border border-purple-100 px-2 py-0.5 rounded text-[10px] font-bold"><i class="fa-solid fa-mobile-screen-button"></i> جوال</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-700 border border-gray-200 px-2 py-0.5 rounded text-[10px] font-bold"><i class="fa-solid fa-desktop"></i> كمبيوتر</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-center text-gray-400 font-serif text-[11px]" dir="ltr">
                                        <?php echo date('m-d h:i A', strtotime($rv['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- قائمة كبار العملاء (Top VIP Customers - 1 Col) -->
        <div class="bg-white border border-royal-gold/10 shadow-sm rounded-2xl overflow-hidden flex flex-col justify-between">
            <div>
                <div class="p-5 border-b border-royal-gold/10 bg-royal-sand/20">
                    <h3 class="font-serif font-bold text-royal-dark text-base flex items-center gap-2">
                        <i class="fa-solid fa-crown text-royal-gold"></i> كبار العملاء والأكثر شراءً (VIP)
                    </h3>
                </div>
                <div class="divide-y divide-gray-100 p-2">
                    <?php if (empty($top_customers)): ?>
                        <div class="p-8 text-center text-gray-400 text-xs">لا توجد طلبات مكتملة كافية بعد.</div>
                    <?php else: ?>
                        <?php foreach($top_customers as $index => $tc): ?>
                            <div class="p-3.5 flex items-center justify-between hover:bg-royal-cream/40 transition-colors rounded-xl">
                                <div class="flex items-center gap-3">
                                    <span class="w-7 h-7 rounded-full bg-royal-gold/20 text-royal-darkgold font-bold font-serif text-xs flex items-center justify-center">
                                        #<?php echo $index + 1; ?>
                                    </span>
                                    <div>
                                        <div class="font-bold text-xs text-royal-dark"><?php echo htmlspecialchars($tc['customer_name']); ?></div>
                                        <div class="text-[10px] text-gray-400 font-serif" dir="ltr"><?php echo htmlspecialchars($tc['customer_phone']); ?></div>
                                    </div>
                                </div>
                                <div class="text-left">
                                    <div class="font-serif font-bold text-xs text-royal-darkgold"><?php echo number_format($tc['total_spent']); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></div>
                                    <div class="text-[9px] text-gray-400 font-bold"><?php echo $tc['order_count']; ?> طلبات شراء</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="p-4 bg-royal-sand/15 border-t text-center text-[10px] text-gray-400 font-bold">
                💡 يتم ترتيب العملاء تلقائياً بناءً على إجمالي المشتريات والطلبات المكتملة.
            </div>
        </div>

    </div>

</div>

<?php
include 'footer.php';
?>
