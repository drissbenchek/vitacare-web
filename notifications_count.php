<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/config.php';
require_once '../includes/auth.php';
start_session_secure();

$user = current_user();
if ($user === null) {
    echo json_encode(['count' => 0]);
    exit;
}

$db   = get_db();
$stmt = $db->prepare(
    'SELECT COUNT(*) FROM notification WHERE id_utilisateur = :id AND lu = 0'
);
$stmt->execute([':id' => $user['id_utilisateur']]);

echo json_encode(['count' => (int)$stmt->fetchColumn()]);
