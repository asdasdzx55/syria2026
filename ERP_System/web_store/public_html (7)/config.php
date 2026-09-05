<?php
// إرسال ترويسات الأمان والحماية البرمجية المتقدمة (Security Headers)
if (!headers_sent()) {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// ضبط أمان ومدة الجلسة لتستمر سنة كاملة مع حماية ملفات تعريف الارتباط
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', '31536000'); // 365 days
    ini_set('session.gc_maxlifetime', '31536000');  // 365 days
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// ==========================================
// 1. إعدادات قاعدة البيانات
// ==========================================
$host = 'localhost'; 
$dbname = 'u323440923_SUPERMARKET'; 
$user = 'u323440923_SUPERMARKET';
$pass = 'SyrianHome#2026!Pos';

// التحقق من البيئة المحلية تلقائياً لتسهيل التجربة والتطوير
$is_local = false;
$host_only = isset($_SERVER['HTTP_HOST']) ? preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST']) : '';

if (PHP_SAPI === 'cli' 
    || $host_only === 'localhost'
    || $host_only === '127.0.0.1'
    || $host_only === '::1'
    || strpos($host_only, '192.168.') === 0
    || strpos($host_only, '10.') === 0
    || (strpos($host_only, '172.') === 0 && ($parts = explode('.', $host_only)) && isset($parts[1]) && $parts[1] >= 16 && $parts[1] <= 31)
) {
    $is_local = true;
}

$supported_countries_data = [
    'مصر' => [
        'code' => 'EG',
        'currency' => 'ج.م',
        'currency_en' => 'EGP',
        'flag' => '🇪🇬',
        'default_cost' => 50.00,
        'govs' => [
            'القاهرة', 'الجيزة', '6 أكتوبر', 'الشيخ زايد', 'الإسكندرية', 'القليوبية', 'الدقهلية', 'الشرقية', 'الغربية', 'المنوفية',
            'البحيرة', 'كفر الشيخ', 'دمياط', 'الإسماعيلية', 'بورسعيد', 'السويس', 'شمال سيناء', 'جنوب سيناء',
            'بني سويف', 'المنيا', 'الفيوم', 'أسيوط', 'سوهاج', 'قنا', 'الأقصر', 'أسوان', 'البحر الأحمر',
            'الوادي الجديد', 'مرسى مطروح'
        ]
    ]
];

$egypt_govs = $supported_countries_data['مصر']['govs'];

$db_type = 'mysql';
if ($is_local && file_exists('database.sqlite')) {
    try {
        $pdo = new PDO("sqlite:database.sqlite");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_type = 'sqlite';
    } catch (PDOException $sqlite_e) {
        $pdo = null;
    }
}

if (!$pdo) {
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db_type = 'mysql';
    } catch (PDOException $e) {
        // محاولة إنشاء قاعدة البيانات تلقائياً على السيرفر إن لم تكن موجودة بعد
        try {
            $pdo_init = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
            $pdo_init->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db_type = 'mysql';
        } catch (PDOException $create_e) {
            if ($is_local) {
                try {
                    $pdo = new PDO("sqlite:database.sqlite");
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $db_type = 'sqlite';
                } catch (PDOException $sqlite_e) {
                    die("خطأ في الاتصال بقواعد البيانات MySQL و SQLite: " . $sqlite_e->getMessage());
                }
            } else {
                die("<div style='direction:rtl;font-family:sans-serif;padding:30px;background:#fff5f5;border:1px solid #feb2b2;color:#9b2c2c;border-radius:15px;max-width:600px;margin:50px auto;text-align:center;'>
                    <h2>⚠️ خطأ في الاتصال بقاعدة بيانات هوستنجر MySQL</h2>
                    <p>يرجى التأكد من إنشاء قاعدة البيانات وتعيين اسم المستخدم وكلمة المرور في لوحة هوستنجر (hPanel):</p>
                    <code style='background:#edf2f7;padding:5px 10px;border-radius:5px;'>$e->getMessage()</code>
                </div>");
            }
        }
    }
}

try {
    if ($db_type === 'mysql') {
        // تهيئة الجداول لـ MySQL
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL, sub_category VARCHAR(100) DEFAULT NULL,
            description TEXT, price DECIMAL(10,2) NOT NULL, old_price DECIMAL(10,2) DEFAULT NULL, image_url VARCHAR(500), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, role VARCHAR(20) DEFAULT 'user'
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY, customer_name VARCHAR(255) NOT NULL, customer_phone VARCHAR(50) NOT NULL, customer_email VARCHAR(255) DEFAULT NULL,
            governorate VARCHAR(100) DEFAULT 'القاهرة', customer_address TEXT NOT NULL, order_details TEXT NOT NULL, 
            total_price DECIMAL(10,2) NOT NULL, discount_amount DECIMAL(10,2) DEFAULT 0, shipping_cost DECIMAL(10,2) DEFAULT 0, coupon_code VARCHAR(50) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'جديد', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, image_url VARCHAR(500), parent_id INT DEFAULT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key_name VARCHAR(50) PRIMARY KEY, setting_value TEXT)");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, user_name VARCHAR(100) NOT NULL,
            rating INT NOT NULL, comment TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, discount_percent INT NOT NULL, is_active TINYINT(1) DEFAULT 1
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS shipping_zones (
            id INT AUTO_INCREMENT PRIMARY KEY, country_name VARCHAR(100) NOT NULL DEFAULT 'مصر', country_code VARCHAR(10) DEFAULT 'EG', currency_symbol VARCHAR(50) DEFAULT 'ج.م', gov_name VARCHAR(100) NOT NULL, cost DECIMAL(10,2) DEFAULT 50.00, is_active TINYINT(1) DEFAULT 1
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
            id INT AUTO_INCREMENT PRIMARY KEY, product_id INT NOT NULL, image_path VARCHAR(500) NOT NULL, is_main TINYINT(1) DEFAULT 0,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS home_slides (
            id INT AUTO_INCREMENT PRIMARY KEY, image_url VARCHAR(500) NOT NULL, title VARCHAR(255) DEFAULT NULL,
            subtitle VARCHAR(255) DEFAULT NULL, link_url VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT 0
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
    } else {
        // تهيئة الجداول لـ SQLite (لا حاجة لـ AUTO_INCREMENT و syntax متوافق)
        $pdo->exec("CREATE TABLE IF NOT EXISTS products (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(255) NOT NULL, category VARCHAR(100) NOT NULL, sub_category VARCHAR(100) DEFAULT NULL,
            description TEXT, price DECIMAL(10,2) NOT NULL, old_price DECIMAL(10,2) DEFAULT NULL, image_url VARCHAR(500), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(50) NOT NULL UNIQUE, password VARCHAR(255) NOT NULL, email VARCHAR(255) DEFAULT NULL, role VARCHAR(20) DEFAULT 'user'
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT, customer_name VARCHAR(255) NOT NULL, customer_phone VARCHAR(50) NOT NULL, customer_email VARCHAR(255) DEFAULT NULL,
            governorate VARCHAR(100) DEFAULT 'القاهرة', customer_address TEXT NOT NULL, order_details TEXT NOT NULL, 
            total_price DECIMAL(10,2) NOT NULL, discount_amount DECIMAL(10,2) DEFAULT 0, shipping_cost DECIMAL(10,2) DEFAULT 0, coupon_code VARCHAR(50) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'جديد', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(100) NOT NULL UNIQUE, image_url VARCHAR(500), parent_id INTEGER DEFAULT NULL)");
        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (key_name VARCHAR(50) PRIMARY KEY, setting_value TEXT)");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INT NOT NULL, user_name VARCHAR(100) NOT NULL,
            rating INT NOT NULL, comment TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR(50) NOT NULL UNIQUE, discount_percent INT NOT NULL, is_active TINYINT(1) DEFAULT 1
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS shipping_zones (
            id INTEGER PRIMARY KEY AUTOINCREMENT, country_name VARCHAR(100) NOT NULL DEFAULT 'مصر', country_code VARCHAR(10) DEFAULT 'EG', currency_symbol VARCHAR(50) DEFAULT 'ج.م', gov_name VARCHAR(100) NOT NULL, cost DECIMAL(10,2) DEFAULT 50.00, is_active TINYINT(1) DEFAULT 1
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
            id INTEGER PRIMARY KEY AUTOINCREMENT, product_id INTEGER NOT NULL, image_path VARCHAR(500) NOT NULL, is_main TINYINT(1) DEFAULT 0,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS home_slides (
            id INTEGER PRIMARY KEY AUTOINCREMENT, image_url VARCHAR(500) NOT NULL, title VARCHAR(255) DEFAULT NULL,
            subtitle VARCHAR(255) DEFAULT NULL, link_url VARCHAR(255) DEFAULT NULL, sort_order INT DEFAULT 0
        )");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
        )");
    }

    // ترقيات الأعمدة إن لم تكن موجودة (لمنع المشاكل في MySQL و SQLite)
    try { $pdo->exec("ALTER TABLE products ADD COLUMN old_price DECIMAL(10,2) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN barcode VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN barcode2 VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN barcode3 VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN all_barcodes TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN local_code VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN cost DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN stock DECIMAL(10,2) DEFAULT 100"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN is_pos_visible TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN synced INT DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN is_weight_based TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN weight_unit VARCHAR(50) DEFAULT 'كيلو'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN weight_options TEXT DEFAULT NULL"); } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE orders ADD COLUMN country VARCHAR(100) DEFAULT 'مصر'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN currency VARCHAR(50) DEFAULT 'ج.م'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN governorate VARCHAR(100) DEFAULT 'القاهرة'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN source VARCHAR(50) DEFAULT 'web'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN cashier_name VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_person VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_fee DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN synced INT DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN customer_email VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN country VARCHAR(100) DEFAULT 'مصر'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN street VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN building VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN floor VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN apartment VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN landmark VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN gov_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN country_name VARCHAR(100) DEFAULT 'مصر'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN country_code VARCHAR(10) DEFAULT 'EG'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN currency_symbol VARCHAR(50) DEFAULT 'ج.م'"); } catch (Exception $e) {}
    
    // محاولة إنشاء جدول المفضلة بشكل منفصل للتأكد من الهجرة لقاعدة البيانات الحالية
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    // محاولة إنشاء جدول الإشعارات بشكل منفصل للتأكد من وجوده
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR(255) NOT NULL, body TEXT NOT NULL, link VARCHAR(500) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(255) NOT NULL, body TEXT NOT NULL, link VARCHAR(500) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    // محاولة إنشاء جدول تتبع الزوار والعملاء (visitor_logs)
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT, ip_address VARCHAR(100), user_id INTEGER DEFAULT NULL, page_url VARCHAR(500) NOT NULL, device_type VARCHAR(50) DEFAULT 'كمبيوتر', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_logs (
                id INT AUTO_INCREMENT PRIMARY KEY, ip_address VARCHAR(100), user_id INT DEFAULT NULL, page_url VARCHAR(500) NOT NULL, device_type VARCHAR(50) DEFAULT 'كمبيوتر', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    // محاولة إضافة أعمدة الدفع لجدول الطلبات (orders)
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_method VARCHAR(100) DEFAULT 'الدفع عند الاستلام'");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN payment_status VARCHAR(50) DEFAULT 'غير مدفوع'");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN receipt_image VARCHAR(500) DEFAULT NULL");
    } catch (Exception $e) {}
    try {
        $pdo->exec("ALTER TABLE orders ADD COLUMN transaction_ref VARCHAR(250) DEFAULT NULL");
    } catch (Exception $e) {}

    // محاولة إنشاء جدول السلات المتروكة (abandoned_carts)
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS abandoned_carts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id VARCHAR(100) NOT NULL,
                user_id INTEGER DEFAULT NULL,
                customer_name VARCHAR(150) DEFAULT NULL,
                customer_phone VARCHAR(50) DEFAULT NULL,
                customer_email VARCHAR(150) DEFAULT NULL,
                country VARCHAR(100) DEFAULT 'مصر',
                governorate VARCHAR(100) DEFAULT NULL,
                cart_data TEXT NOT NULL,
                total_price DECIMAL(10,2) DEFAULT 0.00,
                status VARCHAR(20) DEFAULT 'abandoned',
                recovery_sent_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS abandoned_carts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id VARCHAR(100) NOT NULL,
                user_id INT DEFAULT NULL,
                customer_name VARCHAR(150) DEFAULT NULL,
                customer_phone VARCHAR(50) DEFAULT NULL,
                customer_email VARCHAR(150) DEFAULT NULL,
                country VARCHAR(100) DEFAULT 'مصر',
                governorate VARCHAR(100) DEFAULT NULL,
                cart_data TEXT NOT NULL,
                total_price DECIMAL(10,2) DEFAULT 0.00,
                status VARCHAR(20) DEFAULT 'abandoned',
                recovery_sent_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    // محاولة إنشاء جدول خيارات ومواصفات المنتجات (product_variants)
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                variant_type VARCHAR(50) NOT NULL,
                variant_name VARCHAR(100) NOT NULL,
                color_code VARCHAR(50) DEFAULT NULL,
                price_modifier DECIMAL(10,2) DEFAULT 0.00,
                stock INTEGER DEFAULT 999,
                sku VARCHAR(100) DEFAULT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS product_variants (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                variant_type VARCHAR(50) NOT NULL,
                variant_name VARCHAR(100) NOT NULL,
                color_code VARCHAR(50) DEFAULT NULL,
                price_modifier DECIMAL(10,2) DEFAULT 0.00,
                stock INT DEFAULT 999,
                sku VARCHAR(100) DEFAULT NULL,
                is_active TINYINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    try {
        $pdo->exec("ALTER TABLE product_variants ADD COLUMN color_code VARCHAR(50) DEFAULT NULL");
    } catch (Exception $e) {}

    // إنشاء جداول المصروفات والموردين والشركاء لربط الكاشير والعمليات
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category VARCHAR(100) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                note TEXT,
                date VARCHAR(50),
                partner_name VARCHAR(100) DEFAULT NULL,
                supplier_id INTEGER DEFAULT NULL,
                payment_method VARCHAR(50) DEFAULT 'كاش',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(150) NOT NULL UNIQUE,
                phone VARCHAR(50) DEFAULT NULL,
                balance DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS partners (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_drivers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL UNIQUE,
                phone VARCHAR(50) DEFAULT NULL,
                pin_code VARCHAR(20) DEFAULT '1234',
                is_active INTEGER DEFAULT 1,
                cash_balance DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                role VARCHAR(100) DEFAULT 'عامل',
                salary_type VARCHAR(20) DEFAULT 'monthly',
                base_salary DECIMAL(10,2) DEFAULT 0.00,
                daily_wage DECIMAL(10,2) DEFAULT 0.00,
                hire_date VARCHAR(50) DEFAULT NULL,
                is_active INTEGER DEFAULT 1,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $pdo->exec("CREATE TABLE IF NOT EXISTS employee_payouts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                employee_name VARCHAR(150) NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'كاش من الدرج',
                date VARCHAR(50) NOT NULL,
                month_year VARCHAR(20) DEFAULT NULL,
                notes TEXT,
                cashier_name VARCHAR(100) DEFAULT 'كاشير المحل',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category VARCHAR(100) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                note TEXT,
                date VARCHAR(50),
                partner_name VARCHAR(100) DEFAULT NULL,
                supplier_id INT DEFAULT NULL,
                payment_method VARCHAR(50) DEFAULT 'كاش',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS suppliers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL UNIQUE,
                phone VARCHAR(50) DEFAULT NULL,
                balance DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS expense_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS partners (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_drivers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                phone VARCHAR(50) DEFAULT NULL,
                pin_code VARCHAR(20) DEFAULT '1234',
                is_active TINYINT DEFAULT 1,
                cash_balance DECIMAL(10,2) DEFAULT 0.00,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                role VARCHAR(100) DEFAULT 'عامل',
                salary_type VARCHAR(20) DEFAULT 'monthly',
                base_salary DECIMAL(10,2) DEFAULT 0.00,
                daily_wage DECIMAL(10,2) DEFAULT 0.00,
                hire_date DATE DEFAULT NULL,
                is_active TINYINT DEFAULT 1,
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pdo->exec("CREATE TABLE IF NOT EXISTS employee_payouts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                employee_id INT NOT NULL,
                employee_name VARCHAR(150) NOT NULL,
                type VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                payment_method VARCHAR(50) DEFAULT 'كاش من الدرج',
                date DATE NOT NULL,
                month_year VARCHAR(20) DEFAULT NULL,
                notes TEXT,
                cashier_name VARCHAR(100) DEFAULT 'كاشير المحل',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // إدخال تصنيفات المصروفات الافتراضية
        if ($pdo->query("SELECT COUNT(*) FROM expense_categories")->fetchColumn() == 0) {
            $cats = ["نثريات", "إيجار", "فواتير (كهرباء/مياه)", "صيانة", "رواتب عاملين", "سلف عاملين", "سداد موردين", "مشتريات بضاعة", "مسحوبات الإدارة", "أخرى"];
            $ins_cat = $pdo->prepare("INSERT OR IGNORE INTO expense_categories (name) VALUES (?)");
            foreach ($cats as $c) { $ins_cat->execute([$c]); }
        }

        // إدخال الموردين الافتراضيين
        if ($pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn() == 0) {
            $sups = [
                ["شركة الخيرات الشامية للمؤونة", "01011223344", 4500.00],
                ["مؤسسة زيتون إدلب للزيوت والمخللات", "01022334455", 3200.00],
                ["معامل دمشق للأجبان والألبان البلدية", "01033445566", 6800.00],
                ["مطاحن ومحامص حلب للبهارات والبن", "01044556677", 1800.00],
                ["شركة الفرات للمواد الغذائية والتموين", "01055667788", 2900.00]
            ];
            $ins_sup = $pdo->prepare("INSERT OR IGNORE INTO suppliers (name, phone, balance) VALUES (?, ?, ?)");
            foreach ($sups as $s) { $ins_sup->execute($s); }
        }

        // إدخال الشركاء الافتراضيين
        if ($pdo->query("SELECT COUNT(*) FROM partners")->fetchColumn() == 0) {
            $parts = ["المالك / المدير العام", "الشريك الأول (أبو النور)", "الشريك الثاني (أبو وضاح)"];
            $ins_part = $pdo->prepare("INSERT OR IGNORE INTO partners (name) VALUES (?)");
            foreach ($parts as $p) { $ins_part->execute([$p]); }
        }

        // إدخال الطيارين الافتراضيين مع رموز الـ PIN
        if ($pdo->query("SELECT COUNT(*) FROM delivery_drivers")->fetchColumn() == 0) {
            $drivers = [
                ["كابتن حسام السريع", "01012345678", "1111", 0.00],
                ["كابتن طارق الدليفري", "01023456789", "2222", 0.00],
                ["كابتن محمود الشامي", "01034567890", "3333", 0.00]
            ];
            $ins_drv = $pdo->prepare("INSERT OR IGNORE INTO delivery_drivers (name, phone, pin_code, cash_balance) VALUES (?, ?, ?, ?)");
            foreach ($drivers as $d) { $ins_drv->execute($d); }
        }
    } catch (Exception $e) {}

    // ملء بيانات المعرض الافتراضي للمنتجات القديمة
    $old_prods = $pdo->query("SELECT id, image_url FROM products WHERE id NOT IN (SELECT product_id FROM product_images)")->fetchAll(PDO::FETCH_ASSOC);
    foreach($old_prods as $op) {
        if(!empty($op['image_url'])) {
            $pdo->prepare("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 1)")->execute([$op['id'], $op['image_url']]);
        }
    }

    // ترقيات الأعمدة إن لم تكن موجودة (لمنع المشاكل في MySQL و SQLite)
    try { $pdo->exec("ALTER TABLE products ADD COLUMN old_price DECIMAL(10,2) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN barcode VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN barcode2 VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN barcode3 VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN all_barcodes TEXT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN local_code VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN cost DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN stock DECIMAL(10,2) DEFAULT 100"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN is_pos_visible TINYINT(1) DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN synced INT DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN is_weight_based TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN weight_unit VARCHAR(50) DEFAULT 'كيلو'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN weight_options TEXT DEFAULT NULL"); } catch (Exception $e) {}

    try { $pdo->exec("ALTER TABLE orders ADD COLUMN country VARCHAR(100) DEFAULT 'مصر'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN currency VARCHAR(50) DEFAULT 'ج.م'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN governorate VARCHAR(100) DEFAULT 'القاهرة'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_lat VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_lng VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_distance_km DECIMAL(10,2) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN shipping_type VARCHAR(50) DEFAULT 'flat'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN source VARCHAR(50) DEFAULT 'web'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN cashier_name VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_person VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN delivery_fee DECIMAL(10,2) DEFAULT 0"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN synced INT DEFAULT 1"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN email VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN customer_email VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN country VARCHAR(100) DEFAULT 'مصر'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN street VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN building VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN floor VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN apartment VARCHAR(50) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN landmark VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE users ADD COLUMN gov_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE categories ADD COLUMN parent_id INT DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE products ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN country_name VARCHAR(100) DEFAULT 'مصر'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN country_code VARCHAR(10) DEFAULT 'EG'"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE shipping_zones ADD COLUMN currency_symbol VARCHAR(50) DEFAULT 'ج.م'"); } catch (Exception $e) {}

    // محاولة إنشاء جدول المفضلة بشكل منفصل للتأكد من الهجرة لقاعدة البيانات الحالية
    try {
        if ($db_type === 'sqlite') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
                id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } else {
            $pdo->exec("CREATE TABLE IF NOT EXISTS wishlist (
                id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        }
    } catch (Exception $e) {}

    // إدخال مستخدم المدير الافتراضي بأمان تام
    if ($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt_admin_seed = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt_admin_seed->execute(['admin', $hash, 'admin']);
    }

    // إدخال وتحديث مناطق الشحن لمحافظات جمهورية مصر العربية فقط
    try {
        $pdo->exec("DELETE FROM shipping_zones WHERE country_name != 'مصر' OR country_name IS NULL");
        $stmt_insert_zone = $pdo->prepare("INSERT INTO shipping_zones (country_name, country_code, currency_symbol, gov_name, cost, is_active) VALUES ('مصر', 'EG', 'ج.م', ?, ?, 1)");
        $check_exist = $pdo->prepare("SELECT COUNT(*) FROM shipping_zones WHERE country_name = 'مصر' AND gov_name = ?");
        
        foreach ($egypt_govs as $gov) {
            $check_exist->execute([$gov]);
            if ($check_exist->fetchColumn() == 0) {
                $stmt_insert_zone->execute([$gov, 50.00]);
            }
        }
        $pdo->exec("UPDATE shipping_zones SET country_name = 'مصر', country_code = 'EG', currency_symbol = 'ج.م'");
    } catch (Exception $e) {}

    // إعدادات المتجر الافتراضية العامة مع لوحة الألوان المخصصة (Theme Colors) - مصر فقط
    $default_settings = [
        'store_name' => 'سوبر ماركت المنزل السوري',
        'store_tagline' => 'البيت بيتك لكل المنتجات الغذائية والمؤونة الشامية الأصيلة في مصر',
        'store_description' => 'سوبر ماركت المنزل السوري - تشكيلة شاملة من المواد التموينية، الأجبان والألبان الشامية، الزيوت والمكدوس، البهارات والمكسرات والحلويات بأعلى جودة.',
        'store_logo' => '',
        'store_favicon' => '',
        'store_currency' => 'ج.م',
        'api_secret_key' => 'syrian_home_pos_secret_token_2026',
        
        // خيارات الدولة والعملة (مصر فقط)
        'default_country' => 'مصر',
        'enable_multi_country' => '0',
        'preferred_currency_mode' => 'fixed',
        'active_countries' => json_encode(['مصر'], JSON_UNESCAPED_UNICODE),
        
        // نظام الشحن الذكي بالكيلومتر (القاهرة، الجيزة، 6 أكتوبر)
        'enable_km_shipping' => '1',
        'km_shipping_govs' => json_encode(['القاهرة', 'الجيزة', '6 أكتوبر', 'الشيخ زايد', 'القليوبية'], JSON_UNESCAPED_UNICODE),
        'store_lat' => '30.0444',
        'store_lng' => '31.2357',
        'store_address_name' => 'المحل الرئيسي / مركز الشحن والتوزيع',
        'km_rate' => '2',
        'km_base_min_price' => '25',
        
        // لوحة الألوان والتصميم (Theme Colors)
        'theme_primary_color' => '#15803d',
        'theme_secondary_color' => '#166534',
        'theme_accent_color' => '#d97706',
        'theme_header_bg' => '#0f172a',
        'theme_header_text' => '#ffffff',
        'theme_body_bg' => '#f8fafc',
        'theme_card_bg' => '#ffffff',
        'theme_btn_color' => '#15803d',
        'theme_btn_text' => '#ffffff',
        
        'home_banner' => 'placeholder.php?w=1920&h=800',
        'announcement_bar' => '🌟 أهلاً بكم في سوبر ماركت المنزل السوري - توصيل سريع لجميع المحافظات المصرية!',
        'contact_phone' => '01012345678', 
        'contact_email' => 'info@almanzel-alsoury.com',
        'social_whatsapp' => '01012345678', 
        'social_facebook' => '', 
        'social_instagram' => '',
        'policy_return' => "• يحق للعميل طلب الاستبدال أو الاسترجاع خلال 14 يوماً من تاريخ استلام الشحنة وفقاً لأحكام قانون حماية المستهلك المصري.
• يشترط أن يكون المنتج في حالته الأصلية وبغلافه الأصلي غير المفتوح أو المستخدم مع كامل ملحقاته وفاتورة الشراء.
• في حالة وجود عيب مصنعي أو خطأ في المنتج المستلم، يتحمل المتجر كافة تكاليف الشحن والإرجاع بالكامل دون أي رسوم على العميل.
• لا تقبل المنتجات ذات الاستخدام الشخصي أو المخصصة بطلب خاص بعد فتح غلافها المغلق حفاظاً على الصحة العامة.
• يتم فحص المنتج فور وصوله لمستودعاتنا ويتم استرداد المبلغ خلال 3 إلى 5 أيام عمل عبر فودافون كاش أو إنستا باي أو التحويل البنكي.",
        'policy_shipping' => "• نوفر خدمات الشحن السريع والتوصيل المباشر لكافة محافظات ومدن جمهورية مصر العربية.
• يستغرق التوصيل عادةً من 24 إلى 48 ساعة داخل القاهرة والجيزة والإسكندرية، ومن يومين إلى 4 أيام عمل لباقي المحافظات.
• يتم تزويد العميل برقم تتبع فور تجهيز الشحنة مع إمكانية متابعة خط سير الطلب لحظياً عبر صفحة 'تتبع طلبك'.
• يرجى التأكد من كتابة العنوان بالتفصيل ورقم هاتف متاح لضمان سرعة وسهولة تواصل مندوب التوصيل.",
        'policy_payment' => "• نوفر وسائل دفع محلية سهلة وآمنة: الدفع نقداً عند الاستلام (كاش)، المحافظ الإلكترونية (فودافون كاش / أورانج / اتصالات / وي)، تحويل إنستا باي (InstaPay)، وبطاقات الدفع الإلكتروني (فيزا / ماستركارد / ميزة).
• جميع المعاملات البنكية والمدفوعات مشفرة ومحمية بأعلى بروتوكولات الأمان والحماية المصرفية والتشفير العالمي SSL.
• لا نقوم بحفظ أو تخزين أي بيانات للبطاقات الائتمانية أو الحسابات البنكية على خوادمنا نهائياً.",
        'policy_privacy' => "• نلتزم التزاماً تاماً بحماية خصوصية وأمان بياناتك الشخصية والحفاظ على سريتها المطلقة.
• تُستخدم بياناتك المسجلة (الاسم، الهاتف، العنوان، البريد) فقط لغرض معالجة وتجهيز وتوصيل طلباتك وتقديم الدعم الفني.
• نتعهد بعدم بيع أو تأجير أو مشاركة بياناتك الشخصية مع أي جهة خارجية أو أطراف ثالثة لأغراض إعلانية.",
        'policy_terms' => "• باستخدامك لهذا المتجر وإتمام أي طلب شراء، فإنك توافق على الالتزام بكافة الشروط والأحكام والسياسات المعلنة.
• نحتفظ بالحق في تعديل أو تحديث أسعار المنتجات والعروض الترويجية ومواصفات السلع وفقاً لحالة المخزون المتاح.
• كافة المحتويات والتصميمات والعلامات التجارية المنشورة على المتجر محمية بموجب حقوق الملكية الفكرية.",
        'groq_api_key' => '', 
        'ai_chat_enabled' => '0',
        'meta_pixel_id' => '',
        'meta_pixel_enabled' => '0',
        'cod_enabled' => '1',
        'vodafone_cash_enabled' => '1',
        'vodafone_cash_number' => '01012345678',
        'instapay_enabled' => '1',
        'instapay_address' => 'syrianhome@instapay',
        'instapay_name' => 'سوبر ماركت المنزل السوري',
        'cham_cash_enabled' => '0',
        'cham_cash_number' => '',
        'cham_cash_name' => '',
        'syriatel_cash_number' => '',
        'paypal_enabled' => '0',
        'paypal_email' => '',
        'paypal_client_id' => '',
        'paymob_enabled' => '0',
        'paymob_api_key' => '',
        'paymob_integration_id_card' => '',
        'paymob_integration_id_wallet' => '',
        'paymob_iframe_id' => ''
    ];
    foreach ($default_settings as $key => $val) {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE key_name = ?"); $stmt->execute([$key]);
        $curr = $stmt->fetchColumn();
        if ($curr === false) { 
            $pdo->prepare("INSERT INTO settings (key_name, setting_value) VALUES (?, ?)")->execute([$key, $val]); 
        } elseif ($key === 'store_name' && ($curr === 'المتجر الإلكتروني' || empty(trim($curr)))) {
            $pdo->prepare("UPDATE settings SET setting_value = ? WHERE key_name = 'store_name'")->execute([$val]);
        }
    }

    // تطبيق وتثبيت إعدادات مصر فقط في قاعدة البيانات الحالية
    try {
        $pdo->exec("UPDATE settings SET setting_value = 'مصر' WHERE key_name = 'default_country'");
        $pdo->exec("UPDATE settings SET setting_value = 'ج.م' WHERE key_name = 'store_currency'");
        $pdo->exec("UPDATE settings SET setting_value = '0' WHERE key_name = 'enable_multi_country'");
        $pdo->exec("UPDATE settings SET setting_value = 'fixed' WHERE key_name = 'preferred_currency_mode'");
        $pdo->exec("UPDATE settings SET setting_value = '0' WHERE key_name = 'cham_cash_enabled'");
        $pdo->exec("UPDATE settings SET setting_value = '0' WHERE key_name = 'paypal_enabled'");
    } catch (Exception $e) {}

} catch (PDOException $e) {
    $msg = "خطأ في تهيئة قاعدة البيانات: " . $e->getMessage();
    die("<div style='direction:rtl; text-align:right; font-family:tahoma; padding:20px; border:1px solid #ffcccc; background:#fff5f5; border-radius:5px; margin:50px auto; max-width:600px;'>$msg</div>");
}

// ==========================================
// 2. تحميل الإعدادات في مصفوفة عامة
// ==========================================
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $settings[$row['key_name']] = $row['setting_value']; }

// تعيين القيم الافتراضية العامة إن لم تكن مسجلة
$currency_symbol = htmlspecialchars($settings['store_currency'] ?? 'ج.م');
$store_title = htmlspecialchars($settings['store_name'] ?? 'المتجر الإلكتروني');
$store_tagline = htmlspecialchars($settings['store_tagline'] ?? '');

// ==========================================
// 3. دوال عامة مساعدة وحماية متقدمة
// ==========================================

// دالة تنظيف وطباعة النصوص بأمان لمنع ثغرات XSS
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// توليد رمز حماية النماذج (CSRF Token)
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// توليد حقل CSRF المخفي للنماذج
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// التحقق من صحة رمز CSRF
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// دالة رفع الصور الآمنة مع فحص دقيق للامتداد ونوع الملف والحجم
function uploadImage($file_array, $max_size_mb = 8) {
    if (isset($file_array) && $file_array['error'] === UPLOAD_ERR_OK) {
        // التحقق من حجم الملف
        if ($file_array['size'] > ($max_size_mb * 1024 * 1024)) {
            return false;
        }

        $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];
        $ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed_exts, true)) {
            return false;
        }

        // منع أسماء الملفات التي تحتوي على null bytes أو تلاعب في المسارات
        if (strpos($file_array['name'], "\0") !== false) {
            return false;
        }

        // التأكد من مجلد uploads وحمايته التلقائية
        $upload_dir = 'uploads';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
            @file_put_contents($upload_dir . '/.htaccess', "<FilesMatch \"(?i)\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi|exe)$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
            @file_put_contents($upload_dir . '/index.html', "");
        }

        // إنشاء اسم عشوائي آمن وغير قابل للتوقع
        $safe_name = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = $upload_dir . '/' . $safe_name;

        if (move_uploaded_file($file_array['tmp_name'], $dest)) {
            return $dest;
        }
    }
    return false;
}

function uploadMultipleImages($files, $max_size_mb = 8) {
    $uploaded_paths = [];
    if (!empty($files['name'][0])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK && $files['size'][$i] <= ($max_size_mb * 1024 * 1024)) {
                $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
                if (in_array($ext, $allowed, true) && strpos($files['name'][$i], "\0") === false) {
                    $upload_dir = 'uploads';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $safe_name = bin2hex(random_bytes(16)) . '_' . $i . '.' . $ext;
                    $dest = $upload_dir . '/' . $safe_name;
                    if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                        $uploaded_paths[] = $dest;
                    }
                }
            }
        }
    }
    return $uploaded_paths;
}

// دالة توليد روابط الصور البديلة بمقاسات دقيقة
function getPlaceholderUrl($width = 800, $height = 600, $text = '') {
    $params = ['w' => $width, 'h' => $height];
    if (!empty($text)) $params['text'] = $text;
    return 'placeholder.php?' . http_build_query($params);
}

// التحقق من رتبة المدير
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// دالة استخراج وتنسيق خيارات الوزن لمنتجات الأوزان (سوبر ماركت المنزل السوري)
function getProductWeightOptions($product) {
    if (!empty($product['weight_options'])) {
        if (is_array($product['weight_options'])) {
            return $product['weight_options'];
        }
        $decoded = json_decode($product['weight_options'], true);
        if (is_array($decoded) && !empty($decoded)) {
            return $decoded;
        }
        // في حال تم تخزينها كسلسلة نصية مفصولة بفواصل
        $parts = explode(',', $product['weight_options']);
        $opts = [];
        foreach ($parts as $p) {
            $kv = explode(':', trim($p));
            if (count($kv) >= 2) {
                $opts[] = ['weight' => (float)$kv[0], 'label' => trim($kv[1])];
            }
        }
        if (!empty($opts)) return $opts;
    }
    // الخيارات القياسية الافتراضية لمنتجات الوزن (ربع، نصف، 3/4، كيلو)
    return [
        ['weight' => 0.25, 'label' => 'ربع كيلو (250 غرام)'],
        ['weight' => 0.50, 'label' => 'نصف كيلو (500 غرام)'],
        ['weight' => 0.75, 'label' => 'ثلاثة أرباع كيلو (750 غرام)'],
        ['weight' => 1.00, 'label' => 'كيلو كامل (1000 غرام)']
    ];
}

// ==========================================
// 4. معالجة إضافة المنتجات للسلة (عامة)
// ==========================================
if (isset($_POST['add_to_cart'])) {
    $id = (int)$_POST['product_id'];
    $qty_to_add = isset($_POST['qty']) ? max(1, (int)$_POST['qty']) : 1;
    $base_price = (float)$_POST['product_price'];
    $prod_name = trim($_POST['product_name']);
    $prod_image = trim($_POST['product_image']);
    
    // فحص خيار الوزن المختار (Weight-Based Products)
    $selected_weight = isset($_POST['selected_weight']) && is_numeric($_POST['selected_weight']) ? (float)$_POST['selected_weight'] : null;
    $weight_label = isset($_POST['weight_label']) ? trim($_POST['weight_label']) : '';
    
    // معالجة الخيارات والمواصفات المختارة (Variants)
    $selected_variants = isset($_POST['selected_variants']) ? $_POST['selected_variants'] : [];
    $variant_text_parts = [];
    $total_price_mod = 0.0;
    
    if ($selected_weight !== null && $selected_weight > 0) {
        $final_unit_price = round($base_price * $selected_weight, 2);
        if (!empty($weight_label)) {
            $variant_text_parts[] = 'الوزن: ' . $weight_label;
        } else {
            $variant_text_parts[] = 'الوزن: ' . $selected_weight . ' كجم';
        }
    } else {
        $final_unit_price = $base_price;
    }
    
    if (!empty($selected_variants) && is_array($selected_variants)) {
        foreach ($selected_variants as $v_type => $v_val) {
            // التحقق إن كانت القيمة تحتوي على فرق السعر المشفر JSON أو نص بسيط
            if (is_string($v_val) && str_starts_with($v_val, '{')) {
                $decoded = json_decode($v_val, true);
                if ($decoded && isset($decoded['name'])) {
                    $v_name = $decoded['name'];
                    $v_mod = (float)($decoded['price'] ?? 0);
                    $variant_text_parts[] = $v_type . ': ' . $v_name;
                    $total_price_mod += $v_mod;
                }
            } elseif (!empty($v_val)) {
                $variant_text_parts[] = $v_type . ': ' . $v_val;
            }
        }
    }
    
    $variant_summary = !empty($variant_text_parts) ? implode(' | ', $variant_text_parts) : '';
    $final_unit_price += $total_price_mod;
    $item_display_name = $prod_name . (!empty($variant_summary) ? ' (' . $variant_summary . ')' : '');
    
    // مفتاح فريد للعنصر في السلة يجمع معرّف المنتج مع خياراته والوزن
    $cart_key = !empty($variant_summary) ? ($id . '_' . substr(md5($variant_summary), 0, 8)) : (string)$id;
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    if (isset($_SESSION['cart'][$cart_key])) {
        $_SESSION['cart'][$cart_key]['qty'] += $qty_to_add;
    } else {
        $_SESSION['cart'][$cart_key] = [
            'product_id' => $id,
            'name' => $item_display_name,
            'base_price' => $base_price,
            'price' => $final_unit_price,
            'image' => $prod_image,
            'qty' => $qty_to_add,
            'weight' => $selected_weight,
            'weight_label' => $weight_label,
            'variants' => $variant_summary
        ];
    }
    
    $_SESSION['meta_add_to_cart_event'] = [
        'id' => $id,
        'name' => $item_display_name,
        'price' => $final_unit_price,
        'qty' => $qty_to_add
    ];
    
    // مزامنة السلة المتروكة تلقائياً
    if (function_exists('saveOrUpdateAbandonedCart')) {
        saveOrUpdateAbandonedCart();
    }

    $return_page = isset($_POST['return_page']) ? $_POST['return_page'] : 'shop.php';
    header("Location: " . $return_page);
    exit;
}

// ==========================================
// 4.1 دالة حفظ وتحديث السلة المتروكة (Abandoned Cart)
// ==========================================
function saveOrUpdateAbandonedCart($customer_name = '', $customer_phone = '', $customer_email = '', $governorate = '') {
    global $pdo;
    if (!$pdo) return false;
    
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) return false;
    
    $session_id = session_id();
    $user_id = $_SESSION['user_id'] ?? null;
    
    $total_price = 0;
    foreach ($cart as $item) {
        $total_price += ($item['price'] * $item['qty']);
    }
    
    $cart_json = json_encode($cart, JSON_UNESCAPED_UNICODE);
    
    // فحص وجود سجل سابق لهذه الجلسة
    $stmt = $pdo->prepare("SELECT id, customer_name, customer_phone, customer_email, governorate, status FROM abandoned_carts WHERE session_id = ? AND status != 'converted' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$session_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        $name = !empty($customer_name) ? $customer_name : $existing['customer_name'];
        $phone = !empty($customer_phone) ? $customer_phone : $existing['customer_phone'];
        $email = !empty($customer_email) ? $customer_email : $existing['customer_email'];
        $gov = !empty($governorate) ? $governorate : $existing['governorate'];
        
        $upd = $pdo->prepare("UPDATE abandoned_carts SET user_id = ?, customer_name = ?, customer_phone = ?, customer_email = ?, governorate = ?, cart_data = ?, total_price = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $upd->execute([$user_id, $name, $phone, $email, $gov, $cart_json, $total_price, $existing['id']]);
        return $existing['id'];
    } else {
        $ins = $pdo->prepare("INSERT INTO abandoned_carts (session_id, user_id, customer_name, customer_phone, customer_email, governorate, cart_data, total_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'abandoned')");
        $ins->execute([$session_id, $user_id, $customer_name, $customer_phone, $customer_email, $governorate, $cart_json, $total_price]);
        return $pdo->lastInsertId();
    }
}

// دالة تحويل السلة المتروكة إلى طلب مكتمل (Converted)
function markAbandonedCartConverted() {
    global $pdo;
    if (!$pdo) return false;
    $session_id = session_id();
    $stmt = $pdo->prepare("UPDATE abandoned_carts SET status = 'converted', updated_at = CURRENT_TIMESTAMP WHERE session_id = ?");
    return $stmt->execute([$session_id]);
}

// ==========================================
// 5. دالة إرسال الفاتورة البريدية المنسقة للعميل
// ==========================================
function sendInvoiceEmail($to_email, $customer_name, $order_details, $subtotal, $discount, $shipping, $total, $coupon, $address, $gov, $phone) {
    global $settings;
    $s_name = !empty($settings['store_name']) ? htmlspecialchars($settings['store_name']) : 'المتجر الإلكتروني';
    $s_tagline = !empty($settings['store_tagline']) ? htmlspecialchars($settings['store_tagline']) : '';
    $s_currency = !empty($settings['store_currency']) ? htmlspecialchars($settings['store_currency']) : 'ج.م';
    $s_logo = !empty($settings['store_logo']) ? $settings['store_logo'] : '';

    $subject = "فاتورة طلبك من متجر {$s_name} - رقم " . rand(1000, 9999);
    
    // إعداد تفاصيل المنتجات كجدول HTML
    $items_rows = '';
    $items = explode("\n", trim($order_details));
    foreach ($items as $item) {
        if (empty(trim($item))) continue;
        $clean_item = ltrim($item, '• ');
        $items_rows .= "<tr><td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>{$clean_item}</td></tr>";
    }

    $logo_html = !empty($s_logo) ? "<img src='{$s_logo}' alt='{$s_name}' style='max-height: 50px; margin-bottom: 8px;'>" : "<h1 style=\"color: #FAF9F5; margin: 0; font-size: 24px; font-weight: bold; letter-spacing: 1px;\">{$s_name}</h1>";
    
    $message = "
    <html>
    <head>
        <title>فاتورة الشراء</title>
        <meta charset='UTF-8'>
    </head>
    <body style=\"direction: rtl; text-align: right; font-family: 'Cairo', tahoma, sans-serif; background-color: #FAF9F5; margin: 0; padding: 20px;\">
        <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border: 1px solid rgba(194, 164, 104, 0.2); border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.03);\">
            <!-- الهيدر -->
            <div style=\"background-color: #1a202c; padding: 30px; text-align: center; border-bottom: 3px solid #C2A468;\">
                {$logo_html}
                <p style=\"color: #C2A468; margin: 5px 0 0 0; font-size: 12px;\">{$s_tagline}</p>
            </div>
            
            <!-- المحتوى -->
            <div style=\"padding: 30px;\">
                <h2 style=\"color: #1a202c; margin-top: 0; font-size: 18px;\">مرحباً يا {$customer_name}، ✨</h2>
                <p style=\"color: #666; font-size: 13px; line-height: 1.6;\">شكراً لتسوقك من متجر {$s_name}. تم استلام طلبك بنجاح وجاري تجهيزه للشحن والتوصيل. إليك تفاصيل فاتورتك:</p>
                
                <!-- تفاصيل المنتجات -->
                <table style=\"width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px;\">
                    <thead>
                        <tr style=\"background-color: #F3EFE6; color: #1a202c;\">
                            <th style=\"padding: 10px; text-align: right; font-weight: bold; border-radius: 6px;\">المنتجات المطلوبة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$items_rows}
                    </tbody>
                </table>
                
                <!-- الحساب المالي -->
                <table style=\"width: 100%; margin-top: 20px; font-size: 13px; border-top: 2px solid #F3EFE6; pt-10px;\">
                    <tr>
                        <td style=\"padding: 8px 0; color: #666;\">مجموع المشتريات:</td>
                        <td style=\"padding: 8px 0; text-align: left; font-weight: bold;\">{$subtotal} {$s_currency}</td>
                    </tr>";
                    if ($discount > 0) {
                        $message .= "
                        <tr>
                            <td style=\"padding: 8px 0; color: #d32f2f;\">الخصم المطبق ({$coupon}):</td>
                            <td style=\"padding: 8px 0; text-align: left; font-weight: bold; color: #d32f2f;\">- {$discount} {$s_currency}</td>
                        </tr>";
                    }
                    $message .= "
                    <tr>
                        <td style=\"padding: 8px 0; color: #666;\">تكلفة الشحن والتوصيل:</td>
                        <td style=\"padding: 8px 0; text-align: left; font-weight: bold;\">{$shipping} {$s_currency}</td>
                    </tr>
                    <tr style=\"font-size: 16px; color: #1a202c; font-weight: bold;\">
                        <td style=\"padding: 15px 0 0 0; border-top: 1px dashed #eee;\">الإجمالي النهائي:</td>
                        <td style=\"padding: 15px 0 0 0; text-align: left; border-top: 1px dashed #eee; color: #C2A468;\">{$total} {$s_currency}</td>
                    </tr>
                </table>
                
                <!-- بيانات الشحن -->
                <div style=\"margin-top: 30px; background-color: #FAF9F5; padding: 20px; border-radius: 12px; border: 1px solid #F3EFE6; font-size: 12px;\">
                    <h3 style=\"color: #1a202c; margin-top: 0; font-size: 14px;\">📍 معلومات الشحن والتوصيل</h3>
                    <p style=\"margin: 5px 0; color: #555;\"><b>المستلم:</b> {$customer_name}</p>
                    <p style=\"margin: 5px 0; color: #555;\"><b>الهاتف:</b> {$phone}</p>
                    <p style=\"margin: 5px 0; color: #555;\"><b>المحافظة / المدينة:</b> {$gov}</p>
                    <p style=\"margin: 5px 0; color: #555;\"><b>العنوان:</b> {$address}</p>
                </div>
            </div>
            
            <!-- التذييل -->
            <div style=\"background-color: #F3EFE6; padding: 20px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #e2dbcd;\">
                <p style=\"margin: 0;\">هذه الفاتورة مرسلة تلقائياً من متجر {$s_name}.</p>
                <p style=\"margin: 5px 0 0 0;\">© " . date('Y') . " {$s_name}. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // تحديد الدومين الحالي ديناميكياً لتجنب حظر البريد (Spoofing)
    $domain = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : 'localhost';
    if (substr($domain, 0, 4) === 'www.') {
        $domain = substr($domain, 4);
    }
    
    // إرسال الإيميل كـ HTML
    $headers = "MIME-Version: 1.0\n";
    $headers .= "Content-type:text/html;charset=UTF-8\n";
    $headers .= "From: {$s_name} <no-reply@" . $domain . ">\n";
    $headers .= "Reply-To: no-reply@" . $domain . "\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return @mail($to_email, $subject, $message, $headers);
}

// دالة التحقق من وجود منتج في المفضلة (Wishlist)
function in_wishlist($product_id) {
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$_SESSION['user_id'], $product_id]);
        return $stmt->fetchColumn() > 0;
    }
    return isset($_SESSION['wishlist']) && in_array($product_id, $_SESSION['wishlist']);
}

// دالة جلب تقييم المنتج
function getProductRating($product_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count_reviews FROM reviews WHERE product_id = ?");
    $stmt->execute([$product_id]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return [
        'avg' => $res['avg_rating'] ? round($res['avg_rating'], 1) : 0,
        'count' => $res['count_reviews']
    ];
}

// دالة رسم النجوم الذهبية
function renderStars($rating) {
    $stars_html = '';
    $full_stars = floor($rating);
    $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
    $empty_stars = 5 - $full_stars - $half_star;
    
    for ($i = 0; $i < $full_stars; $i++) {
        $stars_html .= '<i class="fa-solid fa-star text-amber-400"></i>';
    }
    if ($half_star) {
        $stars_html .= '<i class="fa-solid fa-star-half-stroke text-amber-400"></i>';
    }
    for ($i = 0; $i < $empty_stars; $i++) {
        $stars_html .= '<i class="fa-regular fa-star text-gray-300"></i>';
    }
    return $stars_html;
}

// دالة جلب صورة الحوم (Hover Image) للمنتج
function getProductHoverImage($product_id, $default_image) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT image_path FROM product_images WHERE product_id = ? AND is_main = 0 LIMIT 1");
    $stmt->execute([$product_id]);
    $alt_img = $stmt->fetchColumn();
    return $alt_img ? $alt_img : $default_image;
}

// دالة تسجيل وتتبع زيارات العملاء والزوار
function trackVisitor() {
    global $pdo;
    if (!$pdo) return;
    
    $current_script = basename($_SERVER['PHP_SELF']);
    
    // عدم تسجيل زيارات صفحة الإدارة نفسها حتى لا تتلوث البيانات
    if (strpos($current_script, 'admin_') === 0) {
        return;
    }
    
    // منع تكرار التسجيل لنفس الجلسة والصفحة خلال دقيقة واحدة
    $session_key = 'last_visit_' . md5($current_script . ($_SERVER['QUERY_STRING'] ?? ''));
    if (isset($_SESSION[$session_key]) && (time() - $_SESSION[$session_key]) < 60) {
        return;
    }
    $_SESSION[$session_key] = time();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    
    $full_page = $current_script;
    if (!empty($_SERVER['QUERY_STRING'])) {
        $full_page .= '?' . $_SERVER['QUERY_STRING'];
    }
    
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $device = (preg_match('/(android|bb\d+|meego).+mobile|avail|blackberry|emulator|iphone|ipad|ipod|opera mini|mobile/i', $ua)) ? 'جوال' : 'كمبيوتر';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO visitor_logs (ip_address, user_id, page_url, device_type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ip, $user_id, $full_page, $device]);
    } catch (Exception $e) {
        // تجاهل الأخطاء البسيطة
    }
}
?>
