import type { FastifyPluginAsync } from 'fastify';
import fp from 'fastify-plugin';
import { ZodError } from 'zod';

const errorHandlerPlugin: FastifyPluginAsync = async (app) => {
  app.setErrorHandler((err, req, reply) => {
    if (err instanceof ZodError) {
      return reply.code(422).send({
        error: 'validation_error',
        message: 'request body or params invalid',
        details: err.flatten(),
      });
    }

    if ('statusCode' in err && typeof err.statusCode === 'number' && err.statusCode < 500) {
      return reply.code(err.statusCode).send({
        error: err.name || 'http_error',
        message: err.message,
      });
    }

    req.log.error({ err }, 'unhandled error');
    return reply.code(500).send({
      error: 'internal_error',
      message: 'internal server error',
    });
  });

  app.setNotFoundHandler((req, reply) => {
    reply.code(404).send({ error: 'not_found', message: `route ${req.method} ${req.url} not found` });
  });
};

export default fp(errorHandlerPlugin, { name: 'error-handler' });
