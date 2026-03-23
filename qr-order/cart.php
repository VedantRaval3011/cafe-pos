<?php
require_once __DIR__ . "/_bootstrap.php";

$tok = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);

// actions: update qty, remove item, clear cart
if (isset($_POST['update_qty'])) {
  $cart_id = (int)($_POST['cart_id'] ?? 0);
  $qty = (int)($_POST['quantity'] ?? 1);
  if ($qty < 1) $qty = 1;
  if ($qty > 50) $qty = 50;
  mysqli_query($conn, "UPDATE cart SET quantity={$qty} WHERE id={$cart_id} AND session_token='{$tok}'") or die("Query Unsuccessful");
  header("Location: " . url . "/qr-order/cart.php");
  exit();
}

if (isset($_POST['remove_item'])) {
  $cart_id = (int)($_POST['cart_id'] ?? 0);
  mysqli_query($conn, "DELETE FROM cart WHERE id={$cart_id} AND session_token='{$tok}'") or die("Query Unsuccessful");
  header("Location: " . url . "/qr-order/cart.php");
  exit();
}

if (isset($_POST['clear_cart'])) {
  mysqli_query($conn, "DELETE FROM cart WHERE session_token='{$tok}'") or die("Query Unsuccessful");
  header("Location: " . url . "/qr-order/cart.php");
  exit();
}

$items = mysqli_query($conn, "SELECT * FROM cart WHERE session_token='{$tok}' ORDER BY id DESC") or die("Query Unsuccessful");
$sumRes = mysqli_query($conn, "SELECT COALESCE(SUM(price * quantity),0) AS total FROM cart WHERE session_token='{$tok}'") or die("Query Unsuccessful");
$sum = mysqli_fetch_assoc($sumRes);
$total = (float)($sum['total'] ?? 0);

// If user clicked "Buy Now", jump to checkout
if (isset($_GET['buy_now']) && $_GET['buy_now'] === '1' && $total > 0) {
  header("Location: " . url . "/qr-order/checkout.php");
  exit();
}
?>

<?php require_once __DIR__ . "/_header.php"; ?>

<div class="container qr-page">
  <div class="qr-page-top">
    <h3 class="qr-page-title">Your Order</h3>
    <div class="qr-page-actions">
      <a class="btn btn-primary" href="<?php echo url; ?>/qr-order/menu.php">Back to menu</a>
      <form method="post" action="<?php echo url; ?>/qr-order/cart.php" style="display:inline;">
        <button class="btn btn-danger" type="submit" name="clear_cart">Clear order</button>
      </form>
    </div>
  </div>

  <div class="row mt-3">
    <div class="col-lg-8">
      <?php if (mysqli_num_rows($items) === 0) { ?>
        <div class="alert alert-warning">No items yet. Go back to menu and add dishes.</div>
      <?php } ?>

      <?php while ($it = mysqli_fetch_assoc($items)) { ?>
        <div class="qr-card mb-3">
          <div class="qr-order-item">
            <div class="qr-order-thumb" style="background-image:url(<?php echo url; ?>/images/<?php echo htmlspecialchars($it['image']); ?>)"></div>

            <div>
              <div class="qr-order-head">
                <div>
                  <div class="qr-order-name"><?php echo htmlspecialchars($it['name']); ?></div>
                  <div class="qr-order-meta">Size: <?php echo htmlspecialchars($it['size']); ?></div>
                </div>
                <div class="qr-order-price">₹<?php echo number_format((float)$it['price'], 2); ?></div>
              </div>

              <div class="qr-order-controls">
                <form method="post" action="<?php echo url; ?>/qr-order/cart.php" class="qr-qty">
                  <input type="hidden" name="cart_id" value="<?php echo (int)$it['id']; ?>">
                  <input type="number" class="form-control" min="1" max="50" name="quantity" value="<?php echo (int)$it['quantity']; ?>">
                  <button class="btn btn-primary" type="submit" name="update_qty">Update</button>
                </form>

                <form method="post" action="<?php echo url; ?>/qr-order/cart.php" style="display:inline;">
                  <input type="hidden" name="cart_id" value="<?php echo (int)$it['id']; ?>">
                  <button class="btn btn-danger" type="submit" name="remove_item">Remove</button>
                </form>
              </div>

              <div class="qr-order-total">
                Line total: <strong>₹<?php echo number_format(((float)$it['price']) * ((int)$it['quantity']), 2); ?></strong>
              </div>
            </div>
          </div>
        </div>
      <?php } ?>
    </div>

    <div class="col-lg-4">
      <div class="qr-card">
        <h4 style="color:#fff;font-weight:800;">Summary</h4>
        <div class="d-flex justify-content-between mt-3" style="color:#fff;">
          <span>Total</span>
          <strong>₹<?php echo number_format($total, 2); ?></strong>
        </div>
        <hr style="border-color:rgba(255,255,255,0.1);" />
        <a class="btn btn-primary btn-block py-3" href="<?php echo url; ?>/qr-order/checkout.php" <?php echo $total <= 0 ? "style='pointer-events:none;opacity:0.5;'" : ""; ?>>
          Proceed to Payment
        </a>
        <div class="mt-2" style="color:#ddd;opacity:0.8;font-size:12px;">
          Pay online and your invoice will appear instantly.
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/_footer.php"; ?>

