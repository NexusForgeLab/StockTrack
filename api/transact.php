<?php
require_once __DIR__ . '/../app/auth.php';
$user = require_login();
$pdo = db();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // 1. Verify CSRF
    $data = json_decode(file_get_contents('php://input'), true);
    $token = $data['csrf'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        throw new Exception('Session expired. Please refresh.');
    }

    // 2. Validate Inputs
    $id   = (int)($data['id'] ?? 0);
    $type = $data['type'] ?? ''; // 'IN' or 'OUT'
    $qty  = (int)($data['qty'] ?? 0);
    $note = trim($data['note'] ?? '');

    if ($id <= 0 || $qty <= 0) throw new Exception("Invalid Quantity or Item.");
    if (!in_array($type, ['IN', 'OUT'])) throw new Exception("Invalid Action.");

    // 3. Permission Check (Optional: Strict Admin check for IN)
    if ($type === 'IN' && ($user['role'] ?? '') !== 'admin') {
        throw new Exception("Only Admins can add stock (IN).");
    }

    $pdo->beginTransaction();

    // 4. Get Current Item
    $st = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $st->execute([$id]);
    $item = $st->fetch();
    if (!$item) throw new Exception("Item not found.");

    // 5. Calculate New Values
    $current = (int)$item['qty'];
    $newQty = 0;
    $delta = 0;
    $actionDB = '';

    if ($type === 'IN') {
        $newQty = $current + $qty;
        $delta  = $qty;
        $actionDB = 'ADD'; // DB internally uses ADD
    } else {
        // OUT
        if ($current < $qty) throw new Exception("Insufficient stock (Current: $current).");
        $newQty = $current - $qty;
        $delta  = -$qty;
        $actionDB = 'TAKE'; // DB internally uses TAKE
    }

    // 6. Update Database
    $pdo->prepare("UPDATE items SET qty = ?, updated_at = datetime('now') WHERE id = ?")
        ->execute([$newQty, $id]);
    
    $pdo->prepare("INSERT INTO transactions(user_id, action, item_id, delta, qty_after, note, ip) VALUES(?,?,?,?,?,?,?)")
        ->execute([$user['id'], $actionDB, $id, $delta, $newQty, $note, $_SERVER['REMOTE_ADDR'] ?? '']);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}
