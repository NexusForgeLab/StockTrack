<?php
require_once __DIR__ . '/app/layout.php';
$user = current_user();
render_header('Store Stocks', $user);
?>
<div class="card">
  <h1>Store Stocks</h1>
  <div class="muted">Public can check stock. Logged-in users can take items and view audit. Admin can add/increment stock.</div>
  <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap">
    <a class="btn" href="/public.php">Public Check</a>
    <?php if($user): ?>
      <a class="btn" href="/take.php">Take Item</a>
      <a class="btn" href="/audit.php">Audit</a>
      <?php if(($user['role'] ?? '') === 'admin'): ?>
        <a class="btn" href="/admin.php">Admin Stock Entry</a>
        <a class="btn" href="/users.php">User Management</a>
      <?php endif; ?>
    <?php else: ?>
      <a class="btn" href="/login.php">Login</a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <h2>Quick Search</h2>
  <form method="get" action="/public.php" class="grid">
    <div class="col-9">
      <div class="muted">Search item name / code</div>
      <input name="q" placeholder="e.g. screws, cable, ITEM-001" />
    </div>
    <div class="col-3" style="display:flex;align-items:flex-end">
      <button class="btn" type="submit">Search</button>
    </div>
  </form>
</div>
<?php render_footer(); ?>
