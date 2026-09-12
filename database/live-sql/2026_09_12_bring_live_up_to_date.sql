-- Schema and list rows live is missing, for hand-upload through phpMyAdmin.
--
-- Live is cPanel shared hosting with no SSH, so `php artisan migrate` never
-- runs there: a `git pull` brings the code and leaves the database where it
-- was. Everything below is the exact effect of a migration that has run here
-- and not there, plus the `migrations` rows so a later deploy with a console
-- does not try to run them a second time.
--
-- Safe to run more than once.

-- ---------------------------------------------------------------------------
-- 1. experiences.markup_percent
--    Migration: 2026_09_09_100000_give_each_experience_its_own_margin
--
--    THIS IS THE ONE THAT BREAKS SAVING. The admin experience form posts
--    markup_percent on every save and the column is in $fillable, so with the
--    column absent every INSERT and UPDATE fails with
--      SQLSTATE[42S22]: Unknown column 'markup_percent'
--    and the admin is told only "Something went wrong".
-- ---------------------------------------------------------------------------
SET @col := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'experiences'
      AND COLUMN_NAME = 'markup_percent'
);
SET @sql := IF(@col = 0,
    'ALTER TABLE `experiences` ADD COLUMN `markup_percent` DECIMAL(5,2) NULL AFTER `price_currency`',
    'SELECT "experiences.markup_percent already present"'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- 2. Option lists the provider app's pickers read
--    Migration: 2026_09_01_100000_split_the_occupancy_and_unit_lists
--
--    occupancy_unit was doing three jobs, so a member pricing a room was
--    offered "per km". Not related to the three faults reported, but the app
--    reads these lists and live has none of them.
-- ---------------------------------------------------------------------------
INSERT INTO `system_lists` (`list_type`, `name`, `description`, `is_active`, `sort_order`, `created_at`, `updated_at`)
SELECT * FROM (
    SELECT 'room_occupancy' AS a, 'per single' AS b, 'One person in the room. The highest rate per head.' AS c, 1 AS d, 10 AS e, NOW() AS f, NOW() AS g
    UNION ALL SELECT 'room_occupancy', 'per double', 'Two people sharing the room. The usual for a couple.', 1, 20, NOW(), NOW()
    UNION ALL SELECT 'room_occupancy', 'per triple', 'Three people sharing one room.', 1, 30, NOW(), NOW()
    UNION ALL SELECT 'room_occupancy', 'per quad',   'Four sharing — a family room, or a dormitory.', 1, 40, NOW(), NOW()
    UNION ALL SELECT 'room_occupancy', 'per room',   'A flat rate for the room, whatever the number of people in it.', 1, 50, NOW(), NOW()
    UNION ALL SELECT 'transport_unit', 'per km',   'Billed by the distance driven.', 1, 10, NOW(), NOW()
    UNION ALL SELECT 'transport_unit', 'per day',  'A flat rate for the day, whatever the distance.', 1, 20, NOW(), NOW()
    UNION ALL SELECT 'transport_unit', 'per trip', 'A flat rate for the whole journey, there and back.', 1, 30, NOW(), NOW()
    UNION ALL SELECT 'activity_unit', 'per person',         'Each traveller pays this.', 1, 10, NOW(), NOW()
    UNION ALL SELECT 'activity_unit', 'per group',          'One rate for the whole group, whatever its size.', 1, 20, NOW(), NOW()
    UNION ALL SELECT 'activity_unit', 'per day',            'Charged by the day.', 1, 30, NOW(), NOW()
    UNION ALL SELECT 'activity_unit', 'per person per day', 'Each traveller pays this for each day.', 1, 40, NOW(), NOW()
) AS incoming
WHERE NOT EXISTS (
    SELECT 1 FROM `system_lists` sl
    WHERE sl.`list_type` = incoming.a AND sl.`name` = incoming.b
);

-- ---------------------------------------------------------------------------
-- 3. What each experience category covers
--    Migration: 2026_08_31_100000_note_what_each_experience_category_covers
--
--    The voice assistant is given these notes so that "I teach cooking to
--    tourists" files under workshops rather than guiding. Only rows with no
--    note are touched — a description HCT has written by hand stays theirs.
-- ---------------------------------------------------------------------------
UPDATE `system_lists` SET `description` =
    "Staying at the member's own place — a homestay, a village house, a farm stay, a lodge. The traveller sleeps there."
WHERE `list_type` = 'experience_category'
  AND `name` = 'Experiential accommodation'
  AND (`description` IS NULL OR `description` = '');

UPDATE `system_lists` SET `description` =
    'Taking travellers out and showing them something — treks, village and nature walks, wildlife and bird watching, sightseeing, guided visits.'
WHERE `list_type` = 'experience_category'
  AND `name` = 'Guided Cultural & Outdoor Activities'
  AND (`description` IS NULL OR `description` = '');

UPDATE `system_lists` SET `description` =
    'Teaching or demonstrating a skill or a tradition — cooking classes, weaving, pottery, woodwork, farming, music, folklore and storytelling.'
WHERE `list_type` = 'experience_category'
  AND `name` = 'Workshops, Handicrafts, Local Knowledge & Storytelling'
  AND (`description` IS NULL OR `description` = '');

-- ---------------------------------------------------------------------------
-- 4. Mark these migrations as run, so a future deploy that does have a console
--    skips them instead of failing on a column that is already there.
-- ---------------------------------------------------------------------------
INSERT INTO `migrations` (`migration`, `batch`)
SELECT * FROM (
    SELECT '2026_08_31_100000_note_what_each_experience_category_covers' AS m,
           (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations` mm) AS b
    UNION ALL SELECT '2026_09_01_100000_split_the_occupancy_and_unit_lists',
           (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations` mm)
    UNION ALL SELECT '2026_09_09_100000_give_each_experience_its_own_margin',
           (SELECT COALESCE(MAX(batch), 0) + 1 FROM `migrations` mm)
) AS incoming
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations` mg WHERE mg.`migration` = incoming.m
);
