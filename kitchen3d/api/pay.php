<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if ($orderId <= 0) {
    echo json_encode(['error' => 'Invalid order']);
    exit;
}

$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$orderId} LIMIT 1");
if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
    echo json_encode(['error' => 'Order not found']);
    exit;
}
$order = mysqli_fetch_assoc($orderRes);

$userId = $_SESSION['user_id'] ?? null;
$sessionToken = $_SESSION['qr_session_token'] ?? $_SESSION['kitchen_guest_token'] ?? null;
$isOwner = false;
if ($userId && (int)$order['user_id'] === (int)$userId) $isOwner = true;
if ($sessionToken && ($order['session_token'] ?? '') === $sessionToken) $isOwner = true;
if (!$isOwner) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (($order['payment_status'] ?? '') === 'paid') {
    echo json_encode(['error' => 'Already paid']);
    exit;
}

$rzpOrderId = $order['razorpay_order_id'] ?? null;
if (!$rzpOrderId || $rzpOrderId === '') {
    $amountPaise = (int)round(((float)$order['total_price']) * 100);
    $payload = json_encode([
        'amount'          => $amountPaise,
        'currency'        => 'INR',
        'receipt'         => 'order_' . $orderId,
        'payment_capture' => 1,
        'notes'           => [
            'order_id'     => (string)$orderId,
            'table_number' => (string)($order['table_number'] ?? ''),
        ],
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        echo json_encode(['error' => 'Razorpay order creation failed']);
        exit;
    }

    $data = json_decode($resp, true);
    $rzpOrderId = $data['id'] ?? null;
    if (!$rzpOrderId) {
        echo json_encode(['error' => 'Invalid Razorpay response']);
        exit;
    }

    $rzpEsc = mysqli_real_escape_string($conn, $rzpOrderId);
    mysqli_query($conn, "UPDATE orders SET payment_provider='razorpay', razorpay_order_id='{$rzpEsc}' WHERE id={$orderId}");
    $amountPaise = $amountPaise;
} else {
    $amountPaise = (int)round(((float)$order['total_price']) * 100);
}

echo json_encode([
    'order_id'          => $orderId,
    'razorpay_order_id' => $rzpOrderId,
    'amount'            => $amountPaise,
    'currency'          => 'INR',
    'key_id'            => RAZORPAY_KEY_ID,
]);
