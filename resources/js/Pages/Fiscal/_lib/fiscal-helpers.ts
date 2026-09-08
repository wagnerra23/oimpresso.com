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

/**
 * Junta pedaços de uma célula com ` · `, descartando os que não existem.
 *
 * Sem isto, dois fallbacks individualmente CORRETOS somam um terceiro errado: o Controller manda
 * `dest: '—'` quando não há destinatário e o `formatDoc` devolve `'—'` quando não há documento,
 * e a linha renderizava **`— · —`** — um separador entre dois nadas. Visto em produção biz=1,
 * onde as notas rejeitadas não chegaram a ter destinatário gravado.
 *
 * Trata `'—'` como ausência de propósito: é o que os dois produtores usam para dizer "vazio".
 */
export const juntarInfo = (...partes: Array<string | null | undefined>): string => {
  const uteis = partes
    .map((p) => (p ?? '').trim())
    .filter((p) => p !== '' && p !== '—');

  return uteis.length ? uteis.join(' · ') : '—';
};

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

/**
 * Rótulo PT-BR do status de DOMÍNIO da emissão (`nfe_emissoes.status`).
 *
 * É o piso da pílula: existe para toda nota, inclusive as que nunca receberam `cstat` porque a
 * transmissão falhou antes da SEFAZ responder — em produção biz=1 são 3 de 9 (id 235/238/240,
 * `status='inutilizada'`, `cstat` NULL). Sem ele a tela mostrava "Status", que não é rótulo: era
 * o fallback vazando para o usuário.
 *
 * Vocabulário do ENUM da migration `nfe_emissoes.status` (NfeBrasil), incluindo `enviando` e
 * `erro_envio` acrescentados em 2026_05_10_120000.
 */
const STATUS_DOMINIO: Record<string, string> = {
  pendente: 'Pendente na SEFAZ',
  enviando: 'Enviando',
  autorizada: 'Autorizada',
  rejeitada: 'Rejeitada',
  denegada: 'Denegada',
  cancelada: 'Cancelada',
  inutilizada: 'Inutilizada',
  erro_envio: 'Erro no envio',
};

const TOM_DOMINIO: Record<string, SefazTone> = {
  pendente: 'warn',
  enviando: 'warn',
  autorizada: 'ok',
  rejeitada: 'bad',
  denegada: 'bad',
  cancelada: 'ok',
  inutilizada: 'warn',
  erro_envio: 'bad',
};

export interface NotaParaPilula {
  cstat: number;
  status: string;
  motivo?: string | null;
}

/**
 * Resolve a pílula SEFAZ de uma nota. NUNCA devolve rótulo vazio ou genérico.
 *
 * Ordem de resolução, da mais específica para a mais geral:
 *  1. `cstat` traduzido pela tabela oficial da SEFAZ (prop `sefazCodes`, servida pelo backend);
 *  2. `cstat` presente mas fora da tabela — mostra o status de domínio e mantém o número visível,
 *     para o operador poder buscar o código sem que a tela finja saber o que ele significa;
 *  3. sem `cstat` — só o status de domínio.
 *
 * O `hint` (tooltip) prefere sempre o `motivo` gravado na própria nota: é o texto que a SEFAZ
 * respondeu para AQUELA emissão, com o item citado (ex.: "Informado NCM inexistente [nItem:1]").
 */
export function sefazPill(nota: NotaParaPilula, mapa: SefazCodesMap): SefazCodeMeta {
  const oficial = nota.cstat > 0 ? mapa[nota.cstat] : undefined;
  const hint = nota.motivo?.trim() || oficial?.hint || '';

  if (oficial) {
    return { tone: oficial.tone, label: oficial.label, hint };
  }

  const label = STATUS_DOMINIO[nota.status] ?? 'Status desconhecido';
  const tone = TOM_DOMINIO[nota.status] ?? 'warn';

  return {
    tone,
    label: nota.cstat > 0 ? `${label} · código ${nota.cstat} não catalogado` : label,
    hint,
  };
}
