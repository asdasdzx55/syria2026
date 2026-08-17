<?php
require_once 'config.php';

// معالجة تسجيل الخروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// إذا كان مسجلاً بالفعل، أعد توجيهه
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_orders.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$auth_error = '';

// معالجة تسجيل الدخول
if (isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if ($user['role'] === 'admin') {
            header("Location: admin_orders.php");
        } else {
            header("Location: index.php");
        }
        exit;
    } else {
        $auth_error = "خطأ: اسم المستخدم أو كلمة المرور غير صحيحة.";
    }
}

include 'header.php';
?>

<div class="min-h-[70vh] flex items-center justify-center py-16 px-4 animate-fade-in">
    <div class="w-full max-w-md text-center bg-white p-10 border border-royal-gold/10 shadow-xl rounded-2xl">
        <h2 class="text-3xl font-serif text-royal-dark font-bold mb-3">تسجيل الدخول</h2>
        <p class="text-xs text-gray-400 mb-8 font-light">سجلي الدخول لتتمكني من إضافة تعليقات وتقييم المنتجات ومتابعة الطلبات.</p>
        
        <?php if (!empty($auth_error)): ?>
            <div class="bg-red-50 text-red-600 p-3.5 mb-6 text-xs border border-red-200 rounded-xl font-bold">
                <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $auth_error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php" class="space-y-4">
            <?php echo csrf_field(); ?>
            <input type="text" name="username" placeholder="اسم المستخدم" required class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
            <input type="password" name="password" placeholder="كلمة المرور" required class="w-full p-4 border border-gray-200 bg-royal-cream/30 outline-none text-center rounded-xl text-sm focus:border-royal-gold focus:bg-white transition-all">
            
            <div class="text-left w-full px-1">
                <a href="forgot_password.php" class="text-xs text-royal-darkgold hover:text-royal-gold transition-colors font-semibold">نسيتِ كلمة المرور؟</a>
            </div>
            
            <button type="submit" name="login_submit" class="w-full bg-royal-charcoal text-white font-bold py-4 mt-2 tracking-widest uppercase hover:bg-royal-gold hover:text-royal-charcoal transition-all rounded-xl shadow btn-shine text-xs">دخول</button>
        </form>
        
        <div class="mt-8 text-xs text-gray-500 font-medium">
            ليس لديكِ حساب؟ <a href="register.php" class="text-royal-darkgold hover:text-royal-gold transition-colors font-bold">إنشاء حساب جديد</a>
        </div>
    </div>
</div>

<?php
include 'footer.php';
?>
