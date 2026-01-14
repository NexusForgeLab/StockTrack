<?php
require_once __DIR__ . '/app/layout.php';
$admin = require_admin();
$pdo = db();
$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action_type = $_POST['action_type'] ?? '';

  // --- STOCK IN ---
  if ($action_type === 'stock_in') {
      $item_code = strtoupper(trim($_POST['item_code'] ?? ''));
      $item_name = trim($_POST['item_name'] ?? '');
      $add_qty = (int)($_POST['add_qty'] ?? 0);
      $min_qty = (int)($_POST['min_qty'] ?? 0);
      $note = trim($_POST['note'] ?? '');
      
      $is_repaired = isset($_POST['is_repaired']);
      $is_faulty   = isset($_POST['is_faulty']);
      $condition   = 'NEW';
      if ($is_repaired) $condition = 'REPAIRED';
      if ($is_faulty)   $condition = 'FAULTY';

      if ($item_code === '' || $item_name === '' || $add_qty <= 0) {
          $err = "Item code, name, and quantity (>0) are required.";
      } else {
          $pdo->beginTransaction();
          try {
              $st = $pdo->prepare("SELECT * FROM items WHERE item_code=? AND condition=?");
              $st->execute([$item_code, $condition]);
              $it = $st->fetch();
              
              if ($it) {
                  $newQty = (int)$it['qty'] + $add_qty;
                  $pdo->prepare("UPDATE items SET item_name=?, qty=?, min_qty=?, is_deleted=0, updated_at=datetime('now') WHERE id=?")
                      ->execute([$item_name, $newQty, $min_qty, (int)$it['id']]);
                  $itemId = (int)$it['id'];
              } else {
                  $pdo->prepare("INSERT INTO items(item_code,item_name,qty,min_qty,condition) VALUES(?,?,?,?,?)")
                      ->execute([$item_code, $item_name, $add_qty, $min_qty, $condition]);
                  $itemId = (int)$pdo->lastInsertId();
                  $newQty = $add_qty;
              }
              
              $pdo->prepare("INSERT INTO transactions(user_id,action,item_id,delta,qty_after,note,ip) VALUES(?,?,?,?,?,?,?)")
                  ->execute([$admin['id'],'ADD',$itemId,$add_qty,$newQty,$note, $_SERVER['REMOTE_ADDR'] ?? '']);
              $pdo->commit();
              $ok = "Stock IN: $item_name ($condition) +$add_qty";
          } catch (Exception $e) {
              $pdo->rollBack();
              $err = "Failed: " . $e->getMessage();
          }
      }
  } 
  // --- MODIFY ---
  elseif ($action_type === 'modify_item') {
      $id = (int)($_POST['item_id'] ?? 0);
      $code = strtoupper(trim($_POST['item_code'] ?? ''));
      $name = trim($_POST['item_name'] ?? '');
      if($code && $name) {
          try {
              $pdo->prepare("UPDATE items SET item_code=?, item_name=?, updated_at=datetime('now') WHERE id=?")
                  ->execute([$code, $name, $id]);
              $ok = "Item modified.";
          } catch(Exception $e) { $err = "Update failed (Duplicate code+condition?)."; }
      }
  }
  // --- DELETE SINGLE ---
  elseif ($action_type === 'delete_item') {
      $id = (int)($_POST['item_id'] ?? 0);
      $pdo->prepare("UPDATE items SET is_deleted=1 WHERE id=?")->execute([$id]);
      $ok = "Item deleted.";
  }
  // --- RESTORE SINGLE ---
  elseif ($action_type === 'restore_item') {
      $id = (int)($_POST['item_id'] ?? 0);
      $pdo->prepare("UPDATE items SET is_deleted=0 WHERE id=?")->execute([$id]);
      $ok = "Item restored.";
  }
  // --- DELETE ALL ---
  elseif ($action_type === 'delete_all') {
      $pdo->exec("UPDATE items SET is_deleted=1");
      $ok = "All items have been deleted.";
  }
}

// Logic for Showing Deleted
$showDeleted = isset($_GET['show_deleted']) && $_GET['show_deleted'] == '1';
$sql = "SELECT * FROM items WHERE is_deleted=" . ($showDeleted ? '1' : '0') . " ORDER BY updated_at DESC LIMIT 50";
$items = $pdo->query($sql)->fetchAll();

render_header('Admin Stock IN', $admin);
?>

<div class="card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
  <div>
      <h1>Stock IN (Admin)</h1>
      <div class="muted">Add new items or manage stock.</div>
  </div>
  <div style="display:flex; gap:10px; align-items:center;">
      <form method="post" onsubmit="return confirm('⚠️ WARNING: This will delete ALL items from the inventory! Are you sure?');" style="margin:0;">
          <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
          <input type="hidden" name="action_type" value="delete_all"/>
          <button class="btn btn-del" type="submit">🗑️ Delete All Items</button>
      </form>
      
      <a class="btn" href="/export.php">⬇️ Export</a>
      <a class="btn" href="/import.php">⬆️ Import</a>
      <a class="btn" href="/backup_db.php" download>💾 DB Backup</a>
  </div>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <h2>Stock Entry</h2>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <input type="hidden" name="action_type" value="stock_in"/>
    
    <div class="col-3">
      <div class="muted">Item Code *</div>
      <input name="item_code" placeholder="ITEM-001" required />
    </div>
    <div class="col-5">
      <div class="muted">Item Name *</div>
      <input name="item_name" placeholder="HDMI Cable" required />
    </div>
    <div class="col-2">
      <div class="muted">IN Qty *</div>
      <input type="number" name="add_qty" min="1" required />
    </div>
    <div class="col-2">
      <div class="muted">Min Qty</div>
      <input type="number" name="min_qty" value="0" min="0" />
    </div>
    
    <div class="col-12" style="display:flex; gap:20px; align-items:center; margin-top:5px;">
        <label style="display:flex;align-items:center;gap:5px;cursor:pointer">
            <input type="checkbox" name="is_repaired" id="chkRep" onchange="toggleChk('chkRep')"> 
            🛠️ Repaired
        </label>
        <label style="display:flex;align-items:center;gap:5px;cursor:pointer">
            <input type="checkbox" name="is_faulty" id="chkFlt" onchange="toggleChk('chkFlt')"> 
            ⚠️ Faulty
        </label>
        <span class="muted" style="font-size:0.85em; margin-left:10px;">(Leave both unchecked for NEW)</span>
    </div>

    <div class="col-12">
      <div class="muted">Note</div>
      <input name="note" placeholder="optional" />
    </div>
    <div class="col-12">
      <button class="btn" type="submit" style="background:var(--success);border-color:var(--success);">Stock IN</button>
    </div>
  </form>
</div>

<div class="card">
  <div style="display:flex;justify-content:space-between;align-items:center; margin-bottom:10px;">
      <h2>Recent Items</h2>
      <div style="display:flex; gap:10px; align-items:center;">
          <button class="btn" type="button" onclick="printSelected()">🏷️ Print Selected</button>
          <a class="btn" href="labels.php?all=1" target="_blank">🖨️ Print All</a>
          
          <form method="get" style="margin:0; margin-left:10px;">
              <label class="muted" style="cursor:pointer">
                  <input type="checkbox" name="show_deleted" value="1" <?php echo $showDeleted?'checked':''; ?> onchange="this.form.submit()"> 
                  Show Deleted
              </label>
          </form>
      </div>
  </div>
  <table>
    <thead>
        <tr>
            <th style="width:30px"><input type="checkbox" onclick="toggleAll(this)"></th>
            <th>Code</th>
            <th>Name</th>
            <th>Cond</th>
            <th>Qty</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($items as $it): 
        $condClass = $it['condition']=='NEW'?'good':($it['condition']=='FAULTY'?'bad':'muted');
    ?>
      <tr>
        <td><input type="checkbox" class="sel-item" value="<?php echo $it['id']; ?>"></td>
        <form method="post" onsubmit="return confirm('Modify this item?');">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
            <input type="hidden" name="action_type" value="modify_item"/>
            <input type="hidden" name="item_id" value="<?php echo $it['id']; ?>"/>
            <td><input name="item_code" value="<?php echo h($it['item_code']); ?>" class="bare-input" style="width:100px"/></td>
            <td><input name="item_name" value="<?php echo h($it['item_name']); ?>" class="bare-input"/></td>
            <td><span class="<?php echo $condClass; ?>" style="font-size:0.85em"><?php echo h($it['condition']); ?></span></td>
            <td><?php echo (int)$it['qty']; ?></td>
            <td style="display:flex; gap:5px;">
                <?php if($showDeleted): ?>
                   <button class="btn" type="submit" formaction="?" name="action_type" value="restore_item" title="Restore">♻️</button>
                <?php else: ?>
                   <button class="btn" type="submit" title="Save">💾</button>
                   <a class="btn" href="labels.php?id=<?php echo $it['id']; ?>" target="_blank" title="Print Label" style="display:flex;align-items:center;justify-content:center;">🏷️</a>
                   <button class="btn btn-del" type="button" onclick="deleteItem(<?php echo $it['id']; ?>)" title="Delete">🗑️</button>
                <?php endif; ?>
            </td>
        </form>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form id="delForm" method="post"><input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/><input type="hidden" name="action_type" value="delete_item"/><input type="hidden" name="item_id" id="delId"/></form>

<script>
function toggleChk(id) {
    if(document.getElementById(id).checked) {
        if(id === 'chkRep') document.getElementById('chkFlt').checked = false;
        if(id === 'chkFlt') document.getElementById('chkRep').checked = false;
    }
}
function deleteItem(id) { if(confirm('Delete item?')) { document.getElementById('delId').value=id; document.getElementById('delForm').submit(); } }

// Label Print Selection Logic
function toggleAll(master) {
    document.querySelectorAll('.sel-item').forEach(c => c.checked = master.checked);
}
function printSelected() {
    let ids = [];
    document.querySelectorAll('.sel-item:checked').forEach(c => ids.push(c.value));
    if(ids.length === 0) {
        alert("Please select at least one item.");
        return;
    }
    window.open('labels.php?ids=' + ids.join(','), '_blank');
}
</script>
<style>.bare-input{border:none;background:transparent;width:100%;}.bare-input:focus{background:#fff;border:1px solid #ccc;}</style>
<?php render_footer(); ?>