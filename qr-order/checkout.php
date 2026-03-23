<?php
require_once __DIR__ . "/_bootstrap.php";

$tok = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
$sumRes = mysqli_query($conn, "SELECT COALESCE(SUM(price * quantity),0) AS total FROM cart WHERE session_token='{$tok}'") or die("Query Unsuccessful");
$sum = mysqli_fetch_assoc($sumRes);
$total = (float)($sum['total'] ?? 0);
if ($total <= 0) {
  header("Location: " . url . "/qr-order/cart.php");
  exit();
}

if (isset($_POST['place_order'])) {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $email = trim($_POST['email'] ?? '');

  if ($first_name === '' || $phone === '' || $email === '') {
    $err = "Please fill name, phone and email.";
  } else {
    $firstEsc = mysqli_real_escape_string($conn, $first_name);
    $lastEsc = mysqli_real_escape_string($conn, $last_name);
    $phoneEsc = mysqli_real_escape_string($conn, $phone);
    $emailEsc = mysqli_real_escape_string($conn, $email);

    // Orders table requires address fields (legacy). For table orders we store placeholders.
    $country = mysqli_real_escape_string($conn, "Table Order");
    $street = mysqli_real_escape_string($conn, "Table " . (int)$_SESSION['qr_table_number']);
    $town = mysqli_real_escape_string($conn, "In-store");
    $zip = mysqli_real_escape_string($conn, "000000");

    $session_token_sql = "'" . $tok . "'";
    $table_number_sql = (int)$_SESSION['qr_table_number'];
    $totalSql = (float)$total;

    mysqli_query(
      $conn,
      "INSERT INTO orders (first_name, last_name, country, street_address, town, zip_code, phone, email, user_id, session_token, table_number, status, payment_status, total_price)
       VALUES ('{$firstEsc}','{$lastEsc}','{$country}','{$street}','{$town}','{$zip}','{$phoneEsc}','{$emailEsc}',NULL,{$session_token_sql},{$table_number_sql},'created','unpaid',{$totalSql})"
    ) or die("Query Unsuccessful");

    $order_id = mysqli_insert_id($conn);

    // move cart -> order_items
    $cartRows = mysqli_query($conn, "SELECT * FROM cart WHERE session_token='{$tok}'") or die("Query Unsuccessful");
    while ($c = mysqli_fetch_assoc($cartRows)) {
      $p = (float)$c['price'];
      $q = (int)$c['quantity'];
      $line = $p * $q;
      $nameEsc = mysqli_real_escape_string($conn, $c['name']);
      $imgEsc = mysqli_real_escape_string($conn, $c['image']);
      $sizeEsc = mysqli_real_escape_string($conn, $c['size']);
      $pid = (int)$c['product_id'];

      mysqli_query(
        $conn,
        "INSERT INTO order_items (order_id, product_id, name, image, price, size, quantity, line_total, item_status)
         VALUES ({$order_id}, {$pid}, '{$nameEsc}', '{$imgEsc}', {$p}, '{$sizeEsc}', {$q}, {$line}, 'placed')"
      ) or die("Query Unsuccessful");
    }

    header("Location: " . url . "/qr-order/pay.php?order_id=" . $order_id);
    exit();
  }
}
?>

<?php require_once __DIR__ . "/_header.php"; ?>

<div class="container qr-page">
  <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:10px;">
    <h3 style="color:#fff;font-weight:900;margin:0;">Checkout</h3>
    <a class="btn btn-white btn-outline-white" href="<?php echo url; ?>/qr-order/cart.php">Back to order</a>
  </div>

  <div class="row mt-2">
    <div class="col-lg-7">
      <?php if (!empty($err)) { ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
      <?php } ?>

      <div class="qr-card">
        <div class="qr-kicker">Customer details</div>
        <form method="post" action="<?php echo url; ?>/qr-order/checkout.php">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>First name *</label>
              <input class="form-control" name="first_name" placeholder="John" required>
            </div>
            <div class="form-group col-md-6">
              <label>Last name</label>
              <input class="form-control" name="last_name" placeholder="Doe">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Phone *</label>
              <input class="form-control" name="phone" placeholder="98765 43210" required>
            </div>
            <div class="form-group col-md-6">
              <label>Email *</label>
              <input class="form-control" type="email" name="email" placeholder="you@example.com" required>
            </div>
          </div>

          <div class="qr-btn-row">
            <button class="btn btn-primary py-3 px-4" type="submit" name="place_order">
              Continue to payment
            </button>
            <a class="btn btn-white btn-outline-white py-3 px-4" href="<?php echo url; ?>/qr-order/menu.php">Add more items</a>
          </div>
        </form>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="qr-card">
        <div class="qr-kicker">Summary</div>
        <h4 style="color:#fff;font-weight:900;">Payable amount</h4>
        <div class="d-flex justify-content-between mt-2" style="color:#fff;">
          <span>Total</span>
          <strong>₹<?php echo number_format($total, 2); ?></strong>
        </div>
        <hr style="border-color:rgba(255,255,255,0.1);" />
        <div style="color:#ddd;opacity:0.85;">
          After payment, your order instantly appears in the admin portal and the invoice is generated.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/_footer.php"; ?>

