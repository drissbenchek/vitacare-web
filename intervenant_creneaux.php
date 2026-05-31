<?php
$page_title = 'Mes créneaux';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_role('intervenant');

$user = current_user();
if ($user['statut_validation'] !== 'valide') {
    flash('error', 'Votre compte doit être validé pour gérer vos créneaux.');
    redirect(BASE_URL . 'profil.php');
}

$db             = get_db();
$id_intervenant = $user['id_profil'];
$errors         = [];

/* --- Traitement POST : ajouter un créneau --- */
if (isset($_POST['btn_ajouter'])) {
    csrf_check();

    $date_debut = trim($_POST['date_debut'] ?? '');
    $date_fin   = trim($_POST['date_fin']   ?? '');

    if ($date_debut === '') $errors[] = 'La date de début est obligatoire.';
    if ($date_fin   === '') $errors[] = 'La date de fin est obligatoire.';

    if (empty($errors) && strtotime($date_debut) >= strtotime($date_fin)) {
        $errors[] = 'La date de fin doit être après la date de début.';
    }
    if (empty($errors) && strtotime($date_debut) <= time()) {
        $errors[] = 'Le créneau doit être dans le futur.';
    }

    /* Vérification de chevauchement avec un créneau existant */
    if (empty($errors)) {
        $stmt = $db->prepare(
            'SELECT id_creneau FROM creneau
             WHERE id_intervenant = :iv AND statut != "annule"
             AND date_debut < :fin AND date_fin > :debut'
        );
        $stmt->execute([':iv' => $id_intervenant, ':fin' => $date_fin, ':debut' => $date_debut]);
        if ($stmt->fetch()) {
            $errors[] = 'Ce créneau chevauche un créneau existant.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            'INSERT INTO creneau (id_intervenant, date_debut, date_fin, statut)
             VALUES (:iv, :debut, :fin, "libre")'
        );
        $stmt->execute([':iv' => $id_intervenant, ':debut' => $date_debut, ':fin' => $date_fin]);
        flash('success', 'Créneau ajouté.');
        redirect(BASE_URL . 'intervenant_creneaux.php');
    }
}

/* --- Traitement POST : annuler un créneau --- */
if (isset($_POST['btn_annuler'])) {
    csrf_check();
    $id_creneau = (int)($_POST['id_creneau'] ?? 0);

    /* Vérifie que le créneau appartient à cet intervenant */
    $stmt = $db->prepare(
        'SELECT statut FROM creneau WHERE id_creneau = :id AND id_intervenant = :iv'
    );
    $stmt->execute([':id' => $id_creneau, ':iv' => $id_intervenant]);
    $creneau = $stmt->fetch();

    if (!$creneau) {
        flash('error', 'Créneau introuvable.');
    } elseif ($creneau['statut'] === 'reserve') {
        flash('error', 'Impossible d\'annuler un créneau déjà réservé. Annulez d\'abord la réservation.');
    } else {
        $stmt = $db->prepare(
            'UPDATE creneau SET statut = "annule" WHERE id_creneau = :id AND id_intervenant = :iv'
        );
        $stmt->execute([':id' => $id_creneau, ':iv' => $id_intervenant]);
        flash('success', 'Créneau annulé.');
    }
    redirect(BASE_URL . 'intervenant_creneaux.php');
}

/* --- Traitement POST : supprimer un créneau libre --- */
if (isset($_POST['btn_supprimer'])) {
    csrf_check();
    $id_creneau = (int)($_POST['id_creneau'] ?? 0);

    $stmt = $db->prepare(
        'DELETE FROM creneau WHERE id_creneau = :id AND id_intervenant = :iv AND statut = "libre"'
    );
    $stmt->execute([':id' => $id_creneau, ':iv' => $id_intervenant]);
    flash('success', 'Créneau supprimé.');
    redirect(BASE_URL . 'intervenant_creneaux.php');
}

/* --- Chargement des créneaux --- */
$stmt = $db->prepare(
    'SELECT c.id_creneau, c.date_debut, c.date_fin, c.statut,
            r.id_reservation,
            u.prenom AS prenom_sportif, u.nom AS nom_sportif
     FROM creneau c
     LEFT JOIN reservation r ON r.id_creneau = c.id_creneau AND r.statut = "confirmee"
     LEFT JOIN profil_sportif ps ON ps.id_sportif = r.id_sportif
     LEFT JOIN utilisateur u ON u.id_utilisateur = ps.id_utilisateur
     WHERE c.id_intervenant = :iv
     ORDER BY c.date_debut DESC'
);
$stmt->execute([':iv' => $id_intervenant]);
$creneaux = $stmt->fetchAll();

/* Filtre actif par défaut : futurs seulement */
$filtre   = $_GET['filtre'] ?? 'futur';
$now      = date('Y-m-d H:i:s');

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Mes créneaux</h1>
  <button class="btn btn-success" type="button"
          data-bs-toggle="collapse" data-bs-target="#formAjouter">
    + Nouveau créneau
  </button>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Formulaire ajout créneau -->
<div class="collapse mb-4" id="formAjouter">
  <div class="card shadow-sm">
    <div class="card-header bg-success text-white fw-semibold">Nouveau créneau</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Date et heure de début <span class="text-danger">*</span></label>
            <input type="datetime-local" name="date_debut" class="form-control"
                   value="<?= isset($_POST['date_debut']) ? e($_POST['date_debut']) : '' ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Date et heure de fin <span class="text-danger">*</span></label>
            <input type="datetime-local" name="date_fin" class="form-control"
                   value="<?= isset($_POST['date_fin']) ? e($_POST['date_fin']) : '' ?>" required>
          </div>
        </div>
        <button type="submit" name="btn_ajouter" class="btn btn-success mt-3">
          Créer le créneau
        </button>
      </form>
    </div>
  </div>
</div>

<!-- Filtre -->
<div class="btn-group mb-3" role="group">
  <a href="?filtre=futur"  class="btn btn-sm <?= $filtre === 'futur'  ? 'btn-success' : 'btn-outline-success' ?>">À venir</a>
  <a href="?filtre=tous"   class="btn btn-sm <?= $filtre === 'tous'   ? 'btn-success' : 'btn-outline-success' ?>">Tous</a>
  <a href="?filtre=passe"  class="btn btn-sm <?= $filtre === 'passe'  ? 'btn-success' : 'btn-outline-success' ?>">Passés</a>
</div>

<!-- Tableau des créneaux -->
<?php
$creneaux_filtres = array_filter($creneaux, function($c) use ($filtre, $now) {
    if ($filtre === 'futur')  return $c['date_debut'] >= $now;
    if ($filtre === 'passe')  return $c['date_debut'] <  $now;
    return true;
});
?>

<?php if ($creneaux_filtres): ?>
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Début</th>
          <th>Fin</th>
          <th>Statut</th>
          <th>Sportif</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($creneaux_filtres as $c): ?>
          <tr>
            <td><?= date('D d/m/Y H:i', strtotime($c['date_debut'])) ?></td>
            <td><?= date('H:i', strtotime($c['date_fin'])) ?></td>
            <td>
              <?php
              $badge = ['libre' => 'bg-success', 'reserve' => 'bg-danger', 'annule' => 'bg-secondary'];
              $label = ['libre' => 'Libre', 'reserve' => 'Réservé', 'annule' => 'Annulé'];
              ?>
              <span class="badge <?= $badge[$c['statut']] ?? 'bg-secondary' ?>">
                <?= $label[$c['statut']] ?? e($c['statut']) ?>
              </span>
            </td>
            <td class="small">
              <?= $c['prenom_sportif'] ? e($c['prenom_sportif'] . ' ' . $c['nom_sportif']) : '—' ?>
            </td>
            <td class="text-end">
              <?php if ($c['statut'] === 'libre' && $c['date_debut'] >= $now): ?>
                <form method="post" class="d-inline"
                      onsubmit="return confirm('Annuler ce créneau ?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id_creneau" value="<?= (int)$c['id_creneau'] ?>">
                  <button type="submit" name="btn_annuler"
                          class="btn btn-sm btn-outline-warning">Annuler</button>
                </form>
                <form method="post" class="d-inline ms-1"
                      onsubmit="return confirm('Supprimer définitivement ce créneau ?')">
                  <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                  <input type="hidden" name="id_creneau" value="<?= (int)$c['id_creneau'] ?>">
                  <button type="submit" name="btn_supprimer"
                          class="btn btn-sm btn-outline-danger">Supprimer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php else: ?>
  <div class="text-center py-5 text-muted">
    <div class="fs-1 mb-3">📅</div>
    <p>Aucun créneau dans cette période.</p>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
