import { PrismaClient } from '@prisma/client';
import fs from 'fs';
import path from 'path';

const prisma = new PrismaClient();

async function main() {
  const keywordsPath = path.resolve(__dirname, '..', 'keywords.txt');
  const raw = fs.readFileSync(keywordsPath, 'utf-8');
  const keywords = raw
    .split(',')
    .map((k) => k.trim().toLowerCase())
    .filter((k) => k.length > 0);

  const uniqueKeywords = Array.from(new Set(keywords));

  if (uniqueKeywords.length === 0) {
    throw new Error('No keywords found in keywords.txt');
  }

  await prisma.keyword.createMany({
    data: uniqueKeywords.map((value) => ({ value })),
    skipDuplicates: true,
  });

  const existingSetting = await prisma.setting.findUnique({ where: { id: 1 } });
  if (!existingSetting) {
    await prisma.setting.create({
      data: {
        id: 1,
        nextKeywordIndex: 0,
        nextCodeCounter: BigInt(0),
      },
    });
  }
}

main()
  .catch((e) => {
    console.error(e);
    process.exit(1);
  })
  .finally(async () => {
    await prisma.$disconnect();
  });
