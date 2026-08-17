<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

$is_edit = isset($_GET['id']);
$product = ['name'=>'', 'category'=>'', 'price'=>'', 'old_price'=>'', 'description'=>'', 'image_url'=>''];
$product_gallery = [];
$product_variants = [];
$prod_id = 0;

if ($is_edit) {
    $prod_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$prod_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        die("المنتج غير موجود.");
    }
    
    // جلب صور المعرض
    $gal_stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_main DESC, id ASC");
    $gal_stmt->execute([$prod_id]);
    $product_gallery = $gal_stmt->fetchAll(PDO::FETCH_ASSOC);

    // جلب خيارات ومواصفات المنتج (Variants)
    $var_stmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
    $var_stmt->execute([$prod_id]);
    $product_variants = $var_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// دالة مساعدة لحفظ خيارات ومواصفات المنتج
function saveProductVariantsHelper($pdo, $target_prod_id) {
    $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$target_prod_id]);
    if (isset($_POST['variant_name']) && is_array($_POST['variant_name'])) {
        $stmt_var = $pdo->prepare("INSERT INTO product_variants (product_id, variant_type, variant_name, color_code, price_modifier, stock) VALUES (?, ?, ?, ?, ?, ?)");
        
        $types = $_POST['variant_type'] ?? [];
        $names = $_POST['variant_name'] ?? [];
        $colors = $_POST['variant_color'] ?? [];
        $prices = $_POST['variant_price'] ?? [];
        $stocks = $_POST['variant_stock'] ?? [];

        foreach ($names as $idx => $v_name) {
            $v_name = trim($v_name);
            if (!empty($v_name)) {
                $v_type = trim($types[$idx] ?? 'الخيارات');
                if (empty($v_type)) $v_type = 'الخيارات';
                $v_color = !empty($colors[$idx]) ? trim($colors[$idx]) : null;
                $v_price = (float)($prices[$idx] ?? 0.00);
                $v_stock = !empty($stocks[$idx]) ? (int)$stocks[$idx] : 999;
                $stmt_var->execute([$target_prod_id, $v_type, $v_name, $v_color, $v_price, $v_stock]);
            }
        }
    }
}

// 1. تعيين الصورة الرئيسية
if (isset($_GET['action']) && $_GET['action'] == 'set_main_image' && $is_edit) {
    $img_id = (int)$_GET['img_id'];
    $pdo->prepare("UPDATE product_images SET is_main = 0 WHERE product_id = ?")->execute([$prod_id]);
    $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?")->execute([$img_id]);
    
    $imgPath = $pdo->prepare("SELECT image_path FROM product_images WHERE id = ?");
    $imgPath->execute([$img_id]);
    $path = $imgPath->fetchColumn();
    
    $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$path, $prod_id]);
    header("Location: admin_edit_product.php?id=$prod_id&msg=main_updated");
    exit;
}

// 2. حذف صورة من المعرض
if (isset($_GET['action']) && $_GET['action'] == 'delete_gallery_image' && $is_edit) {
    $img_id = (int)$_GET['img_id'];
    $stmt = $pdo->prepare("SELECT image_path, is_main FROM product_images WHERE id = ?");
    $stmt->execute([$img_id]);
    $img_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($img_data) {
        $count = $pdo->query("SELECT COUNT(*) FROM product_images WHERE product_id = $prod_id")->fetchColumn();
        if($count > 1) {
            if (file_exists($img_data['image_path'])) {
                unlink($img_data['image_path']);
            }
            $pdo->prepare("DELETE FROM product_images WHERE id = ?")->execute([$img_id]);
            
            // إذا كانت الصورة المحذوفة هي الرئيسية، اجعل أول صورة أخرى هي الرئيسية
            if($img_data['is_main'] == 1) {
                $first_img = $pdo->query("SELECT id, image_path FROM product_images WHERE product_id = $prod_id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if($first_img) {
                    $pdo->prepare("UPDATE product_images SET is_main = 1 WHERE id = ?")->execute([$first_img['id']]);
                    $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$first_img['image_path'], $prod_id]);
                }
            }
            header("Location: admin_edit_product.php?id=$prod_id&msg=img_deleted");
            exit;
        } else {
            header("Location: admin_edit_product.php?id=$prod_id&error=cannot_delete_last_image");
            exit;
        }
    }
}

// 3. إضافة منتج جديد
if (isset($_POST['add_product'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $sub_category = !empty($_POST['sub_category']) ? trim($_POST['sub_category']) : null;
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null;

    $pdo->prepare("INSERT INTO products (name, category, sub_category, description, price, old_price, image_url) VALUES (?, ?, ?, ?, ?, ?, '')")
        ->execute([$name, $category, $sub_category, $description, $price, $old_price]);
    $new_prod_id = $pdo->lastInsertId();

    // حفظ خيارات ومواصفات المنتج
    saveProductVariantsHelper($pdo, $new_prod_id);

    $images = uploadMultipleImages($_FILES['gallery_images']);
    $main_image = 'placeholder.php?w=800&h=1000';
    
    if (!empty($images)) {
        $main_image = $images[0];
        foreach ($images as $index => $img) {
            $is_main = ($index == 0) ? 1 : 0;
            $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)")
                ->execute([$new_prod_id, $img, $is_main]);
        }
    } else {
        $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 1)")
            ->execute([$new_prod_id, $main_image]);
    }
    
    $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$main_image, $new_prod_id]);
    header("Location: admin_products.php?msg=added");
    exit;
}

// 4. تعديل المنتج
if (isset($_POST['edit_product'])) {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $sub_category = !empty($_POST['sub_category']) ? trim($_POST['sub_category']) : null;
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $old_price = !empty($_POST['old_price']) ? (float)$_POST['old_price'] : null;

    $pdo->prepare("UPDATE products SET name=?, category=?, sub_category=?, description=?, price=?, old_price=? WHERE id=?")
        ->execute([$name, $category, $sub_category, $description, $price, $old_price, $prod_id]);

    // حفظ خيارات ومواصفات المنتج
    saveProductVariantsHelper($pdo, $prod_id);

    $newImages = uploadMultipleImages($_FILES['gallery_images']);
    if (!empty($newImages)) {
        $has_main = $pdo->query("SELECT COUNT(*) FROM product_images WHERE product_id = $prod_id AND is_main = 1")->fetchColumn();
        foreach ($newImages as $index => $img) {
            $is_main = (!$has_main && $index == 0) ? 1 : 0;
            $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, ?)")
                ->execute([$prod_id, $img, $is_main]);
        }
        if(!$has_main && !empty($newImages)){
            $pdo->prepare("UPDATE products SET image_url = ? WHERE id = ?")->execute([$newImages[0], $prod_id]);
        }
    }
    header("Location: admin_edit_product.php?id=$prod_id&msg=edited");
    exit;
}

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-4xl animate-fade-in">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">
                <?php echo $is_edit ? 'تعديل المنتج والمعرض' : 'إضافة منتج جديد'; ?>
            </h2>
            <p class="text-xs text-gray-400 mt-1 font-light">راجعي تفاصيل وبيانات المنتج وصور العرض الخاصة به.</p>
        </div>
        <a href="admin_products.php" class="text-xs font-bold text-gray-500 hover:text-royal-gold flex items-center gap-1"><i class="fa-solid fa-arrow-right"></i> العودة للمنتجات</a>
    </div>

    <!-- رسائل التنبيه -->
    <?php if(isset($_GET['msg'])): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i>
            <?php 
            if($_GET['msg'] == 'edited') echo 'تم تعديل تفاصيل المنتج بنجاح!';
            elseif($_GET['msg'] == 'main_updated') echo 'تم تغيير الصورة الرئيسية للمنتج بنجاح!';
            elseif($_GET['msg'] == 'img_deleted') echo 'تم حذف صورة المعرض المحددة بنجاح!';
            ?>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="bg-red-50 text-red-700 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold">
            <i class="fa-solid fa-circle-exclamation mr-1 text-sm"></i>
            <?php 
            if($_GET['error'] == 'cannot_delete_last_image') echo 'لا يمكن حذف هذه الصورة؛ يجب أن يحتوي المنتج على صورة واحدة على الأقل.';
            ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- نموذج إضافة/تعديل البيانات (يسار) -->
        <div class="lg:col-span-2 bg-white p-8 border border-royal-gold/10 shadow-sm rounded-2xl">
            <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-xs font-bold mb-2 text-gray-600">اسم المنتج *</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold bg-royal-cream/30 focus:bg-white transition rounded-xl text-sm font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-2 text-gray-600">القسم الرئيسي (الفئة الأساسية) *</label>
                        <select name="category" id="main-cat-select" required class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold bg-royal-cream/30 focus:bg-white transition rounded-xl text-sm font-semibold">
                            <option value="">-- اختر القسم الرئيسي --</option>
                            <?php 
                            $main_cats = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
                            foreach($main_cats as $mc): 
                            ?>
                                <option value="<?php echo htmlspecialchars($mc['name']); ?>" <?php echo $product['category'] == $mc['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-2 text-gray-600">القسم الفرعي (الفئة الفرعية)</label>
                        <select name="sub_category" id="sub-cat-select" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold bg-royal-cream/30 focus:bg-white transition rounded-xl text-sm font-semibold">
                            <option value="">-- بدون قسم فرعي --</option>
                            <?php 
                            $sub_cats = $pdo->query("SELECT s.*, p.name as parent_name FROM categories s JOIN categories p ON s.parent_id = p.id WHERE s.parent_id IS NOT NULL ORDER BY s.name ASC")->fetchAll(PDO::FETCH_ASSOC);
                            foreach($sub_cats as $sc): 
                            ?>
                                <option value="<?php echo htmlspecialchars($sc['name']); ?>" data-parent-name="<?php echo htmlspecialchars($sc['parent_name']); ?>" <?php echo ($product['sub_category'] ?? '') == $sc['name'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($sc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-royal-sand/35 p-5 border border-royal-gold/10 rounded-2xl">
                    <div>
                        <label class="block text-xs font-bold mb-2 text-royal-darkgold">سعر البيع الحالي *</label>
                        <input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($product['price']); ?>" required class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold bg-white transition rounded-xl font-serif font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-2 text-gray-500">السعر القديم (لإظهار شارة الخصم - اختياري)</label>
                        <input type="number" step="0.01" name="old_price" value="<?php echo htmlspecialchars($product['old_price'] ?? ''); ?>" class="w-full p-3 border border-gray-200 outline-none focus:border-royal-gold bg-white transition rounded-xl font-serif font-bold text-sm">
                    </div>
                </div>

                <!-- خيارات ومواصفات المنتج المتعددة (الألوان، المقاسات، السعات) -->
                <div class="bg-gradient-to-br from-royal-sand/40 to-royal-cream/60 p-6 border border-royal-gold/20 rounded-2xl space-y-4">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-royal-gold/15 pb-3">
                        <div>
                            <h4 class="text-xs font-bold text-royal-dark flex items-center gap-2">
                                <i class="fa-solid fa-layer-group text-royal-darkgold"></i> خيارات ومواصفات المنتج (Product Variants)
                            </h4>
                            <p class="text-[10px] text-gray-500 mt-0.5">أضف خيارات المقاسات أو الألوان أو السعات مع تحديد السعر والمخزون لكل خيار.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button" onclick="addVariantPreset('sizes')" class="bg-white border border-gray-200 hover:border-royal-gold text-[10px] font-bold text-gray-700 px-2.5 py-1 rounded-lg transition shadow-sm">+ مقاسات ملابس</button>
                            <button type="button" onclick="addVariantPreset('storage')" class="bg-white border border-gray-200 hover:border-royal-gold text-[10px] font-bold text-gray-700 px-2.5 py-1 rounded-lg transition shadow-sm">+ سعات تخزين</button>
                            <button type="button" onclick="addVariantPreset('colors')" class="bg-white border border-gray-200 hover:border-royal-gold text-[10px] font-bold text-gray-700 px-2.5 py-1 rounded-lg transition shadow-sm">+ ألوان</button>
                            <button type="button" onclick="addVariantRow('', '', 0, 100)" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal text-[10px] font-bold px-3 py-1 rounded-lg transition shadow-sm flex items-center gap-1">
                                <i class="fa-solid fa-plus"></i> إضافة خيار
                            </button>
                        </div>
                    </div>

                    <!-- جدول الخيارات التفاعلي -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="text-gray-500 text-[10px] border-b border-royal-gold/10">
                                <tr>
                                    <th class="pb-2 font-bold w-1/5">نوع الخيار</th>
                                    <th class="pb-2 font-bold w-1/4">اسم الخيار / القيمة</th>
                                    <th class="pb-2 font-bold w-1/4 text-center">كود / درجة اللون (Color Swatch)</th>
                                    <th class="pb-2 font-bold w-1/5">فرق السعر (+ الإضافي)</th>
                                    <th class="pb-2 font-bold w-16 text-center">المخزون</th>
                                    <th class="pb-2 font-bold w-10 text-center">حذف</th>
                                </tr>
                            </thead>
                            <tbody id="variants-tbody" class="divide-y divide-royal-gold/10">
                                <!-- سيتم تعبئة الصفوف ديناميكياً بواسطة JavaScript -->
                            </tbody>
                        </table>
                        <div id="no-variants-msg" class="hidden text-center py-6 text-gray-400 text-xs">
                            لا توجد خيارات مضافة بعد لهذا المنتج (سيُباع بالخيارات والمواصفات القياسية الافتراضية).
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">وصف وتفاصيل المنتج *</label>
                    <textarea name="description" rows="5" required class="w-full p-4 border border-gray-200 outline-none focus:border-royal-gold bg-royal-cream/30 focus:bg-white transition rounded-xl text-sm leading-relaxed" placeholder="اكتب مواصفات المنتج، الألوان والمقاسات، والخامات أو المزايا بالتفصيل..."><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">رفع صور إضافية للمعرض</label>
                    <input type="file" name="gallery_images[]" multiple accept="image/*" class="w-full text-xs text-gray-500 bg-royal-cream/30 p-3.5 border border-dashed border-royal-gold/25 rounded-xl cursor-pointer">
                    <p class="text-[10px] text-gray-400 mt-2">💡 يمكنك تحديد عدة صور لرفعها دفعة واحدة (المقاس الموصى به: 800 × 1000 px).</p>
                </div>

                <div class="flex gap-3.5 pt-4">
                    <button type="submit" name="<?php echo $is_edit ? 'edit_product' : 'add_product'; ?>" class="flex-grow bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold py-3.5 tracking-widest uppercase rounded-xl shadow-md btn-shine text-xs transition-all">
                        <?php echo $is_edit ? 'حفظ التعديلات' : 'إضافة المنتج'; ?>
                    </button>
                    <a href="admin_products.php" class="bg-gray-200 text-gray-700 px-8 py-3.5 font-bold uppercase tracking-widest text-xs rounded-xl hover:bg-gray-300 transition-colors flex items-center justify-center">إلغاء</a>
                </div>
            </form>
        </div>

        <!-- معرض الصور الحالي (يمين - يظهر فقط في حالة التعديل) -->
        <div class="lg:col-span-1">
            <?php if ($is_edit): ?>
                <div class="bg-white p-6 border border-royal-gold/10 shadow-sm rounded-2xl sticky top-28">
                    <h3 class="font-serif font-bold text-sm text-royal-dark mb-4 border-b pb-2 flex items-center gap-2">
                        <i class="fa-regular fa-images text-royal-darkgold"></i> معرض الصور الحالي
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach($product_gallery as $img): ?>
                            <div class="relative bg-gray-50 rounded-xl overflow-hidden border border-royal-gold/5 p-1 flex flex-col justify-between">
                                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" class="w-full aspect-[4/5] object-cover rounded-lg shadow-inner bg-gray-100">
                                
                                <div class="mt-3 flex justify-between items-center gap-1.5">
                                    <?php if($img['is_main'] == 1): ?>
                                        <span class="text-[9px] bg-green-50 text-green-700 border border-green-200 px-2 py-0.5 rounded-md font-bold w-full text-center">الرئيسية ✓</span>
                                    <?php else: ?>
                                        <a href="admin_edit_product.php?id=<?php echo $prod_id; ?>&action=set_main_image&img_id=<?php echo $img['id']; ?>" class="text-[9px] bg-royal-sand text-royal-darkgold hover:bg-royal-gold hover:text-white px-2 py-0.5 rounded-md transition-all font-bold w-full text-center">جعلها رئيسية</a>
                                        <a href="admin_edit_product.php?id=<?php echo $prod_id; ?>&action=delete_gallery_image&img_id=<?php echo $img['id']; ?>" onclick="return confirm('حذف الصورة؟')" class="text-red-500 hover:text-red-700 text-sm p-1" title="حذف الصورة"><i class="fa-solid fa-trash-can"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-royal-sand/30 p-6 border border-dashed border-royal-gold/25 rounded-2xl text-center text-gray-400">
                    <i class="fa-solid fa-cloud-arrow-up text-5xl mb-4 text-royal-gold/40"></i>
                    <p class="text-xs font-semibold leading-relaxed">عند إضافة المنتج الجديد، يرجى رفع الصور المخصصة له. أول صورة سيتم رفعها ستعتبر تلقائياً هي الصورة المعروضة في المتجر.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
const existingVariants = <?php echo json_encode($product_variants, JSON_UNESCAPED_UNICODE); ?>;

function checkVariantsEmpty() {
    const tbody = document.getElementById('variants-tbody');
    const noMsg = document.getElementById('no-variants-msg');
    if (tbody && noMsg) {
        if (tbody.children.length === 0) {
            noMsg.classList.remove('hidden');
        } else {
            noMsg.classList.add('hidden');
        }
    }
}

function addVariantRow(type = 'اللون', name = '', color = '', price = 0, stock = 100) {
    const tbody = document.getElementById('variants-tbody');
    if (!tbody) return;

    const tr = document.createElement('tr');
    tr.className = 'hover:bg-royal-sand/20 transition-colors';

    const typesList = ['اللون', 'المقاس', 'السعة', 'الذاكرة', 'الحجم', 'الخامة', 'النوع', 'أخرى'];
    let typeOptions = '';
    typesList.forEach(t => {
        const isSel = (t === type) ? 'selected' : '';
        typeOptions += `<option value="${t}" ${isSel}>${t}</option>`;
    });

    const defaultColor = color ? color : (type === 'اللون' ? '#111827' : '');
    const colorVisibility = (type === 'اللون' || color) ? '' : 'opacity-40 grayscale';

    tr.innerHTML = `
        <td class="py-2.5 pr-1">
            <select name="variant_type[]" onchange="handleVariantTypeChange(this)" class="w-full p-2 border border-gray-200 bg-white rounded-lg text-xs outline-none focus:border-royal-gold font-bold">
                ${typeOptions}
            </select>
        </td>
        <td class="py-2.5 px-2">
            <input type="text" name="variant_name[]" value="${escapeHtml(name)}" required placeholder="مثال: أسود كربوني / 256GB / XL" class="w-full p-2 border border-gray-200 bg-white rounded-lg text-xs outline-none focus:border-royal-gold font-semibold">
        </td>
        <td class="py-2.5 px-2 text-center">
            <div class="color-picker-box flex items-center justify-center gap-1.5 ${colorVisibility} transition-all">
                <input type="color" value="${defaultColor || '#111827'}" oninput="syncColorInput(this, 'picker')" class="w-7 h-7 rounded-lg cursor-pointer border border-gray-200 p-0 bg-transparent shrink-0">
                <input type="text" name="variant_color[]" value="${escapeHtml(color)}" oninput="syncColorInput(this, 'text')" placeholder="#HEX" class="w-20 p-1.5 border border-gray-200 bg-white rounded-lg text-[10px] font-mono text-center outline-none focus:border-royal-gold uppercase font-bold" dir="ltr">
            </div>
        </td>
        <td class="py-2.5 px-2">
            <div class="flex items-center gap-1">
                <span class="text-[10px] text-gray-400 font-bold">+</span>
                <input type="number" step="0.01" name="variant_price[]" value="${parseFloat(price) || 0}" placeholder="0.00" class="w-full p-2 border border-gray-200 bg-white rounded-lg text-xs outline-none focus:border-royal-gold font-mono font-bold">
            </div>
        </td>
        <td class="py-2.5 px-2 text-center">
            <input type="number" name="variant_stock[]" value="${parseInt(stock) || 100}" min="0" class="w-16 p-2 border border-gray-200 bg-white rounded-lg text-xs outline-none focus:border-royal-gold font-mono text-center font-bold">
        </td>
        <td class="py-2.5 pl-1 text-center">
            <button type="button" onclick="removeVariantRow(this)" class="text-red-500 hover:text-red-700 transition p-1.5 rounded-lg hover:bg-red-50" title="حذف هذا الخيار">
                <i class="fa-solid fa-trash-can text-sm"></i>
            </button>
        </td>
    `;

    tbody.appendChild(tr);
    checkVariantsEmpty();
}

function syncColorInput(el, source) {
    const parent = el.closest('.color-picker-box');
    if (!parent) return;
    const picker = parent.querySelector('input[type="color"]');
    const text = parent.querySelector('input[name="variant_color[]"]');

    if (source === 'picker') {
        text.value = picker.value.toUpperCase();
    } else if (source === 'text') {
        let val = text.value.trim();
        if (val && !val.startsWith('#')) {
            val = '#' + val;
            text.value = val;
        }
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            picker.value = val;
        }
    }
}

function handleVariantTypeChange(selectEl) {
    const tr = selectEl.closest('tr');
    if (!tr) return;
    const colorBox = tr.querySelector('.color-picker-box');
    const colorInput = tr.querySelector('input[name="variant_color[]"]');
    if (selectEl.value === 'اللون') {
        colorBox.classList.remove('opacity-40', 'grayscale');
        if (!colorInput.value) {
            colorInput.value = '#111827';
        }
    } else {
        colorBox.classList.add('opacity-40', 'grayscale');
    }
}

function removeVariantRow(btn) {
    const tr = btn.closest('tr');
    if (tr) {
        tr.remove();
        checkVariantsEmpty();
    }
}

function addVariantPreset(preset) {
    if (preset === 'sizes') {
        const sizes = ['S (صغير)', 'M (متوسط)', 'L (كبير)', 'XL (كبير جداً)', 'XXL'];
        sizes.forEach(s => addVariantRow('المقاس', s, '', 0, 50));
    } else if (preset === 'storage') {
        addVariantRow('السعة', '128 GB', '', 0, 50);
        addVariantRow('السعة', '256 GB', '', 200, 30);
        addVariantRow('السعة', '512 GB', '', 500, 15);
        addVariantRow('السعة', '1 TB', '', 900, 10);
    } else if (preset === 'colors') {
        const luxuryColors = [
            { name: 'أسود كربوني', hex: '#111827' },
            { name: 'أبيض عاجي', hex: '#FFFFFF' },
            { name: 'أزرق ملكي كحلي', hex: '#1E3A8A' },
            { name: 'ذهبي ملكي', hex: '#D4AF37' },
            { name: 'فضي تيتانيوم', hex: '#94A3B8' },
            { name: 'عنابي / كستنائي', hex: '#7F1D1D' },
            { name: 'أخضر زمردي', hex: '#047857' }
        ];
        luxuryColors.forEach(c => addVariantRow('اللون', c.name, c.hex, 0, 50));
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return str.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

document.addEventListener("DOMContentLoaded", function() {
    // تعبئة الخيارات الحالية
    if (existingVariants && existingVariants.length > 0) {
        existingVariants.forEach(v => {
            addVariantRow(v.variant_type, v.variant_name, v.color_code || '', v.price_modifier, v.stock);
        });
    }
    checkVariantsEmpty();

    const mainSelect = document.getElementById("main-cat-select");
    const subSelect = document.getElementById("sub-cat-select");
    if (mainSelect && subSelect) {
        const subOptions = Array.from(subSelect.options);
        const initialSubVal = "<?php echo htmlspecialchars($product['sub_category'] ?? ''); ?>";
        
        function filterSubCategories(keepSelection) {
            const selectedMainName = mainSelect.value;
            const currentSelectedVal = keepSelection ? (subSelect.value || initialSubVal) : "";
            
            subSelect.innerHTML = "";
            subSelect.appendChild(subOptions[0]);
            
            if (selectedMainName) {
                subOptions.forEach(opt => {
                    if (opt.value && opt.getAttribute("data-parent-name") === selectedMainName) {
                        const clonedOpt = opt.cloneNode(true);
                        if (clonedOpt.value === currentSelectedVal) {
                            clonedOpt.selected = true;
                        }
                        subSelect.appendChild(clonedOpt);
                    }
                });
            }
            
            if (keepSelection && currentSelectedVal) {
                subSelect.value = currentSelectedVal;
            }
        }
        
        mainSelect.addEventListener("change", function() {
            filterSubCategories(false);
        });
        
        filterSubCategories(true);
    }
});
</script>

<?php
include 'footer.php';
?>
