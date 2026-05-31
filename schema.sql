-- ============================================================
-- VitaCare — Schéma de base de données
-- À importer APRÈS avoir créé la BDD : vitacare (utf8mb4_unicode_ci)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. ROLE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role` (
  `id_role`  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `libelle`  VARCHAR(30)      NOT NULL,
  PRIMARY KEY (`id_role`),
  UNIQUE KEY `uq_role_libelle` (`libelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. UTILISATEUR
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utilisateur` (
  `id_utilisateur`  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_role`         INT UNSIGNED   NOT NULL,
  `nom`             VARCHAR(80)    NOT NULL,
  `prenom`          VARCHAR(80)    NOT NULL,
  `email`           VARCHAR(150)   NOT NULL,
  `mot_de_passe`    VARCHAR(255)   NOT NULL,
  `date_inscription` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `fk_user_role` (`id_role`),
  CONSTRAINT `fk_user_role`
    FOREIGN KEY (`id_role`) REFERENCES `role` (`id_role`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. PROFIL_SPORTIF
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profil_sportif` (
  `id_sportif`   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_utilisateur` INT UNSIGNED NOT NULL,
  `discipline`   VARCHAR(100)   DEFAULT NULL,
  `niveau`       ENUM('debutant','intermediaire','avance','professionnel') DEFAULT 'debutant',
  `objectif`     VARCHAR(255)   DEFAULT NULL,
  `poids`        DECIMAL(5,2)   DEFAULT NULL COMMENT 'kg',
  `taille`       SMALLINT       DEFAULT NULL COMMENT 'cm',
  PRIMARY KEY (`id_sportif`),
  UNIQUE KEY `uq_sportif_user` (`id_utilisateur`),
  CONSTRAINT `fk_sportif_user`
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. PROFIL_INTERVENANT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `profil_intervenant` (
  `id_intervenant`     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `id_utilisateur`     INT UNSIGNED  NOT NULL,
  `specialite`         VARCHAR(150)  DEFAULT NULL,
  `diplomes`           TEXT          DEFAULT NULL,
  `experience`         TEXT          DEFAULT NULL,
  `tarif_horaire`      DECIMAL(7,2)  DEFAULT NULL,
  `statut_validation`  ENUM('en_attente','valide','refuse') NOT NULL DEFAULT 'en_attente',
  PRIMARY KEY (`id_intervenant`),
  UNIQUE KEY `uq_intervenant_user` (`id_utilisateur`),
  KEY `idx_statut_validation` (`statut_validation`),
  CONSTRAINT `fk_intervenant_user`
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. CATEGORIE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categorie` (
  `id_categorie`  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `nom`           VARCHAR(100)   NOT NULL,
  `description`   TEXT           DEFAULT NULL,
  `icone`         VARCHAR(50)    DEFAULT NULL COMMENT 'classe CSS ou emoji',
  PRIMARY KEY (`id_categorie`),
  UNIQUE KEY `uq_categorie_nom` (`nom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. SERVICE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service` (
  `id_service`      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_intervenant`  INT UNSIGNED   NOT NULL,
  `id_categorie`    INT UNSIGNED   NOT NULL,
  `titre`           VARCHAR(150)   NOT NULL,
  `description`     TEXT           DEFAULT NULL,
  `duree`           SMALLINT       NOT NULL DEFAULT 60 COMMENT 'minutes',
  `prix`            DECIMAL(7,2)   NOT NULL DEFAULT 0.00,
  `statut`          ENUM('actif','inactif') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id_service`),
  KEY `fk_service_intervenant` (`id_intervenant`),
  KEY `fk_service_categorie`   (`id_categorie`),
  CONSTRAINT `fk_service_intervenant`
    FOREIGN KEY (`id_intervenant`) REFERENCES `profil_intervenant` (`id_intervenant`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_service_categorie`
    FOREIGN KEY (`id_categorie`) REFERENCES `categorie` (`id_categorie`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. CRENEAU
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `creneau` (
  `id_creneau`     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_intervenant` INT UNSIGNED   NOT NULL,
  `date_debut`     DATETIME       NOT NULL,
  `date_fin`       DATETIME       NOT NULL,
  `statut`         ENUM('libre','reserve','annule') NOT NULL DEFAULT 'libre',
  PRIMARY KEY (`id_creneau`),
  KEY `fk_creneau_intervenant` (`id_intervenant`),
  KEY `idx_creneau_statut_date` (`statut`, `date_debut`),
  CONSTRAINT `fk_creneau_intervenant`
    FOREIGN KEY (`id_intervenant`) REFERENCES `profil_intervenant` (`id_intervenant`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. RESERVATION
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservation` (
  `id_reservation`  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_sportif`      INT UNSIGNED   NOT NULL,
  `id_service`      INT UNSIGNED   NOT NULL,
  `id_creneau`      INT UNSIGNED   NOT NULL,
  `statut`          ENUM('confirmee','annulee','annulation_tardive') NOT NULL DEFAULT 'confirmee',
  `date_reservation` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type_annulation` VARCHAR(50)    DEFAULT NULL,
  PRIMARY KEY (`id_reservation`),
  KEY `fk_resa_sportif`  (`id_sportif`),
  KEY `fk_resa_service`  (`id_service`),
  KEY `fk_resa_creneau`  (`id_creneau`),
  CONSTRAINT `fk_resa_sportif`
    FOREIGN KEY (`id_sportif`) REFERENCES `profil_sportif` (`id_sportif`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_resa_service`
    FOREIGN KEY (`id_service`) REFERENCES `service` (`id_service`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_resa_creneau`
    FOREIGN KEY (`id_creneau`) REFERENCES `creneau` (`id_creneau`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. ACTIVITE
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activite` (
  `id_activite`      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_intervenant`   INT UNSIGNED   NOT NULL,
  `id_categorie`     INT UNSIGNED   NOT NULL,
  `titre`            VARCHAR(150)   NOT NULL,
  `description`      TEXT           DEFAULT NULL,
  `date_debut`       DATETIME       NOT NULL,
  `date_fin`         DATETIME       NOT NULL,
  `capacite_max`     SMALLINT       NOT NULL DEFAULT 20,
  `places_reservees` SMALLINT       NOT NULL DEFAULT 0,
  `lieu`             VARCHAR(150)   DEFAULT NULL,
  `prix`             DECIMAL(7,2)   NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_activite`),
  KEY `fk_activite_intervenant` (`id_intervenant`),
  KEY `fk_activite_categorie`   (`id_categorie`),
  CONSTRAINT `fk_activite_intervenant`
    FOREIGN KEY (`id_intervenant`) REFERENCES `profil_intervenant` (`id_intervenant`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_activite_categorie`
    FOREIGN KEY (`id_categorie`) REFERENCES `categorie` (`id_categorie`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. INSCRIPTION (atelier)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inscription` (
  `id_inscription`  INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_sportif`      INT UNSIGNED   NOT NULL,
  `id_activite`     INT UNSIGNED   NOT NULL,
  `date_inscription` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut`          ENUM('confirmee','annulee') NOT NULL DEFAULT 'confirmee',
  PRIMARY KEY (`id_inscription`),
  UNIQUE KEY `uq_inscription` (`id_sportif`, `id_activite`),
  KEY `fk_inscr_sportif`  (`id_sportif`),
  KEY `fk_inscr_activite` (`id_activite`),
  CONSTRAINT `fk_inscr_sportif`
    FOREIGN KEY (`id_sportif`) REFERENCES `profil_sportif` (`id_sportif`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_inscr_activite`
    FOREIGN KEY (`id_activite`) REFERENCES `activite` (`id_activite`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 11. PANIER
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `panier` (
  `id_panier`     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_sportif`    INT UNSIGNED   NOT NULL,
  `date_creation` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut`        ENUM('actif','valide','abandonne') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (`id_panier`),
  KEY `fk_panier_sportif` (`id_sportif`),
  CONSTRAINT `fk_panier_sportif`
    FOREIGN KEY (`id_sportif`) REFERENCES `profil_sportif` (`id_sportif`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 12. LIGNE_PANIER
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ligne_panier` (
  `id_ligne`      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_panier`     INT UNSIGNED   NOT NULL,
  `type_element`  ENUM('reservation','inscription') NOT NULL,
  `id_element`    INT UNSIGNED   NOT NULL COMMENT 'id_reservation ou id_inscription selon type_element',
  `prix_unitaire` DECIMAL(7,2)   NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_ligne`),
  KEY `fk_ligne_panier` (`id_panier`),
  CONSTRAINT `fk_ligne_panier`
    FOREIGN KEY (`id_panier`) REFERENCES `panier` (`id_panier`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 13. PAIEMENT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `paiement` (
  `id_paiement`    INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_panier`      INT UNSIGNED   NOT NULL,
  `montant_total`  DECIMAL(9,2)   NOT NULL DEFAULT 0.00,
  `date_paiement`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut`         ENUM('simule') NOT NULL DEFAULT 'simule',
  PRIMARY KEY (`id_paiement`),
  KEY `fk_paiement_panier` (`id_panier`),
  CONSTRAINT `fk_paiement_panier`
    FOREIGN KEY (`id_panier`) REFERENCES `panier` (`id_panier`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 14. NOTIFICATION
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification` (
  `id_notification` INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_utilisateur`  INT UNSIGNED   NOT NULL,
  `type`            VARCHAR(50)    NOT NULL DEFAULT 'info',
  `message`         TEXT           NOT NULL,
  `lu`              TINYINT(1)     NOT NULL DEFAULT 0,
  `date_creation`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_notification`),
  KEY `fk_notif_user`   (`id_utilisateur`),
  KEY `idx_notif_lu`    (`id_utilisateur`, `lu`),
  CONSTRAINT `fk_notif_user`
    FOREIGN KEY (`id_utilisateur`) REFERENCES `utilisateur` (`id_utilisateur`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 15. AVIS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `avis` (
  `id_avis`      INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `id_sportif`   INT UNSIGNED   NOT NULL,
  `id_service`   INT UNSIGNED   NOT NULL,
  `note`         TINYINT        NOT NULL DEFAULT 5 COMMENT '1 à 5',
  `commentaire`  TEXT           DEFAULT NULL,
  `date_avis`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_avis`),
  UNIQUE KEY `uq_avis` (`id_sportif`, `id_service`),
  KEY `fk_avis_sportif`  (`id_sportif`),
  KEY `fk_avis_service`  (`id_service`),
  CONSTRAINT `fk_avis_sportif`
    FOREIGN KEY (`id_sportif`) REFERENCES `profil_sportif` (`id_sportif`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_avis_service`
    FOREIGN KEY (`id_service`) REFERENCES `service` (`id_service`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
