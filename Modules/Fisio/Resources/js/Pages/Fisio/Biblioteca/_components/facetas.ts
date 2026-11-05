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

// Região e tipo chegam como chave do seeder (`controle_motor`, `toracica`). A tela mostra o nome.
const NOMES: Record<string, string> = {
  cervical: 'Cervical', toracica: 'Torácica', lombar: 'Lombar', ombro: 'Ombro', quadril: 'Quadril',
  joelho: 'Joelho', tornozelo: 'Tornozelo', alongamento: 'Alongamento', controle_motor: 'Controle motor',
  equilibrio: 'Equilíbrio', fortalecimento: 'Fortalecimento', mobilidade: 'Mobilidade', respiracao: 'Respiração',
};

export function legivel(valor: string): string {
  return NOMES[valor] ?? valor.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}

/** "Nenhum" exclui os outros equipamentos; marcar outro tira o "Nenhum". */
export function alternarEquipamento(atual: string[], valor: string, marcado: boolean): string[] {
  if (!marcado) return atual.filter((v) => v !== valor);
  if (valor === 'nenhum') return ['nenhum'];
  return [...atual.filter((v) => v !== 'nenhum' && v !== valor), valor];
}
