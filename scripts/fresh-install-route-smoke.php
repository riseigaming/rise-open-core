<?php
// Real HTTP with a private cookie jar. Credentials arrive on stdin, never argv or logs.
require dirname(__DIR__).'/vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true);
$origin = rtrim((string) ($input['origin'] ?? ''), '/');
$parts = parse_url($origin);
if (!is_array($input) || !is_array($parts) || ($parts['scheme'] ?? '') !== 'http'
    || !in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true) || empty($parts['port'])
    || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
    || !empty($parts['path']) || empty($input['username']) || empty($input['password'])) {
    fwrite(STDERR, "FAIL explicit loopback HTTP origin and stdin credentials required\n"); exit(1);
}
$client = new GuzzleHttp\Client(['base_uri' => $origin, 'cookies' => new GuzzleHttp\Cookie\CookieJar(),
    'http_errors' => false, 'allow_redirects' => false, 'timeout' => 10]);
$check = static function (bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); };
try {
    $home = $client->get('/'); $check($home->getStatusCode() === 200, 'public home');
    preg_match('/name="csrf-token" content="([^"]+)"/', (string) $home->getBody(), $matches);
    $check(!empty($matches[1]), 'real CSRF token'); $token = html_entity_decode($matches[1]);
    $check($client->get('/cms/admin')->getStatusCode() === 302, 'guest admin denial');
    $login = $client->post('/cms/login', ['form_params' => ['_token' => $token, 'email' => $input['username'], 'password' => $input['password']],
        'headers' => ['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest']]);
    $payload = json_decode((string) $login->getBody(), true); $check($login->getStatusCode() === 200 && ($payload['status'] ?? null) === 200, 'CMS guard login');
    unset($input['password']);
    foreach (['/cms/admin', '/cms/admin/menus/edit', '/cms/admin/settings/site-settings', '/cms/promotions', '/cms/sessions'] as $path) {
        $check($client->get($path)->getStatusCode() === 200, 'selected route '.$path);
    }
    $check($client->post('/cms/admin/menus/update', ['form_params' => ['slot' => 'sidebar', 'menu' => '[]']])->getStatusCode() === 419, 'CSRF denial');
    $check($client->get('/cms/logout')->getStatusCode() === 302, 'logout');
    $check($client->get('/cms/admin')->getStatusCode() === 302, 'logout replay denied');
    echo "PASS real HTTP CMS login, guest denial, selected routes, CSRF and logout replay\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL '.($error instanceof RuntimeException ? $error->getMessage() : get_class($error))."\n"); exit(1);
}
