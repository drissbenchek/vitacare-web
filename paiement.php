<?php
/* ============================================================
   Validation du panier — Règle 4.4
   beginTransaction() → vérif dispo de chaque élément →
   création des réservations/inscriptions confirmées → commit()
   Si un élément indisponible : rollBack() complet
   ============================================================ */
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user = current_user();
if ($user['role'] !== 'sportif') {
    flash('error', 'Accès refusé.');
    redirect(BASE_URL . 'index.php');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . 'panier.php');
}

csrf_check();

$id_panier  = (int)($_POST['id_panier'] ?? 0);
$id_sportif = $user['id_profil'];
$creneaux   = $_POST['creneaux'] ?? []; /* [id_ligne => id_creneau] */
$db         = get_db();

/* Vérifie que le panier appartient au sportif et est actif */
$stmt = $db->prepare(
    'SELECT id_panier FROM panier WHERE id_panier = :p AND id_sportif = :sp AND statut = "actif"'
);
$stmt->execute([':p' => $id_panier, ':sp' => $id_sportif]);
if (!$stmt->fetch()) {
    flash('error', 'Panier introuvable.');
    redirect(BASE_URL . 'panier.php');
}

/* Charge toutes les lignes */
$stmt = $db->prepare('SELECT * FROM ligne_panier WHERE id_panier = :p');
$stmt->execute([':p' => $id_panier]);
$lignes = $stmt->fetchAll();

if (empty($lignes)) {
    flash('error', 'Votre panier est vide.');
    redirect(BASE_URL . 'panier.php');
}

/* Vérifie que chaque ligne de type reservation a un créneau sélectionné */
foreach ($lignes as $l) {
    if ($l['type_element'] === 'reservation') {
        $id_creneau = (int)($creneaux[$l['id_ligne']] ?? 0);
        if ($id_creneau <= 0) {
            flash('error', 'Veuillez choisir un créneau pour chaque service.');
            redirect(BASE_URL . 'panier.php');
        }
    }
}

/* ============================================================
   Transaction atomique — Règle 4.4
   ============================================================ */
$db->beginTransaction();
try {
    $total          = 0;
    $notifications  = []; /* Messages à insérer après le commit */

    foreach ($lignes as $l) {
        $total += (float)$l['prix_unitaire'];

        /* ---- Réservation d'un service ---- */
        if ($l['type_element'] === 'reservation') {
            $id_service = (int)$l['id_element'];
            $id_creneau = (int)$creneaux[$l['id_ligne']];

            /* Vérifie que le créneau est lié au bon intervenant et verrouille */
            $stmt = $db->prepare(
                'SELECT c.id_creneau, c.statut, s.titre AS titre_service,
                        pi.id_utilisateur AS id_int
                 FROM creneau c
                 JOIN service s ON s.id_service = :srv
                 JOIN profil_intervenant pi ON pi.id_intervenant = c.id_intervenant
                    AND pi.id_intervenant = s.id_intervenant
                 WHERE c.id_creneau = :cid
                 FOR UPDATE'
            );
            $stmt->execute([':srv' => $id_service, ':cid' => $id_creneau]);
            $row = $stmt->fetch();

            if (!$row || $row['statut'] !== 'libre') {
                $db->rollBack();
                flash('error', 'Un créneau sélectionné n\'est plus disponible. Votre panier n\'a pas été validé.');
                redirect(BASE_URL . 'panier.php');
            }

            /* Passe le créneau à réservé */
            $db->prepare('UPDATE creneau SET statut = "reserve" WHERE id_creneau = :id')
               ->execute([':id' => $id_creneau]);

            /* Crée la réservation */
            $db->prepare(
                'INSERT INTO reservation (id_sportif, id_service, id_creneau, statut)
                 VALUES (:sp, :srv, :cid, "confirmee")'
            )->execute([':sp' => $id_sportif, ':srv' => $id_service, ':cid' => $id_creneau]);

            $notifications[] = [
                'id_u' => $user['id_utilisateur'],
                'type' => 'success',
                'msg'  => 'Réservation confirmée : « ' . $row['titre_service'] . ' ».',
            ];
            $notifications[] = [
                'id_u' => $row['id_int'],
                'type' => 'info',
                'msg'  => $user['prenom'] . ' ' . $user['nom'] . ' a réservé « ' . $row['titre_service'] . ' ».',
            ];

        /* ---- Inscription à un atelier ---- */
        } elseif ($l['type_element'] === 'inscription') {
            $id_activite = (int)$l['id_element'];

            /* Vérifie les places et verrouille */
            $stmt = $db->prepare(
                'SELECT a.id_activite, a.titre, a.capacite_max, a.places_reservees,
                        pi.id_utilisateur AS id_int
                 FROM activite a
                 JOIN profil_intervenant pi ON pi.id_intervenant = a.id_intervenant
                 WHERE a.id_activite = :id FOR UPDATE'
            );
            $stmt->execute([':id' => $id_activite]);
            $act = $stmt->fetch();

            if (!$act || $act['places_reservees'] >= $act['capacite_max']) {
                $db->rollBack();
                flash('error', 'L\'atelier « ' . ($act['titre'] ?? '') . ' » est complet. Votre panier n\'a pas été validé.');
                redirect(BASE_URL . 'panier.php');
            }

            /* Inscription + incrément places */
            $db->prepare(
                'INSERT INTO inscription (id_sportif, id_activite, statut)
                 VALUES (:sp, :act, "confirmee")'
            )->execute([':sp' => $id_sportif, ':act' => $id_activite]);

            $db->prepare(
                'UPDATE activite SET places_reservees = places_reservees + 1
                 WHERE id_activite = :id'
            )->execute([':id' => $id_activite]);

            $notifications[] = [
                'id_u' => $user['id_utilisateur'],
                'type' => 'success',
                'msg'  => 'Inscription confirmée : atelier « ' . $act['titre'] . ' ».',
            ];
            $notifications[] = [
                'id_u' => $act['id_int'],
                'type' => 'info',
                'msg'  => $user['prenom'] . ' ' . $user['nom'] . ' s\'est inscrit(e) à « ' . $act['titre'] . ' ».',
            ];
        }
    }

    /* Enregistre le paiement simulé */
    $db->prepare(
        'INSERT INTO paiement (id_panier, montant_total, statut) VALUES (:p, :total, "simule")'
    )->execute([':p' => $id_panier, ':total' => $total]);

    /* Clôture le panier */
    $db->prepare('UPDATE panier SET statut = "valide" WHERE id_panier = :p')
       ->execute([':p' => $id_panier]);

    /* Envoie toutes les notifications */
    $stmt_notif = $db->prepare(
        'INSERT INTO notification (id_utilisateur, type, message) VALUES (:id_u, :type, :msg)'
    );
    foreach ($notifications as $n) {
        $stmt_notif->execute($n);
    }

    $db->commit();

    flash('success', 'Paiement confirmé ! Toutes vos réservations sont enregistrées.');
    redirect(BASE_URL . 'confirmation.php?panier=' . $id_panier);

} catch (Exception $e) {
    $db->rollBack();
    flash('error', 'Une erreur est survenue lors du paiement. Veuillez réessayer.');
    redirect(BASE_URL . 'panier.php');
}
