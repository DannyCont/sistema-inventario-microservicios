<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
require_once __DIR__ . '/../../config/push.php';

$user = requireLogin();
$method = $_SERVER['REQUEST_METHOD'];
$config = pushConfig();

if ($method === 'GET') {
    jsonResponse(['ok' => true, 'clavePublica' => $config['publicKey'] ?? null]);
}
if (!in_array($method, ['POST', 'DELETE'], true)) {
    jsonResponse(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
}
$data = input();
$endpoint = $data['endpoint'] ?? null;
if (!is_string($endpoint) || !pushEndpointValido($endpoint)) {
    jsonResponse(['ok' => false, 'mensaje' => 'Servicio push del navegador no admitido.'], 422);
}
$hash = hash('sha256', $endpoint);
if ($method === 'DELETE') {
    db()->prepare('DELETE FROM suscripciones_push WHERE endpoint_hash=? AND usuario_id=?')
        ->execute([$hash, $user['id']]);
    jsonResponse(['ok' => true]);
}
if (!$config) {
    jsonResponse(['ok' => false, 'mensaje' => 'Configura las claves VAPID en el servidor.'], 503);
}

$publicKey = $data['keys']['p256dh'] ?? null;
$auth = $data['keys']['auth'] ?? null;
if (!is_string($publicKey) || !preg_match('/^B[A-Za-z0-9_-]{86}$/D', $publicKey)
    || !is_string($auth) || !preg_match('/^[A-Za-z0-9_-]{22}$/D', $auth)) {
    jsonResponse(['ok' => false, 'mensaje' => 'La suscripción push no es válida.'], 422);
}
$subscription = json_encode(['endpoint' => $endpoint, 'keys' => ['p256dh' => $publicKey, 'auth' => $auth]]);
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO suscripciones_push (usuario_id, endpoint_hash, suscripcion)
        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE suscripcion=IF(usuario_id=?, ?, suscripcion)')
        ->execute([$user['id'], $hash, $subscription, $user['id'], $subscription]);
    $query = $pdo->prepare('SELECT usuario_id FROM suscripciones_push WHERE endpoint_hash=?');
    $query->execute([$hash]);
    if ((int) $query->fetchColumn() !== (int) $user['id']) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'mensaje' => 'Vuelve a activar las notificaciones en este dispositivo.'], 409);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
jsonResponse(['ok' => true]);
