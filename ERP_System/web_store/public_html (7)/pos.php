<?php
/**
 * Syrian Home Supermarket - Professional Mobile & Web POS System
 * نظام الكاشير المتطور الشامل وتطبيق الهاتف الذكي لسوبر ماركت المنزل السوري
 * تجربة مستخدم وتصميم مطابق لتطبيقات الهواتف الاحترافية (PWA-like)
 */
require_once 'config.php';

$page_title = "كاشير سوبر ماركت المنزل السوري 🇸🇾 | النظام المتطور";

// 1. جلب البيانات الأساسية من قاعدة البيانات
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("SELECT id, name, category, sub_category, price, cost, stock, barcode, barcode2, barcode3, all_barcodes, local_code, image_url FROM products ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $pdo->query("SELECT id, name, phone, balance FROM suppliers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$expense_categories = $pdo->query("SELECT name FROM expense_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
$partners = $pdo->query("SELECT name FROM partners ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);

// طيارو الدليفري
$active_drivers = [];
try {
    $active_drivers = $pdo->query("SELECT name, phone FROM drivers WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $active_drivers = [
        ['name' => 'طيار 1 - أحمد', 'phone' => '01011111111'],
        ['name' => 'طيار 2 - محمود', 'phone' => '01022222222']
    ];
}

// بيانات المتجر
$store_name = $settings['store_name'] ?? 'سوبر ماركت المنزل السوري';
$store_tagline = $settings['store_tagline'] ?? 'البيت بيتك لكل المنتجات الغذائية والمؤونة الشامية الأصيلة';
$store_phone = $settings['contact_phone'] ?? '01012345678';
$store_address = $settings['store_address'] ?? 'الفرع الرئيسي - مصر';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            400: '#4ade80',
                            500: '#22c55e',
                            600: '#16a34a',
                            700: '#15803d',
                            800: '#166534',
                            900: '#14532d',
                            950: '#052e16',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Google Fonts Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Html5-Qrcode GitHub Standard Barcode & QR Scanner Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
            overscroll-behavior-y: none;
        }
        
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #090d16;
        }
        ::-webkit-scrollbar-thumb {
            background: #1e293b;
            border-radius: 4px;
        }

        @keyframes laser-sweep {
            0% { top: 12%; opacity: 0.8; }
            50% { top: 88%; opacity: 1; }
            100% { top: 12%; opacity: 0.8; }
        }
        .laser-line {
            position: absolute;
            left: 8%;
            right: 8%;
            height: 3px;
            background: linear-gradient(90deg, transparent, #22c55e, #4ade80, #22c55e, transparent);
            box-shadow: 0 0 12px #22c55e, 0 0 20px #16a34a;
            animation: laser-sweep 2s infinite ease-in-out;
            pointer-events: none;
            z-index: 20;
        }

        .drawer-slide {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @media print {
            body * {
                visibility: hidden !important;
            }
            #printable-receipt-card, #printable-receipt-card * {
                visibility: visible !important;
            }
            #printable-receipt-card {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                max-width: 80mm !important;
                padding: 0 !important;
                margin: 0 !important;
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
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col antialiased select-none pb-24 md:pb-6">

    <header class="bg-slate-900/95 backdrop-blur-md border-b border-slate-800 sticky top-0 z-40 shadow-lg">
        <div class="max-w-7xl mx-auto px-3 py-2.5 flex items-center justify-between gap-2">
            
            <div class="flex items-center gap-2.5">
                <a href="admin_dashboard.php" class="w-9 h-9 rounded-xl bg-gradient-to-tr from-brand-700 to-brand-500 flex items-center justify-center text-white shadow-md active:scale-95 transition-transform" title="لوحة التحكم">
                    <i class="fa-solid fa-store text-base"></i>
                </a>
                <div>
                    <h1 class="font-extrabold text-sm sm:text-base text-white leading-tight">سوبر ماركت المنزل السوري 🇸🇾</h1>
                    <p class="text-[10px] text-brand-400 font-bold hidden sm:block">نظام الكاشير المتنقل وإدارة المخزون الفورية</p>
                </div>
            </div>

            <nav class="hidden md:flex items-center gap-1.5" id="desktop-nav-tabs">
                <button onclick="switchView('pos')" id="dtab-pos" class="dnav-tab px-3.5 py-1.5 rounded-xl bg-brand-600 text-white font-bold text-xs flex items-center gap-1.5 shadow-md">
                    <i class="fa-solid fa-cash-register"></i>
                    <span>الكاشير والبيع</span>
                </button>
                <button onclick="switchView('inventory')" id="dtab-inventory" class="dnav-tab px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-indigo-400 font-bold text-xs flex items-center gap-1.5 border border-slate-700/60 transition">
                    <i class="fa-solid fa-clipboard-list text-indigo-400"></i>
                    <span>الجرد والمخزون</span>
                </button>
                <button onclick="switchView('suppliers')" id="dtab-suppliers" class="dnav-tab px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-amber-400 font-bold text-xs flex items-center gap-1.5 border border-slate-700/60 transition">
                    <i class="fa-solid fa-handshake text-amber-400"></i>
                    <span>الموردين</span>
                </button>
                <button onclick="switchView('expenses')" id="dtab-expenses" class="dnav-tab px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-rose-400 font-bold text-xs flex items-center gap-1.5 border border-slate-700/60 transition">
                    <i class="fa-solid fa-money-bill-wave text-rose-400"></i>
                    <span>المصروفات</span>
                </button>
                <button onclick="switchView('reports')" id="dtab-reports" class="dnav-tab px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-cyan-400 font-bold text-xs flex items-center gap-1.5 border border-slate-700/60 transition">
                    <i class="fa-solid fa-chart-pie text-cyan-400"></i>
                    <span>التقارير</span>
                </button>
                <button onclick="switchView('add-product')" id="dtab-add-product" class="dnav-tab px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 text-slate-300 hover:text-emerald-400 font-bold text-xs flex items-center gap-1.5 border border-slate-700/60 transition">
                    <i class="fa-solid fa-plus-circle text-emerald-400"></i>
                    <span>إضافة صنف</span>
                </button>
            </nav>

            <button onclick="openCartDrawer()" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 text-slate-200 font-bold text-xs flex items-center gap-2 shadow-sm active:scale-95 transition">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                <i class="fa-solid fa-cart-shopping text-brand-400"></i>
                <span id="header-cart-total">0.00 ج.م</span>
            </button>

        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto p-2.5 sm:p-4 overflow-y-auto">

        <!-- أ. شاشة الكاشير والبيع -->
        <section id="view-pos" class="app-page-view flex flex-col gap-3">
            
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-2.5 sm:p-3 shadow-md flex items-center gap-2">
                <div class="relative flex-1">
                    <i class="fa-solid fa-barcode absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                    <input type="text" id="barcode-input" placeholder="امسح الباركود، كود الـ 5 أرقام، أو ابحث بالاسم..." 
                           class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl pr-10 pl-3 py-2.5 text-xs sm:text-sm focus:outline-none focus:border-brand-500 font-semibold"
                           autofocus autocomplete="off">
                </div>

                <button onclick="startCameraScanner()" class="px-3.5 sm:px-4 py-2.5 bg-gradient-to-r from-emerald-600 to-brand-600 hover:from-emerald-500 hover:to-brand-500 text-white rounded-xl font-bold text-xs sm:text-sm flex items-center justify-center gap-1.5 shadow-lg shadow-emerald-950/50 active:scale-95 transition shrink-0">
                    <i class="fa-solid fa-camera text-base"></i>
                    <span class="hidden sm:inline">الكاميرا</span>
                </button>

                <button onclick="clearSearch()" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-750 text-slate-300 rounded-xl text-xs font-bold transition shrink-0">
                    مسح
                </button>

                <button onclick="refreshCatalog()" class="px-3 py-2.5 bg-slate-800 hover:bg-slate-750 text-brand-400 rounded-xl text-xs font-bold transition shrink-0" title="تحديث المنتجات">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-xl px-2.5 py-2 overflow-x-auto flex items-center gap-1.5 text-xs font-bold whitespace-nowrap shadow-inner" id="categories-bar">
                <button onclick="filterCategory('all')" class="cat-pill active px-3 py-1.5 rounded-xl bg-brand-600 text-white shadow-md transition">
                    الكل (<?php echo count($products); ?>)
                </button>
                <?php foreach ($categories as $cat): ?>
                    <button onclick="filterCategory('<?php echo htmlspecialchars($cat['name']); ?>')" class="cat-pill px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 hover:bg-slate-700 hover:text-white transition border border-slate-700/50">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="bg-slate-900/50 border border-slate-800/80 rounded-2xl p-2.5 sm:p-3 min-h-[360px]">
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2.5 sm:gap-3" id="products-grid"></div>
                <div id="no-products-msg" class="hidden text-center py-16 text-slate-400">
                    <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-600 block"></i>
                    <p class="text-sm font-bold">لم يتم العثور على أي منتج مطابق للبحث</p>
                </div>
            </div>

        </section>

        <!-- ب. شاشة الجرد والمخزون -->
        <section id="view-inventory" class="app-page-view hidden flex flex-col gap-3">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xl">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-indigo-500/20 text-indigo-400 flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </span>
                        <div>
                            <h2 class="font-extrabold text-base text-white">جرد المخزون الفعلي ومطابقة الأرصدة</h2>
                            <p class="text-xs text-slate-400">إدخال الجرد الفعلي للمحل ومقارنته بالرصيد المسجل</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button onclick="scanBarcodeForInventory()" class="flex-1 sm:flex-none px-3.5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 shadow-md active:scale-95 transition">
                            <i class="fa-solid fa-camera"></i>
                            <span>مسح بالجرد</span>
                        </button>
                        <button onclick="saveAllInventoryAudit()" class="flex-1 sm:flex-none px-4 py-2 bg-gradient-to-r from-emerald-600 to-brand-600 text-white rounded-xl text-xs font-black flex items-center justify-center gap-1.5 shadow-lg active:scale-95 transition">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span>حفظ الجرد الشامل</span>
                        </button>
                        <button onclick="printInventorySheet()" class="px-3 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-bold transition" title="طباعة كشف الجرد">
                            <i class="fa-solid fa-print"></i>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-3">
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[11px] text-slate-400 block">إجمالي الأصناف:</span>
                        <b class="text-sm sm:text-base text-indigo-400" id="inv-stat-total-items"><?php echo count($products); ?> صنف</b>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[11px] text-slate-400 block">قيمة المخزون (بالتكلفة):</span>
                        <b class="text-sm sm:text-base text-emerald-400" id="inv-stat-cost-value">0.00 ج.م</b>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[11px] text-slate-400 block">أصناف نفدت (رصيد 0):</span>
                        <b class="text-sm sm:text-base text-rose-400" id="inv-stat-zero-stock">0 صنف</b>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 text-center">
                        <span class="text-[11px] text-slate-400 block">أصناف تم تعديلها:</span>
                        <b class="text-sm sm:text-base text-amber-400" id="inv-stat-modified-count">0 صنف</b>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 pt-3">
                    <input type="text" id="inv-search-input" oninput="filterInventoryTable()" placeholder="ابحث باسم الصنف أو الباركود..." class="bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-indigo-500">
                    <select id="inv-category-filter" onchange="filterInventoryTable()" class="bg-slate-950 border border-slate-700 text-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none">
                        <option value="">جميع الأقسام</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo htmlspecialchars($c['name']); ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="inv-status-filter" onchange="filterInventoryTable()" class="bg-slate-950 border border-slate-700 text-slate-300 rounded-xl px-3 py-2 text-xs focus:outline-none">
                        <option value="all">كل الحالات</option>
                        <option value="discrepancy">أصناف بها فارغ (عجز أو زيادة)</option>
                        <option value="shortage">أصناف بها عجز فقط</option>
                        <option value="surplus">أصناف بها زيادة فقط</option>
                        <option value="modified">الأصناف المعدلة فقط</option>
                    </select>
                </div>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-850 border-b border-slate-800 text-slate-400 font-bold">
                            <tr>
                                <th class="p-2.5 text-center">كود</th>
                                <th class="p-2.5">اسم الصنف</th>
                                <th class="p-2.5 text-center">القسم</th>
                                <th class="p-2.5 text-center">السيستم</th>
                                <th class="p-2.5 text-center">الجرد الفعلي</th>
                                <th class="p-2.5 text-center">الفارق</th>
                                <th class="p-2.5 text-center">إجراء</th>
                            </tr>
                        </thead>
                        <tbody id="inventory-tbody" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ج. شاشة الموردين -->
        <section id="view-suppliers" class="app-page-view hidden flex flex-col gap-3">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-handshake"></i>
                        </span>
                        <div>
                            <h2 class="font-extrabold text-base text-white">إدارة حسابات وسداد الموردين</h2>
                            <p class="text-xs text-slate-400">سداد الدفعات النقدية ومتابعة المديونيات</p>
                        </div>
                    </div>
                    <div class="text-left bg-slate-950 px-3 py-1.5 rounded-xl border border-slate-800">
                        <span class="text-[10px] text-slate-400 block">إجمالي الديون:</span>
                        <b class="text-amber-400 font-mono text-sm" id="total-suppliers-debt">0.00 ج.م</b>
                    </div>
                </div>

                <div class="pt-3">
                    <input type="text" oninput="filterSuppliersList(this.value)" placeholder="ابحث باسم المورد أو رقم الهاتف..." class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-amber-500">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3" id="suppliers-grid"></div>
        </section>

        <!-- د. شاشة المصروفات -->
        <section id="view-expenses" class="app-page-view hidden flex flex-col gap-3">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xl">
                <div class="flex items-center gap-2 pb-3 border-b border-slate-800 mb-3">
                    <span class="w-9 h-9 rounded-xl bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold text-lg">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </span>
                    <div>
                        <h2 class="font-extrabold text-base text-white">تسجيل المصروفات النثرية والتشغيلية</h2>
                        <p class="text-xs text-slate-400">إيجار، كهرباء، صيانة، نثريات، وسلف العاملين</p>
                    </div>
                </div>

                <form onsubmit="submitNewExpensePage(event)" class="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                    <div>
                        <label class="block font-bold text-slate-400 mb-1">بند المصروف:</label>
                        <select id="page-exp-category" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none focus:border-rose-500">
                            <?php foreach ($expense_categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">المبلغ (ج.م):</label>
                        <input type="number" id="page-exp-amount" required step="0.5" min="0.5" placeholder="0.00" class="w-full bg-slate-950 border border-slate-700 text-rose-400 font-black rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-rose-500">
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
                        <input type="text" id="page-exp-note" required placeholder="مثال: فاتورة كهرباء..." class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                    </div>

                    <div class="sm:col-span-4 pt-1">
                        <button type="submit" id="page-exp-btn" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-black text-xs sm:text-sm rounded-xl shadow-lg active:scale-95 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-save"></i>
                            <span>حفظ المصروف وخصمه من الخزينة فوراً</span>
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3">
                <h3 class="font-bold text-xs text-slate-300 mb-2 pb-2 border-b border-slate-800">مصروفات اليوم المسجلة:</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="border-b border-slate-800 text-slate-400">
                                <th class="pb-2">البند</th>
                                <th class="pb-2">البيان</th>
                                <th class="pb-2">الوسيلة</th>
                                <th class="pb-2">المبلغ</th>
                                <th class="pb-2">الوقت</th>
                            </tr>
                        </thead>
                        <tbody id="expenses-table-body" class="divide-y divide-slate-800/60"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- هـ. شاشة التقارير -->
        <section id="view-reports" class="app-page-view hidden flex flex-col gap-3">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-cyan-500/20 text-cyan-400 flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-chart-pie"></i>
                        </span>
                        <div>
                            <h2 class="font-extrabold text-base text-white">تقرير الشيفت وحركة الخزينة اليومية</h2>
                            <p class="text-xs text-slate-400">إجمالي المبيعات، المصروفات، والسيولة النقدية</p>
                        </div>
                    </div>
                    <button onclick="loadShiftReportsData()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-cyan-400 font-bold rounded-xl text-xs flex items-center gap-1.5 transition">
                        <i class="fa-solid fa-rotate"></i>
                        <span>تحديث</span>
                    </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-3" id="reports-stats-cards"></div>

                <div class="pt-4">
                    <h4 class="font-bold text-xs text-slate-300 mb-2">المبيعات حسب وسيلة الدفع:</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2" id="reports-payment-methods-grid"></div>
                </div>
            </div>
        </section>

        <!-- و. شاشة إضافة صنف -->
        <section id="view-add-product" class="app-page-view hidden flex flex-col gap-3">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-3 sm:p-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold text-lg">
                            <i class="fa-solid fa-plus-circle"></i>
                        </span>
                        <div>
                            <h2 class="font-extrabold text-base text-white" id="prod-form-title">إضافة صنف جديد للمتجر</h2>
                            <p class="text-xs text-slate-400">مزامنة فورية مع نظام الكاشير المكتبي والويب</p>
                        </div>
                    </div>
                    <button onclick="clearProductForm()" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-750 text-slate-300 rounded-xl text-xs font-bold transition">
                        تفريغ الخانات
                    </button>
                </div>

                <form onsubmit="submitNewProductPage(event)" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <input type="hidden" id="add-prod-edit-id" value="">

                    <div class="sm:col-span-2">
                        <label class="block font-bold text-slate-400 mb-1">اسم الصنف:</label>
                        <input type="text" id="add-prod-name" required placeholder="مثال: جبنة حلوم سوري ممتاز..." class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none focus:border-brand-500 font-bold">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">القسم:</label>
                        <select id="add-prod-category" required class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 focus:outline-none">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">الكود المحلي (5 أرقام):</label>
                        <div class="flex gap-1">
                            <input type="text" id="add-prod-local-code" placeholder="10001" class="w-full bg-slate-950 border border-slate-700 text-cyan-300 font-mono font-bold rounded-xl px-3 py-2 focus:outline-none">
                            <button type="button" onclick="generateRandomLocalCode()" class="px-2.5 py-2 bg-purple-600/30 hover:bg-purple-600 text-purple-300 hover:text-white rounded-xl border border-purple-500/40 text-xs font-bold shrink-0">توليد</button>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">الباركود:</label>
                        <div class="flex gap-1">
                            <input type="text" id="add-prod-barcode" placeholder="امسح أو أدخل الباركود..." class="w-full bg-slate-950 border border-slate-700 text-white font-mono rounded-xl px-3 py-2 focus:outline-none">
                            <button type="button" onclick="scanBarcodeForProductField()" class="px-3 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shrink-0"><i class="fa-solid fa-camera"></i></button>
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">نوع الصنف (تسعير بالوزن / قطعة):</label>
                        <select id="add-prod-unit-type" class="w-full bg-slate-950 border border-slate-700 text-amber-400 font-bold rounded-xl px-3 py-2 focus:outline-none">
                            <option value="piece">بالقطعة / علبة</option>
                            <option value="weight">بالوزن (كجم / جرام)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">سعر البيع (ج.م):</label>
                        <input type="number" id="add-prod-price" required step="0.5" min="0.5" placeholder="0.00" oninput="calcProfitMargin()" class="w-full bg-slate-950 border border-slate-700 text-brand-400 font-black rounded-xl px-3 py-2 text-sm focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">سعر التكلفة (ج.م):</label>
                        <input type="number" id="add-prod-cost" step="0.5" min="0" placeholder="0.00" oninput="calcProfitMargin()" class="w-full bg-slate-950 border border-slate-700 text-slate-300 font-bold rounded-xl px-3 py-2 text-sm focus:outline-none">
                    </div>

                    <div>
                        <label class="block font-bold text-slate-400 mb-1">الرصيد المبدئي:</label>
                        <input type="number" id="add-prod-stock" value="100" class="w-full bg-slate-950 border border-slate-700 text-white rounded-xl px-3 py-2 text-sm focus:outline-none">
                    </div>

                    <div class="sm:col-span-3 pt-1">
                        <button type="submit" id="add-prod-btn" class="w-full py-2.5 bg-gradient-to-r from-emerald-600 to-brand-600 hover:from-emerald-500 hover:to-brand-500 text-white font-black text-xs sm:text-sm rounded-xl shadow-lg active:scale-95 transition flex items-center justify-center gap-2">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span id="save-btn-text">حفظ الصنف ومزامنته فوراً</span>
                        </button>
                    </div>
                </form>
            </div>
        </section>

    </main>

    <!-- شريط التنقل السفلي للموبايل -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-slate-900/95 backdrop-blur-lg border-t border-slate-800 px-2 py-1.5 flex items-center justify-around text-[10px] font-bold shadow-2xl">
        <button onclick="switchView('pos')" id="mtab-pos" class="mnav-tab flex flex-col items-center gap-0.5 text-brand-400 py-1 px-2.5 rounded-xl">
            <i class="fa-solid fa-cash-register text-base"></i>
            <span>الكاشير</span>
        </button>

        <button onclick="switchView('inventory')" id="mtab-inventory" class="mnav-tab flex flex-col items-center gap-0.5 text-slate-400 py-1 px-2.5 rounded-xl">
            <i class="fa-solid fa-clipboard-list text-base"></i>
            <span>الجرد</span>
        </button>

        <button onclick="startCameraScanner()" class="w-12 h-12 -mt-5 rounded-2xl bg-gradient-to-tr from-emerald-600 to-brand-500 text-white flex items-center justify-center text-xl shadow-lg shadow-emerald-950 active:scale-90 transition-transform border-2 border-slate-900" title="ماسح الباركود بالكاميرا">
            <i class="fa-solid fa-camera"></i>
        </button>

        <button onclick="switchView('suppliers')" id="mtab-suppliers" class="mnav-tab flex flex-col items-center gap-0.5 text-slate-400 py-1 px-2.5 rounded-xl">
            <i class="fa-solid fa-handshake text-base"></i>
            <span>الموردين</span>
        </button>

        <button onclick="switchView('expenses')" id="mtab-expenses" class="mnav-tab flex flex-col items-center gap-0.5 text-slate-400 py-1 px-2.5 rounded-xl">
            <i class="fa-solid fa-money-bill-wave text-base"></i>
            <span>المصروفات</span>
        </button>

        <button onclick="switchView('reports')" id="mtab-reports" class="mnav-tab flex flex-col items-center gap-0.5 text-slate-400 py-1 px-2.5 rounded-xl">
            <i class="fa-solid fa-chart-pie text-base"></i>
            <span>التقارير</span>
        </button>
    </nav>

    <!-- شريط السلة العائم السريع للموبايل -->
    <div id="mobile-floating-cart" class="md:hidden fixed bottom-16 left-3 right-3 z-30 flex items-center gap-2">
        <button onclick="openCartDrawer()" class="flex-1 bg-gradient-to-r from-emerald-600 via-brand-600 to-emerald-700 text-white p-2.5 rounded-2xl shadow-2xl flex items-center justify-between font-black border border-emerald-400/30 active:scale-98 transition backdrop-blur-md">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-lg bg-black/30 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-cart-shopping"></i>
                </span>
                <span id="mobile-cart-items-count" class="text-xs">0 أصناف</span>
            </div>
            <div class="flex items-center gap-2">
                <span id="mobile-cart-total-badge" class="text-sm font-black text-amber-300">0.00 ج.م</span>
                <span class="text-[11px] bg-white/20 px-2 py-0.5 rounded-lg">إتمام البيع <i class="fa-solid fa-arrow-left text-[9px]"></i></span>
            </div>
        </button>
    </div>

    <!-- لوحة السلة والدفع -->
    <div id="cart-drawer" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex justify-end">
        <div class="bg-slate-900 border-r border-slate-800 w-full max-w-md h-full flex flex-col shadow-2xl drawer-slide">
            <div class="p-3.5 bg-slate-850 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-brand-600/20 text-brand-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-receipt"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-white">سلة الفاتورة والدفع</h3>
                        <p class="text-[10px] text-slate-400">سوبر ماركت المنزل السوري</p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="clearCartConfirm()" class="px-2.5 py-1 bg-rose-500/10 text-rose-400 hover:bg-rose-500/20 rounded-lg text-xs font-bold transition">
                        إفراغ
                    </button>
                    <button onclick="closeCartDrawer()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
            </div>

            <div class="flex-1 p-3 overflow-y-auto divide-y divide-slate-800/60" id="cart-items-container"></div>

            <div id="empty-cart-view" class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500">
                <i class="fa-solid fa-cart-shopping text-4xl mb-2 text-slate-600"></i>
                <p class="text-sm font-bold">السلة فارغة</p>
                <p class="text-xs text-slate-500 mt-0.5">امسح باركود أو اختر منتجاً من القائمة</p>
            </div>

            <div class="p-3.5 bg-slate-950 border-t border-slate-800 space-y-2.5">
                <div class="space-y-1.5 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>المجموع الفرعي:</span>
                        <span class="font-bold text-slate-200 font-mono" id="subtotal-val">0.00 ج.م</span>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="flex items-center gap-1.5 bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-800">
                            <span class="text-slate-400 text-[11px]">خصم:</span>
                            <input type="number" id="discount-input" value="0" min="0" oninput="calculateTotals()" class="w-full bg-transparent text-amber-400 font-bold text-left text-xs focus:outline-none">
                            <span class="text-slate-500 text-[10px]">ج.م</span>
                        </div>
                        <div class="flex items-center gap-1.5 bg-slate-900 px-2.5 py-1.5 rounded-lg border border-slate-800">
                            <span class="text-slate-400 text-[11px]">توصيل:</span>
                            <input type="number" id="delivery-fee-input" value="0" min="0" oninput="calculateTotals()" class="w-full bg-transparent text-cyan-400 font-bold text-left text-xs focus:outline-none">
                            <span class="text-slate-500 text-[10px]">ج.م</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-center bg-slate-900 p-2.5 rounded-xl border border-brand-500/40">
                        <span class="text-xs font-bold text-slate-300">الإجمالي النهائي:</span>
                        <span class="text-xl font-black text-brand-400 font-mono" id="grand-total-val">0.00 ج.م</span>
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">وسيلة الدفع:</label>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-1 text-xs font-bold">
                        <button type="button" onclick="selectPaymentMethod('كاش')" class="pay-method-btn active p-1.5 rounded-xl bg-brand-600 text-white flex flex-col items-center justify-center gap-0.5 border border-brand-500">
                            <i class="fa-solid fa-money-bill-1-wave text-xs"></i>
                            <span class="text-[10px]">كاش</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('فودافون كاش')" class="pay-method-btn p-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-0.5 border border-slate-800">
                            <i class="fa-solid fa-mobile-screen text-xs text-red-400"></i>
                            <span class="text-[10px]">فودافون كاش</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('انستا باي')" class="pay-method-btn p-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-0.5 border border-slate-800">
                            <i class="fa-solid fa-bolt text-xs text-purple-400"></i>
                            <span class="text-[10px]">إنستا باي</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('فيزا')" class="pay-method-btn p-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-0.5 border border-slate-800">
                            <i class="fa-solid fa-credit-card text-xs text-blue-400"></i>
                            <span class="text-[10px]">فيزا</span>
                        </button>
                        <button type="button" onclick="selectPaymentMethod('آجل')" class="pay-method-btn p-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-slate-300 flex flex-col items-center justify-center gap-0.5 border border-slate-800">
                            <i class="fa-solid fa-file-invoice text-xs text-amber-400"></i>
                            <span class="text-[10px]">آجل</span>
                        </button>
                    </div>
                </div>

                <button onclick="processCheckout()" id="checkout-btn" class="w-full py-3 bg-gradient-to-r from-emerald-600 to-brand-600 hover:from-emerald-500 hover:to-brand-500 text-white font-black text-sm rounded-xl shadow-lg shadow-brand-950 flex items-center justify-center gap-2 active:scale-98 transition">
                    <i class="fa-solid fa-circle-check text-base"></i>
                    <span>إتمام الفاتورة والطباعة 🖨️</span>
                </button>
            </div>
        </div>
    </div>

    <!-- نافذة الكاميرا -->
    <div id="camera-modal" class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-md hidden flex items-center justify-center p-3">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-3xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
            <div class="p-3 bg-slate-850 border-b border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center">
                        <i class="fa-solid fa-camera"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-white">ماسح الباركود بالكاميرا</h3>
                        <p id="camera-status-text" class="text-[10px] text-emerald-400 font-bold">جاري فتح الكاميرا...</p>
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <select id="camera-device-select" onchange="switchCameraDevice(this.value)" class="bg-slate-950 text-slate-300 text-[10px] font-bold px-2 py-1 rounded-lg border border-slate-700 focus:outline-none max-w-[130px]">
                        <option value="">الكاميرا المتاحة</option>
                    </select>

                    <button onclick="stopCameraScanner()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-base"></i>
                    </button>
                </div>
            </div>

            <div class="p-3 bg-slate-950 flex-1 flex flex-col items-center justify-center min-h-[290px] relative overflow-hidden">
                <div id="html5-qrcode-reader" class="w-full max-w-sm min-h-[270px] bg-black rounded-2xl overflow-hidden relative shadow-inner border border-slate-800 flex items-center justify-center text-xs text-slate-500">
                    جاري تحميل قارئ الباركود...
                </div>
            </div>

            <div class="p-3 bg-slate-900 border-t border-slate-800 space-y-2">
                <div id="last-scanned-banner" class="hidden bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 px-3 py-1.5 rounded-xl text-xs flex items-center justify-between font-bold">
                    <span id="last-scanned-text">تم المسح بنجاح!</span>
                    <i class="fa-solid fa-circle-check text-emerald-400"></i>
                </div>

                <div class="flex items-center justify-between text-xs gap-1.5 flex-wrap">
                    <label class="flex items-center gap-1.5 cursor-pointer text-slate-300 font-bold text-[11px]">
                        <input type="checkbox" id="continuous-scan-check" checked class="w-4 h-4 text-brand-600 rounded bg-slate-900 border-slate-700">
                        <span>مسح متتابع</span>
                    </label>

                    <div class="flex items-center gap-1.5">
                        <label class="px-2.5 py-1.5 bg-brand-600/30 hover:bg-brand-600 text-brand-300 hover:text-white rounded-lg font-bold flex items-center gap-1 text-[11px] cursor-pointer border border-brand-500/40 transition">
                            <i class="fa-solid fa-camera-retro"></i>
                            <span>التقاط صورة</span>
                            <input type="file" accept="image/*" capture="environment" onchange="decodeBarcodeFromImage(this)" class="hidden">
                        </label>

                        <button onclick="switchCameraFacing()" class="px-2 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg font-bold flex items-center gap-1 text-[11px]">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>تبديل</span>
                        </button>

                        <button onclick="restartCameraScanner()" class="px-2.5 py-1.5 bg-emerald-700/60 hover:bg-emerald-600 text-white rounded-lg font-bold flex items-center gap-1 text-[11px]">
                            <i class="fa-solid fa-play"></i>
                            <span>تشغيل</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نافذة الوزن -->
    <div id="weight-modal" class="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-sm hidden flex items-center justify-center p-3">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-sm rounded-3xl overflow-hidden shadow-2xl p-4 space-y-3">
            <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                <div class="flex items-center gap-2">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </span>
                    <div>
                        <h3 class="font-extrabold text-sm text-white" id="weight-prod-name">تحديد وزن الصنف</h3>
                        <p class="text-[10px] text-amber-400 font-bold" id="weight-prod-price">سعر الكيلو: 0.00 ج.م</p>
                    </div>
                </div>
                <button onclick="closeModal('weight-modal')" class="w-7 h-7 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="grid grid-cols-4 gap-1.5 text-xs font-bold text-center">
                <button type="button" onclick="setWeightVal(0.125)" class="p-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl border border-slate-700">125غ</button>
                <button type="button" onclick="setWeightVal(0.250)" class="p-2 bg-slate-800 hover:bg-slate-700 text-amber-400 rounded-xl border border-amber-500/30">ربع كجم</button>
                <button type="button" onclick="setWeightVal(0.500)" class="p-2 bg-slate-800 hover:bg-slate-700 text-emerald-400 rounded-xl border border-emerald-500/30">نصف كجم</button>
                <button type="button" onclick="setWeightVal(1.000)" class="p-2 bg-brand-600/30 hover:bg-brand-600 text-brand-300 hover:text-white rounded-xl border border-brand-500/50">1 كجم</button>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs">
                <div>
                    <label class="block text-slate-400 mb-1">الوزن بالكجم:</label>
                    <input type="number" id="weight-kg-input" step="0.005" min="0.005" placeholder="1.000" oninput="onKgInputChanged()" class="w-full bg-slate-950 border border-slate-700 text-emerald-400 font-mono font-black text-center text-sm rounded-xl py-2 focus:outline-none">
                </div>
                <div>
                    <label class="block text-slate-400 mb-1">الوزن بالجرام:</label>
                    <input type="number" id="weight-g-input" step="5" min="5" placeholder="1000" oninput="onGramInputChanged()" class="w-full bg-slate-950 border border-slate-700 text-white font-mono font-bold text-center text-sm rounded-xl py-2 focus:outline-none">
                </div>
            </div>

            <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex justify-between items-center text-xs">
                <span class="text-slate-400" id="weight-formula-preview">1.000 كجم × 0.00 ج.م</span>
                <span class="text-base font-black text-brand-400 font-mono" id="weight-total-price-preview">0.00 ج.م</span>
            </div>

            <button onclick="confirmWeightSelection()" class="w-full py-2.5 bg-brand-600 hover:bg-brand-500 text-white font-black text-xs sm:text-sm rounded-xl shadow-lg active:scale-95 transition">
                إضافة الوزن إلى السلة 🛒
            </button>
        </div>
    </div>

    <!-- نافذة الفاتورة الحرارية -->
    <div id="thermal-receipt-modal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm hidden flex items-center justify-center p-3">
        <div class="bg-white text-black w-full max-w-[340px] rounded-2xl p-4 shadow-2xl text-xs font-mono select-text" id="printable-receipt-card">
            <div class="text-center pb-2 border-b border-dashed border-gray-400 space-y-0.5">
                <h2 class="text-base font-black"><?php echo htmlspecialchars($store_name); ?></h2>
                <p class="text-[10px] text-gray-700"><?php echo htmlspecialchars($store_tagline); ?></p>
                <p class="text-[10px] text-gray-700">هاتف: <?php echo htmlspecialchars($store_phone); ?></p>
                <div class="text-[11px] font-bold text-gray-800 mt-1">فاتورة مبيعات: #<span id="rcpt-id">1001</span></div>
                <div class="text-[10px] text-gray-500" id="rcpt-date"></div>
            </div>

            <div class="py-2 border-b border-dashed border-gray-400">
                <table class="w-full text-right text-[11px]">
                    <thead>
                        <tr class="font-bold border-b border-gray-300">
                            <th class="pb-1">الصنف</th>
                            <th class="pb-1 text-center">الكمية</th>
                            <th class="pb-1 text-left">المجموع</th>
                        </tr>
                    </thead>
                    <tbody id="rcpt-items-body"></tbody>
                </table>
            </div>

            <div class="py-2 space-y-1 text-[11px]">
                <div class="flex justify-between"><span>المجموع:</span><span id="rcpt-subtotal">0.00 ج.م</span></div>
                <div class="flex justify-between text-gray-600"><span>خصم:</span><span id="rcpt-discount">0.00 ج.م</span></div>
                <div class="flex justify-between font-black text-sm pt-1 border-t border-gray-300"><span>الصافي:</span><span id="rcpt-grand-total">0.00 ج.م</span></div>
            </div>

            <div class="no-print mt-3 grid grid-cols-2 gap-2">
                <button onclick="window.print()" class="py-2 bg-emerald-600 text-white font-bold rounded-xl text-xs">طباعة 🖨️</button>
                <button onclick="closeModal('thermal-receipt-modal')" class="py-2 bg-gray-200 text-gray-800 font-bold rounded-xl text-xs">إغلاق</button>
            </div>
        </div>
    </div>

    <!-- كود الجافاسكريبت الموحد -->
    <script>
        let products = <?php echo json_encode($products, JSON_UNESCAPED_UNICODE); ?>;
        let suppliers = <?php echo json_encode($suppliers, JSON_UNESCAPED_UNICODE); ?>;
        let cart = {}; 
        let selectedPaymentMethod = 'كاش';
        let currentWeightProduct = null;
        let audioContext = null;
        
        let html5QrCode = null;
        let selectedCameraDeviceId = null;
        let cameraScanTarget = 'pos_cart';
        let lastScannedCode = '';
        let lastScannedTime = 0;
        let availableCameras = [];
        let inventoryData = [];

        document.addEventListener('DOMContentLoaded', () => {
            try { renderProducts(products); } catch (e) { console.error(e); }
            try { renderSuppliersList(suppliers); } catch (e) { console.error(e); }
            try { renderCart(); } catch (e) { console.error(e); }
            try { loadExpensesData(); } catch (e) { console.error(e); }

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

        function playBeep() {
            try {
                if (!audioContext) audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(920, audioContext.currentTime);
                gain.gain.setValueAtTime(0.15, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.12);
                osc.connect(gain);
                gain.connect(audioContext.destination);
                osc.start();
                osc.stop(audioContext.currentTime + 0.12);
                if (navigator.vibrate) navigator.vibrate(70);
            } catch (e) {}
        }

        function switchView(viewName) {
            document.querySelectorAll('.app-page-view').forEach(p => p.classList.add('hidden'));

            document.querySelectorAll('.dnav-tab').forEach(btn => {
                btn.classList.remove('bg-brand-600', 'text-white');
                btn.classList.add('bg-slate-800', 'text-slate-300');
            });

            document.querySelectorAll('.mnav-tab').forEach(btn => {
                btn.classList.remove('text-brand-400');
                btn.classList.add('text-slate-400');
            });

            const page = document.getElementById('view-' + viewName);
            if (page) page.classList.remove('hidden');

            const dtab = document.getElementById('dtab-' + viewName);
            if (dtab) {
                dtab.classList.add('bg-brand-600', 'text-white');
                dtab.classList.remove('bg-slate-800', 'text-slate-300');
            }
            const mtab = document.getElementById('mtab-' + viewName);
            if (mtab) {
                mtab.classList.add('text-brand-400');
                mtab.classList.remove('text-slate-400');
            }

            if (viewName === 'inventory') initInventoryView();
            if (viewName === 'suppliers') renderSuppliersList(suppliers);
            if (viewName === 'reports') loadShiftReportsData();
            if (viewName === 'expenses') loadExpensesData();
            if (viewName === 'add-product') clearProductForm();
        }

        function renderProducts(items) {
            const grid = document.getElementById('products-grid');
            const noMsg = document.getElementById('no-products-msg');
            if (!grid) return;
            grid.innerHTML = '';

            if (!items || items.length === 0) {
                if (noMsg) noMsg.classList.remove('hidden');
                return;
            }
            if (noMsg) noMsg.classList.add('hidden');

            items.forEach(p => {
                const isWeight = isWeightProduct(p);
                const card = document.createElement('div');
                card.className = "bg-slate-900 hover:bg-slate-850 border border-slate-800 hover:border-brand-500/50 rounded-2xl p-2 sm:p-2.5 flex flex-col justify-between cursor-pointer transition-all duration-150 active:scale-[0.97] group shadow-md";
                card.onclick = () => onProductCardClicked(p);

                const imgUrl = p.image_url || 'placeholder.php?w=200&h=200&text=' + encodeURIComponent(p.name);

                card.innerHTML = `
                    <div class="relative mb-2 overflow-hidden rounded-xl bg-slate-950 h-24 sm:h-28 flex items-center justify-center">
                        <img src="${imgUrl}" alt="${p.name}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200" loading="lazy">
                        ${isWeight ? '<span class="absolute top-1.5 right-1.5 bg-amber-500 text-slate-950 text-[10px] font-black px-1.5 py-0.5 rounded-md shadow"><i class="fa-solid fa-scale-balanced ml-0.5"></i>وزن</span>' : ''}
                        <span class="absolute bottom-1.5 left-1.5 bg-slate-950/85 text-cyan-300 text-[10px] font-mono px-1.5 py-0.5 rounded border border-slate-700">${p.local_code || '#'+p.id}</span>
                    </div>
                    <div>
                        <h4 class="font-bold text-xs text-white line-clamp-2 leading-tight min-h-[32px]">${p.name}</h4>
                        <div class="flex items-baseline justify-between mt-1.5">
                            <span class="text-brand-400 font-black text-xs sm:text-sm font-mono">${parseFloat(p.price).toFixed(2)} <span class="text-[10px]">ج.م</span></span>
                            ${isWeight ? '<button onclick="event.stopPropagation(); openWeightModal('+p.id+')" class="text-amber-400 hover:text-amber-300 text-xs px-1.5 py-0.5 bg-amber-500/10 rounded border border-amber-500/30"><i class="fa-solid fa-scale-balanced"></i></button>' : ''}
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
            if (inp) {
                inp.value = '';
                renderProducts(products);
                inp.focus();
            }
        }

        function handleBarcodeScan(code) {
            if (!code) return;
            code = code.trim();

            if ((code.startsWith('20') || code.startsWith('21') || code.startsWith('02')) && code.length >= 12) {
                const itemCode = code.substring(2, 7);
                const weightPart = parseInt(code.substring(7, 12), 10);
                const weightKg = weightPart / 1000.0;

                const product = products.find(p => p.local_code === itemCode || (p.barcode && p.barcode.includes(itemCode)));
                if (product) {
                    addToCart(product.id, weightKg);
                    showScannedFeedback(`تم مسح وزن: ${product.name} (${weightKg.toFixed(3)} كجم)`);
                    return;
                }
            }

            const product = products.find(p => 
                p.barcode === code || 
                p.barcode2 === code || 
                p.barcode3 === code || 
                (p.all_barcodes && p.all_barcodes.includes(code)) || 
                p.local_code === code
            );

            if (product) {
                if (isWeightProduct(product)) {
                    openWeightModal(product.id);
                } else {
                    addToCart(product.id, 1);
                    showScannedFeedback(`تمت إضافة: ${product.name}`);
                }
            } else {
                playBeep();
                alert(`⚠️ لم يتم العثور على أي صنف بالباركود: ${code}`);
            }
        }

        function showScannedFeedback(msg) {
            playBeep();
            const banner = document.getElementById('last-scanned-banner');
            const text = document.getElementById('last-scanned-text');
            if (banner && text) {
                text.innerText = msg;
                banner.classList.remove('hidden');
                setTimeout(() => banner.classList.add('hidden'), 2000);
            }
        }

        function openCartDrawer() {
            const drawer = document.getElementById('cart-drawer');
            if (drawer) drawer.classList.remove('hidden');
        }

        function closeCartDrawer() {
            const drawer = document.getElementById('cart-drawer');
            if (drawer) drawer.classList.add('hidden');
        }

        function addToCart(productId, qty = 1) {
            const product = products.find(p => p.id == productId);
            if (!product) return;

            if (cart[productId]) {
                cart[productId].qty += qty;
            } else {
                cart[productId] = {
                    id: product.id,
                    name: product.name,
                    price: parseFloat(product.price),
                    cost: parseFloat(product.cost || 0),
                    qty: qty,
                    is_weight: isWeightProduct(product),
                    barcode: product.barcode || ''
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

        function removeCartItem(productId) {
            delete cart[productId];
            renderCart();
        }

        function clearCartConfirm() {
            if (Object.keys(cart).length === 0) return;
            if (confirm("هل أنت متأكد من تفريغ كافة عناصر الفاتورة الحالية؟")) {
                cart = {};
                renderCart();
            }
        }

        function renderCart() {
            const container = document.getElementById('cart-items-container');
            const emptyView = document.getElementById('empty-cart-view');
            if (!container) return;
            container.innerHTML = '';

            const items = Object.values(cart);
            const totalCount = items.length;

            let subtotal = 0;
            items.forEach(it => subtotal += (it.price * it.qty));

            const headerTotal = document.getElementById('header-cart-total');
            const mobCount = document.getElementById('mobile-cart-items-count');
            const mobTotal = document.getElementById('mobile-cart-total-badge');

            if (headerTotal) headerTotal.innerText = `${subtotal.toFixed(2)} ج.م`;
            if (mobCount) mobCount.innerText = `${totalCount} أصناف`;
            if (mobTotal) mobTotal.innerText = `${subtotal.toFixed(2)} ج.م`;

            if (totalCount === 0) {
                if (emptyView) emptyView.classList.remove('hidden');
                container.classList.add('hidden');
                calculateTotals();
                return;
            }

            if (emptyView) emptyView.classList.add('hidden');
            container.classList.remove('hidden');

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
                            <span class="text-brand-400 font-bold font-mono">${qtyDisplay}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-1 bg-slate-950 p-1 rounded-xl border border-slate-800">
                        <button onclick="updateCartQty(${item.id}, -${item.is_weight ? 0.25 : 1})" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-minus text-[10px]"></i>
                        </button>
                        <span class="px-1.5 font-black text-slate-200 text-[11px] min-w-[28px] text-center font-mono">${item.is_weight ? item.qty.toFixed(2) : item.qty}</span>
                        <button onclick="updateCartQty(${item.id}, ${item.is_weight ? 0.25 : 1})" class="w-6 h-6 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 flex items-center justify-center font-bold">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="font-extrabold text-brand-400 text-xs min-w-[50px] text-left font-mono">${itemTotal}</span>
                        <button onclick="removeCartItem(${item.id})" class="text-slate-500 hover:text-rose-400 p-1">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                `;
                container.appendChild(itemRow);
            });

            calculateTotals();
        }

        function calculateTotals() {
            let subtotal = 0;
            Object.values(cart).forEach(it => subtotal += (it.price * it.qty));

            const discount = parseFloat(document.getElementById('discount-input')?.value || 0);
            const delivery = parseFloat(document.getElementById('delivery-fee-input')?.value || 0);
            const grandTotal = Math.max(0, (subtotal - discount) + delivery);

            const elSub = document.getElementById('subtotal-val');
            const elGrand = document.getElementById('grand-total-val');
            const mobTotal = document.getElementById('mobile-cart-total-badge');

            if (elSub) elSub.innerText = subtotal.toFixed(2) + ' ج.م';
            if (elGrand) elGrand.innerText = grandTotal.toFixed(2) + ' ج.م';
            if (mobTotal) mobTotal.innerText = grandTotal.toFixed(2) + ' ج.م';
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

        async function processCheckout() {
            const items = Object.values(cart);
            if (items.length === 0) {
                alert("⚠️ السلة فارغة! يرجى إضافة أصناف أولاً.");
                return;
            }

            const btn = document.getElementById('checkout-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> جاري حفظ الفاتورة...';

            let subtotal = 0;
            items.forEach(it => subtotal += (it.price * it.qty));
            const discount = parseFloat(document.getElementById('discount-input')?.value || 0);
            const delivery = parseFloat(document.getElementById('delivery-fee-input')?.value || 0);
            const grandTotal = Math.max(0, (subtotal - discount) + delivery);

            const payload = {
                action: 'save_order',
                source: 'pos_cashier',
                items: items.map(it => ({
                    product_id: it.id,
                    name: it.name,
                    price: it.price,
                    cost: it.cost,
                    qty: it.qty,
                    barcode: it.barcode
                })),
                total: grandTotal,
                discount: discount,
                delivery_fee: delivery,
                payment_method: selectedPaymentMethod,
                customer_name: 'عميل نقدي',
                customer_phone: '',
                customer_address: ''
            };

            try {
                const res = await fetch('api_sync.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    playBeep();
                    showThermalReceiptModal(data, items, subtotal, grandTotal);
                    cart = {};
                    renderCart();
                    closeCartDrawer();
                    refreshCatalog();
                } else {
                    alert("❌ حدث خطأ: " + (data.error || 'فشل حفظ الفاتورة'));
                }
            } catch (err) {
                alert("❌ تعذر الاتصال بالسيرفر لحفظ الفاتورة!");
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-circle-check text-base"></i><span>إتمام الفاتورة والطباعة 🖨️</span>';
            }
        }

        function showThermalReceiptModal(orderData, items, subtotal, grandTotal) {
            document.getElementById('rcpt-id').innerText = orderData.order_id || Math.floor(1000 + Math.random() * 9000);
            document.getElementById('rcpt-date').innerText = new Date().toLocaleString('ar-EG');

            const tbody = document.getElementById('rcpt-items-body');
            tbody.innerHTML = items.map(it => `
                <tr>
                    <td class="py-1 font-bold">${it.name}</td>
                    <td class="py-1 text-center font-mono">${it.is_weight ? it.qty.toFixed(3) : it.qty}</td>
                    <td class="py-1 text-left font-mono">${(it.price * it.qty).toFixed(2)}</td>
                </tr>
            `).join('');

            document.getElementById('rcpt-subtotal').innerText = subtotal.toFixed(2) + ' ج.م';
            document.getElementById('rcpt-discount').innerText = (parseFloat(document.getElementById('discount-input')?.value) || 0).toFixed(2) + ' ج.م';
            document.getElementById('rcpt-grand-total').innerText = grandTotal.toFixed(2) + ' ج.م';

            openModal('thermal-receipt-modal');
        }

        // ==========================================
        // قارئ وكاميرا الباركود المعتمد عالمياً (Html5Qrcode Standard GitHub Engine)
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
                statusEl.innerText = 'جاري الاتصال بالكاميرا...';
                statusEl.className = 'text-[10px] text-amber-400 font-bold';
            }

            // فحص دعم المتصفح والبيئة الآمنة
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                console.error("المتصفح لا يدعم الكاميرا أو البيئة غير آمنة Secure Context");
                if (statusEl) {
                    statusEl.innerText = "المتصفح لا يدعم الكاميرا أو البيئة غير آمنة (يمكنك استخدام زر التقاط صورة)";
                    statusEl.className = 'text-[10px] text-rose-400 font-bold';
                }
                return;
            }

            await stopCameraStreamOnly();

            try {
                if (!html5QrCode) {
                    const formatsToSupport = [
                        Html5QrcodeSupportedFormats.EAN_13,
                        Html5QrcodeSupportedFormats.EAN_8,
                        Html5QrcodeSupportedFormats.CODE_128,
                        Html5QrcodeSupportedFormats.CODE_39,
                        Html5QrcodeSupportedFormats.UPC_A,
                        Html5QrcodeSupportedFormats.UPC_E,
                        Html5QrcodeSupportedFormats.QR_CODE,
                        Html5QrcodeSupportedFormats.ITF
                    ];
                    html5QrCode = new Html5Qrcode("html5-qrcode-reader", { formatsToSupport: formatsToSupport, verbose: false });
                }

                // كشف الكاميرات المتاحة
                try {
                    const cameras = await Html5Qrcode.getCameras();
                    if (cameras && cameras.length > 0) {
                        availableCameras = cameras;
                        const selectEl = document.getElementById('camera-device-select');
                        if (selectEl) {
                            selectEl.innerHTML = cameras.map((c, idx) => {
                                const isBack = c.label.toLowerCase().includes('back') || c.label.toLowerCase().includes('rear') || c.label.toLowerCase().includes('environment');
                                return `<option value="${c.id}" ${selectedCameraDeviceId === c.id ? 'selected' : ''}>${c.label || 'كاميرا ' + (idx + 1) + (isBack ? ' (خلفية)' : '')}</option>`;
                            }).join('');
                        }
                        if (!selectedCameraDeviceId) {
                            const backCam = cameras.find(c => c.label.toLowerCase().includes('back') || c.label.toLowerCase().includes('rear') || c.label.toLowerCase().includes('environment'));
                            selectedCameraDeviceId = backCam ? backCam.id : cameras[0].id;
                        }
                    }
                } catch(e) {
                    console.warn("Cameras enum error:", e);
                }

                const qrConfig = {
                    fps: 15,
                    qrbox: { width: 260, height: 160 },
                    aspectRatio: 1.0
                };

                const camIdOrConfig = selectedCameraDeviceId ? selectedCameraDeviceId : { facingMode: "environment" };

                await html5QrCode.start(
                    camIdOrConfig,
                    qrConfig,
                    (decodedText, decodedResult) => {
                        onBarcodeScanned(decodedText);
                    },
                    (errorMessage) => {
                        // scanning frame...
                    }
                );

                if (statusEl) {
                    statusEl.innerText = 'الكاميرا نشطة - وجهها نحو الباركود';
                    statusEl.className = 'text-[10px] text-emerald-400 font-bold';
                }

            } catch (err) {
                console.error("Html5Qrcode start error:", err);
                try {
                    await html5QrCode.start(
                        { facingMode: "user" },
                        { fps: 15, qrbox: { width: 260, height: 160 } },
                        (decodedText) => onBarcodeScanned(decodedText),
                        () => {}
                    );
                    if (statusEl) {
                        statusEl.innerText = 'الكاميرا نشطة';
                        statusEl.className = 'text-[10px] text-emerald-400 font-bold';
                    }
                } catch (e2) {
                    if (statusEl) {
                        statusEl.innerText = 'تعذر تشغيل الكاميرا (يرجى مراجعة إذن المتصفح أو التقاط صورة)';
                        statusEl.className = 'text-[10px] text-rose-400 font-bold';
                    }
                }
            }
        }

        async function stopCameraStreamOnly() {
            if (html5QrCode && html5QrCode.isScanning) {
                try {
                    await html5QrCode.stop();
                } catch(e) {}
            }
        }

        async function stopCameraScanner() {
            await stopCameraStreamOnly();
            closeModal('camera-modal');
        }

        async function decodeBarcodeFromImage(inputEl) {
            if (!inputEl.files || inputEl.files.length === 0) return;
            const file = inputEl.files[0];

            const statusEl = document.getElementById('camera-status-text');
            if (statusEl) {
                statusEl.innerText = 'جاري قراءة الباركود من الصورة...';
                statusEl.className = 'text-[10px] text-cyan-400 font-bold';
            }

            try {
                if (!html5QrCode) {
                    html5QrCode = new Html5Qrcode("html5-qrcode-reader", { verbose: false });
                }
                const decodedText = await html5QrCode.scanFile(file, false);
                if (decodedText) {
                    onBarcodeScanned(decodedText);
                    showScannedFeedback('تمت القراءة بنجاح من الصورة: ' + decodedText);
                }
            } catch (e) {
                console.error("Image scan error:", e);
                alert('⚠️ تعذر قراءة الباركود من هذه الصورة، يرجى التقاط صورة أقرب وأوضح.');
            } finally {
                inputEl.value = '';
            }
        }

        function onBarcodeScanned(decodedText) {
            const now = Date.now();
            if (decodedText === lastScannedCode && (now - lastScannedTime) < 1500) return;
            lastScannedCode = decodedText;
            lastScannedTime = now;

            playBeep();

            if (cameraScanTarget === 'add_product_barcode') {
                document.getElementById('add-prod-barcode').value = decodedText;
                showScannedFeedback('تم مسح الباركود: ' + decodedText);
                stopCameraScanner();
                cameraScanTarget = 'pos_cart';
                return;
            }

            if (cameraScanTarget === 'inventory_scan') {
                handleInventoryBarcodeScan(decodedText);
                const isContinuous = document.getElementById('continuous-scan-check')?.checked;
                if (!isContinuous) stopCameraScanner();
                return;
            }

            handleBarcodeScan(decodedText);
            const isContinuous = document.getElementById('continuous-scan-check')?.checked;
            if (!isContinuous) stopCameraScanner();
        }

        async function switchCameraDevice(deviceId) {
            if (!deviceId) return;
            selectedCameraDeviceId = deviceId;
            await startCameraScanner(cameraScanTarget);
        }

        async function switchCameraFacing() {
            try {
                const cameras = await Html5Qrcode.getCameras();
                if (cameras && cameras.length > 1) {
                    const currentIdx = cameras.findIndex(c => c.id === selectedCameraDeviceId);
                    const nextIdx = (currentIdx + 1) % cameras.length;
                    selectedCameraDeviceId = cameras[nextIdx].id;
                }
            } catch(e) {}
            await startCameraScanner(cameraScanTarget);
        }

        async function restartCameraScanner() {
            await startCameraScanner(cameraScanTarget);
        }

        function openWeightModal(productId) {
            const product = products.find(p => p.id == productId);
            if (!product) return;

            currentWeightProduct = product;
            document.getElementById('weight-prod-name').innerText = product.name;
            document.getElementById('weight-prod-price').innerText = `سعر الكيلو: ${parseFloat(product.price).toFixed(2)} ج.م`;

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

        function initInventoryView() {
            if (!products || products.length === 0) return;

            inventoryData = products.map(p => {
                const existing = inventoryData.find(i => i.id === p.id);
                return {
                    id: p.id,
                    name: p.name,
                    category: p.category || 'عام',
                    barcode: p.barcode || '',
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
                if (query) {
                    const matchName = item.name.toLowerCase().includes(query);
                    const matchCode = item.local_code && item.local_code.includes(query);
                    const matchBarcode = item.barcode && item.barcode.includes(query);
                    if (!matchName && !matchCode && !matchBarcode) return false;
                }

                if (category && item.category !== category) return false;

                const diff = (item.actual_stock || 0) - item.stock;
                if (statusFilter === 'discrepancy' && Math.abs(diff) < 0.001) return false;
                if (statusFilter === 'shortage' && diff >= 0) return false;
                if (statusFilter === 'surplus' && diff <= 0) return false;
                if (statusFilter === 'modified' && !item.modified) return false;

                return true;
            });

            renderInventoryTable(filtered);
        }

        function renderInventoryTable(list) {
            const tbody = document.getElementById('inventory-tbody');
            if (!tbody) return;

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="p-6 text-center text-slate-500 font-bold">لا توجد أصناف تطابق شروط البحث</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(item => {
                const diff = (parseFloat(item.actual_stock) || 0) - item.stock;
                let diffBadge = '';
                if (Math.abs(diff) < 0.001) {
                    diffBadge = `<span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-bold text-[10px]">مطابق</span>`;
                } else if (diff < 0) {
                    diffBadge = `<span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-400 font-bold text-[10px]">عجز (${Math.abs(diff).toFixed(2)})</span>`;
                } else {
                    diffBadge = `<span class="px-2 py-0.5 rounded bg-sky-500/20 text-sky-400 font-bold text-[10px]">زيادة (+${diff.toFixed(2)})</span>`;
                }

                return `
                    <tr class="hover:bg-slate-800/40 ${item.modified ? 'bg-indigo-950/30' : ''}">
                        <td class="p-2.5 text-center font-mono font-bold text-slate-400 text-xs">${item.local_code || '---'}</td>
                        <td class="p-2.5">
                            <div class="font-bold text-slate-200 text-xs">${item.name}</div>
                            <div class="text-[10px] text-slate-500 font-mono">${item.barcode || '-'}</div>
                        </td>
                        <td class="p-2.5 text-center text-slate-400 text-[11px]">${item.category}</td>
                        <td class="p-2.5 text-center font-mono text-xs text-slate-300 font-bold">${item.stock.toFixed(2)}</td>
                        <td class="p-2.5 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <button onclick="quickAddInventory(${item.id}, -1)" class="w-6 h-6 bg-slate-800 hover:bg-rose-600 text-slate-300 hover:text-white rounded text-xs font-bold">-1</button>
                                <input type="number" step="any" min="0" value="${item.actual_stock}" onchange="handleInventoryInput(${item.id}, this.value)" class="w-16 py-1 bg-slate-950 border border-slate-700 rounded text-center font-mono font-bold text-xs text-indigo-300">
                                <button onclick="quickAddInventory(${item.id}, 1)" class="w-6 h-6 bg-slate-800 hover:bg-emerald-600 text-slate-300 hover:text-white rounded text-xs font-bold">+1</button>
                            </div>
                        </td>
                        <td class="p-2.5 text-center">${diffBadge}</td>
                        <td class="p-2.5 text-center">
                            <button onclick="saveSingleInventory(${item.id})" class="px-2 py-1 bg-slate-800 hover:bg-emerald-600 text-slate-300 hover:text-white rounded text-[11px] font-bold transition">حفظ</button>
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
                alert("ℹ️ لم يتم تعديل أي صنف بالجرد الحالي!");
                return;
            }

            if (!confirm(`هل أنت متأكد من تطبيق الجرد وتحديث كميات (${modifiedItems.length}) صنف في المخزون فوراً؟`)) return;

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
                    alert(`🎉 تم بنجاح حفظ وتطبيق الجرد لـ (${data.updated_count}) صنف!`);
                } else {
                    alert(`❌ حدث خطأ: ${data.error}`);
                }
            } catch (e) {
                alert("❌ تعذر الاتصال بالسيرفر لحفظ الجرد الشامل!");
            }
        }

        function scanBarcodeForInventory() {
            cameraScanTarget = 'inventory_scan';
            startCameraScanner('inventory_scan');
        }

        function handleInventoryBarcodeScan(code) {
            if (!code) return;
            code = code.trim();
            const item = inventoryData.find(p => p.barcode === code || p.local_code === code);
            if (item) {
                item.actual_stock = (parseFloat(item.actual_stock) || 0) + 1;
                item.modified = true;
                updateInventoryStats();
                filterInventoryTable();
                showScannedFeedback(`تم جرد: ${item.name} (${item.actual_stock})`);
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
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </body>
                </html>
            `);
            printWin.document.close();
            setTimeout(() => { try { printWin.print(); } catch(e) {} }, 500);
        }

        function renderSuppliersList(list) {
            const grid = document.getElementById('suppliers-grid');
            if (!grid) return;
            grid.innerHTML = '';

            let totalDebt = 0;
            list.forEach(s => {
                const bal = parseFloat(s.balance || 0);
                totalDebt += bal;

                const card = document.createElement('div');
                card.className = "bg-slate-900 border border-slate-800 rounded-2xl p-3.5 flex flex-col justify-between gap-2.5 shadow-lg";
                card.innerHTML = `
                    <div>
                        <div class="flex items-start justify-between">
                            <h3 class="font-extrabold text-sm text-white">${s.name}</h3>
                            <span class="text-[10px] bg-slate-800 text-slate-400 px-2 py-0.5 rounded-full">#${s.id}</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1"><i class="fa-solid fa-phone text-[10px] ml-1"></i>${s.phone || 'غير مسجل'}</p>
                    </div>

                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800 flex items-center justify-between">
                        <span class="text-xs text-slate-400">المتبقي له:</span>
                        <span class="text-sm font-black ${bal > 0 ? 'text-amber-400' : 'text-emerald-400'} font-mono">${bal.toFixed(2)} ج.م</span>
                    </div>

                    <button onclick="promptSupplierPayout(${s.id}, '${s.name}', ${bal})" class="w-full py-2 bg-amber-600/20 hover:bg-amber-600 text-amber-400 hover:text-slate-950 font-bold text-xs rounded-xl border border-amber-500/40 transition">
                        سداد دفعة للمورد 🤝
                    </button>
                `;
                grid.appendChild(card);
            });

            const debtEl = document.getElementById('total-suppliers-debt');
            if (debtEl) debtEl.innerText = `${totalDebt.toFixed(2)} ج.م`;
        }

        function filterSuppliersList(query) {
            query = query.trim().toLowerCase();
            if (!query) {
                renderSuppliersList(suppliers);
                return;
            }
            const filtered = suppliers.filter(s => s.name.toLowerCase().includes(query) || (s.phone && s.phone.includes(query)));
            renderSuppliersList(filtered);
        }

        async function promptSupplierPayout(supId, supName, balance) {
            const amtStr = prompt(`سداد دفعة للمورد (${supName}):\nالمبلغ المتبقي له: ${balance.toFixed(2)} ج.م\nأدخل مبلغ الدفعة المسددة:`, "1000");
            if (amtStr !== null && !isNaN(amtStr) && parseFloat(amtStr) > 0) {
                const amount = parseFloat(amtStr);
                try {
                    const res = await fetch('api_sync.php?action=pay_supplier&api_key=syrian_home_pos_secret_token_2026', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ supplier_id: supId, supplier_name: supName, amount, payment_method: 'كاش', note: 'سداد من الكاشير' })
                    });
                    const data = await res.json();
                    if (data.success) {
                        alert(data.message);
                        const sup = suppliers.find(s => s.id == supId);
                        if (sup) sup.balance = Math.max(0, parseFloat(sup.balance) - amount);
                        renderSuppliersList(suppliers);
                    } else {
                        alert("خطأ: " + data.error);
                    }
                } catch (e) {
                    alert("تعذر الاتصال بالسيرفر!");
                }
            }
        }

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
                            <td class="py-2 font-bold text-rose-400">${e.category}</td>
                            <td class="py-2 text-slate-300">${e.note || '-'}</td>
                            <td class="py-2 text-slate-400 font-mono">${e.payment_method || 'كاش'}</td>
                            <td class="py-2 font-black text-rose-400 font-mono">${parseFloat(e.amount).toFixed(2)} ج.م</td>
                            <td class="py-2 text-slate-500 text-[10px] font-mono">${e.date ? (e.date.length > 10 ? e.date.substring(11, 16) : e.date) : '-'}</td>
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

            try {
                const res = await fetch('api_sync.php?action=record_expense&api_key=syrian_home_pos_secret_token_2026', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category, amount, payment_method: method, note })
                });
                const data = await res.json();
                if (data.success) {
                    alert("✅ تم تسجيل المصروف وخصمه بنجاح!");
                    document.getElementById('page-exp-amount').value = '';
                    document.getElementById('page-exp-note').value = '';
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

        async function loadShiftReportsData() {
            const container = document.getElementById('reports-stats-cards');
            const pmGrid = document.getElementById('reports-payment-methods-grid');
            if (!container) return;
            container.innerHTML = '<div class="col-span-4 text-center py-6"><i class="fa-solid fa-spinner fa-spin text-xl text-cyan-400"></i></div>';

            try {
                const res = await fetch('api_sync.php?action=get_pos_reports&api_key=syrian_home_pos_secret_token_2026');
                const data = await res.json();

                if (data.success) {
                    container.innerHTML = `
                        <div class="bg-slate-950 p-3 rounded-xl border border-brand-500/30 text-center">
                            <span class="text-slate-400 block text-[11px]">مبيعات اليوم:</span>
                            <span class="text-base sm:text-lg font-black text-brand-400 font-mono">${data.total_sales.toFixed(2)} ج.م</span>
                            <span class="text-[10px] text-slate-500 block">فواتير: ${data.orders_count}</span>
                        </div>
                        <div class="bg-slate-950 p-3 rounded-xl border border-rose-500/30 text-center">
                            <span class="text-slate-400 block text-[11px]">المنصرفات:</span>
                            <span class="text-base sm:text-lg font-black text-rose-400 font-mono">${data.total_all_expenses.toFixed(2)} ج.م</span>
                            <span class="text-[10px] text-slate-500 block">مصروفات ومسحوبات</span>
                        </div>
                        <div class="bg-slate-950 p-3 rounded-xl border border-emerald-500/40 text-center col-span-2">
                            <span class="text-slate-400 block text-[11px]">صافي السيولة النقدية بالدرج:</span>
                            <span class="text-lg sm:text-xl font-black text-emerald-400 font-mono">${data.net_cash_in_drawer.toFixed(2)} ج.م</span>
                            <span class="text-[10px] text-slate-400 block">(مبيعات الكاش - المنصرفات النقدية)</span>
                        </div>
                    `;

                    if (pmGrid) {
                        pmGrid.innerHTML = `
                            <div class="p-2 bg-slate-950 rounded-lg border border-slate-800 text-center"><span class="text-[10px] text-slate-400 block">كاش:</span><b class="text-xs text-brand-400 font-mono">${(data.sales_by_method['كاش'] || 0).toFixed(2)}</b></div>
                            <div class="p-2 bg-slate-950 rounded-lg border border-slate-800 text-center"><span class="text-[10px] text-slate-400 block">فودافون:</span><b class="text-xs text-red-400 font-mono">${(data.sales_by_method['فودافون كاش'] || 0).toFixed(2)}</b></div>
                            <div class="p-2 bg-slate-950 rounded-lg border border-slate-800 text-center"><span class="text-[10px] text-slate-400 block">إنستا باي:</span><b class="text-xs text-purple-400 font-mono">${(data.sales_by_method['انستا باي'] || 0).toFixed(2)}</b></div>
                            <div class="p-2 bg-slate-950 rounded-lg border border-slate-800 text-center"><span class="text-[10px] text-slate-400 block">فيزا:</span><b class="text-xs text-blue-400 font-mono">${(data.sales_by_method['فيزا'] || 0).toFixed(2)}</b></div>
                            <div class="p-2 bg-slate-950 rounded-lg border border-slate-800 text-center"><span class="text-[10px] text-slate-400 block">آجل:</span><b class="text-xs text-amber-400 font-mono">${(data.sales_by_method['آجل'] || 0).toFixed(2)}</b></div>
                        `;
                    }
                }
            } catch (e) {}
        }

        function calcProfitMargin() {}
        function generateRandomLocalCode() {
            let maxCode = 10000;
            products.forEach(p => {
                if (p.local_code && !isNaN(p.local_code)) {
                    const num = parseInt(p.local_code, 10);
                    if (num > maxCode && num < 99999) maxCode = num;
                }
            });
            document.getElementById('add-prod-local-code').value = (maxCode + 1).toString();
        }

        function clearProductForm() {
            document.getElementById('add-prod-edit-id').value = '';
            document.getElementById('add-prod-name').value = '';
            document.getElementById('add-prod-local-code').value = '';
            document.getElementById('add-prod-barcode').value = '';
            document.getElementById('add-prod-price').value = '';
            document.getElementById('add-prod-cost').value = '';
            document.getElementById('add-prod-stock').value = '100';
            document.getElementById('prod-form-title').innerText = 'إضافة صنف جديد للمتجر';
            document.getElementById('save-btn-text').innerText = 'حفظ الصنف ومزامنته فوراً';
        }

        async function submitNewProductPage(e) {
            e.preventDefault();
            const btn = document.getElementById('add-prod-btn');
            btn.disabled = true;

            const name = document.getElementById('add-prod-name').value.trim();
            const local_code = document.getElementById('add-prod-local-code').value.trim();
            const category = document.getElementById('add-prod-category').value;
            const barcode = document.getElementById('add-prod-barcode').value.trim();
            const price = parseFloat(document.getElementById('add-prod-price').value);
            const cost = parseFloat(document.getElementById('add-prod-cost').value) || 0;
            const stock = parseFloat(document.getElementById('add-prod-stock').value) || 100;
            const unit_type = document.getElementById('add-prod-unit-type').value;

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
                        all_barcodes: barcode,
                        price: price, 
                        cost: cost, 
                        stock: stock 
                    })
                });
                const data = await res.json();
                if (data.success) {
                    alert("✅ تم حفظ الصنف ومزامنته بنجاح!");
                    clearProductForm();
                    await refreshCatalog();
                } else {
                    alert("خطأ: " + data.error);
                }
            } catch (err) {
                alert("حدث خطأ في مزامنة المنتج!");
            } finally {
                btn.disabled = false;
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
    </script>
</body>
</html>
