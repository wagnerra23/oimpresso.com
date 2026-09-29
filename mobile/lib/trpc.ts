import { createTRPCReact } from "@trpc/react-query";
import { httpBatchLink, TRPCClientError, type TRPCLink } from "@trpc/client";
import { observable } from "@trpc/server/observable";
import { router as expoRouter } from "expo-router";
import superjson from "superjson";
import type { AppRouter } from "@/server/routers";
import { getApiBaseUrl } from "@/constants/oauth";
import * as Auth from "@/lib/_core/auth";
import { DEMO_MODE, demoLink } from "@/lib/demo";

/**
 * Custom link that intercepts 401 / UNAUTHORIZED errors and logs the user out.
 * Token may have expired — clear local session and redirect to /login.
 */
const unauthorizedLink: TRPCLink<AppRouter> = () => {
  return ({ op, next }) =>
    observable((observer) => {
      const sub = next(op).subscribe({
        next: observer.next.bind(observer),
        complete: observer.complete.bind(observer),
        error: (err) => {
          if (
            err instanceof TRPCClientError &&
            (err.data?.httpStatus === 401 || err.data?.code === "UNAUTHORIZED")
          ) {
            Auth.removeSessionToken()
              .then(() => Auth.clearUserInfo())
              .finally(() => {
                try {
                  expoRouter.replace("/login");
                } catch {
                  // navigation may fail if router isn't mounted yet — ignore
                }
              });
          }
          observer.error(err);
        },
      });
      return () => sub.unsubscribe();
    });
};

/**
 * tRPC React client for type-safe API calls.
 *
 * IMPORTANT (tRPC v11): The `transformer` must be inside `httpBatchLink`,
 * NOT at the root createClient level. This ensures client and server
 * use the same serialization format (superjson).
 */
export const trpc = createTRPCReact<AppRouter>();

/**
 * Creates the tRPC client with proper configuration.
 * Call this once in your app's root layout.
 */
export function createTRPCClient() {
  if (DEMO_MODE) {
    return trpc.createClient({ links: [demoLink()] });
  }

  return trpc.createClient({
    links: [
      unauthorizedLink,
      httpBatchLink({
        url: `${getApiBaseUrl()}/api/trpc`,
        // tRPC v11: transformer MUST be inside httpBatchLink, not at root
        transformer: superjson,
        async headers() {
          const token = await Auth.getSessionToken();
          return token ? { Authorization: `Bearer ${token}` } : {};
        },
        // Custom fetch to include credentials for cookie-based auth
        fetch(url, options) {
          return fetch(url, {
            ...options,
            credentials: "include",
          });
        },
      }),
    ],
  });
}
