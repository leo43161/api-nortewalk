-- =====================================================================
-- NORTE WALK - MIGRACION v7: RESERVAS CON CUPOS E IDIOMAS
-- =====================================================================
-- Convierte el flujo "lead -> WhatsApp" en "reserva instantánea":
--   * leads gana booking_code, apellido y desglose adultos/niños.
--   * capacity_hint de schedules pasa a ser CUPO REAL (NULL = sin límite).
--   * sp_schedule_get_available_dates devuelve cupos restantes por salida.
--   * sp_lead_create valida cupo y devuelve booking_code.
--   * sp_lead_update_status se alinea al enum nuevo de status.
--   * sp_experience_list_public expone idiomas (guía + salidas) para cards.
--   * sp_booking_list_upcoming: agenda del guía por fecha de salida.
--
-- Ejecutar UNA VEZ por base (phpMyAdmin o mysql CLI). Pensado para correr
-- tanto sobre el esquema viejo (enum redirected_whatsapp/converted) como
-- sobre el nuevo: los UPDATEs de normalización no matchean nada si ya
-- está migrado.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1) LEADS: normalizar status viejos y actualizar enum
-- ---------------------------------------------------------------------
UPDATE leads SET status = 'contacted' WHERE status = 'redirected_whatsapp';
UPDATE leads SET status = 'confirmed' WHERE status = 'converted';

ALTER TABLE leads
  MODIFY status ENUM('new','contacted','confirmed','attended','no_show','lost','spam')
  NOT NULL DEFAULT 'new';

-- ---------------------------------------------------------------------
-- 2) LEADS: columnas nuevas de reserva
-- ---------------------------------------------------------------------
ALTER TABLE leads
  ADD COLUMN booking_code VARCHAR(12) NULL COMMENT 'Código visible al turista (NW-XXXXX)' AFTER id,
  ADD COLUMN tourist_surname VARCHAR(150) NULL AFTER tourist_name,
  ADD COLUMN pax_adults TINYINT UNSIGNED NULL AFTER pax,
  ADD COLUMN pax_children TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER pax_adults;

-- Backfill de filas históricas
UPDATE leads SET pax_adults = pax WHERE pax_adults IS NULL;
UPDATE leads SET booking_code = CONCAT('NW-', LPAD(CONV(id, 10, 36), 5, '0'))
WHERE booking_code IS NULL;

ALTER TABLE leads
  MODIFY pax_adults TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ADD UNIQUE KEY uq_leads_booking_code (booking_code);

-- ---------------------------------------------------------------------
-- 3) SCHEDULES: capacity_hint pasa a ser cupo real
-- ---------------------------------------------------------------------
ALTER TABLE experience_schedules
  MODIFY capacity_hint TINYINT UNSIGNED NULL
  COMMENT 'Cupo real por salida. NULL = sin límite. Las reservas activas lo descuentan.';

-- ---------------------------------------------------------------------
-- 4) STORED PROCEDURES
-- ---------------------------------------------------------------------
DELIMITER $$

-- ============ sp_lead_update_status: enum alineado a la tabla ============
DROP PROCEDURE IF EXISTS sp_lead_update_status$$
CREATE PROCEDURE sp_lead_update_status (
    IN p_id BIGINT UNSIGNED,
    IN p_status ENUM('new','contacted','confirmed','attended','no_show','lost','spam')
)  SQL SECURITY INVOKER
BEGIN
    UPDATE leads SET status = p_status WHERE id = p_id;
    SELECT ROW_COUNT() AS affected;
END$$

-- ============ sp_schedule_get_available_dates: ahora con cupos ============
-- Devuelve cada salida real (fecha x horario) del rango con:
--   capacity    cupo total del horario (NULL = sin límite)
--   booked      pax ya reservados (status new/contacted/confirmed)
--   spots_left  cupo restante (NULL = sin límite)
-- Excluye blackouts, fechas pasadas y salidas de hoy que ya partieron.
DROP PROCEDURE IF EXISTS sp_schedule_get_available_dates$$
CREATE PROCEDURE sp_schedule_get_available_dates (
    IN p_experience_id BIGINT UNSIGNED,
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_locale VARCHAR(2)
)  SQL SECURITY INVOKER
BEGIN
    WITH RECURSIVE date_range AS (
        SELECT GREATEST(p_from_date, CURDATE()) AS d
        UNION ALL
        SELECT DATE_ADD(d, INTERVAL 1 DAY)
        FROM date_range
        WHERE d < p_to_date
    )
    SELECT
        s.id AS schedule_id,
        dr.d AS available_date,
        s.day_of_week,
        s.start_time,
        s.locale,
        s.capacity_hint AS capacity,
        COALESCE(bk.booked, 0) AS booked,
        CASE
            WHEN s.capacity_hint IS NULL THEN NULL
            ELSE GREATEST(CAST(s.capacity_hint AS SIGNED) - COALESCE(bk.booked, 0), 0)
        END AS spots_left
    FROM date_range dr
    INNER JOIN experience_schedules s
      ON s.experience_id = p_experience_id
     AND s.is_active = 1
     AND ((DAYOFWEEK(dr.d) - 1) = s.day_of_week)
     AND (s.valid_from IS NULL OR dr.d >= s.valid_from)
     AND (s.valid_to IS NULL OR dr.d <= s.valid_to)
     AND (p_locale IS NULL OR s.locale = p_locale)
    LEFT JOIN experience_blackout_dates b
      ON b.experience_id = p_experience_id AND b.blackout_date = dr.d
    LEFT JOIN (
        SELECT l.schedule_id, l.desired_date, SUM(l.pax) AS booked
        FROM leads l
        WHERE l.experience_id = p_experience_id
          AND l.status IN ('new','contacted','confirmed')
        GROUP BY l.schedule_id, l.desired_date
    ) bk
      ON bk.schedule_id = s.id AND bk.desired_date = dr.d
    WHERE b.id IS NULL
      AND (dr.d > CURDATE() OR s.start_time > CURTIME())
    ORDER BY dr.d ASC, s.start_time ASC;
END$$

-- ============ sp_lead_create: reserva con apellido, pax desglosado y cupo ============
-- El horario elegido (schedule) manda: fija hora e idioma de la reserva.
-- Si el cupo no alcanza, SIGNAL 45001 -> la API responde 409.
DROP PROCEDURE IF EXISTS sp_lead_create$$
CREATE PROCEDURE sp_lead_create (
    IN p_experience_id BIGINT UNSIGNED,
    IN p_schedule_id BIGINT UNSIGNED,
    IN p_booking_code VARCHAR(12),
    IN p_tourist_name VARCHAR(150),
    IN p_tourist_surname VARCHAR(150),
    IN p_tourist_phone VARCHAR(30),
    IN p_tourist_email VARCHAR(180),
    IN p_preferred_locale ENUM('es','en','pt'),
    IN p_desired_date DATE,
    IN p_desired_time TIME,
    IN p_pax_adults TINYINT UNSIGNED,
    IN p_pax_children TINYINT UNSIGNED,
    IN p_message TEXT,
    IN p_source VARCHAR(60),
    IN p_utm_source VARCHAR(80),
    IN p_utm_medium VARCHAR(80),
    IN p_utm_campaign VARCHAR(120),
    IN p_ip VARCHAR(45),
    IN p_user_agent VARCHAR(255)
)  SQL SECURITY INVOKER
BEGIN
    DECLARE v_provider_id BIGINT UNSIGNED;
    DECLARE v_whatsapp VARCHAR(20);
    DECLARE v_provider_name VARCHAR(180);
    DECLARE v_exp_title VARCHAR(200);
    DECLARE v_capacity TINYINT UNSIGNED;
    DECLARE v_booked INT;
    DECLARE v_sched_time TIME;
    DECLARE v_sched_locale ENUM('es','en','pt');
    DECLARE v_pax INT;
    DECLARE v_time TIME;
    DECLARE v_locale ENUM('es','en','pt');

    SET v_pax = IFNULL(p_pax_adults, 1) + IFNULL(p_pax_children, 0);

    -- v_active_experiences ya filtra provider activo + suscripcion vigente
    SELECT v.provider_id, v.whatsapp_e164, v.provider_name, v.title
      INTO v_provider_id, v_whatsapp, v_provider_name, v_exp_title
    FROM v_active_experiences v
    WHERE v.id = p_experience_id
    LIMIT 1;

    IF v_provider_id IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Experiencia no disponible o proveedor inactivo';
    END IF;

    SET v_time = p_desired_time;
    SET v_locale = IFNULL(p_preferred_locale, 'es');

    -- El schedule elegido manda hora/idioma y valida cupo
    IF p_schedule_id IS NOT NULL THEN
        SELECT s.capacity_hint, s.start_time, s.locale
          INTO v_capacity, v_sched_time, v_sched_locale
        FROM experience_schedules s
        WHERE s.id = p_schedule_id
          AND s.experience_id = p_experience_id
          AND s.is_active = 1
        LIMIT 1;

        IF v_sched_time IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Horario no disponible para esta experiencia';
        END IF;

        SET v_time = v_sched_time;
        SET v_locale = v_sched_locale;

        IF v_capacity IS NOT NULL THEN
            SELECT COALESCE(SUM(pax), 0) INTO v_booked
            FROM leads
            WHERE schedule_id = p_schedule_id
              AND desired_date = p_desired_date
              AND status IN ('new','contacted','confirmed');

            IF v_booked + v_pax > v_capacity THEN
                SIGNAL SQLSTATE '45001'
                SET MESSAGE_TEXT = 'Sin cupo disponible para ese horario';
            END IF;
        END IF;
    END IF;

    INSERT INTO leads
    (booking_code, experience_id, provider_id, schedule_id,
     tourist_name, tourist_surname, tourist_phone, tourist_email,
     preferred_locale, desired_date, desired_time,
     pax, pax_adults, pax_children, message,
     source, utm_source, utm_medium, utm_campaign, ip_address, user_agent, status)
    VALUES
    (p_booking_code, p_experience_id, v_provider_id, p_schedule_id,
     p_tourist_name, p_tourist_surname, p_tourist_phone, p_tourist_email,
     v_locale, p_desired_date, v_time,
     v_pax, IFNULL(p_pax_adults, 1), IFNULL(p_pax_children, 0), p_message,
     p_source, p_utm_source, p_utm_medium, p_utm_campaign, p_ip, p_user_agent, 'new');

    SELECT LAST_INSERT_ID() AS lead_id,
           p_booking_code AS booking_code,
           v_provider_id AS provider_id,
           v_provider_name AS provider_name,
           v_whatsapp AS whatsapp_e164,
           v_exp_title AS experience_title,
           p_desired_date AS desired_date,
           v_time AS desired_time,
           v_locale AS tour_locale,
           v_pax AS pax;
END$$

-- ============ sp_experience_list_public: + idiomas para las cards ============
DROP PROCEDURE IF EXISTS sp_experience_list_public$$
CREATE PROCEDURE sp_experience_list_public (
    IN p_city VARCHAR(100),
    IN p_vertical VARCHAR(20),
    IN p_category VARCHAR(60),
    IN p_type VARCHAR(10),
    IN p_locale VARCHAR(2),
    IN p_limit INT,
    IN p_offset INT
)  SQL SECURITY INVOKER
BEGIN
    SELECT
        v.id, v.slug, v.vertical, v.category, v.type,
        v.price, v.price_min, v.price_max, v.currency,
        v.duration_min, v.difficulty, v.city, v.province,
        v.latitude, v.longitude, v.is_featured,
        v.external_rating, v.external_reviews_count,
        v.provider_id, v.provider_name, v.whatsapp_e164,
        COALESCE(et.title, v.title) AS title,
        COALESCE(et.short_desc, v.short_desc) AS short_desc,
        (SELECT url FROM experience_images WHERE experience_id = v.id AND is_cover = 1 LIMIT 1) AS cover_image,
        (SELECT GROUP_CONCAT(el.language_code ORDER BY el.sort_order ASC SEPARATOR ',')
           FROM experience_languages el WHERE el.experience_id = v.id) AS languages_csv,
        (SELECT GROUP_CONCAT(DISTINCT s.locale ORDER BY s.locale ASC SEPARATOR ',')
           FROM experience_schedules s WHERE s.experience_id = v.id AND s.is_active = 1) AS schedule_locales_csv
    FROM v_active_experiences v
    LEFT JOIN experience_translations et
      ON et.experience_id = v.id AND et.locale = p_locale AND p_locale <> 'es'
    WHERE (p_city IS NULL OR v.city = p_city)
      AND (p_vertical IS NULL OR v.vertical = p_vertical)
      AND (p_category IS NULL OR v.category = p_category)
      AND (p_type IS NULL OR v.type = p_type)
    ORDER BY v.is_featured DESC, v.external_rating DESC, v.created_at DESC
    LIMIT p_limit OFFSET p_offset;

    SELECT COUNT(*) AS total
    FROM v_active_experiences v
    WHERE (p_city IS NULL OR v.city = p_city)
      AND (p_vertical IS NULL OR v.vertical = p_vertical)
      AND (p_category IS NULL OR v.category = p_category)
      AND (p_type IS NULL OR v.type = p_type);
END$$

-- ============ sp_booking_list_upcoming: agenda del guía ============
-- Reservas por FECHA DE SALIDA (no por created_at), con idioma del horario.
DROP PROCEDURE IF EXISTS sp_booking_list_upcoming$$
CREATE PROCEDURE sp_booking_list_upcoming (
    IN p_provider_id BIGINT UNSIGNED,
    IN p_from_date DATE,
    IN p_to_date DATE
)  SQL SECURITY INVOKER
BEGIN
    SELECT l.*,
           e.title AS experience_title, e.slug AS experience_slug,
           e.vertical, e.type,
           s.locale AS schedule_locale, s.start_time AS schedule_time,
           s.capacity_hint AS schedule_capacity
    FROM leads l
    INNER JOIN experiences e ON e.id = l.experience_id
    LEFT JOIN experience_schedules s ON s.id = l.schedule_id
    WHERE l.provider_id = p_provider_id
      AND l.desired_date BETWEEN p_from_date AND p_to_date
      AND l.status IN ('new','contacted','confirmed','attended','no_show')
    ORDER BY l.desired_date ASC, l.desired_time ASC, l.created_at ASC;
END$$

DELIMITER ;
