/**
 * Seed an admin user with email + password.
 *
 * Usage: `npx tsx scripts/seed-admin.ts`
 *
 * Reads DATABASE_URL from .env (loaded via dotenv/config). If the admin email
 * already exists, updates the password hash and forces role=admin. Otherwise
 * creates a fresh row with openId=`local:<email>`.
 */
import "dotenv/config";
import bcrypt from "bcryptjs";
import { randomUUID } from "node:crypto";
import { drizzle } from "drizzle-orm/mysql2";
import { and, eq } from "drizzle-orm";
import {
  companies,
  companyMembers,
  companySettings,
  users,
} from "../drizzle/schema";

const ADMIN_EMAIL = process.env.SEED_ADMIN_EMAIL ?? "admin@erp.local";
const ADMIN_PASSWORD = process.env.SEED_ADMIN_PASSWORD ?? "admin12345";
const ADMIN_NAME = process.env.SEED_ADMIN_NAME ?? "Admin ERP";

async function main() {
  if (!process.env.DATABASE_URL) {
    console.error("[seed-admin] DATABASE_URL not set. Add it to .env and rerun.");
    process.exitCode = 1;
    return;
  }

  const db = drizzle(process.env.DATABASE_URL);
  const passwordHash = await bcrypt.hash(ADMIN_PASSWORD, 10);
  const openId = `local:${ADMIN_EMAIL.toLowerCase()}`;
  const now = new Date();

  // MySQL ON DUPLICATE KEY UPDATE keyed on the unique `openId`.
  await db
    .insert(users)
    .values({
      openId,
      email: ADMIN_EMAIL,
      name: ADMIN_NAME,
      loginMethod: "password",
      passwordHash,
      role: "admin",
      lastSignedIn: now,
    })
    .onDuplicateKeyUpdate({
      set: {
        email: ADMIN_EMAIL,
        name: ADMIN_NAME,
        loginMethod: "password",
        passwordHash,
        role: "admin",
        lastSignedIn: now,
      },
    });

  const [row] = await db.select().from(users).where(eq(users.openId, openId)).limit(1);

  // F3-08: ensure admin has at least one company + owner membership so the
  // app boots straight into a usable tenant. Idempotent.
  if (row) {
    const existing = await db
      .select()
      .from(companies)
      .where(eq(companies.ownerUserId, row.id))
      .limit(1);
    let companyId: string;
    if (existing.length === 0) {
      companyId = randomUUID();
      await db.insert(companies).values({
        id: companyId,
        ownerUserId: row.id,
        nome: "ERP Demo",
        vertical: "outro",
        ativa: 1,
      });
      console.log("[seed-admin] company criada:", companyId);
    } else {
      companyId = existing[0].id;
    }
    const member = await db
      .select()
      .from(companyMembers)
      .where(
        and(
          eq(companyMembers.companyId, companyId),
          eq(companyMembers.userId, row.id),
        ),
      )
      .limit(1);
    if (member.length === 0) {
      await db.insert(companyMembers).values({
        id: randomUUID(),
        companyId,
        userId: row.id,
        role: "owner",
      });
    }
    const cs = await db
      .select()
      .from(companySettings)
      .where(eq(companySettings.companyId, companyId))
      .limit(1);
    if (cs.length === 0) {
      await db.insert(companySettings).values({
        id: randomUUID(),
        userId: row.id,
        companyId,
      });
    }
  }

  console.log("[seed-admin] OK — admin pronto:");
  console.log("  id:       ", row?.id);
  console.log("  email:    ", ADMIN_EMAIL);
  console.log("  password: ", ADMIN_PASSWORD);
  console.log("  role:     ", row?.role);
  console.log("\nUse esses dados na tela de login do app.");
  // Drizzle's mysql2 pool keeps the process alive — exit explicitly.
  process.exit(0);
}

main().catch((err) => {
  console.error("[seed-admin] failed:", err);
  process.exit(1);
});
