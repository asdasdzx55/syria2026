<?php
require_once 'config.php';
include 'header.php';

// جلب تفاصيل آخر طلب من الجلسة
$order_id = isset($_SESSION['last_order_id']) ? (int)$_SESSION['last_order_id'] : 0;
$o = null;
if ($order_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $o = $stmt->fetch(PDO::FETCH_ASSOC);
}

// تجهيز رابط الواتساب الخاص بالمتجر
$whatsapp_phone = $settings['contact_phone'] ?? '201234567890';
// تنظيف رقم الهاتف ليكون أرقام فقط للرابط
$clean_phone = preg_replace('/[^0-9]/', '', $whatsapp_phone);
if (empty($clean_phone)) {
    $clean_phone = '201234567890';
}
// التأكد من إضافة كود البلد لمصر إذا لم يكن مكتوباً
if (strlen($clean_phone) === 11 && str_starts_with($clean_phone, '01')) {
    $clean_phone = '20' . substr($clean_phone, 1);
}

$wa_message = "";
$store_n = $settings['store_name'] ?? 'المتجر الإلكتروني';
$curr_s = $o && !empty($o['currency']) ? $o['currency'] : ($settings['store_currency'] ?? 'ج.م');
if ($o) {
    $wa_message = "أهلاً متجر " . $store_n . "، قمت بعمل طلب شراء جديد رقم #" . $o['id'] . " بقيمة " . $o['total_price'] . " " . $curr_s . " باسم العميل: " . $o['customer_name'] . ". أرجو تأكيد الطلب.";
} else {
    $wa_message = "أهلاً متجر " . $store_n . "، قمت بعمل طلب شراء جديد وأرجو تأكيد الطلب.";
}
$whatsapp_link = "https://wa.me/" . $clean_phone . "?text=" . urlencode($wa_message);
?>

<!-- Meta Pixel Purchase Event (Fires Once Per Order) -->
<?php if(!empty($meta_pixel_id) && $meta_pixel_enabled && $o && empty($_SESSION['meta_purchase_fired_'.$o['id']])): 
    $_SESSION['meta_purchase_fired_'.$o['id']] = true;
?>
<script>
  if (typeof fbq !== 'undefined') {
    fbq('track', 'Purchase', {
      value: <?php echo (float)$o['total_price']; ?>,
      currency: 'EGP',
      order_id: '<?php echo $o['id']; ?>'
    });
  }
</script>
<?php endif; ?>

<style>
    /* تحسينات للطباعة */
    @media print {
        header, footer, #ai-chat-widget, .no-print, .lg\:hidden {
            display: none !important;
        }
        body {
            background: white !important;
            padding: 0 !important;
        }
        .print-card {
            border: none !important;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    }
</style>

<div class="container mx-auto px-4 py-16 max-w-2xl animate-fade-in">
    <div class="bg-white p-6 md:p-10 border border-royal-gold/10 shadow-lg rounded-2xl print-card">
        <!-- أيقونة النجاح -->
        <div class="w-16 h-16 bg-green-50 text-green-600 rounded-full flex items-center justify-center text-3xl mx-auto mb-6 shadow-inner border border-green-100 no-print">
            <i class="fa-regular fa-circle-check animate-bounce"></i>
        </div>
        
        <div class="text-center no-print">
            <h2 class="text-2xl md:text-3xl font-serif text-royal-dark font-bold mb-3">تم استلام طلبك بنجاح!</h2>
            <p class="text-gray-500 text-xs leading-relaxed max-w-md mx-auto mb-8 font-light">
                شكراً جزيلاً لثقتك واختيارك لـ <strong><?php echo htmlspecialchars($store_n); ?></strong>. لقد تم تسجيل الطلب بنجاح في نظامنا وتوليد فاتورتك الرسمية أدناه.
            </p>
        </div>

        <?php if ($o): ?>
            <!-- كارت الفاتورة الرسمية للمعاينة والطباعة -->
            <div class="border border-royal-gold/15 rounded-xl overflow-hidden mb-8 shadow-sm">
                <!-- هيدر الفاتورة -->
                <div class="bg-royal-charcoal text-white p-5 flex justify-between items-center border-b border-royal-gold/15">
                    <div class="text-right">
                        <?php if(!empty($settings['store_logo'])): ?>
                            <img src="<?php echo htmlspecialchars($settings['store_logo']); ?>" alt="<?php echo htmlspecialchars($store_n); ?>" class="h-8 max-h-10 object-contain">
                        <?php else: ?>
                            <h1 class="text-lg font-serif font-bold text-royal-gold tracking-wider"><?php echo htmlspecialchars($store_n); ?></h1>
                            <?php if(!empty($settings['store_tagline'])): ?>
                                <p class="text-[9px] text-gray-400 font-light"><?php echo htmlspecialchars($settings['store_tagline']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="text-left font-serif text-xs">
                        <span class="font-bold text-royal-gold">رقم الفاتورة:</span> #<?php echo $o['id']; ?>
                    </div>
                </div>

                <div class="p-6 space-y-6">
                    <!-- معلومات العميل والتوصيل -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs border-b pb-4 border-gray-100">
                        <div class="space-y-1">
                            <h4 class="font-bold text-royal-dark">📍 تفاصيل الشحن والتوصيل</h4>
                            <p class="text-gray-500"><span class="font-semibold text-gray-700">المستلم:</span> <?php echo htmlspecialchars($o['customer_name']); ?></p>
                            <?php if(!empty($o['country'])): ?>
                                <p class="text-gray-500"><span class="font-semibold text-gray-700">الدولة:</span> <?php echo htmlspecialchars($o['country']); ?></p>
                            <?php endif; ?>
                            <p class="text-gray-500"><span class="font-semibold text-gray-700">المحافظة / المدينة:</span> <?php echo htmlspecialchars($o['governorate']); ?></p>
                            <p class="text-gray-500"><span class="font-semibold text-gray-700">العنوان:</span> <?php echo htmlspecialchars($o['customer_address']); ?></p>
                        </div>
                        <div class="space-y-1 md:text-left">
                            <h4 class="font-bold text-royal-dark">📞 معلومات الاتصال</h4>
                            <p class="text-gray-500" dir="ltr"><span class="font-semibold text-gray-700" dir="rtl">رقم الهاتف:</span> <?php echo htmlspecialchars($o['customer_phone']); ?></p>
                            <?php if(!empty($o['customer_email'])): ?>
                                <p class="text-gray-500"><span class="font-semibold text-gray-700">البريد الإلكتروني:</span> <?php echo htmlspecialchars($o['customer_email']); ?></p>
                            <?php endif; ?>
                            <p class="text-gray-400 text-[10px] mt-2 font-serif" dir="ltr"><?php echo date('Y-m-d h:i A', strtotime($o['created_at'])); ?></p>
                        </div>
                    </div>

                    <!-- المنتجات المطلوبة -->
                    <div class="space-y-2 text-xs">
                        <h4 class="font-bold text-royal-dark">🛍️ المنتجات المطلوبة</h4>
                        <ul class="divide-y divide-gray-100 bg-royal-sand/15 p-3 rounded-lg border border-royal-gold/5 font-medium text-gray-700">
                            <?php 
                            $items = array_filter(explode("\n", trim($o['order_details'])));
                            foreach ($items as $item): 
                            ?>
                                <li class="py-2 first:pt-0 last:pb-0"><?php echo htmlspecialchars($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- الملخص المالي للفاتورة -->
                    <div class="space-y-2 border-t pt-4 text-xs font-semibold text-gray-600">
                        <?php 
                        $subtotal_products = $o['total_price'] - $o['shipping_cost'] + $o['discount_amount'];
                        ?>
                        <div class="flex justify-between"><span>مجموع المشتريات:</span> <span class="font-serif"><?php echo $subtotal_products; ?> <?php echo $curr_s; ?></span></div>
                        <?php if($o['discount_amount'] > 0): ?>
                            <div class="flex justify-between text-green-600 font-bold"><span>خصم الكوبون (<?php echo htmlspecialchars($o['coupon_code']); ?>):</span> <span class="font-serif">- <?php echo $o['discount_amount']; ?> <?php echo $curr_s; ?></span></div>
                        <?php endif; ?>
                        <div class="flex justify-between"><span>تكلفة الشحن والتوصيل:</span> <span class="font-serif">+ <?php echo $o['shipping_cost']; ?> <?php echo $curr_s; ?></span></div>
                        <div class="flex justify-between text-royal-dark text-sm font-bold border-t pt-3 mt-1">
                            <span>الإجمالي المطلوب للتسليم:</span>
                            <span class="font-serif text-royal-darkgold text-base font-bold"><?php echo $o['total_price']; ?> <?php echo $curr_s; ?></span>
                        </div>
                    </div>

                    <!-- طريقة وحالة الدفع وإيصال التحويل المرفق -->
                    <div class="border-t pt-4 text-xs font-semibold text-gray-600 space-y-2">
                        <div class="flex justify-between items-center">
                            <span>طريقة الدفع:</span>
                            <span class="font-bold text-royal-dark"><?php echo htmlspecialchars($o['payment_method'] ?? 'الدفع عند الاستلام'); ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span>حالة الدفع:</span>
                            <span class="px-2.5 py-0.5 rounded-full font-bold text-[10px] bg-royal-sand text-royal-darkgold"><?php echo htmlspecialchars($o['payment_status'] ?? 'غير مدفوع'); ?></span>
                        </div>
                        <?php if (!empty($o['transaction_ref'])): ?>
                            <div class="flex justify-between items-center">
                                <span>رقم المرجع / العملية:</span>
                                <span class="font-mono text-royal-darkgold font-bold"><?php echo htmlspecialchars($o['transaction_ref']); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($o['receipt_image'])): ?>
                            <div class="pt-3 border-t border-gray-100">
                                <span class="block font-bold text-royal-dark mb-2">📄 صورة إيصال التحويل المرفقة:</span>
                                <a href="<?php echo htmlspecialchars($o['receipt_image']); ?>" target="_blank" class="block border border-royal-gold/30 rounded-xl overflow-hidden shadow-sm max-w-xs mx-auto">
                                    <img src="<?php echo htmlspecialchars($o['receipt_image']); ?>" alt="إيصال التحويل" class="w-full h-auto max-h-56 object-contain bg-gray-50">
                                </a>
                                <p class="text-[10px] text-center text-gray-400 mt-1">اضغطي على صورة الإيصال للتكبير 🔍</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- أزرار الإجراءات السريعة -->
        <div class="flex flex-col gap-3 justify-center no-print">
            <a href="<?php echo $whatsapp_link; ?>" target="_blank" class="w-full bg-[#25D366] hover:bg-[#20ba5a] text-white font-bold py-3.5 px-6 rounded-xl shadow-md transition-all text-xs tracking-wider flex items-center justify-center gap-2">
                <i class="fa-brands fa-whatsapp text-lg"></i> تأكيد الطلب واستلام الفاتورة عبر الواتساب
            </a>
            
            <div class="grid grid-cols-2 gap-3">
                <button onclick="window.print()" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold py-3.5 px-6 transition-all text-xs tracking-wider rounded-xl shadow flex items-center justify-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-print"></i> حفظ أو طباعة الفاتورة
                </button>
                <a href="shop.php" class="border border-royal-gold text-royal-darkgold hover:bg-royal-sand hover:text-royal-charcoal font-bold py-3.5 px-6 transition-all text-xs tracking-wider rounded-xl text-center flex items-center justify-center gap-1.5">
                    العودة للمتجر <i class="fa-solid fa-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
