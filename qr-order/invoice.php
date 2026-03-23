<?php
require_once __DIR__ . "/_bootstrap.php";

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
  header("Location: " . url . "/qr-order/menu.php");
  exit();
}

$tokEsc = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} AND session_token='{$tokEsc}' LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($orderRes) === 0) {
  header("Location: " . url . "/qr-order/menu.php");
  exit();
}
$order = mysqli_fetch_assoc($orderRes);

$itemsRes = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = {$order_id}") or die("Query Unsuccessful");
?>

<?php require_once __DIR__ . "/_header.php"; ?>

<div class="container qr-page">
  <div class="qr-card">
    <div class="d-md-flex justify-content-between align-items-center">
      <div>
        <h3 style="color:#fff;font-weight:800;margin-bottom:6px;">Invoice <?php echo htmlspecialchars($order['invoice_number'] ?? ("#" . $order_id)); ?></h3>
        <div style="color:#ddd;opacity:0.9;">Order #<?php echo $order_id; ?> • Table <?php echo (int)($_SESSION['qr_table_number']); ?></div>
      </div>
      <div class="mt-3 mt-md-0">
        <span class="badge badge-success" style="font-size:14px;">Paid</span>
      </div>
    </div>

    <div class="mt-3 alert alert-info">
      Your order is now being made. Please wait—kitchen has received it.
    </div>

    <div class="table-responsive mt-3">
      <table class="table" style="color:#fff;">
        <thead>
          <tr>
            <th>Item</th>
            <th class="text-center">Size</th>
            <th class="text-center">Qty</th>
            <th class="text-right">Price</th>
            <th class="text-right">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php while ($it = mysqli_fetch_assoc($itemsRes)) { ?>
            <tr>
              <td><?php echo htmlspecialchars($it['name']); ?></td>
              <td class="text-center"><?php echo htmlspecialchars($it['size']); ?></td>
              <td class="text-center"><?php echo (int)$it['quantity']; ?></td>
              <td class="text-right">₹<?php echo number_format((float)$it['price'], 2); ?></td>
              <td class="text-right">₹<?php echo number_format((float)$it['line_total'], 2); ?></td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>

    <div class="d-flex justify-content-end">
      <div style="min-width:260px;color:#fff;">
        <div class="d-flex justify-content-between"><span>Total</span><strong>₹<?php echo number_format((float)$order['total_price'], 2); ?></strong></div>
      </div>
    </div>

    <div class="mt-4 d-flex" style="gap:10px;flex-wrap:wrap;">
      <a class="btn btn-primary py-2 px-3" href="<?php echo url; ?>/qr-order/track.php?order_id=<?php echo $order_id; ?>">Track Order Live</a>
      <a class="btn btn-white btn-outline-white py-2 px-3" href="<?php echo url; ?>/qr-order/menu.php">Order more</a>
      <?php if (!empty($order['invoice_pdf_path'])) { ?>
        <a class="btn btn-white btn-outline-white py-2 px-3" target="_blank" href="<?php echo url . '/' . ltrim($order['invoice_pdf_path'], '/'); ?>">Download PDF</a>
      <?php } ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . "/_footer.php"; ?>

