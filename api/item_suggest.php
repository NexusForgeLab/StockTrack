<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/db.php';
$pdo = db();
$q = trim($_GET['q'] ?? '');
if ($q === '') { echo json_encode([]); exit; }

// Search by name or code, filter deleted
$st = $pdo->prepare("SELECT id, item_code, item_name, qty, condition FROM items WHERE (item_name LIKE ? OR item_code LIKE ?) AND is_deleted=0 ORDER BY item_name ASC LIMIT 15");
$like = '%'.$q.'%';
$st->execute([$like, $like]);
header('Content-Type: application/json');
echo json_encode($st->fetchAll());