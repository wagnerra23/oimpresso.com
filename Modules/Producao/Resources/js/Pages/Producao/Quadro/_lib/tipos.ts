export interface Etapa {
  id: number; nome: string | null; status: 'pendente' | 'iniciada';
  posto_id: number | null; posto: string | null; setor: string | null;
  user_id: number | null; responsavel: string | null; inicio_previsto: string; fim_previsto: string;
}
export interface Cartao {
  chave: string; os_tipo: string; os_id: number; numero: string; titulo: string | null; cliente: string | null;
  etapa: Etapa; progresso: number; prazo: string | null; atraso: 'atrasada' | 'vence hoje' | null; midias: number[];
  /** UC-PCV-04 · OS por item com prova sem decisão (aguardando) ou recusada (ajuste); null = não trava. */
  arte: 'aguardando' | 'ajuste' | null;
  /** UC-PCV-07 · coluna onde o cartão aparece (setor da etapa atual, ou o que o PCP escolheu à mão). */
  coluna: string;
  /** O PCP pôs a OS nesta coluna à mão; some quando o operador iniciar ou concluir uma etapa. */
  movida: boolean;
}
export interface Posto { id: number; nome: string; setor: string | null }
export interface Pessoa { id: number; nome: string }
export interface Pessoas { pessoas: Pessoa[]; por_posto: Record<number, number[]> }
export interface Bobina { largura_util_m: string; margem_m: string; permite_girar: boolean }
export interface Midia { id: number; nome: string; bobina: Bobina | null }

// UC-PCV-06: setor e filtro são da Produção inteira (Quadro e Linha), não só do Quadro.
export { SEM_SETOR, setorDe } from '../../_lib/filtro';
/** Só OS da comunicação visual com peça medida entram no simulador de bobina (US-PCP-024). */
export const vaiNaBobina = (c: Cartao) => c.os_tipo === 'comvis_os' && c.midias.length > 0;
