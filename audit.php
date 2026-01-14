<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();

$view = $_GET['view'] ?? $_SESSION['user']['view_pref'] ?? 'SIMPLE';
$view = ($view === 'ALL') ? 'ALL' : 'SIMPLE';

$q = trim($_GET['q'] ?? '');
$cond = $_GET['condition'] ?? 'ALL';
$status = $_GET['status'] ?? 'ALL';
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$args = [];
$where = [];

if($cond !== 'ALL') { $where[] = "i.condition = ?"; $args[] = $cond; }
if($status === 'IN_STOCK') { $where[] = "i.qty > 0"; } 
elseif($status === 'OUT_OF_STOCK') { $where[] = "i.qty <= 0"; }
if($q) {
    $where[] = "(i.id = ? OR i.item_name LIKE ? OR i.item_code LIKE ?)";
    $args[] = $q; $args[] = "%$q%"; $args[] = "%$q%";
}

if ($view === 'SIMPLE') {
    if($from) { $where[] = "date(i.updated_at) >= date(?)"; $args[] = $from; }
    if($to)   { $where[] = "date(i.updated_at) <= date(?)"; $args[] = $to; }
    $where[] = "i.is_deleted = 0";
    $sql = "SELECT * FROM items i";
    if($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY i.item_name ASC";
    $rows = $pdo->prepare($sql);
    $rows->execute($args);
    $rows = $rows->fetchAll();
} else {
    if($from) { $where[] = "date(t.created_at) >= date(?)"; $args[] = $from; }
    if($to)   { $where[] = "date(t.created_at) <= date(?)"; $args[] = $to; }
    $sql = "SELECT t.*, i.item_code, i.item_name, i.condition, u.username 
            FROM transactions t
            LEFT JOIN items i ON i.id=t.item_id 
            LEFT JOIN users u ON u.id=t.user_id";
    if($where) $sql .= " WHERE " . implode(" AND ", $where);
    $sql .= " ORDER BY t.id DESC LIMIT 2000";
    $rows = $pdo->prepare($sql);
    $rows->execute($args);
    $rows = $rows->fetchAll();
}
render_header('Audit', $user);
?>

<div class="card controls-bar">
  <h1>Audit & Inventory</h1>
  <div class="view-toggle">
    <button class="btn <?php echo $view==='SIMPLE'?'active-view':''; ?>" onclick="setView('SIMPLE')">Simple</button>
    <button class="btn <?php echo $view==='ALL'?'active-view':''; ?>" onclick="setView('ALL')">History</button>
  </div>
</div>

<div class="card">
  <form method="get" class="grid" id="auditFilter">
    <input type="hidden" name="view" value="<?php echo h($view); ?>">
    <div class="col-4"><div class="muted">Search</div><input name="q" value="<?php echo h($q); ?>" placeholder="ID, Name, or Code..." /></div>
    <div class="col-4"><div class="muted">From</div><input type="date" name="from" value="<?php echo h($from); ?>" /></div>
    <div class="col-4"><div class="muted">To</div><input type="date" name="to" value="<?php echo h($to); ?>" /></div>
    <div class="col-3"><div class="muted">Condition</div>
        <select name="condition">
            <option value="ALL">All</option>
            <option value="NEW" <?php echo $cond==='NEW'?'selected':''; ?>>New</option>
            <option value="REPAIRED" <?php echo $cond==='REPAIRED'?'selected':''; ?>>Repaired</option>
            <option value="FAULTY" <?php echo $cond==='FAULTY'?'selected':''; ?>>Faulty</option>
        </select>
    </div>
    <div class="col-3"><div class="muted">Status</div>
        <select name="status">
            <option value="ALL">All</option>
            <option value="IN_STOCK" <?php echo $status==='IN_STOCK'?'selected':''; ?>>In Stock</option>
            <option value="OUT_OF_STOCK" <?php echo $status==='OUT_OF_STOCK'?'selected':''; ?>>Out of Stock</option>
        </select>
    </div>
    <div class="col-6" style="display:flex; align-items:flex-end; gap:10px;">
        <button class="btn" type="submit">Filter</button>
        <button class="btn" type="button" onclick="window.print()">🖨️</button>
    </div>
  </form>
</div>

<div class="card">
  <?php if($view === 'SIMPLE'): ?>
    <h2>Inventory List</h2>
    <table>
      <thead><tr><th>ID</th><th>Code</th><th>Name</th><th>Cond</th><th>Qty</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach($rows as $r): 
             $condLbl = $r['condition']=='NEW' ? 'NEW' : ($r['condition']=='REPAIRED'?'??? REP':'?? FLT');
             $condCls = $r['condition']=='NEW' ? 'pill' : ($r['condition']=='REPAIRED'?'pill pill-adjust':'pill pill-take');
             $qty = (int)$r['qty'];
             $min = (int)($r['min_qty'] ?? 0);
             $rowClass = ($qty <= $min && $qty > 0) ? 'row-low-stock' : '';
        ?>
        <tr class="<?php echo $rowClass; ?>" onclick="showItemDetails(<?php echo $r['id']; ?>, '<?php echo h($r['item_name']); ?>')" style="cursor:pointer">
          <td class="muted"><?php echo (int)$r['id']; ?></td>
          <td><span class="pill"><?php echo h($r['item_code']); ?></span></td>
          <td><?php echo h($r['item_name']); ?></td>
          <td><span class="<?php echo $condCls; ?>" style="font-size:0.8em"><?php echo $condLbl; ?></span></td>
          <td><?php echo $qty; ?></td>
          <td><?php echo $qty>0 ? "<span class='good'>In Stock</span>" : "<span class='bad'>Out of Stock</span>"; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <h2>Transaction History</h2>
    <div class="table-scroll">
      <table>
        <thead><tr><th>Time</th><th>Act</th><th>Item</th><th>Delta</th><th>User</th></tr></thead>
        <tbody>
          <?php foreach($rows as $r): 
            $act = $r['action'] === 'ADD' ? 'IN' : ($r['action'] === 'TAKE' ? 'OUT' : $r['action']);
            $cls = $act === 'IN' ? 'pill-add' : ($act === 'OUT' ? 'pill-take' : 'pill-adjust');
            $code = $r['item_code'] ? h($r['item_code']) : '???';
            $name = $r['item_name'] ? h($r['item_name']) : 'Deleted Item (ID: '.$r['item_id'].')';
            $cond = $r['condition'] ?? '';
            $condLbl = $cond=='NEW' ? '' : ($cond=='REPAIRED'?' [REP]':($cond=='FAULTY'?' [FLT]':''));
          ?>
          <tr>
            <td class="muted" style="white-space:nowrap;font-size:0.85em"><?php echo substr($r['created_at'],0,16); ?></td>
            <td><span class="pill <?php echo $cls; ?>"><?php echo h($act); ?></span></td>
            <td><?php echo $code; ?>   <?php echo $name; ?><small><?php echo $condLbl; ?></small></td>
            <td><?php echo (int)$r['delta']; ?></td>
            <td><?php echo h($r['username'] ?? '-'); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
async function setView(mode) {
    await fetch('/api/set_pref.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'view='+mode });
    const url = new URL(window.location.href); url.searchParams.set('view', mode); window.location.href = url.toString();
}
</script>
<style>.active-view{background:var(--primary);color:#fff;border-color:var(--primary);}</style>
<?php render_footer(); ?>