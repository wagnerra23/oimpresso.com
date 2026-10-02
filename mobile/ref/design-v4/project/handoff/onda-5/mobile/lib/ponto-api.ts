/**
 * Cliente da API do Ponto — Modules/Ponto/Http/routes.php, bloco 2: prefixo /ponto/api,
 * middleware auth:api (Passport) + timezone. Controller: Api\MobileMarcacaoController.
 * Caminhos e respostas CONFERIDOS no main (01/10/2026).
 *
 * O servidor decide tudo: colaborador = usuário do token (403 sem_colaborador se não controla
 * ponto), NSR + hash, GPS > 500 m e relógio > 30 s recusam (422 validacao_falhou), geofence
 * sinaliza (revisar). ADR 0383: sem biometria.
 *
 * ⚠ Único bloqueio que resta: de onde vem o token Passport no app (ver README).
 */
export const PONTO_BASE = process.env.EXPO_PUBLIC_PONTO_API_URL ?? "";

export type TipoMarcacao = "ENTRADA" | "ALMOCO_INICIO" | "ALMOCO_FIM" | "SAIDA";
export type MarcacaoHoje = { id: string; nsr: number; tipo: string; origem: string; hora: string | null; hash_trunc: string; revisar: boolean };
export type MarcacaoCriada = { id: string; nsr: number; tipo: string; momento: string; hash_trunc: string; origem: string; revisar: boolean };
export type IntercorrenciaResumo = { id: string; codigo: string; tipo: string; estado: string; data: string | null; dia_todo: boolean; intervalo_inicio: string | null; intervalo_fim: string | null };
export type Turno = { hora_entrada: string | null; hora_almoco_inicio: string | null; hora_almoco_fim: string | null; hora_saida: string | null };

export class PontoApiError extends Error {
  constructor(public status: number, public codigo: string | undefined, message: string, public errors?: Record<string, string[]>) { super(message); }
}

async function call<T>(token: string, method: "GET" | "POST", path: string, body?: unknown): Promise<T> {
  const r = await fetch(PONTO_BASE + "/ponto/api" + path, {
    method,
    headers: { Accept: "application/json", "Content-Type": "application/json", Authorization: `Bearer ${token}` },
    body: body ? JSON.stringify(body) : undefined,
  });
  const j = await r.json().catch(() => ({}));
  if (!r.ok) throw new PontoApiError(r.status, j.erro, j.mensagem ?? j.message ?? "Não foi possível falar com o ponto.", j.errors);
  return j as T;
}

export const pontoApi = {
  /** GET /ponto/api/marcacoes/hoje → { data, marcacoes } (sem anulações) */
  hoje: (t: string) => call<{ data: string; marcacoes: MarcacaoHoje[] }>(t, "GET", "/marcacoes/hoje"),
  /** POST /ponto/api/marcar → 201 { sucesso, marcacao } · 422 { erro: "validacao_falhou", mensagem } */
  marcar: (t: string, p: { tipo: TipoMarcacao; lat: number; lng: number; accuracy: number; device_uuid: string; timestamp_device: string }) =>
    call<{ sucesso: true; marcacao: MarcacaoCriada }>(t, "POST", "/marcar", p),
  /** GET /ponto/api/saldo → banco de horas em minutos */
  saldo: (t: string) => call<{ usa_banco_horas: boolean; saldo_minutos: number; ultima_movimentacao: string | null }>(t, "GET", "/saldo"),
  /** GET /ponto/api/escala/hoje */
  escalaHoje: (t: string) => call<{ data: string; escala: { id: number; nome: string } | null; turno: Turno | null }>(t, "GET", "/escala/hoje"),
  /** GET /ponto/api/dashboard/kpis — do colaborador, não da empresa */
  kpis: (t: string) => call<{ marcacoes_hoje: number; intercorrencias_pendentes: number; saldo_minutos: number }>(t, "GET", "/dashboard/kpis"),
  /** GET /ponto/api/intercorrencias — as minhas, 50 mais recentes */
  intercorrencias: (t: string) => call<{ intercorrencias: IntercorrenciaResumo[] }>(t, "GET", "/intercorrencias"),
  /** POST /ponto/api/intercorrencias — cria E submete (nasce PENDENTE). Regras = StoreIntercorrenciaRequest. */
  justificar: (t: string, p: { tipo: string; data: string; dia_todo: boolean; intervalo_inicio: string | null; intervalo_fim: string | null; justificativa: string }) =>
    call<{ sucesso: true; intercorrencia: IntercorrenciaResumo }>(t, "POST", "/intercorrencias", p),
};

/** 125 → "+2h05" · -40 → "−0h40" */
export function fmtMinutos(min: number): string {
  const s = min < 0 ? "−" : "+";
  const a = Math.abs(min);
  return `${s}${Math.floor(a / 60)}h${String(a % 60).padStart(2, "0")}`;
}
