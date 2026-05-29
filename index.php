<?php
/* =====================================================================
 * NORTE WALK API - Entry Point
 * ===================================================================== */

// ---------------------------------------------------------------------
// 1) Logging defensivo: en Ferozo display_errors=off por defecto, y un
//    fatal silencioso = 500 sin pista. Forzamos log a archivo SIEMPRE.
// ---------------------------------------------------------------------
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/error_log');
error_reporting(E_ALL);

// Handler para fatales: convierte E_ERROR / E_PARSE en JSON 500
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;
    $fatales = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatales, true)) return;
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $debug = defined('APP_DEBUG') && APP_DEBUG;
    echo json_encode([
        'status'  => 500,
        'message' => 'Error interno',
        'error'   => $debug ? $err : 'Revisar error_log del servidor'
    ]);
});

// Excepciones no atrapadas
set_exception_handler(function ($e) {
    error_log('Uncaught exception: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    $debug = defined('APP_DEBUG') && APP_DEBUG;
    echo json_encode([
        'status'  => 500,
        'message' => 'Excepcion no manejada',
        'error'   => $debug ? $e->getMessage() : null
    ]);
});

// ---------------------------------------------------------------------
// 2) Forzar CWD al directorio del index.php (algunos hostings lo cambian)
// ---------------------------------------------------------------------
chdir(__DIR__);

// ---------------------------------------------------------------------
// 3) Carga del framework
// ---------------------------------------------------------------------
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/libs/utils.php';
require_once __DIR__ . '/libs/auth.php';
require_once __DIR__ . '/libs/database.php';
require_once __DIR__ . '/libs/controller.php';
require_once __DIR__ . '/libs/model.php';
require_once __DIR__ . '/libs/app.php';

// ---------------------------------------------------------------------
// 4) Modo debug: en dev mostramos errores, en prod solo log
// ---------------------------------------------------------------------
if (defined('APP_DEBUG') && APP_DEBUG) {
    @ini_set('display_errors', '1');
} else {
    @ini_set('display_errors', '0');
}

// ---------------------------------------------------------------------
// 5) Bootstrap
// ---------------------------------------------------------------------
$app = new App();
