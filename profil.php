<?php
$page_title = 'Mon profil';
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();
require_login();

$user   = current_user();
$db     = get_db();
$errors = [];
$ok     = false;

/* --- Chargement des données actuelles --- */
$stmt = $db->prepare(
    'SELECT u.nom, u.prenom, u.email, r.libelle AS role
     FROM utilisateur u JOIN role r ON r.id_role = u.id_role
     WHERE u.id_utilisateur = :id'
);
$stmt->execute([':id' => $user['id_utilisateur']]);
$data_user = $stmt->fetch();

/* Profil spécifique selon le rôle */
$data_profil = [];
if ($user['role'] === 'sportif') {
    $stmt = $db->prepare(
        'SELECT discipline, niveau, objectif, poids, taille
         FROM profil_sportif WHERE id_utilisateur = :id'
    );
    $stmt->execute([':id' => $user['id_utilisateur']]);
    $data_profil = $stmt->fetch() ?: [];

} elseif ($user['role'] === 'intervenant') {
    $stmt = $db->prepare(
        'SELECT specialite, diplomes, experience, tarif_horaire, statut_validation
         FROM profil_intervenant WHERE id_utilisateur = :id'
    );
    $stmt->execute([':id' => $user['id_utilisateur']]);
    $data_profil = $stmt->fetch() ?: [];
}

/* --- Traitement POST infos générales --- */
if (isset($_POST['btn_infos'])) {
    csrf_check();

    $nom    = trim($_POST['nom']    ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email']  ?? '');

    if ($nom === '')    $errors[] = 'Le nom est obligatoire.';
    if ($prenom === '') $errors[] = 'Le prénom est obligatoire.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';

    /* Vérif email unique (sauf son propre email) */
    if (empty($errors)) {
        $stmt = $db->prepare(
            'SELECT id_utilisateur FROM utilisateur WHERE email = :email AND id_utilisateur != :id'
        );
        $stmt->execute([':email' => $email, ':id' => $user['id_utilisateur']]);
        if ($stmt->fetch()) $errors[] = 'Cet email est déjà utilisé par un autre compte.';
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            'UPDATE utilisateur SET nom = :nom, prenom = :prenom, email = :email
             WHERE id_utilisateur = :id'
        );
        $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':email'  => $email,
            ':id'     => $user['id_utilisateur'],
        ]);

        /* Met à jour la session */
        $_SESSION['user']['nom']    = $nom;
        $_SESSION['user']['prenom'] = $prenom;
        $_SESSION['user']['email']  = $email;

        $data_user['nom']    = $nom;
        $data_user['prenom'] = $prenom;
        $data_user['email']  = $email;

        flash('success', 'Informations mises à jour.');
        redirect(BASE_URL . 'profil.php');
    }
}

/* --- Traitement POST mot de passe --- */
if (isset($_POST['btn_mdp'])) {
    csrf_check();

    $ancien = $_POST['ancien_mdp']   ?? '';
    $nouveau = $_POST['nouveau_mdp']  ?? '';
    $nouveau2 = $_POST['nouveau_mdp2'] ?? '';

    if ($ancien === '')          $errors[] = 'L\'ancien mot de passe est obligatoire.';
    if (strlen($nouveau) < 8)   $errors[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
    if ($nouveau !== $nouveau2)  $errors[] = 'Les nouveaux mots de passe ne correspondent pas.';

    if (empty($errors)) {
        $stmt = $db->prepare('SELECT mot_de_passe FROM utilisateur WHERE id_utilisateur = :id');
        $stmt->execute([':id' => $user['id_utilisateur']]);
        $row = $stmt->fetch();
        if (!password_verify($ancien, $row['mot_de_passe'])) {
            $errors[] = 'Ancien mot de passe incorrect.';
        }
    }

    if (empty($errors)) {
        $stmt = $db->prepare(
            'UPDATE utilisateur SET mot_de_passe = :mdp WHERE id_utilisateur = :id'
        );
        $stmt->execute([':mdp' => password_hash($nouveau, PASSWORD_DEFAULT), ':id' => $user['id_utilisateur']]);
        flash('success', 'Mot de passe modifié avec succès.');
        redirect(BASE_URL . 'profil.php');
    }
}

/* --- Traitement POST profil spécifique --- */
if (isset($_POST['btn_profil'])) {
    csrf_check();

    if ($user['role'] === 'sportif') {
        $discipline = trim($_POST['discipline'] ?? '');
        $niveau     = $_POST['niveau'] ?? 'debutant';
        $objectif   = trim($_POST['objectif'] ?? '');
        $poids      = $_POST['poids']  ?? '';
        $taille     = $_POST['taille'] ?? '';

        if (!in_array($niveau, ['debutant','intermediaire','avance','professionnel'])) {
            $niveau = 'debutant';
        }

        $stmt = $db->prepare(
            'UPDATE profil_sportif SET discipline=:disc, niveau=:niv, objectif=:obj, poids=:poids, taille=:taille
             WHERE id_utilisateur = :id'
        );
        $stmt->execute([
            ':disc'   => $discipline ?: null,
            ':niv'    => $niveau,
            ':obj'    => $objectif   ?: null,
            ':poids'  => $poids !== '' ? (float)$poids : null,
            ':taille' => $taille !== '' ? (int)$taille : null,
            ':id'     => $user['id_utilisateur'],
        ]);

        $data_profil = ['discipline' => $discipline, 'niveau' => $niveau,
                        'objectif' => $objectif, 'poids' => $poids, 'taille' => $taille];

        flash('success', 'Profil sportif mis à jour.');
        redirect(BASE_URL . 'profil.php');

    } elseif ($user['role'] === 'intervenant') {
        $specialite = trim($_POST['specialite'] ?? '');
        $diplomes   = trim($_POST['diplomes']   ?? '');
        $experience = trim($_POST['experience'] ?? '');
        $tarif      = $_POST['tarif'] ?? '';

        if ($specialite === '') $errors[] = 'La spécialité est obligatoire.';

        if (empty($errors)) {
            $stmt = $db->prepare(
                'UPDATE profil_intervenant SET specialite=:spec, diplomes=:dipl, experience=:exp, tarif_horaire=:tarif
                 WHERE id_utilisateur = :id'
            );
            $stmt->execute([
                ':spec'  => $specialite,
                ':dipl'  => $diplomes   ?: null,
                ':exp'   => $experience ?: null,
                ':tarif' => $tarif !== '' ? (float)$tarif : null,
                ':id'    => $user['id_utilisateur'],
            ]);

            flash('success', 'Profil professionnel mis à jour.');
            redirect(BASE_URL . 'profil.php');
        }
    }
}

require_once 'includes/header.php';

$niveaux = ['debutant' => 'Débutant', 'intermediaire' => 'Intermédiaire', 'avance' => 'Avancé', 'professionnel' => 'Professionnel'];
?>

<h1 class="h3 mb-4">Mon profil</h1>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <ul class="mb-0">
      <?php foreach ($errors as $err): ?>
        <li><?= e($err) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<?php if ($user['role'] === 'intervenant' && ($data_profil['statut_validation'] ?? '') === 'en_attente'): ?>
  <div class="alert alert-warning">
    &#9203; Votre compte intervenant est <strong>en attente de validation</strong> par un administrateur.
    Vous ne pouvez pas encore publier de services.
  </div>
<?php elseif ($user['role'] === 'intervenant' && ($data_profil['statut_validation'] ?? '') === 'valide'): ?>
  <div class="alert alert-success">
    &#10003; Votre compte intervenant est <strong>validé</strong>. Vous pouvez publier vos services.
  </div>
<?php endif; ?>

<div class="row g-4">

  <!-- Informations générales -->
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-success text-white fw-semibold">
        Informations générales
      </div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label class="form-label" for="prenom">Prénom</label>
              <input type="text" id="prenom" name="prenom" class="form-control"
                     value="<?= e($data_user['prenom'] ?? '') ?>" required maxlength="80">
            </div>
            <div class="col-sm-6">
              <label class="form-label" for="nom">Nom</label>
              <input type="text" id="nom" name="nom" class="form-control"
                     value="<?= e($data_user['nom'] ?? '') ?>" required maxlength="80">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label" for="email">Email</label>
            <input type="email" id="email" name="email" class="form-control"
                   value="<?= e($data_user['email'] ?? '') ?>" required maxlength="150">
          </div>

          <div class="mb-3">
            <label class="form-label">Rôle</label>
            <input type="text" class="form-control" value="<?= ucfirst(e($data_user['role'] ?? '')) ?>" disabled>
          </div>

          <button type="submit" name="btn_infos" class="btn btn-success">
            Enregistrer
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Changer le mot de passe -->
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header bg-secondary text-white fw-semibold">
        Changer le mot de passe
      </div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="mb-3">
            <label class="form-label" for="ancien_mdp">Ancien mot de passe</label>
            <input type="password" id="ancien_mdp" name="ancien_mdp" class="form-control"
                   required autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label" for="nouveau_mdp">Nouveau mot de passe</label>
            <input type="password" id="nouveau_mdp" name="nouveau_mdp" class="form-control"
                   required minlength="8" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label" for="nouveau_mdp2">Confirmer</label>
            <input type="password" id="nouveau_mdp2" name="nouveau_mdp2" class="form-control"
                   required minlength="8" autocomplete="new-password">
          </div>

          <button type="submit" name="btn_mdp" class="btn btn-secondary">
            Modifier le mot de passe
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Profil spécifique au rôle -->
  <?php if ($user['role'] === 'sportif'): ?>
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-success text-white fw-semibold">
        Profil sportif
      </div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label" for="discipline">Discipline sportive</label>
              <input type="text" id="discipline" name="discipline" class="form-control"
                     value="<?= e($data_profil['discipline'] ?? '') ?>" maxlength="100">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="niveau">Niveau</label>
              <select id="niveau" name="niveau" class="form-select">
                <?php foreach ($niveaux as $val => $label): ?>
                  <option value="<?= $val ?>" <?= ($data_profil['niveau'] ?? 'debutant') === $val ? 'selected' : '' ?>>
                    <?= $label ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label" for="objectif">Objectif</label>
              <input type="text" id="objectif" name="objectif" class="form-control"
                     value="<?= e($data_profil['objectif'] ?? '') ?>" maxlength="255">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="poids">Poids (kg)</label>
              <input type="number" id="poids" name="poids" class="form-control"
                     value="<?= e($data_profil['poids'] ?? '') ?>" min="30" max="300" step="0.1">
            </div>
            <div class="col-md-6">
              <label class="form-label" for="taille">Taille (cm)</label>
              <input type="number" id="taille" name="taille" class="form-control"
                     value="<?= e($data_profil['taille'] ?? '') ?>" min="100" max="250">
            </div>
          </div>

          <button type="submit" name="btn_profil" class="btn btn-success mt-3">
            Mettre à jour le profil sportif
          </button>
        </form>
      </div>
    </div>
  </div>

  <?php elseif ($user['role'] === 'intervenant'): ?>
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header bg-success text-white fw-semibold">
        Profil professionnel
      </div>
      <div class="card-body">
        <form method="post" novalidate>
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label" for="specialite">Spécialité <span class="text-danger">*</span></label>
              <input type="text" id="specialite" name="specialite" class="form-control"
                     value="<?= e($data_profil['specialite'] ?? '') ?>" required maxlength="150">
            </div>
            <div class="col-md-4">
              <label class="form-label" for="tarif">Tarif horaire (€)</label>
              <input type="number" id="tarif" name="tarif" class="form-control"
                     value="<?= e($data_profil['tarif_horaire'] ?? '') ?>" min="0" step="0.01">
            </div>
            <div class="col-12">
              <label class="form-label" for="diplomes">Diplômes et certifications</label>
              <textarea id="diplomes" name="diplomes" class="form-control" rows="3"><?= e($data_profil['diplomes'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
              <label class="form-label" for="experience">Expérience</label>
              <textarea id="experience" name="experience" class="form-control" rows="3"><?= e($data_profil['experience'] ?? '') ?></textarea>
            </div>
          </div>

          <button type="submit" name="btn_profil" class="btn btn-success mt-3">
            Mettre à jour le profil professionnel
          </button>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /row -->

<?php require_once 'includes/footer.php'; ?>
