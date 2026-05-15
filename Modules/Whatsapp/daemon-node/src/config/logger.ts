import { pino, type Logger } from 'pino';
import type { Env } from './env.js';

export function createRootLogger(env: Env): Logger {
  const transport = env.LOG_PRETTY
    ? {
        target: 'pino-pretty',
        options: { colorize: true, singleLine: true, translateTime: 'SYS:HH:MM:ss.l' },
      }
    : undefined;

  return pino({
    level: env.LOG_LEVEL,
    base: {
      service: env.OTEL_SERVICE_NAME,
      env: env.NODE_ENV,
    },
    timestamp: pino.stdTimeFunctions.isoTime,
    redact: {
      paths: [
        'req.headers.authorization',
        'headers.authorization',
        'apiKey',
        'api_key',
        '*.api_key',
        '*.apiKey',
        '*.phone',
        '*.to',
        '*.from',
        '*.jid',
        'msg.key.remoteJid',
        'data.from',
        'data.to',
      ],
      censor: '[REDACTED]',
    },
    ...(transport ? { transport } : {}),
  });
}
