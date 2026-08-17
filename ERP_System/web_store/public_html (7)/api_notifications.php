<?php
// API للإشعارات - يستدعيه تطبيق الأندرويد
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');



// جلب آخر الإشعارات بعد تاريخ معين
$since = isset($_GET['since']) ? trim($_GET['since']) : '';
$limit = isset($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 10;

if (!empty($since)) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE created_at > ? ORDER BY id DESC LIMIT " . (int)$limit);
    $stmt->execute([$since]);
} else {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT " . (int)$limit);
}

$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'count' => count($notifications),
    'notifications' => $notifications
], JSON_UNESCAPED_UNICODE);
