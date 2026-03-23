<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}

$orderId     = (int)($_POST['order_id'] ?? 0);
$rzpPayId    = $_POST['razorpay_payment_id'] ?? '';
$rzpOrderId  = $_POST['razorpay_order_id'] ?? '';
$rzpSig      = $_POST['razorpay_signature'] ?? '';

if ($orderId <= 0 || !$rzpPayId || !$rzpOrderId || !$rzpSig) {
    echo json_encode(['error' => 'Missing payment data']);
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

$payload = $rzpOrderId . '|' . $rzpPayId;
$expected = hash_hmac('sha256', $payload, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $rzpSig)) {
    mysqli_query($conn, "UPDATE orders SET payment_status='failed', status='payment_failed' WHERE id={$orderId}");
    echo json_encode(['error' => 'Signature verification failed']);
    exit;
}

$rzpPayEsc = mysqli_real_escape_string($conn, $rzpPayId);
$rzpOrdEsc = mysqli_real_escape_string($conn, $rzpOrderId);
$rzpSigEsc = mysqli_real_escape_string($conn, $rzpSig);
$invoiceNo = 'INV-' . date('Ymd') . '-' . $orderId;
$invEsc    = mysqli_real_escape_string($conn, $invoiceNo);

mysqli_query($conn,
    "UPDATE orders
     SET payment_status='paid', status='preparing', payment_provider='razorpay',
         razorpay_payment_id='{$rzpPayEsc}', razorpay_order_id='{$rzpOrdEsc}',
         razorpay_signature='{$rzpSigEsc}', invoice_number='{$invEsc}', paid_at=NOW()
     WHERE id={$orderId}"
);
mysqli_query($conn, "UPDATE order_items SET item_status='preparing' WHERE order_id={$orderId}");

if (!empty($order['user_id'])) {
    mysqli_query($conn, "DELETE FROM cart WHERE user_id = " . (int)$order['user_id']);
} elseif (!empty($order['session_token'])) {
    $tok = mysqli_real_escape_string($conn, $order['session_token']);
    mysqli_query($conn, "DELETE FROM cart WHERE session_token = '{$tok}'");
}

$invoicePath = null;
$emailSent = false;
$invoiceServicePath = __DIR__ . '/../../payments/invoice_service.php';
if (file_exists($invoiceServicePath)) {
    require_once $invoiceServicePath;
    [$invoicePath, $emailSent] = generate_and_send_invoice($conn, $orderId);
}

echo json_encode([
    'success'    => true,
    'order_id'   => $orderId,
    'email_sent' => $emailSent,
]);
