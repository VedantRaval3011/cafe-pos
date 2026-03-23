<?php

function generate_and_send_invoice(mysqli $conn, int $order_id): array
{
  $pdfPath = null;
  $emailSent = false;

  $orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} LIMIT 1");
  if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
    return [$pdfPath, $emailSent];
  }
  $order = mysqli_fetch_assoc($orderRes);

  $itemsRes = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id = {$order_id}");
  if (!$itemsRes) {
    return [$pdfPath, $emailSent];
  }

  // Try to generate PDF via Dompdf if available (Composer)
  $autoload = __DIR__ . "/../vendor/autoload.php";
  if (file_exists($autoload)) {
    require_once $autoload;

    if (class_exists(\Dompdf\Dompdf::class)) {
      $html = build_invoice_html($order, $itemsRes);

      $dompdf = new \Dompdf\Dompdf();
      $dompdf->loadHtml($html);
      $dompdf->setPaper('A4', 'portrait');
      $dompdf->render();

      $dir = __DIR__ . "/../storage/invoices";
      if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
      }

      $fileName = ($order['invoice_number'] ?? ("INV-" . $order_id)) . ".pdf";
      $absPath = $dir . "/" . preg_replace('/[^A-Za-z0-9_.-]/', '_', $fileName);
      file_put_contents($absPath, $dompdf->output());

      // store relative path in DB
      $rel = "storage/invoices/" . basename($absPath);
      $relEsc = mysqli_real_escape_string($conn, $rel);
      mysqli_query($conn, "UPDATE orders SET invoice_pdf_path='{$relEsc}' WHERE id={$order_id}");
      $pdfPath = $rel;

      // Try email send via PHPMailer if available
      if (class_exists(\PHPMailer\PHPMailer\PHPMailer::class) && !empty($order['email'])) {
        try {
          $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
          $mail->isSMTP();
          $mail->Host = SMTP_HOST;
          $mail->SMTPAuth = true;
          $mail->Username = SMTP_USERNAME;
          $mail->Password = SMTP_PASSWORD;
          $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
          $mail->Port = SMTP_PORT;

          $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
          $mail->addAddress($order['email'], trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
          $mail->Subject = "Your NS Coffee Invoice " . ($order['invoice_number'] ?? ("#" . $order_id));
          $mail->Body = "Thanks for your order! Your invoice is attached.";
          $mail->addAttachment($absPath, basename($absPath));

          $mail->send();
          $emailSent = true;
        } catch (\Throwable $e) {
          $emailSent = false;
        }
      }
    }
  }

  return [$pdfPath, $emailSent];
}

function build_invoice_html(array $order, $itemsRes): string
{
  $invoiceNo = htmlspecialchars($order['invoice_number'] ?? ("INV-" . ($order['id'] ?? '')));
  $orderId = (int)($order['id'] ?? 0);
  $table = !empty($order['table_number']) ? (int)$order['table_number'] : null;
  $customer = htmlspecialchars(trim(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')));
  $email = htmlspecialchars((string)($order['email'] ?? ''));
  $paidAt = htmlspecialchars((string)($order['paid_at'] ?? ''));

  $rows = "";
  $items = [];
  while ($it = mysqli_fetch_assoc($itemsRes)) {
    $items[] = $it;
  }
  foreach ($items as $it) {
    $rows .= "<tr>
      <td>" . htmlspecialchars($it['name']) . "</td>
      <td style='text-align:center;'>" . htmlspecialchars($it['size']) . "</td>
      <td style='text-align:center;'>" . (int)$it['quantity'] . "</td>
      <td style='text-align:right;'>₹" . number_format((float)$it['price'], 2) . "</td>
      <td style='text-align:right;'>₹" . number_format((float)$it['line_total'], 2) . "</td>
    </tr>";
  }

  $total = number_format((float)($order['total_price'] ?? 0), 2);

  $tableLine = $table ? "<div><strong>Table:</strong> {$table}</div>" : "";

  return "<!doctype html>
<html>
<head>
  <meta charset='utf-8'>
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color:#111; }
    .top { display:flex; justify-content:space-between; }
    h1 { margin:0; font-size: 20px; }
    table { width:100%; border-collapse:collapse; margin-top:16px; }
    th, td { border:1px solid #ddd; padding:8px; }
    th { background:#f6f6f6; }
    .right { text-align:right; }
    .muted { color:#666; }
  </style>
</head>
<body>
  <div class='top'>
    <div>
      <h1>NS Coffee</h1>
      <div class='muted'>Digital Invoice</div>
    </div>
    <div style='text-align:right;'>
      <div><strong>Invoice:</strong> {$invoiceNo}</div>
      <div><strong>Order:</strong> #{$orderId}</div>
      {$tableLine}
      <div><strong>Paid at:</strong> {$paidAt}</div>
    </div>
  </div>

  <div style='margin-top:12px;'>
    <div><strong>Billed to:</strong> {$customer}</div>
    <div><strong>Email:</strong> {$email}</div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Item</th>
        <th style='width:80px;'>Size</th>
        <th style='width:60px;'>Qty</th>
        <th style='width:90px;' class='right'>Price</th>
        <th style='width:90px;' class='right'>Total</th>
      </tr>
    </thead>
    <tbody>
      {$rows}
    </tbody>
  </table>

  <div style='margin-top:14px; text-align:right;'>
    <div><strong>Grand Total:</strong> ₹{$total}</div>
  </div>
</body>
</html>";
}

