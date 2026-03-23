<?php
require_once __DIR__ . "/_bootstrap.php";

// One-tap add to cart
if (isset($_POST['add_to_cart']) || isset($_POST['buy_now'])) {
  $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
  $size = $_POST['size'] ?? 'Medium';
  $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
  if ($quantity < 1) $quantity = 1;
  if ($quantity > 20) $quantity = 20;

  $prodRes = mysqli_query($conn, "SELECT * FROM products WHERE id = {$product_id} LIMIT 1") or die("Query Unsuccessful");
  if (mysqli_num_rows($prodRes) > 0) {
    $p = mysqli_fetch_assoc($prodRes);

    $tok = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
    $sizeEsc = mysqli_real_escape_string($conn, $size);

    $check = mysqli_query($conn, "SELECT id, quantity FROM cart WHERE session_token='{$tok}' AND product_id={$product_id} AND size='{$sizeEsc}' LIMIT 1") or die("Query Unsuccessful");
    if (mysqli_num_rows($check) > 0) {
      $row = mysqli_fetch_assoc($check);
      $newQty = (int)$row['quantity'] + $quantity;
      if ($newQty > 50) $newQty = 50;
      mysqli_query($conn, "UPDATE cart SET quantity={$newQty} WHERE id=" . (int)$row['id']) or die("Query Unsuccessful");
    } else {
      $name = mysqli_real_escape_string($conn, $p['name']);
      $image = mysqli_real_escape_string($conn, $p['image']);
      $description = mysqli_real_escape_string($conn, $p['description']);
      $price = mysqli_real_escape_string($conn, $p['price']);
      $table = (int)$_SESSION['qr_table_number'];

      mysqli_query(
        $conn,
        "INSERT INTO cart (name, image, price, description, size, quantity, product_id, user_id, session_token, table_number)
         VALUES ('{$name}','{$image}','{$price}','{$description}','{$sizeEsc}',{$quantity},{$product_id},NULL,'{$tok}',{$table})"
      ) or die("Query Unsuccessful");
    }
  }

  if (isset($_POST['buy_now'])) {
    header("Location: " . url . "/qr-order/cart.php?buy_now=1");
  } else {
    header("Location: " . url . "/qr-order/menu.php?added=1&type=" . urlencode($_GET['type'] ?? 'coffee'));
  }
  exit();
}

$types = [
  "coffee" => "Coffee",
  "drink" => "Drinks",
  "dessert" => "Desserts",
  "starter" => "Starter",
  "main dish" => "Main Dish"
];

$active = $_GET['type'] ?? 'coffee';
if (!array_key_exists($active, $types)) $active = 'coffee';

$activeEsc = mysqli_real_escape_string($conn, $active);
$prod = mysqli_query($conn, "SELECT * FROM products WHERE type='{$activeEsc}' ORDER BY id DESC") or die("Query Unsuccessful");
?>

<?php require_once __DIR__ . "/_header.php"; ?>

<div class="container qr-page">
  <?php if (isset($_GET['added']) && $_GET['added'] === '1') { ?>
    <div class="alert alert-success">Added to your order. You can continue ordering or open your order.</div>
  <?php } ?>

  <div class="d-flex flex-wrap" style="gap:10px;align-items:center;justify-content:space-between;">
    <div class="d-flex flex-wrap" style="gap:10px;">
    <?php foreach ($types as $k => $label) { ?>
      <a href="<?php echo url; ?>/qr-order/menu.php?type=<?php echo urlencode($k); ?>"
        class="btn <?php echo $k === $active ? 'btn-primary' : 'btn-outline-primary'; ?>">
        <?php echo htmlspecialchars($label); ?>
      </a>
    <?php } ?>
    </div>
    <div class="qr-chip">
      <span class="icon icon-room"></span>
      Ordering for Table <?php echo (int)$_SESSION['qr_table_number']; ?>
    </div>
  </div>

  <div class="row mt-3">
    <?php while ($p = mysqli_fetch_assoc($prod)) { ?>
      <div class="col-md-4 mb-4">
        <div class="qr-card qr-product">
          <div class="qr-img qr-clickable"
            data-zoom-title="<?php echo htmlspecialchars($p['name']); ?>"
            data-zoom-src="<?php echo url; ?>/images/<?php echo htmlspecialchars($p['image']); ?>"
            style="background-image:url(<?php echo url; ?>/images/<?php echo htmlspecialchars($p['image']); ?>)"></div>

          <div class="qr-kicker"><?php echo htmlspecialchars($types[$active]); ?></div>
          <div class="qr-title"><?php echo htmlspecialchars($p['name']); ?></div>
          <div class="qr-desc"><?php echo htmlspecialchars($p['description']); ?></div>

          <div class="qr-toolbar">
            <div class="qr-price">₹<?php echo htmlspecialchars($p['price']); ?></div>
            <div class="qr-chip">
              <span class="icon icon-check"></span>
              Ready in 10–15 min
            </div>
          </div>

          <div class="qr-actions">
            <form method="post" action="<?php echo url; ?>/qr-order/menu.php?type=<?php echo urlencode($active); ?>" style="display:inline-flex;gap:10px;align-items:center;flex-wrap:wrap;width:100%;">
              <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
              <input type="hidden" name="quantity" value="1">
              <div class="qr-size">
                <?php $gid = "p" . (int)$p['id']; ?>
                <input id="<?php echo $gid; ?>_s" type="radio" name="size" value="Small">
                <label for="<?php echo $gid; ?>_s">Small</label>
                <input id="<?php echo $gid; ?>_m" type="radio" name="size" value="Medium" checked>
                <label for="<?php echo $gid; ?>_m">Medium</label>
                <input id="<?php echo $gid; ?>_l" type="radio" name="size" value="Large">
                <label for="<?php echo $gid; ?>_l">Large</label>
                <input id="<?php echo $gid; ?>_xl" type="radio" name="size" value="Extra Large">
                <label for="<?php echo $gid; ?>_xl">XL</label>
              </div>
              <button type="submit" name="add_to_cart" class="btn btn-primary" style="height:44px;flex:1;min-width:140px;">Add to cart</button>
              <button type="submit" name="buy_now" class="btn btn-white btn-outline-white" style="height:44px;flex:1;min-width:140px;">Buy now</button>
            </form>
          </div>
        </div>
      </div>
    <?php } ?>
  </div>
</div>

<div class="qr-modal" id="qrImgModal" aria-hidden="true">
  <div class="qr-modal-content">
    <div class="qr-modal-top">
      <div id="qrModalTitle" style="font-weight:900;">Preview</div>
      <button class="qr-icon-btn" type="button" id="qrModalClose">Close</button>
    </div>
    <img id="qrModalImg" class="qr-modal-img" alt="Preview" />
  </div>
</div>

<script>
  (function() {
    const modal = document.getElementById("qrImgModal");
    const img = document.getElementById("qrModalImg");
    const title = document.getElementById("qrModalTitle");
    const closeBtn = document.getElementById("qrModalClose");

    function openModal(src, t) {
      img.src = src;
      title.textContent = t || "Preview";
      modal.classList.add("open");
      modal.setAttribute("aria-hidden", "false");
      document.body.style.overflow = "hidden";
    }

    function closeModal() {
      modal.classList.remove("open");
      modal.setAttribute("aria-hidden", "true");
      img.src = "";
      document.body.style.overflow = "";
    }

    document.querySelectorAll(".qr-img.qr-clickable").forEach((el) => {
      el.addEventListener("click", () => {
        const src = el.getAttribute("data-zoom-src");
        const t = el.getAttribute("data-zoom-title");
        if (src) openModal(src, t);
      });
    });

    closeBtn.addEventListener("click", closeModal);
    modal.addEventListener("click", (e) => {
      if (e.target === modal) closeModal();
    });
    document.addEventListener("keydown", (e) => {
      if (e.key === "Escape") closeModal();
    });
  })();
</script>

<?php require_once __DIR__ . "/_footer.php"; ?>

