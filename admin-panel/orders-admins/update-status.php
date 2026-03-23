<?php require_once "../layouts/header.php"; ?>
<?php
require_once __DIR__ . "/../../includes/order_item_status.php";

if (!isset($_SERVER['HTTP_REFERER'])) {
    echo "<script>window.location.href = '" . url . "/admin-panel'</script>";
}

if (!isset($_SESSION['admin_name'])) {
    header("Location: " . url . "/admin-panel");
    exit();
}

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id <= 0) {
    die("Order ID not specified.");
}

$allowed = order_item_status_allowed();
$statusLabels = [
    'placed'     => '🟡 Placed',
    'preparing'  => '🔵 Preparing',
    'brewing'    => '🟠 Cooking / Brewing',
    'ready'      => '🟢 Ready',
    'delivered'  => '✅ Delivered',
    'cancelled'  => '❌ Cancelled',
];

if (isset($_POST['submit'])) {
    $masterRaw = $_POST['order_status_master'] ?? '';
    $master = ($masterRaw !== '' && $masterRaw !== null) ? normalize_item_status((string)$masterRaw) : null;

    if ($master !== null) {
        $esc = mysqli_real_escape_string($conn, $master);
        mysqli_query($conn, "UPDATE order_items SET item_status = '{$esc}' WHERE order_id = {$order_id}");
    } else {
        $itemsRes = mysqli_query($conn, "SELECT id FROM order_items WHERE order_id = {$order_id}");
        $ids = [];
        if ($itemsRes) {
            while ($r = mysqli_fetch_assoc($itemsRes)) {
                $ids[] = (int)$r['id'];
            }
            mysqli_free_result($itemsRes);
        }

        foreach ($ids as $lineId) {
            $key = 'item_status_' . $lineId;
            if (!isset($_POST[$key])) {
                continue;
            }
            $raw = normalize_item_status((string)$_POST[$key]);
            if ($raw === null) {
                continue;
            }
            $esc = mysqli_real_escape_string($conn, $raw);
            mysqli_query($conn, "UPDATE order_items SET item_status = '{$esc}' WHERE id = {$lineId} AND order_id = {$order_id}");
        }
    }

    sync_order_status_from_items($conn, $order_id);

    echo "<script>alert('Order / line statuses updated!')</script>";
    echo "<script>window.location.href = '" . url . "/admin-panel/orders-admins/show-orders.php'</script>";
    exit();
}

$orderRes = mysqli_query($conn, "SELECT id, status, table_number, first_name, last_name FROM orders WHERE id = {$order_id} LIMIT 1");
if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
    die("Order not found.");
}
$orderRow = mysqli_fetch_assoc($orderRes);

$itemsRes = mysqli_query($conn, "
    SELECT oi.id, oi.name, oi.size, oi.quantity, oi.item_status, p.type
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = {$order_id}
    ORDER BY oi.id ASC
");
$lines = [];
if ($itemsRes) {
    while ($r = mysqli_fetch_assoc($itemsRes)) {
        $lines[] = $r;
    }
    mysqli_free_result($itemsRes);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Update order #<?php echo (int)$orderRow['id']; ?></h5>
                    <p class="text-muted mb-4">
                        Set <strong>each dish</strong> separately (e.g. dessert preparing while main dish is ready).
                        The order’s main status is kept in sync from the slowest line.
                        <?php if (!empty($orderRow['table_number'])) { ?>
                            <br><span class="badge badge-info">Table <?php echo (int)$orderRow['table_number']; ?></span>
                        <?php } ?>
                    </p>

                    <form method="POST" action="update-status.php?id=<?php echo (int)$order_id; ?>">
                        <div class="table-responsive mb-4">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Type</th>
                                        <th>Size / Qty</th>
                                        <th style="min-width:220px;">Kitchen status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lines as $line) {
                                        $lid = (int)$line['id'];
                                        $cur = strtolower(trim($line['item_status'] ?? 'placed'));
                                        if (!in_array($cur, $allowed, true)) {
                                            $cur = 'placed';
                                        }
                                        $type = htmlspecialchars($line['type'] ?? '—');
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($line['name']); ?></td>
                                            <td><span class="badge badge-secondary"><?php echo $type; ?></span></td>
                                            <td><?php echo htmlspecialchars($line['size']); ?> × <?php echo (int)$line['quantity']; ?></td>
                                            <td>
                                                <select name="item_status_<?php echo $lid; ?>" class="form-control form-control-sm">
                                                    <?php foreach ($statusLabels as $val => $label) {
                                                        if (!in_array($val, $allowed, true)) {
                                                            continue;
                                                        }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars($val); ?>" <?php echo $cur === $val ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($label); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($lines) === 0) { ?>
                            <div class="alert alert-warning">No line items for this order.</div>
                        <?php } ?>

                        <div class="card mb-4 border-secondary">
                            <div class="card-body">
                                <h6 class="card-title">Apply same status to all lines (optional)</h6>
                                <p class="small text-muted mb-2">Use this only if every dish should move together. Leave as “— No change —” to save only the per-line choices above.</p>
                                <select name="order_status_master" class="form-control" style="max-width:320px;">
                                    <option value="">— No change —</option>
                                    <?php foreach ($statusLabels as $val => $label) {
                                        if (!in_array($val, $allowed, true)) {
                                            continue;
                                        }
                                        ?>
                                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="submit" class="btn btn-primary">Save</button>
                        <a href="<?php echo url; ?>/admin-panel/orders-admins/show-orders.php" class="btn btn-outline-secondary ml-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
