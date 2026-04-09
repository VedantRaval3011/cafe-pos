<?php
require_once __DIR__ . '/../includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . url . '/auth/login.php');
    exit;
}

$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
if ($bookingId <= 0) {
    header('Location: ' . url . '/users/bookings.php');
    exit;
}

$res = mysqli_query($conn, "SELECT * FROM bookings WHERE id = {$bookingId} LIMIT 1") or die('Query Unsuccessful');
if (mysqli_num_rows($res) === 0) {
    header('Location: ' . url . '/users/bookings.php');
    exit;
}
$booking = mysqli_fetch_assoc($res);

if ((int)$booking['user_id'] !== (int)$_SESSION['user_id']) {
    header('Location: ' . url . '/index.php');
    exit;
}

if (($booking['payment_status'] ?? '') === 'paid') {
    header('Location: ' . url . '/users/bookings.php');
    exit;
}

$amount = (float)($booking['amount'] ?? BOOKING_FEE_INR);
if ($amount <= 0) {
    header('Location: ' . url . '/users/bookings.php');
    exit;
}

if (RAZORPAY_KEY_ID === '' || RAZORPAY_KEY_SECRET === '') {
    echo '<div class="container mt-5 mb-5"><h3>Payments not configured</h3><p>Add <code>RAZORPAY_KEY_ID</code> and <code>RAZORPAY_KEY_SECRET</code> in your <code>.env</code> file.</p>
    <a class="btn btn-primary" href="' . htmlspecialchars(url) . '/users/bookings.php">Back to bookings</a></div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$razorpayOrderId = $booking['razorpay_order_id'] ?? null;
if (!$razorpayOrderId) {
    $amountPaise = (int)round($amount * 100);
    $receipt = 'booking_' . $bookingId;
    $payload = json_encode([
        'amount' => $amountPaise,
        'currency' => 'INR',
        'receipt' => $receipt,
        'payment_capture' => 1,
        'notes' => [
            'booking_id' => (string)$bookingId,
            'type' => 'table_booking',
        ],
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        echo '<div class="container mt-5 mb-5"><h3>Payment setup error</h3><p>Razorpay order creation failed.</p><pre style="white-space:pre-wrap">' . htmlspecialchars((string)$resp) . '</pre></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }

    $data = json_decode((string)$resp, true);
    $razorpayOrderId = $data['id'] ?? null;
    if (!$razorpayOrderId) {
        echo '<div class="container mt-5 mb-5"><h3>Payment setup error</h3><p>Invalid Razorpay response.</p></div>';
        require_once __DIR__ . '/../includes/footer.php';
        exit;
    }

    $rzpEsc = mysqli_real_escape_string($conn, $razorpayOrderId);
    mysqli_query($conn, "UPDATE bookings SET razorpay_order_id='{$rzpEsc}' WHERE id={$bookingId}") or die('Query Unsuccessful');
}
?>

<section class="home-slider owl-carousel">
    <div class="slider-item" style="background-image: url(<?php echo url; ?>/images/bg_3.jpg)" data-stellar-background-ratio="0.5">
        <div class="overlay"></div>
        <div class="container">
            <div class="row slider-text justify-content-center align-items-center">
                <div class="col-md-7 col-sm-12 text-center ftco-animate">
                    <h1 class="mb-3 mt-5 bread">Pay for table booking</h1>
                    <p class="breadcrumbs">
                        <span class="mr-2"><a href="<?php echo url; ?>">Home</a></span>
                        <span>Booking</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container mt-5 mb-5">
    <div class="p-4" style="background:#1a1a1a;border-radius:10px;color:#fff;">
        <p class="mb-2"><strong>Booking ID:</strong> #<?php echo $bookingId; ?></p>
        <p class="mb-2"><strong>Date:</strong> <?php echo htmlspecialchars($booking['date']); ?> &nbsp; <strong>Time:</strong> <?php echo htmlspecialchars($booking['time']); ?></p>
        <p class="mb-4"><strong>Amount:</strong> ₹<?php echo htmlspecialchars(number_format($amount, 2)); ?></p>
        <button id="rzp-booking-pay" class="btn btn-primary py-3 px-4">Pay with Razorpay</button>
        <p class="mt-3 mb-0" style="opacity:0.8;">After payment, your request is sent to the cafe. Staff will confirm your table.</p>
    </div>

    <form id="rzp-booking-result" method="post" action="<?php echo url; ?>/payments/booking_razorpay_callback.php" style="display:none;">
        <input type="hidden" name="booking_id" value="<?php echo $bookingId; ?>">
        <input type="hidden" name="razorpay_payment_id" id="booking_razorpay_payment_id">
        <input type="hidden" name="razorpay_order_id" id="booking_razorpay_order_id">
        <input type="hidden" name="razorpay_signature" id="booking_razorpay_signature">
    </form>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const options = {
            key: "<?php echo htmlspecialchars(RAZORPAY_KEY_ID); ?>",
            amount: "<?php echo (int)round($amount * 100); ?>",
            currency: "INR",
            name: "Cafe Junction",
            description: "Table booking — Booking #<?php echo (int)$bookingId; ?>",
            order_id: "<?php echo htmlspecialchars($razorpayOrderId); ?>",
            prefill: {
                email: "<?php echo htmlspecialchars($booking['email'] ?? ''); ?>",
                contact: "<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $booking['phone'] ?? '')); ?>"
            },
            handler: function(response) {
                document.getElementById("booking_razorpay_payment_id").value = response.razorpay_payment_id;
                document.getElementById("booking_razorpay_order_id").value = response.razorpay_order_id;
                document.getElementById("booking_razorpay_signature").value = response.razorpay_signature;
                document.getElementById("rzp-booking-result").submit();
            },
            theme: { color: "#c49b63" }
        };
        const rzp = new Razorpay(options);
        document.getElementById("rzp-booking-pay").onclick = function(e) {
            e.preventDefault();
            rzp.open();
        };
    </script>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
