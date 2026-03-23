<?php require_once "../layouts/header.php"; ?>

<?php

// if admin not logged in
// denied to access bookings page
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/index.php"); // Redirect to the home page
  exit();
}

//fetch all bookings from db
$booking_query = "SELECT * FROM bookings";
$query_result = mysqli_query($conn, $booking_query) or die("Query Unsuccessful");

?>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
            <h5 class="card-title mb-0">Bookings</h5>
          </div>
          <div class="table-responsive">
            <table class="table table-striped table-hover">
              <thead>
                <tr>
                  <th class="text-center">Booking Id</th>
                  <th class="text-center">First Name</th>
                  <th class="text-center">Last Name</th>
                  <th class="text-center">Cust. Id</th>
                  <th class="text-center">Date</th>
                  <th class="text-center">Time</th>
                  <th class="text-center">Phone</th>
                  <th class="text-center">Message</th>
                  <th class="text-center">Status</th>
                  <th class="text-center">Update Status</th>
                  <th class="text-center">Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php
                if (mysqli_num_rows($query_result) > 0) {
                  while ($booking = mysqli_fetch_assoc($query_result)) {
                ?>
                    <tr>
                      <td class="text-center"><?php echo $booking["id"]; ?></td>
                      <td class="text-center"><?php echo $booking["first_name"]; ?></td>
                      <td class="text-center"><?php echo $booking["last_name"]; ?></td>
                      <td class="text-center"><?php echo $booking["user_id"]; ?></td>
                      <td class="text-center"><?php echo $booking["date"]; ?></td>
                      <td class="text-center"><?php echo $booking["time"]; ?></td>
                      <td class="text-center"><?php echo $booking["phone"]; ?></td>
                      <td class="text-center"><?php echo $booking["message"]; ?></td>
                      <td class="text-center"><span class="badge badge-secondary" style="font-size:12px;"><?php echo htmlspecialchars($booking["status"]); ?></span></td>
                      <td class="text-center"><a href="update-status.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-primary">Update</a></td>
                      <td class="text-center"><a onclick="return confirm('Delete this booking?');" href="delete-bookings.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm btn-outline-danger">Delete</a></td>
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