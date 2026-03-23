<?php require_once "layouts/header.php"; ?>

<?php

// If admin is not logged in, redirect to the login page
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/admin-panel/admins/login.php");
  exit();
}

// products
$product_query = "SELECT COUNT(*) AS count FROM products";
$product_result = mysqli_query($conn, $product_query) or die("Query Unsuccessful");
if (mysqli_num_rows($product_result) > 0) {
  $products_count = mysqli_fetch_assoc($product_result);
}

// orders
$order_query = "SELECT COUNT(*) AS count FROM orders";
$order_result = mysqli_query($conn, $order_query) or die("Query Unsuccessful");
if (mysqli_num_rows($order_result) > 0) {
  $orders_count = mysqli_fetch_assoc($order_result);
}

// bookings
$booking_query = "SELECT COUNT(*) AS count FROM bookings";
$booking_result = mysqli_query($conn, $booking_query) or die("Query Unsuccessful");
if (mysqli_num_rows($booking_result) > 0) {
  $bookings_count = mysqli_fetch_assoc($booking_result);
}

// admins
$admin_query = "SELECT COUNT(*) AS count FROM admins";
$admin_result = mysqli_query($conn, $admin_query) or die("Query Unsuccessful");
if (mysqli_num_rows($admin_result) > 0) {
  $admins_count = mysqli_fetch_assoc($admin_result);
}

?>

<div class="container-fluid">
  <div class="row">
    <div class="col-12 mb-3">
      <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
        <h4 style="margin:0;font-weight:900;">Dashboard</h4>
        <div class="text-muted" style="font-weight:700;">Welcome, <?php echo htmlspecialchars($_SESSION['admin_name']); ?></div>
      </div>
    </div>

    <div class="col-md-3">
      <a href="<?php echo ADMINURL; ?>/products-admins/show-products.php" style="text-decoration:none;color:inherit;">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Products</h5>
            <p class="card-text mb-2"><strong><?php echo (int)$products_count['count']; ?></strong> total</p>
            <span class="btn btn-sm btn-outline-primary">Manage</span>
          </div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?php echo ADMINURL; ?>/orders-admins/show-orders.php" style="text-decoration:none;color:inherit;">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Orders</h5>
            <p class="card-text mb-2"><strong><?php echo (int)$orders_count['count']; ?></strong> total</p>
            <span class="btn btn-sm btn-outline-primary">Manage</span>
          </div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?php echo ADMINURL; ?>/bookings-admins/show-bookings.php" style="text-decoration:none;color:inherit;">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Bookings</h5>
            <p class="card-text mb-2"><strong><?php echo (int)$bookings_count['count']; ?></strong> total</p>
            <span class="btn btn-sm btn-outline-primary">Manage</span>
          </div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a href="<?php echo ADMINURL; ?>/admins/admins.php" style="text-decoration:none;color:inherit;">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Admins</h5>
            <p class="card-text mb-2"><strong><?php echo (int)$admins_count['count']; ?></strong> total</p>
            <span class="btn btn-sm btn-outline-primary">Manage</span>
          </div>
        </div>
      </a>
    </div>
  </div>
</div>
</div>
</body>

</html>