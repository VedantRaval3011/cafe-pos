<?php require_once "../layouts/header.php"; ?>

<?php
if (!isset($_SESSION['admin_name'])) {
  header("Location: " . url . "/admin-panel/admins/login.php");
  exit();
}

$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($admin_id <= 0) {
  header("Location: " . url . "/admin-panel/admins/admins.php");
  exit();
}

$res = mysqli_query($conn, "SELECT * FROM admins WHERE id = {$admin_id} LIMIT 1") or die("Query Unsuccessful");
if (mysqli_num_rows($res) === 0) {
  header("Location: " . url . "/admin-panel/admins/admins.php");
  exit();
}
$admin = mysqli_fetch_assoc($res);

if (isset($_POST['submit'])) {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $password = (string)($_POST['password'] ?? '');

  if ($name === '' || $email === '') {
    echo "<script>alert('Name and email are required');</script>";
  } else {
    $nameEsc = mysqli_real_escape_string($conn, $name);
    $emailEsc = mysqli_real_escape_string($conn, $email);
    $setPass = '';
    if ($password !== '') {
      $passEsc = mysqli_real_escape_string($conn, $password);
      $setPass = ", password = '{$passEsc}'";
    }

    mysqli_query($conn, "UPDATE admins SET admin_name = '{$nameEsc}', email = '{$emailEsc}'{$setPass} WHERE id = {$admin_id}") or die("Query Unsuccessful");

    // if editing yourself, update session name/email
    if (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $admin_id) {
      $_SESSION['admin_name'] = $name;
      $_SESSION['email'] = $email;
    }

    echo "<script>alert('Admin updated'); window.location.href='" . url . "/admin-panel/admins/admins.php';</script>";
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
            <h5 class="card-title mb-0">Edit Admin</h5>
            <a class="btn btn-light" href="admins.php">Back</a>
          </div>
          <form method="POST" action="edit-admin.php?id=<?php echo $admin_id; ?>">
            <div class="form-outline mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($admin['admin_name']); ?>" />
            </div>
            <div class="form-outline mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" />
            </div>
            <div class="form-outline mb-3">
              <label class="form-label">Password (leave blank to keep)</label>
              <input type="password" name="password" class="form-control" />
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

