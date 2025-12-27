import { Router, Request, Response } from 'express';
import prisma from '../prisma';
import { requireAuth } from '../middleware/auth';
import { encodeCounter } from '../utils/slug';
import { isValidHttpUrl } from '../utils/validation';
import { rateLimit, ipKey, userKey } from '../middleware/rateLimit';
import redisClient from '../redis';

const router = Router();

router.get('/links', requireAuth, async (req: Request, res: Response) => {
  const page = Math.max(parseInt((req.query.page as string) || '1', 10), 1);
  const pageSize = 10;
  const search = (req.query.search as string) || '';

  const where = {
    userId: req.session.userId!,
    ...(search ? { slug: { contains: search } } : {}),
  };

  const [links, total] = await Promise.all([
    prisma.link.findMany({
      where,
      orderBy: { createdAt: 'desc' },
      skip: (page - 1) * pageSize,
      take: pageSize,
    }),
    prisma.link.count({ where }),
  ]);

  res.render('links', {
    title: 'Your Links',
    links,
    page,
    totalPages: Math.ceil(total / pageSize) || 1,
    search,
  });
});

router.get('/links/new', requireAuth, (_req: Request, res: Response) => {
  res.render('new-link', { title: 'Create Link', error: null });
});

router.post(
  '/links',
  requireAuth,
  rateLimit(ipKey('create'), 30, 60),
  rateLimit(userKey('create-user'), 20, 60 * 60),
  async (req: Request, res: Response) => {
    const { longUrl, redirectType, expiresAt } = req.body;
    if (!longUrl) {
      return res.status(400).render('new-link', { title: 'Create Link', error: 'Destination URL is required' });
    }
    if (!isValidHttpUrl(longUrl)) {
      return res.status(400).render('new-link', { title: 'Create Link', error: 'URL must be http or https' });
    }

    try {
      await createLink({
        userId: req.session.userId!,
        longUrl,
        redirectType: parseInt(redirectType, 10) === 301 ? 301 : 302,
        expiresAt: expiresAt ? new Date(expiresAt) : null,
      });
      res.redirect('/dashboard');
    } catch (error) {
      console.error(error);
      res.status(500).render('new-link', { title: 'Create Link', error: 'Unable to create link' });
    }
  }
);

router.get('/links/:id/edit', requireAuth, async (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const link = await prisma.link.findFirst({ where: { id, userId: req.session.userId! } });
  if (!link) return res.status(404).render('404', { title: 'Link not found' });
  res.render('edit-link', { title: 'Edit Link', link, error: null });
});

router.post('/links/:id', requireAuth, async (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const { longUrl, redirectType, expiresAt } = req.body;
  const link = await prisma.link.findFirst({ where: { id, userId: req.session.userId! } });
  if (!link) return res.status(404).render('404', { title: 'Link not found' });

  if (!isValidHttpUrl(longUrl)) {
    return res.status(400).render('edit-link', { title: 'Edit Link', link, error: 'URL must be http or https' });
  }

  await prisma.link.update({
    where: { id },
    data: {
      longUrl,
      redirectType: parseInt(redirectType, 10) === 301 ? 301 : 302,
      expiresAt: expiresAt ? new Date(expiresAt) : null,
    },
  });

  await redisClient.del(`slug:${link.slug}`);

  res.redirect('/dashboard');
});

router.post('/links/:id/toggle', requireAuth, async (req: Request, res: Response) => {
  const id = Number(req.params.id);
  const link = await prisma.link.findFirst({ where: { id, userId: req.session.userId! } });
  if (!link) return res.status(404).render('404', { title: 'Link not found' });

  await prisma.link.update({ where: { id }, data: { enabled: !link.enabled } });
  await redisClient.del(`slug:${link.slug}`);
  res.redirect('/dashboard');
});

async function createLink({
  userId,
  longUrl,
  redirectType,
  expiresAt,
}: {
  userId: number;
  longUrl: string;
  redirectType: number;
  expiresAt: Date | null;
}) {
  const keywords = await prisma.keyword.findMany({ where: { active: true }, orderBy: { id: 'asc' } });
  if (keywords.length === 0) {
    throw new Error('No active keywords available');
  }

  return prisma.$transaction(async (tx) => {
    const settingsRows = await tx.$queryRaw<{ id: number; nextKeywordIndex: number; nextCodeCounter: bigint }[]>`
      SELECT "id", "nextKeywordIndex", "nextCodeCounter" FROM "Setting" WHERE "id" = 1 FOR UPDATE
    `;

    if (settingsRows.length === 0) {
      throw new Error('Settings row missing');
    }

    const currentSettings = settingsRows[0];
    const keywordIndex = currentSettings.nextKeywordIndex;
    const codeCounter = BigInt(currentSettings.nextCodeCounter);
    const keyword = keywords[keywordIndex % keywords.length].value;

    let attempts = 0;
    while (attempts < 5) {
      const counter = codeCounter + BigInt(attempts);
      const code = encodeCounter(counter);
      const slug = `${keyword}-${code}`;
      try {
        const created = await tx.link.create({
          data: {
            userId,
            longUrl,
            redirectType,
            expiresAt: expiresAt || undefined,
            keyword,
            code,
            slug,
          },
        });

        await tx.setting.update({
          where: { id: 1 },
          data: {
            nextKeywordIndex: (keywordIndex + 1) % keywords.length,
            nextCodeCounter: counter + BigInt(1),
          },
        });

        return created;
      } catch (error: any) {
        if (error?.code === 'P2002') {
          attempts += 1;
          continue;
        }
        throw error;
      }
    }

    throw new Error('Unable to generate unique slug after retries');
  });
}

export default router;
