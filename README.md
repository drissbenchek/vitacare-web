# VitaCare — Nutrition & Performance

Plateforme web dynamique de réservation de services de nutrition sportive.  
Projet ING2 — École ECE Paris — Livrable final 31 mai 2026.

**Équipe :** Hmani Hamza · Driss Benchekroun · Amine Hajji

---

## Prérequis

- **WAMP** (Windows Apache MySQL PHP) installé et démarré (icône verte dans la barre des tâches)
- PHP 8.x (inclus dans WAMP)
- MySQL 8.x (inclus dans WAMP)
- Navigateur moderne (Chrome, Firefox, Edge)

---

## Installation pas à pas

### 1. Placer le projet dans WAMP

Assurez-vous que le dossier du projet est bien à l'emplacement :
```
C:\wamp64\www\vitacare\
```

### 2. Démarrer WAMP

Double-cliquez sur l'icône WAMP dans la barre des tâches et attendez que l'icône devienne **verte**.

### 3. Créer la base de données

1. Ouvrez phpMyAdmin : [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)  
   *(ou clic gauche sur l'icône WAMP → phpMyAdmin)*
2. Cliquez sur **Nouvelle base de données** (panneau gauche)
3. Nom : `vitacare` — Interclassement : `utf8mb4_unicode_ci`
4. Cliquez **Créer**

### 4. Importer le schéma SQL

1. Sélectionnez la BDD `vitacare` dans phpMyAdmin
2. Onglet **Importer** → choisir le fichier `sql/schema.sql`
3. Cliquez **Exécuter**

### 5. Importer les données de test

1. Onglet **Importer** → choisir le fichier `sql/seed.sql`
2. Cliquez **Exécuter**

### 6. Ouvrir l'application

Accédez à : [http://localhost/vitacare/](http://localhost/vitacare/)

---

## Comptes de test

| Rôle          | Email                      | Mot de passe |
|---------------|---------------------------|--------------|
| Admin         | `admin@vitacare.fr`        | `password`   |
| Intervenant 1 | `sophie.martin@nutri.fr`   | `password`   |
| Intervenant 2 | `elise.garnier@nutri.fr`   | `password`   |
| Sportif 1     | `marie@sport.fr`           | `password`   |
| Sportif 2     | `lucas@sport.fr`           | `password`   |

---

## Structure du projet

```
vitacare/
├── index.php              — Page d'accueil
├── catalogue.php          — Catalogue React via CDN
├── inscription.php        — Inscription utilisateur
├── connexion.php          — Connexion
├── deconnexion.php        — Déconnexion
├── profil.php             — Profil utilisateur
├── service_detail.php     — Détail d'un service
├── mes_reservations.php   — Historique du sportif
├── mes_ateliers.php       — Ateliers réservés
├── activites.php          — Liste des ateliers
├── panier.php             — Panier de réservations
├── paiement.php           — Validation du panier
├── confirmation.php       — Confirmation de paiement
├── notifications.php      — Notifications
├── intervenant_services.php   — CRUD services intervenant
├── intervenant_creneaux.php   — CRUD créneaux intervenant
├── intervenant_activites.php  — CRUD ateliers intervenant
├── admin_validations.php  — Validation des intervenants (admin)
├── includes/
│   ├── config.php         — Connexion PDO + constantes
│   ├── helpers.php        — e(), redirect(), csrf_token(), flash()
│   ├── auth.php           — Sessions, require_login(), require_role()
│   ├── header.php         — En-tête HTML + navbar Bootstrap
│   └── footer.php         — Pied de page HTML + scripts JS
├── assets/
│   ├── css/styles.css     — Styles custom
│   └── js/main.js         — JS global (compteur notifications)
├── api/                   — Endpoints AJAX (JSON)
│   ├── catalogue_search.php
│   ├── panier_add.php
│   ├── notifications_count.php
│   └── notifications_read.php
└── sql/
    ├── schema.sql         — Création des 15 tables
    └── seed.sql           — Données de test
```

---

## Stack technique

| Couche    | Technologie                        |
|-----------|------------------------------------|
| Frontend  | HTML5 sémantique + Bootstrap 5 CDN |
| Catalogue | React 18 via CDN (TD-TP4 pattern)  |
| Dynamisme | JavaScript ES6 + Fetch API         |
| Backend   | PHP procédural + PDO               |
| BDD       | MySQL 8 (WAMP)                     |

---

## Sécurité minimale implémentée

- Mots de passe hashés avec `password_hash()` (bcrypt)
- Requêtes PDO préparées (protection injections SQL)
- `htmlspecialchars()` via `e()` sur toutes les sorties (protection XSS)
- Sessions sécurisées (httponly + samesite=Strict)
- Tokens CSRF sur tous les formulaires POST
- Vérification du rôle **côté serveur** sur chaque page sensible
