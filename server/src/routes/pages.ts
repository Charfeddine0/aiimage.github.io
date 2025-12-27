import { Router, Request, Response, NextFunction } from 'express';
import prisma from '../prisma';
import redisClient from '../redis';
import { hashIp } from '../utils/security';

const router = Router();

router.get('/', (req: Request, res: Response) => {
  res.render('landing', { title: 'Shorten links with SEO-friendly slugs' });
});

router.get('/features', (req: Request, res: Response) => {
  res.render('features', { title: 'Features' });
});

router.get('/pricing', (req: Request, res: Response) => {
  res.render('pricing', { title: 'Pricing' });
});

router.get('/privacy', (req: Request, res: Response) => {
  res.render('privacy', { title: 'Privacy Policy' });
});

router.get('/terms', (req: Request, res: Response) => {
  res.render('terms', { title: 'Terms of Service' });
});

router.get('/robots.txt', (_req: Request, res: Response) => {
  res.type('text/plain');
  res.send('User-agent: *\nAllow: /\nSitemap: /sitemap.xml');
});

router.get('/sitemap.xml', (_req: Request, res: Response) => {
  res.type('application/xml');
  const urls = ['/', '/features', '/pricing', '/privacy', '/terms'];
  const entries = urls
    .map((url) => `<url><loc>${process.env.APP_URL || 'http://localhost:3000'}${url}</loc></url>`)
    .join('');
  res.send(`<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${entries}</urlset>`);
});

router.get('/status', (_req: Request, res: Response) => {
  res.json({ status: 'ok', uptime: process.uptime(), timestamp: new Date().toISOString() });
});

router.get('/dashboard', async (req: Request, res: Response) => {
  if (!req.session.userId) return res.redirect('/login');
  const links = await prisma.link.findMany({
    where: { userId: req.session.userId },
    orderBy: { createdAt: 'desc' },
  });
  res.render('dashboard', { title: 'Dashboard', links });
});

router.get('/:slug', async (req: Request, res: Response, next: NextFunction) => {
  const slug = req.params.slug;
  const reserved = new Set([
    'register',
    'login',
    'logout',
    'dashboard',
    'links',
    'features',
    'pricing',
    'privacy',
    'terms',
    'robots.txt',
    'sitemap.xml',
    'status',
  ]);

  if (reserved.has(slug)) {
    return next();
  }

  const cacheClient = redisClient?.isOpen ? redisClient : null;
  const cacheKey = `slug:${slug}`;
  let cached: any = null;
  if (cacheClient) {
    try {
      const cachedValue = await cacheClient.get(cacheKey);
      if (cachedValue) {
        cached = JSON.parse(cachedValue);
      }
    } catch (err) {
      console.error('Redis read error', err);
    }
  }

  let link = cached;

  if (!link) {
    link = await prisma.link.findUnique({ where: { slug } });
    if (!link) {
      return res.status(404).render('404', { title: 'Link not found', message: 'Link does not exist.' });
    }
    if (cacheClient) {
      try {
        await cacheClient.set(
          cacheKey,
          JSON.stringify({
            id: link.id,
            longUrl: link.longUrl,
            redirectType: link.redirectType,
            enabled: link.enabled,
            expiresAt: link.expiresAt ? link.expiresAt.toISOString() : null,
          }),
          { EX: 600 }
        );
      } catch (err) {
        console.error('Redis write error', err);
      }
    }
  }

  if (!link.enabled) {
    return res.status(404).render('404', { title: 'Link not available', message: 'This link is disabled.' });
  }

  if (link.expiresAt && new Date(link.expiresAt) < new Date()) {
    return res.status(404).render('404', { title: 'Link expired', message: 'This link has expired.' });
  }

  const salt = process.env.IP_SALT || 'ip_salt';
  const ipHash = hashIp(req.ip || '', salt);
  const referrer = req.get('referer') || null;
  const userAgent = req.get('user-agent') || null;

  await prisma.$transaction([
    prisma.link.update({ where: { id: link.id }, data: { clicks: { increment: 1 } } }),
    prisma.click.create({
      data: {
        linkId: link.id,
        ipHash,
        referrer,
        userAgent,
      },
    }),
  ]);

  res.redirect(link.redirectType === 301 ? 301 : 302, link.longUrl);
});

export default router;
