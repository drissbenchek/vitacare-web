<?php

/* Échappe toute sortie utilisateur (anti-XSS) */
function e(string $val): string {
    return htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
}

/* Redirection et arrêt */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/* Génère et stocke un token CSRF en session */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* Vérifie le token CSRF POST — arrête si invalide */
function csrf_check(): void {
    if (
        empty($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
    ) {
        http_response_code(403);
        exit('Token CSRF invalide.');
    }
}

/* Stocke un message flash (type : success | error | warning | info) */
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/* Retourne et efface le message flash */
function get_flash(): ?array {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}
