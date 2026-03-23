<?php
/**
 * Per–line-item kitchen status on order_items + aggregate orders.status.
 * Include after config.php (needs $conn only for sync functions).
 */

/** @return string[] */
function order_item_status_allowed(): array
{
    return ['placed', 'preparing', 'brewing', 'ready', 'delivered', 'cancelled'];
}

function order_item_status_priority(string $s): int
{
    $s = strtolower(trim($s));
    $map = [
        'created'   => 1,
        'placed'    => 1,
        'preparing' => 2,
        'brewing'   => 3,
        'ready'     => 4,
        'delivered' => 5,
        'cancelled' => 0,
    ];
    return $map[$s] ?? 1;
}

/**
 * Bottleneck rule: overall order status = least advanced among non-delivered lines.
 */
function aggregate_order_status(array $statuses): string
{
    if (empty($statuses)) {
        return 'placed';
    }
    $statuses = array_map('strtolower', array_map('trim', $statuses));
    $nonCancelled = array_values(array_filter($statuses, fn ($s) => $s !== 'cancelled'));
    if (empty($nonCancelled)) {
        return 'cancelled';
    }
    $nonDel = array_values(array_filter($nonCancelled, fn ($s) => $s !== 'delivered'));
    if (empty($nonDel)) {
        return 'delivered';
    }
    $minP = PHP_INT_MAX;
    foreach ($nonDel as $s) {
        $p = order_item_status_priority($s);
        if ($p > 0 && $p < $minP) {
            $minP = $p;
        }
    }
    if ($minP === PHP_INT_MAX) {
        return 'placed';
    }
    foreach (['placed', 'preparing', 'brewing', 'ready'] as $st) {
        if (order_item_status_priority($st) === $minP) {
            return $st;
        }
    }
    return 'placed';
}

function sync_order_status_from_items(mysqli $conn, int $orderId): void
{
    $orderId = (int)$orderId;
    $res = mysqli_query($conn, "SELECT item_status FROM order_items WHERE order_id = {$orderId}");
    if (!$res) {
        return;
    }
    $statuses = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $statuses[] = $row['item_status'] ?? 'placed';
    }
    mysqli_free_result($res);
    $agg = aggregate_order_status($statuses);
    $esc = mysqli_real_escape_string($conn, $agg);
    mysqli_query($conn, "UPDATE orders SET status = '{$esc}' WHERE id = {$orderId}");
}

function normalize_item_status(string $s): ?string
{
    $s = strtolower(trim($s));
    return in_array($s, order_item_status_allowed(), true) ? $s : null;
}
