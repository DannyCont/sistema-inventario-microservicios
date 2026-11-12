<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/push.php';
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
if ($method !== 'POST') {
    jsonResponse(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}

$pdo = db();
$role = $pdo->prepare('SELECT r.nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id WHERE u.id=? AND u.activo=1');
$role->execute([$user['id']]);
if (!in_array($role->fetchColumn(), ['Administrador', 'Almacenista'], true)) {
    jsonResponse(['ok' => false, 'mensaje' => 'Tu cuenta no puede registrar movimientos.'], 403);
}
session_write_close();

$data = input();
$producto = filter_var($data['producto_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
$cantidad = filter_var($data['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4294967295]]);
$tipo = is_string($data['tipo'] ?? null) ? strtoupper(trim($data['tipo'])) : '';
$motivo = is_string($data['motivo'] ?? null) ? trim($data['motivo']) : '';
$solicitud = $data['solicitud_id'] ?? null;
if (!$producto || !$cantidad || !in_array($tipo, ['ENTRADA', 'SALIDA'], true)
    || $motivo === '' || preg_match('/^.{1,255}$/usD', $motivo) !== 1
    || ($solicitud !== null && (!is_string($solicitud)
        || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $solicitud)))) {
    jsonResponse(['ok' => false, 'mensaje' => 'Completa correctamente los datos del movimiento.'], 422);
}

try {
    $pdo->beginTransaction();
    if ($solicitud !== null) {
        $huella = hash('sha256', json_encode([$producto, $tipo, $cantidad, $motivo], JSON_THROW_ON_ERROR));
        // La clave única serializa reintentos incluso si llegan al mismo tiempo.
        $pdo->prepare('INSERT INTO solicitudes_movimiento (usuario_id, solicitud_id, huella)
            VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE solicitud_id=solicitud_id')
            ->execute([$user['id'], $solicitud, $huella]);
        $query = $pdo->prepare('SELECT huella, existencia_actual FROM solicitudes_movimiento
            WHERE usuario_id=? AND solicitud_id=? FOR UPDATE');
        $query->execute([$user['id'], $solicitud]);
        $previa = $query->fetch();
        if ($previa['huella'] !== $huella) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'mensaje' => 'El identificador ya corresponde a otro movimiento.'], 409);
        }
        if ($previa['existencia_actual'] !== null) {
            $pdo->commit();
            jsonResponse(['ok' => true, 'mensaje' => 'Movimiento registrado.', 'existencia_actual' => (int) $previa['existencia_actual']]);
        }
    }

    $stmt = $pdo->prepare('SELECT i.existencia, p.activo, p.stock_minimo FROM inventario i
        JOIN productos p ON p.id=i.producto_id WHERE i.producto_id=? FOR UPDATE');
    $stmt->execute([$producto]);
    $stock = $stmt->fetch();
    if (!$stock || !(int) $stock['activo']) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'mensaje' => 'El producto no existe o está inactivo.'], 404);
    }
    $anterior = (int) $stock['existencia'];
    $actual = $tipo === 'ENTRADA' ? $anterior + $cantidad : $anterior - $cantidad;
    if ($actual < 0 || $actual > 4294967295) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'mensaje' => $actual < 0
            ? 'La salida supera la existencia disponible.'
            : 'La entrada supera el límite de existencias.'], 409);
    }

    $pdo->prepare('UPDATE inventario SET existencia=? WHERE producto_id=?')->execute([$actual, $producto]);
    $pdo->prepare('INSERT INTO movimientos (producto_id, usuario_id, tipo, cantidad, existencia_anterior, existencia_actual, motivo)
        VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$producto, $user['id'], $tipo, $cantidad, $anterior, $actual, $motivo]);
    $movimiento = $pdo->lastInsertId();
    if ($solicitud !== null) {
        $pdo->prepare('UPDATE solicitudes_movimiento SET existencia_actual=? WHERE usuario_id=? AND solicitud_id=?')
            ->execute([$actual, $user['id'], $solicitud]);
    }
    if (pushConfig() && $anterior > (int) $stock['stock_minimo'] && $actual <= (int) $stock['stock_minimo']) {
        $pdo->prepare('INSERT INTO entregas_push (suscripcion_id, movimiento_id)
            SELECT s.id, ? FROM suscripciones_push s JOIN usuarios u ON u.id=s.usuario_id WHERE u.activo=1')
            ->execute([$movimiento]);
    }
    $pdo->commit();
    jsonResponse(['ok' => true, 'mensaje' => 'Movimiento registrado.', 'existencia_actual' => $actual], 201);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e instanceof PDOException && (int) ($e->errorInfo[1] ?? 0) === 1146
        && preg_match('/\b(solicitudes_movimiento|suscripciones_push|entregas_push)\b/', $e->getMessage())) {
        jsonResponse(['ok' => false, 'mensaje' => 'Faltan tablas de la PWA. Aplica database/pwa.sql a la base de inventario y vuelve a sincronizar.'], 503);
    }
    error_log('FlexiAlmacén: error al registrar movimiento: ' . $e->getMessage());
    jsonResponse(['ok' => false, 'mensaje' => 'No se pudo registrar el movimiento. Revisa el registro de errores de PHP.'], 500);
}
