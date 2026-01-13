<?php
require_once __DIR__ . '/../app/auth.php';
$user = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $view = $_POST['view'] ?? 'SIMPLE';
    if(in_array($view, ['SIMPLE', 'ALL'])) {
        $pdo->prepare("UPDATE users SET view_pref = ? WHERE id = ?")
            ->execute([$view, $user['id']]);
        $_SESSION['user']['view_pref'] = $view;
    }
}
