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
- PostgreSQL + Prisma ORM
- Redis for sessions (express-session + connect-redis)
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
- `DATABASE_URL`: Postgres connection string
- `REDIS_URL`: Redis connection string
- `SESSION_SECRET`: session signing secret
- `PORT`: HTTP port
- `APP_URL`: Base URL used in sitemap
- `IP_SALT`: salt used to hash client IP addresses for privacy-preserving click analytics

## Development Notes
- Sessions are HTTP-only, same-site Lax, and persist for 7 days.
- Redirect endpoint validates enabled status and expiration, returns 404 otherwise, and records each visit.
- Public pricing page clearly states the service is free.
- CSRF protection is enabled across all POST routes.
- Rate limits: login/register (10 req/min per IP), link creation (30 req/min per IP and 20 links/hour per user).

## Deploying to Namecheap Stellar Plus (cPanel + Node.js app)
Stellar Plus is shared hosting, so you’ll need to rely on managed databases/caches outside the account. A typical setup:
1) Provision Postgres and Redis externally (e.g., a managed cloud DB + Redis), and allow connections from your server IP.  
2) In cPanel, open “Setup Node.js App” → Create Application.  
   - Application mode: Production  
   - Node.js version: choose 18.x or newer  
   - Application root: upload the `server` folder contents (package.json, dist, prisma, keywords.txt, etc.).  
   - Application startup file: `dist/index.js`  
3) Build locally, then deploy artifacts:  
   - Run locally: `npm install && npm run build` in `server/`  
   - Copy `dist/`, `package.json`, `node_modules`, `prisma/`, `keywords.txt`, and `.env` (with your production values) into the application root on Stellar Plus.  
4) Configure environment variables in cPanel “Environment variables”:  
   - `DATABASE_URL` → your managed Postgres URL  
   - `REDIS_URL` → your managed Redis URL  
   - `SESSION_SECRET`, `IP_SALT`, `APP_URL`, `PORT` (e.g., 3000)  
5) Run `npx prisma migrate deploy` and `npx prisma db seed` from the cPanel terminal inside the app root.  
6) Restart the Node.js app from the cPanel “Setup Node.js App” panel.  
7) Point your domain to the application via cPanel (setup a subdomain or route via `.htaccess` if needed). Use HTTPS and set `SESSION_COOKIE_SECURE` by running in production (NODE_ENV=production).
