<?php require_once "../layouts/header.php"; ?>

<?php
if (!isset($_SESSION['admin_name'])) {
    header("Location: " . url . "/admin-panel");
    exit();
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($booking_id <= 0) {
    die("Booking ID not specified.");
}

$bookingRes = mysqli_query($conn, "SELECT * FROM bookings WHERE id = {$booking_id} LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($bookingRes) === 0) {
    die("Booking not found.");
}
$b = mysqli_fetch_assoc($bookingRes);

if (isset($_POST['submit'])) {
    $rawStatus = $_POST['order-status'] ?? '';
    if ($rawStatus === '' || $rawStatus === null) {
        echo "<script>alert('Please select a status.');</script>";
    } else {
        $order_status = mysqli_real_escape_string($conn, $rawStatus);
        mysqli_query($conn, "UPDATE bookings SET status = '{$order_status}' WHERE id = {$booking_id}") or die("Query Unsuccessful");
        echo "<script>alert('Booking status updated.'); window.location.href = '" . url . "/admin-panel/bookings-admins/show-bookings.php'</script>";
    }
}

$pay = $b['payment_status'] ?? 'unpaid';
$amt = isset($b['amount']) ? (float)$b['amount'] : 0;
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Booking #<?php echo (int)$b['id']; ?></h5>
                    <p class="mb-1"><strong>Guest:</strong> <?php echo htmlspecialchars($b['first_name'] . ' ' . $b['last_name']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($b['email'] ?? ''); ?></p>
                    <p class="mb-1"><strong>When:</strong> <?php echo htmlspecialchars($b['date']); ?> at <?php echo htmlspecialchars($b['time']); ?></p>
                    <p class="mb-1"><strong>Phone:</strong> <?php echo htmlspecialchars($b['phone']); ?></p>
                    <p class="mb-1"><strong>Fee:</strong> <?php echo $amt > 0 ? '₹' . htmlspecialchars(number_format($amt, 2)) : '—'; ?></p>
                    <p class="mb-1"><strong>Payment:</strong> <?php echo htmlspecialchars($pay); ?><?php if (!empty($b['paid_at'])) { ?> (<?php echo htmlspecialchars($b['paid_at']); ?>)<?php } ?></p>
                    <p class="mb-1"><strong>Razorpay payment id:</strong> <?php echo htmlspecialchars($b['razorpay_payment_id'] ?? '—'); ?></p>
                    <p class="mb-3"><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($b['message'] ?? '')); ?></p>
                    <?php if ($pay !== 'paid' && $amt > 0) { ?>
                        <div class="alert alert-warning">Customer has not completed Razorpay payment yet. You can still set status (e.g. cancelled).</div>
                    <?php } ?>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Update booking status</h5>
                    <p class="text-muted small mb-3">Current status: <strong><?php echo htmlspecialchars($b['status']); ?></strong></p>
                    <form method="POST" action="update-status.php?id=<?php echo (int)$booking_id; ?>">
                        <div class="form-outline mb-4">
                            <select name="order-status" class="form-select form-control" required>
                                <option value="" disabled hidden>Choose status</option>
                                <option value="awaiting_payment" <?php echo ($b['status'] === 'awaiting_payment') ? 'selected' : ''; ?>>Awaiting payment</option>
                                <option value="pending" <?php echo ($b['status'] === 'pending') ? 'selected' : ''; ?>>Pending (paid, needs confirmation)</option>
                                <option value="confirmed" <?php echo ($b['status'] === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="cancelled" <?php echo ($b['status'] === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                <option value="on hold" <?php echo ($b['status'] === 'on hold') ? 'selected' : ''; ?>>On hold</option>
                            </select>
                        </div>
                        <button type="submit" name="submit" class="btn btn-primary mb-4">Update</button>
                        <a href="<?php echo url; ?>/admin-panel/bookings-admins/show-bookings.php" class="btn btn-outline-secondary mb-4">Back to list</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
