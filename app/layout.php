<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function render_header(string $title, ?array $user): void { ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0"/>
  
  <title><?php echo h($title); ?></title>
  
  <link rel="manifest" href="/assets/manifest.json">
  <meta name="theme-color" content="#d9730d">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="StockTrack">
  
  <link rel="icon" type="image/png" href="/assets/logo.png">
  <link rel="apple-touch-icon" href="/assets/icon-192.png">

  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <link rel="stylesheet" href="/assets/style.css"/>
</head>
<body>
  <div class="topbar">
    <div class="brand">
      <a href="/" style="display:flex;align-items:center;gap:8px;">
        <img src="/assets/logo.png" alt="Logo" style="height:28px;width:auto;">
        Stock Track
      </a>
    </div>
    <div class="nav">
      <a class="btn" href="/public.php">Public Check</a>
      <?php if($user): ?>
        <button class="btn" onclick="startGlobalScanner()">📷 Scan</button>
        <a class="btn" href="/take.php">OUT</a> 
        <a class="btn" href="/audit.php">Audit</a>
        <?php if(($user['role'] ?? '') === 'admin'): ?>
          <a class="btn" href="/admin.php">IN (Admin)</a>
          <a class="btn" href="/users.php">Users</a>
        <?php endif; ?>
        <a class="btn" href="/logout.php">Logout</a>
      <?php else: ?>
        <a class="btn" href="/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="wrap">
<?php }

function render_footer(): void { ?>
  </div>
  
  <div id="globalScannerModal" class="modal">
    <div class="modal-content" style="max-width:500px; text-align:center;">
        <span class="close" onclick="stopGlobalScanner()">&times;</span>
        <h2>Scan Item</h2>
        <div id="globalReader" style="width:100%"></div>
        <p class="muted">Point camera at QR Code / Barcode</p>
    </div>
  </div>

  <div id="globalDetailModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeGlobalModal()">&times;</span>
        <h2 id="globalModalTitle">Details</h2>
        <div id="globalModalBody">Loading...</div>
    </div>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
<?php }