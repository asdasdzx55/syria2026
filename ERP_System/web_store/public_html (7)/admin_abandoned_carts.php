<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// معالجة الإجراءات
if (isset($_GET['action'])) {
    $cart_id = (int)($_GET['id'] ?? 0);
    
    if ($_GET['action'] === 'delete' && $cart_id > 0) {
        $pdo->prepare("DELETE FROM abandoned_carts WHERE id = ?")->execute([$cart_id]);
        header("Location: admin_abandoned_carts.php?msg=deleted");
        exit;
    }
    
    if ($_GET['action'] === 'mark_recovered' && $cart_id > 0) {
        $pdo->prepare("UPDATE abandoned_carts SET status = 'recovered', recovery_sent_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$cart_id]);
        header("Location: admin_abandoned_carts.php?msg=recovered");
        exit;
    }
}

// دالة حساب الوقت المنقضي بشكل لطيف
function timeAgoArabic($datetime) {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return '-';
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'منذ لحظات';
    if ($diff < 3600) return 'منذ ' . floor($diff / 60) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff / 3600) . ' ساعة';
    if ($diff < 2592000) return 'منذ ' . floor($diff / 86400) . ' يوم';
    return date('Y-m-d', $timestamp);
}

// التصفية
$filter = $_GET['filter'] ?? 'all';
$where = "status != 'converted'";

if ($filter === 'with_phone') {
    $where .= " AND customer_phone IS NOT NULL AND customer_phone != ''";
} elseif ($filter === 'recovered') {
    $where = "status = 'recovered'";
} elseif ($filter === 'abandoned') {
    $where = "status = 'abandoned'";
}

$stmt = $pdo->query("SELECT * FROM abandoned_carts WHERE $where ORDER BY updated_at DESC");
$carts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// إحصائيات عامة
$total_abandoned_count = $pdo->query("SELECT COUNT(*) FROM abandoned_carts WHERE status = 'abandoned'")->fetchColumn();
$total_lost_revenue = $pdo->query("SELECT SUM(total_price) FROM abandoned_carts WHERE status = 'abandoned'")->fetchColumn() ?: 0;
$total_with_phone = $pdo->query("SELECT COUNT(*) FROM abandoned_carts WHERE status = 'abandoned' AND customer_phone IS NOT NULL AND customer_phone != ''")->fetchColumn();
$total_recovered_count = $pdo->query("SELECT COUNT(*) FROM abandoned_carts WHERE status = 'recovered'")->fetchColumn();

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <!-- هيدر الصفحة -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-royal-gold/10 pb-6">
        <div>
            <span class="text-royal-darkgold text-xs font-bold tracking-widest uppercase mb-1 block">SALES RECOVERY ENGINE</span>
            <h2 class="text-2xl md:text-3xl font-serif font-bold text-royal-dark flex items-center gap-2.5">
                <i class="fa-solid fa-cart-arrow-down text-royal-gold"></i> استعادة السلات المتروكة
            </h2>
            <p class="text-xs text-gray-400 mt-1 font-light">متابعة العملاء الذين أضافوا منتجات للسلة ولم يكملوا الطلب، والتواصل المباشر معهم عبر الواتساب لزيادة مبيعاتك.</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="bg-red-50 text-red-700 px-3.5 py-1.5 rounded-full text-xs font-bold border border-red-200 flex items-center gap-1.5">
                <i class="fa-solid fa-bell animate-pulse"></i> <?php echo $total_abandoned_count; ?> سلة متروكة حالياً
            </span>
        </div>
    </div>

    <!-- رسائل التنبيه -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="p-4 mb-6 rounded-2xl text-xs font-bold flex items-center gap-2 animate-fade-in bg-green-50 text-green-700 border border-green-200">
            <i class="fa-solid fa-circle-check text-lg"></i>
            <?php
            if ($_GET['msg'] === 'deleted') echo 'تم حذف السلة المتروكة بنجاح.';
            elseif ($_GET['msg'] === 'recovered') echo '🎉 تم تحديث حالة السلة وتسجيلها كـ "تمت الاستعادة بنجاح"!';
            ?>
        </div>
    <?php endif; ?>

    <!-- بطاقات الإحصائيات السريعة -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-cart-shopping text-red-500 text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">السلات المتروكة النشطة</h4>
            <span class="text-2xl font-serif font-bold text-royal-dark mt-1 block"><?php echo $total_abandoned_count; ?></span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-coins text-amber-500 text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">المبيعات المفقودة المحتملة</h4>
            <span class="text-2xl font-serif font-bold text-royal-darkgold mt-1 block"><?php echo number_format($total_lost_revenue, 2); ?> <small class="text-xs text-gray-500 font-sans">ج.م</small></span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-brands fa-whatsapp text-green-600 text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">سلات برقم هاتف للواتساب</h4>
            <span class="text-2xl font-serif font-bold text-green-700 mt-1 block"><?php echo $total_with_phone; ?></span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-circle-check text-blue-500 text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">سلات تم استعادتها</h4>
            <span class="text-2xl font-serif font-bold text-blue-700 mt-1 block"><?php echo $total_recovered_count; ?></span>
        </div>
    </div>

    <!-- أزرار التصفية -->
    <div class="flex overflow-x-auto gap-2 mb-6 border-b border-gray-200 pb-2 text-xs font-bold no-scrollbar">
        <a href="admin_abandoned_carts.php?filter=all" class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-1.5 <?php echo $filter === 'all' ? 'bg-royal-charcoal text-white shadow-md' : 'bg-white text-gray-600 hover:bg-royal-cream border border-gray-200'; ?>">
            <i class="fa-solid fa-list"></i> جميع السلات غير المكتملة
        </a>
        <a href="admin_abandoned_carts.php?filter=with_phone" class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-1.5 <?php echo $filter === 'with_phone' ? 'bg-green-700 text-white shadow-md' : 'bg-white text-green-800 hover:bg-green-50 border border-green-200'; ?>">
            <i class="fa-brands fa-whatsapp"></i> سلات برقم هاتف للتواصل (جاهزة للاستعادة)
        </a>
        <a href="admin_abandoned_carts.php?filter=abandoned" class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-1.5 <?php echo $filter === 'abandoned' ? 'bg-royal-charcoal text-white shadow-md' : 'bg-white text-gray-600 hover:bg-royal-cream border border-gray-200'; ?>">
            <i class="fa-solid fa-clock"></i> متروكة حالياً
        </a>
        <a href="admin_abandoned_carts.php?filter=recovered" class="px-4 py-2.5 rounded-xl transition-all flex items-center gap-1.5 <?php echo $filter === 'recovered' ? 'bg-blue-600 text-white shadow-md' : 'bg-white text-blue-700 hover:bg-blue-50 border border-blue-200'; ?>">
            <i class="fa-solid fa-circle-check"></i> تم استعادتها بنجاح
        </a>
    </div>

    <!-- جدول عرض السلات المتروكة -->
    <div class="bg-white rounded-3xl border border-royal-gold/15 shadow-sm overflow-hidden">
        <?php if (empty($carts)): ?>
            <div class="text-center py-16 px-4">
                <div class="w-16 h-16 rounded-full bg-green-50 text-green-600 flex items-center justify-center text-3xl mx-auto mb-3 shadow-inner">
                    <i class="fa-solid fa-check"></i>
                </div>
                <h4 class="font-serif font-bold text-base text-royal-dark mb-1">لا توجد سلات متروكة تطابق هذه التصفية حالياً</h4>
                <p class="text-xs text-gray-400 font-light max-w-sm mx-auto">سيقوم النظام بتسجيل وتحديث أي سلة يتركها الزوار في صفحة الشراء تلقائياً هنا.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-royal-sand/40 text-royal-dark font-bold border-b border-gray-200">
                        <tr>
                            <th class="p-4">العميل / الاتصال</th>
                            <th class="p-4">المنتجات في السلة</th>
                            <th class="p-4">إجمالي القيمة</th>
                            <th class="p-4">الوقت المنقضي</th>
                            <th class="p-4">الحالة</th>
                            <th class="p-4 text-center">التواصل والاستعادة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-600">
                        <?php foreach ($carts as $c): 
                            $items = json_decode($c['cart_data'], true) ?: [];
                            $clean_phone = preg_replace('/[^0-9]/', '', $c['customer_phone'] ?? '');
                            
                            // إعداد رقم الواتساب بالصيغة الدولية المصرية
                            $wa_phone = $clean_phone;
                            if (strpos($wa_phone, '01') === 0) {
                                $wa_phone = '2' . $wa_phone;
                            } elseif (strpos($wa_phone, '1') === 0 && strlen($wa_phone) === 10) {
                                $wa_phone = '20' . $wa_phone;
                            }

                            // تجميع أسماء المنتجات للرسالة الترويجية
                            $product_names = [];
                            foreach ($items as $it) {
                                $product_names[] = $it['name'] . ' (كمية: ' . ($it['qty'] ?? 1) . ')';
                            }
                            $prods_text = implode('، ', array_slice($product_names, 0, 2));
                            if (count($product_names) > 2) {
                                $prods_text .= ' ومنتجات أخرى';
                            }

                            $cust_name = !empty($c['customer_name']) ? $c['customer_name'] : 'عميلنا العزيز';
                            $cur_sname = $settings['store_name'] ?? 'المتجر الإلكتروني';
                            $wa_msg = "أهلاً بك {$cust_name} في متجر {$cur_sname} ✨\n\nلاحظنا أن لديك منتجات مميزة في سلتك لم يكتمل طلبها بعد:\n🛒 {$prods_text}\n\nيسعدنا مساعدتك في إتمام طلبك وتوصيله إليك بأسرع وقت! 🚚\n\nهل ترغب في تأكيد الطلب الآن؟";
                            $wa_url = "https://api.whatsapp.com/send?phone={$wa_phone}&text=" . urlencode($wa_msg);
                        ?>
                        <tr class="hover:bg-royal-sand/10 transition items-center">
                            <!-- العميل -->
                            <td class="p-4">
                                <div class="font-bold text-royal-dark text-xs flex items-center gap-1.5">
                                    <i class="fa-solid fa-user text-royal-darkgold text-[10px]"></i>
                                    <?php echo htmlspecialchars($c['customer_name'] ?: 'زائر غير مسجل الاسم'); ?>
                                </div>
                                <?php if (!empty($c['customer_phone'])): ?>
                                    <div class="font-mono text-gray-700 text-[11px] mt-1 flex items-center gap-1" dir="ltr">
                                        <i class="fa-solid fa-phone text-[10px] text-gray-400"></i>
                                        <?php echo htmlspecialchars($c['customer_phone']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($c['governorate'])): ?>
                                    <div class="text-[10px] text-gray-400 mt-0.5">
                                        📍 <?php echo htmlspecialchars($c['governorate']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <!-- المنتجات -->
                            <td class="p-4 max-w-xs">
                                <div class="space-y-1.5">
                                    <?php foreach (array_slice($items, 0, 3) as $it): ?>
                                        <div class="flex items-center gap-2">
                                            <?php if (!empty($it['image'])): ?>
                                                <img src="<?php echo htmlspecialchars($it['image']); ?>" class="w-8 h-8 rounded-lg object-cover border border-gray-200 bg-gray-50 shrink-0" loading="lazy" decoding="async">
                                            <?php endif; ?>
                                            <div class="truncate text-[11px] text-royal-charcoal font-semibold">
                                                <?php echo htmlspecialchars($it['name']); ?>
                                                <span class="text-[10px] text-gray-400 font-mono">(×<?php echo $it['qty'] ?? 1; ?>)</span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($items) > 3): ?>
                                        <span class="text-[10px] text-royal-darkgold font-bold">+ <?php echo count($items) - 3; ?> منتجات إضافية</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- إجمالي القيمة -->
                            <td class="p-4 font-serif font-bold text-royal-dark text-sm">
                                <?php echo number_format($c['total_price'], 2); ?> <small class="text-[10px] text-gray-500 font-sans">ج.م</small>
                            </td>

                            <!-- الوقت المنقضي -->
                            <td class="p-4">
                                <span class="font-bold text-gray-600 block text-[11px]">
                                    <?php echo timeAgoArabic($c['updated_at']); ?>
                                </span>
                                <span class="text-[9px] text-gray-400 font-mono block">
                                    <?php echo date('Y-m-d h:i A', strtotime($c['updated_at'])); ?>
                                </span>
                            </td>

                            <!-- الحالة -->
                            <td class="p-4">
                                <?php if ($c['status'] === 'recovered'): ?>
                                    <span class="bg-green-100 text-green-800 text-[10px] px-2.5 py-1 rounded-full font-bold flex items-center gap-1 w-fit">
                                        <i class="fa-solid fa-check"></i> تمت الاستعادة
                                    </span>
                                <?php else: ?>
                                    <span class="bg-red-50 text-red-700 border border-red-200 text-[10px] px-2.5 py-1 rounded-full font-bold flex items-center gap-1 w-fit">
                                        <i class="fa-solid fa-clock"></i> متروكة
                                    </span>
                                <?php endif; ?>
                            </td>

                            <!-- أزرار الإجراءات والواتساب -->
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <?php if (!empty($clean_phone)): ?>
                                        <a href="<?php echo $wa_url; ?>" target="_blank" onclick="fetch('admin_abandoned_carts.php?action=mark_recovered&id=<?php echo $c['id']; ?>')" class="bg-[#25D366] hover:bg-[#20ba5a] text-white text-[11px] font-bold px-3 py-1.5 rounded-xl shadow-sm transition-all flex items-center gap-1.5" title="مراسلة العميل بالواتساب وإرسال كود الخصم">
                                            <i class="fa-brands fa-whatsapp text-sm"></i> استعادة عبر الواتساب
                                        </a>
                                    <?php else: ?>
                                        <span class="text-[10px] text-gray-400 bg-gray-100 px-2.5 py-1 rounded-lg">بدون رقم هاتف</span>
                                    <?php endif; ?>

                                    <?php if ($c['status'] !== 'recovered'): ?>
                                        <a href="admin_abandoned_carts.php?action=mark_recovered&id=<?php echo $c['id']; ?>" class="bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-700 text-[11px] font-bold p-1.5 px-2.5 rounded-lg border border-blue-200 shadow-2xs transition" title="تحديد كـ تمت الاستعادة">
                                            <i class="fa-solid fa-check"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="admin_abandoned_carts.php?action=delete&id=<?php echo $c['id']; ?>" onclick="return confirm('حذف هذا السجل نهائياً؟')" class="bg-red-50 hover:bg-red-600 hover:text-white text-red-600 text-[11px] font-bold p-1.5 px-2.5 rounded-lg border border-red-200 shadow-2xs transition" title="حذف السلة">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include 'footer.php';
?>
