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
     * Crea una reserva (lead). Valida cupo contra el schedule elegido.
     *
     * Body: experience_id, schedule_id, desired_date,
     *       tourist_name, tourist_surname, tourist_phone, tourist_email,
     *       pax_adults, pax_children, preferred_locale, message, utms...
     *
     * 201 -> { booking_code, lead_id, summary..., whatsapp_url }
     * 409 -> sin cupo para ese horario
     * 422 -> experiencia/horario no disponible
     */
    public function lead()
    {
        setCors('POST');
        $d = readJson();

        $required = ['experience_id', 'tourist_name', 'tourist_phone', 'desired_date'];
        foreach ($required as $f) {
            if (empty($d[$f])) jsonResponse(400, "Falta $f");
        }
        if (empty($d['pax_adults']) && empty($d['pax'])) {
            jsonResponse(400, 'Falta pax_adults');
        }
        // Retrocompatibilidad: si llega "pax" viejo, tratarlo como adultos.
        if (empty($d['pax_adults'])) {
            $d['pax_adults'] = (int)$d['pax'];
        }

        // Auto-capturar IP y UA
        $d['ip']         = $_SERVER['REMOTE_ADDR'] ?? null;
        $d['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

        // booking_code único; reintenta si colisiona (uq_leads_booking_code)
        $res = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $d['booking_code'] = $this->makeBookingCode();
            $res = $this->model->leadCreate($d);
            $isDup = is_array($res) && isset($res['errno']) && (int)$res['errno'] === 1062;
            if (!$isDup) break;
        }

        if (!$res) jsonResponse(500, 'Error al crear la reserva');
        if (isset($res['error'])) {
            $state = $res['sqlstate'] ?? '';
            if ($state === '45001') {
                jsonResponse(409, 'Sin cupo disponible para ese horario');
            }
            if ($state === '45000') {
                jsonResponse(422, $res['error']);
            }
            jsonResponse(500, 'Error al crear la reserva: ' . $res['error']);
        }

        jsonResponse(201, 'Reserva registrada', [
            'lead_id'          => $res['lead_id'],
            'booking_code'     => $res['booking_code'],
            'experience_title' => $res['experience_title'],
            'provider_name'    => $res['provider_name'],
            'desired_date'     => $res['desired_date'],
            'desired_time'     => $res['desired_time'],
            'tour_locale'      => $res['tour_locale'],
            'pax'              => $res['pax'],
            'whatsapp_url'     => $this->buildWhatsAppLink($res, $d)
        ]);
    }

    /** Código corto legible: NW-7K3FQ (sin 0/O ni 1/I para evitar confusión). */
    private function makeBookingCode()
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'NW-' . $code;
    }

    private function buildWhatsAppLink($leadInfo, $d)
    {
        $phone = preg_replace('/\D/', '', $leadInfo['whatsapp_e164'] ?? '');
        $fullName = trim($d['tourist_name'] . ' ' . ($d['tourist_surname'] ?? ''));
        $msg = "Hola! Soy {$fullName}, hice la reserva {$leadInfo['booking_code']} por Norte Walk.\n";
        $msg .= "Tour: {$leadInfo['experience_title']}\n";
        $msg .= "Fecha: {$leadInfo['desired_date']}";
        if (!empty($leadInfo['desired_time'])) $msg .= " " . substr($leadInfo['desired_time'], 0, 5) . "hs";
        $msg .= "\nPersonas: {$leadInfo['pax']}\n";
        if (!empty($d['message'])) $msg .= "Mensaje: {$d['message']}\n";
        $msg .= "Quedo a la espera de la confirmación. Gracias!";
        return "https://wa.me/$phone?text=" . urlencode($msg);
    }
}
?>
