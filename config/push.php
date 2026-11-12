<?php
declare(strict_types=1);

function pushConfig(): ?array
{
    $subject = getenv('VAPID_SUBJECT');
    $publicKey = getenv('VAPID_PUBLIC_KEY');
    $privateKey = getenv('VAPID_PRIVATE_KEY');
    return $subject && $publicKey && $privateKey
        ? compact('subject', 'publicKey', 'privateKey')
        : null;
}

function pushEndpointValido(string $endpoint): bool
{
    if (strlen($endpoint) > 2048 || !filter_var($endpoint, FILTER_VALIDATE_URL)) {
        return false;
    }

    $url = parse_url($endpoint);
    $host = strtolower($url['host'] ?? '');
    return ($url['scheme'] ?? '') === 'https'
        && !isset($url['user']) && !isset($url['pass']) && !isset($url['fragment'])
        && ($url['port'] ?? 443) === 443
        && (in_array($host, ['fcm.googleapis.com', 'android.googleapis.com',
            'updates.push.services.mozilla.com', 'web.push.apple.com'], true)
            || str_ends_with($host, '.push.apple.com')
            || str_ends_with($host, '.notify.windows.com'));
}
