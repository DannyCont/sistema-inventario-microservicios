<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(): array
{
    $data = json_decode(file_get_contents('php://input'), true);
    return is_array($data) ? $data : $_POST;
}

function requireLogin(): array
{
    if (empty($_SESSION['usuario'])) {
        jsonResponse(['ok' => false, 'mensaje' => 'Sesión no válida.'], 401);
    }
    $user = $_SESSION['usuario'];
    if (isset($_SERVER['HTTP_X_FLEXI_USER']) && $_SERVER['HTTP_X_FLEXI_USER'] !== (string) $user['id']) {
        jsonResponse(['ok' => false, 'mensaje' => 'La sesión cambió. Vuelve a iniciar sesión.'], 401);
    }
    return $user;
}

function requireRole(array $roles): array
{
    $user = requireLogin();
    if (!in_array($user['rol'], $roles, true)) {
        jsonResponse(['ok' => false, 'mensaje' => 'No tienes permiso para realizar esta acción.'], 403);
    }
    return $user;
}

function clean(?string $value): string
{
    return trim((string) $value);
}
