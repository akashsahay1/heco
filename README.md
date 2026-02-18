# HECO Portal

Himalayan Ecotourism Collective (HECO) is a regenerative travel platform that connects travellers with authentic, community-driven experiences across the Himalayas. The platform enables travellers to discover experiences, build AI-assisted itineraries, and track their regenerative impact — while giving HCT (HECO Core Team) admins a full dashboard to manage operations.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.4
- **Database:** MySQL 8
- **Frontend:** Bootstrap 5, jQuery 3.7, Leaflet.js (maps)
- **AI:** Google Gemini API (primary), Ollama + Mistral 7B (fallback)
- **PDF:** DomPDF (via barryvdh/laravel-dompdf)
- **Social Auth:** Laravel Socialite (Google OAuth)
- **Dev Server:** Laravel Herd / Valet (multi-domain)

## Architecture

The app runs on **two domains** from a single codebase:

| Domain | Layer | Purpose |
|---|---|---|
| `hecoportal.test` | Traveller Portal | Public homepage, experience discovery, AI chatbot, itinerary builder, traveller dashboard |
| `hecoadmin.test` | HCT Admin Dashboard | Leads, trips, payments, experience/region management, trip manager, control panel |

### Core Layers

1. **Traveller Portal** — Public-facing site where guests and logged-in users browse experiences, chat with the AI assistant, build itineraries, and track regenerative impact.
2. **HCT Dashboard** — Admin panel for the HECO Core Team to manage the entire operation: leads, trips, payments, service providers, experiences, regions, regenerative projects, and more.
3. **Trip Manager** — Detailed trip-level management within the admin dashboard. Plan day-by-day itineraries, assign services, calculate costs, and generate PDF proposals.
4. **Service Provider (SP) Portal** — Dashboard for service providers (homestays, guides, transport operators) to manage their offerings and view assigned trips.

### Key Patterns

- **Single AjaxController** — All AJAX requests POST to `/ajax` with a key-based dispatch pattern (`$request->has('key')`). Portal and admin each have their own index method (`portalIndex`, `adminIndex`).
- **Session-based guest flow** — Guest users can browse, chat with AI, add experiences, and build itineraries without logging in. All data is stored in Laravel session. On login, `syncGuestJourney()` transfers everything to the database.
- **AI abstraction** — `callAi()` helper tries Gemini first, falls back to Ollama. Used for traveller chat, itinerary generation, HCT chat, and itinerary optimization.
- **View isolation** — `views/portal/` and `views/admin/` are completely separate with their own layouts, CSS, and JS.

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── AjaxController.php        # All AJAX handlers (portal + admin)
│   │   ├── HomepageController.php     # Landing, homepage, experience detail
│   │   ├── TravellerController.php    # My itineraries, profile
│   │   ├── HctController.php          # Admin dashboard views
│   │   ├── TripManagerController.php  # Trip detail management
│   │   ├── SpController.php           # Service provider portal
│   │   ├── PdfController.php          # PDF generation
│   │   ├── AuthController.php         # Portal auth (email/password)
│   │   ├── AdminAuthController.php    # Admin auth
│   │   └── SocialAuthController.php   # Google OAuth
│   └── Middleware/
│       ├── HctMiddleware.php          # HCT role check (admin + collaborator)
│       ├── HctAdminMiddleware.php     # HCT admin-only role check
│       └── SpMiddleware.php           # Service provider role check
├── Models/
│   ├── User.php                       # Custom auth (auth_type + user_role enums)
│   ├── Trip.php                       # Core trip entity
│   ├── TripDay.php                    # Day within an itinerary
│   ├── TripDayExperience.php          # Experience assigned to a day
│   ├── TripDayService.php             # Service assigned to a day
│   ├── TripSelectedExperience.php     # Experience added to trip
│   ├── TripRegion.php                 # Region linked to trip
│   ├── Experience.php                 # Bookable experience
│   ├── Region.php                     # Geographic region
│   ├── RegenerativeProject.php        # Impact/sustainability project
│   ├── ServiceProvider.php            # HRP, HLH, OSP entities
│   ├── Lead.php                       # CRM lead tracking
│   ├── AiConversation.php             # Chat history storage
│   ├── AiPrompt.php                   # Configurable AI prompts
│   ├── Currency.php                   # Multi-currency support
│   ├── SpPricing.php                  # Service provider pricing
│   ├── SpPayment.php / SpPaymentEntry.php  # SP payment tracking
│   ├── TravellerPayment.php           # Traveller payment tracking
│   ├── SupportRequest.php             # Support tickets
│   ├── PdfTemplate.php                # PDF template configuration
│   ├── Setting.php                    # System settings (key/value)
│   ├── SystemList.php                 # Dropdown/enum options
│   └── ActivityLog.php                # Audit trail
└── Services/
    ├── GeminiService.php              # Google Gemini API client
    ├── OllamaService.php              # Ollama local LLM client
    ├── PromptBuilderService.php       # Dynamic AI prompt builder
    ├── CostCalculatorService.php      # Trip pricing engine
    ├── ImpactCalculatorService.php    # Regenerative impact metrics
    ├── ItineraryService.php           # Itinerary persistence
    ├── LeadService.php                # Lead management
    └── PdfService.php                 # PDF generation

resources/views/
├── portal/                            # Traveller-facing views
│   ├── layout.blade.php              # Portal master layout
│   ├── landing.blade.php             # Landing page
│   ├── homepage.blade.php            # Main app (discover, journey, impact tabs)
│   ├── experience-detail.blade.php   # Single experience page
│   ├── my-itineraries.blade.php      # Traveller's saved trips
│   ├── profile.blade.php             # User profile
│   ├── auth/                         # Login/register modals
│   ├── pages/                        # Static pages (about, privacy, terms, etc.)
│   └── sp/                           # Service provider views
└── admin/                             # HCT admin views
    ├── layout.blade.php              # Admin master layout
    ├── dashboard.blade.php           # Overview dashboard
    ├── leads.blade.php               # Lead management
    ├── trips.blade.php               # Trip list
    ├── payments.blade.php            # Payment tracking
    ├── experiences/                   # Experience CRUD
    ├── regions/                       # Region management
    ├── currencies/                    # Currency management
    ├── regenerative-projects/         # Impact project CRUD
    ├── trip-manager/                  # Day-by-day trip builder
    └── ...                           # Other admin views

public/css/
├── style.css                          # Global styles + design tokens
├── portal.css                         # Portal-specific styles
└── admin.css                          # Admin-specific styles
```

## User Roles

| Role | Access | Description |
|---|---|---|
| `traveller` | Portal | Browse experiences, build itineraries, track impact |
| `hct_admin` | Admin (full) | Full HCT dashboard + admin-only settings |
| `hct_collaborator` | Admin (limited) | HCT dashboard without admin-only features |
| `hrp` | SP Portal | Himalayan Resource Partner |
| `hlh` | SP Portal | Himalayan Local Host |
| `osp` | SP Portal | Outside Service Provider |

## Requirements

- PHP >= 8.2
- Composer
- MySQL 8.x
- Node.js >= 18 and npm
- Google Gemini API key (free tier: [Google AI Studio](https://aistudio.google.com/apikey))
- (Optional) Ollama with Mistral 7B for local AI fallback

## Installation

### 1. Clone and install dependencies

```bash
git clone <repository-url> hecoapp
cd hecoapp
composer install
npm install
```

### 2. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your configuration:

```env
APP_NAME="HECO Portal"
APP_URL=http://hecoportal.test
ADMIN_DOMAIN=hecoadmin.test
PORTAL_DOMAIN=hecoportal.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hecoapp
DB_USERNAME=root
DB_PASSWORD=your_password

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

GEMINI_API_KEY=your_gemini_api_key
GEMINI_MODEL=gemini-2.5-flash
GEMINI_TIMEOUT=60

# Optional: Ollama fallback
OLLAMA_HOST=http://localhost:11434
OLLAMA_MODEL=mistral
```

### 3. Database setup

```bash
php artisan migrate
php artisan db:seed
```

The seeders create:
- Admin user: `admin@hecoapp.com` / `password` (hct_admin)
- Collaborator: `collaborator@hecoapp.com` / `password` (hct_collaborator)
- Traveller: `traveller@hecoapp.com` / `password` (traveller)
- Regions, currencies, system lists, AI prompts, and settings

### 4. Storage link

```bash
php artisan storage:link
```

### 5. Domain configuration

The app requires two domains. With **Laravel Herd** or **Valet**:

```bash
# The domains are auto-configured by Herd/Valet
# Just ensure both hecoportal.test and hecoadmin.test resolve
```

With other setups, add to `/etc/hosts`:

```
127.0.0.1  hecoportal.test
127.0.0.1  hecoadmin.test
```

And configure your web server (Nginx/Apache) to serve both domains from the `public/` directory.

### 6. Build frontend assets

```bash
npm run build
```

### 7. Start the server

With Herd/Valet the app is available automatically. Otherwise:

```bash
php artisan serve
```

## Development

```bash
# Run all dev services (server, queue, logs, vite)
composer dev

# Or individually
php artisan serve
npm run dev
```

## AI Configuration

### Gemini (Recommended)

1. Get a free API key from [Google AI Studio](https://aistudio.google.com/apikey)
2. Set `GEMINI_API_KEY` in `.env`
3. The app uses `gemini-2.5-flash` by default (fast, free tier)

### Ollama (Optional fallback)

1. Install [Ollama](https://ollama.ai)
2. Pull the Mistral model: `ollama pull mistral`
3. Set `OLLAMA_HOST` in `.env`

The `callAi()` helper automatically tries Gemini first. If unavailable or failing, it falls back to Ollama.

## Key Features

- **AI Chatbot** — Inline chat on the Discover tab. Collects trip details conversationally and recommends experiences with card highlighting.
- **Session-based Guest Flow** — Full functionality without login. Experiences, preferences, itineraries, and chat history stored in Laravel session. Syncs to DB on login.
- **AI Itinerary Generation** — Auto-generates day-by-day itineraries when experiences are added/removed. Includes per-day activities, descriptions, and full pricing breakdown.
- **Multi-currency** — Prices displayed in user's selected currency with real-time conversion.
- **Interactive Map** — Leaflet.js map with experience markers, click-to-filter by region.
- **Trip Manager** — Admin tool for day-by-day trip planning, service assignment, cost calculation, and PDF proposal generation.
- **Regenerative Impact** — Track and display environmental/social impact metrics per trip.
- **Lead Management** — Auto-creates leads from trips, tracks through pipeline stages.
- **PDF Proposals** — Generate professional trip proposals via DomPDF.
- **Social Auth** — Google OAuth login via Laravel Socialite.
