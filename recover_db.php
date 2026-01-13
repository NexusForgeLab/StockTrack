<?php
require_once __DIR__ . '/app/db.php';
$pdo = db();

echo "<h2>Database Recovery Tool</h2><pre>";

try {
    // 1. Get list of tables
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
    echo "Found Tables: " . implode(', ', $tables) . "\n\n";

    $hasItems = in_array('items', $tables);
    $hasOld   = in_array('items_old', $tables);

    // 2. LOGIC FLOW
    if ($hasItems) {
        // Check if 'items' is the NEW schema (has 'condition' column)
        $cols = $pdo->query("PRAGMA table_info(items)")->fetchAll(PDO::FETCH_COLUMN, 1);
        $isNewSchema = in_array('condition', $cols);
        $rowCount = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();

        echo "Table 'items': " . ($isNewSchema ? "NEW Schema (Correct)" : "OLD Schema") . ". Rows: $rowCount\n";

        if ($isNewSchema && $rowCount > 0) {
            echo "✅ Database looks correct. No action needed on 'items'.\n";
        } elseif ($isNewSchema && $rowCount === 0) {
            // New schema but empty? Try to recover from items_old
            if ($hasOld) {
                echo "⚠️ 'items' is empty. Recovering data from 'items_old'...\n";
                // Check columns in old table
                $oldCols = $pdo->query("PRAGMA table_info(items_old)")->fetchAll(PDO::FETCH_COLUMN, 1);
                $hasDeleted = in_array('is_deleted', $oldCols);
                
                if($hasDeleted) {
                     $pdo->exec("INSERT INTO items (id, item_code, item_name, qty, is_deleted, created_at, updated_at, condition)
                                SELECT id, item_code, item_name, qty, is_deleted, created_at, updated_at, 'NEW' FROM items_old");
                } else {
                     $pdo->exec("INSERT INTO items (id, item_code, item_name, qty, created_at, updated_at, condition)
                                SELECT id, item_code, item_name, qty, created_at, updated_at, 'NEW' FROM items_old");
                }
                echo "✅ Data recovered.\n";
            } else {
                echo "❌ 'items' is empty and no 'items_old' found. Data might be lost.\n";
            }
        } elseif (!$isNewSchema) {
            // Old schema. Perform Migration.
            echo "🔄 Migrating 'items' to NEW schema...\n";
            
            // 1. Rename
            if ($hasOld) $pdo->exec("DROP TABLE items_old"); // Safety
            $pdo->exec("ALTER TABLE items RENAME TO items_old");
            
            // 2. Create New
            $pdo->exec("
                CREATE TABLE items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    item_code TEXT NOT NULL,
                    item_name TEXT NOT NULL,
                    condition TEXT NOT NULL DEFAULT 'NEW',
                    qty INTEGER NOT NULL DEFAULT 0,
                    is_deleted INTEGER DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                    UNIQUE(item_code, condition)
                )
            ");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_items_name ON items(item_name)");

            // 3. Copy
            $oldCols = $pdo->query("PRAGMA table_info(items_old)")->fetchAll(PDO::FETCH_COLUMN, 1);
            $hasDeleted = in_array('is_deleted', $oldCols);
            if($hasDeleted) {
                $pdo->exec("INSERT INTO items (id, item_code, item_name, qty, is_deleted, created_at, updated_at, condition)
                            SELECT id, item_code, item_name, qty, is_deleted, created_at, updated_at, 'NEW' FROM items_old");
            } else {
                $pdo->exec("INSERT INTO items (id, item_code, item_name, qty, created_at, updated_at, condition)
                            SELECT id, item_code, item_name, qty, created_at, updated_at, 'NEW' FROM items_old");
            }
            echo "✅ Migration Complete.\n";
        }
    } else {
        // 'items' missing completely
        if ($hasOld) {
            echo "⚠️ 'items' missing. Restoring from 'items_old'...\n";
            $pdo->exec("ALTER TABLE items_old RENAME TO items");
            echo "Restored. Please run this script again to perform migration.\n";
        } else {
            echo "❌ CRITICAL: Neither 'items' nor 'items_old' found. Creating empty table.\n";
            $pdo->exec("
                CREATE TABLE items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    item_code TEXT NOT NULL,
                    item_name TEXT NOT NULL,
                    condition TEXT NOT NULL DEFAULT 'NEW',
                    qty INTEGER NOT NULL DEFAULT 0,
                    is_deleted INTEGER DEFAULT 0,
                    created_at TEXT NOT NULL DEFAULT (datetime('now')),
                    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
                    UNIQUE(item_code, condition)
                )
            ");
        }
    }

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage();
}
echo "</pre><a href='/'>Go Home</a>";
?>
