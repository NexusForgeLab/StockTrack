<?php
require_once __DIR__ . '/app/auth.php';
require_admin(); // Security check

$file = __DIR__ . '/data/app.db';

if (file_exists($file)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="stocktrack_backup_'.date('Y-m-d').'.db"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    exit;
} else {
    echo "Database file not found.";
}
