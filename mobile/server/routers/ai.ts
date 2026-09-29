import { TRPCError } from "@trpc/server";
import { and, desc, eq, sql } from "drizzle-orm";
import { z } from "zod";

import {
  priceTables,
  quotes,
  serviceOrders,
} from "../../drizzle/schema";
import { getDb } from "../db";
import { invokeLLM } from "../_core/llm";
import { companyProcedure, router } from "../_core/trpc";

/**
 * AI router — F3-06 (sugestão de diagnóstico para Mecânica) e
 * F3-07 (sugestão de preços para Comunicação Visual).
 *
 * Stateless: usa histórico do próprio usuário como contexto + LLM via
 * helper `invokeLLM`. Sem novas tabelas. Em caso de IA indisponível
 * (env não configurado / falha de rede), devolve SERVICE_UNAVAILABLE.
 */

const DiagnosisInputSchema = z.object({
  queixaCliente: z.string().min(5),
  vehicleMarca: z.string().optional(),
  vehicleModelo: z.string().optional(),
  vehicleAno: z.number().optional(),
  kmAtual: z.number().optional(),
});

const PricingItemSchema = z.object({
  descricao: z.string(),
  material: z.string().optional(),
  larguraCm: z.number(),
  alturaCm: z.number(),
  quantidade: z.number(),
});

const PricingInputSchema = z.object({
  items: z.array(PricingItemSchema).min(1),
  customerName: z.string().optional(),
});

function parseJsonContent(content: string): Record<string, unknown> {
  try {
    return JSON.parse(content) as Record<string, unknown>;
  } catch {
    // Sometimes LLM wraps JSON in markdown fences — strip them.
    const stripped = content
      .replace(/^```(?:json)?/i, "")
      .replace(/```$/, "")
      .trim();
    try {
      return JSON.parse(stripped) as Record<string, unknown>;
    } catch {
      throw new TRPCError({
        code: "INTERNAL_SERVER_ERROR",
        message: "IA retornou resposta em formato inválido",
      });
    }
  }
}

export const aiRouter = router({
  /**
   * F3-06 — Sugestão de diagnóstico para Mecânica.
   * Recebe queixa + dados do veículo, usa últimas 20 OSs do usuário
   * com queixa+diagnóstico preenchidos como contexto, devolve JSON
   * estruturado.
   */
  suggestDiagnosis: companyProcedure
    .input(DiagnosisInputSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) {
        throw new TRPCError({
          code: "SERVICE_UNAVAILABLE",
          message: "Banco de dados indisponível",
        });
      }

      const history = await db
        .select({
          queixa: serviceOrders.queixaCliente,
          diagnostico: serviceOrders.diagnostico,
          valor: serviceOrders.valorTotal,
        })
        .from(serviceOrders)
        .where(
          and(
            eq(serviceOrders.companyId, ctx.companyId),
            sql`${serviceOrders.queixaCliente} IS NOT NULL`,
            sql`${serviceOrders.diagnostico} IS NOT NULL`,
          ),
        )
        .orderBy(desc(serviceOrders.createdAt))
        .limit(20);

      const historicoStr = history
        .slice(0, 5)
        .map(
          (h, i) =>
            `OS ${i + 1}: Queixa "${h.queixa ?? ""}" → Diagnóstico "${h.diagnostico ?? ""}" — R$ ${h.valor ?? "0"}`,
        )
        .join("\n");

      const systemPrompt =
        "Você é um mecânico experiente. Com base na queixa do cliente e no histórico de OSs anteriores da oficina, sugira um diagnóstico inicial provável, indicando peças e serviços que tipicamente são necessários. Seja objetivo e use linguagem técnica acessível. Responda SEMPRE em pt-BR e SEMPRE em JSON válido.";

      const userPrompt = `Veículo: ${input.vehicleMarca ?? "?"} ${input.vehicleModelo ?? "?"} ${input.vehicleAno ?? ""}\nKM atual: ${input.kmAtual ?? "?"}\nQueixa: ${input.queixaCliente}\n\nHistórico de OSs similares da oficina:\n${historicoStr || "(sem histórico)"}\n\nSugira um diagnóstico inicial em formato JSON com estes campos exatos: { "diagnosticoSugerido": string, "pecasProvaveis": string[], "servicosProvaveis": string[], "tempoEstimadoHoras": number, "confianca": "baixa"|"media"|"alta" }`;

      let response;
      try {
        response = await invokeLLM({
          messages: [
            { role: "system", content: systemPrompt },
            { role: "user", content: userPrompt },
          ],
          response_format: {
            type: "json_schema",
            json_schema: {
              name: "diagnosis_suggestion",
              schema: {
                type: "object",
                properties: {
                  diagnosticoSugerido: { type: "string" },
                  pecasProvaveis: {
                    type: "array",
                    items: { type: "string" },
                  },
                  servicosProvaveis: {
                    type: "array",
                    items: { type: "string" },
                  },
                  tempoEstimadoHoras: { type: "number" },
                  confianca: {
                    type: "string",
                    enum: ["baixa", "media", "alta"],
                  },
                },
                required: [
                  "diagnosticoSugerido",
                  "pecasProvaveis",
                  "servicosProvaveis",
                  "tempoEstimadoHoras",
                  "confianca",
                ],
                additionalProperties: false,
              },
              strict: true,
            },
          },
        });
      } catch (err) {
        throw new TRPCError({
          code: "SERVICE_UNAVAILABLE",
          message:
            "IA indisponível: " +
            (err instanceof Error ? err.message : "erro desconhecido"),
        });
      }

      const content = response.choices?.[0]?.message?.content;
      const text =
        typeof content === "string"
          ? content
          : Array.isArray(content)
            ? content
                .map((p) => (p.type === "text" ? p.text : ""))
                .join("")
            : "{}";
      const parsed = parseJsonContent(text || "{}");
      return parsed as {
        diagnosticoSugerido: string;
        pecasProvaveis: string[];
        servicosProvaveis: string[];
        tempoEstimadoHoras: number;
        confianca: "baixa" | "media" | "alta";
      };
    }),

  /**
   * F3-07 — Sugestão de precificação para Comunicação Visual.
   * Recebe itens (dimensões + material) e usa a tabela de preços
   * + últimos 10 orçamentos aprovados como contexto.
   */
  suggestPricing: companyProcedure
    .input(PricingInputSchema)
    .mutation(async ({ ctx, input }) => {
      const db = await getDb();
      if (!db) {
        throw new TRPCError({
          code: "SERVICE_UNAVAILABLE",
          message: "Banco de dados indisponível",
        });
      }

      const tables = await db
        .select()
        .from(priceTables)
        .where(
          and(eq(priceTables.companyId, ctx.companyId), eq(priceTables.ativo, 1)),
        )
        .limit(20);

      const recentQuotes = await db
        .select({
          valorTotal: quotes.valorTotal,
          titulo: quotes.titulo,
        })
        .from(quotes)
        .where(
          and(
            eq(quotes.companyId, ctx.companyId),
            eq(quotes.status, "aprovado"),
          ),
        )
        .orderBy(desc(quotes.createdAt))
        .limit(10);

      const tabelaStr = tables
        .map(
          (t) =>
            `${t.material}: R$ ${t.precoPorM2}/m² (+ R$ ${t.acabamentoExtra ?? 0} acabamento)`,
        )
        .join("\n");

      const recentesStr = recentQuotes
        .map((q) => `${q.titulo}: R$ ${q.valorTotal}`)
        .join("\n");

      const itensStr = input.items
        .map((it, i) => {
          const m2 = (
            (it.larguraCm * it.alturaCm * it.quantidade) /
            10000
          ).toFixed(2);
          return `Item ${i + 1}: ${it.descricao} — ${it.larguraCm}x${it.alturaCm}cm × ${it.quantidade}u (${m2}m²) — material: ${it.material ?? "?"}`;
        })
        .join("\n");

      const systemPrompt =
        "Você é um especialista em precificação de orçamentos para empresas de Comunicação Visual (impressão, plotagem, sinalização). Use a tabela de preços da empresa e histórico de orçamentos aprovados para sugerir preços competitivos e justificar a margem. Responda SEMPRE em pt-BR e SEMPRE em JSON válido.";

      const userPrompt = `Cliente: ${input.customerName ?? "novo"}\n\nItens a precificar:\n${itensStr}\n\nTabela de preços atual:\n${tabelaStr || "(sem tabela)"}\n\nÚltimos orçamentos aprovados:\n${recentesStr || "(sem histórico)"}\n\nSugira preço unitário (R$/m²), preço total por item, total geral e margem sugerida. Formato JSON exato: { "itensSugeridos": [{ "descricao": string, "precoPorM2Sugerido": number, "totalItem": number, "justificativa": string }], "totalGeral": number, "margemSugerida": number, "observacoes": string }`;

      let response;
      try {
        response = await invokeLLM({
          messages: [
            { role: "system", content: systemPrompt },
            { role: "user", content: userPrompt },
          ],
          response_format: {
            type: "json_schema",
            json_schema: {
              name: "pricing_suggestion",
              schema: {
                type: "object",
                properties: {
                  itensSugeridos: {
                    type: "array",
                    items: {
                      type: "object",
                      properties: {
                        descricao: { type: "string" },
                        precoPorM2Sugerido: { type: "number" },
                        totalItem: { type: "number" },
                        justificativa: { type: "string" },
                      },
                      required: [
                        "descricao",
                        "precoPorM2Sugerido",
                        "totalItem",
                        "justificativa",
                      ],
                      additionalProperties: false,
                    },
                  },
                  totalGeral: { type: "number" },
                  margemSugerida: { type: "number" },
                  observacoes: { type: "string" },
                },
                required: [
                  "itensSugeridos",
                  "totalGeral",
                  "margemSugerida",
                  "observacoes",
                ],
                additionalProperties: false,
              },
              strict: true,
            },
          },
        });
      } catch (err) {
        throw new TRPCError({
          code: "SERVICE_UNAVAILABLE",
          message:
            "IA indisponível: " +
            (err instanceof Error ? err.message : "erro desconhecido"),
        });
      }

      const content = response.choices?.[0]?.message?.content;
      const text =
        typeof content === "string"
          ? content
          : Array.isArray(content)
            ? content
                .map((p) => (p.type === "text" ? p.text : ""))
                .join("")
            : "{}";
      const parsed = parseJsonContent(text || "{}");
      return parsed as {
        itensSugeridos: Array<{
          descricao: string;
          precoPorM2Sugerido: number;
          totalItem: number;
          justificativa: string;
        }>;
        totalGeral: number;
        margemSugerida: number;
        observacoes: string;
      };
    }),
});
