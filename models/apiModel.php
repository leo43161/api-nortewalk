<?php
/* =====================================================================
 * NORTE WALK - API MODEL (Admin)
 * Llamadas a Stored Procedures de la DB norte_walk
 * ===================================================================== */

class apiModel extends Model
{
    public function __construct()
    {
        parent::__construct();
    }

    // AUTH / ADMIN
    // =================================================================

    public function getAdminByEmail($email)
    {
        $c = $this->db->connect();
        $email = $this->esc($c, $email);
        $row = $this->callOne($c, "CALL sp_admin_login($email)");
        mysqli_close($c);
        return $row;
    }

    public function adminCreate($email, $hash, $fullName, $role, $providerId = null)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_admin_create(%s, %s, %s, %s, %s)",
            $this->esc($c, $email),
            $this->esc($c, $hash),
            $this->esc($c, $fullName),
            $this->esc($c, $role),
            $providerId !== null ? (int)$providerId : 'NULL'
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    // =================================================================
    // OWNERSHIP HELPERS (para middleware de scope)
    // =================================================================

    /**
     * Devuelve provider_id dueño de la experiencia, o null si no existe.
     */
    public function getExperienceProviderId($experienceId)
    {
        $c = $this->db->connect();
        $id = (int)$experienceId;
        $res = mysqli_query($c, "SELECT provider_id FROM experiences WHERE id = $id LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) mysqli_free_result($res);
        mysqli_close($c);
        return $row ? (int)$row['provider_id'] : null;
    }

    /**
     * Devuelve provider_id dueño del lead, o null si no existe.
     */
    public function getLeadProviderId($leadId)
    {
        $c = $this->db->connect();
        $id = (int)$leadId;
        $res = mysqli_query($c, "SELECT provider_id FROM leads WHERE id = $id LIMIT 1");
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) mysqli_free_result($res);
        mysqli_close($c);
        return $row ? (int)$row['provider_id'] : null;
    }

    /**
     * Lookup genérico de provider_id a través de la experience asociada
     * para tablas hijas: experience_images, experience_inclusions, experience_itinerary,
     * experience_schedules, experience_blackout_dates.
     */
    private function getProviderIdViaExperience($childTable, $childId)
    {
        $allowed = [
            'experience_images',
            'experience_inclusions',
            'experience_itinerary',
            'experience_schedules',
            'experience_blackout_dates',
        ];
        if (!in_array($childTable, $allowed, true)) return null;

        $c = $this->db->connect();
        $id = (int)$childId;
        $sql = "SELECT e.provider_id
                FROM `$childTable` t
                INNER JOIN experiences e ON e.id = t.experience_id
                WHERE t.id = $id LIMIT 1";
        $res = mysqli_query($c, $sql);
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) mysqli_free_result($res);
        mysqli_close($c);
        return $row ? (int)$row['provider_id'] : null;
    }

    public function getImageProviderId($id)     { return $this->getProviderIdViaExperience('experience_images', $id); }
    public function getInclusionProviderId($id) { return $this->getProviderIdViaExperience('experience_inclusions', $id); }
    public function getItineraryProviderId($id) { return $this->getProviderIdViaExperience('experience_itinerary', $id); }
    public function getScheduleProviderId($id)  { return $this->getProviderIdViaExperience('experience_schedules', $id); }
    public function getBlackoutProviderId($id)  { return $this->getProviderIdViaExperience('experience_blackout_dates', $id); }

    public function adminList($limit, $offset)
    {
        $c = $this->db->connect();
        $sets = $this->callMulti($c, "CALL sp_admin_list($limit, $offset)");
        mysqli_close($c);
        return [
            'rows'  => $sets[0] ?? [],
            'total' => (int)($sets[1][0]['total'] ?? 0)
        ];
    }

    public function adminGetById($id)
    {
        $c = $this->db->connect();
        $id = (int)$id;
        $res = mysqli_query(
            $c,
            "SELECT id, email, full_name, role, provider_id, password_hash, is_active
             FROM admin_users WHERE id = $id LIMIT 1"
        );
        $row = $res ? mysqli_fetch_assoc($res) : null;
        if ($res) mysqli_free_result($res);
        mysqli_close($c);
        return $row;
    }

    public function adminUpdatePassword($id, $hash)
    {
        $c = $this->db->connect();
        $id = (int)$id;
        $hashEsc = mysqli_real_escape_string($c, $hash);
        $ok = mysqli_query(
            $c,
            "UPDATE admin_users SET password_hash = '$hashEsc' WHERE id = $id"
        );
        $affected = $ok ? mysqli_affected_rows($c) : 0;
        mysqli_close($c);
        return $affected > 0;
    }

    // =================================================================
    // PROVIDERS
    // =================================================================

    public function providerCreate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_provider_create(%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            $this->esc($c, $d['slug']),
            $this->esc($c, $d['business_name']),
            $this->esc($c, $d['contact_name']),
            $this->esc($c, $d['email']),
            $this->esc($c, $d['whatsapp_e164']),
            $this->esc($c, $d['city']),
            $this->esc($c, $d['province'] ?? null),
            $this->esc($c, $d['country']  ?? null),
            $this->esc($c, $d['bio']      ?? null),
            $this->esc($c, $d['logo_url'] ?? null),
            (int)($d['trial_days'] ?? 14)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function providerUpdate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_provider_update(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
            (int)$d['id'],
            $this->esc($c, $d['business_name']),
            $this->esc($c, $d['contact_name']),
            $this->esc($c, $d['email']),
            $this->esc($c, $d['whatsapp_e164']),
            $this->esc($c, $d['city']),
            $this->esc($c, $d['province'] ?? null),
            $this->esc($c, $d['country']  ?? null),
            $this->esc($c, $d['bio']      ?? null),
            $this->esc($c, $d['logo_url'] ?? null),
            $this->esc($c, $d['notes_admin'] ?? null)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function providerGet($id)
    {
        $c = $this->db->connect();
        $row = $this->callOne($c, "CALL sp_provider_get_by_id($id)");
        mysqli_close($c);
        return $row;
    }

    public function providerList($status, $city, $search, $limit, $offset)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_provider_list_admin(%s,%s,%s,%d,%d)",
            $this->esc($c, $status),
            $this->esc($c, $city),
            $this->esc($c, $search),
            $limit, $offset
        );
        $sets = $this->callMulti($c, $sql);
        mysqli_close($c);
        return [
            'rows'  => $sets[0] ?? [],
            'total' => (int)($sets[1][0]['total'] ?? 0)
        ];
    }

    public function providerChangeStatus($id, $status, $reason, $adminEmail)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_provider_change_status(%d,%s,%s,%s)",
            (int)$id,
            $this->esc($c, $status),
            $this->esc($c, $reason),
            $this->esc($c, $adminEmail)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function providerDashboard($id)
    {
        $c = $this->db->connect();
        $row = $this->callOne($c, "CALL sp_provider_dashboard_stats($id)");
        mysqli_close($c);
        return $row;
    }

    // =================================================================
    // SUBSCRIPTIONS
    // =================================================================

    public function subscriptionPay($d, $adminEmail)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_subscription_register_payment(%d,%.2f,%s,%s,%d,%s,%s)",
            (int)$d['provider_id'],
            (float)$d['amount_usd'],
            $this->esc($c, $d['payment_method'] ?? 'transfer'),
            $this->esc($c, $d['reference']      ?? null),
            (int)($d['days_added'] ?? 30),
            $this->esc($c, $d['notes']          ?? null),
            $this->esc($c, $adminEmail)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function subscriptionHistory($providerId, $limit, $offset)
    {
        $c = $this->db->connect();
        $sets = $this->callMulti($c, "CALL sp_subscription_history($providerId, $limit, $offset)");
        mysqli_close($c);
        return [
            'rows'  => $sets[0] ?? [],
            'total' => (int)($sets[1][0]['total'] ?? 0)
        ];
    }

    public function subscriptionKillswitch()
    {
        $c = $this->db->connect();
        $sets = $this->callMulti($c, "CALL sp_subscription_apply_killswitch()");
        mysqli_close($c);
        return [
            'suspended'     => (int)($sets[0][0]['suspended_count'] ?? 0),
            'trial_expired' => (int)($sets[1][0]['trial_expired_count'] ?? 0)
        ];
    }

    public function subscriptionExpiring($days)
    {
        $c = $this->db->connect();
        $rows = $this->callAll($c, "CALL sp_subscription_expiring_soon($days)");
        mysqli_close($c);
        return $rows;
    }

    // =================================================================
    // EXPERIENCES
    // =================================================================

    public function experienceCreate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_experience_create(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d,%s,%s)",
            (int)$d['provider_id'],
            $this->esc($c, $d['slug']),
            $this->esc($c, $d['title']),
            $this->esc($c, $d['short_desc'] ?? null),
            $this->esc($c, $d['long_desc']  ?? null),
            $this->esc($c, $d['vertical']),
            $this->esc($c, $d['category']),
            $this->esc($c, $d['type'] ?? 'paid'),
            $this->escNum($d['price'] ?? null),
            $this->escNum($d['price_min'] ?? null),
            $this->escNum($d['price_max'] ?? null),
            $this->esc($c, $d['currency'] ?? 'ARS'),
            (int)($d['duration_min'] ?? 120),
            $this->esc($c, $d['difficulty'] ?? 'easy'),
            $this->esc($c, $d['meeting_point'] ?? null),
            $this->esc($c, $d['city']),
            $this->esc($c, $d['province'] ?? null),
            $this->esc($c, $d['country']  ?? null),
            $this->escNum($d['latitude']  ?? null),
            $this->escNum($d['longitude'] ?? null),
            (int)($d['min_pax'] ?? 1),
            (int)($d['max_pax'] ?? 20),
            (int)($d['min_age'] ?? 0),
            $this->esc($c, $d['meta_title']       ?? null),
            $this->esc($c, $d['meta_description'] ?? null)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function experienceUpdate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_experience_update(%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s,%d,%d,%d,%s,%s)",
            (int)$d['id'],
            $this->esc($c, $d['title']),
            $this->esc($c, $d['short_desc'] ?? null),
            $this->esc($c, $d['long_desc']  ?? null),
            $this->esc($c, $d['vertical']),
            $this->esc($c, $d['category']),
            $this->esc($c, $d['type'] ?? 'paid'),
            $this->escNum($d['price']     ?? null),
            $this->escNum($d['price_min'] ?? null),
            $this->escNum($d['price_max'] ?? null),
            $this->esc($c, $d['currency'] ?? 'ARS'),
            (int)($d['duration_min'] ?? 120),
            $this->esc($c, $d['difficulty'] ?? 'easy'),
            $this->esc($c, $d['meeting_point'] ?? null),
            $this->esc($c, $d['city']),
            $this->esc($c, $d['province'] ?? null),
            $this->esc($c, $d['country']  ?? null),
            $this->escNum($d['latitude']  ?? null),
            $this->escNum($d['longitude'] ?? null),
            (int)($d['min_pax'] ?? 1),
            (int)($d['max_pax'] ?? 20),
            (int)($d['min_age'] ?? 0),
            $this->esc($c, $d['meta_title']       ?? null),
            $this->esc($c, $d['meta_description'] ?? null)
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    public function experienceDelete($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_experience_delete($id)");
        mysqli_close($c);
    }

    public function experienceToggleActive($id)
    {
        $c = $this->db->connect();
        $row = $this->callOne($c, "CALL sp_experience_toggle_active($id)");
        mysqli_close($c);
        return $row;
    }

    public function experienceToggleFeatured($id)
    {
        $c = $this->db->connect();
        $row = $this->callOne($c, "CALL sp_experience_toggle_featured($id)");
        mysqli_close($c);
        return $row;
    }

    public function experienceGet($id)
    {
        $c = $this->db->connect();
        $row = $this->callOne($c, "CALL sp_experience_get_by_id_admin($id)");
        mysqli_close($c);
        return $row;
    }

    public function experienceList($providerId, $city, $vertical, $isActive, $search, $limit, $offset)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_experience_list_admin(%s,%s,%s,%s,%s,%d,%d)",
            $providerId !== null ? (int)$providerId : 'NULL',
            $this->esc($c, $city),
            $this->esc($c, $vertical),
            $isActive !== null ? (int)$isActive : 'NULL',
            $this->esc($c, $search),
            $limit, $offset
        );
        $sets = $this->callMulti($c, $sql);
        mysqli_close($c);
        return [
            'rows'  => $sets[0] ?? [],
            'total' => (int)($sets[1][0]['total'] ?? 0)
        ];
    }

    // =================================================================
    // IMAGENES
    // =================================================================

    public function imageAdd($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_image_add(%d,%s,%s,%d,%d)",
            (int)$d['experience_id'],
            $this->esc($c, $d['url']),
            $this->esc($c, $d['alt_text'] ?? null),
            (int)($d['sort_order'] ?? 0),
            (int)($d['is_cover']   ?? 0)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function imageDelete($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_image_delete($id)");
        mysqli_close($c);
    }

    public function imageSetCover($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_image_set_cover($id)");
        mysqli_close($c);
    }

    public function imageList($experienceId)
    {
        $c = $this->db->connect();
        $rows = $this->callAll($c, "CALL sp_image_list_by_experience($experienceId)");
        mysqli_close($c);
        return $rows;
    }

    // =================================================================
    // INCLUSIONES
    // =================================================================

    public function inclusionAdd($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_inclusion_add(%d,%s,%s,%d)",
            (int)$d['experience_id'],
            $this->esc($c, $d['text']),
            $this->esc($c, $d['kind'] ?? 'included'),
            (int)($d['sort_order'] ?? 0)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function inclusionUpdate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_inclusion_update(%d,%s,%s,%d)",
            (int)$d['id'],
            $this->esc($c, $d['text']),
            $this->esc($c, $d['kind'] ?? 'included'),
            (int)($d['sort_order'] ?? 0)
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    public function inclusionDelete($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_inclusion_delete($id)");
        mysqli_close($c);
    }

    public function inclusionList($experienceId, $locale = 'es')
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_inclusion_list_by_experience(%d,%s)",
            (int)$experienceId,
            $this->esc($c, $locale)
        );
        $rows = $this->callAll($c, $sql);
        mysqli_close($c);
        return $rows;
    }

    // =================================================================
    // ITINERARIO
    // =================================================================

    public function itineraryAdd($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_itinerary_add(%d,%d,%s,%s,%s)",
            (int)$d['experience_id'],
            (int)$d['step_order'],
            $this->esc($c, $d['title']),
            $this->esc($c, $d['description']  ?? null),
            $d['duration_min'] !== null && $d['duration_min'] !== '' ? (int)$d['duration_min'] : 'NULL'
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function itineraryUpdate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_itinerary_update(%d,%d,%s,%s,%s)",
            (int)$d['id'],
            (int)$d['step_order'],
            $this->esc($c, $d['title']),
            $this->esc($c, $d['description']  ?? null),
            isset($d['duration_min']) && $d['duration_min'] !== '' ? (int)$d['duration_min'] : 'NULL'
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    public function itineraryDelete($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_itinerary_delete($id)");
        mysqli_close($c);
    }

    public function itineraryList($experienceId, $locale = 'es')
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_itinerary_list_by_experience(%d,%s)",
            (int)$experienceId,
            $this->esc($c, $locale)
        );
        $rows = $this->callAll($c, $sql);
        mysqli_close($c);
        return $rows;
    }

    // =================================================================
    // SCHEDULES
    // =================================================================

    public function scheduleAdd($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_schedule_add(%d,%d,%s,%s,%s,%s,%s)",
            (int)$d['experience_id'],
            (int)$d['day_of_week'],
            $this->esc($c, $d['start_time']),
            $this->esc($c, $d['locale']),
            isset($d['capacity_hint']) && $d['capacity_hint'] !== '' ? (int)$d['capacity_hint'] : 'NULL',
            $this->esc($c, $d['valid_from'] ?? null),
            $this->esc($c, $d['valid_to']   ?? null)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function scheduleUpdate($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_schedule_update(%d,%d,%s,%s,%s,%s,%s)",
            (int)$d['id'],
            (int)$d['day_of_week'],
            $this->esc($c, $d['start_time']),
            $this->esc($c, $d['locale']),
            isset($d['capacity_hint']) && $d['capacity_hint'] !== '' ? (int)$d['capacity_hint'] : 'NULL',
            $this->esc($c, $d['valid_from'] ?? null),
            $this->esc($c, $d['valid_to']   ?? null)
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    public function scheduleDelete($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_schedule_delete($id)");
        mysqli_close($c);
    }

    public function scheduleList($experienceId)
    {
        $c = $this->db->connect();
        $rows = $this->callAll($c, "CALL sp_schedule_list_by_experience($experienceId)");
        mysqli_close($c);
        return $rows;
    }

    // =================================================================
    // BLACKOUTS
    // =================================================================

    public function blackoutAdd($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_blackout_add(%d,%s,%s)",
            (int)$d['experience_id'],
            $this->esc($c, $d['blackout_date']),
            $this->esc($c, $d['reason'] ?? null)
        );
        $row = $this->callOne($c, $sql);
        mysqli_close($c);
        return $row;
    }

    public function blackoutDelete($id)
    {
        $c = $this->db->connect();
        $this->callOne($c, "CALL sp_blackout_delete($id)");
        mysqli_close($c);
    }

    public function blackoutList($experienceId)
    {
        $c = $this->db->connect();
        $rows = $this->callAll($c, "CALL sp_blackout_list_by_experience($experienceId)");
        mysqli_close($c);
        return $rows;
    }

    // =================================================================
    // LEADS
    // =================================================================

    public function leadList($providerId, $experienceId, $status, $from, $to, $limit, $offset)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_lead_list_admin(%s,%s,%s,%s,%s,%d,%d)",
            $providerId   !== null ? (int)$providerId   : 'NULL',
            $experienceId !== null ? (int)$experienceId : 'NULL',
            $this->esc($c, $status),
            $this->esc($c, $from),
            $this->esc($c, $to),
            $limit, $offset
        );
        $sets = $this->callMulti($c, $sql);
        mysqli_close($c);
        return [
            'rows'  => $sets[0] ?? [],
            'total' => (int)($sets[1][0]['total'] ?? 0)
        ];
    }

    public function leadGet($id)
    {
        $c = $this->db->connect();
        $row = $this->callOne($c, "CALL sp_lead_get_detail($id)");
        mysqli_close($c);
        return $row;
    }

    public function leadUpdateStatus($id, $status)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_lead_update_status(%d,%s)", (int)$id, $this->esc($c, $status));
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    // =================================================================
    // TRADUCCIONES
    // =================================================================

    public function translationExperience($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_experience_translation_upsert(%d,%s,%s,%s,%s,%s,%s,%s)",
            (int)$d['experience_id'],
            $this->esc($c, $d['locale']),
            $this->esc($c, $d['title']            ?? null),
            $this->esc($c, $d['short_desc']       ?? null),
            $this->esc($c, $d['long_desc']        ?? null),
            $this->esc($c, $d['meeting_point']    ?? null),
            $this->esc($c, $d['meta_title']       ?? null),
            $this->esc($c, $d['meta_description'] ?? null)
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    public function translationInclusion($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_inclusion_translation_upsert(%d,%s,%s)",
            (int)$d['inclusion_id'],
            $this->esc($c, $d['locale']),
            $this->esc($c, $d['text'])
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }

    public function translationItinerary($d)
    {
        $c = $this->db->connect();
        $sql = sprintf("CALL sp_itinerary_translation_upsert(%d,%s,%s,%s)",
            (int)$d['itinerary_id'],
            $this->esc($c, $d['locale']),
            $this->esc($c, $d['title']       ?? null),
            $this->esc($c, $d['description'] ?? null)
        );
        $this->callOne($c, $sql);
        mysqli_close($c);
    }
}
?>
