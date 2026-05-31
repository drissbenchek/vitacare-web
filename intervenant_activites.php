<?php
$page_title = 'Mes ateliers';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_role('intervenant');

$user = current_user();
if ($user['statut_validation'] !== 'valide') {
    flash('error', 'Votre compte doit être validé pour gérer vos ateliers.');
    redirect(BASE_URL . 'profil.php');
}

$db             = get_db();
$id_intervenant = $user['id_profil'];
$errors         = [];

$categories = $db->query('SELECT id_categorie, nom FROM categorie ORDER BY nom')->fetchAll();

/* --- Traitement POST : ajouter --- */
if (isset($_POST['btn_ajouter'])) {
    csrf_check();
    $errors = valider_form_activite($_POST);
    if (empty($errors)) {
        $stmt = $db->prepare(
            'INSERT INTO activite
             (id_intervenant, id_categorie, titre, description, date_debut, date_fin,
              capacite_max, lieu, prix)
             VALUES (:iv, :cat, :titre, :desc, :debut, :fin, :cap, :lieu, :prix)'
        );
        $stmt->execute(params_activite($_POST, $id_intervenant));
        flash('success', 'Atelier créé.');
        redirect(BASE_URL . 'intervenant_activites.php');
    }
}

/* --- Traitement POST : modifier --- */
if (isset($_POST['btn_modifier'])) {
    csrf_check();
    $id_activite = (int)($_POST['id_activite'] ?? 0);
    $errors      = valider_form_activite($_POST);

    /* Interdit si des inscrits existent et que la capacité diminue en-dessous */
    if (empty($errors)) {
        $stmt = $db->prepare('SELECT places_reservees FROM activite WHERE id_activite=:id AND id_intervenant=:iv');
        $stmt->execute([':id' => $id_activite, ':iv' => $id_intervenant]);
        $row = $stmt->fetch();
        if ($row && (int)$_POST['capacite_max'] < $row['places_reservees']) {
            $errors[] = 'La capacité ne peut pas être inférieure au nombre d\'inscrits actuels (' . $row['places_reservees'] . ').';
        }
    }

    if (empty($errors)) {
        $p = params_activite($_POST, $id_intervenant);
        $p[':id'] = $id_activite;
        $stmt = $db->prepare(
            'UPDATE activite SET id_categorie=:cat, titre=:titre, description=:desc,
             date_debut=:debut, date_fin=:fin, capacite_max=:cap, lieu=:lieu, prix=:prix
             WHERE id_activite=:id AND id_intervenant=:iv'
        );
        $stmt->execute($p);
        flash('success', 'Atelier mis à jour.');
        redirect(BASE_URL . 'intervenant_activites.php');
    }
}

/* --- Traitement POST : supprimer --- */
if (isset($_POST['btn_supprimer'])) {
    csrf_check();
    $id_activite = (int)($_POST['id_activite'] ?? 0);
    $stmt = $db->prepare(
        'SELECT places_reservees FROM activite WHERE id_activite=:id AND id_intervenant=:iv'
    );
    $stmt->execute([':id' => $id_activite, ':iv' => $id_intervenant]);
    $row = $stmt->fetch();
    if ($row && $row['places_reservees'] > 0) {
        flash('error', 'Impossible de supprimer un atelier avec des inscrits.');
    } else {
        $db->prepare('DELETE FROM activite WHERE id_activite=:id AND id_intervenant=:iv')
           ->execute([':id' => $id_activite, ':iv' => $id_intervenant]);
        flash('success', 'Atelier supprimé.');
    }
    redirect(BASE_URL . 'intervenant_activites.php');
}

/* --- Chargement des ateliers --- */
$stmt = $db->prepare(
    'SELECT a.id_activite, a.titre, a.date_debut, a.date_fin,
            a.capacite_max, a.places_reservees, a.lieu, a.prix,
            a.id_categorie, c.nom AS categorie
     FROM activite a
     JOIN categorie c ON c.id_categorie = a.id_categorie
     WHERE a.id_intervenant = :iv
     ORDER BY a.date_debut DESC'
);
$stmt->execute([':iv' => $id_intervenant]);
$activites = $stmt->fetchAll();

$edit_id      = (int)($_GET['edit'] ?? 0);
$activite_edit = null;
if ($edit_id > 0) {
    foreach ($activites as $a) {
        if ((int)$a['id_activite'] === $edit_id) { $activite_edit = $a; break; }
    }
}

/* ---- Fonctions helpers locales ---- */
function valider_form_activite(array $p): array {
    $errors = [];
    if (trim($p['titre'] ?? '') === '')    $errors[] = 'Le titre est obligatoire.';
    if ((int)($p['id_categorie'] ?? 0) <= 0) $errors[] = 'La catégorie est obligatoire.';
    if (empty($p['date_debut']))           $errors[] = 'La date de début est obligatoire.';
    if (empty($p['date_fin']))             $errors[] = 'La date de fin est obligatoire.';
    if (!empty($p['date_debut']) && !empty($p['date_fin']) &&
        strtotime($p['date_debut']) >= strtotime($p['date_fin'])) {
        $errors[] = 'La date de fin doit être après la date de début.';
    }
    if ((int)($p['capacite_max'] ?? 0) <= 0) $errors[] = 'La capacité doit être au moins 1.';
    if ((float)($p['prix'] ?? 0) < 0)        $errors[] = 'Le prix ne peut pas être négatif.';
    return $errors;
}

function params_activite(array $p, int $id_intervenant): array {
    return [
        ':iv'    => $id_intervenant,
        ':cat'   => (int)$p['id_categorie'],
        ':titre' => trim($p['titre']),
        ':desc'  => trim($p['description'] ?? '') ?: null,
        ':debut' => $p['date_debut'],
        ':fin'   => $p['date_fin'],
        ':cap'   => (int)$p['capacite_max'],
        ':lieu'  => trim($p['lieu'] ?? '') ?: null,
        ':prix'  => (float)($p['prix'] ?? 0),
    ];
}

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Mes ateliers</h1>
  <button class="btn btn-success" type="button"
          data-bs-toggle="collapse" data-bs-target="#formAjouter">
    + Nouvel atelier
  </button>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>

<!-- Formulaire ajout -->
<div class="collapse mb-4" id="formAjouter">
  <div class="card shadow-sm">
    <div class="card-header bg-success text-white fw-semibold">Nouvel atelier</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <?php include '_form_activite.inc.php'; ?>
        <button type="submit" name="btn_ajouter" class="btn btn-success">Créer l'atelier</button>
      </form>
    </div>
  </div>
</div>

<!-- Formulaire modification -->
<?php if ($activite_edit): ?>
  <div class="card shadow-sm mb-4 border-warning">
    <div class="card-header bg-warning fw-semibold">
      Modifier : <?= e($activite_edit['titre']) ?>
      <a href="intervenant_activites.php" class="btn btn-sm btn-outline-secondary float-end">Annuler</a>
    </div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
        <input type="hidden" name="id_activite"  value="<?= (int)$activite_edit['id_activite'] ?>">
        <?php include '_form_activite.inc.php'; ?>
        <button type="submit" name="btn_modifier" class="btn btn-warning">Enregistrer</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<!-- Liste -->
<?php if ($activites): ?>
  <div class="row g-3">
    <?php foreach ($activites as $a):
      $passe  = $a['date_debut'] < date('Y-m-d H:i:s');
      $complet = $a['places_reservees'] >= $a['capacite_max'];
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm <?= $passe ? 'opacity-60' : '' ?>">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between mb-2">
              <h6 class="fw-semibold mb-0"><?= e($a['titre']) ?></h6>
              <?php if ($passe): ?>
                <span class="badge bg-secondary">Passé</span>
              <?php elseif ($complet): ?>
                <span class="badge bg-danger">Complet</span>
              <?php else: ?>
                <span class="badge bg-success"><?= $a['capacite_max'] - $a['places_reservees'] ?> pl.</span>
              <?php endif; ?>
            </div>
            <p class="text-muted small mb-1">
              <span class="badge bg-light text-success border border-success"><?= e($a['categorie']) ?></span>
            </p>
            <p class="text-muted small mb-auto">
              &#128197; <?= date('d/m/Y H:i', strtotime($a['date_debut'])) ?><br>
              &#128202; <?= $a['places_reservees'] ?>/<?= $a['capacite_max'] ?> inscrits &nbsp;&middot;&nbsp;
              <?= $a['prix'] > 0 ? number_format($a['prix'], 2, ',', '') . ' €' : 'Gratuit' ?>
            </p>
            <div class="d-flex gap-2 mt-3">
              <a href="?edit=<?= (int)$a['id_activite'] ?>"
                 class="btn btn-sm btn-outline-warning flex-grow-1">Modifier</a>
              <form method="post" class="flex-grow-1"
                    onsubmit="return confirm('Supprimer cet atelier ?')">
                <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
                <input type="hidden" name="id_activite" value="<?= (int)$a['id_activite'] ?>">
                <button type="submit" name="btn_supprimer"
                        class="btn btn-sm btn-outline-danger w-100"
                        <?= $a['places_reservees'] > 0 ? 'disabled title="Inscrits existants"' : '' ?>>
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
    <div class="fs-1 mb-3">&#127939;</div>
    <p>Aucun atelier créé pour l'instant.</p>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
