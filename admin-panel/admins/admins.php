<?php require_once "../layouts/header.php"; ?>

<?php

// fetching desserts
$query = "SELECT * FROM admins";
$result = mysqli_query($conn, $query) or die("Query Unsuccessful");

?>

<div class="container-fluid">
  <div class="row">
    <div class="col">
      <div class="card">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center flex-wrap mb-3" style="gap:10px;">
            <h5 class="card-title mb-0">Admins</h5>
            <a href="create-admins.php" class="btn btn-primary">Create Admin</a>
          </div>
          <table class="table table-striped table-hover">
            <thead>
              <tr>
                <th scope="col">Id</th>
                <th scope="col">Name</th>
                <th scope="col">Email</th>
                <th scope="col" class="text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              if (mysqli_num_rows($result) > 0) {
                while ($row = mysqli_fetch_assoc($result)) {
              ?>
                  <tr>
                    <th scope="row"><?php echo $row['id']; ?></th>
                    <td><?php echo $row['admin_name']; ?></td>
                    <td class="admin-email"><?php echo $row['email']; ?></td>
                    <td class="text-right">
                      <a class="btn btn-sm btn-outline-primary" href="edit-admin.php?id=<?php echo (int)$row['id']; ?>">Edit</a>
                      <?php if (!isset($_SESSION['admin_id']) || (int)$_SESSION['admin_id'] !== (int)$row['id']) { ?>
                        <a class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this admin?');" href="delete-admin.php?id=<?php echo (int)$row['id']; ?>">Delete</a>
                      <?php } ?>
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
</body>

</html>