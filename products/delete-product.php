<?php require_once "../includes/header.php"; ?>

<?php

// allow delete for either logged-in user OR QR guest session
$isUser = isset($_SESSION['user_id']);
$isQr = isset($_SESSION['qr_session_token']);
if (!$isUser && !$isQr) {
    header("Location: " . url . "/index.php");
    exit();
}

if (isset($_GET['id'])) {

    $product_id = $_GET['id'];
    $product_id = (int)$product_id;

    if ($isUser) {
        $user_id = (int)$_SESSION['user_id'];
        $query = "DELETE FROM cart WHERE product_id = {$product_id} AND user_id = {$user_id}";
    } else {
        $tok = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
        $query = "DELETE FROM cart WHERE product_id = {$product_id} AND session_token = '{$tok}'";
    }
    mysqli_query($conn, $query) or die("Query Unsuccessful");

    echo "<script>alert('item removed')</script>";

    echo "<script>window.location.href = 'cart.php'</script>";
}

?>