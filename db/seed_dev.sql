-- =====================================================================
-- NORTE WALK - SEED DE DESARROLLO (solo local)
-- =====================================================================
-- Datos de prueba realistas para XAMPP. NO ejecutar en producción.
-- Requiere el esquema del dump c2731887_nortew-6 + migration_v7_reservas.
--
-- Usuarios:
--   admin@nortewalk.com  / admin123  (admin)
--   guia@tucuwalking.com / guia123   (provider #1)
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE leads;
TRUNCATE TABLE experience_schedules;
TRUNCATE TABLE experience_languages;
TRUNCATE TABLE experience_images;
TRUNCATE TABLE experience_inclusions;
TRUNCATE TABLE experience_itinerary;
TRUNCATE TABLE experience_blackout_dates;
TRUNCATE TABLE experience_translations;
TRUNCATE TABLE inclusion_translations;
TRUNCATE TABLE itinerary_translations;
TRUNCATE TABLE experiences;
TRUNCATE TABLE admin_users;
TRUNCATE TABLE subscription_events;
TRUNCATE TABLE providers;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- PROVIDERS
-- ---------------------------------------------------------------------
INSERT INTO providers
(id, slug, business_name, contact_name, email, whatsapp_e164, city, province, country, bio, status, paid_until, monthly_fee_usd)
VALUES
(1, 'tucu-walking', 'Tucumán Walking Tours', 'Lara Páez', 'guia@tucuwalking.com', '+5493815551234',
 'san-miguel-de-tucuman', 'Tucuman', 'AR',
 'Guías habilitados del Ente Tucumán Turismo. Hacemos free walking tours por el casco histórico desde 2019.',
 'active', '2027-12-31', 10.00),
(2, 'norte-aventura', 'Norte Aventura', 'Bruno Díaz', 'bruno@norteaventura.com', '+5493815559876',
 'yerba-buena', 'Tucuman', 'AR',
 'Trekking y experiencias de montaña en las yungas tucumanas. Grupos chicos, ritmo humano.',
 'active', '2027-12-31', 10.00);

-- ---------------------------------------------------------------------
-- ADMIN USERS  (admin123 / guia123)
-- ---------------------------------------------------------------------
INSERT INTO admin_users (id, email, password_hash, full_name, role, provider_id) VALUES
(1, 'admin@nortewalk.com', '$2y$10$gN7Bkpg7N/Yp/4zNLVTsau8Ppoaqk3xhN88QXL9Z6uBlkWvxl/J7e', 'Admin NorteWalk', 'admin', NULL),
(2, 'guia@tucuwalking.com', '$2y$10$Je0E4xp.UBDYcQ606GnN1uClDIK53IAKTdCMEGHTheNKy2Bnrtxl.', 'Lara Páez', 'provider', 1);

-- ---------------------------------------------------------------------
-- EXPERIENCES
-- ---------------------------------------------------------------------
INSERT INTO experiences
(id, provider_id, slug, title, short_desc, long_desc, vertical, category, type,
 price, price_min, price_max, currency, duration_min, difficulty,
 meeting_point, city, province, latitude, longitude,
 min_pax, max_pax, min_age, is_active, is_featured, external_rating, external_reviews_count)
VALUES
(1, 1, 'fwt-centro-historico-tucuman',
 'Free Walking Tour Centro Histórico de Tucumán',
 'La introducción perfecta a Tucumán: Casa Histórica, Plaza Independencia y las historias que no salen en los folletos.',
 'Caminamos el casco fundacional de San Miguel de Tucumán: la Casa Histórica de la Independencia, la Plaza Independencia, la Catedral y los pasajes que cuentan 200 años de historia argentina. Un recorrido pensado para ubicarte en la ciudad el primer día: te llevás contexto, recomendaciones de comida y los mejores planes para el resto de tu viaje.\n\nEl tour es a la gorra: al final aportás lo que sientas que valió la experiencia.',
 'fwt', 'ciudad', 'free',
 NULL, 5000.00, 20000.00, 'ARS', 150, 'easy',
 'Fuente de Plaza Independencia, frente a la Casa de Gobierno', 'san-miguel-de-tucuman', 'Tucuman',
 -26.8305000, -65.2038000,
 1, 15, 0, 1, 1, 4.90, 127),

(2, 1, 'fwt-atardecer-yerba-buena',
 'Caminata al Atardecer por Yerba Buena',
 'El lado verde de Tucumán: avenida Aconquija, historias de quintas y el mejor atardecer con vista al cerro.',
 'Yerba Buena es el jardín de Tucumán. Caminamos la avenida Aconquija hacia el pedemonte mientras baja el sol detrás del San Javier. Historias de las quintas históricas, la transformación de la ciudad y una parada para el mate compartido viendo el atardecer.\n\nTour a la gorra: vos decidís el valor al final.',
 'fwt', 'ciudad', 'free',
 NULL, 5000.00, 20000.00, 'ARS', 120, 'easy',
 'Portón del Parque Percy Hill, Av. Perón 2400', 'yerba-buena', 'Tucuman',
 -26.8167000, -65.3167000,
 1, 12, 0, 1, 1, 4.80, 64),

(3, 2, 'trekking-cerro-san-javier',
 'Trekking en las Yungas del San Javier',
 'Selva de montaña a 30 minutos de la ciudad: senderos entre helechos gigantes, miradores y cascadas.',
 'Subimos al cerro San Javier para meternos en la selva de yungas: un ecosistema único de niebla, helechos gigantes y aves que no vas a ver en otro lado. Caminata de dificultad moderada con paradas de interpretación de flora, mirador del valle y final en una cascada escondida.\n\nIncluye guía habilitado de montaña y seguro de actividad. Llevá calzado cerrado y agua.',
 'adventure', 'trekking', 'paid',
 35000.00, NULL, NULL, 'ARS', 240, 'moderate',
 'Base del cerro, rotonda El Corte, Yerba Buena', 'yerba-buena', 'Tucuman',
 -26.7833000, -65.3667000,
 2, 10, 12, 1, 0, 4.70, 41),

(4, 1, 'sabores-del-norte-mercado',
 'Sabores del Norte: Mercado y Empanadas',
 'Ruta gastronómica por el Mercado del Norte: empanadas, sanguche de milanesa y los puestos que solo conocen los locales.',
 'Un recorrido para comer Tucumán: arrancamos en el Mercado del Norte probando quesillo con miel de caña, seguimos por las mejores empanaderías del centro y cerramos con el clásico sanguche de milanesa. Historia gastronómica, datos de cocina criolla y todas las degustaciones incluidas.',
 'gastronomy', 'gastro', 'paid',
 45000.00, NULL, NULL, 'ARS', 180, 'easy',
 'Entrada del Mercado del Norte, Av. Sáenz Peña y Mendoza', 'san-miguel-de-tucuman', 'Tucuman',
 -26.8244000, -65.2107000,
 2, 8, 0, 1, 1, 5.00, 23);

-- ---------------------------------------------------------------------
-- IMAGES (las dos que existen físicamente en public/img/experiences)
-- ---------------------------------------------------------------------
INSERT INTO experience_images (experience_id, url, alt_text, sort_order, is_cover) VALUES
(1, '/api/public/img/experiences/exp2_img_6a0dacd687793.jpg', 'Casa Histórica de Tucumán', 0, 1),
(2, '/api/public/img/experiences/exp7_img_6a0df1305c70c.jpg', 'Atardecer en Yerba Buena', 0, 1);

-- ---------------------------------------------------------------------
-- LANGUAGES (idiomas que habla el guía por experiencia)
-- ---------------------------------------------------------------------
INSERT INTO experience_languages (experience_id, language_code, sort_order) VALUES
(1, 'es', 0), (1, 'en', 1), (1, 'pt', 2),
(2, 'es', 0), (2, 'en', 1),
(3, 'es', 0),
(4, 'es', 0), (4, 'en', 1);

-- ---------------------------------------------------------------------
-- SCHEDULES (day_of_week: 0=Dom ... 6=Sab) con cupos reales
-- ---------------------------------------------------------------------
INSERT INTO experience_schedules (experience_id, day_of_week, start_time, locale, capacity_hint, is_active) VALUES
-- FWT Centro Histórico: ES lun-sab 10:30, EN mar/jue/sab 10:30, PT sab 16:00
(1, 1, '10:30:00', 'es', 15, 1),
(1, 2, '10:30:00', 'es', 15, 1),
(1, 3, '10:30:00', 'es', 15, 1),
(1, 4, '10:30:00', 'es', 15, 1),
(1, 5, '10:30:00', 'es', 15, 1),
(1, 6, '10:30:00', 'es', 15, 1),
(1, 2, '10:30:00', 'en', 10, 1),
(1, 4, '10:30:00', 'en', 10, 1),
(1, 6, '10:30:00', 'en', 10, 1),
(1, 6, '16:00:00', 'pt', 8, 1),
-- FWT Yerba Buena: ES vie/sab/dom 17:30, EN sab 17:30
(2, 5, '17:30:00', 'es', 12, 1),
(2, 6, '17:30:00', 'es', 12, 1),
(2, 0, '17:30:00', 'es', 12, 1),
(2, 6, '17:30:00', 'en', 10, 1),
-- Trekking San Javier: ES sab/dom 08:00
(3, 6, '08:00:00', 'es', 8, 1),
(3, 0, '08:00:00', 'es', 8, 1),
-- Sabores del Norte: ES jue/vie 19:00, EN vie 19:00
(4, 4, '19:00:00', 'es', 10, 1),
(4, 5, '19:00:00', 'es', 10, 1),
(4, 5, '19:00:00', 'en', 6, 1);

-- ---------------------------------------------------------------------
-- INCLUSIONS (experiencia 1 y 3 como muestra)
-- ---------------------------------------------------------------------
INSERT INTO experience_inclusions (experience_id, text, kind, sort_order) VALUES
(1, 'Guía local habilitado', 'included', 0),
(1, 'Mapa con recomendaciones para el resto de tu viaje', 'included', 1),
(1, 'Entradas a museos', 'excluded', 0),
(3, 'Guía de montaña habilitado', 'included', 0),
(3, 'Seguro de actividad', 'included', 1),
(3, 'Snack de cumbre y agua', 'included', 2),
(3, 'Traslado hasta la base', 'excluded', 0);

-- ---------------------------------------------------------------------
-- ITINERARY (experiencia 1)
-- ---------------------------------------------------------------------
INSERT INTO experience_itinerary (experience_id, step_order, title, description, duration_min) VALUES
(1, 1, 'Plaza Independencia', 'Punto de encuentro y contexto: la fundación de la ciudad y los edificios de poder.', 30),
(1, 2, 'Casa Histórica de la Independencia', 'La casa donde se declaró la independencia argentina en 1816 y sus mitos.', 45),
(1, 3, 'Mercado y pasajes', 'Los pasajes escondidos del microcentro y cierre con recomendaciones gastronómicas.', 45);

-- ---------------------------------------------------------------------
-- TRANSLATIONS (experiencia 1 en inglés, como muestra)
-- ---------------------------------------------------------------------
INSERT INTO experience_translations (experience_id, locale, title, short_desc, long_desc, meeting_point) VALUES
(1, 'en', 'Free Walking Tour: Tucumán Historic Center',
 'The perfect intro to Tucumán: Independence House, Plaza Independencia and the stories guidebooks miss.',
 'We walk the founding blocks of San Miguel de Tucumán: the Independence House, Plaza Independencia, the Cathedral and the hidden passages that tell 200 years of Argentine history. Designed for your first day in town — you leave with context, food tips and the best plans for the rest of your trip.\n\nThis is a tip-based tour: at the end you contribute what you feel it was worth.',
 'Fountain at Plaza Independencia, in front of the Government House');

-- ---------------------------------------------------------------------
-- LEADS de muestra (para ver el panel con datos)
-- desired_date relativos a hoy para que siempre haya "próximas reservas"
-- ---------------------------------------------------------------------
INSERT INTO leads
(booking_code, experience_id, provider_id, schedule_id,
 tourist_name, tourist_surname, tourist_phone, tourist_email,
 preferred_locale, desired_date, desired_time, pax, pax_adults, pax_children,
 message, source, status, created_at)
VALUES
('NW-DEMO1', 1, 1, 1, 'María', 'González', '+5491155512345', 'maria.gonzalez@gmail.com',
 'es', DATE_ADD(CURDATE(), INTERVAL 2 DAY), '10:30:00', 2, 2, 0,
 NULL, 'nortewalk.com', 'new', NOW()),
('NW-DEMO2', 1, 1, 7, 'James', 'Carter', '+447911123456', 'jcarter@outlook.com',
 'en', DATE_ADD(CURDATE(), INTERVAL 3 DAY), '10:30:00', 3, 2, 1,
 'We are travelling with a 6 year old, is that ok?', 'nortewalk.com', 'contacted', DATE_SUB(NOW(), INTERVAL 1 DAY)),
('NW-DEMO3', 2, 1, 11, 'Ana', 'Silva', '+5511999887766', 'ana.silva@uol.com.br',
 'es', DATE_ADD(CURDATE(), INTERVAL 4 DAY), '17:30:00', 4, 4, 0,
 NULL, 'nortewalk.com', 'confirmed', DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Alinear la fecha de cada lead demo al próximo día de semana de su schedule
-- (los INSERT usan offsets fijos; esto garantiza coherencia fecha/horario).
UPDATE leads l
INNER JOIN experience_schedules s ON s.id = l.schedule_id
SET l.desired_date = DATE_ADD(
    CURDATE(),
    INTERVAL ((CAST(s.day_of_week AS SIGNED) - (DAYOFWEEK(CURDATE()) - 1) + 6) % 7) + 1 DAY
);
