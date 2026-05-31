<?php
$page_title = 'Ateliers & programmes';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();

$user = current_user();
$db   = get_db();

/* Filtres GET */
$filtre_cat = (int)($_GET['id_categorie'] ?? 0);

/* Catégories pour le filtre */
$categories = $db->query('SELECT id_categorie, nom FROM categorie ORDER BY nom')->fetchAll();

/* Inscriptions actuelles du sportif (pour afficher "Déjà inscrit") */
$inscriptions_sportif = [];
if ($user && $user['role'] === 'sportif') {
    $stmt = $db->prepare(
        'SELECT id_activite FROM inscription
         WHERE id_sportif = :sp AND statut = "confirmee"'
    );
    $stmt->execute([':sp' => $user['id_profil']]);
    foreach ($stmt->fetchAll() as $row) {
        $inscriptions_sportif[$row['id_activite']] = true;
    }
}

/* Récupération des ateliers */
$where  = ['a.date_debut > NOW()', 'pi.statut_validation = "valide"'];
$params = [];
if ($filtre_cat > 0) {
    $where[]       = 'a.id_categorie = :cat';
    $params[':cat'] = $filtre_cat;
}

$sql = 'SELECT a.id_activite, a.titre, a.description, a.date_debut, a.date_fin,
               a.capacite_max, a.places_reservees, a.lieu, a.prix,
               c.nom AS categorie, c.icone,
               u.prenom, u.nom AS nom_intervenant
        FROM activite a
        JOIN profil_intervenant pi ON pi.id_intervenant = a.id_intervenant
        JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
        JOIN categorie c ON c.id_categorie = a.id_categorie
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.date_debut';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$activites = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">Ateliers &amp; programmes</h1>
</div>

<!-- Filtres catégorie -->
<div class="mb-4 d-flex flex-wrap gap-2">
  <a href="activites.php"
     class="btn btn-sm <?= $filtre_cat === 0 ? 'btn-success' : 'btn-outline-success' ?>">
    Tous
  </a>
  <?php foreach ($categories as $cat): ?>
    <a href="activites.php?id_categorie=<?= (int)$cat['id_categorie'] ?>"
       class="btn btn-sm <?= $filtre_cat === (int)$cat['id_categorie'] ? 'btn-success' : 'btn-outline-success' ?>">
      <?= e($cat['nom']) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($activites): ?>
  <div class="row g-4">
    <?php foreach ($activites as $a):
      $places_restantes = $a['capacite_max'] - $a['places_reservees'];
      $complet          = $places_restantes <= 0;
      $deja_inscrit     = isset($inscriptions_sportif[$a['id_activite']]);
    ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm <?= $complet ? 'opacity-75' : '' ?>">
          <div class="card-body d-flex flex-column">

            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-start mb-2">
              <span class="fs-3"><?= $a['icone'] ?></span>
              <?php if ($complet): ?>
                <span class="badge bg-danger">Complet</span>
              <?php elseif ($places_restantes <= 3): ?>
                <span class="badge bg-warning text-dark">Dernières places</span>
              <?php else: ?>
                <span class="badge bg-success"><?= $places_restantes ?> place<?= $places_restantes > 1 ? 's' : '' ?></span>
              <?php endif; ?>
            </div>

            <h5 class="fw-semibold mb-1"><?= e($a['titre']) ?></h5>
            <p class="mb-1">
              <span class="badge bg-success bg-opacity-10 text-success small"><?= e($a['categorie']) ?></span>
            </p>

            <?php if ($a['description']): ?>
              <p class="text-muted small mb-2" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden">
                <?= e($a['description']) ?>
              </p>
            <?php endif; ?>

            <ul class="list-unstyled small text-muted mb-auto">
              <li>&#128197; <?= date('D d M Y', strtotime($a['date_debut'])) ?></li>
              <li>&#128336; <?= date('H:i', strtotime($a['date_debut'])) ?> &ndash; <?= date('H:i', strtotime($a['date_fin'])) ?></li>
              <?php if ($a['lieu']): ?>
                <li>&#128205; <?= e($a['lieu']) ?></li>
              <?php endif; ?>
              <li>&#128100; <?= e($a['prenom'] . ' ' . $a['nom_intervenant']) ?></li>
              <li>&#128202; <?= $a['places_reservees'] ?>/<?= $a['capacite_max'] ?> inscrits</li>
            </ul>

            <div class="d-flex justify-content-between align-items-center mt-3">
              <span class="fw-bold text-success">
                <?= $a['prix'] > 0 ? number_format($a['prix'], 2, ',', '') . ' €' : 'Gratuit' ?>
              </span>

              <?php if ($deja_inscrit): ?>
                <div class="d-flex gap-1">
                  <span class="btn btn-sm btn-success disabled">&#10003; Inscrit</span>
                  <form method="post" action="inscrire_activite.php"
                        onsubmit="return confirm('Se désinscrire de cet atelier ?')">
                    <input type="hidden" name="csrf_token"   value="<?= csrf_token() ?>">
                    <input type="hidden" name="id_activite"  value="<?= (int)$a['id_activite'] ?>">
                    <input type="hidden" name="action"       value="desinscrire">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Annuler</button>
                  </form>
                </div>
              <?php elseif (!$user): ?>
                <a href="connexion.php" class="btn btn-sm btn-outline-success">Se connecter</a>
              <?php elseif ($user['role'] === 'sportif' && !$complet): ?>
                <a href="inscrire_activite.php?id=<?= (int)$a['id_activite'] ?>"
                   class="btn btn-sm btn-success">S'inscrire</a>
              <?php elseif ($complet): ?>
                <button class="btn btn-sm btn-secondary" disabled>Complet</button>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: ?>
  <div class="text-center py-5 text-muted">
    <div class="fs-1 mb-3">&#127939;</div>
    <p>Aucun atelier disponible pour le moment.</p>
    <?php if ($filtre_cat): ?>
      <a href="activites.php" class="btn btn-outline-success btn-sm">Voir tous les ateliers</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
