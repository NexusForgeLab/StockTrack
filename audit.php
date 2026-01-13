<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();
$action = strtoupper(trim($_GET['action'] ?? ''));
$q = trim($_GET['q'] ?? '');
$who = trim($_GET['who'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$where=[]; $args=[];
if ($action && in_array($action, ['ADD','TAKE','ADJUST'], true)) { $where[]="t.action=?"; $args[]=$action; }
if ($q !== '') { $where[]="(i.item_name LIKE ? OR i.item_code LIKE ?)"; $args[]='%'.$q.'%'; $args[]='%'.$q.'%'; }
if ($who !== '') { $where[]="(u.username LIKE ? OR u.display_name LIKE ?)"; $args[]='%'.$who.'%'; $args[]='%'.$who.'%'; }
if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where[]="date(t.created_at) >= date(?)"; $args[]=$from; }
if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $where[]="date(t.created_at) <= date(?)"; $args[]=$to; }
$sql = "SELECT t.*, i.item_code, i.item_name, u.username, u.display_name
        FROM transactions t
        JOIN items i ON i.id=t.item_id
        LEFT JOIN users u ON u.id=t.user_id";
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY t.id DESC LIMIT 500";
$st=$pdo->prepare($sql); $st->execute($args); $rows=$st->fetchAll();
render_header('Audit', $user);
?>
<div class="card">
  <h1>Audit</h1>
  <div class="muted">Shows who added or took items and when. Filter by action, item, user, dates.</div>
</div>

<div class="card">
  <form method="get" class="grid">
    <div class="col-2">
      <div class="muted">Action</div>
      <select name="action">
        <option value="">All</option>
        <option value="ADD" <?php echo $action==='ADD'?'selected':''; ?>>ADD</option>
        <option value="TAKE" <?php echo $action==='TAKE'?'selected':''; ?>>TAKE</option>
        <option value="ADJUST" <?php echo $action==='ADJUST'?'selected':''; ?>>ADJUST</option>
      </select>
    </div>
    <div class="col-4">
      <div class="muted">Item (name/code)</div>
      <input name="q" value="<?php echo h($q); ?>" />
    </div>
    <div class="col-3">
      <div class="muted">Who (username/name)</div>
      <input name="who" value="<?php echo h($who); ?>" />
    </div>
    <div class="col-3">
      <div class="muted">From / To</div>
      <div style="display:flex;gap:8px">
        <input type="date" name="from" value="<?php echo h($from); ?>"/>
        <input type="date" name="to" value="<?php echo h($to); ?>"/>
      </div>
    </div>
    <div class="col-12">
      <button class="btn" type="submit">Filter</button>
      <a class="btn" href="/audit.php">Reset</a>
    </div>
  </form>
</div>

<div class="card">
  <h2>Transactions (latest 500)</h2>
  <table>
    <thead><tr><th>When</th><th>Action</th><th>Item</th><th>Delta</th><th>Qty After</th><th>Who</th><th>Note</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td class="muted"><?php echo h($r['created_at']); ?></td>
        <td><span class="pill"><?php echo h($r['action']); ?></span></td>
        <td><?php echo h($r['item_code']); ?> — <?php echo h($r['item_name']); ?></td>
        <td><?php echo (int)$r['delta']; ?></td>
        <td><?php echo (int)$r['qty_after']; ?></td>
        <td><?php echo h(($r['username'] ?? 'public') . ' (' . ($r['display_name'] ?? '-') . ')'); ?></td>
        <td class="muted"><?php echo h($r['note'] ?? ''); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php render_footer(); ?>
