<?php require_once "../layouts/header.php"; ?>

<?php
if (!isset($_SESSION['admin_name'])) {
    header("Location: " . url . "/index.php");
    exit();
}

$booking_query = "SELECT * FROM bookings ORDER BY created_at DESC, id DESC";
$query_result = mysqli_query($conn, $booking_query) or die("Query Unsuccessful");

?>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
            <h5 class="card-title mb-0">Table bookings (online)</h5>
            <small class="text-muted">Confirm tables after payment is <strong>Paid</strong>.</small>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
              <thead>
                <tr>
                  <th class="text-center">ID</th>
                  <th class="text-center">Customer</th>
                  <th class="text-center">User ID</th>
                  <th class="text-center">Email</th>
                  <th class="text-center">Date</th>
                  <th class="text-center">Time</th>
                  <th class="text-center">Phone</th>
                  <th class="text-center">Fee</th>
                  <th class="text-center">Payment</th>
                  <th class="text-center">Razorpay</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Update</th>
                  <th class="text-center">Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (mysqli_num_rows($query_result) > 0) {
                  while ($booking = mysqli_fetch_assoc($query_result)) {
                    $pay = $booking['payment_status'] ?? 'unpaid';
                    $badgePay = 'secondary';
                    if ($pay === 'paid') {
                        $badgePay = 'success';
                    } elseif ($pay === 'failed') {
                        $badgePay = 'danger';
                    } elseif ($pay === 'unpaid') {
                        $badgePay = 'warning';
                    }
                    $amt = isset($booking['amount']) ? (float)$booking['amount'] : 0;
                    $rzp = $booking['razorpay_payment_id'] ?? '';
                    $rzpShort = $rzp !== '' ? htmlspecialchars(substr($rzp, 0, 12)) . '…' : '—';
                ?>
                    <tr>
                      <td class="text-center"><?php echo (int)$booking["id"]; ?></td>
                      <td class="text-center"><?php echo htmlspecialchars($booking["first_name"] . ' ' . $booking["last_name"]); ?></td>
                      <td class="text-center"><?php echo (int)$booking["user_id"]; ?></td>
                      <td class="text-center" style="max-width:140px;word-break:break-all;"><?php echo htmlspecialchars($booking['email'] ?? ''); ?></td>
                      <td class="text-center"><?php echo htmlspecialchars($booking["date"]); ?></td>
                      <td class="text-center"><?php echo htmlspecialchars($booking["time"]); ?></td>
                      <td class="text-center"><?php echo htmlspecialchars($booking["phone"]); ?></td>
                      <td class="text-center"><?php echo $amt > 0 ? '₹' . htmlspecialchars(number_format($amt, 2)) : '—'; ?></td>
                      <td class="text-center"><span class="badge badge-<?php echo $badgePay; ?>" style="font-size:11px;"><?php echo htmlspecialchars($pay); ?></span></td>
                      <td class="text-center" style="font-size:11px;" title="<?php echo htmlspecialchars($rzp); ?>"><?php echo $rzpShort; ?></td>
                      <td class="text-center"><span class="badge badge-info" style="font-size:11px;"><?php echo htmlspecialchars($booking["status"]); ?></span></td>
                      <td class="text-center"><a href="update-status.php?id=<?php echo (int)$booking['id']; ?>" class="btn btn-sm btn-outline-primary">Update</a></td>
                      <td class="text-center"><a onclick="return confirm('Delete this booking?');" href="delete-bookings.php?id=<?php echo (int)$booking['id']; ?>" class="btn btn-sm btn-outline-danger">Delete</a></td>
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
