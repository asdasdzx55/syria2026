<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// معالجة تحديث أسعار وحالة مناطق الشحن
if (isset($_POST['update_shipping_zones'])) {
    $costs = $_POST['gov_cost'];
    $active_status = isset($_POST['gov_active']) ? $_POST['gov_active'] : [];

    $stmt_update = $pdo->prepare("UPDATE shipping_zones SET cost = ?, is_active = ? WHERE id = ?");
    foreach($costs as $id => $cost) {
        $is_active = isset($active_status[$id]) ? 1 : 0;
        $stmt_update->execute([$cost, $is_active, $id]);
    }
    header("Location: admin_shipping.php?msg=shipping_updated");
    exit;
}

$zones = $pdo->query("SELECT * FROM shipping_zones ORDER BY gov_name ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <div class="flex flex-col sm:flex-row justify-between items-center sm:items-end mb-8 gap-4">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">تسعير مناطق الشحن</h2>
            <p class="text-xs text-gray-400 mt-1 font-light">تحديد تكاليف التوصيل للمحافظات المصرية أو تعطيل الشحن لبعضها.</p>
        </div>
        <button type="button" onclick="document.getElementById('shipping-form').submit();" class="bg-gold-gradient text-white font-bold py-2.5 px-6 hover:bg-gold-gradient-hover hover:text-royal-charcoal transition-all text-xs rounded-xl shadow flex items-center gap-1.5 btn-shine">
            حفظ التغييرات الحالية
        </button>
    </div>

    <!-- رسائل التنبيه والنجاح -->
    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'shipping_updated'): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold animate-fade-in">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i> تم حفظ وتحديث أسعار وحالة مناطق الشحن بنجاح!
        </div>
    <?php endif; ?>

    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-4 rounded-xl text-xs mb-8 flex gap-3 items-center">
        <i class="fa-solid fa-circle-info text-lg shrink-0 animate-bounce"></i>
        <span><strong>توضيح هام:</strong> إلغاء علامة الصح (✓) من أمام المحافظة سيؤدي لتعطيل التوصيل إليها فوراً في صفحة الدفع للعميل، ولن يتمكن من تأكيد طلبه.</span>
    </div>

    <form id="shipping-form" method="POST" action="admin_shipping.php" class="bg-white shadow-sm border border-royal-gold/10 rounded-2xl p-6 md:p-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach($zones as $z): ?>
                <div class="border p-4 rounded-xl transition-all duration-300 <?php echo !$z['is_active'] ? 'opacity-40 bg-gray-50 border-gray-200' : 'bg-royal-cream/15 border-royal-gold/10 shadow-sm'; ?>">
                    <div class="flex justify-between items-center mb-3">
                        <label class="font-bold text-royal-dark text-xs flex items-center gap-2 cursor-pointer select-none">
                            <input type="checkbox" name="gov_active[<?php echo $z['id']; ?>]" value="1" <?php echo $z['is_active'] ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-gold cursor-pointer rounded"> 
                            <span><?php echo htmlspecialchars($z['gov_name']); ?></span>
                        </label>
                        <?php if(!$z['is_active']): ?>
                            <span class="text-[9px] bg-red-50 text-red-600 px-2 py-0.5 rounded font-bold">الشحن معطل</span>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-gray-400 font-bold shrink-0">سعر التوصيل:</span>
                        <input type="number" step="0.01" name="gov_cost[<?php echo $z['id']; ?>]" value="<?php echo $z['cost']; ?>" class="w-full p-2 border border-gray-200 rounded-lg text-center outline-none focus:border-royal-gold font-serif font-bold text-royal-dark text-xs">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <input type="hidden" name="update_shipping_zones" value="1">
        <div class="mt-10 text-center border-t border-gray-100 pt-6">
            <button type="submit" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-14 py-4 text-xs font-bold rounded-xl shadow-md btn-shine transition-all uppercase tracking-wider">
                حفظ وتحديث كل الأسعار
            </button>
        </div>
    </form>
</div>

<?php
include 'footer.php';
?>
