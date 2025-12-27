import { createClient, RedisClientType } from 'redis';
import dotenv from 'dotenv';

dotenv.config();

const redisUrl = process.env.REDIS_URL;

let redisClient: RedisClientType | null = null;

if (!redisUrl) {
  console.warn('REDIS_URL not set. Redis-backed caching, sessions, and rate limiting are disabled.');
} else {
  redisClient = createClient({ url: redisUrl });

  redisClient.on('error', (err) => {
    console.error('Redis error', err);
  });

  redisClient
    .connect()
    .then(() => {
      console.log('Redis connected');
    })
    .catch((err) => {
      console.error('Redis connection error', err);
    });
}

export default redisClient;
