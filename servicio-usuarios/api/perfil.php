<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'GET') jsonResponse(['ok'=>true,'usuario'=>$user]);
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') jsonResponse(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
$data = input();
$nombre = clean($data['nombre'] ?? '');
$apellidos = clean($data['apellidos'] ?? '');
if ($nombre === '' || $apellidos === '') jsonResponse(['ok'=>false,'mensaje'=>'Nombre y apellidos son obligatorios.'],422);
$stmt = db()->prepare('UPDATE usuarios SET nombre=?, apellidos=? WHERE id=?');
$stmt->execute([$nombre,$apellidos,$user['id']]);
$_SESSION['usuario']['nombre']=$nombre;
$_SESSION['usuario']['apellidos']=$apellidos;
jsonResponse(['ok'=>true,'mensaje'=>'Perfil actualizado.','usuario'=>$_SESSION['usuario']]);

