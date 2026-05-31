<?php

/* Démarre la session avec options de sécurité */
function start_session_secure(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/* Retourne le tableau utilisateur courant ou null */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/* Redirige vers connexion si non connecté */
function require_login(): void {
    if (current_user() === null) {
        flash('error', 'Vous devez être connecté pour accéder à cette page.');
        redirect(BASE_URL . 'connexion.php');
    }
}

/* Redirige vers accueil si le rôle ne correspond pas */
function require_role(string $role): void {
    require_login();
    if (current_user()['role'] !== $role) {
        flash('error', 'Accès refusé.');
        redirect(BASE_URL . 'index.php');
    }
}
