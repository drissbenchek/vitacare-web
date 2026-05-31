<?php
require_once 'includes/config.php';
require_once 'includes/helpers.php';
require_once 'includes/auth.php';
start_session_secure();

/* Détruit la session et redirige */
$_SESSION = [];
session_destroy();

/* Supprime le cookie de session */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

redirect(BASE_URL . 'connexion.php');
