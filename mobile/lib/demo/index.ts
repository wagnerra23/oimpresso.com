import { TRPCClientError, type TRPCLink } from "@trpc/client";
import { observable } from "@trpc/server/observable";

import type { User } from "@/lib/_core/auth";
import type { AppRouter } from "@/server/routers";

import { handlers } from "./handlers";

export { resetDemoStore } from "./handlers";

/**
 * Demo mode — enabled at build time with `EXPO_PUBLIC_DEMO_MODE=1`.
 *
 * Signs in with a fixed user and swaps the tRPC backend for an in-browser
 * store seeded with sample data (see `./seed.ts` and `./handlers.ts`), so the
 * app runs with no API server, database, OAuth or third-party integrations.
 * Regular builds are unaffected.
 */
export const DEMO_MODE = process.env.EXPO_PUBLIC_DEMO_MODE === "1";

export const DEMO_USER: User = {
  id: 1,
  openId: "demo",
  name: "Usuário Demo",
  email: "demo@oimpresso.exemplo.com.br",
  loginMethod: "demo",
  lastSignedIn: new Date(),
};

const LATENCY_MS = 180;

/**
 * Terminating tRPC link that answers every procedure from the demo store,
 * with a short delay so loading states still show.
 */
export function demoLink(): TRPCLink<AppRouter> {
  return () =>
    ({ op }) =>
      observable((observer) => {
        const timer = setTimeout(() => {
          const handler = handlers.get(op.path);
          try {
            if (!handler) {
              throw new Error(`"${op.path}" não está disponível na demonstração.`);
            }
            // Return a copy so callers can't mutate the store through the cache.
            const data = structuredClone(handler(op.input));
            observer.next({ result: { type: "data", data } });
            observer.complete();
          } catch (err) {
            observer.error(TRPCClientError.from(err as Error));
          }
        }, LATENCY_MS);
        return () => clearTimeout(timer);
      });
}
