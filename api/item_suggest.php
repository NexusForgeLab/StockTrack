<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/db.php';
$pdo = db();
$q = trim($_GET['q'] ?? '');
if ($q === '') { header('Content-Type: application/json'); echo json_encode([]); exit; }
$st = $pdo->prepare("SELECT item_code, item_name, qty FROM items WHERE item_name LIKE ? OR item_code LIKE ? ORDER BY item_name ASC LIMIT 10");
$like = '%'.$q.'%';
$st->execute([$like, $like]);
header('Content-Type: application/json');
echo json_encode($st->fetchAll());
