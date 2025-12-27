import { Router, Request, Response } from 'express';
import bcrypt from 'bcrypt';
import prisma from '../prisma';
import { rateLimit, ipKey } from '../middleware/rateLimit';

const router = Router();

router.get('/register', (_req: Request, res: Response) => {
  res.render('register', { title: 'Register', error: null });
});

router.post('/register', rateLimit(ipKey('register'), 10, 60), async (req: Request, res: Response) => {
  const { email, password } = req.body;
  if (!email || !password) {
    return res.status(400).render('register', { title: 'Register', error: 'Email and password are required' });
  }

  const existing = await prisma.user.findUnique({ where: { email } });
  if (existing) {
    return res.status(400).render('register', { title: 'Register', error: 'Email already registered' });
  }

  const hashed = await bcrypt.hash(password, 10);
  const user = await prisma.user.create({ data: { email, password: hashed } });
  req.session.userId = user.id;
  req.session.email = user.email;
  res.redirect('/dashboard');
});

router.get('/login', (_req: Request, res: Response) => {
  res.render('login', { title: 'Login', error: null });
});

router.post('/login', rateLimit(ipKey('login'), 10, 60), async (req: Request, res: Response) => {
  const { email, password } = req.body;
  if (!email || !password) {
    return res.status(400).render('login', { title: 'Login', error: 'Email and password are required' });
  }

  const user = await prisma.user.findUnique({ where: { email } });
  if (!user) {
    return res.status(401).render('login', { title: 'Login', error: 'Invalid credentials' });
  }

  const valid = await bcrypt.compare(password, user.password);
  if (!valid) {
    return res.status(401).render('login', { title: 'Login', error: 'Invalid credentials' });
  }

  req.session.userId = user.id;
  req.session.email = user.email;
  res.redirect('/dashboard');
});

router.post('/logout', (req: Request, res: Response) => {
  req.session.destroy(() => {
    res.redirect('/');
  });
});

export default router;
