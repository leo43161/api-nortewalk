<?php
/* =====================================================================
 * NORTE WALK API - Entry Point
 * ===================================================================== */

require_once 'config/config.php';
require_once 'libs/utils.php';
require_once 'libs/auth.php';
require_once 'libs/database.php';
require_once 'libs/controller.php';
require_once 'libs/model.php';
require_once 'libs/app.php';

// Reportar errores solo en dev
if (constant('APP_DEBUG')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

$app = new App();
?>
