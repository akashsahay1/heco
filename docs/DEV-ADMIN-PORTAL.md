# HECO Admin Portal — Development Tasks

Source: MVP.txt sections 3, 8, 12, 13, 15, 16
Domain: `hecoadmin.test`
Target users: HCT Admin / HCT Collaborator

**Verified against codebase 2026-04-29.** Each task was checked by inspecting routes (`routes/web.php`), the AJAX dispatcher in `AjaxController.php`, all admin views under `resources/views/admin/`, controllers, models, and migration columns.

Legend: `[x]` complete · `[~]` partial · `[ ]` not started

---

## 1. Authentication & Session

- [x] Admin login page (`AdminAuthController` + `auth/login.blade.php`)
- [x] Admin domain ajax endpoint with `auth + hct` middleware (route group at `routes/web.php:28`)
- [x] Role-based access: `hct_admin` vs `hct_collaborator` (`User::isHct()`, `isHctAdmin()`)
- [x] Logout
- [x] `hct_admin` middleware gating Admin tab

## 2. Admin Tab (Admin-only — system governance)

### 2.A HCT User Management
- [x] List all HCT users (`admin.blade.php:16` — loops `$hctUsers`)
- [x] Create HCT user (`create_hct_user` AJAX + form at `admin.blade.php:117`)
- [x] Update HCT user (`update_hct_user` AJAX, fields: full_name, email, role, mobile, photo, password)
- [x] Activate / deactivate HCT user (`deactivate_hct_user` at `admin.blade.php:126`)
- [x] Deactivated users cannot log in
- [x] Deactivated users remain visible in historical records

### 2.B System Reference Lists
- [x] `system_lists` table with `list_type` grouping
- [x] CRUD via AJAX (`get_system_lists`, `save_system_list_item`, `deactivate_system_list_item`)
- [x] Seeded lists: service_type, accommodation_category, vehicle_type, activity_type, experience_type, payment_mode
- [x] Seeded preference lists: accommodation_comfort, vehicle_comfort, guide_preference, travel_pace, budget_sensitivity (`PreferenceListsSeeder`)
- [x] Lists feed traveller portal dropdowns
- [x] Historical records keep their old value (no retroactive rewrite)
- [x] Region admin (`/regions` + `save_region` / `toggle_region` / `delete_region`)
- [x] Currency admin (`/currencies` + `save_currency` / `toggle_currency` / `delete_currency`)

### 2.C System Settings
- [x] `settings` table + `get_settings` / `save_settings` AJAX
- [x] PDF templates table + admin (`get_pdf_templates` / `save_pdf_template`)
- [x] GST/tax + margin defaults stored on Trip and configurable per trip
- [ ] Global default GST rate / margin defaults UI in Admin tab
- [ ] Email templates configuration UI
- [ ] AI prompts admin UI (table `ai_prompts` exists with seeded data; no admin page to edit prompts)

## 3. Control Panel Tab

### 3.A Profile & Access Management
- [x] Edit own profile — `update_profile` AJAX (name, photo, mobile, address)
- [x] Change password — `change_password` AJAX
- [x] View own role + permissions

### 3.B Traveller Support & Communication Hub
- [x] List of incoming support requests — `get_support_requests`
- [x] Each request shows traveller ID, trip ID, message, date/time, status
- [x] Resolve button — `resolve_support_request`
- [x] Open Trip Manager from a support request
- [x] Trip notes field (`trips.general_notes` editable from Trip Manager)
- [x] Per-trip AI conversation pause works via routing message into AiConversation log

### 3.C AI Interaction & Data Feeding Interface
- [x] HCT chat with AI — `chat_with_ai_hct` → `chatWithAiHct`
- [x] Slim trip context payload to AI (no `$trip->toJson()` blowup — replaced with `$tripSummary`)
- [x] AiConversation log retains all data-feeding turns
- [~] AI explicit "I don't have data X" prompts — AI generates these inline; no dedicated MVP-style queue/worklist UI
- [~] Manual data feeding loop — happens via standard chat; no specific "missing data" widget

### 3.D Lead Follow-up Reminders
- [x] `get_lead_reminders` AJAX endpoint
- [x] List shows leads where `last_communication + reminder_delay_days < today`
- [x] Lead name + Traveller ID + Trip ID
- [x] Read-only list (must edit in Leads tab)
- [x] Reddish row when overdue

### 3.E Experience and RP Creation Shortcuts
- [x] Manage Experiences button → `/experiences`
- [x] Manage RPs button → `/regenerative-projects`

## 4. Leads Tab

- [x] One row per active Follow-up lead (`leads.blade.php`)
- [x] Columns: enquiry date, traveller ID, name, assigned HCT, lead stage, trip ID, last interaction, mode, reminder delay, notes
- [x] Stages: Follow-up / Won / Lost
- [x] Auto-create lead when traveller starts building (`LeadService::createOrGetLead` in `createTrip`)
- [x] Auto Won when payment received
- [x] Manual Lost
- [x] Won/Lost removes from active list
- [x] Assign lead to HCT member
- [x] "Open Trip Manager" button per row
- [x] Update lead — `update_lead` (stage, assignee, mode, reminder, notes)
- [x] Lead history view — `get_lead_history` AJAX returns full record
- [ ] Edit stage from history pop-up (Lost → Follow-up restoration UI not wired; backend `update_lead` accepts it)

## 5. Trips Tab (Upcoming Trips)

- [x] List of confirmed/running trips not yet completed (`trips.blade.php`)
- [x] Columns: Trip ID, traveller, PAX, days, dates, price, paid, balance
- [x] "Open Trip Manager" button per row
- [x] `getUpcomingTrips` AJAX
- [x] Date range filter UI (`#dateFrom` / `#dateTo` inputs at `trips.blade.php:16-17`, `get_trips_by_date_range`)
- [x] Edit trip status from list — `update_trip_status` AJAX
- [x] Reopen trip — `reopen_trip` AJAX
- [x] Completed trips archived but accessible

## 6. Calendar Tab

- [x] Calendar view (`calendar.blade.php` + `renderCalendar()`)
- [x] Each trip as a block — `get_calendar_trips` AJAX
- [x] Click trip → Trip Manager
- [x] Month navigation

## 7. Payments Tab

### 7.A Payments to SPs
- [x] One block per confirmed trip
- [x] Trip blocks ordered by departure date (soonest at top)
- [x] SP payment entries — `add_sp_payment_entry` / `edit_sp_payment_entry`
- [x] Each SP payment: SP name, service type, trip ID, amount due/paid/balance, notes
- [x] Add payment pop-up
- [x] View payment history per SP per trip — `get_sp_payment_history`
- [x] Edit payment entry
- [x] Cancelled trips remove SP payment block
- [~] Auto-generate SP payment entries on trip *confirmation* (entries are created during itinerary build via `SpMatchingService`; explicit confirmation-time generator TBD)
- [~] Block-disappears-when-balance-zero behaviour (data supports it; UI filter logic verify)

### 7.B Payments from Travellers
- [x] Per-trip listing
- [x] Read-only on Payments tab (entered in Trip Manager) — `get_traveller_payments_overview`
- [x] Auto-update of Total paid + Balance due
- [x] Razorpay integration (test mode) — `create_razorpay_order` / `verify_razorpay_payment`
- [x] Razorpay payment failure logging — `log_razorpay_failure`
- [x] Add traveller payment — `add_traveller_payment`
- [x] Edit traveller payment — `edit_traveller_payment`

## 8. GST Tab

- [x] `gst.blade.php` view
- [x] `get_gst_report` AJAX with month filter
- [x] Columns: date, client ID, name, trip ID, amount, mode, trip price, start date

## 9. Providers Tab

- [x] List of HRPs / HLHs / OSPs (`providers.blade.php`)
- [x] Filters: type, region, status, search
- [x] **Last updated by** column (Admin / Provider role badge)
- [x] Per-row View button → `/providers/{id}` (full page; modal removed)
- [x] Per-row Edit button → `/providers/{id}/edit`
- [x] SP detail page: identity, bank, capabilities (services, accommodation, vehicle, **guide_types**), status controls, trip history, payment history, availability calendar
- [x] SP edit page (admin): identity, type, region, contact, bank, capabilities, status, notes
- [x] Status changes locked to HCT users (defensive `isHct()` check in `editProvider` + UI scope)
- [x] `last_updated_by` + `last_updated_by_role` audit fields populated on every update
- [x] Approval timestamp + approver tracked (`approved_at`, `approved_by`)
- [x] iCal sync for SP availability (`sp_save_ical_url` / `sp_sync_ical_now`)
- [x] Trip history per SP — `get_provider_trips`
- [x] Payment history per SP — `get_provider_payment_history`

## 10. Travelers Tab

- [x] List of travellers — `get_travelers_list` (`travelers.blade.php`)
- [x] Sorted by end date of last trip
- [x] Columns: name, ID, status
- [x] Filter by region + date range
- [x] Per-traveller: trips list — `get_traveler_trips`
- [x] Per-traveller: payment history — `get_traveler_payment_history`

## 11. Provider Applications Tab

- [x] List of new applications — `get_provider_applications` (`provider-applications.blade.php`)
- [x] Columns: applicant name, type, region, phone, email
- [x] View / edit details button
- [x] Approve action — `approve_provider`
- [x] Reject action — `reject_provider`
- [x] Approved providers move out of Applications, become active
- [x] Rejected providers don't appear in operational lists
- [~] "Request clarification" outcome (MVP says outside-platform; no in-app placeholder)

---

## 12. Trip Manager — Trip Info Tab (`trip-info.blade.php`)

### 12.1 Trip Identity
- [x] Trip ID display
- [x] Traveller ID + name
- [x] Traveller origin (Indian / Foreigner) — `trips.traveller_origin`
- [x] Trip status: Not confirmed / Confirmed / Running / Completed / Cancelled
- [x] Trip stage: Open / Closed
- [x] Auto status transitions on payment / cancellation

### 12.2 Group & Logistics
- [x] PAX breakdown (adults / children / infants)
- [x] Start date, start location, end date, end location
- [x] Regions involved (`tripRegions`)
- [x] HRPs / HLHs / OSPs involved (via `trip_day_services`)

### 12.3 Travel Preferences
- [x] Editable preferences — `update_travel_preferences` AJAX
- [x] All 5 fields stored on `trips`: accommodation_comfort, vehicle_comfort, guide_preference, travel_pace, budget_sensitivity
- [x] `other_preferences` free-text field

### 12.4 Financial Snapshot
- [x] Cost breakdown (transport, accommodation, guide, activity, other) — all on `trips`
- [x] Editable margin %: RP, HRP, HCT
- [x] Auto-calc final price (`CostCalculatorService` + `recalculate_trip_cost`)
- [x] Subtotal, GST, final price

### 12.5 Payments from Traveller
- [x] List of received payments (date, amount, mode)
- [x] Total paid + balance due
- [x] Add payment button + pop-up
- [x] Payment history pop-up — `get_traveller_payment_history`
- [x] Edit payment entry — `edit_traveller_payment`

### 12.6 Payments to SP
- [x] Read-only listing of SP payments for this trip — `get_sp_payments`
- [x] SP name, date, amount

### 12.7 Pickup & Drop Details
- [x] Pickup location & time (`trips.pickup_location`, `pickup_time`)
- [x] Drop location & time (`trips.drop_location`, `drop_time`)
- [x] Operations notes (`trips.operations_notes`)
- [x] All wired in form at `trip-info.blade.php:100-116`

### 12.8 Notes
- [x] Free-text trip notes — `trips.general_notes`

---

## 13. Trip Manager — Trip Itinerary Tab (`trip-itinerary.blade.php`)

### 13.A Left — Experience Search & Selection
- [x] Search bar — `search_experiences_for_trip` AJAX
- [x] Search results list
- [x] Add experience to day — `add_experience_to_day`
- [~] Native HTML5 drag-and-drop into timeline (add via dropdown works; left-column → timeline drag UX TBD)

### 13.B Center — Itinerary Timeline
- [x] Day-by-day blocks
- [x] Day reorder — `reorder_trip_days`
- [x] Add day — `add_day_to_trip` / `add_trip_day`
- [x] Remove day — `remove_day_from_trip` / `remove_trip_day`
- [x] Experience reorder within day
- [x] Experience drag between days
- [x] Remove experience from day — `remove_experience_from_day`
- [x] AI on-request only — `request_ai_recalculation`

### 13.C Right — Day-Level Services & Providers
- [x] Service panel per day, aligned with timeline block
- [x] Day locking by experience (services inherited, not editable)
- [x] Editable for non-locked days
- [x] Service entries: SP, type, cost, notes — `add_day_service` / `edit_day_service` / `remove_day_service`
- [x] AI-generated vs manual entries (deterministic backend build, AI additive)
- [x] SP dropdown to replace — `change_day_service_provider`
- [x] Plus / minus buttons to add/remove
- [x] `changeDayServiceProvider` with availability check + auto-rebook + price refresh from `SpPricing`
- [x] **Inclusions inheritance from `experience_days.inclusions`** — meal/accommodation/guide/transport rows generated per day

---

## 14. Experience Management

### 14.1 List Page (`experiences/index.blade.php`)
- [x] Filterable by region, HLH, HRP — `get_experiences_list`
- [x] Columns: ID, name, region, HLH, HRP, duration
- [x] Edit / Disable actions — `disable_experience`
- [x] Disabled experiences kept for record (`is_active` flag)

### 14.2 Create / Edit Page (`experiences/form.blade.php`)
- [x] Identification (HLH, region, type, RP)
- [x] Short + long descriptions + cultural context + unique description
- [x] Cost breakdown (`cost_accommodation`, `cost_logistics`, `cost_guide`, `cost_activities`, `cost_other`)
- [x] Constraints (`group_size_min/max`, `fitness_requirements`, `age_min/max`, `weather_dependency`, `cultural_sensitivities`, `environmental_constraints`, `difficulty_level`)
- [x] Seasonality (`best_seasons`, `available_months`, `restricted_months`, `unavailable_months`, `seasonality_notes`)
- [x] Media (`card_image` + `gallery`)
- [x] Duration type: less_than_day / single_day / multi_day + days/nights/start/end
- [x] Day-wise Details (per-day title, description, inclusions, start/end time)
- [x] Backfill command: `experiences:fill-days [--rebuild]`
- [x] Location & Access (`start_latitude/longitude`, `end_latitude/longitude`, `area`, `trekking_required`, `road_seasonal_closure`, `altitude_max/min`)
- [x] Service Providers inside experience — `osps_involved`, `osp_services`
- [x] Traveller information & preparation (`traveller_bring_list`, `clothing_recommendations`, `health_notes`, `connectivity_notes`, `cultural_etiquette`)
- [x] Operational notes (`operational_risks`, `past_issues`, `backup_options`, `emergency_notes`)
- [x] Single supplement (`single_supplement`)
- [x] Seasonal price variation (`seasonal_price_variation`)
- [x] Save — `save_experience`

## 15. Regenerative Project Management

### 15.1 List Page (`regenerative-projects/index.blade.php`)
- [x] List view — `get_regenerative_projects`
- [x] Filter by region, LA, status
- [x] Columns: RP ID, name, region, LA, type
- [x] Edit / Disable actions — `disable_regenerative_project`

### 15.2 Create / Edit Page (`regenerative-projects/form.blade.php`)
- [x] Identification & governance (`name`, `region_id`, `local_association`, `action_type`)
- [x] Short + detailed description
- [x] Impact unit definition + conversion rules (`impact_unit`, `conversion_rules`)
- [x] Measurement frequency (one-time / periodic / cumulative — `measurement_frequency`)
- [x] Reference budget + cost-per-impact-unit (`reference_budget`, `cost_per_impact_unit`)
- [x] Active / paused periods (`active_periods`, `paused_periods`)
- [x] Operational constraints (`operational_constraints`)
- [x] Media (`main_image` + `gallery`)
- [x] Region linking + fallback for regions without active RP (`fallback_for_regions`)
- [x] Save — `save_regenerative_project`

---

## 16. Cross-cutting Backend

- [x] Trip lifecycle: Lead → Client → Former Client (auto-driven by payment, `LeadService`)
- [x] Trip never deleted (status field only)
- [x] Activity log table for admin actions (`activity_logs`)
- [x] AI conversation log per trip (`AiConversation`)
- [x] OPcache awareness in dev (`herd restart` documented in CLAUDE.md)
- [x] AI fallback chain: Gemini → Groq → Ollama
- [x] Groq retry-after handling on 429
- [x] Chat token budget: only selected experiences sent to AI (`chatWithAi` + `chatWithAiHct` trimmed)
- [x] PDF export — `PdfController::tripPdf` + admin/pdf templates

---

## Open / Phase 2 (intentionally out of MVP scope)

- [ ] Direct AI ↔ Service Provider communication
- [ ] In-platform messaging with travellers
- [ ] Payment gateways for SP payouts
- [ ] Automated invoices / receipts
- [ ] Stronger analytics dashboard
- [ ] Local Associations (LA) as user type
- [ ] National / international scaling (multi-currency, multi-locale)
