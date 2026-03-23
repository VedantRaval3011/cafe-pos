<?php require_once "../includes/header.php"; ?>

<?php

// clear cart for either logged-in user OR QR guest session
$isUser = isset($_SESSION['user_id']);
$isQr = isset($_SESSION['qr_session_token']);
if (!$isUser && !$isQr) {
    header("Location: " . url . "/index.php");
    exit();
}

if ($isUser) {
    $user_id = (int)$_SESSION['user_id'];
    mysqli_query($conn, "DELETE FROM cart WHERE user_id = {$user_id}") or die("Query Unsuccessful");
} else {
    $tok = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
    mysqli_query($conn, "DELETE FROM cart WHERE session_token = '{$tok}'") or die("Query Unsuccessful");
}

echo "<script>
          alert('Cart cleared');
          window.location.href = 'cart.php';
        </script>";

?>