<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();
$err=''; $ok='';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $item_code = strtoupper(trim($_POST['item_code'] ?? ''));
  $take_qty = (int)($_POST['take_qty'] ?? 0);
  $note = trim($_POST['note'] ?? '');
  if ($item_code === '' || $take_qty <= 0) $err = "Item code and quantity (>0) are required.";
  else {
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare("SELECT * FROM items WHERE item_code=?");
      $st->execute([$item_code]);
      $it = $st->fetch();
      if (!$it) throw new Exception("Item not found: $item_code");
      $cur = (int)$it['qty'];
      if ($cur < $take_qty) throw new Exception("Not enough stock. Available: $cur");
      $newQty = $cur - $take_qty;
      $pdo->prepare("UPDATE items SET qty=?, updated_at=datetime('now') WHERE id=?")
          ->execute([$newQty, (int)$it['id']]);
      $pdo->prepare("INSERT INTO transactions(user_id,action,item_id,delta,qty_after,note,ip) VALUES(?,?,?,?,?,?,?)")
          ->execute([$user['id'],'TAKE',(int)$it['id'],-$take_qty,$newQty,$note, $_SERVER['REMOTE_ADDR'] ?? '']);
      $pdo->commit();
      $ok = "Taken $take_qty from $item_code. Remaining: $newQty";
    } catch (Exception $e) {
      $pdo->rollBack();
      $err = $e->getMessage();
    }
  }
}
render_header('Take Item', $user);
?>
<div class="card">
  <h1>Take Item</h1>
  <div class="muted">Logged in users can decrement stock when an item is taken from the store.</div>
</div>
<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <h2>Decrement Stock</h2>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <div class="col-6">
      <div class="muted">Item Name (type to search)</div>
      <input id="itemName" placeholder="Start typing item name..." />
      <div class="muted" style="margin-top:6px">Choose suggestion to fill Item Code.</div>
    </div>
    <div class="col-3">
      <div class="muted">Item Code *</div>
      <input id="itemCode" name="item_code" placeholder="ITEM-001" required />
    </div>
    <div class="col-3">
      <div class="muted">Take Qty *</div>
      <input type="number" name="take_qty" min="1" required />
    </div>
    <div class="col-12">
      <div class="muted">Note (optional)</div>
      <input name="note" placeholder="e.g. issued to maintenance" />
    </div>
    <div class="col-12">
      <button class="btn" type="submit">Submit</button>
      <a class="btn" href="/public.php">Check Stock</a>
    </div>
  </form>
</div>
<script src="/assets/app.js"></script>
<script>attachItemSuggest('itemName','itemCode');</script>
<?php render_footer(); ?>
