<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/config.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';
start_session_secure();

$user = current_user();
if ($user === null) {
    echo json_encode(['success' => false]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$db   = get_db();

/* Marquer une notif spécifique ou toutes */
if (!empty($data['id_notification'])) {
    $stmt = $db->prepare(
        'UPDATE notification SET lu = 1
         WHERE id_notification = :id AND id_utilisateur = :uid'
    );
    $stmt->execute([':id' => (int)$data['id_notification'], ':uid' => $user['id_utilisateur']]);
} else {
    $stmt = $db->prepare(
        'UPDATE notification SET lu = 1 WHERE id_utilisateur = :uid'
    );
    $stmt->execute([':uid' => $user['id_utilisateur']]);
}

echo json_encode(['success' => true]);
