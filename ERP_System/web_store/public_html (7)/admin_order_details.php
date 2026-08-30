<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: admin_orders.php");
    exit;
}
$order_id = (int)$_GET['id'];

// معالجة تحديث حالة الطلب
if (isset($_POST['update_order_status'])) {
    $status = $_POST['status'];
    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $order_id]);
    header("Location: admin_order_details.php?id=$order_id&msg=status_updated");
    exit;
}

// جلب تفاصيل الفاتورة
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$o = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$o) {
    die("الطلب غير موجود.");
}

$order_items = array_filter(explode("\n", trim($o['order_details'])));
$subtotal_products = $o['total_price'] - $o['shipping_cost'] + $o['discount_amount'];

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-4xl animate-fade-in">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">تفاصيل الطلب #<?php echo $o['id']; ?></h2>
            <p class="text-xs text-gray-400 mt-1 font-light">راجعي تفاصيل العميل، المشتريات، وحالة الشحن.</p>
        </div>
        <a href="admin_orders.php" class="text-xs font-bold text-gray-500 hover:text-royal-gold transition-colors flex items-center gap-1"><i class="fa-solid fa-arrow-right"></i> العودة للطلبات</a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'status_updated'): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i> تم تحديث حالة الطلب بنجاح!
        </div>
    <?php endif; ?>

    <div class="bg-white border border-royal-gold/10 shadow-sm rounded-2xl overflow-hidden">
        <!-- ترويسة الفاتورة -->
        <div class="bg-royal-charcoal text-white p-6 flex justify-between items-center border-b border-royal-gold/15">
            <div>
                <h3 class="text-lg font-serif font-bold text-royal-gold">فاتورة مشتريات</h3>
                <p class="text-[10px] text-gray-400 font-serif mt-1" dir="ltr"><?php echo date('Y-m-d h:i A', strtotime($o['created_at'])); ?></p>
            </div>
            <div class="text-center">
                <span class="px-4 py-1.5 rounded-full text-xs font-bold bg-white text-royal-dark shadow-md"><?php echo $o['status']; ?></span>
            </div>
        </div>

        <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-10">
            <!-- بيانات العميل والتوصيل -->
            <div>
                <h4 class="font-serif font-bold text-royal-dark mb-4 border-b pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-user-tie text-royal-darkgold"></i> بيانات المستلم والتوصيل
                </h4>
                <ul class="space-y-3.5 text-xs text-gray-700 font-medium">
                    <li class="flex gap-2"><span class="font-bold w-20 text-gray-400">الاسم:</span> <span class="text-royal-dark font-semibold"><?php echo htmlspecialchars($o['customer_name']); ?></span></li>
                    <?php if(!empty($o['customer_email'])): ?>
                        <li class="flex gap-2"><span class="font-bold w-20 text-gray-400">البريد:</span> <span class="text-royal-dark font-semibold font-serif"><?php echo htmlspecialchars($o['customer_email']); ?></span></li>
                    <?php endif; ?>
                    <li class="flex gap-2"><span class="font-bold w-20 text-gray-400">رقم الهاتف:</span> <span class="text-royal-dark font-semibold font-serif" dir="ltr"><?php echo htmlspecialchars($o['customer_phone']); ?></span></li>
                    <?php if(!empty($o['country'])): ?>
                        <li class="flex gap-2"><span class="font-bold w-20 text-gray-400">الدولة:</span> <span class="bg-royal-sand text-royal-darkgold px-2.5 py-0.5 rounded-lg border border-royal-gold/20 font-bold"><?php echo htmlspecialchars($o['country']); ?></span></li>
                    <?php endif; ?>
                    <li class="flex gap-2"><span class="font-bold w-20 text-gray-400">المحافظة:</span> <span><span class="bg-yellow-100/50 text-yellow-800 px-2.5 py-0.5 rounded-lg border border-yellow-200 font-bold"><?php echo htmlspecialchars($o['governorate']); ?></span></span></li>
                    <li class="flex gap-2"><span class="font-bold w-20 text-gray-400">العنوان:</span> <span class="text-royal-dark leading-relaxed font-light"><?php echo htmlspecialchars($o['customer_address']); ?></span></li>
                    <?php if(!empty($o['delivery_distance_km'])): ?>
                        <li class="flex gap-2 items-center">
                            <span class="font-bold w-20 text-gray-400">المسافة:</span>
                            <span class="bg-emerald-50 text-emerald-800 px-2.5 py-0.5 rounded-lg border border-emerald-200 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-route text-emerald-600"></i> <?php echo $o['delivery_distance_km']; ?> كم من المتجر (شحن بالمسافة)
                            </span>
                        </li>
                    <?php endif; ?>
                    <?php if(!empty($o['delivery_lat']) && !empty($o['delivery_lng'])): ?>
                        <li class="flex gap-2 items-center">
                            <span class="font-bold w-20 text-gray-400">موقع GPS:</span>
                            <a href="https://www.google.com/maps?q=<?php echo $o['delivery_lat']; ?>,<?php echo $o['delivery_lng']; ?>" target="_blank" class="bg-blue-50 hover:bg-blue-100 text-blue-700 px-3 py-1 rounded-lg border border-blue-200 font-bold flex items-center gap-1.5 transition text-xs shadow-sm">
                                <i class="fa-solid fa-map-location-dot text-blue-600"></i> فتح موقع العميل في Google Maps 🗺️
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <!-- زر تحديث الشحن عبر الواتساب للمسؤول -->
                <?php
                $clean_cust_phone = preg_replace('/[^0-9]/', '', $o['customer_phone']);
                if (strlen($clean_cust_phone) === 11 && str_starts_with($clean_cust_phone, '01')) {
                    $clean_cust_phone = '20' . substr($clean_cust_phone, 1);
                }
                
                $store_n = $settings['store_name'] ?? 'المتجر الإلكتروني';
                $wa_status_message = "";
                if ($o['status'] === 'جديد') {
                    $wa_status_message = "أهلاً عميلنا العزيز " . $o['customer_name'] . "، معك متجر " . $store_n . ". يسعدنا إبلاغك بأنه تم تأكيد استلام طلبك رقم #" . $o['id'] . " وجاري تجهيزه للشحن قريباً ✨";
                } elseif ($o['status'] === 'قيد التنفيذ') {
                    $wa_status_message = "أهلاً عميلنا العزيز " . $o['customer_name'] . "، معك متجر " . $store_n . ". طلبك رقم #" . $o['id'] . " قيد التجهيز والتغليف الآن وسيتم تسليمه للمندوب قريباً ✨";
                } elseif ($o['status'] === 'شُحن') {
                    $wa_status_message = "أهلاً عميلنا العزيز " . $o['customer_name'] . "، معك متجر " . $store_n . ". تم شحن طلبك رقم #" . $o['id'] . " وهو حالياً مع مندوب التوصيل وسيصلك قريباً بإذن الله ✨";
                } elseif ($o['status'] === 'تم التوصيل') {
                    $wa_status_message = "أهلاً عميلنا العزيز " . $o['customer_name'] . "، معك متجر " . $store_n . ". تم توصيل طلبك رقم #" . $o['id'] . " بنجاح. نأمل أن تكون المنتجات قد نالت رضاك ونسعد برأيك دائماً ✨";
                }
                
                $wa_update_link = "https://wa.me/" . $clean_cust_phone . "?text=" . urlencode($wa_status_message);
                $curr_display = htmlspecialchars($o['currency'] ?: ($settings['store_currency'] ?? 'ج.م'));
                ?>
                <?php if (!empty($wa_status_message)): ?>
                    <div class="mt-5">
                        <a href="<?php echo $wa_update_link; ?>" target="_blank" class="w-full bg-[#25D366] hover:bg-[#20ba5a] text-white font-bold py-2.5 px-4 rounded-xl shadow transition-all text-xs flex items-center justify-center gap-2">
                            <i class="fa-brands fa-whatsapp text-base"></i> إرسال تحديث حالة الطلب للعميل عبر الواتساب
                        </a>
                    </div>
                <?php endif; ?>

                <!-- طريقة وحالة الدفع وإيصال التحويل المرفق -->
                <div class="mt-6 p-4 bg-royal-sand/20 rounded-2xl border border-royal-gold/15 space-y-3">
                    <h5 class="font-bold text-xs text-royal-dark border-b border-royal-gold/10 pb-1.5 flex items-center gap-1.5">
                        <i class="fa-solid fa-credit-card text-royal-darkgold"></i> تفاصيل وسيلة الدفع والإيصال
                    </h5>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-400 font-bold">طريقة الدفع:</span>
                            <span class="font-bold text-royal-dark"><?php echo htmlspecialchars($o['payment_method'] ?? 'الدفع عند الاستلام'); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400 font-bold">حالة الدفع:</span>
                            <?php 
                            $p_status = $o['payment_status'] ?? 'غير مدفوع';
                            $p_class = 'bg-yellow-100 text-yellow-800';
                            if ($p_status === 'مدفوع') $p_class = 'bg-green-100 text-green-800';
                            if ($p_status === 'قيد التحقق والمراجعة') $p_class = 'bg-purple-100 text-purple-800';
                            ?>
                            <span class="px-2 py-0.5 rounded-full font-bold text-[10px] <?php echo $p_class; ?>"><?php echo htmlspecialchars($p_status); ?></span>
                        </div>
                        <?php if (!empty($o['transaction_ref'])): ?>
                            <div class="flex justify-between">
                                <span class="text-gray-400 font-bold">رقم العملية / المرجع:</span>
                                <span class="font-mono text-royal-darkgold font-bold"><?php echo htmlspecialchars($o['transaction_ref']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($o['receipt_image'])): ?>
                            <div class="pt-2 border-t border-royal-gold/10">
                                <span class="block text-gray-500 font-bold text-[11px] mb-1.5">صورة إيصال التحويل المرفقة من العميل:</span>
                                <a href="<?php echo htmlspecialchars($o['receipt_image']); ?>" target="_blank" class="block border border-royal-gold/30 rounded-xl overflow-hidden shadow-sm hover:opacity-90 transition-opacity">
                                    <img src="<?php echo htmlspecialchars($o['receipt_image']); ?>" alt="إيصال التحويل" class="w-full max-h-48 object-cover">
                                </a>
                                <a href="<?php echo htmlspecialchars($o['receipt_image']); ?>" target="_blank" class="text-[10px] text-royal-gold font-bold underline block mt-1 text-center">فتح صورة الإيصال بحجم كامل 🔍</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- المشتريات والحسابات -->
            <div>
                <h4 class="font-serif font-bold text-royal-dark mb-4 border-b pb-2 flex items-center gap-2">
                    <i class="fa-solid fa-basket-shopping text-royal-darkgold"></i> سلة المنتجات المشتراة
                </h4>
                <ul class="space-y-2 text-xs text-gray-600 mb-5 bg-royal-sand/20 p-4 rounded-xl border border-royal-gold/5 font-semibold">
                    <?php foreach($order_items as $item): ?>
                        <li class="pb-2.5 border-b border-royal-gold/5 last:border-0 last:pb-0"><?php echo htmlspecialchars($item); ?></li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="space-y-2 text-xs mb-5 border-b pb-4">
                    <div class="flex justify-between text-gray-500 font-medium"><span>مجموع المنتجات:</span> <span class="font-serif"><?php echo $subtotal_products; ?> <?php echo $curr_display; ?></span></div>
                    <?php if($o['discount_amount'] > 0): ?>
                        <div class="flex justify-between text-green-600 font-bold"><span>كوبون خصم (<?php echo htmlspecialchars($o['coupon_code']); ?>):</span> <span class="font-serif">- <?php echo $o['discount_amount']; ?> <?php echo $curr_display; ?></span></div>
                    <?php endif; ?>
                    <div class="flex justify-between text-gray-500 font-medium"><span>مصاريف الشحن:</span> <span class="font-serif">+ <?php echo $o['shipping_cost']; ?> <?php echo $curr_display; ?></span></div>
                </div>
                
                <div class="flex justify-between items-center border-t-2 border-royal-dark pt-4">
                    <span class="font-bold text-royal-dark text-base">الصافي المطلوب للتسليم:</span>
                    <span class="font-serif font-bold text-royal-darkgold text-xl"><?php echo $o['total_price']; ?> <?php echo $curr_display; ?></span>
                </div>
            </div>
        </div>

        <!-- تحديث حالة الطلب -->
        <div class="bg-royal-sand/20 p-6 border-t border-royal-gold/10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <span class="text-xs font-bold text-royal-darkgold flex items-center gap-1.5"><i class="fa-solid fa-spinner animate-spin"></i> تغيير وتحديث حالة الشحن:</span>
            <form method="POST" action="admin_order_details.php?id=<?php echo $o['id']; ?>" class="flex gap-2 w-full sm:w-auto">
                <select name="status" class="border border-gray-200 p-2 px-3 rounded-lg text-xs bg-white outline-none focus:border-royal-gold flex-grow">
                    <option value="جديد" <?php echo $o['status']=='جديد'?'selected':''; ?>>جديد</option>
                    <option value="قيد التنفيذ" <?php echo $o['status']=='قيد التنفيذ'?'selected':''; ?>>قيد التنفيذ</option>
                    <option value="شُحن" <?php echo $o['status']=='شُحن'?'selected':''; ?>>تم الشحن (خارج للتوصيل)</option>
                    <option value="تم التوصيل" <?php echo $o['status']=='تم التوصيل'?'selected':''; ?>>تم التوصيل</option>
                    <option value="ملغي" <?php echo $o['status']=='ملغي'?'selected':''; ?>>إلغاء الطلب</option>
                </select>
                <button type="submit" name="update_order_status" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-6 py-2.5 rounded-lg text-xs font-bold transition-all shadow-md">تحديث وحفظ</button>
            </form>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
