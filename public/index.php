<?php

use App\Kernel;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__).'/config/bootstrap.php';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);

    Debug::enable();
}

if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? false) {
    Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
}

if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? false) {
    Request::setTrustedHosts([$trustedHosts]);
}
// Support for behat test from stepup-deploy, when the `testcookie` and GuzzleHttp user agent
// are present and app_env is not prod, then engage smoketest mode.
if ($_SERVER['APP_ENV'] !== 'prod' &&
    isset($_COOKIE['testcookie']) &&
    strpos($_SERVER['HTTP_USER_AGENT'], 'GuzzleHttp') !== false
) {
    $_SERVER['APP_ENV'] = 'smoketest';
    $_SERVER['APP_DEBUG'] = true;
}

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
