<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();
$err=''; $ok='';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $item_id = (int)($_POST['item_id'] ?? 0); // Get ID directly
  $take_qty = (int)($_POST['take_qty'] ?? 0);
  $note = trim($_POST['note'] ?? '');

  if ($item_id <= 0 || $take_qty <= 0) $err = "Valid item and quantity required.";
  else {
    $pdo->beginTransaction();
    try {
      // Select by ID
      $st = $pdo->prepare("SELECT * FROM items WHERE id=? AND is_deleted=0");
      $st->execute([$item_id]);
      $it = $st->fetch();
      
      if (!$it) throw new Exception("Item not found.");
      
      $cur = (int)$it['qty'];
      if ($cur < $take_qty) throw new Exception("Not enough stock. Available: $cur");
      
      $newQty = $cur - $take_qty;
      
      // Update
      $pdo->prepare("UPDATE items SET qty=?, updated_at=datetime('now') WHERE id=?")
          ->execute([$newQty, $item_id]);
      
      // Log
      $pdo->prepare("INSERT INTO transactions(user_id,action,item_id,delta,qty_after,note,ip) VALUES(?,?,?,?,?,?,?)")
          ->execute([$user['id'],'TAKE',$item_id,-$take_qty,$newQty,$note, $_SERVER['REMOTE_ADDR'] ?? '']);
      
      $pdo->commit();
      $itemStr = $it['item_name'] . " (" . $it['condition'] . ")";
      $ok = "OUT: $take_qty x $itemStr. Remaining: $newQty";
    } catch (Exception $e) {
      $pdo->rollBack();
      $err = $e->getMessage();
    }
  }
}
render_header('Stock OUT', $user);
?>

<div class="card">
  <h1>Stock OUT</h1>
  <div class="muted">Search item by name/code to record usage.</div>
</div>
<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    
    <div class="col-6">
      <div class="muted">Search Item (Name or Code)</div>
      <input type="hidden" name="item_id" id="itemId" /> 
      <input id="itemSearch" placeholder="Type to search..." autocomplete="off"/>
    </div>
    
    <div class="col-3">
      <div class="muted">Selected Code</div>
      <input id="itemCodeDisplay" readonly style="background:#f0f0f0; border-color:transparent;" />
    </div>

    <div class="col-3">
      <div class="muted">OUT Qty *</div>
      <input type="number" name="take_qty" min="1" required />
    </div>
    <div class="col-12">
      <div class="muted">Note</div>
      <input name="note" />
    </div>
    <div class="col-12">
      <button class="btn" type="submit" style="background:var(--danger);border-color:var(--danger);">Stock OUT</button>
    </div>
  </form>
</div>

<script src="/assets/app.js"></script>
<script>
// Use specific helper for ID
attachItemSuggest('itemSearch', 'itemId', 'itemCodeDisplay');
</script>
<?php render_footer(); ?>