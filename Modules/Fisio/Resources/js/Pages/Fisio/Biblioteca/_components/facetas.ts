// Tipos e funções puras das facetas da biblioteca (posição, equipamento, favoritos).
// Opções vêm da lista fechada do servidor (`config('fisio.biblioteca.*')`).

export interface OpcaoFaceta {
  value: string;
  label: string;
}

export interface Facetas {
  posicoes: OpcaoFaceta[];
  equipamentos: OpcaoFaceta[];
}

export interface FiltrosFacetasValor {
  pose: string;
  equipment: string;
  fav: boolean;
}

export function rotulo(opcoes: OpcaoFaceta[], valor: string | null | undefined): string {
  return opcoes.find((o) => o.value === valor)?.label ?? valor ?? '';
}

/** "Nenhum" exclui os outros equipamentos; marcar outro tira o "Nenhum". */
export function alternarEquipamento(atual: string[], valor: string, marcado: boolean): string[] {
  if (!marcado) return atual.filter((v) => v !== valor);
  if (valor === 'nenhum') return ['nenhum'];
  return [...atual.filter((v) => v !== 'nenhum' && v !== valor), valor];
}
