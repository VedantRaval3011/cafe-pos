<?php require_once "../includes/header.php"; ?>

<?php

// allow checkout for either logged-in user OR QR guest session
$isUser = isset($_SESSION['user_id']);
$isQr = isset($_SESSION['qr_session_token']);
if (!$isUser && !$isQr) {
  header("Location: " . url . "/index.php");
  exit();
}

if (isset($_POST['submit'])) {
  $first_name = $_POST['first-name'];
  $last_name = $_POST['last-name'];
  $country = $_POST['country']; //state/country
  $street_address = $_POST['street-address'];
  $town_city = $_POST['town-or-city'];
  $zip_code = $_POST['postcode-or-zip'];
  $phone = $_POST['phone'];
  $email = $_POST['email'];
  $user_id = $_SESSION['user_id'] ?? null;
  $session_token = $_SESSION['qr_session_token'] ?? null;
  $table_number = $_SESSION['qr_table_number'] ?? null;

  // create order first (unpaid) then redirect to payment page
  $status = "created";
  $total_price = $_SESSION['total_price'] ?? 0;

  $user_id_sql = $user_id ? (int)$user_id : "NULL";
  $session_token_sql = $session_token ? ("'" . mysqli_real_escape_string($conn, $session_token) . "'") : "NULL";
  $table_number_sql = $table_number ? (int)$table_number : "NULL";

  // sql query
  $query = "INSERT INTO orders (first_name, last_name, country, street_address, town, zip_code, phone, email, user_id, session_token, table_number, status, payment_status, total_price)
            VALUES ('{$first_name}','{$last_name}','{$country}','{$street_address}','{$town_city}','{$zip_code}','{$phone}','{$email}',{$user_id_sql},{$session_token_sql},{$table_number_sql},'{$status}','unpaid','{$total_price}')";
  mysqli_query($conn, $query) or die("Query Unsuccessful");
  $order_id = mysqli_insert_id($conn);

  // move cart lines into order_items for invoice/kitchen
  $where = $user_id
    ? ("user_id = " . (int)$user_id)
    : ("session_token = '" . mysqli_real_escape_string($conn, $session_token) . "'");
  $cartRows = mysqli_query($conn, "SELECT * FROM cart WHERE {$where}") or die("Query Unsuccessful");
  while ($c = mysqli_fetch_assoc($cartRows)) {
    $p = (float)$c['price'];
    $q = (int)$c['quantity'];
    $line = $p * $q;
    $nameEsc = mysqli_real_escape_string($conn, $c['name']);
    $imgEsc = mysqli_real_escape_string($conn, $c['image']);
    $descEsc = mysqli_real_escape_string($conn, $c['description']);
    $sizeEsc = mysqli_real_escape_string($conn, $c['size']);
    $pid = (int)$c['product_id'];

    mysqli_query(
      $conn,
      "INSERT INTO order_items (order_id, product_id, name, image, price, size, quantity, line_total, item_status)
       VALUES ({$order_id}, {$pid}, '{$nameEsc}', '{$imgEsc}', {$p}, '{$sizeEsc}', {$q}, {$line}, 'placed')"
    ) or die("Query Unsuccessful");
  }

  // keep minimal user info for payment redirect
  $_SESSION['pending_order_id'] = $order_id;

  echo "<script>window.location.href = 'pay.php?order_id={$order_id}';</script>";
  exit();
}
?>

<section class="home-slider owl-carousel">
  <div class="slider-item" style="background-image: url(<?php echo url; ?>/images/bg_3.jpg)" data-stellar-background-ratio="0.5">
    <div class="overlay"></div>
    <div class="container">
      <div class="row slider-text justify-content-center align-items-center">
        <div class="col-md-7 col-sm-12 text-center ftco-animate">
          <h1 class="mb-3 mt-5 bread">Checkout</h1>
          <p class="breadcrumbs">
            <span class="mr-2"><a href="<?php echo url; ?>/index.php">Home</a></span>
            <span>Checkout</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="ftco-section">
  <div class="container">
    <div class="row">
      <div class="col-md-12 ftco-animate">
        <form action="checkout.php" method="post" class="billing-form ftco-bg-dark p-3 p-md-5">
          <h3 class="mb-4 billing-heading">Billing Details</h3>
          <div class="row align-items-end">
            <div class="col-md-6">
              <div class="form-group">
                <label for="first-name">First Name *</label>
                <input type="text" id="first-name" name="first-name" class="form-control" placeholder="" />
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="last-name">Last Name</label>
                <input type="text" id="last-name" name="last-name" class="form-control" placeholder="" />
              </div>
            </div>
            <div class="w-100"></div>
            <div class="col-md-12">
              <div class="form-group">
                <label for="country">State / Country *</label>
                <div class="select-wrap">
                  <div class="icon">
                    <span class="ion-ios-arrow-down"></span>
                  </div>
                  <select name="country" id="country" class="form-control">
                    <option value="" selected hidden>Select State/Country</option>
                    <option value="France">France</option>
                    <option value="Italy">Italy</option>
                    <option value="India">India</option>
                    <option value="Philippines">Philippines</option>
                    <option value="South Korea">South Korea</option>
                    <option value="Hongkong">Hongkong</option>
                    <option value="Japan">Japan</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="w-100"></div>
            <div class="col-md-12">
              <div class="form-group">
                <label for="street-address">Street Address *</label>
                <input type="text" id="street-address" name="street-address" class="form-control" placeholder="House number and street name" />
              </div>
            </div>
            <div class="w-100"></div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="town-or-city">Town / City *</label>
                <input type="text" id="town-or-city" name="town-or-city" class="form-control" placeholder="" />
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="postcode-or-zip">Postcode / ZIP *</label>
                <input type="text" id="postcode-or-zip" name="postcode-or-zip" class="form-control" placeholder="" />
              </div>
            </div>
            <div class="w-100"></div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="phone">Phone *</label>
                <input type="text" id="phone" name="phone" class="form-control" placeholder="" />
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="" />
              </div>
            </div>
            <div class="w-100"></div>
            <div class="col-md-12">
              <div class="form-group mt-4">
                <div class="radio">
                  <p>
                    <button type="submit" name="submit" id="submit" class="btn btn-primary py-3 px-4">Place an order</button>
                  </p>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <!-- .col-md-8 -->
    </div>
  </div>
</section>
<!-- .section -->

<script>
  const billingForm = document.querySelector(".billing-form");

  // input fields
  const firstName = document.querySelector("#first-name");
  const lastName = document.querySelector("#last-name");
  const country = document.querySelector("#country");
  const streetAddress = document.querySelector("#street-address");
  const townCity = document.querySelector("#town-or-city");
  const postcodeZip = document.querySelector("#postcode-or-zip");
  const phone = document.querySelector("#phone");
  const email = document.querySelector("#email");

  billingForm.addEventListener("submit", (e) => {
    if (firstName.value === "" || country.value === "" || streetAddress.value === "" || townCity.value === "" || postcodeZip.value === "" || phone.value === "" || email.value === "") {
      e.preventDefault();
      alert("Please fill all the details !!");
    }
  })
</script>

<?php require_once "../includes/footer.php"; ?>