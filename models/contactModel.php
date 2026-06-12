<?php
/* =====================================================================
 * NORTE WALK - CONTACT MODEL (Publico)
 * Inserts de los formularios públicos: sugerencias, waitlist, ayuda y
 * solicitudes para sumarse (guías / prestadores / empresas).
 * ===================================================================== */

class contactModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Inserta un mensaje libre (suggestion | waitlist | help | contact).
     * Devuelve el id insertado o 0 si falló.
     */
    public function createMessage($d)
    {
        $c = $this->db->connect();
        $sql = sprintf(
            "INSERT INTO contact_messages
                (kind, name, email, phone, subject, message, locale, ref,
                 source_url, utm_source, utm_medium, utm_campaign, ip_address, user_agent)
             VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            $this->esc($c, $d['kind']),
            $this->esc($c, $d['name']       ?? null),
            $this->esc($c, $d['email']      ?? null),
            $this->esc($c, $d['phone']      ?? null),
            $this->esc($c, $d['subject']    ?? null),
            $this->esc($c, $d['message']    ?? null),
            $this->esc($c, $d['locale']     ?? 'es'),
            $this->esc($c, $d['ref']        ?? null),
            $this->esc($c, $d['source_url'] ?? null),
            $this->esc($c, $d['utm_source'] ?? null),
            $this->esc($c, $d['utm_medium'] ?? null),
            $this->esc($c, $d['utm_campaign'] ?? null),
            $this->esc($c, $d['ip']         ?? null),
            $this->esc($c, $d['user_agent'] ?? null)
        );
        $ok = mysqli_query($c, $sql);
        $id = $ok ? mysqli_insert_id($c) : 0;
        mysqli_close($c);
        return $id;
    }

    /**
     * Inserta una solicitud para sumarse a la plataforma.
     * Devuelve el id insertado o 0 si falló.
     */
    public function createJoin($d)
    {
        $c = $this->db->connect();
        $sql = sprintf(
            "INSERT INTO join_requests
                (applicant_type, business_name, contact_name, email, phone, city,
                 website, offering, message, locale, source_url,
                 utm_source, utm_medium, utm_campaign, ip_address, user_agent)
             VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            $this->esc($c, $d['applicant_type'] ?? 'guide'),
            $this->esc($c, $d['business_name']  ?? null),
            $this->esc($c, $d['contact_name']),
            $this->esc($c, $d['email']),
            $this->esc($c, $d['phone']        ?? null),
            $this->esc($c, $d['city']         ?? null),
            $this->esc($c, $d['website']      ?? null),
            $this->esc($c, $d['offering']     ?? null),
            $this->esc($c, $d['message']      ?? null),
            $this->esc($c, $d['locale']       ?? 'es'),
            $this->esc($c, $d['source_url']   ?? null),
            $this->esc($c, $d['utm_source']   ?? null),
            $this->esc($c, $d['utm_medium']   ?? null),
            $this->esc($c, $d['utm_campaign'] ?? null),
            $this->esc($c, $d['ip']           ?? null),
            $this->esc($c, $d['user_agent']   ?? null)
        );
        $ok = mysqli_query($c, $sql);
        $id = $ok ? mysqli_insert_id($c) : 0;
        mysqli_close($c);
        return $id;
    }
}
?>
