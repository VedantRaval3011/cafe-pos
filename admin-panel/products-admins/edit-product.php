<?php require_once "../layouts/header.php"; ?>

<?php
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/admin-panel/admins/login.php");
  exit();
}

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
  header("Location: " . url . "/admin-panel/products-admins/show-products.php");
  exit();
}

$res = mysqli_query($conn, "SELECT * FROM products WHERE id = {$product_id} LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($res) === 0) {
  header("Location: " . url . "/admin-panel/products-admins/show-products.php");
  exit();
}
$product = mysqli_fetch_assoc($res);

if (isset($_POST['submit'])) {
  $name = trim($_POST['name'] ?? '');
  $price = trim($_POST['price'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $type = trim($_POST['type'] ?? '');

  if ($name === '' || $price === '' || $description === '' || $type === '') {
    echo "<script>alert('One or more inputs are empty');</script>";
  } else {
    $nameEsc = mysqli_real_escape_string($conn, $name);
    $priceEsc = mysqli_real_escape_string($conn, $price);
    $descEsc = mysqli_real_escape_string($conn, $description);
    $typeEsc = mysqli_real_escape_string($conn, $type);

    $imageName = $product['image'];

    // optional image replace
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
      $tmp = $_FILES['image']['tmp_name'];
      $newName = $_FILES['image']['name'];
      $size = $_FILES['image']['size'];
      $mime = $_FILES['image']['type'];
      $allowed = ['image/jpeg', 'image/png', 'image/gif'];

      if (!in_array($mime, $allowed)) {
        die("Only JPG, PNG, and GIF files are allowed.");
      }
      if ($size > 5000000) {
        die("File size exceeds the maximum limit of 5MB.");
      }
      if (preg_match('/\\s/', $newName)) {
        die("image name should not contain any blank spaces.");
      }

      $folder = "../../images/" . $newName;
      move_uploaded_file($tmp, $folder);

      // delete old image if different
      if (!empty($imageName) && $imageName !== $newName && file_exists("../../images/" . $imageName)) {
        @unlink("../../images/" . $imageName);
      }
      $imageName = $newName;
    }

    $imgEsc = mysqli_real_escape_string($conn, $imageName);
    mysqli_query($conn, "UPDATE products SET name='{$nameEsc}', image='{$imgEsc}', description='{$descEsc}', price='{$priceEsc}', type='{$typeEsc}' WHERE id={$product_id}") or die("Query Unsuccessful");
    echo "<script>alert('Product updated'); window.location.href='" . url . "/admin-panel/products-admins/show-products.php';</script>";
    exit();
  }
}
?>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">Edit Product</h5>
            <a class="btn btn-light" href="show-products.php">Back</a>
          </div>
          <form method="POST" action="edit-product.php?id=<?php echo $product_id; ?>" enctype="multipart/form-data">
            <div class="form-outline mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" />
            </div>
            <div class="form-outline mb-3">
              <label class="form-label">Price</label>
              <input type="text" name="price" class="form-control" value="<?php echo htmlspecialchars($product['price']); ?>" />
            </div>
            <div class="form-group mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            <div class="form-outline mb-3">
              <label class="form-label">Type</label>
              <select name="type" class="form-select form-control">
                <?php
                $types = ["coffee", "drink", "dessert", "starter", "main dish"];
                foreach ($types as $t) {
                  $sel = ($product['type'] === $t) ? "selected" : "";
                  echo "<option value=\"" . htmlspecialchars($t) . "\" {$sel}>" . htmlspecialchars($t) . "</option>";
                }
                ?>
              </select>
            </div>
            <div class="form-outline mb-3">
              <label class="form-label">Image (optional)</label>
              <input type="file" name="image" class="form-control" />
              <div class="mt-2">
                <img src="../../images/<?php echo htmlspecialchars($product['image']); ?>" width="90" height="90" style="object-fit:cover;border-radius:8px;border:1px solid rgba(0,0,0,0.1);" />
              </div>
            </div>
            <button type="submit" name="submit" class="btn btn-primary">Save changes</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

