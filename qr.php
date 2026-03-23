<?php
require_once __DIR__ . "/includes/header.php";

// QR entry point:
// Example: http://localhost/coffee/qr.php?table=5
$table = isset($_GET['table']) ? (int)$_GET['table'] : 0;
if ($table <= 0) {
  echo "<script>alert('Invalid table number'); window.location.href = '" . url . "/index.php';</script>";
  exit();
}

// If user scans a DIFFERENT table in same browser, create a new session token
// so each table has its own separate "Order".
$prevTable = $_SESSION['qr_table_number'] ?? null;
if (!isset($_SESSION['qr_session_token']) || ($prevTable !== null && (int)$prevTable !== (int)$table)) {
  // 32 bytes => 64 hex chars
  $_SESSION['qr_session_token'] = bin2hex(random_bytes(32));
}
$_SESSION['qr_table_number'] = (int)$table;

echo "<script>
  window.location.href = '" . url . "/qr-order/menu.php';
</script>";
exit();

