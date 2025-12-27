import crypto from 'crypto';

export function hashIp(ip: string, salt: string): string {
  return crypto.createHash('sha256').update(`${ip}${salt}`).digest('hex');
}
