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
    $originId = (int)($data['id'] ?? 0);
    $type = $data['type'] ?? ''; // 'IN' or 'OUT'
    $qty  = (int)($data['qty'] ?? 0);
    $note = trim($data['note'] ?? '');
    $targetCond = $data['condition'] ?? 'NEW'; 
    $isReturn = $data['is_return'] ?? false; // Check for Return flag

    if ($originId <= 0 || $qty <= 0) throw new Exception("Invalid Quantity or Item.");
    if (!in_array($type, ['IN', 'OUT'])) throw new Exception("Invalid Action.");

    // 3. Permission Check
    // ALLOW if Admin OR if it is a user Return
    if ($type === 'IN' && ($user['role'] ?? '') !== 'admin' && !$isReturn) {
        throw new Exception("Only Admins can add stock (IN).");
    }

    $pdo->beginTransaction();

    // 4. Get Origin Item
    $st = $pdo->prepare("SELECT item_code, item_name FROM items WHERE id = ?");
    $st->execute([$originId]);
    $origin = $st->fetch();
    if (!$origin) throw new Exception("Original item not found.");
    
    $itemCode = $origin['item_code'];
    $itemName = $origin['item_name'];

    // 5. Find Target Item
    $st = $pdo->prepare("SELECT * FROM items WHERE item_code = ? AND condition = ?");
    $st->execute([$itemCode, $targetCond]);
    $target = $st->fetch();

    $targetId = 0;
    $currentQty = 0;

    if ($target) {
        $targetId = (int)$target['id'];
        $currentQty = (int)$target['qty'];
    } else {
        if ($type === 'OUT') {
            throw new Exception("Cannot take OUT: $targetCond item does not exist.");
        }
        $pdo->prepare("INSERT INTO items(item_code, item_name, qty, condition) VALUES(?,?,0,?)")
            ->execute([$itemCode, $itemName, $targetCond]);
        $targetId = (int)$pdo->lastInsertId();
        $currentQty = 0;
    }

    // 6. Calculate New Values
    $newQty = 0;
    $delta = 0;
    $actionDB = '';

    if ($type === 'IN') {
        $newQty = $currentQty + $qty;
        $delta  = $qty;
        $actionDB = $isReturn ? 'ADD' : 'ADD'; // You could use 'RETURN' if you added it to schema check constraint
        if($isReturn && !$note) $note = "User Return"; 
    } else {
        // OUT
        if ($currentQty < $qty) throw new Exception("Insufficient $targetCond stock (Avail: $currentQty).");
        $newQty = $currentQty - $qty;
        $delta  = -$qty;
        $actionDB = 'TAKE';
    }

    // 7. Update Database
    $pdo->prepare("UPDATE items SET qty = ?, updated_at = datetime('now') WHERE id = ?")
        ->execute([$newQty, $targetId]);
    
    $pdo->prepare("INSERT INTO transactions(user_id, action, item_id, delta, qty_after, note, ip) VALUES(?,?,?,?,?,?,?)")
        ->execute([$user['id'], $actionDB, $targetId, $delta, $newQty, $note, $_SERVER['REMOTE_ADDR'] ?? '']);

    // 8. Email Notification (Low Stock)
    if ($type === 'OUT' && isset($target['min_qty']) && $newQty <= (int)$target['min_qty']) {
        // Simple mail trigger (ensure server is configured)
        $to = 'admin@localhost'; // Replace with real admin email
        $subject = "Low Stock Alert: $itemName";
        $msg = "Item '$itemName' ($targetCond) has dropped to $newQty (Threshold: " . $target['min_qty'] . ")";
        // Suppress errors with @ to prevent breaking JSON response if mail fails
        @mail($to, $subject, $msg);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['error' => $e->getMessage()]);
}