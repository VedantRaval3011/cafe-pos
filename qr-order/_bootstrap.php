<?php
// QR ordering bootstrap (NO HTML OUTPUT here)
session_start();
require_once __DIR__ . "/../config/config.php";

// QR ordering pages must have a table session
if (!isset($_SESSION['qr_session_token']) || !isset($_SESSION['qr_table_number'])) {
  header("Location: " . url . "/index.php");
  exit();
}

