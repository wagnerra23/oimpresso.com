// Tipos e utilitários compartilhados pelas telas do portal do paciente (US-FISIO-007b).
// Tudo o que decide etapa, modo e números vem do servidor (PortalController); aqui só formata.

export interface ItemHoje {
  id: number;
  nome: string | null;
  passos: string[];
  cuidados: string[];
  sets: number | null;
  reps: number | null;
  hold_s: number | null;
  times_per_day: number;
  notes: string | null;
}

export interface Jogo {
  xp: number;
  nivel: number;
  xp_no_nivel: number;
  xp_para_proximo: number | null;
  sequencia: number;
  em_pausa: boolean;
  dias_cumpridos: number;
}

/** Missões do dia, desafio da semana e medalhas (T-08c) — JogoService::constancia(). */
export interface Constancia {
  missoes: { chave: string; texto: string; cumprida: boolean }[];
  desafio: { oferecido: boolean; decidido: boolean; aceito: boolean; meta: number | null; opcoes: number[]; cumpridos: number; pago: boolean };
  medalhas: { codigo: string; nome: string }[];
}

export interface Relato {
  feitos: number[];
  dor: number | null;
  esforco: Esforco | null;
  observacao: string | null;
  crise: boolean;
}

export type Esforco = 'facil' | 'adequado' | 'dificil';

export interface Termo { purpose: string; version: string | null }

export interface RespostaCheckin {
  modo: 'normal' | 'pausa';
  checkin_id: number;
  instrucao?: string | null;
  xp_ganho?: number;
  jogo?: Jogo;
}

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export async function enviar(metodo: 'POST' | 'DELETE', url: string, corpo?: unknown): Promise<{ status: number; json: Record<string, unknown> }> {
  const res = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: corpo === undefined ? undefined : JSON.stringify(corpo),
  });
  const json = (await res.json().catch(() => ({}))) as Record<string, unknown>;
  return { status: res.status, json };
}

/** "3×12 · segurar 5s" — a dose como a fisioterapeuta prescreveu. */
export function dose(i: Pick<ItemHoje, 'sets' | 'reps' | 'hold_s'>): string {
  const partes: string[] = [];
  if (i.sets && i.reps) partes.push(`${i.sets}×${i.reps}`);
  else if (i.sets) partes.push(`${i.sets} séries`);
  else if (i.reps) partes.push(`${i.reps} repetições`);
  if (i.hold_s) partes.push(`segurar ${i.hold_s}s`);
  return partes.join(' · ');
}

/** Âncoras verbais da escala de dor (etapa 02 §3.5). */
export function ancoraDor(v: number): string {
  if (v === 0) return 'Sem dor';
  if (v <= 3) return 'Leve';
  if (v <= 6) return 'Moderada';
  if (v <= 9) return 'Forte';
  return 'Pior possível';
}

export const ESFORCOS: { valor: Esforco; rotulo: string }[] = [
  { valor: 'facil', rotulo: 'Fácil' },
  { valor: 'adequado', rotulo: 'Na medida' },
  { valor: 'dificil', rotulo: 'Difícil' },
];

/** Texto do paciente para cada sinal da lista fechada `config('fisio.red_flags')`. */
export const SINAIS: Record<string, string> = {
  inchaco_vermelhidao_calor: 'Inchaço, vermelhidão ou calor novos',
  febre: 'Febre',
  queda: 'Tive uma queda',
  dor_noturna_intensa: 'Dor que acorda à noite ou não melhora em repouso',
  dormencia_progressiva: 'Formigamento ou dormência novos',
  perda_forca_pernas: 'Perda de força nas pernas',
  perda_controle_esfincter: 'Alteração para urinar ou evacuar',
  dormencia_regiao_intima: 'Dormência na região íntima',
  secrecao_ou_abertura_corte: 'Secreção ou abertura no corte',
  panturrilha_inchada_dolorida: 'Panturrilha inchada e dolorida',
};

export function hojePorExtenso(): string {
  const s = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/** Percentual do nível atual (0–100). Nível máximo = barra cheia. */
export function progressoNivel(j: Jogo): number {
  if (j.xp_para_proximo === null) return 100;
  const total = j.xp_no_nivel + j.xp_para_proximo;
  return total <= 0 ? 0 : Math.round((j.xp_no_nivel / total) * 100);
}

/** Liga o service worker do app instalável (public/sw-fisio-portal.js · etapa 02 §3.6). Falha calada. */
export function registrarAppDoPortal(): void {
  if (typeof window === 'undefined' || !('serviceWorker' in navigator)) return;
  navigator.serviceWorker.register('/sw-fisio-portal.js', { scope: '/fisio/p/' }).catch(() => undefined);
}

// ── Fila offline do check-in (etapa 02 §3.6 · T-13 parte 2 · UC-FPP-30..32) ─────────────────
// Sem rede, o check-in fica guardado no aparelho e vai quando a internet volta. Regras:
//  - só em aparelho LEMBRADO (`guardar_offline`, decidido pelo servidor): com o PIN só na
//    sessão nada clínico fica no aparelho, como na tela do dia guardada (UC-FPP-27);
//  - um item por link e por dia; envio novo substitui o guardado;
//  - cada envio leva uma `chave`: se a resposta se perdeu e a fila reenvia, o servidor
//    reconhece a chave e não grava duas vezes;
//  - o servidor recusa o que não é do dia (`dia_passou`) ou de aparelho não lembrado: a fila
//    apaga o item, e nada fica guardado à toa.

export interface ItemDaFila { chave: string; dia: string; corpo: Record<string, unknown> }

const chaveDaFila = (token: string) => `fisio-fila-checkin:${token}`;

/** Dia civil do paciente (America/Sao_Paulo, como o DiaLocal do servidor). */
export function diaDeHoje(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Sao_Paulo', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
}

export function novaChave(): string {
  if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) return crypto.randomUUID();
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
}

export function lerFila(token: string): ItemDaFila | null {
  try {
    const bruto = localStorage.getItem(chaveDaFila(token));
    return bruto ? (JSON.parse(bruto) as ItemDaFila) : null;
  } catch {
    return null;
  }
}

export function guardarNaFila(token: string, item: ItemDaFila): boolean {
  try {
    localStorage.setItem(chaveDaFila(token), JSON.stringify(item));
    return true;
  } catch {
    return false;
  }
}

export function limparFila(token: string): void {
  try {
    localStorage.removeItem(chaveDaFila(token));
  } catch {
    /* sem armazenamento: nada guardado */
  }
}

/**
 * Envia o check-in guardado. `enviado` = o servidor aceitou (gravou agora ou já tinha a chave);
 * `descartado` = recusado de vez (dia passou, aparelho não lembrado, link inválido) e apagado;
 * `pendente` = ainda sem rede ou erro do servidor: continua na fila.
 */
export async function enviarFila(token: string): Promise<'vazia' | 'enviado' | 'descartado' | 'pendente'> {
  const item = lerFila(token);
  if (!item) return 'vazia';
  if (item.dia !== diaDeHoje()) {
    limparFila(token);
    return 'descartado';
  }
  try {
    const r = await enviar('POST', `/fisio/p/${token}/checkin`, { ...item.corpo, chave: item.chave, dia: item.dia, offline: true });
    if (r.status === 200 || r.status === 201) {
      limparFila(token);
      return 'enviado';
    }
    if (r.status === 403 || r.status === 404 || r.status === 422) {
      limparFila(token);
      return 'descartado';
    }
    return 'pendente';
  } catch {
    return 'pendente';
  }
}
