<?php
$page_title = 'Notifications';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user = current_user();
$db   = get_db();

/* Marquer toutes comme lues à l'ouverture de la page */
$db->prepare('UPDATE notification SET lu = 1 WHERE id_utilisateur = :id')
   ->execute([':id' => $user['id_utilisateur']]);

/* Charge les notifications (50 dernières) */
$stmt = $db->prepare(
    'SELECT id_notification, type, message, lu, date_creation
     FROM notification
     WHERE id_utilisateur = :id
     ORDER BY date_creation DESC
     LIMIT 50'
);
$stmt->execute([':id' => $user['id_utilisateur']]);
$notifs = $stmt->fetchAll();

require_once 'includes/header.php';

$icones = [
    'success' => '✅',
    'info'    => 'ℹ️',
    'warning' => '⚠️',
    'error'   => '❌',
];
$badge_class = [
    'success' => 'border-success',
    'info'    => 'border-info',
    'warning' => 'border-warning',
    'error'   => 'border-danger',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h1 class="h3 mb-0">&#128276; Notifications</h1>
  <?php if ($notifs): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <button type="submit" name="btn_tout_supprimer"
              class="btn btn-sm btn-outline-danger"
              onclick="return confirm('Supprimer toutes les notifications ?')">
        Tout supprimer
      </button>
    </form>
  <?php endif; ?>
</div>

<?php
/* Traitement suppression */
if (isset($_POST['btn_tout_supprimer'])) {
    csrf_check();
    $db->prepare('DELETE FROM notification WHERE id_utilisateur = :id')
       ->execute([':id' => $user['id_utilisateur']]);
    flash('success', 'Notifications supprimées.');
    redirect(BASE_URL . 'notifications.php');
}
if (isset($_POST['btn_supprimer'])) {
    csrf_check();
    $db->prepare(
        'DELETE FROM notification WHERE id_notification = :id AND id_utilisateur = :uid'
    )->execute([':id' => (int)$_POST['id_notification'], ':uid' => $user['id_utilisateur']]);
    redirect(BASE_URL . 'notifications.php');
}
?>

<?php if ($notifs): ?>
  <div class="list-group shadow-sm">
    <?php foreach ($notifs as $n): ?>
      <div class="list-group-item list-group-item-action border-start border-3 <?= $badge_class[$n['type']] ?? 'border-secondary' ?> py-3">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <span class="me-2"><?= $icones[$n['type']] ?? 'ℹ️' ?></span>
            <?= e($n['message']) ?>
            <div class="text-muted small mt-1">
              <?= date('d/m/Y à H:i', strtotime($n['date_creation'])) ?>
            </div>
          </div>
          <form method="post" class="ms-3">
            <input type="hidden" name="csrf_token"       value="<?= csrf_token() ?>">
            <input type="hidden" name="id_notification"  value="<?= (int)$n['id_notification'] ?>">
            <button type="submit" name="btn_supprimer"
                    class="btn btn-sm btn-outline-secondary">&#10005;</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="text-center py-5 text-muted">
    <div class="fs-1 mb-3">&#128276;</div>
    <p>Aucune notification.</p>
  </div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
