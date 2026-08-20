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
 *   vendedor   → Código · Produto · Disponibilidade · Preço de venda · Ações
 *   autorizado → + Tipo (só se a aba tem mais de um tipo) · Custo · Margem
 *
 * Fora da grade de propósito: Referência (vive na busca e no detalhe), Categoria e Unidade
 * (segunda linha do nome), Última compra.
 */

import type { MouseEvent, ReactNode } from 'react';
import { MoreHorizontal, Star } from 'lucide-react';
import Disponibilidade from './Disponibilidade';
import Mono from './Mono';
// Cópia local, não import da golden master: o charter desta tela proíbe consumir componente
// compartilhado com a /contacts ([M] 2026-08-18). A razão está escrita no arquivo.
import AvatarProduto from './AvatarProduto';
import { Inline } from '@/Components/layout';
import {
  brl,
  estadoEstoque,
  margemFrac,
  ordemDisponibilidade,
  pct,
  sobOPiso,
  TIPO_LABEL,
  type Permissoes,
  type ProdutoRow,
} from './catalogo';

export type ColunaKey = 'cod' | 'prod' | 'tipo' | 'est' | 'custo' | 'preco' | 'margem' | 'act';

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
    // Código antes de Produto (exceção de domínio 2).
    { key: 'cod', label: 'Código', width: '76px', sortable: true },
    { key: 'prod', label: 'Produto', sortable: true },
    // Tipo some quando a aba tem um tipo só — a coluna não repetiria nada além do rótulo
    // da própria aba (exceção 3).
    ...(mostraTipo ? [{ key: 'tipo', label: 'Tipo', width: '86px', sortable: true } as Coluna] : []),
    { key: 'est', label: 'Disponibilidade', width: '168px', sortable: true },
    ...(verCusto ? [{ key: 'custo', label: 'Custo', width: '104px', align: 'right', sortable: true } as Coluna] : []),
    ...(perm.preco ? [{ key: 'preco', label: 'Preço de venda', width: '128px', align: 'right', sortable: true } as Coluna] : []),
    ...(verCusto && perm.preco ? [{ key: 'margem', label: 'Margem', width: '84px', align: 'right', sortable: true } as Coluna] : []),
    // 72px = dois alvos de 28 + gap 4 + 6 de padding de cada lado (handoff V2 §3.1).
    { key: 'act', label: '', width: '72px', align: 'right' },
  ];
}

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
  { perm, mostraTipo, piso, onAcao }: { perm: Permissoes; mostraTipo: boolean; piso: number; onAcao: (e: MouseEvent) => void }
): Partial<Record<ColunaKey, ReactNode>> {
  const cells: Partial<Record<ColunaKey, ReactNode>> = {
    cod: <Mono className="text-[12px] text-muted-foreground">{r.codigo}</Mono>,
    prod: (
      // Avatar 32 + gap 10 + nome (handoff V2 §3.2). O avatar não é enfeite: na lista densa
      // ele é o que o balcão acha primeiro, antes de ler o texto — mesma âncora visual que a
      // consulta de clientes usa. `seed` é o código, não o nome: renomear o produto não pode
      // trocar a cor com que a pessoa já aprendeu a reconhecê-lo.
      <Inline gap={2} className="min-w-0">
        <AvatarProduto nome={r.name} seed={String(r.codigo)} tamanho={32} />
        <div className="min-w-0 max-w-[280px]">
          {/* Nome em MAIÚSCULAS (exceção 5) + segunda linha com unidade e categoria — que por
              isso não ocupam coluna própria (D-09). */}
          <div className="font-semibold text-[13px] uppercase truncate leading-tight">{r.name}</div>
          <div className="font-mono text-[11px] text-muted-foreground truncate leading-tight mt-0.5">
            {r.unit}
            {r.cat_label ? ` · ${r.cat_label}` : ''}
          </div>
        </div>
      </Inline>
    ),
    est: <Disponibilidade estado={estadoEstoque(r)} />,
    act: (
      <Inline gap={1} justify="end">
        {/* `stopPropagation` pra ação de linha não abrir o drawer junto. */}
        <button
          type="button"
          onClick={onAcao}
          aria-label={`Favoritar ${r.name}`}
          className="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
        >
          <Star size={13} />
        </button>
        <button
          type="button"
          onClick={onAcao}
          aria-label={`Mais ações de ${r.name}`}
          className="inline-flex h-7 w-7 items-center justify-center rounded text-muted-foreground hover:text-foreground hover:bg-muted transition-colors"
        >
          <MoreHorizontal size={13} />
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
        <Mono className={'text-[12.5px] ' + (abaixo ? 'font-semibold text-destructive-fg' : 'text-muted-foreground')}>
          {pct(m)}
        </Mono>
      );
    }
  }

  return cells;
}
