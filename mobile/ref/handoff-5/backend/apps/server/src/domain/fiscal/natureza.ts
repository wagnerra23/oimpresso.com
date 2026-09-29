// ──────────────────────────────────────────────────────────────
// domain/fiscal · regra de natureza (ISS × ICMS) e tipo de documento.
// Gráfica vive o pior caso: impresso PERSONALIZADO sob encomenda é
// SERVIÇO (NFS-e/ISS, municipal); venda de produto pronto de prateleira
// é MERCADORIA (NF-e/ICMS, estadual). Esta regra decide qual emitir.
// Resolve v2 §13 (ISS×ICMS ignorado).
// ──────────────────────────────────────────────────────────────

export type Natureza = "servico" | "mercadoria";
export type TipoDoc = "NFSe" | "NFe" | "NFCe";
export type RegimeTributario = "simples" | "presumido" | "real";

export interface ItemFiscal {
  /** true = feito sob encomenda/personalizado (serviço gráfico). */
  sobEncomenda: boolean;
  /** venda direta a consumidor final (favorece NFC-e). */
  consumidorFinal: boolean;
  ncm?: string;
}

/**
 * Natureza da operação. A personalização sob encomenda é o gatilho de
 * SERVIÇO (Súmula/relevante para o setor gráfico). Produto pronto é mercadoria.
 */
export function naturezaDe(item: ItemFiscal): Natureza {
  return item.sobEncomenda ? "servico" : "mercadoria";
}

/** Documento fiscal correto a partir da natureza + destinatário. */
export function tipoDocumento(item: ItemFiscal): TipoDoc {
  if (naturezaDe(item) === "servico") return "NFSe";          // ISS, municipal
  return item.consumidorFinal ? "NFCe" : "NFe";               // ICMS, estadual
}

export interface TributacaoResultado {
  natureza: Natureza;
  tipoDoc: TipoDoc;
  imposto: "ISS" | "ICMS";
  /** alíquota efetiva estimada (%). Tabela real vem do município/UF + regime. */
  aliquotaPct: number;
  baseLegal: string;
}

// Alíquotas-EXEMPLO para simulação. No produto, vêm de tabela por município
// (ISS) e por UF/regime (ICMS), mantidas via provedor fiscal.
const ISS_PADRAO_PCT = 3;       // típico de serviços gráficos (2–5% conforme município)
const ICMS_PADRAO_PCT = 18;     // interno típico; varia por UF/NCM/ST

export function tributar(item: ItemFiscal, _regime: RegimeTributario): TributacaoResultado {
  const natureza = naturezaDe(item);
  const tipoDoc = tipoDocumento(item);
  if (natureza === "servico") {
    return { natureza, tipoDoc, imposto: "ISS", aliquotaPct: ISS_PADRAO_PCT, baseLegal: "ISS — LC 116/2003 (lista de serviços, gráfica sob encomenda)" };
  }
  return { natureza, tipoDoc, imposto: "ICMS", aliquotaPct: ICMS_PADRAO_PCT, baseLegal: "ICMS — RICMS da UF (mercadoria)" };
}
