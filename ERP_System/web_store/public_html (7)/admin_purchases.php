<?php
require_once 'config.php';

if (!isAdmin()) {
    header('Location: login.php');
    exit;
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT DEFAULT NULL,
        supplier_name VARCHAR(255) DEFAULT NULL,
        invoice_number VARCHAR(100) DEFAULT NULL,
        payment_method VARCHAR(100) DEFAULT 'نقدي',
        total_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        paid_amount DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
        date DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(50) DEFAULT 'مكتملة',
        discount DECIMAL(12, 2) DEFAULT 0.00,
        source VARCHAR(50) DEFAULT 'web_pos',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {}

$purchases = $pdo->query("SELECT p.*, (SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id = p.id) as items_count FROM purchases p ORDER BY p.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);

$total_purchases_val = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM purchases WHERE status != 'مرتجع'")->fetchColumn() ?: 0;
$total_purchases_count = count($purchases);

include 'header.php';
include 'admin_nav.php';
?>

<div class="container mx-auto max-w-6xl px-4 py-8">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6 bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
        <div>
            <h1 class="text-2xl font-black text-royal-charcoal flex items-center gap-2">
                <i class="fa-solid fa-truck-ramp-box text-amber-600"></i> فواتير المشتريات والتوريد
            </h1>
            <p class="text-gray-500 text-sm mt-1">سجل فواتير استلام البضائع والمشتريات المتزامنة من الكاشير والويب</p>
        </div>
        <div class="flex gap-4">
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3 text-center min-w-[120px]">
                <span class="text-xs text-amber-600 font-bold block">إجمالي الفواتير</span>
                <span class="text-xl font-black text-amber-900"><?php echo $total_purchases_count; ?></span>
            </div>
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3 text-center min-w-[140px]">
                <span class="text-xs text-blue-600 font-bold block">إجمالي المشتريات</span>
                <span class="text-xl font-black text-blue-700"><?php echo number_format($total_purchases_val, 2); ?> ج.م</span>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 overflow-x-auto">
        <table class="w-full text-right text-xs">
            <thead class="bg-gray-50 text-gray-500 font-bold border-b">
                <tr>
                    <th class="py-3 px-3">رقم الفاتورة</th>
                    <th class="py-3 px-3">المورد</th>
                    <th class="py-3 px-3">طريقة الدفع</th>
                    <th class="py-3 px-3">الإجمالي</th>
                    <th class="py-3 px-3">المدفوع</th>
                    <th class="py-3 px-3">المتبقي (آجل)</th>
                    <th class="py-3 px-3">الأصناف</th>
                    <th class="py-3 px-3">المصدر</th>
                    <th class="py-3 px-3">التاريخ</th>
                    <th class="py-3 px-3">الحالة</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if (empty($purchases)): ?>
                    <tr><td colspan="10" class="text-center py-8 text-gray-400">لا توجد فواتير مشتريات مسجلة بعد.</td></tr>
                <?php else: ?>
                    <?php foreach ($purchases as $p): 
                        $rem = (float)$p['total_amount'] - (float)$p['paid_amount'];
                    ?>
                        <tr class="hover:bg-gray-50/80 transition-colors">
                            <td class="py-3 px-3 font-bold text-gray-800"><?php echo htmlspecialchars($p['invoice_number'] ?: ('INV-' . $p['id'])); ?></td>
                            <td class="py-3 px-3 font-black text-blue-700"><?php echo htmlspecialchars($p['supplier_name'] ?: 'مورد عام'); ?></td>
                            <td class="py-3 px-3"><span class="bg-gray-100 text-gray-700 px-2 py-0.5 rounded font-bold"><?php echo htmlspecialchars($p['payment_method']); ?></span></td>
                            <td class="py-3 px-3 font-black text-gray-900"><?php echo number_format($p['total_amount'], 2); ?> ج.م</td>
                            <td class="py-3 px-3 text-emerald-600 font-bold"><?php echo number_format($p['paid_amount'], 2); ?> ج.م</td>
                            <td class="py-3 px-3 font-black <?php echo $rem > 0 ? 'text-red-600' : 'text-gray-400'; ?>">
                                <?php echo number_format($rem, 2); ?> ج.م
                            </td>
                            <td class="py-3 px-3"><span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full font-bold"><?php echo $p['items_count']; ?> صنف</span></td>
                            <td class="py-3 px-3 text-gray-500 font-semibold"><?php echo htmlspecialchars($p['source'] == 'desktop_pos' ? 'كاشير المحل' : 'كاشير الويب'); ?></td>
                            <td class="py-3 px-3 text-gray-400"><?php echo htmlspecialchars($p['date']); ?></td>
                            <td class="py-3 px-3">
                                <?php if ($p['status'] == 'مرتجع'): ?>
                                    <span class="bg-red-100 text-red-700 px-2 py-0.5 rounded font-bold">مرتجع</span>
                                <?php else: ?>
                                    <span class="bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded font-bold">مكتملة</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
