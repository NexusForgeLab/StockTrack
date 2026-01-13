<?php
require_once __DIR__ . '/app/db.php';
$pdo = db();

$pdo->exec("
CREATE TABLE IF NOT EXISTS users(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  pass_hash TEXT NOT NULL,
  display_name TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('admin','user')) DEFAULT 'user',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS items(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  item_code TEXT NOT NULL UNIQUE,
  item_name TEXT NOT NULL,
  qty INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_items_name ON items(item_name);

CREATE TABLE IF NOT EXISTS transactions(
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
  action TEXT NOT NULL CHECK(action IN ('ADD','TAKE','ADJUST')),
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  delta INTEGER NOT NULL,
  qty_after INTEGER NOT NULL,
  note TEXT NOT NULL DEFAULT '',
  ip TEXT NOT NULL DEFAULT '',
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_tx_created ON transactions(created_at);
CREATE INDEX IF NOT EXISTS idx_tx_user ON transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_tx_item ON transactions(item_id);
");

$exists = (int)($pdo->query("SELECT COUNT(*) FROM users")->fetchColumn());
if ($exists === 0) {
  $st = $pdo->prepare("INSERT INTO users(username, pass_hash, display_name, role) VALUES (?,?,?,?)");
  $st->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Admin', 'admin']);
  $st->execute(['user',  password_hash('user123',  PASSWORD_DEFAULT), 'User',  'user']);
}

echo "<h2>Installed ✅</h2>";
echo "<ul><li><b>admin</b> / admin123</li><li><b>user</b> / user123</li></ul>";
echo "<p><a href='/login.php'>Login</a></p>";
