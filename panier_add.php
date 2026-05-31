<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/config.php';
require_once '../includes/helpers.php';
require_once '../includes/auth.php';
start_session_secure();

/* Seuls les sportifs connectés peuvent ajouter au panier */
if (current_user() === null) {
    echo json_encode(['success' => false, 'error' => 'Vous devez être connecté pour ajouter au panier.']);
    exit;
}
if (current_user()['role'] !== 'sportif') {
    echo json_encode(['success' => false, 'error' => 'Seuls les sportifs peuvent utiliser le panier.']);
    exit;
}

/* Lecture du corps JSON */
$data       = json_decode(file_get_contents('php://input'), true) ?? [];
$type       = $data['type']       ?? '';
$id_service = (int)($data['id_service'] ?? 0);

if ($type !== 'service' || $id_service <= 0) {
    echo json_encode(['success' => false, 'error' => 'Paramètres invalides.']);
    exit;
}

$db   = get_db();
$user = current_user();

/* Vérifie que le service existe et est actif */
$stmt = $db->prepare(
    'SELECT s.id_service, s.titre, s.prix
     FROM service s
     JOIN profil_intervenant pi ON pi.id_intervenant = s.id_intervenant
     WHERE s.id_service = :id AND s.statut = "actif" AND pi.statut_validation = "valide"'
);
$stmt->execute([':id' => $id_service]);
$service = $stmt->fetch();

if (!$service) {
    echo json_encode(['success' => false, 'error' => 'Service introuvable ou indisponible.']);
    exit;
}

/* Récupère ou crée le panier actif du sportif */
$stmt = $db->prepare(
    'SELECT id_panier FROM panier WHERE id_sportif = :id AND statut = "actif" LIMIT 1'
);
$stmt->execute([':id' => $user['id_profil']]);
$panier = $stmt->fetch();

if (!$panier) {
    $stmt = $db->prepare(
        'INSERT INTO panier (id_sportif, statut) VALUES (:id, "actif")'
    );
    $stmt->execute([':id' => $user['id_profil']]);
    $id_panier = (int)$db->lastInsertId();
} else {
    $id_panier = (int)$panier['id_panier'];
}

/* Vérifie si ce service est déjà dans le panier (sans créneau assigné encore) */
$stmt = $db->prepare(
    'SELECT id_ligne FROM ligne_panier
     WHERE id_panier = :panier AND type_element = "reservation" AND id_element = :srv'
);
$stmt->execute([':panier' => $id_panier, ':srv' => $id_service]);
if ($stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Ce service est déjà dans votre panier.']);
    exit;
}

/* Ajoute la ligne panier (id_element = id_service, créneau sera choisi au checkout) */
$stmt = $db->prepare(
    'INSERT INTO ligne_panier (id_panier, type_element, id_element, prix_unitaire)
     VALUES (:panier, "reservation", :srv, :prix)'
);
$stmt->execute([
    ':panier' => $id_panier,
    ':srv'    => $id_service,
    ':prix'   => $service['prix'],
]);

echo json_encode([
    'success' => true,
    'message' => '« ' . $service['titre'] . ' » ajouté au panier.',
]);
