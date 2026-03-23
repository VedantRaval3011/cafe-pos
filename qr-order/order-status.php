<?php
// JSON API: returns order status + item types for AJAX polling
session_start();
require_once __DIR__ . "/../config/config.php";

header("Content-Type: application/json");

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
  echo json_encode(["error" => "Invalid order"]);
  exit();
}

$orderRes = mysqli_query($conn, "SELECT id, status, payment_status, table_number, total_price, invoice_number, invoice_pdf_path FROM orders WHERE id = {$order_id} LIMIT 1");
if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
  echo json_encode(["error" => "Order not found"]);
  exit();
}

$order = mysqli_fetch_assoc($orderRes);

$itemsRes = mysqli_query($conn, "SELECT oi.name, oi.size, oi.quantity, oi.price, oi.line_total, oi.item_status, p.type
  FROM order_items oi
  LEFT JOIN products p ON oi.product_id = p.id
  WHERE oi.order_id = {$order_id}");

$items = [];
$types = [];
while ($it = mysqli_fetch_assoc($itemsRes)) {
  $items[] = $it;
  $t = strtolower(trim($it['type'] ?? 'coffee'));
  if (!in_array($t, $types)) $types[] = $t;
}

echo json_encode([
  "order_id" => (int)$order['id'],
  "status" => $order['status'],
  "payment_status" => $order['payment_status'],
  "table_number" => $order['table_number'] ? (int)$order['table_number'] : null,
  "total_price" => (float)$order['total_price'],
  "invoice_number" => $order['invoice_number'],
  "invoice_pdf_path" => $order['invoice_pdf_path'],
  "items" => $items,
  "item_types" => $types
]);
