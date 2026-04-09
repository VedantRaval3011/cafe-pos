<?php require_once "../includes/header.php"; ?>

<?php
if (!isset($_SESSION['user_id'])) {
    header("Location: " . url . "/index.php");
    exit();
}

$uid = (int)$_SESSION['user_id'];
$query = "SELECT * FROM bookings WHERE user_id = {$uid} ORDER BY created_at DESC, id DESC";
$result = mysqli_query($conn, $query) or die("Query Unsuccessful");

?>

<section class="home-slider owl-carousel">
    <div class="slider-item" style="background-image: url(<?php echo url; ?>/images/bg_3.jpg)" data-stellar-background-ratio="0.5">
        <div class="overlay"></div>
        <div class="container">
            <div class="row slider-text justify-content-center align-items-center">
                <div class="col-md-7 col-sm-12 text-center ftco-animate">
                    <h1 class="mb-3 mt-5 bread">My Bookings</h1>
                    <p class="breadcrumbs">
                        <span class="mr-2"><a href="<?php echo url; ?>">Home</a></span>
                        <span>Bookings</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="ftco-section ftco-cart">
    <div class="container">
        <?php
        if (!empty($_SESSION['flash_booking'])) {
            $fb = $_SESSION['flash_booking'];
            unset($_SESSION['flash_booking']);
            ?>
            <div class="alert alert-<?php echo $fb['type'] === 'ok' ? 'success' : 'danger'; ?> mb-4"><?php echo htmlspecialchars($fb['msg']); ?></div>
        <?php } ?>

        <div class="row">
            <div class="col-md-12 ftco-animate">
                <div class="cart-list">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="thead-primary">
                                <tr class="text-center">
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Fee</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if (mysqli_num_rows($result) > 0) {
                                    while ($booking = mysqli_fetch_assoc($result)) {
                                        $amt = isset($booking['amount']) ? (float)$booking['amount'] : 0;
                                        $paySt = $booking['payment_status'] ?? 'unpaid';
                                        $needsPay = $paySt === 'unpaid' && $amt > 0;
                                        ?>
                                        <tr class="text-center">
                                            <td><?php echo htmlspecialchars($booking['date']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['time']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['email'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                                            <td><?php echo $amt > 0 ? '₹' . htmlspecialchars(number_format($amt, 2)) : '—'; ?></td>
                                            <td>
                                                <?php
                                                if ($paySt === 'paid') {
                                                    echo '<span class="badge badge-success">Paid</span>';
                                                } elseif ($paySt === 'failed') {
                                                    echo '<span class="badge badge-danger">Failed</span>';
                                                } else {
                                                    echo '<span class="badge badge-warning text-dark">Unpaid</span>';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($booking['status']); ?></td>
                                            <td>
                                                <?php if ($needsPay) { ?>
                                                    <a href="<?php echo url; ?>/booking/pay.php?booking_id=<?php echo (int)$booking['id']; ?>" class="btn btn-sm btn-primary">Pay with Razorpay</a>
                                                <?php } else { ?>
                                                    <span class="text-muted">—</span>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php }
                                } else { ?>
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <h5 class="mb-0">No table bookings yet.</h5>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once "../includes/footer.php"; ?>
