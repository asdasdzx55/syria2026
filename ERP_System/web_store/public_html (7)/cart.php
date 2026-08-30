<?php
require_once 'config.php';

$cart_error = '';
$cart_msg = '';

// تهيئة السلة والكوبون إن لم يكونا موجودين
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
if (!isset($_SESSION['coupon'])) $_SESSION['coupon'] = null;

// 1. تحديث الكمية في السلة
if (isset($_POST['update_cart_qty'])) {
    $id = $_POST['product_id'];
    $new_qty = (int)$_POST['qty'];
    if ($new_qty > 0 && isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id]['qty'] = $new_qty;
    }
    header("Location: cart.php");
    exit;
}

// 2. إزالة منتج من السلة
if (isset($_GET['action']) && $_GET['action'] == 'remove_cart' && isset($_GET['id'])) {
    $remove_id = $_GET['id'];
    unset($_SESSION['cart'][$remove_id]);
    if (empty($_SESSION['cart'])) {
        $_SESSION['coupon'] = null;
    }
    header("Location: cart.php");
    exit;
}

// 3. تطبيق الكوبون
if (isset($_POST['apply_coupon'])) {
    $code = strtoupper(trim($_POST['coupon_code']));
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($coupon) {
        $_SESSION['coupon'] = [
            'code' => $coupon['code'],
            'discount_percent' => $coupon['discount_percent']
        ];
        $cart_msg = "تم تطبيق كوبون الخصم بنجاح! 🎉";
    } else {
        $cart_error = "كود الخصم غير صحيح أو منتهي الصلاحية.";
        $_SESSION['coupon'] = null;
    }
}

// 4. إزالة الكوبون
if (isset($_GET['action']) && $_GET['action'] == 'remove_coupon') {
    $_SESSION['coupon'] = null;
    header("Location: cart.php");
    exit;
}

include 'header.php';
?>

<!-- عنوان صفحة السلة -->
<div class="bg-royal-sand/40 py-12 text-center border-b border-royal-gold/10 mb-10">
    <h2 class="text-3xl font-serif text-royal-dark font-bold">حقيبة التسوق</h2>
    <p class="text-xs text-gray-500 mt-2 font-light">راجعي المنتجات المضافة وأتمي عملية الطلب لتجهيز شحنتكِ.</p>
</div>

<div class="container mx-auto px-4 md:px-8 pb-20 max-w-5xl animate-fade-in">
    <?php if(empty($_SESSION['cart'])): ?>
        <div class="text-center py-20 bg-white rounded-2xl border border-royal-gold/10 shadow-sm">
            <i class="fa-solid fa-bag-shopping text-6xl text-gray-200 mb-6"></i>
            <h3 class="text-xl text-gray-500 font-serif mb-5 font-semibold">حقيبة التسوق الخاصة بكِ فارغة حالياً</h3>
            <a href="shop.php" class="bg-royal-charcoal text-white px-10 py-4 font-bold hover:bg-royal-gold hover:text-royal-charcoal transition-all text-xs tracking-widest uppercase rounded-lg shadow btn-shine">العودة للتسوق</a>
        </div>
    <?php else: ?>
        <div class="flex flex-col lg:flex-row gap-10">
            <!-- قائمة المشتريات -->
            <div class="lg:w-2/3">
                <div class="bg-white rounded-2xl shadow-sm border border-royal-gold/10 overflow-hidden divide-y divide-gray-100">
                    <?php 
                    $subtotal_cart = 0; 
                    foreach($_SESSION['cart'] as $id => $item): 
                        $subtotal = $item['price'] * $item['qty']; 
                        $subtotal_cart += $subtotal; 
                    ?>
                        <div class="flex flex-col sm:flex-row p-6 relative items-center sm:items-start text-center sm:text-right gap-5">
                            <!-- صورة المنتج -->
                            <img src="<?php echo htmlspecialchars($item['image']); ?>" class="w-24 h-32 object-cover bg-gray-50 border border-royal-gold/10 rounded-lg shrink-0" alt="Product Image">
                            
                            <!-- تفاصيل المنتج والكمية -->
                            <div class="flex flex-col justify-between flex-grow w-full h-32 py-1">
                                <div>
                                    <div class="flex justify-between items-start mb-1.5">
                                        <?php $real_pid = $item['product_id'] ?? (is_numeric($id) ? $id : explode('_', $id)[0]); ?>
                                        <a href="product.php?id=<?php echo $real_pid; ?>" class="font-bold text-base text-royal-dark hover:text-royal-gold transition-colors block w-full sm:w-auto leading-tight"><?php echo htmlspecialchars($item['name']); ?></a>
                                        <a href="cart.php?action=remove_cart&id=<?php echo $id; ?>" class="hidden sm:block text-gray-300 hover:text-red-500 transition-colors text-lg" title="حذف المنتج"><i class="fa-solid fa-xmark"></i></a>
                                    </div>
                                    
                                    <!-- شارات الوزن والمواصفات -->
                                    <div class="flex flex-wrap items-center gap-1.5 mb-2 justify-center sm:justify-start">
                                        <?php if (!empty($item['weight_label'])): ?>
                                            <span class="bg-amber-100 text-amber-800 font-bold text-[10px] px-2 py-0.5 rounded-md border border-amber-200/80 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-scale-balanced text-[9px]"></i> <?php echo htmlspecialchars($item['weight_label']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <?php if (!empty($item['variant_summary'])): ?>
                                            <span class="bg-royal-gold/15 text-royal-darkgold font-bold text-[10px] px-2 py-0.5 rounded-md border border-royal-gold/20 inline-flex items-center gap-1">
                                                <i class="fa-solid fa-sliders text-[9px]"></i> <?php echo htmlspecialchars($item['variant_summary']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="font-serif text-gray-400 text-xs mb-3 font-semibold">
                                        <?php echo !empty($item['weight_label']) ? 'سعر الوزن المحدد:' : 'سعر القطعة:'; ?> 
                                        <span class="text-royal-darkgold font-bold"><?php echo $item['price']; ?></span> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?>
                                    </div>
                                </div>
                                <!-- التحكم بالكمية -->
                                <div class="flex justify-between items-end mt-auto">
                                    <form method="POST" action="cart.php" class="flex items-center border border-gray-200 rounded-lg h-9 bg-white overflow-hidden shadow-inner mx-auto sm:mx-0">
                                        <input type="hidden" name="product_id" value="<?php echo $id; ?>">
                                        <button type="submit" name="update_cart_qty" onclick="this.nextElementSibling.value = Math.max(1, parseInt(this.nextElementSibling.value) - 1);" class="w-8 h-full text-gray-500 hover:bg-gray-100 flex items-center justify-center font-bold">-</button>
                                        <input type="number" name="qty" value="<?php echo $item['qty']; ?>" min="1" class="w-10 h-full text-center outline-none bg-transparent font-bold font-serif text-xs pointer-events-none" readonly>
                                        <button type="submit" name="update_cart_qty" onclick="this.previousElementSibling.value = parseInt(this.previousElementSibling.value) + 1;" class="w-8 h-full text-gray-500 hover:bg-gray-100 flex items-center justify-center font-bold">+</button>
                                    </form>
                                    <div class="font-serif text-royal-darkgold text-base font-bold hidden sm:block"><?php echo $subtotal; ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></div>
                                </div>
                            </div>
                            
                            <!-- السعر الكلي وحذف الجوال -->
                            <div class="font-serif text-royal-darkgold text-lg font-bold block sm:hidden mt-2"><?php echo $subtotal; ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></div>
                            <a href="cart.php?action=remove_cart&id=<?php echo $id; ?>" class="block sm:hidden text-red-500 text-xs mt-3 border border-red-200 px-5 py-1.5 rounded-full hover:bg-red-50 transition-colors">حذف من الحقيبة</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- ملخص الفاتورة والكوبونات -->
            <div class="lg:w-1/3">
                <div class="bg-white p-8 sticky top-28 border border-royal-gold/10 shadow-sm rounded-2xl">
                    <h3 class="font-serif font-bold text-lg mb-6 text-royal-dark border-b pb-3">ملخص الفاتورة</h3>
                    <div class="flex justify-between mb-4 text-xs text-gray-500 font-bold">
                        <span>المجموع الفرعي</span> 
                        <span class="font-serif"><?php echo $subtotal_cart; ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
                    </div>
                    
                    <!-- إدخال وتطبيق كوبونات الخصم -->
                    <div class="mb-6 border-b border-gray-100 pb-6">
                        <?php if(!empty($cart_error)): ?>
                            <p class="text-red-500 text-xs mb-3 font-semibold"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $cart_error; ?></p>
                        <?php endif; ?>
                        <?php if(!empty($cart_msg)): ?>
                            <p class="text-green-600 text-xs mb-3 font-bold"><i class="fa-solid fa-circle-check"></i> <?php echo $cart_msg; ?></p>
                        <?php endif; ?>
                        
                        <?php if($_SESSION['coupon']): 
                            $discount_val = $subtotal_cart * ($_SESSION['coupon']['discount_percent'] / 100);
                            $total_cart = $subtotal_cart - $discount_val;
                        ?>
                            <div class="flex justify-between text-xs text-green-600 font-bold bg-green-50 p-3 rounded-lg border border-green-100 items-center">
                                <span>كوبون الخصم (<?php echo $_SESSION['coupon']['code']; ?>)</span>
                                <span class="font-serif font-extrabold">- <?php echo $discount_val; ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
                            </div>
                            <div class="text-left mt-2">
                                <a href="cart.php?action=remove_coupon" class="text-[10px] text-red-500 hover:underline"><i class="fa-regular fa-trash-can"></i> إزالة الكوبون</a>
                            </div>
                        <?php else: $total_cart = $subtotal_cart; ?>
                            <form method="POST" action="cart.php" class="flex gap-2">
                                <input type="text" name="coupon_code" placeholder="كود الخصم (كوبون)" class="w-full p-2.5 border border-gray-200 outline-none text-xs text-center uppercase rounded-lg focus:border-royal-gold bg-royal-cream/40 font-bold font-serif" required>
                                <button type="submit" name="apply_coupon" class="bg-royal-charcoal text-white px-5 text-xs font-bold hover:bg-royal-gold hover:text-royal-charcoal transition-all rounded-lg">تطبيق</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <!-- الإجمالي النهائي للمنتجات بدون شحن -->
                    <div class="flex justify-between font-bold text-lg text-royal-dark mb-8 border-b pb-4">
                        <span>الإجمالي (بدون شحن)</span>
                        <span class="font-serif text-royal-darkgold text-xl"><?php echo $total_cart; ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
                    </div>
                    
                    <a href="checkout.php" class="block text-center w-full bg-gold-gradient text-white font-bold py-4 hover:bg-gold-gradient-hover hover:text-royal-charcoal transition-all text-xs tracking-widest uppercase rounded-lg shadow-md btn-shine">
                        متابعة الشراء وتأكيد الطلب
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
include 'footer.php';
?>
