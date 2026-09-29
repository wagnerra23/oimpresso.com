import { TRPCError } from "@trpc/server";
import { randomUUID } from "node:crypto";
import { and, eq } from "drizzle-orm";
import { z } from "zod";

import { pushTokens } from "../../drizzle/schema";
import { getDb } from "../db";
import { protectedProcedure, router } from "../_core/trpc";

const PlatformSchema = z.enum(["ios", "android", "web"]);

async function requireDb() {
  const db = await getDb();
  if (!db) {
    throw new TRPCError({
      code: "SERVICE_UNAVAILABLE",
      message: "Database is not configured (DATABASE_URL missing).",
    });
  }
  return db;
}

export const notificationsRouter = router({
  /**
   * Register an Expo push token for the current user.
   *
   * Tokens are globally unique — if the same token is already registered
   * (under any user), the old row is removed first so the latest user wins.
   */
  registerToken: protectedProcedure
    .input(
      z.object({
        token: z.string().min(1).max(255),
        platform: PlatformSchema,
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db.transaction(async (tx) => {
        await tx.delete(pushTokens).where(eq(pushTokens.token, input.token));
        await tx.insert(pushTokens).values({
          id: randomUUID(),
          userId: ctx.user.id,
          token: input.token,
          platform: input.platform,
        });
      });
      return { ok: true as const };
    }),

  /**
   * Remove a push token for the current user (e.g. on logout).
   */
  unregisterToken: protectedProcedure
    .input(z.object({ token: z.string().min(1).max(255) }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(pushTokens)
        .where(
          and(
            eq(pushTokens.token, input.token),
            eq(pushTokens.userId, ctx.user.id),
          ),
        );
      return { ok: true as const };
    }),
});
