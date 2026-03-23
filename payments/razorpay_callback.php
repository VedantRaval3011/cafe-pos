<?php
// IMPORTANT: Do NOT include includes/header.php here (it outputs HTML).
// This endpoint must stay output-free so we can redirect with header().
session_start();
require_once __DIR__ . "/../config/config.php";

// Accept POST from Razorpay checkout handler
$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';

if ($order_id <= 0 || !$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
  echo "<script>alert('Payment data missing'); window.location.href='" . url . "/index.php';</script>";
  exit();
}

// Fetch order
$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($orderRes) === 0) {
  echo "<script>alert('Order not found'); window.location.href='" . url . "/index.php';</script>";
  exit();
}
$order = mysqli_fetch_assoc($orderRes);

// Ensure ownership (user, QR session, or kitchen guest)
$isUser = isset($_SESSION['user_id']);
$isQr = isset($_SESSION['qr_session_token']);
$isKitchenGuest = isset($_SESSION['kitchen_guest_token']);
if (!$isUser && !$isQr && !$isKitchenGuest) {
  header("Location: " . url . "/index.php");
  exit();
}
if ($isUser && (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
  header("Location: " . url . "/index.php");
  exit();
}
$sessionToken = $_SESSION['qr_session_token'] ?? $_SESSION['kitchen_guest_token'] ?? null;
if (!$isUser && $sessionToken && ($order['session_token'] ?? '') !== $sessionToken) {
  header("Location: " . url . "/index.php");
  exit();
}

// Verify signature (HMAC SHA256 of order_id|payment_id with secret)
$payload = $razorpay_order_id . "|" . $razorpay_payment_id;
$expected = hash_hmac('sha256', $payload, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $razorpay_signature)) {
  mysqli_query($conn, "UPDATE orders SET payment_status='failed', status='payment_failed' WHERE id={$order_id}") or die("Query Unsuccessful");
  $fallback = isset($_SESSION['qr_session_token']) ? (url . "/qr-order/cart.php") : (url . "/products/cart.php");
  echo "<script>alert('Payment verification failed'); window.location.href='" . $fallback . "';</script>";
  exit();
}

// Mark paid and save payment ids
$rzpPayEsc = mysqli_real_escape_string($conn, $razorpay_payment_id);
$rzpOrdEsc = mysqli_real_escape_string($conn, $razorpay_order_id);
$rzpSigEsc = mysqli_real_escape_string($conn, $razorpay_signature);

// Create an invoice number (simple demo format)
$invoice_number = "INV-" . date("Ymd") . "-" . $order_id;
$invEsc = mysqli_real_escape_string($conn, $invoice_number);

mysqli_query(
  $conn,
  "UPDATE orders
     SET payment_status='paid',
         status='preparing',
         payment_provider='razorpay',
         razorpay_payment_id='{$rzpPayEsc}',
         razorpay_order_id='{$rzpOrdEsc}',
         razorpay_signature='{$rzpSigEsc}',
         invoice_number='{$invEsc}',
         paid_at=NOW()
   WHERE id={$order_id}"
) or die("Query Unsuccessful");

mysqli_query($conn, "UPDATE order_items SET item_status='preparing' WHERE order_id={$order_id}") or die("Query Unsuccessful");

// Clear cart for this user/session
if (!empty($order['user_id'])) {
  $uid = (int)$order['user_id'];
  mysqli_query($conn, "DELETE FROM cart WHERE user_id = {$uid}") or die("Query Unsuccessful");
} elseif (!empty($order['session_token'])) {
  $tok = mysqli_real_escape_string($conn, $order['session_token']);
  mysqli_query($conn, "DELETE FROM cart WHERE session_token = '{$tok}'") or die("Query Unsuccessful");
}

// Generate PDF + send email if libraries exist (Composer)
require_once __DIR__ . "/invoice_service.php";
[$pdfPath, $emailSent] = generate_and_send_invoice($conn, $order_id);

// Redirect to 3D live kitchen tracking
if (isset($_SESSION['qr_session_token'])) {
  header("Location: " . url . "/kitchen3d/?order_id=" . $order_id);
  exit();
}

header("Location: " . url . "/kitchen3d/?order_id=" . $order_id . "&email_sent=" . ($emailSent ? "1" : "0"));
exit();

