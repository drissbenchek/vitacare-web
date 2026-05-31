<?php
$page_title = 'Administration';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_role('admin');

$db     = get_db();
$errors = [];

/* ============================================================
   Règle 4.5 — Validation / refus d'un intervenant
   Un intervenant ne peut publier que si statut_validation='valide'
   ============================================================ */
if (isset($_POST['btn_valider']) || isset($_POST['btn_refuser'])) {
    csrf_check();

    $id_intervenant = (int)($_POST['id_intervenant'] ?? 0);
    $nouveau_statut = isset($_POST['btn_valider']) ? 'valide' : 'refuse';

    $stmt = $db->prepare(
        'SELECT pi.id_intervenant, pi.id_utilisateur, u.prenom, u.nom
         FROM profil_intervenant pi
         JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
         WHERE pi.id_intervenant = :id'
    );
    $stmt->execute([':id' => $id_intervenant]);
    $intervenant = $stmt->fetch();

    if ($intervenant) {
        $db->prepare(
            'UPDATE profil_intervenant SET statut_validation = :statut
             WHERE id_intervenant = :id'
        )->execute([':statut' => $nouveau_statut, ':id' => $id_intervenant]);

        /* Notification à l'intervenant */
        $msg = $nouveau_statut === 'valide'
            ? 'Votre compte intervenant a été validé. Vous pouvez maintenant publier vos services.'
            : 'Votre demande de compte intervenant a été refusée. Contactez l\'administration.';
        $type = $nouveau_statut === 'valide' ? 'success' : 'error';

        $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message) VALUES (:id_u, :type, :msg)'
        )->execute([':id_u' => $intervenant['id_utilisateur'], ':type' => $type, ':msg' => $msg]);

        $label = $nouveau_statut === 'valide' ? 'validé' : 'refusé';
        flash('success', 'Compte de ' . $intervenant['prenom'] . ' ' . $intervenant['nom'] . ' ' . $label . '.');
    }
    redirect(BASE_URL . 'admin_validations.php');
}

/* Suppression d'un compte utilisateur */
if (isset($_POST['btn_supprimer_user'])) {
    csrf_check();
    $id_user = (int)($_POST['id_utilisateur'] ?? 0);
    /* Protège le compte admin courant */
    if ($id_user === $user['id_utilisateur'] ?? 0) {
        flash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
    } else {
        $db->prepare('DELETE FROM utilisateur WHERE id_utilisateur = :id')->execute([':id' => $id_user]);
        flash('success', 'Utilisateur supprimé.');
    }
    redirect(BASE_URL . 'admin_validations.php');
}

/* ---- Stats globales ---- */
$stats = [];
foreach ([
    'nb_sportifs'      => 'SELECT COUNT(*) FROM profil_sportif',
    'nb_intervenants'  => 'SELECT COUNT(*) FROM profil_intervenant',
    'nb_valides'       => 'SELECT COUNT(*) FROM profil_intervenant WHERE statut_validation="valide"',
    'nb_attente'       => 'SELECT COUNT(*) FROM profil_intervenant WHERE statut_validation="en_attente"',
    'nb_services'      => 'SELECT COUNT(*) FROM service WHERE statut="actif"',
    'nb_resa'          => 'SELECT COUNT(*) FROM reservation WHERE statut="confirmee"',
    'nb_inscriptions'  => 'SELECT COUNT(*) FROM inscription WHERE statut="confirmee"',
] as $key => $sql) {
    $stats[$key] = (int)$db->query($sql)->fetchColumn();
}

/* Intervenants en attente */
$stmt = $db->query(
    'SELECT pi.id_intervenant, pi.specialite, pi.diplomes, pi.tarif_horaire,
            pi.statut_validation,
            u.id_utilisateur, u.nom, u.prenom, u.email, u.date_inscription
     FROM profil_intervenant pi
     JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
     WHERE pi.statut_validation = "en_attente"
     ORDER BY u.date_inscription'
);
$en_attente = $stmt->fetchAll();

/* Tous les intervenants */
$stmt = $db->query(
    'SELECT pi.id_intervenant, pi.specialite, pi.statut_validation,
            u.id_utilisateur, u.nom, u.prenom, u.email,
            COUNT(s.id_service) AS nb_services,
            COUNT(r.id_reservation) AS nb_resa
     FROM profil_intervenant pi
     JOIN utilisateur u ON u.id_utilisateur = pi.id_utilisateur
     LEFT JOIN service s ON s.id_intervenant = pi.id_intervenant AND s.statut = "actif"
     LEFT JOIN reservation r ON r.id_service = s.id_service AND r.statut = "confirmee"
     GROUP BY pi.id_intervenant
     ORDER BY pi.statut_validation, u.nom'
);
$intervenants = $stmt->fetchAll();

/* Tous les sportifs */
$stmt = $db->query(
    'SELECT u.id_utilisateur, u.nom, u.prenom, u.email, u.date_inscription,
            ps.discipline, ps.niveau,
            COUNT(r.id_reservation) AS nb_resa
     FROM profil_sportif ps
     JOIN utilisateur u ON u.id_utilisateur = ps.id_utilisateur
     LEFT JOIN reservation r ON r.id_sportif = ps.id_sportif AND r.statut = "confirmee"
     GROUP BY ps.id_sportif
     ORDER BY u.nom'
);
$sportifs = $stmt->fetchAll();

$onglet = $_GET['tab'] ?? 'validations';

require_once 'includes/header.php';
?>

<h1 class="h3 mb-4">&#9881; Administration VitaCare</h1>

<!-- Stats globales -->
<div class="row g-3 mb-4">
  <?php
  $kpis = [
    ['nb_sportifs',     'Sportifs',         'success'],
    ['nb_valides',      'Intervenants validés', 'success'],
    ['nb_attente',      'En attente',        'warning'],
    ['nb_services',     'Services actifs',   'success'],
    ['nb_resa',         'Consultations',     'success'],
    ['nb_inscriptions', 'Inscriptions',      'success'],
  ];
  foreach ($kpis as [$key, $label, $color]): ?>
    <div class="col-6 col-md-2">
      <div class="card text-center border-0 shadow-sm p-2 bg-<?= $color ?> bg-opacity-10">
        <div class="fs-4 fw-bold text-<?= $color ?>"><?= $stats[$key] ?></div>
        <div class="small text-muted"><?= $label ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Onglets -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'validations' ? 'active' : '' ?>" href="?tab=validations">
      Validations
      <?php if ($stats['nb_attente'] > 0): ?>
        <span class="badge bg-warning text-dark ms-1"><?= $stats['nb_attente'] ?></span>
      <?php endif; ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'intervenants' ? 'active' : '' ?>" href="?tab=intervenants">
      Intervenants <span class="badge bg-secondary ms-1"><?= $stats['nb_intervenants'] ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $onglet === 'sportifs' ? 'active' : '' ?>" href="?tab=sportifs">
      Sportifs <span class="badge bg-secondary ms-1"><?= $stats['nb_sportifs'] ?></span>
    </a>
  </li>
</ul>

<?php if ($onglet === 'validations'): ?>
  <!-- ===== ONGLET VALIDATIONS ===== -->
  <?php if ($en_attente): ?>
    <div class="row g-3">
      <?php foreach ($en_attente as $iv): ?>
        <div class="col-md-6">
          <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning fw-semibold text-dark">
              &#9203; <?= e($iv['prenom'] . ' ' . $iv['nom']) ?>
            </div>
            <div class="card-body">
              <p class="small mb-1"><strong>Email :</strong> <?= e($iv['email']) ?></p>
              <p class="small mb-1"><strong>Spécialité :</strong> <?= e($iv['specialite'] ?? '—') ?></p>
              <?php if ($iv['diplomes']): ?>
                <p class="small mb-1"><strong>Diplômes :</strong> <?= e($iv['diplomes']) ?></p>
              <?php endif; ?>
              <p class="small mb-2"><strong>Tarif :</strong>
                <?= $iv['tarif_horaire'] ? number_format($iv['tarif_horaire'], 0) . ' €/h' : '—' ?>
              </p>
              <div class="d-flex gap-2">
                <form method="post" class="flex-grow-1">
                  <input type="hidden" name="csrf_token"     value="<?= csrf_token() ?>">
                  <input type="hidden" name="id_intervenant" value="<?= (int)$iv['id_intervenant'] ?>">
                  <button type="submit" name="btn_valider" class="btn btn-success w-100">
                    &#10003; Valider
                  </button>
                </form>
                <form method="post" class="flex-grow-1"
                      onsubmit="return confirm('Refuser ce compte ?')">
                  <input type="hidden" name="csrf_token"     value="<?= csrf_token() ?>">
                  <input type="hidden" name="id_intervenant" value="<?= (int)$iv['id_intervenant'] ?>">
                  <button type="submit" name="btn_refuser" class="btn btn-outline-danger w-100">
                    &#10005; Refuser
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
      <div class="fs-1 mb-2">&#9989;</div>
      <p>Aucune demande en attente de validation.</p>
    </div>
  <?php endif; ?>

<?php elseif ($onglet === 'intervenants'): ?>
  <!-- ===== ONGLET INTERVENANTS ===== -->
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Nom</th><th>Email</th><th>Spécialité</th>
          <th>Statut</th><th>Services</th><th>Résa.</th><th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($intervenants as $iv):
          $badge = ['valide' => 'success', 'en_attente' => 'warning', 'refuse' => 'danger'];
          $label = ['valide' => 'Validé', 'en_attente' => 'En attente', 'refuse' => 'Refusé'];
        ?>
          <tr>
            <td><?= e($iv['prenom'] . ' ' . $iv['nom']) ?></td>
            <td class="small"><?= e($iv['email']) ?></td>
            <td class="small"><?= e($iv['specialite'] ?? '—') ?></td>
            <td>
              <span class="badge bg-<?= $badge[$iv['statut_validation']] ?? 'secondary' ?>">
                <?= $label[$iv['statut_validation']] ?? $iv['statut_validation'] ?>
              </span>
            </td>
            <td><?= (int)$iv['nb_services'] ?></td>
            <td><?= (int)$iv['nb_resa'] ?></td>
            <td>
              <?php if ($iv['statut_validation'] === 'en_attente'): ?>
                <form method="post" class="d-inline">
                  <input type="hidden" name="csrf_token"     value="<?= csrf_token() ?>">
                  <input type="hidden" name="id_intervenant" value="<?= (int)$iv['id_intervenant'] ?>">
                  <button type="submit" name="btn_valider"
                          class="btn btn-sm btn-success">Valider</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php elseif ($onglet === 'sportifs'): ?>
  <!-- ===== ONGLET SPORTIFS ===== -->
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr><th>Nom</th><th>Email</th><th>Discipline</th><th>Niveau</th><th>Résa.</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($sportifs as $sp): ?>
          <tr>
            <td><?= e($sp['prenom'] . ' ' . $sp['nom']) ?></td>
            <td class="small"><?= e($sp['email']) ?></td>
            <td class="small"><?= e($sp['discipline'] ?? '—') ?></td>
            <td class="small"><?= e($sp['niveau'] ?? '—') ?></td>
            <td><?= (int)$sp['nb_resa'] ?></td>
            <td>
              <form method="post" class="d-inline"
                    onsubmit="return confirm('Supprimer cet utilisateur ?')">
                <input type="hidden" name="csrf_token"    value="<?= csrf_token() ?>">
                <input type="hidden" name="id_utilisateur" value="<?= (int)$sp['id_utilisateur'] ?>">
                <button type="submit" name="btn_supprimer_user"
                        class="btn btn-sm btn-outline-danger"
                        <?= $sp['nb_resa'] > 0 ? 'disabled title="Réservations existantes"' : '' ?>>
                  Supprimer
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
