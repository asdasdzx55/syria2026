<?php
require_once 'config.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS delivery_drivers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        pin_code VARCHAR(10) DEFAULT '1234',
        cash_balance DECIMAL(12, 2) DEFAULT 0.00,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$msg = '';
$err = '';

// إضافة طيار جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_driver'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pin = trim($_POST['pin_code'] ?? '1234');

    if (!empty($name)) {
        $ins = $pdo->prepare("INSERT INTO delivery_drivers (name, phone, pin_code) VALUES (?, ?, ?)");
        $ins->execute([$name, $phone, $pin]);
        $msg = "تمت إضافة الطيار ({$name}) بنجاح!";
    } else {
        $err = 'اسم الطيار مطلوب!';
    }
}

// تصفية عهدة طيار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_driver'])) {
    $d_id = (int)($_POST['driver_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    if ($d_id > 0) {
        if ($amount > 0) {
            $pdo->prepare("UPDATE delivery_drivers SET cash_balance = GREATEST(0, cash_balance - ?) WHERE id = ?")->execute([$amount, $d_id]);
        } else {
            $pdo->prepare("UPDATE delivery_drivers SET cash_balance = 0 WHERE id = ?")->execute([$d_id]);
        }
        $msg = "تمت تصفية عهدة الطيار بنجاح!";
    }
}

// إسناد أوردر لطيار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_order'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $driver_name = trim($_POST['driver_name'] ?? '');
    if ($order_id > 0 && !empty($driver_name)) {
        $pdo->prepare("UPDATE orders SET delivery_person = ?, status = 'قيد التوصيل' WHERE id = ?")->execute([$driver_name, $order_id]);
        $msg = "تم إسناد الطلب #{$order_id} للطيار ({$driver_name}) بنجاح!";
    }
}

// جلب الطيارين
$drivers = $pdo->query("SELECT * FROM delivery_drivers ORDER BY is_active DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);

// جلب الطلبات النشطة للدليفري (جديدة أو قيد التنفيذ أو قيد التوصيل)
$active_delivery_orders = $pdo->query("SELECT * FROM orders WHERE status IN ('جديد', 'قيد التنفيذ', 'شُحن', 'قيد التوصيل') ORDER BY id DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto max-w-6xl px-4 py-8">
    <!-- الهيدر -->
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <div>
            <h1 class="text-2xl font-black text-royal-charcoal flex items-center gap-2">
                <i class="fa-solid fa-motorcycle text-amber-500"></i> منظومة إدارة الدليفري وتتبع الطيارين
            </h1>
            <p class="text-gray-500 text-sm mt-1">تخصيص الطلبات بالاسم ومزامنة المبالغ والعهدة النقدية لحظياً</p>
        </div>
        <div class="flex gap-3">
            <a href="delivery.php" target="_blank" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 shadow-md transition-all">
                <i class="fa-solid fa-mobile-screen-button text-amber-300"></i> فتح تطبيق الطيارين (Mobile App)
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-4 font-bold">
            <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($msg); ?>
        </div>
    <?php endif; ?>

    <?php if ($err): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-4 font-bold">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($err); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
        <!-- إضافة طيار جديد -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 h-fit">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-user-plus text-amber-500"></i> إضافة طيار / مندوب جديد
            </h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">اسم الطيار *</label>
                    <input type="text" name="name" required class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-amber-500" placeholder="مثال: أحمد الدليفري">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">رقم الهاتف</label>
                    <input type="text" name="phone" class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-amber-500" placeholder="01xxxxxxxxx">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">رمز الدخول للتطبيق (PIN)</label>
                    <input type="text" name="pin_code" value="1234" class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
                </div>
                <button type="submit" name="add_driver" class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 rounded-xl shadow-md transition-all">
                    🛵 حفظ الطيار
                </button>
            </form>
        </div>

        <!-- قائمة الطيارين والعهدة النقدية -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
            <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-id-badge text-amber-500"></i> قائمة الطيارين ومحصلة العهدة النقدية
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php if (empty($drivers)): ?>
                    <div class="col-span-2 text-center py-8 text-gray-400">لا يوجد طيارين مسجلين بعد.</div>
                <?php else: ?>
                    <?php foreach ($drivers as $d): ?>
                        <div class="bg-gray-50 border border-gray-100 p-4 rounded-xl flex flex-col justify-between">
                            <div>
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h3 class="font-black text-gray-800 text-base flex items-center gap-1.5">
                                            <i class="fa-solid fa-helmet-safety text-amber-500"></i> <?php echo htmlspecialchars($d['name']); ?>
                                        </h3>
                                        <span class="text-xs text-gray-500 block">📞 <?php echo htmlspecialchars($d['phone'] ?: 'بدون هاتف'); ?> | رمز: <code class="bg-gray-200 px-1.5 py-0.5 rounded text-gray-800 font-mono"><?php echo htmlspecialchars($d['pin_code']); ?></code></span>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold <?php echo $d['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'; ?>">
                                        <?php echo $d['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                    </span>
                                </div>
                                <div class="mt-3 bg-white p-2.5 rounded-lg border flex justify-between items-center">
                                    <span class="text-xs font-bold text-gray-600">العهدة النقدية بيده:</span>
                                    <span class="text-sm font-black text-amber-600"><?php echo number_format($d['cash_balance'], 2); ?> ج.م</span>
                                </div>
                            </div>
                            <form method="POST" class="mt-3 flex gap-2">
                                <input type="hidden" name="driver_id" value="<?php echo $d['id']; ?>">
                                <input type="number" step="0.01" name="amount" placeholder="المبلغ المورد" class="w-full border rounded-lg px-2.5 py-1 text-xs">
                                <button type="submit" name="settle_driver" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-1 rounded-lg text-xs whitespace-nowrap">
                                    تصفية
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- جدول الطلبات النشطة وإسناد الطيارين -->
    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <h2 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-blue-600"></i> طلبات الدليفري الجارية وتعيين الطيار بالاسم
        </h2>
        <div class="overflow-x-auto">
            <table class="w-full text-right text-xs">
                <thead class="bg-gray-50 text-gray-500 font-bold border-b">
                    <tr>
                        <th class="py-3 px-3">رقم الطلب</th>
                        <th class="py-3 px-3">اسم العميل</th>
                        <th class="py-3 px-3">العنوان والمعالم</th>
                        <th class="py-3 px-3">الإجمالي</th>
                        <th class="py-3 px-3">رسوم الدليفري</th>
                        <th class="py-3 px-3">الطيار المعين</th>
                        <th class="py-3 px-3">الحالة</th>
                        <th class="py-3 px-3 text-center">إسناد طيار</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($active_delivery_orders)): ?>
                        <tr><td colspan="8" class="text-center py-8 text-gray-400">لا توجد طلبات جارية بحاجة لدليفري حالياً.</td></tr>
                    <?php else: ?>
                        <?php foreach ($active_delivery_orders as $o): ?>
                            <tr class="hover:bg-gray-50/80 transition-colors">
                                <td class="py-3 px-3 font-bold text-gray-800">#<?php echo $o['id']; ?></td>
                                <td class="py-3 px-3 font-black text-gray-900"><?php echo htmlspecialchars($o['customer_name']); ?></td>
                                <td class="py-3 px-3 text-gray-600"><?php echo htmlspecialchars($o['address'] ?: ($o['governorate'] ?? '-')); ?></td>
                                <td class="py-3 px-3 font-black text-emerald-600"><?php echo number_format($o['total_price'], 2); ?> ج.م</td>
                                <td class="py-3 px-3 text-amber-600 font-bold"><?php echo number_format($o['delivery_fee'], 2); ?> ج.م</td>
                                <td class="py-3 px-3 font-black text-blue-700">
                                    <?php echo htmlspecialchars($o['delivery_person'] ?: 'لم يحدد بعد'); ?>
                                </td>
                                <td class="py-3 px-3">
                                    <span class="bg-amber-100 text-amber-800 px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($o['status']); ?></span>
                                </td>
                                <td class="py-3 px-3 text-center">
                                    <form method="POST" class="inline-flex gap-1 items-center">
                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                        <select name="driver_name" required class="border rounded-lg px-2 py-1 text-xs">
                                            <option value="">اختر الطيار...</option>
                                            <?php foreach ($drivers as $dr): ?>
                                                <option value="<?php echo htmlspecialchars($dr['name']); ?>" <?php echo $o['delivery_person'] == $dr['name'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dr['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="assign_order" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-2.5 py-1 rounded-lg text-xs">
                                            إسناد
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
