/**
 * Colunas por CONFIGURAÇÃO — arquivo próprio porque é regra de AUTORIZAÇÃO, não layout
 * (handoff §5).
 *
 * A regra dura: coluna é MONTADA ou NÃO MONTADA. Nunca escondida por CSS.
 * E a célula segue a coluna: se `custo` não é coluna, `cells.custo` não é construída — o dado
 * não deve nem chegar ao cliente. (No servidor a chave já nem é emitida; aqui a tela não
 * reintroduz o valor por descuido.)
 *
 * Conjuntos resultantes:
 *   vendedor   → Código · Produto · Disponível · Preço de venda · Ações
 *   autorizado → + Tipo (só se a aba tem mais de um tipo) · Custo · Margem
 *
 * As larguras são as do handoff de 21/08 §3.1 e existem pra que o `min-width` da tabela seja
 * CALCULADO a partir das colunas visíveis, não chutado: esconder Custo tira 108px de verdade
 * da soma, e a rolagem horizontal some junto. Coluna sem largura declarada (Produto) é a que
 * absorve a sobra.
 *
 * Fora da grade de propósito: Referência (vive na busca e no detalhe), Categoria e Unidade
 * (segunda linha do nome), Última compra.
 */

import type { MouseEvent, ReactNode } from 'react';
import { MoreHorizontal } from 'lucide-react';
import Disponibilidade from './Disponibilidade';
import Mono from './Mono';
import MiniaturaProduto from './MiniaturaProduto';
import { Inline } from '@/Components/layout';
import Observacao from './Observacao';
import {
  brl,
  estadoEstoque,
  gradeComFuro,
  marcadorGrade,
  margemFrac,
  ordemDisponibilidade,
  pct,
  sobOPiso,
  TIPO_LABEL,
  type Permissoes,
  type ProdutoRow,
} from './catalogo';

export type ColunaKey = 'sel' | 'cod' | 'prod' | 'tipo' | 'est' | 'custo' | 'preco' | 'margem' | 'act';

export type Coluna = {
  key: ColunaKey;
  label: string;
  width?: string;
  align?: 'left' | 'right';
  sortable?: boolean;
};

export function colunasDe({ perm, mostraTipo }: { perm: Permissoes; mostraTipo: boolean }): Coluna[] {
  const verCusto = perm.custo;
  return [
    // Seleção primeiro, 44px (handoff 21/08 §3.1). Não é ordenável nem tem rótulo visível — a
    // caixa do cabeçalho já é o controle, e um "SELEÇÃO" em caixa alta ali só gastaria largura.
    { key: 'sel', label: '', width: '44px' },
    // Código antes de Produto (exceção de domínio 2).
    { key: 'cod', label: 'Código', width: '88px', sortable: true },
    { key: 'prod', label: 'Produto', sortable: true },
    // Tipo some quando a aba tem um tipo só — a coluna não repetiria nada além do rótulo
    // da própria aba (exceção 3).
    ...(mostraTipo ? [{ key: 'tipo', label: 'Tipo', width: '96px', sortable: true } as Coluna] : []),
    // "Disponível", não "Disponibilidade": o rótulo curto é o que o balcão fala, e 210px
    // acomodam o selo com o saldo dentro mais a segunda linha de marcadores (§4.3).
    { key: 'est', label: 'Disponível', width: '210px', sortable: true },
    ...(verCusto ? [{ key: 'custo', label: 'Custo', width: '108px', align: 'right', sortable: true } as Coluna] : []),
    ...(perm.preco ? [{ key: 'preco', label: 'Preço de venda', width: '136px', align: 'right', sortable: true } as Coluna] : []),
    ...(verCusto && perm.preco ? [{ key: 'margem', label: 'Margem', width: '92px', align: 'right', sortable: true } as Coluna] : []),
    // 48px = UM gatilho de 30 + 9 de padding de cada lado (handoff 21/08 §3.1). Era 72 com
    // dois alvos; o de favoritar saiu — não fazia nada e roubava a metade do alvo de quem
    // mirava o menu.
    { key: 'act', label: '', width: '48px', align: 'right' },
  ];
}

/** Soma das larguras declaradas — o `min-width` da tabela sai daqui, não de um número fixo. */
export const larguraMinima = (colunas: ReadonlyArray<Coluna>): number =>
  colunas.reduce((soma, c) => soma + (c.width ? parseInt(c.width, 10) : LARGURA_PRODUTO), 0);

/** Piso da coluna Produto (a única sem largura): miniatura 30 + gap 10 + nome legível. */
const LARGURA_PRODUTO = 340;

/**
 * Valor de ordenação por coluna. Disponibilidade ordena por RANK SEMÂNTICO (sem estoque <
 * baixo < em estoque), não pelo texto do badge — ordenar alfabeticamente colocaria
 * "Em estoque" antes de "Sem estoque" e esconderia o que precisa de ação.
 */
export function valorOrdenacao(r: ProdutoRow, key: ColunaKey): string | number {
  switch (key) {
    case 'cod': return r.codigo;
    case 'prod': return r.name.toLowerCase();
    case 'tipo': return r.tipo;
    case 'est': return ordemDisponibilidade(r);
    case 'custo': return r.cost ?? -1;
    case 'preco': return r.price ?? -1;
    case 'margem': return margemFrac(r) ?? -1;
    default: return 0;
  }
}

export function celulasDe(
  r: ProdutoRow,
  { perm, mostraTipo, piso, densa, onAcao, onCopiar }: {
    perm: Permissoes;
    mostraTipo: boolean;
    piso: number;
    /** Densidade compacta: some só o REDUNDANTE (§3.2), nunca um marcador semântico. */
    densa: boolean;
    onAcao: (e: MouseEvent) => void;
    onCopiar: (texto: string, rotulo: string) => void;
  }
): Partial<Record<ColunaKey, ReactNode>> {
  const cells: Partial<Record<ColunaKey, ReactNode>> = {
    // O código é o que se dita no telefone e se cola no orçamento — por isso ele COPIA em vez
    // de abrir o painel (handoff 21/08 §3.1). `cursor: copy` avisa que este clique é outro.
    cod: (
      <button
        type="button"
        title="Copiar código"
        onClick={(e) => {
          e.stopPropagation();
          onCopiar(String(r.codigo), `Código ${r.codigo}`);
        }}
        className="cursor-copy border-0 bg-transparent p-0 text-left font-mono text-[12px] text-foreground hover:underline"
      >
        {r.codigo}
      </button>
    ),
    prod: (
      // Miniatura 30 + gap 10 + nome (handoff 21/08 §3.2). DOIS níveis, não três: nome e
      // depois a linha de contexto — `unidade · categoria` mais os marcadores semânticos.
      <Inline gap={2} className="min-w-0">
        <MiniaturaProduto nome={r.name} tamanho={30} />
        <div className="min-w-0 max-w-[280px]">
          {/* Caixa NORMAL (§3.2): o cadastro do tenant já vem em caixa alta em muitos itens, e
              forçar `uppercase` em cima disso apagava a única pista de onde uma sigla começa. */}
          <div className="truncate text-[13px] font-semibold leading-tight">{r.name}</div>
          <Inline gap={2} className="mt-0.5 min-w-0 gap-1.5">
            {!densa && (
              <span className="truncate font-mono text-[11px] leading-tight text-muted-foreground">
                {r.unit}
                {r.cat_label ? ` · ${r.cat_label}` : ''}
              </span>
            )}
            {/* Marcadores semânticos ficam MESMO na densidade compacta: a observação e o
                resumo de variação mudam o que o balcão promete ao cliente. */}
            {r.obs && <Observacao produto={r} />}
            {/* Marcador DERIVADO da grade (V3 §3.2), não o nome do atributo do cadastro: o
                que o balcão precisa saber é quanto da grade vende, não como o tenant batizou
                o eixo. Vermelho quando há furo — parte das combinações não tem saldo. */}
            {r.grade && (
              <span
                className={
                  'truncate text-[11px] leading-tight ' +
                  (gradeComFuro(r.grade) ? 'font-medium text-destructive' : 'text-muted-foreground/80')
                }
                title={
                  gradeComFuro(r.grade)
                    ? `${r.grade.total - r.grade.com} de ${r.grade.total} combinações sem saldo`
                    : 'Todas as combinações têm saldo'
                }
              >
                {marcadorGrade(r.grade)}
              </span>
            )}
          </Inline>
        </div>
      </Inline>
    ),
    est: <Disponibilidade estado={estadoEstoque(r)} locais={r.locais} unidade={r.unit} nome={r.name} densa={densa} />,
    act: (
      // UM alvo. O botão de favoritar saiu no pacote de 21/08 — não persistia nada e dividia
      // ao meio a área de clique de quem mirava o menu.
      <Inline gap={1} justify="end">
        {/* `stopPropagation` pra ação de linha não abrir o drawer junto. */}
        <button
          type="button"
          onClick={onAcao}
          aria-label={`Mais ações de ${r.name}`}
          className="inline-flex h-[30px] w-[30px] items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
        >
          <MoreHorizontal size={16} />
        </button>
      </Inline>
    ),
  };

  if (mostraTipo) {
    cells.tipo = (
      <span className="inline-flex items-center rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px] tracking-wide text-muted-foreground">
        {TIPO_LABEL[r.tipo]}
      </span>
    );
  }

  if (perm.preco && r.price !== undefined) {
    cells.preco = <Mono className="text-[13px] font-semibold">{brl(r.price)}</Mono>;
  }

  if (perm.custo && r.cost !== undefined) {
    cells.custo = <Mono className="text-[12.5px] text-muted-foreground">{brl(r.cost)}</Mono>;
  }

  if (perm.custo && perm.preco) {
    const m = margemFrac(r);
    if (m !== undefined) {
      const abaixo = sobOPiso(r, piso);
      cells.margem = (
        <Mono className={'text-[12.5px] ' + (abaixo ? 'font-semibold text-destructive' : 'text-muted-foreground')}>
          {pct(m)}
        </Mono>
      );
    }
  }

  return cells;
}
