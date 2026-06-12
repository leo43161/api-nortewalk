<?php
/* =====================================================================
 * NORTE WALK - CONTACT CONTROLLER (Publico)
 * Formularios públicos del frontend Next.js. No requieren JWT.
 *
 *   POST /contact/suggest   → sugerencias del home ("qué te gustaría...")
 *   POST /contact/waitlist  → "avisame cuando se habilite" (pre-lanzamiento)
 *   POST /contact/help      → formulario de Ayuda
 *   POST /contact/join      → guías/prestadores que quieren sumarse
 * ===================================================================== */

class Contact extends Controller
{
    function render()
    {
        setCors('GET');
        jsonResponse(200, 'Norte Walk Contact (Publico)', null, [
            'endpoints' => [
                'POST /contact/suggest',
                'POST /contact/waitlist',
                'POST /contact/help',
                'POST /contact/join',
            ]
        ]);
    }

    /** Sugerencia libre: requiere un mensaje. Email/nombre opcionales. */
    public function suggest()
    {
        $d = $this->prelude();
        if ($this->isBlank($d['message'] ?? null)) {
            jsonResponse(400, 'Contanos qué te gustaría ver en el Norte');
        }
        $this->store('suggestion', $d);
    }

    /** Waitlist: requiere al menos un contacto (email o teléfono). */
    public function waitlist()
    {
        $d = $this->prelude();
        if ($this->isBlank($d['email'] ?? null) && $this->isBlank($d['phone'] ?? null)) {
            jsonResponse(400, 'Dejanos un email o WhatsApp para avisarte');
        }
        if (!$this->isBlank($d['email'] ?? null) && !$this->validEmail($d['email'])) {
            jsonResponse(400, 'Email inválido');
        }
        $this->store('waitlist', $d);
    }

    /** Ayuda: requiere mensaje + un medio de contacto para responder. */
    public function help()
    {
        $d = $this->prelude();
        if ($this->isBlank($d['message'] ?? null)) {
            jsonResponse(400, 'Escribinos en qué te podemos ayudar');
        }
        if ($this->isBlank($d['email'] ?? null) && $this->isBlank($d['phone'] ?? null)) {
            jsonResponse(400, 'Dejanos un email o WhatsApp para responderte');
        }
        if (!$this->isBlank($d['email'] ?? null) && !$this->validEmail($d['email'])) {
            jsonResponse(400, 'Email inválido');
        }
        $this->store('help', $d);
    }

    /** Sumate: guía/prestador/empresa. Requiere nombre de contacto + email. */
    public function join()
    {
        $d = $this->prelude();
        if ($this->isBlank($d['contact_name'] ?? null)) {
            jsonResponse(400, 'Falta tu nombre');
        }
        if ($this->isBlank($d['email'] ?? null) || !$this->validEmail($d['email'])) {
            jsonResponse(400, 'Falta un email válido');
        }
        $id = $this->model->createJoin($d);
        if (!$id) jsonResponse(500, 'No pudimos guardar tu solicitud. Probá de nuevo.');
        jsonResponse(201, 'Solicitud recibida', ['id' => $id]);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * CORS + parse + honeypot + captura de IP/UA + normalización de locale.
     * Si el honeypot `hp` viene lleno (bot), fingimos éxito y cortamos.
     */
    private function prelude()
    {
        setCors('POST');
        $d = readJson();

        if (!empty($d['hp'])) {
            // Bot detectado: respondemos 200 para no darle señal.
            jsonResponse(200, 'OK', ['id' => 0]);
        }

        $loc = $d['locale'] ?? 'es';
        $d['locale'] = in_array($loc, ['es', 'en', 'pt'], true) ? $loc : 'es';
        $d['ip']         = $_SERVER['REMOTE_ADDR'] ?? null;
        $d['user_agent'] = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        return $d;
    }

    /** Inserta un contact_message y responde 201. */
    private function store($kind, $d)
    {
        $d['kind'] = $kind;
        $id = $this->model->createMessage($d);
        if (!$id) jsonResponse(500, 'No pudimos guardar tu mensaje. Probá de nuevo.');
        jsonResponse(201, 'Recibido', ['id' => $id]);
    }

    private function isBlank($v)
    {
        return $v === null || trim((string)$v) === '';
    }

    private function validEmail($v)
    {
        return (bool) filter_var(trim((string)$v), FILTER_VALIDATE_EMAIL);
    }
}
?>
