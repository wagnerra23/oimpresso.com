import { TRPCError } from "@trpc/server";
import bcrypt from "bcryptjs";
import { z } from "zod";
import { COOKIE_NAME, ONE_YEAR_MS } from "../shared/const.js";
import { getSessionCookieOptions } from "./_core/cookies";
import { sdk } from "./_core/sdk";
import { systemRouter } from "./_core/systemRouter";
import { publicProcedure, router } from "./_core/trpc";
import * as db from "./db";
import { produtosRouter } from "./routers/produtos";
import { pedidosRouter } from "./routers/pedidos";
import { opsRouter } from "./routers/ops";
import { transacoesRouter } from "./routers/transacoes";
import { notificationsRouter } from "./routers/notifications";
import { customersRouter } from "./routers/customers";
import { quotesRouter } from "./routers/quotes";
import { artworksRouter } from "./routers/artworks";
import { inventoryRouter } from "./routers/inventory";
import { whatsappRouter } from "./routers/whatsapp";
import { vehiclesRouter } from "./routers/vehicles";
import { serviceOrdersRouter } from "./routers/serviceOrders";
import { reportsRouter } from "./routers/reports";
import { aiRouter } from "./routers/ai";
import { fiscalRouter } from "./routers/fiscal";
import { paymentsRouter } from "./routers/payments";
import { companiesRouter } from "./routers/companies";

const INVALID_CREDENTIALS = "Email ou senha incorretos";

function buildAuthResponse(user: NonNullable<Awaited<ReturnType<typeof db.getUserByOpenId>>>) {
  return {
    id: user.id,
    openId: user.openId,
    name: user.name,
    email: user.email,
    loginMethod: user.loginMethod,
    role: user.role,
    lastSignedIn: user.lastSignedIn.toISOString(),
  };
}

export const appRouter = router({
  // if you need to use socket.io, read and register route in server/_core/index.ts, all api should start with '/api/' so that the gateway can route correctly
  system: systemRouter,
  auth: router({
    me: publicProcedure.query((opts) => opts.ctx.user),
    logout: publicProcedure.mutation(({ ctx }) => {
      const cookieOptions = getSessionCookieOptions(ctx.req);
      ctx.res.clearCookie(COOKIE_NAME, { ...cookieOptions, maxAge: -1 });
      return {
        success: true,
      } as const;
    }),
    /**
     * Email + password login. Issues a JWT identical in shape to the OAuth
     * callback so the rest of the system (protectedProcedure, /api/auth/me,
     * client tRPC link) treats it the same.
     */
    loginWithPassword: publicProcedure
      .input(
        z.object({
          email: z.string().email("Email inválido"),
          password: z.string().min(1, "Informe a senha"),
        }),
      )
      .mutation(async ({ ctx, input }) => {
        const email = input.email.trim().toLowerCase();
        const user = await db.getUserByEmail(email);
        if (!user || !user.passwordHash) {
          throw new TRPCError({ code: "UNAUTHORIZED", message: INVALID_CREDENTIALS });
        }
        const ok = await bcrypt.compare(input.password, user.passwordHash);
        if (!ok) {
          throw new TRPCError({ code: "UNAUTHORIZED", message: INVALID_CREDENTIALS });
        }
        await db.updateLastSignedIn(user.id);
        const sessionToken = await sdk.signSession(
          { openId: user.openId, appId: "local", name: user.name ?? email },
          { expiresInMs: ONE_YEAR_MS },
        );
        const cookieOptions = getSessionCookieOptions(ctx.req);
        ctx.res.cookie(COOKIE_NAME, sessionToken, {
          ...cookieOptions,
          maxAge: ONE_YEAR_MS,
        });
        const fresh = (await db.getUserByOpenId(user.openId)) ?? user;
        return { sessionToken, user: buildAuthResponse(fresh) };
      }),
    /**
     * Register a new local user. Email must be unique. Password is hashed
     * with bcrypt cost 10. Same token shape as `loginWithPassword`.
     */
    registerWithPassword: publicProcedure
      .input(
        z.object({
          email: z.string().email("Email inválido"),
          password: z.string().min(8, "Senha precisa de no mínimo 8 caracteres"),
          name: z.string().min(1, "Informe seu nome").max(120),
        }),
      )
      .mutation(async ({ ctx, input }) => {
        const email = input.email.trim().toLowerCase();
        const existing = await db.getUserByEmail(email);
        if (existing) {
          throw new TRPCError({
            code: "CONFLICT",
            message: "Já existe uma conta com esse email",
          });
        }
        const passwordHash = await bcrypt.hash(input.password, 10);
        const user = await db.createLocalUser({
          email,
          passwordHash,
          name: input.name.trim(),
        });
        if (!user) {
          throw new TRPCError({
            code: "INTERNAL_SERVER_ERROR",
            message: "Falha ao criar conta",
          });
        }
        const sessionToken = await sdk.signSession(
          { openId: user.openId, appId: "local", name: user.name ?? email },
          { expiresInMs: ONE_YEAR_MS },
        );
        const cookieOptions = getSessionCookieOptions(ctx.req);
        ctx.res.cookie(COOKIE_NAME, sessionToken, {
          ...cookieOptions,
          maxAge: ONE_YEAR_MS,
        });
        return { sessionToken, user: buildAuthResponse(user) };
      }),
  }),

  // ERP domain
  produtos: produtosRouter,
  pedidos: pedidosRouter,
  ops: opsRouter,
  transacoes: transacoesRouter,
  notifications: notificationsRouter,
  customers: customersRouter,
  quotes: quotesRouter,
  artworks: artworksRouter,
  inventory: inventoryRouter,
  whatsapp: whatsappRouter,
  vehicles: vehiclesRouter,
  serviceOrders: serviceOrdersRouter,
  reports: reportsRouter,
  ai: aiRouter,
  fiscal: fiscalRouter,
  payments: paymentsRouter,
  companies: companiesRouter,
});

export type AppRouter = typeof appRouter;
