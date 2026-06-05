<?php
/* =====================================================================
 * NORTE WALK - CATALOG MODEL (Publico)
 * ===================================================================== */

class catalogModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function experienceListPublic($city, $vertical, $category, $type, $locale, $limit, $offset)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_experience_list_public(%s,%s,%s,%s,%s,%d,%d)",
            $this->esc($c, $city),
            $this->esc($c, $vertical),
            $this->esc($c, $category),
            $this->esc($c, $type),
            $this->esc($c, $locale ?? 'es'),
            $limit, $offset
        );
        $sets = $this->callMulti($c, $sql);
        mysqli_close($c);
        return [
            'rows'  => $sets[0] ?? [],
            'total' => (int)($sets[1][0]['total'] ?? 0)
        ];
    }

    public function experienceBySlug($slug, $locale)
    {
        $c = $this->db->connect();

        // 1. Detalle base
        $sql = sprintf("CALL sp_experience_get_by_slug_public(%s,%s)",
            $this->esc($c, $slug),
            $this->esc($c, $locale ?? 'es')
        );
        $main = $this->callOne($c, $sql);
        if (!$main) {
            mysqli_close($c);
            return null;
        }

        $expId = (int)$main['id'];

        // 2. Imagenes
        $main['images']     = $this->callAll($c, "CALL sp_image_list_by_experience($expId)");

        // 3. Inclusiones (i18n)
        $sqlInc = sprintf("CALL sp_inclusion_list_by_experience(%d,%s)", $expId, $this->esc($c, $locale ?? 'es'));
        $main['inclusions'] = $this->callAll($c, $sqlInc);

        // 4. Itinerario (i18n)
        $sqlItin = sprintf("CALL sp_itinerary_list_by_experience(%d,%s)", $expId, $this->esc($c, $locale ?? 'es'));
        $main['itinerary']  = $this->callAll($c, $sqlItin);

        // 5. Schedules
        $main['schedules']  = $this->callAll($c, "CALL sp_schedule_list_by_experience($expId)");

        // 6. Idiomas hablados por el guía
        $main['languages']  = $this->callAll($c, "CALL sp_language_list_by_experience($expId)");

        mysqli_close($c);
        return $main;
    }

    public function availableDates($experienceId, $from, $to, $locale)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_schedule_get_available_dates(%d,%s,%s,%s)",
            (int)$experienceId,
            $this->esc($c, $from),
            $this->esc($c, $to),
            $this->esc($c, $locale)
        );
        $rows = $this->callAll($c, $sql);
        mysqli_close($c);
        return $rows;
    }

    public function providerBySlug($slug)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_provider_get_by_slug(%s)", $this->esc($c, $slug));
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function leadCreate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_lead_create(%d,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s)",
            (int)$d['experience_id'],
            !empty($d['schedule_id']) ? (int)$d['schedule_id'] : 'NULL',
            $this->esc($c, $d['tourist_name']),
            $this->esc($c, $d['tourist_phone']),
            $this->esc($c, $d['tourist_email']     ?? null),
            $this->esc($c, $d['preferred_locale'] ?? 'es'),
            $this->esc($c, $d['desired_date']),
            $this->esc($c, $d['desired_time']     ?? null),
            (int)$d['pax'],
            $this->esc($c, $d['message']      ?? null),
            $this->esc($c, $d['source']       ?? null),
            $this->esc($c, $d['utm_source']   ?? null),
            $this->esc($c, $d['utm_medium']   ?? null),
            $this->esc($c, $d['utm_campaign'] ?? null),
            $this->esc($c, $d['ip']           ?? null),
            $this->esc($c, $d['user_agent']   ?? null)
        );
        $resultado = mysqli_query($c, $sql);
        if (!$resultado) {
            $err = mysqli_error($c);
            mysqli_close($c);
            return ['error' => $err];
        }
        $row = mysqli_fetch_assoc($resultado);
        mysqli_free_result($resultado);
        $this->drainResults($c);
        mysqli_close($c);
        return $row;
    }
}
?>
