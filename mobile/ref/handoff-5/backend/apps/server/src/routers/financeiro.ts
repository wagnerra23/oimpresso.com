// ──────────────────────────────────────────────────────────────
// Router Financeiro (Fase 2). Títulos a receber/pagar ligados à OS
// e ao cliente POR ID (não string). Saldo e status DERIVADOS das
// baixas. Resolve v2 §2 do laudo.
// ──────────────────────────────────────────────────────────────
import { z } from "zod";
import { and, eq, inArray, gte, lte } from "drizzle-orm";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure } from "../trpc/trpc";
import { withTenant } from "../trpc/context";
import { assertCan } from "../domain/policy";
import { audit } from "../lib/audit";
import { titulos, tituloParcelas, baixas, pedidos } from "../db/schema";
import { gerarParcelas, saldoEmAberto, statusTitulo, aging } from "../domain/finance/titulos";
import { calcularDRE, projetarFluxo, type LancamentoFuturo } from "../domain/finance/dre";

export const financeiroRouter = router({
  // Cria título a receber a partir de uma OS. Gera parcelas sem perder centavo.
  criarReceber: protectedProcedure
    .input(z.object({
      pedidoId: z.string().uuid(),
      pessoaId: z.string().uuid(),
      valorCents: z.number().int().positive(),
      parcelas: z.number().int().min(1).max(48).default(1),
      primeiroVencimento: z.coerce.date(),
      intervaloDias: z.number().int().positive().default(30),
    }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "criar", "titulo");

        // Garante que o pedido é do tenant (integridade por id).
        const [ped] = await tx.select({ id: pedidos.id }).from(pedidos)
          .where(and(eq(pedidos.id, input.pedidoId), eq(pedidos.tenantId, ctx.user.tenantId)));
        if (!ped) throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });

        const [titulo] = await tx.insert(titulos).values({
          tenantId: ctx.user.tenantId, pedidoId: input.pedidoId, pessoaId: input.pessoaId,
          tipo: "receber", valorCents: input.valorCents, status: "aberto",
        }).returning();

        const parcelas = gerarParcelas(input.valorCents, input.parcelas, input.primeiroVencimento, input.intervaloDias);
        await tx.insert(tituloParcelas).values(
          parcelas.map((p) => ({
            tituloId: titulo.id, numero: p.numero,
            vencimentoAt: p.vencimentoAt, valorCents: p.valorCents, status: "aberto",
          })),
        );
        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id, entity: "titulo", entityId: titulo.id,
          action: "criar_receber", after: { valorCents: input.valorCents, parcelas: input.parcelas },
        });
        return { ...titulo, parcelas };
      })),

  // Baixa (pagamento) parcial ou total de uma parcela. Status recalculado.
  baixar: protectedProcedure
    .input(z.object({
      tituloId: z.string().uuid(),
      parcelaId: z.string().uuid().optional(),
      valorCents: z.number().int().positive(),
      meio: z.enum(["pix", "boleto", "dinheiro", "cartao", "transferencia"]),
      contaId: z.string().uuid().optional(),
    }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "baixar", "titulo");

        const [titulo] = await tx.select().from(titulos)
          .where(and(eq(titulos.id, input.tituloId), eq(titulos.tenantId, ctx.user.tenantId)));
        if (!titulo) throw new TRPCError({ code: "NOT_FOUND", message: "Título não encontrado" });

        // Saldo atual derivado das baixas já existentes.
        const jaBaixado = await tx.select({ v: baixas.valorCents }).from(baixas).where(eq(baixas.tituloId, titulo.id));
        const saldoAtual = saldoEmAberto(titulo.valorCents, jaBaixado.map((b) => b.v));
        if (input.valorCents > saldoAtual) {
          throw new TRPCError({ code: "PRECONDITION_FAILED", message: `Baixa (${input.valorCents}) excede o saldo em aberto (${saldoAtual}).` });
        }

        await tx.insert(baixas).values({
          tituloId: titulo.id, parcelaId: input.parcelaId,
          valorCents: input.valorCents, contaId: input.contaId, meio: input.meio,
        });

        // Recalcula status do título a partir das baixas (derivado, nunca digitado).
        const todas = [...jaBaixado.map((b) => b.v), input.valorCents];
        const novoStatus = statusTitulo(titulo.valorCents, todas, titulo.createdAt as Date);
        await tx.update(titulos).set({ status: novoStatus }).where(eq(titulos.id, titulo.id));

        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id, entity: "titulo", entityId: titulo.id,
          action: "baixar", after: { valorCents: input.valorCents, meio: input.meio, status: novoStatus },
        });
        return { saldoRestante: saldoAtual - input.valorCents, status: novoStatus };
      })),

  // Aging de recebíveis (contas a receber por faixa de atraso).
  aging: protectedProcedure
    .query(({ ctx }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "ler", "titulo");

        const abertos = await tx.select().from(titulos)
          .where(and(eq(titulos.tenantId, ctx.user.tenantId), eq(titulos.tipo, "receber"),
            inArray(titulos.status, ["aberto", "parcial", "vencido"])));

        const linhas = [];
        for (const t of abertos) {
          const bx = await tx.select({ v: baixas.valorCents }).from(baixas).where(eq(baixas.tituloId, t.id));
          const [parc] = await tx.select({ venc: tituloParcelas.vencimentoAt }).from(tituloParcelas)
            .where(eq(tituloParcelas.tituloId, t.id)).limit(1);
          linhas.push({
            saldoCents: saldoEmAberto(t.valorCents, bx.map((x) => x.v)),
            vencimentoAt: (parc?.venc ?? t.createdAt) as Date,
          });
        }
        return aging(linhas);
      })),

  // DRE gerencial do período (competência). Agrega receita/deduções/custo/despesa.
  dre: protectedProcedure
    .input(z.object({ desde: z.coerce.date(), ate: z.coerce.date() }))
    .query(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "ler", "titulo");

        // Receita = títulos a receber emitidos no período; custo vem do custeio da OS.
        const recebiveis = await tx.select().from(titulos)
          .where(and(eq(titulos.tenantId, ctx.user.tenantId), eq(titulos.tipo, "receber"),
            gte(titulos.createdAt, input.desde), lte(titulos.createdAt, input.ate)));
        const pagaveis = await tx.select().from(titulos)
          .where(and(eq(titulos.tenantId, ctx.user.tenantId), eq(titulos.tipo, "pagar"),
            gte(titulos.createdAt, input.desde), lte(titulos.createdAt, input.ate)));

        const receitaBrutaCents = recebiveis.reduce((s, t) => s + t.valorCents, 0);
        // deduções (impostos sobre venda) e custo aproximados pelos campos da OS/título
        const deducoesCents = recebiveis.reduce((s, t) => s + ((t as any).impostoCents ?? 0), 0);
        const custoCents = recebiveis.reduce((s, t) => s + ((t as any).custoOsCents ?? 0), 0);
        const despesasCents = pagaveis.reduce((s, t) => s + t.valorCents, 0);

        return calcularDRE({ receitaBrutaCents, deducoesCents, custoCents, despesasCents });
      })),

  // Fluxo de caixa projetado: parcelas a vencer (entradas) − pagáveis (saídas).
  fluxoProjetado: protectedProcedure
    .input(z.object({ saldoInicialCents: z.number().int(), dias: z.number().int().min(1).max(180).default(30) }))
    .query(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "ler", "titulo");

        // Entradas: parcelas em aberto de títulos a receber.
        const recebiveis = await tx.select().from(titulos)
          .where(and(eq(titulos.tenantId, ctx.user.tenantId), eq(titulos.tipo, "receber"),
            inArray(titulos.status, ["aberto", "parcial", "vencido"])));
        const lanc: LancamentoFuturo[] = [];
        for (const t of recebiveis) {
          const parc = await tx.select().from(tituloParcelas)
            .where(and(eq(tituloParcelas.tituloId, t.id), eq(tituloParcelas.status, "aberto")));
          for (const p of parc) lanc.push({ vencimentoAt: p.vencimentoAt as Date, valorCents: p.valorCents, sentido: "entrada" });
        }
        // Saídas: títulos a pagar em aberto.
        const pagaveis = await tx.select().from(titulos)
          .where(and(eq(titulos.tenantId, ctx.user.tenantId), eq(titulos.tipo, "pagar"),
            inArray(titulos.status, ["aberto", "parcial", "vencido"])));
        for (const t of pagaveis) lanc.push({ vencimentoAt: (t.createdAt as Date), valorCents: t.valorCents, sentido: "saida" });

        return projetarFluxo(input.saldoInicialCents, lanc, input.dias);
      })),
});
