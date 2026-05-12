import { readFileSync } from 'node:fs';
import { z } from 'zod';

const numberFromString = (def: number) =>
  z
    .string()
    .optional()
    .transform((v) => (v === undefined || v === '' ? def : Number(v)))
    .pipe(z.number().int().positive());

const boolFromString = (def: boolean) =>
  z
    .string()
    .optional()
    .transform((v) => {
      if (v === undefined || v === '') return def;
      return ['1', 'true', 'yes', 'on'].includes(v.toLowerCase());
    });

const schema = z.object({
  NODE_ENV: z.enum(['development', 'production', 'test']).default('production'),

  HTTP_HOST: z.string().default('0.0.0.0'),
  HTTP_PORT: numberFromString(3000),
  HTTP_BODY_LIMIT_BYTES: numberFromString(2 * 1024 * 1024),

  API_KEY: z.string().min(16, 'API_KEY deve ter ao menos 16 caracteres').optional(),
  API_KEY_FILE: z.string().optional(),

  WEBHOOK_BASE_URL: z.string().url(),
  WEBHOOK_TIMEOUT_MS: numberFromString(10_000),
  WEBHOOK_MAX_RETRIES: numberFromString(5),
  WEBHOOK_BACKOFF_BASE_MS: numberFromString(1_000),

  SESSIONS_DIR: z.string().default('./var/sessions'),

  LOG_LEVEL: z.enum(['fatal', 'error', 'warn', 'info', 'debug', 'trace']).default('info'),
  LOG_PRETTY: boolFromString(false),

  OTEL_ENABLED: boolFromString(true),
  OTEL_SERVICE_NAME: z.string().default('whatsapp-baileys-daemon'),
  OTEL_EXPORTER_OTLP_ENDPOINT: z.string().url().optional(),
  OTEL_EXPORTER_OTLP_PROTOCOL: z.enum(['http/protobuf', 'http/json', 'grpc']).default('http/protobuf'),

  METRICS_ENABLED: boolFromString(true),
  METRICS_ROUTE: z.string().default('/metrics'),

  MAX_INSTANCES: numberFromString(30),
  INSTANCE_CONNECT_TIMEOUT_MS: numberFromString(60_000),
  QR_TIMEOUT_MS: numberFromString(120_000),

  // Anti-ban middleware (US-WA-094) — jitter Gaussian + typing presence + warmup
  // quota per-instance. Defaults seguros: ENABLED=true em prod, dev/test
  // setam ANTIBAN_ENABLED=false no .env pra evitar slow tests (1.5-4s/msg).
  ANTIBAN_ENABLED: boolFromString(true),
  ANTIBAN_JITTER_MIN_MS: numberFromString(1_500),
  ANTIBAN_JITTER_MAX_MS: numberFromString(4_000),
  ANTIBAN_TYPING_MS: numberFromString(500),
  ANTIBAN_WARMUP_DAYS: numberFromString(7),
  // P0 #1 — auth state backend (filesystem default = dev, mysql = prod).
  // Filesystem mantém useMultiFileAuthState (NÃO usar em prod — corrupção FS = session revogada).
  // MySQL usa useMySQLAuthState custom com AES-256-CBC + APP_KEY Laravel.
  AUTH_STATE_BACKEND: z.enum(['filesystem', 'mysql']).default('filesystem'),
  WHATSAPP_AUTH_STATE_ENCRYPTION_KEY: z.string().optional(),
  MYSQL_AUTH_STATE_HOST: z.string().default('127.0.0.1'),
  MYSQL_AUTH_STATE_PORT: numberFromString(3306),
  MYSQL_AUTH_STATE_USER: z.string().optional(),
  MYSQL_AUTH_STATE_PASS: z.string().optional(),
  MYSQL_AUTH_STATE_DB: z.string().optional(),
});

export type Env = z.infer<typeof schema> & { API_KEY: string };

function resolveApiKey(parsed: z.infer<typeof schema>): string {
  if (parsed.API_KEY && parsed.API_KEY.length >= 16) return parsed.API_KEY;
  if (parsed.API_KEY_FILE) {
    const content = readFileSync(parsed.API_KEY_FILE, 'utf8').trim();
    if (content.length < 16) {
      throw new Error(`API_KEY_FILE conteúdo curto (<16 chars): ${parsed.API_KEY_FILE}`);
    }
    return content;
  }
  throw new Error('Nenhum API_KEY ou API_KEY_FILE configurado');
}

let cached: Env | undefined;

export function loadEnv(): Env {
  if (cached) return cached;
  const result = schema.safeParse(process.env);
  if (!result.success) {
    const flat = result.error.flatten().fieldErrors;
    const msg = Object.entries(flat)
      .map(([k, v]) => `${k}: ${(v ?? []).join(', ')}`)
      .join('; ');
    throw new Error(`Configuração inválida — ${msg}`);
  }
  cached = { ...result.data, API_KEY: resolveApiKey(result.data) };
  return cached;
}
