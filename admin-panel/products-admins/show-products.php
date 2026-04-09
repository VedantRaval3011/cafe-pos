<?php require_once "../layouts/header.php"; ?>

<?php

// if admin not logged in
// denied to access this page
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/index.php");
  exit();
}

//fetch all orders from db
$product_query = "SELECT * FROM products";
$query_result = mysqli_query($conn, $product_query) or die("Query Unsuccessful");

?>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
            <h5 class="card-title mb-0">Products</h5>
            <a href="create-products.php" class="btn btn-primary">Add New Product</a>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th class="text-center">Id</th>
                  <th class="text-center">name</th>
                  <th class="text-center">image</th>
                  <th class="text-center">Price (₹)</th>
                  <th class="text-center">type</th>
                  <th class="text-center">actions</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (mysqli_num_rows($query_result) > 0) {
                  while ($product = mysqli_fetch_assoc($query_result)) {
                ?>
                    <tr>
                      <td class="text-center"><?php echo $product['id']; ?></td>
                      <td class="text-center"><?php echo $product['name']; ?></td>
                      <td class="text-center"><img src="../../images/<?php echo $product['image']; ?>" height="60px" width="60px"></td>
                      <td class="text-center">₹<?php echo htmlspecialchars($product['price']); ?></td>
                      <td class="text-center"><?php echo $product['type']; ?></td>
                      <td class="text-center">
                        <a href="edit-product.php?id=<?php echo (int)$product['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                        <a href="delete-product.php?id=<?php echo (int)$product['id']; ?>" onclick="return confirm('Delete this product?');" class="btn btn-sm btn-outline-danger">Delete</a>
                      </td>
                    </tr>
                <?php }
                } ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>

</html>