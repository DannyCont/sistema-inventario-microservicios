<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';

if (empty($_SESSION['usuario'])) {
    jsonResponse(['ok' => false, 'autenticado' => false]);
}

jsonResponse([
    'ok' => true,
    'autenticado' => true,
    'usuario' => $_SESSION['usuario']
]);
