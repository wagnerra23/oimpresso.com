import type { Logger } from 'pino';
import type { Env } from '../config/env';
import type { WebhookDispatcher } from '../webhook/WebhookDispatcher';
import { Instance, type InstanceMeta, type InstanceSnapshot } from './Instance';

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
}
