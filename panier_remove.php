<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/config.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';
start_session_secure();

if (current_user() === null || current_user()['role'] !== 'sportif') {
    echo json_encode(['success' => false, 'error' => 'Non autorisé.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$id_ligne = (int)($data['id_ligne'] ?? 0);
if ($id_ligne <= 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètre invalide.']);
    exit;
}

$db   = get_db();
$user = current_user();

/* Vérifie que la ligne appartient au panier actif du sportif */
$stmt = $db->prepare(
    'SELECT lp.id_ligne FROM ligne_panier lp
     JOIN panier p ON p.id_panier = lp.id_panier
     WHERE lp.id_ligne = :id AND p.id_sportif = :sp AND p.statut = "actif"'
);
$stmt->execute([':id' => $id_ligne, ':sp' => $user['id_profil']]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Ligne introuvable.']);
    exit;
}

$db->prepare('DELETE FROM ligne_panier WHERE id_ligne = :id')->execute([':id' => $id_ligne]);

echo json_encode(['success' => true]);
