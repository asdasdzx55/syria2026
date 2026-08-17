<?php
require_once 'config.php';
include 'header.php';

$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$orders_found = [];
$selected_order = null;
$error_msg = '';

if (isset($_GET['track_submit']) && !empty($search_query)) {
    // 1. تحديد نوع المدخل والبحث المرن في قاعدة البيانات
    if (filter_var($search_query, FILTER_VALIDATE_EMAIL)) {
        // البحث بالبريد الإلكتروني
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY id DESC");
        $stmt->execute([$search_query]);
        $orders_found = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (preg_match('/^[0-9]{1,6}$/', $search_query)) {
        // البحث برقم الطلب مباشرة
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([(int)$search_query]);
        $orders_found = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // البحث برقم الهاتف
        $clean_phone = preg_replace('/[^0-9]/', '', $search_query);
        if (strlen($clean_phone) >= 7) {
            // البحث بآخر 9 أرقام لتفادي كود الدولة
            $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_phone LIKE ? ORDER BY id DESC");
            $stmt->execute(['%' . substr($clean_phone, -9) . '%']);
            $orders_found = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error_msg = "يرجى كتابة رقم هاتف صحيح أو بريد إلكتروني أو رقم طلب.";
        }
    }
    
    if (empty($error_msg)) {
        if (empty($orders_found)) {
            $error_msg = "عذراً، لم نجد أي طلبات مسجلة بهذه البيانات. يرجى التحقق من الرقم المكتوب.";
        } elseif (count($orders_found) === 1) {
            $selected_order = $orders_found[0];
        }
    }
}

// إذا تم تحديد طلب معين يدوياً من نتائج البحث المتعددة
$view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
if ($view_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$view_id]);
    $selected_order = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<div class="container mx-auto px-4 md:px-8 py-16 max-w-2xl animate-fade-in">
    <div class="text-center mb-10">
        <span class="text-royal-darkgold text-xs font-bold tracking-widest uppercase mb-2 block">TRACK YOUR ORDER</span>
        <h2 class="text-3xl font-serif text-royal-dark font-bold">تتبع حالة طلبكِ بسهولة</h2>
        <p class="text-xs text-gray-500 font-light mt-2">أدخلي رقم الهاتف، أو البريد الإلكتروني، أو رقم الطلب لمعرفة أين وصلت شحنتكِ الآن.</p>
    </div>

    <!-- نموذج التتبع الذكي -->
    <div class="bg-white p-8 border border-royal-gold/10 shadow-lg rounded-2xl mb-8 animate-fade-in">
        <?php if (!empty($error_msg)): ?>
            <div class="bg-red-50 text-red-650 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold text-center">
                <i class="fa-solid fa-circle-exclamation mr-1 text-sm"></i> <?php echo $error_msg; ?>
            </div>
        <?php endif; ?>

        <form method="GET" action="track.php" class="space-y-4">
            <div>
                <input type="text" name="search_query" required placeholder="رقم الهاتف، البريد، أو رقم الطلب *" value="<?php echo htmlspecialchars($search_query); ?>" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm text-center font-bold">
            </div>
            <button type="submit" name="track_submit" value="1" class="w-full bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold py-4 transition shadow-md rounded-xl btn-shine text-xs uppercase tracking-widest">تتبع حالة الشحن</button>
        </form>
    </div>

    <!-- قائمة الطلبات المتعددة المكتشفة -->
    <?php if (count($orders_found) > 1 && !$selected_order): ?>
        <div class="bg-white p-6 border border-royal-gold/10 shadow-lg rounded-2xl mb-8 space-y-4 animate-fade-in">
            <h3 class="font-serif font-bold text-royal-dark text-sm border-b pb-2 flex items-center gap-1.5"><i class="fa-solid fa-list-check text-royal-darkgold"></i> تم العثور على <?php echo count($orders_found); ?> طلبات مسجلة:</h3>
            <div class="divide-y divide-gray-150">
                <?php foreach ($orders_found as $ord): ?>
                    <div class="py-4 flex justify-between items-center text-xs">
                        <div>
                            <span class="font-bold text-royal-dark font-serif">طلب رقم #<?php echo $ord['id']; ?></span>
                            <span class="text-gray-400 font-serif mr-2">(<?php echo date('Y-m-d', strtotime($ord['created_at'])); ?>)</span>
                            <div class="text-[10px] text-gray-500 mt-1 font-light max-w-[280px] sm:max-w-md truncate"><?php echo htmlspecialchars(str_replace("\n", " | ", trim($ord['order_details']))); ?></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="font-serif font-bold text-royal-darkgold whitespace-nowrap"><?php echo $ord['total_price']; ?> <?php echo htmlspecialchars($ord['currency'] ?: ($settings['store_currency'] ?? 'ج.م')); ?></span>
                            <a href="track.php?view_id=<?php echo $ord['id']; ?>&search_query=<?php echo urlencode($search_query); ?>&track_submit=1" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-3 py-1.5 rounded-lg font-bold transition whitespace-nowrap">تتبع 📦</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- شاشة التتبع البصري للطلب المحدد -->
    <?php if ($selected_order): $o = $selected_order; ?>
        <div class="bg-white border border-royal-gold/10 shadow-lg rounded-2xl overflow-hidden animate-fade-in">
            <div class="bg-royal-charcoal text-white p-5 flex justify-between items-center border-b border-royal-gold/15">
                <div class="text-right">
                    <h3 class="text-sm font-serif font-bold text-royal-gold">حالة الطلب #<?php echo $o['id']; ?></h3>
                    <p class="text-[9px] text-gray-400 mt-1 font-serif" dir="ltr"><?php echo date('Y-m-d h:i A', strtotime($o['created_at'])); ?></p>
                </div>
                <div>
                    <?php if ($o['status'] === 'ملغي'): ?>
                        <span class="bg-red-100/10 text-red-650 px-3.5 py-1.5 rounded-full text-xs font-bold border border-red-200/30">طلب ملغي</span>
                    <?php else: ?>
                        <span class="bg-royal-gold/25 text-royal-gold px-3.5 py-1.5 rounded-full text-xs font-bold border border-royal-gold/25"><?php echo $o['status']; ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="p-8 space-y-8">
                <?php if ($o['status'] === 'ملغي'): ?>
                    <div class="bg-red-50 text-red-700 p-5 rounded-xl border border-red-100 text-xs text-center leading-relaxed">
                        <i class="fa-solid fa-ban text-3xl mb-3 block text-red-500"></i>
                        <strong>تم إلغاء هذا الطلب.</strong> يرجى التواصل معنا عبر الواتساب لمعرفة الأسباب أو إجراء طلب آخر.
                    </div>
                <?php else: ?>
                    <!-- البار البصري التفاعلي للخطوات (Stepper) -->
                    <?php
                    $step = 1; // جديد
                    if ($o['status'] === 'قيد التنفيذ') {
                        $step = 2;
                    } elseif ($o['status'] === 'شُحن') {
                        $step = 3;
                    } elseif ($o['status'] === 'تم التوصيل') {
                        $step = 4;
                    }
                    ?>
                    <div class="relative py-4 flex items-center justify-between">
                        <div class="absolute left-0 right-0 top-1/2 transform -translate-y-1/2 h-[3px] bg-gray-100 -z-10"></div>
                        <div class="absolute left-0 right-0 top-1/2 transform -translate-y-1/2 h-[3px] bg-royal-gold transition-all duration-700 -z-10" style="width: <?php echo (($step - 1) / 3) * 100; ?>%; right: 0; left: auto;"></div>

                        <!-- الخطوة 1 -->
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all <?php echo $step >= 1 ? 'bg-royal-charcoal border-royal-gold text-royal-gold shadow-md' : 'bg-white border-gray-200 text-gray-400'; ?>">
                                <i class="fa-solid fa-file-invoice text-sm"></i>
                            </div>
                            <span class="text-[10px] font-bold <?php echo $step >= 1 ? 'text-royal-charcoal' : 'text-gray-400'; ?>">تم الاستلام</span>
                        </div>

                        <!-- الخطوة 2 -->
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all <?php echo $step >= 2 ? 'bg-royal-charcoal border-royal-gold text-royal-gold shadow-md' : 'bg-white border-gray-200 text-gray-400'; ?>">
                                <i class="fa-solid fa-box text-sm <?php echo $step === 2 ? 'animate-pulse' : ''; ?>"></i>
                            </div>
                            <span class="text-[10px] font-bold <?php echo $step >= 2 ? 'text-royal-charcoal' : 'text-gray-400'; ?>">قيد التجهيز</span>
                        </div>

                        <!-- الخطوة 3 -->
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all <?php echo $step >= 3 ? 'bg-royal-charcoal border-royal-gold text-royal-gold shadow-md' : 'bg-white border-gray-200 text-gray-400'; ?>">
                                <i class="fa-solid fa-truck-fast text-sm <?php echo $step === 3 ? 'animate-bounce' : ''; ?>"></i>
                            </div>
                            <span class="text-[10px] font-bold <?php echo $step >= 3 ? 'text-royal-charcoal' : 'text-gray-400'; ?>">تم الشحن</span>
                        </div>

                        <!-- الخطوة 4 -->
                        <div class="flex flex-col items-center gap-2">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all <?php echo $step >= 4 ? 'bg-royal-charcoal border-royal-gold text-royal-gold shadow-md' : 'bg-white border-gray-200 text-gray-400'; ?>">
                                <i class="fa-solid fa-house-chimney text-sm"></i>
                            </div>
                            <span class="text-[10px] font-bold <?php echo $step >= 4 ? 'text-royal-charcoal' : 'text-gray-400'; ?>">تم التوصيل</span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- معلومات الفاتورة الملخصة -->
                <div class="border-t pt-6 text-xs text-gray-600 space-y-3">
                    <h4 class="font-bold text-royal-dark font-serif border-b pb-2 flex items-center gap-1.5"><i class="fa-solid fa-info-circle text-royal-darkgold"></i> ملخص الطلب والتفاصيل</h4>
                    <div class="grid grid-cols-2 gap-y-2">
                        <div><span class="text-gray-400 font-medium">اسم العميل:</span> <span class="font-bold text-royal-dark"><?php echo htmlspecialchars($o['customer_name']); ?></span></div>
                        <div><span class="text-gray-400 font-medium">المحافظة:</span> <span class="font-bold text-royal-dark"><?php echo htmlspecialchars((!empty($o['country']) ? $o['country'] . ' - ' : '') . $o['governorate']); ?></span></div>
                        <div class="col-span-2"><span class="text-gray-400 font-medium">العنوان:</span> <span class="font-light text-royal-dark"><?php echo htmlspecialchars($o['customer_address']); ?></span></div>
                    </div>

                    <div class="bg-royal-sand/15 p-4 rounded-xl border border-royal-gold/5 mt-4">
                        <span class="text-gray-400 font-bold block mb-2">المنتجات المطلوبة:</span>
                        <ul class="space-y-1.5 font-medium text-gray-700 leading-relaxed">
                            <?php 
                            $items = array_filter(explode("\n", trim($o['order_details'])));
                            foreach($items as $it): 
                            ?>
                                <li><?php echo htmlspecialchars($it); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="flex justify-between items-center text-sm font-bold text-royal-dark border-t pt-3 mt-4">
                        <span>إجمالي الفاتورة للتسليم:</span>
                        <span class="font-serif text-royal-darkgold text-base font-bold"><?php echo $o['total_price']; ?> <?php echo htmlspecialchars($o['currency'] ?: ($settings['store_currency'] ?? 'ج.م')); ?></span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include 'footer.php';
?>
