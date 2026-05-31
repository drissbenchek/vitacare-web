<?php
$page_title = 'Mes ateliers';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user = current_user();
if ($user['role'] !== 'sportif') {
    flash('error', 'Accès réservé aux sportifs.');
    redirect(BASE_URL . 'index.php');
}

$db         = get_db();
$id_sportif = $user['id_profil'];
$onglet     = $_GET['tab'] ?? 'avenir';

$stmt = $db->prepare(
    'SELECT i.id_inscription, i.statut, i.date_inscription,
            a.id_activite, a.titre, a.date_debut, a.date_fin, a.lieu, a.prix,
            cat.nom AS categorie,
            u.prenom, u.nom AS nom_int
     FROM inscription i
     JOIN activite a ON a.id_activite = i.id_activite
     JOIN categorie cat ON cat.id_categorie = a.id_categorie
     JOIN profil_intervenant pi ON pi.id_intervenant = a.id_intervenant
     JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
     WHERE i.id_sportif = :sp
     ORDER BY a.date_debut DESC'
);
$stmt->execute([':sp' => $id_sportif]);
$toutes = $stmt->fetchAll();

$now     = date('Y-m-d H:i:s');
$avenir  = array_filter($toutes, fn($i) => $i['date_debut'] >= $now && $i['statut'] === 'confirmee');
$passes  = array_filter($toutes, fn($i) => $i['date_debut'] <  $now && $i['statut'] === 'confirmee');
$annulees = array_filter($toutes, fn($i) => $i['statut'] === 'annulee');

require_once 'includes/header.php';
?>

<h1 class="h3 mb-4">&#127939; Mes ateliers</h1>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'avenir'   ? 'active' : '' ?>" href="?tab=avenir">
      À venir <span class="badge bg-success ms-1"><?= count($avenir) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'passes'   ? 'active' : '' ?>" href="?tab=passes">
      Passés <span class="badge bg-secondary ms-1"><?= count($passes) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'annulees' ? 'active' : '' ?>" href="?tab=annulees">
      Annulés <span class="badge bg-secondary ms-1"><?= count($annulees) ?></span>
    </a>
  </li>
</ul>

<?php
$affichees = match($onglet) {
    'passes'   => $passes,
    'annulees' => $annulees,
    default    => $avenir,
};
?>

<?php if ($affichees): ?>
  <div class="row g-3">
    <?php foreach ($affichees as $i): ?>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="fw-semibold mb-0"><?= e($i['titre']) ?></h6>
              <span class="badge <?= $i['statut'] === 'confirmee' ? 'bg-success' : 'bg-secondary' ?> ms-2">
                <?= $i['statut'] === 'confirmee' ? 'Inscrit' : 'Annulé' ?>
              </span>
            </div>
            <p class="text-muted small mb-1">
              <span class="badge bg-success bg-opacity-10 text-success"><?= e($i['categorie']) ?></span>
            </p>
            <p class="text-muted small mb-1">
              &#128197; <?= date('D d/m/Y', strtotime($i['date_debut'])) ?>
              &nbsp;&#128336; <?= date('H:i', strtotime($i['date_debut'])) ?> &ndash; <?= date('H:i', strtotime($i['date_fin'])) ?>
            </p>
            <?php if ($i['lieu']): ?>
              <p class="text-muted small mb-1">&#128205; <?= e($i['lieu']) ?></p>
            <?php endif; ?>
            <p class="text-muted small mb-2">
              &#128100; <?= e($i['prenom'] . ' ' . $i['nom_int']) ?>
              &nbsp;&middot;&nbsp; <strong><?= $i['prix'] > 0 ? number_format($i['prix'], 2, ',', '') . ' €' : 'Gratuit' ?></strong>
            </p>

            <?php if ($i['statut'] === 'confirmee' && $i['date_debut'] >= $now): ?>
              <form method="post" action="inscrire_activite.php"
                    onsubmit="return confirm('Se désinscrire de cet atelier ?')">
                <input type="hidden" name="csrf_token"  value="<?= csrf_token() ?>">
                <input type="hidden" name="id_activite" value="<?= (int)$i['id_activite'] ?>">
                <input type="hidden" name="action"      value="desinscrire">
                <button type="submit" class="btn btn-sm btn-outline-danger">
                  Se désinscrire
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="text-center py-5 text-muted">
    <div class="fs-1 mb-3">&#127939;</div>
    <p>Aucun atelier dans cette catégorie.</p>
    <?php if ($onglet === 'avenir'): ?>
      <a href="activites.php" class="btn btn-success">Voir les ateliers</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
