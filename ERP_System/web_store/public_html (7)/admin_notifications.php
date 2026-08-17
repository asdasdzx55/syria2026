<?php
require_once 'config.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$success_msg = '';
$error_msg = '';

// إرسال إشعار جديد
if (isset($_POST['send_notification'])) {
    $title = trim($_POST['notif_title'] ?? '');
    $body = trim($_POST['notif_body'] ?? '');
    $link = trim($_POST['notif_link'] ?? '');

    if (empty($title) || empty($body)) {
        $error_msg = "يرجى كتابة عنوان ونص الإشعار.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO notifications (title, body, link) VALUES (?, ?, ?)");
        $stmt->execute([$title, $body, $link]);
        $success_msg = "تم إرسال الإشعار بنجاح إلى جميع مستخدمي التطبيق! ✅";
    }
}

// حذف إشعار
if (isset($_GET['delete_notif'])) {
    $del_id = (int)$_GET['delete_notif'];
    $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$del_id]);
    header('Location: admin_notifications.php?deleted=1');
    exit;
}
if (isset($_GET['deleted'])) $success_msg = "تم حذف الإشعار بنجاح.";

// جلب كل الإشعارات
$notifications = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-12 max-w-4xl">
    <div class="text-center mb-10">
        <span class="text-royal-darkgold text-xs font-bold tracking-widest uppercase mb-2 block">PUSH NOTIFICATIONS</span>
        <h2 class="text-3xl font-serif text-royal-dark font-bold">إدارة إشعارات التطبيق</h2>
        <p class="text-xs text-gray-500 font-light mt-2">أرسل إشعارات فورية لجميع مستخدمي وتطبيق المتجر على الهاتف.</p>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold text-center animate-fade-in">
            <i class="fa-solid fa-check-circle mr-1"></i> <?php echo $success_msg; ?>
        </div>
    <?php endif; ?>
    <?php if (!empty($error_msg)): ?>
        <div class="bg-red-50 text-red-700 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold text-center animate-fade-in">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?php echo $error_msg; ?>
        </div>
    <?php endif; ?>

    <!-- نموذج إرسال إشعار جديد -->
    <div class="bg-white p-8 border border-royal-gold/10 shadow-lg rounded-2xl mb-8">
        <h3 class="font-serif font-bold text-base border-b pb-2 mb-5 text-royal-dark flex items-center gap-2">
            <i class="fa-solid fa-bell text-royal-darkgold"></i> إرسال إشعار جديد
        </h3>
        <form method="POST" action="" class="space-y-4">
            <div>
                <label class="text-xs text-gray-500 font-bold block mb-1">عنوان الإشعار *</label>
                <input type="text" name="notif_title" required placeholder="مثال: عروض حصرية 🔥" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-bold">
            </div>
            <div>
                <label class="text-xs text-gray-500 font-bold block mb-1">نص الإشعار *</label>
                <textarea name="notif_body" required rows="3" placeholder="مثال: خصم 30% على جميع المفارش لفترة محدودة! سارعي بالطلب الآن." class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm resize-none"></textarea>
            </div>
            <div>
                <label class="text-xs text-gray-500 font-bold block mb-1">رابط عند الضغط (اختياري)</label>
                <input type="text" name="notif_link" placeholder="مثال: product.php?id=5 أو shop.php" class="w-full p-4 border border-gray-200 bg-royal-cream/35 outline-none focus:bg-white focus:border-royal-gold transition rounded-xl text-sm font-serif" dir="ltr">
            </div>
            <button type="submit" name="send_notification" class="w-full bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold py-4 transition shadow-md rounded-xl btn-shine text-xs uppercase tracking-widest">
                <i class="fa-solid fa-paper-plane mr-1"></i> إرسال الإشعار الآن
            </button>
        </form>
    </div>

    <!-- سجل الإشعارات المرسلة -->
    <div class="bg-white p-6 border border-royal-gold/10 shadow-lg rounded-2xl">
        <h3 class="font-serif font-bold text-base border-b pb-2 mb-4 text-royal-dark flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-royal-darkgold"></i> سجل الإشعارات المرسلة (<?php echo count($notifications); ?>)
        </h3>

        <?php if (empty($notifications)): ?>
            <div class="text-center text-gray-400 py-8 text-xs">
                <i class="fa-solid fa-bell-slash text-3xl mb-3 block"></i>
                لم يتم إرسال أي إشعارات بعد.
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($notifications as $n): ?>
                    <div class="py-4 flex justify-between items-start gap-4">
                        <div class="flex-grow text-right">
                            <h4 class="font-bold text-sm text-royal-dark"><?php echo htmlspecialchars($n['title']); ?></h4>
                            <p class="text-xs text-gray-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($n['body']); ?></p>
                            <?php if (!empty($n['link'])): ?>
                                <span class="text-[10px] text-royal-darkgold mt-1 block text-left" dir="ltr">🔗 <?php echo htmlspecialchars($n['link']); ?></span>
                            <?php endif; ?>
                            <span class="text-[10px] text-gray-400 mt-1 block text-left" dir="ltr"><?php echo $n['created_at']; ?></span>
                        </div>
                        <a href="admin_notifications.php?delete_notif=<?php echo $n['id']; ?>" onclick="return confirm('حذف هذا الإشعار؟')" class="text-red-400 hover:text-red-600 transition text-xs shrink-0">
                            <i class="fa-solid fa-trash"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
