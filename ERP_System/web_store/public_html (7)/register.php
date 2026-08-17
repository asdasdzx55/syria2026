<?php
require_once 'config.php';

// إذا كان مسجلاً بالفعل، أعد توجيهه
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$auth_error = '';

// معالجة تسجيل الحساب الجديد
if (isset($_POST['register_submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $email = trim($_POST['email']);

    if (empty($username) || empty($password) || empty($email)) {
        $auth_error = "خطأ: يرجى ملء كافة الحقول المطلوبة.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $auth_error = "خطأ: يرجى إدخال عنوان بريد إلكتروني صالح.";
    } elseif (strlen($password) < 6) {
        $auth_error = "خطأ: كلمة المرور يجب ألا تقل عن 6 أحرف أو أرقام.";
    } else {
        // التحقق من تكرار اسم المستخدم أو البريد الإلكتروني
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            $auth_error = "خطأ: اسم المستخدم أو البريد الإلكتروني مسجل مسبقاً.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'user')")->execute([$username, $hash, $email]);
            
            session_regenerate_id(true);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'user';

            header("Location: index.php");
            exit;
        }
    }
}

include 'header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center py-16 px-4 animate-fade-in">
    <div class="w-full max-w-md text-center bg-white p-10 border border-royal-gold/10 shadow-xl rounded-2xl">
        <h2 class="text-3xl font-serif text-royal-dark font-bold mb-3">إنشاء حساب جديد</h2>
        <p class="text-xs text-gray-400 mb-8 font-light">سجل حساباً جديداً للتمتع بتجربة تسوق متكاملة ومتابعة طلباتك أولاً بأول.</p>
        
        <?php if (!empty($auth_error)): ?>
            <div class="bg-red-50 text-red-600 p-3.5 mb-6 text-xs border border-red-200 rounded-xl font-bold">
                <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $auth_error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="text" name="username" placeholder="اسم المستخدم *" required class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
            <input type="email" name="email" placeholder="البريد الإلكتروني *" required class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
            <input type="password" name="password" placeholder="كلمة المرور *" required class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
            
            <button type="submit" name="register_submit" class="w-full bg-royal-charcoal text-white font-bold py-4 mt-2 tracking-widest uppercase hover:bg-royal-gold hover:text-royal-charcoal transition-all rounded-xl shadow btn-shine text-xs">تسجيل حسابي الجديد</button>
        </form>
        
        <div class="mt-8 text-xs text-gray-500 font-medium">
            لديكِ حساب بالفعل؟ <a href="login.php" class="text-royal-darkgold hover:text-royal-gold transition-colors font-bold">تسجيل الدخول</a>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
