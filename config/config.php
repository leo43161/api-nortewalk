<?php
/* =====================================================================
 * NORTE WALK - CONFIG
 * =====================================================================
 * Este archivo SI se trackea en git. Contiene defaults seguros y los
 * valores de PRODUCCION (Ferozo / DonWeb).
 *
 * Para pisar valores en LOCAL o en el SERVER (con credenciales reales),
 * crear el archivo:
 *     config/config.local.php
 *
 * Ese archivo esta en .gitignore y NO se sube al server por GitHub
 * Actions. Si lo subis manualmente por FTP al server, sobrevive a los
 * redeploys (porque el zip no lo incluye).
 *
 * Ejemplo en config.local.php:
 *     <?php
 *     define('HOST',     'localhost');
 *     define('USER',     'root');
 *     define('PASSWORD', '');
 *     define('DB',       'c2731887_nortew');
 *     define('JWT_KEY',  'algo-largo-y-random');
 * ===================================================================== */

// 1) Override local PRIMERO (si existe) para que tenga precedencia
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// 2) Helper: define solo si no esta definido (permite el override de arriba)
if (!function_exists('nw_define_default')) {
    function nw_define_default($name, $value) {
        if (!defined($name)) define($name, $value);
    }
}

// 3) Deteccion de entorno (si el override no lo seteo)
if (!defined('APP_ENV')) {
    $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'cli';
    $isLocal = (
        strpos($serverName, 'localhost') !== false
        || strpos($serverName, '127.0.0.1') !== false
        || strpos($serverName, '::1') !== false
        || PHP_SAPI === 'cli'
    );
    define('APP_ENV', $isLocal ? 'development' : 'production');
}
nw_define_default('APP_DEBUG', APP_ENV === 'development');

// 4) JWT
// IMPORTANTE: en produccion pisar este valor desde config.local.php
nw_define_default('JWT_KEY', 'NorteWalk_CHANGE_ME_use_64_random_chars_in_production');

// 5) Base de datos
// Defaults de PRODUCCION (Ferozo). En local pisarlos en config.local.php.
nw_define_default('HOST',     'localhost');
nw_define_default('DB',       'c2731887_nortew');
nw_define_default('USER',     'c2731887_nortew');
nw_define_default('PASSWORD', '');   // En el server real va en config.local.php
nw_define_default('CHARSET',  'utf8mb4');

// 6) CORS
nw_define_default('ALLOWED_ORIGINS', json_encode([
    'http://localhost:3000',
    'http://localhost:3001',
    'http://localhost:7777',
    'https://amadamia.com.ar',
    'https://www.amadamia.com.ar',
    'https://nortewalk.com.ar',
    'https://www.nortewalk.com.ar',
    'https://admin.nortewalk.com',
    'https://nortewalk.com'
]));

// 7) Uploads
nw_define_default('UPLOAD_DIR', 'public/img');
nw_define_default('UPLOAD_PUBLIC_BASE',
    APP_ENV === 'development'
        ? '/nortewalk_api/api/public/img'
        : '/nortewalk/api/public/img'
);
nw_define_default('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB
