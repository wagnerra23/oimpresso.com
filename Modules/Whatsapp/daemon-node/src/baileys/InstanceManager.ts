import { readdir, readFile, stat } from 'node:fs/promises';
import { join } from 'node:path';
import type { Logger } from 'pino';
import type { Env } from '../config/env';
import type { WebhookDispatcher } from '../webhook/WebhookDispatcher';
import { instanceDir } from './authState';
import { Instance, type InstanceMeta, type InstanceSnapshot } from './Instance';

export interface BootstrapResult {
  scanned: number;
  reconnected: number;
  failed: number;
  skipped: number;
}

// Shape do meta.json persistido por Instance.persistMeta no sucesso da
// conexão. Lido na boot pra restaurar business_uuid/business_id sem
// depender do app Hostinger.
interface PersistedMeta {
  instance_id: string;
  business_uuid: string;
  business_id: number | null;
  last_connected_at: string;
}

export class InstanceManager {
  private readonly instances = new Map<string, Instance>();

  constructor(
    private readonly env: Env,
    private readonly logger: Logger,
    private readonly webhook: WebhookDispatcher,
  ) {}

  list(): InstanceSnapshot[] {
    return [...this.instances.values()].map((i) => i.snapshot());
  }

  get(instanceId: string): Instance | undefined {
    return this.instances.get(instanceId);
  }

  async connect(meta: InstanceMeta): Promise<Instance> {
    let instance = this.instances.get(meta.instance_id);
    if (!instance) {
      if (this.instances.size >= this.env.MAX_INSTANCES) {
        throw new Error(`MAX_INSTANCES (${this.env.MAX_INSTANCES}) atingido`);
      }
      instance = new Instance(meta, this.env, this.logger.child({ instance_id: meta.instance_id }), this.webhook);
      this.instances.set(meta.instance_id, instance);
    }
    await instance.connect();
    return instance;
  }

  async disconnect(instanceId: string): Promise<void> {
    const instance = this.instances.get(instanceId);
    if (!instance) return;
    await instance.disconnect();
  }

  async purge(instanceId: string): Promise<void> {
    const instance = this.instances.get(instanceId);
    if (!instance) return;
    await instance.purgeSession();
    this.instances.delete(instanceId);
  }

  async shutdownAll(): Promise<void> {
    await Promise.allSettled([...this.instances.values()].map((i) => i.disconnect()));
    this.instances.clear();
  }

  /**
   * Auto-reconnect das instances ao boot do daemon (Camada 1 self-healing).
   *
   * Cenário: após `docker compose up -d` ou crash recovery do CT 100, os
   * subdir de session em `SESSIONS_DIR/ch-{uuid}/` continuam no disco mas
   * o daemon novo não tem nenhuma `Instance` viva em memória. Antes deste
   * método, Wagner precisava abrir a UI e clicar "Conectar" em cada canal
   * pra ressuscitar a sessão WhatsApp.
   *
   * Algoritmo:
   * 1. Scan `SESSIONS_DIR` por subpastas `ch-{...}` (padrão de naming).
   * 2. Pra cada, lê `meta.json` (escrito por `Instance.persistMeta` em todo
   *    `connection.update === 'open'`) — recupera `business_uuid` +
   *    `business_id`.
   * 3. Confere `creds.json` existente (auth state do Baileys). Sem
   *    `creds.json` a sessão não tem como auto-resumir → skip.
   * 4. Cria instance + dispara `connect()` em **background** (sem await).
   *    Baileys auto-resume com auth válido (sem QR).
   *
   * Sessions "legacy" (criadas antes deste PR, sem `meta.json`) são
   * puladas com log warn — usuário precisa clicar "Conectar" 1× pra
   * popular o meta; a partir daí auto-reconnect funciona.
   *
   * NÃO bloqueia o server.listen — `server.ts` chama sem await + log do
   * resultado.
   */
  async bootstrap(): Promise<BootstrapResult> {
    const sessionsDir = this.env.SESSIONS_DIR;
    const result: BootstrapResult = { scanned: 0, reconnected: 0, failed: 0, skipped: 0 };

    let entries;
    try {
      entries = await readdir(sessionsDir, { withFileTypes: true });
    } catch (err) {
      // Diretório ausente é OK (primeira boot ever) — só loga e retorna.
      this.logger.warn(
        { err: (err as Error).message, sessions_dir: sessionsDir },
        'bootstrap: sessions dir inacessível — skip',
      );
      return result;
    }

    const channelDirs = entries.filter((e) => e.isDirectory() && e.name.startsWith('ch-'));
    result.scanned = channelDirs.length;

    this.logger.info({ scanned: result.scanned, sessions_dir: sessionsDir }, 'bootstrap: scan sessions');

    for (const dir of channelDirs) {
      const instanceId = dir.name;
      try {
        const meta = await this.readPersistedMeta(instanceId);
        if (!meta) {
          this.logger.warn(
            { instance_id: instanceId },
            'bootstrap: sessão legacy sem meta.json — skip (usuário precisa clicar Conectar 1x)',
          );
          result.skipped++;
          continue;
        }

        const hasAuth = await this.hasAuthState(instanceId);
        if (!hasAuth) {
          this.logger.warn(
            { instance_id: instanceId },
            'bootstrap: creds.json ausente — auth state inválido, skip',
          );
          result.skipped++;
          continue;
        }

        // Cria via createOrGet (idempotente — não duplica se algo já existir).
        // connect() dispara async pra não bloquear scan dos demais.
        const restoredMeta: InstanceMeta = {
          instance_id: instanceId,
          business_uuid: meta.business_uuid,
          ...(typeof meta.business_id === 'number' ? { business_id: meta.business_id } : {}),
        };
        const instance = this.createOrGet(restoredMeta);

        instance.connect().catch((err) => {
          this.logger.error(
            { instance_id: instanceId, err: (err as Error).message },
            'bootstrap: auto-reconnect falhou (instance segue criada mas em estado degraded)',
          );
        });

        result.reconnected++;
      } catch (err) {
        // Erro inesperado em 1 instance não pode quebrar o scan das outras.
        this.logger.error(
          { instance_id: instanceId, err: (err as Error).message },
          'bootstrap: erro inesperado — continua próxima',
        );
        result.failed++;
      }
    }

    return result;
  }

  /**
   * createOrGet — usado pelo bootstrap. Não chama `connect()` direto pra
   * permitir o caller disparar em background.
   */
  private createOrGet(meta: InstanceMeta): Instance {
    let instance = this.instances.get(meta.instance_id);
    if (instance) return instance;
    if (this.instances.size >= this.env.MAX_INSTANCES) {
      throw new Error(`MAX_INSTANCES (${this.env.MAX_INSTANCES}) atingido`);
    }
    instance = new Instance(
      meta,
      this.env,
      this.logger.child({ instance_id: meta.instance_id }),
      this.webhook,
    );
    this.instances.set(meta.instance_id, instance);
    return instance;
  }

  private async readPersistedMeta(instanceId: string): Promise<PersistedMeta | null> {
    try {
      const path = join(instanceDir(this.env.SESSIONS_DIR, instanceId), 'meta.json');
      const raw = await readFile(path, 'utf8');
      const parsed = JSON.parse(raw) as Partial<PersistedMeta>;
      if (typeof parsed.business_uuid !== 'string' || parsed.business_uuid.length === 0) {
        return null;
      }
      return {
        instance_id: parsed.instance_id ?? instanceId,
        business_uuid: parsed.business_uuid,
        business_id: typeof parsed.business_id === 'number' ? parsed.business_id : null,
        last_connected_at: parsed.last_connected_at ?? '',
      };
    } catch {
      return null;
    }
  }

  private async hasAuthState(instanceId: string): Promise<boolean> {
    try {
      const path = join(instanceDir(this.env.SESSIONS_DIR, instanceId), 'creds.json');
      const st = await stat(path);
      return st.isFile();
    } catch {
      return false;
    }
  }
}
