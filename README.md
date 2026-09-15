# Foogra

A restaurant directory and table-booking platform: **Laravel 13 REST API** + **React 19 / Vite / TypeScript** SPA, built on the Foogra ThemeForest template.

Three public pages are ported from the mockups, backed by a full CRUD API with role-based access control:

| # | Page | Ported from | What it does |
|---|------|-------------|--------------|
| 1 | **Home** — `/` | `index-13.html` (Parallax Video Fullscreen) | Fullscreen hero video + search, popular categories, featured restaurants, best deals |
| 2 | **Listing** — `/restaurants` | `grid-listing-filterscol-full-width.html` | Search, category / rating / price / radius filters with live facet counts, sorting, pagination |
| 3 | **Detail** — `/restaurants/{slug}` | `detail-restaurant.html` | Gallery, menu, opening hours, reviews with voting and owner replies, live availability booking |

Plus a **dashboard** at `/admin` for administrators and restaurant owners.

---

## Quick start

Prerequisites: **PHP 8.3+**, **Composer 2**, **Node 20+**, **Docker**.

```bash
# 1. Databases and mail catcher
docker compose up -d

# 2. Backend
cd backend
composer install
cp .env.example .env          # already points at the Docker services
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve             # http://localhost:8000

# 3. Frontend (in a second terminal)
cd frontend
npm install
bash copy-assets.sh           # copies the Foogra CSS / images / video into public/
npm run dev                   # http://localhost:5173
```

| Service | URL |
|---------|-----|
| React app | <http://localhost:5173> |
| API | <http://localhost:8000/api/v1> |
| Interactive API docs | <http://localhost:8000/docs/api> |
| Mailpit (booking + reset emails) | <http://localhost:8025> |

### Demo accounts

Password for all of them is `password`.

| Role | Email | Can do |
|------|-------|--------|
| Admin | `admin@foogra.test` | Everything, across every restaurant |
| Owner | `owner@foogra.test` | Manage their own restaurants, menus, bookings, reviews |
| Owner | `owner2@foogra.test` | As above, a second tenant to prove isolation |
| Customer | `customer@foogra.test` | Book tables, write reviews, keep a wishlist |

The sign-in dialog has one-tap buttons for each.

---

## Architecture

```
hackaton-project/
├── docker-compose.yml     MySQL 8.4 (app + test) and Mailpit
├── backend/               Laravel 13 API
│   ├── app/
│   │   ├── Enums/         UserRole, RestaurantStatus, ReviewStatus, BookingStatus
│   │   ├── Models/        12 Eloquent models with scopes and derived-value hooks
│   │   ├── Policies/      Per-record authorization (admin / owner / customer)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/   Public, authenticated, and Admin controllers
│   │   │   ├── Requests/             One FormRequest per write path
│   │   │   ├── Resources/            JSON shaping
│   │   │   └── Middleware/           ForceJsonResponse, EnsureUserHasRole
│   │   └── Services/      RestaurantSearch (filters + facets), ImageStorage
│   ├── database/          Migrations, factories, seeders
│   ├── scripts/           smoke-test.sh — 77 end-to-end HTTP checks
│   └── tests/Feature/     136 tests
└── frontend/              React 19 + Vite + TypeScript
    ├── public/            Foogra template assets (copied, not committed)
    └── src/
        ├── lib/           api.ts (axios + interceptors), services.ts, types.ts
        ├── context/       AuthContext, UiContext
        ├── components/    layout, ui, detail, admin
        ├── pages/         Public pages + pages/admin/ dashboard
        └── styles/        foogra-react.css — the bridge to the theme
```

### Data model

```
User ──< Restaurant ──< MenuSection ──< Dish
 │           ├──< RestaurantImage
 │           ├──< OpeningHour        (day × lunch/dinner)
 │           ├──< Review ──< ReviewVote
 │           │        └──1 ReviewReply
 │           ├──< Booking
 │           └──< Wishlist
 └──< Review / Booking / Wishlist
Category ──< Restaurant
```

Notable choices:

- **Cached aggregates.** `restaurants.rating_avg`, `reviews_count` and `bookings_count` are denormalised and kept in sync by model events. Recomputing `AVG()`/`COUNT()` over reviews on every listing request is the easiest way to make this page slow.
- **Derived, not trusted.** A review's `rating_overall` is always computed from its four sub-scores in a `saving` hook, so a client cannot post a headline score that does not match its parts.
- **Moderation by default.** New and edited reviews go to `pending` and do not affect the public score until approved.
- **Opening hours drive everything.** The "Now Open" badge, the `open_now` filter, the bookable time slots, and booking validation all read the same timetable. A sitting that runs past midnight is credited to the day it *started*, so at 00:30 on Saturday it is Friday's 18:00–01:00 row that is still serving — `OpeningHour::covers()` owns that attribution, and callers hand it the whole timetable rather than pre-filtering to one weekday. `Restaurant::scopeOpenAt` mirrors the same rule in SQL, so the filter composes with pagination and facet counts instead of thinning an already-paginated page.
- **Soft deletes** on every user-facing entity, with restore endpoints in the dashboard.

### Authorization

| | Admin | Owner | Customer | Guest |
|---|---|---|---|---|
| Browse, search, view details | ✓ | ✓ | ✓ | ✓ |
| Book a table | ✓ | ✓ | ✓ | ✓ (with email) |
| Review, vote, wishlist | ✓ | ✓ | ✓ | — |
| Manage **own** restaurants, menus, bookings, reviews | ✓ | ✓ | — | — |
| Manage **any** restaurant | ✓ | — | — | — |
| Feature on the homepage, reassign owners | ✓ | — | — | — |
| Categories and users | ✓ | read-only | — | — |

The `role:` middleware is the coarse gate; per-record ownership is enforced by Policies, so an owner hitting another owner's record gets a 403 even on a shared endpoint.

---

## The API

`GET /api/v1/health` · full interactive reference at `/docs/api` (55 paths, 80 operations).

Every list returns `{ data, meta: { current_page, last_page, total } }`.
Every error returns `{ message, errors?: { field: string[] } }` — one shape, whatever went wrong.

Restaurants and categories are addressed by **slug**, bookings by **reference** (`FG-8K3QP2`).

<details>
<summary><strong>Endpoint summary</strong></summary>

**Auth** — `POST /auth/register` · `POST /auth/login` · `POST /auth/logout` · `GET /auth/me` · `PATCH /auth/profile` · `PUT /auth/password` · `POST /auth/forgot-password` · `POST /auth/reset-password`

**Catalogue** — `GET /categories` · `GET /restaurants` · `/restaurants/featured` · `/restaurants/deals` · `/restaurants/suggestions` · `/restaurants/{slug}` · `/{slug}/menu` · `/{slug}/availability` · `/{slug}/reviews`

**Diner** — `POST /restaurants/{slug}/reviews` · `PATCH|DELETE /reviews/{id}` · `POST|DELETE /reviews/{id}/vote` · `POST|DELETE /reviews/{id}/reply` · `GET /reviews/mine` · `POST /bookings` · `GET /bookings` · `GET /bookings/{ref}` · `POST /bookings/{ref}/cancel` · `GET|POST /wishlist` · `POST /wishlist/{slug}/toggle`

**Dashboard** (`/admin`, role `admin` or `owner`) — `GET /stats` · restaurants CRUD + `restore` / `force` / `images` / `opening-hours` · `menu-sections` and `dishes` CRUD + `reorder` / `availability` · `GET /reviews` + `PATCH /reviews/{id}/status` · bookings + `transition` · categories · users (admin only)

</details>

### Listing query parameters

```
GET /api/v1/restaurants
  ?q=sushi                       full-text over name, description, address, city
  &location=Shoreditch           address / city / postcode match
  &categories[]=pizza-italian    repeatable, or comma-separated
  &min_rating=9                  9 / 8 / 7 / 6 — the sidebar's rating bands
  &min_price=0&max_price=50
  &lat=51.5074&lng=-0.1278&radius=10    haversine radius in km (all three required)
  &open_now=1&has_discount=1&featured=1
  &sort=popularity|rating|date|price|price-desc|name|distance
  &page=2&per_page=12
```

The response carries a `facets` block with per-category, per-rating-band and per-price-band counts. Each facet is counted against the *other* active filters but not against itself, so ticking "Pizza" does not collapse every other category to zero — computed in two queries via conditional aggregation. `open_now` is the one filter left out of every facet count: it filters on the clock rather than on the catalogue, and folding it in would make the sidebar numbers drift minute to minute while the user reads them.

### Rate limits

10 requests/minute per IP on the auth endpoints (plus a per-email+IP counter inside the login controller, so a shared office IP cannot lock out one person's account by attacking another's); 120/minute on browsing.

---

## Testing

```bash
cd backend
php artisan test                  # 136 feature tests, 477 assertions
bash scripts/smoke-test.sh        # 77 live HTTP checks against a running server
```

The suite runs against the **second** MySQL container (port 3308), so it never truncates the demo data the app is pointed at. The schema uses MySQL-only features — `FULLTEXT` indexes, `FIELD()` ordering, the haversine expression — so SQLite is not a substitute.

Coverage is behavioural rather than incidental. Among the things asserted:

- A customer cannot create a restaurant; an owner cannot touch another owner's; only an admin can feature one or reassign it.
- A review's overall score is derived, never trusted; approving one moves the restaurant's cached rating, rejecting it moves it back.
- An owner cannot review their own restaurant, and nobody can vote on their own review.
- A booking outside opening hours, on a closed day, in the past, or at a draft restaurant is refused — but a cancelled booking frees its slot for re-booking.
- A guest needs the matching email to read a booking by reference.
- A dish cannot be moved to another restaurant's menu section.
- A category still in use cannot be deleted; an owner with restaurants cannot be deleted.
- Other diners never see a reviewer's email address.

`scripts/smoke-test.sh` drives the same surface over real HTTP, including the full owner CRUD lifecycle. Note it uses 7 of the 10 auth requests allowed per minute, so leave a minute between runs or the second one will (correctly) get throttled.

---

## Notes on the port

The template is jQuery-driven; this is a React SPA. The original stylesheets, icon fonts and imagery are used **verbatim** — components emit the same class names, so `style.css`, `home.css`, `listing.css` and `detail-page.css` are unmodified. The behaviour those plugins provided was rebuilt:

| Template used | Replaced with |
|---|---|
| jarallax video background | A real `<video>` behind the same `.hero_single.fullscreen.video_bg` wrapper |
| Owl Carousel | `Carousel.tsx` (Embla) styled to match |
| Magnific Popup gallery | `Lightbox.tsx` with keyboard navigation |
| jQuery datepicker + dropdowns | Native `<input type="date">` and React state, driven by real availability |
| `sticky_sidebar.js` | CSS `position: sticky` |
| Bootstrap collapse JS | React state toggling the same `.show` class |

Two theme details worth knowing if you edit the markup:

- The theme ships **two header variants**. `.header` is `position: fixed` and transparent (home page, over the hero); `.header_in` is `position: relative` and white (every inner page). Using the wrong one hides the page title behind the header.
- `#datepicker_field` is `display: none` in `detail-page.css` — in the template it was a hidden proxy for the jQuery calendar. Do not reuse that id for a real input.

One asset is deliberately not the template's: `img/logo.svg` ships as a grey "IMAGE PLACEHOLDER" graphic, so the header uses an inline SVG wordmark (`components/layout/Logo.tsx`) that recolours with the header state instead.

The restaurant photographs *are* the template's own placeholders — grey 460×310 and 1400×930 panels. Swap `frontend/public/img/location_*.jpg`, `location_list_*.jpg` and `restaurant_detail_hero.jpg` for real photography before going live, or upload images per restaurant through the dashboard, which writes to the `public` disk and overrides them.

---

## Configuration

`backend/.env` is preconfigured for the Docker services:

| Variable | Default | Notes |
|---|---|---|
| `DB_PORT` | `3307` | App database container |
| `DB_TEST_PORT` | `3308` | Test database container |
| `MAIL_PORT` | `1025` | Mailpit SMTP; web UI on 8025 |
| `FRONTEND_URL` | `http://localhost:5173` | CORS, Sanctum, and password-reset links |

The frontend proxies `/api` and `/storage` to `localhost:8000` in development, so there is no CORS preflight while developing. For a deployed build set `VITE_API_URL` to the API's origin.

---

## DevOps: containerized multi-service deployment

The root `Dockerfile` bakes the SPA into Laravel's `public/` for a single-container
Render deploy — see the comment at the top of that file. Alongside it, `docker/`
also has a **split** setup: three independently built and deployed services
(`web`, `api`, `db`), used by `docker-compose.yml`'s `api`/`web` services, by
`.github/workflows/ci-cd.yml`, and by `k8s/`.

```
docker/
├── api.Dockerfile          backend-only image (php-fpm + nginx, no SPA)
├── api/nginx.conf          API routes + PHP only, no static/SPA fallback
├── api/entrypoint.sh       key check, caches, migrations — no PORT templating
├── frontend.Dockerfile     SPA build → its own nginx image
├── web/nginx.conf.template SPA + reverse proxy to the api service
├── php/, supervisord.conf  shared with the combined Render image
└── entrypoint.sh, nginx.conf.template   (combined image only, unchanged)
```

### Run it locally with Docker Compose

```bash
./scripts/gen-secrets.sh        # writes ./.env with a real APP_KEY
docker compose up -d --build mysql api web
docker compose ps
```

| Service | URL |
|---|---|
| App (React SPA, proxies `/api` server-side) | <http://localhost:8081> |
| API directly | <http://localhost:8080/api/v1/health> |

`mysql_test` and `mailpit` from the original compose file are unaffected —
they still back local `php artisan serve` / `npm run dev` development.

### CI/CD

`.github/workflows/ci-cd.yml` builds and pushes `foogra-api` and `foogra-web`
to Docker Hub on every push to `main` (tags: `latest`, `sha-<short>`, branch
name). Needs repo secrets `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN`. A second,
optional `deploy` job rolls the new images out to a Kubernetes cluster with
`kubectl apply -k k8s/`; it no-ops automatically unless `KUBE_CONFIG` (base64
kubeconfig) plus `DB_PASSWORD`, `DB_ROOT_PASSWORD` and `APP_KEY` are set as
repo secrets.

### Kubernetes

All resources live in their own `foogra` namespace. `k8s/` has a `Deployment`
+ `ConfigMap`/`Secret` for the API, a `Deployment` (2 replicas) for the
frontend, `ClusterIP` Services for both, an `Ingress` routing `/api`,
`/sanctum`, `/docs`, `/storage` to the API and everything else to the
frontend, and a `StatefulSet` (with `volumeClaimTemplates`) + headless
`Service` for MySQL, so the database keeps its data and its stable
`db-0.db.foogra.svc.cluster.local` identity across restarts.

```bash
./scripts/cluster-up.sh                       # minikube + ingress addon
./scripts/gen-secrets.sh                      # real secrets into k8s/*-secret.yaml
./scripts/load-images.sh <dockerhub-username> dev
./scripts/deploy.sh <dockerhub-username> dev  # apply -k + wait for rollout

kubectl -n foogra get all
kubectl -n foogra get ingress
```

Verify end-to-end through the Ingress (`Host: foogra.local`, since minikube's
docker driver on Windows/WSL isn't reachable directly from the host):

```bash
kubectl -n ingress-nginx port-forward svc/ingress-nginx-controller 18080:80 &
curl -H "Host: foogra.local" http://127.0.0.1:18080/api/v1/health
curl -H "Host: foogra.local" http://127.0.0.1:18080/
```
