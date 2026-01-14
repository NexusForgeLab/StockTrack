<?php
require_once __DIR__ . '/app/layout.php';
$pdo = db();
$user = current_user();
$q = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM items WHERE is_deleted=0 ";
$args = [];

if ($q !== '') {
  $sql .= " AND (item_name LIKE ? OR item_code LIKE ?) ";
  $args[] = "%$q%"; $args[] = "%$q%";
}
$sql .= " ORDER BY item_name ASC LIMIT 200";

$items = $pdo->prepare($sql);
$items->execute($args);
$items = $items->fetchAll();

render_header('Public Stock Check', $user);
?>
<div class="card" style="display:flex; justify-content:space-between; align-items:center;">
  <div>
    <h1>Public Stock Check</h1>
    <div class="muted">Check availability.</div>
  </div>
  <button class="btn" onclick="window.print()">🖨️ Print List</button>
</div>

<div class="card">
  <form method="get" class="grid">
    <div class="col-10">
      <input name="q" value="<?php echo h($q); ?>" placeholder="Search item name or code..." />
    </div>
    <div class="col-2">
      <button class="btn" type="submit">Search</button>
    </div>
  </form>
</div>

<div class="card">
  <table>
    <thead><tr><th>Item Code</th><th>Name</th><th>Condition</th><th>Qty</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach($items as $it):
      $qty = (int)$it['qty']; 
      $min = (int)($it['min_qty'] ?? 0);
      
      // Determine Condition Label & Color
      $cond = $it['condition']; 
      $condLbl = $cond === 'NEW' ? 'NEW' : ($cond === 'REPAIRED' ? '🛠️ REP' : '⚠️ FLT');
      $condCls = $cond === 'NEW' ? 'pill' : ($cond === 'REPAIRED' ? 'pill pill-adjust' : 'pill pill-take');
      
      // Low Stock Logic
      $rowClass = ($qty <= $min && $qty > 0) ? 'row-low-stock' : '';
    ?>
      <tr class="<?php echo $rowClass; ?>">
        <td><span class="pill"><?php echo h($it['item_code']); ?></span></td>
        <td><?php echo h($it['item_name']); ?></td>
        <td><span class="<?php echo $condCls; ?>" style="font-size:0.85em"><?php echo $condLbl; ?></span></td>
        <td><?php echo $qty; ?></td>
        <td><?php echo $qty>0 ? "<span class='good'>In Stock</span>" : "<span class='bad'>Out of Stock</span>"; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>