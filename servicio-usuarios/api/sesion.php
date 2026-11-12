<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

if (empty($_SESSION['usuario'])) {
    jsonResponse(['ok' => false, 'autenticado' => false]);
}

$session = requireLogin();
$stmt = db()->prepare('SELECT u.id, u.nombre, u.apellidos, u.correo, r.nombre rol
    FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1');
$stmt->execute([$session['id']]);
$user = $stmt->fetch();
if (!$user) {
    $_SESSION = [];
    session_destroy();
    jsonResponse(['ok' => false, 'autenticado' => false]);
}

$_SESSION['usuario'] = $user;
jsonResponse(['ok' => true, 'autenticado' => true, 'usuario' => $user]);
