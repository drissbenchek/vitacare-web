<?php
$page_title = 'Mes réservations';
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

/* Charge toutes les réservations */
$stmt = $db->prepare(
    'SELECT r.id_reservation, r.statut, r.date_reservation, r.type_annulation,
            s.id_service, s.titre AS titre_service, s.duree, s.prix,
            c.date_debut, c.date_fin,
            cat.nom AS categorie,
            u.prenom, u.nom AS nom_int
     FROM reservation r
     JOIN service s ON s.id_service = r.id_service
     JOIN creneau c ON c.id_creneau = r.id_creneau
     JOIN categorie cat ON cat.id_categorie = s.id_categorie
     JOIN profil_intervenant pi ON pi.id_intervenant = s.id_intervenant
     JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
     WHERE r.id_sportif = :sp
     ORDER BY c.date_debut DESC'
);
$stmt->execute([':sp' => $id_sportif]);
$toutes = $stmt->fetchAll();

$now = date('Y-m-d H:i:s');
$avenir  = array_filter($toutes, fn($r) => $r['date_debut'] >= $now && $r['statut'] === 'confirmee');
$passees = array_filter($toutes, fn($r) => $r['date_debut'] <  $now && $r['statut'] === 'confirmee');
$annulees = array_filter($toutes, fn($r) => in_array($r['statut'], ['annulee', 'annulation_tardive']));

require_once 'includes/header.php';
?>

<h1 class="h3 mb-4">&#128203; Mes réservations</h1>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'avenir'  ? 'active' : '' ?>" href="?tab=avenir">
      À venir <span class="badge bg-success ms-1"><?= count($avenir) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'passees' ? 'active' : '' ?>" href="?tab=passees">
      Passées <span class="badge bg-secondary ms-1"><?= count($passees) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'annulees' ? 'active' : '' ?>" href="?tab=annulees">
      Annulées <span class="badge bg-secondary ms-1"><?= count($annulees) ?></span>
    </a>
  </li>
</ul>

<?php
$affichees = match($onglet) {
    'passees'  => $passees,
    'annulees' => $annulees,
    default    => $avenir,
};
?>

<?php if ($affichees): ?>
  <div class="row g-3">
    <?php foreach ($affichees as $r):
      $heures = (strtotime($r['date_debut']) - time()) / 3600;
      $annulable = $r['statut'] === 'confirmee' && $r['date_debut'] >= $now;
    ?>
      <div class="col-md-6">
        <div class="card shadow-sm h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h6 class="fw-semibold mb-0"><?= e($r['titre_service']) ?></h6>
              <?php
              $badge = [
                  'confirmee'          => ['bg-success', 'Confirmée'],
                  'annulee'            => ['bg-secondary', 'Annulée'],
                  'annulation_tardive' => ['bg-warning text-dark', 'Annulation tardive'],
              ];
              $b = $badge[$r['statut']] ?? ['bg-secondary', $r['statut']];
              ?>
              <span class="badge <?= $b[0] ?> ms-2"><?= $b[1] ?></span>
            </div>

            <p class="text-muted small mb-1">
              <span class="badge bg-success bg-opacity-10 text-success"><?= e($r['categorie']) ?></span>
            </p>
            <p class="text-muted small mb-1">
              &#128197; <?= date('D d/m/Y', strtotime($r['date_debut'])) ?>
              &nbsp;&#128336; <?= date('H:i', strtotime($r['date_debut'])) ?> &ndash; <?= date('H:i', strtotime($r['date_fin'])) ?>
            </p>
            <p class="text-muted small mb-2">
              &#128100; <?= e($r['prenom'] . ' ' . $r['nom_int']) ?>
              &nbsp;&middot;&nbsp; <?= (int)$r['duree'] ?> min
              &nbsp;&middot;&nbsp; <strong><?= number_format($r['prix'], 2, ',', '') ?> €</strong>
            </p>

            <?php if ($r['type_annulation'] === 'tardive'): ?>
              <p class="text-warning small mb-2">⚠ Annulation enregistrée comme tardive.</p>
            <?php endif; ?>

            <?php if ($annulable): ?>
              <div class="mt-2">
                <?php if ($heures > 24): ?>
                  <a href="annuler_reservation.php?id_reservation=<?= (int)$r['id_reservation'] ?>"
                     class="btn btn-sm btn-outline-danger">
                    Annuler (gratuit)
                  </a>
                <?php else: ?>
                  <a href="annuler_reservation.php?id_reservation=<?= (int)$r['id_reservation'] ?>"
                     class="btn btn-sm btn-outline-warning">
                    Annuler (tardive — &lt; 24h)
                  </a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="text-center py-5 text-muted">
    <div class="fs-1 mb-3">&#128203;</div>
    <p>Aucune réservation dans cette catégorie.</p>
    <?php if ($onglet === 'avenir'): ?>
      <a href="catalogue.php" class="btn btn-success">Réserver une consultation</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
