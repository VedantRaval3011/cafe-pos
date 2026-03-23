<?php require_once "../layouts/header.php"; ?>

<?php

// if admin not logged in
// denied to access orders page
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/index.php");
  exit();
}

//fetch all orders from db (latest first)
$order_query = "SELECT * FROM orders ORDER BY id DESC";
$query_result = mysqli_query($conn, $order_query) or die("Query Unsuccessful");

?>

<script>
  // auto-refresh every 10s for kitchen/admin view
  setTimeout(() => window.location.reload(), 10000);
</script>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
            <h5 class="card-title mb-0">Orders</h5>
            <div class="text-muted" style="font-weight:700;">Auto-refresh: 10s</div>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th class="text-center">Order Id</th>
                  <th class="text-center">First Name</th>
                  <th class="text-center">Last Name</th>
                  <th class="text-center">Cust. Id</th>
                  <th class="text-center">Table</th>
                  <th class="text-center">Street Address</th>
                  <th class="text-center">State</th>
                  <th class="text-center">Zip Code</th>
                  <th class="text-center">Phone</th>
                  <th class="text-center">Total Price</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Payment</th>
                  <th class="text-center">Invoice</th>
                  <th class="text-center">Update Status</th>
                  <th class="text-center">Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (mysqli_num_rows($query_result) > 0) {
                  while ($orders = mysqli_fetch_assoc($query_result)) {
                ?>
                    <tr>
                      <td class="text-center"><?php echo $orders["id"]; ?></td>
                      <td class="text-center"><?php echo $orders["first_name"]; ?></td>
                      <td class="text-center"><?php echo $orders["last_name"]; ?></td>
                      <td class="text-center"><?php echo $orders["user_id"]; ?></td>
                      <td class="text-center"><?php echo $orders["table_number"] ? (int)$orders["table_number"] : "-"; ?></td>
                      <td class="text-center"><?php echo $orders["street_address"]; ?></td>
                      <td class="text-center"><?php echo $orders["town"]; ?></td>
                      <td class="text-center"><?php echo $orders["zip_code"]; ?></td>
                      <td class="text-center"><?php echo $orders["phone"]; ?></td>
                      <td class="text-center">$<?php echo $orders["total_price"]; ?></td>
                      <td class="text-center">
                        <?php
                          $s = $orders["status"];
                          $badges = [
                            "placed" => "badge-secondary", "created" => "badge-secondary",
                            "preparing" => "badge-primary", "brewing" => "badge-warning",
                            "ready" => "badge-success", "delivered" => "badge-info",
                            "cancelled" => "badge-danger", "payment_failed" => "badge-danger"
                          ];
                          $bc = $badges[$s] ?? "badge-secondary";
                        ?>
                        <span class="badge <?php echo $bc; ?>" style="font-size:12px;"><?php echo htmlspecialchars($s); ?></span>
                      </td>
                      <td class="text-center"><?php echo $orders["payment_status"] ?? "-"; ?></td>
                      <td class="text-center"><?php echo $orders["invoice_number"] ?? "-"; ?></td>
                      <td class="text-center"><a href="update-status.php?id=<?php echo $orders['id']; ?>" class="btn btn-sm btn-outline-primary">Update</a></td>
                      <td class="text-center"><a onclick="return confirm('Delete this order?');" href="delete-orders.php?id=<?php echo $orders['id']; ?>" class="btn btn-sm btn-outline-danger">Delete</a></td>
                    </tr>
                <?php }
                } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>