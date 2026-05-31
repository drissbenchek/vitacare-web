<?php
$page_title = 'Mes services';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_role('intervenant');

$user = current_user();

/* Seul un intervenant validé peut gérer ses services */
if ($user['statut_validation'] !== 'valide') {
    flash('error', 'Votre compte doit être validé par un administrateur avant de pouvoir gérer vos services.');
    redirect(BASE_URL . 'profil.php');
}

$db            = get_db();
$id_intervenant = $user['id_profil'];
$errors         = [];

/* --- Chargement des catégories --- */
$categories = $db->query('SELECT id_categorie, nom FROM categorie ORDER BY nom')->fetchAll();

/* --- Traitement POST : ajouter un service --- */
if (isset($_POST['btn_ajouter'])) {
    csrf_check();

    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $id_cat      = (int)($_POST['id_categorie'] ?? 0);
    $duree       = (int)($_POST['duree']        ?? 0);
    $prix        = (float)($_POST['prix']       ?? 0);

    if ($titre === '')   $errors[] = 'Le titre est obligatoire.';
    if ($id_cat <= 0)   $errors[] = 'La catégorie est obligatoire.';
    if ($duree <= 0)    $errors[] = 'La durée doit être supérieure à 0.';
    if ($prix < 0)      $errors[] = 'Le prix ne peut pas être négatif.';

    if (empty($errors)) {
        $stmt = $db->prepare(
            'INSERT INTO service (id_intervenant, id_categorie, titre, description, duree, prix, statut)
             VALUES (:iv, :cat, :titre, :desc, :duree, :prix, "actif")'
        );
        $stmt->execute([
            ':iv'    => $id_intervenant,
            ':cat'   => $id_cat,
            ':titre' => $titre,
            ':desc'  => $description ?: null,
            ':duree' => $duree,
            ':prix'  => $prix,
        ]);
        flash('success', 'Service « ' . $titre . ' » créé avec succès.');
        redirect(BASE_URL . 'intervenant_services.php');
    }
}

/* --- Traitement POST : modifier un service --- */
if (isset($_POST['btn_modifier'])) {
    csrf_check();

    $id_service  = (int)($_POST['id_service']   ?? 0);
    $titre       = trim($_POST['titre']         ?? '');
    $description = trim($_POST['description']   ?? '');
    $id_cat      = (int)($_POST['id_categorie'] ?? 0);
    $duree       = (int)($_POST['duree']        ?? 0);
    $prix        = (float)($_POST['prix']       ?? 0);
    $statut      = in_array($_POST['statut'] ?? '', ['actif', 'inactif']) ? $_POST['statut'] : 'actif';

    if ($titre === '')  $errors[] = 'Le titre est obligatoire.';
    if ($id_cat <= 0)  $errors[] = 'La catégorie est obligatoire.';
    if ($duree <= 0)   $errors[] = 'La durée doit être supérieure à 0.';

    if (empty($errors)) {
        /* Vérifie que le service appartient bien à cet intervenant */
        $stmt = $db->prepare(
            'UPDATE service SET titre=:titre, description=:desc, id_categorie=:cat,
             duree=:duree, prix=:prix, statut=:statut
             WHERE id_service=:id AND id_intervenant=:iv'
        );
        $stmt->execute([
            ':titre'  => $titre,
            ':desc'   => $description ?: null,
            ':cat'    => $id_cat,
            ':duree'  => $duree,
            ':prix'   => $prix,
            ':statut' => $statut,
            ':id'     => $id_service,
            ':iv'     => $id_intervenant,
        ]);
        flash('success', 'Service mis à jour.');
        redirect(BASE_URL . 'intervenant_services.php');
    }
}

/* --- Traitement POST : supprimer un service --- */
if (isset($_POST['btn_supprimer'])) {
    csrf_check();
    $id_service = (int)($_POST['id_service'] ?? 0);

    /* Vérifie qu'il n'y a pas de réservations confirmées sur ce service */
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM reservation WHERE id_service = :id AND statut = "confirmee"'
    );
    $stmt->execute([':id' => $id_service]);
    if ((int)$stmt->fetchColumn() > 0) {
        flash('error', 'Impossible de supprimer un service avec des réservations confirmées.');
    } else {
        $stmt = $db->prepare(
            'DELETE FROM service WHERE id_service = :id AND id_intervenant = :iv'
        );
        $stmt->execute([':id' => $id_service, ':iv' => $id_intervenant]);
        flash('success', 'Service supprimé.');
    }
    redirect(BASE_URL . 'intervenant_services.php');
}

/* --- Chargement des services de l'intervenant --- */
$stmt = $db->prepare(
    'SELECT s.id_service, s.titre, s.description, s.duree, s.prix, s.statut,
            c.nom AS categorie, c.id_categorie,
            COUNT(r.id_reservation) AS nb_reservations
     FROM service s
     JOIN categorie c ON c.id_categorie = s.id_categorie
     LEFT JOIN reservation r ON r.id_service = s.id_service AND r.statut = "confirmee"
     WHERE s.id_intervenant = :iv
     GROUP BY s.id_service
     ORDER BY s.id_service DESC'
);
$stmt->execute([':iv' => $id_intervenant]);
$services = $stmt->fetchAll();

/* Service à éditer (si paramètre GET) */
$edit_id      = (int)($_GET['edit'] ?? 0);
$service_edit = null;
if ($edit_id > 0) {
    foreach ($services as $s) {
        if ((int)$s['id_service'] === $edit_id) {
            $service_edit = $s;
            break;
        }
    }
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Mes services</h1>
  <button class="btn btn-success" type="button"
          data-bs-toggle="collapse" data-bs-target="#formAjouter">
    + Nouveau service
  </button>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<!-- Formulaire : ajouter un service -->
<div class="collapse mb-4 <?= empty($errors) && !$service_edit ? '' : '' ?>" id="formAjouter">
  <div class="card shadow-sm">
    <div class="card-header bg-success text-white fw-semibold">Nouveau service</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <?php include '_form_service.inc.php'; ?>
        <button type="submit" name="btn_ajouter" class="btn btn-success">
          Créer le service
        </button>
      </form>
    </div>
  </div>
</div>

<?php if ($service_edit): ?>
<!-- Formulaire : modifier un service -->
<div class="card shadow-sm mb-4 border-warning">
  <div class="card-header bg-warning fw-semibold">
    Modifier : <?= e($service_edit['titre']) ?>
    <a href="intervenant_services.php" class="btn btn-sm btn-outline-secondary float-end">Annuler</a>
  </div>
  <div class="card-body">
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="id_service" value="<?= (int)$service_edit['id_service'] ?>">
      <?php include '_form_service.inc.php'; ?>
      <div class="mb-3">
        <label class="form-label">Statut</label>
        <select name="statut" class="form-select">
          <option value="actif"   <?= $service_edit['statut'] === 'actif'   ? 'selected' : '' ?>>Actif</option>
          <option value="inactif" <?= $service_edit['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
        </select>
      </div>
      <button type="submit" name="btn_modifier" class="btn btn-warning">Enregistrer</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Liste des services -->
<?php if ($services): ?>
  <div class="row g-3">
    <?php foreach ($services as $s): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm <?= $s['statut'] === 'inactif' ? 'opacity-75' : '' ?>">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="fw-semibold mb-0"><?= e($s['titre']) ?></h6>
              <span class="badge <?= $s['statut'] === 'actif' ? 'bg-success' : 'bg-secondary' ?> ms-2">
                <?= $s['statut'] ?>
              </span>
            </div>
            <p class="text-muted small mb-1">
              <span class="badge bg-light text-success border border-success"><?= e($s['categorie']) ?></span>
            </p>
            <p class="text-muted small mb-auto">
              ⏱ <?= (int)$s['duree'] ?> min &nbsp;·&nbsp;
              <?= number_format($s['prix'], 2, ',', '') ?> € &nbsp;·&nbsp;
              <?= (int)$s['nb_reservations'] ?> réservation<?= $s['nb_reservations'] > 1 ? 's' : '' ?>
            </p>
            <div class="d-flex gap-2 mt-3">
              <a href="?edit=<?= (int)$s['id_service'] ?>"
                 class="btn btn-sm btn-outline-warning flex-grow-1">Modifier</a>
              <form method="post" class="flex-grow-1"
                    onsubmit="return confirm('Supprimer ce service ?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id_service" value="<?= (int)$s['id_service'] ?>">
                <button type="submit" name="btn_supprimer"
                        class="btn btn-sm btn-outline-danger w-100"
                        <?= $s['nb_reservations'] > 0 ? 'disabled title="Réservations existantes"' : '' ?>>
                  Supprimer
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="text-center py-5 text-muted">
    <div class="fs-1 mb-3">📋</div>
    <p>Vous n'avez pas encore créé de service.</p>
    <button class="btn btn-success" type="button"
            data-bs-toggle="collapse" data-bs-target="#formAjouter">
      Créer mon premier service
    </button>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
