<?php
require_once __DIR__ . '/app/layout.php';
$admin = require_admin();
$pdo = db();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['csv_file']['tmp_name'];
        
        if (($handle = fopen($tmp, "r")) !== FALSE) {
            $pdo->beginTransaction();
            try {
                fgetcsv($handle); // Skip Header
                
                $count = 0;
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $id   = (int)($data[0] ?? 0);
                    $code = strtoupper(trim($data[1] ?? 'ITEM-UNKNOWN'));
                    $name = trim($data[2] ?? 'Unknown Item');
                    
                    // --- CONDITION LOGIC ---
                    // Trim input. If empty, default to 'NEW'.
                    $condRaw = trim($data[3] ?? '');
                    $cond = ($condRaw === '') ? 'NEW' : strtoupper($condRaw);
                    // -----------------------
                    
                    $qty  = (int)($data[4] ?? 0);
                    if($qty < 0) $qty = 0;

                    $targetId = 0;
                    $oldQty = 0;

                    // 1. Resolve Target Item
                    $existing = null;
                    if($id > 0) {
                        $st = $pdo->prepare("SELECT id, qty FROM items WHERE id=?");
                        $st->execute([$id]);
                        $existing = $st->fetch();
                    }
                    if(!$existing) {
                        $st = $pdo->prepare("SELECT id, qty FROM items WHERE item_code=? AND condition=?");
                        $st->execute([$code, $cond]);
                        $existing = $st->fetch();
                    }

                    // 2. Perform DB Action
                    if($existing) {
                        $targetId = (int)$existing['id'];
                        $oldQty = (int)$existing['qty'];
                        $pdo->prepare("UPDATE items SET item_code=?, item_name=?, condition=?, qty=?, is_deleted=0, updated_at=datetime('now') WHERE id=?")
                            ->execute([$code, $name, $cond, $qty, $targetId]);
                    } else {
                        $pdo->prepare("INSERT INTO items (item_code, item_name, condition, qty) VALUES (?,?,?,?)")
                            ->execute([$code, $name, $cond, $qty]);
                        $targetId = (int)$pdo->lastInsertId();
                        $oldQty = 0;
                    }

                    // 3. Log Transaction
                    $delta = $qty - $oldQty;
                    if($delta !== 0 || !$existing) {
                        $pdo->prepare("INSERT INTO transactions(user_id, action, item_id, delta, qty_after, note, ip) VALUES(?,?,?,?,?,?,?)")
                            ->execute([$admin['id'], 'ADJUST', $targetId, $delta, $qty, "Import CSV", $_SERVER['REMOTE_ADDR'] ?? '']);
                    }
                    $count++;
                }
                fclose($handle);
                $pdo->commit();
                $msg = "<div class='card good'>Success! Processed $count items.</div>";
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg = "<div class='card bad'>Error: " . h($e->getMessage()) . "</div>";
            }
        }
    } else {
        $msg = "<div class='card bad'>Upload failed. Please try again.</div>";
    }
}

render_header('Import Stock', $admin);
?>

<div class="card">
    <h1>Import Stock (Excel/CSV)</h1>
    <div class="muted">
        <b>Instructions:</b><br>
        1. Use the "Export" button to get a template.<br>
        2. Modify Quantity or Name in Excel.<br>
        3. Save as <b>CSV (Comma delimited)</b> and upload here.<br>
        <em>Note: Empty condition column will be treated as <b>NEW</b>.</em>
    </div>
    
    <div style="margin-top:15px; display:flex; gap:10px;">
        <a class="btn" href="/admin.php">&larr; Back to Admin</a>
        <a class="btn" href="/export.php" style="background:#28a745; border-color:#28a745; color:white;">⬇️ Download Current Data</a>
    </div>
</div>

<?php echo $msg; ?>

<div class="card">
    <h2>Upload File</h2>
    <form method="post" enctype="multipart/form-data" class="grid">
        <div class="col-12">
            <input type="file" name="csv_file" accept=".csv" required style="border:2px dashed #ccc; padding:20px; background:#f9f9f9; width:100%;">
        </div>
        <div class="col-12">
            <button class="btn" type="submit">Upload & Import</button>
        </div>
    </form>
</div>
<?php render_footer(); ?>