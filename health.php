<?php
/* =====================================================================
 * NORTE WALK - Health Check
 * =====================================================================
 * Endpoint standalone para diagnosticar Ferozo sin depender del router.
 * Acceder a: https://amadamia.com.ar/nortewalk/api/health.php
 * NO conecta a la DB por defecto. Pasale ?db=1 para probar conexion.
 * BORRAR ESTE ARCHIVO en produccion una vez que todo funcione.
 * ===================================================================== */

header('Content-Type: application/json; charset=utf-8');

$out = [
    'status'        => 200,
    'message'       => 'health OK',
    'php_version'   => PHP_VERSION,
    'php_sapi'      => PHP_SAPI,
    'server_name'   => $_SERVER['SERVER_NAME']  ?? null,
    'host'          => $_SERVER['HTTP_HOST']    ?? null,
    'request_uri'   => $_SERVER['REQUEST_URI']  ?? null,
    'script'        => __FILE__,
    'cwd'           => getcwd(),
    'extensions'    => [
        'mysqli'   => extension_loaded('mysqli'),
        'openssl'  => extension_loaded('openssl'),
        'mbstring' => extension_loaded('mbstring'),
        'json'     => extension_loaded('json'),
        'curl'     => extension_loaded('curl'),
    ],
    'files_check'   => [
        'config/config.php'       => file_exists(__DIR__ . '/config/config.php'),
        'config/config.local.php' => file_exists(__DIR__ . '/config/config.local.php'),
        'libs/app.php'            => file_exists(__DIR__ . '/libs/app.php'),
        'libs/auth.php'           => file_exists(__DIR__ . '/libs/auth.php'),
        'config/jwt/JWT.php'      => file_exists(__DIR__ . '/config/jwt/JWT.php'),
        '.htaccess'               => file_exists(__DIR__ . '/.htaccess'),
    ],
];

// Probar carga del config
try {
    require_once __DIR__ . '/config/config.php';
    $out['config_loaded'] = true;
    $out['app_env']       = defined('APP_ENV') ? APP_ENV : null;
    $out['db_host']       = defined('HOST') ? HOST : null;
    $out['db_name']       = defined('DB')   ? DB   : null;
    $out['db_user']       = defined('USER') ? USER : null;
    $out['jwt_key_len']   = defined('JWT_KEY') ? strlen(JWT_KEY) : 0;
} catch (Throwable $e) {
    $out['config_loaded']  = false;
    $out['config_error']   = $e->getMessage();
}

// Probar DB solo si lo piden con ?db=1
if (!empty($_GET['db']) && extension_loaded('mysqli') && defined('HOST')) {
    $link = @mysqli_connect(HOST, USER, PASSWORD, DB);
    if (!$link) {
        $out['db_test'] = [
            'status'  => 'fail',
            'error'   => mysqli_connect_error(),
            'errno'   => mysqli_connect_errno()
        ];
    } else {
        $out['db_test'] = ['status' => 'ok', 'server_info' => mysqli_get_server_info($link)];
        mysqli_close($link);
    }
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
