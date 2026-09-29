// ──────────────────────────────────────────────────────────────
// Router Fiscal (Fase 2 · coração). Orquestra a emissão:
//   numeração atômica → provider → máquina de estados → persistência.
// A rejeição da SEFAZ/prefeitura é tratada (status rejeitado + motivo),
// não um toast perdido (v3 §B2). Tudo destravado pela fundação:
// item estruturado, data real e numeração sequencial já existem.
// ──────────────────────────────────────────────────────────────
import { z } from "zod";
import { and, eq } from "drizzle-orm";
import { TRPCError } from "@trpc/server";
import { router, protectedProcedure } from "../trpc/trpc";
import { withTenant } from "../trpc/context";
import { assertCan } from "../domain/policy";
import { audit } from "../lib/audit";
import { nextNumero } from "../lib/sequences";
import { documentosFiscais, pedidos, pedidoItens, pessoas, tenants } from "../db/schema";
import { tributar, tipoDocumento, naturezaDe, type ItemFiscal } from "../domain/fiscal/natureza";
import { transicionar, type StatusDoc } from "../domain/fiscal/documentoStateMachine";
import { makeMockProvider, type FiscalProvider, type NotaParaEmitir } from "../domain/fiscal/provider";

// Injetável: produção passa o adapter real (PlugNotas etc.).
const provider: FiscalProvider = makeMockProvider();

export const fiscalRouter = router({
  // Emite o documento fiscal de uma OS. Decide NFS-e/NF-e/NFC-e pela natureza.
  emitir: protectedProcedure
    .input(z.object({
      pedidoId: z.string().uuid(),
      sobEncomenda: z.boolean().default(true),     // gráfica: padrão é serviço
      consumidorFinal: z.boolean().default(false),
    }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "emitir", "documento_fiscal");

        const [pedido] = await tx.select().from(pedidos)
          .where(and(eq(pedidos.id, input.pedidoId), eq(pedidos.tenantId, ctx.user.tenantId)));
        if (!pedido) throw new TRPCError({ code: "NOT_FOUND", message: "Pedido não encontrado" });

        const [emit] = await tx.select().from(tenants).where(eq(tenants.id, ctx.user.tenantId));
        const [cliente] = await tx.select().from(pessoas).where(eq(pessoas.id, pedido.clienteId));
        const itens = await tx.select().from(pedidoItens).where(eq(pedidoItens.pedidoId, pedido.id));
        if (!itens.length) throw new TRPCError({ code: "PRECONDITION_FAILED", message: "OS sem itens — nada a faturar" });

        // Natureza/tipo/imposto pela regra do domínio.
        const itemFiscal: ItemFiscal = { sobEncomenda: input.sobEncomenda, consumidorFinal: input.consumidorFinal };
        const tipo = tipoDocumento(itemFiscal);
        const trib = tributar(itemFiscal, (emit?.regimeTributario ?? "simples") as any);

        // Numeração sequencial atômica por (tenant, série/tipo) — gapless.
        const serie = "1";
        const numero = await nextNumero(tx, ctx.user.tenantId, `FISCAL:${tipo}:${serie}`);

        // Cria o documento em RASCUNHO (estado inicial da máquina).
        const totalCents = itens.reduce((s, i) => s + (i.precoUnitCents ?? 0) * Number(i.qtd), 0);
        const [doc] = await tx.insert(documentosFiscais).values({
          tenantId: ctx.user.tenantId, pedidoId: pedido.id, tipo,
          serie, numero, status: "rascunho", natureza: naturezaDe(itemFiscal),
        }).returning();

        // Monta a nota e transmite via provider (porta anticorrupção).
        const nota: NotaParaEmitir = {
          tipo, serie, numero, naturezaOperacao: trib.baseLegal,
          emitenteTenantId: ctx.user.tenantId,
          destinatario: { nome: cliente?.nome ?? "Consumidor", documento: (cliente as any)?.doc ?? "" },
          itens: itens.map((i) => ({
            descricao: (i.atributos as any)?.descricao ?? "Item gráfico",
            ncm: (i.atributos as any)?.ncm, quantidade: Number(i.qtd), valorUnitCents: i.precoUnitCents ?? 0,
          })),
          impostoPct: trib.aliquotaPct, totalCents,
        };

        let status: StatusDoc = transicionar("rascunho", "enviando");
        const ret = await provider.emitir(nota);

        if (!ret.ok) {
          // CAMINHO INFELIZ: rejeição tratada — status + motivo, sem quebrar.
          status = transicionar(status, "rejeitado");
          await tx.update(documentosFiscais).set({ status, providerRef: ret.rejeicao?.codigo })
            .where(eq(documentosFiscais.id, doc.id));
          await audit(tx, {
            tenantId: ctx.user.tenantId, actorId: ctx.user.id, entity: "documento_fiscal", entityId: doc.id,
            action: "rejeitado", after: { motivo: ret.rejeicao?.motivo },
          });
          throw new TRPCError({ code: "BAD_REQUEST", message: `Documento rejeitado: ${ret.rejeicao?.motivo}` });
        }

        status = transicionar(status, "autorizado");
        const [final] = await tx.update(documentosFiscais)
          .set({ status, providerRef: ret.providerRef, xml: ret.xml })
          .where(eq(documentosFiscais.id, doc.id)).returning();

        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id, entity: "documento_fiscal", entityId: doc.id,
          action: "autorizado", after: { tipo, numero, imposto: trib.imposto, aliquotaPct: trib.aliquotaPct },
        });
        return { documento: final, tributacao: trib };
      })),

  // Cancela um documento autorizado (dentro do prazo legal).
  cancelar: protectedProcedure
    .input(z.object({ documentoId: z.string().uuid(), justificativa: z.string().min(15) }))
    .mutation(({ ctx, input }) =>
      withTenant(ctx, async (tx) => {
        assertCan(ctx.user.papel, "emitir", "documento_fiscal");
        const [doc] = await tx.select().from(documentosFiscais)
          .where(and(eq(documentosFiscais.id, input.documentoId), eq(documentosFiscais.tenantId, ctx.user.tenantId)));
        if (!doc) throw new TRPCError({ code: "NOT_FOUND" });

        const status = transicionar(doc.status as StatusDoc, "cancelado"); // valida que estava autorizado
        if (doc.providerRef) await provider.cancelar(doc.providerRef, input.justificativa);
        const [final] = await tx.update(documentosFiscais).set({ status })
          .where(eq(documentosFiscais.id, doc.id)).returning();
        await audit(tx, {
          tenantId: ctx.user.tenantId, actorId: ctx.user.id, entity: "documento_fiscal", entityId: doc.id,
          action: "cancelado", after: { justificativa: input.justificativa },
        });
        return final;
      })),
});
