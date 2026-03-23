<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST only']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$sessionToken = $_SESSION['qr_session_token'] ?? $_SESSION['kitchen_guest_token'] ?? null;
$tableNumber = $_SESSION['qr_table_number'] ?? null;

if (!$userId && !$sessionToken) {
    echo json_encode(['error' => 'No session — refresh the page']);
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$email     = trim($_POST['email'] ?? '');

if ($firstName === '' || $phone === '' || $email === '') {
    echo json_encode(['error' => 'Name, phone and email are required']);
    exit;
}

$where = $userId
    ? "user_id = " . (int)$userId
    : "session_token = '" . mysqli_real_escape_string($conn, $sessionToken) . "'";

$cartRes = mysqli_query($conn, "SELECT * FROM cart WHERE {$where}");
if (!$cartRes || mysqli_num_rows($cartRes) === 0) {
    echo json_encode(['error' => 'Cart is empty']);
    exit;
}

$cartRows = [];
$total = 0;
while ($row = mysqli_fetch_assoc($cartRes)) {
    $cartRows[] = $row;
    $total += (float)$row['price'] * (int)$row['quantity'];
}
$total = round($total, 2);

$fnEsc = mysqli_real_escape_string($conn, $firstName);
$lnEsc = mysqli_real_escape_string($conn, $lastName);
$phEsc = mysqli_real_escape_string($conn, $phone);
$emEsc = mysqli_real_escape_string($conn, $email);
$uidSql = $userId ? (int)$userId : "NULL";
$tokSql = $sessionToken ? "'" . mysqli_real_escape_string($conn, $sessionToken) . "'" : "NULL";
$tblSql = $tableNumber ? (int)$tableNumber : "NULL";

mysqli_query($conn,
    "INSERT INTO orders (first_name, last_name, country, street_address, town, zip_code, phone, email,
                         user_id, session_token, table_number, status, payment_status, total_price)
     VALUES ('{$fnEsc}','{$lnEsc}','','','','','{$phEsc}','{$emEsc}',
             {$uidSql},{$tokSql},{$tblSql},'placed','unpaid','{$total}')"
) or die(json_encode(['error' => 'DB error creating order']));

$orderId = mysqli_insert_id($conn);

foreach ($cartRows as $c) {
    $p = (float)$c['price'];
    $q = (int)$c['quantity'];
    $line = round($p * $q, 2);
    $nEsc = mysqli_real_escape_string($conn, $c['name']);
    $iEsc = mysqli_real_escape_string($conn, $c['image']);
    $dEsc = mysqli_real_escape_string($conn, $c['description'] ?? '');
    $sEsc = mysqli_real_escape_string($conn, $c['size']);
    $pid  = (int)$c['product_id'];

    mysqli_query($conn,
        "INSERT INTO order_items (order_id, product_id, name, image, price, size, quantity, line_total, item_status)
         VALUES ({$orderId}, {$pid}, '{$nEsc}', '{$iEsc}', {$p}, '{$sEsc}', {$q}, {$line}, 'placed')"
    );
}

// Clear cart
mysqli_query($conn, "DELETE FROM cart WHERE {$where}");

$_SESSION['total_price'] = $total;
$_SESSION['pending_order_id'] = $orderId;

echo json_encode([
    'order_id'       => $orderId,
    'total'          => $total,
    'payment_status' => 'unpaid',
]);
