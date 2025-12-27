# Open Shortlinks

A production-ready, free-for-everyone URL shortener with keyword-based slugs, built with Node.js, TypeScript, Express, PostgreSQL, Redis, Prisma, and EJS. Users must register to create and manage shortlinks. Slugs follow the format `<keyword>-<code>` with sequential keywords and codes for SEO-friendly predictability.

## Features
- User registration and login with bcrypt-hashed passwords and Redis-backed sessions
- Auth-required dashboard to create, edit, enable/disable, and search links with pagination
- Deterministic slug generation: round-robin keywords plus sequential code counter encoded in a base62 charset
- Concurrency-safe link creation using a single PostgreSQL transaction with row-level locking
- Click analytics with hashed IPs (no raw IP stored), referrer, and user agent
- Public SEO pages (landing, features, pricing, privacy, terms) plus robots.txt, sitemap.xml, and health status endpoint

## Tech Stack
- Node.js + TypeScript + Express
- SQLite (file-based) via Prisma ORM (PostgreSQL is optional for local/docker)
- Redis (optional) for sessions, caching, and rate limiting; falls back to in-memory when unset
- EJS server-rendered views
- Docker + docker-compose for local development

## Getting Started (Docker)
1. Ensure Docker and Docker Compose are installed.
2. Copy the example environment file:
   ```bash
   cp server/.env.example server/.env
   ```
3. Start all services:
   ```bash
   docker compose up --build
   ```
   This builds the app, applies Prisma migrations, seeds keywords from `server/keywords.txt`, and starts the web server at http://localhost:3000.

## Slug Generation Rules
- Keywords are loaded from `server/keywords.txt` (comma-separated, trimmed, lowercased, deduped) during the Prisma seed and stored in the `Keyword` table as active entries.
- A `Setting` row maintains `nextKeywordIndex` and `nextCodeCounter`.
- Link creation locks the settings row (`SELECT ... FOR UPDATE`) inside a single transaction, picks the next keyword in round-robin order, encodes the numeric counter using the charset `abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ`, and forms the slug `<keyword>-<code>`.
- Both pointers are advanced atomically; slug collisions trigger up to 5 retries with incremented counters.
- The first link uses code `a`.

## Database Schema (Prisma)
- `User`: accounts with email/password
- `Keyword`: active keywords for slug prefixes
- `Setting`: singleton row for slug pointers
- `Link`: user-owned links with slug, target URL, redirect type, enabled flag, expiration, and click counter
- `Click`: hashed-IP click records with referrer/user-agent

## Running Prisma Commands Manually
Inside the `server` folder:
```bash
npm install
npx prisma migrate dev
npx prisma db seed
npm run dev
```

## Environment Variables
See `server/.env.example` for defaults:
- `DATABASE_URL`: SQLite database file URL, e.g. `file:./data/shortlinks.db`
- `REDIS_URL` (optional): Redis connection string. Leave blank to disable Redis-backed caching, rate limiting, and sessions.
- `SESSION_SECRET`: session signing secret
- `PORT`: HTTP port
- `APP_URL`: Base URL used in sitemap
- `IP_SALT`: salt used to hash client IP addresses for privacy-preserving click analytics

### Running Without Redis
If your hosting plan does not provide Redis, leave `REDIS_URL` blank. The app will:
- Fall back to the in-memory session store (single-instance only)
- Disable Redis-backed caching for redirects
- Bypass Redis rate limiting

Redis is still recommended for production so sessions survive restarts and rate limiting/caching remain effective.
For SQLite deployments (e.g., Stellar Plus), ensure the `server/data/` directory exists (a `.gitkeep` is included) so Prisma can create `shortlinks.db`. An example schema dump is provided at `server/data/shortlinks.example.db.sql`; to create a database file manually, run:
```bash
cd server
sqlite3 data/shortlinks.db < data/shortlinks.example.db.sql
```

## Development Notes
- Sessions are HTTP-only, same-site Lax, and persist for 7 days.
- Redirect endpoint validates enabled status and expiration, returns 404 otherwise, and records each visit.
- Public pricing page clearly states the service is free.
- CSRF protection is enabled across all POST routes.
- Rate limits: login/register (10 req/min per IP), link creation (30 req/min per IP and 20 links/hour per user).

## Deploying to Namecheap Stellar Plus (cPanel + Node.js app, no external DB/Redis)
Stellar Plus only provides a Node runtime—no PostgreSQL or Redis. Use the built-in SQLite database file and optional in-memory sessions:
1) Build locally so you can upload a ready-to-run bundle:  
   ```bash
   cd server
   npm install
   npm run build
   npx prisma migrate deploy   # creates the SQLite DB file
   npx prisma db seed
   ```  
   This creates `data/shortlinks.db` next to `prisma/`.
2) Upload to cPanel → “Setup Node.js App”:  
   - Application mode: Production  
   - Node.js version: choose 18.x or newer  
   - Application root: upload the `server` folder contents (including `dist/`, `package.json`, `node_modules/`, `prisma/`, `keywords.txt`, `data/shortlinks.db`, and `.env`).  
   - Application startup file: `dist/index.js`
3) Environment variables in cPanel “Environment variables”:  
   - `DATABASE_URL` → `file:./data/shortlinks.db`  
   - Leave `REDIS_URL` blank (uses in-memory sessions/rate-limit bypass)  
   - `SESSION_SECRET`, `IP_SALT`, `APP_URL`, `PORT` (e.g., 3000)
4) After uploading, run in the cPanel terminal inside the app root:  
   ```bash
   npx prisma migrate deploy
   npx prisma db seed
   ```  
   (These are no-ops if the DB file already exists but are safe to run.)
5) Restart the Node.js app from the cPanel “Setup Node.js App” panel.  
6) Point your domain to the application via cPanel (subdomain or `.htaccess`). Use HTTPS; with `NODE_ENV=production`, cookies become secure.
