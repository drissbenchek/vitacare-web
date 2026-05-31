<?php
$page_title = 'Connexion';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();

/* Redirige si déjà connecté */
if (current_user() !== null) {
    redirect(BASE_URL . 'index.php');
}

/* --- Init variables --- */
$errors = [];
$email  = $_POST['email'] ?? '';

/* --- Traitement POST --- */
if (isset($_POST['btn_connexion'])) {
    csrf_check();

    $email = trim($_POST['email'] ?? '');
    $mdp   = $_POST['mot_de_passe'] ?? '';

    if ($email === '') $errors[] = 'L\'email est obligatoire.';
    if ($mdp === '')   $errors[] = 'Le mot de passe est obligatoire.';

    if (empty($errors)) {
        $db   = get_db();
        $stmt = $db->prepare(
            'SELECT u.id_utilisateur, u.mot_de_passe, u.nom, u.prenom, u.email,
                    r.libelle AS role
             FROM utilisateur u
             JOIN role r ON r.id_role = u.id_role
             WHERE u.email = :email'
        );
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($mdp, $user['mot_de_passe'])) {
            $errors[] = 'Email ou mot de passe incorrect.';
        } else {
            /* Récupère l'id du profil et les infos spécifiques au rôle */
            $id_profil          = null;
            $statut_validation  = null;

            if ($user['role'] === 'sportif') {
                $stmt2 = $db->prepare('SELECT id_sportif FROM profil_sportif WHERE id_utilisateur = :id');
                $stmt2->execute([':id' => $user['id_utilisateur']]);
                $profil = $stmt2->fetch();
                $id_profil = $profil ? (int)$profil['id_sportif'] : null;

            } elseif ($user['role'] === 'intervenant') {
                $stmt2 = $db->prepare(
                    'SELECT id_intervenant, statut_validation FROM profil_intervenant WHERE id_utilisateur = :id'
                );
                $stmt2->execute([':id' => $user['id_utilisateur']]);
                $profil = $stmt2->fetch();
                $id_profil         = $profil ? (int)$profil['id_intervenant'] : null;
                $statut_validation = $profil ? $profil['statut_validation'] : null;
            }

            /* Stocke la session */
            $_SESSION['user'] = [
                'id_utilisateur'    => (int)$user['id_utilisateur'],
                'id_profil'         => $id_profil,
                'role'              => $user['role'],
                'nom'               => $user['nom'],
                'prenom'            => $user['prenom'],
                'email'             => $user['email'],
                'statut_validation' => $statut_validation,
            ];

            /* Renouvelle l'ID de session (sécurité) */
            session_regenerate_id(true);

            flash('success', 'Bienvenue, ' . $user['prenom'] . ' !');
            redirect(BASE_URL . 'index.php');
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5 col-lg-4">

    <h1 class="h3 mb-4 text-center">Connexion</h1>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
          <p class="mb-0"><?= e($err) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= e($email) ?>" required autofocus autocomplete="email">
      </div>

      <div class="mb-3">
        <label class="form-label" for="mot_de_passe">Mot de passe</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control"
               required autocomplete="current-password">
      </div>

      <div class="d-grid mt-4">
        <button type="submit" name="btn_connexion" class="btn btn-success btn-lg">
          Se connecter
        </button>
      </div>
    </form>

    <p class="text-center mt-3 text-muted small">
      Pas encore de compte ? <a href="inscription.php">S'inscrire</a>
    </p>

    <hr class="my-4">
    <p class="text-muted small text-center">
      <strong>Comptes de test</strong><br>
      admin@vitacare.fr · sophie.martin@nutri.fr · marie@sport.fr<br>
      Mot de passe : <code>password</code>
    </p>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
