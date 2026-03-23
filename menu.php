<?php require_once "includes/header.php"; ?>

<?php
// Quick add-to-cart from menu cards (works for logged-in user OR QR guest session)
$isUser = isset($_SESSION['user_id']);
$isQr = isset($_SESSION['qr_session_token']);

if (isset($_POST['add_to_cart'])) {
  if (!$isUser && !$isQr) {
    echo "<script>alert('Scan table QR or login to start ordering'); window.location.href='" . url . "/index.php';</script>";
    exit();
  }

  $product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
  $size = $_POST['size'] ?? 'Medium';
  $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
  if ($quantity < 1) $quantity = 1;
  if ($quantity > 20) $quantity = 20;

  $prodRes = mysqli_query($conn, "SELECT * FROM products WHERE id = {$product_id} LIMIT 1") or die("Query Unsuccessful");
  if (mysqli_num_rows($prodRes) === 0) {
    echo "<script>alert('Product not found'); window.location.href='" . url . "/menu.php';</script>";
    exit();
  }
  $p = mysqli_fetch_assoc($prodRes);

  $name = mysqli_real_escape_string($conn, $p['name']);
  $image = mysqli_real_escape_string($conn, $p['image']);
  $description = mysqli_real_escape_string($conn, $p['description']);
  $price = mysqli_real_escape_string($conn, $p['price']);
  $sizeEsc = mysqli_real_escape_string($conn, $size);

  $user_id = $isUser ? (int)$_SESSION['user_id'] : null;
  $session_token = $isQr ? $_SESSION['qr_session_token'] : null;
  $table_number = $isQr && isset($_SESSION['qr_table_number']) ? (int)$_SESSION['qr_table_number'] : null;

  // If item already exists in cart for this user/session + same size, increment quantity
  if ($isUser) {
    $check = mysqli_query($conn, "SELECT id, quantity FROM cart WHERE user_id={$user_id} AND product_id={$product_id} AND size='{$sizeEsc}' LIMIT 1") or die("Query Unsuccessful");
  } else {
    $tokEsc = mysqli_real_escape_string($conn, $session_token);
    $check = mysqli_query($conn, "SELECT id, quantity FROM cart WHERE session_token='{$tokEsc}' AND product_id={$product_id} AND size='{$sizeEsc}' LIMIT 1") or die("Query Unsuccessful");
  }

  if (mysqli_num_rows($check) > 0) {
    $row = mysqli_fetch_assoc($check);
    $newQty = (int)$row['quantity'] + $quantity;
    if ($newQty > 50) $newQty = 50;
    mysqli_query($conn, "UPDATE cart SET quantity={$newQty} WHERE id=" . (int)$row['id']) or die("Query Unsuccessful");
  } else {
    $user_id_sql = $user_id ? (int)$user_id : "NULL";
    $session_token_sql = $session_token ? ("'" . mysqli_real_escape_string($conn, $session_token) . "'") : "NULL";
    $table_number_sql = $table_number ? (int)$table_number : "NULL";

    mysqli_query(
      $conn,
      "INSERT INTO cart (name, image, price, description, size, quantity, product_id, user_id, session_token, table_number)
       VALUES ('{$name}','{$image}','{$price}','{$description}','{$sizeEsc}',{$quantity},{$product_id},{$user_id_sql},{$session_token_sql},{$table_number_sql})"
    ) or die("Query Unsuccessful");
  }

  echo "<script>window.location.href='" . url . "/menu.php?added=1';</script>";
  exit();
}
?>

<section class="home-slider owl-carousel">
  <div class="slider-item" style="background-image: url(images/bg_3.jpg)" data-stellar-background-ratio="0.5">
    <div class="overlay"></div>
    <div class="container">
      <div class="row slider-text justify-content-center align-items-center">
        <div class="col-md-7 col-sm-12 text-center ftco-animate">
          <h1 class="mb-3 mt-5 bread">Our Menu</h1>
          <p class="breadcrumbs">
            <span class="mr-2"><a href="index.php">Home</a></span>
            <span>Menu</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if (isset($_SESSION['qr_session_token']) && isset($_SESSION['qr_table_number'])) { ?>
  <div class="container mt-4">
    <div class="p-3" style="background:#1a1a1a;border-radius:10px;color:#fff;">
      <div class="d-md-flex justify-content-between align-items-center">
        <div>
          <strong>Table <?php echo (int)$_SESSION['qr_table_number']; ?></strong> ordering is active.
          <span style="opacity:0.85;">Add items below and open cart anytime.</span>
          <?php if (isset($_GET['added']) && $_GET['added'] === '1') { ?>
            <span style="margin-left:10px;color:#8be28b;">Item added to cart.</span>
          <?php } ?>
        </div>
        <div class="mt-2 mt-md-0">
          <a class="btn btn-primary py-2 px-3" href="<?php echo url; ?>/products/cart.php">View Cart</a>
        </div>
      </div>
    </div>
  </div>
<?php } ?>

<section class="ftco-intro">
  <div class="container-wrap">
    <div class="wrap d-md-flex align-items-xl-end">
      <div class="info">
        <div class="row no-gutters">
          <div class="col-md-4 d-flex ftco-animate">
            <div class="icon"><span class="icon-phone"></span></div>
            <div class="text">
              <h3>000 (123) 456 7890</h3>
              <p>
                A small river named Duden flows by their place and supplies.
              </p>
            </div>
          </div>
          <div class="col-md-4 d-flex ftco-animate">
            <div class="icon"><span class="icon-my_location"></span></div>
            <div class="text">
              <h3>198 West 21th Street</h3>
              <p>
                203 Fake St. Mountain View, San Francisco, California, USA
              </p>
            </div>
          </div>
          <div class="col-md-4 d-flex ftco-animate">
            <div class="icon"><span class="icon-clock-o"></span></div>
            <div class="text">
              <h3>Open Monday-Friday</h3>
              <p>8:00am - 9:00pm</p>
            </div>
          </div>
        </div>
      </div>
      <div class="book p-4">
        <h3>Book a Table</h3>
        <form action="booking/book.php" method="POST" class="appointment-form">
          <div class="d-md-flex">
            <div class="form-group">
              <input type="text" id="first_name" name="first_name" class="form-control" placeholder="First Name*" />
            </div>
            <div class="form-group ml-md-4">
              <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Last Name" />
            </div>
          </div>
          <div class="d-md-flex">
            <div class="form-group">
              <div class="input-wrap">
                <div class="icon">
                  <span class="ion-md-calendar"></span>
                </div>
                <input type="text" id="date" name="date" class="form-control appointment_date" placeholder="Date*" />
              </div>
            </div>
            <div class="form-group ml-md-4">
              <div class="input-wrap">
                <div class="icon"><span class="ion-ios-clock"></span></div>
                <input type="text" id="time" name="time" class="form-control appointment_time" placeholder="Time*" />
              </div>
            </div>
            <div class="form-group ml-md-4">
              <input type="text" id="phone" name="phone" class="form-control" placeholder="Phone*" />
            </div>
          </div>
          <div class="d-md-flex">
            <div class="form-group">
              <textarea name="message" cols="30" rows="2" class="form-control" placeholder="Message"></textarea>
            </div>
            <div class="form-group ml-md-4">
              <?php if (isset($_SESSION['user_id'])) { ?>
                <button type="submit" name="submit" class="btn btn-white py-3 px-4">Book a Table</button>
              <?php } else { ?>
                <a href="auth/login.php" class="btn btn-white py-3 px-4">Login to Book Table</a>
              <?php } ?>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>

<section class="ftco-section">
  <div class="container">
    <div class="row">
      <div class="col-md-6 mb-5 pb-3">
        <h3 class="mb-5 heading-pricing ftco-animate">Desserts</h3>
        <?php
        // fetching desserts
        $query1 = "SELECT * FROM products where type='dessert'";
        $desserts = mysqli_query($conn, $query1) or die("Query Unsuccessful");

        if (mysqli_num_rows($desserts) > 0) {
          while ($dessert = mysqli_fetch_assoc($desserts)) {
        ?>
            <div class="pricing-entry d-flex ftco-animate">
              <div class="img" style="background-image: url(images/<?php echo $dessert['image']; ?>)"></div>
              <div class="desc pl-3">
                <div class="d-flex text align-items-center">
                  <h3><span><?php echo $dessert['name']; ?></span></h3>
                  <span class="price">$<?php echo $dessert['price']; ?></span>
                </div>
                <div class="d-block">
                  <p>
                    <?php echo $dessert['description']; ?>
                  </p>
                </div>
              </div>
            </div>
        <?php }
        } ?>
      </div>

      <div class="col-md-6 mb-5 pb-3">
        <h3 class="mb-5 heading-pricing ftco-animate">Drinks</h3>
        <?php
        // fetching drinks
        $query2 = "SELECT * FROM products where type='drink'";
        $drinks = mysqli_query($conn, $query2) or die("Query Unsuccessful");

        if (mysqli_num_rows($drinks) > 0) {
          while ($drink = mysqli_fetch_assoc($drinks)) {
        ?>
            <div class="pricing-entry d-flex ftco-animate">
              <div class="img" style="background-image: url(images/<?php echo $drink['image']; ?>)"></div>
              <div class="desc pl-3">
                <div class="d-flex text align-items-center">
                  <h3><span><?php echo $drink['name']; ?></span></h3>
                  <span class="price">$<?php echo $drink['price']; ?></span>
                </div>
                <div class="d-block">
                  <p>
                    <?php echo $drink['description']; ?>
                  </p>
                </div>
              </div>
            </div>
        <?php }
        } ?>
      </div>

      <div class="col-md-6">
        <h3 class="mb-5 heading-pricing ftco-animate">Starter</h3>
        <?php
        // fetching starters
        $query2 = "SELECT * FROM products where type='starter'";
        $drinks = mysqli_query($conn, $query2) or die("Query Unsuccessful");

        if (mysqli_num_rows($drinks) > 0) {
          while ($drink = mysqli_fetch_assoc($drinks)) {
        ?>
            <div class="pricing-entry d-flex ftco-animate">
              <div class="img" style="background-image: url(images/<?php echo $drink['image']; ?>)"></div>
              <div class="desc pl-3">
                <div class="d-flex text align-items-center">
                  <h3><span><?php echo $drink['name']; ?></span></h3>
                  <span class="price">$<?php echo $drink['price']; ?></span>
                </div>
                <div class="d-block">
                  <p>
                    <?php echo $drink['description']; ?>
                  </p>
                </div>
              </div>
            </div>
        <?php }
        } ?>
      </div>

      <div class="col-md-6">
        <h3 class="mb-5 heading-pricing ftco-animate">Main Dish</h3>
        <?php
        // fetching main dish
        $query2 = "SELECT * FROM products where type='main dish'";
        $drinks = mysqli_query($conn, $query2) or die("Query Unsuccessful");

        if (mysqli_num_rows($drinks) > 0) {
          while ($drink = mysqli_fetch_assoc($drinks)) {
        ?>
            <div class="pricing-entry d-flex ftco-animate">
              <div class="img" style="background-image: url(images/<?php echo $drink['image']; ?>)"></div>
              <div class="desc pl-3">
                <div class="d-flex text align-items-center">
                  <h3><span><?php echo $drink['name']; ?></span></h3>
                  <span class="price">$<?php echo $drink['price']; ?></span>
                </div>
                <div class="d-block">
                  <p>
                    <?php echo $drink['description']; ?>
                  </p>
                </div>
              </div>
            </div>
        <?php }
        } ?>
      </div>
    </div>
  </div>
</section>

<section class="ftco-menu mb-5 pb-5">
  <div class="container">
    <div class="row justify-content-center mb-5">
      <div class="col-md-7 heading-section text-center ftco-animate">
        <span class="subheading">Discover</span>
        <h2 class="mb-4">Our Products</h2>
        <p>
          Far far away, behind the word mountains, far from the countries
          Vokalia and Consonantia, there live the blind texts.
        </p>
      </div>
    </div>
    <div class="row d-md-flex">
      <div class="col-lg-12 ftco-animate p-md-5">
        <div class="row">
          <div class="col-md-12 nav-link-wrap mb-5">
            <div class="nav ftco-animate nav-pills justify-content-center" id="v-pills-tab" role="tablist" aria-orientation="vertical">
              <a class="nav-link active" id="v-pills-1-tab" data-toggle="pill" href="#v-pills-1" role="tab" aria-controls="v-pills-1" aria-selected="true">Coffee</a>

              <a class="nav-link" id="v-pills-2-tab" data-toggle="pill" href="#v-pills-2" role="tab" aria-controls="v-pills-2" aria-selected="false">Drinks</a>

              <a class="nav-link" id="v-pills-3-tab" data-toggle="pill" href="#v-pills-3" role="tab" aria-controls="v-pills-3" aria-selected="false">Desserts</a>

              <a class="nav-link" id="v-pills-4-tab" data-toggle="pill" href="#v-pills-4" role="tab" aria-controls="v-pills-3" aria-selected="false">Main Dish</a>
            </div>
          </div>
          <div class="col-md-12 d-flex align-items-center">
            <div class="tab-content ftco-animate" id="v-pills-tabContent">
              <div class="tab-pane fade show active" id="v-pills-1" role="tabpanel" aria-labelledby="v-pills-1-tab">
                <div class="row">
                  <?php
                  // fetching coffee
                  $query2 = "SELECT * FROM products where type='coffee'";
                  $drinks = mysqli_query($conn, $query2) or die("Query Unsuccessful");

                  if (mysqli_num_rows($drinks) > 0) {
                    while ($drink = mysqli_fetch_assoc($drinks)) {
                  ?>
                      <div class="col-md-4 text-center">
                        <div class="menu-wrap">
                          <a href="products/product-single.php?id=<?php echo $drink['id']; ?>" class="menu-img img mb-4" style="background-image: url(images/<?php echo $drink['image']; ?>)"></a>
                          <div class="text">
                            <h3><a href="products/product-single.php?id=<?php echo $drink['id']; ?>"><?php echo $drink['name']; ?></a></h3>
                            <p>
                              <?php echo $drink['description']; ?>
                            </p>
                            <p class="price"><span>$<?php echo $drink['price']; ?></span></p>
                            <p>
                              <?php if (isset($_SESSION['user_id']) || isset($_SESSION['qr_session_token'])) { ?>
                                <form method="post" action="menu.php" style="display:inline-block;">
                                  <input type="hidden" name="product_id" value="<?php echo (int)$drink['id']; ?>">
                                  <input type="hidden" name="size" value="Medium">
                                  <input type="hidden" name="quantity" value="1">
                                  <button type="submit" name="add_to_cart" class="btn btn-primary">Add to Cart</button>
                                </form>
                                <a href="products/product-single.php?id=<?php echo $drink['id']; ?>" class="btn btn-white btn-outline-white">Customize</a>
                              <?php } else { ?>
                                <a href="qr.php?table=1" class="btn btn-primary">Scan QR to Order</a>
                              <?php } ?>
                            </p>
                          </div>
                        </div>
                      </div>
                  <?php }
                  } ?>
                </div>
              </div>

              <div class="tab-pane fade" id="v-pills-2" role="tabpanel" aria-labelledby="v-pills-2-tab">
                <div class="row">
                  <?php
                  // fetching drinks
                  $query2 = "SELECT * FROM products where type='drink'";
                  $drinks = mysqli_query($conn, $query2) or die("Query Unsuccessful");

                  if (mysqli_num_rows($drinks) > 0) {
                    while ($drink = mysqli_fetch_assoc($drinks)) {
                  ?>
                      <div class="col-md-4 text-center">
                        <div class="menu-wrap">
                          <a href="products/product-single.php?id=<?php echo $drink['id']; ?>" class="menu-img img mb-4" style="background-image: url(images/<?php echo $drink['image']; ?>)"></a>
                          <div class="text">
                            <h3><a href="products/product-single.php?id=<?php echo $drink['id']; ?>"><?php echo $drink['name']; ?></a></h3>
                            <p>
                              <?php echo $drink['description']; ?>
                            </p>
                            <p class="price"><span>$<?php echo $drink['price']; ?></span></p>
                            <p>
                              <?php if (isset($_SESSION['user_id']) || isset($_SESSION['qr_session_token'])) { ?>
                                <form method="post" action="menu.php" style="display:inline-block;">
                                  <input type="hidden" name="product_id" value="<?php echo (int)$drink['id']; ?>">
                                  <input type="hidden" name="size" value="Medium">
                                  <input type="hidden" name="quantity" value="1">
                                  <button type="submit" name="add_to_cart" class="btn btn-primary">Add to Cart</button>
                                </form>
                                <a href="products/product-single.php?id=<?php echo $drink['id']; ?>" class="btn btn-white btn-outline-white">Customize</a>
                              <?php } else { ?>
                                <a href="qr.php?table=1" class="btn btn-primary">Scan QR to Order</a>
                              <?php } ?>
                            </p>
                          </div>
                        </div>
                      </div>
                  <?php }
                  } ?>
                </div>
              </div>

              <div class="tab-pane fade" id="v-pills-3" role="tabpanel" aria-labelledby="v-pills-3-tab">
                <div class="row">
                  <?php
                  // fetching desserts
                  $query1 = "SELECT * FROM products where type='dessert'";
                  $desserts = mysqli_query($conn, $query1) or die("Query Unsuccessful");

                  if (mysqli_num_rows($desserts) > 0) {
                    while ($dessert = mysqli_fetch_assoc($desserts)) {
                  ?>
                      <div class="col-md-4 text-center">
                        <div class="menu-wrap">
                          <a href="products/product-single.php?id=<?php echo $dessert['id']; ?>" class="menu-img img mb-4" style="background-image: url(images/<?php echo $dessert['image']; ?>)"></a>
                          <div class="text">
                            <h3><a href="products/product-single.php?id=<?php echo $dessert['id']; ?>"><?php echo $dessert['name']; ?></a></h3>
                            <p>
                              <?php echo $dessert['description']; ?>
                            </p>
                            <p class="price"><span>$<?php echo $dessert['price']; ?></span></p>
                            <p>
                              <?php if (isset($_SESSION['user_id']) || isset($_SESSION['qr_session_token'])) { ?>
                                <form method="post" action="menu.php" style="display:inline-block;">
                                  <input type="hidden" name="product_id" value="<?php echo (int)$dessert['id']; ?>">
                                  <input type="hidden" name="size" value="Medium">
                                  <input type="hidden" name="quantity" value="1">
                                  <button type="submit" name="add_to_cart" class="btn btn-primary">Add to Cart</button>
                                </form>
                                <a href="products/product-single.php?id=<?php echo $dessert['id']; ?>" class="btn btn-white btn-outline-white">Customize</a>
                              <?php } else { ?>
                                <a href="qr.php?table=1" class="btn btn-primary">Scan QR to Order</a>
                              <?php } ?>
                            </p>
                          </div>
                        </div>
                      </div>
                  <?php }
                  } ?>
                </div>
              </div>

              <div class="tab-pane fade" id="v-pills-4" role="tabpanel" aria-labelledby="v-pills-4-tab">
                <div class="row">
                  <?php
                  // fetching main dish
                  $query1 = "SELECT * FROM products where type='main dish'";
                  $desserts = mysqli_query($conn, $query1) or die("Query Unsuccessful");

                  if (mysqli_num_rows($desserts) > 0) {
                    while ($dessert = mysqli_fetch_assoc($desserts)) {
                  ?>
                      <div class="col-md-4 text-center">
                        <div class="menu-wrap">
                          <a href="products/product-single.php?id=<?php echo $dessert['id']; ?>" class="menu-img img mb-4" style="background-image: url(images/<?php echo $dessert['image']; ?>)"></a>
                          <div class="text">
                            <h3><a href="products/product-single.php?id=<?php echo $dessert['id']; ?>"><?php echo $dessert['name']; ?></a></h3>
                            <p>
                              <?php echo $dessert['description']; ?>
                            </p>
                            <p class="price"><span>$<?php echo $dessert['price']; ?></span></p>
                            <p>
                              <?php if (isset($_SESSION['user_id']) || isset($_SESSION['qr_session_token'])) { ?>
                                <form method="post" action="menu.php" style="display:inline-block;">
                                  <input type="hidden" name="product_id" value="<?php echo (int)$dessert['id']; ?>">
                                  <input type="hidden" name="size" value="Medium">
                                  <input type="hidden" name="quantity" value="1">
                                  <button type="submit" name="add_to_cart" class="btn btn-primary">Add to Cart</button>
                                </form>
                                <a href="products/product-single.php?id=<?php echo $dessert['id']; ?>" class="btn btn-white btn-outline-white">Customize</a>
                              <?php } else { ?>
                                <a href="qr.php?table=1" class="btn btn-primary">Scan QR to Order</a>
                              <?php } ?>
                            </p>
                          </div>
                        </div>
                      </div>
                  <?php }
                  } ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
  const bookTableForm = document.querySelector(".appointment-form");

  // input fields
  const firstName = document.querySelector("#first_name");
  const date = document.querySelector("#date");
  const time = document.querySelector("#time");
  const phone = document.querySelector("#phone");

  // form authentication
  bookTableForm.addEventListener("submit", (e) => {
    if (firstName.value === "" || date.value === "" || time.value === "" || phone.value === "") {
      e.preventDefault();
      alert("Please fill all the Mandatory details !!");
    }
  })
</script>

<?php require_once "includes/footer.php"; ?>