<?php
/* =====================================================================
 * NORTE WALK - CONFIG LOCAL (EJEMPLO)
 * =====================================================================
 * Copiar este archivo a config/config.local.php y completar valores.
 * El archivo config.local.php esta en .gitignore.
 *
 * USOS:
 *  - En desarrollo (XAMPP): override de HOST/USER/PASSWORD locales.
 *  - En produccion (Ferozo): subir POR FTP UNA SOLA VEZ con las
 *    credenciales reales de MySQL del panel cPanel/DonWeb. El zip de
 *    GitHub Actions NO lo incluye, asi que sobrevive a redeploys.
 * ===================================================================== */

// --- Base de datos ---
define('HOST',     'localhost');
define('DB',       'c2731887_nortew');
define('USER',     'c2731887_nortew');     // En Ferozo: el usuario MySQL que creaste en cPanel
define('PASSWORD', 'TU_PASSWORD_REAL');    // Password del usuario MySQL
define('CHARSET',  'utf8mb4');

// --- JWT ---
// Generar con: php -r "echo bin2hex(random_bytes(32));"
define('JWT_KEY',  'reemplazar-por-string-largo-y-aleatorio');

// --- Entorno ---
// En local podes forzar development; en Ferozo dejalo en production.
// define('APP_ENV',   'production');
// define('APP_DEBUG', false);
