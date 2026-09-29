// ──────────────────────────────────────────────────────────────
// Inicialização tRPC — procedures base com autenticação + RBAC.
// `protectedProcedure` exige sessão; o restante da autorização
// (por recurso/ação) é feito no domínio com policy.assertCan().
// ──────────────────────────────────────────────────────────────
import { initTRPC, TRPCError } from "@trpc/server";
import type { Context } from "./context";

const t = initTRPC.context<Context>().create();

export const router = t.router;
export const middleware = t.middleware;
export const publicProcedure = t.procedure;

/** Exige usuário autenticado (sessão resolvida em createContext). */
export const protectedProcedure = t.procedure.use(({ ctx, next }) => {
  if (!ctx.user) {
    throw new TRPCError({ code: "UNAUTHORIZED", message: "Sessão necessária" });
  }
  return next({ ctx: { ...ctx, user: ctx.user } });
});
