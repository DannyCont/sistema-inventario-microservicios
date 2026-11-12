<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
requireLogin();
$_SESSION = [];
session_destroy();
jsonResponse(['ok'=>true,'mensaje'=>'Sesión cerrada.']);

