<?php
require_once __DIR__ . '/app/db.php';
$pdo = db();

echo "<h2>Transaction Table Fixer</h2><pre>";

try {
    // 1. Rename broken table
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    if(in_array('transactions', $tables)) {
        $pdo->exec("ALTER TABLE transactions RENAME TO transactions_broken");
        echo "Renamed broken table.\n";
    }

    // 2. Create NEW correct table
    $pdo->exec("
        CREATE TABLE transactions(
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
    echo "Created new transactions table.\n";

    // 3. Rescue data (if any exists)
    // We ignore errors here in case data is incompatible
    if(in_array('transactions_broken', $tables)) {
        echo "Attempting to copy old logs...\n";
        // Only copy rows where the item still exists to prevent FK errors
        $sql = "INSERT INTO transactions (id, user_id, action, item_id, delta, qty_after, note, ip, created_at)
                SELECT t.id, t.user_id, t.action, t.item_id, t.delta, t.qty_after, t.note, t.ip, t.created_at 
                FROM transactions_broken t
                WHERE EXISTS (SELECT 1 FROM items i WHERE i.id = t.item_id)";
        $pdo->exec($sql);
        echo "Data copied.\n";
        
        $pdo->exec("DROP TABLE transactions_broken");
        echo "Cleaned up old table.\n";
    }

    echo "\n✅ SUCCESS! Transactions table repaired.";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage();
}
echo "</pre><a href='/'>Go Home</a>";
