import { TRPCError } from "@trpc/server";
import { randomBytes, randomUUID } from "node:crypto";
import { and, asc, desc, eq } from "drizzle-orm";
import { z } from "zod";

import { artworkApprovals, artworks } from "../../drizzle/schema";
import { getDb } from "../db";
import { ENV } from "../_core/env";
import { companyProcedure, router } from "../_core/trpc";

type ArtworkRow = typeof artworks.$inferSelect;
type ArtworkApprovalRow = typeof artworkApprovals.$inferSelect;

function rowToArtwork(row: ArtworkRow) {
  return {
    id: row.id,
    opId: row.opId,
    quoteId: row.quoteId,
    titulo: row.titulo,
    descricao: row.descricao,
    fileKey: row.fileKey,
    mimeType: row.mimeType,
    fileSizeBytes: row.fileSizeBytes,
    status: row.status,
    publicToken: row.publicToken,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
    fileUrl: `/manus-storage/${row.fileKey}`,
  };
}

function rowToApproval(row: ArtworkApprovalRow) {
  return {
    id: row.id,
    artworkId: row.artworkId,
    decisao: row.decisao,
    comentario: row.comentario,
    aprovadorNome: row.aprovadorNome,
    aprovadorEmail: row.aprovadorEmail,
    ipAddress: row.ipAddress,
    createdAt: row.createdAt.toISOString(),
  };
}

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

function generatePublicToken() {
  return randomBytes(48).toString("hex").slice(0, 64);
}

function sanitizeFilename(name: string) {
  return name.replace(/[^a-zA-Z0-9._-]+/g, "_").slice(0, 80) || "file";
}

function appendHashSuffix(relKey: string): string {
  const hash = randomUUID().replace(/-/g, "").slice(0, 8);
  const lastDot = relKey.lastIndexOf(".");
  if (lastDot === -1) return `${relKey}_${hash}`;
  return `${relKey.slice(0, lastDot)}_${hash}${relKey.slice(lastDot)}`;
}

/**
 * Request a presigned PUT URL so the client can upload the file directly to
 * S3. Mirrors the internal presign step of `storagePut` without buffering the
 * file through the API server.
 */
async function getPresignedPutUrl(relKey: string): Promise<string> {
  const forgeUrl = ENV.forgeApiUrl;
  const forgeKey = ENV.forgeApiKey;
  if (!forgeUrl || !forgeKey) {
    throw new TRPCError({
      code: "SERVICE_UNAVAILABLE",
      message: "Storage não configurado (forge keys missing)",
    });
  }
  const presignUrl = new URL(
    "v1/storage/presign/put",
    forgeUrl.replace(/\/+$/, "") + "/",
  );
  presignUrl.searchParams.set("path", relKey.replace(/^\/+/, ""));
  const resp = await fetch(presignUrl, {
    headers: { Authorization: `Bearer ${forgeKey}` },
  });
  if (!resp.ok) {
    const msg = await resp.text().catch(() => resp.statusText);
    throw new TRPCError({
      code: "INTERNAL_SERVER_ERROR",
      message: `Storage presign failed (${resp.status}): ${msg}`,
    });
  }
  const { url } = (await resp.json()) as { url: string };
  if (!url) {
    throw new TRPCError({
      code: "INTERNAL_SERVER_ERROR",
      message: "Forge returned empty presign URL",
    });
  }
  return url;
}

export const artworksRouter = router({
  list: companyProcedure
    .input(
      z
        .object({
          opId: z.string().uuid().optional(),
          quoteId: z.string().uuid().optional(),
        })
        .optional(),
    )
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const filters = [eq(artworks.companyId, ctx.companyId)];
      if (input?.opId) filters.push(eq(artworks.opId, input.opId));
      if (input?.quoteId) filters.push(eq(artworks.quoteId, input.quoteId));
      const rows = await db
        .select()
        .from(artworks)
        .where(and(...filters))
        .orderBy(desc(artworks.createdAt));
      return rows.map(rowToArtwork);
    }),

  getById: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      const rows = await db
        .select()
        .from(artworks)
        .where(
          and(eq(artworks.id, input.id), eq(artworks.companyId, ctx.companyId)),
        )
        .limit(1);
      if (!rows[0]) {
        throw new TRPCError({
          code: "NOT_FOUND",
          message: "Arte não encontrada",
        });
      }
      return rowToArtwork(rows[0]);
    }),

  create: companyProcedure
    .input(
      z.object({
        titulo: z.string().min(1).max(255),
        descricao: z.string().optional().nullable(),
        opId: z.string().uuid().optional().nullable(),
        quoteId: z.string().uuid().optional().nullable(),
        fileKey: z.string().min(1).max(255),
        mimeType: z.string().min(1).max(64),
        fileSizeBytes: z.number().int().nonnegative(),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      const id = randomUUID();
      const publicToken = generatePublicToken();
      await db.insert(artworks).values({
        id,
        userId: ctx.user.id,
        companyId: ctx.companyId,
        opId: input.opId ?? null,
        quoteId: input.quoteId ?? null,
        titulo: input.titulo.trim(),
        descricao: input.descricao ?? null,
        fileKey: input.fileKey,
        mimeType: input.mimeType,
        fileSizeBytes: input.fileSizeBytes,
        publicToken,
      });
      const rows = await db
        .select()
        .from(artworks)
        .where(eq(artworks.id, id))
        .limit(1);
      return rowToArtwork(rows[0]);
    }),

  delete: companyProcedure
    .input(z.object({ id: z.string().uuid() }))
    .mutation(async ({ ctx, input }) => {
      const db = await requireDb();
      await db
        .delete(artworks)
        .where(
          and(eq(artworks.id, input.id), eq(artworks.companyId, ctx.companyId)),
        );
      return { id: input.id };
    }),

  getApprovalHistory: companyProcedure
    .input(z.object({ artworkId: z.string().uuid() }))
    .query(async ({ ctx, input }) => {
      const db = await requireDb();
      // Enforce ownership through artworks scope.
      const owner = await db
        .select({ id: artworks.id })
        .from(artworks)
        .where(
          and(
            eq(artworks.id, input.artworkId),
            eq(artworks.companyId, ctx.companyId),
          ),
        )
        .limit(1);
      if (!owner[0]) {
        throw new TRPCError({
          code: "NOT_FOUND",
          message: "Arte não encontrada",
        });
      }
      const rows = await db
        .select()
        .from(artworkApprovals)
        .where(eq(artworkApprovals.artworkId, input.artworkId))
        .orderBy(asc(artworkApprovals.createdAt));
      return rows.map(rowToApproval);
    }),

  /**
   * Returns a presigned PUT URL for the client to upload the file directly to
   * S3, plus the final storage key to persist on the artwork row afterwards.
   * Client flow:
   *   1. requestUploadUrl → { uploadUrl, key }
   *   2. fetch(uploadUrl, { method: "PUT", body: file, headers: { "Content-Type": contentType }})
   *   3. create({ fileKey: key, mimeType: contentType, fileSizeBytes, ... })
   */
  requestUploadUrl: companyProcedure
    .input(
      z.object({
        filename: z.string().min(1).max(200),
        contentType: z.string().min(1).max(64),
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const safeName = sanitizeFilename(input.filename);
      const key = appendHashSuffix(`artworks/${ctx.user.id}/${safeName}`);
      const uploadUrl = await getPresignedPutUrl(key);
      return { uploadUrl, key };
    }),
});
