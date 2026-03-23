<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$statusToAnim = [
    'created'   => 'placed',
    'placed'    => 'placed',
    'preparing' => 'preparing',
    'brewing'   => 'cooking',
    'ready'     => 'ready',
];

$priority = [
    'created' => 0, 'placed' => 1,
    'preparing' => 2, 'brewing' => 3, 'ready' => 4,
];

$typeRes = mysqli_query($conn, "SELECT DISTINCT type FROM products");
$stations = [];
$stationPriority = [];
while ($row = mysqli_fetch_assoc($typeRes)) {
    $t = strtolower(trim($row['type']));
    $stations[$t] = ['status' => 'idle', 'orderId' => null, 'item' => null];
    $stationPriority[$t] = -1;
}

// Table filter: if ?table=X is passed, only show that table's orders
$tableFilter = isset($_GET['table']) ? (int)$_GET['table'] : 0;
if ($tableFilter <= 0) {
    $tableFilter = $_SESSION['qr_table_number'] ?? 0;
}
$tableClause = $tableFilter > 0 ? "AND o.table_number = {$tableFilter}" : "";

$query = "
    SELECT o.id AS order_id, o.status AS order_status,
           oi.name AS item_name, oi.item_status AS item_status, p.type AS product_type
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    LEFT JOIN products p ON p.id = oi.product_id
    WHERE o.status IN ('created','placed','preparing','brewing','ready')
    {$tableClause}
    ORDER BY o.created_at DESC
    LIMIT 50
";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $type = strtolower(trim($row['product_type'] ?? ''));
        if (!isset($stations[$type])) continue;

        $lineStatus = strtolower(trim($row['item_status'] ?? ''));
        if ($lineStatus === '' || $lineStatus === 'delivered') {
            continue;
        }
        $dbStatus = $lineStatus;
        $p = $priority[$dbStatus] ?? -1;

        if ($p > $stationPriority[$type]) {
            $stationPriority[$type] = $p;
            $stations[$type] = [
                'status'  => $statusToAnim[$dbStatus] ?? 'idle',
                'orderId' => (int)$row['order_id'],
                'item'    => $row['item_name'],
            ];
        }
    }
    mysqli_free_result($result);
}

echo json_encode([
    'stations'  => $stations,
    'table'     => $tableFilter > 0 ? $tableFilter : null,
    'timestamp' => time(),
]);
