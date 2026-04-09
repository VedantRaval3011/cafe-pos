<?php require_once "../includes/header.php"; ?>

<?php

// allow pay for either logged-in user OR QR guest session
$isUser = isset($_SESSION['user_id']);
$isQr = isset($_SESSION['qr_session_token']);
if (!$isUser && !$isQr) {
    header("Location: " . url . "/index.php");
    exit();
}

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
    header("Location: " . url . "/index.php");
    exit();
}

// fetch order
$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($orderRes) === 0) {
    header("Location: " . url . "/index.php");
    exit();
}
$order = mysqli_fetch_assoc($orderRes);

// Security: ensure this order belongs to current user/session
if ($isUser && (int)$order['user_id'] !== (int)$_SESSION['user_id']) {
    header("Location: " . url . "/index.php");
    exit();
}
if ($isQr) {
    $tok = $_SESSION['qr_session_token'];
    if (($order['session_token'] ?? '') !== $tok) {
        header("Location: " . url . "/index.php");
        exit();
    }
}

if (($order['payment_status'] ?? '') === 'paid') {
    header("Location: " . url . "/payments/invoice.php?order_id=" . $order_id);
    exit();
}

// Create a Razorpay order via REST API (no SDK needed)
$razorpayOrderId = $order['razorpay_order_id'] ?? null;
if (!$razorpayOrderId || $razorpayOrderId === '') {
    $amountPaise = (int)round(((float)$order['total_price']) * 100);
    $receipt = "order_" . $order_id;

    $payload = json_encode([
        "amount" => $amountPaise,
        "currency" => "INR",
        "receipt" => $receipt,
        "payment_capture" => 1,
        "notes" => [
            "order_id" => (string)$order_id,
            "table_number" => (string)($order['table_number'] ?? '')
        ]
    ]);

    $ch = curl_init("https://api.razorpay.com/v1/orders");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ":" . RAZORPAY_KEY_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http < 200 || $http >= 300) {
        $safe = htmlspecialchars((string)$resp);
        echo "<div class='container mt-5 mb-5'><h3>Payment setup error</h3><p>Razorpay order creation failed. Check keys in <code>config/config.php</code>.</p><pre style='white-space:pre-wrap'>{$safe}</pre></div>";
        require_once "../includes/footer.php";
        exit();
    }

    $data = json_decode((string)$resp, true);
    $razorpayOrderId = $data["id"] ?? null;
    if (!$razorpayOrderId) {
        echo "<div class='container mt-5 mb-5'><h3>Payment setup error</h3><p>Invalid Razorpay response.</p></div>";
        require_once "../includes/footer.php";
        exit();
    }

    $rzpEsc = mysqli_real_escape_string($conn, $razorpayOrderId);
    mysqli_query($conn, "UPDATE orders SET payment_provider='razorpay', razorpay_order_id='{$rzpEsc}' WHERE id={$order_id}") or die("Query Unsuccessful");
}

?>

<section class="home-slider owl-carousel">
    <div class="slider-item" style="background-image: url(<?php echo url; ?>/images/bg_3.jpg)" data-stellar-background-ratio="0.5">
        <div class="overlay"></div>
        <div class="container">
            <div class="row slider-text justify-content-center align-items-center">
                <div class="col-md-7 col-sm-12 text-center ftco-animate">
                    <h1 class="mb-3 mt-5 bread">Pay Online</h1>
                    <p class="breadcrumbs">
                        <span class="mr-2"><a href="<?php echo url; ?>">Home</a></span>
                        <span>Cart</span>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<div class="container mt-5 mb-5">
    <div class="p-4" style="background:#1a1a1a;border-radius:10px;color:#fff;">
        <p class="mb-2"><strong>Order ID:</strong> #<?php echo $order_id; ?></p>
        <?php if (!empty($order['table_number'])) { ?>
            <p class="mb-2"><strong>Table:</strong> <?php echo (int)$order['table_number']; ?></p>
        <?php } ?>
        <p class="mb-4"><strong>Amount:</strong> ₹<?php echo htmlspecialchars((string)$order['total_price']); ?></p>
        <button id="rzp-pay" class="btn btn-primary py-3 px-4">Pay with Razorpay</button>
        <p class="mt-3 mb-0" style="opacity:0.8;">For demo: use Razorpay Test Mode credentials.</p>
    </div>

    <form id="rzp-result" method="post" action="<?php echo url; ?>/payments/razorpay_callback.php" style="display:none;">
        <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature">
    </form>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const options = {
            key: "<?php echo htmlspecialchars(RAZORPAY_KEY_ID); ?>",
            amount: "<?php echo (int)round(((float)$order['total_price']) * 100); ?>",
            currency: "INR",
            name: "Cafe Junction",
            description: "Table Order",
            order_id: "<?php echo htmlspecialchars($razorpayOrderId); ?>",
            handler: function(response) {
                document.getElementById("razorpay_payment_id").value = response.razorpay_payment_id;
                document.getElementById("razorpay_order_id").value = response.razorpay_order_id;
                document.getElementById("razorpay_signature").value = response.razorpay_signature;
                document.getElementById("rzp-result").submit();
            },
            theme: { color: "#c49b63" }
        };
        const rzp = new Razorpay(options);
        document.getElementById("rzp-pay").onclick = function(e) {
            e.preventDefault();
            rzp.open();
        };
    </script>
</div>


<?php require_once "../includes/footer.php"; ?>