// Entry do servidor tRPC (adapter standalone). Troque por Express/Fastify
// quando precisar de middlewares HTTP adicionais.
import { createHTTPServer } from "@trpc/server/adapters/standalone";
import { appRouter } from "./trpc/appRouter";
import { createContext } from "./trpc/context";

const port = Number(process.env.PORT ?? 4000);

const server = createHTTPServer({
  router: appRouter,
  createContext: ({ req }) => createContext({ req: { headers: req.headers as Record<string, string | undefined> } }),
});

server.listen(port);
// eslint-disable-next-line no-console
console.log(`Oimpresso API ouvindo em http://localhost:${port}`);
