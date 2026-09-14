<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
$data = input();
$correo = strtolower(clean($data['correo'] ?? ''));
$password = (string)($data['password'] ?? '');
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $password === '') jsonResponse(['ok'=>false,'mensaje'=>'Ingresa un correo y contraseña válidos.'],422);

$stmt = db()->prepare('SELECT u.id,u.nombre,u.apellidos,u.correo,r.nombre rol FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.correo=? AND u.password=? AND u.activo=1');
$stmt->execute([$correo, hash('sha256', $password)]);
$user = $stmt->fetch();
if (!$user) jsonResponse(['ok'=>false,'mensaje'=>'Correo o contraseña incorrectos.'],401);
session_regenerate_id(true);
$_SESSION['usuario'] = $user;
jsonResponse(['ok'=>true,'usuario'=>$user]);
