# HECO Frontend Portal — Development Tasks

Source: MVP.txt sections 5–11, 17–19
Domain: `hecoportal.test`
Target users: Travellers (logged-in or guest), Service Providers (self-service)

**Verified against codebase 2026-04-29.** Each task was checked by inspecting `routes/web.php`, the AJAX dispatcher in `AjaxController.php`, all portal views under `resources/views/portal/`, controllers, models, and migration columns.

Legend: `[x]` complete · `[~]` partial · `[ ]` not started

---

## 1. Authentication

- [x] Sign-up / Log-in pop-up modal (in `portal/layout.blade.php`)
- [x] Sign-up fields: full_name, email, phone_1, phone_2, password
- [x] Email + password login — `userlogin` / `usersignup` AJAX
- [x] Google OAuth (`/auth/google/redirect` → `SocialAuthController`)
- [x] Facebook OAuth (`/auth/facebook/redirect` → `SocialAuthController`)
- [x] Forgot password / reset flow (`forgot-password.blade.php`, `reset-password.blade.php`)
- [x] Trip persistence + linking on login
- [x] Auto-redirect to landing/home after login
- [x] Auth modal — DB-driven dropdowns and consistent layout
- [ ] Newsletter opt-in checkbox at sign-up (`users.newsletter_optin` column exists; UI not in register form)
- [ ] "Notify me when Heco is fully fledged" opt-in checkbox (`users.portal_notify_optin` column exists; UI not in register form)

## 2. Landing Page (Intent Detection)

- [x] First-entry landing page (`HomepageController::landing` → `portal/landing.blade.php`)
- [x] Sections: hero, about, how-it-works, regions, categories, partner CTA
- [~] Resume previous trip invitation (logic exists via `/my-itineraries` and trip resume; landing page itself just CTAs to `/home`)
- [ ] Soft signals collection — region, travel style, timing, involvement (`set_landing_preferences` AJAX exists in dispatcher but no form on landing page)
- [x] Landing → `/home` flow

## 3. Homepage (AI Workspace)

- [x] Single continuous interaction environment (no disruptive page changes)
- [x] AI chatbot always visible
- [x] Three tabs: Discover / Your Journey / Your Impact
- [x] AI memory of preferences (group size, dates, accom, transport, budget, pace)
- [x] AI autofill of Travel Preferences in Your Journey
- [x] Pricing & feasibility update on AI memory changes
- [x] Manual override of any AI-set value
- [x] "Request support" button — `request_support` AJAX
- [x] Login pop-up triggered if guest clicks "Request support"

## 4. Discover Tab

### 4.1 Map (Leaflet)
- [x] Highlight only operational regions
- [x] Non-operational regions muted
- [x] Experience markers on map (`markerMap` + Leaflet markers, lines 1305-1319)
- [x] Map markers built from `get_regions_for_map` / `get_experiences_for_discover`
- [ ] Region name on map → opens `himalayanecotourism.com` region landing in new tab (no such link found in `homepage.blade.php`)

### 4.2 Experience Cards
- [x] Curated, predefined experiences (loaded via `get_experiences_for_discover`)
- [x] Dynamic ordering (sort_order + filters)
- [x] Hover card → highlights map marker (mouseenter handler at line 1317-1320)
- [x] Hover map marker → highlights card
- [x] Click card name → opens experience detail in new tab (`/experience/{slug}` with `target="_blank"` at line 1604)

### 4.3 Filters
- [x] Continent / Country / Region cascading filters
- [x] Experience type (8 options)
- [x] Difficulty (5 levels)
- [x] Month (12 months)
- [x] Reset / clear filters button
- [x] Filters affect both map + list
- [ ] Season filter (only month — no season filter)
- [ ] Duration filter
- [ ] Impact-related tags filter

### 4.4 Interaction with Journey
- [x] ❤️ Mark as preferred (non-binding) — `prefer_experience`
- [x] ➕ Add to journey (binding) — `add_experience_to_trip`
- [ ] Drag experience cards directly from Discover into Your Journey (cards are not `draggable`; only journey-list reorder uses HTML5 drag)

## 5. Your Journey Tab

### 5.1 Left Column — Selected Experiences
- [x] Trip ID at top
- [x] Auto-save trip on every change
- [x] Trip name input (`#tripName`) with debounced AI sync (`homepage.blade.php:2516`)
- [x] `save_trip_name` AJAX endpoint
- [x] Per-experience card: name, summary, remove button
- [ ] Click selected experience card → detail pop-up (per MVP — currently only remove button; no click-to-open behaviour)
- [x] Reorder via timeline drag (`draggable="true"` on `.journey-exp-item`, `dragstart/dragover/dragend` handlers, lines 2374-2395)
- [x] `reorder_experiences` AJAX

### 5.2 Center Column — Timeline Itinerary
- [x] Day blocks (expandable / collapsible)
- [x] Activities, transfers, accommodation, key timings displayed
- [x] Multi-day experiences visually grouped + colored differently
- [x] Single-day-without-accommodation: AI auto-suggests night-before + night-after stays + transport
- [x] Partial-day experiences: AI suggests start time based on travel + other activities
- [x] Multiple short experiences in same day (smaller blocks)
- [x] AI gap-filling — auto transport, accommodation, meals, guides
- [x] AI clarifying questions when uncertain
- [x] Manual day add/remove — `add_day_to_trip` / `remove_day_from_trip`
- [x] Reorder/remove/add experiences on timeline
- [x] AI reacts to manual changes — rearrange services
- [x] Inclusion icons per day (Accommodation / Meals / Transport / Guide)
- [x] Color: green = included, grey = not included
- [x] Day data populated from `experience_days` (title, description, inclusions, times)
- [x] Backend `generateItinerary()` builds full day details deterministically (AI is additive, never required)
- [x] Get timeline — `get_trip_timeline`

### 5.3 Right Column — Group Details, Travel Preferences, Pricing
- [x] Group details: adults / children / infants
- [x] Triggers feasibility check + price recalc on change — `update_group_details`
- [x] Travel preferences: 5 dropdowns (all DB-driven from `system_lists`):
  - [x] Accommodation Comfort (filtered by region SPs' `accommodation_categories`)
  - [x] Vehicle Comfort (filtered by region SPs' `vehicle_types`)
  - [x] Guide Preference (filtered by region SPs' `guide_types`; "No Guide" always available)
  - [x] Travel Pace (not filtered — traveller signal only)
  - [x] Budget Sensitivity (not filtered — traveller signal only)
- [x] Preferences influence AI choices + override defaults
- [x] Preferences stored on Trip model
- [x] Save preferences — `update_travel_preferences` (all 5 keys whitelisted)
- [x] Pricing: dynamic update reflecting experiences, services, group, preferences
- [x] Transparent breakdown (Transport / Accommodation / Guide / Activity / Other / Subtotal / RP / GST / Final)
- [x] Get pricing — `get_trip_pricing`
- [x] Pay Now (Razorpay test) — `create_razorpay_order` + `verify_razorpay_payment`
- [x] Razorpay failure handling — friendly messages + server-side log (`log_razorpay_failure`)
- [x] Post-payment thank-you page with View Itinerary button (`/trip/{id}/thank-you`)
- [ ] Phase 2: "Book your trip" instant-confirm button (intentionally out of MVP)

## 6. Your Impact Tab

- [x] One RP card per region in trip (`get_trip_impact`)
- [x] Multiple RP cards if multi-region
- [x] Impact calculation: % of trip value → physical metric (trees, area, carbon, etc.)
- [x] Region without active RP → fallback to linked region's RP (`fallback_for_regions` column)
- [x] Click RP card → pop-up with project description + impact type
- [x] MVP scope: current trip only (Phase 2: full impact dashboard with history)

## 7. AI Chatbot

- [x] Always present, non-intrusive
- [x] Conversational guide, memory keeper, orchestrator
- [x] Three modes: ask AI, AI clarifies, ignore-and-explore
- [x] DB-driven prompt templates (`ai_prompts` table)
- [x] AI fallback chain: Gemini → Groq → Ollama
- [x] Friendly fallback message when all providers down
- [x] Token-budget-aware: only selected experiences sent (`chatWithAi`)
- [x] Tag parsing: [SET_FILTERS], [TRIP_DETAILS], [ADD_TO_TRIP], [REMOVE_FROM_TRIP], [RECOMMEND_IDS]
- [x] Trip details extraction with whitelisted keys including `travel_pace` + `budget_sensitivity`
- [x] Get chat history — `get_chat_history`
- [x] Auto-pause AI behaviour during HCT manual control

## 8. Your Itineraries (Saved Trips)

- [x] `/my-itineraries` page (auth required)
- [x] List of all trips by current traveller — `get_user_trips`
- [x] Columns: Trip ID, name, last opened
- [x] Open trip → resume in `/home?trip_id=X` (`/my-itineraries/{trip_id}` route)
- [x] Erase trip — `erase_trip` AJAX with confirm dialog (`my-itineraries.blade.php:130-152`)
- [x] Multiple trips supported simultaneously
- [x] Status filter / overview

## 9. Trip Detail Page

- [x] `/trip/{id}` page — `TravellerController::tripDetail`
- [x] Read-only summary view
- [x] Loaded with regions + selected experiences
- [x] Linked from Thank-You and My Itineraries
- [x] Get info — `get_trip_info` AJAX

## 10. Experience Detail Page

- [x] `/experience/{slug}` page (`HomepageController::experienceDetail`)
- [x] Long description, region, HLH, RP
- [x] Reviews + average rating — `get_reviews`, `submit_review`, `check_review_eligibility`
- [x] Card image + gallery
- [x] Add to trip CTA
- [x] Get experience detail — `get_experience_detail`

## 11. Profile

- [x] `/profile` page (`TravellerController::profile`)
- [x] Edit name / email / phone / photo / address — `update_profile`
- [x] Change password — `change_password`
- [x] Communication opt-ins schema (`newsletter_optin`, `portal_notify_optin`)
- [~] Communication opt-in toggles in profile UI (DB columns ready; verify UI checkboxes)
- [x] Profile data linked to all trips, payments, impact

## 12. Service Provider Application Page

- [x] Public page accessible from "Join the HECO family" (`/join` route)
- [x] Account creation required before applying
- [x] Submission stores in `service_providers` with status=pending — `submit_sp_application`
- [x] Confirmation message on submit
- [x] Goes into Provider Applications queue in HCT

### Application form fields (`portal/sp/application.blade.php`)
- [x] Provider type: HRP / HLH / OSP (radio buttons)
- [x] Name (org / individual)
- [x] Contact person
- [x] Email
- [x] Phone 1 + Phone 2
- [x] Region of operation
- [x] Address
- [x] Description (free text — background, services offered)
- [ ] Service types selector (form field not present; HCT fills after approval per MVP)
- [ ] Accommodation categories selector (HCT fills after approval)
- [ ] Vehicle types selector (HCT fills after approval)
- [ ] Activity types selector (HCT fills after approval)
- [ ] Guide types selector (HCT fills after approval)
- [ ] Bank details fields (HCT collects via outside-platform per MVP)
- [ ] UPI field (HCT collects)
- [ ] Experience background field (separate from generic description)
- [ ] Motivation to work with HECO field

## 13. SP Self-Service Dashboard

- [x] `/sp/dashboard` (auth + `sp` middleware)
- [x] Identity, region, status, bank, capabilities display
- [x] Last updated by audit banner
- [x] Edit Profile button → `/sp/profile/edit`

### SP Self-Edit (`portal/sp/edit-profile.blade.php`)
- [x] Edit identity (name, contact, email, phones, address)
- [x] Edit bank details (name, IFSC, account name+number, UPI)
- [x] Edit capabilities (services, accommodation, vehicle, **guide_types**, activity)
- [x] Status / region / approval **NOT** editable (admin-only) — backend `updateSpProfile` whitelist
- [x] Audit fields: `last_updated_by` + `last_updated_by_role = 'provider'` on save

### SP Calendar (availability)
- [x] Month-view calendar
- [x] Block / unblock dates (`sp_block_dates` / `sp_unblock_dates`)
- [x] Booked dates from trip assignments via `SpAvailabilityService`
- [x] iCal import (`sp_save_ical_url` / `sp_sync_ical_now`)
- [x] Get calendar — `get_sp_calendar`

## 14. Currency / Locale

- [x] Currency selector modal (in `portal/layout.blade.php`)
- [x] DB-driven currencies list with flags — `get_currencies_list`
- [x] Currency stored per session

## 15. Footer & Static Pages

- [x] Footer with brand, navigation, contact (in `portal/layout.blade.php`)
- [x] About (`/about`)
- [x] Contact (`/contact`)
- [x] Help (`/help`)
- [x] Careers (`/careers`)
- [x] Guidelines (`/guidelines`)
- [x] Privacy (`/privacy-policy`)
- [x] Terms (`/terms`)
- [x] Wishlist page (`/wishlist`)

---

## 16. SEO & Integration with HimalayanEcotourism.com

- [ ] Map region names link to `himalayanEcotourism.com` region landing pages (no `himalayanecotourism` URL anywhere in portal views)
- [ ] Canonical URLs / redirects from old site to new (SEO-expert work, out of dev scope)
- [ ] Progressive content migration plan
- [x] HECO portal homepage map structure ready to attach external links (Leaflet markers in place)

## 17. Cross-cutting / Infrastructure

- [x] Database-driven configuration (`system_lists`, `settings`, `ai_prompts`, `activity_logs`)
- [x] AJAX-key dispatcher pattern via single `/ajax` POST endpoint (over 80 keys wired)
- [x] Guest trip session (full functionality without login)
- [x] `syncGuestJourney` on login transfers session → DB
- [x] Razorpay test mode wired (key checks pass)
- [x] PHP / Laravel 12 / MySQL 8 / Vite (Herd)
- [x] CSRF token endpoint (`/csrf-token`)
- [x] OPcache reset endpoint for dev (`/opcache-reset`)

---

## Phase 2 (Out of MVP scope per MVP.txt)

- [ ] In-platform messaging traveller ↔ HCT
- [ ] Direct AI ↔ Service Provider communication
- [ ] Direct booking button (instant confirm without HCT)
- [ ] Cumulative Impact Dashboard (history of all trips)
- [ ] Multi-language support
- [ ] Mobile app
- [ ] Local Association (LA) user type

---

## Summary of changes from previous version

The codebase is **substantially more complete** than the first draft of this document indicated. After verification:

- 🟢 **Items promoted from `[~]` to `[x]`:** Pickup & Drop UI, all Experience editor fields (Location & Access, Constraints, Seasonality, Traveller info, Operational notes, OSP involvement), all Regenerative Project fields (impact unit, conversion rules, measurement frequency, budget, seasonality), HCT user management UI, Trips date range filter, drag-and-drop in Selected Experiences, save trip name, erase trip, hover-card-highlights-map.
- 🟡 **Items still genuinely `[~]` (partial):** Communication opt-in toggles UI in profile, "Resume previous trip" widget on landing page (logic exists, dedicated landing-page UI doesn't), drag-from-Discover-into-Journey, "Request clarification" path for SP applications, AI "missing data" dedicated worklist UI.
- 🔴 **Items now correctly `[ ]` (not started):** Newsletter opt-in checkbox in register form, region link to himalayanecotourism.com, click-selected-experience-card-pop-up, season/duration/impact-tag filters, AI prompts admin UI, edit-stage-from-lead-history-pop-up, the SP application form's optional fields (background, motivation, bank, UPI, capability selectors).
