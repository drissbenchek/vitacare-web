# Rapport de compromis techniques et décisions de conception
## VitaCare — Nutrition & Performance

*Ce document sera complété progressivement au fur et à mesure du développement.*

---

## 1. Compromis stack technique

### React via CDN vs React SPA (npm/Vite)

**Décision :** React utilisé uniquement sur la page catalogue (`catalogue.php`) via CDN, pas de build step.

**Justification :** Le TD-TP4 du module enseigne React via CDN avec Babel standalone. Utiliser npm/Vite aurait introduit un outillage (Node.js, bundler) hors scope des TD/TP, difficile à expliquer en soutenance. La valeur de React est concentrée là où elle est maximale : la page catalogue avec filtres dynamiques et re-render automatique.

**Limite acceptée :** Pas de composants React sur les autres pages — c'est un choix délibéré justifiable.

---

## 2. Compromis architecture

### PHP multi-pages vs React SPA

**Décision :** Architecture PHP multi-pages (chaque écran = un fichier `.php`).

**Justification :** Cohérent avec les TD-TP5 et TP6. Évite React Router qui n'est pas enseigné. La gestion de session est native avec PHP. Chaque page est autonome et explicable en soutenance.

---

## 3. Compromis base de données

### `ligne_panier` polymorphe

**Décision :** `type_element ENUM('reservation','inscription')` + `id_element INT` sans FK MySQL.

**Justification :** MySQL ne supporte pas les FK polymorphes. L'intégrité est garantie en PHP lors des opérations sur le panier. Alternative refusée (deux colonnes nullable) car elle complique les requêtes sans gain réel dans ce contexte pédagogique.

---

## 4. Difficultés rencontrées

*(À compléter)*

---

## 5. Fonctionnalités simplifiées ou abandonnées

*(À compléter)*

---

## 6. Limites actuelles

*(À compléter)*
