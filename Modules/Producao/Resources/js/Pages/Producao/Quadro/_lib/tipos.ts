export interface Etapa {
  id: number; nome: string | null; status: 'pendente' | 'iniciada';
  posto_id: number | null; posto: string | null; setor: string | null;
  user_id: number | null; responsavel: string | null; inicio_previsto: string; fim_previsto: string;
}
export interface Cartao {
  chave: string; os_tipo: string; os_id: number; numero: string; titulo: string | null; cliente: string | null;
  etapa: Etapa; progresso: number; prazo: string | null; atraso: 'atrasada' | 'vence hoje' | null; midias: number[];
}
export interface Posto { id: number; nome: string; setor: string | null }
export interface Pessoa { id: number; nome: string }
export interface Pessoas { pessoas: Pessoa[]; por_posto: Record<number, number[]> }
export interface Bobina { largura_util_m: string; margem_m: string; permite_girar: boolean }
export interface Midia { id: number; nome: string; bobina: Bobina | null }

export const SEM_SETOR = 'Sem setor';
export const setorDe = (e: Etapa) => e.setor?.trim() || SEM_SETOR;
/** Só OS da comunicação visual com peça medida entram no simulador de bobina (US-PCP-024). */
export const vaiNaBobina = (c: Cartao) => c.os_tipo === 'comvis_os' && c.midias.length > 0;
