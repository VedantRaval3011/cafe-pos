<?php // requires qr-order/_bootstrap.php BEFORE including this file ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Table <?php echo (int)$_SESSION['qr_table_number']; ?> • NS Coffee</title>
  <link rel="icon" type="image/x-icon" href="<?php echo url; ?>/images/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

  <link rel="stylesheet" href="<?php echo url; ?>/css/open-iconic-bootstrap.min.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/animate.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/owl.carousel.min.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/owl.theme.default.min.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/magnific-popup.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/aos.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/ionicons.min.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/bootstrap-datepicker.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/jquery.timepicker.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/flaticon.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/icomoon.css" />
  <link rel="stylesheet" href="<?php echo url; ?>/css/style.css">

  <style>
    body {
      font-family: Poppins, sans-serif;
    }

    .qr-topbar {
      position: sticky;
      top: 0;
      z-index: 1000;
      background: rgba(0, 0, 0, 0.92);
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(8px);
    }

    .qr-topbar .inner {
      padding: 10px 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .qr-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.10);
      border-radius: 999px;
      color: #fff;
      font-weight: 600;
      font-size: 14px;
    }

    .qr-topbar-actions {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    .qr-kitchen-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 10px;
      background: rgba(196, 155, 99, 0.12);
      color: #e8c99a;
      font-weight: 700;
      border: 2px solid rgba(196, 155, 99, 0.55);
      text-decoration: none;
      white-space: nowrap;
      transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
    }

    .qr-kitchen-btn:hover {
      background: rgba(196, 155, 99, 0.22);
      border-color: #c49b63;
      color: #fff;
      text-decoration: none;
    }

    .qr-kitchen-btn .icon {
      font-size: 1.05em;
    }

    .qr-cart-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 10px;
      background: #c49b63;
      color: #111;
      font-weight: 700;
      border: none;
      text-decoration: none;
    }

    .qr-badge {
      min-width: 22px;
      height: 22px;
      border-radius: 999px;
      background: #111;
      color: #fff;
      font-size: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0 6px;
    }

    .qr-card {
      background: rgba(0, 0, 0, 0.55);
      border: 1px solid rgba(255, 255, 255, 0.08);
      border-radius: 14px;
      padding: 14px;
      height: 100%;
    }

    .qr-card.qr-product {
      padding: 14px;
      border-radius: 16px;
      transition: transform 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
      box-shadow: 0 10px 26px rgba(0, 0, 0, 0.25);
    }

    .qr-card.qr-product:hover {
      transform: translateY(-2px);
      border-color: rgba(196, 155, 99, 0.35);
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.35);
    }

    .qr-img {
      width: 100%;
      padding-top: 62%;
      border-radius: 12px;
      background-size: cover;
      background-position: center;
      margin-bottom: 12px;
    }

    .qr-img.qr-clickable {
      cursor: zoom-in;
      position: relative;
      overflow: hidden;
    }

    .qr-img.qr-clickable::after {
      content: "Tap to zoom";
      position: absolute;
      right: 10px;
      bottom: 10px;
      font-size: 12px;
      font-weight: 700;
      padding: 6px 10px;
      border-radius: 999px;
      background: rgba(0, 0, 0, 0.55);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: #fff;
      opacity: 0;
      transform: translateY(6px);
      transition: opacity 160ms ease, transform 160ms ease;
    }

    .qr-card.qr-product:hover .qr-img.qr-clickable::after {
      opacity: 1;
      transform: translateY(0);
    }

    .qr-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    /* Tighter page rhythm */
    .qr-page {
      padding-top: 12px;
      padding-bottom: 18px;
    }

    .qr-page h3,
    .qr-page h4 {
      margin-bottom: 8px;
    }

    .qr-page .alert {
      margin-bottom: 12px;
    }

    /* Premium form controls (same theme) */
    .qr-page .form-control {
      height: 46px;
      border-radius: 14px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #fff;
    }

    .qr-page .form-control::placeholder {
      color: rgba(255, 255, 255, 0.55);
    }

    .qr-page .form-control:focus {
      background: rgba(255, 255, 255, 0.07);
      border-color: rgba(196, 155, 99, 0.55);
      box-shadow: 0 0 0 0.2rem rgba(196, 155, 99, 0.15);
      color: #fff;
    }

    .qr-page label {
      color: rgba(255, 255, 255, 0.88);
      font-weight: 800;
      font-size: 13px;
      margin-bottom: 6px;
    }

    .qr-page .form-group {
      margin-bottom: 12px;
    }

    .qr-btn-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      margin-top: 6px;
    }

    /* Order page layout helpers */
    .qr-page-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 12px;
    }

    .qr-page-title {
      color: #fff;
      font-weight: 900;
      margin: 0;
      line-height: 1.1;
    }

    .qr-page-actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: flex-end;
    }

    .qr-order-item {
      display: grid;
      grid-template-columns: 86px 1fr;
      gap: 12px;
      align-items: start;
    }

    .qr-order-thumb {
      width: 86px;
      height: 72px;
      border-radius: 14px;
      background-size: cover;
      background-position: center;
      border: 1px solid rgba(255, 255, 255, 0.10);
    }

    .qr-order-head {
      display: flex;
      justify-content: space-between;
      gap: 10px;
      align-items: start;
    }

    .qr-order-name {
      color: #fff;
      font-weight: 900;
      margin: 0;
      font-size: 15px;
      line-height: 1.2;
    }

    .qr-order-meta {
      color: rgba(255, 255, 255, 0.78);
      font-size: 13px;
      margin-top: 4px;
    }

    .qr-order-price {
      color: #c49b63;
      font-weight: 900;
      white-space: nowrap;
    }

    .qr-order-controls {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      margin-top: 12px;
    }

    .qr-qty {
      display: inline-flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
    }

    .qr-qty input.form-control {
      width: 110px !important;
      height: 44px !important;
    }

    .qr-order-total {
      margin-top: 10px;
      text-align: right;
      color: rgba(255, 255, 255, 0.9);
    }

    @media (max-width: 576px) {
      .qr-page-actions {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        justify-content: stretch;
      }

      .qr-page-actions a,
      .qr-page-actions button,
      .qr-page-actions form {
        width: 100%;
      }

      .qr-page-actions .btn {
        width: 100%;
      }

      .qr-page-top {
        margin-bottom: 10px;
      }

      .qr-page-title {
        font-size: 30px;
      }
      .qr-order-controls .btn {
        flex: 1;
        min-width: 140px;
      }
      .qr-order-controls {
        justify-content: flex-start;
      }
    }

    .qr-size {
      display: inline-flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      flex: 1;
      min-width: 220px;
    }

    .qr-size input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .qr-size label {
      user-select: none;
      cursor: pointer;
      padding: 10px 12px;
      border-radius: 12px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: rgba(255, 255, 255, 0.92);
      font-weight: 800;
      font-size: 13px;
      line-height: 1;
      transition: transform 120ms ease, border-color 120ms ease, background 120ms ease;
    }

    .qr-size input:checked + label {
      background: rgba(196, 155, 99, 0.22);
      border-color: rgba(196, 155, 99, 0.55);
      color: #fff;
      transform: translateY(-1px);
    }

    .qr-size label:hover {
      border-color: rgba(196, 155, 99, 0.35);
    }

    .qr-kicker {
      color: rgba(255, 255, 255, 0.72);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
    }

    .qr-title {
      color: #fff;
      font-weight: 900;
      font-size: 16px;
      line-height: 1.25;
      margin-top: 6px;
      margin-bottom: 6px;
    }

    .qr-desc {
      color: rgba(255, 255, 255, 0.78);
      font-size: 13px;
      line-height: 1.35;
      margin-bottom: 10px;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .qr-price {
      font-weight: 900;
      color: #c49b63;
      font-size: 16px;
    }

    .qr-toolbar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      margin-top: 12px;
    }

    .qr-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 10px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.10);
      color: #fff;
      font-weight: 700;
      font-size: 13px;
    }

    .qr-modal {
      position: fixed;
      inset: 0;
      z-index: 2000;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 18px;
      background: rgba(0, 0, 0, 0.75);
      backdrop-filter: blur(6px);
    }

    .qr-modal.open {
      display: flex;
    }

    .qr-modal-content {
      width: min(980px, 100%);
      background: rgba(0, 0, 0, 0.92);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 16px;
      box-shadow: 0 24px 70px rgba(0, 0, 0, 0.6);
      overflow: hidden;
    }

    .qr-modal-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 14px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      color: #fff;
    }

    .qr-modal-img {
      width: 100%;
      max-height: 72vh;
      object-fit: contain;
      background: #000;
      display: block;
    }

    .qr-icon-btn {
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(255, 255, 255, 0.12);
      color: #fff;
      border-radius: 10px;
      padding: 8px 10px;
      font-weight: 800;
    }

    .qr-icon-btn:hover {
      border-color: rgba(196, 155, 99, 0.35);
    }
  </style>
</head>

<body style="background:#000;">
  <?php
  $tokEsc = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
  $countRes = mysqli_query($conn, "SELECT COALESCE(SUM(quantity),0) AS c FROM cart WHERE session_token='{$tokEsc}'");
  $countRow = $countRes ? mysqli_fetch_assoc($countRes) : ['c' => 0];
  $cartCount = (int)($countRow['c'] ?? 0);
  ?>

  <div class="qr-topbar">
    <div class="container">
      <div class="inner">
        <div class="qr-pill">
          <span class="icon icon-restaurant_menu"></span>
          Table <?php echo (int)$_SESSION['qr_table_number']; ?>
        </div>
        <div class="qr-topbar-actions">
          <a class="qr-kitchen-btn" href="<?php echo url; ?>/kitchen3d/?table=<?php echo (int)$_SESSION['qr_table_number']; ?>" title="Open 3D live kitchen for this table">
            <span class="icon icon-view_module"></span>
            Live Kitchen
          </a>
          <a class="qr-cart-btn" href="<?php echo url; ?>/qr-order/cart.php">
            <span class="icon icon-shopping_cart"></span>
            Order
            <span class="qr-badge"><?php echo $cartCount; ?></span>
          </a>
        </div>
      </div>
    </div>
  </div>

