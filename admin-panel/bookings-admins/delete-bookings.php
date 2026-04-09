<?php
session_start();
require_once __DIR__ . "/../../config/config.php";

if (!isset($_SESSION['admin_name'])) {
    header("Location: " . url . "/admin-panel/admins/login.php");
    exit();
}

$booking_id = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($booking_id <= 0) {
    header("Location: " . url . "/admin-panel/bookings-admins/show-bookings.php");
    exit();
}

mysqli_query($conn, "DELETE FROM bookings WHERE id = {$booking_id}") or die("Query Unsuccessful");

header("Location: " . url . "/admin-panel/bookings-admins/show-bookings.php?deleted=1");
exit();
