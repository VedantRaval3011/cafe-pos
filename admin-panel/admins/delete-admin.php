<?php require_once "../layouts/header.php"; ?>

<?php
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/admin-panel/admins/login.php");
  exit();
}

$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($admin_id <= 0) {
  header("Location: " . url . "/admin-panel/admins/admins.php");
  exit();
}

// prevent deleting yourself
if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $admin_id) {
  echo "<script>alert('You cannot delete your own admin account while logged in.'); window.location.href='" . url . "/admin-panel/admins/admins.php';</script>";
  exit();
}

mysqli_query($conn, "DELETE FROM admins WHERE id = {$admin_id}") or die("Query Unsuccessful");
echo "<script>alert('Admin deleted'); window.location.href='" . url . "/admin-panel/admins/admins.php';</script>";
exit();

