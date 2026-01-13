<?php
require_once __DIR__ . '/app/auth.php';
require_admin(); // Security: Only admin can export
$pdo = db();

// Set filename with date
$filename = "stock_backup_" . date('Y-m-d') . ".csv";

// Force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$out = fopen('php://output', 'w');

// 1. Add Column Headers
// Note: Code and Condition are included so you can restore items perfectly.
fputcsv($out, ['ID', 'Item Code', 'Item Name', 'Condition', 'Quantity']);

// 2. Fetch Data
$stmt = $pdo->query("SELECT id, item_code, item_name, condition, qty FROM items WHERE is_deleted=0 ORDER BY id ASC");

// 3. Write Rows
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    fputcsv($out, $row);
}

fclose($out);
exit;
