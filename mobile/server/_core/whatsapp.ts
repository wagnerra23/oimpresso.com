import { getDb } from "../db";
import { whatsappMessages } from "../../drizzle/schema";
import { randomUUID } from "node:crypto";

const ENV = process.env;

function normalizePhone(raw: string): string {
  const digits = raw.replace(/\D/g, "");
  if (!digits) return "";
  if (digits.startsWith("55")) return digits;
  if (digits.length === 11 || digits.length === 10) return `55${digits}`;
  return digits;
}

export async function sendWhatsApp(input: {
  userId: number;
  /** F3-08: tenant scope. Optional so legacy callers still compile. */
  companyId?: string | null;
  telefone: string;
  mensagem: string;
  customerId?: string | null;
  referenciaTipo?: string;
  referenciaId?: string;
}): Promise<{ ok: boolean; providerMessageId?: string; error?: string }> {
  const db = await getDb();
  if (!db) return { ok: false, error: "DB indisponível" };

  const phone = normalizePhone(input.telefone);
  const baseRow = {
    id: randomUUID(),
    userId: input.userId,
    companyId: input.companyId ?? null,
    customerId: input.customerId ?? null,
    telefone: phone,
    mensagem: input.mensagem,
    referenciaTipo: input.referenciaTipo ?? null,
    referenciaId: input.referenciaId ?? null,
  };

  const instanceId = ENV.ZAPI_INSTANCE_ID;
  const token = ENV.ZAPI_TOKEN;
  const clientToken = ENV.ZAPI_CLIENT_TOKEN;

  if (!instanceId || !token || !clientToken) {
    await db
      .insert(whatsappMessages)
      .values({
        ...baseRow,
        tipo: "pending",
        erro: "WhatsApp não configurado (ZAPI_*)",
      });
    return { ok: false, error: "WhatsApp não configurado" };
  }

  try {
    const res = await fetch(
      `https://api.z-api.io/instances/${instanceId}/token/${token}/send-text`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "Client-Token": clientToken,
        },
        body: JSON.stringify({ phone, message: input.mensagem }),
      },
    );
    if (!res.ok) {
      const errText = await res.text().catch(() => res.statusText);
      await db
        .insert(whatsappMessages)
        .values({ ...baseRow, tipo: "falhou", erro: errText.slice(0, 1000) });
      return { ok: false, error: errText };
    }
    const json = (await res.json().catch(() => ({}))) as {
      messageId?: string;
      id?: string;
    };
    const providerMessageId = json.messageId || json.id || null;
    await db
      .insert(whatsappMessages)
      .values({ ...baseRow, tipo: "enviada", providerMessageId });
    return { ok: true, providerMessageId: providerMessageId ?? undefined };
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : String(e);
    await db
      .insert(whatsappMessages)
      .values({ ...baseRow, tipo: "falhou", erro: msg });
    return { ok: false, error: msg };
  }
}

export const whatsappTemplates = {
  pedido_em_producao: (nome: string, produto: string) =>
    `Olá ${nome}! Seu pedido (${produto}) entrou em produção. Avisaremos quando estiver pronto.`,
  os_pronta: (nome: string, placa: string) =>
    `Olá ${nome}, seu veículo ${placa} está pronto para retirada na nossa oficina. Aguardamos você!`,
  aprovacao_orcamento: (nome: string, link: string) =>
    `Olá ${nome}, segue o link do orçamento para aprovação: ${link}`,
  entrega_agendada: (nome: string, data: string) =>
    `Olá ${nome}, sua entrega está agendada para ${data}. Confirme se está tudo certo.`,
};

export type WhatsappTemplateName = keyof typeof whatsappTemplates;
