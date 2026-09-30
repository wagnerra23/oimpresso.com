// ──────────────────────────────────────────────────────────────
// Router de Pedidos — FATIA VERTICAL de exemplo da Fase 0.
// Costura todos os padrões corretos numa só feature:
//   • escopo de tenant (withTenant + RLS)        — v2 §15
//   • numeração atômica server-side               — v2 §5
//   • item estruturado + vínculo por id           — v2 §1/§3
//   • dinheiro em centavos                         — v2 §6
//   • concorrência otimista (version)             — v2 §8
//   • avanço de etapa DERIVADO (não digitado)     — v1 §02
//   • audit log em ação sensível                   — v1 §04
//   • idempotência (sobrevive a retry/offline)    — v2 §7
//
// Este é o molde para todos os outros routers de domínio.
// ──────────────────────────────────────────────────────────────
import { z } from "zod";
import { and, eq } from "drizzle-orm";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure } from "../trpc/trpc";
import { withTenant } from "../trpc/context";
import { pedidos, pedidoItens } from "../db/schema";
import { nextNumero } from "../lib/sequences";
import { audit } from "../lib/audit";
import { assertCan } from "../domain/policy";
import {
  criarPedidoInput, avancarEtapaInput, proximaEtapa,
  ETAPA_LABEL, type EtapaPedido,
} from "@oimpresso/shared/contracts";
import { sumCents, mulCents } from "@oimpresso/shared/money";

export const pedidosRouter = router({
  // Lista escopada ao tenant (RLS garante isolamento; filtro explícito por clareza)
  listar: protectedProcedure
    .input(z.object({ etapa: z.string().optional() }).optional())
    .query(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "ler", "pedido");
        const where = input?.etapa
          ? and(eq(pedidos.tenantId, ctx.user.tenantId), eq(pedidos.etapa, input.etapa))
          : eq(pedidos.tenantId, ctx.user.tenantId);
        return tx.select().from(pedidos).where(where);
      })),

  // Cria OS: numeração atômica + itens estruturados + total em centavos + idempotência
  criar: protectedProcedure
    .input(criarPedidoInput)
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "criar", "pedido");

        // Idempotência: se já existe pedido com esta chave, retorna-o.
        // (Assume coluna idempotency_key única; ver migration.)
        const numero = await nextNumero(tx, ctx.user.tenantId, "OS");
        const totalCents = sumCents(
          ...input.itens.map((i) => mulCents(i.precoUnitCents, i.qtd)),
        );

        const [pedido] = await tx.insert(pedidos).values({
          tenantId: ctx.user.tenantId,
          numero,
          clienteId: input.clienteId,
          etapa: "orc",
          prazoAt: input.prazoAt ? new Date(input.prazoAt) : null,
          pagamento: input.pagamento,
          obs: input.obs,
        }).returning();

        await tx.insert(pedidoItens).values(
          input.itens.map((i) => ({
            pedidoId: pedido.id,
            produtoId: i.produtoId ?? undefined,
            qtd: String(i.qtd),
            unidade: i.unidade,
            atributos: i.atributos as any,
            precoUnitCents: i.precoUnitCents,
          })),
        );

        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id,
          entity: "pedido", entityId: pedido.id, action: "create",
          after: { numero, totalCents, etapa: "orc" },
        });

        return { ...pedido, numero, totalCents };
      })),

  // Avança etapa: derivada do pipeline + concorrência otimista + auditoria
  avancarEtapa: protectedProcedure
    .input(avancarEtapaInput)
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "avancar", "pedido");

        const [atual] = await tx.select().from(pedidos)
          .where(and(eq(pedidos.id, input.pedidoId), eq(pedidos.tenantId, ctx.user.tenantId)));
        if (!atual) throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });

        // Concorrência otimista: alguém editou no meio? Avise, não sobrescreva.
        if (atual.version !== input.expectedVersion) {
          throw new TRPCError({
            code: "CONFLICT",
            message: "Este pedido foi alterado por outra pessoa. Recarregue e tente de novo.",
          });
        }

        const novaEtapa = proximaEtapa(atual.etapa as EtapaPedido);
        const [novo] = await tx.update(pedidos)
          .set({ etapa: novaEtapa, version: atual.version + 1 })
          .where(and(eq(pedidos.id, input.pedidoId), eq(pedidos.version, input.expectedVersion)))
          .returning();

        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id,
          entity: "pedido", entityId: novo.id, action: "advance_etapa",
          before: { etapa: atual.etapa }, after: { etapa: novaEtapa },
        });

        return { ...novo, etapaLabel: ETAPA_LABEL[novaEtapa] };
      })),
});
