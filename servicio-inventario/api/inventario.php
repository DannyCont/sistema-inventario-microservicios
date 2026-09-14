<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
$user=requireLogin(); $method=$_SERVER['REQUEST_METHOD'];

if($method==='GET'){
    $tipo=$_GET['tipo']??'existencias';
    if($tipo==='movimientos'){
        $rows=db()->query('SELECT m.*,p.codigo,p.nombre producto,CONCAT(u.nombre," ",u.apellidos) usuario FROM movimientos m JOIN productos p ON p.id=m.producto_id JOIN usuarios u ON u.id=m.usuario_id ORDER BY m.id DESC LIMIT 200')->fetchAll();
        jsonResponse(['ok'=>true,'movimientos'=>$rows]);
    }
    $where=$tipo==='alertas'?' AND i.existencia<=p.stock_minimo':'';
    $rows=db()->query('SELECT p.id,p.codigo,p.nombre,p.stock_minimo,i.existencia,p.unidad_medida FROM productos p JOIN inventario i ON i.producto_id=p.id WHERE p.activo=1'.$where.' ORDER BY p.nombre')->fetchAll();
    $key=$tipo==='alertas'?'alertas':'existencias';
    jsonResponse(['ok'=>true,$key=>$rows]);
}
requireRole(['Administrador','Almacenista']);
if($method!=='POST')jsonResponse(['ok'=>false,'mensaje'=>'Método no permitido.'],405);
$data=input(); $producto=(int)($data['producto_id']??0); $tipo=strtoupper(clean($data['tipo']??'')); $cantidad=(int)($data['cantidad']??0); $motivo=clean($data['motivo']??'');
if($producto<1||!in_array($tipo,['ENTRADA','SALIDA'],true)||$cantidad<1||$motivo==='')jsonResponse(['ok'=>false,'mensaje'=>'Completa correctamente los datos del movimiento.'],422);
$pdo=db();
try{
    $pdo->beginTransaction();
    $stmt=$pdo->prepare('SELECT i.existencia,p.activo FROM inventario i JOIN productos p ON p.id=i.producto_id WHERE i.producto_id=? FOR UPDATE'); $stmt->execute([$producto]); $stock=$stmt->fetch();
    if(!$stock||!(int)$stock['activo']){ $pdo->rollBack(); jsonResponse(['ok'=>false,'mensaje'=>'El producto no existe o está inactivo.'],404); }
    $anterior=(int)$stock['existencia'];
    if($tipo==='SALIDA'&&$cantidad>$anterior){$pdo->rollBack();jsonResponse(['ok'=>false,'mensaje'=>'La salida supera la existencia disponible.'],409);}
    $actual=$tipo==='ENTRADA'?$anterior+$cantidad:$anterior-$cantidad;
    $pdo->prepare('UPDATE inventario SET existencia=? WHERE producto_id=?')->execute([$actual,$producto]);
    $pdo->prepare('INSERT INTO movimientos(producto_id,usuario_id,tipo,cantidad,existencia_anterior,existencia_actual,motivo) VALUES(?,?,?,?,?,?,?)')->execute([$producto,$user['id'],$tipo,$cantidad,$anterior,$actual,$motivo]);
    $pdo->commit(); jsonResponse(['ok'=>true,'mensaje'=>'Movimiento registrado.','existencia_actual'=>$actual],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
