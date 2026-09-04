<?php
require_once __DIR__ . '/config.php';

session_name(ADMIN_SESSION_NAME);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function is_logged_in(): bool { return !empty($_SESSION['admin_logged_in']); }
function require_login(): void { if (!is_logged_in()) { header('Location: login.php'); exit; } }

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_check(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Ungültige Anfrage (CSRF-Token fehlt oder abgelaufen). Bitte Seite neu laden und erneut versuchen.');
    }
}
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
