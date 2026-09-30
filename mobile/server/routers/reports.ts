/**
 * F3-02 — Relatórios e Exportação
 *
 * Aggregations over existing tables (no new persistence). Every query is
 * scoped by `ctx.user.id`. Decimals are summed via SQL and `Number()`-cast
 * at the API boundary (we never return Decimal strings to the client).
 *
 * Date filters use ISO 8601 strings; since the relevant columns
 * (`pedidos.data`, `transacoes.data`) are stored as VARCHAR ISO strings,
 * lexicographic comparison is equivalent to chronological comparison.
 */

import { TRPCError } from "@trpc/server";
import { and, eq, gte, lte, sql, desc, inArray } from "drizzle-orm";
import PDFDocument from "pdfkit";
import ExcelJS from "exceljs";
import { z } from "zod";

import {
  customers,
  inventory,
  inventoryMovements,
  ops,
  orderItems,
  pedidos,
  serviceOrderItems,
  serviceOrders,
  transacoes,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { storagePut } from "../storage";
import { companyProcedure, router } from "../_core/trpc";

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

const PeriodSchema = z.object({
  from: z.string().datetime().optional(),
  to: z.string().datetime().optional(),
});

const GroupBySchema = z.enum(["dia", "semana", "mes"]).default("dia");

function defaultPeriod(input: { from?: string; to?: string }) {
  const now = new Date();
  const to = input.to ?? now.toISOString();
  const from =
    input.from ??
    new Date(now.getTime() - 30 * 24 * 60 * 60 * 1000).toISOString();
  return { from, to };
}

function toNumber(v: unknown): number {
  if (v === null || v === undefined) return 0;
  if (typeof v === "number") return v;
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function fmtBRL(n: number) {
  return `R$ ${n.toFixed(2).replace(".", ",")}`;
}

// ─── DRE ───────────────────────────────────────────────────────────────────

async function computeDre(
  companyId: string,
  input: { from?: string; to?: string },
) {
  const db = await requireDb();
  const { from, to } = defaultPeriod(input);

  const where = and(
    eq(transacoes.companyId, companyId),
    gte(transacoes.data, from),
    lte(transacoes.data, to),
  );

  // Sums per tipo
  const totalsRows = await db
    .select({
      tipo: transacoes.tipo,
      total: sql<string>`COALESCE(SUM(${transacoes.valor}), 0)`,
    })
    .from(transacoes)
    .where(where)
    .groupBy(transacoes.tipo);

  let totalReceitas = 0;
  let totalDespesas = 0;
  for (const r of totalsRows) {
    if (r.tipo === "receita") totalReceitas = toNumber(r.total);
    if (r.tipo === "despesa") totalDespesas = toNumber(r.total);
  }

  const catRows = await db
    .select({
      tipo: transacoes.tipo,
      categoria: transacoes.categoria,
      total: sql<string>`COALESCE(SUM(${transacoes.valor}), 0)`,
    })
    .from(transacoes)
    .where(where)
    .groupBy(transacoes.tipo, transacoes.categoria);

  const receitasPorCategoria = catRows
    .filter((r) => r.tipo === "receita")
    .map((r) => ({ categoria: r.categoria, valor: toNumber(r.total) }))
    .sort((a, b) => b.valor - a.valor);
  const despesasPorCategoria = catRows
    .filter((r) => r.tipo === "despesa")
    .map((r) => ({ categoria: r.categoria, valor: toNumber(r.total) }))
    .sort((a, b) => b.valor - a.valor);

  const resultadoLiquido = totalReceitas - totalDespesas;
  const margemPercent =
    totalReceitas > 0 ? (resultadoLiquido / totalReceitas) * 100 : 0;

  return {
    from,
    to,
    totalReceitas,
    totalDespesas,
    resultadoLiquido,
    margemPercent,
    receitasPorCategoria,
    despesasPorCategoria,
  };
}

// ─── VENDAS ────────────────────────────────────────────────────────────────

function bucketKey(iso: string, groupBy: "dia" | "semana" | "mes"): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso.slice(0, 10);
  const y = d.getUTCFullYear();
  const m = String(d.getUTCMonth() + 1).padStart(2, "0");
  const day = String(d.getUTCDate()).padStart(2, "0");
  if (groupBy === "mes") return `${y}-${m}`;
  if (groupBy === "semana") {
    // ISO week start (Mon) — return YYYY-Www
    const tmp = new Date(Date.UTC(y, d.getUTCMonth(), d.getUTCDate()));
    const dayNum = (tmp.getUTCDay() + 6) % 7; // Mon=0..Sun=6
    tmp.setUTCDate(tmp.getUTCDate() - dayNum + 3);
    const firstThursday = tmp.getTime();
    tmp.setUTCMonth(0, 1);
    if (tmp.getUTCDay() !== 4) {
      tmp.setUTCMonth(0, 1 + ((4 - tmp.getUTCDay() + 7) % 7));
    }
    const week =
      1 + Math.ceil((firstThursday - tmp.getTime()) / (7 * 24 * 3600 * 1000));
    return `${y}-W${String(week).padStart(2, "0")}`;
  }
  return `${y}-${m}-${day}`;
}

async function computeVendas(
  companyId: string,
  input: { from?: string; to?: string; groupBy?: "dia" | "semana" | "mes" },
) {
  const db = await requireDb();
  const { from, to } = defaultPeriod(input);
  const groupBy = input.groupBy ?? "dia";

  const sellingStatuses = ["execucao", "entregue"] as const;
  const where = and(
    eq(pedidos.companyId, companyId),
    gte(pedidos.data, from),
    lte(pedidos.data, to),
  );

  const allRows = await db
    .select({
      id: pedidos.id,
      customerId: pedidos.customerId,
      cliente: pedidos.cliente,
      valor: pedidos.valor,
      status: pedidos.status,
      data: pedidos.data,
    })
    .from(pedidos)
    .where(where);

  const sellingRows = allRows.filter((r) => sellingStatuses.includes(r.status as any));

  let totalVendas = 0;
  const seriesMap = new Map<string, { total: number; count: number }>();
  const clienteMap = new Map<
    string,
    { customerId: string | null; nome: string; valor: number; count: number }
  >();

  for (const r of sellingRows) {
    const v = toNumber(r.valor);
    totalVendas += v;
    const key = bucketKey(r.data, groupBy);
    const prev = seriesMap.get(key) ?? { total: 0, count: 0 };
    seriesMap.set(key, { total: prev.total + v, count: prev.count + 1 });
    const ckey = r.customerId ?? `name:${r.cliente}`;
    const c = clienteMap.get(ckey) ?? {
      customerId: r.customerId,
      nome: r.cliente,
      valor: 0,
      count: 0,
    };
    c.valor += v;
    c.count += 1;
    clienteMap.set(ckey, c);
  }

  const ticketMedio = sellingRows.length > 0 ? totalVendas / sellingRows.length : 0;

  const countPedidos: Record<string, number> = {
    novo: 0,
    aprovado: 0,
    execucao: 0,
    entregue: 0,
  };
  for (const r of allRows) {
    countPedidos[r.status] = (countPedidos[r.status] ?? 0) + 1;
  }

  const series = [...seriesMap.entries()]
    .map(([date, v]) => ({ date, total: v.total, count: v.count }))
    .sort((a, b) => (a.date < b.date ? -1 : 1));

  const topClientes = [...clienteMap.values()]
    .sort((a, b) => b.valor - a.valor)
    .slice(0, 10);

  // Top produtos via orderItems (only count items whose pedido is in selling list)
  const sellingIds = sellingRows.map((r) => r.id);
  let topProdutos: { produto: string; valor: number; qtdItens: number }[] = [];
  if (sellingIds.length > 0) {
    const itemRows = await db
      .select({
        descricao: orderItems.descricao,
        valor: sql<string>`COALESCE(SUM(${orderItems.valorTotal}), 0)`,
        qtd: sql<string>`COALESCE(SUM(${orderItems.quantidade}), 0)`,
      })
      .from(orderItems)
      .where(inArray(orderItems.pedidoId, sellingIds))
      .groupBy(orderItems.descricao);
    topProdutos = itemRows
      .map((r) => ({
        produto: r.descricao,
        valor: toNumber(r.valor),
        qtdItens: toNumber(r.qtd),
      }))
      .sort((a, b) => b.valor - a.valor)
      .slice(0, 10);
  }

  return {
    from,
    to,
    groupBy,
    series,
    totalVendas,
    ticketMedio,
    countPedidos,
    topClientes,
    topProdutos,
  };
}

// ─── PRODUÇÃO ──────────────────────────────────────────────────────────────

async function computeProducao(
  companyId: string,
  input: { from?: string; to?: string },
) {
  const db = await requireDb();
  const { from, to } = defaultPeriod(input);

  // OPs: createdAt is a timestamp column → compare via gte/lte with Date objects
  const fromDate = new Date(from);
  const toDate = new Date(to);

  const opsRows = await db
    .select({
      status: ops.status,
      createdAt: ops.createdAt,
      updatedAt: ops.updatedAt,
    })
    .from(ops)
    .where(
      and(
        eq(ops.companyId, companyId),
        gte(ops.createdAt, fromDate),
        lte(ops.createdAt, toDate),
      ),
    );

  const countOPsPorStatus: Record<string, number> = {
    fila: 0,
    andamento: 0,
    revisao: 0,
    concluido: 0,
  };
  let totalConclusaoMs = 0;
  let countConclusao = 0;
  for (const r of opsRows) {
    countOPsPorStatus[r.status] = (countOPsPorStatus[r.status] ?? 0) + 1;
    if (r.status === "concluido" && r.createdAt && r.updatedAt) {
      const diff =
        new Date(r.updatedAt).getTime() - new Date(r.createdAt).getTime();
      if (diff > 0) {
        totalConclusaoMs += diff;
        countConclusao += 1;
      }
    }
  }

  // Service Orders (MEC vertical) — may not have rows for non-mecânica users; safe.
  const osRows = await db
    .select({
      id: serviceOrders.id,
      status: serviceOrders.status,
      dataEntrada: serviceOrders.dataEntrada,
      dataSaida: serviceOrders.dataSaida,
    })
    .from(serviceOrders)
    .where(
      and(
        eq(serviceOrders.companyId, companyId),
        gte(serviceOrders.dataEntrada, from),
        lte(serviceOrders.dataEntrada, to),
      ),
    );

  const countOSsPorStatus: Record<string, number> = {};
  for (const r of osRows) {
    countOSsPorStatus[r.status] = (countOSsPorStatus[r.status] ?? 0) + 1;
    if (
      (r.status === "entregue" || r.status === "pronto") &&
      r.dataEntrada &&
      r.dataSaida
    ) {
      const diff =
        new Date(r.dataSaida).getTime() - new Date(r.dataEntrada).getTime();
      if (diff > 0) {
        totalConclusaoMs += diff;
        countConclusao += 1;
      }
    }
  }

  const tempoMedioConclusaoDias =
    countConclusao > 0
      ? totalConclusaoMs / countConclusao / (1000 * 60 * 60 * 24)
      : 0;

  // Mecânicos mais ativos
  const osIds = osRows.map((r) => r.id);
  let mecanicosMaisAtivos: { mecanico: string; count: number }[] = [];
  if (osIds.length > 0) {
    const mecRows = await db
      .select({
        mecanico: serviceOrderItems.mecanicoResponsavel,
        count: sql<number>`COUNT(*)`,
      })
      .from(serviceOrderItems)
      .where(inArray(serviceOrderItems.serviceOrderId, osIds))
      .groupBy(serviceOrderItems.mecanicoResponsavel);
    mecanicosMaisAtivos = mecRows
      .filter((r) => r.mecanico && r.mecanico.trim().length > 0)
      .map((r) => ({ mecanico: r.mecanico as string, count: toNumber(r.count) }))
      .sort((a, b) => b.count - a.count)
      .slice(0, 10);
  }

  return {
    from,
    to,
    countOPsPorStatus,
    countOSsPorStatus,
    tempoMedioConclusaoDias,
    mecanicosMaisAtivos,
    totalOPs: opsRows.length,
    totalOSs: osRows.length,
  };
}

// ─── ESTOQUE (snapshot) ────────────────────────────────────────────────────

async function computeEstoque(companyId: string) {
  const db = await requireDb();

  const items = await db
    .select({
      id: inventory.id,
      nome: inventory.nome,
      quantidade: inventory.quantidade,
      estoqueMinimo: inventory.estoqueMinimo,
      custoUnit: inventory.custoUnit,
      ativo: inventory.ativo,
    })
    .from(inventory)
    .where(eq(inventory.companyId, companyId));

  const ativos = items.filter((i) => i.ativo === 1);
  let valorTotalEstoque = 0;
  const itensComEstoqueBaixo: {
    id: string;
    nome: string;
    quantidade: number;
    estoqueMinimo: number;
  }[] = [];
  for (const it of ativos) {
    const q = toNumber(it.quantidade);
    const c = toNumber(it.custoUnit);
    valorTotalEstoque += q * c;
    const min = toNumber(it.estoqueMinimo);
    if (q <= min && min > 0) {
      itensComEstoqueBaixo.push({
        id: it.id,
        nome: it.nome,
        quantidade: q,
        estoqueMinimo: min,
      });
    }
  }
  itensComEstoqueBaixo.sort((a, b) => a.quantidade - b.quantidade);

  const cutoff = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000);
  const movRows = await db
    .select({ id: inventoryMovements.id })
    .from(inventoryMovements)
    .where(
      and(
        eq(inventoryMovements.companyId, companyId),
        gte(inventoryMovements.createdAt, cutoff),
      ),
    );

  return {
    itensAtivos: ativos.length,
    itensTotal: items.length,
    valorTotalEstoque,
    itensComEstoqueBaixo,
    movimentosUltimos30Dias: movRows.length,
  };
}

// ─── EXPORT: PDF ───────────────────────────────────────────────────────────

function renderPdf(
  title: string,
  sections: { heading: string; rows: (string | number)[][] }[],
  summary: { label: string; value: string }[],
): Promise<Buffer> {
  return new Promise((resolve, reject) => {
    try {
      const doc = new PDFDocument({ size: "A4", margin: 40 });
      const chunks: Buffer[] = [];
      doc.on("data", (c: Buffer) => chunks.push(c));
      doc.on("end", () => resolve(Buffer.concat(chunks)));
      doc.on("error", reject);

      doc.fontSize(18).text(title, { align: "left" });
      doc.moveDown(0.3);
      doc.fontSize(9).text(`Gerado em ${new Date().toLocaleString("pt-BR")}`);
      doc.moveDown();

      if (summary.length > 0) {
        doc.fontSize(11).text("Resumo", { underline: true });
        doc.moveDown(0.3);
        doc.fontSize(10);
        for (const s of summary) {
          doc.text(`${s.label}: ${s.value}`);
        }
        doc.moveDown();
      }

      for (const sec of sections) {
        doc.fontSize(12).text(sec.heading, { underline: true });
        doc.moveDown(0.3);
        doc.fontSize(9);
        for (const row of sec.rows) {
          doc.text(row.map((c) => String(c)).join("  |  "));
        }
        doc.moveDown(0.5);
      }

      doc.end();
    } catch (err) {
      reject(err);
    }
  });
}

// ─── EXPORT: EXCEL ─────────────────────────────────────────────────────────

async function renderExcel(
  title: string,
  sheets: { name: string; columns: { header: string; key: string }[]; rows: any[] }[],
): Promise<Buffer> {
  const wb = new ExcelJS.Workbook();
  wb.creator = "mini-erp-mobile";
  wb.created = new Date();
  for (const s of sheets) {
    const ws = wb.addWorksheet(s.name.slice(0, 31));
    ws.columns = s.columns.map((c) => ({
      header: c.header,
      key: c.key,
      width: Math.max(12, c.header.length + 4),
    }));
    if (ws.getRow(1)) ws.getRow(1).font = { bold: true };
    for (const row of s.rows) ws.addRow(row);
  }
  // workaround for typing: ExcelJS returns ArrayBuffer in some setups
  const buf = await wb.xlsx.writeBuffer();
  return Buffer.from(buf as ArrayBuffer);
}

// ─── EXPORT: shared ────────────────────────────────────────────────────────

const ExportTipoSchema = z.enum(["dre", "vendas", "producao", "estoque"]);

async function buildExportData(
  companyId: string,
  tipo: z.infer<typeof ExportTipoSchema>,
  period: { from?: string; to?: string },
) {
  if (tipo === "dre") return { dre: await computeDre(companyId, period) };
  if (tipo === "vendas")
    return { vendas: await computeVendas(companyId, period) };
  if (tipo === "producao")
    return { producao: await computeProducao(companyId, period) };
  return { estoque: await computeEstoque(companyId) };
}

// ─── ROUTER ────────────────────────────────────────────────────────────────

export const reportsRouter = router({
  dre: companyProcedure.input(PeriodSchema).query(async ({ ctx, input }) => {
    return computeDre(ctx.companyId, input);
  }),

  vendas: companyProcedure
    .input(PeriodSchema.extend({ groupBy: GroupBySchema.optional() }))
    .query(async ({ ctx, input }) => {
      return computeVendas(ctx.companyId, input);
    }),

  producao: companyProcedure
    .input(PeriodSchema)
    .query(async ({ ctx, input }) => {
      return computeProducao(ctx.companyId, input);
    }),

  estoque: companyProcedure.query(async ({ ctx }) => {
    return computeEstoque(ctx.companyId);
  }),

  exportPdf: companyProcedure
    .input(
      PeriodSchema.extend({
        tipo: ExportTipoSchema,
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const { tipo, from, to } = input;
      const data = await buildExportData(ctx.companyId, tipo, { from, to });

      let title = "Relatório";
      let summary: { label: string; value: string }[] = [];
      let sections: { heading: string; rows: (string | number)[][] }[] = [];

      if ("dre" in data) {
        const d = data.dre!;
        title = "DRE Simplificado";
        summary = [
          { label: "Período", value: `${d.from.slice(0, 10)} → ${d.to.slice(0, 10)}` },
          { label: "Receitas", value: fmtBRL(d.totalReceitas) },
          { label: "Despesas", value: fmtBRL(d.totalDespesas) },
          { label: "Resultado Líquido", value: fmtBRL(d.resultadoLiquido) },
          { label: "Margem", value: `${d.margemPercent.toFixed(2)}%` },
        ];
        sections = [
          {
            heading: "Receitas por Categoria",
            rows: d.receitasPorCategoria.map((r) => [r.categoria, fmtBRL(r.valor)]),
          },
          {
            heading: "Despesas por Categoria",
            rows: d.despesasPorCategoria.map((r) => [r.categoria, fmtBRL(r.valor)]),
          },
        ];
      } else if ("vendas" in data) {
        const v = data.vendas!;
        title = "Relatório de Vendas";
        summary = [
          { label: "Período", value: `${v.from.slice(0, 10)} → ${v.to.slice(0, 10)}` },
          { label: "Total Vendas", value: fmtBRL(v.totalVendas) },
          { label: "Ticket Médio", value: fmtBRL(v.ticketMedio) },
          { label: "Agrupamento", value: v.groupBy },
        ];
        sections = [
          {
            heading: `Série (${v.groupBy})`,
            rows: v.series.map((s) => [s.date, fmtBRL(s.total), `${s.count} pedido(s)`]),
          },
          {
            heading: "Top Clientes",
            rows: v.topClientes.map((c) => [c.nome, fmtBRL(c.valor), `${c.count}`]),
          },
          {
            heading: "Top Produtos",
            rows: v.topProdutos.map((p) => [p.produto, fmtBRL(p.valor), p.qtdItens.toFixed(2)]),
          },
        ];
      } else if ("producao" in data) {
        const p = data.producao!;
        title = "Relatório de Produção";
        summary = [
          { label: "Período", value: `${p.from.slice(0, 10)} → ${p.to.slice(0, 10)}` },
          { label: "Total OPs", value: String(p.totalOPs) },
          { label: "Total OSs", value: String(p.totalOSs) },
          {
            label: "Tempo Médio de Conclusão",
            value: `${p.tempoMedioConclusaoDias.toFixed(2)} dias`,
          },
        ];
        sections = [
          {
            heading: "OPs por Status",
            rows: Object.entries(p.countOPsPorStatus).map(([k, v]) => [k, v]),
          },
          {
            heading: "OSs por Status",
            rows: Object.entries(p.countOSsPorStatus).map(([k, v]) => [k, v]),
          },
          {
            heading: "Mecânicos Mais Ativos",
            rows: p.mecanicosMaisAtivos.map((m) => [m.mecanico, `${m.count} itens`]),
          },
        ];
      } else {
        const e = data.estoque;
        title = "Relatório de Estoque";
        summary = [
          { label: "Itens Ativos", value: String(e.itensAtivos) },
          { label: "Valor Total em Estoque", value: fmtBRL(e.valorTotalEstoque) },
          { label: "Movimentos (últimos 30d)", value: String(e.movimentosUltimos30Dias) },
        ];
        sections = [
          {
            heading: "Itens com Estoque Baixo",
            rows: e.itensComEstoqueBaixo.map((i) => [
              i.nome,
              `qtd ${i.quantidade.toFixed(2)}`,
              `min ${i.estoqueMinimo.toFixed(2)}`,
            ]),
          },
        ];
      }

      const buffer = await renderPdf(title, sections, summary);
      const { key, url } = await storagePut(
        `reports/${ctx.user.id}/${tipo}-${Date.now()}.pdf`,
        buffer,
        "application/pdf",
      );
      return { key, url };
    }),

  exportExcel: companyProcedure
    .input(
      PeriodSchema.extend({
        tipo: ExportTipoSchema,
      }),
    )
    .mutation(async ({ ctx, input }) => {
      const { tipo, from, to } = input;
      const data = await buildExportData(ctx.companyId, tipo, { from, to });

      let title = "Relatorio";
      let sheets: {
        name: string;
        columns: { header: string; key: string }[];
        rows: any[];
      }[] = [];

      if ("dre" in data) {
        const d = data.dre!;
        title = "DRE";
        sheets = [
          {
            name: "Resumo",
            columns: [
              { header: "Métrica", key: "k" },
              { header: "Valor", key: "v" },
            ],
            rows: [
              { k: "Período", v: `${d.from} → ${d.to}` },
              { k: "Receitas", v: d.totalReceitas },
              { k: "Despesas", v: d.totalDespesas },
              { k: "Resultado Líquido", v: d.resultadoLiquido },
              { k: "Margem %", v: d.margemPercent },
            ],
          },
          {
            name: "Receitas",
            columns: [
              { header: "Categoria", key: "categoria" },
              { header: "Valor", key: "valor" },
            ],
            rows: d.receitasPorCategoria,
          },
          {
            name: "Despesas",
            columns: [
              { header: "Categoria", key: "categoria" },
              { header: "Valor", key: "valor" },
            ],
            rows: d.despesasPorCategoria,
          },
        ];
      } else if ("vendas" in data) {
        const v = data.vendas!;
        title = "Vendas";
        sheets = [
          {
            name: "Resumo",
            columns: [
              { header: "Métrica", key: "k" },
              { header: "Valor", key: "v" },
            ],
            rows: [
              { k: "Período", v: `${v.from} → ${v.to}` },
              { k: "Total Vendas", v: v.totalVendas },
              { k: "Ticket Médio", v: v.ticketMedio },
              { k: "Agrupamento", v: v.groupBy },
            ],
          },
          {
            name: "Série",
            columns: [
              { header: "Data", key: "date" },
              { header: "Total", key: "total" },
              { header: "Qtd Pedidos", key: "count" },
            ],
            rows: v.series,
          },
          {
            name: "Top Clientes",
            columns: [
              { header: "Cliente", key: "nome" },
              { header: "Valor", key: "valor" },
              { header: "Qtd", key: "count" },
            ],
            rows: v.topClientes,
          },
          {
            name: "Top Produtos",
            columns: [
              { header: "Produto", key: "produto" },
              { header: "Valor", key: "valor" },
              { header: "Qtd Itens", key: "qtdItens" },
            ],
            rows: v.topProdutos,
          },
        ];
      } else if ("producao" in data) {
        const p = data.producao!;
        title = "Producao";
        sheets = [
          {
            name: "Resumo",
            columns: [
              { header: "Métrica", key: "k" },
              { header: "Valor", key: "v" },
            ],
            rows: [
              { k: "Período", v: `${p.from} → ${p.to}` },
              { k: "Total OPs", v: p.totalOPs },
              { k: "Total OSs", v: p.totalOSs },
              { k: "Tempo Médio (dias)", v: p.tempoMedioConclusaoDias },
            ],
          },
          {
            name: "OPs por Status",
            columns: [
              { header: "Status", key: "status" },
              { header: "Qtd", key: "count" },
            ],
            rows: Object.entries(p.countOPsPorStatus).map(([status, count]) => ({
              status,
              count,
            })),
          },
          {
            name: "OSs por Status",
            columns: [
              { header: "Status", key: "status" },
              { header: "Qtd", key: "count" },
            ],
            rows: Object.entries(p.countOSsPorStatus).map(([status, count]) => ({
              status,
              count,
            })),
          },
          {
            name: "Mecânicos",
            columns: [
              { header: "Mecânico", key: "mecanico" },
              { header: "Qtd", key: "count" },
            ],
            rows: p.mecanicosMaisAtivos,
          },
        ];
      } else {
        const e = data.estoque;
        title = "Estoque";
        sheets = [
          {
            name: "Resumo",
            columns: [
              { header: "Métrica", key: "k" },
              { header: "Valor", key: "v" },
            ],
            rows: [
              { k: "Itens Ativos", v: e.itensAtivos },
              { k: "Valor Total", v: e.valorTotalEstoque },
              { k: "Movimentos 30d", v: e.movimentosUltimos30Dias },
            ],
          },
          {
            name: "Estoque Baixo",
            columns: [
              { header: "Item", key: "nome" },
              { header: "Quantidade", key: "quantidade" },
              { header: "Estoque Mínimo", key: "estoqueMinimo" },
            ],
            rows: e.itensComEstoqueBaixo,
          },
        ];
      }

      const buffer = await renderExcel(title, sheets);
      const { key, url } = await storagePut(
        `reports/${ctx.user.id}/${tipo}-${Date.now()}.xlsx`,
        buffer,
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      );
      return { key, url };
    }),
});

// Unused-imports guard for `desc` (kept for readability above)
void desc;
void customers;
