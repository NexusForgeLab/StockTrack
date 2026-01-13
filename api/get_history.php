<?php
require_once __DIR__ . '/../app/auth.php';
$user = require_login();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

// 1. Get Item Details
$st = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$st->execute([$id]);
$item = $st->fetch();

if(!$item) {
    echo "<p class='bad'>Item not found (ID: $id). It may have been deleted.</p>";
    // Fallback logic for deleted items...
    $logs = $pdo->prepare("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE t.item_id = ? ORDER BY t.created_at DESC LIMIT 500");
    $logs->execute([$id]);
    $logs = $logs->fetchAll();
    if(!$logs) { echo "No history found."; exit; }
} else {
    $rel = $pdo->prepare("SELECT condition, qty FROM items WHERE item_code = ? ORDER BY condition ASC");
    $rel->execute([$item['item_code']]);
    $related = $rel->fetchAll();

    $hist = $pdo->prepare("SELECT t.*, u.username FROM transactions t LEFT JOIN users u ON t.user_id = u.id WHERE t.item_id = ? ORDER BY t.created_at DESC LIMIT 500");
    $hist->execute([$id]);
    $logs = $hist->fetchAll();
}
?>

<?php if($item): ?>
<div style="background:#fff; border:1px solid #ddd; padding:15px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
    <div style="margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
        <strong style="font-size:1.1em"><?php echo h($item['item_name']); ?></strong> 
        <span class="muted">(<?php echo h($item['item_code']); ?>)</span>
        <br>
        <span class="pill" style="margin-top:5px; display:inline-block">
            <?php echo h($item['condition']); ?>: <b><?php echo (int)$item['qty']; ?></b> in stock
        </span>
    </div>

    <strong style="font-size:0.9em; text-transform:uppercase; color:#888;">Quick Actions</strong>
    <div style="display:flex; gap:10px; margin-top:8px; align-items:center; flex-wrap:wrap;">
        <input type="number" id="modalQty" placeholder="Qty" min="1" value="1" style="width:70px; padding:8px; border:1px solid #ccc; border-radius:4px;">
        <input type="text" id="modalNote" placeholder="Note (optional)" style="flex-grow:1; padding:8px; border:1px solid #ccc; border-radius:4px; min-width:150px;">
        
        <?php if($user['role'] === 'admin'): ?>
            <button onclick="doTransact('IN', '<?php echo csrf_token(); ?>')" style="background:#28a745; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">+ IN</button>
        <?php endif; ?>
        
        <button onclick="doTransact('OUT', '<?php echo csrf_token(); ?>')" style="background:#dc3545; color:white; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; font-weight:bold;">- OUT</button>
    </div>
    <div id="modalMsg" style="margin-top:5px; font-size:0.85em; height:1.2em;"></div>
</div>

<div style="margin-bottom:15px;">
    <span class="muted">Other Conditions:</span>
    <div style="display:flex; gap:8px; margin-top:4px; flex-wrap:wrap;">
        <?php foreach($related as $r): 
            if($r['condition'] == $item['condition']) continue; // Skip current
            $c = $r['condition'];
            $bg = $c==='NEW'?'#d4edda':($c==='FAULTY'?'#f8d7da':'#fff3cd');
        ?>
        <span style="background:<?php echo $bg; ?>; padding:2px 8px; border-radius:4px; font-size:0.85em; border:1px solid #ddd;">
            <?php echo $c; ?>: <b><?php echo (int)$r['qty']; ?></b>
        </span>
        <?php endforeach; ?>
        <?php if(count($related) <= 1) echo "<span class='muted' style='font-style:italic'>None</span>"; ?>
    </div>
</div>
<?php endif; ?>

<h4>History Log</h4>
<div class="table-scroll">
<table style="width:100%; font-size:0.95em;">
    <thead><tr style="background:#f9f9f9"><th>Time</th><th>Action</th><th>Delta</th><th>User</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach($logs as $r): 
        $act = $r['action'] === 'ADD' ? 'IN' : ($r['action'] === 'TAKE' ? 'OUT' : $r['action']);
        $cls = $act === 'IN' ? 'pill-add' : ($act === 'OUT' ? 'pill-take' : 'pill-adjust');
    ?>
    <tr>
        <td class="muted"><?php echo substr($r['created_at'],5,11); ?> <small><?php echo substr($r['created_at'],11,5); ?></small></td>
        <td><span class="pill <?php echo $cls; ?>"><?php echo h($act); ?></span></td>
        <td style="font-weight:bold; color:<?php echo $r['delta']>0?'green':'red'; ?>"><?php echo ($r['delta']>0?'+':'').(int)$r['delta']; ?></td>
        <td><?php echo h($r['username'] ?? 'Unknown'); ?></td>
        <td class="muted"><?php echo h($r['note']); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>