const CHARSET = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

export function encodeCounter(counter: bigint): string {
  if (counter < 0) {
    throw new Error('Counter must be non-negative');
  }
  const base = BigInt(CHARSET.length);
  if (counter === BigInt(0)) return CHARSET[0];
  let value = counter;
  let result = '';
  while (value > BigInt(0)) {
    const remainder = Number(value % base);
    result = CHARSET[remainder] + result;
    value = value / base;
  }
  return result;
}
