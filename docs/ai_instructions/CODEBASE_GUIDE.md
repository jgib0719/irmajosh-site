# IrmaJosh.com Codebase Context

## 🚨 CRITICAL AI RULES
1. **Service Worker**: ALWAYS bump `CACHE_VERSION` in `public_html/service-worker.js` when modifying CSS/JS/Views. NEVER cache 3xx redirects.
2. **Database**: ALWAYS use `migrations/` for schema changes. Check FK constraints before drops.
3. **Cleanup**: Remove ALL references (Routes, Controllers, Views, Locales, JS, CSS) when deleting features.
4. **Mobile First**: All mobile styles in `@media (max-width: 768px)` block in `style.css`. Test navigation/tabs/overflow.
5. **Security**: Use `db()` for PDO. Use `credentials: 'same-origin'` for fetch. Use `nonce="<?= cspNonce() ?>"` for inline scripts.
6. **Error Handling**: If `lastInsertId()` returns 0, use fallback `SELECT LAST_INSERT_ID()`.

## Architecture
- **Stack**: PHP 8.4 (Custom MVC), MySQL, Apache, Vanilla JS/HTMX.
- **Root**: `/var/www/irmajosh.com/`
- **Entry**: `public_html/index.php` -> `src/bootstrap.php` -> `src/middleware.php` -> `src/router.php`.
- **Assets**: `public_html/assets/`. Versioned via `getAssetVersion()`.
- **Config**: `.env` (Environment), `config/routes.php` (Routes).

## Database Schema (Source of Truth: `migrations/`)
- **users**: `id`, `google_user_id`, `email`, `name`, `picture`, `locale`, `ical_token`.
- **user_tokens**: `id`, `user_id`, `encrypted_tokens` (JSON), `key_version`.
- **tasks**: `id`, `user_id`, `title`, `description`, `due_date`, `status` ('pending','in_progress','completed'), `is_shared` (TINYINT), `google_event_id`.
- **calendar_events**: `id`, `user_id`, `title`, `description`, `start_at`, `end_at`, `is_all_day`, `recurrence_*`, `color`.
- **event_reminders**: `id`, `event_id`, `occurrence_start`, `sent_at`.
- **push_subscriptions**: `id`, `user_id`, `endpoint`, `p256dh`, `auth`, `user_agent`.
- **shopping_items**: `id`, `user_id`, `item_name`, `is_completed`, `completed_at`.
- **date_categories**: `id`, `name`, `icon`, `color`.
- **date_ideas**: `id`, `category_id`, `title`, `description`, `url`, `cost_level`, `season`, `points_value`, `is_active`.
- **completed_dates**: `id`, `user_id`, `date_idea_id`, `completed_at`, `rating`, `notes`, `photo_url`, `points_awarded`.
- **audit_logs**: `id`, `user_id`, `event_type`, `ip_address`, `user_agent`, `details`.

## Core Helpers (`src/helpers.php`)
- `db(): PDO` - Singleton DB connection.
- `env(key, default)` / `config(key)` - Config access.
- `view(template, data)` / `json(data)` / `redirect(url)` - Response helpers. `json` and `redirect` EXIT immediately.
- `csrfToken()` / `verifyCsrfToken(token)` - Security.
- `cspNonce()` - For `<script nonce="...">`.
- `t(key)` - Translation (`locales/`).
- `getAssetVersion()` - Cache busting string.
- `currentUser()` / `requireAuth()` - Auth state.

## Code Patterns
- **Models**: Mostly **Static Methods** (`Task::create()`, `Task::find()`). Exception: `PushSubscription` (Instance).
- **Controllers**: Extend `BaseController`. Do NOT inject PDO in constructor (use `db()`).
- **Services**: Instantiate as needed (`new EmailService()`).
- **Routes**: Defined in `config/routes.php`. Middleware: `authMiddleware`, `csrfMiddleware`.

## Frontend & Mobile
- **CSS**: `style.css`. Variables for colors/spacing.
- **Mobile**: Breakpoint `768px`.
    - **Tabs**: Use negative margins to break container padding: `margin: 0 calc(var(--spacing-md) * -1);`.
    - **Calendar**: Use `height: 'auto'` in FullCalendar config.
    - **Nav**: Icons only on mobile.
- **JS**: Vanilla + HTMX.
    - **Fetch**: `fetch(url, { credentials: 'same-origin', headers: { 'X-CSRF-Token': ... } })`.

## Service Worker (`public_html/service-worker.js`)
- **Strategy**: Cache-first for assets. Network-only for dynamic (`/tasks`, `/api`, `/calendar`, `/shopping-list`).
- **Updates**: Run `./scripts/bust_cache.sh` to touch files and reload PHP-FPM.
- **Version**: Update `CACHE_VERSION` manually in SW file on changes.

## Common Issues
- **500 on Insert**: `PDO::lastInsertId()` may return 0. Use fallback query.
- **Mobile Scroll**: Check for elements wider than viewport. Use `overflow-x: hidden` on body.
- **CSRF**: Ensure meta tag exists and is sent in headers.
