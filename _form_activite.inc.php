<?php
/* Partiel partagé entre ajout et modification d'atelier */
$fa_titre   = $activite_edit['titre']        ?? ($_POST['titre']        ?? '');
$fa_desc    = $activite_edit['description']  ?? ($_POST['description']  ?? '');
$fa_cat     = $activite_edit['id_categorie'] ?? ($_POST['id_categorie'] ?? 0);
$fa_debut   = $activite_edit['date_debut']   ?? ($_POST['date_debut']   ?? '');
$fa_fin     = $activite_edit['date_fin']     ?? ($_POST['date_fin']     ?? '');
$fa_cap     = $activite_edit['capacite_max'] ?? ($_POST['capacite_max'] ?? 20);
$fa_lieu    = $activite_edit['lieu']         ?? ($_POST['lieu']         ?? '');
$fa_prix    = $activite_edit['prix']         ?? ($_POST['prix']         ?? '0');

/* Convertit les dates BDD (Y-m-d H:i:s) au format datetime-local (Y-m-dTH:i) */
if ($fa_debut && strlen($fa_debut) > 10) $fa_debut = date('Y-m-d\TH:i', strtotime($fa_debut));
if ($fa_fin   && strlen($fa_fin)   > 10) $fa_fin   = date('Y-m-d\TH:i', strtotime($fa_fin));
?>
<div class="row g-3 mb-3">
  <div class="col-md-8">
    <label class="form-label">Titre <span class="text-danger">*</span></label>
    <input type="text" name="titre" class="form-control"
           value="<?= e((string)$fa_titre) ?>" required maxlength="150">
  </div>
  <div class="col-md-4">
    <label class="form-label">Catégorie <span class="text-danger">*</span></label>
    <select name="id_categorie" class="form-select" required>
      <option value="">— Choisir —</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id_categorie'] ?>"
                <?= (int)$fa_cat === (int)$cat['id_categorie'] ? 'selected' : '' ?>>
          <?= e($cat['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="2"><?= e((string)$fa_desc) ?></textarea>
  </div>
  <div class="col-md-6">
    <label class="form-label">Début <span class="text-danger">*</span></label>
    <input type="datetime-local" name="date_debut" class="form-control"
           value="<?= e((string)$fa_debut) ?>" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Fin <span class="text-danger">*</span></label>
    <input type="datetime-local" name="date_fin" class="form-control"
           value="<?= e((string)$fa_fin) ?>" required>
  </div>
  <div class="col-md-4">
    <label class="form-label">Capacité max <span class="text-danger">*</span></label>
    <input type="number" name="capacite_max" class="form-control"
           value="<?= (int)$fa_cap ?>" min="1" max="500" required>
  </div>
  <div class="col-md-4">
    <label class="form-label">Prix (€)</label>
    <input type="number" name="prix" class="form-control"
           value="<?= e((string)$fa_prix) ?>" min="0" max="9999" step="0.01">
  </div>
  <div class="col-md-4">
    <label class="form-label">Lieu</label>
    <input type="text" name="lieu" class="form-control"
           value="<?= e((string)$fa_lieu) ?>" maxlength="150">
  </div>
</div>
