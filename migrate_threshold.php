<?php
require_once __DIR__ . '/app/db.php';
$pdo = db();
try {
    $pdo->exec("ALTER TABLE items ADD COLUMN min_qty INTEGER NOT NULL DEFAULT 0");
    echo "Column 'min_qty' added successfully.";
} catch (Exception $e) { echo "Error (column might exist): " . $e->getMessage(); }
