<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// تصدير الطلبات لملف Excel/CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Store_Orders_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    // إرسال علامة UTF-8 BOM لبرنامج إكسيل لكي يقرأ الحروف العربية بشكل صحيح
    fwrite($output, "\xEF\xBB\xBF");
    
    // كتابة العناوين
    fputcsv($output, [
        'رقم الطلب', 'تاريخ الطلب', 'اسم العميل', 'رقم الهاتف', 'البريد الإلكتروني', 
        'الدولة', 'المحافظة', 'العملة', 'العنوان بالتفصيل', 'تفاصيل المنتجات', 'مجموع المشتريات', 
        'قيمة الشحن', 'قيمة الخصم', 'كوبون الخصم', 'المبلغ الإجمالي', 'حالة الطلب'
    ]);
    
    $export_orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($export_orders as $o) {
        $subtotal = $o['total_price'] - $o['shipping_cost'] + $o['discount_amount'];
        fputcsv($output, [
            '#' . $o['id'],
            date('Y-m-d H:i', strtotime($o['created_at'])),
            $o['customer_name'],
            $o['customer_phone'],
            $o['customer_email'] ?? '',
            $o['country'] ?? 'مصر',
            $o['governorate'],
            $o['currency'] ?? 'ج.م',
            $o['customer_address'],
            str_replace("\n", " | ", trim($o['order_details'])),
            $subtotal,
            $o['shipping_cost'],
            $o['discount_amount'],
            $o['coupon_code'] ?? '',
            $o['total_price'],
            $o['status']
        ]);
    }
    fclose($output);
    exit;
}

include 'header.php';
include 'admin_nav.php';

$orders = $pdo->query("SELECT * FROM orders ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-8">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">طلبات العملاء</h2>
            <p class="text-xs text-gray-400 mt-1 font-light">مراجعة ومعالجة فواتير وطلبات المشتريات ومتابعة الشحن والتوصيل.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="admin_orders.php?action=export" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal text-xs font-bold py-2.5 px-5 rounded-xl shadow-md transition-all flex items-center gap-2"><i class="fa-solid fa-file-csv text-base"></i> تصدير الطلبات (Excel)</a>
            <div class="text-xs font-bold text-gray-500 bg-white border border-royal-gold/15 py-2.5 px-4 rounded-xl h-fit">
                إجمالي الطلبات: <span class="text-royal-darkgold font-serif text-sm"><?php echo count($orders); ?></span>
            </div>
        </div>
    </div>
    
    <div class="bg-white shadow-sm border border-royal-gold/10 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-royal-sand/40 text-gray-500 border-b border-royal-gold/10 font-bold">
                    <tr>
                        <th class="p-5 font-bold">رقم الطلب</th>
                        <th class="p-5 font-bold">العميل</th>
                        <th class="p-5 font-bold">الدولة والمحافظة</th>
                        <th class="p-5 font-bold">المبلغ المطلوب</th>
                        <th class="p-5 font-bold text-center">الحالة</th>
                        <th class="p-5 font-bold text-center">التاريخ</th>
                        <th class="p-5 font-bold text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    <?php if(empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="p-10 text-center text-gray-400 font-serif">لا توجد أي طلبات مسجلة في المتجر حتى الآن.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($orders as $o): ?>
                        <tr class="hover:bg-royal-cream/35 transition-colors">
                            <td class="p-5 text-gray-400 font-bold font-serif">#<?php echo $o['id']; ?></td>
                            <td class="p-5 font-bold text-royal-dark"><?php echo htmlspecialchars($o['customer_name']); ?></td>
                            <td class="p-5">
                                <span class="bg-royal-sand text-royal-darkgold px-2.5 py-1 rounded-lg font-bold text-[10px]">
                                    <?php echo htmlspecialchars((!empty($o['country']) ? $o['country'] . ' - ' : '') . $o['governorate']); ?>
                                </span>
                                <?php if(!empty($o['delivery_distance_km'])): ?>
                                    <span class="block mt-1 text-[9px] text-emerald-700 font-bold">
                                        <i class="fa-solid fa-route"></i> <?php echo $o['delivery_distance_km']; ?> كم
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-5 font-serif text-royal-darkgold font-bold text-sm">
                                <?php echo $o['total_price']; ?> <?php echo htmlspecialchars($o['currency'] ?: ($settings['store_currency'] ?? 'ج.م')); ?>
                            </td>
                            <td class="p-5 text-center">
                                <?php 
                                $status_class = 'bg-yellow-50 text-yellow-700 border border-yellow-100';
                                if($o['status'] == 'جديد') {
                                    $status_class = 'bg-red-50 text-red-700 border border-red-100';
                                } elseif($o['status'] == 'تم التوصيل') {
                                    $status_class = 'bg-green-50 text-green-700 border border-green-100';
                                } elseif($o['status'] == 'ملغي') {
                                    $status_class = 'bg-gray-100 text-gray-500 border border-gray-200';
                                }
                                ?>
                                <span class="px-3 py-1 rounded-full text-[10px] font-bold <?php echo $status_class; ?>"><?php echo $o['status']; ?></span>
                            </td>
                            <td class="p-5 text-center text-gray-400 text-[10px] font-serif" dir="ltr"><?php echo date('Y-m-d h:i A', strtotime($o['created_at'])); ?></td>
                            <td class="p-5 text-center">
                                <a href="admin_order_details.php?id=<?php echo $o['id']; ?>" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-4 py-2 rounded-lg hover:shadow transition-all inline-block font-bold">عرض الفاتورة</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
