// Formas do payload que o DocumentacaoController publica pras Pages de /documentacao.
// Espelham o PHP (navegacao() · comSumario()) — não inventam campo. Se o controller mudar a
// forma, o teste de contrato do lado PHP quebra primeiro.

export interface ItemNav {
  id: string;
  grupo: string;
  titulo: string;
  rotulo: string;
  descricao: string | null;
  /** Posição VISÍVEL na lente ativa (1..N sem buraco) — não é `nav_order` (AR-DOC-012). */
  ordinal: number;
}

export interface GrupoNav {
  id: string;
  titulo: string;
  itens: ItemNav[];
}

export interface Navegacao {
  grupos: GrupoNav[];
  linear: ItemNav[];
  lente: string | null;
  lentes: Record<string, string>;
}

export interface ItemSumario {
  id: string;
  nivel: number;
  codigo: string | null;
  rotulo: string;
}
