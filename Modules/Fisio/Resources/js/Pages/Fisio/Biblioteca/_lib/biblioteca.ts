// Tipos e rótulos da Biblioteca do Fisio (US-FISIO-004). As facetas (código → rótulo) vêm do
// servidor (`config('fisio.biblioteca.*')`); aqui fica só o rótulo dos marcadores de condição,
// cuja lista de códigos também vem do servidor (`config('fisio.condition_flags')`).

export interface Exercicio {
  id: number;
  code: string | null;
  name: string;
  region: string;
  type: string;
  pose: string | null;
  equipment: string[];
  steps: string[];
  default_sets: number | null;
  default_reps: number | null;
  default_hold_s: number | null;
  times_per_day: number;
  safety_notes: string[];
  contraindication_flags: string[];
  needs_support: boolean;
  is_custom: boolean;
  global: boolean;
  editavel: boolean;
  review_status: 'rascunho' | 'em_revisao' | 'publicado' | 'retirado' | null;
}

export interface Facetas {
  regioes: Record<string, string>;
  tipos: Record<string, string>;
  posicoes: Record<string, string>;
  equipamentos: Record<string, string>;
  contraindicacoes: string[];
}

export interface Filtros {
  region: string | null;
  equipamento: string | null;
}

const CONTRAINDICACOES: Record<string, string> = {
  protese_quadril_posterior: 'Prótese de quadril (via posterior)',
  protese_quadril_anterior: 'Prótese de quadril (via anterior)',
  pos_op_manguito: 'Pós-operatório de manguito',
  pos_lca: 'Pós-operatório de LCA',
  gestante: 'Gestante',
  osteoporose: 'Osteoporose',
  risco_queda: 'Risco de queda',
  hipertensao_nao_controlada: 'Hipertensão não controlada',
};

export const rotuloContraindicacao = (codigo: string) => CONTRAINDICACOES[codigo] ?? codigo;

/** "3 × 10 · segurar 5 s · 2× ao dia" — só o que o exercício tem. */
export function dose(e: Exercicio): string {
  const partes: string[] = [];
  if (e.default_sets && e.default_reps) partes.push(`${e.default_sets} × ${e.default_reps}`);
  else if (e.default_sets) partes.push(`${e.default_sets} série(s)`);
  if (e.default_hold_s) partes.push(`segurar ${e.default_hold_s} s`);
  if (e.times_per_day > 1) partes.push(`${e.times_per_day}× ao dia`);
  return partes.join(' · ');
}
