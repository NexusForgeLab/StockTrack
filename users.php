<?php
require_once __DIR__ . '/app/layout.php';
$admin = require_admin();
$pdo = db();
$err=''; $ok='';

// --- ACTION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check();
  $action = $_POST['action'] ?? '';

  // 1. CREATE USER
  if ($action === 'create') {
      $username = strtolower(trim($_POST['username'] ?? ''));
      $display  = trim($_POST['display_name'] ?? '');
      $pass     = $_POST['password'] ?? '';
      $role     = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';

      if ($username==='' || $display==='' || $pass==='') $err="Fill all fields.";
      elseif (!preg_match('/^[a-z0-9_.-]{3,30}$/', $username)) $err="Username 3-30 chars: a-z 0-9 _ . -";
      elseif (strlen($pass) < 6) $err="Password min 6 chars.";
      else {
        try {
          $pdo->prepare("INSERT INTO users(username,pass_hash,display_name,role) VALUES(?,?,?,?)")
              ->execute([$username, password_hash($pass, PASSWORD_DEFAULT), $display, $role]);
          $ok="User created.";
        } catch (Exception $e) { $err="Could not create user (username might exist)."; }
      }
  }
  // 2. DELETE USER
  elseif ($action === 'delete') {
      $del_id = (int)$_POST['user_id'];
      if($del_id === $admin['id']) {
          $err = "You cannot delete yourself.";
      } else {
          $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$del_id]);
          $ok = "User deleted.";
      }
  }
  // 3. RESET PASSWORD
  elseif ($action === 'reset_pass') {
      $target_id = (int)$_POST['user_id'];
      $new_pass  = $_POST['new_pass'] ?? '';
      if(strlen($new_pass) < 6) {
          $err = "Password must be at least 6 chars.";
      } else {
          $pdo->prepare("UPDATE users SET pass_hash=? WHERE id=?")
              ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $target_id]);
          $ok = "Password updated for user ID $target_id.";
      }
  }
}

$rows = $pdo->query("SELECT id,username,display_name,role,created_at FROM users ORDER BY id DESC")->fetchAll();
render_header('Users', $admin);
?>

<div class="card">
  <h1>User Management</h1>
  <div class="muted">Admin can add users, remove users, or reset passwords.</div>
  <div style="margin-top:10px"><a class="btn" href="/password.php">Change My Password</a></div>
</div>

<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>

<div class="card">
  <h2>Add User</h2>
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <input type="hidden" name="action" value="create"/>
    
    <div class="col-3"><div class="muted">Username *</div><input name="username" required /></div>
    <div class="col-4"><div class="muted">Display Name *</div><input name="display_name" required /></div>
    <div class="col-3"><div class="muted">Password *</div><input type="password" name="password" required /></div>
    <div class="col-2">
      <div class="muted">Role</div>
      <select name="role"><option value="user">user</option><option value="admin">admin</option></select>
    </div>
    <div class="col-12"><button class="btn" type="submit">Create User</button></div>
  </form>
</div>

<div class="card">
  <h2>Users</h2>
  <table>
    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Role</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><?php echo (int)$r['id']; ?></td>
        <td><span class="pill"><?php echo h($r['username']); ?></span></td>
        <td><?php echo h($r['display_name']); ?></td>
        <td><?php echo h($r['role']); ?></td>
        <td class="muted"><?php echo h($r['created_at']); ?></td>
        <td>
            <?php if((int)$r['id'] !== $admin['id']): ?>
                <button class="btn" onclick="openResetModal(<?php echo $r['id']; ?>, '<?php echo h($r['username']); ?>')">🔑 Pwd</button>
                <button class="btn btn-del" onclick="deleteUser(<?php echo $r['id']; ?>, '<?php echo h($r['username']); ?>')">🗑️</button>
            <?php else: ?>
                <span class="muted">(You)</span>
            <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<form id="delForm" method="post">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <input type="hidden" name="action" value="delete"/>
    <input type="hidden" name="user_id" id="delInput"/>
</form>

<div id="pwdModal" class="modal">
    <div class="modal-content" style="max-width:400px">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Reset Password</h3>
        <p>Set new password for <b id="pwdUser"></b>:</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
            <input type="hidden" name="action" value="reset_pass"/>
            <input type="hidden" name="user_id" id="pwdInput"/>
            <input type="password" name="new_pass" placeholder="New Password" required style="margin-bottom:10px"/>
            <button class="btn" type="submit">Update Password</button>
        </form>
    </div>
</div>

<script>
function deleteUser(id, name) {
    if(confirm('Are you sure you want to delete user: ' + name + '?')) {
        document.getElementById('delInput').value = id;
        document.getElementById('delForm').submit();
    }
}
function openResetModal(id, name) {
    document.getElementById('pwdModal').style.display = 'block';
    document.getElementById('pwdUser').innerText = name;
    document.getElementById('pwdInput').value = id;
}
function closeModal() {
    document.getElementById('pwdModal').style.display = 'none';
}
window.onclick = function(e) {
    if(e.target == document.getElementById('pwdModal')) closeModal();
}
</script>

<?php render_footer(); ?>