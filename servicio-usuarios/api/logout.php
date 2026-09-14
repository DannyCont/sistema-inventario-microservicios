<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/helpers.php';
$_SESSION = [];
session_destroy();
jsonResponse(['ok'=>true,'mensaje'=>'Sesión cerrada.']);

