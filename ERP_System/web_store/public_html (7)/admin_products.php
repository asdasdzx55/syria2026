<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// معالجة حذف المنتج
if (isset($_GET['action']) && $_GET['action'] == 'delete_product' && isset($_GET['id'])) {
    $prod_id = (int)$_GET['id'];
    
    // حذف صور المعرض محلياً أولاً
    $images_stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ?");
    $images_stmt->execute([$prod_id]);
    $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($images as $img) {
        if(file_exists($img['image_path'])) {
            unlink($img['image_path']);
        }
    }
    
    // حذف السجلات (بفضل ON DELETE CASCADE سيتم حذف صور المعرض تلقائياً)
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$prod_id]);
    header("Location: admin_products.php?msg=deleted");
    exit;
}

$products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">إدارة المنتجات</h2>
            <p class="text-xs text-gray-400 mt-1 font-light">تعديل وإضافة منتجات المتجر ومعرض الصور الخاصة بها.</p>
        </div>
        <a href="admin_edit_product.php" class="bg-gold-gradient text-white font-bold py-2.5 px-6 hover:bg-gold-gradient-hover hover:text-royal-charcoal transition-all text-xs rounded-xl shadow flex items-center gap-1.5 btn-shine">
            إضافة منتج جديد +
        </a>
    </div>

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'deleted'): ?>
        <div class="bg-red-50 text-red-700 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i> تم حذف المنتج وكل صوره بنجاح!
        </div>
    <?php endif; ?>

    <!-- جدول المنتجات للكمبيوتر (Desktop Table View) -->
    <div class="hidden md:block bg-white shadow-sm border border-royal-gold/10 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-royal-sand/40 text-gray-500 border-b border-royal-gold/10 font-bold">
                    <tr>
                        <th class="p-5 font-bold">الصورة الرئيسية</th>
                        <th class="p-5 font-bold">اسم المنتج</th>
                        <th class="p-5 font-bold">القسم</th>
                        <th class="p-5 font-bold">السعر الحالي</th>
                        <th class="p-5 font-bold">السعر القديم</th>
                        <th class="p-5 font-bold text-center">الإجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    <?php if(empty($products)): ?>
                        <tr>
                            <td colspan="6" class="p-10 text-center text-gray-400 font-serif">لا توجد منتجات مضافة حتى الآن. ابدأ بإضافة منتج جديد!</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($products as $p): ?>
                        <tr class="hover:bg-royal-cream/35 transition-colors">
                            <td class="p-5 w-24">
                                <img src="<?php echo htmlspecialchars($p['image_url'] ?: 'placeholder.php?w=800&h=1000'); ?>" class="w-12 h-16 object-cover bg-gray-50 border border-royal-gold/10 rounded-lg shadow-sm" alt="Product Image" loading="lazy" decoding="async">
                            </td>
                            <td class="p-5 font-bold text-royal-dark text-sm">
                                <?php echo htmlspecialchars($p['name']); ?>
                                <?php if(!empty($p['is_weight_based'])): ?>
                                    <span class="ms-2 bg-amber-100 text-amber-800 text-[10px] px-2 py-0.5 rounded-full font-bold inline-flex items-center gap-1 border border-amber-300/50">
                                        <i class="fa-solid fa-scale-balanced text-[9px]"></i> بيع بالوزن
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-5 text-gray-500">
                                <?php echo htmlspecialchars($p['category']); ?>
                                <?php if(!empty($p['sub_category'])): ?>
                                    <span class="text-gray-300 mx-1">/</span>
                                    <span class="text-xs text-gray-400 font-semibold bg-gray-50 px-2 py-0.5 rounded-md"><?php echo htmlspecialchars($p['sub_category']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="p-5 font-serif text-royal-darkgold font-bold text-sm">
                                <?php echo htmlspecialchars($p['price']); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?>
                                <?php if(!empty($p['is_weight_based'])): ?>
                                    <span class="text-[10px] text-gray-400 font-normal">/ كجم</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-5 font-serif text-gray-400 font-semibold">
                                <?php echo $p['old_price'] ? htmlspecialchars($p['old_price']) . ' ' . htmlspecialchars($settings['store_currency'] ?? 'ج.م') : '-'; ?>
                            </td>
                            <td class="p-5 text-center space-x-2.5 space-x-reverse">
                                <a href="admin_edit_product.php?id=<?php echo $p['id']; ?>" class="text-blue-500 hover:text-blue-700 transition-colors text-base p-1.5 inline-block" title="تعديل"><i class="fa-solid fa-pen-to-square"></i></a>
                                <a href="admin_products.php?action=delete_product&id=<?php echo $p['id']; ?>" onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا المنتج وكل معرض صوره نهائياً؟')" class="text-red-500 hover:text-red-700 transition-colors text-base p-1.5 inline-block" title="حذف"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- قائمة بطاقات المنتجات للجوال (Mobile Card View) -->
    <div class="block md:hidden space-y-4">
        <?php if(empty($products)): ?>
            <div class="bg-white p-10 text-center text-gray-400 border border-royal-gold/10 rounded-2xl font-serif text-xs">
                لا توجد منتجات مضافة حتى الآن. ابدأ بإضافة منتج جديد!
            </div>
        <?php else: ?>
            <?php foreach($products as $p): ?>
            <div class="bg-white p-4 border border-royal-gold/10 rounded-2xl flex items-center justify-between gap-4 shadow-sm">
                <div class="flex items-center gap-3.5">
                    <img src="<?php echo htmlspecialchars($p['image_url'] ?: 'placeholder.php?w=800&h=1000'); ?>" class="w-12 h-16 object-cover bg-gray-50 border border-royal-gold/10 rounded-lg shadow-sm shrink-0" alt="Product Image" loading="lazy" decoding="async">
                    <div class="space-y-1">
                        <h4 class="text-xs font-bold text-royal-dark leading-tight">
                            <?php echo htmlspecialchars($p['name']); ?>
                            <?php if(!empty($p['is_weight_based'])): ?>
                                <span class="ms-1 bg-amber-100 text-amber-800 text-[8px] px-1.5 py-0.2 rounded font-bold inline-block">⚖️ بالوزن</span>
                            <?php endif; ?>
                        </h4>
                        <span class="text-[9px] bg-royal-gold/15 text-royal-darkgold px-2 py-0.5 rounded-full inline-block font-semibold">
                            <?php echo htmlspecialchars($p['category']) . ($p['sub_category'] ? ' / ' . htmlspecialchars($p['sub_category']) : ''); ?>
                        </span>
                        <div class="flex items-center gap-2 mt-1">
                            <span class="text-xs text-royal-darkgold font-bold font-serif">
                                <?php echo htmlspecialchars($p['price']); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?>
                                <?php if(!empty($p['is_weight_based'])): ?>
                                    <span class="text-[9px] text-gray-400 font-normal">/ كجم</span>
                                <?php endif; ?>
                            </span>
                            <?php if($p['old_price']): ?>
                                <span class="text-[9px] text-gray-400 line-through font-serif"><?php echo htmlspecialchars($p['old_price']); ?> <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="flex gap-1.5 shrink-0">
                    <a href="admin_edit_product.php?id=<?php echo $p['id']; ?>" class="w-9 h-9 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded-full flex items-center justify-center text-sm transition-colors" title="تعديل"><i class="fa-solid fa-pen-to-square"></i></a>
                    <a href="admin_products.php?action=delete_product&id=<?php echo $p['id']; ?>" onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا المنتج وكل معرض صوره نهائياً؟')" class="w-9 h-9 bg-red-50 text-red-600 hover:bg-red-100 rounded-full flex items-center justify-center text-sm transition-colors" title="حذف"><i class="fa-solid fa-trash-can"></i></a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include 'footer.php';
?>
