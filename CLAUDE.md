# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Environment

- **Laravel Herd** on Windows 11 with two domains: `hecoportal.test` (portal) and `hecoadmin.test` (admin)
- PHP path: `C:\Users\akash\.config\herd\bin\php.bat`
- Herd CLI: `C:\Users\akash\.config\herd\bin\herd.bat`
- MySQL 8 database (`hecoDB`), sessions/queue/cache all database-driven

### OPcache Warning

Herd's PHP-FPM uses OPcache. CLI commands like `php artisan optimize:clear` do NOT clear the web server's cache. After changing routes, config, or PHP files that aren't reflected in the browser, run:
```bash
"C:\Users\akash\.config\herd\bin\herd.bat" restart
```

## Common Commands

```bash
# Full setup (dependencies, env, key, migrate, npm, build)
composer setup

# Run all dev services concurrently (PHP server, queue, logs, Vite)
composer dev

# Run tests
composer test                          # all tests (clears config first)
php artisan test --filter=TestName     # single test

# Frontend
npm run dev                            # Vite dev server with HMR
npm run build                          # production build

# Database
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed       # reset everything

# Cache (use after config/route changes if not restarting Herd)
php artisan optimize:clear
```

## Architecture Overview

This is a **Laravel 12** regenerative travel platform. A single codebase serves two domains via `Route::domain()` groups in `routes/web.php`:
- **Portal** (`hecoportal.test`) — traveller-facing: browse experiences, AI chat, itinerary builder
- **Admin** (`hecoadmin.test`) — HCT dashboard: leads, trips, payments, experience/region CRUD, trip manager

### AJAX Dispatch Pattern

Nearly all user interactions go through a **single AJAX endpoint** (`POST /ajax`). `AjaxController` dispatches by checking `$request->has('key_name')`:
- `portalIndex()` — handles portal AJAX keys
- `adminIndex()` — handles admin AJAX keys

The frontend uses `ajaxPost()` helper (defined in `portal/layout.blade.php`) for consistent requests. A global jQuery error handler catches failures — suppress it with `skipGlobalError: true` in jQuery.ajax settings.

### AI System

Three AI providers with automatic fallback chain (`callAi()` in AjaxController):
1. **Gemini** (primary) — `gemini-2.5-flash` via `GeminiService`
2. **Groq** (secondary) — `llama-3.3-70b-versatile` via `GroqService`
3. **Ollama** (local fallback) — `mistral` via `OllamaService`

AI prompts are stored in the `ai_prompts` DB table with `{{placeholder}}` template variables, built by `PromptBuilderService`. `ItineraryService` parses AI JSON responses into Trip/TripDay/TripDayExperience/TripDayService records.

AjaxController includes `repairTruncatedJson()` to handle incomplete AI JSON responses.

### Session-Based Guest Flow

Guest users get full functionality via Laravel session (`session('guest_trip')`). On login, `syncGuestJourney()` transfers session data to the database.

### Cost Calculation

`CostCalculatorService` applies comfort-based multipliers to base experience costs. Multiplier categories: accommodation comfort (Cat A–E), vehicle type, guide preference. Dropdown option values must match DB strings exactly (e.g., `'Cat C - Standard'`, not `'standard'`).

### User Roles

`user_role` enum on User model: `hct_admin`, `hct_collaborator`, `traveller`, `hrp`, `hlh`, `osp`. Helper methods: `isHctAdmin()`, `isHct()`, `isTraveller()`, `isServiceProvider()`.

### Database Enums

- **service_type:** `'accommodation'`, `'transport'`, `'guide'`, `'activity'`, `'meal'`, `'other'`
- **trip status:** `'not_confirmed'`, `'confirmed'`, `'running'`, `'completed'`, `'cancelled'`
- **trip stage:** `'open'` (editable), `'closed'` (locked)

## Key Code Locations

| Area | Path |
|---|---|
| All AJAX handlers | `app/Http/Controllers/AjaxController.php` |
| Route definitions | `routes/web.php` |
| AI services | `app/Services/{Gemini,Groq,Ollama}Service.php` |
| Prompt builder | `app/Services/PromptBuilderService.php` |
| Itinerary builder | `app/Services/ItineraryService.php` |
| Pricing engine | `app/Services/CostCalculatorService.php` |
| Portal views | `resources/views/portal/` |
| Admin views | `resources/views/admin/` |
| Portal layout + ajaxPost() | `resources/views/portal/layout.blade.php` |
| Global styles | `public/css/style.css` |

## Coding Conventions

- Use `?:` (not `??`) when checking for falsy values like empty strings in defaults
- AJAX keys are simple snake_case strings checked via `$request->has()`
- Portal and admin views are fully isolated — separate layouts, CSS, and JS
- Inline `<script>` blocks in Blade views are common (especially `homepage.blade.php`)
- Database-driven config: settings, system lists, AI prompts, and activity logs are in DB tables for runtime management
