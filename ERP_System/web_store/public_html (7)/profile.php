<?php
require_once 'config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// جلب تفاصيل المستخدم الحالية
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

// جلب كل المحافظات النشطة
$all_govs = $pdo->query("SELECT * FROM shipping_zones WHERE is_active = 1 ORDER BY gov_name ASC")->fetchAll(PDO::FETCH_ASSOC);

// تحديث الملف الشخصي والعنوان المحفوظ
if (isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $street = trim($_POST['street'] ?? '');
    $building = trim($_POST['building'] ?? '');
    $floor = trim($_POST['floor'] ?? '');
    $apartment = trim($_POST['apartment'] ?? '');
    $landmark = trim($_POST['landmark'] ?? '');
    $gov_id = !empty($_POST['gov_id']) ? (int)$_POST['gov_id'] : null;

    if (empty($email)) {
        $error_msg = "البريد الإلكتروني مطلوب.";
    } else {
        $stmt_up = $pdo->prepare("UPDATE users SET email = ?, phone = ?, street = ?, building = ?, floor = ?, apartment = ?, landmark = ?, gov_id = ? WHERE id = ?");
        $stmt_up->execute([$email, $phone, $street, $building, $floor, $apartment, $landmark, $gov_id, $user_id]);
        
        $success_msg = "تم تحديث معلوماتكِ وعنوانكِ المحفوظ بنجاح! ✨";
        
        // إعادة جلب التفاصيل المحدثة
        $stmt->execute([$user_id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// جلب طلبات العميل السابقة
// نبحث برقم المعرّف (user_id) أو بمطابقة البريد الإلكتروني
$stmt_orders = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? OR (customer_email = ? AND customer_email != '') ORDER BY id DESC");
$stmt_orders->execute([$user_id, $u['email']]);
$user_orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
?>

<div class="container mx-auto px-4 md:px-8 py-12 max-w-5xl animate-fade-in">
    <div class="text-center mb-10">
        <span class="text-royal-darkgold text-xs font-bold tracking-widest uppercase mb-2 block">MY ACCOUNT</span>
        <h2 class="text-3xl font-serif text-royal-dark font-bold">حسابي الشخصي</h2>
        <p class="text-xs text-gray-500 font-light mt-2">أديري معلوماتكِ، وتتبعي طلباتكِ السابقة والجارية بكل سهولة.</p>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold text-center">
            <i class="fa-solid fa-check-circle mr-1"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="bg-red-50 text-red-700 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold text-center">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-col lg:flex-row gap-10">
        <!-- القسم الأيمن: تعديل البيانات والعنوان -->
        <div class="lg:w-1/2">
            <div class="bg-white p-8 border border-royal-gold/10 shadow-lg rounded-2xl">
                <h3 class="font-serif font-bold text-base border-b pb-2 mb-6 text-royal-dark flex items-center gap-2">
                    <i class="fa-solid fa-address-card text-royal-darkgold"></i> تعديل بياناتي وعنواني المحفوظ
                </h3>
                
                <form method="POST" action="" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-[10px] text-gray-400 font-bold block mb-1">اسم المستخدم</label>
                            <input type="text" disabled value="<?php echo htmlspecialchars($u['username']); ?>" class="w-full p-3.5 border border-gray-100 bg-gray-50 outline-none rounded-xl text-xs text-gray-400 cursor-not-allowed">
                        </div>
                        <div>
                            <label class="text-[10px] text-gray-400 font-bold block mb-1">البريد الإلكتروني *</label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($u['email'] ?? ''); ?>" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] text-gray-400 font-bold block mb-1">رقم الهاتف (الواتس اب) *</label>
                        <input type="text" name="phone" required value="<?php echo htmlspecialchars($u['phone'] ?? ''); ?>" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs" dir="ltr" style="text-align: right;">
                    </div>

                    <div class="border-t pt-4 mt-2">
                        <span class="text-xs text-royal-darkgold font-bold block mb-3">📍 تفاصيل عنوان التوصيل الافتراضي:</span>
                        
                        <div class="space-y-3">
                            <div>
                                <select name="gov_id" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition text-gray-700 rounded-xl text-xs">
                                    <option value="">اختر المحافظة للتوصيل الافتراضي *</option>
                                    <?php foreach ($all_govs as $gov): ?>
                                        <option value="<?php echo $gov['id']; ?>" <?php echo (isset($u['gov_id']) && $u['gov_id'] == $gov['id']) ? 'selected' : ''; ?>><?php echo $gov['gov_name']; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <input type="text" name="street" value="<?php echo htmlspecialchars($u['street'] ?? ''); ?>" placeholder="اسم الشارع / المنطقة / الميدان *" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs">
                            </div>
                            
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <input type="text" name="building" value="<?php echo htmlspecialchars($u['building'] ?? ''); ?>" placeholder="العمارة *" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs text-center">
                                </div>
                                <div>
                                    <input type="text" name="floor" value="<?php echo htmlspecialchars($u['floor'] ?? ''); ?>" placeholder="الدور" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs text-center">
                                </div>
                                <div>
                                    <input type="text" name="apartment" value="<?php echo htmlspecialchars($u['apartment'] ?? ''); ?>" placeholder="الشقة *" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs text-center">
                                </div>
                            </div>
                            
                            <div>
                                <input type="text" name="landmark" value="<?php echo htmlspecialchars($u['landmark'] ?? ''); ?>" placeholder="أقرب علامة مميزة (مثال: بجوار مسجد النور)" class="w-full p-3.5 border border-gray-200 bg-royal-cream/20 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-xs">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="w-full bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold py-3.5 transition shadow-md rounded-xl btn-shine text-xs uppercase tracking-widest">
                        حفظ البيانات والعنوان 💾
                    </button>
                    
                    <a href="login.php?action=logout" class="block text-center text-red-500 hover:text-red-700 text-xs font-bold mt-4 transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-1"></i> تسجيل الخروج من الحساب
                    </a>
                </form>
            </div>
        </div>

        <!-- القسم الأيسر: سجل المشتريات وتتبع الطلبات -->
        <div class="lg:w-1/2">
            <div class="bg-white p-6 border border-royal-gold/10 shadow-lg rounded-2xl">
                <h3 class="font-serif font-bold text-base border-b pb-2 mb-4 text-royal-dark flex items-center gap-2">
                    <i class="fa-solid fa-receipt text-royal-darkgold"></i> سجل طلباتي ومشترياتي (<?php echo count($user_orders); ?>)
                </h3>

                <?php if (empty($user_orders)): ?>
                    <div class="text-center text-gray-400 py-16 text-xs">
                        <i class="fa-solid fa-bag-shopping text-4xl mb-4 block text-gray-300"></i>
                        لم تقم بإجراء أي طلبات معنا بعد. 
                        <a href="shop.php" class="text-royal-darkgold hover:text-royal-gold font-bold block mt-2 underline">تصفح المتجر الآن 🛍️</a>
                    </div>
                <?php else: ?>
                    <div class="space-y-4 max-h-[600px] overflow-y-auto pr-1">
                        <?php foreach ($user_orders as $ord): ?>
                            <div class="p-4 border border-royal-gold/5 bg-royal-cream/20 rounded-xl space-y-3 hover:shadow-md transition">
                                <div class="flex justify-between items-center text-xs">
                                    <div>
                                        <span class="font-bold text-royal-dark font-serif">طلب رقم #<?php echo $ord['id']; ?></span>
                                        <span class="text-gray-400 font-serif mr-2">(<?php echo date('Y-m-d', strtotime($ord['created_at'])); ?>)</span>
                                    </div>
                                    <div>
                                        <?php if ($ord['status'] == 'ملغي'): ?>
                                            <span class="bg-red-50 text-red-700 px-2.5 py-1 rounded-full text-[9px] font-bold border border-red-200">ملغي</span>
                                        <?php elseif ($ord['status'] == 'تم التوصيل'): ?>
                                            <span class="bg-green-50 text-green-700 px-2.5 py-1 rounded-full text-[9px] font-bold border border-green-200">تم التوصيل ✅</span>
                                        <?php else: ?>
                                            <span class="bg-royal-gold/15 text-royal-darkgold px-2.5 py-1 rounded-full text-[9px] font-bold border border-royal-gold/20"><?php echo $ord['status']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="bg-white p-3 rounded-lg border border-gray-150 text-[10px] text-gray-600">
                                    <span class="font-bold block mb-1">المنتجات المطلوبة:</span>
                                    <div class="whitespace-pre-line leading-relaxed"><?php echo htmlspecialchars(trim($ord['order_details'])); ?></div>
                                </div>

                                <div class="flex justify-between items-center text-xs border-t pt-2.5 mt-2">
                                    <div>
                                        <span class="text-gray-400">الإجمالي:</span>
                                        <span class="font-serif font-bold text-royal-darkgold text-sm"><?php echo $ord['total_price']; ?> <?php echo htmlspecialchars($ord['currency'] ?: ($settings['store_currency'] ?? 'ج.م')); ?></span>
                                    </div>
                                    
                                    <!-- زر التتبع المباشر الذكي والسهل للغاية دون كتابة أي بيانات -->
                                    <a href="track.php?view_id=<?php echo $ord['id']; ?>&search_query=<?php echo urlencode($ord['customer_phone']); ?>&track_submit=1" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal py-1.5 px-3.5 rounded-lg text-[10px] font-bold transition flex items-center gap-1.5 shadow-sm">
                                        <i class="fa-solid fa-truck-fast"></i> تتبع الشحنة 📦
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
