<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
header('Content-Type: application/json');

$result = mysqli_query($conn, "SELECT * FROM products ORDER BY type, name");
$grouped = [];
while ($row = mysqli_fetch_assoc($result)) {
    $type = $row['type'];
    if (!isset($grouped[$type])) $grouped[$type] = [];
    $grouped[$type][] = [
        'id'          => (int)$row['id'],
        'name'        => $row['name'],
        'image'       => $row['image'],
        'description' => $row['description'],
        'price'       => $row['price'],
    ];
}

$categories = [];
foreach ($grouped as $type => $products) {
    $categories[] = ['type' => $type, 'products' => $products];
}

echo json_encode(['categories' => $categories]);
