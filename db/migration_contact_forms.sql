-- =====================================================================
-- NORTE WALK — Migración: formularios públicos (sugerencias / waitlist /
-- ayuda / sumate). Tablas para captar leads e interés durante el
-- pre-lanzamiento y después.
--
-- Idempotente (CREATE TABLE IF NOT EXISTS). Correr en local (XAMPP) y en
-- producción (Ferozo / DonWeb) sobre la base c2731887_nortew.
--
--   mysql -u USER -p c2731887_nortew < api/db/migration_contact_forms.sql
-- =====================================================================

-- ---------------------------------------------------------------------
-- contact_messages
-- Mensajes libres del público. Una sola tabla con `kind` para distinguir:
--   suggestion → "¿Qué te gustaría conocer/experimentar del Norte?" (home)
--   waitlist   → "Avisame cuando se habilite" (botón reservar en pre-lanzamiento)
--   help       → formulario de Ayuda
--   contact    → contacto genérico
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind`        enum('suggestion','waitlist','help','contact') NOT NULL DEFAULT 'suggestion',
  `name`        varchar(150) DEFAULT NULL,
  `email`       varchar(180) DEFAULT NULL,
  `phone`       varchar(30)  DEFAULT NULL,
  `subject`     varchar(180) DEFAULT NULL,
  `message`     text         DEFAULT NULL,
  `locale`      enum('es','en','pt') NOT NULL DEFAULT 'es',
  `ref`         varchar(190) DEFAULT NULL COMMENT 'contexto: slug de experiencia (waitlist), etc.',
  `source_url`  varchar(255) DEFAULT NULL COMMENT 'página desde donde se envió',
  `utm_source`  varchar(80)  DEFAULT NULL,
  `utm_medium`  varchar(80)  DEFAULT NULL,
  `utm_campaign` varchar(120) DEFAULT NULL,
  `ip_address`  varchar(45)  DEFAULT NULL,
  `user_agent`  varchar(255) DEFAULT NULL,
  `status`      enum('new','read','replied','archived','spam') NOT NULL DEFAULT 'new',
  `created_at`  timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_contact_kind`    (`kind`),
  KEY `idx_contact_status`  (`status`),
  KEY `idx_contact_created` (`created_at`),
  KEY `idx_contact_email`   (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- join_requests
-- Guías / prestadores / empresas que quieren sumarse a NorteWalk.
-- Datos estructurados para que el equipo se ponga en contacto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `join_requests` (
  `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `applicant_type` enum('guide','provider','company','other') NOT NULL DEFAULT 'guide',
  `business_name` varchar(160) DEFAULT NULL,
  `contact_name`  varchar(150) NOT NULL,
  `email`         varchar(180) NOT NULL,
  `phone`         varchar(30)  DEFAULT NULL COMMENT 'WhatsApp',
  `city`          varchar(120) DEFAULT NULL,
  `website`       varchar(190) DEFAULT NULL COMMENT 'web o instagram',
  `offering`      varchar(255) DEFAULT NULL COMMENT 'qué ofrece: fwt, aventura, gastronomía...',
  `message`       text         DEFAULT NULL,
  `locale`        enum('es','en','pt') NOT NULL DEFAULT 'es',
  `source_url`    varchar(255) DEFAULT NULL,
  `utm_source`    varchar(80)  DEFAULT NULL,
  `utm_medium`    varchar(80)  DEFAULT NULL,
  `utm_campaign`  varchar(120) DEFAULT NULL,
  `ip_address`    varchar(45)  DEFAULT NULL,
  `user_agent`    varchar(255) DEFAULT NULL,
  `status`        enum('new','contacted','approved','rejected','archived','spam') NOT NULL DEFAULT 'new',
  `created_at`    timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_join_type`    (`applicant_type`),
  KEY `idx_join_status`  (`status`),
  KEY `idx_join_created` (`created_at`),
  KEY `idx_join_email`   (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
