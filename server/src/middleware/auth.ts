import { Request, Response, NextFunction } from 'express';

export function requireAuth(req: Request, res: Response, next: NextFunction) {
  if (req.session && req.session.userId) {
    return next();
  }
  return res.redirect('/login');
}

export function attachUser(req: Request, res: Response, next: NextFunction) {
  res.locals.currentUser = req.session?.userId ? { id: req.session.userId, email: req.session.email } : null;
  next();
}
