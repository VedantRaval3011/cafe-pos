<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
$sessionToken = $_SESSION['qr_session_token'] ?? $_SESSION['kitchen_guest_token'] ?? null;
$tableNumber = $_SESSION['qr_table_number'] ?? null;

if (!$userId && !$sessionToken) {
    $sessionToken = bin2hex(random_bytes(32));
    $_SESSION['kitchen_guest_token'] = $sessionToken;
}

$where = $userId
    ? "user_id = " . (int)$userId
    : "session_token = '" . mysqli_real_escape_string($conn, $sessionToken) . "'";

function getCart($conn, $where) {
    $res = mysqli_query($conn, "SELECT c.*, p.type FROM cart c LEFT JOIN products p ON c.product_id = p.id WHERE {$where}");
    $items = [];
    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        $lineTotal = (float)$row['price'] * (int)$row['quantity'];
        $items[] = [
            'id'         => (int)$row['id'],
            'product_id' => (int)$row['product_id'],
            'name'       => $row['name'],
            'image'      => $row['image'],
            'price'      => $row['price'],
            'size'       => $row['size'],
            'quantity'   => (int)$row['quantity'],
            'type'       => $row['type'] ?? 'coffee',
            'line_total' => round($lineTotal, 2),
        ];
        $total += $lineTotal;
    }
    return ['items' => $items, 'count' => count($items), 'total' => round($total, 2)];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(getCart($conn, $where));
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $size = $_POST['size'] ?? 'Medium';
    $quantity = max(1, min(20, (int)($_POST['quantity'] ?? 1)));

    $prodRes = mysqli_query($conn, "SELECT * FROM products WHERE id = {$productId} LIMIT 1");
    if (!$prodRes || mysqli_num_rows($prodRes) === 0) {
        echo json_encode(['error' => 'Product not found']);
        exit;
    }
    $p = mysqli_fetch_assoc($prodRes);
    $sizeEsc = mysqli_real_escape_string($conn, $size);

    $checkWhere = $where . " AND product_id={$productId} AND size='{$sizeEsc}'";
    $check = mysqli_query($conn, "SELECT id, quantity FROM cart WHERE {$checkWhere} LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        $row = mysqli_fetch_assoc($check);
        $newQty = min(50, (int)$row['quantity'] + $quantity);
        mysqli_query($conn, "UPDATE cart SET quantity={$newQty} WHERE id=" . (int)$row['id']);
    } else {
        $nameEsc = mysqli_real_escape_string($conn, $p['name']);
        $imgEsc = mysqli_real_escape_string($conn, $p['image']);
        $descEsc = mysqli_real_escape_string($conn, $p['description']);
        $price = mysqli_real_escape_string($conn, $p['price']);
        $uidSql = $userId ? (int)$userId : "NULL";
        $tokSql = $sessionToken ? "'" . mysqli_real_escape_string($conn, $sessionToken) . "'" : "NULL";
        $tblSql = $tableNumber ? (int)$tableNumber : "NULL";

        mysqli_query($conn,
            "INSERT INTO cart (name, image, price, description, size, quantity, product_id, user_id, session_token, table_number)
             VALUES ('{$nameEsc}','{$imgEsc}','{$price}','{$descEsc}','{$sizeEsc}',{$quantity},{$productId},{$uidSql},{$tokSql},{$tblSql})"
        );
    }
    echo json_encode(getCart($conn, $where));
    exit;
}

if ($action === 'update') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    $qty = (int)($_POST['quantity'] ?? 0);
    if ($qty <= 0) {
        mysqli_query($conn, "DELETE FROM cart WHERE id={$cartId} AND ({$where})");
    } else {
        $qty = min(50, $qty);
        mysqli_query($conn, "UPDATE cart SET quantity={$qty} WHERE id={$cartId} AND ({$where})");
    }
    echo json_encode(getCart($conn, $where));
    exit;
}

if ($action === 'remove') {
    $cartId = (int)($_POST['cart_id'] ?? 0);
    mysqli_query($conn, "DELETE FROM cart WHERE id={$cartId} AND ({$where})");
    echo json_encode(getCart($conn, $where));
    exit;
}

echo json_encode(['error' => 'Invalid action']);
