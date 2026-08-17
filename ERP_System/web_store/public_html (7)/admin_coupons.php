<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$coupon_error = '';
$coupon_msg = '';

// 1. إنشاء كوبون جديد
if (isset($_POST['add_coupon'])) {
    $code = strtoupper(trim($_POST['coupon_code']));
    $discount_percent = (int)$_POST['discount_percent'];

    try {
        $pdo->prepare("INSERT INTO coupons (code, discount_percent) VALUES (?, ?)")->execute([$code, $discount_percent]);
        header("Location: admin_coupons.php?msg=coupon_added");
        exit;
    } catch (PDOException $e) {
        $coupon_error = "خطأ: كود الخصم هذا مسجل وموجود بالفعل!";
    }
}

// 2. تفعيل / تعطيل الكوبون
if (isset($_GET['action']) && $_GET['action'] == 'toggle_coupon' && isset($_GET['id'])) {
    $c_id = (int)$_GET['id'];
    $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?")->execute([$c_id]);
    header("Location: admin_coupons.php");
    exit;
}

// 3. حذف الكوبون نهائياً
if (isset($_GET['action']) && $_GET['action'] == 'delete_coupon' && isset($_GET['id'])) {
    $c_id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$c_id]);
    header("Location: admin_coupons.php?msg=coupon_deleted");
    exit;
}

$coupons = $pdo->query("SELECT * FROM coupons ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <div class="mb-8">
        <h2 class="text-2xl font-serif font-bold text-royal-dark">إدارة الكوبونات</h2>
        <p class="text-xs text-gray-400 mt-1 font-light">توليد أكواد الخصم وتفعيلها للعملاء لتطبيقها في السلة.</p>
    </div>

    <!-- تنبيهات النجاح والأخطاء -->
    <?php if(isset($_GET['msg'])): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold animate-fade-in">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i>
            <?php 
            if($_GET['msg'] == 'coupon_added') echo 'تم إنشاء كوبون الخصم الجديد بنجاح!';
            elseif($_GET['msg'] == 'coupon_deleted') echo 'تم حذف الكوبون نهائياً بنجاح!';
            ?>
        </div>
    <?php endif; ?>

    <?php if(!empty($coupon_error)): ?>
        <div class="bg-red-50 text-red-700 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold animate-fade-in">
            <i class="fa-solid fa-circle-exclamation mr-1 text-sm"></i> <?php echo $coupon_error; ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 items-start">
        
        <!-- كارت إنشاء كوبون جديد -->
        <div class="bg-white p-6 rounded-2xl border border-royal-gold/10 shadow-sm">
            <h3 class="font-serif font-bold text-base border-b pb-2 mb-5 text-royal-dark flex items-center gap-1.5">
                <i class="fa-solid fa-square-plus text-royal-darkgold"></i> إنشاء كوبون خصم جديد
            </h3>
            
            <form method="POST" action="admin_coupons.php" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">كود الخصم (مثال: ROYAL20)</label>
                    <input type="text" name="coupon_code" placeholder="ROYAL20" required class="w-full p-3 border border-gray-200 outline-none text-center font-serif font-bold uppercase rounded-xl bg-royal-cream/35 focus:bg-white focus:border-royal-gold transition-all text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">نسبة الخصم %</label>
                    <input type="number" name="discount_percent" placeholder="20" required min="1" max="99" class="w-full p-3 border border-gray-200 outline-none text-center font-serif font-bold rounded-xl bg-royal-cream/35 focus:bg-white focus:border-royal-gold transition-all text-sm">
                </div>
                <button type="submit" name="add_coupon" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal w-full py-3.5 font-bold tracking-widest text-xs rounded-xl shadow btn-shine transition-all">توليد وإنشاء الكوبون</button>
            </form>
        </div>
        
        <!-- جدول عرض الكوبونات الحالية -->
        <div class="md:col-span-2 bg-white rounded-2xl border border-royal-gold/10 shadow-sm overflow-hidden">
            <table class="w-full text-right text-xs">
                <thead class="bg-royal-sand/40 text-gray-500 border-b border-royal-gold/10 font-bold">
                    <tr>
                        <th class="p-5 font-bold">كود الكوبون</th>
                        <th class="p-5 font-bold">نسبة الخصم</th>
                        <th class="p-5 font-bold text-center">الحالة</th>
                        <th class="p-5 font-bold text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    <?php if(empty($coupons)): ?>
                        <tr>
                            <td colspan="4" class="p-10 text-center text-gray-400 font-serif">لا توجد كوبونات خصم مضافة حتى الآن.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($coupons as $c): ?>
                        <tr class="hover:bg-royal-cream/35 transition-colors">
                            <td class="p-5 font-bold uppercase font-serif text-sm text-royal-dark"><?php echo htmlspecialchars($c['code']); ?></td>
                            <td class="p-5 text-royal-darkgold font-bold font-serif text-sm"><?php echo $c['discount_percent']; ?>%</td>
                            <td class="p-5 text-center">
                                <a href="admin_coupons.php?action=toggle_coupon&id=<?php echo $c['id']; ?>" class="px-3.5 py-1.5 rounded-full text-[10px] font-bold inline-block border <?php echo $c['is_active'] ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'; ?>" title="اضغطي للتغيير">
                                    <?php echo $c['is_active'] ? 'نشط ومفعل ✓' : 'معطل ومخفي ✕'; ?>
                                </a>
                            </td>
                            <td class="p-5 text-center">
                                <a href="admin_coupons.php?action=delete_coupon&id=<?php echo $c['id']; ?>" onclick="return confirm('هل أنتِ متأكدة من رغبتكِ في حذف هذا الكوبون نهائياً؟')" class="text-red-500 hover:text-red-700 transition-colors text-base p-1.5 inline-block" title="حذف نهائياً"><i class="fa-solid fa-trash-can"></i></a>
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
