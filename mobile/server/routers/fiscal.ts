import { TRPCError } from "@trpc/server";
import { and, desc, eq } from "drizzle-orm";
import { randomUUID } from "node:crypto";
import { z } from "zod";

import {
  companySettings,
  customers,
  fiscalDocuments,
  orderItems,
  pedidos,
  quoteItems,
  quotes,
  serviceOrderItems,
  serviceOrders,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { storagePut } from "../storage";
import { companyProcedure, router } from "../_core/trpc";
import {
  cancelar,
  consultarStatus,
  downloadPdf,
  downloadXml,
  emitirNFe,
  emitirNFSe,
  focusAmbiente,
  isFocusConfigured,
  mapFocusStatus,
  type FocusCompany,
  type FocusCustomer,
  type FocusItem,
  type FocusStatusResponse,
} from "../_core/focusnfe";

type FiscalRow = typeof fiscalDocuments.$inferSelect;
type CompanyRow = typeof companySettings.$inferSelect;

const TipoSchema = z.enum(["NFe", "NFCe", "NFSe"]);
const ReferenciaTipoSchema = z.enum(["pedido", "os", "quote"]);
const StatusSchema = z.enum([
  "rascunho",
  "processando",
  "autorizado",
  "cancelado",
  "rejeitado",
]);

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

function rowToCompany(row: CompanyRow) {
  return {
    id: row.id,
    razaoSocial: row.razaoSocial,
    nomeFantasia: row.nomeFantasia,
    cnpj: row.cnpj,
    inscricaoEstadual: row.inscricaoEstadual,
    inscricaoMunicipal: row.inscricaoMunicipal,
    regimeTributario: row.regimeTributario,
    cep: row.cep,
    endereco: row.endereco,
    cidade: row.cidade,
    uf: row.uf,
    telefone: row.telefone,
    email: row.email,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
  };
}

function rowToDocument(row: FiscalRow) {
  return {
    id: row.id,
    tipo: row.tipo,
    referenciaTipo: row.referenciaTipo,
    referenciaId: row.referenciaId,
    customerId: row.customerId,
    status: row.status,
    chaveAcesso: row.chaveAcesso,
    numero: row.numero,
    serie: row.serie,
    providerRef: row.providerRef,
    valor: Number(row.valor),
    pdfKey: row.pdfKey,
    xmlKey: row.xmlKey,
    erroMensagem: row.erroMensagem,
    ambiente: row.ambiente,
    createdAt: row.createdAt.toISOString(),
    updatedAt: row.updatedAt.toISOString(),
  };
}

function toFocusCompany(row: CompanyRow | null): FocusCompany {
  return {
    razaoSocial: row?.razaoSocial ?? null,
    nomeFantasia: row?.nomeFantasia ?? null,
    cnpj: row?.cnpj ?? null,
    inscricaoEstadual: row?.inscricaoEstadual ?? null,
    inscricaoMunicipal: row?.inscricaoMunicipal ?? null,
    regimeTributario: row?.regimeTributario ?? null,
    cep: row?.cep ?? null,
    endereco: row?.endereco ?? null,
    cidade: row?.cidade ?? null,
    uf: row?.uf ?? null,
    telefone: row?.telefone ?? null,
    email: row?.email ?? null,
  };
}

async function loadReferencia(
  db: NonNullable<Awaited<ReturnType<typeof getDb>>>,
  companyId: string,
  referenciaTipo: "pedido" | "os" | "quote",
  referenciaId: string,
): Promise<{
  customerId: string | null;
  valor: number;
  items: FocusItem[];
  descricaoServico: string;
}> {
  if (referenciaTipo === "pedido") {
    const found = await db
      .select()
      .from(pedidos)
      .where(and(eq(pedidos.id, referenciaId), eq(pedidos.companyId, companyId)))
      .limit(1);
    const p = found[0];
    if (!p) throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });
    const its = await db
      .select()
      .from(orderItems)
      .where(eq(orderItems.pedidoId, referenciaId));
    const items: FocusItem[] = its.length
      ? its.map((it) => ({
          descricao: it.descricao,
          quantidade: Number(it.quantidade),
          valorUnit: Number(it.valorUnit),
        }))
      : [
          {
            descricao: p.produto,
            quantidade: 1,
            valorUnit: Number(p.valor),
          },
        ];
    return {
      customerId: p.customerId,
      valor: Number(p.valor),
      items,
      descricaoServico: p.produto,
    };
  }
  if (referenciaTipo === "os") {
    const found = await db
      .select()
      .from(serviceOrders)
      .where(
        and(
          eq(serviceOrders.id, referenciaId),
          eq(serviceOrders.companyId, companyId),
        ),
      )
      .limit(1);
    const os = found[0];
    if (!os) throw new TRPCError({ code: "NOT_FOUND", message: "OS não encontrada" });
    const its = await db
      .select()
      .from(serviceOrderItems)
      .where(eq(serviceOrderItems.serviceOrderId, referenciaId));
    const items: FocusItem[] = its.map((it) => ({
      descricao: it.descricao,
      quantidade: Number(it.quantidade),
      valorUnit: Number(it.valorUnit),
    }));
    const desc =
      its.map((i) => i.descricao).join("; ").slice(0, 1000) ||
      `OS #${os.numero}`;
    return {
      customerId: os.customerId,
      valor: Number(os.valorTotal),
      items,
      descricaoServico: desc,
    };
  }
  // quote
  const found = await db
    .select()
    .from(quotes)
    .where(and(eq(quotes.id, referenciaId), eq(quotes.companyId, companyId)))
    .limit(1);
  const q = found[0];
  if (!q) throw new TRPCError({ code: "NOT_FOUND", message: "Orçamento não encontrado" });
  const its = await db
    .select()
    .from(quoteItems)
    .where(eq(quoteItems.quoteId, referenciaId));
  const items: FocusItem[] = its.map((it) => ({
    descricao: it.descricao,
    quantidade: Number(it.quantidade),
    valorUnit: Number(it.precoPorM2) * Number(it.areaM2) || Number(it.valorTotal),
  }));
  return {
    customerId: q.customerId,
    valor: Number(q.valorTotal),
    items: items.length
      ? items
      : [{ descricao: q.titulo, quantidade: 1, valorUnit: Number(q.valorTotal) }],
    descricaoServico: q.titulo,
  };
}

async function loadCustomer(
  db: NonNullable<Awaited<ReturnType<typeof getDb>>>,
  companyId: string,
  customerId: string | null,
): Promise<FocusCustomer | null> {
  if (!customerId) return null;
  const found = await db
    .select()
    .from(customers)
    .where(and(eq(customers.id, customerId), eq(customers.companyId, companyId)))
    .limit(1);
  const c = found[0];
  if (!c) return null;
  return {
    nome: c.nome,
    tipo: c.tipo,
    documento: c.documento,
    email: c.email,
    telefone: c.telefone,
    endereco: c.endereco,
  };
}

async function persistArtifacts(
  ref: string,
  tipo: "NFe" | "NFCe" | "NFSe",
): Promise<{ pdfKey: string | null; xmlKey: string | null }> {
  let pdfKey: string | null = null;
  let xmlKey: string | null = null;
  const pdf = await downloadPdf(ref, tipo);
  if (pdf.ok && pdf.buffer) {
    try {
      const put = await storagePut(
        `fiscal/${ref}.pdf`,
        pdf.buffer,
        "application/pdf",
      );
      pdfKey = put.key;
    } catch {
      // ignore — storage may be unavailable; status update still succeeds.
    }
  }
  const xml = await downloadXml(ref, tipo);
  if (xml.ok && xml.buffer) {
    try {
      const put = await storagePut(
        `fiscal/${ref}.xml`,
        xml.buffer,
        "application/xml",
      );
      xmlKey = put.key;
    } catch {
      // ignore
    }
  }
  return { pdfKey, xmlKey };
}

export const fiscalRouter = router({
  status: companyProcedure.query(() => ({
    configured: isFocusConfigured(),
    ambiente: focusAmbiente(),
  })),

  companySettings: router({
    get: companyProcedure.query(async ({ ctx }) => {
      const db = await requireDb();
      const found = await db
        .select()
        .from(companySettings)
        .where(eq(companySettings.companyId, ctx.companyId))
        .limit(1);
      return found[0] ? rowToCompany(found[0]) : null;
    }),
    upsert: companyProcedure
      .input(
        z.object({
          razaoSocial: z.string().max(255).optional().nullable(),
          nomeFantasia: z.string().max(255).optional().nullable(),
          cnpj: z.string().max(18).optional().nullable(),
          inscricaoEstadual: z.string().max(32).optional().nullable(),
          inscricaoMunicipal: z.string().max(32).optional().nullable(),
          regimeTributario: z
            .enum(["simples_nacional", "lucro_presumido", "lucro_real"])
            .optional()
            .nullable(),
          cep: z.string().max(9).optional().nullable(),
          endereco: z.string().optional().nullable(),
          cidade: z.string().max(120).optional().nullable(),
          uf: z.string().length(2).optional().nullable(),
          telefone: z.string().max(32).optional().nullable(),
          email: z.string().email().optional().nullable(),
        }),
      )
      .mutation(async ({ ctx, input }) => {
        const db = await requireDb();
        const existing = await db
          .select()
          .from(companySettings)
          .where(eq(companySettings.companyId, ctx.companyId))
          .limit(1);
        if (existing[0]) {
          await db
            .update(companySettings)
            .set({
              ...input,
              regimeTributario: input.regimeTributario ?? undefined,
            })
            .where(eq(companySettings.companyId, ctx.companyId));
        } else {
          await db.insert(companySettings).values({
            id: randomUUID(),
            userId: ctx.user.id,
            companyId: ctx.companyId,
            ...input,
            regimeTributario: input.regimeTributario ?? undefined,
          });
        }
        const fresh = await db
          .select()
          .from(companySettings)
          .where(eq(companySettings.companyId, ctx.companyId))
          .limit(1);
        return fresh[0] ? rowToCompany(fresh[0]) : null;
      }),
  }),

  documents: router({
    list: companyProcedure
      .input(
        z
          .object({
            status: StatusSchema.optional(),
            tipo: TipoSchema.optional(),
            referenciaTipo: z.string().max(32).optional(),
            referenciaId: z.string().max(36).optional(),
          })
          .optional(),
      )
      .query(async ({ ctx, input }) => {
        const db = await requireDb();
        const filters = [eq(fiscalDocuments.companyId, ctx.companyId)];
        if (input?.status) filters.push(eq(fiscalDocuments.status, input.status));
        if (input?.tipo) filters.push(eq(fiscalDocuments.tipo, input.tipo));
        if (input?.referenciaTipo)
          filters.push(eq(fiscalDocuments.referenciaTipo, input.referenciaTipo));
        if (input?.referenciaId)
          filters.push(eq(fiscalDocuments.referenciaId, input.referenciaId));
        const rows = await db
          .select()
          .from(fiscalDocuments)
          .where(and(...filters))
          .orderBy(desc(fiscalDocuments.createdAt))
          .limit(500);
        return rows.map(rowToDocument);
      }),

    getById: companyProcedure
      .input(z.object({ id: z.string().uuid() }))
      .query(async ({ ctx, input }) => {
        const db = await requireDb();
        const found = await db
          .select()
          .from(fiscalDocuments)
          .where(
            and(
              eq(fiscalDocuments.id, input.id),
              eq(fiscalDocuments.companyId, ctx.companyId),
            ),
          )
          .limit(1);
        if (!found[0]) {
          throw new TRPCError({ code: "NOT_FOUND", message: "Documento não encontrado" });
        }
        return rowToDocument(found[0]);
      }),

    emit: companyProcedure
      .input(
        z.object({
          tipo: TipoSchema,
          referenciaTipo: ReferenciaTipoSchema,
          referenciaId: z.string().min(1).max(36),
          customerId: z.string().uuid().optional().nullable(),
          /**
           * Accepted (and ignored) idempotency key from offline mutation queue.
           * Fiscal emit's natural dedup is the unique `providerRef` per row;
           * we accept the field so queued retries don't fail input validation.
           */
          idempotencyKey: z.string().min(8).max(64).optional(),
        }),
      )
      .mutation(async ({ ctx, input }) => {
        const db = await requireDb();

        // Load company config (singleton per user).
        const companyRows = await db
          .select()
          .from(companySettings)
          .where(eq(companySettings.companyId, ctx.companyId))
          .limit(1);
        const companyRow = companyRows[0] ?? null;

        const ref = await loadReferencia(
          db,
          ctx.companyId,
          input.referenciaTipo,
          input.referenciaId,
        );
        const customerId = input.customerId ?? ref.customerId;
        const customer = await loadCustomer(db, ctx.companyId, customerId);

        const id = randomUUID();
        const providerRef = `${input.tipo.toLowerCase()}-${id.slice(0, 8)}-${Date.now()}`;
        const ambiente = focusAmbiente();

        // Graceful degradation: no token → store as rascunho.
        if (!isFocusConfigured()) {
          await db.insert(fiscalDocuments).values({
            id,
            userId: ctx.user.id,
            companyId: ctx.companyId,
            tipo: input.tipo,
            referenciaTipo: input.referenciaTipo,
            referenciaId: input.referenciaId,
            customerId,
            status: "rascunho",
            providerRef,
            valor: ref.valor.toFixed(2),
            ambiente,
            erroMensagem: "Focus NFe não configurado (defina FOCUS_NFE_TOKEN)",
          });
          return rowToDocument({
            id,
            userId: ctx.user.id,
            companyId: ctx.companyId,
            tipo: input.tipo,
            referenciaTipo: input.referenciaTipo,
            referenciaId: input.referenciaId,
            customerId,
            status: "rascunho",
            chaveAcesso: null,
            numero: null,
            serie: null,
            providerRef,
            valor: ref.valor.toFixed(2),
            pdfKey: null,
            xmlKey: null,
            providerResponse: null,
            erroMensagem: "Focus NFe não configurado (defina FOCUS_NFE_TOKEN)",
            ambiente,
            createdAt: new Date(),
            updatedAt: new Date(),
          });
        }

        if (!customer) {
          throw new TRPCError({
            code: "BAD_REQUEST",
            message: "Cliente é obrigatório para emissão fiscal",
          });
        }
        if (!companyRow || !companyRow.cnpj) {
          throw new TRPCError({
            code: "BAD_REQUEST",
            message: "Configure os dados da empresa (CNPJ) antes de emitir",
          });
        }

        const focusCompany = toFocusCompany(companyRow);

        const providerCall =
          input.tipo === "NFSe"
            ? await emitirNFSe({
                referenciaId: providerRef,
                company: focusCompany,
                customer,
                servico: ref.descricaoServico,
                valor: ref.valor,
              })
            : await emitirNFe({
                referenciaId: providerRef,
                company: focusCompany,
                customer,
                items: ref.items,
                valorTotal: ref.valor,
              });

        const providerStatus = providerCall.data?.status ?? null;
        const mapped = providerCall.ok
          ? mapFocusStatus(providerStatus)
          : "rejeitado";

        await db.insert(fiscalDocuments).values({
          id,
          userId: ctx.user.id,
          companyId: ctx.companyId,
          tipo: input.tipo,
          referenciaTipo: input.referenciaTipo,
          referenciaId: input.referenciaId,
          customerId,
          status: mapped,
          providerRef,
          valor: ref.valor.toFixed(2),
          providerResponse: providerCall.raw?.slice(0, 60_000) ?? null,
          erroMensagem: providerCall.ok ? null : providerCall.error ?? null,
          ambiente,
          chaveAcesso:
            providerCall.data?.chave_nfe ?? providerCall.data?.chave_nfse ?? null,
          numero: providerCall.data?.numero ?? null,
          serie: providerCall.data?.serie ?? null,
        });

        // Fire-and-forget: re-consultar em 3s para capturar o status autorizado.
        if (providerCall.ok && mapped !== "rejeitado") {
          setTimeout(() => {
            consultarStatus(providerRef, input.tipo)
              .then(async (res) => {
                if (!res.ok || !res.data) return;
                const finalStatus = mapFocusStatus(res.data.status);
                const updates: Partial<typeof fiscalDocuments.$inferInsert> = {
                  status: finalStatus,
                  providerResponse: res.raw?.slice(0, 60_000) ?? null,
                  chaveAcesso:
                    res.data.chave_nfe ?? res.data.chave_nfse ?? undefined,
                  numero: res.data.numero ?? undefined,
                  serie: res.data.serie ?? undefined,
                };
                if (finalStatus === "autorizado") {
                  const { pdfKey, xmlKey } = await persistArtifacts(
                    providerRef,
                    input.tipo,
                  );
                  if (pdfKey) updates.pdfKey = pdfKey;
                  if (xmlKey) updates.xmlKey = xmlKey;
                }
                if (finalStatus === "rejeitado") {
                  updates.erroMensagem =
                    res.data.mensagem_sefaz ??
                    res.data.erros?.[0]?.mensagem ??
                    null;
                }
                const dbInner = await getDb();
                if (!dbInner) return;
                await dbInner
                  .update(fiscalDocuments)
                  .set(updates)
                  .where(eq(fiscalDocuments.id, id));
              })
              .catch(() => {});
          }, 3000);
        }

        const fresh = await db
          .select()
          .from(fiscalDocuments)
          .where(eq(fiscalDocuments.id, id))
          .limit(1);
        return rowToDocument(fresh[0]!);
      }),

    consultStatus: companyProcedure
      .input(z.object({ id: z.string().uuid() }))
      .mutation(async ({ ctx, input }) => {
        const db = await requireDb();
        const found = await db
          .select()
          .from(fiscalDocuments)
          .where(
            and(
              eq(fiscalDocuments.id, input.id),
              eq(fiscalDocuments.companyId, ctx.companyId),
            ),
          )
          .limit(1);
        const doc = found[0];
        if (!doc) throw new TRPCError({ code: "NOT_FOUND", message: "Documento não encontrado" });
        if (!doc.providerRef) {
          throw new TRPCError({
            code: "BAD_REQUEST",
            message: "Documento sem referência no provedor",
          });
        }
        const res = await consultarStatus(doc.providerRef, doc.tipo);
        if (!res.ok || !res.data) {
          return rowToDocument(doc);
        }
        const data: FocusStatusResponse = res.data;
        const finalStatus = mapFocusStatus(data.status);
        const updates: Partial<typeof fiscalDocuments.$inferInsert> = {
          status: finalStatus,
          providerResponse: res.raw?.slice(0, 60_000) ?? null,
          chaveAcesso: data.chave_nfe ?? data.chave_nfse ?? doc.chaveAcesso ?? null,
          numero: data.numero ?? doc.numero ?? null,
          serie: data.serie ?? doc.serie ?? null,
        };
        if (finalStatus === "autorizado" && (!doc.pdfKey || !doc.xmlKey)) {
          const { pdfKey, xmlKey } = await persistArtifacts(doc.providerRef, doc.tipo);
          if (pdfKey) updates.pdfKey = pdfKey;
          if (xmlKey) updates.xmlKey = xmlKey;
        }
        if (finalStatus === "rejeitado") {
          updates.erroMensagem =
            data.mensagem_sefaz ?? data.erros?.[0]?.mensagem ?? null;
        }
        await db
          .update(fiscalDocuments)
          .set(updates)
          .where(eq(fiscalDocuments.id, input.id));
        const fresh = await db
          .select()
          .from(fiscalDocuments)
          .where(eq(fiscalDocuments.id, input.id))
          .limit(1);
        return rowToDocument(fresh[0]!);
      }),

    cancel: companyProcedure
      .input(
        z.object({
          id: z.string().uuid(),
          justificativa: z.string().min(15).max(255),
        }),
      )
      .mutation(async ({ ctx, input }) => {
        const db = await requireDb();
        const found = await db
          .select()
          .from(fiscalDocuments)
          .where(
            and(
              eq(fiscalDocuments.id, input.id),
              eq(fiscalDocuments.companyId, ctx.companyId),
            ),
          )
          .limit(1);
        const doc = found[0];
        if (!doc) throw new TRPCError({ code: "NOT_FOUND", message: "Documento não encontrado" });
        if (!doc.providerRef) {
          throw new TRPCError({
            code: "BAD_REQUEST",
            message: "Documento sem referência no provedor",
          });
        }
        const res = await cancelar(doc.providerRef, doc.tipo, input.justificativa);
        if (!res.ok) {
          throw new TRPCError({
            code: "BAD_GATEWAY",
            message: res.error ?? "Falha ao cancelar no provedor",
          });
        }
        await db
          .update(fiscalDocuments)
          .set({
            status: "cancelado",
            providerResponse: res.raw?.slice(0, 60_000) ?? null,
          })
          .where(eq(fiscalDocuments.id, input.id));
        const fresh = await db
          .select()
          .from(fiscalDocuments)
          .where(eq(fiscalDocuments.id, input.id))
          .limit(1);
        return rowToDocument(fresh[0]!);
      }),

    downloadUrl: companyProcedure
      .input(z.object({ id: z.string().uuid() }))
      .query(async ({ ctx, input }) => {
        const db = await requireDb();
        const found = await db
          .select()
          .from(fiscalDocuments)
          .where(
            and(
              eq(fiscalDocuments.id, input.id),
              eq(fiscalDocuments.companyId, ctx.companyId),
            ),
          )
          .limit(1);
        const doc = found[0];
        if (!doc) throw new TRPCError({ code: "NOT_FOUND", message: "Documento não encontrado" });
        return {
          pdfUrl: doc.pdfKey ? `/manus-storage/${doc.pdfKey}` : null,
          xmlUrl: doc.xmlKey ? `/manus-storage/${doc.xmlKey}` : null,
        };
      }),
  }),
});
