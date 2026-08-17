<?php
require_once 'config.php';

$error = '';
$success = '';
$step = 1; // 1: التحقق من البيانات، 2: تعيين كلمة المرور الجديدة، 3: النجاح

if (isset($_POST['verify_submit'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND email = ?");
    $stmt->execute([$username, $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if ($user['role'] === 'admin') {
            $error = "عذراً، لا يمكن استعادة حساب المسؤول بهذه الطريقة لحماية الأمان. يرجى تعديله مباشرة من الخادم.";
            $step = 1;
        } else {
            $step = 2;
            $_SESSION['reset_user_id'] = $user['id'];
        }
    } else {
        $error = "خطأ: اسم المستخدم أو البريد الإلكتروني غير متطابق مع سجلاتنا.";
    }
}

if (isset($_POST['reset_submit'])) {
    if (isset($_SESSION['reset_user_id'])) {
        $user_id = $_SESSION['reset_user_id'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 6) {
            $error = "خطأ: يجب ألا تقل كلمة المرور عن 6 أحرف.";
            $step = 2;
        } elseif ($new_password !== $confirm_password) {
            $error = "خطأ: كلمات المرور الجديدة غير متطابقة.";
            $step = 2;
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);

            unset($_SESSION['reset_user_id']);
            $success = "تمت إعادة تعيين كلمة المرور بنجاح! يمكنكِ الآن تسجيل الدخول باستخدام كلمة المرور الجديدة.";
            $step = 3;
        }
    } else {
        $error = "حدث خطأ في الجلسة، يرجى إعادة المحاولة من الخطوة الأولى.";
        $step = 1;
    }
}

include 'header.php';
?>

<div class="min-h-[75vh] flex items-center justify-center py-16 px-4 animate-fade-in">
    <div class="w-full max-w-md text-center bg-white p-8 md:p-10 border border-royal-gold/10 shadow-xl rounded-3xl">
        <h2 class="text-3xl font-serif text-royal-dark font-bold mb-3">استعادة كلمة المرور</h2>
        
        <?php if ($step === 1): ?>
            <p class="text-xs text-gray-400 mb-8 font-light">أدخلي اسم المستخدم والبريد الإلكتروني المسجلين للتحقق من هويتكِ.</p>
        <?php elseif ($step === 2): ?>
            <p class="text-xs text-gray-400 mb-8 font-light">تم التحقق بنجاح! أدخلي كلمة المرور الجديدة لحسابكِ.</p>
        <?php endif; ?>
        
        <!-- رسائل الخطأ والنجاح -->
        <?php if (!empty($error)): ?>
            <div class="bg-red-50 text-red-600 p-3.5 mb-6 text-xs border border-red-200 rounded-xl font-bold">
                <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="bg-green-50 text-green-700 p-3.5 mb-6 text-xs border border-green-200 rounded-xl font-bold">
                <i class="fa-solid fa-circle-check mr-1"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- الخطوة 1: التحقق من الهوية -->
        <?php if ($step === 1): ?>
            <form method="POST" action="forgot_password.php" class="space-y-4 text-right">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">اسم المستخدم *</label>
                    <input type="text" name="username" required placeholder="مثال: amira_99" class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">البريد الإلكتروني المسجل *</label>
                    <input type="email" name="email" required placeholder="مثال: amira@example.com" class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
                </div>
                <button type="submit" name="verify_submit" class="w-full bg-royal-charcoal text-white font-bold py-4 mt-2 tracking-widest uppercase hover:bg-royal-gold hover:text-royal-charcoal transition-all rounded-xl shadow btn-shine text-xs">التحقق من البيانات</button>
            </form>
        <?php endif; ?>

        <!-- الخطوة 2: تعيين كلمة المرور الجديدة -->
        <?php if ($step === 2): ?>
            <form method="POST" action="forgot_password.php" class="space-y-4 text-right">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">كلمة المرور الجديدة *</label>
                    <input type="password" name="new_password" required placeholder="أدخلي 6 أحرف أو أكثر" class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">تأكيد كلمة المرور الجديدة *</label>
                    <input type="password" name="confirm_password" required placeholder="أعيدي كتابة كلمة المرور" class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
                </div>
                <button type="submit" name="reset_submit" class="w-full bg-royal-gold text-royal-charcoal font-bold py-4 mt-2 tracking-widest uppercase hover:bg-royal-charcoal hover:text-white transition-all rounded-xl shadow btn-shine text-xs">تحديث كلمة المرور</button>
            </form>
        <?php endif; ?>

        <!-- الخطوة 3: النجاح والذهاب لتسجيل الدخول -->
        <?php if ($step === 3): ?>
            <a href="login.php" class="inline-block w-full bg-royal-charcoal text-white font-bold py-4 mt-4 tracking-widest uppercase hover:bg-royal-gold hover:text-royal-charcoal transition-all rounded-xl text-center shadow text-xs">ذهاب لتسجيل الدخول</a>
        <?php endif; ?>

        <!-- زر المساعدة الاحتياطي عبر الواتساب -->
        <?php
        $whatsapp_number = $settings['contact_phone'] ?? '';
        $cur_sn = $settings['store_name'] ?? 'المتجر الإلكتروني';
        $wa_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $whatsapp_number) . "?text=" . urlencode("أهلاً متجر " . $cur_sn . "، أريد المساعدة في استعادة كلمة المرور الخاصة بحسابي.");
        ?>
        <div class="mt-8 pt-6 border-t border-gray-100">
            <p class="text-[11px] text-gray-400 mb-2.5">تواجه صعوبة في تذكر بياناتك؟</p>
            <a href="<?php echo $wa_url; ?>" target="_blank" class="inline-flex items-center gap-2 text-xs bg-green-50 hover:bg-green-100 text-green-700 px-5 py-2.5 rounded-full border border-green-200 transition-colors font-bold">
                <i class="fa-brands fa-whatsapp text-sm"></i> تواصل مع الدعم الفني عبر الواتساب
            </a>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
