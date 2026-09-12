-- Trips that were never anything, and the leads that point at them.
--
-- WHY THESE EXIST. Until this was fixed, every chat turn from a signed-in
-- traveller made a brand new trip and a brand new lead whenever the page had
-- no trip_id to send — which is most turns, because the page sends whatever it
-- was rendered with and that is empty until a trip exists. The new trip then
-- carried the newest updated_at, so it won the ordering every other handler
-- resolves by, and the journey the traveller had actually built stopped being
-- the one they were shown. That is the fault reported as "not able to see my
-- added journey to book further".
--
-- The code fix stops new ones. This clears what has already collected.
--
-- RUN STEP 1 FIRST and read it. It changes nothing and shows exactly what
-- step 2 would remove. Only run step 2 when the list looks right.
--
-- Deliberately conservative: it takes only trips with no name of their own or
-- the default "My Trip", so a trip somebody named and meant to come back to is
-- left alone even if it is empty. Widen it by removing the trip_name condition
-- from BOTH statements if that is what you want — but look at step 1 again
-- after you do.


-- ---------------------------------------------------------------------------
-- STEP 1 — what would go. Changes nothing.
-- ---------------------------------------------------------------------------
SELECT
    t.`id`,
    t.`user_id`,
    u.`email`,
    t.`trip_name`,
    t.`created_at`,
    (SELECT COUNT(*) FROM `ai_conversations` c WHERE c.`trip_id` = t.`id`) AS chat_messages,
    (SELECT COUNT(*) FROM `leads` l WHERE l.`trip_id` = t.`id`)            AS leads
FROM `trips` t
LEFT JOIN `users` u ON u.`id` = t.`user_id`
WHERE t.`status` = 'not_confirmed'
  AND (t.`trip_name` IS NULL OR t.`trip_name` = '' OR t.`trip_name` = 'My Trip')
  AND NOT EXISTS (SELECT 1 FROM `trip_selected_experiences` x WHERE x.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `trip_days`                 d WHERE d.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `traveller_payments`        p WHERE p.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `sp_payments`               s WHERE s.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `sp_room_bookings`          b WHERE b.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `support_requests`          r WHERE r.`trip_id` = t.`id`)
ORDER BY t.`user_id`, t.`id`;


-- ---------------------------------------------------------------------------
-- STEP 2 — remove them. Run only after reading step 1.
--
-- The leads go first and on purpose. leads.trip_id is ON DELETE SET NULL, so
-- dropping the trip alone would leave the lead sitting in HCT's list attached
-- to nothing, which is worse than the trip was. Everything else a trip owns —
-- ai_conversations, trip_days, trip_regions — is ON DELETE CASCADE and goes
-- with it.
-- ---------------------------------------------------------------------------
DELETE l FROM `leads` l
JOIN `trips` t ON t.`id` = l.`trip_id`
WHERE t.`status` = 'not_confirmed'
  AND (t.`trip_name` IS NULL OR t.`trip_name` = '' OR t.`trip_name` = 'My Trip')
  AND NOT EXISTS (SELECT 1 FROM `trip_selected_experiences` x WHERE x.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `trip_days`                 d WHERE d.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `traveller_payments`        p WHERE p.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `sp_payments`               s WHERE s.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `sp_room_bookings`          b WHERE b.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `support_requests`          r WHERE r.`trip_id` = t.`id`);

DELETE t FROM `trips` t
WHERE t.`status` = 'not_confirmed'
  AND (t.`trip_name` IS NULL OR t.`trip_name` = '' OR t.`trip_name` = 'My Trip')
  AND NOT EXISTS (SELECT 1 FROM `trip_selected_experiences` x WHERE x.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `trip_days`                 d WHERE d.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `traveller_payments`        p WHERE p.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `sp_payments`               s WHERE s.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `sp_room_bookings`          b WHERE b.`trip_id` = t.`id`)
  AND NOT EXISTS (SELECT 1 FROM `support_requests`          r WHERE r.`trip_id` = t.`id`);


-- ---------------------------------------------------------------------------
-- STEP 3 — check. Both should come back 0.
-- ---------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM `leads` WHERE `trip_id` IS NULL)                       AS leads_pointing_at_nothing,
    (SELECT COUNT(*) FROM (
        SELECT `user_id` FROM `trips` WHERE `status` = 'not_confirmed'
        GROUP BY `user_id` HAVING COUNT(*) > 1
    ) AS several)                                                                AS travellers_with_several_open_trips;
