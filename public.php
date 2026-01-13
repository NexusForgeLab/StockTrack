<?php
require_once __DIR__ . '/app/layout.php';
$pdo = db();
$user = current_user();
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $st = $pdo->prepare("SELECT * FROM items WHERE item_name LIKE ? OR item_code LIKE ? ORDER BY item_name ASC LIMIT 200");
  $like = '%'.$q.'%';
  $st->execute([$like, $like]);
  $items = $st->fetchAll();
} else {
  $items = $pdo->query("SELECT * FROM items ORDER BY item_name ASC LIMIT 200")->fetchAll();
}
render_header('Public Stock Check', $user);
?>
<div class="card">
  <h1>Public Stock Check</h1>
  <div class="muted">Anyone can check if an item is in stock (no login required).</div>
</div>

<div class="card">
  <form method="get" class="grid">
    <div class="col-10">
      <div class="muted">Search</div>
      <input name="q" value="<?php echo h($q); ?>" placeholder="Type item name or code" />
    </div>
    <div class="col-2" style="display:flex;align-items:flex-end">
      <button class="btn" type="submit">Go</button>
    </div>
  </form>
</div>

<div class="card">
  <h2>Items</h2>
  <table>
    <thead><tr><th>Item Code</th><th>Name</th><th>Qty</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($items as $it):
      $qty = (int)$it['qty']; ?>
      <tr>
        <td><span class="pill"><?php echo h($it['item_code']); ?></span></td>
        <td><?php echo h($it['item_name']); ?></td>
        <td><?php echo $qty; ?></td>
        <td><?php echo $qty>0 ? "<span class='good'>In Stock</span>" : "<span class='bad'>Out of Stock</span>"; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
