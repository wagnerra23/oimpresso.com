// Agenda do paciente no portal (US-FISIO-016): tipos e chamadas às rotas /fisio/p/{token}/agenda/*.
// Contrato: memory/requisitos/Fisio/_telas/agenda-paciente.casos.md (UC-FPA-01..07).

export interface ConsultaPortal {
  id: number;
  inicio: string;
  tipo: string | null;
  type_id: number;
  professional_id: number;
  teleconsulta: boolean;
  profissional: string | null;
  status: 'agendado' | 'confirmado' | 'chegou' | 'em_atendimento' | 'realizado' | 'faltou' | 'cancelado';
  pode_mexer: boolean;
  nota: number | null;
}
export interface TipoPortal { id: number; nome: string; duracao: number; teleconsulta: boolean }
export interface ProfissionalPortal { id: number; nome: string; acompanha: boolean }
export interface AgendaPortal {
  impedimento: 'desligado' | 'bloqueado_faltas' | 'so_pela_clinica' | null;
  prazo_horas: number;
  checkin: boolean;
  proximas: ConsultaPortal[];
  historico: ConsultaPortal[];
  espera: { id: number; preferencia: string } | null;
  tipos: TipoPortal[];
  profissionais: ProfissionalPortal[];
}

export const MOTIVO_SEM_AGENDA: Record<NonNullable<AgendaPortal['impedimento']>, string> = {
  desligado: 'A clínica ainda não abriu o agendamento pelo app. Marque pelo WhatsApp da clínica.',
  bloqueado_faltas: 'O agendamento pelo app foi pausado por faltas sem aviso. Fale com a clínica para liberar.',
  so_pela_clinica: 'Nesta clínica as consultas são marcadas pela recepção. Aqui você acompanha e confirma.',
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export async function pedir(metodo: 'GET' | 'POST' | 'DELETE', url: string, corpo?: unknown): Promise<{ status: number; json: Record<string, unknown> }> {
  const res = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    body: corpo === undefined ? undefined : JSON.stringify(corpo),
  });
  let json: Record<string, unknown> = {};
  try {
    json = (await res.json()) as Record<string, unknown>;
  } catch {
    json = {};
  }
  return { status: res.status, json };
}

/** Primeira mensagem de erro de uma resposta 422 do Laravel. */
export function erroDe(json: Record<string, unknown>): string | null {
  const errors = json.errors as Record<string, string[]> | undefined;
  const primeiro = errors ? Object.values(errors)[0]?.[0] : null;
  if (primeiro === 'fora_do_prazo') return 'Faltam menos horas que o prazo da clínica. Para mudar, fale com a clínica.';
  if (primeiro === 'fora_da_janela') return 'O "Cheguei" abre 1 hora antes da consulta.';
  if (primeiro && primeiro in MOTIVO_SEM_AGENDA) return MOTIVO_SEM_AGENDA[primeiro as keyof typeof MOTIVO_SEM_AGENDA];
  return primeiro ?? null;
}

/** Os próximos `n` dias a partir de amanhã, em ISO local. */
export function proximosDias(n: number, base = new Date()): { iso: string; semana: string; dia: string }[] {
  return Array.from({ length: n }, (_, i) => {
    const d = new Date(base);
    d.setDate(base.getDate() + i + 1);
    return {
      iso: d.toLocaleDateString('en-CA'),
      semana: d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', ''),
      dia: String(d.getDate()),
    };
  });
}

/** "qui, 23/10 · 08:30". */
export function quando(iso: string): string {
  const d = new Date(iso);
  return `${d.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '')}, ${d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} · ${d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}`;
}

/** "Cheguei" aparece a partir de 1 h antes até o fim previsto (A19). */
export function podeChegar(c: Pick<ConsultaPortal, 'inicio' | 'status'>, agora = new Date()): boolean {
  const ini = new Date(c.inicio).getTime();
  return ['agendado', 'confirmado'].includes(c.status) && agora.getTime() >= ini - 60 * 60 * 1000 && agora.getTime() < ini + 60 * 60 * 1000;
}
