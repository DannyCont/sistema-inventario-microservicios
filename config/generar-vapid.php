<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
$autoload = '/opt/flexialmacen/vendor/autoload.php';
require_once is_file($autoload) ? $autoload : __DIR__ . '/vendor/autoload.php';
$keys = Minishlink\WebPush\VAPID::createVapidKeys();
echo 'VAPID_PUBLIC_KEY=' . $keys['publicKey'] . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . $keys['privateKey'] . PHP_EOL;
