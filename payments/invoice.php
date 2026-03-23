<?php require_once __DIR__ . "/../includes/header.php"; ?>

<?php
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
  header("Location: " . url . "/index.php");
  exit();
}

$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($orderRes) === 0) {
  header("Location: " . url . "/index.php");
  exit();
}
$order = mysqli_fetch_assoc($orderRes);

$itemsRes = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = {$order_id}") or die("Query Unsuccessful");
$emailSent = isset($_GET['email_sent']) && $_GET['email_sent'] === '1';
?>

<section class="home-slider owl-carousel">
  <div class="slider-item" style="background-image: url(<?php echo url; ?>/images/bg_3.jpg)" data-stellar-background-ratio="0.5">
    <div class="overlay"></div>
    <div class="container">
      <div class="row slider-text justify-content-center align-items-center">
        <div class="col-md-7 col-sm-12 text-center ftco-animate">
          <h1 class="mb-3 mt-5 bread">Invoice</h1>
          <p class="breadcrumbs">
            <span class="mr-2"><a href="<?php echo url; ?>">Home</a></span>
            <span>Invoice</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<div class="container mt-5 mb-5">
  <div class="p-4" style="background:#1a1a1a;border-radius:10px;color:#fff;">
    <div class="d-md-flex justify-content-between align-items-center">
      <div>
        <h3 class="mb-1">Invoice <?php echo htmlspecialchars($order['invoice_number'] ?? ("#" . $order_id)); ?></h3>
        <div style="opacity:0.85;">Order #<?php echo $order_id; ?><?php if (!empty($order['table_number'])) { ?> • Table <?php echo (int)$order['table_number']; ?><?php } ?></div>
      </div>
      <div class="mt-3 mt-md-0">
        <span class="badge badge-success" style="font-size:14px;">Paid</span>
      </div>
    </div>

    <?php if ($emailSent) { ?>
      <div class="mt-3 alert alert-success">Invoice emailed successfully (if SMTP is configured).</div>
    <?php } else { ?>
      <div class="mt-3 alert alert-warning">Email not sent (SMTP/Composer not configured). You can still download the invoice PDF if generated.</div>
    <?php } ?>

    <hr style="border-color:rgba(255,255,255,0.1);" />

    <div class="alert alert-info">
      Your order is now being made. Please wait—kitchen has received it.
    </div>

    <div class="table-responsive">
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
      <div style="min-width:260px;">
        <div class="d-flex justify-content-between"><span>Total</span><strong>₹<?php echo number_format((float)$order['total_price'], 2); ?></strong></div>
      </div>
    </div>

    <?php if (!empty($order['invoice_pdf_path'])) { ?>
      <div class="mt-4">
        <a class="btn btn-primary py-2 px-3" target="_blank" href="<?php echo url . '/' . ltrim($order['invoice_pdf_path'], '/'); ?>">Download PDF</a>
      </div>
    <?php } ?>
  </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>

