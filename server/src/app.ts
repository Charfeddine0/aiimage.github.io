import express from 'express';
import session from 'express-session';
import RedisStore from 'connect-redis';
import path from 'path';
import dotenv from 'dotenv';
import helmet from 'helmet';
import compression from 'compression';
import morgan from 'morgan';
import csrf from 'csurf';
import redisClient from './redis';
import { attachUser } from './middleware/auth';
import pagesRouter from './routes/pages';
import authRouter from './routes/auth';
import linksRouter from './routes/links';

dotenv.config();

const app = express();

const store = new RedisStore({ client: redisClient });

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));

app.use(helmet());
app.use(compression());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, 'public')));
app.use(morgan('dev'));

app.use(
  session({
    store,
    secret: process.env.SESSION_SECRET || 'secret',
    resave: false,
    saveUninitialized: false,
    cookie: {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'lax',
      maxAge: 1000 * 60 * 60 * 24 * 7,
    },
  })
);

app.use(csrf());

app.use((req, res, next) => {
  res.locals.csrfToken = req.csrfToken();
  next();
});

app.use(attachUser);

app.use('/', pagesRouter);
app.use('/', authRouter);
app.use('/', linksRouter);

app.use((err: any, req: express.Request, res: express.Response, next: express.NextFunction) => {
  if (err.code === 'EBADCSRFTOKEN') {
    return res.status(403).render('404', { title: 'Invalid CSRF token', message: 'Invalid CSRF token' });
  }
  return next(err);
});

export default app;
