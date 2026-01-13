<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

function render_header(string $title, ?array $user): void { ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?php echo h($title); ?></title>
  <link rel="stylesheet" href="/assets/style.css"/>
</head>
<body>
  <div class="topbar">
    <div class="brand"><a href="/">Store Stocks</a></div>
    <div class="nav">
      <a class="btn" href="/public.php">Public Check</a>
      <?php if($user): ?>
        <a class="btn" href="/take.php">Take Item</a>
        <a class="btn" href="/audit.php">Audit</a>
        <?php if(($user['role'] ?? '') === 'admin'): ?>
          <a class="btn" href="/admin.php">Admin</a>
          <a class="btn" href="/users.php">Users</a>
        <?php endif; ?>
        <a class="btn" href="/logout.php">Logout</a>
      <?php else: ?>
        <a class="btn" href="/login.php">Login</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="wrap">
<?php }

function render_footer(): void { ?>
  </div>
</body>
</html>
<?php }
