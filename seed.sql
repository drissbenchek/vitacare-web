-- ============================================================
-- VitaCare — Données de test (seed)
-- Tous les mots de passe = 'password' (hash bcrypt)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Rôles
-- ------------------------------------------------------------
INSERT INTO `role` (`id_role`, `libelle`) VALUES
  (1, 'admin'),
  (2, 'sportif'),
  (3, 'intervenant');

-- ------------------------------------------------------------
-- Utilisateurs
-- Hash = password_hash('password', PASSWORD_DEFAULT)
-- ------------------------------------------------------------
INSERT INTO `utilisateur` (`id_utilisateur`, `id_role`, `nom`, `prenom`, `email`, `mot_de_passe`, `date_inscription`) VALUES
  (1, 1, 'Admin',       'VitaCare',  'admin@vitacare.fr',      '$2y$10$VkN0VYetAXAC9xL8OzqTN.NWw2H3KQZRIzV1eafokDN5tANZR.vi.', '2026-01-01 08:00:00'),
  (2, 3, 'Martin',      'Sophie',    'sophie.martin@nutri.fr', '$2y$10$VkN0VYetAXAC9xL8OzqTN.NWw2H3KQZRIzV1eafokDN5tANZR.vi.', '2026-01-10 09:00:00'),
  (3, 3, 'Garnier',     'Elise',     'elise.garnier@nutri.fr', '$2y$10$VkN0VYetAXAC9xL8OzqTN.NWw2H3KQZRIzV1eafokDN5tANZR.vi.', '2026-01-15 10:00:00'),
  (4, 3, 'Leroy',       'Thomas',    'thomas.leroy@nutri.fr',  '$2y$10$VkN0VYetAXAC9xL8OzqTN.NWw2H3KQZRIzV1eafokDN5tANZR.vi.', '2026-02-01 11:00:00'),
  (5, 2, 'Dubois',      'Marie',     'marie@sport.fr',         '$2y$10$VkN0VYetAXAC9xL8OzqTN.NWw2H3KQZRIzV1eafokDN5tANZR.vi.', '2026-02-10 08:30:00'),
  (6, 2, 'Dupont',      'Lucas',     'lucas@sport.fr',         '$2y$10$VkN0VYetAXAC9xL8OzqTN.NWw2H3KQZRIzV1eafokDN5tANZR.vi.', '2026-02-15 09:15:00');

-- ------------------------------------------------------------
-- Profils sportifs
-- ------------------------------------------------------------
INSERT INTO `profil_sportif` (`id_sportif`, `id_utilisateur`, `discipline`, `niveau`, `objectif`, `poids`, `taille`) VALUES
  (1, 5, 'Trail running', 'intermediaire', 'Préparer un semi-marathon en juin 2026', 62.5, 168),
  (2, 6, 'Musculation',   'avance',        'Prise de masse sèche pour compétition',  80.0, 182);

-- ------------------------------------------------------------
-- Profils intervenants
-- ------------------------------------------------------------
INSERT INTO `profil_intervenant` (`id_intervenant`, `id_utilisateur`, `specialite`, `diplomes`, `experience`, `tarif_horaire`, `statut_validation`) VALUES
  (1, 2, 'Nutrition du sport et endurance',    'Master nutrition et performance sportive, BPJEPS',  '8 ans d''expérience avec des athlètes de haut niveau', 65.00, 'valide'),
  (2, 3, 'Diététique et nutrition de la force', 'DUT Diététique, DU Nutrition et sport',             '5 ans en cabinet spécialisé musculation et fitness',  55.00, 'valide'),
  (3, 4, 'Micronutrition et immunité sportive', 'Licence STAPS, formation micronutrition en cours', '2 ans de pratique, en cours de certification',        45.00, 'en_attente');

-- ------------------------------------------------------------
-- Catégories
-- ------------------------------------------------------------
INSERT INTO `categorie` (`id_categorie`, `nom`, `description`, `icone`) VALUES
  (1, 'Consultation nutrition sportive', 'Bilan et suivi nutritionnel adapté à votre pratique sportive', '&#127807;'),
  (2, 'Bilan diététique',                'Analyse complète de vos habitudes alimentaires',               '&#128203;'),
  (3, 'Plan nutritionnel',               'Élaboration d''un plan personnalisé sur mesure',               '&#128200;'),
  (4, 'Atelier collectif',               'Ateliers et programmes en groupe à capacité limitée',          '&#127939;');

-- ------------------------------------------------------------
-- Services (publiés par les 2 intervenants validés)
-- ------------------------------------------------------------
INSERT INTO `service` (`id_service`, `id_intervenant`, `id_categorie`, `titre`, `description`, `duree`, `prix`, `statut`) VALUES
  (1, 1, 1, 'Consultation nutrition endurance',       'Analyse de votre alimentation et recommandations pour optimiser vos performances en endurance (trail, marathon, cyclisme).', 60, 65.00, 'actif'),
  (2, 1, 3, 'Plan nutritionnel prépa compétition',    'Élaboration d''un plan nutritionnel complet sur 8 semaines, adapté à votre objectif compétition.', 90, 95.00, 'actif'),
  (3, 1, 2, 'Bilan diététique sport',                 'Bilan complet de vos apports nutritionnels avec analyse des carences et recommandations pratiques.', 60, 70.00, 'actif'),
  (4, 2, 1, 'Consultation nutrition musculation',     'Suivi nutritionnel spécialisé prise de masse et force : macros, timing des repas, supplémentation.', 60, 55.00, 'actif'),
  (5, 2, 3, 'Plan nutrition prise de masse sèche',    'Programme nutritionnel progressif sur 12 semaines pour une prise de masse musculaire sans excès de graisse.', 75, 80.00, 'actif');

-- ------------------------------------------------------------
-- Créneaux (8 créneaux variés)
-- ------------------------------------------------------------
INSERT INTO `creneau` (`id_creneau`, `id_intervenant`, `date_debut`, `date_fin`, `statut`) VALUES
  (1, 1, '2026-06-02 09:00:00', '2026-06-02 10:00:00', 'libre'),
  (2, 1, '2026-06-02 11:00:00', '2026-06-02 12:30:00', 'libre'),
  (3, 1, '2026-06-03 10:00:00', '2026-06-03 11:00:00', 'reserve'),
  (4, 1, '2026-06-04 14:00:00', '2026-06-04 15:00:00', 'libre'),
  (5, 2, '2026-06-02 14:00:00', '2026-06-02 15:00:00', 'libre'),
  (6, 2, '2026-06-03 09:00:00', '2026-06-03 10:00:00', 'reserve'),
  (7, 2, '2026-06-05 11:00:00', '2026-06-05 12:00:00', 'libre'),
  (8, 2, '2026-06-06 15:00:00', '2026-06-06 16:15:00', 'libre');

-- Réservation existante sur créneau 3 et 6
INSERT INTO `reservation` (`id_reservation`, `id_sportif`, `id_service`, `id_creneau`, `statut`, `date_reservation`) VALUES
  (1, 1, 1, 3, 'confirmee', '2026-05-28 10:15:00'),
  (2, 2, 4, 6, 'confirmee', '2026-05-29 14:30:00');

-- ------------------------------------------------------------
-- Activités (ateliers collectifs)
-- ------------------------------------------------------------
INSERT INTO `activite` (`id_activite`, `id_intervenant`, `id_categorie`, `titre`, `description`, `date_debut`, `date_fin`, `capacite_max`, `places_reservees`, `lieu`, `prix`) VALUES
  (1, 1, 4, 'Atelier nutrition endurance',
   'Apprenez à optimiser votre alimentation avant, pendant et après l''effort. Atelier pratique avec démonstrations.',
   '2026-06-07 10:00:00', '2026-06-07 12:00:00', 15, 4, 'Salle Nutrition, VitaCare Centre', 25.00),

  (2, 2, 4, 'Atelier prise de masse',
   'Comprendre les bases de la nutrition pour la prise de masse musculaire : protéines, glucides, timing.',
   '2026-06-14 14:00:00', '2026-06-14 16:00:00', 12, 7, 'Studio Fitness, VitaCare Centre', 30.00),

  (3, 1, 4, 'Programme prépa marathon',
   'Programme nutritionnel complet sur 6 semaines pour préparer votre marathon : 3 sessions collectives + suivi individuel.',
   '2026-06-21 09:00:00', '2026-06-21 11:30:00', 10, 2, 'Salle Sport, VitaCare Centre', 120.00);

-- ------------------------------------------------------------
-- Notifications de bienvenue
-- ------------------------------------------------------------
INSERT INTO `notification` (`id_utilisateur`, `type`, `message`, `lu`, `date_creation`) VALUES
  (5, 'info',    'Bienvenue sur VitaCare ! Consultez notre catalogue de services.', 0, NOW()),
  (6, 'info',    'Bienvenue sur VitaCare ! Consultez notre catalogue de services.', 0, NOW()),
  (2, 'success', 'Votre compte intervenant a été validé. Vous pouvez publier vos services.',  0, NOW()),
  (3, 'success', 'Votre compte intervenant a été validé. Vous pouvez publier vos services.',  0, NOW()),
  (4, 'warning', 'Votre compte intervenant est en attente de validation par un administrateur.', 0, NOW());

SET FOREIGN_KEY_CHECKS = 1;
