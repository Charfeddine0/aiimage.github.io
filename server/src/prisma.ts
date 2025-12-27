import { PrismaClient } from '@prisma/client';
import fs from 'fs';
import path from 'path';

const databaseUrl = process.env.DATABASE_URL;

if (databaseUrl?.startsWith('file:')) {
  const dbPath = databaseUrl.replace('file:', '');
  const absolutePath = path.isAbsolute(dbPath) ? dbPath : path.resolve(__dirname, '..', dbPath);
  const dir = path.dirname(absolutePath);
  try {
    fs.mkdirSync(dir, { recursive: true });
  } catch (err) {
    console.error('Unable to create SQLite directory', { dir, error: err });
  }
}

const prisma = new PrismaClient();

export default prisma;
