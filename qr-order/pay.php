<?php
require_once __DIR__ . "/_bootstrap.php";

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
  header("Location: " . url . "/qr-order/cart.php");
  exit();
}

$tok = $_SESSION['qr_session_token'];
$tokEsc = mysqli_real_escape_string($conn, $tok);

$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} AND session_token='{$tokEsc}' LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($orderRes) === 0) {
  header("Location: " . url . "/qr-order/cart.php");
  exit();
}
$order = mysqli_fetch_assoc($orderRes);

if (($order['payment_status'] ?? '') === 'paid') {
  header("Location: " . url . "/qr-order/invoice.php?order_id=" . $order_id);
  exit();
}

// Create Razorpay order if missing
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
    echo "<div class='container mt-5 mb-5'><h3 style='color:#fff;'>Payment setup error</h3><p style='color:#ddd;'>Razorpay order creation failed. Check keys in <code>config/config.php</code>.</p><pre style='white-space:pre-wrap;color:#fff;'>{$safe}</pre></div>";
    require_once __DIR__ . "/_footer.php";
    exit();
  }

  $data = json_decode((string)$resp, true);
  $razorpayOrderId = $data["id"] ?? null;
  if (!$razorpayOrderId) {
    echo "<div class='container mt-5 mb-5'><h3 style='color:#fff;'>Payment setup error</h3><p style='color:#ddd;'>Invalid Razorpay response.</p></div>";
    require_once __DIR__ . "/_footer.php";
    exit();
  }

  $rzpEsc = mysqli_real_escape_string($conn, $razorpayOrderId);
  mysqli_query($conn, "UPDATE orders SET payment_provider='razorpay', razorpay_order_id='{$rzpEsc}' WHERE id={$order_id}") or die("Query Unsuccessful");
}
?>

<?php require_once __DIR__ . "/_header.php"; ?>

<div class="container qr-page">
  <div class="qr-card">
    <h3 style="color:#fff;font-weight:800;">Pay for Table <?php echo (int)($_SESSION['qr_table_number']); ?></h3>
    <div style="color:#ddd;opacity:0.9;">Order #<?php echo $order_id; ?></div>
    <div class="mt-3" style="font-size:18px;color:#fff;">
      Amount: <strong>₹<?php echo number_format((float)$order['total_price'], 2); ?></strong>
    </div>

    <div class="mt-4">
      <button id="rzp-pay" class="btn btn-primary py-3 px-4">Pay Now</button>
      <a class="btn btn-white btn-outline-white py-3 px-4" href="<?php echo url; ?>/qr-order/cart.php">Back to order</a>
    </div>
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
      name: "NS Coffee",
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

<?php require_once __DIR__ . "/_footer.php"; ?>

