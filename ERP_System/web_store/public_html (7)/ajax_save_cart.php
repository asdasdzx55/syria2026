<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['customer_phone'] ?? '');
    $email = trim($_POST['customer_email'] ?? '');
    $gov = trim($_POST['governorate'] ?? '');

    $cart_id = saveOrUpdateAbandonedCart($name, $phone, $email, $gov);

    if ($cart_id) {
        echo json_encode(['success' => true, 'cart_id' => $cart_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Cart is empty or could not be saved']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
