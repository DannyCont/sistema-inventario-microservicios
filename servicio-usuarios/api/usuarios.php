<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
requireRole(['Administrador']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows=db()->query('SELECT u.id,u.nombre,u.apellidos,u.correo,r.nombre rol,u.activo,u.creado_en FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.id DESC')->fetchAll();
    jsonResponse(['ok'=>true,'usuarios'=>$rows]);
}
$data=input();
if ($method === 'POST') {
    $nombre=clean($data['nombre']??''); $apellidos=clean($data['apellidos']??'');
    $correo=strtolower(clean($data['correo']??'')); $password=(string)($data['password']??''); $rol=(int)($data['rol_id']??0);
    if ($nombre===''||$apellidos===''||!filter_var($correo,FILTER_VALIDATE_EMAIL)||strlen($password)<6||!in_array($rol,[1,2,3],true)) jsonResponse(['ok'=>false,'mensaje'=>'Revisa los datos. La contraseña debe tener al menos 6 caracteres.'],422);
    try { $stmt=db()->prepare('INSERT INTO usuarios(nombre,apellidos,correo,password,rol_id) VALUES(?,?,?,?,?)'); $stmt->execute([$nombre,$apellidos,$correo,hash('sha256',$password),$rol]); }
    catch(PDOException $e){ if($e->getCode()==='23000') jsonResponse(['ok'=>false,'mensaje'=>'El correo ya está registrado.'],409); throw $e; }
    jsonResponse(['ok'=>true,'mensaje'=>'Usuario registrado.'],201);
}
if ($method === 'PUT') {
    $id=(int)($data['id']??0); $rol=(int)($data['rol_id']??0); $activo=(int)($data['activo']??1);
    if($id<1||!in_array($rol,[1,2,3],true)||!in_array($activo,[0,1],true)) jsonResponse(['ok'=>false,'mensaje'=>'Datos no válidos.'],422);
    $stmt=db()->prepare('UPDATE usuarios SET rol_id=?,activo=? WHERE id=?'); $stmt->execute([$rol,$activo,$id]);
    jsonResponse(['ok'=>true,'mensaje'=>'Usuario actualizado.']);
}
jsonResponse(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
