<?php
/**
 * Norte Walk - Utilidades comunes
 */

/**
 * Setea headers CORS + JSON segun ALLOWED_ORIGINS.
 */
function setCors($methods = 'GET, POST, OPTIONS')
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowed = json_decode(constant('ALLOWED_ORIGINS'), true) ?: [];

    if (in_array($origin, $allowed) || constant('APP_ENV') === 'development') {
        header("Access-Control-Allow-Origin: " . ($origin ?: '*'));
    }
    header("Access-Control-Allow-Methods: $methods");
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Allow-Credentials: true');
    header('Content-Type: application/json; charset=utf-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

/**
 * Devuelve respuesta JSON y termina ejecucion.
 */
function jsonResponse($status, $message = 'OK', $data = null, $extra = [])
{
    http_response_code($status);
    $payload = ['status' => $status, 'message' => $message];
    if ($data !== null) $payload['data'] = $data;
    if (!empty($extra)) $payload = array_merge($payload, $extra);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Extrae y valida JWT del header Authorization. Devuelve payload o termina con 401.
 */
function requireAuth($rolesPermitidos = null)
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authHeader = $headers['Authorization']
        ?? $headers['authorization']
        ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? null;

    if (!$authHeader || !preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
        jsonResponse(401, 'Token faltante');
    }

    $jwt = new JwtHandler(constant('JWT_KEY'));
    $res = $jwt->validate($m[1]);
    if ($res['status'] !== 'success') {
        jsonResponse(401, $res['message']);
    }

    $payload = $res['data'];
    if ($rolesPermitidos !== null) {
        $rol = $payload->data->role ?? null;
        if (!in_array($rol, (array) $rolesPermitidos)) {
            jsonResponse(403, 'Permisos insuficientes');
        }
    }
    return $payload;
}

/**
 * Exige rol=admin. 403 si es provider.
 */
function requireAdmin()
{
    return requireAuth('admin');
}

/**
 * Devuelve el provider_id del token si el usuario es provider, NULL si es admin.
 * Útil para forzar scope en queries: si es admin no hay scope, si es provider hay que filtrar.
 */
function getScopeProviderId($payload)
{
    $role = $payload->data->role ?? null;
    if ($role === 'provider') {
        $pid = $payload->data->provider_id ?? null;
        if (!$pid) {
            jsonResponse(403, 'Provider sin provider_id asignado');
        }
        return (int)$pid;
    }
    return null; // admin
}

/**
 * Si el usuario es provider, valida que el providerId solicitado coincida con el suyo.
 * Si es admin, no hace nada.
 */
function assertProviderScope($payload, $providerId)
{
    $scope = getScopeProviderId($payload);
    if ($scope !== null && (int)$providerId !== $scope) {
        jsonResponse(403, 'No tenes permiso sobre ese provider');
    }
}

/**
 * Valida que la experience pertenezca al provider del token.
 * Si es admin, no hace nada. Si la exp no existe, 404.
 */
function assertOwnsExperience($model, $payload, $experienceId)
{
    $scope = getScopeProviderId($payload);
    if ($scope === null) return; // admin
    $owner = $model->getExperienceProviderId((int)$experienceId);
    if ($owner === null) {
        jsonResponse(404, 'Experiencia no encontrada');
    }
    if ((int)$owner !== $scope) {
        jsonResponse(403, 'Esa experiencia no es tuya');
    }
}

/**
 * Valida que el lead pertenezca al provider del token.
 */
function assertOwnsLead($model, $payload, $leadId)
{
    $scope = getScopeProviderId($payload);
    if ($scope === null) return;
    $owner = $model->getLeadProviderId((int)$leadId);
    if ($owner === null) {
        jsonResponse(404, 'Lead no encontrado');
    }
    if ((int)$owner !== $scope) {
        jsonResponse(403, 'Ese lead no es tuyo');
    }
}

/**
 * Helper genérico: valida que un recurso (resuelto por un provider_id externo)
 * pertenezca al provider del token. Si admin, no hace nada.
 *  - $providerId === null  → 404 (recurso no existe)
 *  - $providerId !== scope → 403
 */
function assertOwnsResource($payload, $providerId)
{
    $scope = getScopeProviderId($payload);
    if ($scope === null) return;
    if ($providerId === null) {
        jsonResponse(404, 'Recurso no encontrado');
    }
    if ((int)$providerId !== $scope) {
        jsonResponse(403, 'No tenes permiso sobre ese recurso');
    }
}

/**
 * Lee body JSON del request y lo devuelve como array asociativo.
 */
function readJson()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Genera slug seguro para URL.
 */
function slugify($text)
{
    $text = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/[\s-]+/', '-', $text);
    return $text ?: ('item-' . time());
}

/**
 * Procesa upload de imagen unica.
 *
 * @param array  $file    Entrada $_FILES['campo']
 * @param string $subdir  Subcarpeta dentro de UPLOAD_DIR (ej: 'experiences')
 * @param string $prefix  Prefijo opcional para el nombre (ej: "exp12_")
 * @param int    $maxSize Tamano maximo en bytes (default MAX_UPLOAD_SIZE)
 *
 * @return array {status, url?, public_url?, filename?, message?}
 *   - url: URL absoluta servible desde el navegador
 *   - public_url: misma URL (compat)
 */
function uploadImage($file, $subdir = 'experiences', $prefix = '', $maxSize = null)
{
    $maxSize = $maxSize ?: constant('MAX_UPLOAD_SIZE');
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => 400, 'message' => 'Error en el upload'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return ['status' => 400, 'message' => 'Formato no permitido (jpg, png, webp, gif)'];
    }
    if ($file['size'] > $maxSize) {
        return ['status' => 400, 'message' => 'Archivo demasiado grande (max 5MB)'];
    }

    // Sanitizar subdir y prefix para evitar path traversal
    $subdir = preg_replace('/[^a-zA-Z0-9_\-]/', '', $subdir) ?: 'experiences';
    $prefix = preg_replace('/[^a-zA-Z0-9_\-]/', '', $prefix);

    // Carpeta absoluta en el FS (relativa al index.php del API)
    $apiRoot = realpath(dirname(__DIR__));
    $dirAbs  = $apiRoot . DIRECTORY_SEPARATOR . constant('UPLOAD_DIR') . DIRECTORY_SEPARATOR . $subdir;

    if (!is_dir($dirAbs)) {
        if (!mkdir($dirAbs, 0777, true)) {
            return ['status' => 500, 'message' => 'No se pudo crear la carpeta destino'];
        }
    }

    $name = ($prefix ? $prefix . '_' : '') . uniqid('img_') . '.' . $ext;
    $pathAbs = $dirAbs . DIRECTORY_SEPARATOR . $name;

    if (!move_uploaded_file($file['tmp_name'], $pathAbs)) {
        return ['status' => 500, 'message' => 'Error al guardar archivo'];
    }

    // URL publica: <scheme>://<host><UPLOAD_PUBLIC_BASE>/<subdir>/<file>
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base   = rtrim(constant('UPLOAD_PUBLIC_BASE'), '/');
    $url    = $scheme . '://' . $host . $base . '/' . $subdir . '/' . $name;

    return [
        'status'     => 200,
        'message'    => 'Imagen subida',
        'url'        => $url,
        'public_url' => $url,
        'filename'   => $name,
    ];
}
?>
