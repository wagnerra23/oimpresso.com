import { timingSafeEqual } from 'node:crypto';
import type { FastifyPluginAsync } from 'fastify';
import fp from 'fastify-plugin';
import type { Env } from '../../config/env.js';

declare module 'fastify' {
  interface FastifyInstance {
    requireBearer: (token: string) => boolean;
  }
}

const PUBLIC_ROUTES = new Set<string>(['/health', '/metrics']);

const authPlugin: FastifyPluginAsync<{ env: Env }> = async (app, opts) => {
  const expected = Buffer.from(opts.env.API_KEY, 'utf8');

  const compare = (provided: string): boolean => {
    const buf = Buffer.from(provided, 'utf8');
    if (buf.length !== expected.length) return false;
    return timingSafeEqual(buf, expected);
  };

  app.decorate('requireBearer', compare);

  app.addHook('onRequest', async (req, reply) => {
    if (PUBLIC_ROUTES.has(req.routerPath ?? req.url)) return;

    const header = req.headers.authorization;
    if (!header || !header.startsWith('Bearer ')) {
      return reply.code(401).send({ error: 'unauthorized', message: 'missing bearer token' });
    }
    if (!compare(header.slice(7).trim())) {
      return reply.code(401).send({ error: 'unauthorized', message: 'invalid bearer token' });
    }
  });
};

export default fp(authPlugin, { name: 'auth' });
