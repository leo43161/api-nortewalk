-- =====================================================================
-- NORTE WALK - MIGRACION v8: SIMPLIFICACION (panel nuevo)
-- =====================================================================
-- * Idiomas de una experiencia = idiomas de sus salidas activas
--   (experience_schedules.locale). Se elimina experience_languages: era una
--   segunda fuente de verdad que se desincronizaba de los horarios (podías
--   marcar "Francés" sin ninguna salida en francés).
--   sp_language_list_by_experience y sp_experience_list_public devuelven
--   las MISMAS columnas que antes: el sitio público no cambia.
-- * sp_experience_set_rating: el admin carga la reseña externa
--   (Google / TripAdvisor) desde el panel.
-- * sp_experience_list_admin devuelve además cover_image (miniaturas).
-- * Las experiencias nuevas se crean ocultas (is_active DEFAULT 0).
-- * Limpieza: 13 SPs y 1 vista que ninguna parte del sistema usa,
--   2 índices redundantes, provincia "Tucuman" -> "Tucumán".
--
-- Correr UNA VEZ en phpMyAdmin, ANTES de subir la API y el panel nuevos.
-- (El DROP INDEX falla si se corre dos veces; el resto es idempotente.)
-- =====================================================================

-- Sin esto, una consola con otro charset guarda "TucumÃ¡n".
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1) Datos: provincia con tilde (la collation ya las trataba iguales,
--    esto es solo para que se vea bien).
-- ---------------------------------------------------------------------
UPDATE experiences SET province = 'Tucumán' WHERE province = 'Tucuman';
UPDATE providers   SET province = 'Tucumán' WHERE province = 'Tucuman';
ALTER TABLE experiences ALTER province SET DEFAULT 'Tucumán';
ALTER TABLE providers   ALTER province SET DEFAULT 'Tucumán';

-- Una experiencia nueva nace OCULTA: sin fotos ni horarios no tiene que
-- aparecer en el sitio. El panel avisa cuando está lista para activarla.
-- (Las existentes no cambian.)
ALTER TABLE experiences ALTER is_active SET DEFAULT 0;

-- ---------------------------------------------------------------------
-- 2) Índices redundantes (ya cubiertos por idx_exp_city_vertical e
--    idx_exp_category_active, que empiezan por la misma columna).
-- ---------------------------------------------------------------------
ALTER TABLE experiences
  DROP INDEX idx_exp_city,
  DROP INDEX idx_exp_category;

-- ---------------------------------------------------------------------
-- 3) Stored procedures
-- ---------------------------------------------------------------------
DELIMITER $$

-- ============ Idiomas: derivados de las salidas ============
-- Mismo shape que la tabla vieja (id, experience_id, language_code, sort_order).
-- locale es ENUM('es','en','pt'): ordenar por el enum da es, en, pt.
DROP PROCEDURE IF EXISTS sp_language_list_by_experience$$
CREATE PROCEDURE sp_language_list_by_experience (
    IN p_experience_id BIGINT UNSIGNED
)  SQL SECURITY INVOKER
BEGIN
    SELECT MIN(id) AS id,
           experience_id,
           locale AS language_code,
           (locale + 0) - 1 AS sort_order
    FROM experience_schedules
    WHERE experience_id = p_experience_id AND is_active = 1
    GROUP BY experience_id, locale
    ORDER BY locale ASC;
END$$

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
        sl.locales_csv AS languages_csv,
        sl.locales_csv AS schedule_locales_csv
    FROM v_active_experiences v
    LEFT JOIN experience_translations et
      ON et.experience_id = v.id AND et.locale = p_locale AND p_locale <> 'es'
    LEFT JOIN (
        SELECT experience_id,
               GROUP_CONCAT(DISTINCT locale ORDER BY locale ASC SEPARATOR ',') AS locales_csv
        FROM experience_schedules
        WHERE is_active = 1
        GROUP BY experience_id
    ) sl ON sl.experience_id = v.id
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

-- ============ Listado del panel: + foto de portada (misma firma) ============
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
             ORDER BY i.is_cover DESC, i.sort_order ASC, i.id ASC LIMIT 1) AS cover_image
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

-- ============ Reseña externa (solo admin) ============
DROP PROCEDURE IF EXISTS sp_experience_set_rating$$
CREATE PROCEDURE sp_experience_set_rating (
    IN p_id BIGINT UNSIGNED,
    IN p_rating DECIMAL(3,2),
    IN p_count INT UNSIGNED,
    IN p_url VARCHAR(500)
)  SQL SECURITY INVOKER
BEGIN
    UPDATE experiences
    SET external_rating        = p_rating,
        external_reviews_count = IFNULL(p_count, 0),
        external_reviews_url   = p_url
    WHERE id = p_id;
    SELECT ROW_COUNT() AS affected;
END$$

DELIMITER ;

-- ---------------------------------------------------------------------
-- 4) Fuera: tabla de idiomas y todo lo que nadie llama
-- ---------------------------------------------------------------------
DROP PROCEDURE IF EXISTS sp_language_add;
DROP PROCEDURE IF EXISTS sp_language_delete;
DROP PROCEDURE IF EXISTS sp_language_replace;
DROP TABLE IF EXISTS experience_languages;

DROP VIEW IF EXISTS v_active_schedules;

DROP PROCEDURE IF EXISTS sp_admin_change_password;   -- la API usa un UPDATE directo
DROP PROCEDURE IF EXISTS sp_admin_update;
DROP PROCEDURE IF EXISTS sp_experience_list_by_provider;
DROP PROCEDURE IF EXISTS sp_experience_search;
DROP PROCEDURE IF EXISTS sp_experience_translation_delete;
DROP PROCEDURE IF EXISTS sp_image_reorder;
DROP PROCEDURE IF EXISTS sp_inclusion_reorder;
DROP PROCEDURE IF EXISTS sp_itinerary_reorder;
DROP PROCEDURE IF EXISTS sp_lead_list_by_provider;
DROP PROCEDURE IF EXISTS sp_schedule_toggle_active;
