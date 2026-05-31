<?php
/* ============================================================
   Inscription / désinscription à un atelier — Règle 4.3
   places_reservees >= capacite_max → refus
   Incrément/décrément en transaction
   ============================================================ */
$page_title = 'Inscription à l\'atelier';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user = current_user();
if ($user['role'] !== 'sportif') {
    flash('error', 'Seuls les sportifs peuvent s\'inscrire aux ateliers.');
    redirect(BASE_URL . 'activites.php');
}

$id_sportif  = $user['id_profil'];
$db          = get_db();

/* ============================================================
   POST : désinscription (depuis activites.php)
   ============================================================ */
if (isset($_POST['action']) && $_POST['action'] === 'desinscrire') {
    csrf_check();
    $id_activite = (int)($_POST['id_activite'] ?? 0);

    $stmt = $db->prepare(
        'SELECT id_inscription FROM inscription
         WHERE id_sportif = :sp AND id_activite = :act AND statut = "confirmee"'
    );
    $stmt->execute([':sp' => $id_sportif, ':act' => $id_activite]);
    $inscr = $stmt->fetch();

    if (!$inscr) {
        flash('error', 'Inscription introuvable.');
        redirect(BASE_URL . 'activites.php');
    }

    $db->beginTransaction();
    try {
        /* Annule l'inscription */
        $stmt = $db->prepare(
            'UPDATE inscription SET statut = "annulee" WHERE id_inscription = :id'
        );
        $stmt->execute([':id' => $inscr['id_inscription']]);

        /* Décrémente le compteur de places (règle 4.3) */
        $stmt = $db->prepare(
            'UPDATE activite SET places_reservees = places_reservees - 1
             WHERE id_activite = :act AND places_reservees > 0'
        );
        $stmt->execute([':act' => $id_activite]);

        /* Notification */
        $stmt = $db->prepare(
            'SELECT titre FROM activite WHERE id_activite = :id'
        );
        $stmt->execute([':id' => $id_activite]);
        $titre = $stmt->fetchColumn();

        $stmt = $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message)
             VALUES (:id_u, "warning", :msg)'
        );
        $stmt->execute([
            ':id_u' => $user['id_utilisateur'],
            ':msg'  => 'Vous avez annulé votre inscription à « ' . $titre . ' ».',
        ]);

        $db->commit();
        flash('success', 'Désinscription effectuée.');
    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Erreur lors de la désinscription.');
    }
    redirect(BASE_URL . 'activites.php');
}

/* ============================================================
   GET : page de confirmation d'inscription
   ============================================================ */
$id_activite = (int)($_GET['id'] ?? $_POST['id_activite'] ?? 0);
if ($id_activite <= 0) {
    flash('error', 'Atelier introuvable.');
    redirect(BASE_URL . 'activites.php');
}

/* Chargement de l'atelier */
$stmt = $db->prepare(
    'SELECT a.id_activite, a.titre, a.description, a.date_debut, a.date_fin,
            a.capacite_max, a.places_reservees, a.lieu, a.prix,
            c.nom AS categorie,
            u.prenom, u.nom AS nom_intervenant,
            pi.id_utilisateur AS id_utilisateur_intervenant
     FROM activite a
     JOIN profil_intervenant pi ON pi.id_intervenant = a.id_intervenant
     JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
     JOIN categorie c ON c.id_categorie = a.id_categorie
     WHERE a.id_activite = :id'
);
$stmt->execute([':id' => $id_activite]);
$activite = $stmt->fetch();

if (!$activite) {
    flash('error', 'Atelier introuvable.');
    redirect(BASE_URL . 'activites.php');
}
if ($activite['date_debut'] <= date('Y-m-d H:i:s')) {
    flash('error', 'Cet atelier est déjà passé.');
    redirect(BASE_URL . 'activites.php');
}

/* Vérifie si déjà inscrit */
$stmt = $db->prepare(
    'SELECT id_inscription FROM inscription
     WHERE id_sportif = :sp AND id_activite = :act AND statut = "confirmee"'
);
$stmt->execute([':sp' => $id_sportif, ':act' => $id_activite]);
if ($stmt->fetch()) {
    flash('error', 'Vous êtes déjà inscrit à cet atelier.');
    redirect(BASE_URL . 'activites.php');
}

/* ============================================================
   POST : confirmation d'inscription — Règle 4.3
   ============================================================ */
if (isset($_POST['btn_inscrire'])) {
    csrf_check();

    $db->beginTransaction();
    try {
        /* Relit places_reservees en transaction pour éviter la concurrence */
        $stmt = $db->prepare(
            'SELECT capacite_max, places_reservees FROM activite
             WHERE id_activite = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $id_activite]);
        $row = $stmt->fetch();

        /* Règle 4.3 : refuse si complet */
        if ($row['places_reservees'] >= $row['capacite_max']) {
            $db->rollBack();
            flash('error', 'Désolé, l\'atelier est complet. Inscription refusée.');
            redirect(BASE_URL . 'activites.php');
        }

        /* Crée l'inscription */
        $stmt = $db->prepare(
            'INSERT INTO inscription (id_sportif, id_activite, statut)
             VALUES (:sp, :act, "confirmee")'
        );
        $stmt->execute([':sp' => $id_sportif, ':act' => $id_activite]);

        /* Incrémente le compteur de places (règle 4.3) */
        $stmt = $db->prepare(
            'UPDATE activite SET places_reservees = places_reservees + 1
             WHERE id_activite = :id'
        );
        $stmt->execute([':id' => $id_activite]);

        /* Notification sportif */
        $stmt = $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message)
             VALUES (:id_u, "success", :msg)'
        );
        $stmt->execute([
            ':id_u' => $user['id_utilisateur'],
            ':msg'  => 'Votre inscription à « ' . $activite['titre'] . ' » le '
                . date('d/m/Y à H:i', strtotime($activite['date_debut']))
                . ' est confirmée.',
        ]);

        /* Notification intervenant */
        $stmt = $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message)
             VALUES (:id_u, "info", :msg)'
        );
        $stmt->execute([
            ':id_u' => $activite['id_utilisateur_intervenant'],
            ':msg'  => $user['prenom'] . ' ' . $user['nom']
                . ' s\'est inscrit(e) à votre atelier « ' . $activite['titre'] . ' ».',
        ]);

        $db->commit();
        flash('success', 'Inscription confirmée pour « ' . $activite['titre'] . ' » !');
        redirect(BASE_URL . 'activites.php');

    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Erreur lors de l\'inscription. Veuillez réessayer.');
        redirect(BASE_URL . 'activites.php');
    }
}

require_once 'includes/header.php';

$places_restantes = $activite['capacite_max'] - $activite['places_reservees'];
?>

<nav aria-label="breadcrumb" class="mb-4">
  <ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="activites.php">Ateliers</a></li>
    <li class="breadcrumb-item active">Inscription</li>
  </ol>
</nav>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-success text-white fw-semibold">
        &#10003; Confirmer l'inscription
      </div>
      <div class="card-body">

        <h5 class="fw-bold mb-3"><?= e($activite['titre']) ?></h5>

        <table class="table table-borderless table-sm mb-3">
          <tr>
            <td class="text-muted small">Intervenant</td>
            <td class="fw-semibold"><?= e($activite['prenom'] . ' ' . $activite['nom_intervenant']) ?></td>
          </tr>
          <tr>
            <td class="text-muted small">Date</td>
            <td class="fw-semibold"><?= date('l d F Y', strtotime($activite['date_debut'])) ?></td>
          </tr>
          <tr>
            <td class="text-muted small">Horaire</td>
            <td><?= date('H:i', strtotime($activite['date_debut'])) ?> &ndash; <?= date('H:i', strtotime($activite['date_fin'])) ?></td>
          </tr>
          <?php if ($activite['lieu']): ?>
          <tr>
            <td class="text-muted small">Lieu</td>
            <td><?= e($activite['lieu']) ?></td>
          </tr>
          <?php endif; ?>
          <tr>
            <td class="text-muted small">Places</td>
            <td>
              <span class="<?= $places_restantes <= 3 ? 'text-danger fw-bold' : 'text-success' ?>">
                <?= $places_restantes ?> place<?= $places_restantes > 1 ? 's' : '' ?> restante<?= $places_restantes > 1 ? 's' : '' ?>
              </span>
              <span class="text-muted"> / <?= $activite['capacite_max'] ?></span>
            </td>
          </tr>
          <tr class="border-top">
            <td class="text-muted small fw-semibold">Tarif</td>
            <td class="fw-bold text-success fs-5">
              <?= $activite['prix'] > 0 ? number_format($activite['prix'], 2, ',', '') . ' €' : 'Gratuit' ?>
            </td>
          </tr>
        </table>

        <?php if ($activite['description']): ?>
          <p class="text-muted small mb-3"><?= nl2br(e($activite['description'])) ?></p>
        <?php endif; ?>

        <?php if ($places_restantes <= 0): ?>
          <div class="alert alert-danger">Cet atelier est complet.</div>
          <a href="activites.php" class="btn btn-outline-secondary w-100">Retour aux ateliers</a>
        <?php else: ?>
          <form method="post">
            <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
            <input type="hidden" name="id_activite" value="<?= $id_activite ?>">
            <div class="d-grid gap-2">
              <button type="submit" name="btn_inscrire" class="btn btn-success btn-lg">
                Confirmer l'inscription
              </button>
              <a href="activites.php" class="btn btn-outline-secondary">Annuler</a>
            </div>
          </form>
        <?php endif; ?>

      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
