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
