<?php
require_once __DIR__ . '/app/layout.php';
$user = require_login();
$pdo = db();
$err=''; $ok='';
if($_SERVER['REQUEST_METHOD']==='POST'){
  csrf_check();
  $cur = $_POST['current_password'] ?? '';
  $n1 = $_POST['new_password'] ?? '';
  $n2 = $_POST['new_password2'] ?? '';
  if($n1 !== $n2) $err='New passwords do not match.';
  elseif(strlen($n1) < 6) $err='New password min 6 chars.';
  else{
    $st=$pdo->prepare("SELECT pass_hash FROM users WHERE id=?");
    $st->execute([$user['id']]);
    $hash=$st->fetchColumn();
    if(!$hash || !password_verify($cur, (string)$hash)) $err='Current password is wrong.';
    else{
      $pdo->prepare("UPDATE users SET pass_hash=? WHERE id=?")
          ->execute([password_hash($n1, PASSWORD_DEFAULT), $user['id']]);
      $ok='Password updated.';
    }
  }
}
render_header('Change Password', $user);
?>
<div class="card"><h1>Change Password</h1></div>
<?php if($err): ?><div class="card bad"><?php echo h($err); ?></div><?php endif; ?>
<?php if($ok): ?><div class="card good"><?php echo h($ok); ?></div><?php endif; ?>
<div class="card">
  <form method="post" class="grid">
    <input type="hidden" name="csrf" value="<?php echo h(csrf_token()); ?>"/>
    <div class="col-4"><div class="muted">Current Password</div><input type="password" name="current_password" required /></div>
    <div class="col-4"><div class="muted">New Password</div><input type="password" name="new_password" required /></div>
    <div class="col-4"><div class="muted">Repeat New Password</div><input type="password" name="new_password2" required /></div>
    <div class="col-12"><button class="btn" type="submit">Update</button></div>
  </form>
</div>
<?php render_footer(); ?>
