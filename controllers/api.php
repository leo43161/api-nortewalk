<?php
/* =====================================================================
 * NORTE WALK - API CONTROLLER (Admin / B2B)
 * Todos los endpoints excepto login requieren JWT.
 * ===================================================================== */

class Api extends Controller
{
    function render()
    {
        setCors('GET');
        jsonResponse(200, 'Norte Walk Admin API v1', null, [
            'endpoints' => [
                'POST /api/login',
                'POST /api/admin_create',
                'GET  /api/admin_list',
                'POST /api/admin_change_password',
                'POST /api/provider_create',
                'POST /api/provider_update',
                'GET  /api/provider_list',
                'GET  /api/provider_get?id=N',
                'POST /api/provider_change_status',
                'GET  /api/provider_dashboard?id=N',
                'POST /api/subscription_pay',
                'GET  /api/subscription_history?provider_id=N',
                'POST /api/subscription_killswitch',
                'GET  /api/subscription_expiring?days=7',
                'POST /api/experience_create',
                'POST /api/experience_update',
                'POST /api/experience_delete',
                'POST /api/experience_toggle_active',
                'POST /api/experience_toggle_featured',
                'GET  /api/experience_list',
                'GET  /api/experience_get?id=N',
                'POST /api/image_add',
                'POST /api/image_delete',
                'POST /api/image_set_cover',
                'GET  /api/image_list?experience_id=N',
                'POST /api/inclusion_add',
                'POST /api/inclusion_update',
                'POST /api/inclusion_delete',
                'GET  /api/inclusion_list?experience_id=N&locale=es',
                'POST /api/itinerary_add',
                'POST /api/itinerary_update',
                'POST /api/itinerary_delete',
                'GET  /api/itinerary_list?experience_id=N&locale=es',
                'POST /api/schedule_add',
                'POST /api/schedule_update',
                'POST /api/schedule_delete',
                'GET  /api/schedule_list?experience_id=N',
                'POST /api/blackout_add',
                'POST /api/blackout_delete',
                'GET  /api/blackout_list?experience_id=N',
                'GET  /api/lead_list',
                'GET  /api/lead_get?id=N',
                'POST /api/lead_update_status',
                'POST /api/translation_experience',
                'POST /api/translation_inclusion',
                'POST /api/translation_itinerary',
                'POST /api/upload_image'
            ]
        ]);
    }

    // =================================================================
    // AUTH
    // =================================================================

    public function login()
    {
        setCors('POST');
        $data = readJson();

        if (empty($data['email']) || empty($data['password'])) {
            jsonResponse(400, 'Faltan credenciales');
        }

        $admin = $this->model->getAdminByEmail($data['email']);
        if (!$admin || !password_verify($data['password'], $admin['password_hash'])) {
            jsonResponse(401, 'Credenciales invalidas');
        }

        // Validar integridad: provider sin provider_id es inconsistente
        if ($admin['role'] === 'provider' && empty($admin['provider_id'])) {
            jsonResponse(403, 'Usuario provider sin provider asignado. Contactar al admin.');
        }

        $jwt = new JwtHandler(constant('JWT_KEY'));
        $token = $jwt->generateToken(
            $admin['id'],
            $admin['email'],
            $admin['role'],
            $admin['provider_id'] ?? null
        );

        jsonResponse(200, 'Login OK', [
            'token' => $token,
            'admin' => [
                'id'          => $admin['id'],
                'email'       => $admin['email'],
                'full_name'   => $admin['full_name'],
                'role'        => $admin['role'],
                'provider_id' => $admin['provider_id'] !== null ? (int)$admin['provider_id'] : null
            ]
        ]);
    }

    public function admin_create()
    {
        setCors('POST');
        requireAdmin();
        $data = readJson();

        if (empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
            jsonResponse(400, 'Faltan campos obligatorios');
        }

        $role = $data['role'] ?? 'admin';
        if (!in_array($role, ['admin', 'provider'], true)) {
            jsonResponse(400, 'Rol invalido (admin|provider)');
        }
        $providerId = $data['provider_id'] ?? null;
        if ($role === 'provider' && empty($providerId)) {
            jsonResponse(400, 'role=provider requiere provider_id');
        }
        if ($role === 'admin' && !empty($providerId)) {
            jsonResponse(400, 'role=admin no debe tener provider_id');
        }

        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        $res = $this->model->adminCreate(
            $data['email'], $hash, $data['full_name'], $role, $providerId
        );
        jsonResponse(201, 'Admin creado', $res);
    }

    public function admin_list()
    {
        setCors('GET');
        requireAdmin();
        $limit  = (int)($_GET['limit']  ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        $res = $this->model->adminList($limit, $offset);
        jsonResponse(200, 'OK', $res);
    }

    public function admin_change_password()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();

        $current = $d['current_password'] ?? '';
        $new     = $d['new_password'] ?? '';

        if (!$current || !$new) {
            jsonResponse(400, 'Faltan campos');
        }
        if (strlen($new) < 8) {
            jsonResponse(400, 'La nueva contraseña debe tener al menos 8 caracteres');
        }

        $adminId = $payload->data->admin_id ?? null;
        if (!$adminId) jsonResponse(401, 'Token invalido');

        $admin = $this->model->adminGetById($adminId);
        if (!$admin) jsonResponse(404, 'Admin no encontrado');

        if (!password_verify($current, $admin['password_hash'])) {
            jsonResponse(401, 'Contraseña actual incorrecta');
        }

        if (password_verify($new, $admin['password_hash'])) {
            jsonResponse(400, 'La nueva contraseña debe ser distinta de la actual');
        }

        $hash = password_hash($new, PASSWORD_BCRYPT);
        $ok = $this->model->adminUpdatePassword($adminId, $hash);
        if (!$ok) jsonResponse(500, 'No se pudo actualizar la contraseña');

        jsonResponse(200, 'Contraseña actualizada');
    }

    // =================================================================
    // PROVIDERS
    // =================================================================

    public function provider_create()
    {
        setCors('POST');
        requireAdmin();
        $d = readJson();

        if (empty($d['slug']) || empty($d['business_name']) || empty($d['contact_name'])
            || empty($d['email']) || empty($d['whatsapp_e164']) || empty($d['city'])) {
            jsonResponse(400, 'Faltan campos obligatorios');
        }

        $res = $this->model->providerCreate($d);
        jsonResponse(201, 'Proveedor creado', $res);
    }

    public function provider_update()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertProviderScope($payload, $d['id']);
        $res = $this->model->providerUpdate($d);
        jsonResponse(200, 'Proveedor actualizado', $res);
    }

    public function provider_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $scope = getScopeProviderId($payload);

        // Si es provider, devolver solo el suyo
        if ($scope !== null) {
            $row = $this->model->providerGet($scope);
            jsonResponse(200, 'OK', $row ? [$row] : [], ['total' => $row ? 1 : 0]);
        }

        $res = $this->model->providerList(
            $_GET['status'] ?? null,
            $_GET['city']   ?? null,
            $_GET['search'] ?? null,
            (int)($_GET['limit']  ?? 25),
            (int)($_GET['offset'] ?? 0)
        );
        jsonResponse(200, 'OK', $res['rows'], ['total' => $res['total']]);
    }

    public function provider_get()
    {
        setCors('GET');
        $payload = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(400, 'Falta id');
        assertProviderScope($payload, $id);
        $res = $this->model->providerGet($id);
        if (!$res) jsonResponse(404, 'No encontrado');
        jsonResponse(200, 'OK', $res);
    }

    public function provider_change_status()
    {
        setCors('POST');
        $payload = requireAdmin();
        $d = readJson();
        if (empty($d['provider_id']) || empty($d['status'])) jsonResponse(400, 'Faltan datos');
        $email = $payload->data->email ?? null;
        $this->model->providerChangeStatus($d['provider_id'], $d['status'], $d['reason'] ?? null, $email);
        jsonResponse(200, 'Estado actualizado');
    }

    public function provider_dashboard()
    {
        setCors('GET');
        $payload = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(400, 'Falta id');
        assertProviderScope($payload, $id);
        $res = $this->model->providerDashboard($id);
        jsonResponse(200, 'OK', $res);
    }

    // =================================================================
    // SUBSCRIPTIONS
    // =================================================================

    public function subscription_pay()
    {
        setCors('POST');
        $payload = requireAdmin();
        $d = readJson();
        if (empty($d['provider_id']) || empty($d['amount_usd'])) jsonResponse(400, 'Faltan datos');
        $email = $payload->data->email ?? null;
        $res = $this->model->subscriptionPay($d, $email);
        jsonResponse(201, 'Pago registrado', $res);
    }

    public function subscription_history()
    {
        setCors('GET');
        $payload = requireAuth();
        $pid = (int)($_GET['provider_id'] ?? 0);
        if (!$pid) jsonResponse(400, 'Falta provider_id');
        assertProviderScope($payload, $pid);
        $res = $this->model->subscriptionHistory(
            $pid,
            (int)($_GET['limit'] ?? 50),
            (int)($_GET['offset'] ?? 0)
        );
        jsonResponse(200, 'OK', $res['rows'], ['total' => $res['total']]);
    }

    public function subscription_killswitch()
    {
        setCors('POST');
        requireAdmin();
        $res = $this->model->subscriptionKillswitch();
        jsonResponse(200, 'Killswitch ejecutado', $res);
    }

    public function subscription_expiring()
    {
        setCors('GET');
        requireAdmin();
        $days = (int)($_GET['days'] ?? 7);
        $res = $this->model->subscriptionExpiring($days);
        jsonResponse(200, 'OK', $res);
    }

    // =================================================================
    // EXPERIENCES
    // =================================================================

    public function experience_create()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();

        // Si es provider, forzar provider_id del token (ignorar body)
        $scope = getScopeProviderId($payload);
        if ($scope !== null) {
            $d['provider_id'] = $scope;
        }

        if (empty($d['provider_id']) || empty($d['slug']) || empty($d['title'])
            || empty($d['vertical']) || empty($d['category']) || empty($d['city'])) {
            jsonResponse(400, 'Faltan campos obligatorios');
        }
        $res = $this->model->experienceCreate($d);
        jsonResponse(201, 'Experiencia creada', $res);
    }

    public function experience_update()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsExperience($this->model, $payload, $d['id']);
        $this->model->experienceUpdate($d);
        jsonResponse(200, 'Experiencia actualizada');
    }

    public function experience_delete()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsExperience($this->model, $payload, $d['id']);
        $this->model->experienceDelete($d['id']);
        jsonResponse(200, 'Experiencia desactivada');
    }

    public function experience_toggle_active()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsExperience($this->model, $payload, $d['id']);
        $res = $this->model->experienceToggleActive($d['id']);
        jsonResponse(200, 'OK', $res);
    }

    public function experience_toggle_featured()
    {
        setCors('POST');
        requireAdmin(); // featured = home, solo admin
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        $res = $this->model->experienceToggleFeatured($d['id']);
        jsonResponse(200, 'OK', $res);
    }

    public function experience_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $scope = getScopeProviderId($payload);
        $providerFilter = !empty($_GET['provider_id']) ? (int)$_GET['provider_id'] : null;

        // Si es provider, forzar filtro al propio provider_id
        if ($scope !== null) $providerFilter = $scope;

        $res = $this->model->experienceList(
            $providerFilter,
            $_GET['city']     ?? null,
            $_GET['vertical'] ?? null,
            isset($_GET['is_active']) ? (int)$_GET['is_active'] : null,
            $_GET['search']   ?? null,
            (int)($_GET['limit']  ?? 25),
            (int)($_GET['offset'] ?? 0)
        );
        jsonResponse(200, 'OK', $res['rows'], ['total' => $res['total']]);
    }

    public function experience_get()
    {
        setCors('GET');
        $payload = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(400, 'Falta id');
        assertOwnsExperience($this->model, $payload, $id);
        $res = $this->model->experienceGet($id);
        if (!$res) jsonResponse(404, 'No encontrado');
        jsonResponse(200, 'OK', $res);
    }

    // =================================================================
    // IMAGENES
    // =================================================================

    public function image_add()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['experience_id']) || empty($d['url'])) jsonResponse(400, 'Faltan datos');
        assertOwnsExperience($this->model, $payload, $d['experience_id']);
        $res = $this->model->imageAdd($d);
        jsonResponse(201, 'Imagen agregada', $res);
    }

    public function image_delete()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getImageProviderId($d['id']));
        $this->model->imageDelete($d['id']);
        jsonResponse(200, 'Imagen eliminada');
    }

    public function image_set_cover()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getImageProviderId($d['id']));
        $this->model->imageSetCover($d['id']);
        jsonResponse(200, 'Portada actualizada');
    }

    public function image_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $eid = (int)($_GET['experience_id'] ?? 0);
        if (!$eid) jsonResponse(400, 'Falta experience_id');
        assertOwnsExperience($this->model, $payload, $eid);
        jsonResponse(200, 'OK', $this->model->imageList($eid));
    }

    public function upload_image()
    {
        setCors('POST');
        $payload = requireAuth();
        // Aceptamos el field como 'imagen' o 'file' (cliente puede usar cualquiera)
        $file = $_FILES['imagen'] ?? $_FILES['file'] ?? null;
        if (!$file) jsonResponse(400, 'Falta archivo');

        $subdir = $_POST['subdir'] ?? 'experiences';
        $prefix = '';
        if (!empty($_POST['experience_id'])) {
            assertOwnsExperience($this->model, $payload, $_POST['experience_id']);
            $prefix = 'exp' . (int)$_POST['experience_id'];
        } elseif (!empty($_POST['provider_id'])) {
            assertProviderScope($payload, $_POST['provider_id']);
            $prefix = 'prov' . (int)$_POST['provider_id'];
        }

        $res = uploadImage($file, $subdir, $prefix);
        jsonResponse($res['status'], $res['message'] ?? 'OK', $res);
    }

    // =================================================================
    // INCLUSIONES
    // =================================================================

    public function inclusion_add()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['experience_id']) || empty($d['text'])) jsonResponse(400, 'Faltan datos');
        assertOwnsExperience($this->model, $payload, $d['experience_id']);
        $res = $this->model->inclusionAdd($d);
        jsonResponse(201, 'Inclusion agregada', $res);
    }

    public function inclusion_update()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getInclusionProviderId($d['id']));
        $this->model->inclusionUpdate($d);
        jsonResponse(200, 'OK');
    }

    public function inclusion_delete()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getInclusionProviderId($d['id']));
        $this->model->inclusionDelete($d['id']);
        jsonResponse(200, 'Eliminada');
    }

    public function inclusion_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $eid = (int)($_GET['experience_id'] ?? 0);
        if (!$eid) jsonResponse(400, 'Falta experience_id');
        assertOwnsExperience($this->model, $payload, $eid);
        $locale = $_GET['locale'] ?? 'es';
        jsonResponse(200, 'OK', $this->model->inclusionList($eid, $locale));
    }

    // =================================================================
    // ITINERARIO
    // =================================================================

    public function itinerary_add()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['experience_id']) || empty($d['title']) || !isset($d['step_order'])) {
            jsonResponse(400, 'Faltan datos');
        }
        assertOwnsExperience($this->model, $payload, $d['experience_id']);
        $res = $this->model->itineraryAdd($d);
        jsonResponse(201, 'Paso agregado', $res);
    }

    public function itinerary_update()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getItineraryProviderId($d['id']));
        $this->model->itineraryUpdate($d);
        jsonResponse(200, 'OK');
    }

    public function itinerary_delete()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getItineraryProviderId($d['id']));
        $this->model->itineraryDelete($d['id']);
        jsonResponse(200, 'Eliminado');
    }

    public function itinerary_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $eid = (int)($_GET['experience_id'] ?? 0);
        if (!$eid) jsonResponse(400, 'Falta experience_id');
        assertOwnsExperience($this->model, $payload, $eid);
        $locale = $_GET['locale'] ?? 'es';
        jsonResponse(200, 'OK', $this->model->itineraryList($eid, $locale));
    }

    // =================================================================
    // SCHEDULES
    // =================================================================

    public function schedule_add()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (!isset($d['experience_id']) || !isset($d['day_of_week'])
            || empty($d['start_time']) || empty($d['locale'])) {
            jsonResponse(400, 'Faltan datos');
        }
        assertOwnsExperience($this->model, $payload, $d['experience_id']);
        $res = $this->model->scheduleAdd($d);
        jsonResponse(201, 'Horario agregado', $res);
    }

    public function schedule_update()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getScheduleProviderId($d['id']));
        $this->model->scheduleUpdate($d);
        jsonResponse(200, 'OK');
    }

    public function schedule_delete()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getScheduleProviderId($d['id']));
        $this->model->scheduleDelete($d['id']);
        jsonResponse(200, 'Eliminado');
    }

    public function schedule_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $eid = (int)($_GET['experience_id'] ?? 0);
        if (!$eid) jsonResponse(400, 'Falta experience_id');
        assertOwnsExperience($this->model, $payload, $eid);
        jsonResponse(200, 'OK', $this->model->scheduleList($eid));
    }

    // =================================================================
    // BLACKOUTS
    // =================================================================

    public function blackout_add()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['experience_id']) || empty($d['blackout_date'])) jsonResponse(400, 'Faltan datos');
        assertOwnsExperience($this->model, $payload, $d['experience_id']);
        $res = $this->model->blackoutAdd($d);
        jsonResponse(201, 'Excepcion agregada', $res);
    }

    public function blackout_delete()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id'])) jsonResponse(400, 'Falta id');
        assertOwnsResource($payload, $this->model->getBlackoutProviderId($d['id']));
        $this->model->blackoutDelete($d['id']);
        jsonResponse(200, 'Eliminada');
    }

    public function blackout_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $eid = (int)($_GET['experience_id'] ?? 0);
        if (!$eid) jsonResponse(400, 'Falta experience_id');
        assertOwnsExperience($this->model, $payload, $eid);
        jsonResponse(200, 'OK', $this->model->blackoutList($eid));
    }

    // =================================================================
    // LEADS
    // =================================================================

    public function lead_list()
    {
        setCors('GET');
        $payload = requireAuth();
        $scope = getScopeProviderId($payload);
        $providerFilter = !empty($_GET['provider_id']) ? (int)$_GET['provider_id'] : null;

        // Si es provider, forzar filtro al propio provider_id
        if ($scope !== null) $providerFilter = $scope;

        $res = $this->model->leadList(
            $providerFilter,
            !empty($_GET['experience_id']) ? (int)$_GET['experience_id'] : null,
            $_GET['status']    ?? null,
            $_GET['from_date'] ?? null,
            $_GET['to_date']   ?? null,
            (int)($_GET['limit']  ?? 50),
            (int)($_GET['offset'] ?? 0)
        );
        jsonResponse(200, 'OK', $res['rows'], ['total' => $res['total']]);
    }

    public function lead_get()
    {
        setCors('GET');
        $payload = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) jsonResponse(400, 'Falta id');
        assertOwnsLead($this->model, $payload, $id);
        $res = $this->model->leadGet($id);
        if (!$res) jsonResponse(404, 'No encontrado');
        jsonResponse(200, 'OK', $res);
    }

    public function lead_update_status()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['id']) || empty($d['status'])) jsonResponse(400, 'Faltan datos');
        assertOwnsLead($this->model, $payload, $d['id']);
        $this->model->leadUpdateStatus($d['id'], $d['status']);
        jsonResponse(200, 'OK');
    }

    // =================================================================
    // TRADUCCIONES
    // =================================================================

    public function translation_experience()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['experience_id']) || empty($d['locale'])) jsonResponse(400, 'Faltan datos');
        assertOwnsExperience($this->model, $payload, $d['experience_id']);
        $this->model->translationExperience($d);
        jsonResponse(200, 'Traduccion guardada');
    }

    public function translation_inclusion()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['inclusion_id']) || empty($d['locale']) || empty($d['text'])) jsonResponse(400, 'Faltan datos');
        assertOwnsResource($payload, $this->model->getInclusionProviderId($d['inclusion_id']));
        $this->model->translationInclusion($d);
        jsonResponse(200, 'Traduccion guardada');
    }

    public function translation_itinerary()
    {
        setCors('POST');
        $payload = requireAuth();
        $d = readJson();
        if (empty($d['itinerary_id']) || empty($d['locale'])) jsonResponse(400, 'Faltan datos');
        assertOwnsResource($payload, $this->model->getItineraryProviderId($d['itinerary_id']));
        $this->model->translationItinerary($d);
        jsonResponse(200, 'Traduccion guardada');
    }
}
?>
