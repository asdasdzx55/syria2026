<?php
require_once 'config.php';

// التحقق من رتبة المدير
if (!isAdmin()) {
    header("Location: login.php");
    exit;
}

// إنشاء مجلد النسخ الاحتياطية المحمي إن لم يكن موجوداً
$backups_dir = __DIR__ . '/backups';
if (!is_dir($backups_dir)) {
    @mkdir($backups_dir, 0755, true);
    @file_put_contents($backups_dir . '/.htaccess', "Deny from all\n");
    @file_put_contents($backups_dir . '/index.html', "");
}

// دالة مساعدة لتوليد نص SQL Dump كامل لقاعدة البيانات
function generateDatabaseSqlDump($pdo, $db_type) {
    $tables = [
        'users',
        'categories',
        'products',
        'product_images',
        'orders',
        'reviews',
        'coupons',
        'shipping_zones',
        'home_slides',
        'settings',
        'notifications',
        'visitor_logs',
        'wishlist'
    ];

    $sql = "-- ============================================================\n";
    $sql .= "-- E-Commerce Platform Database Backup\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Engine: " . strtoupper($db_type) . "\n";
    $sql .= "-- ============================================================\n\n";

    if ($db_type === 'mysql') {
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
    }

    foreach ($tables as $table) {
        // التحقق من وجود الجدول
        try {
            $stmt_check = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        } catch (Exception $e) {
            continue;
        }

        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "-- Table structure for `$table`\n";
        $sql .= "-- ------------------------------------------------------------\n";
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";

        // جلب تعريف الجدول
        if ($db_type === 'sqlite') {
            $schema_stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");
            $schema_stmt->execute([$table]);
            $create_sql = $schema_stmt->fetchColumn();
            if ($create_sql) {
                $sql .= $create_sql . ";\n\n";
            }
        } else {
            $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $create_stmt->fetch(PDO::FETCH_NUM);
            if ($row && isset($row[1])) {
                $sql .= $row[1] . ";\n\n";
            }
        }

        // جلب البيانات وإدراجها
        $rows_stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $rows_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($rows)) {
            $sql .= "-- Dumping data for table `$table`\n";
            $columns = array_keys($rows[0]);
            $col_names = implode('`, `', $columns);

            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = "NULL";
                    } elseif (is_numeric($val) && !preg_match('/^0[0-9]+/', $val)) {
                        $values[] = $val;
                    } else {
                        $values[] = $pdo->quote($val);
                    }
                }
                $sql .= "INSERT INTO `$table` (`$col_names`) VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }
    }

    if ($db_type === 'mysql') {
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    }

    return $sql;
}

// دالة مساعدة لتنفيذ استعلامات ملف SQL بأمان
function executeSqlDump($pdo, $sql_content, $db_type) {
    if ($db_type === 'sqlite') {
        $pdo->exec("PRAGMA foreign_keys = OFF;");
        $statements = preg_split('/;\s*[\r\n]+/', $sql_content);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt) && strpos($stmt, '--') !== 0) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $e) {
                    if (stripos($stmt, 'DROP TABLE') === false) {
                        // إعادة المحاولة أو تجاهل إذا كان جدول غير موجود
                    }
                }
            }
        }
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } else {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $statements = preg_split('/;\s*[\r\n]+/', $sql_content);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (!empty($stmt) && strpos($stmt, '--') !== 0) {
                try {
                    $pdo->exec($stmt);
                } catch (Exception $e) {
                    if (stripos($stmt, 'DROP TABLE') === false) {
                        // استمرار
                    }
                }
            }
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }
}

// -----------------------------------------------------------------------------
// 1. إجراء: تحميل ملف SQL لقاعدة البيانات مباشرة
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download_sql') {
    $sql_dump = generateDatabaseSqlDump($pdo, $db_type);
    $filename = 'store_db_backup_' . date('Y-m-d_H-i') . '.sql';

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql_dump));
    echo $sql_dump;
    exit;
}

// -----------------------------------------------------------------------------
// 2. إجراء: تحميل نسخة احتياطية شاملة (Database + Media Uploads ZIP)
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download_zip') {
    if (!class_exists('ZipArchive')) {
        die('مكتبة ZipArchive غير مفعلة على هذا السيرفر.');
    }

    $zip = new ZipArchive();
    $temp_zip = tempnam(sys_get_temp_dir(), 'store_zip_');
    
    if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
        // إضافة ملف SQL
        $sql_dump = generateDatabaseSqlDump($pdo, $db_type);
        $zip->addFromString('database_dump.sql', $sql_dump);

        // إضافة ملفات مجلد uploads
        $uploads_dir = __DIR__ . '/uploads';
        if (is_dir($uploads_dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = 'uploads/' . substr($filePath, strlen($uploads_dir) + 1);
                    $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                }
            }
        }

        $zip->close();

        $filename = 'store_full_backup_' . date('Y-m-d_H-i') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temp_zip));
        readfile($temp_zip);
        @unlink($temp_zip);
        exit;
    } else {
        die('تعذر إنشاء الملف المضغوط للنسخة الاحتياطية.');
    }
}

// -----------------------------------------------------------------------------
// 3. إجراء: حفظ نقطة استعادة على السيرفر (Server Snapshot)
// -----------------------------------------------------------------------------
if (isset($_POST['create_server_snapshot'])) {
    $snap_type = $_POST['snapshot_type'] ?? 'sql';
    $date_str = date('Y-m-d_H-i-s');

    if ($snap_type === 'zip' && class_exists('ZipArchive')) {
        $filename = 'snapshot_full_' . $date_str . '.zip';
        $dest = $backups_dir . '/' . $filename;

        $zip = new ZipArchive();
        if ($zip->open($dest, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $sql_dump = generateDatabaseSqlDump($pdo, $db_type);
            $zip->addFromString('database_dump.sql', $sql_dump);

            $uploads_dir = __DIR__ . '/uploads';
            if (is_dir($uploads_dir)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'uploads/' . substr($filePath, strlen($uploads_dir) + 1);
                        $zip->addFile($filePath, str_replace('\\', '/', $relativePath));
                    }
                }
            }
            $zip->close();
            header("Location: admin_backup.php?msg=snapshot_created");
            exit;
        }
    } else {
        $filename = 'snapshot_db_' . $date_str . '.sql';
        $dest = $backups_dir . '/' . $filename;
        $sql_dump = generateDatabaseSqlDump($pdo, $db_type);
        file_put_contents($dest, $sql_dump);
        header("Location: admin_backup.php?msg=snapshot_created");
        exit;
    }
}

// -----------------------------------------------------------------------------
// 4. إجراء: استعادة من ملف تم رفعه (Restore from Uploaded File)
// -----------------------------------------------------------------------------
if (isset($_POST['restore_uploaded_file']) && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        header("Location: admin_backup.php?error=upload_failed");
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    try {
        if ($ext === 'sql') {
            $sql_content = file_get_contents($file['tmp_name']);
            executeSqlDump($pdo, $sql_content, $db_type);
            header("Location: admin_backup.php?msg=restore_success");
            exit;
        } elseif ($ext === 'zip') {
            if (!class_exists('ZipArchive')) {
                throw new Exception('مكتبة Zip غير متوفرة لفك الضغط.');
            }
            $zip = new ZipArchive();
            if ($zip->open($file['tmp_name']) === TRUE) {
                // البحث عن ملف SQL داخل الـ ZIP
                $sql_content = $zip->getFromName('database_dump.sql');
                if (!$sql_content) {
                    // البحث عن أي ملف ينتهي بـ .sql
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $stat = $zip->statIndex($i);
                        if (preg_match('/\.sql$/i', $stat['name'])) {
                            $sql_content = $zip->getFromIndex($i);
                            break;
                        }
                    }
                }

                if ($sql_content) {
                    executeSqlDump($pdo, $sql_content, $db_type);
                }

                // استخراج ملفات uploads
                $uploads_dir = __DIR__ . '/uploads';
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $filename_in_zip = $zip->getNameIndex($i);
                    if (strpos($filename_in_zip, 'uploads/') === 0) {
                        $zip->extractTo(__DIR__, $filename_in_zip);
                    }
                }

                $zip->close();
                header("Location: admin_backup.php?msg=restore_success");
                exit;
            } else {
                throw new Exception('تعذر فتح ملف الـ ZIP.');
            }
        } else {
            header("Location: admin_backup.php?error=invalid_file_type");
            exit;
        }
    } catch (Exception $e) {
        header("Location: admin_backup.php?error=restore_error&err_msg=" . urlencode($e->getMessage()));
        exit;
    }
}

// -----------------------------------------------------------------------------
// 5. إجراء: استعادة من نقطة حفظ على السيرفر
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'restore_server_file' && isset($_GET['file'])) {
    $target_file = basename($_GET['file']);
    $full_path = $backups_dir . '/' . $target_file;

    if (file_exists($full_path)) {
        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        try {
            if ($ext === 'sql') {
                $sql_content = file_get_contents($full_path);
                executeSqlDump($pdo, $sql_content, $db_type);
            } elseif ($ext === 'zip') {
                $zip = new ZipArchive();
                if ($zip->open($full_path) === TRUE) {
                    $sql_content = $zip->getFromName('database_dump.sql');
                    if ($sql_content) {
                        executeSqlDump($pdo, $sql_content, $db_type);
                    }
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $fn = $zip->getNameIndex($i);
                        if (strpos($fn, 'uploads/') === 0) {
                            $zip->extractTo(__DIR__, $fn);
                        }
                    }
                    $zip->close();
                }
            }
            header("Location: admin_backup.php?msg=restore_success");
            exit;
        } catch (Exception $e) {
            header("Location: admin_backup.php?error=restore_error&err_msg=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

// -----------------------------------------------------------------------------
// 6. إجراء: حذف نقطة استعادة من السيرفر
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'delete_server_file' && isset($_GET['file'])) {
    $target_file = basename($_GET['file']);
    $full_path = $backups_dir . '/' . $target_file;
    if (file_exists($full_path)) {
        @unlink($full_path);
        header("Location: admin_backup.php?msg=snapshot_deleted");
        exit;
    }
}

// -----------------------------------------------------------------------------
// 7. إجراء: تحميل ملف نقطة استعادة مخزنة على السيرفر
// -----------------------------------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download_server_file' && isset($_GET['file'])) {
    $target_file = basename($_GET['file']);
    $full_path = $backups_dir . '/' . $target_file;
    if (file_exists($full_path)) {
        $ext = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        header('Content-Type: ' . ($ext === 'zip' ? 'application/zip' : 'application/sql'));
        header('Content-Disposition: attachment; filename="' . $target_file . '"');
        header('Content-Length: ' . filesize($full_path));
        readfile($full_path);
        exit;
    }
}

// جلب قائمة ملفات النسخ الاحتياطية الموجودة على السيرفر
$server_backups = [];
if (is_dir($backups_dir)) {
    $scanned = scandir($backups_dir);
    foreach ($scanned as $f) {
        if ($f !== '.' && $f !== '..' && $f !== '.htaccess' && $f !== 'index.html') {
            $f_path = $backups_dir . '/' . $f;
            $server_backups[] = [
                'name' => $f,
                'size' => round(filesize($f_path) / 1024, 2) . ' KB',
                'size_raw' => filesize($f_path),
                'date' => date('Y-m-d h:i A', filemtime($f_path)),
                'type' => strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'zip' ? 'شامل (قاعدة بيانات + صور)' : 'قاعدة بيانات فقط (SQL)'
            ];
        }
    }
    // ترتيب بالأحدث أولاً
    usort($server_backups, function($a, $b) use ($backups_dir) {
        return filemtime($backups_dir . '/' . $b['name']) - filemtime($backups_dir . '/' . $a['name']);
    });
}

// إحصائيات سريعة للبيانات
$count_prods = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$count_orders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$count_cats = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$count_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto px-4 md:px-8 py-10 max-w-6xl animate-fade-in">
    <!-- عنوان الصفحة -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-royal-gold/10 pb-6">
        <div>
            <span class="text-royal-darkgold text-xs font-bold tracking-widest uppercase mb-1 block">DATA & BACKUP CENTER</span>
            <h2 class="text-2xl md:text-3xl font-serif font-bold text-royal-dark flex items-center gap-2.5">
                <i class="fa-solid fa-cloud-arrow-down text-royal-gold"></i> مركز النسخ الاحتياطي والاستعادة
            </h2>
            <p class="text-xs text-gray-400 mt-1 font-light">تصدير واستعادة بيانات المتجر والصور بسهولة وأمان لحماية متجرك من أي طارئ.</p>
        </div>

        <div class="flex items-center gap-2">
            <span class="bg-royal-gold/15 text-royal-darkgold px-3.5 py-1.5 rounded-full text-xs font-bold border border-royal-gold/20 flex items-center gap-1.5">
                <i class="fa-solid fa-server"></i> محرك القاعدة: <?php echo strtoupper($db_type); ?>
            </span>
        </div>
    </div>

    <!-- رسائل التنبيه والنجاح -->
    <?php if (isset($_GET['msg'])): ?>
        <div class="p-4 mb-6 rounded-2xl text-xs font-bold flex items-center gap-2 animate-fade-in bg-green-50 text-green-700 border border-green-200">
            <i class="fa-solid fa-circle-check text-lg"></i>
            <?php
            if ($_GET['msg'] === 'snapshot_created') echo 'تم إنشاء وحفظ نقطة النسخ الاحتياطي على السيرفر بنجاح!';
            elseif ($_GET['msg'] === 'restore_success') echo '🎉 تمت استعادة النسخة الاحتياطية بنجاح وتحديث كافة البيانات والصور!';
            elseif ($_GET['msg'] === 'snapshot_deleted') echo 'تم حذف ملف النسخة الاحتياطية من السيرفر بنجاح.';
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="p-4 mb-6 rounded-2xl text-xs font-bold flex items-center gap-2 animate-fade-in bg-red-50 text-red-700 border border-red-200">
            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
            <?php
            if ($_GET['error'] === 'upload_failed') echo 'فشل رفع الملف. يرجى المحاولة مرة أخرى.';
            elseif ($_GET['error'] === 'invalid_file_type') echo 'نوع الملف غير مدعوم! يرجى رفع ملف بصيغة (.sql) أو (.zip).';
            elseif ($_GET['error'] === 'restore_error') echo 'حدث خطأ أثناء الاستعادة: ' . htmlspecialchars($_GET['err_msg'] ?? 'خطأ غير معروف');
            ?>
        </div>
    <?php endif; ?>

    <!-- بطاقات الإحصائيات السريعة -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-boxes-stacked text-royal-gold text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">المنتجات المسجلة</h4>
            <span class="text-xl font-serif font-bold text-royal-dark mt-1 block"><?php echo $count_prods; ?></span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-receipt text-royal-gold text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">طلبات العملاء</h4>
            <span class="text-xl font-serif font-bold text-royal-dark mt-1 block"><?php echo $count_orders; ?></span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-layer-group text-royal-gold text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">الأقسام والتصنيفات</h4>
            <span class="text-xl font-serif font-bold text-royal-dark mt-1 block"><?php echo $count_cats; ?></span>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-royal-gold/10 shadow-sm text-center">
            <i class="fa-solid fa-users text-royal-gold text-xl mb-2"></i>
            <h4 class="text-xs text-gray-500 font-bold">المستخدمين والمدراء</h4>
            <span class="text-xl font-serif font-bold text-royal-dark mt-1 block"><?php echo $count_users; ?></span>
        </div>
    </div>

    <!-- شبكة خيارات التصدير والاستعادة -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10 items-stretch">
        
        <!-- كارت 1: تصدير قاعدة البيانات فقط -->
        <div class="bg-white p-6 rounded-3xl border border-royal-gold/15 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mb-4 shadow-inner">
                    <i class="fa-solid fa-database"></i>
                </div>
                <h3 class="font-serif font-bold text-base text-royal-dark mb-2">نسخ قاعدة البيانات (SQL)</h3>
                <p class="text-xs text-gray-500 leading-relaxed font-light mb-6">
                    تصدير ملف <strong>.sql</strong> شامل لجميع الجداول والبيانات (الطلبات، المنتجات، الإعدادات، الكوبونات) لاستيرادها في phpMyAdmin أو الاستعادة الفورية.
                </p>
            </div>
            
            <div class="space-y-2.5">
                <a href="admin_backup.php?action=download_sql" class="w-full bg-royal-charcoal text-white hover:bg-royal-gold hover:text-royal-charcoal text-xs py-3 px-4 font-bold rounded-xl shadow btn-shine transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-download"></i> تحميل ملف SQL للكمبيوتر
                </a>
                <form method="POST" action="admin_backup.php">
                    <input type="hidden" name="snapshot_type" value="sql">
                    <button type="submit" name="create_server_snapshot" class="w-full bg-royal-sand/60 hover:bg-royal-sand text-royal-charcoal text-xs py-2.5 px-4 font-bold rounded-xl transition-all border border-royal-gold/10 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-floppy-disk text-royal-darkgold"></i> حفظ نسخة على السيرفر
                    </button>
                </form>
            </div>
        </div>

        <!-- كارت 2: النسخ الشامل الكامل (قاعدة البيانات + الصور) -->
        <div class="bg-gradient-to-br from-royal-sand/30 to-royal-cream/40 p-6 rounded-3xl border-2 border-royal-gold/30 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden">
            <span class="absolute top-4 left-4 bg-royal-gold text-royal-charcoal text-[9px] font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider shadow-sm">الأكثر شمولاً</span>
            <div>
                <div class="w-12 h-12 rounded-2xl bg-royal-gold/20 text-royal-darkgold flex items-center justify-center text-2xl mb-4 shadow-inner">
                    <i class="fa-solid fa-file-zipper"></i>
                </div>
                <h3 class="font-serif font-bold text-base text-royal-dark mb-2">النسخ الاحتياطي الكامل (ZIP)</h3>
                <p class="text-xs text-gray-600 leading-relaxed font-light mb-6">
                    حزم قاعدة البيانات بالكامل مع مجلد صور المنتجات وإيصالات التحويل (<strong>uploads/</strong>) في أرشيف مضغوط واحد متكامل.
                </p>
            </div>

            <div class="space-y-2.5">
                <a href="admin_backup.php?action=download_zip" class="w-full bg-gold-gradient text-white font-bold text-xs py-3 px-4 rounded-xl shadow-md bg-gold-gradient-hover btn-shine transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-arrow-down"></i> تحميل الأرشيف الشامل (ZIP)
                </a>
                <form method="POST" action="admin_backup.php">
                    <input type="hidden" name="snapshot_type" value="zip">
                    <button type="submit" name="create_server_snapshot" class="w-full bg-white hover:bg-royal-cream text-royal-dark text-xs py-2.5 px-4 font-bold rounded-xl transition-all border border-royal-gold/20 flex items-center justify-center gap-2 shadow-2xs">
                        <i class="fa-solid fa-server text-royal-darkgold"></i> حفظ نقطة شاملة بالسيرفر
                    </button>
                </form>
            </div>
        </div>

        <!-- كارت 3: استعادة نسخة احتياطية من ملف خارجي -->
        <div class="bg-white p-6 rounded-3xl border border-royal-gold/15 shadow-sm hover:shadow-md transition-all flex flex-col justify-between">
            <div>
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-4 shadow-inner">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                </div>
                <h3 class="font-serif font-bold text-base text-royal-dark mb-2">استعادة من ملف خارجي</h3>
                <p class="text-xs text-gray-500 leading-relaxed font-light mb-4">
                    رفع ملف نسخة احتياطية (<strong>.sql</strong> أو <strong>.zip</strong>) واسترجاع البيانات والصور تلقائياً.
                </p>
            </div>

            <form method="POST" action="admin_backup.php" enctype="multipart/form-data" onsubmit="return confirm('⚠️ تنبيه هام:\nاستعادة النسخة الاحتياطية ستقوم باستبدال البيانات الحالية بالبيانات الموجودة في الملف.\n\nهل أنت متأكد من المتابعة؟')" class="space-y-3">
                <div class="border border-dashed border-gray-300 rounded-xl p-3 bg-gray-50 text-center hover:border-royal-gold transition">
                    <input type="file" name="backup_file" accept=".sql,.zip" required class="w-full text-xs text-gray-500 file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-royal-charcoal file:text-white hover:file:bg-royal-gold hover:file:text-royal-charcoal cursor-pointer">
                </div>
                <button type="submit" name="restore_uploaded_file" class="w-full bg-blue-600 hover:bg-blue-700 text-white text-xs py-3 px-4 font-bold rounded-xl shadow transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-rotate-left"></i> بدء الاستعادة الآن
                </button>
            </form>
        </div>

    </div>

    <!-- جدول نقاط الاستعادة المحفوظة على السيرفر -->
    <div class="bg-white p-6 md:p-8 rounded-3xl border border-royal-gold/15 shadow-sm mb-10">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 border-b border-royal-gold/10 pb-4 mb-6">
            <div>
                <h3 class="font-serif font-bold text-lg text-royal-dark flex items-center gap-2">
                    <i class="fa-solid fa-hard-drive text-royal-darkgold"></i> نقاط الاستعادة المحفوظة على السيرفر
                </h3>
                <p class="text-xs text-gray-400 font-light mt-0.5">النسخ الاحتياطية المحفوظة بأمان داخل مجلد backups المحمي على الاستضافة.</p>
            </div>
            <span class="text-xs font-bold text-gray-500 bg-royal-sand/40 px-3 py-1 rounded-full">
                إجمالي النسخ: <?php echo count($server_backups); ?>
            </span>
        </div>

        <?php if (empty($server_backups)): ?>
            <div class="text-center py-12 border border-dashed border-gray-200 rounded-2xl bg-gray-50">
                <i class="fa-solid fa-folder-open text-4xl text-gray-300 mb-3"></i>
                <p class="text-xs text-gray-500 font-semibold">لا توجد نقاط استعادة محفوظة على السيرفر حالياً.</p>
                <p class="text-[11px] text-gray-400 font-light mt-1">اضغط على زر "حفظ نسخة على السيرفر" بالأعلى لإنشاء نقطة استعادة سريعة.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-2xl border border-gray-100">
                <table class="w-full text-right text-xs">
                    <thead class="bg-royal-sand/40 text-royal-dark font-bold border-b border-gray-200">
                        <tr>
                            <th class="p-4">اسم ملف النسخة</th>
                            <th class="p-4">النوع</th>
                            <th class="p-4">الحجم</th>
                            <th class="p-4">تاريخ الإنشاء</th>
                            <th class="p-4 text-center">إجراءات التحكم</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 font-medium text-gray-600">
                        <?php foreach ($server_backups as $b): ?>
                        <tr class="hover:bg-royal-sand/10 transition">
                            <td class="p-4 font-mono font-bold text-royal-charcoal flex items-center gap-2">
                                <i class="fa-solid <?php echo strpos($b['name'], '.zip') !== false ? 'fa-file-zipper text-royal-gold' : 'fa-database text-amber-500'; ?> text-base"></i>
                                <?php echo htmlspecialchars($b['name']); ?>
                            </td>
                            <td class="p-4">
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold <?php echo strpos($b['name'], '.zip') !== false ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'; ?>">
                                    <?php echo $b['type']; ?>
                                </span>
                            </td>
                            <td class="p-4 font-mono"><?php echo $b['size']; ?></td>
                            <td class="p-4 text-gray-400 text-[11px]"><?php echo $b['date']; ?></td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <!-- تحميل -->
                                    <a href="admin_backup.php?action=download_server_file&file=<?php echo urlencode($b['name']); ?>" class="bg-white border border-gray-200 hover:border-royal-gold text-royal-charcoal text-[11px] font-bold px-3 py-1.5 rounded-lg shadow-2xs transition flex items-center gap-1" title="تحميل للجهاز">
                                        <i class="fa-solid fa-download text-royal-darkgold"></i> تحميل
                                    </a>
                                    <!-- استعادة -->
                                    <a href="admin_backup.php?action=restore_server_file&file=<?php echo urlencode($b['name']); ?>" onclick="return confirm('⚠️ تأكيد الاستعادة:\nهل أنت متأكد من استعادة النسخة (<?php echo $b['name']; ?>)؟ سيتم استبدال البيانات الحالية.')" class="bg-blue-50 border border-blue-200 hover:bg-blue-600 hover:text-white text-blue-700 text-[11px] font-bold px-3 py-1.5 rounded-lg shadow-2xs transition flex items-center gap-1" title="استعادة فورية">
                                        <i class="fa-solid fa-rotate-left"></i> استعادة
                                    </a>
                                    <!-- حذف -->
                                    <a href="admin_backup.php?action=delete_server_file&file=<?php echo urlencode($b['name']); ?>" onclick="return confirm('هل أنت متأكد من رغبتك في حذف هذا الملف نهائياً؟')" class="bg-red-50 border border-red-200 hover:bg-red-600 hover:text-white text-red-600 text-[11px] font-bold px-2.5 py-1.5 rounded-lg shadow-2xs transition" title="حذف">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
include 'footer.php';
?>
