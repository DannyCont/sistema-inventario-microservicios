<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
$user=requireLogin(); $method=$_SERVER['REQUEST_METHOD'];

if($method==='GET'){
    $q=clean($_GET['q']??''); $sql='SELECT p.*,c.nombre categoria,COALESCE(i.existencia,0) existencia FROM productos p JOIN categorias c ON c.id=p.categoria_id LEFT JOIN inventario i ON i.producto_id=p.id WHERE p.activo=1'; $params=[];
    if($q!==''){ $sql.=' AND (p.codigo LIKE ? OR p.nombre LIKE ? OR c.nombre LIKE ?)'; $like="%$q%"; $params=[$like,$like,$like]; }
    $sql.=' ORDER BY p.id DESC'; $stmt=db()->prepare($sql); $stmt->execute($params);
    jsonResponse(['ok'=>true,'productos'=>$stmt->fetchAll()]);
}
requireRole(['Administrador']); $data=input();
if($method==='POST'){
    $codigo=strtoupper(clean($data['codigo']??'')); $nombre=clean($data['nombre']??''); $categoria=(int)($data['categoria_id']??0); $precio=(float)($data['precio']??-1); $min=(int)($data['stock_minimo']??-1);
    if($codigo===''||$nombre===''||$categoria<1||$precio<0||$min<0) jsonResponse(['ok'=>false,'mensaje'=>'Completa correctamente los datos obligatorios.'],422);
    $pdo=db(); try{$pdo->beginTransaction(); $stmt=$pdo->prepare('INSERT INTO productos(codigo,nombre,descripcion,categoria_id,precio,unidad_medida,stock_minimo) VALUES(?,?,?,?,?,?,?)'); $stmt->execute([$codigo,$nombre,clean($data['descripcion']??''),$categoria,$precio,clean($data['unidad_medida']??'Pieza'),$min]); $id=(int)$pdo->lastInsertId(); $pdo->prepare('INSERT INTO inventario(producto_id,existencia) VALUES(?,0)')->execute([$id]); $pdo->commit();}
    catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack(); if($e->getCode()==='23000')jsonResponse(['ok'=>false,'mensaje'=>'El código ya existe o la categoría no es válida.'],409); throw $e;}
    jsonResponse(['ok'=>true,'mensaje'=>'Producto registrado.'],201);
}
if($method==='PUT'){
    $id=(int)($data['id']??0); $codigo=strtoupper(clean($data['codigo']??'')); $nombre=clean($data['nombre']??''); $categoria=(int)($data['categoria_id']??0); $precio=(float)($data['precio']??-1); $min=(int)($data['stock_minimo']??-1);
    if($id<1||$codigo===''||$nombre===''||$categoria<1||$precio<0||$min<0) jsonResponse(['ok'=>false,'mensaje'=>'Datos no válidos.'],422);
    try{$stmt=db()->prepare('UPDATE productos SET codigo=?,nombre=?,descripcion=?,categoria_id=?,precio=?,unidad_medida=?,stock_minimo=? WHERE id=? AND activo=1'); $stmt->execute([$codigo,$nombre,clean($data['descripcion']??''),$categoria,$precio,clean($data['unidad_medida']??'Pieza'),$min,$id]);}
    catch(PDOException $e){if($e->getCode()==='23000')jsonResponse(['ok'=>false,'mensaje'=>'El código ya pertenece a otro producto.'],409);throw $e;}
    jsonResponse(['ok'=>true,'mensaje'=>'Producto actualizado.']);
}
if($method==='DELETE'){
    $id=(int)($_GET['id']??0); if($id<1)jsonResponse(['ok'=>false,'mensaje'=>'ID no válido.'],422);
    db()->prepare('UPDATE productos SET activo=0 WHERE id=?')->execute([$id]); jsonResponse(['ok'=>true,'mensaje'=>'Producto desactivado.']);
}
jsonResponse(['ok'=>false,'mensaje'=>'Método no permitido.'],405);

