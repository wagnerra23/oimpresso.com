// IMPORTANTE: OTel deve ser inicializado antes de qualquer require de bibliotecas instrumentadas.
import { loadEnv } from './config/env';
import { startOtel, shutdownOtel } from './observability/otel';

const env = loadEnv();
startOtel(env);

import Fastify from 'fastify';
import { createRootLogger } from './config/logger';
import { WebhookDispatcher } from './webhook/WebhookDispatcher';
import { InstanceManager } from './baileys/InstanceManager';
import authPlugin from './http/plugins/auth';
import errorHandlerPlugin from './http/plugins/errorHandler';
import { healthRoutes } from './http/routes/health';
import { instanceRoutes } from './http/routes/instances';
import { mediaRoutes } from './http/routes/media';
import { messageRoutes } from './http/routes/messages';

async function main(): Promise<void> {
  const logger = createRootLogger(env);
  const startedAt = new Date();

  const app = Fastify({
    logger,
    bodyLimit: env.HTTP_BODY_LIMIT_BYTES,
    disableRequestLogging: env.NODE_ENV === 'production',
    trustProxy: true,
    requestIdHeader: 'x-request-id',
    requestIdLogLabel: 'req_id',
  });

  const webhook = new WebhookDispatcher(env, logger.child({ scope: 'webhook' }));
  const manager = new InstanceManager(env, logger.child({ scope: 'instances' }), webhook);

  await app.register(errorHandlerPlugin);
  await app.register(authPlugin, { env });
  await app.register(healthRoutes, { manager, env, startedAt });
  await app.register(instanceRoutes, { manager });
  await app.register(messageRoutes, { manager });
  await app.register(mediaRoutes, { prefix: '/media' });

  let shuttingDown = false;
  const shutdown = async (signal: string): Promise<void> => {
    if (shuttingDown) return;
    shuttingDown = true;
    logger.info({ signal }, 'shutdown initiated');
    const timeout = setTimeout(() => {
      logger.error('shutdown timed out, forcing exit');
      process.exit(1);
    }, 30_000);
    timeout.unref();

    try {
      await app.close();
      await manager.shutdownAll();
      await shutdownOtel();
      logger.info('shutdown complete');
      process.exit(0);
    } catch (err) {
      logger.error({ err }, 'error during shutdown');
      process.exit(1);
    }
  };

  process.on('SIGTERM', () => void shutdown('SIGTERM'));
  process.on('SIGINT', () => void shutdown('SIGINT'));
  process.on('unhandledRejection', (reason) => logger.error({ reason }, 'unhandledRejection'));
  process.on('uncaughtException', (err) => {
    logger.fatal({ err }, 'uncaughtException');
    void shutdown('uncaughtException');
  });

  await app.listen({ host: env.HTTP_HOST, port: env.HTTP_PORT });
  logger.info(
    { host: env.HTTP_HOST, port: env.HTTP_PORT, otel: env.OTEL_ENABLED, max_instances: env.MAX_INSTANCES },
    'whatsapp-baileys-daemon ready',
  );

  // Camada 1 self-healing — auto-reconnect das instances com session salva.
  // Background (sem await) pra não bloquear /health enquanto Baileys
  // handshake pode demorar 5-15s por canal. Falha aqui NÃO derruba o
  // daemon: log error + segue (manager continua aceitando connect via API).
  manager
    .bootstrap()
    .then((result) => {
      logger.info(result, 'bootstrap auto-reconnect completo');
    })
    .catch((err) => {
      logger.error({ err: (err as Error).message }, 'bootstrap auto-reconnect falhou (daemon segue OK)');
    });
}

main().catch((err) => {
  // eslint-disable-next-line no-console
  console.error('fatal bootstrap error', err);
  process.exit(1);
});
