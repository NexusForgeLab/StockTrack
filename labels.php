<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login(); // Any logged-in user can print
$pdo = db();

$items = [];

// Logic: Fetch items based on GET parameters
if (isset($_GET['all'])) {
    // 1. Fetch ALL items
    $stmt = $pdo->query("SELECT * FROM items WHERE is_deleted=0 ORDER BY item_code ASC");
    $items = $stmt->fetchAll();
} 
elseif (isset($_GET['ids'])) {
    // 2. Fetch Selected IDs (comma separated)
    $ids = explode(',', $_GET['ids']);
    $ids = array_map('intval', $ids);
    $ids = array_filter($ids); // Remove empty/zeros
    
    if(!empty($ids)) {
        $in  = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT * FROM items WHERE id IN ($in) ORDER BY item_code ASC");
        $stmt->execute($ids);
        $items = $stmt->fetchAll();
    }
} 
elseif (isset($_GET['id'])) {
    // 3. Fetch Single Item
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $res = $stmt->fetch();
    if ($res) $items[] = $res;
}

// Handle empty result
if (empty($items) && !isset($_GET['test'])) {
    render_header('Print Labels', $user);
    echo "<div class='card bad'>No items selected. <a href='/admin.php'>Go Back</a></div>";
    render_footer();
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Labels - StockTrack</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
            background: #f0f0f0; margin: 0; padding: 20px;
        }
        .no-print { 
            background: white; padding: 20px; border-radius: 8px; 
            box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn {
            background: #333; color: white; border: none; padding: 10px 20px; 
            border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 1rem;
        }
        .btn:hover { background: #555; }
        
        /* Grid Layout for Labels */
        .label-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, 2.6in); /* Adjust for your label paper width */
            gap: 10px; 
        }

        /* Individual Label Card */
        .label-card {
            background: white;
            width: 2.5in;   /* Standard Address Label Width */
            height: 1.2in;  /* Standard Address Label Height */
            padding: 10px;
            box-sizing: border-box;
            border: 1px dashed #ccc; /* Guide border for screen */
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            page-break-inside: avoid;
            position: relative;
        }
        
        .info { flex-grow: 1; padding-right: 10px; overflow: hidden; }
        .code { font-weight: 700; font-size: 1.1em; color: #000; margin-bottom: 4px; }
        .name { font-size: 0.85em; color: #444; line-height: 1.2; max-height: 2.4em; overflow: hidden; }
        .meta { font-size: 0.7em; color: #888; margin-top: 4px; }
        
        /* QR Code Container */
        .qr-wrap { width: 64px; height: 64px; flex-shrink: 0; }
        
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .label-grid { display: block; } /* Allow natural flow */
            .label-card {
                border: none; /* Remove border for printing */
                outline: 1px dotted #eee; /* Optional light guide */
                float: left;
                margin: 0 10px 10px 0;
            }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div>
        <h1 style="margin:0; font-size:1.5rem;">Print Labels</h1>
        <div style="color:#666">Generating <?php echo count($items); ?> label(s)</div>
    </div>
    <div>
        <a href="/admin.php" class="btn" style="background:#ddd; color:#333">Cancel</a>
        <button onclick="window.print()" class="btn">🖨️ Print Now</button>
    </div>
</div>

<div class="label-grid">
    <?php foreach($items as $i => $item): ?>
    <div class="label-card">
        <div class="info">
            <div class="code"><?php echo htmlspecialchars($item['item_code']); ?></div>
            <div class="name"><?php echo htmlspecialchars($item['item_name']); ?></div>
            <div class="meta">Cond: <?php echo $item['condition']; ?></div>
        </div>
        <div class="qr-wrap" id="qr-<?php echo $i; ?>"></div>
        <script>
            // Generate QR Code immediately
            new QRCode(document.getElementById("qr-<?php echo $i; ?>"), {
                text: "<?php echo $item['item_code']; ?>", // Scanning this fills search box
                width: 64,
                height: 64,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.M
            });
        </script>
    </div>
    <?php endforeach; ?>
</div>

</body>
</html>
