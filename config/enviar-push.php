<?php
declare(strict_types=1);

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/push.php';

$config = pushConfig();
if (!$config) {
    echo "Web Push desactivado. Configura las claves VAPID para habilitarlo.\n";
    exit;
}

$autoload = '/opt/flexialmacen/vendor/autoload.php';
require_once is_file($autoload) ? $autoload : __DIR__ . '/vendor/autoload.php';

$push = new WebPush(['VAPID' => $config], ['TTL' => 3600, 'urgency' => 'normal'], 10, [
    'allow_redirects' => false,
]);
$pdo = db();
if (!(int) $pdo->query("SELECT GET_LOCK('flexialmacen-push', 0)")->fetchColumn()) {
    exit;
}

while (true) {
    $pdo->exec('DELETE FROM entregas_push WHERE creado_en < CURRENT_TIMESTAMP - INTERVAL 1 HOUR');
    $rows = $pdo->query('SELECT e.id, e.movimiento_id, e.intentos, e.suscripcion_id, s.suscripcion, u.activo
        FROM entregas_push e JOIN suscripciones_push s ON s.id=e.suscripcion_id
        JOIN usuarios u ON u.id=s.usuario_id
        WHERE e.disponible_en <= CURRENT_TIMESTAMP ORDER BY e.disponible_en, e.id LIMIT 25')->fetchAll();
    foreach ($rows as $row) {
        $subscription = json_decode($row['suscripcion'], true, 512, JSON_THROW_ON_ERROR);
        if (!$row['activo'] || !pushEndpointValido($subscription['endpoint'])) {
            $pdo->prepare('DELETE FROM entregas_push WHERE id=?')->execute([$row['id']]);
            continue;
        }

        $sent = false;
        $expired = false;
        try {
            $report = $push->sendOneNotification(Subscription::create($subscription), json_encode([
                'title' => 'FlexiAlmacén · Stock bajo',
                'body' => 'Un producto llegó a su mínimo. Abre la app para consultar el inventario.',
                'tag' => 'stock-' . $row['movimiento_id'],
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $sent = $report->isSuccess();
            $expired = $report->isSubscriptionExpired();
            if (!$sent && !$expired) {
                fwrite(STDERR, "Web Push: entrega {$row['id']} sin confirmar.\n");
            }
        } catch (Throwable $e) {
            fwrite(STDERR, "Web Push: error al enviar la entrega {$row['id']}.\n");
        }

        if ($expired) {
            $pdo->prepare('DELETE FROM suscripciones_push WHERE id=?')->execute([$row['suscripcion_id']]);
        } elseif ($sent || (int) $row['intentos'] >= 4) {
            $pdo->prepare('DELETE FROM entregas_push WHERE id=?')->execute([$row['id']]);
        } else {
            $pdo->prepare('UPDATE entregas_push SET intentos=intentos+1,
                disponible_en=CURRENT_TIMESTAMP + INTERVAL 1 MINUTE WHERE id=?')->execute([$row['id']]);
        }
    }
    if (count($rows) < 25) {
        sleep(5);
    }
}
