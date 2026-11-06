// Cálculos da grade da semana (posição, dias, tom do status). Sem React, para a tela e o teste.
export type StatusAtendimento = 'agendado' | 'confirmado' | 'chegou' | 'em_atendimento' | 'realizado' | 'faltou' | 'cancelado';

export interface Atendimento {
  id: number;
  patient_id: number;
  paciente: string;
  tipo: string | null;
  teleconsulta: boolean;
  inicio: string;
  fim: string;
  status: StatusAtendimento;
  encaixe: boolean;
  /** Paciente com o agendamento pelo app travado por faltas (A21). */
  app_bloqueado?: boolean;
}
export interface Bloqueio { id: number; inicio: string; fim: string; motivo: string | null }

export const HORA_INICIAL = 8;
export const HORAS = 11; // 8h às 18h, como no protótipo
export const PX_HORA = 52;

/** Tom do bloco: confirmado (pos), aguardando (warn), teleconsulta (info), falta (neg). */
export function tomDo(a: Pick<Atendimento, 'status' | 'teleconsulta'>): 'ok' | 'aguardando' | 'tele' | 'falta' | 'feito' {
  if (a.status === 'faltou') return 'falta';
  if (a.status === 'realizado') return 'feito';
  if (a.teleconsulta) return 'tele';
  return a.status === 'agendado' ? 'aguardando' : 'ok';
}

export const TOM: Record<ReturnType<typeof tomDo>, string> = {
  ok: 'bg-success-soft text-success-fg border-l-success',
  aguardando: 'bg-warning-soft text-warning-fg border-l-warning',
  tele: 'bg-info-soft text-info-fg border-l-info',
  falta: 'bg-destructive-soft text-destructive-fg border-l-destructive',
  feito: 'bg-muted text-muted-foreground border-l-border',
};

/**
 * Status que a agenda oferece a partir do status atual. Espelha AgendaService::TRANSICOES; a regra
 * vale no servidor. "Realizado" não entra: exige a evolução assinada, que se faz na ficha.
 */
const PROXIMOS: Partial<Record<StatusAtendimento, StatusAtendimento[]>> = {
  agendado: ['confirmado', 'chegou', 'em_atendimento', 'faltou'],
  confirmado: ['chegou', 'em_atendimento', 'faltou'],
  chegou: ['em_atendimento', 'faltou'],
};
export const ROTULO_STATUS: Record<StatusAtendimento, string> = {
  agendado: 'Aguardando', confirmado: 'Confirmado', chegou: 'Chegou', em_atendimento: 'Em atendimento',
  realizado: 'Realizado', faltou: 'Faltou', cancelado: 'Desmarcado',
};
export const proximosStatus = (s: StatusAtendimento): StatusAtendimento[] => PROXIMOS[s] ?? [];
/** Desmarcar vale enquanto o paciente não entrou em atendimento. */
export const podeDesmarcar = (s: StatusAtendimento) => ['agendado', 'confirmado', 'chegou'].includes(s);

const ROTULO_DIA = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

export const dataLocal = (iso: string) => new Date(iso);
export const isoDia = (d: Date) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const horasDecimais = (d: Date) => d.getHours() + d.getMinutes() / 60;

export function diasDaSemana(segunda: string): { rotulo: string; iso: string; dia: string }[] {
  const base = new Date(`${segunda}T12:00:00`);
  return ROTULO_DIA.map((rotulo, i) => {
    const d = new Date(base);
    d.setDate(base.getDate() + i);
    return { rotulo, iso: isoDia(d), dia: String(d.getDate()) };
  });
}

/** Posição vertical (px) de um horário na grade; fora das 8h–19h cola na borda. */
export function topo(iso: string): number {
  const h = Math.min(Math.max(horasDecimais(dataLocal(iso)), HORA_INICIAL), HORA_INICIAL + HORAS);
  return (h - HORA_INICIAL) * PX_HORA;
}

