<?php
/* Partiel réutilisé par le formulaire ajout ET modification de service.
 * Variables attendues dans le scope : $categories (tableau), $service_edit (array|null)
 */
$f_titre       = $service_edit['titre']       ?? ($titre       ?? '');
$f_description = $service_edit['description'] ?? ($description ?? '');
$f_id_cat      = $service_edit['id_categorie'] ?? ($id_cat     ?? 0);
$f_duree       = $service_edit['duree']       ?? ($duree       ?? 60);
$f_prix        = $service_edit['prix']        ?? ($prix        ?? '');
?>
<div class="row g-3 mb-3">
  <div class="col-md-8">
    <label class="form-label">Titre <span class="text-danger">*</span></label>
    <input type="text" name="titre" class="form-control"
           value="<?= e((string)$f_titre) ?>" required maxlength="150">
  </div>
  <div class="col-md-4">
    <label class="form-label">Catégorie <span class="text-danger">*</span></label>
    <select name="id_categorie" class="form-select" required>
      <option value="">— Choisir —</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id_categorie'] ?>"
                <?= (int)$f_id_cat === (int)$cat['id_categorie'] ? 'selected' : '' ?>>
          <?= e($cat['nom']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-12">
    <label class="form-label">Description</label>
    <textarea name="description" class="form-control" rows="3"
              placeholder="Décrivez votre service…"><?= e((string)$f_description) ?></textarea>
  </div>
  <div class="col-md-6">
    <label class="form-label">Durée (min) <span class="text-danger">*</span></label>
    <input type="number" name="duree" class="form-control"
           value="<?= (int)$f_duree ?>" min="15" max="480" step="15" required>
  </div>
  <div class="col-md-6">
    <label class="form-label">Prix (€) <span class="text-danger">*</span></label>
    <input type="number" name="prix" class="form-control"
           value="<?= e((string)$f_prix) ?>" min="0" max="9999" step="0.01" required>
  </div>
</div>
