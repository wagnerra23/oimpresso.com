// ──────────────────────────────────────────────────────────────
// domain/fiscal · adapter de provedor (porta anticorrupção).
// O domínio NÃO conhece PlugNotas/Focus/Nuvem Fiscal — fala só esta
// interface. Trocar de provedor = nova implementação, zero mudança no
// domínio. Resolve a dependência de protocolo da SEFAZ (v2 §13–14).
// ──────────────────────────────────────────────────────────────
import type { TipoDoc } from "./natureza";

export interface NotaParaEmitir {
  tipo: TipoDoc;
  serie: string;
  numero: number;
  naturezaOperacao: string;
  emitenteTenantId: string;
  destinatario: { nome: string; documento: string; municipio?: string; uf?: string };
  itens: { descricao: string; ncm?: string; quantidade: number; valorUnitCents: number }[];
  impostoPct: number;
  totalCents: number;
}

export interface RetornoEmissao {
  ok: boolean;
  providerRef?: string;   // protocolo/chave do provedor
  chaveAcesso?: string;   // chave NF-e (44 dígitos) quando aplicável
  xml?: string;
  /** preenchido quando ok=false — motivo da rejeição da SEFAZ/prefeitura. */
  rejeicao?: { codigo: string; motivo: string };
}

export interface FiscalProvider {
  emitir(nota: NotaParaEmitir): Promise<RetornoEmissao>;
  cancelar(providerRef: string, justificativa: string): Promise<{ ok: boolean; motivo?: string }>;
  consultar(providerRef: string): Promise<{ status: string }>;
}

/**
 * Provider de simulação para dev/teste (não chama rede). No produto,
 * implemente um adapter concreto (ex.: PlugNotasProvider) com a mesma forma.
 */
export function makeMockProvider(opts?: { rejeitarSe?: (n: NotaParaEmitir) => string | null }): FiscalProvider {
  return {
    async emitir(nota) {
      const motivo = opts?.rejeitarSe?.(nota);
      if (motivo) return { ok: false, rejeicao: { codigo: "MOCK-REJ", motivo } };
      return {
        ok: true,
        providerRef: `MOCK-${nota.serie}-${nota.numero}`,
        chaveAcesso: nota.tipo === "NFe" ? "0".repeat(44) : undefined,
        xml: `<nota tipo="${nota.tipo}" numero="${nota.numero}"/>`,
      };
    },
    async cancelar() { return { ok: true }; },
    async consultar() { return { status: "autorizado" }; },
  };
}
