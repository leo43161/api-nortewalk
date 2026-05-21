<?php
/**
 * Norte Walk - Test de conexión y SPs
 * USO: php test_connection.php  (CLI)
 *      o acceder via browser una vez en el servidor
 * BORRAR después de testear.
 */

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== NORTE WALK - DIAGNOSTICO ===\n\n";

// 1. Constantes cargadas
echo "[1] Config:\n";
echo "  HOST      = " . HOST . "\n";
echo "  DB        = " . DB . "\n";
echo "  USER      = " . USER . "\n";
echo "  APP_ENV   = " . APP_ENV . "\n";
echo "  JWT_KEY   = " . (strlen(JWT_KEY) >= 16 ? 'OK (' . strlen(JWT_KEY) . ' chars)' : 'CORTO o vacio') . "\n\n";

// 2. Conexion MySQL
echo "[2] Conexion MySQL: ";
$link = @mysqli_connect(HOST, USER, PASSWORD, DB);
if (!$link) {
    echo "FALLO - " . mysqli_connect_error() . "\n";
    exit(1);
}
echo "OK\n";

// 3. Charset
mysqli_set_charset($link, 'utf8mb4');
echo "[3] Charset utf8mb4: OK\n\n";

// 4. Tablas existentes
echo "[4] Tablas en " . DB . ":\n";
$r = mysqli_query($link, "SHOW TABLES");
$tablas = [];
while ($row = mysqli_fetch_row($r)) {
    $tablas[] = $row[0];
    echo "  - " . $row[0] . "\n";
}
echo "\n";

$requeridas = [
    'providers','experiences','experience_images','experience_inclusions',
    'experience_itinerary','experience_schedules','experience_blackout_dates',
    'experience_translations','leads','admin_users','subscriptions'
];
$faltantes = array_diff($requeridas, $tablas);
if ($faltantes) {
    echo "  FALTAN: " . implode(', ', $faltantes) . "\n";
    echo "  -> Ejecuta norte_walk.sql primero\n\n";
} else {
    echo "  Todas las tablas OK\n\n";
}

// 5. Stored Procedures
echo "[5] Stored Procedures:\n";
$r = mysqli_query($link, "SELECT ROUTINE_NAME FROM information_schema.ROUTINES
    WHERE ROUTINE_SCHEMA = '" . DB . "' AND ROUTINE_TYPE = 'PROCEDURE'
    ORDER BY ROUTINE_NAME");
$sps = [];
while ($row = mysqli_fetch_assoc($r)) {
    $sps[] = $row['ROUTINE_NAME'];
}
echo "  Total SPs: " . count($sps) . " (esperados: 52)\n";
if (count($sps) < 52) {
    echo "  -> Ejecuta norte_walk_procedures.sql\n";
}
echo "\n";

// 6. Test SP simple: sp_experience_list_public
echo "[6] Test sp_experience_list_public:\n";
$sql = "CALL sp_experience_list_public(NULL,NULL,NULL,NULL,'es',1,10)";
if (mysqli_multi_query($link, $sql)) {
    $res = mysqli_store_result($link);
    $rows = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $rows[] = $row;
        mysqli_free_result($res);
    }
    while (mysqli_more_results($link) && mysqli_next_result($link)) {
        $res = mysqli_store_result($link);
        if ($res) mysqli_free_result($res);
    }
    echo "  OK - " . count($rows) . " experiencias encontradas\n\n";
} else {
    echo "  FALLO - " . mysqli_error($link) . "\n\n";
}

// 7. Admin users
echo "[7] Admin users:\n";
$r = mysqli_query($link, "SELECT id, email, role, is_active FROM admin_users LIMIT 5");
if ($r && mysqli_num_rows($r) > 0) {
    while ($row = mysqli_fetch_assoc($r)) {
        echo "  id={$row['id']} email={$row['email']} role={$row['role']} active={$row['is_active']}\n";
    }
} else {
    echo "  Sin admins. Crea uno con password_hash():\n";
    echo "  INSERT INTO admin_users (email,password_hash,full_name,role)\n";
    echo "  VALUES ('tu@email.com', '\$2y\$...hash...', 'Nombre', 'superadmin');\n";
}
echo "\n";

// 8. JWT
echo "[8] JWT:\n";
require_once __DIR__ . '/libs/auth.php';
$jwt = new JwtHandler(JWT_KEY);
$token = $jwt->generateToken(999, 'test@test.com', 'superadmin');
$decoded = $jwt->validate($token);
var_dump($decoded);
if ($decoded['status'] === 'success') {
    echo "  Generacion: OK\n";
    echo "  Validacion: OK (role=" . $decoded["data"]->data->role . ")\n";
    echo "  Token: " . $token . ")\n";
} else {
    echo "  FALLO: " . "" . "\n";
    var_dump($decoded);
}
echo "\n";

mysqli_close($link);

echo "=== FIN DIAGNOSTICO ===\n";
echo "Borrar este archivo antes de ir a produccion.\n";
