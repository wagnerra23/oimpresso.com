// Drizzle client (node-postgres pool). Fonte de conexão única do server.
import { drizzle } from "drizzle-orm/node-postgres";
import { Pool } from "pg";
import * as schema from "./schema";

export const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  max: Number(process.env.PG_POOL_MAX ?? 10),
});

export const db = drizzle(pool, { schema });
export type DB = typeof db;
