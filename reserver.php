<?php
$page_title = 'Réserver un créneau';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user = current_user();
if ($user['role'] !== 'sportif') {
    flash('error', 'Seuls les sportifs peuvent effectuer des réservations.');
    redirect(BASE_URL . 'catalogue.php');
}

$id_service = (int)($_GET['service'] ?? $_POST['id_service'] ?? 0);
$id_creneau = (int)($_GET['creneau'] ?? $_POST['id_creneau'] ?? 0);

if ($id_service <= 0 || $id_creneau <= 0) {
    flash('error', 'Paramètres manquants.');
    redirect(BASE_URL . 'catalogue.php');
}

$db          = get_db();
$id_sportif  = $user['id_profil'];

/* --- Chargement du service et du créneau (vérification préalable) --- */
$stmt = $db->prepare(
    'SELECT s.id_service, s.titre, s.description, s.duree, s.prix,
            c.nom AS categorie,
            u.prenom, u.nom AS nom_intervenant,
            pi.id_intervenant
     FROM service s
     JOIN profil_intervenant pi ON pi.id_intervenant = s.id_intervenant
     JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
     JOIN categorie c ON c.id_categorie = s.id_categorie
     WHERE s.id_service = :sid AND s.statut = "actif" AND pi.statut_validation = "valide"'
);
$stmt->execute([':sid' => $id_service]);
$service = $stmt->fetch();

if (!$service) {
    flash('error', 'Service introuvable ou indisponible.');
    redirect(BASE_URL . 'catalogue.php');
}

$stmt = $db->prepare(
    'SELECT id_creneau, date_debut, date_fin, statut, id_intervenant
     FROM creneau
     WHERE id_creneau = :cid AND id_intervenant = :iv'
);
$stmt->execute([':cid' => $id_creneau, ':iv' => $service['id_intervenant']]);
$creneau = $stmt->fetch();

if (!$creneau) {
    flash('error', 'Créneau introuvable.');
    redirect(BASE_URL . 'service_detail.php?id=' . $id_service);
}
if ($creneau['statut'] !== 'libre') {
    flash('error', 'Ce créneau n\'est plus disponible.');
    redirect(BASE_URL . 'service_detail.php?id=' . $id_service);
}
if (strtotime($creneau['date_debut']) <= time()) {
    flash('error', 'Ce créneau est déjà passé.');
    redirect(BASE_URL . 'service_detail.php?id=' . $id_service);
}

/* Vérif : le sportif n'a pas déjà réservé ce créneau */
$stmt = $db->prepare(
    'SELECT id_reservation FROM reservation
     WHERE id_sportif = :sp AND id_creneau = :cid AND statut = "confirmee"'
);
$stmt->execute([':sp' => $id_sportif, ':cid' => $id_creneau]);
if ($stmt->fetch()) {
    flash('error', 'Vous avez déjà une réservation sur ce créneau.');
    redirect(BASE_URL . 'service_detail.php?id=' . $id_service);
}

/* ============================================================
   TRAITEMENT POST — Règle 4.1 : transaction + SELECT FOR UPDATE
   ============================================================ */
if (isset($_POST['btn_confirmer'])) {
    csrf_check();

    $db->beginTransaction();
    try {
        /* 1. Verrouille le créneau pour éviter la double réservation */
        $stmt = $db->prepare(
            'SELECT statut FROM creneau
             WHERE id_creneau = :cid AND id_intervenant = :iv
             FOR UPDATE'
        );
        $stmt->execute([':cid' => $id_creneau, ':iv' => $service['id_intervenant']]);
        $row = $stmt->fetch();

        /* 2. Vérifie que le créneau est toujours libre (une autre transaction
           concurrente aurait pu le réserver entre-temps) */
        if (!$row || $row['statut'] !== 'libre') {
            $db->rollBack();
            flash('error', 'Désolé, ce créneau vient d\'être réservé par quelqu\'un d\'autre. Choisissez un autre créneau.');
            redirect(BASE_URL . 'service_detail.php?id=' . $id_service);
        }

        /* 3. Passe le créneau à "réservé" */
        $stmt = $db->prepare(
            'UPDATE creneau SET statut = "reserve"
             WHERE id_creneau = :cid'
        );
        $stmt->execute([':cid' => $id_creneau]);

        /* 4. Crée la réservation */
        $stmt = $db->prepare(
            'INSERT INTO reservation (id_sportif, id_service, id_creneau, statut)
             VALUES (:sp, :srv, :cid, "confirmee")'
        );
        $stmt->execute([
            ':sp'  => $id_sportif,
            ':srv' => $id_service,
            ':cid' => $id_creneau,
        ]);
        $id_reservation = (int)$db->lastInsertId();

        /* 5. Notification sportif */
        $msg_sportif = 'Votre réservation pour « ' . $service['titre'] . ' » le '
            . date('d/m/Y à H:i', strtotime($creneau['date_debut'])) . ' est confirmée.';
        $stmt = $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message)
             VALUES (:id_u, "success", :msg)'
        );
        $stmt->execute([':id_u' => $user['id_utilisateur'], ':msg' => $msg_sportif]);

        /* 6. Notification intervenant */
        $stmt_int = $db->prepare(
            'SELECT id_utilisateur FROM profil_intervenant WHERE id_intervenant = :iv'
        );
        $stmt_int->execute([':iv' => $service['id_intervenant']]);
        $row_int = $stmt_int->fetch();
        if ($row_int) {
            $msg_int = $user['prenom'] . ' ' . $user['nom'] . ' a réservé votre service « '
                . $service['titre'] . ' » le ' . date('d/m/Y à H:i', strtotime($creneau['date_debut'])) . '.';
            $stmt = $db->prepare(
                'INSERT INTO notification (id_utilisateur, type, message)
                 VALUES (:id_u, "info", :msg)'
            );
            $stmt->execute([':id_u' => $row_int['id_utilisateur'], ':msg' => $msg_int]);
        }

        $db->commit();

        flash('success', 'Réservation confirmée ! Rendez-vous le '
            . date('d/m/Y à H:i', strtotime($creneau['date_debut'])) . '.');
        redirect(BASE_URL . 'mes_reservations.php');

    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Une erreur est survenue lors de la réservation. Veuillez réessayer.');
        redirect(BASE_URL . 'service_detail.php?id=' . $id_service);
    }
}

require_once 'includes/header.php';
?>

<nav aria-label="breadcrumb" class="mb-4">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="catalogue.php">Catalogue</a></li>
    <li class="breadcrumb-item"><a href="service_detail.php?id=<?= $id_service ?>">
      <?= e($service['titre']) ?></a></li>
    <li class="breadcrumb-item active">Réservation</li>
  </ol>
</nav>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">

    <div class="card shadow-sm mb-4">
      <div class="card-header bg-success text-white fw-semibold">
        ✅ Confirmer la réservation
      </div>
      <div class="card-body">

        <!-- Récapitulatif -->
        <div class="mb-4">
          <h5 class="fw-bold"><?= e($service['titre']) ?></h5>
          <p class="text-muted small mb-2">
            <span class="badge bg-success bg-opacity-10 text-success"><?= e($service['categorie']) ?></span>
          </p>

          <div class="table-responsive">
            <table class="table table-borderless table-sm mb-0">
              <tr>
                <td class="text-muted small">Intervenant</td>
                <td class="fw-semibold"><?= e($service['prenom'] . ' ' . $service['nom_intervenant']) ?></td>
              </tr>
              <tr>
                <td class="text-muted small">Date</td>
                <td class="fw-semibold">
                  <?= date('l d F Y', strtotime($creneau['date_debut'])) ?>
                </td>
              </tr>
              <tr>
                <td class="text-muted small">Horaire</td>
                <td class="fw-semibold">
                  <?= date('H:i', strtotime($creneau['date_debut'])) ?>
                  &ndash; <?= date('H:i', strtotime($creneau['date_fin'])) ?>
                </td>
              </tr>
              <tr>
                <td class="text-muted small">Durée</td>
                <td><?= (int)$service['duree'] ?> min</td>
              </tr>
              <tr class="border-top">
                <td class="text-muted small fw-semibold">Total</td>
                <td class="fw-bold text-success fs-5">
                  <?= number_format($service['prix'], 2, ',', '') ?> €
                </td>
              </tr>
            </table>
          </div>
        </div>

        <div class="alert alert-info small">
          <strong>Politique d'annulation :</strong>
          Annulation gratuite jusqu'à 24h avant le rendez-vous.
          Au-delà, l'annulation reste possible mais sera signalée comme tardive.
        </div>

        <form method="post">
          <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
          <input type="hidden" name="id_service"  value="<?= $id_service ?>">
          <input type="hidden" name="id_creneau"  value="<?= $id_creneau ?>">

          <div class="d-grid gap-2">
            <button type="submit" name="btn_confirmer" class="btn btn-success btn-lg">
              Confirmer la réservation
            </button>
            <a href="service_detail.php?id=<?= $id_service ?>"
               class="btn btn-outline-secondary">
              Annuler
            </a>
          </div>
        </form>

      </div>
    </div>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
