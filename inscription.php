<?php
$page_title = 'Inscription';
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
$role   = $_POST['role']    ?? $_GET['role'] ?? 'sportif';
$nom    = $_POST['nom']     ?? '';
$prenom = $_POST['prenom']  ?? '';
$email  = $_POST['email']   ?? '';

/* Champs sportif */
$discipline = $_POST['discipline'] ?? '';
$niveau     = $_POST['niveau']     ?? 'debutant';
$objectif   = $_POST['objectif']   ?? '';
$poids      = $_POST['poids']      ?? '';
$taille     = $_POST['taille']     ?? '';

/* Champs intervenant */
$specialite   = $_POST['specialite']   ?? '';
$diplomes     = $_POST['diplomes']     ?? '';
$experience   = $_POST['experience']   ?? '';
$tarif        = $_POST['tarif']        ?? '';

/* --- Traitement POST --- */
if (isset($_POST['btn_inscription'])) {
    csrf_check();

    /* Validation commune */
    $nom    = trim($nom);
    $prenom = trim($prenom);
    $email  = trim($email);
    $mdp    = $_POST['mot_de_passe']      ?? '';
    $mdp2   = $_POST['mot_de_passe2']     ?? '';
    $role   = in_array($role, ['sportif', 'intervenant']) ? $role : 'sportif';

    if ($nom === '')    $errors[] = 'Le nom est obligatoire.';
    if ($prenom === '') $errors[] = 'Le prénom est obligatoire.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (strlen($mdp) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($mdp !== $mdp2)   $errors[] = 'Les mots de passe ne correspondent pas.';

    /* Vérif email unique */
    if (empty($errors)) {
        $db   = get_db();
        $stmt = $db->prepare('SELECT id_utilisateur FROM utilisateur WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'Cet email est déjà utilisé.';
        }
    }

    /* Validation spécifique intervenant */
    if ($role === 'intervenant' && empty($errors)) {
        if (trim($specialite) === '') $errors[] = 'La spécialité est obligatoire.';
    }

    /* --- Insertion si aucune erreur --- */
    if (empty($errors)) {
        $db = get_db();
        $db->beginTransaction();
        try {
            /* Récupère l'id du rôle */
            $stmt = $db->prepare('SELECT id_role FROM role WHERE libelle = :libelle');
            $stmt->execute([':libelle' => $role]);
            $row    = $stmt->fetch();
            $id_role = $row['id_role'];

            /* Insert utilisateur */
            $hash = password_hash($mdp, PASSWORD_DEFAULT);
            $stmt = $db->prepare(
                'INSERT INTO utilisateur (id_role, nom, prenom, email, mot_de_passe)
                 VALUES (:id_role, :nom, :prenom, :email, :mdp)'
            );
            $stmt->execute([
                ':id_role' => $id_role,
                ':nom'     => $nom,
                ':prenom'  => $prenom,
                ':email'   => $email,
                ':mdp'     => $hash,
            ]);
            $id_utilisateur = (int) $db->lastInsertId();

            /* Insert profil selon le rôle */
            if ($role === 'sportif') {
                $stmt = $db->prepare(
                    'INSERT INTO profil_sportif (id_utilisateur, discipline, niveau, objectif, poids, taille)
                     VALUES (:id_u, :disc, :niv, :obj, :poids, :taille)'
                );
                $stmt->execute([
                    ':id_u'   => $id_utilisateur,
                    ':disc'   => trim($discipline) ?: null,
                    ':niv'    => $niveau,
                    ':obj'    => trim($objectif)   ?: null,
                    ':poids'  => $poids !== '' ? (float)$poids : null,
                    ':taille' => $taille !== '' ? (int)$taille : null,
                ]);
                $id_profil = (int) $db->lastInsertId();
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO profil_intervenant (id_utilisateur, specialite, diplomes, experience, tarif_horaire)
                     VALUES (:id_u, :spec, :dipl, :exp, :tarif)'
                );
                $stmt->execute([
                    ':id_u'  => $id_utilisateur,
                    ':spec'  => trim($specialite),
                    ':dipl'  => trim($diplomes)   ?: null,
                    ':exp'   => trim($experience)  ?: null,
                    ':tarif' => $tarif !== '' ? (float)$tarif : null,
                ]);
                $id_profil = (int) $db->lastInsertId();
            }

            /* Notification de bienvenue */
            $msg = $role === 'intervenant'
                ? 'Votre compte intervenant est en attente de validation par un administrateur.'
                : 'Bienvenue sur VitaCare ! Consultez notre catalogue de services.';
            $type_notif = $role === 'intervenant' ? 'warning' : 'info';
            $stmt = $db->prepare(
                'INSERT INTO notification (id_utilisateur, type, message) VALUES (:id_u, :type, :msg)'
            );
            $stmt->execute([':id_u' => $id_utilisateur, ':type' => $type_notif, ':msg' => $msg]);

            $db->commit();

            /* Session */
            $_SESSION['user'] = [
                'id_utilisateur' => $id_utilisateur,
                'id_profil'      => $id_profil,
                'role'           => $role,
                'nom'            => $nom,
                'prenom'         => $prenom,
                'email'          => $email,
                'statut_validation' => $role === 'intervenant' ? 'en_attente' : null,
            ];

            flash('success', 'Compte créé avec succès. Bienvenue, ' . $prenom . ' !');
            redirect(BASE_URL . 'index.php');

        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = 'Erreur lors de la création du compte. Veuillez réessayer.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-7 col-lg-6">

    <h1 class="h3 mb-4 text-center">Créer un compte</h1>

    <!-- Choix du rôle -->
    <div class="btn-group w-100 mb-4" role="group">
      <a href="?role=sportif"
         class="btn <?= $role === 'sportif' ? 'btn-success' : 'btn-outline-success' ?>">
        &#127939; Je suis sportif
      </a>
      <a href="?role=intervenant"
         class="btn <?= $role === 'intervenant' ? 'btn-success' : 'btn-outline-success' ?>">
        &#127807; Je suis intervenant
      </a>
    </div>

    <?php if ($role === 'intervenant'): ?>
      <div class="alert alert-info small">
        Les comptes intervenants sont soumis à validation par un administrateur avant d'être actifs.
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="role" value="<?= e($role) ?>">

      <!-- Informations communes -->
      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label" for="prenom">Prénom <span class="text-danger">*</span></label>
          <input type="text" id="prenom" name="prenom" class="form-control"
                 value="<?= e($prenom) ?>" required maxlength="80">
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="nom">Nom <span class="text-danger">*</span></label>
          <input type="text" id="nom" name="nom" class="form-control"
                 value="<?= e($nom) ?>" required maxlength="80">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label" for="email">Email <span class="text-danger">*</span></label>
        <input type="email" id="email" name="email" class="form-control"
               value="<?= e($email) ?>" required maxlength="150">
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label" for="mot_de_passe">Mot de passe <span class="text-danger">*</span></label>
          <input type="password" id="mot_de_passe" name="mot_de_passe" class="form-control"
                 required minlength="8" autocomplete="new-password">
          <div class="form-text">8 caractères minimum.</div>
        </div>
        <div class="col-sm-6">
          <label class="form-label" for="mot_de_passe2">Confirmer <span class="text-danger">*</span></label>
          <input type="password" id="mot_de_passe2" name="mot_de_passe2" class="form-control"
                 required minlength="8" autocomplete="new-password">
        </div>
      </div>

      <hr>

      <?php if ($role === 'sportif'): ?>
        <!-- Profil sportif -->
        <h6 class="text-muted mb-3">Profil sportif</h6>

        <div class="mb-3">
          <label class="form-label" for="discipline">Discipline sportive</label>
          <input type="text" id="discipline" name="discipline" class="form-control"
                 value="<?= e($discipline) ?>" placeholder="ex. Trail running, Musculation, Cyclisme…" maxlength="100">
        </div>

        <div class="mb-3">
          <label class="form-label" for="niveau">Niveau</label>
          <select id="niveau" name="niveau" class="form-select">
            <?php foreach (['debutant' => 'Débutant', 'intermediaire' => 'Intermédiaire', 'avance' => 'Avancé', 'professionnel' => 'Professionnel'] as $val => $label): ?>
              <option value="<?= $val ?>" <?= $niveau === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label" for="objectif">Objectif</label>
          <input type="text" id="objectif" name="objectif" class="form-control"
                 value="<?= e($objectif) ?>" placeholder="ex. Préparer un marathon en juin" maxlength="255">
        </div>

        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label" for="poids">Poids (kg)</label>
            <input type="number" id="poids" name="poids" class="form-control"
                   value="<?= e($poids) ?>" min="30" max="300" step="0.1">
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="taille">Taille (cm)</label>
            <input type="number" id="taille" name="taille" class="form-control"
                   value="<?= e($taille) ?>" min="100" max="250">
          </div>
        </div>

      <?php else: ?>
        <!-- Profil intervenant -->
        <h6 class="text-muted mb-3">Profil professionnel</h6>

        <div class="mb-3">
          <label class="form-label" for="specialite">Spécialité <span class="text-danger">*</span></label>
          <input type="text" id="specialite" name="specialite" class="form-control"
                 value="<?= e($specialite) ?>" required placeholder="ex. Nutrition du sport et endurance" maxlength="150">
        </div>

        <div class="mb-3">
          <label class="form-label" for="diplomes">Diplômes et certifications</label>
          <textarea id="diplomes" name="diplomes" class="form-control" rows="3"
                    placeholder="ex. Master nutrition et performance sportive, BPJEPS…"><?= e($diplomes) ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label" for="experience">Expérience</label>
          <textarea id="experience" name="experience" class="form-control" rows="3"
                    placeholder="Décrivez votre expérience professionnelle…"><?= e($experience) ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label" for="tarif">Tarif horaire (€)</label>
          <input type="number" id="tarif" name="tarif" class="form-control"
                 value="<?= e($tarif) ?>" min="0" max="9999" step="0.01">
        </div>
      <?php endif; ?>

      <div class="d-grid mt-4">
        <button type="submit" name="btn_inscription" class="btn btn-success btn-lg">
          Créer mon compte
        </button>
      </div>
    </form>

    <p class="text-center mt-3 text-muted small">
      Déjà un compte ? <a href="connexion.php">Se connecter</a>
    </p>

  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
