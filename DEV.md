# WharfList — Rebuild Spec (DEV)

Feature-complete inventory of the current PHP/SQLite app, written as the source of truth for a full Laravel rebuild.

---

## Target stack (rebuild)

> **Add / confirm in your environment before scaffolding.**

| Item | Choice |
|------|--------|
| Framework | **Laravel 13** |
| Starter kit | [laravel/livewire-starter-kit](https://github.com/laravel/livewire-starter-kit) (Livewire 4 + Alpine + TypeScript + Tailwind) |
| Admin UI | **Flux free components only** (no Flux Pro) |
| Auth | Starter-kit auth; extend with TOTP 2FA + backup codes |
| DB | Prefer SQLite for parity (or MySQL/Postgres in prod) |
| Queues | Laravel queues + scheduler (replace custom `worker.php` / `cron.php`) |
| Mail | Laravel Mail + dynamic SMTP from settings |
| Public widget | **Reuse the same embed UI** from current `widget.js` (look, markup, motion). Standalone script (or Vite-built) calling Laravel API; do **not** restyle with Flux |

**UI note (current app):** README mentions Franken UI; the live UI is Tailwind CDN + Alpine 3 + Quill + qrcode.js. Rebuild should use Flux free + Livewire/Alpine from the starter kit, not Franken.

---

## Product summary

WharfList is an **email collection + newsletter** system:

1. Admins manage lists, sites, subscribers, imports, and campaigns.
2. Sites embed a JS widget that posts to a public subscribe API.
3. Double opt-in verification + per-list/global unsubscribe.
4. Campaigns send to verified subscribers via SMTP with batching, progress, cancel, and schedule.

**Current scale:** ~7.5k PHP LOC, ~8–10 SQLite tables, single (multi-user capable) admin app, no separate SPA.

---

## Current architecture (legacy)

```
Public (root)          Admin (/app)              Background
─────────────────      ─────────────────         ─────────────────
setup.php              dashboard.php             worker.php
api.php                lists / sites / …         cron.php
widget.js              compose / campaigns       run-worker.php
verify.php             settings / login          trigger-worker.php
unsubscribe.php        upload_image.php          check-scheduler.php (lazy cron)
index.php → login|dash
```

- Install gate: `wharflist.db` + `.installed` → else redirect to setup.
- Config: SQLite `settings` key/value (no `.env` for app settings).
- Security is inconsistent (CSRF on some forms only). Rebuild must fix that globally.

---

## Domain model

```
User 1──* BackupCode
List 1──* Site                    (site.list_id = default signup list)
List *──* Subscriber              (via subscriber_lists; per-list unsubscribed)
Site 1──* Subscriber              (origin site_id on first signup)
Campaign *── lists                (list_id + list_ids CSV today → use pivot in Laravel)
Campaign 1──* QueueJob            (or Job batch / custom CampaignSend model)
Site · rate_limits(ip, site_id)
Setting (key → value)             (or typed settings table / Spatie)
```

### Tables (canonical fields)

#### `users`
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| username | unique | login by username **or** email |
| email | unique | |
| password | hashed | |
| totp_secret | nullable | base32 |
| totp_enabled | bool | |
| timestamps | | |

#### `backup_codes`
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| user_id | FK → users | cascade delete |
| code | hashed | 10× 8-char codes |
| used | bool | one-use |
| created_at | | |

#### `lists`
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| name | | |
| description | nullable | |
| is_default | bool | cannot delete default |
| timestamps | | |

#### `sites`
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| name | | |
| domain | | no scheme; www-normalized compare |
| list_id | FK → lists | widget signup target |
| api_key | unique | `random_bytes(32)` hex |
| custom_fields | JSON/text | comma-separated today; **widget does not collect them yet** |
| timestamps | | |

#### `subscribers`
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| email | unique **globally** | one subscriber row per email |
| site_id | FK → sites | origin |
| verification_token | | |
| verified | bool | |
| unsubscribed | bool | **global** unsubscribe |
| custom_data | JSON | |
| subscribed_at | | |
| verified_at | nullable | |

#### `subscriber_lists` (pivot)
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| subscriber_id | FK | cascade |
| list_id | FK | cascade |
| unsubscribed | bool | **per-list** |
| subscribed_at | | |
| UNIQUE(subscriber_id, list_id) | | |

#### `email_campaigns`
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| list_id | FK | legacy primary |
| list_ids | text/CSV → **pivot** | multi-list |
| subject | | |
| body | HTML | Quill |
| status | draft \| sent \| scheduled | |
| sent_count | int | |
| created_at / sent_at | | |

#### `queue_jobs` → prefer Laravel jobs + `campaign_sends` table
| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| campaign_id | FK | |
| list_ids | | |
| status | pending \| scheduled \| processing \| completed \| failed \| cancelled | |
| progress / total | int | |
| scheduled_at | nullable UTC | |
| started_at / completed_at | | |
| error | text | used by worker (ensure migration exists) |

#### `rate_limits`
| Column | Type | Notes |
|--------|------|-------|
| ip_address | | |
| site_id | FK | |
| attempts | | |
| last_attempt | | |
| UNIQUE(ip, site) | | 5 / hour / site |

#### `settings` (key/value)
Keys in use today:

| Key | Purpose |
|-----|---------|
| site_name | Branding |
| site_url | Absolute BASE_URL for emails/widget |
| timezone | App timezone (schedule display/convert) |
| smtp_host, smtp_port, smtp_user, smtp_pass, smtp_from | Mail |
| emails_per_batch, delay_between_emails | Send throttle |
| email_logo, logo_* | Email header logo |
| footer_* | CAN-SPAM style footer fields |
| cron_secret | HTTP cron auth |
| migrations_version | legacy only |

---

## Feature inventory (must ship)

### A. Installation / first run

- [ ] Block app until installed (equivalent of `.installed`).
- [ ] Wizard: create admin user, SMTP, timezone, default list, `site_url`.
- [ ] Create storage/DB; secure permissions as appropriate for hosting.
- [ ] Idempotent: refuse re-run when already installed.
- **Laravel:** Artisan install command and/or Livewire setup wizard + middleware `EnsureInstalled`.

### B. Authentication & users

- [ ] Login (username **or** email + password).
- [ ] Session regeneration after login / 2FA.
- [ ] Logout (POST + CSRF).
- [ ] Change password (current password, min length ≥ 8).
- [ ] TOTP 2FA enable → QR (`otpauth://`, issuer WharfList) → verify → enable.
- [ ] Disable 2FA.
- [ ] Backup codes: generate 10, regenerate, one-use; show once; regenerate with password confirm.
- [ ] Login second step: TOTP **or** backup code.
- [ ] Multi-user admin: list / add / delete (cannot delete self); reset another user’s 2FA.
- **Laravel:** starter-kit auth + Fortify/Livewire pages; package e.g. `pragmarx/google2fa` or equivalent; Flux forms.

### C. Dashboard

- [ ] Counts: verified, pending, lists, sites.
- [ ] Latest ~10 subscribers (email, lists, site, status, date).
- **Livewire page + Flux stats/table.**

### D. Lists

- [ ] Index with verified / pending / unsubscribed / total per list.
- [ ] Create (name, description).
- [ ] Delete (forbid default).
- [ ] Link into subscribers filtered by list.
- **Flux:** modal create, confirm delete.

### E. Sites

- [ ] Index sites (name, domain, list, api_key masked).
- [ ] Create: name, domain (no scheme), target list, optional custom_fields.
- [ ] Auto-generate `api_key`.
- [ ] Embed code modal: `#wharflist-form` + `widget.js` with `data-api-key`, `data-site-id`.
- [ ] Delete site.
- [ ] Copy embed to clipboard.
- **Gap to decide:** implement custom fields in widget, or drop from UI until ready.

### F. Subscribers

- [ ] Filter: `list_id`, status `all | verified | pending | unsubscribed`.
- [ ] Row select + bulk bar.
- [ ] Actions:
  - Manual verify
  - Resend verification (single / bulk)
  - Change list memberships
  - Bulk add to list(s)
  - Bulk delete
- [ ] CSRF on all mutating actions.
- **Livewire table + Flux checkboxes/modals.**

### G. Import

- [ ] Download sample CSV (`email` required; optional custom columns).
- [ ] Upload CSV/XLSX → preview (~10 rows) → confirm.
- [ ] Select target **site** (implies list); option **skip verification**.
- [ ] Upsert: existing global email → add to list / update; new row → create subscriber + list pivot.
- [ ] Optionally queue verification emails.
- **Laravel:** queued import job recommended for large files; Maatwebsite Excel or league/csv.

### H. Compose & campaigns

- [ ] Multi-list select.
- [ ] Subject + rich HTML body (Quill today → TipTap / Quill / Flux-friendly editor).
- [ ] Image upload → public disk URL inject into editor.
- [ ] Preview.
- [ ] Save / update **draft**.
- [ ] **Send now** → campaign + queue job; dispatch worker.
- [ ] **Schedule** → store `scheduled_at` in UTC from app timezone; job status scheduled.
- [ ] Validations: ≥1 list, subject, body; send/schedule requires verified audience > 0.
- [ ] Recent campaigns sidebar / link to all campaigns.
- [ ] Campaigns index: status badges, lists, schedule time, progress, edit draft, open status.
- [ ] Campaign status: progress bar, timestamps, errors; poll while pending/processing; cancel → job cancelled, campaign back to draft.
- **Laravel:** `SendCampaignJob` (chunked), schedule via `scheduled_at` + scheduler, broadcast/Livewire polling for progress.

#### Send audience rules (critical)
```
DISTINCT subscribers WHERE
  in selected lists
  AND verified = 1
  AND global unsubscribed = 0
  AND list unsubscribed = 0
```

#### Email template rules
- HTML body from campaign.
- Logo / branding from settings.
- Footer fields (CAN-SPAM style).
- Replace `{{UNSUBSCRIBE_LINK}}` with signed/per-recipient unsubscribe URL.
- Throttle: `emails_per_batch`, `delay_between_emails`.
- Respect cancel mid-run; persist progress; statuses: pending → processing → completed | failed | cancelled.

### I. Settings

Tabs / sections:

1. **General** — site_name, site_url, timezone, email logo, footer_*.
2. **SMTP** — host/port/user/pass/from; batch + delay; **Send test email**.
3. **Security** — password; 2FA enable/verify/disable; backup codes.
4. **Cron / Scheduler** — show Laravel scheduler instructions (replace curl+secret pattern, or keep HTTP secret endpoint).
5. **Users** — CRUD admins as above.

Dynamic SMTP: Runtime mailer config from DB (not only `.env`).

### J. Public subscribe API

**Endpoint:** `POST /api/subscribe` (legacy: `/api.php`)

**CORS:** allow OPTIONS; `Access-Control-Allow-Origin: *` (or lock to site domains).

**Body (JSON):**
```json
{
  "api_key": "...",
  "email": "user@example.com",
  "custom_data": {},
  "_hp": "",
  "_t": 1710000000000
}
```

**Response shape (keep for widget compatibility):**
```json
{
  "success": true,
  "message": "Please check your email to verify your subscription",
  "data": {}
}
```

**Rules:**
1. Method POST only.
2. Validate api_key → site; email format.
3. **Honeypot** `_hp` filled → silent fake success.
4. **Timing** `_t` present and submitted &lt; 1s → silent fake success.
5. **Referer domain** must match site.domain (strip `www.`, case-insensitive). Missing referer: current app allows through — document and decide harden vs preserve.
6. Rate limit: **5 attempts / IP / site / hour**.
7. Existing email globally:
   - If globally unsubscribed → clear flag (re-subscribe).
   - If already on this list + verified → error “already subscribed”.
   - If already on list + unverified → error “pending verification”.
   - If not on list → add pivot; if verified success without email; if not verified resend verification.
8. New email → create subscriber + pivot + send verification email (or soft success if mail fails).

### K. Embeddable widget (`widget.js`) — **same UI required**

**Requirement:** Port the existing embed widget **as-is visually and behaviorally**. Third-party sites already style around it; do not redesign, do not wrap it in Flux/Admin chrome, and do not change IDs/classes that matter for CSS overrides.

**Reference implementation:** current root [`widget.js`](widget.js). Prefer copying CSS + markup + interaction timing 1:1; only swap the POST URL to the Laravel subscribe route (keep `/api.php` alias if possible).

#### Embed contract (unchanged)

```html
<div id="wharflist-form"></div>
<script>
(function() {
    var script = document.createElement('script');
    script.src = 'https://yourdomain.com/widget.js';
    script.setAttribute('data-api-key', 'YOUR_API_KEY');
    script.setAttribute('data-site-id', 'YOUR_SITE_ID');
    document.head.appendChild(script);
})();
</script>
```

- Mount target: `#wharflist-form`
- Script attrs: `data-api-key`, `data-site-id`
- Base URL derived from script `src` (strip `/widget.js`, normalize slashes)

#### Visual design (black / white, self-contained)

Scoped under `#wharflist-widget` with **inline `<style>`** (no Tailwind/Flux on host pages):

| Token | Value |
|-------|--------|
| Max width | `400px` |
| Font | `inherit` (host page) |
| Palette | black `#000` / white `#fff` / muted `#333`, `#666` |
| Input | white bg, black text, `2px solid #000`, `border-radius: 5px`, `14px` padding, right padding `50px` for button |
| Focus | black border + `box-shadow: 0 0 0 2px rgba(0,0,0,0.1)` |
| Submit button | absolute right inside field; `40×40`, black bg, white icon, `border-radius: 5px` |
| Hover | bg `#333`, `scale(1.05)` |
| Active | `scale(0.95)` |
| Disabled | bg `#666`, no scale |
| Icon | 20×20 arrow SVG (line + polyline right) |
| Message success | white bg, black text, `2px` black border, centered |
| Message error | black bg, white text, `2px` black border, centered |

**Structure (DOM IDs to preserve):**

```
#wharflist-widget
  form#wharflist-subscribe-form
    input.wharflist-hp (honeypot, off-screen)
    input#wharflist-timestamp (hidden)
    #wharflist-email-wrapper
      input#wharflist-email  placeholder="Enter your email"
      button#wharflist-submit-btn  aria-label="Subscribe"  → arrow SVG
    #wharflist-message
```

There is **no title/heading** in the live markup (CSS allows `h3` but widget does not inject one). Keep that: input + arrow button only.

#### Interaction / motion (preserve timing)

1. On init: set `#wharflist-timestamp` to `Date.now()`.
2. Client anti-spam: honeypot filled **or** submit &lt; 1000ms → silent return (no message).
3. Submit: disable button → spinner SVG; email wrapper `opacity: 0.5`.
4. On response: widget `.fade-out` (300ms) → hide email wrapper → show `#wharflist-message` with `.show.success` or `.show.error`.
5. Success: reset form; after **5s** fade back to input; re-enable arrow button.
6. Error / network: after **3s** fade back to input; re-enable arrow button.
7. Message enter animation: opacity 0→1, `translateY(-10px)`→0 over 300ms.

#### Payload / API (compat)

```json
{ "api_key": "...", "email": "...", "_t": <ms>, "_hp": "" }
```

POST `{baseUrl}/api.php` (or Laravel `/api/subscribe` with `/api.php` alias). Expect `{ success, message, data }`.

#### Rebuild rules

- [ ] Ship the **same** look (sizes, radii, colors, arrow icon, message styles).
- [ ] Preserve DOM IDs/classes listed above for merchant CSS overrides.
- [ ] Preserve fade / spinner / 5s / 3s timings.
- [ ] No Flux, no Alpine dependency required inside the embed (vanilla JS IIFE).
- [ ] Optional later: theme variants — **not** in parity scope; default UI stays identical.
- [ ] Custom fields remain out of the widget until a separate decision (Sites UI may still store them).

### L. Verify (double opt-in)

- `GET /verify/{token}` (legacy `verify.php?token=`).
- Match token, `verified = 0` → set verified + `verified_at`.
- Friendly success / already used / invalid pages (public, branded).

### M. Unsubscribe

- `GET` confirm + `POST` confirm.
- Params: email, token, optional `list_id`.
- Token today: `md5(email . 'unsubscribe_salt_2024')` — **replace with signed Laravel URL / HMAC secret in settings**.
- With `list_id` → set `subscriber_lists.unsubscribed = 1`.
- Without → set global `subscribers.unsubscribed = 1`.
- Branded confirm / success / error.

### N. Background processing (Laravel mapping)

| Legacy | Laravel |
|--------|---------|
| `worker.php` loop | `SendCampaignJob` / batch jobs |
| `cron.php?key=` | `php artisan schedule:run` (+ optional secured HTTP trigger) |
| `run-worker` / `trigger-worker` | `Bus::dispatch` / horizon / queue:work |
| `check-scheduler` on every page | Scheduler only (remove lazy cron) |
| `emails_per_batch` + delay | chunk size + `usleep` / rate limited job |

Keep: progress tracking, cancel mid-send, scheduled sends, logs.

---

## Suggested Livewire / Flux surface map

| Module | Livewire component (suggested) | Flux free building blocks |
|--------|--------------------------------|---------------------------|
| Login / 2FA | Auth pages from kit + `TwoFactorChallenge` | Input, Button, Field, Card |
| Dashboard | `Pages/Dashboard` | Stat cards, Table |
| Lists | `Lists/Index`, `Lists/CreateModal` | Modal, Button, Input, Table |
| Sites | `Sites/Index`, `Sites/EmbedModal` | Modal, Code/clipboard UI |
| Subscribers | `Subscribers/Index` | Table, Checkbox, Badge, Modal |
| Import | `Import/Wizard` (upload → preview → commit) | Steps, File upload, Table |
| Compose | `Campaigns/Compose` | Form, Editor, Select, Modal |
| Campaigns | `Campaigns/Index`, `Campaigns/Status` | Badge, Progress, poll |
| Settings | `Settings/{General,Smtp,Security,Users}` | Tabs, Form, Toggle |
| Setup | `Setup/Wizard` | Form wizard |
| Public verify/unsub | Blade (or minimal Livewire) | Simple layout |

**Do not use Flux Pro components.** Stay on free set only.

---

## Routes sketch (Laravel)

```
# Public
GET  /setup
POST /setup
GET  /verify/{token}
GET|POST /unsubscribe
POST /api/subscribe
GET  /widget.js                    # public/js or route
# Compat aliases (optional)
POST /api.php → subscribe
GET  /verify.php → verify

# Auth (starter kit)
… login, logout, password, profile …

# Admin (auth middleware)
GET  /dashboard
GET|POST /lists …
GET|POST /sites …
GET|POST /subscribers …
GET|POST /import …
GET|POST /campaigns …
GET  /campaigns/{campaign}/status
POST /campaigns/{campaign}/cancel
POST /uploads/images
GET|POST /settings/{section}
```

---

## Security checklist (fix / improve)

- [ ] CSRF on **all** mutating admin actions (legacy gaps: settings, sites, import, cancel job).
- [ ] Auth middleware on all admin + upload + worker triggers.
- [ ] Rate limit subscribe API (Redis/cache preferred).
- [ ] Domain allowlist on widget (harden missing Referer policy).
- [ ] Signed unsubscribe / verify URLs (drop hardcoded MD5 salt).
- [ ] Store SMTP password encrypted at rest if possible.
- [ ] Remove debug scripts (`debug-2fa.php`, `debug-users.php`, API debug logs).
- [ ] Validate upload MIME + size (JPG/PNG/GIF/WebP, 5MB).
- [ ] `session_regenerate_id` equivalent on privilege change.

---

## Out of scope / non-goals (unless requested)

- Marketing site / Landing for WharfList itself
- Multi-tenant SaaS billing
- Contact segmentation beyond lists/custom_data
- A/B testing, analytics opens/clicks (not in current app)
- Mobile native apps
- Franken UI (not actually used today)

---

## Migration plan (data)

1. Scaffold Laravel + Livewire starter kit; Flux free only.
2. Migrations/models matching domain above (normalize `list_ids` CSV → `campaign_list` pivot).
3. Artisan command: import from legacy SQLite (`app/data/wharflist.db`) → new DB.
4. Port settings keys 1:1.
5. Keep widget JSON contract; point embeds to new base URL (or reverse-proxy aliases).
6. Cut over SMTP + cron/queue worker.
7. Delete debug/legacy PHP entrypoints.

---

## Acceptance criteria (parity)

1. New install wizard produces working admin + default list.
2. Create list → site → embed widget on allowed domain → subscribe → verify email → appears verified. Widget **matches current UI** (black/white inline form, arrow submit, fade/message timings).
3. Import CSV with skip-verify and without; list membership correct.
4. Compose to multiple lists → send → progress updates → completes; cancel mid-send restores draft.
5. Scheduled campaign fires via scheduler.
6. 2FA login + backup code works; multi-user admin works.
7. Unsubscribe list-specific and global; CAN-SPAM footer + unsubscribe link in campaign HTML.
8. SMTP test email from settings succeeds.
9. Rate limit + honeypot + timing + domain checks behave as documented.
10. UI built with Livewire + Alpine + **Flux free only**.

---

## Reference: legacy file index

| Area | Paths |
|------|--------|
| Admin | `app/dashboard.php`, `lists.php`, `sites.php`, `subscribers.php`, `import.php`, `compose.php`, `campaigns.php`, `campaign-status.php`, `settings.php`, `login.php`, `logout.php`, `upload_image.php`, `send-emails.php` (legacy) |
| Public | `setup.php`, `api.php`, `widget.js`, `verify.php`, `unsubscribe.php`, `index.php` |
| Background | `app/worker.php`, `cron.php`, `run-worker.php`, `trigger-worker.php`, `check-scheduler.php`, `email-helper.php`, `phpmailer.php` |
| Core | `config.php`, `app/database.php`, `app/migrations.php`, `app/auth.php`, `app/csrf-helper.php` |

---

## Open decisions (fill before build)

- [ ] Should work with SQLITE, MYSQL, POSTGRES
- [ ] Widget custom fields: implement
- [ ] Unsubscribe token: signed URLs only (breaks old links) vs temporary dual-support? (from scratch - better solution for safety)
- [ ] Campaign editor: Quill vs TipTap vs simple HTML textarea? Quill will be enought - with Image support please 
- [ ] Queue driver: `database` vs Redis for production? Should work in both way 
- [ ] Keep HTTP `cron_secret` endpoint for hosts without real cron? No 

Add your notes below this line:

---

### Notes

- Stack: Laravel 13 + [livewire-starter-kit](https://github.com/laravel/livewire-starter-kit) + Flux free only + Alpine.
- Public embed: keep **same widget UI** as current `widget.js` (no Flux redesign).
-
