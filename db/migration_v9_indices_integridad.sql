-- =====================================================================
-- NORTE WALK - MIGRACION v9: INDICES REALES + INTEGRIDAD
-- =====================================================================
-- * Índices pensados para las consultas que hace el sistema:
--     - agenda del guía (reservas por fecha de SALIDA, no de creación)
--     - control de cupo (reservas por horario + día)
--     - bandeja del guía (reservas por estado)
--   y fuera los que ninguna consulta usa (tipo, lat/lng, horarios activos).
-- * CHECKs: la base rechaza datos imposibles aunque falle una validación
--   en la API o en el panel (MySQL 8.0.16+ los hace cumplir).
-- * sp_booking_list_upcoming suma provider_name (el WhatsApp firma con el nombre).
-- * sp_experience_list_admin suma active_schedules e inclusions_count:
--   el panel marca "le faltan horarios" sin abrir cada experiencia.
--   (Mismas columnas que antes + 2: la API y el sitio no cambian.)
--
-- Correr UNA VEZ en phpMyAdmin, DESPUÉS de la v8.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Índices
-- ---------------------------------------------------------------------
ALTER TABLE leads
  -- sp_booking_list_upcoming: provider_id = ? AND desired_date BETWEEN ? AND ?
  ADD INDEX idx_leads_provider_trip (provider_id, desired_date),
  -- sp_lead_create (cupo): schedule_id = ? AND desired_date = ? AND status IN (...)
  -- Reemplaza a idx_leads_schedule (la FK sigue cubierta: empieza por schedule_id).
  ADD INDEX idx_leads_schedule_trip (schedule_id, desired_date, status),
  -- sp_lead_list_admin del guía: provider_id = ? AND status = ? ORDER BY created_at
  -- Reemplaza a idx_leads_status (status solo tiene 7 valores: no filtra nada).
  ADD INDEX idx_leads_provider_status (provider_id, status, created_at),
  DROP INDEX idx_leads_schedule,
  DROP INDEX idx_leads_status;

ALTER TABLE experiences
  DROP INDEX idx_exp_type,   -- 2 valores, nunca se filtra solo por tipo
  DROP INDEX idx_exp_geo;    -- ninguna consulta busca por rango de lat/lng

ALTER TABLE experience_schedules
  DROP INDEX idx_sched_active;  -- todas las consultas filtran por experience_id (uq_sched_unique)

-- ---------------------------------------------------------------------
-- 2) Integridad
-- ---------------------------------------------------------------------
ALTER TABLE experiences
  ADD CONSTRAINT chk_exp_pax    CHECK (min_pax >= 1 AND max_pax >= min_pax),
  ADD CONSTRAINT chk_exp_rating CHECK (external_rating IS NULL OR external_rating BETWEEN 0 AND 5),
  ADD CONSTRAINT chk_exp_price  CHECK (
        (price IS NULL OR price >= 0)
    AND (price_min IS NULL OR price_min >= 0)
    AND (price_max IS NULL OR price_min IS NULL OR price_max >= price_min));

ALTER TABLE experience_schedules
  ADD CONSTRAINT chk_sched_day   CHECK (day_of_week BETWEEN 0 AND 6),
  ADD CONSTRAINT chk_sched_range CHECK (valid_from IS NULL OR valid_to IS NULL OR valid_to >= valid_from);

-- pax es la suma que calcula sp_lead_create: que nunca se desincronice.
ALTER TABLE leads
  ADD CONSTRAINT chk_leads_pax CHECK (pax >= 1 AND pax = pax_adults + pax_children);

-- ---------------------------------------------------------------------
-- 3) Listado del panel: + cuántos horarios activos y cuántos "qué incluye"
-- ---------------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS sp_experience_list_admin$$
CREATE PROCEDURE sp_experience_list_admin (
    IN p_provider_id BIGINT UNSIGNED,
    IN p_city VARCHAR(100),
    IN p_vertical VARCHAR(20),
    IN p_is_active TINYINT,
    IN p_search VARCHAR(180),
    IN p_limit INT,
    IN p_offset INT
)  SQL SECURITY INVOKER
BEGIN
    SELECT e.*, p.business_name AS provider_name, p.status AS provider_status,
           (SELECT COUNT(*) FROM leads l WHERE l.experience_id = e.id) AS total_leads,
           (SELECT url FROM experience_images i WHERE i.experience_id = e.id
             ORDER BY i.is_cover DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS cover_image,
           (SELECT COUNT(*) FROM experience_schedules s
             WHERE s.experience_id = e.id AND s.is_active = 1) AS active_schedules,
           (SELECT COUNT(*) FROM experience_inclusions n
             WHERE n.experience_id = e.id) AS inclusions_count
    FROM experiences e
    INNER JOIN providers p ON p.id = e.provider_id
    WHERE (p_provider_id IS NULL OR e.provider_id = p_provider_id)
      AND (p_city IS NULL OR e.city = p_city)
      AND (p_vertical IS NULL OR e.vertical = p_vertical)
      AND (p_is_active IS NULL OR e.is_active = p_is_active)
      AND (p_search IS NULL OR e.title LIKE CONCAT('%',p_search,'%') OR e.slug LIKE CONCAT('%',p_search,'%'))
    ORDER BY e.created_at DESC
    LIMIT p_limit OFFSET p_offset;

    SELECT COUNT(*) AS total
    FROM experiences e
    WHERE (p_provider_id IS NULL OR e.provider_id = p_provider_id)
      AND (p_city IS NULL OR e.city = p_city)
      AND (p_vertical IS NULL OR e.vertical = p_vertical)
      AND (p_is_active IS NULL OR e.is_active = p_is_active)
      AND (p_search IS NULL OR e.title LIKE CONCAT('%',p_search,'%') OR e.slug LIKE CONCAT('%',p_search,'%'));
END$$

-- ---------------------------------------------------------------------
-- 4) Agenda del guía: + provider_name, para que el WhatsApp diga
--    "Soy Tucumán Walking Tours" y no "Soy tu guía". (Misma firma.)
-- ---------------------------------------------------------------------
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
           p.business_name AS provider_name,
           s.locale AS schedule_locale, s.start_time AS schedule_time,
           s.capacity_hint AS schedule_capacity
    FROM leads l
    INNER JOIN experiences e ON e.id = l.experience_id
    INNER JOIN providers p ON p.id = l.provider_id
    LEFT JOIN experience_schedules s ON s.id = l.schedule_id
    WHERE l.provider_id = p_provider_id
      AND l.desired_date BETWEEN p_from_date AND p_to_date
      AND l.status IN ('new','contacted','confirmed','attended','no_show')
    ORDER BY l.desired_date ASC, l.desired_time ASC, l.created_at ASC;
END$$

DELIMITER ;
