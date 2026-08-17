<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// 0.1 تحديث هوية المتجر واللوجو والعملة
if (isset($_POST['update_general_settings'])) {
    $store_name = trim($_POST['store_name'] ?? '');
    $store_tagline = trim($_POST['store_tagline'] ?? '');
    $store_description = trim($_POST['store_description'] ?? '');
    $store_currency = trim($_POST['store_currency'] ?? 'ج.م');
    
    $text_settings = [
        'store_name' => $store_name,
        'store_tagline' => $store_tagline,
        'store_description' => $store_description,
        'store_currency' => $store_currency,
    ];
    
    foreach ($text_settings as $key => $val) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = ?")->execute([$val, $key]);
        } else {
            $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES (?, ?)")->execute([$key, $val]);
        }
    }
    
    // رفع شعار المتجر (Logo)
    if (isset($_FILES['store_logo']) && $_FILES['store_logo']['error'] == 0) {
        $logo_url = uploadImage($_FILES['store_logo']);
        if ($logo_url) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = 'store_logo'")->execute([$logo_url]);
        }
    }
    
    // رفع أيقونة المتجر (Favicon)
    if (isset($_FILES['store_favicon']) && $_FILES['store_favicon']['error'] == 0) {
        $fav_url = uploadImage($_FILES['store_favicon']);
        if ($fav_url) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = 'store_favicon'")->execute([$fav_url]);
        }
    }
    
    header("Location: admin_settings.php?tab=general&msg=general_updated");
    exit;
}

// 0.2 حذف شعار المتجر
if (isset($_GET['action']) && $_GET['action'] == 'remove_logo') {
    $pdo->prepare("UPDATE settings SET setting_value = '' WHERE key_name = 'store_logo'")->execute();
    header("Location: admin_settings.php?tab=general&msg=logo_removed");
    exit;
}

// 0.3 تغيير كلمة مرور المسؤول الأساسي (Master Admin Password)
if (isset($_POST['update_admin_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    $admin_username = $_SESSION['username'] ?? 'admin';
    
    // جلب مستخدم المدير
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR role = 'admin' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$admin_username]);
    $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin_user) {
        header("Location: admin_settings.php?tab=security&error=user_not_found");
        exit;
    }
    
    // التحقق من كلمة المرور الحالية
    if (!password_verify($current_pass, $admin_user['password'])) {
        header("Location: admin_settings.php?tab=security&error=wrong_current_password");
        exit;
    }
    
    if (strlen($new_pass) < 6) {
        header("Location: admin_settings.php?tab=security&error=password_too_short");
        exit;
    }
    
    if ($new_pass !== $confirm_pass) {
        header("Location: admin_settings.php?tab=security&error=password_mismatch");
        exit;
    }
    
    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
    $upd_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd_stmt->execute([$new_hash, $admin_user['id']]);
    
    header("Location: admin_settings.php?tab=security&msg=password_updated");
    exit;
}

// 0.4 تحديث ألوان وتصميم المتجر (Theme Palette)
if (isset($_POST['update_theme_colors'])) {
    $color_keys = [
        'theme_primary_color',
        'theme_secondary_color',
        'theme_accent_color',
        'theme_header_bg',
        'theme_header_text',
        'theme_body_bg',
        'theme_card_bg',
        'theme_btn_color',
        'theme_btn_text'
    ];
    
    foreach ($color_keys as $ck) {
        $cval = trim($_POST[$ck] ?? '');
        if (!empty($cval)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = ?");
            $stmt->execute([$ck]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = ?")->execute([$cval, $ck]);
            } else {
                $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES (?, ?)")->execute([$ck, $cval]);
            }
        }
    }
    
    header("Location: admin_settings.php?tab=colors&msg=colors_updated");
    exit;
}

// 1. تحديث شريط التنبيهات
if (isset($_POST['update_announcement'])) {
    $ann_text = trim($_POST['announcement_bar']);
    $pdo->prepare("UPDATE settings SET setting_value=? WHERE key_name='announcement_bar'")->execute([$ann_text]);
    header("Location: admin_settings.php?tab=theme&msg=announcement_updated");
    exit;
}

// 1.1. تحديث إعدادات ميتا بكسل
if (isset($_POST['update_meta_pixel'])) {
    $pixel_id = trim($_POST['meta_pixel_id']);
    $pixel_enabled = isset($_POST['meta_pixel_enabled']) ? '1' : '0';
    
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name='meta_pixel_id'");
    $stmt1->execute();
    if ($stmt1->fetchColumn() > 0) {
        $pdo->prepare("UPDATE settings SET setting_value=? WHERE key_name='meta_pixel_id'")->execute([$pixel_id]);
    } else {
        $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES ('meta_pixel_id', ?)")->execute([$pixel_id]);
    }
    
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name='meta_pixel_enabled'");
    $stmt2->execute();
    if ($stmt2->fetchColumn() > 0) {
        $pdo->prepare("UPDATE settings SET setting_value=? WHERE key_name='meta_pixel_enabled'")->execute([$pixel_enabled]);
    } else {
        $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES ('meta_pixel_enabled', ?)")->execute([$pixel_enabled]);
    }

    header("Location: admin_settings.php?tab=meta&msg=meta_updated");
    exit;
}

// 1.2. تحديث إعدادات بوابات الدفع الإلكتروني
if (isset($_POST['update_payment_settings'])) {
    $pay_settings = [
        'cod_enabled' => isset($_POST['cod_enabled']) ? '1' : '0',
        'vodafone_cash_enabled' => isset($_POST['vodafone_cash_enabled']) ? '1' : '0',
        'vodafone_cash_number' => trim($_POST['vodafone_cash_number'] ?? ''),
        'instapay_enabled' => isset($_POST['instapay_enabled']) ? '1' : '0',
        'instapay_address' => trim($_POST['instapay_address'] ?? ''),
        'instapay_name' => trim($_POST['instapay_name'] ?? ''),
        'cham_cash_enabled' => isset($_POST['cham_cash_enabled']) ? '1' : '0',
        'cham_cash_number' => trim($_POST['cham_cash_number'] ?? ''),
        'cham_cash_name' => trim($_POST['cham_cash_name'] ?? ''),
        'syriatel_cash_number' => trim($_POST['syriatel_cash_number'] ?? ''),
        'paypal_enabled' => isset($_POST['paypal_enabled']) ? '1' : '0',
        'paypal_email' => trim($_POST['paypal_email'] ?? ''),
        'paypal_client_id' => trim($_POST['paypal_client_id'] ?? ''),
        'paymob_enabled' => isset($_POST['paymob_enabled']) ? '1' : '0',
        'paymob_api_key' => trim($_POST['paymob_api_key'] ?? ''),
        'paymob_integration_id_card' => trim($_POST['paymob_integration_id_card'] ?? ''),
        'paymob_integration_id_wallet' => trim($_POST['paymob_integration_id_wallet'] ?? ''),
        'paymob_iframe_id' => trim($_POST['paymob_iframe_id'] ?? '')
    ];
    
    foreach ($pay_settings as $key => $val) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = ?");
        $stmt->execute([$key]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = ?")->execute([$val, $key]);
        } else {
            $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES (?, ?)")->execute([$key, $val]);
        }
    }

    header("Location: admin_settings.php?tab=payments&msg=payments_updated");
    exit;
}

// 1.3. تحديث إعدادات الدول العامة والعملات المفضلة
if (isset($_POST['update_country_settings'])) {
    $enable_multi = isset($_POST['enable_multi_country']) ? '1' : '0';
    $def_country = trim($_POST['default_country'] ?? 'مصر');
    $curr_mode = trim($_POST['preferred_currency_mode'] ?? 'local');
    $selected_countries = isset($_POST['active_countries']) && is_array($_POST['active_countries']) ? $_POST['active_countries'] : ['مصر'];
    $active_countries_json = json_encode(array_values($selected_countries), JSON_UNESCAPED_UNICODE);

    $settings_to_update = [
        'enable_multi_country' => $enable_multi,
        'default_country' => $def_country,
        'preferred_currency_mode' => $curr_mode,
        'active_countries' => $active_countries_json
    ];

    foreach ($settings_to_update as $k => $v) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = ?");
        $stmt->execute([$k]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = ?")->execute([$v, $k]);
        } else {
            $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES (?, ?)")->execute([$k, $v]);
        }
    }

    header("Location: admin_settings.php?tab=shipping&country=" . urlencode($def_country) . "&msg=country_settings_updated");
    exit;
}

// 1.4. تحديث أسعار وحالة مناطق الشحن لدولة معينة
if (isset($_POST['update_shipping_zones'])) {
    $current_country = trim($_POST['country_filter'] ?? 'مصر');
    $costs = isset($_POST['gov_cost']) && is_array($_POST['gov_cost']) ? $_POST['gov_cost'] : [];
    $active_status = isset($_POST['gov_active']) ? $_POST['gov_active'] : [];
    $currencies = isset($_POST['gov_currency']) && is_array($_POST['gov_currency']) ? $_POST['gov_currency'] : [];

    $stmt_update = $pdo->prepare("UPDATE shipping_zones SET cost = ?, is_active = ?, currency_symbol = ? WHERE id = ?");
    foreach($costs as $id => $cost) {
        $is_active = isset($active_status[$id]) ? 1 : 0;
        $curr = isset($currencies[$id]) ? trim($currencies[$id]) : ($supported_countries_data[$current_country]['currency'] ?? 'ج.م');
        $stmt_update->execute([$cost, $is_active, $curr, $id]);
    }
    header("Location: admin_settings.php?tab=shipping&country=" . urlencode($current_country) . "&msg=shipping_updated");
    exit;
}

// 1.5. تطبيق سعر شحن موحد على جميع محافظات دولة
if (isset($_POST['mass_update_country_cost'])) {
    $target_country = trim($_POST['country_filter'] ?? 'مصر');
    $mass_cost = (float)($_POST['mass_cost'] ?? 0);
    $stmt_mass = $pdo->prepare("UPDATE shipping_zones SET cost = ? WHERE country_name = ?");
    $stmt_mass->execute([$mass_cost, $target_country]);
    header("Location: admin_settings.php?tab=shipping&country=" . urlencode($target_country) . "&msg=mass_updated");
    exit;
}

// 1.6. إضافة محافظة أو مدينة جديدة لدولة
if (isset($_POST['add_new_governorate'])) {
    $target_country = trim($_POST['target_country'] ?? 'مصر');
    $new_gov_name = trim($_POST['new_gov_name'] ?? '');
    $new_gov_cost = (float)($_POST['new_gov_cost'] ?? 50.00);
    $country_info = $supported_countries_data[$target_country] ?? ['code' => 'XX', 'currency' => 'ج.م'];

    if (!empty($new_gov_name)) {
        $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM shipping_zones WHERE country_name = ? AND gov_name = ?");
        $stmt_check->execute([$target_country, $new_gov_name]);
        if ($stmt_check->fetchColumn() == 0) {
            $pdo->prepare("INSERT INTO shipping_zones (country_name, country_code, currency_symbol, gov_name, cost, is_active) VALUES (?, ?, ?, ?, ?, 1)")
                ->execute([$target_country, $country_info['code'] ?? 'XX', $country_info['currency'] ?? 'ج.م', $new_gov_name, $new_gov_cost]);
        }
    }
    header("Location: admin_settings.php?tab=shipping&country=" . urlencode($target_country) . "&msg=gov_added");
    exit;
}

// 1.7. حذف محافظة من دولة
if (isset($_GET['action']) && $_GET['action'] === 'delete_gov' && isset($_GET['gov_id'])) {
    $gov_id = (int)$_GET['gov_id'];
    $c_return = trim($_GET['country'] ?? 'مصر');
    $pdo->prepare("DELETE FROM shipping_zones WHERE id = ?")->execute([$gov_id]);
    header("Location: admin_settings.php?tab=shipping&country=" . urlencode($c_return) . "&msg=gov_deleted");
    exit;
}

$selected_country_tab = isset($_GET['country']) && !empty($_GET['country']) ? trim($_GET['country']) : ($settings['default_country'] ?? 'مصر');
$active_countries_list = !empty($settings['active_countries']) ? json_decode($settings['active_countries'], true) : array_keys($supported_countries_data);
if (!is_array($active_countries_list) || empty($active_countries_list)) {
    $active_countries_list = array_keys($supported_countries_data);
}

$shipping_zones = $pdo->prepare("SELECT * FROM shipping_zones WHERE country_name = ? ORDER BY id ASC");
$shipping_zones->execute([$selected_country_tab]);
$shipping_zones = $shipping_zones->fetchAll(PDO::FETCH_ASSOC);

// 2. إضافة صورة جديدة للسلايدر
if (isset($_POST['add_slide'])) {
    $title = trim($_POST['slide_title']);
    $subtitle = trim($_POST['slide_subtitle']);
    $link = trim($_POST['slide_link']);
    $sort = (int)$_POST['slide_sort'];
    
    $slide_img = uploadImage($_FILES['slide_file']);
    if ($slide_img) {
        $pdo->prepare("INSERT INTO home_slides (image_url, title, subtitle, link_url, sort_order) VALUES (?, ?, ?, ?, ?)")
            ->execute([$slide_img, $title, $subtitle, $link, $sort]);
        header("Location: admin_settings.php?tab=theme&msg=slide_added");
        exit;
    } else {
        header("Location: admin_settings.php?tab=theme&error=upload_failed");
        exit;
    }
}

// 3. حذف شريحة من السلايدر
if (isset($_GET['action']) && $_GET['action'] == 'delete_slide' && isset($_GET['id'])) {
    $slide_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT image_url FROM home_slides WHERE id = ?");
    $stmt->execute([$slide_id]);
    $img_path = $stmt->fetchColumn();
    
    if ($img_path) {
        if (file_exists($img_path) && strpos($img_path, 'uploads/') !== false) {
            unlink($img_path);
        }
        $pdo->prepare("DELETE FROM home_slides WHERE id = ?")->execute([$slide_id]);
        header("Location: admin_settings.php?tab=theme&msg=slide_deleted");
        exit;
    }
}

// 4. إضافة قسم جديد للمتجر
if (isset($_POST['add_category'])) {
    $cat_name = trim($_POST['cat_name']);
    $parent_id = !empty($_POST['parent_id']) && $_POST['parent_id'] !== 'none' ? (int)$_POST['parent_id'] : null;
    $cat_img = uploadImage($_FILES['cat_image']) ?: 'https://placehold.co/600x800/f8f5f0/D4AF37?text=';
    
    try {
        $pdo->prepare("INSERT INTO categories (name, image_url, parent_id) VALUES (?, ?, ?)")->execute([$cat_name, $cat_img, $parent_id]);
        header("Location: admin_settings.php?tab=categories&msg=cat_added");
        exit;
    } catch (PDOException $e) {
        header("Location: admin_settings.php?tab=categories&error=cat_duplicate");
        exit;
    }
}

// 5. تعديل القسم المحدد
if (isset($_POST['edit_category'])) {
    $cat_id = (int)$_POST['cat_id'];
    $newName = trim($_POST['cat_name']);
    $parent_id = !empty($_POST['parent_id']) && $_POST['parent_id'] !== 'none' ? (int)$_POST['parent_id'] : null;
    
    $oldName_stmt = $pdo->prepare("SELECT name FROM categories WHERE id=?");
    $oldName_stmt->execute([$cat_id]);
    $oldName = $oldName_stmt->fetchColumn();
    
    $newImg = uploadImage($_FILES['cat_image']);
    if ($newImg) {
        $pdo->prepare("UPDATE categories SET name=?, image_url=?, parent_id=? WHERE id=?")->execute([$newName, $newImg, $parent_id, $cat_id]);
    } else {
        $pdo->prepare("UPDATE categories SET name=?, parent_id=? WHERE id=?")->execute([$newName, $parent_id, $cat_id]);
    }
    
    if ($oldName && $oldName !== $newName) {
        $pdo->prepare("UPDATE products SET category=? WHERE category=?")->execute([$newName, $oldName]);
        $pdo->prepare("UPDATE products SET sub_category=? WHERE sub_category=?")->execute([$newName, $oldName]);
    }
    header("Location: admin_settings.php?tab=categories&msg=cat_edited");
    exit;
}

// 6. حذف القسم
if (isset($_GET['action']) && $_GET['action'] == 'delete_category' && isset($_GET['id'])) {
    $cat_id = (int)$_GET['id'];
    $pdo->prepare("UPDATE categories SET parent_id = NULL WHERE parent_id = ?")->execute([$cat_id]);
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cat_id]);
    header("Location: admin_settings.php?tab=categories&msg=cat_deleted");
    exit;
}

// إعادة جلب الإعدادات المحدثة
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $settings[$row['key_name']] = $row['setting_value']; }

$slides = $pdo->query("SELECT * FROM home_slides ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'admin_nav.php';

$active_tab = $_GET['tab'] ?? (isset($_GET['edit_cat_id']) ? 'categories' : 'general');
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <div class="mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-royal-gold/10 pb-4">
        <div>
            <h2 class="text-2xl font-serif font-bold text-royal-dark">
                <?php 
                if ($active_tab === 'general') echo '🏛️ هوية المتجر والشعار والعملة';
                elseif ($active_tab === 'security') echo '🔐 كلمة مرور المسؤول والحساب';
                elseif ($active_tab === 'colors') echo '🎨 ألوان وهوية تصميم المتجر (Theme Palette)';
                elseif ($active_tab === 'payments') echo '💳 وسائل وبوابات الدفع الإلكتروني';
                elseif ($active_tab === 'shipping') echo '🚚 مناطق ومصاريف الشحن والتوصيل';
                elseif ($active_tab === 'theme') echo '🖼️ إعدادات السلايدر وشريط الإعلانات';
                elseif ($active_tab === 'categories') echo '🏷️ إدارة الأقسام والتصنيفات';
                elseif ($active_tab === 'meta') echo '📈 إعدادات ميتا وإعلانات فيسبوك';
                else echo 'مركز إعدادات المتجر';
                ?>
            </h2>
            <p class="text-xs text-gray-400 mt-1 font-light">تخصيص كامل لمعلومات المتجر، بيانات الحماية، طرق الدفع، والشحن بكل سهولة.</p>
        </div>
    </div>

    <!-- التنبيهات ورسائل النجاح -->
    <?php if(isset($_GET['msg'])): ?>
        <div class="bg-green-50 text-green-700 p-4 mb-6 rounded-xl border border-green-200 text-xs font-bold animate-fade-in">
            <i class="fa-solid fa-circle-check mr-1 text-sm"></i>
            <?php 
            if($_GET['msg'] == 'general_updated') echo 'تم تحديث هوية المتجر واللوجو والعملة بنجاح!';
            elseif($_GET['msg'] == 'logo_removed') echo 'تم إزالة الشعار بنجاح والاعتماد على اسم المتجر النصي!';
            elseif($_GET['msg'] == 'password_updated') echo 'تم تغيير وتحديث كلمة مرور المسؤول بنجاح!';
            elseif($_GET['msg'] == 'colors_updated') echo 'تم حفظ وتطبيق لوحة ألوان المتجر بنجاح على كامل الموقع!';
            elseif($_GET['msg'] == 'announcement_updated') echo 'تم تحديث شريط الإعلانات العلوي بنجاح!';
            elseif($_GET['msg'] == 'meta_updated') echo 'تم تحديث وتفعيل إعدادات Meta Pixel بنجاح!';
            elseif($_GET['msg'] == 'payments_updated') echo 'تم تحديث وتفعيل إعدادات بوابات ووسائل الدفع بنجاح!';
            elseif($_GET['msg'] == 'shipping_updated') echo 'تم تحديث أسعار وحالة مناطق الشحن بنجاح!';
            elseif($_GET['msg'] == 'slide_added') echo 'تم إضافة شريحة البانر الجديد بنجاح!';
            elseif($_GET['msg'] == 'slide_deleted') echo 'تم حذف شريحة السلايدر بنجاح!';
            elseif($_GET['msg'] == 'cat_added') echo 'تم إضافة القسم الجديد بنجاح!';
            elseif($_GET['msg'] == 'cat_edited') echo 'تم تعديل القسم وتحديث المنتجات بنجاح!';
            elseif($_GET['msg'] == 'cat_deleted') echo 'تم حذف القسم بنجاح!';
            ?>
        </div>
    <?php endif; ?>

    <?php if(isset($_GET['error'])): ?>
        <div class="bg-red-50 text-red-700 p-4 mb-6 rounded-xl border border-red-200 text-xs font-bold animate-fade-in">
            <i class="fa-solid fa-circle-exclamation mr-1 text-sm"></i>
            <?php 
            if($_GET['error'] == 'wrong_current_password') echo 'كلمة المرور الحالية غير صحيحة! يرجى إعادة المحاولة.';
            elseif($_GET['error'] == 'password_too_short') echo 'كلمة المرور الجديدة يجب ألا تقل عن 6 أحرف أو أرقام.';
            elseif($_GET['error'] == 'password_mismatch') echo 'كلمة المرور الجديدة وتأكيدها غير متطابقين!';
            elseif($_GET['error'] == 'upload_failed') echo 'فشل رفع الصورة؛ يرجى التحقق من الامتداد ومحاولة الرفع مجدداً.';
            elseif($_GET['error'] == 'cat_duplicate') echo 'القسم موجود بالفعل! يرجى اختيار اسم قسم مختلف.';
            elseif($_GET['error'] == 'user_not_found') echo 'تعذر العثور على حساب المسؤول!';
            ?>
        </div>
    <?php endif; ?>

    <!-- ==================== TABS CONTENT ==================== -->

    <!-- TAB 0: 🏛️ هوية المتجر واللوجو والعملة -->
    <?php if ($active_tab === 'general'): ?>
    <div class="bg-white p-6 md:p-8 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in">
        <h3 class="font-serif font-bold text-sm text-royal-dark mb-6 border-b pb-3 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fa-solid fa-store text-royal-darkgold"></i> هوية المتجر، الشعار، والعملة (White-Label Store Settings)</span>
            <span class="text-[10px] text-gray-400 font-bold">💡 تتيح لك تخصيص المتجر لأي نشاط تجاري فوراً</span>
        </h3>
        
        <form method="POST" action="admin_settings.php?tab=general" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-2">اسم المتجر الرسمي *</label>
                    <input type="text" name="store_name" value="<?php echo htmlspecialchars($settings['store_name'] ?? 'المتجر الإلكتروني'); ?>" required placeholder="مثال: إلكتروتك / متجر الهواتف" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs font-bold bg-royal-cream/20">
                    <p class="text-[10px] text-gray-400 mt-1">يظهر في ترويسة الموقع، الفوتر، رسائل البريد، والفواتير.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-2">الشعار اللفظي أو الوصف المختصر (Tagline)</label>
                    <input type="text" name="store_tagline" value="<?php echo htmlspecialchars($settings['store_tagline'] ?? ''); ?>" placeholder="مثال: وجهتك الأولى لأحدث الأجهزة والإلكترونيات" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-royal-cream/20">
                    <p class="text-[10px] text-gray-400 mt-1">يظهر بجانب اللوجو وفي أعلى الموقع أو الفوتر.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-xs font-bold text-gray-700 mb-2">نبذة عن المتجر (تظهر في الفوتر وصفحة من نحن)</label>
                    <textarea name="store_description" rows="3" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs leading-relaxed bg-royal-cream/20"><?php echo htmlspecialchars($settings['store_description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-2">رمز العملة الرسمي</label>
                    <input type="text" name="store_currency" value="<?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?>" placeholder="مثال: ج.م أو ر.س أو $" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs font-bold bg-royal-cream/20">
                    <p class="text-[10px] text-gray-400 mt-1">يظهر بجانب أسعار جميع المنتجات والشحن والسلة.</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-2">شعار المتجر (Logo Image)</label>
                    <div class="flex items-center gap-4">
                        <?php if (!empty($settings['store_logo'])): ?>
                            <div class="p-2 border rounded-xl bg-gray-50 flex items-center gap-2">
                                <img src="<?php echo htmlspecialchars($settings['store_logo']); ?>" alt="Store Logo" class="h-10 max-w-[120px] object-contain">
                                <a href="admin_settings.php?tab=general&action=remove_logo" onclick="return confirm('إزالة صورة اللوجو والاعتماد على الاسم النصي؟')" class="text-red-500 hover:text-red-700 text-xs font-bold p-1" title="حذف الشعار"><i class="fa-solid fa-trash-can"></i></a>
                            </div>
                        <?php endif; ?>
                        <input type="file" name="store_logo" accept="image/*" class="w-full text-xs text-gray-500">
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">PNG أو WebP شفاف مقاس مفضل 250x70. (في حال تركه فارغاً سيتم عرض اسم المتجر بتصميم راقٍ).</p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-2">أيقونة المتصفح والتطبيق (Favicon / App Icon)</label>
                    <div class="flex items-center gap-4">
                        <?php if (!empty($settings['store_favicon'])): ?>
                            <img src="<?php echo htmlspecialchars($settings['store_favicon']); ?>" alt="Favicon" class="w-8 h-8 rounded-lg border object-cover">
                        <?php endif; ?>
                        <input type="file" name="store_favicon" accept="image/*" class="w-full text-xs text-gray-500">
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">تظهر في لسان المتصفح وأيقونة التطبيق على هواتف العملاء.</p>
                </div>

            </div>

            <div class="pt-4 border-t">
                <button type="submit" name="update_general_settings" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold px-10 py-3.5 text-xs rounded-xl shadow btn-shine transition-all">
                    حفظ هوية المتجر والإعدادات العامة
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- TAB 0.1: 🔐 كلمة مرور المسؤول والحساب -->
    <?php if ($active_tab === 'security'): ?>
    <div class="bg-white p-6 md:p-8 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in max-w-2xl">
        <h3 class="font-serif font-bold text-sm text-royal-dark mb-6 border-b pb-3 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fa-solid fa-shield-halved text-royal-darkgold"></i> تغيير كلمة مرور حساب المسؤول الأساسي</span>
            <span class="text-[10px] text-green-700 bg-green-50 px-2 py-0.5 rounded-full font-bold border border-green-200">مشفرة بأمان BCRYPT</span>
        </h3>
        
        <form method="POST" action="admin_settings.php?tab=security" class="space-y-5">
            <div>
                <label class="block text-xs font-bold text-gray-700 mb-2">كلمة المرور الحالية *</label>
                <div class="relative">
                    <input type="password" name="current_password" required placeholder="أدخل كلمة المرور الحالية" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-royal-cream/20">
                </div>
                <p class="text-[10px] text-gray-400 mt-1">كلمة المرور الافتراضية عند أول تثبيت كانت: <code>admin123</code></p>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-2">كلمة المرور الجديدة *</label>
                <input type="password" name="new_password" required minlength="6" placeholder="أدخل كلمة مرور جديدة قوية (6 أحرف أو أرقام على الأقل)" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-royal-cream/20">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-700 mb-2">تأكيد كلمة المرور الجديدة *</label>
                <input type="password" name="confirm_password" required minlength="6" placeholder="أعد كتابة كلمة المرور الجديدة للتأكيد" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-royal-cream/20">
            </div>

            <div class="pt-4 border-t">
                <button type="submit" name="update_admin_password" class="bg-red-700 text-white hover:bg-red-800 font-bold px-10 py-3.5 text-xs rounded-xl shadow transition-all flex items-center gap-2">
                    <i class="fa-solid fa-key"></i> تحديث كلمة المرور الآن
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- TAB 0.2: 🎨 ألوان وتصميم المتجر (Theme & Color Palette) -->
    <?php if ($active_tab === 'colors'): ?>
    <div class="bg-white p-6 md:p-8 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in">
        <h3 class="font-serif font-bold text-sm text-royal-dark mb-6 border-b pb-3 flex items-center justify-between">
            <span class="flex items-center gap-2"><i class="fa-solid fa-palette text-royal-darkgold"></i> تخصيص لوحة ألوان وهوية تصميم المتجر (Custom Theme Palette)</span>
            <span class="text-[10px] text-gray-400 font-bold">💡 يتم تطبيق الألوان فوراً على كافة الصفحات والأزرار والهيدر والفواتير</span>
        </h3>

        <!-- باليتات ألوان جاهزة بضغطة زر واحدة (Theme Presets) -->
        <div class="mb-8 p-5 bg-royal-cream/40 rounded-2xl border border-royal-gold/15">
            <h4 class="text-xs font-bold text-royal-dark mb-3 flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-royal-darkgold"></i> نماذج وقوالب ألوان جاهزة (اضغط لتطبيق القالب فوراً):
            </h4>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <button type="button" onclick="applyColorPreset('modern_blue')" class="p-3 rounded-xl border border-gray-200 bg-white hover:border-blue-500 hover:shadow-md transition text-center group">
                    <div class="flex h-5 rounded-lg overflow-hidden mb-2 border">
                        <div class="w-1/3 bg-[#2563eb]"></div>
                        <div class="w-1/3 bg-[#3b82f6]"></div>
                        <div class="w-1/3 bg-[#0f172a]"></div>
                    </div>
                    <span class="text-[11px] font-bold text-gray-700 block group-hover:text-blue-600">أزرق تقني عصري</span>
                </button>

                <button type="button" onclick="applyColorPreset('midnight_black')" class="p-3 rounded-xl border border-gray-200 bg-white hover:border-gray-800 hover:shadow-md transition text-center group">
                    <div class="flex h-5 rounded-lg overflow-hidden mb-2 border">
                        <div class="w-1/3 bg-[#111827]"></div>
                        <div class="w-1/3 bg-[#475569]"></div>
                        <div class="w-1/3 bg-[#000000]"></div>
                    </div>
                    <span class="text-[11px] font-bold text-gray-700 block group-hover:text-gray-900">أسود وأبيض مونوكروم</span>
                </button>

                <button type="button" onclick="applyColorPreset('royal_gold')" class="p-3 rounded-xl border border-gray-200 bg-white hover:border-amber-500 hover:shadow-md transition text-center group">
                    <div class="flex h-5 rounded-lg overflow-hidden mb-2 border">
                        <div class="w-1/3 bg-[#D4AF37]"></div>
                        <div class="w-1/3 bg-[#A0814A]"></div>
                        <div class="w-1/3 bg-[#0E3326]"></div>
                    </div>
                    <span class="text-[11px] font-bold text-gray-700 block group-hover:text-amber-600">ذهبي وفحمي فاخر</span>
                </button>

                <button type="button" onclick="applyColorPreset('emerald_green')" class="p-3 rounded-xl border border-gray-200 bg-white hover:border-emerald-500 hover:shadow-md transition text-center group">
                    <div class="flex h-5 rounded-lg overflow-hidden mb-2 border">
                        <div class="w-1/3 bg-[#059669]"></div>
                        <div class="w-1/3 bg-[#10b981]"></div>
                        <div class="w-1/3 bg-[#064e3b]"></div>
                    </div>
                    <span class="text-[11px] font-bold text-gray-700 block group-hover:text-emerald-600">أخضر زمردي طبيعي</span>
                </button>

                <button type="button" onclick="applyColorPreset('crimson_red')" class="p-3 rounded-xl border border-gray-200 bg-white hover:border-red-500 hover:shadow-md transition text-center group">
                    <div class="flex h-5 rounded-lg overflow-hidden mb-2 border">
                        <div class="w-1/3 bg-[#dc2626]"></div>
                        <div class="w-1/3 bg-[#ef4444]"></div>
                        <div class="w-1/3 bg-[#1c1917]"></div>
                    </div>
                    <span class="text-[11px] font-bold text-gray-700 block group-hover:text-red-600">عنابي ملكي فخم</span>
                </button>

                <button type="button" onclick="applyColorPreset('electric_violet')" class="p-3 rounded-xl border border-gray-200 bg-white hover:border-purple-500 hover:shadow-md transition text-center group">
                    <div class="flex h-5 rounded-lg overflow-hidden mb-2 border">
                        <div class="w-1/3 bg-[#7c3aed]"></div>
                        <div class="w-1/3 bg-[#8b5cf6]"></div>
                        <div class="w-1/3 bg-[#1e1b4b]"></div>
                    </div>
                    <span class="text-[11px] font-bold text-gray-700 block group-hover:text-purple-600">بنفسجي حديث</span>
                </button>
            </div>
        </div>

        <form method="POST" action="admin_settings.php?tab=colors" class="space-y-6" id="colors-form">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- 1. اللون الرئيسي -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">اللون الرئيسي للمتجر (Primary)</label>
                    <p class="text-[10px] text-gray-400 mb-3">للأزرار الأساسية، العناوين، والروابط النشطة.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_primary" value="<?php echo htmlspecialchars($settings['theme_primary_color'] ?? '#2563eb'); ?>" onchange="syncColor('primary', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_primary_color" id="hex_primary" value="<?php echo htmlspecialchars($settings['theme_primary_color'] ?? '#2563eb'); ?>" oninput="syncColor('primary', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 2. اللون الثانوي -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">اللون الثانوي (Secondary)</label>
                    <p class="text-[10px] text-gray-400 mb-3">للتأثيرات الجانبية، والأسعار، والنصوص الفرعية.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_secondary" value="<?php echo htmlspecialchars($settings['theme_secondary_color'] ?? '#1d4ed8'); ?>" onchange="syncColor('secondary', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_secondary_color" id="hex_secondary" value="<?php echo htmlspecialchars($settings['theme_secondary_color'] ?? '#1d4ed8'); ?>" oninput="syncColor('secondary', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 3. لون التمييز والتدرجات -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">لون التمييز والتدرجات (Accent)</label>
                    <p class="text-[10px] text-gray-400 mb-3">لتدرجات الأزرار، والبادجات، ونقاط التمرير.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_accent" value="<?php echo htmlspecialchars($settings['theme_accent_color'] ?? '#3b82f6'); ?>" onchange="syncColor('accent', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_accent_color" id="hex_accent" value="<?php echo htmlspecialchars($settings['theme_accent_color'] ?? '#3b82f6'); ?>" oninput="syncColor('accent', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 4. خلفية الهيدر والفوتر -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">خلفية الهيدر والفوتر (Header/Footer)</label>
                    <p class="text-[10px] text-gray-400 mb-3">لون شريط الترويسة العلوي وأسفل الموقع.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_header_bg" value="<?php echo htmlspecialchars($settings['theme_header_bg'] ?? '#0f172a'); ?>" onchange="syncColor('header_bg', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_header_bg" id="hex_header_bg" value="<?php echo htmlspecialchars($settings['theme_header_bg'] ?? '#0f172a'); ?>" oninput="syncColor('header_bg', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 5. نصوص الهيدر والفوتر -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">لون نصوص الهيدر والفوتر</label>
                    <p class="text-[10px] text-gray-400 mb-3">لون الروابط والكتابة داخل الهيدر والفوتر.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_header_text" value="<?php echo htmlspecialchars($settings['theme_header_text'] ?? '#ffffff'); ?>" onchange="syncColor('header_text', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_header_text" id="hex_header_text" value="<?php echo htmlspecialchars($settings['theme_header_text'] ?? '#ffffff'); ?>" oninput="syncColor('header_text', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 6. خلفية الموقع العامة -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">خلفية الموقع العامة (Body BG)</label>
                    <p class="text-[10px] text-gray-400 mb-3">اللون العام لخلفية جميع صفحات المتجر.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_body_bg" value="<?php echo htmlspecialchars($settings['theme_body_bg'] ?? '#f8fafc'); ?>" onchange="syncColor('body_bg', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_body_bg" id="hex_body_bg" value="<?php echo htmlspecialchars($settings['theme_body_bg'] ?? '#f8fafc'); ?>" oninput="syncColor('body_bg', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 7. خلفية الكروت والصناديق -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">خلفية كروت المنتجات (Card BG)</label>
                    <p class="text-[10px] text-gray-400 mb-3">لون خلفية بطاقات العرض وصناديق الفاتورة.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_card_bg" value="<?php echo htmlspecialchars($settings['theme_card_bg'] ?? '#ffffff'); ?>" onchange="syncColor('card_bg', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_card_bg" id="hex_card_bg" value="<?php echo htmlspecialchars($settings['theme_card_bg'] ?? '#ffffff'); ?>" oninput="syncColor('card_bg', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 8. لون أزرار الشراء -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">لون أزرار الشراء والسلة (Button BG)</label>
                    <p class="text-[10px] text-gray-400 mb-3">لون الأزرار التفاعلية للشراء وإتمام الطلب.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_btn_color" value="<?php echo htmlspecialchars($settings['theme_btn_color'] ?? '#2563eb'); ?>" onchange="syncColor('btn_color', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_btn_color" id="hex_btn_color" value="<?php echo htmlspecialchars($settings['theme_btn_color'] ?? '#2563eb'); ?>" oninput="syncColor('btn_color', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

                <!-- 9. لون نصوص الأزرار -->
                <div class="p-4 border border-gray-200 rounded-xl bg-gray-50/50">
                    <label class="block text-xs font-bold text-gray-700 mb-1">لون كتابة الأزرار (Button Text)</label>
                    <p class="text-[10px] text-gray-400 mb-3">لون الخط المكتوب داخل أزرار الشراء.</p>
                    <div class="flex items-center gap-3">
                        <input type="color" id="picker_btn_text" value="<?php echo htmlspecialchars($settings['theme_btn_text'] ?? '#ffffff'); ?>" onchange="syncColor('btn_text', this.value)" class="w-12 h-10 border rounded-lg cursor-pointer bg-white p-1">
                        <input type="text" name="theme_btn_text" id="hex_btn_text" value="<?php echo htmlspecialchars($settings['theme_btn_text'] ?? '#ffffff'); ?>" oninput="syncColor('btn_text', this.value)" class="w-full p-2.5 border rounded-lg text-xs font-mono font-bold uppercase bg-white">
                    </div>
                </div>

            </div>

            <!-- المعاينة الحية المباشرة (Live Preview Box) -->
            <div class="mt-8 border border-dashed border-gray-300 p-6 rounded-2xl bg-gray-50">
                <h4 class="text-xs font-bold text-royal-dark mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-eye text-royal-darkgold"></i> معاينة مباشرة للمتجر بالألوان المحددة:
                </h4>
                
                <div id="preview_container" class="rounded-xl overflow-hidden border shadow-sm p-4 space-y-4" style="background-color: <?php echo $settings['theme_body_bg'] ?? '#f8fafc'; ?>;">
                    <!-- محاكاة الهيدر -->
                    <div id="preview_header" class="p-3 rounded-lg flex justify-between items-center" style="background-color: <?php echo $settings['theme_header_bg'] ?? '#0f172a'; ?>; color: <?php echo $settings['theme_header_text'] ?? '#ffffff'; ?>;">
                        <div class="font-bold text-xs flex items-center gap-2">
                            <i class="fa-solid fa-store" id="preview_icon" style="color: <?php echo $settings['theme_primary_color'] ?? '#2563eb'; ?>;"></i>
                            <span><?php echo htmlspecialchars($settings['store_name'] ?? 'المتجر الإلكتروني'); ?></span>
                        </div>
                        <div class="text-[10px] space-x-3 space-x-reverse flex items-center">
                            <span class="opacity-80">الرئيسية</span>
                            <span class="opacity-80">المتجر</span>
                            <span class="px-2 py-1 rounded font-bold text-xs" id="preview_badge" style="background-color: <?php echo $settings['theme_primary_color'] ?? '#2563eb'; ?>; color: #ffffff;">السلة (1)</span>
                        </div>
                    </div>

                    <!-- محاكاة كارت منتج -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div id="preview_card" class="p-4 rounded-xl border shadow-sm flex flex-col justify-between" style="background-color: <?php echo $settings['theme_card_bg'] ?? '#ffffff'; ?>;">
                            <div class="h-28 rounded-lg mb-3 flex items-center justify-center font-bold text-xs" style="background: linear-gradient(135deg, <?php echo $settings['theme_accent_color'] ?? '#3b82f6'; ?> 0%, <?php echo $settings['theme_primary_color'] ?? '#2563eb'; ?> 100%); color: #ffffff;">
                                800 × 1000 px
                            </div>
                            <div>
                                <h5 class="font-bold text-xs text-gray-800">اسم المنتج المعروض</h5>
                                <div class="flex justify-between items-center my-2">
                                    <span id="preview_price" class="font-serif font-bold text-sm" style="color: <?php echo $settings['theme_secondary_color'] ?? '#1d4ed8'; ?>;">250 <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
                                    <span class="text-[9px] bg-red-100 text-red-600 px-2 py-0.5 rounded font-bold">خصم 20%</span>
                                </div>
                                <button type="button" id="preview_button" class="w-full py-2.5 rounded-lg text-xs font-bold shadow transition" style="background-color: <?php echo $settings['theme_btn_color'] ?? '#2563eb'; ?>; color: <?php echo $settings['theme_btn_text'] ?? '#ffffff'; ?>;">
                                    أضف إلى الحقيبة <i class="fa-solid fa-cart-plus mr-1"></i>
                                </button>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl border flex flex-col justify-center space-y-2 text-xs" style="background-color: <?php echo $settings['theme_card_bg'] ?? '#ffffff'; ?>;">
                            <span class="text-[10px] text-gray-400 font-bold">ملخص الطلب</span>
                            <div class="flex justify-between font-bold">
                                <span>الإجمالي:</span>
                                <span id="preview_price_summary" style="color: <?php echo $settings['theme_primary_color'] ?? '#2563eb'; ?>;">250 <?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?></span>
                            </div>
                            <button type="button" id="preview_btn_checkout" class="w-full py-2.5 rounded-lg text-xs font-bold shadow transition" style="background-color: <?php echo $settings['theme_btn_color'] ?? '#2563eb'; ?>; color: <?php echo $settings['theme_btn_text'] ?? '#ffffff'; ?>;">
                                إتمام عملية الشراء 🚀
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t flex flex-col sm:flex-row justify-between items-center gap-4">
                <button type="submit" name="update_theme_colors" class="w-full sm:w-auto bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal font-bold px-10 py-3.5 text-xs rounded-xl shadow btn-shine transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i> حفظ وتطبيق الألوان على كامل المتجر
                </button>

                <button type="button" onclick="applyColorPreset('modern_blue')" class="text-xs text-gray-500 hover:text-royal-dark font-bold underline">
                    استعادة الألوان الافتراضية القياسية
                </button>
            </div>
        </form>
    </div>

    <!-- Script to handle dynamic color syncing and presets -->
    <script>
        const colorPresets = {
            modern_blue: {
                primary: '#2563eb', secondary: '#1d4ed8', accent: '#3b82f6',
                header_bg: '#0f172a', header_text: '#ffffff',
                body_bg: '#f8fafc', card_bg: '#ffffff',
                btn_color: '#2563eb', btn_text: '#ffffff'
            },
            midnight_black: {
                primary: '#000000', secondary: '#334155', accent: '#64748b',
                header_bg: '#111827', header_text: '#ffffff',
                body_bg: '#f9fafb', card_bg: '#ffffff',
                btn_color: '#000000', btn_text: '#ffffff'
            },
            royal_gold: {
                primary: '#D4AF37', secondary: '#A0814A', accent: '#D4BE92',
                header_bg: '#0E3326', header_text: '#ffffff',
                body_bg: '#FAF8F5', card_bg: '#ffffff',
                btn_color: '#A0814A', btn_text: '#ffffff'
            },
            emerald_green: {
                primary: '#059669', secondary: '#047857', accent: '#10b981',
                header_bg: '#064e3b', header_text: '#ffffff',
                body_bg: '#f0fdf4', card_bg: '#ffffff',
                btn_color: '#059669', btn_text: '#ffffff'
            },
            crimson_red: {
                primary: '#dc2626', secondary: '#b91c1c', accent: '#ef4444',
                header_bg: '#1c1917', header_text: '#ffffff',
                body_bg: '#fef2f2', card_bg: '#ffffff',
                btn_color: '#dc2626', btn_text: '#ffffff'
            },
            electric_violet: {
                primary: '#7c3aed', secondary: '#6d28d9', accent: '#8b5cf6',
                header_bg: '#1e1b4b', header_text: '#ffffff',
                body_bg: '#faf5ff', card_bg: '#ffffff',
                btn_color: '#7c3aed', btn_text: '#ffffff'
            }
        };

        function syncColor(type, val) {
            if (!val.startsWith('#')) val = '#' + val;
            if (/^#[0-9A-F]{6}$/i.test(val) || /^#[0-9A-F]{3}$/i.test(val)) {
                const picker = document.getElementById('picker_' + type);
                const hex = document.getElementById('hex_' + type);
                if (picker && picker.value !== val) picker.value = val;
                if (hex && hex.value !== val) hex.value = val;
                updateLivePreview();
            }
        }

        function applyColorPreset(name) {
            const p = colorPresets[name];
            if (!p) return;
            for (let k in p) {
                syncColor(k, p[k]);
            }
            updateLivePreview();
        }

        function updateLivePreview() {
            const primary = document.getElementById('hex_primary').value;
            const secondary = document.getElementById('hex_secondary').value;
            const accent = document.getElementById('hex_accent').value;
            const header_bg = document.getElementById('hex_header_bg').value;
            const header_text = document.getElementById('hex_header_text').value;
            const body_bg = document.getElementById('hex_body_bg').value;
            const card_bg = document.getElementById('hex_card_bg').value;
            const btn_color = document.getElementById('hex_btn_color').value;
            const btn_text = document.getElementById('hex_btn_text').value;

            document.getElementById('preview_container').style.backgroundColor = body_bg;
            document.getElementById('preview_header').style.backgroundColor = header_bg;
            document.getElementById('preview_header').style.color = header_text;
            document.getElementById('preview_icon').style.color = primary;
            document.getElementById('preview_badge').style.backgroundColor = primary;
            
            document.getElementById('preview_card').style.backgroundColor = card_bg;
            document.getElementById('preview_price').style.color = secondary;
            document.getElementById('preview_price_summary').style.color = primary;
            
            document.getElementById('preview_button').style.backgroundColor = btn_color;
            document.getElementById('preview_button').style.color = btn_text;
            
            document.getElementById('preview_btn_checkout').style.backgroundColor = btn_color;
            document.getElementById('preview_btn_checkout').style.color = btn_text;
        }
    </script>
    <?php endif; ?>

    <!-- TAB 1: 💳 وسائل وبوابات الدفع الإلكتروني -->
    <?php if ($active_tab === 'payments'): ?>
    <div class="bg-white p-6 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in">
        <div class="border-b pb-4 mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <div>
                <h3 class="font-serif font-bold text-base text-royal-dark flex items-center gap-2">
                    <i class="fa-solid fa-wallet text-royal-darkgold"></i> إعدادات وتنشيط وسائل وبوابات الدفع (Payment Methods)
                </h3>
                <p class="text-xs text-gray-400 mt-1">تخصيص وسائل الدفع لتظهر تلقائياً للعملاء حسب الدولة المحددة (شام كاش لسوريا، باي بال للعالم والدول العربية، إنستا باي وفودافون كاش لمصر، وباي موب للدول المدعومة).</p>
            </div>
            <span class="text-[10px] bg-royal-sand text-royal-darkgold px-3 py-1.5 rounded-xl font-bold">💡 تظهر الوسيلة للعميل فقط عند تفعيلها هنا</span>
        </div>
        
        <form method="POST" action="admin_settings.php?tab=payments" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <!-- 1. محفظة شام كاش وسيريتل كاش (خاصة بسوريا 🇸🇾) -->
                <div class="bg-gradient-to-br from-emerald-50/50 to-teal-50/30 p-5 rounded-2xl border border-emerald-200/70 flex flex-col justify-between space-y-3 md:col-span-1 shadow-sm">
                    <div>
                        <div class="flex items-center justify-between border-b border-emerald-200/50 pb-2 mb-3">
                            <span class="font-bold text-xs text-emerald-950 flex items-center gap-1.5">
                                🇸🇾 محفظة شام كاش (سوريا)
                            </span>
                            <input type="checkbox" name="cham_cash_enabled" id="cham_active" value="1" <?php echo ($settings['cham_cash_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-emerald-600 cursor-pointer">
                        </div>
                        <div class="space-y-2">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">رقم حساب / محفظة شام كاش:</label>
                                <input type="text" name="cham_cash_number" value="<?php echo htmlspecialchars($settings['cham_cash_number'] ?? ''); ?>" placeholder="مثال: 0987654321 أو معرف الحساب" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-emerald-600 text-xs font-mono bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">اسم صاحب الحساب (المستفيد):</label>
                                <input type="text" name="cham_cash_name" value="<?php echo htmlspecialchars($settings['cham_cash_name'] ?? ''); ?>" placeholder="اسم المستلم في شام كاش" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-emerald-600 text-xs bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">رقم سيريتل كاش / MTN (اختياري):</label>
                                <input type="text" name="syriatel_cash_number" value="<?php echo htmlspecialchars($settings['syriatel_cash_number'] ?? ''); ?>" placeholder="مثال: 0933123456" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-emerald-600 text-xs font-mono bg-white">
                            </div>
                        </div>
                        <p class="text-[10px] text-emerald-700 mt-2 font-medium">✨ تظهر تلقائياً لعملاء <b>سوريا 🇸🇾</b> مع زر نسخ فوري ورفع إيصال التحويل.</p>
                    </div>
                </div>

                <!-- 2. باي بال (PayPal - للدول العربية والدولية 🌐) -->
                <div class="bg-gradient-to-br from-blue-50/50 to-indigo-50/30 p-5 rounded-2xl border border-blue-200/70 flex flex-col justify-between space-y-3 md:col-span-1 shadow-sm">
                    <div>
                        <div class="flex items-center justify-between border-b border-blue-200/50 pb-2 mb-3">
                            <span class="font-bold text-xs text-blue-950 flex items-center gap-1.5">
                                <i class="fa-brands fa-paypal text-blue-600 text-sm"></i> باي بال (PayPal)
                            </span>
                            <input type="checkbox" name="paypal_enabled" id="paypal_active" value="1" <?php echo ($settings['paypal_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-blue-600 cursor-pointer">
                        </div>
                        <div class="space-y-2">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">بريد PayPal أو رابط PayPal.Me:</label>
                                <input type="text" name="paypal_email" value="<?php echo htmlspecialchars($settings['paypal_email'] ?? ''); ?>" placeholder="مثال: payment@store.com أو paypal.me/name" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-blue-600 text-xs font-mono bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">PayPal Client ID (اختياري للربط الذكي):</label>
                                <input type="text" name="paypal_client_id" value="<?php echo htmlspecialchars($settings['paypal_client_id'] ?? ''); ?>" placeholder="Client ID الخاص بتطبيق PayPal" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-blue-600 text-xs font-mono bg-white">
                            </div>
                        </div>
                        <p class="text-[10px] text-blue-700 mt-2 font-medium">✨ تظهر للدول العربية (العراق، لبنان، الأردن، الكويت، إلخ) وللدفع بالدولار.</p>
                    </div>
                </div>

                <!-- 3. الدفع عند الاستلام (COD) -->
                <div class="bg-royal-cream/40 p-5 rounded-2xl border border-royal-gold/15 flex flex-col justify-between space-y-3 md:col-span-1 shadow-sm">
                    <div>
                        <div class="flex items-center justify-between border-b pb-2 mb-3">
                            <span class="font-bold text-xs text-royal-dark flex items-center gap-1.5">
                                <i class="fa-solid fa-hand-holding-dollar text-green-600"></i> الدفع عند الاستلام (COD)
                            </span>
                            <input type="checkbox" name="cod_enabled" id="cod_active" value="1" <?php echo ($settings['cod_enabled'] ?? '1') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-darkgold cursor-pointer">
                        </div>
                        <p class="text-xs text-gray-500 font-medium">يتيح للعميل دفع قيمة الفاتورة كاش نقدياً للمندوب فور استلام الشحنة (متاح لكافة الدول).</p>
                    </div>
                </div>

                <!-- 4. فودافون كاش / المحافظ (خاص بمصر 🇪🇬) -->
                <div class="bg-royal-cream/40 p-5 rounded-2xl border border-royal-gold/15 flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex items-center justify-between border-b pb-2 mb-3">
                            <span class="font-bold text-xs text-royal-dark flex items-center gap-1.5">
                                🇪🇬 فودافون كاش / المحافظ (مصر)
                            </span>
                            <input type="checkbox" name="vodafone_cash_enabled" id="vc_active" value="1" <?php echo ($settings['vodafone_cash_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-darkgold cursor-pointer">
                        </div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">رقم محفظة استقبال التحويلات:</label>
                        <input type="text" name="vodafone_cash_number" value="<?php echo htmlspecialchars($settings['vodafone_cash_number'] ?? ''); ?>" placeholder="مثال: 01012345678" class="w-full p-2.5 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs font-mono bg-white">
                        <p class="text-[10px] text-gray-400 mt-2">📌 يظهر عند اختيار مصر مع إلزام رفع صورة إيصال التحويل.</p>
                    </div>
                </div>

                <!-- 5. انستا باي (InstaPay - مصر 🇪🇬) -->
                <div class="bg-royal-cream/40 p-5 rounded-2xl border border-royal-gold/15 flex flex-col justify-between space-y-3 md:col-span-2">
                    <div>
                        <div class="flex items-center justify-between border-b pb-2 mb-3">
                            <span class="font-bold text-xs text-royal-dark flex items-center gap-1.5">
                                🇪🇬 تحويل انستا باي InstaPay (مصر)
                            </span>
                            <input type="checkbox" name="instapay_enabled" id="insta_active" value="1" <?php echo ($settings['instapay_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-darkgold cursor-pointer">
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">عنوان InstaPay IPA أو رقم الحساب:</label>
                                <input type="text" name="instapay_address" value="<?php echo htmlspecialchars($settings['instapay_address'] ?? ''); ?>" placeholder="مثال: name@instapay" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs font-mono bg-white">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-600 mb-0.5">اسم المستفيد (الحساب):</label>
                                <input type="text" name="instapay_name" value="<?php echo htmlspecialchars($settings['instapay_name'] ?? ''); ?>" placeholder="اسم صاحب الحساب بالبنك" class="w-full p-2 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-white">
                            </div>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">📌 يظهر عند اختيار مصر مع إلزام رفع صورة إيصال التحويل ورقم المرجع.</p>
                    </div>
                </div>

            </div>

            <!-- 6. إعدادات بوابة Paymob التلقائية (مصر، السعودية، الإمارات، سلطنة عمان) -->
            <div class="bg-blue-50/50 p-5 rounded-2xl border border-blue-100 space-y-4">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-blue-200/60 pb-3 gap-2">
                    <span class="font-bold text-xs text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-credit-card text-blue-600"></i> بوابة باي موب التلقائية (Paymob Gateway API)
                        <span class="bg-blue-100 text-blue-800 text-[10px] px-2 py-0.5 rounded-md font-bold">🇪🇬 مصر | 🇸🇦 السعودية | 🇦🇪 الإمارات | 🇴🇲 عمان</span>
                    </span>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="paymob_enabled" id="pm_active" value="1" <?php echo ($settings['paymob_enabled'] ?? '0') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-blue-600 cursor-pointer">
                        <label for="pm_active" class="text-xs font-bold text-royal-dark cursor-pointer">تفعيل الدفع الإلكتروني عبر Paymob</label>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 mb-1">Paymob API Key:</label>
                        <input type="password" name="paymob_api_key" value="<?php echo htmlspecialchars($settings['paymob_api_key'] ?? ''); ?>" placeholder="مفتاح API الخاص بحسابك" class="w-full p-2 border border-gray-200 rounded-xl text-xs font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 mb-1">Card Integration ID (كروت):</label>
                        <input type="text" name="paymob_integration_id_card" value="<?php echo htmlspecialchars($settings['paymob_integration_id_card'] ?? ''); ?>" placeholder="مثال: 123456" class="w-full p-2 border border-gray-200 rounded-xl text-xs font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 mb-1">Wallet Integration ID (محافظ):</label>
                        <input type="text" name="paymob_integration_id_wallet" value="<?php echo htmlspecialchars($settings['paymob_integration_id_wallet'] ?? ''); ?>" placeholder="مثال: 654321" class="w-full p-2 border border-gray-200 rounded-xl text-xs font-mono bg-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-600 mb-1">Paymob iFrame ID:</label>
                        <input type="text" name="paymob_iframe_id" value="<?php echo htmlspecialchars($settings['paymob_iframe_id'] ?? ''); ?>" placeholder="مثال: 78901" class="w-full p-2 border border-gray-200 rounded-xl text-xs font-mono bg-white">
                    </div>
                </div>
            </div>

            <button type="submit" name="update_payment_settings" class="bg-royal-charcoal text-white px-8 py-3.5 font-bold text-xs rounded-xl hover:bg-royal-gold hover:text-royal-charcoal shadow btn-shine transition-all">
                <i class="fa-solid fa-floppy-disk mr-1"></i> حفظ وتحديث وسائل وبوابات الدفع
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- TAB 2: 🚚 مناطق ومصاريف الشحن والتوصيل ومتعدد الدول والعملات -->
    <?php if ($active_tab === 'shipping'): 
        $current_country_meta = $supported_countries_data[$selected_country_tab] ?? [
            'code' => 'XX', 'currency' => ($settings['store_currency'] ?? 'ج.م'), 'flag' => '🌐', 'default_cost' => 50.00
        ];
    ?>
    <div class="space-y-8 animate-fade-in">
        
        <!-- 1. كارت إعدادات الدول والعملات العامة -->
        <div class="bg-white p-6 border border-royal-gold/10 shadow-sm rounded-2xl">
            <div class="flex items-center justify-between border-b pb-4 mb-6">
                <div>
                    <h3 class="font-serif font-bold text-base text-royal-dark flex items-center gap-2">
                        <i class="fa-solid fa-globe text-royal-darkgold"></i> إعدادات الشحن الدولي والدول المعتمدة
                    </h3>
                    <p class="text-xs text-gray-400 mt-1">تحديد الدول المتاح الشحن إليها، وتعيين نظام العملات وتفعيل المحافظات التابعة لكل دولة.</p>
                </div>
            </div>

            <form method="POST" action="admin_settings.php?tab=shipping" class="space-y-6">
                <input type="hidden" name="update_country_settings" value="1">
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 bg-royal-sand/30 p-5 rounded-2xl border border-royal-gold/10">
                    <!-- خيار تفعيل تعدد الدول -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-royal-dark flex items-center gap-2">
                            <i class="fa-solid fa-earth-americas text-royal-darkgold"></i> تفعيل الشحن لعدة دول
                        </label>
                        <div class="flex items-center gap-2 pt-1">
                            <input type="checkbox" name="enable_multi_country" id="multi_country_active" value="1" <?php echo ($settings['enable_multi_country'] ?? '1') === '1' ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-darkgold cursor-pointer">
                            <label for="multi_country_active" class="text-xs text-gray-600 font-medium cursor-pointer">إتاحة اختيار الدولة للعميل في صفحة الدفع</label>
                        </div>
                    </div>

                    <!-- الدولة الافتراضية للمتجر -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-royal-dark flex items-center gap-2">
                            <i class="fa-solid fa-flag text-royal-darkgold"></i> الدولة الافتراضية للزوار
                        </label>
                        <select name="default_country" class="w-full p-2.5 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-royal-gold font-medium">
                            <?php foreach($supported_countries_data as $c_name => $c_info): ?>
                                <option value="<?php echo $c_name; ?>" <?php echo ($settings['default_country'] ?? 'مصر') === $c_name ? 'selected' : ''; ?>>
                                    <?php echo $c_info['flag'] . ' ' . $c_name . ' (' . $c_info['currency'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- نظام تسعير العملات -->
                    <div class="space-y-2">
                        <label class="block text-xs font-bold text-royal-dark flex items-center gap-2">
                            <i class="fa-solid fa-coins text-royal-darkgold"></i> نظام العملات في المتجر
                        </label>
                        <select name="preferred_currency_mode" class="w-full p-2.5 bg-white border border-gray-200 rounded-xl text-xs outline-none focus:border-royal-gold font-medium">
                            <option value="local" <?php echo ($settings['preferred_currency_mode'] ?? 'local') === 'local' ? 'selected' : ''; ?>>عملة كل بلد تلقائياً (ج.م، ر.س، د.إ، ل.س، د.ع، ل.ل...)</option>
                            <option value="usd" <?php echo ($settings['preferred_currency_mode'] ?? '') === 'usd' ? 'selected' : ''; ?>>الدولار الأمريكي ($ - USD)</option>
                            <option value="store" <?php echo ($settings['preferred_currency_mode'] ?? '') === 'store' ? 'selected' : ''; ?>>العملة الثابتة للمتجر (<?php echo htmlspecialchars($settings['store_currency'] ?? 'ج.م'); ?>)</option>
                        </select>
                    </div>
                </div>

                <!-- الدول المتاحة للتفعيل السريع -->
                <div>
                    <label class="block text-xs font-bold text-royal-dark mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-royal-darkgold"></i> الدول المفعلة للشحن والاستلام (اختر الدول المتاحة لعملائك):
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <?php foreach($supported_countries_data as $c_name => $c_info): 
                            $is_c_active = in_array($c_name, $active_countries_list);
                        ?>
                            <label class="border p-3 rounded-xl flex items-center gap-2.5 cursor-pointer transition-all duration-200 <?php echo $is_c_active ? 'bg-royal-cream/40 border-royal-gold/30 shadow-sm' : 'bg-gray-50 border-gray-200 opacity-60'; ?>">
                                <input type="checkbox" name="active_countries[]" value="<?php echo $c_name; ?>" <?php echo $is_c_active ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-gold cursor-pointer rounded">
                                <span class="text-base"><?php echo $c_info['flag']; ?></span>
                                <div class="leading-tight">
                                    <div class="font-bold text-xs text-royal-dark"><?php echo $c_name; ?></div>
                                    <div class="text-[10px] text-gray-400 font-mono"><?php echo $c_info['currency']; ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-8 py-3 rounded-xl text-xs font-bold transition shadow-md btn-shine">
                        حفظ إعدادات الدول والعملات
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. إدارة محافظات ومناطق الدولة المختارة -->
        <div class="bg-white p-6 border border-royal-gold/10 shadow-sm rounded-2xl">
            <!-- شريط التبديل بين الدول -->
            <div class="border-b pb-4 mb-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                    <div>
                        <h3 class="font-serif font-bold text-base text-royal-dark flex items-center gap-2">
                            <span><?php echo $current_country_meta['flag']; ?></span>
                            تسعير محافظات ومناطق: <span class="text-royal-darkgold"><?php echo htmlspecialchars($selected_country_tab); ?></span>
                        </h3>
                        <p class="text-xs text-gray-400 mt-1">العملة الافتراضية: <strong class="text-royal-dark font-bold"><?php echo $current_country_meta['currency']; ?></strong> | عدد المحافظات المسجلة: <strong class="text-royal-dark font-bold"><?php echo count($shipping_zones); ?></strong></p>
                    </div>
                </div>

                <!-- أزرار اختيار الدولة للتعديل -->
                <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-thin">
                    <?php foreach($supported_countries_data as $c_name => $c_info): 
                        $is_sel = ($selected_country_tab === $c_name);
                    ?>
                        <a href="admin_settings.php?tab=shipping&country=<?php echo urlencode($c_name); ?>" class="shrink-0 px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border <?php echo $is_sel ? 'bg-royal-charcoal text-royal-gold border-royal-gold shadow-md scale-105' : 'bg-white hover:bg-royal-cream/50 text-gray-600 border-gray-200'; ?>">
                            <span><?php echo $c_info['flag']; ?></span>
                            <span><?php echo $c_name; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- شريط الأدوات السريعة للدولة (تطبيق سعر موحد + إضافة محافظة) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6 bg-royal-cream/20 p-4 rounded-xl border border-royal-gold/10">
                <!-- أداة السعر الموحد -->
                <form method="POST" action="admin_settings.php?tab=shipping" class="flex items-center gap-2">
                    <input type="hidden" name="country_filter" value="<?php echo htmlspecialchars($selected_country_tab); ?>">
                    <input type="number" step="0.5" name="mass_cost" placeholder="سعر موحد لكل المحافظات..." required class="w-48 p-2.5 border border-gray-200 rounded-xl text-xs bg-white outline-none focus:border-royal-gold font-bold">
                    <button type="submit" name="mass_update_country_cost" class="bg-royal-sand hover:bg-royal-gold text-royal-dark hover:text-white px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm whitespace-nowrap">
                        تطبيق على كل <?php echo htmlspecialchars($selected_country_tab); ?>
                    </button>
                </form>

                <!-- أداة إضافة محافظة / مدينة جديدة -->
                <form method="POST" action="admin_settings.php?tab=shipping" class="flex items-center gap-2 justify-end">
                    <input type="hidden" name="target_country" value="<?php echo htmlspecialchars($selected_country_tab); ?>">
                    <input type="text" name="new_gov_name" placeholder="اسم محافظة/مدينة جديدة..." required class="flex-grow p-2.5 border border-gray-200 rounded-xl text-xs bg-white outline-none focus:border-royal-gold">
                    <input type="number" step="0.5" name="new_gov_cost" value="<?php echo $current_country_meta['default_cost']; ?>" class="w-24 p-2.5 border border-gray-200 rounded-xl text-xs bg-white outline-none focus:border-royal-gold font-bold" title="سعر الشحن">
                    <button type="submit" name="add_new_governorate" class="bg-gold-gradient text-white hover:bg-gold-gradient-hover px-4 py-2.5 rounded-xl text-xs font-bold transition shadow-sm whitespace-nowrap btn-shine">
                        إضافة +
                    </button>
                </form>
            </div>

            <!-- نموذج تعديل وحفظ أسعار المحافظات -->
            <form id="shipping-settings-form" method="POST" action="admin_settings.php?tab=shipping" class="space-y-6">
                <input type="hidden" name="update_shipping_zones" value="1">
                <input type="hidden" name="country_filter" value="<?php echo htmlspecialchars($selected_country_tab); ?>">
                
                <?php if(empty($shipping_zones)): ?>
                    <div class="text-center py-12 bg-gray-50 border border-dashed rounded-xl">
                        <p class="text-xs text-gray-400 font-medium">لا توجد محافظات مضافة لهذه الدولة حتى الآن. يمكنك إضافة مدن جديدة من النموذج بالأعلى.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach($shipping_zones as $z): ?>
                            <div class="border p-4 rounded-xl transition-all duration-300 <?php echo !$z['is_active'] ? 'opacity-40 bg-gray-50 border-gray-200' : 'bg-white border-royal-gold/15 shadow-sm hover:border-royal-gold/40'; ?>">
                                <div class="flex justify-between items-center mb-3">
                                    <label class="font-bold text-royal-dark text-xs flex items-center gap-2 cursor-pointer select-none">
                                        <input type="checkbox" name="gov_active[<?php echo $z['id']; ?>]" value="1" <?php echo $z['is_active'] ? 'checked' : ''; ?> class="w-4 h-4 accent-royal-gold cursor-pointer rounded"> 
                                        <span><?php echo htmlspecialchars($z['gov_name']); ?></span>
                                    </label>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[9px] px-2 py-0.5 rounded font-bold <?php echo $z['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo $z['is_active'] ? 'شحن متاح' : 'موقف'; ?>
                                        </span>
                                        <a href="admin_settings.php?tab=shipping&country=<?php echo urlencode($selected_country_tab); ?>&action=delete_gov&gov_id=<?php echo $z['id']; ?>" onclick="return confirm('هل تريد بالتأكيد حذف هذه المحافظة؟')" class="text-gray-300 hover:text-red-600 p-1 text-xs transition-colors" title="حذف المحافظة">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-400 font-medium whitespace-nowrap">سعر الشحن:</span>
                                    <div class="relative flex-grow">
                                        <input type="number" step="0.5" name="gov_cost[<?php echo $z['id']; ?>]" value="<?php echo $z['cost']; ?>" class="w-full p-2.5 text-xs border border-gray-200 rounded-lg outline-none focus:border-royal-gold font-serif font-bold text-royal-dark text-left bg-royal-cream/20 focus:bg-white" dir="ltr">
                                        <input type="hidden" name="gov_currency[<?php echo $z['id']; ?>]" value="<?php echo htmlspecialchars($z['currency_symbol'] ?: $current_country_meta['currency']); ?>">
                                        <span class="absolute left-3 top-2.5 text-[10px] text-gray-500 font-bold"><?php echo htmlspecialchars($z['currency_symbol'] ?: $current_country_meta['currency']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pt-4 flex justify-between items-center border-t">
                        <div class="text-xs text-gray-400">
                            تم تحديد <span class="font-bold text-royal-darkgold"><?php echo count($shipping_zones); ?></span> منطقة لدولة <strong class="text-royal-dark"><?php echo htmlspecialchars($selected_country_tab); ?></strong>
                        </div>
                        <button type="submit" class="bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal px-10 py-3.5 rounded-xl text-xs font-bold shadow-md transition-all btn-shine">
                            حفظ أسعار شحن <?php echo htmlspecialchars($selected_country_tab); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>

    </div>
    <?php endif; ?>

    <!-- TAB 3: 🎨 الواجهة والسلايدر -->
    <?php if ($active_tab === 'theme'): ?>
    <div class="bg-white p-6 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in">
        <h3 class="font-serif font-bold text-sm text-royal-dark mb-4 border-b pb-2 flex items-center gap-1.5"><i class="fa-solid fa-bullhorn text-royal-darkgold"></i> شريط التنبيهات العلوي</h3>
        <form method="POST" action="admin_settings.php?tab=theme" class="space-y-4">
            <input type="text" name="announcement_bar" value="<?php echo htmlspecialchars($settings['announcement_bar'] ?? ''); ?>" placeholder="اكتب نص الإعلان المرفق بالأعلى" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-royal-cream/35">
            <button type="submit" name="update_announcement" class="bg-royal-charcoal text-white px-8 py-3 font-bold text-xs rounded-xl hover:bg-royal-gold hover:text-royal-charcoal shadow transition-all">حفظ نص الشريط العلوي</button>
        </form>
    </div>

    <!-- السلايدر المتحرك -->
    <div class="bg-white p-6 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in">
        <h3 class="font-serif font-bold text-sm text-royal-dark mb-6 border-b pb-2 flex items-center gap-1.5"><i class="fa-regular fa-images text-royal-darkgold"></i> سلايدر البانر المتحرك (Home Page Carousel)</h3>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- نموذج إضافة شريحة جديدة -->
            <form method="POST" action="admin_settings.php?tab=theme" enctype="multipart/form-data" class="space-y-4 bg-royal-sand/20 p-5 rounded-2xl border border-royal-gold/10">
                <h4 class="font-bold text-xs text-royal-darkgold border-b pb-2 mb-2"><i class="fa-solid fa-plus-circle"></i> إضافة صورة بنر متحرك جديدة</h4>
                <div>
                    <label class="block text-[10px] text-gray-500 font-bold mb-1">صورة البانر (1920x1080) *</label>
                    <input type="file" name="slide_file" accept="image/*" required class="w-full text-xs text-gray-500">
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 font-bold mb-1">العنوان الرئيسي (اختياري)</label>
                    <input type="text" name="slide_title" class="w-full p-2 border border-gray-200 rounded-lg text-xs outline-none focus:border-royal-gold">
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 font-bold mb-1">العنوان الفرعي (اختياري)</label>
                    <input type="text" name="slide_subtitle" class="w-full p-2 border border-gray-200 rounded-lg text-xs outline-none focus:border-royal-gold">
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 font-bold mb-1">رابط التوجيه (اختياري - افتراضياً المتجر)</label>
                    <input type="text" name="slide_link" placeholder="shop.php" class="w-full p-2 border border-gray-200 rounded-lg text-xs outline-none focus:border-royal-gold font-serif">
                </div>
                <div>
                    <label class="block text-[10px] text-gray-500 font-bold mb-1">رقم الترتيب (للفرز)</label>
                    <input type="number" name="slide_sort" value="0" class="w-full p-2 border border-gray-200 rounded-lg text-xs outline-none focus:border-royal-gold font-serif">
                </div>
                <button type="submit" name="add_slide" class="w-full bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal text-xs py-3 font-bold rounded-xl shadow btn-shine transition-all">إضافة شريحة بنر جديدة</button>
            </form>

            <!-- جدول عرض صور البانر الحالية ومراجعتها وحذفها -->
            <div class="lg:col-span-2 overflow-x-auto border rounded-2xl overflow-hidden">
                <table class="w-full text-right text-xs">
                    <thead class="bg-royal-sand/40 text-gray-500 border-b font-bold">
                        <tr>
                            <th class="p-4 font-bold">معاينة البانر</th>
                            <th class="p-4 font-bold">العناوين</th>
                            <th class="p-4 font-bold">رابط التوجيه</th>
                            <th class="p-4 text-center font-bold">الترتيب</th>
                            <th class="p-4 text-center font-bold">إجراء</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium">
                        <?php foreach($slides as $slide): ?>
                        <tr>
                            <td class="p-4 w-28">
                                <img src="<?php echo htmlspecialchars($slide['image_url']); ?>" class="w-24 h-12 object-cover rounded-lg bg-gray-50 border shadow-inner">
                            </td>
                            <td class="p-4">
                                <div class="font-bold text-royal-dark text-xs"><?php echo htmlspecialchars($slide['title'] ?: '-'); ?></div>
                                <div class="text-[10px] text-gray-400 mt-1"><?php echo htmlspecialchars($slide['subtitle'] ?: '-'); ?></div>
                            </td>
                            <td class="p-4 font-serif text-[10px]"><?php echo htmlspecialchars($slide['link_url'] ?: 'shop.php'); ?></td>
                            <td class="p-4 text-center font-serif"><?php echo $slide['sort_order']; ?></td>
                            <td class="p-4 text-center">
                                <a href="admin_settings.php?tab=theme&action=delete_slide&id=<?php echo $slide['id']; ?>" onclick="return confirm('حذف صورة البانر هذه نهائياً؟')" class="text-red-500 hover:text-red-700 text-sm" title="حذف شريحة البانر"><i class="fa-solid fa-trash-can"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB 4: 📈 إعلانات ميتا وتتبع البكسل -->
    <?php if ($active_tab === 'meta'): ?>
    <div class="bg-white p-6 border border-royal-gold/10 shadow-sm mb-8 rounded-2xl animate-fade-in">
        <h3 class="font-serif font-bold text-sm text-royal-dark mb-4 border-b pb-2 flex items-center justify-between">
            <span class="flex items-center gap-1.5"><i class="fa-brands fa-facebook text-blue-600"></i> إعدادات ميتا بكسل (Meta Ads Pixel)</span>
            <?php if(!empty($settings['meta_pixel_id']) && ($settings['meta_pixel_enabled'] ?? '1') === '1'): ?>
                <span class="bg-green-100 text-green-700 text-[9px] px-2 py-0.5 rounded-full font-bold">مُفعّل ✓</span>
            <?php else: ?>
                <span class="bg-gray-100 text-gray-500 text-[9px] px-2 py-0.5 rounded-full font-bold">غير مفعّل</span>
            <?php endif; ?>
        </h3>
        <form method="POST" action="admin_settings.php?tab=meta" class="space-y-4 max-w-xl">
            <div>
                <label class="block text-[11px] font-bold text-gray-600 mb-1">معرّف ميتا بكسل (Meta Pixel ID):</label>
                <input type="text" name="meta_pixel_id" value="<?php echo htmlspecialchars($settings['meta_pixel_id'] ?? ''); ?>" placeholder="مثال: 123456789012345" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs font-mono bg-royal-cream/35">
            </div>
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="meta_pixel_enabled" id="pixel_active" value="1" <?php echo ($settings['meta_pixel_enabled'] ?? '1') === '1' ? 'checked' : ''; ?> class="w-4 h-4 text-royal-gold rounded accent-royal-darkgold cursor-pointer">
                <label for="pixel_active" class="text-xs font-bold text-royal-dark cursor-pointer">تفعيل تتبع إعلانات ميتا (Meta Pixel Standard Events)</label>
            </div>
            <button type="submit" name="update_meta_pixel" class="bg-royal-charcoal text-white px-8 py-3 font-bold text-xs rounded-xl hover:bg-royal-gold hover:text-royal-charcoal shadow transition-all">حفظ إعدادات Meta Pixel</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- TAB 5: 🏷️ أقسام وتصنيفات المتجر -->
    <?php if ($active_tab === 'categories'): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <!-- كارت إضافة/تعديل قسم -->
        <div class="bg-white p-6 border border-royal-gold/10 shadow-sm rounded-2xl">
            <?php 
            $edit_cat_mode = false;
            $edit_cat = ['id'=>'', 'name'=>'', 'image_url'=>'', 'parent_id'=>''];
            if(isset($_GET['edit_cat_id'])) {
                $edit_cat_id = (int)$_GET['edit_cat_id'];
                $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
                $stmt->execute([$edit_cat_id]);
                $cat_data = $stmt->fetch(PDO::FETCH_ASSOC);
                if($cat_data) {
                    $edit_cat = $cat_data;
                    $edit_cat_mode = true;
                }
            }
            ?>
            
            <h3 class="font-serif font-bold text-sm text-royal-dark mb-4 border-b pb-2 flex items-center gap-1.5">
                <i class="fa-solid fa-folder-tree text-royal-darkgold"></i> 
                <?php echo $edit_cat_mode ? 'تعديل بيانات القسم' : 'إضافة تصنيف/قسم جديد'; ?>
            </h3>
            
            <form method="POST" action="admin_settings.php?tab=categories" enctype="multipart/form-data" class="space-y-4">
                <?php if($edit_cat_mode): ?>
                    <input type="hidden" name="cat_id" value="<?php echo $edit_cat['id']; ?>">
                <?php endif; ?>
                
                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">اسم القسم *</label>
                    <input type="text" name="cat_name" value="<?php echo htmlspecialchars($edit_cat['name']); ?>" required class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs">
                </div>

                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">القسم الأب (اختر قسم رئيسي أو اتركه مستقل) *</label>
                    <select name="parent_id" class="w-full p-3 border border-gray-200 rounded-xl outline-none focus:border-royal-gold text-xs bg-white">
                        <option value="none">-- قسم رئيسي مستقل (بدون أب) --</option>
                        <?php 
                        $main_cats_query = "SELECT * FROM categories WHERE parent_id IS NULL";
                        if ($edit_cat_mode) {
                            $main_cats_query .= " AND id != " . (int)$edit_cat['id'];
                        }
                        $main_cats_query .= " ORDER BY name ASC";
                        $main_cats = $pdo->query($main_cats_query)->fetchAll(PDO::FETCH_ASSOC);
                        foreach($main_cats as $mc): 
                        ?>
                            <option value="<?php echo $mc['id']; ?>" <?php echo $edit_cat['parent_id'] == $mc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mc['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-xs font-bold mb-2 text-gray-600">صورة التصنيف (المقاس الموصى به: 600 × 800 px)</label>
                    <?php if($edit_cat_mode && !empty($edit_cat['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($edit_cat['image_url']); ?>" class="w-16 h-16 object-cover mb-3 rounded-lg border">
                    <?php endif; ?>
                    <input type="file" name="cat_image" accept="image/*" class="w-full text-xs text-gray-500">
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" name="<?php echo $edit_cat_mode ? 'edit_category' : 'add_category'; ?>" class="flex-grow bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal text-xs py-3.5 font-bold rounded-xl shadow btn-shine transition-all">
                        <?php echo $edit_cat_mode ? 'حفظ وتحديث القسم' : 'إضافة القسم للمتجر'; ?>
                    </button>
                    <?php if($edit_cat_mode): ?>
                        <a href="admin_settings.php?tab=categories" class="bg-gray-100 text-gray-700 px-4 py-3.5 text-xs font-bold rounded-xl hover:bg-gray-200 transition-colors flex items-center justify-center border">إلغاء</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- قائمة الأقسام وتعديلها -->
        <div class="md:col-span-2 bg-white border border-royal-gold/10 shadow-sm rounded-2xl overflow-hidden">
            <div class="bg-royal-sand/40 p-4 font-bold text-xs text-royal-darkgold border-b border-royal-gold/10"><i class="fa-solid fa-list-ul"></i> الأقسام والتصنيفات الحالية</div>
            <table class="w-full text-right text-xs">
                <thead class="bg-royal-sand/10 text-gray-400 border-b">
                    <tr>
                        <th class="p-4 font-bold w-20">الصورة</th>
                        <th class="p-4 font-bold">اسم القسم</th>
                        <th class="p-4 font-bold">النوع والتبعية</th>
                        <th class="p-4 text-center font-bold">إجراء</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 font-medium">
                    <?php if(empty($all_categories_list)): ?>
                        <tr>
                            <td colspan="4" class="p-8 text-center text-gray-400 text-xs">لا توجد أقسام مضافة حتى الآن.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($all_categories_list as $c): 
                            $is_sub = !empty($c['parent_id']);
                        ?>
                        <tr class="hover:bg-royal-cream/25 transition-colors <?php echo $is_sub ? 'bg-gray-50/50' : ''; ?>">
                            <td class="p-4 w-20">
                                <img src="<?php echo htmlspecialchars($c['image_url']); ?>" class="w-10 h-10 object-cover bg-gray-50 border rounded-lg shadow-sm">
                            </td>
                            <td class="p-4 font-bold text-royal-dark text-sm">
                                <?php if($is_sub): ?>
                                    <span class="text-royal-gold ml-1 text-xs">↳</span>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </td>
                            <td class="p-4">
                                <?php if(!$is_sub): ?>
                                    <span class="bg-green-50 text-green-700 border border-green-200 px-2.5 py-0.5 rounded-full font-bold text-[10px]">قسم رئيسي أساسي</span>
                                <?php else: ?>
                                    <span class="bg-blue-50 text-blue-700 border border-blue-200 px-2.5 py-0.5 rounded-full font-bold text-[10px]">
                                        فرعي ➔ <?php echo htmlspecialchars($c['parent_name'] ?? 'قسم رئيسي'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4 text-center space-x-3 space-x-reverse">
                                <a href="admin_settings.php?tab=categories&edit_cat_id=<?php echo $c['id']; ?>" class="text-blue-500 hover:text-blue-700 text-xs font-bold">تعديل</a>
                                <a href="admin_settings.php?tab=categories&action=delete_category&id=<?php echo $c['id']; ?>" onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا القسم؟')" class="text-red-500 hover:text-red-700 text-xs font-bold">حذف القسم</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
include 'footer.php';
?>
