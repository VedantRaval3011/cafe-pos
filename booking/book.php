<?php
/**
 * Creates a table booking and sends the user to Razorpay checkout (or My Bookings if fee is ₹0).
 * No HTML output — must stay before any includes that print markup.
 */
session_start();
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit'])) {
    header('Location: ' . url . '/index.php');
    exit;
}

$returnTo = $_POST['return_to'] ?? 'index';
$returnPath = ($returnTo === 'menu') ? '/menu.php' : '/index.php';
$returnHash = '#ftco-intro';

if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_booking'] = ['type' => 'error', 'msg' => 'Please log in to book a table.'];
    header('Location: ' . url . $returnPath . $returnHash);
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$date = trim($_POST['date'] ?? '');
$time = trim($_POST['time'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');
$userId = (int)$_SESSION['user_id'];

if ($firstName === '' || $date === '' || $time === '' || $phone === '' || $email === '') {
    $_SESSION['flash_booking'] = ['type' => 'error', 'msg' => 'Please fill first name, date, time, phone and email.'];
    header('Location: ' . url . $returnPath . $returnHash);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['flash_booking'] = ['type' => 'error', 'msg' => 'Please enter a valid email address.'];
    header('Location: ' . url . $returnPath . $returnHash);
    exit;
}

// Date: allow today or future (m/d/yyyy from datepicker)
$bookingTs = strtotime($date);
$todayStart = strtotime('today');
if ($bookingTs === false || $bookingTs < $todayStart) {
    $_SESSION['flash_booking'] = ['type' => 'error', 'msg' => 'Please choose today or a future date.'];
    header('Location: ' . url . $returnPath . $returnHash);
    exit;
}

$fee = BOOKING_FEE_INR;
$paymentStatus = ($fee <= 0) ? 'paid' : 'unpaid';
$status = ($fee <= 0) ? 'pending' : 'awaiting_payment';
$amountSql = number_format($fee, 2, '.', '');
$paidAtSql = ($fee <= 0) ? 'NOW()' : 'NULL';

$fnEsc = mysqli_real_escape_string($conn, $firstName);
$lnEsc = mysqli_real_escape_string($conn, $lastName);
$dateEsc = mysqli_real_escape_string($conn, $date);
$timeEsc = mysqli_real_escape_string($conn, $time);
$phoneEsc = mysqli_real_escape_string($conn, $phone);
$emailEsc = mysqli_real_escape_string($conn, $email);
$msgEsc = mysqli_real_escape_string($conn, $message);

$sql = "INSERT INTO bookings (first_name, last_name, date, time, phone, email, message, amount, payment_status, status, user_id, paid_at)
        VALUES ('{$fnEsc}','{$lnEsc}','{$dateEsc}','{$timeEsc}','{$phoneEsc}','{$emailEsc}','{$msgEsc}','{$amountSql}','{$paymentStatus}','{$status}',{$userId}, {$paidAtSql})";

if (!mysqli_query($conn, $sql)) {
    $_SESSION['flash_booking'] = [
        'type' => 'error',
        'msg' => 'Could not save booking. If you just upgraded the app, run the SQL migration: db/migrations/2026-04-08_booking_razorpay.sql',
    ];
    header('Location: ' . url . $returnPath . $returnHash);
    exit;
}

$bookingId = (int)mysqli_insert_id($conn);

if ($fee <= 0) {
    $_SESSION['flash_booking'] = ['type' => 'ok', 'msg' => 'Your table request is saved. We will confirm shortly.'];
    header('Location: ' . url . '/users/bookings.php');
    exit;
}

header('Location: ' . url . '/booking/pay.php?booking_id=' . $bookingId);
exit;
