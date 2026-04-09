<?php
session_start();
require_once __DIR__ . '/../config/config.php';

$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$razorpayPaymentId = $_POST['razorpay_payment_id'] ?? '';
$razorpayOrderId = $_POST['razorpay_order_id'] ?? '';
$razorpaySignature = $_POST['razorpay_signature'] ?? '';

if ($bookingId <= 0 || !$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
    echo "<script>alert('Payment data missing'); window.location.href='" . htmlspecialchars(url) . "/users/bookings.php';</script>";
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url . '/auth/login.php');
    exit;
}

$res = mysqli_query($conn, "SELECT * FROM bookings WHERE id = {$bookingId} LIMIT 1") or die('Query Unsuccessful');
if (mysqli_num_rows($res) === 0) {
    echo "<script>alert('Booking not found'); window.location.href='" . htmlspecialchars(url) . "/users/bookings.php';</script>";
    exit;
}
$booking = mysqli_fetch_assoc($res);

if ((int)$booking['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: ' . url . '/index.php');
    exit;
}

if (($booking['payment_status'] ?? '') === 'paid') {
    header('Location: ' . url . '/users/bookings.php');
    exit;
}

$payload = $razorpayOrderId . '|' . $razorpayPaymentId;
$expected = hash_hmac('sha256', $payload, RAZORPAY_KEY_SECRET);
if (!hash_equals($expected, $razorpaySignature)) {
    mysqli_query($conn, "UPDATE bookings SET payment_status='failed' WHERE id={$bookingId}") or die('Query Unsuccessful');
    echo "<script>alert('Payment verification failed'); window.location.href='" . htmlspecialchars(url) . "/booking/pay.php?booking_id={$bookingId}';</script>";
    exit;
}

$payEsc = mysqli_real_escape_string($conn, $razorpayPaymentId);
$ordEsc = mysqli_real_escape_string($conn, $razorpayOrderId);
$sigEsc = mysqli_real_escape_string($conn, $razorpaySignature);

mysqli_query(
    $conn,
    "UPDATE bookings SET
        payment_status='paid',
        status='pending',
        razorpay_payment_id='{$payEsc}',
        razorpay_order_id='{$ordEsc}',
        razorpay_signature='{$sigEsc}',
        paid_at=NOW()
     WHERE id={$bookingId}"
) or die('Query Unsuccessful');

$_SESSION['flash_booking'] = ['type' => 'ok', 'msg' => 'Payment successful. We will confirm your table shortly.'];

header('Location: ' . url . '/users/bookings.php');
exit;
