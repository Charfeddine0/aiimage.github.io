FROM node:20-alpine AS builder
WORKDIR /app
COPY package.json package-lock.json* tsconfig.json .eslintrc.json .env.example ./
COPY prisma ./prisma
COPY src ./src
COPY keywords.txt ./keywords.txt
RUN npm install
RUN npm run build
RUN npx prisma generate

FROM node:20-alpine
WORKDIR /app
ENV NODE_ENV=production
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/dist ./dist
COPY --from=builder /app/prisma ./prisma
COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/.env.example ./
COPY --from=builder /app/keywords.txt ./keywords.txt

CMD ["sh", "-c", "npx prisma migrate deploy && npx prisma db seed && node dist/index.js"]
