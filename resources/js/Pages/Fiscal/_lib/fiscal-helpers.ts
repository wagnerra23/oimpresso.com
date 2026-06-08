// fiscal-helpers.ts — utilitários do módulo Fiscal
// Port do design fiscal-page.jsx §1 HELPERS (R#1 KB-9.75)

export const brl = (n: number | null | undefined): string =>
  (n ?? 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

export const truncKey = (k: string | null | undefined): string =>
  k ? `${k.slice(0, 4)}…${k.slice(-6)}` : '—';

export const formatDoc = (
  cnpj: string | null | undefined,
  cpf: string | null | undefined,
): string => cnpj || cpf || '—';

export type Urgency = 'ok' | 'warn' | 'crit';

export interface CancelWindow {
  h: number;
  m: number;
  urgency: Urgency;
}
export interface CCeWindow {
  d: number;
  urgency: Urgency;
}

export interface NotaForPrazo {
  emittedAtIso?: string | null;
  modelo: number;
  status: string;
}

/**
 * Janela de cancelamento SEFAZ — 24h NFC-e (65) / 168h (7d) NF-e (55).
 * CONFAZ Ajuste SINIEF 07/2005 Art. 14.
 */
export function prazoCancel(nota: NotaForPrazo, nowMs: number = Date.now()): CancelWindow | null {
  if (!nota?.emittedAtIso) return null;
  if (![55, 65].includes(nota.modelo)) return null;
  if (nota.status !== 'autorizada') return null;

  const emitTs = new Date(nota.emittedAtIso).getTime();
  const prazoHoras = nota.modelo === 65 ? 24 : 168;
  const deadline = emitTs + prazoHoras * 36e5;
  const msLeft = deadline - nowMs;
  if (msLeft <= 0) return null;

  const h = Math.floor(msLeft / 36e5);
  const m = Math.floor((msLeft % 36e5) / 6e4);
  const urgency: Urgency = h < 6 ? 'crit' : h < 12 ? 'warn' : 'ok';
  return { h, m, urgency };
}

/**
 * Janela de Carta de Correção Eletrônica (CC-e) — 30d da emissão.
 * RICMS SP Art. 19 §1 — vale pra NF-e modelo 55.
 */
export function prazoCCe(nota: NotaForPrazo, nowMs: number = Date.now()): CCeWindow | null {
  if (!nota?.emittedAtIso || nota.status !== 'autorizada') return null;

  const emitTs = new Date(nota.emittedAtIso).getTime();
  const deadline = emitTs + 30 * 24 * 36e5;
  const dLeft = Math.floor((deadline - nowMs) / (24 * 36e5));
  if (dLeft <= 0) return null;

  const urgency: Urgency = dLeft < 3 ? 'crit' : dLeft < 7 ? 'warn' : 'ok';
  return { d: dLeft, urgency };
}

/**
 * SEFAZ status tone — derivado do código cstat retornado pelo webservice.
 * Espelha fiscal-data.jsx SEFAZ_CODES; Controller já passa via prop sefazCodes.
 */
export type SefazTone = 'ok' | 'warn' | 'bad';

export interface SefazCodeMeta {
  tone: SefazTone;
  label: string;
  hint: string;
}

export type SefazCodesMap = Record<number, SefazCodeMeta>;
