// ──────────────────────────────────────────────────────────────
// domain/fiscal · máquina de estados do documento fiscal.
// Modela o CAMINHO INFELIZ (v3 §B2): a SEFAZ/prefeitura rejeita, cai,
// exige contingência. Transições inválidas são bloqueadas — o doc não
// "pula" de rascunho para autorizado sem passar pela emissão.
// ──────────────────────────────────────────────────────────────

export type StatusDoc =
  | "rascunho"      // montado, ainda não enviado
  | "enviando"      // transmitido ao provedor/SEFAZ, aguardando retorno
  | "autorizado"    // aprovado (protocolo recebido)
  | "rejeitado"     // recusado (precisa corrigir e reenviar)
  | "contingencia"  // emitido offline (SEFAZ indisponível), regularizar depois
  | "cancelado";    // cancelado após autorização (dentro do prazo legal)

const TRANSICOES: Record<StatusDoc, StatusDoc[]> = {
  rascunho:     ["enviando", "contingencia"],
  enviando:     ["autorizado", "rejeitado", "contingencia"],
  rejeitado:    ["rascunho", "enviando"],     // corrige e reenvia
  contingencia: ["autorizado", "rejeitado"],  // transmite quando volta
  autorizado:   ["cancelado"],
  cancelado:    [],
};

export function podeTransicionar(de: StatusDoc, para: StatusDoc): boolean {
  return TRANSICOES[de]?.includes(para) ?? false;
}

export class TransicaoInvalidaError extends Error {
  constructor(de: StatusDoc, para: StatusDoc) {
    super(`Transição fiscal inválida: ${de} → ${para}`);
    this.name = "TransicaoInvalidaError";
  }
}

/** Aplica a transição ou lança. Garante que o documento nunca pule etapas. */
export function transicionar(de: StatusDoc, para: StatusDoc): StatusDoc {
  if (!podeTransicionar(de, para)) throw new TransicaoInvalidaError(de, para);
  return para;
}

/** Estados terminais não aceitam mais mudança (exceto cancelamento de autorizado). */
export function ehTerminal(s: StatusDoc): boolean {
  return s === "cancelado";
}
