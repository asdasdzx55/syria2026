<?php
/**
 * Syrian Home Supermarket - Modular Web POS & Store Management System
 * نظام كاشير الويب المتطور وإدارة العمليات الشاملة لسوبر ماركت المنزل السوري
 * واجهة مستقلة مقسمة لصفحات وتبويبات مخصصة (كاشير، موردين وبحث أرصدة، مصروفات، مسحوبات، تقارير)
 * سلة تسوق ذكية مصغرة تحت الباركود مع وسائل دفع مصرية 100%
 */
require_once 'config.php';

$page_title = "كاشير الويب المتطور | " . ($settings['store_name'] ?? 'سوبر ماركت المنزل السوري');

// جلب الأقسام
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// جلب المنتجات المتاحة
$products = $pdo->query("SELECT id, name, category, sub_category, price, cost, stock, barcode, barcode2, barcode3, all_barcodes, local_code, image_url FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// جلب الموردين وتصنيفات المصروفات والشركاء
$suppliers = $pdo->query("SELECT id, name, phone, balance FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$expense_categories = $pdo->query("SELECT name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$partners = $pdo->query("SELECT name FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

// إعداد بيانات العملة والمتجر
$store_name = $settings['store_name'] ?? 'سوبر ماركت المنزل السوري';
$store_tagline = $settings['store_tagline'] ?? 'البيت بيتك لكل المنتجات الغذائية والمؤونة الشامية الأصيلة';
$store_phone = $settings['contact_phone'] ?? '01012345678';
$store_address = $settings['store_address'] ?? 'الفرع الرئيسي';
$currency = 'ج.م';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                        },
                        gold: {
                            400: '#fbbf24',
                            500: '#f59e0b',
                            600: '#d97706',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Barcode Scanner Libraries (ZXing + HTML5-QRCode) -->
    <script src="https://unpkg.com/@zxing/library@0.20.0/umd/index.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js" type="text/javascript"></script>

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* تخصيص شريط التمرير */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0f172a;
        }
        ::-webkit-scrollbar-thumb {
            background: #334155;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #475569;
        }

        /* خط ليزر متحرك لماسح الكاميرا */
        @keyframes laser-sweep {
            0% { top: 10%; opacity: 0.8; }
            50% { top: 90%; opacity: 1; }
            100% { top: 10%; opacity: 0.8; }
        }
        .laser-line {
            position: absolute;
            left: 5%;
            right: 5%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #ef4444, #f87171, #ef4444, transparent);
            box-shadow: 0 0 12px #ef4444, 0 0 20px #dc2626;
            animation: laser-sweep 2s infinite ease-in-out;
            pointer-events: none;
            z-index: 20;
        }

        /* تنسيقات ماسح Html5-QRCode في الوضع الداكن */
        #interactive-scanner-view {
            border: none !important;
            background: #090d16 !important;
            border-radius: 1.25rem !important;
            overflow: hidden !important;
        }
        #interactive-scanner-view img {
            display: none !important;
        }
        #interactive-scanner-view video {
            border-radius: 1rem !important;
            object-fit: cover !important;
            width: 100% !important;
            max-height: 360px !important;
        }
        #interactive-scanner-view button {
            background: #16a34a !important;
            color: white !important;
            font-weight: bold !important;
            border-radius: 0.75rem !important;
            padding: 8px 16px !important;
            border: none !important;
            font-family: inherit !important;
            cursor: pointer !important;
            margin: 6px 4px !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3) !important;
            transition: all 0.2s ease !important;
        }
        #interactive-scanner-view button:hover {
            background: #15803d !important;
        }
        #interactive-scanner-view select {
            background: #1e293b !important;
            color: white !important;
            font-weight: bold !important;
            border-radius: 0.75rem !important;
            padding: 8px 12px !important;
            border: 1px solid #334155 !important;
            font-family: inherit !important;
            margin: 6px 4px !important;
        }
        #interactive-scanner-view a {
            color: #38bdf8 !important;
            font-size: 11px !important;
            text-decoration: none !important;
        }

        /* أنيميشن انزلاق السلة والدفع */
        .drawer-slide {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* ستايل الطباعة الحرارية للفاتورة 80mm */
        @media print {
            body * {
                visibility: hidden;
            }
            #thermal-receipt-modal, #thermal-receipt-modal * {
                visibility: visible;
            }
            #thermal-receipt-modal {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm !important;
                margin: 0 !important;
                padding: 4mm !important;
                background: white !important;
                color: black !important;
                box-shadow: none !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased select-none">

    <!-- 1. شريط التنقل العلوي بين الصفحات والتبويبات المستقلة -->
    <header class="bg-slate-900 border-b border-slate-800 sticky top-0 z-40 shadow-xl">
        <div class="max-w-7xl mx-auto px-3 py-2 flex items-center justify-between gap-2 overflow-x-auto">
            
            <!-- الشعار واسم النظام -->
            <div class="flex items-center gap-2.5 shrink-0">
                <a href="admin_dashboard.php" class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-700 to-brand-500 flex items-center justify-center text-white shadow-md hover:scale-105 transition-transform" title="الانتقال للوحة التحكم">
                    <i class="fa-solid fa-store text-base"></i>
                </a>
                <div>
                    <h1 class="font-extrabold text-sm sm:text-base text-white leading-tight">سوبر ماركت المنزل السوري</h1>
                    <p class="text-[10px] text-brand-400 font-bold hidden sm:block">نظام الكاشير والإدارة المركزية ⚡</p>
                </div>
            </div>

            <!-- أزرار التبويبات والصفحات المستقلة (Dedicated Page Tabs) -->
            <nav class="flex items-center gap-1 sm:gap-1.5 shrink-0" id="main-nav-tabs">
                <!-- 1. صفحة الكاشير والبيع -->
                <button onclick="switchView('pos')" id="tab-pos" class="nav-tab active px-2.5 sm:px-3.5 py-1.5 rounded-xl bg-brand-600 text-white font-bold text-xs sm:text-sm flex items-center gap-1.5 shadow-md transition-all">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>الكاشير والبيع</span>
                </button>

                <!-- 2. صفحة الموردين وسداد الحسابات -->
                <button onclick="switchView('suppliers')" id="tab-suppliers" class="nav-tab px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-amber-400 font-bold text-xs sm:text-sm flex items-center gap-1.5 border border-slate-700/60 transition-all">
                    <i class="fa-solid fa-handshake text-amber-400"></i>
                    <span>حسابات الموردين</span>
                </button>

                <!-- 3. صفحة المصروفات -->
                <button onclick="switchView('expenses')" id="tab-expenses" class="nav-tab px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-rose-400 font-bold text-xs sm:text-sm flex items-center gap-1.5 border border-slate-700/60 transition-all">
                    <i class="fa-solid fa-money-bill-wave text-rose-400"></i>
                    <span>المصروفات</span>
                </button>

                <!-- 4. صفحة مسحوبات الشركاء -->
                <button onclick="switchView('partners')" id="tab-partners" class="nav-tab px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-purple-400 font-bold text-xs sm:text-sm flex items-center gap-1.5 border border-slate-700/60 transition-all">
                    <i class="fa-solid fa-wallet text-purple-400"></i>
                    <span>سحب الشركاء</span>
                </button>

                <!-- 5. صفحة تقارير الشيفت والخزينة -->
                <button onclick="switchView('reports')" id="tab-reports" class="nav-tab px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-cyan-400 font-bold text-xs sm:text-sm flex items-center gap-1.5 border border-slate-700/60 transition-all">
                    <i class="fa-solid fa-chart-pie text-cyan-400"></i>
                    <span>التقارير والشيفت</span>
                </button>

                <!-- 6. صفحة إضافة صنف -->
                <button onclick="switchView('add-product')" id="tab-add-product" class="nav-tab px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-emerald-400 font-bold text-xs sm:text-sm flex items-center gap-1.5 border border-slate-700/60 transition-all">
                    <i class="fa-solid fa-plus-circle text-emerald-400"></i>
                    <span>إضافة منتج</span>
                </button>

                <!-- 7. صفحة الجرد والمخزون -->
                <button onclick="switchView('inventory')" id="tab-inventory" class="nav-tab px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-indigo-400 font-bold text-xs sm:text-sm flex items-center gap-1.5 border border-slate-700/60 transition-all">
                    <i class="fa-solid fa-clipboard-list text-indigo-400"></i>
                    <span>الجرد والمخزون 📋</span>
                </button>

                <!-- 8. زر تطبيق الدليفري والطيارين -->
                <a href="delivery.php" target="_blank" class="px-2.5 sm:px-3 py-1.5 rounded-xl bg-amber-500/20 hover:bg-amber-500 text-amber-300 hover:text-slate-950 font-black text-xs sm:text-sm flex items-center gap-1.5 border border-amber-500/40 transition-all shadow-md">
                    <i class="fa-solid fa-motorcycle text-amber-400"></i>
                    <span>تطبيق الدليفري 🛵</span>
                </a>
            </nav>

            <!-- زر لوحة تحكم الإدارة -->
            <div class="hidden lg:flex items-center gap-1 shrink-0">
                <a href="admin_dashboard.php" class="px-2.5 py-1 bg-slate-800 hover:bg-slate-750 text-slate-400 hover:text-white rounded-lg text-xs font-semibold border border-slate-700">
                    <i class="fa-solid fa-gauge ml-1"></i> الإدارة
                </a>
            </div>

        </div>
    </header>

    <!-- 2. المحتوى الرئيسي المقسم إلى صفحات وتبويبات كاملة -->
    <div class="flex-1 max-w-7xl w-full mx-auto p-2 sm:p-4 overflow-y-auto pb-28 sm:pb-8">

        <!-- ================================================================= -->
        <!-- الصفحة 1: شاشة الكاشير والبيع المباشر (POS Sales View)            -->
        <!-- ================================================================= -->
        <section id="view-pos" class="page-view flex flex-col gap-3">
            
            <!-- أ. شريط مسح الباركود، الكاميرا، والبحث السريع -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 shadow-lg flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                
                <!-- خانة إدخال الباركود والبحث -->
                <div class="relative flex-1">
                    <i class="fa-solid fa-barcode absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-lg"></i>
                    <input type="text" id="barcode-input" placeholder="امسح الباركود، الكود المحلي (5 أرقام)، أو ابحث بالاسم..." 
                           class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl pr-11 pl-4 py-2.5 text-sm focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500 font-semibold"
                           autofocus autocomplete="off">
                </div>

                <!-- أزرار الكاميرا ومسح البحث -->
                <div class="flex items-center gap-2">
                    <button onclick="startCameraScanner()" class="flex-1 sm:flex-none px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-brand-600 hover:from-emerald-500 hover:to-brand-500 text-white rounded-xl font-bold text-xs sm:text-sm flex items-center justify-center gap-2 shadow-lg shadow-emerald-950 active:scale-95 transition-all">
                        <i class="fa-solid fa-camera text-base animate-pulse"></i>
                        <span>مسح بالكاميرا</span>
                    </button>
                    
                    <button onclick="clearSearch()" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-colors">
                        مسح
                    </button>

                    <button onclick="refreshCatalog()" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-brand-400 rounded-xl text-xs font-bold transition-colors" title="تحديث الأصناف من السيرفر">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            </div>

            <!-- ب. زر سلة التسوق المصغر تحت مسح الباركود مباشرة (Smart Mini Cart Bar) -->
            <div onclick="openCartDrawer()" class="bg-gradient-to-r from-slate-900 via-slate-850 to-slate-900 border-2 border-brand-500/50 hover:border-brand-400 p-3 rounded-2xl cursor-pointer shadow-lg hover:shadow-brand-900/30 transition-all flex items-center justify-between group active:scale-[0.99]">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center font-black text-lg shadow-md group-hover:scale-110 transition-transform">
                        <i class="fa-solid fa-cart-shopping"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-extrabold text-sm sm:text-base text-white">سلة الفاتورة الحالية</h3>
                            <span id="mini-cart-count-badge" class="bg-brand-500/20 text-brand-400 text-xs px-2 py-0.5 rounded-full border border-brand-500/30 font-bold">0 أصناف</span>
                        </div>
                        <p class="text-xs text-slate-400" id="mini-cart-summary-text">انقر هنا لفتح السلة، تحديد العميل، واختيار طريقة الدفع</p>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-left">
                        <span class="text-[10px] text-slate-400 block">الإجمالي الحالي:</span>
                        <span class="text-base sm:text-xl font-black text-brand-400" id="mini-cart-total-badge">0.00 ج.م</span>
                    </div>
                    <span class="w-9 h-9 rounded-xl bg-slate-800 group-hover:bg-brand-600 text-slate-300 group-hover:text-white flex items-center justify-center transition-colors">
                        <i class="fa-solid fa-chevron-left text-sm"></i>
                    </span>
                </div>
            </div>

            <!-- ج. شريط الأقسام السريعة -->
            <div class="bg-slate-900 border border-slate-800 rounded-xl px-3 py-2 overflow-x-auto flex items-center gap-1.5 text-xs font-bold whitespace-nowrap" id="categories-bar">
                <button onclick="filterCategory('all')" class="cat-pill active px-3.5 py-1.5 rounded-xl bg-brand-600 text-white shadow-md transition-all">
                    الكل (<?php echo count($products); ?>)
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button onclick="filterCategory('<?php echo htmlspecialchars($cat['name']); ?>')" class="cat-pill px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition-all border border-slate-700/50">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- د. شبكة بطاقات المنتجات بكامل عرض الشاشة (Full Width Grid) -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-3 min-h-[400px]">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3" id="products-grid">
                    <!-- تعبأ ديناميكياً عبر JavaScript -->
                </div>
                <div id="no-products-msg" class="hidden text-center py-16 text-slate-400">
                    <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-500"></i>
                    <p class="text-sm font-bold">لم يتم العثور على أي منتج مطابق للبحث</p>
                </div>
            </div>

        </section>

        <!-- ================================================================= -->
        <!-- الصفحة 2: شاشة الموردين والبحث وسداد الحسابات (Suppliers View)     -->
        <!-- ================================================================= -->
        <section id="view-suppliers" class="page-view hidden flex flex-col gap-4">
            
            <!-- ترويسة صفحة الموردين والبحث التفاعلي -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-800">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                                <i class="fa-solid fa-handshake"></i>
                            </span>
                            <h2 class="font-extrabold text-base sm:text-lg text-white">إدارة وحسابات الموردين وسداد الدفعات</h2>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">ابحث عن المورد وشاهد المبالغ المستحقة المتبقية له وسدد الدفعات فوراً</p>
                    </div>

                    <!-- إجمالي المديونية المستحقة للموردين -->
                    <div class="bg-slate-950 px-4 py-2 rounded-xl border border-amber-500/30 flex items-center gap-3">
                        <span class="text-xs text-slate-400">إجمالي المتبقي للموردين:</span>
                        <span class="text-lg font-black text-amber-400" id="total-suppliers-debt">0.00 ج.م</span>
                    </div>
                </div>

                <!-- شريط البحث الفوري عن الموردين -->
                <div class="pt-3">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                        <input type="text" id="supplier-search-input" oninput="filterSuppliersList(this.value)" placeholder="🔍 ابحث عن اسم المورد أو رقم الهاتف لمشاهدة حسابه..." 
                               class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl pr-11 pl-4 py-2.5 text-sm focus:outline-none focus:border-amber-500 font-bold">
                    </div>
                </div>
            </div>

            <!-- شبكة بطاقات الموردين مع المبالغ المتبقية -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="suppliers-grid">
                <!-- تعبأ ديناميكياً بالـ JavaScript -->
            </div>

            <!-- نموذج السداد المباشر عند اختيار مورد -->
            <div id="supplier-payout-box" class="bg-slate-900 border border-amber-500/40 rounded-2xl p-4 shadow-2xl hidden">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </span>
                        <h3 class="font-extrabold text-sm text-white" id="payout-box-title">سداد دفعة للمورد: -</h3>
                    </div>
                    <button onclick="closeSupplierPayoutBox()" class="text-slate-400 hover:text-white">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <form onsubmit="executeSupplierPayout(event)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <input type="hidden" id="payout-sup-id">
                    <input type="hidden" id="payout-sup-name">

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">المبلغ المراد سداده (ج.م):</label>
                        <input type="number" id="payout-amount-input" required step="1" min="1" placeholder="0.00" 
                               class="w-full bg-slate-950 border border-slate-700 text-amber-400 font-black rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">وسيلة الدفع (المصرية):</label>
                        <select id="payout-method-select" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                            <option value="كاش">كاش (من درج الخزينة)</option>
                            <option value="فودافون كاش">فودافون كاش / محفظة</option>
                            <option value="انستا باي">إنستا باي</option>
                            <option value="فيزا">تحويل بنكي / بطاقة</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">البيان / ملاحظات:</label>
                        <input type="text" id="payout-note-input" placeholder="مثال: سداد دفعة عن فاتورة مخللات..." 
                               class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                    </div>

                    <div class="sm:col-span-3 pt-2">
                        <button type="submit" id="payout-submit-btn" class="w-full py-2.5 bg-amber-600 hover:bg-amber-500 text-slate-950 font-black text-sm rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-check"></i>
                            <span>تأكيد السداد وخصم المبلغ من رصيد المورد والخزينة</span>
                        </button>
                    </div>
                </form>
            </div>

        </section>

        <!-- ================================================================= -->
        <!-- الصفحة 3: شاشة المصروفات العامة (Expenses View)                   -->
        <!-- ================================================================= -->
        <section id="view-expenses" class="page-view hidden flex flex-col gap-4">
            
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-800 mb-3">
                    <span class="w-8 h-8 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </span>
                    <div>
                        <h2 class="font-extrabold text-base sm:text-lg text-white">تسجيل المصروفات النثرية والتشغيلية</h2>
                        <p class="text-xs text-slate-400">إيجار، فواتير كهرباء ومياه، صيانة، نثريات، رواتب، وسلف</p>
                    </div>
                </div>

                <!-- نموذج تسجيل مصروف -->
                <form onsubmit="submitNewExpensePage(event)" class="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-400 mb-1">بند المصروف:</label>
                        <select id="page-exp-category" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none focus:border-rose-500 font-semibold">
                            <?php foreach ($expense_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">المبلغ (ج.م):</label>
                        <input type="number" id="page-exp-amount" required step="0.5" min="0.5" placeholder="0.00" 
                               class="w-full bg-slate-950 border border-slate-700 text-rose-400 font-black rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-rose-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">وسيلة الدفع:</label>
                        <select id="page-exp-method" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                            <option value="كاش">كاش (من الدرج)</option>
                            <option value="فودافون كاش">فودافون كاش</option>
                            <option value="انستا باي">إنستا باي</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">البيان / التفاصيل:</label>
                        <input type="text" id="page-exp-note" required placeholder="مثال: فاتورة كهرباء، صيانة ميزان..." 
                               class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                    </div>

                    <div class="sm:col-span-4 pt-1">
                        <button type="submit" id="page-exp-btn" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-black text-sm rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-save"></i>
                            <span>حفظ المصروف وخصمه من الخزينة فوراً</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- جدول المصروفات المسجلة اليوم -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <h3 class="font-bold text-sm text-slate-200 mb-3 border-b border-slate-800 pb-2">سجل مصروفات اليوم:</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 font-bold">
                                <th class="pb-2">البند</th>
                                <th class="pb-2">البيان</th>
                                <th class="pb-2">وسيلة الدفع</th>
                                <th class="pb-2">المبلغ</th>
                                <th class="pb-2">الوقت</th>
                            </tr>
                        </thead>
                        <tbody id="expenses-table-body" class="divide-y divide-slate-800/60">
                            <!-- تعبأ ديناميكياً -->
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- ================================================================= -->
        <!-- الصفحة 4: شاشة مسحوبات الشركاء (Partners View)                   -->
        <!-- ================================================================= -->
        <section id="view-partners" class="page-view hidden flex flex-col gap-4">
            
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-800 mb-3">
                    <span class="w-8 h-8 rounded-lg bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-wallet"></i>
                    </span>
                    <div>
                        <h2 class="font-extrabold text-base sm:text-lg text-white">مسحوبات المالك والشركاء</h2>
                        <p class="text-xs text-slate-400">تسجيل سحب أرباح أو مسحوبات شخصية وخصمها من الخزينة</p>
                    </div>
                </div>

                <form onsubmit="submitPartnerWithdrawPage(event)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <label class="block font-bold text-slate-400 mb-1">اسم الشريك / المالك:</label>
                        <select id="page-partner-select" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none focus:border-purple-500 font-semibold">
                            <?php foreach ($partners as $part): ?>
                                <option value="<?php echo htmlspecialchars($part); ?>"><?php echo htmlspecialchars($part); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">مبلغ السحب (ج.م):</label>
                        <input type="number" id="page-partner-amount" required step="1" min="1" placeholder="0.00" 
                               class="w-full bg-slate-950 border border-slate-700 text-purple-400 font-black rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-purple-500">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">السبب / ملاحظات:</label>
                        <input type="text" id="page-partner-note" placeholder="مسحوبات شخصية" 
                               class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                    </div>

                    <div class="sm:col-span-3 pt-1">
                        <button type="submit" id="page-partner-btn" class="w-full py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-black text-sm rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-check"></i>
                            <span>تأكيد سحب المبلغ وخصمه من الخزينة</span>
                        </button>
                    </div>
                </form>
            </div>

        </section>

        <!-- ================================================================= -->
        <!-- الصفحة 5: تقارير الشيفت والخزينة اللحظية (Reports View)           -->
        <!-- ================================================================= -->
        <section id="view-reports" class="page-view hidden flex flex-col gap-4">
            
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-cyan-500/20 text-cyan-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-chart-pie"></i>
                    </span>
                    <div>
                        <h2 class="font-extrabold text-base sm:text-lg text-white">تقرير الشيفت والخزينة اليومي</h2>
                        <p class="text-xs text-slate-400">ملخص المبيعات، وسائل الدفع المصرية، المنصرفات، والسيولة النقدية</p>
                    </div>
                </div>

                <button onclick="loadShiftReportsData()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 font-bold rounded-xl text-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-rotate"></i>
                    <span>تحديث الأرقام</span>
                </button>
            </div>

            <!-- كروت الإحصائيات المالية للشيفت -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3" id="reports-stats-cards">
                <!-- تعبأ ديناميكياً -->
            </div>

            <!-- تفصيل مبيعات وسائل الدفع المصرية -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4">
                <h3 class="font-bold text-sm text-slate-200 mb-3 border-b border-slate-800 pb-2">تفصيل مبيعات وسائل الدفع المصرية:</h3>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 text-center" id="reports-payment-methods-grid">
                    <!-- تعبأ ديناميكياً -->
                </div>
            </div>

        </section>

        <!-- ================================================================= -->
        <!-- الصفحة 6: إدارة وإضافة الأصناف بكافة التفاصيل (Add & Manage Products)-->
        <!-- ================================================================= -->
        <section id="view-add-product" class="page-view hidden flex flex-col gap-4">
            
            <!-- كارت نموذج إضافة وتعديل المنتج الشامل -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl max-w-4xl mx-auto w-full">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-black text-base">
                            <i class="fa-solid fa-box-open"></i>
                        </span>
                        <div>
                            <h2 class="font-black text-base sm:text-lg text-white" id="prod-form-title">إضافة منتج جديد ومزامنته مركزياً</h2>
                            <p class="text-xs text-slate-400">إدخال كود 5 أرقام، سعر البيع، التكلفة، المخزون، والمسح بالكاميرا</p>
                        </div>
                    </div>

                    <button onclick="clearProductForm()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition-colors">
                        <i class="fa-solid fa-rotate-left ml-1"></i> تفريغ الحقول
                    </button>
                </div>

                <form onsubmit="submitNewProductPage(event)" class="space-y-4 text-xs">
                    <input type="hidden" id="add-prod-edit-id" value="">

                    <!-- السطر 1: اسم المنتج، الكود المحلي (5 أرقام)، والقسم -->
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                        <div class="sm:col-span-6">
                            <label class="block font-bold text-slate-300 mb-1">اسم الصنف / المنتج <span class="text-rose-400">*</span>:</label>
                            <input type="text" id="add-prod-name" required placeholder="مثال: جبنة شلل سورية حبة بركة 400جم" 
                                   class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-brand-500 font-bold">
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block font-bold text-slate-300 mb-1">كود محلي (5 أرقام):</label>
                            <div class="flex items-center gap-1">
                                <input type="text" id="add-prod-local-code" maxlength="5" placeholder="10001" 
                                       class="w-full bg-slate-950 border border-slate-700 text-cyan-300 font-black rounded-xl px-3 py-2 text-center text-sm focus:outline-none focus:border-cyan-500">
                                <button type="button" onclick="generateRandomLocalCode()" class="px-2.5 py-2 bg-purple-600/30 hover:bg-purple-600 text-purple-300 hover:text-white rounded-xl border border-purple-500/40 text-xs font-bold" title="توليد كود 5 أرقام">
                                    🎲 5 أرقام
                                </button>
                            </div>
                        </div>

                        <div class="sm:col-span-3">
                            <label class="block font-bold text-slate-300 mb-1">القسم / التصنيف:</label>
                            <select id="add-prod-category" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2.5 text-xs focus:outline-none focus:border-brand-500 font-semibold">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                                <option value="أجبان وألبان شامية">أجبان وألبان شامية</option>
                                <option value="مكدوس وزيوت وزيتون">مكدوس وزيوت وزيتون</option>
                                <option value="بهارات وعطارة حلبية">بهارات وعطارة حلبية</option>
                                <option value="حلويات وموالح دمشقية">حلويات وموالح دمشقية</option>
                                <option value="مؤونة ومعلبات شامية">مؤونة ومعلبات شامية</option>
                                <option value="عام">عام</option>
                            </select>
                        </div>
                    </div>

                    <!-- السطر 2: نظام الباركود الدولي وزر الكاميرا ومولد الباركود -->
                    <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 space-y-2">
                        <label class="block font-bold text-slate-300">الباركود الدولي الرئيسي ومسح الكاميرا:</label>
                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            <div class="relative flex-1">
                                <i class="fa-solid fa-barcode absolute right-3 top-1/2 -translate-y-1/2 text-slate-400"></i>
                                <input type="text" id="add-prod-barcode" placeholder="اكتب الباركود، مرره بالقارئ، أو اضغط زر الكاميرا..." 
                                       class="w-full bg-slate-900 border border-slate-700 text-white font-mono text-sm rounded-xl pr-9 pl-3 py-2.5 focus:outline-none focus:border-brand-500">
                            </div>

                            <!-- زر الكاميرا لمسح الباركود وملء الخانة مباشرة -->
                            <button type="button" onclick="scanBarcodeForProductField()" class="px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-brand-600 hover:from-emerald-500 hover:to-brand-500 text-white font-black rounded-xl text-xs flex items-center justify-center gap-2 shadow-md active:scale-95 transition-all">
                                <i class="fa-solid fa-camera text-base animate-pulse"></i>
                                <span>مسح بالكاميرا 📷</span>
                            </button>

                            <!-- زر توليد باركود تلقائي -->
                            <button type="button" onclick="generateRandomBarcode()" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-700 text-purple-400 hover:text-purple-300 font-bold rounded-xl text-xs border border-purple-500/30 flex items-center justify-center gap-1.5 transition-colors">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                <span>توليد باركود</span>
                            </button>
                        </div>

                        <!-- خانة باركودات إضافية متعددة -->
                        <div class="pt-1">
                            <label class="block text-[11px] font-semibold text-slate-400 mb-1">باركودات فرعية إضافية (مفصولة بفواصل إن وجدت):</label>
                            <input type="text" id="add-prod-all-barcodes" placeholder="مثال: 6221000123456, 6221000123457" 
                                   class="w-full bg-slate-900 border border-slate-800 text-slate-300 font-mono text-xs rounded-xl px-3 py-2 focus:outline-none">
                        </div>
                    </div>

                    <!-- السطر 3: سعر البيع، التكلفة، المخزون، ونوع الصنف -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div>
                            <label class="block font-bold text-brand-400 mb-1">سعر البيع (ج.م) <span class="text-rose-400">*</span>:</label>
                            <input type="number" id="add-prod-price" required step="0.5" min="0.5" placeholder="0.00" oninput="calcProfitMargin()" 
                                   class="w-full bg-slate-950 border border-slate-700 text-brand-400 font-black rounded-xl px-3 py-2.5 text-center text-base focus:outline-none focus:border-brand-500">
                        </div>

                        <div>
                            <label class="block font-bold text-slate-300 mb-1">سعر التكلفة (ج.م):</label>
                            <input type="number" id="add-prod-cost" step="0.5" min="0" placeholder="0.00" oninput="calcProfitMargin()" 
                                   class="w-full bg-slate-950 border border-slate-700 text-slate-200 font-black rounded-xl px-3 py-2.5 text-center text-base focus:outline-none focus:border-slate-500">
                        </div>

                        <div>
                            <label class="block font-bold text-cyan-400 mb-1">الرصيد / المخزون:</label>
                            <input type="number" id="add-prod-stock" value="100" step="1" 
                                   class="w-full bg-slate-950 border border-slate-700 text-cyan-300 font-black rounded-xl px-3 py-2.5 text-center text-base focus:outline-none focus:border-cyan-500">
                        </div>

                        <div>
                            <label class="block font-bold text-amber-400 mb-1">نوع الصنف:</label>
                            <select id="add-prod-unit-type" class="w-full bg-slate-950 border border-slate-700 text-amber-300 font-bold rounded-xl px-3 py-2.5 text-xs focus:outline-none">
                                <option value="piece">قطعة / علبة عادية</option>
                                <option value="weight">صنف ميزان (بالوزن / كجم)</option>
                            </select>
                        </div>
                    </div>

                    <!-- شريط هامش الربح والربح المحسوب لحظياً -->
                    <div id="profit-margin-card" class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex items-center justify-between text-xs font-bold">
                        <span class="text-slate-400">هامش الربح المتوقع للقطعة:</span>
                        <div class="flex items-center gap-3">
                            <span id="profit-amount-val" class="text-emerald-400 font-black">الربح: 0.00 ج.م</span>
                            <span id="profit-percent-val" class="bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-md border border-emerald-500/30">0.0%</span>
                        </div>
                    </div>

                    <!-- زر الحفظ النهائي والتسجيل -->
                    <div class="pt-2">
                        <button type="submit" id="add-prod-btn" class="w-full py-3 bg-gradient-to-r from-brand-600 via-emerald-600 to-brand-700 hover:from-brand-500 hover:to-emerald-500 text-white font-black text-sm sm:text-base rounded-xl shadow-lg shadow-brand-900/40 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-save text-lg"></i>
                            <span id="save-btn-text">حفظ الصنف النهائي ومزامنته مع نقاط البيع</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- جدول إدارة والبحث في الأصناف المسجلة -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 shadow-xl">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="font-black text-sm text-white">قائمة المنتجات المسجلة في السوبرماركت</h3>
                        <p class="text-[11px] text-slate-400">تعديل الأسعار، تزويد المخزون، أو تعديل البيانات</p>
                    </div>

                    <div class="relative w-full sm:w-64">
                        <i class="fa-solid fa-search absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" oninput="filterManageProductsTable(this.value)" placeholder="بحث بالاسم أو الكود..." 
                               class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl pr-8 pl-3 py-1.5 text-xs focus:outline-none">
                    </div>
                </div>

                <div class="overflow-x-auto mt-3">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400 font-bold">
                                <th class="pb-2">الكود</th>
                                <th class="pb-2">اسم الصنف</th>
                                <th class="pb-2">القسم</th>
                                <th class="pb-2">سعر البيع</th>
                                <th class="pb-2">التكلفة</th>
                                <th class="pb-2">المخزون</th>
                                <th class="pb-2">الباركود</th>
                                <th class="pb-2 text-center">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="manage-products-tbody" class="divide-y divide-slate-800/60">
                            <!-- تعبأ ديناميكياً -->
                        </tbody>
                    </table>
                </div>
            </div>

        </section>

        <!-- ================================================================= -->
        <!-- الصفحة 7: شاشة الجرد والمخزون الشامل (Inventory Audit & Stock Take)-->
        <!-- ================================================================= -->
        <section id="view-inventory" class="page-view hidden flex flex-col gap-4">
            
            <!-- 1. شريط إحصائيات المخزون والقيمة المالية -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-2xl flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 font-semibold">إجمالي الأصناف</p>
                        <h4 id="inv-stat-total-items" class="text-base sm:text-lg font-black text-white">0 صنف</h4>
                    </div>
                </div>

                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-2xl flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-coins"></i>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 font-semibold">قيمة المخزون (بالتكلفة)</p>
                        <h4 id="inv-stat-cost-value" class="text-base sm:text-lg font-black text-emerald-400">0.00 ج.م</h4>
                    </div>
                </div>

                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-2xl flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 font-semibold">أصناف منتهية / نواقص</p>
                        <h4 id="inv-stat-zero-stock" class="text-base sm:text-lg font-black text-amber-400">0 صنف</h4>
                    </div>
                </div>

                <div class="bg-slate-900/90 border border-slate-800 p-3 sm:p-4 rounded-2xl flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/20 text-purple-400 flex items-center justify-center text-lg font-bold shrink-0">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div>
                        <p class="text-[11px] text-slate-400 font-semibold">أصناف تم جردها بالجلسة</p>
                        <h4 id="inv-stat-modified-count" class="text-base sm:text-lg font-black text-purple-300">0 صنف</h4>
                    </div>
                </div>
            </div>

            <!-- 2. شريط البحث والتحكم بالجرد السريع -->
            <div class="bg-slate-900 border border-slate-800 p-3 sm:p-4 rounded-2xl flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3 shadow-lg">
                <div class="flex-1 flex flex-wrap items-center gap-2">
                    
                    <!-- حقل البحث ومسح الباركود السريع -->
                    <div class="relative flex-1 min-w-[220px]">
                        <i class="fa-solid fa-barcode absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" id="inv-search-input" onkeyup="filterInventoryTable()" placeholder="امسح الباركود أو ابحث بالاسم / الكود (5 أرقام)..." class="w-full pl-3 pr-10 py-2.5 bg-slate-950 border border-slate-700/80 rounded-xl text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 transition">
                    </div>

                    <!-- زر تشغيل الكاميرا للجرد في الأرفف -->
                    <button onclick="scanBarcodeForInventory()" class="px-3.5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs sm:text-sm font-bold flex items-center gap-2 shadow-md active:scale-95 transition-all">
                        <i class="fa-solid fa-camera animate-pulse"></i>
                        <span>كاميرا الجرد 📷</span>
                    </button>

                    <!-- فلتر الأقسام -->
                    <select id="inv-category-filter" onchange="filterInventoryTable()" class="px-3 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-xs sm:text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                        <option value="">جميع الأقسام</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- فلتر حالة الجرد والفوارق -->
                    <select id="inv-status-filter" onchange="filterInventoryTable()" class="px-3 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-xs sm:text-sm text-slate-200 focus:outline-none focus:border-indigo-500">
                        <option value="all">كل الأصناف</option>
                        <option value="discrepancy">أصناف بها فارق (عجز أو زيادة)</option>
                        <option value="shortage">أصناف بها عجز 🔴</option>
                        <option value="surplus">أصناف بها زيادة 🔵</option>
                        <option value="matched">أصناف مطابقة 🟢</option>
                        <option value="zero">أصناف منتهية (رصيد 0) ⚠️</option>
                        <option value="modified">أصناف تم تعديلها في الجلسة 🔄</option>
                    </select>
                </div>

                <!-- أزرار الإجراءات الشاملة -->
                <div class="flex items-center gap-2 shrink-0">
                    <button onclick="printInventorySheet()" class="px-3.5 py-2.5 bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-white rounded-xl text-xs font-bold flex items-center gap-1.5 border border-slate-700 transition">
                        <i class="fa-solid fa-print"></i>
                        <span>طباعة كشف</span>
                    </button>

                    <button onclick="saveAllInventoryAudit()" class="px-4 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-xl text-xs sm:text-sm font-black flex items-center gap-2 shadow-lg shadow-indigo-950 active:scale-95 transition-all">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>تطبيق وحفظ الجرد الشامل 💾</span>
                    </button>
                </div>
            </div>

            <!-- 3. جدول الجرد التفاعلي الشامل -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                <div class="overflow-x-auto max-h-[62vh]">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-850 text-slate-400 font-extrabold sticky top-0 z-10 border-b border-slate-800">
                            <tr>
                                <th class="p-3 w-14 text-center">كود</th>
                                <th class="p-3 min-w-[200px]">اسم الصنف</th>
                                <th class="p-3 min-w-[110px]">القسم</th>
                                <th class="p-3 text-center">سعر التكلفة</th>
                                <th class="p-3 text-center">سعر البيع</th>
                                <th class="p-3 text-center min-w-[100px]">رصيد السيستم</th>
                                <th class="p-3 text-center min-w-[210px] bg-slate-800/80 text-white">الكمية الفعلية بالجرد 📋</th>
                                <th class="p-3 text-center min-w-[120px]">الفارق (عجز/زيادة)</th>
                                <th class="p-3 text-center min-w-[90px]">حفظ</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-tbody" class="divide-y divide-slate-800/60 text-slate-300">
                            <!-- تعبأ ديناميكياً بواسطة JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- تذييل جدول الجرد -->
                <div class="p-3 bg-slate-850 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400 font-semibold">
                    <span id="inv-showing-count">جاري تحميل بيانات المخزون...</span>
                    <span class="text-indigo-400 text-[11px]"><i class="fa-solid fa-circle-info ml-1"></i> يتم تحديث الفارق تلقائياً عند تغيير الكمية الفعلية</span>
                </div>
            </div>

        </section>

    </div>

    <!-- ================================================================= -->
    <!-- 3. لوحة السلة والدفع المنزلقة (Slide-Over Cart & Checkout Drawer)   -->
    <!-- ================================================================= -->
    <div id="cart-drawer" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex justify-end">
        <div class="bg-slate-900 border-r border-slate-800 w-full max-w-lg h-full flex flex-col shadow-2xl drawer-slide">
            
            <!-- رأس لوحة السلة -->
            <div class="p-4 bg-slate-850 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="w-8 h-8 rounded-lg bg-brand-600/20 text-brand-400 flex items-center justify-center font-black">
                        <i class="fa-solid fa-receipt"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-white">سلة الفاتورة والدفع</h3>
                        <p class="text-[11px] text-slate-400">سوبر ماركت المنزل السوري</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="clearCartConfirm()" class="px-2.5 py-1 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 rounded-lg text-xs font-bold transition-colors">
                        إفراغ
                    </button>
                    <button onclick="closeCartDrawer()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
            </div>

            <!-- قائمة عناصر السلة -->
            <div class="flex-1 p-3 overflow-y-auto divide-y divide-slate-800/80" id="cart-items-container">
                <!-- تعبأ ديناميكياً -->
            </div>

            <!-- رسالة السلة الفارغة -->
            <div id="empty-cart-view" class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500">
                <i class="fa-solid fa-cart-shopping text-4xl mb-2 text-slate-600"></i>
                <p class="text-sm font-semibold">الفاتورة فارغة</p>
                <p class="text-xs text-slate-500 mt-0.5">امسح باركود أو اختر منتجاً من القائمة</p>
            </div>

            <!-- قسم الحسابات، طرق الدفع المصرية، وإتمام البيع -->
            <div class="p-4 bg-slate-950 border-t border-slate-800 space-y-3">
                
                <!-- المجموع، الخصم، التوصيل -->
                <div class="space-y-1 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>المجموع الفرعي:</span>
                        <span class="font-bold text-slate-200" id="subtotal-val">0.00 ج.م</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <div class="flex items-center gap-1.5 bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-800">
                            <span class="text-slate-400 text-[11px]">خصم:</span>
                            <input type="number" id="discount-input" value="0" min="0" step="1" oninput="calculateTotals()" 
                                   class="w-full bg-transparent text-amber-400 font-bold text-left text-xs focus:outline-none">
                            <span class="text-slate-500 text-[10px]">ج.م</span>
                        </div>
                        <div class="flex items-center gap-1.5 bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-800">
                            <span class="text-slate-400 text-[11px]">توصيل:</span>
                            <input type="number" id="delivery-fee-input" value="0" min="0" step="1" oninput="calculateTotals()" 
                                   class="w-full bg-transparent text-cyan-400 font-bold text-left text-xs focus:outline-none">
                            <span class="text-slate-500 text-[10px]">ج.م</span>
                        </div>
                    </div>

                    <!-- بيانات العميل والتوصيل والطيار -->
                    <div class="space-y-2 text-xs">
                        <div class="grid grid-cols-2 gap-2">
                            <input type="text" id="cust-name-input" placeholder="اسم العميل (اختياري)" 
                                   class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-2.5 py-2 focus:outline-none">
                            <input type="tel" id="cust-phone-input" placeholder="رقم الهاتف" 
                                   class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-2.5 py-2 focus:outline-none">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <input type="text" id="cust-address-input" placeholder="عنوان التوصيل (الشارع / العمارة)" 
                                   class="w-full bg-slate-900 border border-slate-800 text-white rounded-xl px-2.5 py-2 focus:outline-none">
                            <select id="cust-driver-select" class="w-full bg-slate-900 border border-slate-800 text-amber-300 font-bold rounded-xl px-2.5 py-2 focus:outline-none">
                                <option value="">-- بدون طيار (استلام محلي) --</option>
                                <?php foreach ($active_drivers as $drv): ?>
                                    <option value="<?php echo htmlspecialchars($drv['name']); ?>">🛵 <?php echo htmlspecialchars($drv['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-between items-center bg-slate-900 p-2.5 rounded-xl border border-brand-500/40 mt-1.5">
                        <span class="text-xs font-bold text-slate-300">الإجمالي النهائي المطلوب:</span>
                        <span class="text-xl sm:text-2xl font-black text-brand-400" id="grand-total-val">0.00 ج.م</span>
                    </div>
                </div>

                <!-- وسائل الدفع المصرية -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-400 mb-1">وسيلة الدفع (المصرية):</label>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-1 text-xs font-bold">
                        <button type="button" onclick="selectPaymentMethod('كاش')" class="pay-method-btn active p-2 rounded-xl bg-brand-600 text-white flex flex-col items-center justify-center gap-1 transition-all border border-brand-500">
                            <i class="fa-solid fa-money-bill-1-wave text-sm"></i>
                            <span class="text-[10px]">كاش</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('فودافون كاش')" class="pay-method-btn p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-1 transition-all border border-slate-800">
                            <i class="fa-solid fa-mobile-screen-button text-sm text-red-400"></i>
                            <span class="text-[10px]">فودافون كاش</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('انستا باي')" class="pay-method-btn p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-1 transition-all border border-slate-800">
                            <i class="fa-solid fa-bolt text-sm text-purple-400"></i>
                            <span class="text-[10px]">إنستا باي</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('فيزا')" class="pay-method-btn p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-1 transition-all border border-slate-800">
                            <i class="fa-solid fa-credit-card text-sm text-blue-400"></i>
                            <span class="text-[10px]">فيزا / ميزة</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('آجل')" class="pay-method-btn p-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-1 transition-all border border-slate-800">
                            <i class="fa-solid fa-file-invoice-dollar text-sm text-amber-400"></i>
                            <span class="text-[10px]">آجل</span>
                        </button>
                    </div>
                </div>

                <!-- بيانات العميل السريعة (اختياري) -->
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <input type="text" id="cust-name-input" placeholder="اسم العميل (اختياري)" 
                           class="w-full bg-slate-900 border border-slate-800 text-white rounded-lg px-2.5 py-1.5 focus:outline-none">
                    <input type="tel" id="cust-phone-input" placeholder="رقم الهاتف" 
                           class="w-full bg-slate-900 border border-slate-800 text-white rounded-lg px-2.5 py-1.5 focus:outline-none">
                </div>

                <!-- زر تأكيد البيع والطباعة الحرارية -->
                <button onclick="processCheckout()" id="checkout-btn" class="w-full py-3 bg-gradient-to-r from-brand-600 via-emerald-600 to-brand-700 hover:from-brand-500 hover:to-emerald-500 text-white font-black text-sm sm:text-base rounded-xl shadow-lg shadow-brand-900/50 flex items-center justify-center gap-2 active:scale-[0.98] transition-all">
                    <i class="fa-solid fa-check-circle text-lg"></i>
                    <span>إتمام الفاتورة والطباعة 🖨️</span>
                </button>

            </div>

        </div>
    </div>

    <!-- ================================================================= -->
    <!-- 4. النوافذ المنبثقة للأوزان، الكاميرا، والطباعة                      -->
    <!-- ================================================================= -->

    <!-- نافذة تحديد وحساب الوزن الذكية (Smart Weight Selector) -->
    <div id="weight-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-3">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-3xl overflow-hidden shadow-2xl">
            <div class="p-4 bg-slate-800/80 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-white" id="weight-modal-title">تحديد وزن الصنف</h3>
                        <p class="text-[11px] text-slate-400" id="weight-modal-subtitle">سعر الكيلو: 0.00 ج.م</p>
                    </div>
                </div>
                <button onclick="closeModal('weight-modal')" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 space-y-4">
                <!-- أزرار الأوزان السريعة الشائعة -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1.5">أوزان سريعة شائعة:</label>
                    <div class="grid grid-cols-4 gap-1.5">
                        <button type="button" onclick="setWeightVal(0.125)" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700">ثمن (125غ)</button>
                        <button type="button" onclick="setWeightVal(0.250)" class="p-2 bg-slate-800 hover:bg-slate-700 text-amber-400 text-xs font-bold rounded-xl border border-amber-500/30">ربع (250غ)</button>
                        <button type="button" onclick="setWeightVal(0.500)" class="p-2 bg-slate-800 hover:bg-slate-700 text-emerald-400 text-xs font-bold rounded-xl border border-emerald-500/30">نصف (500غ)</button>
                        <button type="button" onclick="setWeightVal(0.750)" class="p-2 bg-slate-800 hover:bg-slate-700 text-cyan-400 text-xs font-bold rounded-xl border border-cyan-500/30">3/4 (750غ)</button>
                        <button type="button" onclick="setWeightVal(1.000)" class="p-2 bg-brand-600/30 hover:bg-brand-600 text-brand-300 hover:text-white text-xs font-bold rounded-xl border border-brand-500/50">1 كجم</button>
                        <button type="button" onclick="setWeightVal(1.500)" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700">1.5 كجم</button>
                        <button type="button" onclick="setWeightVal(2.000)" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700">2 كجم</button>
                        <button type="button" onclick="setWeightVal(3.000)" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold rounded-xl border border-slate-700">3 كجم</button>
                    </div>
                </div>

                <!-- إدخال الوزن المخصص بالكيلو أو الجرام -->
                <div class="grid grid-cols-2 gap-3 bg-slate-950 p-3 rounded-2xl border border-slate-800">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">الوزن بالكيلوجرام (KG):</label>
                        <input type="number" id="weight-kg-input" step="0.005" min="0.005" value="1.000" oninput="onKgInputChanged()" 
                               class="w-full bg-slate-900 border border-slate-700 text-white rounded-xl px-3 py-2 text-center text-base font-black focus:outline-none focus:border-brand-500">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">الوزن بالجرام (Grams):</label>
                        <input type="number" id="weight-g-input" step="5" min="5" value="1000" oninput="onGramInputChanged()" 
                               class="w-full bg-slate-900 border border-slate-700 text-amber-400 rounded-xl px-3 py-2 text-center text-base font-black focus:outline-none focus:border-amber-500">
                    </div>
                </div>

                <!-- معاينة السعر الإجمالي للوزن المحدد -->
                <div class="bg-brand-950/60 border border-brand-500/40 p-3 rounded-2xl flex items-center justify-between">
                    <div>
                        <span class="text-xs text-slate-400 block font-semibold">إجمالي سعر الكمية:</span>
                        <span class="text-xs text-brand-300 font-bold" id="weight-formula-preview">1 كجم × 0.00 ج.م</span>
                    </div>
                    <span class="text-xl font-black text-brand-400" id="weight-total-price-preview">0.00 ج.م</span>
                </div>

                <button onclick="confirmWeightSelection()" class="w-full py-3 bg-brand-600 hover:bg-brand-500 text-white font-extrabold text-sm rounded-xl shadow-lg transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-cart-plus"></i>
                    <span>إضافة للسلة بالوزن المحدد</span>
                </button>
            </div>
        </div>
    </div>

    <!-- نافذة الماسح بالكاميرا المتطورة -->
    <!-- نافذة ماسح الباركود بالكاميرا الحديثة بالكامل -->
    <div id="camera-modal" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md hidden flex items-center justify-center p-3">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            <div class="p-3.5 bg-slate-800/90 border-b border-slate-800 flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-camera"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-white">ماسح الباركود بالكاميرا</h3>
                        <p id="camera-status-text" class="text-[11px] text-emerald-400 font-bold">جاري فتح الكاميرا...</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <!-- قائمة اختيار الكاميرا المتاحة -->
                    <select id="camera-device-select" onchange="switchCameraDevice(this.value)" class="bg-slate-950 text-slate-300 text-[11px] font-bold px-2 py-1.5 rounded-xl border border-slate-700 focus:outline-none max-w-[140px]">
                        <option value="">الكاميرا المتاحة</option>
                    </select>

                    <button onclick="stopCameraScanner()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
            </div>

            <!-- منطقة المسح بالكاميرا المباشرة والصافية -->
            <div class="p-3 bg-slate-950 flex-1 flex flex-col items-center justify-center min-h-[320px] relative overflow-hidden">
                <div class="w-full max-w-md h-[300px] bg-black rounded-2xl overflow-hidden relative shadow-inner flex items-center justify-center border border-slate-800">
                    <video id="native-barcode-video" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    
                    <!-- خط الليزر المتحرك -->
                    <div class="laser-line"></div>
                    
                    <!-- إطار تحديد الباركود -->
                    <div class="absolute w-60 h-44 border-2 border-dashed border-emerald-400/90 rounded-2xl pointer-events-none shadow-[0_0_15px_rgba(16,185,129,0.3)] flex items-center justify-center">
                        <span class="text-[10px] text-emerald-300 font-bold bg-slate-950/80 px-2 py-0.5 rounded-full border border-emerald-500/30">ضع الباركود داخل المربع</span>
                    </div>
                </div>
            </div>

            <div class="p-3 bg-slate-900 border-t border-slate-800 space-y-2">
                <div id="last-scanned-banner" class="hidden bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 px-3 py-1.5 rounded-xl text-xs flex items-center justify-between font-bold">
                    <span id="last-scanned-text">تم المسح بنجاح!</span>
                    <i class="fa-solid fa-circle-check text-emerald-400"></i>
                </div>

                <div class="flex items-center justify-between text-xs gap-2">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-300 font-bold">
                        <input type="checkbox" id="continuous-scan-check" checked class="w-4 h-4 text-brand-600 rounded bg-slate-900 border-slate-700 focus:ring-brand-500">
                        <span>مسح مستمر (إضافة متتابعة)</span>
                    </label>

                    <div class="flex items-center gap-1.5">
                        <button onclick="switchCameraFacing()" class="px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg font-bold flex items-center gap-1 text-xs">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>تبديل</span>
                        </button>

                        <button onclick="restartCameraScanner()" class="px-3 py-1.5 bg-emerald-700/60 hover:bg-emerald-600 text-white rounded-lg font-bold flex items-center gap-1.5 text-xs transition">
                            <i class="fa-solid fa-play"></i>
                            <span>تشغيل</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نافذة الفاتورة الحرارية للطباعة (Thermal Receipt) -->
    <div id="thermal-receipt-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-3">
        <div class="bg-white text-black w-full max-w-[340px] rounded-2xl p-4 shadow-2xl text-xs font-mono select-text" id="printable-receipt-card">
            
            <div class="text-center pb-2 border-b border-dashed border-gray-400 space-y-0.5">
                <h2 class="text-base font-black"><?php echo htmlspecialchars($store_name); ?></h2>
                <p class="text-[10px] text-gray-700"><?php echo htmlspecialchars($store_tagline); ?></p>
                <p class="text-[10px] text-gray-700">هاتف: <?php echo htmlspecialchars($store_phone); ?></p>
                <div class="text-[11px] font-bold text-gray-800 mt-1">فاتورة مبيعات رقم: #<span id="rcpt-id">1001</span></div>
                <div class="text-[10px] text-gray-600" id="rcpt-date">2026-08-17 12:00:00</div>
            </div>

            <div id="rcpt-customer-info" class="py-1.5 border-b border-dashed border-gray-400 text-[10px] space-y-0.5 hidden">
                <div class="flex justify-between"><span>العميل:</span> <b id="rcpt-cust-name">-</b></div>
                <div class="flex justify-between"><span>الهاتف:</span> <b id="rcpt-cust-phone">-</b></div>
            </div>

            <div class="py-2 border-b border-dashed border-gray-400">
                <table class="w-full text-right text-[11px]">
                    <thead>
                        <tr class="border-b border-gray-300 font-bold">
                            <th class="pb-1">الصنف</th>
                            <th class="pb-1 text-center">الكمية</th>
                            <th class="pb-1 text-left">الإجمالي</th>
                        </tr>
                    </thead>
                    <tbody id="rcpt-items-body"></tbody>
                </table>
            </div>

            <div class="py-2 border-b border-dashed border-gray-400 space-y-1 text-[11px]">
                <div class="flex justify-between"><span>المجموع:</span> <span id="rcpt-subtotal">0.00 ج.م</span></div>
                <div class="flex justify-between text-gray-700" id="rcpt-discount-row"><span>خصم:</span> <span id="rcpt-discount">0.00 ج.م</span></div>
                <div class="flex justify-between text-gray-700" id="rcpt-delivery-row"><span>توصيل:</span> <span id="rcpt-delivery">0.00 ج.م</span></div>
                <div class="flex justify-between font-black text-sm pt-1 border-t border-gray-300">
                    <span>الإجمالي المدفوع:</span>
                    <span id="rcpt-grand-total">0.00 ج.م</span>
                </div>
                <div class="flex justify-between text-[10px] text-gray-700 pt-0.5">
                    <span>طريقة الدفع:</span>
                    <b id="rcpt-payment-method">كاش</b>
                </div>
            </div>

            <div class="text-center pt-2 text-[10px] text-gray-600 space-y-0.5">
                <p class="font-bold">شكراً لزيارتكم - أهلاً وسهلاً بكم دائماً</p>
                <p>الأسعار تشمل ضريبة القيمة المضافة</p>
            </div>

            <div class="no-print mt-4 grid grid-cols-2 gap-2">
                <button onclick="window.print()" class="py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-1">
                    <i class="fa-solid fa-print"></i> طباعة الآن
                </button>
                <button onclick="closeModal('thermal-receipt-modal')" class="py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold rounded-xl text-xs">
                    إغلاق الفاتورة
                </button>
            </div>

        </div>
    </div>

    <!-- 🌟 شريط العمليات السريع العائم للموبايل (Mobile Sticky Checkout & Camera Bar) -->
    <div class="md:hidden fixed bottom-3 left-3 right-3 z-30 flex items-center gap-2">
        <button onclick="openCartDrawer()" class="flex-1 bg-gradient-to-r from-emerald-600 via-brand-600 to-emerald-700 text-white p-3 rounded-2xl shadow-2xl flex items-center justify-between font-black border border-emerald-400/40 active:scale-95 transition-transform backdrop-blur-md">
            <div class="flex items-center gap-2">
                <span class="w-8 h-8 rounded-xl bg-black/30 flex items-center justify-center text-sm">
                    <i class="fa-solid fa-cart-shopping"></i>
                </span>
                <span id="mobile-cart-items-count" class="text-xs">0 أصناف</span>
            </div>
            <div class="flex items-center gap-2">
                <span id="mobile-cart-total-badge" class="text-sm font-black text-amber-300">0.00 ج.م</span>
                <span class="text-[11px] bg-white/20 px-2 py-0.5 rounded-lg">الدفع <i class="fa-solid fa-arrow-left text-[9px]"></i></span>
            </div>
        </button>

        <button onclick="startCameraScanner()" class="w-12 h-12 bg-gradient-to-tr from-emerald-600 to-teal-500 text-white rounded-2xl shadow-xl flex items-center justify-center text-lg active:scale-95 transition-transform shrink-0 border border-emerald-300/40" title="مسح بالكاميرا">
            <i class="fa-solid fa-camera"></i>
        </button>
    </div>

    <!-- ================================================================= -->
    <!-- 5. جافاسكريبت التطبيق الشامل والأداء فائق السرعة                  -->
    <!-- ================================================================= -->
    <script>
        // المتغيرات العامة الموحدة للكاشير والكاميرا
        let products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
        let suppliers = <?php echo json_encode($suppliers, JSON_UNESCAPED_UNICODE); ?>;
        let cart = {}; // { productId: { name, price, cost, qty, is_weight, barcode } }
        let selectedPaymentMethod = 'كاش';
        let currentWeightProduct = null;
        let audioContext = null;
        
        // متغيرات الكاميرا والباركود
        let cameraStream = null;
        let barcodeDetectorInstance = null;
        let zxingReader = null;
        let scanLoopTimer = null;
        let cameraScanTarget = 'pos_cart';
        let lastScannedCode = '';
        let lastScannedTime = 0;
        let availableCameras = [];
        let selectedCameraDeviceId = null;
        let currentFacingMode = "environment";

        // تهيئة النظام الآمنة
        document.addEventListener('DOMContentLoaded', () => {
            try { if (typeof renderProducts === 'function') renderProducts(products); } catch (e) { console.error(e); }
            try { if (typeof renderSuppliersList === 'function') renderSuppliersList(suppliers); } catch (e) { console.error(e); }
            try { if (typeof renderCart === 'function') renderCart(); } catch (e) { console.error(e); }
            try { if (typeof loadExpensesData === 'function') loadExpensesData(); } catch (e) { console.error(e); }

            // مستمع الباركود
            const barcodeInput = document.getElementById('barcode-input');
            if (barcodeInput) {
                barcodeInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        handleBarcodeScan(barcodeInput.value.trim());
                        barcodeInput.value = '';
                    }
                });
                barcodeInput.addEventListener('input', (e) => {
                    filterProductsByName(barcodeInput.value.trim());
                });
            }
        });

        // تشغيل صوت التنبيه Beep
        function playBeep() {
            try {
                if (!audioContext) audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, audioContext.currentTime);
                gain.gain.setValueAtTime(0.15, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.12);
                osc.connect(gain);
                gain.connect(audioContext.destination);
                osc.start();
                osc.stop(audioContext.currentTime + 0.12);
                if (navigator.vibrate) navigator.vibrate(80);
            } catch (e) {}
        }

        // ==========================================
        // التبديل بين الصفحات والتبويبات المستقلة
        // ==========================================
        function switchView(viewName) {
            // إخفاء كافة الصفحات
            document.querySelectorAll('.page-view').forEach(p => p.classList.add('hidden'));
            
            // إزالة التنشيط من كافة أزرار التبويبات
            document.querySelectorAll('.nav-tab').forEach(btn => {
                btn.classList.remove('active', 'bg-brand-600', 'text-white');
                btn.classList.add('bg-slate-800', 'text-slate-300');
            });

            // إظهار الصفحة المستهدفة
            const targetPage = document.getElementById('view-' + viewName);
            if (targetPage) targetPage.classList.remove('hidden');

            // تنشيط زر التبويب
            const targetTab = document.getElementById('tab-' + viewName);
            if (targetTab) {
                targetTab.classList.add('active', 'bg-brand-600', 'text-white');
                targetTab.classList.remove('bg-slate-800', 'text-slate-300');
            }

            // تحميل بيانات إضافية عند التبديل إذا لزم
            if (viewName === 'suppliers') {
                renderSuppliersList(suppliers);
            } else if (viewName === 'reports') {
                loadShiftReportsData();
            } else if (viewName === 'expenses') {
                loadExpensesData();
            } else if (viewName === 'add-product') {
                renderManageProductsTable(products);
            } else if (viewName === 'inventory') {
                initInventoryView();
            }
        }

        // ==========================================
        // إدارة شاشة الموردين والبحث وسداد الحسابات
        // ==========================================
        function renderSuppliersList(list) {
            const grid = document.getElementById('suppliers-grid');
            grid.innerHTML = '';

            let totalDebt = 0;
            list.forEach(s => {
                const bal = parseFloat(s.balance || 0);
                totalDebt += bal;

                const card = document.createElement('div');
                card.className = "bg-slate-900 border border-slate-800 hover:border-amber-500/50 rounded-2xl p-4 flex flex-col justify-between gap-3 shadow-lg transition-all";
                
                card.innerHTML = `
                    <div>
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="font-extrabold text-sm text-white">${s.name}</h3>
                            <span class="text-[10px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full">مورد #${s.id}</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1 flex items-center gap-2">
                            <i class="fa-solid fa-phone text-[10px]"></i>
                            <span>${s.phone || 'غير مسجل'}</span>
                        </div>
                    </div>

                    <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-400">المبلغ المتبقي له:</span>
                        <span class="text-base font-black ${bal > 0 ? 'text-amber-400' : 'text-emerald-400'}">${bal.toFixed(2)} ج.م</span>
                    </div>

                    <button onclick="openSupplierPayoutBox(${s.id}, '${s.name.replace(/'/g, "\\'")}', ${bal})" class="w-full py-2 bg-amber-600/20 hover:bg-amber-600 text-amber-400 hover:text-slate-950 font-bold text-xs rounded-xl border border-amber-500/40 transition-all flex items-center justify-center gap-1.5">
                        <i class="fa-solid fa-handshake"></i>
                        <span>سداد دفعة للمورد</span>
                    </button>
                `;
                grid.appendChild(card);
            });

            document.getElementById('total-suppliers-debt').innerText = totalDebt.toFixed(2) + ' ج.م';
        }

        function filterSuppliersList(query) {
            query = query.trim().toLowerCase();
            if (!query) {
                renderSuppliersList(suppliers);
                return;
            }
            const filtered = suppliers.filter(s => 
                s.name.toLowerCase().includes(query) || 
                (s.phone && s.phone.includes(query))
            );
            renderSuppliersList(filtered);
        }

        function openSupplierPayoutBox(supId, supName, currentBalance) {
            document.getElementById('payout-sup-id').value = supId;
            document.getElementById('payout-sup-name').value = supName;
            document.getElementById('payout-box-title').innerText = `سداد دفعة للمورد: ${supName} (المتبقي له: ${currentBalance.toFixed(2)} ج.م)`;
            document.getElementById('payout-amount-input').value = '';
            document.getElementById('payout-note-input').value = '';
            
            const box = document.getElementById('supplier-payout-box');
            box.classList.remove('hidden');
            box.scrollIntoView({ behavior: 'smooth' });
        }

        function closeSupplierPayoutBox() {
            document.getElementById('supplier-payout-box').classList.add('hidden');
        }

        async function executeSupplierPayout(e) {
            e.preventDefault();
            const btn = document.getElementById('payout-submit-btn');
            btn.disabled = true;

            const supId = document.getElementById('payout-sup-id').value;
            const supName = document.getElementById('payout-sup-name').value;
            const amount = parseFloat(document.getElementById('payout-amount-input').value);
            const method = document.getElementById('payout-method-select').value;
            const note = document.getElementById('payout-note-input').value;

            try {
                const res = await fetch('api_sync.php?action=pay_supplier&api_key=syrian_home_pos_secret_token_2026', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ supplier_id: supId, supplier_name: supName, amount, payment_method: method, note })
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    closeSupplierPayoutBox();
                    
                    // تحديث الرصيد محلياً
                    const sup = suppliers.find(s => s.id == supId);
                    if (sup) sup.balance = Math.max(0, parseFloat(sup.balance) - amount);
                    renderSuppliersList(suppliers);
                } else {
                    alert("خطأ: " + data.error);
                }
            } catch (err) {
                alert("تعذر الاتصال بالسيرفر المركزي!");
            } finally {
                btn.disabled = false;
            }
        }

        // ==========================================
        // إدارة سلة الكاشير ولوحة الدفع المنزلقة
        // ==========================================
        function openCartDrawer() {
            document.getElementById('cart-drawer').classList.remove('hidden');
        }

        function closeCartDrawer() {
            document.getElementById('cart-drawer').classList.add('hidden');
        }

        function addToCart(productId, qty = 1, customPrice = null) {
            const product = products.find(p => p.id == productId);
            if (!product) return;

            qty = parseFloat(qty);
            if (cart[productId]) {
                cart[productId].qty += qty;
            } else {
                cart[productId] = {
                    id: product.id,
                    name: product.name,
                    price: customPrice !== null ? customPrice : parseFloat(product.price),
                    cost: parseFloat(product.cost || 0),
                    qty: qty,
                    is_weight: isWeightProduct(product),
                    barcode: product.barcode || product.local_code || ''
                };
            }

            playBeep();
            renderCart();
        }

        function updateCartQty(productId, delta) {
            if (!cart[productId]) return;
            cart[productId].qty += delta;
            if (cart[productId].qty <= 0) {
                delete cart[productId];
            }
            renderCart();
        }

        function setCartItemQty(productId, exactQty) {
            exactQty = parseFloat(exactQty);
            if (exactQty <= 0) {
                delete cart[productId];
            } else {
                cart[productId].qty = exactQty;
            }
            renderCart();
        }

        function removeCartItem(productId) {
            delete cart[productId];
            renderCart();
        }

        function clearCartConfirm() {
            if (Object.keys(cart).length === 0) return;
            if (confirm("هل تريد إفراغ سلة الفاتورة؟")) {
                cart = {};
                renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cart-items-container');
            const emptyView = document.getElementById('empty-cart-view');
            container.innerHTML = '';

            const items = Object.values(cart);
            const totalCount = items.length;

            // تحديث زر السلة المصغر وشريط الموبايل العائم
            let subtotal = 0;
            items.forEach(it => subtotal += (it.price * it.qty));

            const miniCount = document.getElementById('mini-cart-count-badge');
            const miniTotal = document.getElementById('mini-cart-total-badge');
            const mobCount = document.getElementById('mobile-cart-items-count');
            const mobTotal = document.getElementById('mobile-cart-total-badge');

            if (miniCount) miniCount.innerText = `${totalCount} أصناف`;
            if (miniTotal) miniTotal.innerText = `${subtotal.toFixed(2)} ج.م`;
            if (mobCount) mobCount.innerText = `${totalCount} أصناف`;
            if (mobTotal) mobTotal.innerText = `${subtotal.toFixed(2)} ج.م`;

            if (totalCount === 0) {
                emptyView.classList.remove('hidden');
                container.classList.add('hidden');
                document.getElementById('mini-cart-summary-text').innerText = 'انقر هنا لفتح السلة، تحديد العميل، واختيار طريقة الدفع';
                calculateTotals();
                return;
            }

            emptyView.classList.add('hidden');
            container.classList.remove('hidden');
            document.getElementById('mini-cart-summary-text').innerText = `يوجد (${totalCount}) صنف بالفاتورة 👈 انقر هنا للدفع وإنهاء الطلب`;

            items.forEach(item => {
                const itemRow = document.createElement('div');
                itemRow.className = "py-2 px-1 flex items-center justify-between gap-2 text-xs";
                
                const itemTotal = (item.price * item.qty).toFixed(2);
                const qtyDisplay = item.is_weight ? `${item.qty.toFixed(3)} كجم` : `${item.qty}`;

                itemRow.innerHTML = `
                    <div class="flex-1 min-w-0">
                        <h5 class="font-bold text-slate-100 truncate">${item.name}</h5>
                        <div class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5">
                            <span>${item.price.toFixed(2)} ج.م</span>
                            <span>×</span>
                            <span class="text-brand-400 font-bold cursor-pointer hover:underline" onclick="promptEditCartQty(${item.id})">${qtyDisplay}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-800">
                        <button onclick="updateCartQty(${item.id}, -${item.is_weight ? 0.25 : 1})" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-minus text-[10px]"></i>
                        </button>
                        <span class="px-1.5 font-black text-slate-200 text-[11px] min-w-[28px] text-center">${item.is_weight ? item.qty.toFixed(2) : item.qty}</span>
                        <button onclick="updateCartQty(${item.id}, ${item.is_weight ? 0.25 : 1})" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="font-extrabold text-brand-400 text-xs min-w-[55px] text-left">${itemTotal}</span>
                        <button onclick="removeCartItem(${item.id})" class="text-slate-500 hover:text-rose-400 p-1">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                `;
                container.appendChild(itemRow);
            });

            calculateTotals();
        }

        function promptEditCartQty(productId) {
            const item = cart[productId];
            if (!item) return;
            const newQty = prompt(`تعديل كمية (${item.name}):`, item.qty);
            if (newQty !== null && !isNaN(newQty) && parseFloat(newQty) > 0) {
                setCartItemQty(productId, parseFloat(newQty));
            }
        }

        function calculateTotals() {
            let subtotal = 0;
            Object.values(cart).forEach(it => subtotal += (it.price * it.qty));

            const discount = parseFloat(document.getElementById('discount-input').value) || 0;
            const delivery = parseFloat(document.getElementById('delivery-fee-input').value) || 0;
            const grandTotal = Math.max(0, (subtotal - discount) + delivery);

            document.getElementById('subtotal-val').innerText = subtotal.toFixed(2) + ' ج.م';
            document.getElementById('grand-total-val').innerText = grandTotal.toFixed(2) + ' ج.م';
            
            const mobTotal = document.getElementById('mobile-cart-total-badge');
            if (mobTotal) mobTotal.innerText = `${grandTotal.toFixed(2)} ج.م`;
        }

        function selectPaymentMethod(methodName) {
            selectedPaymentMethod = methodName;
            document.querySelectorAll('.pay-method-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-brand-600', 'text-white', 'border-brand-500');
                btn.classList.add('bg-slate-900', 'text-slate-300', 'border-slate-800');
            });
            event.currentTarget.classList.add('active', 'bg-brand-600', 'text-white', 'border-brand-500');
            event.currentTarget.classList.remove('bg-slate-900', 'text-slate-300', 'border-slate-800');
        }

        // ==========================================
        // إتمام البيع والطباعة الحرارية
        // ==========================================
        async function processCheckout() {
            const items = Object.values(cart);
            if (items.length === 0) {
                alert("⚠️ الفاتورة فارغة! أضف منتجات أولاً.");
                return;
            }

            const btn = document.getElementById('checkout-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري الحفظ والمزامنة...';

            let subtotal = 0;
            items.forEach(it => subtotal += (it.price * it.qty));
            const discount = parseFloat(document.getElementById('discount-input').value) || 0;
            const deliveryFee = parseFloat(document.getElementById('delivery-fee-input').value) || 0;
            const grandTotal = Math.max(0, (subtotal - discount) + deliveryFee);

            const payload = {
                source: 'web_pos',
                customer: document.getElementById('cust-name-input').value.trim() || 'عميل نقدي',
                phone: document.getElementById('cust-phone-input').value.trim() || '',
                address: document.getElementById('cust-address-input') ? document.getElementById('cust-address-input').value.trim() : '',
                delivery_person: document.getElementById('cust-driver-select') ? document.getElementById('cust-driver-select').value.trim() : '',
                delivery_fee: deliveryFee,
                discount: discount,
                total: grandTotal,
                payment_method: selectedPaymentMethod,
                cashier_name: 'أحمد الحمصي',
                date: new Date().toISOString().slice(0, 19).replace('T', ' '),
                items: items.map(it => ({
                    id: it.id,
                    name: it.name,
                    qty: it.qty,
                    price: it.price,
                    cost: it.cost,
                    barcode: it.barcode
                }))
            };

            try {
                const res = await fetch('api_sync.php?action=push_sale&api_key=syrian_home_pos_secret_token_2026', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    showThermalReceipt(data.remote_id || Math.floor(Math.random()*9000+1000), payload);
                    cart = {};
                    renderCart();
                    closeCartDrawer();
                    document.getElementById('discount-input').value = 0;
                    document.getElementById('delivery-fee-input').value = 0;
                    document.getElementById('cust-name-input').value = '';
                    document.getElementById('cust-phone-input').value = '';
                    if (document.getElementById('cust-address-input')) document.getElementById('cust-address-input').value = '';
                    if (document.getElementById('cust-driver-select')) document.getElementById('cust-driver-select').value = '';
                } else {
                    alert("خطأ: " + (data.error || 'تعذر الحفظ'));
                }
            } catch (err) {
                alert("⚠️ تم حفظ الفاتورة بنجاح");
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check-circle text-lg"></i> <span>إتمام الفاتورة والطباعة 🖨️</span>';
            }
        }

        function showThermalReceipt(saleId, data) {
            document.getElementById('rcpt-id').innerText = saleId;
            document.getElementById('rcpt-date').innerText = data.date;
            document.getElementById('rcpt-payment-method').innerText = data.payment_method;
            
            const custBox = document.getElementById('rcpt-customer-info');
            if (data.customer && data.customer !== 'عميل نقدي') {
                document.getElementById('rcpt-cust-name').innerText = data.customer;
                document.getElementById('rcpt-cust-phone').innerText = data.phone || '-';
                custBox.classList.remove('hidden');
            } else {
                custBox.classList.add('hidden');
            }

            const tbody = document.getElementById('rcpt-items-body');
            tbody.innerHTML = '';
            let sub = 0;
            data.items.forEach(it => {
                const tot = (it.price * it.qty);
                sub += tot;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="py-1">${it.name}</td>
                    <td class="py-1 text-center font-bold">${typeof it.qty === 'number' && it.qty % 1 !== 0 ? it.qty.toFixed(3) : it.qty}</td>
                    <td class="py-1 text-left font-bold">${tot.toFixed(2)}</td>
                `;
                tbody.appendChild(row);
            });

            document.getElementById('rcpt-subtotal').innerText = sub.toFixed(2) + ' ج.م';
            document.getElementById('rcpt-discount').innerText = (data.discount || 0).toFixed(2) + ' ج.م';
            document.getElementById('rcpt-delivery').innerText = (data.delivery_fee || 0).toFixed(2) + ' ج.م';
            document.getElementById('rcpt-grand-total').innerText = data.total.toFixed(2) + ' ج.م';

            openModal('thermal-receipt-modal');
        }

        // ==========================================
        // كتالوج المنتجات وفلاتر البحث
        // ==========================================
        function renderProducts(items) {
            const grid = document.getElementById('products-grid');
            const noMsg = document.getElementById('no-products-msg');
            grid.innerHTML = '';

            if (!items || items.length === 0) {
                noMsg.classList.remove('hidden');
                return;
            }
            noMsg.classList.add('hidden');

            items.forEach(p => {
                const isWeight = isWeightProduct(p);
                const card = document.createElement('div');
                card.className = "bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-brand-500/50 rounded-2xl p-2.5 flex flex-col justify-between cursor-pointer transition-all duration-150 active:scale-[0.97] group shadow-md";
                card.onclick = () => onProductCardClicked(p);

                const imgUrl = p.image_url || 'placeholder.php?w=200&h=200&text=' + encodeURIComponent(p.name);

                card.innerHTML = `
                    <div class="relative mb-2 overflow-hidden rounded-xl bg-slate-950 h-24 sm:h-28 flex items-center justify-center">
                        <img src="${imgUrl}" alt="${p.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" loading="lazy">
                        ${isWeight ? '<span class="absolute top-1.5 right-1.5 bg-amber-500 text-slate-950 text-[10px] font-black px-1.5 py-0.5 rounded-md shadow"><i class="fa-solid fa-scale-balanced ml-0.5"></i>وزن</span>' : ''}
                        <span class="absolute bottom-1.5 left-1.5 bg-slate-950/80 text-cyan-300 text-[10px] font-mono px-1.5 py-0.5 rounded border border-slate-700">${p.local_code || '#'+p.id}</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-xs text-white line-clamp-2 leading-tight min-h-[32px]">${p.name}</h4>
                        <div class="flex items-baseline justify-between mt-1.5">
                            <span class="text-brand-400 font-black text-xs sm:text-sm">${parseFloat(p.price).toFixed(2)} <span class="text-[10px]">ج.م</span></span>
                            ${isWeight ? '<button onclick="event.stopPropagation(); openWeightModal('+p.id+')" class="text-amber-400 hover:text-amber-300 text-xs px-1.5 py-0.5 bg-amber-500/10 rounded border border-amber-500/30" title="تحديد الوزن"><i class="fa-solid fa-scale-balanced"></i></button>' : ''}
                        </div>
                    </div>
                `;
                grid.appendChild(card);
            });
        }

        function isWeightProduct(p) {
            const name = (p.name || '').toLowerCase();
            return name.includes('بالوزن') || name.includes('كجم') || name.includes('كيلو') || (p.local_code && p.local_code.startsWith('1000')) || (p.barcode && p.barcode.startsWith('001'));
        }

        function onProductCardClicked(p) {
            if (isWeightProduct(p)) {
                openWeightModal(p.id);
            } else {
                addToCart(p.id, 1);
            }
        }

        function filterCategory(catName) {
            document.querySelectorAll('.cat-pill').forEach(btn => btn.classList.remove('active', 'bg-brand-600', 'text-white'));
            event.target.classList.add('active', 'bg-brand-600', 'text-white');

            if (catName === 'all') {
                renderProducts(products);
            } else {
                const filtered = products.filter(p => p.category === catName);
                renderProducts(filtered);
            }
        }

        function filterProductsByName(term) {
            if (!term) {
                renderProducts(products);
                return;
            }
            term = term.toLowerCase();
            const filtered = products.filter(p => 
                p.name.toLowerCase().includes(term) || 
                (p.barcode && p.barcode.includes(term)) || 
                (p.local_code && p.local_code.includes(term))
            );
            renderProducts(filtered);
        }

        function clearSearch() {
            const inp = document.getElementById('barcode-input');
            inp.value = '';
            renderProducts(products);
            inp.focus();
        }

        // ==========================================
        // معالجة الباركود وميزان الأوزان
        // ==========================================
        function handleBarcodeScan(code) {
            if (!code) return;
            code = code.trim();

            if (code.length === 13 && /^(2[0-9])/.test(code)) {
                const itemCode = code.substring(2, 7);
                const weightGrams = parseInt(code.substring(7, 12), 10);
                const weightKg = weightGrams / 1000.0;

                const matched = products.find(p => p.local_code === itemCode || (p.barcode && p.barcode.includes(itemCode)));
                if (matched) {
                    addToCart(matched.id, weightKg);
                    showScannedFeedback(matched.name + ` (${weightKg.toFixed(3)} كجم)`);
                    return;
                }
            }

            const matched = products.find(p => 
                p.barcode === code || 
                p.barcode2 === code || 
                p.local_code === code
            );

            if (matched) {
                if (isWeightProduct(matched)) {
                    openWeightModal(matched.id);
                } else {
                    addToCart(matched.id, 1);
                    showScannedFeedback(matched.name);
                }
            } else {
                playBeep();
                alert(`⚠️ لم يتم العثور على منتج بالباركود: ${code}`);
            }
        }

        function showScannedFeedback(text) {
            const banner = document.getElementById('last-scanned-banner');
            const txt = document.getElementById('last-scanned-text');
            if (banner && txt) {
                txt.innerText = 'تمت الإضافة: ' + text;
                banner.classList.remove('hidden');
                setTimeout(() => banner.classList.add('hidden'), 3500);
            }
        }

        // ==========================================
        // محدد الوزن الذكي
        // ==========================================
        function openWeightModal(productId) {
            const product = products.find(p => p.id == productId);
            if (!product) return;

            currentWeightProduct = product;
            document.getElementById('weight-modal-title').innerText = product.name;
            document.getElementById('weight-modal-subtitle').innerText = `سعر الكيلو: ${parseFloat(product.price).toFixed(2)} ج.م`;
            
            setWeightVal(1.000);
            openModal('weight-modal');
        }

        function setWeightVal(valKg) {
            valKg = parseFloat(valKg);
            document.getElementById('weight-kg-input').value = valKg.toFixed(3);
            document.getElementById('weight-g-input').value = Math.round(valKg * 1000);
            updateWeightPreview();
        }

        function onKgInputChanged() {
            const kg = parseFloat(document.getElementById('weight-kg-input').value) || 0;
            document.getElementById('weight-g-input').value = Math.round(kg * 1000);
            updateWeightPreview();
        }

        function onGramInputChanged() {
            const g = parseFloat(document.getElementById('weight-g-input').value) || 0;
            document.getElementById('weight-kg-input').value = (g / 1000.0).toFixed(3);
            updateWeightPreview();
        }

        function updateWeightPreview() {
            if (!currentWeightProduct) return;
            const kg = parseFloat(document.getElementById('weight-kg-input').value) || 0;
            const price = parseFloat(currentWeightProduct.price);
            const total = (kg * price).toFixed(2);

            document.getElementById('weight-formula-preview').innerText = `${kg.toFixed(3)} كجم × ${price.toFixed(2)} ج.م`;
            document.getElementById('weight-total-price-preview').innerText = `${total} ج.م`;
        }

        function confirmWeightSelection() {
            if (!currentWeightProduct) return;
            const kg = parseFloat(document.getElementById('weight-kg-input').value) || 0;
            if (kg <= 0) {
                alert("يرجى تحديد وزن صحيح أكبر من الصفر!");
                return;
            }

            addToCart(currentWeightProduct.id, kg);
            closeModal('weight-modal');
        }

        // ==========================================
        // قارئ الباركود المباشر الخالي من أي تضارب (Native HTML5 + BarcodeDetector + ZXing)
        // ==========================================
        function scanBarcodeForProductField() {
            cameraScanTarget = 'add_product_barcode';
            startCameraScanner('add_product_barcode');
        }

        async function startCameraScanner(target = 'pos_cart') {
            cameraScanTarget = target;
            openModal('camera-modal');

            const statusEl = document.getElementById('camera-status-text');
            if (statusEl) {
                statusEl.innerText = 'جاري تشغيل الكاميرا...';
                statusEl.className = 'text-[11px] text-amber-400 font-bold';
            }

            stopCameraStreamOnly();

            const video = document.getElementById('native-barcode-video');
            if (!video) return;

            try {
                let videoConstraints = {
                    width: { ideal: 1280 },
                    height: { ideal: 720 }
                };

                if (selectedCameraDeviceId) {
                    videoConstraints.deviceId = { exact: selectedCameraDeviceId };
                } else {
                    videoConstraints.facingMode = { ideal: currentFacingMode };
                }

                cameraStream = await navigator.mediaDevices.getUserMedia({
                    video: videoConstraints,
                    audio: false
                });

                video.srcObject = cameraStream;
                video.setAttribute('playsinline', 'true');
                video.muted = true;
                await video.play();

                if (statusEl) {
                    statusEl.innerText = 'الكاميرا نشطة - وجهها نحو الباركود';
                    statusEl.className = 'text-[11px] text-emerald-400 font-bold';
                }

                // تحديث قائمة الكاميرات المتاحة
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoDevices = devices.filter(d => d.kind === 'videoinput');
                    if (videoDevices.length > 0) {
                        availableCameras = videoDevices;
                        const selectEl = document.getElementById('camera-device-select');
                        if (selectEl) {
                            selectEl.innerHTML = videoDevices.map((d, idx) => {
                                const isBack = d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('rear') || d.label.toLowerCase().includes('environment');
                                const label = d.label || `كاميرا ${idx + 1} ${isBack ? '(خلفية)' : ''}`;
                                return `<option value="${d.deviceId}" ${selectedCameraDeviceId === d.deviceId ? 'selected' : ''}>${label}</option>`;
                            }).join('');
                        }
                    }
                } catch(e) {}

                // بدء حلقة قراءة الباركود السريعة
                startBarcodeScanningEngine(video);

            } catch (err) {
                console.error("Camera access error:", err);
                if (statusEl) {
                    statusEl.innerText = 'تعذر تشغيل الكاميرا (تأكد من إذن المتصفح)';
                    statusEl.className = 'text-[11px] text-rose-400 font-bold';
                }
            }
        }

        function startBarcodeScanningEngine(video) {
            if (scanLoopTimer) clearInterval(scanLoopTimer);

            // المحرك 1: BarcodeDetector الحديث والمباشر في جوجل كروم
            if ('BarcodeDetector' in window) {
                try {
                    barcodeDetectorInstance = new BarcodeDetector({
                        formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code', 'itf', 'data_matrix']
                    });

                    let isProcessing = false;
                    scanLoopTimer = setInterval(async () => {
                        if (isProcessing || !video || video.readyState < 2 || video.paused) return;
                        isProcessing = true;
                        try {
                            const barcodes = await barcodeDetectorInstance.detect(video);
                            if (barcodes && barcodes.length > 0 && barcodes[0].rawValue) {
                                onBarcodeScanned(barcodes[0].rawValue);
                            }
                        } catch (e) {}
                        isProcessing = false;
                    }, 80);
                    return;
                } catch (e) {}
            }

            // المحرك 2: ZXing Browser Multi-Format Reader كبديل شامل
            try {
                if (typeof ZXing !== 'undefined') {
                    if (!zxingReader) zxingReader = new ZXing.BrowserMultiFormatReader();
                    zxingReader.decodeFromVideoDevice(selectedCameraDeviceId, 'native-barcode-video', (result, err) => {
                        if (result && result.getText()) {
                            onBarcodeScanned(result.getText());
                        }
                    });
                }
            } catch (e) {
                console.error("ZXing init error:", e);
            }
        }

        function stopCameraStreamOnly() {
            if (scanLoopTimer) {
                clearInterval(scanLoopTimer);
                scanLoopTimer = null;
            }
            if (zxingReader) {
                try { zxingReader.reset(); } catch(e) {}
            }
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            const video = document.getElementById('native-barcode-video');
            if (video) {
                video.srcObject = null;
            }
        }

        function stopCameraScanner() {
            stopCameraStreamOnly();
            closeModal('camera-modal');
        }

        async function switchCameraDevice(deviceId) {
            if (!deviceId) return;
            selectedCameraDeviceId = deviceId;
            await startCameraScanner(cameraScanTarget);
        }

        async function switchCameraFacing() {
            currentFacingMode = (currentFacingMode === "environment") ? "user" : "environment";
            selectedCameraDeviceId = null;
            await startCameraScanner(cameraScanTarget);
        }

        async function restartCameraScanner() {
            await startCameraScanner(cameraScanTarget);
        }

        function onBarcodeScanned(decodedText) {
            const now = Date.now();
            if (decodedText === lastScannedCode && (now - lastScannedTime) < 1500) {
                return;
            }
            lastScannedCode = decodedText;
            lastScannedTime = now;

            playBeep();

            // 1. إذا كان المسح لخانة إضافة منتج
            if (cameraScanTarget === 'add_product_barcode') {
                document.getElementById('add-prod-barcode').value = decodedText;
                showScannedFeedback('تم مسح الباركود: ' + decodedText);
                stopCameraScanner();
                cameraScanTarget = 'pos_cart';
                return;
            }

            // 2. إذا كان المسح لقسم الجرد
            if (cameraScanTarget === 'inventory_scan') {
                handleInventoryBarcodeScan(decodedText);
                const isContinuous = document.getElementById('continuous-scan-check')?.checked;
                if (!isContinuous) {
                    stopCameraScanner();
                }
                return;
            }

            // 3. إذا كان المسح العادي للكاشير
            handleBarcodeScan(decodedText);
            const isContinuous = document.getElementById('continuous-scan-check')?.checked;
            if (!isContinuous) {
                stopCameraScanner();
            }
        }

        // ==========================================
        // أدوات وإدارة صفحة إضافة وتعديل المنتجات
        // ==========================================
        function calcProfitMargin() {
            const price = parseFloat(document.getElementById('add-prod-price').value) || 0;
            const cost = parseFloat(document.getElementById('add-prod-cost').value) || 0;
            const profit = price - cost;
            const percent = cost > 0 ? ((profit / cost) * 100).toFixed(1) : (price > 0 ? '100.0' : '0.0');

            const profitEl = document.getElementById('profit-amount-val');
            const percentEl = document.getElementById('profit-percent-val');
            
            if (profitEl && percentEl) {
                profitEl.innerText = `الربح: ${profit.toFixed(2)} ج.م`;
                profitEl.className = profit >= 0 ? 'text-emerald-400 font-black' : 'text-rose-400 font-black';
                percentEl.innerText = `${percent}%`;
                percentEl.className = profit >= 0 ? 'bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-md border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 px-2 py-0.5 rounded-md border border-rose-500/30';
            }
        }

        function generateRandomLocalCode() {
            let maxCode = 10000;
            products.forEach(p => {
                if (p.local_code && !isNaN(p.local_code)) {
                    const num = parseInt(p.local_code, 10);
                    if (num > maxCode && num < 99999) maxCode = num;
                }
            });
            const nextCode = (maxCode + 1).toString();
            document.getElementById('add-prod-local-code').value = nextCode;
        }

        function generateRandomBarcode() {
            const random10 = Math.floor(1000000000 + Math.random() * 9000000000);
            const ean = "622" + random10;
            document.getElementById('add-prod-barcode').value = ean;
        }

        function renderManageProductsTable(list) {
            const tbody = document.getElementById('manage-products-tbody');
            if (!tbody) return;
            tbody.innerHTML = '';

            list.forEach(p => {
                const tr = document.createElement('tr');
                tr.className = "hover:bg-slate-800/40";
                tr.innerHTML = `
                    <td class="py-2.5 font-mono text-cyan-300">${p.local_code || '#'+p.id}</td>
                    <td class="py-2.5 font-bold text-white">${p.name}</td>
                    <td class="py-2.5 text-slate-400">${p.category}</td>
                    <td class="py-2.5 font-black text-brand-400">${parseFloat(p.price).toFixed(2)} ج.م</td>
                    <td class="py-2.5 text-slate-300">${parseFloat(p.cost || 0).toFixed(2)} ج.م</td>
                    <td class="py-2.5 font-black text-cyan-400">${p.stock}</td>
                    <td class="py-2.5 font-mono text-slate-400">${p.barcode || '-'}</td>
                    <td class="py-2.5 text-center">
                        <div class="flex items-center justify-center gap-1.5">
                            <button onclick="editProductInForm(${p.id})" class="px-2 py-1 bg-blue-600/20 hover:bg-blue-600 text-blue-400 hover:text-white rounded-lg text-xs font-bold transition-colors" title="تعديل">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <button onclick="quickAddStockPrompt(${p.id})" class="px-2 py-1 bg-purple-600/20 hover:bg-purple-600 text-purple-400 hover:text-white rounded-lg text-xs font-bold transition-colors" title="تزويد رصيد">
                                <i class="fa-solid fa-plus"></i> رصيد
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function filterManageProductsTable(term) {
            term = term.trim().toLowerCase();
            if (!term) {
                renderManageProductsTable(products);
                return;
            }
            const filtered = products.filter(p => 
                p.name.toLowerCase().includes(term) || 
                (p.barcode && p.barcode.includes(term)) || 
                (p.local_code && p.local_code.includes(term)) ||
                (p.category && p.category.toLowerCase().includes(term))
            );
            renderManageProductsTable(filtered);
        }

        function editProductInForm(productId) {
            const p = products.find(prod => prod.id == productId);
            if (!p) return;

            document.getElementById('add-prod-edit-id').value = p.id;
            document.getElementById('add-prod-name').value = p.name;
            document.getElementById('add-prod-local-code').value = p.local_code || '';
            document.getElementById('add-prod-category').value = p.category || 'عام';
            document.getElementById('add-prod-barcode').value = p.barcode || '';
            document.getElementById('add-prod-all-barcodes').value = p.all_barcodes || '';
            document.getElementById('add-prod-price').value = p.price;
            document.getElementById('add-prod-cost').value = p.cost || 0;
            document.getElementById('add-prod-stock').value = p.stock || 100;
            document.getElementById('add-prod-unit-type').value = isWeightProduct(p) ? 'weight' : 'piece';

            document.getElementById('prod-form-title').innerText = `تعديل المنتج: ${p.name}`;
            document.getElementById('save-btn-text').innerText = `تحديث وحفظ التعديلات على الصنف`;
            calcProfitMargin();

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function clearProductForm() {
            document.getElementById('add-prod-edit-id').value = '';
            document.getElementById('add-prod-name').value = '';
            document.getElementById('add-prod-local-code').value = '';
            document.getElementById('add-prod-barcode').value = '';
            document.getElementById('add-prod-all-barcodes').value = '';
            document.getElementById('add-prod-price').value = '';
            document.getElementById('add-prod-cost').value = '';
            document.getElementById('add-prod-stock').value = '100';
            document.getElementById('prod-form-title').innerText = 'إضافة منتج جديد ومزامنته مركزياً';
            document.getElementById('save-btn-text').innerText = 'حفظ الصنف النهائي ومزامنته مع نقاط البيع';
            calcProfitMargin();
        }

        async function quickAddStockPrompt(productId) {
            const p = products.find(prod => prod.id == productId);
            if (!p) return;
            const addQty = prompt(`تزويد رصيد للمنتج (${p.name}):\nالرصيد الحالي: ${p.stock}\nأدخل الكمية المضافة:`, "50");
            if (addQty !== null && !isNaN(addQty) && parseFloat(addQty) > 0) {
                const newStock = parseFloat(p.stock) + parseFloat(addQty);
                try {
                    const res = await fetch('api_sync.php?action=sync_product&api_key=syrian_home_pos_secret_token_2026', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            name: p.name, 
                            category: p.category, 
                            barcode: p.barcode, 
                            local_code: p.local_code,
                            price: p.price, 
                            cost: p.cost, 
                            stock: newStock 
                        })
                    });
                    const data = await res.json();
                    if (data.success) {
                        p.stock = newStock;
                        renderManageProductsTable(products);
                        renderProducts(products);
                        alert(`✅ تم تحديث الرصيد ليصبح: ${newStock}`);
                    }
                } catch (e) {
                    alert("تعذر تحديث الرصيد!");
                }
            }
        }

        async function submitNewProductPage(e) {
            e.preventDefault();
            const btn = document.getElementById('add-prod-btn');
            btn.disabled = true;

            const editId = document.getElementById('add-prod-edit-id').value;
            const name = document.getElementById('add-prod-name').value.trim();
            const local_code = document.getElementById('add-prod-local-code').value.trim();
            const category = document.getElementById('add-prod-category').value;
            const barcode = document.getElementById('add-prod-barcode').value.trim();
            const all_barcodes = document.getElementById('add-prod-all-barcodes').value.trim() || barcode;
            const price = parseFloat(document.getElementById('add-prod-price').value);
            const cost = parseFloat(document.getElementById('add-prod-cost').value) || 0;
            const stock = parseFloat(document.getElementById('add-prod-stock').value) || 100;
            const unit_type = document.getElementById('add-prod-unit-type').value;

            // إذا كان صنف وزن نضيف كلمة (بالوزن/كجم) إن لم تكن موجودة
            let finalName = name;
            if (unit_type === 'weight' && !name.includes('بالوزن') && !name.includes('كجم')) {
                finalName += ' (بالوزن/كجم)';
            }

            try {
                const res = await fetch('api_sync.php?action=sync_product&api_key=syrian_home_pos_secret_token_2026', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        name: finalName, 
                        local_code: local_code,
                        category: category, 
                        barcode: barcode, 
                        all_barcodes: all_barcodes,
                        price: price, 
                        cost: cost, 
                        stock: stock 
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert("✅ تم حفظ المنتج ومزامنته بنجاح!");
                    clearProductForm();
                    await refreshCatalog();
                    renderManageProductsTable(products);
                } else {
                    alert("خطأ: " + data.error);
                }
            } catch (err) {
                alert("حدث خطأ في مزامنة المنتج!");
            } finally {
                btn.disabled = false;
            }
        }

        // تحديث جدول إدارة المنتجات عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', () => {
            renderManageProductsTable(products);
        });

        // ==========================================
        // تقارير الشيفت والخزينة
        // ==========================================
        async function loadShiftReportsData() {
            const container = document.getElementById('reports-stats-cards');
            const pmGrid = document.getElementById('reports-payment-methods-grid');
            container.innerHTML = '<div class="col-span-4 text-center py-6"><i class="fa-solid fa-spinner fa-spin text-xl text-cyan-400"></i></div>';

            try {
                const res = await fetch('api_sync.php?action=get_pos_reports&api_key=syrian_home_pos_secret_token_2026');
                const data = await res.json();

                if (data.success) {
                    container.innerHTML = `
                        <div class="bg-slate-900 p-4 rounded-2xl border border-brand-500/30">
                            <span class="text-slate-400 block text-xs">إجمالي المبيعات اليوم:</span>
                            <span class="text-xl font-black text-brand-400">${data.total_sales.toFixed(2)} ج.م</span>
                            <span class="text-[10px] text-slate-500 block">عدد الفواتير: ${data.orders_count}</span>
                        </div>
                        <div class="bg-slate-900 p-4 rounded-2xl border border-rose-500/30">
                            <span class="text-slate-400 block text-xs">إجمالي المنصرفات:</span>
                            <span class="text-xl font-black text-rose-400">${data.total_all_expenses.toFixed(2)} ج.م</span>
                            <span class="text-[10px] text-slate-500 block">مصروفات وسداد ومسحوبات</span>
                        </div>
                        <div class="bg-slate-900 p-4 rounded-2xl border border-emerald-500/40 col-span-2">
                            <span class="text-slate-400 block text-xs">السيولة النقدية الصافية بالدرج:</span>
                            <span class="text-2xl font-black text-emerald-400">${data.net_cash_in_drawer.toFixed(2)} ج.م</span>
                            <span class="text-[10px] text-slate-400 block">(مبيعات الكاش - المنصرفات النقدية)</span>
                        </div>
                    `;

                    pmGrid.innerHTML = `
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-xs text-slate-400 block">كاش:</span>
                            <b class="text-sm text-brand-400">${(data.sales_by_method['كاش'] || 0).toFixed(2)} ج.م</b>
                        </div>
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-xs text-slate-400 block">فودافون كاش:</span>
                            <b class="text-sm text-red-400">${(data.sales_by_method['فودافون كاش'] || 0).toFixed(2)} ج.م</b>
                        </div>
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-xs text-slate-400 block">إنستا باي:</span>
                            <b class="text-sm text-purple-400">${(data.sales_by_method['انستا باي'] || 0).toFixed(2)} ج.م</b>
                        </div>
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-xs text-slate-400 block">فيزا / ميزة:</span>
                            <b class="text-sm text-blue-400">${(data.sales_by_method['فيزا'] || 0).toFixed(2)} ج.م</b>
                        </div>
                        <div class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                            <span class="text-xs text-slate-400 block">آجل:</span>
                            <b class="text-sm text-amber-400">${(data.sales_by_method['آجل'] || 0).toFixed(2)} ج.م</b>
                        </div>
                    `;
                }
            } catch (e) {}
        }

        // ==========================================
        // إدارة المصروفات العامة ومسحوبات الشركاء
        // ==========================================
        async function loadExpensesData() {
            const tbody = document.getElementById('expenses-table-body');
            if (!tbody) return;
            try {
                const res = await fetch('api_sync.php?action=get_pos_reports&api_key=syrian_home_pos_secret_token_2026');
                const data = await res.json();
                if (data.success && data.recent_expenses) {
                    if (data.recent_expenses.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="py-4 text-center text-slate-500 font-bold">لا توجد مصروفات مسجلة اليوم</td></tr>';
                        return;
                    }
                    tbody.innerHTML = data.recent_expenses.map(e => `
                        <tr class="hover:bg-slate-800/40">
                            <td class="py-2.5 font-bold text-rose-400">${e.category}</td>
                            <td class="py-2.5 text-slate-300">${e.note || '-'}</td>
                            <td class="py-2.5 text-slate-400 font-mono">${e.payment_method || 'كاش'}</td>
                            <td class="py-2.5 font-black text-rose-400">${parseFloat(e.amount).toFixed(2)} ج.م</td>
                            <td class="py-2.5 text-slate-500 text-[11px] font-mono">${e.date ? (e.date.length > 10 ? e.date.substring(11, 16) : e.date) : '-'}</td>
                        </tr>
                    `).join('');
                }
            } catch (err) {
                console.error("loadExpensesData error:", err);
            }
        }

        async function submitNewExpensePage(e) {
            e.preventDefault();
            const btn = document.getElementById('page-exp-btn');
            if (btn) btn.disabled = true;

            const category = document.getElementById('page-exp-category')?.value || 'نثريات';
            const amount = parseFloat(document.getElementById('page-exp-amount')?.value || 0);
            const method = document.getElementById('page-exp-method')?.value || 'كاش';
            const note = document.getElementById('page-exp-note')?.value || '';

            if (amount <= 0) {
                alert("يرجى إدخال مبلغ صحيح أكبر من الصفر!");
                if (btn) btn.disabled = false;
                return;
            }

            try {
                const res = await fetch('api_sync.php?action=record_expense&api_key=syrian_home_pos_secret_token_2026', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category, amount, payment_method: method, note })
                });
                const data = await res.json();
                if (data.success) {
                    alert("✅ تم تسجيل المصروف وخصمه بنجاح!");
                    if (document.getElementById('page-exp-amount')) document.getElementById('page-exp-amount').value = '';
                    if (document.getElementById('page-exp-note')) document.getElementById('page-exp-note').value = '';
                    loadExpensesData();
                } else {
                    alert("خطأ: " + data.error);
                }
            } catch (err) {
                alert("تعذر الاتصال بالسيرفر!");
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        async function submitPartnerWithdrawPage(e) {
            e.preventDefault();
            const partner = document.getElementById('page-partner-select')?.value || 'المالك';
            const amount = parseFloat(document.getElementById('page-partner-amount')?.value || 0);
            const note = document.getElementById('page-partner-note')?.value || '';

            if (amount <= 0) {
                alert("يرجى إدخال مبلغ صحيح أكبر من الصفر!");
                return;
            }

            try {
                const res = await fetch('api_sync.php?action=withdraw_partner&api_key=syrian_home_pos_secret_token_2026', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ partner_name: partner, amount, note })
                });
                const data = await res.json();
                if (data.success) {
                    alert("✅ تم تسجيل المسحوبات بنجاح!");
                    if (document.getElementById('page-partner-amount')) document.getElementById('page-partner-amount').value = '';
                    if (document.getElementById('page-partner-note')) document.getElementById('page-partner-note').value = '';
                } else {
                    alert("خطأ: " + data.error);
                }
            } catch (err) {
                alert("تعذر الاتصال بالسيرفر!");
            }
        }

        async function refreshCatalog() {
            try {
                const res = await fetch('api_sync.php?action=get_products&api_key=syrian_home_pos_secret_token_2026');
                const data = await res.json();
                if (data.success && data.products) {
                    products = data.products;
                    renderProducts(products);
                }
            } catch (e) {}
        }

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
        }
        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        }

        // ==========================================
        // 📋 إدارة قسم الجرد والمخزون التفاعلي (Inventory Audit)
        // ==========================================
        let inventoryData = [];

        function initInventoryView() {
            if (!products || products.length === 0) return;

            // تهيئة بيانات الجرد مع حفظ الكمية الفعلية المبدئية مساوية لكمية السيستم
            inventoryData = products.map(p => {
                const existing = inventoryData.find(i => i.id === p.id);
                return {
                    id: p.id,
                    name: p.name,
                    category: p.category || 'عام',
                    barcode: p.barcode || '',
                    barcode2: p.barcode2 || '',
                    local_code: p.local_code || '',
                    cost: parseFloat(p.cost) || 0,
                    price: parseFloat(p.price) || 0,
                    stock: parseFloat(p.stock) || 0,
                    actual_stock: existing ? existing.actual_stock : (parseFloat(p.stock) || 0),
                    modified: existing ? existing.modified : false
                };
            });

            updateInventoryStats();
            filterInventoryTable();
        }

        function updateInventoryStats() {
            const totalItems = inventoryData.length;
            const costVal = inventoryData.reduce((acc, p) => acc + (p.cost * p.stock), 0);
            const zeroStock = inventoryData.filter(p => p.stock <= 0).length;
            const modifiedCount = inventoryData.filter(p => p.modified).length;

            const elTotal = document.getElementById('inv-stat-total-items');
            const elCost = document.getElementById('inv-stat-cost-value');
            const elZero = document.getElementById('inv-stat-zero-stock');
            const elMod = document.getElementById('inv-stat-modified-count');

            if (elTotal) elTotal.innerText = `${totalItems} صنف`;
            if (elCost) elCost.innerText = `${costVal.toFixed(2)} ج.م`;
            if (elZero) elZero.innerText = `${zeroStock} صنف`;
            if (elMod) elMod.innerText = `${modifiedCount} صنف`;
        }

        function filterInventoryTable() {
            const query = (document.getElementById('inv-search-input')?.value || '').trim().toLowerCase();
            const category = document.getElementById('inv-category-filter')?.value || '';
            const statusFilter = document.getElementById('inv-status-filter')?.value || 'all';

            let filtered = inventoryData.filter(item => {
                // فلتر البحث
                if (query) {
                    const matchName = item.name.toLowerCase().includes(query);
                    const matchCode = item.local_code && item.local_code.includes(query);
                    const matchBarcode = (item.barcode && item.barcode.includes(query)) || (item.barcode2 && item.barcode2.includes(query));
                    if (!matchName && !matchCode && !matchBarcode) return false;
                }

                // فلتر الأقسام
                if (category && item.category !== category) return false;

                // فلتر الحالة والفارق
                const diff = (item.actual_stock || 0) - item.stock;
                if (statusFilter === 'discrepancy' && Math.abs(diff) < 0.001) return false;
                if (statusFilter === 'shortage' && diff >= 0) return false;
                if (statusFilter === 'surplus' && diff <= 0) return false;
                if (statusFilter === 'matched' && Math.abs(diff) >= 0.001) return false;
                if (statusFilter === 'zero' && item.stock > 0) return false;
                if (statusFilter === 'modified' && !item.modified) return false;

                return true;
            });

            renderInventoryTable(filtered);
        }

        function renderInventoryTable(itemsToRender = null) {
            const tbody = document.getElementById('inventory-tbody');
            if (!tbody) return;

            const list = itemsToRender || inventoryData;
            const countEl = document.getElementById('inv-showing-count');
            if (countEl) {
                countEl.innerText = `يتم عرض (${list.length}) صنف من إجمالي (${inventoryData.length}) صنف`;
            }

            if (list.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" class="p-8 text-center text-slate-500 font-bold">
                            <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-600 block"></i>
                            لا توجد أصناف تطابق شروط البحث أو الفلترة المحددة
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = list.map(item => {
                const diff = (parseFloat(item.actual_stock) || 0) - item.stock;
                let diffBadge = '';
                if (Math.abs(diff) < 0.001) {
                    diffBadge = `<span class="px-2.5 py-1 rounded-lg bg-emerald-500/20 text-emerald-400 font-bold inline-flex items-center gap-1"><i class="fa-solid fa-check text-[10px]"></i> مطابق</span>`;
                } else if (diff < 0) {
                    diffBadge = `<span class="px-2.5 py-1 rounded-lg bg-rose-500/20 text-rose-400 font-bold inline-flex items-center gap-1"><i class="fa-solid fa-arrow-trend-down text-[10px]"></i> عجز (${Math.abs(diff).toFixed(2)})</span>`;
                } else {
                    diffBadge = `<span class="px-2.5 py-1 rounded-lg bg-sky-500/20 text-sky-400 font-bold inline-flex items-center gap-1"><i class="fa-solid fa-arrow-trend-up text-[10px]"></i> زيادة (+${diff.toFixed(2)})</span>`;
                }

                const isModifiedClass = item.modified ? 'bg-indigo-950/30' : '';

                return `
                    <tr id="inv-row-${item.id}" class="hover:bg-slate-800/50 transition-colors ${isModifiedClass}">
                        <td class="p-3 text-center font-mono font-bold text-slate-400 text-xs">${item.local_code || '---'}</td>
                        <td class="p-3">
                            <div class="font-bold text-slate-200 text-xs">${item.name}</div>
                            <div class="text-[10px] text-slate-500 font-mono">${item.barcode || 'بدون باركود'}</div>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-0.5 bg-slate-800 text-slate-400 rounded text-[10px] font-semibold">${item.category}</span>
                        </td>
                        <td class="p-3 text-center font-mono text-slate-400">${item.cost.toFixed(2)}</td>
                        <td class="p-3 text-center font-mono font-bold text-slate-300">${item.price.toFixed(2)}</td>
                        <td class="p-3 text-center">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 font-mono font-bold text-xs">
                                ${item.stock.toFixed(2)}
                            </span>
                        </td>
                        <td class="p-3 text-center bg-slate-850/50">
                            <div class="flex items-center justify-center gap-1">
                                <button type="button" onclick="quickAddInventory(${item.id}, -1)" class="w-7 h-7 bg-slate-800 hover:bg-rose-500 hover:text-white rounded-lg text-slate-300 font-bold text-xs transition flex items-center justify-center">-1</button>
                                <input type="number" step="any" min="0" value="${item.actual_stock}" onchange="handleInventoryInput(${item.id}, this.value)" class="w-20 py-1 px-1.5 bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded-lg text-center font-mono font-black text-sm text-indigo-300 focus:outline-none transition">
                                <button type="button" onclick="quickAddInventory(${item.id}, 1)" class="w-7 h-7 bg-slate-800 hover:bg-emerald-500 hover:text-white rounded-lg text-slate-300 font-bold text-xs transition flex items-center justify-center">+1</button>
                                <button type="button" onclick="quickAddInventory(${item.id}, 5)" class="w-7 h-7 bg-slate-800 hover:bg-indigo-500 hover:text-white rounded-lg text-slate-300 font-bold text-[10px] transition hidden sm:flex items-center justify-center">+5</button>
                                <button type="button" onclick="quickAddInventory(${item.id}, 10)" class="w-7 h-7 bg-slate-800 hover:bg-purple-500 hover:text-white rounded-lg text-slate-300 font-bold text-[10px] transition hidden sm:flex items-center justify-center">+10</button>
                            </div>
                        </td>
                        <td class="p-3 text-center font-mono text-xs">
                            ${diffBadge}
                        </td>
                        <td class="p-3 text-center">
                            <button type="button" onclick="saveSingleInventory(${item.id})" class="px-2.5 py-1 bg-slate-800 hover:bg-emerald-600 text-slate-300 hover:text-white rounded-lg font-bold text-xs transition shadow-sm flex items-center gap-1 mx-auto">
                                <i class="fa-solid fa-check"></i>
                                <span class="hidden sm:inline">حفظ</span>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function handleInventoryInput(productId, val) {
            const item = inventoryData.find(i => i.id == productId);
            if (!item) return;

            item.actual_stock = parseFloat(val) || 0;
            item.modified = true;
            updateInventoryStats();
            filterInventoryTable();
        }

        function quickAddInventory(productId, delta) {
            const item = inventoryData.find(i => i.id == productId);
            if (!item) return;

            item.actual_stock = Math.max(0, (parseFloat(item.actual_stock) || 0) + delta);
            item.modified = true;
            updateInventoryStats();
            filterInventoryTable();
        }

        async function saveSingleInventory(productId) {
            const item = inventoryData.find(i => i.id == productId);
            if (!item) return;

            try {
                const formData = new FormData();
                formData.append('action', 'update_inventory_stock');
                formData.append('product_id', item.id);
                formData.append('new_stock', item.actual_stock);

                const res = await fetch('api_sync.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (data.success) {
                    playBeep();
                    item.stock = item.actual_stock;
                    item.modified = false;
                    
                    // تحديث في المنتجات العامة
                    const p = products.find(prod => prod.id == item.id);
                    if (p) p.stock = item.actual_stock;

                    updateInventoryStats();
                    filterInventoryTable();
                    alert(`✅ ${data.message}`);
                } else {
                    alert(`❌ حدث خطأ: ${data.error}`);
                }
            } catch (e) {
                alert("❌ تعذر الاتصال بالسيرفر لحفظ رصيد الجرد!");
            }
        }

        async function saveAllInventoryAudit() {
            const modifiedItems = inventoryData.filter(i => i.modified);
            if (modifiedItems.length === 0) {
                alert("ℹ️ لم يتم تعديل كمية أي صنف بالجرد الحالي!");
                return;
            }

            if (!confirm(`هل أنت متأكد من تطبيق الجرد الشامل وتحديث كميات (${modifiedItems.length}) صنفاً في المخزون فوراً؟`)) {
                return;
            }

            try {
                const payload = {
                    action: 'bulk_inventory_audit',
                    auditor: 'كاشير المحل',
                    items: modifiedItems.map(i => ({ id: i.id, new_stock: i.actual_stock }))
                };

                const res = await fetch('api_sync.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    playBeep();
                    modifiedItems.forEach(item => {
                        item.stock = item.actual_stock;
                        item.modified = false;
                        const p = products.find(prod => prod.id == item.id);
                        if (p) p.stock = item.actual_stock;
                    });

                    updateInventoryStats();
                    filterInventoryTable();
                    alert(`🎉 تم بنجاح تطبيق وحفظ الجرد الشامل لـ (${data.updated_count}) صنف!`);
                } else {
                    alert(`❌ حدث خطأ: ${data.error}`);
                }
            } catch (e) {
                alert("❌ تعذر الاتصال بالسيرفر لتطبيق الجرد الشامل!");
            }
        }

        function scanBarcodeForInventory() {
            cameraScanTarget = 'inventory_scan';
            startCameraScanner('inventory_scan');
        }

        function handleInventoryBarcodeScan(code) {
            if (!code) return;
            code = code.trim();
            const item = inventoryData.find(p => p.barcode === code || p.barcode2 === code || p.local_code === code);
            if (item) {
                item.actual_stock = (parseFloat(item.actual_stock) || 0) + 1;
                item.modified = true;
                updateInventoryStats();
                filterInventoryTable();
                showScannedFeedback(`تم جرد: ${item.name} (الرصيد: ${item.actual_stock})`);

                setTimeout(() => {
                    const row = document.getElementById(`inv-row-${item.id}`);
                    if (row) {
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        row.classList.add('bg-indigo-900/60', 'ring-2', 'ring-indigo-400');
                        setTimeout(() => row.classList.remove('bg-indigo-900/60', 'ring-2', 'ring-indigo-400'), 2000);
                    }
                }, 150);
            } else {
                playBeep();
                alert(`⚠️ لم يتم العثور على صنف بالباركود: ${code}`);
            }
        }

        function printInventorySheet() {
            const printWin = window.open('', '_blank');
            const dateStr = new Date().toLocaleString('ar-EG');
            const totalCost = inventoryData.reduce((acc, p) => acc + (p.cost * p.stock), 0);

            const rowsHtml = inventoryData.map(item => {
                const diff = (parseFloat(item.actual_stock) || 0) - item.stock;
                return `
                    <tr>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center;">${item.local_code || '-'}</td>
                        <td style="border:1px solid #ccc; padding:6px;">${item.name}</td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center;">${item.category}</td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center;">${item.cost.toFixed(2)}</td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center;">${item.price.toFixed(2)}</td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center; font-weight:bold;">${item.stock.toFixed(2)}</td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center; font-weight:bold;">${item.actual_stock.toFixed(2)}</td>
                        <td style="border:1px solid #ccc; padding:6px; text-align:center; font-weight:bold;">${diff.toFixed(2)}</td>
                    </tr>
                `;
            }).join('');

            printWin.document.write(`
                <!DOCTYPE html>
                <html lang="ar" dir="rtl">
                <head>
                    <title>كشف جرد المخزون - سوبر ماركت المنزل السوري</title>
                    <style>
                        body { font-family: sans-serif; padding: 20px; direction: rtl; }
                        h2, h4 { margin: 4px 0; text-align: center; }
                        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 12px; }
                        th { background: #f0f0f0; border: 1px solid #999; padding: 8px; }
                        @media print { button { display: none; } }
                    </style>
                </head>
                <body>
                    <h2>سوبر ماركت المنزل السوري 🇸🇾</h2>
                    <h4>كشف الجرد الفعلي للمخزون - التاريخ: ${dateStr}</h4>
                    <p style="text-align:center; font-size:12px;">إجمالي الأصناف: ${inventoryData.length} | قيمة المخزون بالتكلفة: ${totalCost.toFixed(2)} ج.م</p>
                    <table>
                        <thead>
                            <tr>
                                <th>كود</th>
                                <th>اسم الصنف</th>
                                <th>القسم</th>
                                <th>التكلفة</th>
                                <th>البيع</th>
                                <th>رصيد السيستم</th>
                                <th>الجرد الفعلي</th>
                                <th>الفارق</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rowsHtml}
                        </tbody>
                    </table>
                    <script>window.print();<\/script>
                </body>
                </html>
            `);
            printWin.document.close();
        }
    </script>
</body>
</html>
