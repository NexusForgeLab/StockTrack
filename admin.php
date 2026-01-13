<?php
require_once __DIR__ . '/app/layout.php';
$admin = require_admin();
$pdo = db();
$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $item_code = strtoupper(trim($_POST['item_code'] ?? ''));
  $item_name = trim($_POST['item_name'] ?? '');
  $add_qty = (int)($_POST['add_qty'] ?? 0);
  $note = trim($_POST['note'] ?? '');
  if ($item_code === '' || $item_name === '' || $add_qty <= 0) $err = "Item code, name, and quantity (>0) are required.";
  else {
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare("SELECT * FROM items WHERE item_code=?");
      $st->execute([$item_code]);
      $it = $st->fetch();
      if ($it) {
        $newQty = (int)$it['qty'] + $add_qty;
        $pdo->prepare("UPDATE items SET item_name=?, qty=?, updated_at=datetime('now') WHERE id=?")
            ->execute([$item_name, $newQty, (int)$it['id']]);
        $itemId = (int)$it['id'];
      } else {
        $pdo->prepare("INSERT INTO items(item_code,item_name,qty) VALUES(?,?,?)")
            ->execute([$item_code, $item_name, $add_qty]);
        $itemId = (int)$pdo->lastInsertId();
        $newQty = $add_qty;
      }
      $pdo->prepare("INSERT INTO transactions(user_id,action,item_id,delta,qty_after,note,ip) VALUES(?,?,?,?,?,?,?)")
          ->execute([$admin['id'],'ADD',$itemId,$add_qty,$newQty,$note, $_SERVER['REMOTE_ADDR'] ?? '']);
      $pdo->commit();
      $ok = "Stock updated for $item_code (+$add_qty).";
    } catch (Exception $e) {
      $pdo->rollBack();
      $err = "Failed: " . $e->getMessage();
    }
  }
}
$items = $pdo->query("SELECT * FROM items ORDER BY updated_at DESC LIMIT 100")->fetchAll();
render_header('Admin Stock Entry', $admin);
?>
<div class="card">
  <h1>Admin Stock Entry</h1>
  <div class="muted">Add new item or increment existing stock (admin only).</div>
</div>
<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <h2>Add / Increment Stock</h2>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <div class="col-3">
      <div class="muted">Item Code *</div>
      <input name="item_code" placeholder="ITEM-001" required />
    </div>
    <div class="col-5">
      <div class="muted">Item Name *</div>
      <input name="item_name" placeholder="HDMI Cable" required />
    </div>
    <div class="col-2">
      <div class="muted">Add Qty *</div>
      <input type="number" name="add_qty" min="1" required />
    </div>
    <div class="col-2">
      <div class="muted">Note</div>
      <input name="note" placeholder="optional" />
    </div>
    <div class="col-12">
      <button class="btn" type="submit">Save</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Recent Items</h2>
  <table>
    <thead><tr><th>Code</th><th>Name</th><th>Qty</th><th>Updated</th></tr></thead>
    <tbody>
    <?php foreach($items as $it): ?>
      <tr>
        <td><span class="pill"><?php echo h($it['item_code']); ?></span></td>
        <td><?php echo h($it['item_name']); ?></td>
        <td><?php echo (int)$it['qty']; ?></td>
        <td class="muted"><?php echo h($it['updated_at']); ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
