<?php
require_once __DIR__ . "/_bootstrap.php";

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if ($order_id <= 0) {
  header("Location: " . url . "/qr-order/menu.php");
  exit();
}

$tokEsc = mysqli_real_escape_string($conn, $_SESSION['qr_session_token']);
$orderRes = mysqli_query($conn, "SELECT * FROM orders WHERE id = {$order_id} AND session_token='{$tokEsc}' LIMIT 1");
if (!$orderRes || mysqli_num_rows($orderRes) === 0) {
  header("Location: " . url . "/qr-order/menu.php");
  exit();
}
$order = mysqli_fetch_assoc($orderRes);

$itemsRes = mysqli_query($conn, "SELECT oi.*, p.type FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = {$order_id}");
$items = [];
$types = [];
while ($it = mysqli_fetch_assoc($itemsRes)) {
  $items[] = $it;
  $t = strtolower(trim($it['type'] ?? 'coffee'));
  if (!in_array($t, $types)) $types[] = $t;
}
?>

<?php require_once __DIR__ . "/_header.php"; ?>

<style>
/* ═══════════════════════════════════════════════
   3D CAFÉ DIORAMA — INTERACTIVE ORDER TRACKING
   ═══════════════════════════════════════════════ */

.cafe-wrap { max-width: 720px; margin: 0 auto; }

/* ── Progress Bar ──────────────────────────── */
.track-bar {
  display: flex; justify-content: space-between;
  position: relative; margin: 16px 0 22px; padding: 0 4px;
}
.track-bar::before {
  content: ""; position: absolute; top: 18px; left: 24px; right: 24px;
  height: 4px; background: rgba(255,255,255,0.08); border-radius: 2px; z-index: 0;
}
.track-fill {
  position: absolute; top: 18px; left: 24px; height: 4px;
  background: #c49b63; border-radius: 2px; z-index: 1; transition: width 600ms ease;
}
.track-step { position: relative; z-index: 2; text-align: center; flex: 1; }
.track-dot {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.08); border: 3px solid rgba(255,255,255,0.12);
  margin: 0 auto 8px; display: flex; align-items: center; justify-content: center; font-size: 16px;
  transition: all 400ms ease;
}
.track-step.active .track-dot {
  background: rgba(196,155,99,0.25); border-color: #c49b63;
  box-shadow: 0 0 16px rgba(196,155,99,0.35);
}
.track-step.done .track-dot { background: #c49b63; border-color: #c49b63; color: #111; }
.track-lbl {
  font-size: 11px; font-weight: 800; color: rgba(255,255,255,0.5);
  text-transform: uppercase; letter-spacing: 0.04em; transition: color 400ms ease;
}
.track-step.active .track-lbl,
.track-step.done .track-lbl { color: #fff; }

/* ── 3D Room Container ─────────────────────── */
.cafe-room {
  position: relative; width: 100%; height: 420px;
  border-radius: 20px; overflow: hidden; cursor: default;
  border: 1px solid rgba(255,255,255,0.08);
  box-shadow: 0 20px 60px rgba(0,0,0,0.5);
  user-select: none;
  -webkit-user-select: none;
  perspective: 1200px;
  perspective-origin: 50% 45%;
}

.room-scene {
  position: absolute; inset: 0;
  transform-style: preserve-3d;
  transition: transform 150ms ease-out;
  will-change: transform;
}

/* ── Wall ──────────────────────────────────── */
.room-wall {
  position: absolute; inset: 0; bottom: 30%;
  background:
    repeating-linear-gradient(0deg,
      #3a1e0b 0px, #3a1e0b 2px,
      #4d2810 2px, #553015 19px, #4d2810 19px, #3a1e0b 20px,
      #3a1e0b 20px, #3a1e0b 22px,
      #52300f 22px, #5a3518 39px, #52300f 39px, #3a1e0b 40px
    );
  transform: translateZ(0);
}
.room-wall::after {
  content: ""; position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(0,0,0,0.45) 0%, rgba(0,0,0,0) 60%);
  pointer-events: none;
}

/* ── Floor with 3D tilt ────────────────────── */
.room-floor {
  position: absolute; left: 0; right: 0; bottom: 0; height: 30%;
  background:
    repeating-linear-gradient(90deg,
      #6d4424 0px, #7a4e2a 1px,
      #8b5e35 1px, #8b5e35 29px,
      #7a4e2a 29px, #6d4424 30px
    );
  border-top: 3px solid #9a6b3a;
  transform: translateZ(10px);
  transform-origin: center top;
}

.room-carpet {
  position: absolute; bottom: 0; left: 28%; right: 28%; height: 26%;
  background: linear-gradient(90deg, #8b1a1a, #a52525 10%, #b92e2e 50%, #a52525 90%, #8b1a1a);
  border-top: 3px solid #cc3838;
  opacity: 0.7;
  transform: translateZ(12px);
}

/* ── 3D Counter ────────────────────────────── */
.room-counter {
  position: absolute; left: 4%; right: 4%; bottom: 30%; height: 28px;
  background: linear-gradient(180deg, #a07818 0%, #8b6914 40%, #6b5010 100%);
  border: 2px solid #b8892a; border-bottom: none;
  border-radius: 4px 4px 0 0;
  z-index: 10;
  transform: translateZ(30px);
  transform-style: preserve-3d;
}
.room-counter::after {
  content: ""; position: absolute; top: 100%; left: 0; right: 0; height: 14px;
  background: linear-gradient(180deg, #6b5818, #4a3510);
  border: 2px solid #6b5818; border-top: none;
  transform-origin: top center;
  transform: rotateX(-15deg);
}

/* Counter items that appear during prep */
.counter-items {
  position: absolute; top: -18px; left: 10%; right: 10%;
  display: flex; justify-content: center; gap: 16px;
  z-index: 11;
}
.counter-item {
  font-size: 18px;
  opacity: 0; transform: translateY(10px) scale(0.5);
  transition: all 400ms cubic-bezier(0.34, 1.56, 0.64, 1);
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
}
.counter-item.visible {
  opacity: 1; transform: translateY(0) scale(1);
}

/* ── Windows with 3D depth ─────────────────── */
.room-window {
  position: absolute; top: 6%; width: 56px; height: 66px; z-index: 2;
  background: linear-gradient(160deg, #6BA3BE 0%, #89C4D8 40%, #A5D8E8 80%, #C5E8F0 100%);
  border: 4px solid #3a1e0b;
  box-shadow: 0 0 35px rgba(255,248,200,0.12), inset 0 0 18px rgba(255,255,255,0.15);
  transform: translateZ(5px);
  cursor: pointer;
  transition: box-shadow 400ms ease, background 600ms ease;
}
.room-window::before {
  content: ""; position: absolute; top: 0; left: 50%; width: 4px; height: 100%;
  background: #3a1e0b; transform: translateX(-50%);
}
.room-window::after {
  content: ""; position: absolute; top: 50%; left: 0; width: 100%; height: 4px;
  background: #3a1e0b; transform: translateY(-50%);
}
.room-window:hover {
  box-shadow: 0 0 50px rgba(255,248,200,0.3), inset 0 0 25px rgba(255,255,255,0.3);
}
.win-l { left: 10%; }
.win-r { right: 10%; }

/* Night mode windows */
.cafe-room.night .room-window {
  background: linear-gradient(160deg, #1a1a3a 0%, #2a2a5a 40%, #1a1a4a 80%, #0a0a2a 100%);
  box-shadow: 0 0 20px rgba(100,100,200,0.15), inset 0 0 10px rgba(100,100,200,0.1);
}
.cafe-room.night .room-wall::after {
  background: linear-gradient(180deg, rgba(0,0,30,0.6) 0%, rgba(0,0,20,0.3) 60%);
}

/* Window light beams */
.room-light {
  position: absolute; top: 6%; width: 90px; height: 250px; z-index: 1;
  opacity: 0.06; pointer-events: none;
  background: linear-gradient(180deg, rgba(255,248,200,1) 0%, rgba(255,248,200,0) 100%);
  transition: opacity 600ms ease;
}
.light-l { left: 8%; transform: skewX(8deg); }
.light-r { right: 8%; transform: skewX(-8deg); }
.cafe-room.night .room-light { opacity: 0.02; }

/* Day/Night overlay */
.night-overlay {
  position: absolute; inset: 0; z-index: 45;
  background: radial-gradient(ellipse at 50% 30%, transparent 30%, rgba(0,0,30,0.35) 100%);
  pointer-events: none; opacity: 0;
  transition: opacity 800ms ease;
}
.cafe-room.night .night-overlay { opacity: 1; }

/* ── Lanterns ──────────────────────────────── */
.room-lantern {
  position: absolute; top: 14%; z-index: 3; width: 4px; height: 4px;
  image-rendering: pixelated;
  cursor: pointer;
  transition: filter 300ms ease;
}
.room-lantern:hover { filter: brightness(1.4); }
.room-lantern .lant-body {
  position: absolute; width: 4px; height: 4px;
  box-shadow:
    0px -20px 0 0 #666, 0px -16px 0 0 #666, 0px -12px 0 0 #666,
    -4px -8px 0 0 #8b6914, 0px -8px 0 0 #FFD700, 4px -8px 0 0 #8b6914,
    -4px -4px 0 0 #8b6914, 0px -4px 0 0 #FFEC80, 4px -4px 0 0 #8b6914,
    -4px 0px 0 0 #8b6914, 0px 0px 0 0 #FFD700, 4px 0px 0 0 #8b6914,
    -4px 4px 0 0 #6b5010, 0px 4px 0 0 #6b5010, 4px 4px 0 0 #6b5010;
}
.room-lantern .lant-glow {
  position: absolute; top: -10px; left: -12px;
  width: 28px; height: 28px; border-radius: 50%;
  background: radial-gradient(circle, rgba(255,215,0,0.35) 0%, transparent 70%);
  animation: lantern-flicker 3s ease infinite alternate;
  transition: opacity 400ms ease;
}
.room-lantern.dim .lant-glow { opacity: 0.15; }

@keyframes lantern-flicker {
  0%, 100% { opacity: 0.7; transform: scale(1); }
  33% { opacity: 1; transform: scale(1.1); }
  66% { opacity: 0.6; transform: scale(0.95); }
}

/* Disco mode */
@keyframes disco-glow {
  0%   { background: radial-gradient(circle, rgba(255,0,0,0.5) 0%, transparent 70%); }
  25%  { background: radial-gradient(circle, rgba(0,255,0,0.5) 0%, transparent 70%); }
  50%  { background: radial-gradient(circle, rgba(0,100,255,0.5) 0%, transparent 70%); }
  75%  { background: radial-gradient(circle, rgba(255,0,255,0.5) 0%, transparent 70%); }
  100% { background: radial-gradient(circle, rgba(255,215,0,0.5) 0%, transparent 70%); }
}
.cafe-room.disco .room-lantern .lant-glow {
  animation: disco-glow 0.8s linear infinite;
  opacity: 1 !important;
  width: 50px; height: 50px; top: -20px; left: -23px;
}

/* ── Shelves ───────────────────────────────── */
.room-shelf {
  position: absolute; top: 28%; z-index: 2;
  width: 72px; height: 8px;
  background: #6b4226;
  border: 2px solid #4a2e16;
  border-radius: 1px;
  box-shadow: 0 3px 6px rgba(0,0,0,0.4);
  transform: translateZ(8px);
}
.shelf-items {
  position: absolute; bottom: 100%; left: 4px; display: flex; gap: 6px;
}
.shelf-item {
  width: 12px; height: 14px; border-radius: 2px 2px 0 0;
  border: 1px solid rgba(255,255,255,0.1);
  cursor: pointer;
  transition: transform 300ms cubic-bezier(0.34, 1.56, 0.64, 1);
}
.shelf-item:hover { transform: translateY(-4px) rotate(-8deg); }
.shelf-item:active { transform: translateY(-2px) rotate(8deg) scale(0.9); }
.si-cup { background: linear-gradient(180deg, #fff 60%, #ddd); width: 10px; height: 12px; }
.si-jar { background: linear-gradient(180deg, #8b6914, #6b5010); width: 14px; height: 16px; border-radius: 3px 3px 1px 1px; }
.si-plant { background: linear-gradient(180deg, #2d7a2d, #1a5a1a); width: 10px; height: 14px; border-radius: 4px 4px 1px 1px; }
.si-bottle { background: linear-gradient(180deg, #a04040, #802020); width: 8px; height: 18px; border-radius: 2px; }

@keyframes shelf-jiggle {
  0%, 100% { transform: rotate(0deg); }
  20% { transform: rotate(-12deg); }
  40% { transform: rotate(10deg); }
  60% { transform: rotate(-8deg); }
  80% { transform: rotate(5deg); }
}
.shelf-item.jiggle { animation: shelf-jiggle 0.5s ease; }

/* ── Painting ──────────────────────────────── */
.room-painting {
  position: absolute; top: 10%; left: 50%; transform: translateX(-50%) translateZ(6px); z-index: 2;
  width: 52px; height: 36px;
  border: 4px solid #5a3218;
  border-radius: 2px;
  background: linear-gradient(135deg, #2d5a1a 0%, #3d7a2a 30%, #6BA3BE 60%, #89C4D8 100%);
  box-shadow: 0 3px 10px rgba(0,0,0,0.5);
  cursor: pointer;
  transition: transform 400ms cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 300ms ease;
}
.room-painting:hover {
  transform: translateX(-50%) translateZ(6px) scale(1.1);
  box-shadow: 0 6px 20px rgba(0,0,0,0.6);
}

@keyframes painting-swap {
  0% { transform: translateX(-50%) translateZ(6px) rotateY(0deg); }
  50% { transform: translateX(-50%) translateZ(6px) rotateY(90deg); }
  100% { transform: translateX(-50%) translateZ(6px) rotateY(0deg); }
}
.room-painting.swapping { animation: painting-swap 0.5s ease; }

/* ── Station Zones ─────────────────────────── */
.station-zone {
  position: absolute; bottom: 10%; z-index: 20;
  width: 22%; text-align: center;
  cursor: pointer; transition: transform 200ms ease;
  transform: translateZ(40px);
}
.station-zone:hover { transform: translateZ(40px) translateY(-4px); }
.stn-coffee  { left: 2%; }
.stn-cooking { left: 27%; }
.stn-juice   { left: 52%; }
.stn-dessert { left: 76%; }

.station-zone::before {
  content: ""; position: absolute; inset: -10px -8px;
  border-radius: 14px; opacity: 0;
  transition: opacity 250ms ease; pointer-events: none;
  z-index: -1;
}
.station-zone:hover::before { opacity: 1; }
.stn-coffee::before  { background: radial-gradient(ellipse, rgba(107,66,38,0.4) 0%, transparent 70%); }
.stn-cooking::before { background: radial-gradient(ellipse, rgba(212,32,32,0.25) 0%, transparent 70%); }
.stn-juice::before   { background: radial-gradient(ellipse, rgba(26,138,26,0.3) 0%, transparent 70%); }
.stn-dessert::before { background: radial-gradient(ellipse, rgba(240,160,184,0.3) 0%, transparent 70%); }

.stn-label {
  font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.08em;
  color: rgba(255,255,255,0.6); margin-top: 6px;
  transition: color 250ms ease;
}
.station-zone:hover .stn-label { color: #fff; }
.station-zone.active .stn-label { color: #c49b63; }

.stn-prop {
  position: absolute; bottom: 68px; left: 50%; transform: translateX(-50%);
  font-size: 22px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.5));
  transition: transform 300ms ease;
  pointer-events: none;
}
.station-zone:hover .stn-prop { transform: translateX(-50%) scale(1.15) rotate(-5deg); }

/* ── 3D Pixel Characters ──────────────────── */
.mc-char {
  position: relative; width: 42px; height: 64px; margin: 0 auto;
  image-rendering: pixelated;
  transition: transform 300ms ease, filter 300ms ease;
  transform-origin: bottom center;
  transform-style: preserve-3d;
}
.mc-char .mc-pixel-body {
  position: absolute; bottom: 0; left: 50%;
  transform: translateX(-50%); width: 4px; height: 4px;
  image-rendering: pixelated;
}

/* 3D depth layer (darker duplicate behind character) */
.mc-char .mc-depth {
  position: absolute; bottom: 0; left: 50%;
  transform: translateX(calc(-50% + 2px)) translateY(2px);
  width: 4px; height: 4px;
  image-rendering: pixelated;
  opacity: 0.3;
  filter: brightness(0.3);
}

/* Ground shadow under character */
.mc-char .mc-ground-shadow {
  position: absolute; bottom: -4px; left: 50%;
  transform: translateX(-50%);
  width: 30px; height: 8px;
  background: radial-gradient(ellipse, rgba(0,0,0,0.4) 0%, transparent 70%);
  border-radius: 50%;
  transition: all 300ms ease;
}

.station-zone:hover .mc-char { transform: scale(1.12); }
.station-zone:hover .mc-ground-shadow { width: 34px; height: 10px; opacity: 0.8; }
.station-zone.active .mc-char { filter: drop-shadow(0 0 8px rgba(196,155,99,0.5)); }
.station-zone.inactive .mc-char { opacity: 0.4; filter: grayscale(0.5); }

/* Barista */
.mc-barista .mc-pixel-body,
.mc-barista .mc-depth {
  box-shadow:
    -4px -52px 0 0 #f5c89a, 0px -52px 0 0 #f5c89a, 4px -52px 0 0 #f5c89a,
    -8px -48px 0 0 #f5c89a, -4px -48px 0 0 #f5c89a, 0px -48px 0 0 #f5c89a, 4px -48px 0 0 #f5c89a, 8px -48px 0 0 #f5c89a,
    -4px -44px 0 0 #222, 4px -44px 0 0 #222,
    0px -40px 0 0 #d4956a,
    -8px -56px 0 0 #4a2800, -4px -56px 0 0 #4a2800, 0px -56px 0 0 #4a2800, 4px -56px 0 0 #4a2800, 8px -56px 0 0 #4a2800,
    -12px -52px 0 0 #4a2800, 12px -52px 0 0 #4a2800,
    -8px -36px 0 0 #6b4226, -4px -36px 0 0 #6b4226, 0px -36px 0 0 #6b4226, 4px -36px 0 0 #6b4226, 8px -36px 0 0 #6b4226,
    -8px -32px 0 0 #6b4226, -4px -32px 0 0 #fff, 0px -32px 0 0 #fff, 4px -32px 0 0 #fff, 8px -32px 0 0 #6b4226,
    -8px -28px 0 0 #6b4226, -4px -28px 0 0 #fff, 0px -28px 0 0 #fff, 4px -28px 0 0 #fff, 8px -28px 0 0 #6b4226,
    -8px -24px 0 0 #6b4226, -4px -24px 0 0 #6b4226, 0px -24px 0 0 #6b4226, 4px -24px 0 0 #6b4226, 8px -24px 0 0 #6b4226,
    -16px -36px 0 0 #f5c89a, -16px -32px 0 0 #f5c89a,
    16px -36px 0 0 #f5c89a, 16px -32px 0 0 #f5c89a,
    -4px -20px 0 0 #333, 4px -20px 0 0 #333,
    -4px -16px 0 0 #333, 4px -16px 0 0 #333,
    -4px -12px 0 0 #333, 4px -12px 0 0 #333,
    -4px -8px 0 0 #222, 4px -8px 0 0 #222,
    -4px -4px 0 0 #222, 4px -4px 0 0 #222;
}

/* Chef */
.mc-chef .mc-pixel-body,
.mc-chef .mc-depth {
  box-shadow:
    -8px -64px 0 0 #fff, -4px -64px 0 0 #fff, 0px -64px 0 0 #fff, 4px -64px 0 0 #fff, 8px -64px 0 0 #fff,
    -4px -60px 0 0 #fff, 0px -60px 0 0 #fff, 4px -60px 0 0 #fff,
    -4px -52px 0 0 #f5c89a, 0px -52px 0 0 #f5c89a, 4px -52px 0 0 #f5c89a,
    -8px -48px 0 0 #f5c89a, -4px -48px 0 0 #f5c89a, 0px -48px 0 0 #f5c89a, 4px -48px 0 0 #f5c89a, 8px -48px 0 0 #f5c89a,
    -8px -56px 0 0 #d42020, -4px -56px 0 0 #d42020, 0px -56px 0 0 #d42020, 4px -56px 0 0 #d42020, 8px -56px 0 0 #d42020,
    -4px -44px 0 0 #222, 4px -44px 0 0 #222,
    0px -40px 0 0 #d4956a,
    -8px -36px 0 0 #eee, -4px -36px 0 0 #eee, 0px -36px 0 0 #eee, 4px -36px 0 0 #eee, 8px -36px 0 0 #eee,
    -8px -32px 0 0 #eee, -4px -32px 0 0 #ddd, 0px -32px 0 0 #ddd, 4px -32px 0 0 #ddd, 8px -32px 0 0 #eee,
    -8px -28px 0 0 #eee, -4px -28px 0 0 #ddd, 0px -28px 0 0 #ddd, 4px -28px 0 0 #ddd, 8px -28px 0 0 #eee,
    -8px -24px 0 0 #eee, -4px -24px 0 0 #eee, 0px -24px 0 0 #eee, 4px -24px 0 0 #eee, 8px -24px 0 0 #eee,
    -16px -36px 0 0 #f5c89a, -16px -32px 0 0 #f5c89a,
    16px -36px 0 0 #f5c89a, 16px -32px 0 0 #f5c89a,
    -4px -20px 0 0 #444, 4px -20px 0 0 #444,
    -4px -16px 0 0 #444, 4px -16px 0 0 #444,
    -4px -12px 0 0 #444, 4px -12px 0 0 #444,
    -4px -8px 0 0 #222, 4px -8px 0 0 #222,
    -4px -4px 0 0 #222, 4px -4px 0 0 #222;
}

/* Mixer */
.mc-mixer .mc-pixel-body,
.mc-mixer .mc-depth {
  box-shadow:
    -4px -52px 0 0 #f5c89a, 0px -52px 0 0 #f5c89a, 4px -52px 0 0 #f5c89a,
    -8px -48px 0 0 #f5c89a, -4px -48px 0 0 #f5c89a, 0px -48px 0 0 #f5c89a, 4px -48px 0 0 #f5c89a, 8px -48px 0 0 #f5c89a,
    -4px -44px 0 0 #222, 4px -44px 0 0 #222,
    0px -40px 0 0 #d4956a,
    -8px -56px 0 0 #1a8a1a, -4px -56px 0 0 #1a8a1a, 0px -56px 0 0 #1a8a1a, 4px -56px 0 0 #1a8a1a, 8px -56px 0 0 #1a8a1a,
    -12px -52px 0 0 #1a8a1a, 12px -52px 0 0 #1a8a1a,
    -8px -36px 0 0 #1a8a1a, -4px -36px 0 0 #1a8a1a, 0px -36px 0 0 #1a8a1a, 4px -36px 0 0 #1a8a1a, 8px -36px 0 0 #1a8a1a,
    -8px -32px 0 0 #1a8a1a, -4px -32px 0 0 #fff, 0px -32px 0 0 #fff, 4px -32px 0 0 #fff, 8px -32px 0 0 #1a8a1a,
    -8px -28px 0 0 #1a8a1a, -4px -28px 0 0 #fff, 0px -28px 0 0 #fff, 4px -28px 0 0 #fff, 8px -28px 0 0 #1a8a1a,
    -8px -24px 0 0 #1a8a1a, -4px -24px 0 0 #1a8a1a, 0px -24px 0 0 #1a8a1a, 4px -24px 0 0 #1a8a1a, 8px -24px 0 0 #1a8a1a,
    -16px -36px 0 0 #f5c89a, -16px -32px 0 0 #f5c89a,
    16px -36px 0 0 #f5c89a, 16px -32px 0 0 #f5c89a,
    -4px -20px 0 0 #335, 4px -20px 0 0 #335,
    -4px -16px 0 0 #335, 4px -16px 0 0 #335,
    -4px -12px 0 0 #335, 4px -12px 0 0 #335,
    -4px -8px 0 0 #222, 4px -8px 0 0 #222,
    -4px -4px 0 0 #222, 4px -4px 0 0 #222;
}

/* Pastry */
.mc-pastry .mc-pixel-body,
.mc-pastry .mc-depth {
  box-shadow:
    -8px -64px 0 0 #f0a0b8, -4px -64px 0 0 #f0a0b8, 0px -64px 0 0 #f0a0b8, 4px -64px 0 0 #f0a0b8, 8px -64px 0 0 #f0a0b8,
    -4px -60px 0 0 #f0a0b8, 0px -60px 0 0 #f0a0b8, 4px -60px 0 0 #f0a0b8,
    -4px -52px 0 0 #f5c89a, 0px -52px 0 0 #f5c89a, 4px -52px 0 0 #f5c89a,
    -8px -48px 0 0 #f5c89a, -4px -48px 0 0 #f5c89a, 0px -48px 0 0 #f5c89a, 4px -48px 0 0 #f5c89a, 8px -48px 0 0 #f5c89a,
    -4px -44px 0 0 #222, 4px -44px 0 0 #222,
    0px -40px 0 0 #d4956a,
    -8px -56px 0 0 #c06080, 12px -52px 0 0 #c06080, -12px -52px 0 0 #c06080,
    -8px -36px 0 0 #f0a0b8, -4px -36px 0 0 #f0a0b8, 0px -36px 0 0 #f0a0b8, 4px -36px 0 0 #f0a0b8, 8px -36px 0 0 #f0a0b8,
    -8px -32px 0 0 #f0a0b8, -4px -32px 0 0 #fff, 0px -32px 0 0 #fff, 4px -32px 0 0 #fff, 8px -32px 0 0 #f0a0b8,
    -8px -28px 0 0 #f0a0b8, -4px -28px 0 0 #fff, 0px -28px 0 0 #fff, 4px -28px 0 0 #fff, 8px -28px 0 0 #f0a0b8,
    -8px -24px 0 0 #f0a0b8, -4px -24px 0 0 #f0a0b8, 0px -24px 0 0 #f0a0b8, 4px -24px 0 0 #f0a0b8, 8px -24px 0 0 #f0a0b8,
    -16px -36px 0 0 #f5c89a, -16px -32px 0 0 #f5c89a,
    16px -36px 0 0 #f5c89a, 16px -32px 0 0 #f5c89a,
    -4px -20px 0 0 #555, 4px -20px 0 0 #555,
    -4px -16px 0 0 #555, 4px -16px 0 0 #555,
    -4px -12px 0 0 #555, 4px -12px 0 0 #555,
    -4px -8px 0 0 #222, 4px -8px 0 0 #222,
    -4px -4px 0 0 #222, 4px -4px 0 0 #222;
}

/* ── Character Animations ──────────────────── */
@keyframes mc-idle {
  0%, 100% { transform: translateX(-50%) translateY(0); }
  50% { transform: translateX(-50%) translateY(-6px); }
}
@keyframes mc-work {
  0%, 100% { transform: translateX(-50%) translateY(0) rotate(0deg); }
  25% { transform: translateX(-50%) translateY(-5px) rotate(-4deg); }
  75% { transform: translateX(-50%) translateY(-5px) rotate(4deg); }
}
@keyframes mc-celebrate {
  0%, 100% { transform: translateX(-50%) translateY(0) scale(1); }
  25% { transform: translateX(-50%) translateY(-16px) scale(1.08); }
  50% { transform: translateX(-50%) translateY(-4px) scale(1); }
  75% { transform: translateX(-50%) translateY(-16px) scale(1.08); }
}
@keyframes mc-wave {
  0%, 100% { transform: translateX(-50%) translateY(0) rotate(0); }
  15% { transform: translateX(-50%) translateY(-8px) rotate(-6deg); }
  30% { transform: translateX(-50%) translateY(-4px) rotate(6deg); }
  45% { transform: translateX(-50%) translateY(-8px) rotate(-6deg); }
  60% { transform: translateX(-50%) translateY(-4px) rotate(6deg); }
  75% { transform: translateX(-50%) translateY(0) rotate(0); }
}
@keyframes mc-jump {
  0% { transform: translateX(-50%) translateY(0) scale(1, 1); }
  15% { transform: translateX(-50%) translateY(2px) scale(1.1, 0.85); }
  30% { transform: translateX(-50%) translateY(-24px) scale(0.9, 1.15); }
  50% { transform: translateX(-50%) translateY(-28px) scale(0.95, 1.08); }
  70% { transform: translateX(-50%) translateY(-6px) scale(1.05, 0.92); }
  85% { transform: translateX(-50%) translateY(2px) scale(1.05, 0.95); }
  100% { transform: translateX(-50%) translateY(0) scale(1, 1); }
}
@keyframes mc-spin {
  0% { transform: translateX(-50%) rotateY(0deg); }
  100% { transform: translateX(-50%) rotateY(360deg); }
}

.anim-idle .mc-pixel-body,
.anim-idle .mc-depth      { animation: mc-idle 1.4s ease infinite; }
.anim-work .mc-pixel-body,
.anim-work .mc-depth      { animation: mc-work 0.55s ease infinite; }
.anim-celebrate .mc-pixel-body,
.anim-celebrate .mc-depth { animation: mc-celebrate 0.7s ease infinite; }
.anim-wave .mc-pixel-body,
.anim-wave .mc-depth      { animation: mc-wave 0.8s ease; }
.anim-jump .mc-pixel-body,
.anim-jump .mc-depth      { animation: mc-jump 0.6s cubic-bezier(0.34, 1.56, 0.64, 1); }

/* ── Emoji Reaction Pop ────────────────────── */
@keyframes emoji-pop {
  0% { opacity: 1; transform: translate(-50%, 0) scale(0.3); }
  30% { opacity: 1; transform: translate(-50%, -30px) scale(1.2); }
  100% { opacity: 0; transform: translate(-50%, -60px) scale(0.6); }
}
.emoji-react {
  position: absolute; top: -10px; left: 50%; z-index: 55;
  font-size: 24px; pointer-events: none;
  animation: emoji-pop 1s ease forwards;
}

/* ── Dialogue Bubble ───────────────────────── */
.bubble {
  position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%) translateY(8px);
  background: rgba(0,0,0,0.88); border: 1.5px solid rgba(255,255,255,0.2);
  border-radius: 12px; padding: 6px 12px;
  font-size: 12px; font-weight: 700; color: #fff; white-space: nowrap;
  opacity: 0; pointer-events: none;
  transition: opacity 350ms ease, transform 350ms ease;
  z-index: 50;
  backdrop-filter: blur(8px);
}
.bubble::after {
  content: ""; position: absolute; top: 100%; left: 50%;
  transform: translateX(-50%);
  border: 6px solid transparent; border-top-color: rgba(0,0,0,0.88);
}
.bubble.visible {
  opacity: 1; transform: translateX(-50%) translateY(-4px);
}

/* ── Particles ─────────────────────────────── */
@keyframes steam-rise {
  0% { opacity: 0.7; transform: translateY(0) scale(1); }
  100% { opacity: 0; transform: translateY(-40px) scale(0.4); }
}
@keyframes fire-flicker {
  0% { opacity: 0.8; transform: translateY(0) scale(1); }
  50% { opacity: 1; transform: translateY(-8px) scale(1.1); }
  100% { opacity: 0; transform: translateY(-30px) scale(0.3); }
}
.particle {
  position: absolute; width: 6px; height: 6px; border-radius: 50%;
  pointer-events: none; z-index: 30;
}
.particle.steam { background: rgba(255,255,255,0.5); animation: steam-rise 2s ease infinite; }
.particle.fire  { background: #ff6030; animation: fire-flicker 1s ease infinite; }

/* ── Sparkles ──────────────────────────────── */
@keyframes sparkle-pop {
  0% { opacity: 0; transform: translateY(0) scale(0.3); }
  40% { opacity: 1; transform: translateY(-25px) scale(1); }
  100% { opacity: 0; transform: translateY(-50px) scale(0.3); }
}
.sparkle {
  position: absolute; width: 8px; height: 8px; border-radius: 50%;
  pointer-events: none; z-index: 40;
  animation: sparkle-pop 1.2s ease infinite;
}

/* ── Confetti ──────────────────────────────── */
@keyframes confetti-fall {
  0% { opacity: 1; transform: translateY(-20px) rotate(0deg) scale(1); }
  100% { opacity: 0; transform: translateY(420px) rotate(720deg) scale(0.3); }
}
.confetti-piece {
  position: absolute; top: 0; width: 8px; height: 12px;
  pointer-events: none; z-index: 55; border-radius: 2px;
  animation: confetti-fall linear forwards;
}

/* ── Ingredient Catching Game ──────────────── */
.ingredient-game {
  position: absolute; inset: 0; z-index: 42;
  pointer-events: none; overflow: hidden;
}
.ingredient-game.active { pointer-events: auto; cursor: grab; }

@keyframes ingredient-float {
  0% { transform: translateY(-40px) rotate(0deg); opacity: 0; }
  10% { opacity: 1; }
  90% { opacity: 1; }
  100% { transform: translateY(420px) rotate(360deg); opacity: 0; }
}
.floating-ingredient {
  position: absolute; top: 0;
  font-size: 28px; pointer-events: auto; cursor: pointer;
  animation: ingredient-float linear forwards;
  filter: drop-shadow(0 2px 6px rgba(0,0,0,0.4));
  transition: transform 100ms ease;
  z-index: 43;
}
.floating-ingredient:hover { transform: scale(1.3); }

@keyframes catch-burst {
  0% { opacity: 1; transform: scale(1); }
  100% { opacity: 0; transform: scale(2.5); }
}
.catch-effect {
  position: absolute; pointer-events: none; z-index: 44;
  font-size: 20px;
  animation: catch-burst 0.4s ease forwards;
}
.catch-score {
  position: absolute; pointer-events: none; z-index: 44;
  font-weight: 900; font-size: 18px; color: #FFD700;
  animation: float-up 0.7s ease forwards;
  text-shadow: 0 1px 4px rgba(0,0,0,0.6);
}

/* ── Help Button ───────────────────────────── */
.help-zone {
  text-align: center; margin-top: 12px;
  opacity: 0; transform: translateY(10px);
  transition: opacity 400ms ease, transform 400ms ease;
  pointer-events: none;
}
.help-zone.show { opacity: 1; transform: translateY(0); pointer-events: auto; }
.help-btn {
  display: inline-flex; align-items: center; gap: 10px;
  padding: 12px 24px; border-radius: 999px; border: 2px solid rgba(196,155,99,0.4);
  background: rgba(196,155,99,0.12); color: #fff;
  font-weight: 800; font-size: 14px; cursor: pointer;
  transition: all 200ms ease;
  animation: help-pulse 2s ease infinite;
}
.help-btn:hover {
  background: rgba(196,155,99,0.25); border-color: #c49b63;
  transform: scale(1.05);
}
.help-btn:active { transform: scale(0.96); }
@keyframes help-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(196,155,99,0.3); }
  50% { box-shadow: 0 0 0 10px rgba(196,155,99,0); }
}
.boost-bar {
  width: 200px; height: 8px; border-radius: 999px; margin: 10px auto 0;
  background: rgba(255,255,255,0.08); overflow: hidden;
}
.boost-fill {
  height: 100%; width: 0%; border-radius: 999px;
  background: linear-gradient(90deg, #c49b63, #FFD700);
  transition: width 300ms ease;
}
.boost-msg {
  font-size: 13px; font-weight: 800; color: #c49b63;
  margin-top: 6px; min-height: 20px;
}

/* ── Tap Burst ─────────────────────────────── */
@keyframes tap-burst {
  0% { opacity: 1; transform: translate(-50%,-50%) scale(0.5); }
  100% { opacity: 0; transform: translate(-50%,-50%) scale(2.5); }
}
.tap-ring {
  position: absolute; width: 40px; height: 40px; border-radius: 50%;
  border: 3px solid #c49b63; pointer-events: none; z-index: 60;
  animation: tap-burst 0.5s ease forwards;
}
@keyframes float-up {
  0% { opacity: 1; transform: translateY(0) scale(1); }
  100% { opacity: 0; transform: translateY(-40px) scale(0.6); }
}
.float-text {
  position: absolute; pointer-events: none; z-index: 60;
  font-weight: 900; font-size: 16px; color: #FFD700;
  animation: float-up 0.8s ease forwards;
}

/* ── Sound Toggle ──────────────────────────── */
.sound-toggle {
  position: absolute; top: 12px; right: 12px; z-index: 50;
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.15);
  color: #fff; font-size: 16px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 200ms ease;
}
.sound-toggle:hover {
  background: rgba(0,0,0,0.8); border-color: rgba(255,255,255,0.3);
}

/* ── Day/Night Toggle ──────────────────────── */
.dn-toggle {
  position: absolute; top: 12px; right: 56px; z-index: 50;
  width: 36px; height: 36px; border-radius: 10px;
  background: rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.15);
  color: #fff; font-size: 16px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all 200ms ease;
}
.dn-toggle:hover {
  background: rgba(0,0,0,0.8); border-color: rgba(255,255,255,0.3);
}

/* ── Interaction Hint ──────────────────────── */
.interact-hint {
  position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%);
  z-index: 48; font-size: 11px; color: rgba(255,255,255,0.35);
  font-weight: 700; letter-spacing: 0.05em;
  pointer-events: none;
  animation: hint-fade 4s ease 2s forwards;
}
@keyframes hint-fade {
  0% { opacity: 1; }
  100% { opacity: 0; }
}

/* ── Status & Items ────────────────────────── */
.cafe-status-text {
  text-align: center; margin: 18px 0 6px;
  font-size: 22px; font-weight: 900; color: #fff;
}
.cafe-status-sub {
  text-align: center; color: rgba(255,255,255,0.65); font-size: 14px; margin-bottom: 16px;
}
.cafe-items { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.cafe-chip {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 8px 14px; border-radius: 999px;
  background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.10);
  color: #fff; font-weight: 700; font-size: 13px;
}
.cafe-chip .dot { width: 8px; height: 8px; border-radius: 50%; }

/* ── Responsive ────────────────────────────── */
@media (max-width: 576px) {
  .cafe-room { height: 340px; }
  .station-zone { width: 23%; }
  .stn-coffee  { left: 1%; }
  .stn-cooking { left: 26%; }
  .stn-juice   { left: 51%; }
  .stn-dessert { left: 75%; }
  .mc-char { transform: scale(0.85); }
  .station-zone:hover .mc-char { transform: scale(0.95); }
  .stn-prop { font-size: 18px; bottom: 58px; }
  .bubble { font-size: 10px; padding: 5px 9px; }
  .room-window { width: 44px; height: 52px; }
  .room-painting { width: 40px; height: 28px; }
  .room-shelf { width: 56px; }
  .cafe-status-text { font-size: 18px; }
  .floating-ingredient { font-size: 22px; }
  .dn-toggle { right: 52px; }
}
</style>

<div class="container qr-page">
  <div class="cafe-wrap">

    <div class="qr-page-top">
      <h3 class="qr-page-title">Order #<?php echo $order_id; ?></h3>
      <div class="qr-page-actions">
        <a class="btn btn-white btn-outline-white" href="<?php echo url; ?>/qr-order/invoice.php?order_id=<?php echo $order_id; ?>">Invoice</a>
        <a class="btn btn-primary" href="<?php echo url; ?>/qr-order/menu.php">Order more</a>
      </div>
    </div>

    <!-- Progress bar -->
    <div class="track-bar" id="trackBar">
      <div class="track-fill" id="trackFill" style="width:0%"></div>
      <div class="track-step" data-step="placed">
        <div class="track-dot">🍳</div>
        <div class="track-lbl">Placed</div>
      </div>
      <div class="track-step" data-step="preparing">
        <div class="track-dot">👨‍🍳</div>
        <div class="track-lbl">Preparing</div>
      </div>
      <div class="track-step" data-step="brewing">
        <div class="track-dot">🔥</div>
        <div class="track-lbl">Cooking</div>
      </div>
      <div class="track-step" data-step="ready">
        <div class="track-dot">✅</div>
        <div class="track-lbl">Ready</div>
      </div>
    </div>

    <!-- ═══ THE 3D CAFÉ DIORAMA ═══ -->
    <div class="cafe-room" id="cafeRoom">
      <div class="room-scene" id="roomScene">
        <!-- Ambient layers -->
        <div class="room-wall"></div>
        <div class="room-floor"></div>
        <div class="room-carpet"></div>

        <!-- Windows -->
        <div class="room-window win-l" id="winL"></div>
        <div class="room-window win-r" id="winR"></div>
        <div class="room-light light-l"></div>
        <div class="room-light light-r"></div>

        <!-- Lanterns -->
        <div class="room-lantern" id="lant1" style="left:25%"><div class="lant-body"></div><div class="lant-glow"></div></div>
        <div class="room-lantern" id="lant2" style="left:50%"><div class="lant-body"></div><div class="lant-glow"></div></div>
        <div class="room-lantern" id="lant3" style="left:75%"><div class="lant-body"></div><div class="lant-glow"></div></div>

        <!-- Shelves -->
        <div class="room-shelf" style="left:32%">
          <div class="shelf-items">
            <div class="shelf-item si-cup"></div>
            <div class="shelf-item si-jar"></div>
            <div class="shelf-item si-cup"></div>
          </div>
        </div>
        <div class="room-shelf" style="left:55%">
          <div class="shelf-items">
            <div class="shelf-item si-bottle"></div>
            <div class="shelf-item si-plant"></div>
            <div class="shelf-item si-jar"></div>
          </div>
        </div>

        <!-- Painting -->
        <div class="room-painting" id="roomPainting"></div>

        <!-- Counter with 3D items -->
        <div class="room-counter">
          <div class="counter-items" id="counterItems"></div>
        </div>

        <!-- Night overlay -->
        <div class="night-overlay"></div>

        <!-- Ingredient Game overlay -->
        <div class="ingredient-game" id="ingredientGame"></div>

        <!-- ═══ STATION ZONES ═══ -->
        <div class="station-zone stn-coffee" id="stnCoffee" data-type="coffee">
          <div class="stn-prop">☕</div>
          <div class="mc-char mc-barista anim-idle" id="charCoffee">
            <div class="bubble" id="bubCoffee"></div>
            <div class="mc-pixel-body"></div>
            <div class="mc-depth"></div>
            <div class="mc-ground-shadow"></div>
          </div>
          <div class="stn-label">Barista</div>
        </div>

        <div class="station-zone stn-cooking" id="stnCooking" data-type="cooking">
          <div class="stn-prop">🍔</div>
          <div class="mc-char mc-chef anim-idle" id="charCooking">
            <div class="bubble" id="bubCooking"></div>
            <div class="mc-pixel-body"></div>
            <div class="mc-depth"></div>
            <div class="mc-ground-shadow"></div>
          </div>
          <div class="stn-label">Chef</div>
        </div>

        <div class="station-zone stn-juice" id="stnJuice" data-type="juice">
          <div class="stn-prop">🧃</div>
          <div class="mc-char mc-mixer anim-idle" id="charJuice">
            <div class="bubble" id="bubJuice"></div>
            <div class="mc-pixel-body"></div>
            <div class="mc-depth"></div>
            <div class="mc-ground-shadow"></div>
          </div>
          <div class="stn-label">Mixer</div>
        </div>

        <div class="station-zone stn-dessert" id="stnDessert" data-type="dessert">
          <div class="stn-prop">🍰</div>
          <div class="mc-char mc-pastry anim-idle" id="charDessert">
            <div class="bubble" id="bubDessert"></div>
            <div class="mc-pixel-body"></div>
            <div class="mc-depth"></div>
            <div class="mc-ground-shadow"></div>
          </div>
          <div class="stn-label">Pastry</div>
        </div>
      </div>

      <!-- Interaction hint -->
      <div class="interact-hint">✦ Move mouse to look around · Click characters to interact ✦</div>

      <!-- Controls -->
      <button class="dn-toggle" id="dnToggle" title="Day / Night">☀️</button>
      <button class="sound-toggle" id="soundToggle" title="Toggle sound">🔇</button>
    </div>

    <!-- Help / Gamification -->
    <div class="help-zone" id="helpZone">
      <button class="help-btn" id="helpBtn">🤚 Tap to help the kitchen!</button>
      <div class="boost-bar"><div class="boost-fill" id="boostFill"></div></div>
      <div class="boost-msg" id="boostMsg"></div>
    </div>

    <!-- Status text -->
    <div class="cafe-status-text" id="statusText">Preparing your order...</div>
    <div class="cafe-status-sub" id="statusSub">Our kitchen team is working on it</div>

    <!-- Item chips -->
    <div class="cafe-items" id="cafeItems"></div>
  </div>
</div>

<script>
(function() {
  /* ═══════════════════════════════════════════
     CONFIG
     ═══════════════════════════════════════════ */
  const ORDER_ID = <?php echo $order_id; ?>;
  const API = "<?php echo url; ?>/qr-order/order-status.php?order_id=" + ORDER_ID;
  const INIT_STATUS = "<?php echo htmlspecialchars($order['status']); ?>";
  const INIT_TYPES = <?php echo json_encode($types); ?>;
  const INIT_ITEMS = <?php echo json_encode(array_map(function($it) {
    return ["name" => $it["name"], "quantity" => $it["quantity"], "type" => $it["type"] ?? "coffee"];
  }, $items)); ?>;

  const STATUS_LABELS = {
    placed: "Order placed", created: "Order placed",
    preparing: "Preparing your order...",
    brewing: "Cooking & brewing...",
    ready: "Your order is ready! 🎉"
  };
  const STATUS_SUBS = {
    placed: "We've received your order", created: "We've received your order",
    preparing: "Our kitchen team is working on it",
    brewing: "Almost there — final touches",
    ready: "Pick it up from the counter"
  };
  const DOT_COLORS = {
    coffee: "#6b4226", drink: "#1a8a1a", dessert: "#f0a0b8",
    starter: "#c49b63", "main dish": "#c49b63"
  };
  const TYPE_TO_STATION = {
    coffee: "coffee", drink: "juice", dessert: "dessert",
    starter: "cooking", "main dish": "cooking"
  };

  /* ═══════════════════════════════════════════
     DIALOGUES
     ═══════════════════════════════════════════ */
  const DIALOGUES = {
    coffee: {
      idle:      ["Waiting for orders… ☕", "These beans smell amazing 🫘", "Ready when you are!"],
      preparing: ["Grinding those fresh beans ☕", "Getting the temperature right 🌡️", "Freshly ground, coming up!", "The aroma is unreal today ☕"],
      brewing:   ["Pouring the perfect latte art 🎨", "Almost done! Smell that? ☕", "Final touches on your drink!", "This one's gonna be perfect ✨"],
      ready:     ["Your coffee is ready! ☕🎉", "Hot and fresh, just for you!", "Come get this masterpiece! ☕"],
      tap:       ["Hey! I'm working! 😄", "Easy there! ☕", "That tickles!", "Want a taste? 😏", "I'll make yours extra special!"]
    },
    cooking: {
      idle:      ["Kitchen's ready! 🔪", "Fire's hot and waiting 🔥", "What are we cooking today?"],
      preparing: ["Chopping fresh ingredients 🔪", "Your sandwich is on the grill! 🔥", "Sizzling away! Hear that?", "Secret spice goes in now 🤫"],
      brewing:   ["Flipping your order perfectly 🍳", "Golden brown, almost done! 🔥", "Plating this up for you…", "Smells incredible! ✨"],
      ready:     ["Order up! Come get it! 🎉", "Perfectly cooked, just for you!", "Chef's special is served 👨‍🍳"],
      tap:       ["Don't distract the chef! 😤", "Careful, it's hot! 🔥", "You want a turn? 🍳", "My spatula is not a toy!"]
    },
    juice: {
      idle:      ["Got fresh fruits ready! 🍊", "Blender is clean and ready 🧃", "What flavor today?"],
      preparing: ["Adding the freshest fruits 🍓", "Blending your juice 🧃", "Mixing in something special ✨", "Fresh squeeze coming up!"],
      brewing:   ["Pouring your fresh juice 🧃", "Almost there! Looks amazing 🌈", "Adding a dash of love ❤️"],
      ready:     ["Your fresh juice is ready! 🧃🎉", "Cold, fresh, and delicious!", "Come grab this beauty! 🌈"],
      tap:       ["Don't spill the juice! 🧃", "Hey, I'm mixing here!", "Want to try blending? 🌀", "Fruity vibes only! 🍓"]
    },
    dessert: {
      idle:      ["Got fresh pastries! 🍰", "The oven is warm and ready 🧁", "Sweet tooth? I got you!"],
      preparing: ["Decorating with care 🎨", "Adding the perfect toppings 🍰", "This is gonna look beautiful!", "Almost too pretty to eat ✨"],
      brewing:   ["Final sprinkle of magic ✨", "Plating your masterpiece 🍰", "The toppings are *chef's kiss* 💋"],
      ready:     ["Your dessert is ready! 🍰🎉", "A masterpiece, if I say so!", "Sweet perfection awaits! ✨"],
      tap:       ["Don't poke the cake! 🎂", "These hands are artists! 🎨", "Sprinkles for you? ✨", "No samples! ...okay maybe one"]
    },
    inactive: {
      idle: ["Not on your order, but hi! 👋", "My station is free right now 😊", "Order from me next time! 😄"]
    }
  };

  const CHAR_EMOJIS = {
    coffee: ["☕", "🫘", "🎨", "✨"],
    cooking: ["🍳", "🔥", "🍔", "👨‍🍳"],
    juice: ["🧃", "🍓", "🍊", "🌈"],
    dessert: ["🍰", "🎂", "🧁", "✨"]
  };

  const PAINTING_STYLES = [
    "linear-gradient(135deg, #2d5a1a 0%, #3d7a2a 30%, #6BA3BE 60%, #89C4D8 100%)",
    "linear-gradient(135deg, #8b1a1a 0%, #c49b63 40%, #FFD700 100%)",
    "linear-gradient(135deg, #1a1a5a 0%, #6BA3BE 50%, #f0a0b8 100%)",
    "linear-gradient(135deg, #4a2800 0%, #6b4226 30%, #c49b63 60%, #FFD700 100%)",
    "linear-gradient(135deg, #0a0a2a 0%, #1a1a5a 40%, #c06080 70%, #f0a0b8 100%)"
  ];

  const INGREDIENTS = {
    coffee:  ["☕", "🫘", "🥛", "🍫"],
    cooking: ["🍅", "🧅", "🥬", "🌶️", "🧀"],
    juice:   ["🍓", "🍊", "🍋", "🥝", "🍇"],
    dessert: ["🍓", "🍫", "🥚", "🧈", "🍒"]
  };

  const COUNTER_FOOD = {
    coffee:  ["☕", "🍵"],
    cooking: ["🍔", "🥪", "🍕"],
    juice:   ["🧃", "🥤"],
    dessert: ["🍰", "🧁", "🍩"]
  };

  /* ═══════════════════════════════════════════
     STATE
     ═══════════════════════════════════════════ */
  let currentStatus = "";
  let activeStations = new Set();
  let boostCount = 0;
  const BOOST_MAX = 12;
  let isNight = false;
  let paintingIdx = 0;
  let discoMode = false;
  let discoClicks = 0;
  let discoTimer = null;
  let charClickCount = {};
  let ingredientScore = 0;
  let ingredientInterval = null;

  /* ═══════════════════════════════════════════
     SOUND ENGINE
     ═══════════════════════════════════════════ */
  const Sound = {
    ctx: null, enabled: false, ambient: null,

    init() {
      this.ctx = new (window.AudioContext || window.webkitAudioContext)();
    },

    toggle() {
      if (!this.ctx) this.init();
      this.enabled = !this.enabled;
      document.getElementById("soundToggle").textContent = this.enabled ? "🔊" : "🔇";
      if (this.enabled) this.startAmbient(); else this.stopAmbient();
    },

    startAmbient() {
      if (!this.ctx || this.ambient) return;
      const osc = this.ctx.createOscillator();
      osc.type = "sine"; osc.frequency.value = 80;
      const lfo = this.ctx.createOscillator();
      lfo.type = "sine"; lfo.frequency.value = 0.25;
      const lfoG = this.ctx.createGain(); lfoG.gain.value = 4;
      lfo.connect(lfoG); lfoG.connect(osc.frequency);
      const g = this.ctx.createGain(); g.gain.value = 0.015;
      osc.connect(g); g.connect(this.ctx.destination);
      osc.start(); lfo.start();
      this.ambient = { osc, lfo, g };
    },

    stopAmbient() {
      if (this.ambient) {
        try { this.ambient.osc.stop(); this.ambient.lfo.stop(); } catch(e) {}
        this.ambient = null;
      }
    },

    play(freq, dur, vol) {
      if (!this.enabled || !this.ctx) return;
      const o = this.ctx.createOscillator();
      const g = this.ctx.createGain();
      o.type = "sine"; o.frequency.value = freq;
      g.gain.setValueAtTime(vol || 0.08, this.ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + (dur || 0.15));
      o.connect(g); g.connect(this.ctx.destination);
      o.start(); o.stop(this.ctx.currentTime + (dur || 0.15));
    },

    click()     { this.play(660, 0.08, 0.06); },
    boost()     { this.play(880, 0.12, 0.07); },
    catch()     { this.play(1200, 0.06, 0.05); },
    nightChime(){ this.play(330, 0.3, 0.04); setTimeout(() => this.play(220, 0.3, 0.04), 150); },
    dayChime()  { this.play(440, 0.3, 0.04); setTimeout(() => this.play(660, 0.3, 0.04), 150); },
    jiggle()    { this.play(1400, 0.05, 0.03); },
    paintSwap() { this.play(550, 0.15, 0.05); },

    celebrate() {
      if (!this.enabled || !this.ctx) return;
      [0, 0.1, 0.2, 0.35].forEach((d, i) => {
        setTimeout(() => this.play(400 + i * 200, 0.25, 0.07), d * 1000);
      });
    }
  };

  /* ═══════════════════════════════════════════
     3D MOUSE PARALLAX
     ═══════════════════════════════════════════ */
  const cafeRoom = document.getElementById("cafeRoom");
  const roomScene = document.getElementById("roomScene");
  let mouseX = 0, mouseY = 0, targetRX = 0, targetRY = 0, curRX = 0, curRY = 0;

  cafeRoom.addEventListener("mousemove", function(e) {
    const rect = cafeRoom.getBoundingClientRect();
    mouseX = (e.clientX - rect.left) / rect.width - 0.5;
    mouseY = (e.clientY - rect.top) / rect.height - 0.5;
    targetRY = mouseX * 5;
    targetRX = mouseY * -3;
  });

  cafeRoom.addEventListener("mouseleave", function() {
    targetRY = 0;
    targetRX = 0;
  });

  function animateParallax() {
    curRX += (targetRX - curRX) * 0.08;
    curRY += (targetRY - curRY) * 0.08;
    roomScene.style.transform =
      "rotateY(" + curRY.toFixed(2) + "deg) rotateX(" + curRX.toFixed(2) + "deg)";
    requestAnimationFrame(animateParallax);
  }
  requestAnimationFrame(animateParallax);

  /* ═══════════════════════════════════════════
     STATION HELPERS
     ═══════════════════════════════════════════ */
  const STATIONS = ["coffee", "cooking", "juice", "dessert"];
  const stnEl  = id => document.getElementById("stn" + id.charAt(0).toUpperCase() + id.slice(1));
  const charEl = id => document.getElementById("char" + id.charAt(0).toUpperCase() + id.slice(1));
  const bubEl  = id => document.getElementById("bub" + id.charAt(0).toUpperCase() + id.slice(1));

  function resolveActive(orderTypes) {
    activeStations.clear();
    orderTypes.forEach(t => {
      const s = TYPE_TO_STATION[t] || "coffee";
      activeStations.add(s);
    });
    STATIONS.forEach(s => {
      const el = stnEl(s);
      if (!el) return;
      if (activeStations.has(s)) {
        el.classList.add("active");
        el.classList.remove("inactive");
      } else {
        el.classList.remove("active");
        el.classList.add("inactive");
      }
    });
  }

  function setAnimation(status) {
    const isReady = status === "ready";
    STATIONS.forEach(s => {
      const c = charEl(s);
      if (!c) return;
      c.classList.remove("anim-idle", "anim-work", "anim-celebrate", "anim-wave", "anim-jump");
      if (!activeStations.has(s)) {
        c.classList.add("anim-idle");
        return;
      }
      if (isReady) c.classList.add("anim-celebrate");
      else if (status === "preparing" || status === "brewing") c.classList.add("anim-work");
      else c.classList.add("anim-idle");
    });
  }

  /* ═══════════════════════════════════════════
     DIALOGUE SYSTEM
     ═══════════════════════════════════════════ */
  let bubbleTimers = {};

  function showDialogue(station, pool) {
    const bub = bubEl(station);
    if (!bub) return;

    if (!pool) {
      const isActive = activeStations.has(station);
      const statusKey = (currentStatus === "placed" || currentStatus === "created") ? "idle" : currentStatus;
      if (!isActive) {
        pool = DIALOGUES.inactive.idle;
      } else {
        const dlg = DIALOGUES[station] || DIALOGUES.coffee;
        pool = dlg[statusKey] || dlg.idle;
      }
    }
    bub.textContent = pool[Math.floor(Math.random() * pool.length)];
    bub.classList.add("visible");

    clearTimeout(bubbleTimers[station]);
    bubbleTimers[station] = setTimeout(() => bub.classList.remove("visible"), 4000);
  }

  function cycleActiveBubbles() {
    STATIONS.forEach(s => {
      if (activeStations.has(s)) showDialogue(s);
    });
  }

  /* ═══════════════════════════════════════════
     EMOJI REACTION
     ═══════════════════════════════════════════ */
  function spawnEmojiReact(charElement, station) {
    const emojis = CHAR_EMOJIS[station] || ["✨"];
    const emoji = emojis[Math.floor(Math.random() * emojis.length)];
    const el = document.createElement("div");
    el.className = "emoji-react";
    el.textContent = emoji;
    el.style.left = (30 + Math.random() * 40) + "%";
    charElement.appendChild(el);
    setTimeout(() => el.remove(), 1000);
  }

  /* ═══════════════════════════════════════════
     PARTICLES
     ═══════════════════════════════════════════ */
  let particleEls = [];

  function spawnParticles(status) {
    clearParticles();
    if (status === "ready") { spawnSparkles(); return; }
    if (status !== "preparing" && status !== "brewing") return;

    const room = document.getElementById("cafeRoom");
    activeStations.forEach(s => {
      const zone = stnEl(s);
      if (!zone) return;
      const rect = zone.getBoundingClientRect();
      const roomRect = room.getBoundingClientRect();
      const cx = rect.left - roomRect.left + rect.width / 2;
      const bot = roomRect.height - (rect.top - roomRect.top) + 20;

      for (let i = 0; i < 4; i++) {
        const p = document.createElement("div");
        p.className = "particle " + (s === "cooking" ? "fire" : "steam");
        p.style.left = (cx + (Math.random() - 0.5) * 30) + "px";
        p.style.bottom = bot + "px";
        p.style.animationDelay = (Math.random() * 2) + "s";
        p.style.animationDuration = (1.5 + Math.random()) + "s";
        if (s === "juice") p.style.background = "rgba(100,220,100,0.5)";
        if (s === "dessert") p.style.background = "rgba(240,160,184,0.5)";
        room.appendChild(p);
        particleEls.push(p);
      }
    });
  }

  function spawnSparkles() {
    const room = document.getElementById("cafeRoom");
    const colors = ["#c49b63", "#FFD700", "#fff", "#f0a0b8", "#1a8a1a"];
    for (let i = 0; i < 20; i++) {
      const sp = document.createElement("div");
      sp.className = "sparkle";
      sp.style.left = (8 + Math.random() * 84) + "%";
      sp.style.bottom = (35 + Math.random() * 50) + "%";
      sp.style.animationDelay = (Math.random() * 1.5) + "s";
      sp.style.background = colors[Math.floor(Math.random() * colors.length)];
      room.appendChild(sp);
      particleEls.push(sp);
    }
  }

  function clearParticles() {
    particleEls.forEach(p => p.remove());
    particleEls = [];
  }

  /* ═══════════════════════════════════════════
     CONFETTI SYSTEM
     ═══════════════════════════════════════════ */
  function launchConfetti() {
    const room = document.getElementById("cafeRoom");
    const colors = ["#c49b63", "#FFD700", "#ff6060", "#60ff60", "#6060ff", "#f0a0b8", "#fff"];
    for (let i = 0; i < 50; i++) {
      const piece = document.createElement("div");
      piece.className = "confetti-piece";
      piece.style.left = (Math.random() * 100) + "%";
      piece.style.background = colors[Math.floor(Math.random() * colors.length)];
      piece.style.animationDuration = (1.5 + Math.random() * 2) + "s";
      piece.style.animationDelay = (Math.random() * 0.5) + "s";
      piece.style.width = (4 + Math.random() * 8) + "px";
      piece.style.height = (6 + Math.random() * 10) + "px";
      piece.style.borderRadius = Math.random() > 0.5 ? "50%" : "2px";
      room.appendChild(piece);
      setTimeout(() => piece.remove(), 4000);
    }
  }

  /* ═══════════════════════════════════════════
     COUNTER ITEMS (progressive appear)
     ═══════════════════════════════════════════ */
  let counterItemEls = [];

  function updateCounterItems(status) {
    const wrap = document.getElementById("counterItems");
    counterItemEls.forEach(el => el.remove());
    counterItemEls = [];

    if (status !== "brewing" && status !== "ready") return;

    const foods = [];
    activeStations.forEach(s => {
      const pool = COUNTER_FOOD[s] || ["☕"];
      foods.push(pool[Math.floor(Math.random() * pool.length)]);
    });

    foods.forEach((emoji, i) => {
      const el = document.createElement("div");
      el.className = "counter-item";
      el.textContent = emoji;
      wrap.appendChild(el);
      counterItemEls.push(el);
      setTimeout(() => el.classList.add("visible"), 200 + i * 300);
    });
  }

  /* ═══════════════════════════════════════════
     INGREDIENT CATCHING GAME
     ═══════════════════════════════════════════ */
  const ingredientGame = document.getElementById("ingredientGame");

  function startIngredientGame() {
    ingredientScore = 0;
    ingredientGame.classList.add("active");
    if (ingredientInterval) clearInterval(ingredientInterval);
    ingredientInterval = setInterval(spawnIngredient, 1800);
  }

  function stopIngredientGame() {
    ingredientGame.classList.remove("active");
    if (ingredientInterval) { clearInterval(ingredientInterval); ingredientInterval = null; }
    ingredientGame.innerHTML = "";
  }

  function spawnIngredient() {
    if (currentStatus !== "preparing" && currentStatus !== "brewing") {
      stopIngredientGame();
      return;
    }

    const stationArr = Array.from(activeStations);
    const station = stationArr[Math.floor(Math.random() * stationArr.length)];
    const pool = INGREDIENTS[station] || INGREDIENTS.coffee;
    const emoji = pool[Math.floor(Math.random() * pool.length)];

    const el = document.createElement("div");
    el.className = "floating-ingredient";
    el.textContent = emoji;
    el.style.left = (10 + Math.random() * 75) + "%";
    el.style.animationDuration = (3 + Math.random() * 2) + "s";

    el.addEventListener("click", function(e) {
      e.stopPropagation();
      ingredientScore++;
      Sound.catch();

      const burst = document.createElement("div");
      burst.className = "catch-effect";
      burst.textContent = "✨";
      burst.style.left = el.style.left;
      burst.style.top = el.offsetTop + "px";
      ingredientGame.appendChild(burst);
      setTimeout(() => burst.remove(), 400);

      const score = document.createElement("div");
      score.className = "catch-score";
      score.textContent = "+" + ingredientScore;
      score.style.left = el.style.left;
      score.style.top = el.offsetTop + "px";
      ingredientGame.appendChild(score);
      setTimeout(() => score.remove(), 700);

      el.remove();

      if (ingredientScore % 5 === 0 && boostCount < BOOST_MAX) {
        boostCount = Math.min(boostCount + 2, BOOST_MAX);
        boostFill.style.width = (boostCount / BOOST_MAX * 100) + "%";
        boostMsg.textContent = "Catching boost! +" + ingredientScore + " ingredients! 🎯";
      }
    });

    ingredientGame.appendChild(el);
    setTimeout(() => { if (el.parentNode) el.remove(); }, 5500);
  }

  /* ═══════════════════════════════════════════
     TAP BURST
     ═══════════════════════════════════════════ */
  function tapBurst(x, y) {
    const room = document.getElementById("cafeRoom");
    const ring = document.createElement("div");
    ring.className = "tap-ring";
    ring.style.left = x + "px"; ring.style.top = y + "px";
    room.appendChild(ring);
    setTimeout(() => ring.remove(), 500);

    const txt = document.createElement("div");
    txt.className = "float-text";
    txt.textContent = "+1 ⚡";
    txt.style.left = x + "px"; txt.style.top = (y - 20) + "px";
    room.appendChild(txt);
    setTimeout(() => txt.remove(), 800);
  }

  /* ═══════════════════════════════════════════
     PROGRESS BAR
     ═══════════════════════════════════════════ */
  function statusIndex(s) {
    return { placed: 0, created: 0, preparing: 1, brewing: 2, ready: 3 }[s] ?? 0;
  }

  function updateBar(s) {
    const idx = statusIndex(s);
    const steps = document.querySelectorAll(".track-step");
    steps.forEach((el, i) => {
      el.classList.remove("active", "done");
      if (i < idx) el.classList.add("done");
      else if (i === idx) el.classList.add("active");
    });
    const bar = document.getElementById("trackBar");
    const fill = document.getElementById("trackFill");
    const maxW = bar.offsetWidth - 48;
    const pct = idx === 0 ? 0 : idx / (steps.length - 1);
    fill.style.width = (maxW * pct) + "px";
  }

  /* ═══════════════════════════════════════════
     ITEMS RENDER
     ═══════════════════════════════════════════ */
  function renderItems(items) {
    const wrap = document.getElementById("cafeItems");
    wrap.innerHTML = "";
    items.forEach(it => {
      const t = (it.type || "coffee").toLowerCase().trim();
      const col = DOT_COLORS[t] || "#c49b63";
      const chip = document.createElement("div");
      chip.className = "cafe-chip";
      chip.innerHTML = '<span class="dot" style="background:' + col + '"></span>' +
        it.name + ' &times; ' + it.quantity;
      wrap.appendChild(chip);
    });
  }

  /* ═══════════════════════════════════════════
     GAMIFICATION
     ═══════════════════════════════════════════ */
  const helpZone = document.getElementById("helpZone");
  const helpBtn  = document.getElementById("helpBtn");
  const boostFill = document.getElementById("boostFill");
  const boostMsg  = document.getElementById("boostMsg");
  const BOOST_MSGS = [
    "Nice! Keep going! ⚡", "You're helping! 🔥", "Faster! Faster! 💨",
    "Chef appreciates it! 👨‍🍳", "Almost there! ✨", "The kitchen loves you! ❤️"
  ];

  helpBtn.addEventListener("click", function(e) {
    if (currentStatus === "ready") return;
    boostCount = Math.min(boostCount + 1, BOOST_MAX);
    boostFill.style.width = (boostCount / BOOST_MAX * 100) + "%";
    Sound.boost();

    const roomRect = document.getElementById("cafeRoom").getBoundingClientRect();
    const btnRect = helpBtn.getBoundingClientRect();
    tapBurst(
      btnRect.left - roomRect.left + btnRect.width / 2,
      btnRect.top - roomRect.top
    );

    if (boostCount >= BOOST_MAX) {
      boostMsg.textContent = "BOOST COMPLETE! ⚡ Kitchen speed +100%!";
      boostMsg.style.color = "#FFD700";
      helpBtn.textContent = "🎉 Kitchen boosted!";
      helpBtn.style.pointerEvents = "none";
    } else {
      boostMsg.textContent = BOOST_MSGS[Math.floor(Math.random() * BOOST_MSGS.length)];
      boostMsg.style.color = "#c49b63";
    }
  });

  function updateHelp(status) {
    const show = status === "preparing" || status === "brewing";
    helpZone.classList.toggle("show", show);
    if (!show) {
      boostCount = 0;
      boostFill.style.width = "0%";
      boostMsg.textContent = "";
      helpBtn.textContent = "🤚 Tap to help the kitchen!";
      helpBtn.style.pointerEvents = "";
    }
  }

  /* ═══════════════════════════════════════════
     DAY / NIGHT TOGGLE
     ═══════════════════════════════════════════ */
  const dnToggle = document.getElementById("dnToggle");
  dnToggle.addEventListener("click", function() {
    isNight = !isNight;
    cafeRoom.classList.toggle("night", isNight);
    dnToggle.textContent = isNight ? "🌙" : "☀️";
    if (isNight) Sound.nightChime(); else Sound.dayChime();
  });

  /* ═══════════════════════════════════════════
     PAINTING INTERACTION
     ═══════════════════════════════════════════ */
  const painting = document.getElementById("roomPainting");
  painting.addEventListener("click", function() {
    paintingIdx = (paintingIdx + 1) % PAINTING_STYLES.length;
    painting.classList.add("swapping");
    Sound.paintSwap();
    setTimeout(() => {
      painting.style.background = PAINTING_STYLES[paintingIdx];
      painting.classList.remove("swapping");
    }, 250);
  });

  /* ═══════════════════════════════════════════
     LANTERN INTERACTION + DISCO EASTER EGG
     ═══════════════════════════════════════════ */
  document.querySelectorAll(".room-lantern").forEach(lant => {
    lant.addEventListener("click", function(e) {
      e.stopPropagation();
      lant.classList.toggle("dim");
      Sound.jiggle();

      discoClicks++;
      clearTimeout(discoTimer);
      discoTimer = setTimeout(() => { discoClicks = 0; }, 1500);

      if (discoClicks >= 5) {
        discoMode = !discoMode;
        cafeRoom.classList.toggle("disco", discoMode);
        discoClicks = 0;
        if (discoMode) {
          document.querySelectorAll(".room-lantern").forEach(l => l.classList.remove("dim"));
        }
      }
    });
  });

  /* ═══════════════════════════════════════════
     SHELF ITEM INTERACTION
     ═══════════════════════════════════════════ */
  document.querySelectorAll(".shelf-item").forEach(item => {
    item.addEventListener("click", function(e) {
      e.stopPropagation();
      item.classList.remove("jiggle");
      void item.offsetWidth;
      item.classList.add("jiggle");
      Sound.jiggle();
      setTimeout(() => item.classList.remove("jiggle"), 500);
    });
  });

  /* ═══════════════════════════════════════════
     WINDOW INTERACTION (also toggles day/night)
     ═══════════════════════════════════════════ */
  document.querySelectorAll(".room-window").forEach(win => {
    win.addEventListener("click", function(e) {
      e.stopPropagation();
      isNight = !isNight;
      cafeRoom.classList.toggle("night", isNight);
      dnToggle.textContent = isNight ? "🌙" : "☀️";
      if (isNight) Sound.nightChime(); else Sound.dayChime();
    });
  });

  /* ═══════════════════════════════════════════
     CHARACTER CLICK INTERACTIONS
     ═══════════════════════════════════════════ */
  STATIONS.forEach(s => {
    charClickCount[s] = 0;
    const zone = stnEl(s);
    if (!zone) return;

    zone.addEventListener("click", function(e) {
      Sound.click();
      charClickCount[s]++;

      const c = charEl(s);
      if (!c) return;

      spawnEmojiReact(c, s);

      const dlg = DIALOGUES[s] || DIALOGUES.coffee;
      if (dlg.tap && charClickCount[s] >= 2) {
        showDialogue(s, dlg.tap);
      } else {
        showDialogue(s);
      }

      c.classList.remove("anim-idle", "anim-work", "anim-celebrate", "anim-wave", "anim-jump");

      if (charClickCount[s] >= 4) {
        c.classList.add("anim-jump");
        charClickCount[s] = 0;
      } else if (charClickCount[s] >= 2) {
        c.classList.add("anim-wave");
      } else {
        c.style.transition = "transform 100ms ease";
        c.style.transform = "scale(1.2)";
        setTimeout(() => { c.style.transform = ""; c.style.transition = ""; }, 150);
        return;
      }

      setTimeout(() => {
        c.classList.remove("anim-wave", "anim-jump");
        setAnimation(currentStatus);
      }, 800);

      const roomRect = cafeRoom.getBoundingClientRect();
      tapBurst(e.clientX - roomRect.left, e.clientY - roomRect.top);
    });

    let lastTouchTime = 0;
    zone.addEventListener("touchstart", function() {
      const now = Date.now();
      if (now - lastTouchTime < 300) {
        charClickCount[s] = 4;
      }
      lastTouchTime = now;
    }, { passive: true });
  });

  /* ═══════════════════════════════════════════
     MAIN STATUS UPDATE
     ═══════════════════════════════════════════ */
  function applyStatus(s) {
    if (s === currentStatus) return;
    const prev = currentStatus;
    currentStatus = s;
    updateBar(s);
    setAnimation(s);
    spawnParticles(s);
    updateHelp(s);
    updateCounterItems(s);
    document.getElementById("statusText").textContent = STATUS_LABELS[s] || s;
    document.getElementById("statusSub").textContent = STATUS_SUBS[s] || "";
    cycleActiveBubbles();

    if (s === "preparing" || s === "brewing") {
      startIngredientGame();
    } else {
      stopIngredientGame();
    }

    if (s === "ready") {
      Sound.celebrate();
      launchConfetti();
      setTimeout(launchConfetti, 1200);
    }
  }

  /* ═══════════════════════════════════════════
     POLLING
     ═══════════════════════════════════════════ */
  function poll() {
    fetch(API)
      .then(r => r.json())
      .then(data => {
        if (data.error) return;
        if (data.item_types) resolveActive(data.item_types);
        if (data.items) renderItems(data.items);
        applyStatus(data.status || "placed");
        if (data.status !== "ready") setTimeout(poll, 3000);
      })
      .catch(() => setTimeout(poll, 5000));
  }

  /* ═══════════════════════════════════════════
     SOUND TOGGLE
     ═══════════════════════════════════════════ */
  document.getElementById("soundToggle").addEventListener("click", () => Sound.toggle());

  /* ═══════════════════════════════════════════
     INIT
     ═══════════════════════════════════════════ */
  resolveActive(INIT_TYPES);
  renderItems(INIT_ITEMS);
  currentStatus = "";
  applyStatus(INIT_STATUS);

  setInterval(cycleActiveBubbles, 5000);
  setTimeout(poll, 3000);

  STATIONS.forEach(s => { charClickCount[s] = 0; });
})();
</script>

<?php require_once __DIR__ . "/_footer.php"; ?>
