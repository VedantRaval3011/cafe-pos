<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Table setup: accept ?table=X to create/switch QR-like session
$tableParam = isset($_GET['table']) ? (int)$_GET['table'] : 0;
if ($tableParam > 0) {
    $prevTable = $_SESSION['qr_table_number'] ?? null;
    if (!isset($_SESSION['qr_session_token']) || ($prevTable !== null && (int)$prevTable !== $tableParam)) {
        $_SESSION['qr_session_token'] = bin2hex(random_bytes(32));
    }
    $_SESSION['qr_table_number'] = $tableParam;
}

// Resolve identifiers
$userId = $_SESSION['user_id'] ?? null;
$sessionToken = $_SESSION['qr_session_token'] ?? $_SESSION['kitchen_guest_token'] ?? null;
$tableNumber = $_SESSION['qr_table_number'] ?? null;
if (!$userId && !$sessionToken) {
    $sessionToken = bin2hex(random_bytes(32));
    $_SESSION['kitchen_guest_token'] = $sessionToken;
}

// Load categories + products for KITCHEN_CONFIG
$catRes = mysqli_query($conn, "SELECT * FROM products ORDER BY type, name");
$grouped = [];
while ($row = mysqli_fetch_assoc($catRes)) {
    $type = $row['type'];
    if (!isset($grouped[$type])) $grouped[$type] = [];
    $grouped[$type][] = [
        'id'          => (int)$row['id'],
        'name'        => $row['name'],
        'image'       => $row['image'],
        'description' => $row['description'],
        'price'       => $row['price'],
    ];
}
$categories = [];
foreach ($grouped as $type => $products) {
    $categories[] = ['type' => $type, 'products' => $products];
}

// Order tracking mode
$order = null;
$orderItems = [];
$orderTypes = [];
$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$trackingMode = false;

if ($orderId > 0) {
    $res = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$orderId} LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $order = mysqli_fetch_assoc($res);
        $trackingMode = true;

        $itemsRes = mysqli_query($conn, "
            SELECT oi.name, oi.quantity, oi.price, oi.line_total, oi.item_status, p.type
            FROM order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = {$orderId}
        ");
        while ($it = mysqli_fetch_assoc($itemsRes)) {
            $orderItems[] = $it;
            $t = strtolower(trim($it['type'] ?? 'coffee'));
            if (!in_array($t, $orderTypes)) $orderTypes[] = $t;
        }
    }
}

$isQr = isset($_SESSION['qr_session_token']);
$invoiceUrl = $trackingMode
    ? ($isQr
        ? url . "/qr-order/invoice.php?order_id=" . $orderId
        : url . "/payments/invoice.php?order_id=" . $orderId)
    : '#';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php
        if ($trackingMode) echo "Order #{$orderId} — Live Kitchen";
        elseif ($tableNumber) echo "Table {$tableNumber} — Live Kitchen";
        else echo "Live Kitchen";
    ?> | Cafe Junction</title>
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        body { overflow: hidden; background: #0a0604; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #fff; }
        #canvas-container { width: 100vw; height: 100vh; position: fixed; inset: 0; }

        /* ── Loading Screen ── */
        #loading {
            position: fixed; inset: 0; z-index: 2000;
            background: #0a0604;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            transition: opacity 1s ease;
        }
        #loading.done { opacity: 0; pointer-events: none; }
        #loading .icon { font-size: 4rem; margin-bottom: 1.2rem; animation: pulse 1.5s ease infinite; }
        #loading h2 { color: #e8a87c; font-size: 1.4rem; font-weight: 500; letter-spacing: 0.5px; margin-bottom: 1.5rem; }
        #loading .bar { width: 220px; height: 3px; background: #2a1a0e; border-radius: 2px; overflow: hidden; }
        #loading .fill { height: 100%; background: linear-gradient(90deg, #e8a87c, #d4764e); width: 0%; transition: width 0.4s ease; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }

        /* ── Top Bar ── */
        #top-bar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            padding: 14px 20px;
            display: flex; justify-content: space-between; align-items: center;
            pointer-events: none;
            background: linear-gradient(to bottom, rgba(10,6,4,0.8) 0%, transparent 100%);
        }
        #top-bar > * { pointer-events: auto; }
        .back-btn {
            display: inline-flex; align-items: center; gap: 8px;
            color: #e8a87c; text-decoration: none; font-size: 0.85rem; font-weight: 500;
            padding: 7px 14px; border-radius: 8px;
            background: rgba(232,168,124,0.1); border: 1px solid rgba(232,168,124,0.2);
            backdrop-filter: blur(10px); transition: all 0.3s;
        }
        .back-btn:hover { background: rgba(232,168,124,0.2); color: #fff; }
        .back-btn svg { width: 16px; height: 16px; }
        .scene-title {
            font-size: 1rem; font-weight: 600; color: #e8a87c;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            display: flex; align-items: center; gap: 8px;
        }
        .scene-title span { font-size: 1.2rem; }

        /* ── Controls ── */
        #controls { display: flex; gap: 8px; align-items: center; }
        .ctrl-btn {
            padding: 7px 13px; border-radius: 8px; cursor: pointer;
            font-size: 0.78rem; font-weight: 500; border: 1px solid rgba(232,168,124,0.25);
            background: rgba(232,168,124,0.1); color: #e8a87c;
            backdrop-filter: blur(10px); transition: all 0.3s;
            text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
        }
        .ctrl-btn:hover { background: rgba(232,168,124,0.25); color: #fff; }
        .ctrl-btn.active { background: rgba(232,168,124,0.3); border-color: #e8a87c; }

        /* ── Order Progress Bar ── */
        #order-track {
            position: fixed; top: 56px; left: 50%; transform: translateX(-50%);
            z-index: 100; pointer-events: none;
            display: none; align-items: center; gap: 0;
            padding: 10px 22px; border-radius: 14px;
            background: rgba(10,6,4,0.85); border: 1px solid rgba(232,168,124,0.15);
            backdrop-filter: blur(12px);
        }
        #order-track.visible { display: flex; }
        .track-step {
            display: flex; flex-direction: column; align-items: center;
            width: 80px; position: relative; z-index: 2;
        }
        .track-dot {
            width: 32px; height: 32px; border-radius: 50%;
            background: rgba(255,255,255,0.06); border: 2.5px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; transition: all 0.5s ease;
        }
        .track-step.done .track-dot { background: #c49b63; border-color: #c49b63; color: #111; }
        .track-step.active .track-dot {
            background: rgba(196,155,99,0.25); border-color: #c49b63;
            box-shadow: 0 0 14px rgba(196,155,99,0.4);
            animation: dotPulse 2s ease infinite;
        }
        @keyframes dotPulse { 0%,100% { box-shadow: 0 0 14px rgba(196,155,99,0.4); } 50% { box-shadow: 0 0 22px rgba(196,155,99,0.6); } }
        .track-lbl {
            font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
            color: rgba(255,255,255,0.35); margin-top: 5px; transition: color 0.4s;
        }
        .track-step.done .track-lbl,
        .track-step.active .track-lbl { color: #fff; }
        .track-line {
            flex: 1; height: 3px; min-width: 24px;
            background: rgba(255,255,255,0.06); border-radius: 2px;
            position: relative; z-index: 1;
        }
        .track-line-fill {
            height: 100%; width: 0%; border-radius: 2px;
            background: #c49b63; transition: width 0.8s ease;
        }

        /* ── Order Items Overlay (above station bar; must stay clear of payment banner) ── */
        #order-items {
            position: fixed; bottom: 72px; left: 50%; transform: translateX(-50%);
            z-index: 100; pointer-events: none;
            display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;
            max-width: min(96vw, 920px);
            padding: 0 12px;
        }
        .order-chip {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 13px; border-radius: 20px;
            background: rgba(10,6,4,0.8); border: 1px solid rgba(232,168,124,0.15);
            backdrop-filter: blur(8px);
            font-size: 0.75rem; font-weight: 600; color: #ddd;
        }
        .order-chip .chip-dot { width: 7px; height: 7px; border-radius: 50%; }
        .order-chip .chip-status { color: #c49b63; font-weight: 700; opacity: 0.95; }

        /* ── Status Message — compact strip under order track (keeps 3D characters visible) ── */
        #status-msg {
            position: fixed;
            top: 128px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 118;
            pointer-events: none;
            text-align: center;
            opacity: 0;
            transition: opacity 0.4s ease, transform 0.35s ease;
            max-width: min(360px, 88vw);
            padding: 5px 14px 6px;
            border-radius: 999px;
            background: rgba(6, 4, 2, 0.72);
            border: 1px solid rgba(232, 168, 124, 0.28);
            backdrop-filter: blur(10px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }
        #status-msg.visible {
            opacity: 1;
            transform: translateX(-50%) translateY(0);
        }
        #status-msg:not(.visible) {
            transform: translateX(-50%) translateY(-6px);
        }
        .status-text {
            font-size: 0.72rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-shadow: 0 1px 6px rgba(0, 0, 0, 0.6);
            line-height: 1.2;
            display: block;
        }
        .status-sub {
            font-size: 0.62rem;
            color: rgba(255, 255, 255, 0.65);
            margin-top: 2px;
            font-weight: 500;
            line-height: 1.25;
            display: block;
            max-width: 42ch;
            margin-left: auto;
            margin-right: auto;
        }
        /* Accent by phase (set from JS) — subtle glow, small footprint */
        #status-msg.status-msg--placed { border-color: rgba(122, 179, 255, 0.45); box-shadow: 0 6px 20px rgba(0,0,0,0.35), 0 0 14px rgba(122,179,255,0.1); }
        #status-msg.status-msg--preparing { border-color: rgba(255, 180, 50, 0.45); box-shadow: 0 6px 20px rgba(0,0,0,0.35), 0 0 16px rgba(255,180,50,0.12); }
        #status-msg.status-msg--brewing { border-color: rgba(255, 119, 68, 0.45); box-shadow: 0 6px 20px rgba(0,0,0,0.35), 0 0 16px rgba(255,119,68,0.12); }
        #status-msg.status-msg--ready { border-color: rgba(80, 220, 100, 0.5); box-shadow: 0 6px 20px rgba(0,0,0,0.35), 0 0 18px rgba(80,220,100,0.14); }
        #status-msg.status-msg--delivered { border-color: rgba(120, 200, 255, 0.4); }

        /* ── Character Info Popup ── */
        #char-info {
            position: fixed; z-index: 150;
            padding: 16px 20px; border-radius: 12px;
            background: rgba(15,10,5,0.9); border: 1px solid rgba(232,168,124,0.3);
            backdrop-filter: blur(12px);
            pointer-events: none;
            opacity: 0; transform: translateY(10px);
            transition: opacity 0.3s ease, transform 0.3s ease;
            min-width: 200px; text-align: center;
        }
        #char-info.visible { opacity: 1; transform: translateY(0); pointer-events: auto; }
        .info-name { font-size: 1.1rem; font-weight: 700; color: #e8a87c; margin-bottom: 4px; }
        .info-msg { font-size: 0.9rem; color: #ccc; margin-bottom: 8px; }
        .info-badge {
            display: inline-block; padding: 3px 12px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;
        }
        .info-badge.idle { background: rgba(150,150,150,0.2); color: #999; }
        .info-badge.placed { background: rgba(100,150,255,0.2); color: #7ab3ff; }
        .info-badge.preparing { background: rgba(255,180,50,0.2); color: #ffb432; }
        .info-badge.cooking { background: rgba(255,100,50,0.2); color: #ff7744; }
        .info-badge.ready { background: rgba(80,220,100,0.2); color: #50dc64; }
        .info-view-menu {
            display: inline-block; margin-top: 8px; padding: 5px 16px; border-radius: 20px;
            background: rgba(232,168,124,0.2); border: 1px solid rgba(232,168,124,0.4);
            color: #e8a87c; font-size: 0.75rem; font-weight: 600; cursor: pointer;
            transition: all 0.3s; text-decoration: none;
        }
        .info-view-menu:hover { background: rgba(232,168,124,0.35); color: #fff; }

        /* ── Station Status Bar ── */
        #status-bar {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 100;
            padding: 10px 20px 12px;
            display: flex; justify-content: center; gap: 14px;
            flex-wrap: wrap;
            pointer-events: none;
            background: linear-gradient(to top, rgba(10,6,4,0.85) 0%, rgba(10,6,4,0.2) 55%, transparent 100%);
        }
        .station-pill {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 14px; border-radius: 10px;
            background: rgba(15,10,5,0.7); border: 1px solid rgba(232,168,124,0.15);
            backdrop-filter: blur(8px); pointer-events: auto; cursor: pointer;
            transition: all 0.3s;
        }
        .station-pill:hover { border-color: rgba(232,168,124,0.4); background: rgba(25,15,8,0.8); }
        .station-dot {
            width: 8px; height: 8px; border-radius: 50%;
            background: #555; transition: background 0.5s;
        }
        .station-dot.idle { background: #666; }
        .station-dot.placed { background: #7ab3ff; box-shadow: 0 0 6px #7ab3ff; }
        .station-dot.preparing { background: #ffb432; box-shadow: 0 0 6px #ffb432; }
        .station-dot.cooking { background: #ff7744; box-shadow: 0 0 6px #ff7744; animation: glow 1s ease infinite; }
        .station-dot.ready { background: #50dc64; box-shadow: 0 0 8px #50dc64; animation: glow 0.6s ease infinite; }
        @keyframes glow { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
        .station-label { font-size: 0.72rem; color: #aaa; font-weight: 500; }
        .station-status-text { font-size: 0.62rem; color: #777; text-transform: uppercase; letter-spacing: 0.5px; }

        /* ── 3D Confetti (canvas overlay) ── */
        #confetti-canvas { position: fixed; inset: 0; z-index: 200; pointer-events: none; }

        /* ── Instruction Banner (top-left: avoids overlapping the 3D CAFE JUNCTION sign in the upper centre) ── */
        #instruction-banner {
            position: fixed;
            top: 58px;
            left: 16px;
            right: auto;
            transform: none;
            z-index: 120; pointer-events: auto;
            display: flex; align-items: center; gap: 12px;
            padding: 12px 20px; border-radius: 12px;
            background: rgba(196,155,99,0.18); border: 1px solid rgba(232,168,124,0.3);
            backdrop-filter: blur(12px);
            animation: bannerSlide 0.5s ease;
            max-width: min(360px, calc(100vw - 32px));
        }
        #instruction-banner.hidden { display: none; }
        .ib-icon { font-size: 1.4rem; flex-shrink: 0; }
        .ib-text { font-size: 0.82rem; color: #e8d4be; line-height: 1.4; }
        .ib-dismiss {
            width: 24px; height: 24px; border-radius: 50%; border: none; cursor: pointer;
            background: rgba(255,255,255,0.08); color: #999; font-size: 1rem;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            transition: all 0.2s;
        }
        .ib-dismiss:hover { background: rgba(255,255,255,0.15); color: #fff; }
        @keyframes bannerSlide { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ── Payment Banner (top-right: avoids overlapping order chips + station row) ── */
        #payment-banner {
            position: fixed;
            top: 56px;
            right: 16px;
            left: auto;
            transform: none;
            z-index: 170;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: 14px;
            max-width: min(380px, calc(100vw - 32px));
            background: rgba(180,60,30,0.22); border: 1px solid rgba(255,100,60,0.4);
            backdrop-filter: blur(14px);
            box-shadow: 0 8px 28px rgba(0,0,0,0.45);
            animation: pbSlideIn 0.45s ease;
        }
        @keyframes pbSlideIn {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .pb-icon { font-size: 1.4rem; flex-shrink: 0; }
        .pb-text { flex: 1; min-width: 0; }
        .pb-title { font-size: 0.85rem; font-weight: 700; color: #ffb080; }
        .pb-sub { font-size: 0.68rem; color: rgba(255,255,255,0.55); margin-top: 1px; }
        .pb-amount { font-size: 1.1rem; font-weight: 800; color: #fff; flex-shrink: 0; }
        .pb-pay-btn {
            padding: 8px 20px; border-radius: 8px; border: none; cursor: pointer;
            background: linear-gradient(135deg, #c49b63, #e8a87c); color: #111;
            font-size: 0.82rem; font-weight: 700; flex-shrink: 0;
            transition: all 0.2s; white-space: nowrap;
        }
        .pb-pay-btn:hover { opacity: 0.9; }
        .pb-pay-btn:disabled { opacity: 0.4; cursor: default; }

        /* ── Rotating chef prompt — compact toast (bottom-left, stays off characters) ── */
        #chef-prompt {
            position: fixed;
            left: 10px;
            right: auto;
            bottom: 142px;
            transform: none;
            z-index: 127;
            display: none;
            flex-direction: column;
            width: min(220px, calc(100vw - 88px));
            padding: 7px 9px 8px;
            border-radius: 11px;
            pointer-events: auto;
            text-align: left;
            background: linear-gradient(145deg, rgba(22, 16, 11, 0.94), rgba(10, 7, 5, 0.96));
            border: 1px solid rgba(232, 168, 124, 0.3);
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.05) inset,
                0 6px 22px rgba(0, 0, 0, 0.45),
                0 0 18px rgba(196, 155, 99, 0.12);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }
        #chef-prompt.chef-prompt--visible {
            display: flex;
        }
        #chef-prompt.chef-prompt--pop {
            animation: cpPop 0.45s cubic-bezier(0.34, 1.4, 0.64, 1);
        }
        @keyframes cpPop {
            from { opacity: 0; transform: translateY(8px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        #chef-prompt::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(110deg, transparent 40%, rgba(255, 200, 120, 0.06) 50%, transparent 60%);
            background-size: 200% 100%;
            animation: cpShimmer 3.5s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes cpShimmer {
            0% { background-position: 100% 0; }
            100% { background-position: -100% 0; }
        }
        .cp-head {
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            z-index: 1;
            margin-bottom: 5px;
        }
        .cp-icon {
            width: 28px; height: 28px;
            border-radius: 8px;
            background: rgba(196, 155, 99, 0.2);
            border: 1px solid rgba(196, 155, 99, 0.35);
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem;
            flex-shrink: 0;
        }
        .cp-name { font-size: 0.72rem; font-weight: 800; color: #fff; letter-spacing: 0.02em; line-height: 1.15; }
        .cp-role { font-size: 0.55rem; color: #c49b63; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-top: 1px; }
        .cp-line {
            position: relative;
            z-index: 1;
            font-size: 0.68rem;
            font-weight: 700;
            color: #f0e6dc;
            line-height: 1.25;
            margin: 0 0 3px 0;
        }
        .cp-sub {
            position: relative;
            z-index: 1;
            font-size: 0.58rem;
            color: rgba(255, 255, 255, 0.58);
            line-height: 1.3;
            margin: 0 0 6px 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .cp-actions {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
            align-items: stretch;
        }
        .cp-cta {
            flex: 1;
            min-width: 0;
            padding: 5px 8px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 0.62rem;
            font-weight: 800;
            color: #1a1208;
            background: linear-gradient(135deg, #e8c99a, #c49b63);
            box-shadow: 0 2px 12px rgba(196, 155, 99, 0.3);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .cp-cta:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(196, 155, 99, 0.4); }
        .cp-skip {
            flex-shrink: 0;
            padding: 5px 7px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            background: rgba(255, 255, 255, 0.06);
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.58rem;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s;
        }
        .cp-skip:hover { background: rgba(255, 255, 255, 0.1); color: #fff; }

        /* ── Hint ── */
        #hint {
            position: fixed; bottom: 56px; left: 50%; transform: translateX(-50%);
            z-index: 100; font-size: 0.72rem; color: rgba(232,168,124,0.45);
            pointer-events: none; white-space: nowrap;
            animation: fadeHint 8s ease forwards;
        }
        @keyframes fadeHint { 0%,70% { opacity: 1; } 100% { opacity: 0; } }

        /* ── Vignette ── */
        #vignette {
            position: fixed; inset: 0; z-index: 50; pointer-events: none;
            background: radial-gradient(ellipse at center, transparent 50%, rgba(5,3,1,0.5) 100%);
        }

        /* ═══════════ ORDERING OVERLAYS ═══════════ */

        /* ── Cart FAB ── */
        #cart-fab {
            position: fixed; bottom: 60px; right: 24px; z-index: 160;
            width: 56px; height: 56px; border-radius: 50%;
            background: linear-gradient(135deg, #c49b63, #e8a87c); border: none;
            color: #111; font-size: 1.5rem; cursor: pointer;
            box-shadow: 0 4px 20px rgba(196,155,99,0.4);
            display: flex; align-items: center; justify-content: center;
            transition: all 0.3s;
        }
        #cart-fab:hover { transform: scale(1.08); box-shadow: 0 6px 28px rgba(196,155,99,0.55); }
        #cart-fab .badge {
            position: absolute; top: -4px; right: -4px;
            min-width: 20px; height: 20px; border-radius: 10px;
            background: #ff4444; color: #fff; font-size: 0.65rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
            padding: 0 5px; opacity: 0; transition: opacity 0.3s;
        }
        #cart-fab .badge.visible { opacity: 1; }

        /* ── Glass overlay base ── */
        .k3d-overlay {
            position: fixed; top: 0; right: -420px; z-index: 300;
            width: 400px; max-width: 90vw; height: 100vh;
            background: rgba(12,8,4,0.95); border-left: 1px solid rgba(232,168,124,0.15);
            backdrop-filter: blur(20px);
            transition: right 0.35s cubic-bezier(.22,.68,.36,1);
            display: flex; flex-direction: column;
            overflow: hidden;
        }
        .k3d-overlay.open { right: 0; }
        .k3d-overlay-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 18px 22px; border-bottom: 1px solid rgba(232,168,124,0.1);
        }
        .k3d-overlay-header h3 { font-size: 1.1rem; font-weight: 700; color: #e8a87c; }
        .k3d-close {
            width: 32px; height: 32px; border-radius: 50%; border: none; cursor: pointer;
            background: rgba(255,255,255,0.06); color: #999; font-size: 1.2rem;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .k3d-close:hover { background: rgba(255,255,255,0.12); color: #fff; }
        .k3d-overlay-body { flex: 1; overflow-y: auto; padding: 16px 22px; }
        .k3d-overlay-footer {
            padding: 16px 22px; border-top: 1px solid rgba(232,168,124,0.1);
        }

        /* ── Product Panel ── */
        #product-panel .product-card {
            display: flex; gap: 14px; padding: 14px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        #product-panel .product-card:last-child { border-bottom: none; }
        .product-img {
            width: 70px; height: 70px; border-radius: 10px;
            object-fit: cover; flex-shrink: 0;
            background: rgba(255,255,255,0.05);
        }
        .product-info { flex: 1; min-width: 0; }
        .product-info h4 {
            font-size: 0.9rem; font-weight: 600; color: #fff;
            margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .product-info .pdesc {
            font-size: 0.72rem; color: #888; margin-bottom: 6px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .product-info .pprice { font-size: 0.85rem; font-weight: 700; color: #c49b63; }
        .product-actions { display: flex; align-items: center; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
        .size-select {
            padding: 3px 8px; border-radius: 6px; font-size: 0.7rem;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            color: #ccc; cursor: pointer;
        }
        .qty-ctrl {
            display: flex; align-items: center; gap: 0;
            border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; overflow: hidden;
        }
        .qty-ctrl button {
            width: 26px; height: 26px; border: none; cursor: pointer;
            background: rgba(255,255,255,0.06); color: #ccc; font-size: 0.8rem;
            display: flex; align-items: center; justify-content: center;
        }
        .qty-ctrl button:hover { background: rgba(255,255,255,0.12); }
        .qty-ctrl .qty-val {
            width: 28px; text-align: center; font-size: 0.75rem; font-weight: 600; color: #fff;
            background: transparent;
        }
        .add-btn {
            padding: 5px 14px; border-radius: 6px; border: none; cursor: pointer;
            background: linear-gradient(135deg, #c49b63, #e8a87c); color: #111;
            font-size: 0.72rem; font-weight: 700; transition: all 0.2s;
        }
        .add-btn:hover { opacity: 0.85; }
        .add-btn:disabled { opacity: 0.4; cursor: default; }

        /* ── Cart Drawer ── */
        .cart-item {
            display: flex; gap: 12px; align-items: center;
            padding: 12px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-img {
            width: 50px; height: 50px; border-radius: 8px; object-fit: cover;
            background: rgba(255,255,255,0.05); flex-shrink: 0;
        }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-info h4 {
            font-size: 0.82rem; font-weight: 600; color: #fff; margin-bottom: 2px;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .cart-item-info .cart-meta { font-size: 0.68rem; color: #888; }
        .cart-item-right { text-align: right; flex-shrink: 0; }
        .cart-item-price { font-size: 0.85rem; font-weight: 700; color: #c49b63; margin-bottom: 4px; }
        .cart-remove {
            font-size: 0.65rem; color: #ff6666; cursor: pointer; background: none; border: none;
            padding: 2px 6px; border-radius: 4px; transition: all 0.2s;
        }
        .cart-remove:hover { background: rgba(255,100,100,0.1); }
        .cart-total { font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 12px; }
        .cart-total span { color: #c49b63; }
        .cart-empty { text-align: center; padding: 40px 0; color: #666; font-size: 0.9rem; }

        /* ── Primary button ── */
        .k3d-btn-primary {
            width: 100%; padding: 12px; border: none; border-radius: 10px; cursor: pointer;
            background: linear-gradient(135deg, #c49b63, #e8a87c); color: #111;
            font-size: 0.9rem; font-weight: 700; transition: all 0.2s;
        }
        .k3d-btn-primary:hover { opacity: 0.9; }
        .k3d-btn-primary:disabled { opacity: 0.4; cursor: default; }

        /* ── Checkout Modal ── */
        #checkout-overlay {
            position: fixed; inset: 0; z-index: 400;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(8px);
            display: none; align-items: center; justify-content: center;
        }
        #checkout-overlay.open { display: flex; }
        .checkout-box {
            width: 440px; max-width: 92vw; max-height: 90vh; overflow-y: auto;
            background: rgba(18,12,6,0.97); border: 1px solid rgba(232,168,124,0.2);
            border-radius: 16px; padding: 28px;
        }
        .checkout-box h3 { font-size: 1.2rem; font-weight: 700; color: #e8a87c; margin-bottom: 20px; }
        .checkout-box label { display: block; font-size: 0.75rem; font-weight: 600; color: #aaa; margin-bottom: 4px; margin-top: 12px; }
        .checkout-box input {
            width: 100%; padding: 10px 12px; border-radius: 8px;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            color: #fff; font-size: 0.85rem; outline: none; transition: border-color 0.2s;
        }
        .checkout-box input:focus { border-color: #c49b63; }
        .checkout-summary {
            margin-top: 18px; padding: 14px; border-radius: 10px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06);
        }
        .checkout-summary .cs-row {
            display: flex; justify-content: space-between; font-size: 0.78rem; color: #aaa;
            padding: 4px 0;
        }
        .checkout-summary .cs-total {
            display: flex; justify-content: space-between; font-size: 1rem; font-weight: 700;
            color: #fff; padding-top: 8px; margin-top: 8px; border-top: 1px solid rgba(255,255,255,0.08);
        }
        .checkout-summary .cs-total span { color: #c49b63; }
        .checkout-actions { display: flex; gap: 10px; margin-top: 20px; }
        .checkout-actions .k3d-btn-secondary {
            flex: 1; padding: 12px; border-radius: 10px; cursor: pointer;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            color: #aaa; font-size: 0.85rem; font-weight: 600; transition: all 0.2s;
        }
        .checkout-actions .k3d-btn-secondary:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .checkout-actions .k3d-btn-primary { flex: 2; }
        .checkout-error { color: #ff6666; font-size: 0.78rem; margin-top: 8px; display: none; }

        /* ── Scrim (for closing panels on click outside) ── */
        #panel-scrim {
            position: fixed; inset: 0; z-index: 290; background: rgba(0,0,0,0.3);
            display: none; cursor: pointer;
        }
        #panel-scrim.visible { display: block; }

        /* ── Phone / narrow screens: vertical rhythm, no overlap ── */
        @media (max-width: 640px) {
            #top-bar {
                flex-direction: column;
                align-items: stretch;
                gap: 6px;
                padding: 8px 10px 10px;
                padding-top: max(8px, env(safe-area-inset-top));
                background: linear-gradient(to bottom, rgba(10,6,4,0.94) 0%, rgba(10,6,4,0.82) 85%, transparent 100%);
            }
            #top-bar > div:first-child {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 0;
                width: 100%;
            }
            .back-btn {
                padding: 6px 10px;
                font-size: 0.72rem;
                flex-shrink: 0;
            }
            .scene-title {
                font-size: 0.74rem;
                min-width: 0;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            #controls {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 6px;
                width: 100%;
                align-items: stretch;
            }
            .ctrl-btn {
                font-size: 0.58rem;
                padding: 8px 4px;
                justify-content: center;
                text-align: center;
                line-height: 1.15;
                min-height: 36px;
                white-space: normal;
                word-break: break-word;
            }

            /* Progress stepper: below stacked header, scroll if needed */
            #order-track {
                top: 118px;
                left: 8px;
                right: 8px;
                transform: none;
                max-width: none;
                width: auto;
                z-index: 102;
                padding: 6px 8px;
                pointer-events: auto;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                justify-content: flex-start;
                touch-action: pan-x;
            }
            .track-step { width: 52px; flex-shrink: 0; }
            .track-dot { width: 26px; height: 26px; font-size: 11px; }
            .track-lbl { font-size: 7px; margin-top: 3px; }
            .track-line { min-width: 10px; flex-shrink: 0; }

            /* Status pill sits under the track (track ~64px tall) */
            #status-msg {
                top: 188px;
                left: 8px;
                right: 8px;
                transform: none;
                max-width: none;
                width: auto;
                padding: 4px 10px 5px;
            }
            #status-msg.visible {
                transform: none;
            }
            #status-msg:not(.visible) {
                transform: translateY(-4px);
            }
            .status-text { font-size: 0.62rem; }
            .status-sub { font-size: 0.52rem; margin-top: 2px; }

            /* Order chips: clear of station strip + cart */
            #order-items {
                bottom: 96px;
                max-width: 100vw;
                padding: 0 8px;
                gap: 6px;
            }
            .order-chip { font-size: 0.62rem; padding: 4px 9px; }

            /* Station row: single horizontal scroll — no wrap, no overlap with cart */
            #status-bar {
                flex-wrap: nowrap;
                justify-content: flex-start;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                gap: 6px;
                padding: 8px 56px calc(10px + env(safe-area-inset-bottom)) 10px;
                pointer-events: auto;
                touch-action: pan-x;
            }
            .station-pill {
                flex: 0 0 auto;
                padding: 5px 9px;
                gap: 5px;
                border-radius: 8px;
            }
            .station-label { font-size: 0.6rem; }
            .station-status-text { font-size: 0.5rem; }
            .station-dot { width: 6px; height: 6px; }

            /* Cart sits above dock, inside padding reserve on status-bar */
            #cart-fab {
                bottom: calc(58px + env(safe-area-inset-bottom));
                right: 8px;
                width: 46px;
                height: 46px;
                font-size: 1.2rem;
                z-index: 161;
            }

            #chef-prompt {
                left: 8px;
                bottom: calc(150px + env(safe-area-inset-bottom));
                width: min(200px, calc(100vw - 70px));
                padding: 6px 8px 7px;
            }
            .cp-line { font-size: 0.64rem; }
            .cp-sub { font-size: 0.55rem; -webkit-line-clamp: 2; }

            .k3d-overlay { width: 100vw; max-width: 100vw; }

            /* Payment: compact bottom-right — above order chips + station row; not over #order-items */
            /* Order chips sit ~bottom:96px and grow upward (often 2+ rows); cart FAB ~104px tall from bottom */
            #payment-banner {
                top: auto;
                left: auto;
                right: 6px;
                /* Clear chip band (96px + multi-row height) + gap; sits above chef toast on left */
                bottom: calc(160px + env(safe-area-inset-bottom));
                transform: none;
                max-width: min(156px, calc(100vw - 96px));
                width: min(156px, calc(100vw - 96px));
                flex-wrap: wrap;
                align-items: flex-start;
                align-content: flex-start;
                row-gap: 4px;
                column-gap: 5px;
                padding: 6px 8px 7px;
                border-radius: 10px;
                box-sizing: border-box;
            }
            #payment-banner .pb-icon { font-size: 0.95rem; line-height: 1; }
            #payment-banner .pb-text { flex: 1 1 65%; min-width: 0; }
            #payment-banner .pb-title { font-size: 0.64rem; line-height: 1.2; }
            #payment-banner .pb-sub {
                display: none;
            }
            #payment-banner .pb-amount { font-size: 0.78rem; }
            #payment-banner .pb-pay-btn {
                flex: 1 1 100%;
                width: 100%;
                min-height: 32px;
                font-size: 0.65rem;
                padding: 6px 8px;
                border-radius: 7px;
            }

            #hint { display: none; }

            /* Hint sits above station row + chips so it never covers the 3D title or stacked header */
            #instruction-banner {
                top: auto;
                left: 8px;
                right: 8px;
                bottom: calc(148px + env(safe-area-inset-bottom));
                max-width: none;
            }
        }

        /* Short screens: keep payment above cart/chips without hitting the header */
        @media (max-width: 640px) and (max-height: 560px) {
            #payment-banner {
                bottom: calc(50px + env(safe-area-inset-bottom));
            }
        }

        @media (max-width: 380px) {
            #order-track { top: 122px; }
            #status-msg { top: 194px; }
            .ctrl-btn { font-size: 0.54rem; padding: 7px 2px; min-height: 34px; }
        }

        /* ════════════════════════════════════════════════════
           MINI-GAME OVERLAY
           ════════════════════════════════════════════════════ */
        #minigame-overlay {
            position: fixed; inset: 0; z-index: 600;
            background: rgba(0,0,0,0.82); backdrop-filter: blur(10px);
            display: none; align-items: center; justify-content: center;
            padding: 16px;
        }
        #minigame-box {
            background: rgba(18,12,6,0.98); border: 1px solid rgba(232,168,124,0.28);
            border-radius: 18px; overflow: hidden;
            box-shadow: 0 24px 70px rgba(0,0,0,0.75), 0 0 0 1px rgba(255,255,255,0.04) inset;
            display: flex; flex-direction: column;
            width: min(640px, 96vw);
            height: min(620px, 92vh);
            animation: mgSlide 0.3s cubic-bezier(0.34,1.3,0.64,1);
        }
        @keyframes mgSlide {
            from { transform: scale(0.88) translateY(20px); opacity: 0; }
            to   { transform: scale(1)    translateY(0);    opacity: 1; }
        }
        #minigame-header {
            display: flex; align-items: center; gap: 10px;
            padding: 13px 18px; border-bottom: 1px solid rgba(232,168,124,0.1);
            background: rgba(196,155,99,0.07);
        }
        #mg-npc-icon { font-size: 1.5rem; line-height: 1; }
        #mg-title { flex: 1; font-size: 0.95rem; font-weight: 800; color: #e8a87c; letter-spacing: 0.02em; }
        #mg-close {
            width: 30px; height: 30px; border-radius: 50%; border: none; cursor: pointer;
            background: rgba(255,255,255,0.07); color: #888; font-size: 1.2rem;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        #mg-close:hover { background: rgba(255,100,100,0.2); color: #ff8888; }
        #mg-canvas {
            display: block;
            width: 100%;
            flex: 1;
            min-height: 0;
            background: #1a0e05;
        }
        #mg-iframe {
            display: none; border: none;
            width: 100%;
            flex: 1;          /* fills all remaining height inside #minigame-box */
            min-height: 0;    /* required for flex children to shrink/grow correctly */
            background: #1a0a00;
        }
        #minigame-footer {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 18px; border-top: 1px solid rgba(232,168,124,0.1);
            background: rgba(0,0,0,0.25);
        }
        #mg-score { font-size: 1rem; font-weight: 800; color: #c49b63; flex: 1; }
        #mg-timer {
            font-size: 0.82rem; font-weight: 700; color: #aaa;
            min-width: 34px; text-align: center;
            background: rgba(255,255,255,0.06); border-radius: 6px; padding: 3px 7px;
        }
        #mg-replay {
            padding: 7px 18px; border-radius: 8px; border: none; cursor: pointer;
            background: linear-gradient(135deg, #c49b63, #e8a87c); color: #111;
            font-size: 0.8rem; font-weight: 800; letter-spacing: 0.02em; transition: all 0.2s;
        }
        #mg-replay:hover { opacity: 0.85; transform: translateY(-1px); }
        @media (max-width: 480px) {
            #mg-canvas { min-height: 220px; }
        }

        /* ════════════════════════════════════════════════════
           INTERACTION PROGRESS BAR (click feedback)
           ════════════════════════════════════════════════════ */
        #interaction-progress {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 180;
            display: none; flex-direction: column; align-items: center; gap: 9px;
            padding: 13px 22px; border-radius: 14px;
            background: rgba(10,6,4,0.9); border: 1px solid rgba(232,168,124,0.35);
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.5), 0 0 20px rgba(196,155,99,0.1);
            pointer-events: none; min-width: 190px;
        }
        .ip-label { font-size: 0.88rem; font-weight: 800; color: #e8a87c; letter-spacing: 0.03em; }
        .ip-bar {
            width: 160px; height: 7px;
            background: rgba(255,255,255,0.08); border-radius: 4px; overflow: hidden;
        }
        .ip-fill {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #c49b63, #ffcc80, #e8a87c);
            border-radius: 4px; transition: width 0.08s linear;
            box-shadow: 0 0 8px rgba(232,168,124,0.6);
        }

        /* ════════════════════════════════════════════════════
           NPC SPEECH BUBBLES (HTML overlay, tracks 3D pos)
           ════════════════════════════════════════════════════ */
        .npc-bubble {
            position: fixed;
            pointer-events: none;
            z-index: 210;
            transform: translate(-50%, -100%) translateY(-14px);
            background: rgba(255,248,220,0.97);
            border: 2px solid rgba(196,155,80,0.75);
            border-radius: 12px;
            padding: 8px 13px 9px;
            min-width: 130px; max-width: 200px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0,0,0,0.45), 0 0 0 1px rgba(255,255,255,0.3) inset;
            font-family: 'Segoe UI', sans-serif;
            line-height: 1.35;
        }
        /* Tail pointing down toward character */
        .npc-bubble::after {
            content: '';
            position: absolute;
            bottom: -10px; left: 50%;
            transform: translateX(-50%);
            border: 9px solid transparent;
            border-top-color: rgba(255,248,220,0.97);
            border-bottom: 0;
        }
        .npc-bubble::before {
            content: '';
            position: absolute;
            bottom: -13px; left: 50%;
            transform: translateX(-50%);
            border: 10px solid transparent;
            border-top-color: rgba(196,155,80,0.75);
            border-bottom: 0;
            z-index: -1;
        }
        .npc-bub-header {
            font-size: 0.72rem; font-weight: 800;
            color: #c49b63; letter-spacing: 0.04em;
            text-transform: uppercase; margin-bottom: 4px;
        }
        .npc-bub-text {
            font-size: 0.78rem; font-weight: 600;
            color: #2a1a08; margin-bottom: 3px;
        }
        .npc-bub-cta {
            font-size: 0.72rem; font-weight: 700;
            color: #b8860b;
            background: rgba(196,155,80,0.15);
            border-radius: 6px; padding: 2px 6px;
            display: inline-block; margin-top: 2px;
        }

        /* ════════════════════════════════════════════════════
           KITCHEN SHOUT BUBBLES (random events)
           ════════════════════════════════════════════════════ */
        .kitchen-shout {
            position: fixed; z-index: 450; pointer-events: none;
            padding: 6px 14px; border-radius: 20px;
            background: rgba(255,230,120,0.96); color: #1a1208;
            font-size: 0.78rem; font-weight: 900; letter-spacing: 0.02em;
            border: 2px solid rgba(196,155,80,0.8);
            box-shadow: 0 4px 16px rgba(0,0,0,0.55), 0 0 12px rgba(255,220,80,0.3);
            white-space: nowrap;
        }
        .kitchen-shout::after {
            content: '';
            position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            border: 8px solid transparent;
            border-top-color: rgba(255,230,120,0.96);
            border-bottom: 0; filter: drop-shadow(0 2px 3px rgba(0,0,0,0.3));
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div id="loading">
        <div class="icon">☕</div>
        <h2><?php echo $trackingMode ? "Entering the Kitchen for Order #{$orderId}..." : "Entering the Kitchen..."; ?></h2>
        <div class="bar"><div class="fill" id="load-fill"></div></div>
    </div>

    <!-- 3D Canvas -->
    <div id="canvas-container"></div>

    <!-- Vignette -->
    <div id="vignette"></div>

    <!-- Confetti Canvas -->
    <canvas id="confetti-canvas"></canvas>

    <!-- Top Bar -->
    <div id="top-bar">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="<?php echo url; ?>" class="back-btn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back
            </a>
            <?php if ($trackingMode): ?>
                <div class="scene-title"><span>🔥</span> Order #<?php echo $orderId; ?><?php if ($tableNumber) echo " · Table {$tableNumber}"; ?></div>
            <?php elseif ($tableNumber): ?>
                <div class="scene-title"><span>🔥</span> Table <?php echo $tableNumber; ?> Kitchen</div>
            <?php else: ?>
                <div class="scene-title"><span>🔥</span> Live Kitchen</div>
            <?php endif; ?>
        </div>
        <div id="controls">
            <?php if ($trackingMode): ?>
                <a href="<?php echo $invoiceUrl; ?>" class="ctrl-btn">📄 Invoice</a>
                <button type="button" class="ctrl-btn" id="btn-order-more" style="background:rgba(196,155,99,0.2);border-color:#c49b63;" title="Optional: hide the progress bar — you can already add items with the cart or chefs">Simple view</button>
            <?php else: ?>
                <button class="ctrl-btn" id="btn-demo" title="Toggle demo animation cycle">Demo Mode</button>
            <?php endif; ?>
            <button class="ctrl-btn" id="btn-rotate" title="Toggle auto-rotation">Auto Rotate</button>
        </div>
    </div>

    <!-- Order Progress Bar -->
    <div id="order-track" class="<?php echo $trackingMode ? 'visible' : ''; ?>">
        <div class="track-step" data-step="placed">
            <div class="track-dot">🍳</div>
            <div class="track-lbl">Placed</div>
        </div>
        <div class="track-line"><div class="track-line-fill" id="fill-1"></div></div>
        <div class="track-step" data-step="preparing">
            <div class="track-dot">👨‍🍳</div>
            <div class="track-lbl">Preparing</div>
        </div>
        <div class="track-line"><div class="track-line-fill" id="fill-2"></div></div>
        <div class="track-step" data-step="brewing">
            <div class="track-dot">🔥</div>
            <div class="track-lbl">Cooking</div>
        </div>
        <div class="track-line"><div class="track-line-fill" id="fill-3"></div></div>
        <div class="track-step" data-step="ready">
            <div class="track-dot">✅</div>
            <div class="track-lbl">Ready</div>
        </div>
    </div>

    <!-- Status Message -->
    <div id="status-msg">
        <div class="status-text" id="statusText">Preparing</div>
        <div class="status-sub" id="statusSub">Items in progress</div>
    </div>

    <!-- Order Items -->
    <div id="order-items"></div>

    <!-- Rotating chef “buy from me” (tracking mode; JS fills & shows) -->
    <div id="chef-prompt" aria-live="polite">
        <div class="cp-head">
            <div class="cp-icon" id="cp-icon">☕</div>
            <div>
                <div class="cp-name" id="cp-name">Chef</div>
                <div class="cp-role" id="cp-role">Station</div>
            </div>
        </div>
        <p class="cp-line" id="cp-line">Order from my station</p>
        <p class="cp-sub" id="cp-sub">Tap to open menu.</p>
        <div class="cp-actions">
            <button type="button" class="cp-cta" id="cp-cta">Order ✨</button>
            <button type="button" class="cp-skip" id="cp-skip" title="Show next chef">Next →</button>
        </div>
    </div>

    <!-- Character Info Popup -->
    <div id="char-info">
        <div class="info-name"></div>
        <div class="info-msg"></div>
        <div class="info-badge"></div>
        <button class="info-view-menu" id="info-view-menu-btn" style="display:none;">View Menu</button>
    </div>

    <!-- Station Status Bar (dynamically filled by JS) -->
    <div id="status-bar"></div>

    <!-- Cart FAB (always available — add more while order is preparing) -->
    <button type="button" id="cart-fab" style="display:flex;">
        🛒
        <span class="badge" id="cart-badge">0</span>
    </button>

    <!-- Scrim -->
    <div id="panel-scrim"></div>

    <!-- Product Panel (slide-in from right) -->
    <div class="k3d-overlay" id="product-panel">
        <div class="k3d-overlay-header">
            <h3 id="pp-title">Menu</h3>
            <button class="k3d-close" id="pp-close">&times;</button>
        </div>
        <div class="k3d-overlay-body" id="pp-body"></div>
    </div>

    <!-- Cart Drawer (slide-in from right) -->
    <div class="k3d-overlay" id="cart-drawer">
        <div class="k3d-overlay-header">
            <h3>Your Cart</h3>
            <button class="k3d-close" id="cd-close">&times;</button>
        </div>
        <div class="k3d-overlay-body" id="cd-body"></div>
        <div class="k3d-overlay-footer" id="cd-footer"></div>
    </div>

    <!-- Checkout Modal -->
    <div id="checkout-overlay">
        <div class="checkout-box">
            <h3>Checkout</h3>
            <label for="ck-fname">First Name *</label>
            <input id="ck-fname" type="text" placeholder="Your first name" autocomplete="given-name">
            <label for="ck-lname">Last Name</label>
            <input id="ck-lname" type="text" placeholder="Your last name" autocomplete="family-name">
            <label for="ck-phone">Phone *</label>
            <input id="ck-phone" type="tel" placeholder="Phone number" autocomplete="tel">
            <label for="ck-email">Email *</label>
            <input id="ck-email" type="email" placeholder="Email address" autocomplete="email">
            <div class="checkout-summary" id="ck-summary"></div>
            <div class="checkout-error" id="ck-error"></div>
            <div class="checkout-actions">
                <button class="k3d-btn-secondary" id="ck-cancel">Back</button>
                <button class="k3d-btn-primary" id="ck-pay">Place Order</button>
            </div>
        </div>
    </div>

    <!-- Instruction Banner -->
    <div id="instruction-banner">
        <div class="ib-icon">👆</div>
        <div class="ib-text"><?php if ($tableNumber): ?>Table <?php echo $tableNumber; ?> — Click on a station or chef to browse the menu and place your order<?php else: ?>Click on a station or chef to browse the menu and add items to your cart<?php endif; ?></div>
        <button class="ib-dismiss" id="ib-dismiss">&times;</button>
    </div>

    <!-- Payment Banner (shown when order is unpaid) -->
    <div id="payment-banner" style="display:none;">
        <div class="pb-icon">💳</div>
        <div class="pb-text">
            <div class="pb-title">Payment Pending</div>
            <div class="pb-sub">Your order is being prepared. Please pay before leaving.</div>
        </div>
        <div class="pb-amount" id="pb-amount">₹0</div>
        <button class="pb-pay-btn" id="pb-pay-btn">Pay Now</button>
    </div>

    <!-- Hint -->
    <div id="hint">🖱️ Drag to orbit &bull; Scroll to zoom</div>

    <!-- Pass order data to JS -->
    <?php if ($trackingMode): ?>
    <script>
    window.KITCHEN_ORDER = {
        orderId: <?php echo $orderId; ?>,
        status: "<?php echo htmlspecialchars($order['status']); ?>",
        paymentStatus: "<?php echo htmlspecialchars($order['payment_status'] ?? 'unpaid'); ?>",
        totalPrice: <?php echo (float)$order['total_price']; ?>,
        items: <?php echo json_encode(array_map(function($it) {
            return [
                'name' => $it['name'],
                'quantity' => $it['quantity'],
                'type' => $it['type'] ?? 'coffee',
                'item_status' => $it['item_status'] ?? 'placed',
            ];
        }, $orderItems)); ?>,
        types: <?php echo json_encode($orderTypes); ?>,
        apiUrl: "<?php echo url; ?>/qr-order/order-status.php?order_id=<?php echo $orderId; ?>"
    };
    </script>
    <?php endif; ?>

    <!-- Kitchen config for ordering (always available) -->
    <script>
    window.KITCHEN_CONFIG = {
        categories: <?php echo json_encode($categories); ?>,
        razorpayKeyId: "<?php echo htmlspecialchars(RAZORPAY_KEY_ID); ?>",
        appUrl: "<?php echo url; ?>",
        apiBase: "<?php echo url; ?>/kitchen3d/api",
        tableNumber: <?php echo $tableNumber ? (int)$tableNumber : 'null'; ?>
    };
    </script>

    <!-- Razorpay SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <!-- Mini-Game Overlay -->
    <div id="minigame-overlay" style="display:none;" role="dialog" aria-modal="true">
        <div id="minigame-box">
            <div id="minigame-header">
                <span id="mg-npc-icon">🎮</span>
                <span id="mg-title">Mini Game</span>
                <button id="mg-close" aria-label="Close game">×</button>
            </div>
            <canvas id="mg-canvas" width="420" height="300"></canvas>
            <iframe id="mg-iframe" title="Mini Game" allowfullscreen></iframe>
            <div id="minigame-footer">
                <span id="mg-score">Score: 0</span>
                <span id="mg-timer">20s</span>
                <button id="mg-replay">▶ Play Again</button>
            </div>
        </div>
    </div>

    <!-- Click Interaction Progress Bar -->
    <div id="interaction-progress" style="display:none;" aria-hidden="true">
        <span class="ip-label">Brewing...</span>
        <div class="ip-bar"><div class="ip-fill"></div></div>
    </div>

    <!-- Three.js -->
    <script type="importmap">
    {
        "imports": {
            "three": "https://cdn.jsdelivr.net/npm/three@0.162.0/build/three.module.js",
            "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.162.0/examples/jsm/"
        }
    }
    </script>
    <script type="module" src="kitchen.js"></script>
</body>
</html>
