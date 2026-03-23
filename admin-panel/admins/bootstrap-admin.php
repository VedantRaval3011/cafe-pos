<?php
// Local-only admin bootstrap utility.
// Use once, then delete this file.

session_start();
require_once __DIR__ . '/../../config/config.php';

// Allow only local access
$host = $_SERVER['HTTP_HOST'] ?? '';
$remote = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocal = ($host === 'localhost' || str_starts_with($host, 'localhost:') || str_starts_with($host, '127.0.0.1')) &&
           ($remote === '127.0.0.1' || $remote === '::1');

if (!$isLocal) {
  http_response_code(403);
  echo "Forbidden";
  exit();
}

// Defaults (you can change after login)
$defaultName = "Admin";
$defaultEmail = "admin@admin.com";
$defaultPassword = "admin@admin";

if (isset($_POST['create'])) {
  $name = trim($_POST['name'] ?? $defaultName);
  $email = trim($_POST['email'] ?? $defaultEmail);
  $password = (string)($_POST['password'] ?? $defaultPassword);

  if ($name === '' || $email === '' || $password === '') {
    $msg = "All fields are required.";
  } else {
    $nameEsc = mysqli_real_escape_string($conn, $name);
    $emailEsc = mysqli_real_escape_string($conn, $email);
    $passEsc = mysqli_real_escape_string($conn, $password); // login uses plain text comparison

    $exists = mysqli_query($conn, "SELECT id FROM admins WHERE email='{$emailEsc}' LIMIT 1") or die("Query Unsuccessful");
    if (mysqli_num_rows($exists) > 0) {
      $msg = "Admin already exists for this email. Try logging in.";
    } else {
      mysqli_query($conn, "INSERT INTO admins (admin_name, email, password) VALUES ('{$nameEsc}','{$emailEsc}','{$passEsc}')") or die("Query Unsuccessful");
      $msg = "Admin created successfully. Now login with these credentials.";
      $created = true;
      $createdEmail = $email;
      $createdPassword = $password;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Bootstrap Admin (Local)</title>
  <style>
    body { font-family: Arial, sans-serif; padding: 24px; background:#0b0b0b; color:#fff; }
    .card { max-width: 520px; margin: 0 auto; background: rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 18px; }
    input { width: 100%; padding: 12px; border-radius: 10px; border:1px solid rgba(255,255,255,0.18); background: rgba(0,0,0,0.35); color:#fff; margin-top: 6px; }
    label { display:block; margin-top: 12px; font-weight: 700; }
    button { margin-top: 14px; padding: 12px 14px; border-radius: 10px; border:0; font-weight: 800; background:#c49b63; color:#111; cursor:pointer; }
    .msg { margin-top: 12px; padding: 10px 12px; border-radius: 10px; background: rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.12); }
    code { color:#c49b63; }
  </style>
</head>
<body>
  <div class="card">
    <h2 style="margin:0 0 8px;">Create Admin (Local Only)</h2>
    <div style="opacity:0.8;margin-bottom:10px;">After creating, login at <code><?php echo htmlspecialchars(url . "/admin-panel/admins/login.php"); ?></code>. Then delete this file.</div>

    <form method="post">
      <label>Name</label>
      <input name="name" value="<?php echo htmlspecialchars($defaultName); ?>" />

      <label>Email</label>
      <input name="email" value="<?php echo htmlspecialchars($defaultEmail); ?>" />

      <label>Password</label>
      <input name="password" value="<?php echo htmlspecialchars($defaultPassword); ?>" />

      <button type="submit" name="create">Create Admin</button>
    </form>

    <?php if (!empty($msg)) { ?>
      <div class="msg"><?php echo htmlspecialchars($msg); ?></div>
    <?php } ?>

    <?php if (!empty($created)) { ?>
      <div class="msg">
        <div><strong>Email:</strong> <code><?php echo htmlspecialchars($createdEmail); ?></code></div>
        <div><strong>Password:</strong> <code><?php echo htmlspecialchars($createdPassword); ?></code></div>
        <div style="margin-top:8px;">Now open <code><?php echo htmlspecialchars(url . "/admin-panel/admins/login.php"); ?></code></div>
      </div>
    <?php } ?>
  </div>
</body>
</html>

