import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Logger } from 'pino';
import type { Env } from '../config/env';
import type { WebhookDispatcher } from '../webhook/WebhookDispatcher';
import { InstanceManager } from './InstanceManager';
import { Instance } from './Instance';

// ------------------------------------------------------------------------------------------------
// Camada 1 self-healing — vitest spec do InstanceManager.bootstrap()
//
// Setup: cria SESSIONS_DIR real em /tmp pra cada teste, popula com
// fixtures (ch-{uuid}/{creds.json,meta.json}) que simulam sessions
// persistidas, então roda bootstrap e valida que:
//  - sessions completas viram instance criada + connect() chamado
//  - sessions sem meta.json (legacy) são puladas com log warn
//  - sessions sem creds.json (auth inválido) são puladas
//  - falha em 1 connect() NÃO derruba o bootstrap dos outros
//  - SESSIONS_DIR vazio retorna 0/0/0
//
// `Instance.connect` é mockado via prototype spy pra evitar subir socket
// Baileys real (que tentaria conectar no WhatsApp).
// ------------------------------------------------------------------------------------------------

function makeLogger(): Logger {
  const noop = () => undefined;
  const logger = {
    info: vi.fn(),
    warn: vi.fn(),
    error: vi.fn(),
    debug: vi.fn(),
    fatal: vi.fn(),
    trace: vi.fn(),
    child: vi.fn(),
  } as unknown as Logger;
  // child retorna o próprio mock pra simplificar
  (logger.child as unknown as ReturnType<typeof vi.fn>).mockReturnValue(logger);
  void noop;
  return logger;
}

function makeWebhook(): WebhookDispatcher {
  return {
    dispatch: vi.fn().mockResolvedValue(undefined),
  } as unknown as WebhookDispatcher;
}

function makeEnv(sessionsDir: string): Env {
  return {
    NODE_ENV: 'test',
    HTTP_HOST: '0.0.0.0',
    HTTP_PORT: 3000,
    HTTP_BODY_LIMIT_BYTES: 2 * 1024 * 1024,
    API_KEY: 'test-api-key-1234567890',
    WEBHOOK_BASE_URL: 'http://localhost:8000/webhook',
    WEBHOOK_TIMEOUT_MS: 10_000,
    WEBHOOK_MAX_RETRIES: 5,
    WEBHOOK_BACKOFF_BASE_MS: 1_000,
    SESSIONS_DIR: sessionsDir,
    LOG_LEVEL: 'info',
    LOG_PRETTY: false,
    OTEL_ENABLED: false,
    OTEL_SERVICE_NAME: 'test',
    OTEL_EXPORTER_OTLP_PROTOCOL: 'http/protobuf',
    METRICS_ENABLED: false,
    METRICS_ROUTE: '/metrics',
    MAX_INSTANCES: 30,
    INSTANCE_CONNECT_TIMEOUT_MS: 60_000,
    QR_TIMEOUT_MS: 120_000,
  } as Env;
}

async function seedSession(
  baseDir: string,
  instanceId: string,
  opts: { meta?: boolean; creds?: boolean } = { meta: true, creds: true },
): Promise<void> {
  const dir = join(baseDir, instanceId);
  await mkdir(dir, { recursive: true });
  if (opts.creds !== false) {
    await writeFile(join(dir, 'creds.json'), JSON.stringify({ noiseKey: 'fake' }), 'utf8');
  }
  if (opts.meta !== false) {
    await writeFile(
      join(dir, 'meta.json'),
      JSON.stringify({
        instance_id: instanceId,
        business_uuid: `biz-uuid-${instanceId}`,
        business_id: 1,
        last_connected_at: new Date().toISOString(),
      }),
      'utf8',
    );
  }
}

describe('InstanceManager.bootstrap', () => {
  let baseDir: string;
  let connectSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(async () => {
    baseDir = await mkdtemp(join(tmpdir(), 'baileys-bootstrap-'));
    // Mocka Instance.connect — não queremos subir socket Baileys real.
    connectSpy = vi
      .spyOn(Instance.prototype, 'connect')
      .mockImplementation(async function (this: Instance) {
        // no-op — testa só que bootstrap chama o método, não o handshake.
      });
  });

  afterEach(async () => {
    connectSpy.mockRestore();
    await rm(baseDir, { recursive: true, force: true });
  });

  it('SESSIONS_DIR vazio — scanned=0, reconnected=0', async () => {
    const manager = new InstanceManager(makeEnv(baseDir), makeLogger(), makeWebhook());
    const result = await manager.bootstrap();
    expect(result).toEqual({ scanned: 0, reconnected: 0, failed: 0, skipped: 0 });
    expect(connectSpy).not.toHaveBeenCalled();
  });

  it('2 sessions com meta.json + creds.json válidos — 2 reconnects', async () => {
    await seedSession(baseDir, 'ch-abc123');
    await seedSession(baseDir, 'ch-def456');

    const manager = new InstanceManager(makeEnv(baseDir), makeLogger(), makeWebhook());
    const result = await manager.bootstrap();

    expect(result.scanned).toBe(2);
    expect(result.reconnected).toBe(2);
    expect(result.skipped).toBe(0);
    expect(result.failed).toBe(0);

    // connect() é disparado async (sem await) — aguarda microtasks
    await new Promise((r) => setImmediate(r));
    expect(connectSpy).toHaveBeenCalledTimes(2);

    // Instances ficam registradas no manager
    expect(manager.list()).toHaveLength(2);
    const ids = manager.list().map((i) => i.instance_id).sort();
    expect(ids).toEqual(['ch-abc123', 'ch-def456']);
  });

  it('session legacy (sem meta.json) — skip + log warn', async () => {
    await seedSession(baseDir, 'ch-legacy', { meta: false, creds: true });

    const logger = makeLogger();
    const manager = new InstanceManager(makeEnv(baseDir), logger, makeWebhook());
    const result = await manager.bootstrap();

    expect(result.scanned).toBe(1);
    expect(result.reconnected).toBe(0);
    expect(result.skipped).toBe(1);
    expect(connectSpy).not.toHaveBeenCalled();

    // Validar warn message contém o instance_id
    expect(logger.warn).toHaveBeenCalledWith(
      expect.objectContaining({ instance_id: 'ch-legacy' }),
      expect.stringContaining('legacy'),
    );
  });

  it('session sem creds.json (auth state ausente) — skip', async () => {
    await seedSession(baseDir, 'ch-no-auth', { meta: true, creds: false });

    const manager = new InstanceManager(makeEnv(baseDir), makeLogger(), makeWebhook());
    const result = await manager.bootstrap();

    expect(result.scanned).toBe(1);
    expect(result.reconnected).toBe(0);
    expect(result.skipped).toBe(1);
    expect(connectSpy).not.toHaveBeenCalled();
  });

  it('1 connect falha + 2 OK — não crash, conta como reconnected (erro async)', async () => {
    await seedSession(baseDir, 'ch-aaa');
    await seedSession(baseDir, 'ch-bbb');
    await seedSession(baseDir, 'ch-ccc');

    // Reset + reconfig: ch-bbb explode no connect
    connectSpy.mockImplementation(async function (this: Instance) {
      if (this.meta.instance_id === 'ch-bbb') {
        throw new Error('Connection Failure (fake)');
      }
    });

    const logger = makeLogger();
    const manager = new InstanceManager(makeEnv(baseDir), logger, makeWebhook());
    const result = await manager.bootstrap();

    // Bootstrap continua os 3 (erro é capturado no .catch() async)
    expect(result.scanned).toBe(3);
    expect(result.reconnected).toBe(3);
    expect(result.failed).toBe(0);

    await new Promise((r) => setImmediate(r));
    // Logger.error chamado pro ch-bbb (catch async do connect)
    expect(logger.error).toHaveBeenCalledWith(
      expect.objectContaining({ instance_id: 'ch-bbb' }),
      expect.stringContaining('falhou'),
    );
  });

  it('ignora subpastas que não começam com ch-', async () => {
    // Pasta lixo (ex: backup, lost+found) — deve ser ignorada
    await mkdir(join(baseDir, 'backup'), { recursive: true });
    await mkdir(join(baseDir, 'lost+found'), { recursive: true });
    await seedSession(baseDir, 'ch-valid');

    const manager = new InstanceManager(makeEnv(baseDir), makeLogger(), makeWebhook());
    const result = await manager.bootstrap();

    expect(result.scanned).toBe(1); // só ch-valid
    expect(result.reconnected).toBe(1);
  });

  it('meta.json inválido (business_uuid ausente) — skip graceful', async () => {
    const dir = join(baseDir, 'ch-broken');
    await mkdir(dir, { recursive: true });
    await writeFile(join(dir, 'creds.json'), JSON.stringify({ noiseKey: 'fake' }));
    await writeFile(join(dir, 'meta.json'), JSON.stringify({ instance_id: 'ch-broken' })); // falta business_uuid

    const manager = new InstanceManager(makeEnv(baseDir), makeLogger(), makeWebhook());
    const result = await manager.bootstrap();

    expect(result.scanned).toBe(1);
    expect(result.skipped).toBe(1);
    expect(result.reconnected).toBe(0);
  });

  it('SESSIONS_DIR inexistente — não crash, retorna zeros', async () => {
    const ghostDir = join(baseDir, 'does-not-exist');
    const logger = makeLogger();
    const manager = new InstanceManager(makeEnv(ghostDir), logger, makeWebhook());
    const result = await manager.bootstrap();

    expect(result).toEqual({ scanned: 0, reconnected: 0, failed: 0, skipped: 0 });
    expect(logger.warn).toHaveBeenCalledWith(
      expect.objectContaining({ sessions_dir: ghostDir }),
      expect.stringContaining('inacessível'),
    );
  });
});
