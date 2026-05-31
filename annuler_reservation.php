<?php
/* ============================================================
   Annulation d'une réservation — Règle 4.2
   - Gratuite si > 24h avant le RDV → statut = 'annulee'
   - Possible si ≤ 24h           → statut = 'annulation_tardive'
   Dans les deux cas : creneau repasse à 'libre'
   ============================================================ */
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user           = current_user();
$id_reservation = (int)($_POST['id_reservation'] ?? 0);

if ($id_reservation <= 0) {
    flash('error', 'Identifiant de réservation invalide.');
    redirect(BASE_URL . 'mes_reservations.php');
}

$db = get_db();

/* Chargement de la réservation (vérif que la réservation appartient bien au sportif connecté) */
if ($user['role'] === 'sportif') {
    $stmt = $db->prepare(
        'SELECT r.id_reservation, r.id_creneau, r.statut,
                c.date_debut,
                s.titre AS titre_service,
                u_int.id_utilisateur AS id_utilisateur_intervenant
         FROM reservation r
         JOIN creneau c ON c.id_creneau = r.id_creneau
         JOIN service s ON s.id_service = r.id_service
         JOIN profil_intervenant pi ON pi.id_intervenant = s.id_intervenant
         JOIN utilisateur u_int ON u_int.id_utilisateur = pi.id_utilisateur
         WHERE r.id_reservation = :id AND r.id_sportif = :sp'
    );
    $stmt->execute([':id' => $id_reservation, ':sp' => $user['id_profil']]);
} else {
    flash('error', 'Action non autorisée.');
    redirect(BASE_URL . 'index.php');
}

$resa = $stmt->fetch();

if (!$resa) {
    flash('error', 'Réservation introuvable.');
    redirect(BASE_URL . 'mes_reservations.php');
}
if ($resa['statut'] !== 'confirmee') {
    flash('error', 'Cette réservation a déjà été annulée.');
    redirect(BASE_URL . 'mes_reservations.php');
}

/* Demande de confirmation GET (affiche la page) */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $heures_avant = (strtotime($resa['date_debut']) - time()) / 3600;
    $tardive      = $heures_avant <= 24;

    $page_title = 'Annuler la réservation';
    require_once 'includes/header.php';
    ?>

    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow-sm">
          <div class="card-header bg-danger text-white fw-semibold">
            Annuler la réservation
          </div>
          <div class="card-body">
            <p>Vous êtes sur le point d'annuler :</p>
            <ul>
              <li><strong><?= e($resa['titre_service']) ?></strong></li>
              <li>Rendez-vous le <?= date('d/m/Y à H:i', strtotime($resa['date_debut'])) ?></li>
            </ul>

            <?php if ($tardive): ?>
              <div class="alert alert-warning">
                ⚠️ Ce rendez-vous a lieu dans moins de 24 heures.
                L'annulation sera enregistrée comme <strong>tardive</strong>.
              </div>
            <?php else: ?>
              <div class="alert alert-success">
                ✅ Annulation gratuite (plus de 24h avant le rendez-vous).
              </div>
            <?php endif; ?>

            <form method="post">
              <input type="hidden" name="csrf_token"      value="<?= csrf_token() ?>">
              <input type="hidden" name="id_reservation"  value="<?= $id_reservation ?>">
              <div class="d-grid gap-2">
                <button type="submit" name="btn_confirmer_annulation"
                        class="btn btn-danger">
                  Confirmer l'annulation
                </button>
                <a href="mes_reservations.php" class="btn btn-outline-secondary">Retour</a>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <?php
    require_once 'includes/footer.php';
    exit;
}

/* Traitement POST — Règle 4.2 */
if (isset($_POST['btn_confirmer_annulation'])) {
    csrf_check();

    $heures_avant = (strtotime($resa['date_debut']) - time()) / 3600;
    $statut_annul = $heures_avant <= 24 ? 'annulation_tardive' : 'annulee';
    $type_annul   = $heures_avant <= 24 ? 'tardive' : 'gratuite';

    $db->beginTransaction();
    try {
        /* Met à jour le statut de la réservation */
        $stmt = $db->prepare(
            'UPDATE reservation
             SET statut = :statut, type_annulation = :type
             WHERE id_reservation = :id'
        );
        $stmt->execute([
            ':statut' => $statut_annul,
            ':type'   => $type_annul,
            ':id'     => $id_reservation,
        ]);

        /* Remet le créneau à "libre" */
        $stmt = $db->prepare(
            'UPDATE creneau SET statut = "libre" WHERE id_creneau = :cid'
        );
        $stmt->execute([':cid' => $resa['id_creneau']]);

        /* Notification sportif */
        $msg = $statut_annul === 'annulation_tardive'
            ? 'Votre réservation « ' . $resa['titre_service'] . ' » a été annulée (annulation tardive).'
            : 'Votre réservation « ' . $resa['titre_service'] . ' » a été annulée.';
        $stmt = $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message)
             VALUES (:id_u, "warning", :msg)'
        );
        $stmt->execute([':id_u' => $user['id_utilisateur'], ':msg' => $msg]);

        /* Notification intervenant */
        $msg_int = 'La réservation de ' . $user['prenom'] . ' ' . $user['nom']
            . ' pour « ' . $resa['titre_service'] . ' » a été annulée'
            . ($statut_annul === 'annulation_tardive' ? ' (tardive)' : '') . '.';
        $stmt = $db->prepare(
            'INSERT INTO notification (id_utilisateur, type, message)
             VALUES (:id_u, "warning", :msg)'
        );
        $stmt->execute([':id_u' => $resa['id_utilisateur_intervenant'], ':msg' => $msg_int]);

        $db->commit();

        $msg_flash = $statut_annul === 'annulation_tardive'
            ? 'Réservation annulée (annulation tardive enregistrée).'
            : 'Réservation annulée avec succès.';
        flash('success', $msg_flash);

    } catch (Exception $e) {
        $db->rollBack();
        flash('error', 'Erreur lors de l\'annulation. Veuillez réessayer.');
    }

    redirect(BASE_URL . 'mes_reservations.php');
}

redirect(BASE_URL . 'mes_reservations.php');
