import { Request, Response, NextFunction } from 'express';
import redisClient from '../redis';

interface RateLimitConfig {
  key: string;
  limit: number;
  windowSeconds: number;
}

async function hitLimit({ key, limit, windowSeconds }: RateLimitConfig): Promise<boolean> {
  const client = redisClient;

  if (!client || !client.isOpen) {
    return false;
  }

  const ttlKey = `rl:${key}`;
  const multi = client.multi();
  multi.incr(ttlKey);
  multi.pttl(ttlKey);
  const result = await multi.exec();
  if (!result) return false;
  const [countRaw, ttlRaw] = result;
  const count = typeof countRaw === 'number' ? countRaw : Number(countRaw ?? 0);
  const ttl = typeof ttlRaw === 'number' ? ttlRaw : Number(ttlRaw ?? -2);

  if (Number.isNaN(count) || Number.isNaN(ttl)) return false;

  if (ttl === -1) {
    await client.pexpire(ttlKey, windowSeconds * 1000);
  }

  if (count > limit) {
    return true;
  }

  if (ttl === -2) {
    await client.pexpire(ttlKey, windowSeconds * 1000);
  }

  return false;
}

export function rateLimit(keyBuilder: (req: Request) => string, limit: number, windowSeconds: number) {
  return async (req: Request, res: Response, next: NextFunction) => {
    try {
      const key = keyBuilder(req);
      const limited = await hitLimit({ key, limit, windowSeconds });
      if (limited) {
        return res.status(429).render('404', { title: 'Too many requests', message: 'Rate limit exceeded. Please try again later.' });
      }
      return next();
    } catch (err) {
      console.error('Rate limiter error', err);
      return res.status(429).render('404', { title: 'Too many requests', message: 'Rate limit exceeded. Please try again later.' });
    }
  };
}

export function ipKey(prefix: string) {
  return (req: Request) => `${prefix}:ip:${req.ip}`;
}

export function userKey(prefix: string) {
  return (req: Request) => `${prefix}:user:${req.session.userId ?? 'anon'}`;
}
