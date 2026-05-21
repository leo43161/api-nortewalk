<?php
/* =====================================================================
 * NORTE WALK - CATALOG CONTROLLER (Publico)
 * Endpoints publicos para el frontend Next.js.
 * No requieren JWT.
 * ===================================================================== */

class Catalog extends Controller
{
    function render()
    {
        setCors('GET');
        jsonResponse(200, 'Norte Walk Catalog (Publico)', null, [
            'endpoints' => [
                'GET  /catalog/experiences',
                'GET  /catalog/experience?slug=xxx',
                'GET  /catalog/available_dates?experience_id=N&from=YYYY-MM-DD&to=YYYY-MM-DD',
                'GET  /catalog/provider?slug=xxx',
                'POST /catalog/lead'
            ]
        ]);
    }

    /**
     * Lista catalogo publico con filtros.
     * Query: city, vertical, category, type, locale, limit, offset
     */
    public function experiences()
    {
        setCors('GET');
        $res = $this->model->experienceListPublic(
            $_GET['city']     ?? null,
            $_GET['vertical'] ?? null,
            $_GET['category'] ?? null,
            $_GET['type']     ?? null,
            $_GET['locale']   ?? 'es',
            (int)($_GET['limit']  ?? 24),
            (int)($_GET['offset'] ?? 0)
        );
        jsonResponse(200, 'OK', $res['rows'], ['total' => $res['total']]);
    }

    /**
     * Detalle de experiencia publica + imagenes + inclusiones + itinerario + schedules.
     */
    public function experience()
    {
        setCors('GET');
        $slug   = $_GET['slug'] ?? null;
        $locale = $_GET['locale'] ?? 'es';
        if (!$slug) jsonResponse(400, 'Falta slug');

        $res = $this->model->experienceBySlug($slug, $locale);
        if (!$res) jsonResponse(404, 'No encontrado');
        jsonResponse(200, 'OK', $res);
    }

    /**
     * Calendario reactivo: fechas reales disponibles.
     */
    public function available_dates()
    {
        setCors('GET');
        $eid    = (int)($_GET['experience_id'] ?? 0);
        $from   = $_GET['from'] ?? date('Y-m-d');
        $to     = $_GET['to']   ?? date('Y-m-d', strtotime('+30 days'));
        $locale = $_GET['locale'] ?? null;

        if (!$eid) jsonResponse(400, 'Falta experience_id');

        $res = $this->model->availableDates($eid, $from, $to, $locale);
        jsonResponse(200, 'OK', $res);
    }

    /**
     * Datos publicos de un proveedor por slug.
     */
    public function provider()
    {
        setCors('GET');
        $slug = $_GET['slug'] ?? null;
        if (!$slug) jsonResponse(400, 'Falta slug');
        $res = $this->model->providerBySlug($slug);
        if (!$res) jsonResponse(404, 'No encontrado');
        jsonResponse(200, 'OK', $res);
    }

    /**
     * Crea un lead. Devuelve link wa.me listo para redirigir.
     */
    public function lead()
    {
        setCors('POST');
        $d = readJson();

        $required = ['experience_id', 'tourist_name', 'tourist_phone', 'desired_date', 'pax'];
        foreach ($required as $f) {
            if (empty($d[$f])) jsonResponse(400, "Falta $f");
        }

        // Auto-capturar IP y UA
        $d['ip']         = $_SERVER['REMOTE_ADDR'] ?? null;
        $d['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        $res = $this->model->leadCreate($d);
        if (!$res) jsonResponse(500, 'Error al crear lead');
        if (isset($res['error'])) {
            $msg = $res['error'];
            if (stripos($msg, 'no disponible') !== false || stripos($msg, 'inactivo') !== false) {
                jsonResponse(422, 'Experiencia no disponible o proveedor inactivo');
            }
            jsonResponse(500, 'Error al crear lead: ' . $msg);
        }

        // Armar link WhatsApp con mensaje prearmado
        $waLink = $this->buildWhatsAppLink($res, $d);

        jsonResponse(201, 'Lead registrado', [
            'lead_id'      => $res['lead_id'],
            'whatsapp_url' => $waLink
        ]);
    }

    private function buildWhatsAppLink($leadInfo, $d)
    {
        $phone = preg_replace('/\D/', '', $leadInfo['whatsapp_e164'] ?? '');
        $msg = "Hola! Soy {$d['tourist_name']}, me interesa reservar.\n";
        $msg .= "Fecha: {$d['desired_date']}\n";
        if (!empty($d['desired_time'])) $msg .= "Hora: {$d['desired_time']}\n";
        $msg .= "Personas: {$d['pax']}\n";
        if (!empty($d['message'])) $msg .= "Mensaje: {$d['message']}\n";
        $msg .= "(Reserva via Norte Walk)";
        return "https://wa.me/$phone?text=" . urlencode($msg);
    }
}
?>
