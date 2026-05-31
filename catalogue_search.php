<?php
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/config.php';

/* --- Paramètres de filtre (GET) --- */
$recherche   = trim($_GET['recherche']    ?? '');
$id_categorie = (int)($_GET['id_categorie'] ?? 0);
$prix_max    = (float)($_GET['prix_max']  ?? 0);
$duree_max   = (int)($_GET['duree_max']   ?? 0);

/* --- Construction dynamique de la requête --- */
$where  = ['s.statut = "actif"', 'pi.statut_validation = "valide"'];
$params = [];

if ($recherche !== '') {
    $where[]          = '(s.titre LIKE :rech OR s.description LIKE :rech2)';
    $params[':rech']  = '%' . $recherche . '%';
    $params[':rech2'] = '%' . $recherche . '%';
}
if ($id_categorie > 0) {
    $where[]      = 's.id_categorie = :cat';
    $params[':cat'] = $id_categorie;
}
if ($prix_max > 0) {
    $where[]           = 's.prix <= :prix_max';
    $params[':prix_max'] = $prix_max;
}
if ($duree_max > 0) {
    $where[]             = 's.duree <= :duree_max';
    $params[':duree_max'] = $duree_max;
}

$sql = 'SELECT s.id_service, s.titre, s.description, s.duree, s.prix,
               c.nom AS categorie, c.icone,
               u.prenom, u.nom AS nom_intervenant,
               pi.id_intervenant,
               COALESCE(AVG(a.note), 0) AS note_moyenne,
               COUNT(a.id_avis)         AS nb_avis
        FROM service s
        JOIN profil_intervenant pi ON pi.id_intervenant = s.id_intervenant
        JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
        JOIN categorie c ON c.id_categorie = s.id_categorie
        LEFT JOIN avis a ON a.id_service = s.id_service
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY s.id_service
        ORDER BY s.id_service';

$db   = get_db();
$stmt = $db->prepare($sql);
$stmt->execute($params);
$services = $stmt->fetchAll();

echo json_encode([
    'success'  => true,
    'services' => $services,
    'total'    => count($services),
]);
