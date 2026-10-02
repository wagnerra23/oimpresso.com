import { useState, useEffect, useRef, ReactNode } from 'react';
import { router } from '@inertiajs/react';
import {
  ColumnDef,
  flexRender,
  getCoreRowModel,
  useReactTable,
} from '@tanstack/react-table';
import { ArrowUpDown, ChevronLeft, ChevronRight, Search, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

/**
 * DataTable reusável com TanStack Table v8 + server-side pagination
 * (Inertia paginator + withQueryString). Integra com Laravel Scout
 * quando o controller aceita `?q=` (ADR arq/0006).
 *
 * Features built-in:
 * - Busca debounced (300ms) via query param
 * - Sort via query param (?sort=X&dir=asc)
 * - Paginação server-side via Inertia links
 * - Colunas tipadas em TypeScript
 * - Zero jQuery, zero CSS externo — só Tailwind + shadcn primitives
 *
 * Uso:
 *   const columns: ColumnDef<Role>[] = [
 *     { accessorKey: 'name', header: 'Nome' },
 *     { accessorKey: 'users_count', header: 'Usuários', enableSorting: true },
 *     { id: 'actions', cell: ({ row }) => <Button>Editar</Button> },
 *   ];
 *
 *   <DataTable
 *     columns={columns}
 *     data={paginator.data}
 *     pagination={paginator}
 *     endpoint="/roles"
 *     filters={{ status: 'active' }}
 *     searchPlaceholder="Buscar roles..."
 *   />
 */

/**
 * GEOMETRIA DA COLUNA — o vocabulário que o protótipo já falava e a travessia não tinha
 * onde pousar.
 *
 * Os protótipos Cowork declaram `columns[] = { width, align, mono }` e `rows[].state` desde
 * sempre. O `ColumnDef` do TanStack não tem campo pra nada disso, então o `meta` chegava aqui
 * e era **ignorado em silêncio** — não dava erro de tipo, não dava aviso, simplesmente não
 * acontecia. Foi assim que a Arquivos/Index perdeu as 7 larguras, o alinhamento à direita da
 * coluna Tamanho e a trilha de urgência, enquanto o `mono` (que dava pra fazer à mão dentro
 * da célula) chegou. Reportado por [W] em 2026-08-27.
 *
 * Declarado por module augmentation, e não como prop paralela, de propósito: assim o campo
 * aparece no autocomplete de QUALQUER `ColumnDef` do repo. O que não se enxerga não se aplica.
 */
declare module '@tanstack/react-table' {
  // Os dois parametros existem so pra casar a assinatura do tipo original do TanStack —
  // este `meta` nao depende de nenhum dos dois.
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  interface ColumnMeta<TData, TValue> {
    /** Largura fixa em px. Uma largura declarada já põe a tabela em `table-layout: fixed`. */
    width?: number;
    /** Alinhamento da CÉLULA (`<th>` e `<td>`) — nunca de um filho dela. Ver nota abaixo. */
    align?: 'left' | 'center' | 'right';
    /** Fonte monoespaçada + `tabular-nums` na célula inteira. */
    mono?: boolean;
  }
}

/**
 * Estado visual da LINHA — o `rows[].state` do protótipo.
 * `urgent` = trilha vermelha à esquerda · `archived` = esmaecida · `selected` = fundo accent.
 */
export type EstadoDaLinha = 'urgent' | 'archived' | 'selected';

/** Classe de alinhamento aplicada à célula. `undefined` mantém o padrão (esquerda). */
const CLASSE_ALINHAMENTO: Record<'left' | 'center' | 'right', string> = {
  left: 'text-left',
  center: 'text-center',
  right: 'text-right',
};

/** O cabeçalho ordenável é um flex — `text-*` não move flex, `justify-*` move. */
const CLASSE_JUSTIFICA: Record<'left' | 'center' | 'right', string> = {
  left: 'justify-start',
  center: 'justify-center',
  right: 'justify-end',
};

/**
 * A trilha vai no `td:first-child`, não no `<tr>`.
 *
 * Não é preciosismo: `box-shadow` em `<tr>` só pinta quando `border-collapse: separate`, e
 * qualquer folha que colapse a tabela apaga a trilha sem erro nenhum. `<td>` pinta sempre.
 * O `opacity` do arquivado, ao contrário, herda bem e fica na própria linha.
 */
const CLASSE_ESTADO: Record<EstadoDaLinha, string> = {
  urgent: '[&>td:first-child]:shadow-[inset_3px_0_0_var(--color-destructive)]',
  archived: 'opacity-60',
  selected: 'bg-accent/50',
};

/**
 * DENSIDADE — a "tabela densa" do protótipo (`table.pt-tbl` do Ponto), como prop ADITIVA.
 *
 * Playbook `ds-atomos` thread 04, decisão D-GRADE ([W] 2026-09-24): a tabela densa continua
 * paginando no SERVIDOR — muda só a anatomia, nenhum controller. Por isso mora aqui, no
 * primitivo, e não em cada tela.
 *
 * `default` é a string de HOJE, literal, em cada elemento: quem não passa `density` não muda
 * um caractere de classe. Os números do `dense` vêm da §Alvo do índice do playbook (medidos no
 * protótipo servido, dark, 1280px) — `th` 11px uppercase .07em 600 · pad 8×10 · `td` 12.5px ·
 * pad 7×10 · divisória `--border` a 60% · hover accent 5%. "Accent" ali é o roxo da marca, que
 * aqui é `primary`: o `accent` do shadcn é neutro.
 */
export type DensidadeDaTabela = 'default' | 'dense' | 'grid';

const THEAD_PADRAO = 'border-b border-border bg-muted/30 text-xs text-muted-foreground';

/**
 * `grid` — a anatomia do componente `DataGrid` do DS
 * (`prototipo-ui/design-system/components/DataGrid/DataGrid.jsx`), que é a grade das telas do
 * protótipo da Fabricação. NÃO é o `dense`: o `dense` veio da `table.pt-tbl` do Ponto (th 11px
 * .07em, pad 8×10, td 7×10, divisória); o DataGrid é outra anatomia (th 10px .05em sobre
 * `--bg-2`, pad 7×10, td 5×10, borda `--border-2` por célula, linhas listradas, moldura com o
 * rodapé dentro). As duas existem no protótipo, então as duas existem aqui — fundir mudaria uma
 * tela pra parecer a outra.
 *
 * Duas trocas deliberadas em relação ao DS, ambas já canon no app:
 * - cabeçalho em `--text-dim`, não `--text-mute` (contraste AA em texto de 10px — ADR 0410);
 * - linha selecionada e caixinha no roxo da marca (`--color-primary`), não `--accent`/`--accent-soft`,
 *   que o `AppShellV2` reescreve a partir do matiz salvo no navegador de cada pessoa
 *   (`Manufacturing/Recipes.charter.md` §Acento visual).
 *
 * O fundo da LINHA (listra · selecionada) é decidido por linha no JS e posto na `<td>`, não no
 * `<tr>`: com `border-collapse: separate` o fundo do `<tr>` some atrás do da célula, e três
 * variantes arbitrárias concorrendo no mesmo `<td>` dependeriam da ordem de geração do CSS.
 */
const CLASSE_DENSIDADE: Record<DensidadeDaTabela, { thead: string; th: string; td: string; tbody: string; tr: string }> = {
  default: {
    thead: THEAD_PADRAO,
    th: 'p-3 font-medium whitespace-nowrap',
    td: 'p-3 align-top',
    tbody: 'divide-y divide-border',
    tr: 'hover:bg-accent/30',
  },
  dense: {
    thead: THEAD_PADRAO,
    th: 'px-2.5 py-2 text-[11px] uppercase tracking-[.07em] font-semibold whitespace-nowrap',
    td: 'px-2.5 py-[7px] text-[12.5px] align-top',
    tbody: 'divide-y divide-border/60',
    tr: 'hover:bg-primary/5',
  },
  grid: {
    thead: 'text-[var(--text-dim)]',
    th: 'sticky top-0 z-[1] bg-[var(--bg-2)] px-2.5 py-[7px] text-[10px] leading-[1.2] uppercase tracking-[.05em] font-semibold whitespace-nowrap border-b border-border select-none',
    td: 'px-2.5 py-[5px] text-[12.5px] align-middle border-b border-[var(--border-2)] whitespace-nowrap overflow-hidden text-ellipsis max-w-[320px]',
    tbody: '',
    tr: '',
  },
};

/** `grid`: listra das linhas pares — `color-mix` do DS, literal. */
const GRID_LISTRA = 'bg-[color-mix(in_oklch,var(--bg-2)_55%,transparent)]';
/** `grid`: linha selecionada — roxo da marca (ver nota da `CLASSE_DENSIDADE`). */
const GRID_SELECIONADA = 'bg-primary/15';
/** `grid`: hover vale só pra linha NÃO selecionada, como no DS. */
const GRID_HOVER = 'hover:[&>td]:bg-[var(--bg-2)]';
/**
 * `grid`: a moldura — borda, raio, superfície e a sombra leve medidas no protótipo da
 * Fabricação (2026-10-01). `overflow-hidden` recorta o cabeçalho e o rodapé no raio.
 */
const MOLDURA_GRID = 'overflow-hidden rounded-lg border border-border bg-[var(--surface)] shadow-[0_1px_2px_rgba(0,0,0,.04)]';

/**
 * No `grid` a rolagem mora num filho da moldura (pro rodapé não rolar junto). O
 * `containerType` vai com ela: é o container de `cqw` da mensagem de lista vazia.
 */
function Rolagem({ ativa, children }: { ativa: boolean; children: ReactNode }) {
  if (!ativa) return <>{children}</>;
  return (
    <div className="overflow-auto" style={{ containerType: 'inline-size' }}>
      {children}
    </div>
  );
}

/** Caixinha nativa do DataGrid: 13px tingida. A célula dela tem 34px. */
const CLASSE_CAIXINHA = 'm-0 size-[13px] cursor-pointer align-middle accent-[var(--color-primary)]';

/**
 * SELEÇÃO POR CAIXINHA — o `selectable` do DataGrid do DS, controlado por fora.
 *
 * A tabela não guarda quais linhas estão marcadas: ela pergunta (`isSelected`) e avisa
 * (`onToggle`). É a tela que sabe o ESCOPO do "marcar todas" — que pode ser a página, ou todas
 * as filtradas inclusive de outras páginas (R-08 da Fabricação). Por isso o estado do cabeçalho
 * (`allState`) também vem de fora: a tabela só vê a página atual e não teria como calculá-lo.
 */
export interface SelecaoDaTabela<T> {
  isSelected: (row: T) => boolean;
  onToggle: (row: T) => void;
  allState: 'all' | 'some' | 'none';
  onToggleAll: (marcar: boolean) => void;
  /** Nome acessível da caixinha da linha — "Selecionar <isto>". */
  rowLabel: (row: T) => string;
  /** Nome acessível da caixinha do cabeçalho. Omitido = "Selecionar todas". */
  allLabel?: string;
}

export interface PaginatorShape<T> {
  data: T[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props<T> {
  columns: ColumnDef<T, any>[];
  data: T[];
  /**
   * Paginador do servidor. OPCIONAL desde 2026-10-02: lista que cabe numa página só (os
   * Insumos e o Relatório da Fabricação) não tem paginador, e sem ele não há rodapé.
   */
  pagination?: PaginatorShape<T>;
  /**
   * Rota que recebe busca (`?q=`) e ordenação (`?sort=&dir=`). OPCIONAL desde 2026-10-02: sem
   * ela a busca integrada não é mostrada e o cabeçalho não vira botão de ordenar.
   */
  endpoint?: string;
  /**
   * NOME ACESSÍVEL da tabela — vira `<caption class="sr-only">`. OBRIGATÓRIO.
   *
   * É obrigatório e não opcional-com-default de propósito. Um default genérico
   * ("Tabela de dados") daria a MESMA string às 8 tabelas do repo, e duas telas
   * (`Arquivos/Index` e `Jana/Plataforma`) renderizam DUAS tabelas na mesma página —
   * quem navega por tabela (NVDA `T`, lista de tabelas do JAWS, rotor do VoiceOver)
   * não teria como distingui-las. Exigir a prop faz o TypeScript cobrar um nome de
   * cada consumidor novo, que é o único jeito de isso não apodrecer.
   *
   * `sr-only`, e não visível: caption visível mexeria no layout das 6 telas que já
   * estão em produção, e o defeito é de NOME, não de rótulo na tela. O par
   * `<caption class="sr-only">` já é o precedente da casa — ver
   * `Pages/Auditoria/Detail.tsx:47` e `Pages/Home/Index.tsx:422`.
   *
   * MEDIÇÃO (axe-core 4.12.1 em jsdom, 2026-09-04) — por que isto NÃO foi pego por
   * gate nenhum: axe **não tem regra** que exija nome acessível em tabela. Rodado
   * nos 5 arranjos (sem nada · só `scope` · só `caption` · os dois · só `aria-label`),
   * todos deram **0 violações em qualquer impacto**. Ou seja: subir o
   * `assertNoAccessibilityIssues(level: 0)` do `UC-DASH-18` pra `level: 1` (+serious)
   * — ou até pegar todos os impactos — **não pegaria isto**. O piso não está baixo:
   * o axe é cego a esta classe. Por isso o teste que defende esta prop mede o
   * **nome computado** (`getByRole('table', { name })`), nunca o axe.
   */
  caption: string;
  filters?: Record<string, string | number | null | undefined>;
  searchPlaceholder?: string;
  emptyMessage?: string;
  rowKey?: (row: T) => string | number;
  /** Se true, mostra SearchBar integrada com Scout (?q=) */
  showSearch?: boolean;
  /** Valor inicial da busca vindo do backend */
  initialSearch?: string;
  /**
   * Estado visual por linha — o `rows[].state` do protótipo. Devolva `undefined` pra linha
   * sem estado. É a linha que decide, com o dado dela: a tabela não adivinha.
   */
  rowState?: (row: T) => EstadoDaLinha | undefined;
  /**
   * Clique na linha inteira — o `onRowClick` do `DataTablePro` do protótipo, usado
   * para abrir um drawer de detalhe.
   *
   * OPT-IN: sem ele a linha continua sendo uma linha de tabela. Com ele a linha
   * ganha `role="button"`, `tabIndex` e Enter/Espaço — linha clicável que só
   * responde ao mouse é armadilha de teclado, e o DS não tem por que produzir uma.
   * Não use junto de controles dentro da célula sem parar a propagação neles.
   */
  onRowClick?: (row: T) => void;
  /**
   * Piso de largura da tabela, em px. Só vale quando alguma coluna declara `meta.width`.
   *
   * Sem piso, `table-layout: fixed` num container estreito espreme a coluna fluida até zero
   * em vez de rolar. O default é a SOMA das larguras declaradas — o menor número que não é
   * inventado: garante que nenhuma coluna com largura declarada seja espremida, e deixa a
   * rolagem horizontal do wrapper fazer o resto. Passe um valor maior quando o protótipo
   * declarar um (ex.: `.arq-lista table{min-width:1020px}`).
   */
  minTableWidth?: number;
  /**
   * SUBSTITUI as classes do wrapper da tabela (nao soma).
   *
   * Existe pela regra "um elemento, uma familia": quando a tela aplica o CSS do proprio
   * modulo no wrapper (ex.: `.arq-lista`, que ja traz superficie + borda + raio + rolagem),
   * somar as utilitarias do default renderiza DUAS molduras — dois bordos, dois raios, dois
   * contextos de rolagem. Substituir e a unica forma de ter uma so.
   *
   * Omitido = default DS canon, que e o que as outras 3 telas usam.
   */
  tableWrapperClassName?: string;
  /** Anatomia da tabela — ver `DensidadeDaTabela`. Omitido = `default` (markup de sempre). */
  density?: DensidadeDaTabela;
  /** Caixinha por linha + "marcar todas" no cabeçalho — ver `SelecaoDaTabela`. */
  selection?: SelecaoDaTabela<T>;
  /**
   * Com `onRowClick`, diz QUAIS linhas são clicáveis. Omitido = todas. A linha que devolve
   * `false` volta a ser linha comum: sem `role`, sem foco, sem cursor.
   */
  rowClickable?: (row: T) => boolean;
  /**
   * Substantivo do rodapé do `grid` — "1–10 de 32 <totalLabel>". Omitido = "itens".
   * Só o `grid` usa: os outros rodapés seguem "Página X de Y · N item(s)".
   */
  totalLabel?: string;
}

/**
 * Rodapé do `grid`, a partir dos `links` do paginador do Laravel — as URLs vêm do servidor
 * (com `withQueryString`), então filtro, busca e ordem atravessam a troca de página sem a
 * tabela precisar conhecê-los. O primeiro link é "anterior", o último é "próxima", e o "..."
 * do Laravel vira a reticência do DS.
 */
function RodapeGrid<T>({ pagination, totalLabel }: { pagination: PaginatorShape<T>; totalLabel: string }) {
  const links = pagination.links;
  const anterior = links[0];
  const proxima = links[links.length - 1];
  const paginas = links.slice(1, -1);
  const ir = (url: string | null) => url && router.visit(url, { preserveScroll: true, preserveState: true });
  const botao =
    'inline-flex h-7 min-w-7 items-center justify-center rounded-[8px] border border-border bg-transparent px-2 text-[12.5px] font-medium leading-none tabular-nums text-[var(--text-dim)] cursor-pointer disabled:cursor-not-allowed disabled:opacity-45 aria-[current=page]:border-transparent aria-[current=page]:bg-primary aria-[current=page]:font-semibold aria-[current=page]:text-primary-foreground';
  const seta = (dir: 'esq' | 'dir') => (
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
      <polyline points={dir === 'esq' ? '15 18 9 12 15 6' : '9 18 15 12 9 6'} />
    </svg>
  );
  return (
    <div
      data-slot="datatable-rodape"
      className="flex flex-wrap items-center justify-end gap-3 border-t border-border bg-[var(--bg-2)] px-2.5 py-[7px]"
    >
      <nav aria-label="Paginação" className="inline-flex items-center gap-2">
        <div className="inline-flex items-center gap-1">
          <button type="button" className={`${botao} w-7 px-0`} disabled={!anterior?.url} aria-label="Página anterior" onClick={() => ir(anterior?.url ?? null)}>
            {seta('esq')}
          </button>
          {paginas.map((l, i) =>
            l.url === null && !l.active ? (
              <span key={`e${i}`} className="px-0.5 text-[11.5px] text-[var(--text-dim)]">
                …
              </span>
            ) : (
              <button
                type="button"
                key={`${l.label}-${i}`}
                className={botao}
                aria-current={l.active ? 'page' : undefined}
                onClick={() => !l.active && ir(l.url)}
              >
                {l.label}
              </button>
            ),
          )}
          <button type="button" className={`${botao} w-7 px-0`} disabled={!proxima?.url} aria-label="Próxima página" onClick={() => ir(proxima?.url ?? null)}>
            {seta('dir')}
          </button>
        </div>
        <span className="pl-3 text-[11.5px] leading-none tabular-nums text-[var(--text-dim)]">
          <b className="font-semibold">
            {pagination.total === 0 ? 0 : (pagination.from ?? 0)}–{pagination.to ?? 0}
          </b>{' '}
          de {pagination.total} {totalLabel}
        </span>
      </nav>
    </div>
  );
}

export default function DataTable<T>({
  columns,
  data,
  pagination,
  endpoint,
  caption,
  filters = {},
  onRowClick,
  searchPlaceholder = 'Buscar...',
  emptyMessage = 'Nenhum resultado.',
  rowKey,
  showSearch = true,
  initialSearch = '',
  rowState,
  minTableWidth,
  tableWrapperClassName,
  density = 'default',
  selection,
  rowClickable,
  totalLabel = 'itens',
}: Props<T>) {
  const [searchTerm, setSearchTerm] = useState(initialSearch);
  const dens = CLASSE_DENSIDADE[density];
  const grid = density === 'grid';
  const nColunas = columns.length + (selection ? 1 : 0);

  // O "marcar todas" pode estar parcial — e `indeterminate` só existe como propriedade do DOM,
  // não como atributo HTML.
  const caixinhaTodas = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (caixinhaTodas.current) caixinhaTodas.current.indeterminate = selection?.allState === 'some';
  }, [selection?.allState]);

  // A geometria é lida das colunas UMA vez e vira `<colgroup>` — que é a forma canônica de
  // declarar largura em tabela HTML, e a única que o navegador respeita sob `table-layout:
  // fixed`. Largura em `<td>` sob layout fixo é ignorada; foi por isso que declarar no
  // `className` da célula nunca teria funcionado.
  const larguras = columns.map((c) => c.meta?.width);
  const temLargura = larguras.some((w) => typeof w === 'number' && w > 0);
  const somaDeclarada = larguras.reduce<number>((s, w) => s + (typeof w === 'number' ? w : 0), 0);
  const pisoDaTabela = temLargura ? (minTableWidth ?? somaDeclarada + (selection ? 34 : 0)) : undefined;

  // Debounce busca — envia pro backend (Scout faz keyword/vector lookup)
  useEffect(() => {
    if (searchTerm === initialSearch || !endpoint) return;
    const handle = setTimeout(() => {
      router.get(
        endpoint,
        { ...filters, q: searchTerm || undefined },
        { preserveScroll: true, preserveState: true, replace: true }
      );
    }, 300);
    return () => clearTimeout(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchTerm]);

  const table = useReactTable({
    data,
    columns,
    getCoreRowModel: getCoreRowModel(),
    manualPagination: true,   // paginação é server-side
    manualSorting: true,      // sort é server-side
    pageCount: pagination?.last_page ?? 1,
  });

  const sortAtual = typeof filters.sort === 'string' ? filters.sort : undefined;
  const dirAtual = filters.dir === 'desc' ? 'desc' : 'asc';

  const handleSort = (columnId: string) => {
    if (!endpoint) return;
    const currentSort = (filters as any).sort as string | undefined;
    const currentDir = (filters as any).dir as string | undefined;
    let newDir: 'asc' | 'desc' = 'asc';
    if (currentSort === columnId) newDir = currentDir === 'asc' ? 'desc' : 'asc';

    router.get(
      endpoint,
      { ...filters, q: searchTerm || undefined, sort: columnId, dir: newDir },
      { preserveScroll: true, preserveState: true, replace: true }
    );
  };

  return (
    <div className="space-y-3">
      {showSearch && endpoint && (
        <div className="relative">
          <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          {/* O className abaixo NÃO volta a ser `pl-9 pr-9`: o <Input> nasce `variant="cowork"`,
              cujo `.cw-input` é UNLAYERED (cowork-fields.css entra por @import sem @layer) e usa
              o shorthand `padding: 0 8px`. As utilitárias do Tailwind v4 vivem em
              `@layer utilities`, e pela cascata de layers estilo unlayered SEMPRE vence layered —
              então `pl-9` era simplesmente ignorado, o texto começava em 8px e a lupa
              (`left-3` = 12px) ficava encavalada sobre a primeira letra do placeholder.
              As longhands são a solução canônica dessa colisão, criada por Wagner em 2026-06-13
              pro MESMO bug na lista de clientes. Quatro telas já as usavam à mão e este DataTable
              COMPARTILHADO tinha ficado de fora — por isso o defeito aparecia em toda tela que
              usa a busca dele, não só na Arquivos onde foi reportado. */}
          <Input
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder={searchPlaceholder}
            className="cw-input-icon-left cw-input-icon-right"
          />
          {searchTerm && (
            <button
              type="button"
              onClick={() => setSearchTerm('')}
              className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
              aria-label="Limpar busca"
            >
              <X size={14} />
            </button>
          )}
        </div>
      )}

      {/* `containerType: inline-size` faz do wrapper o container de `cqw`: é o que deixa a
          mensagem de lista vazia medir a largura VISÍVEL da rolagem (ver o `<td>` vazio abaixo).
          Vai em `style`, não em classe, pra valer também quando `tableWrapperClassName`
          substitui as classes do default. */}
      {/* No `grid` o wrapper é a MOLDURA (borda, raio, rodapé dentro) e a rolagem mora num
          filho — senão o rodapé rolaria junto com a tabela. Fora do `grid`, o de sempre. */}
      <div
        className={
          tableWrapperClassName ??
          (grid ? MOLDURA_GRID : 'border border-border rounded overflow-x-auto')
        }
        style={grid ? undefined : { containerType: 'inline-size' }}
      >
        <Rolagem ativa={grid}>
        <table
          className={`w-full ${grid ? 'border-separate border-spacing-0 text-[12.5px]' : 'text-sm'}${temLargura ? ' table-fixed' : ''}`}
          style={pisoDaTabela ? { minWidth: pisoDaTabela } : undefined}
        >
          {/* PRIMEIRO filho do <table>, antes do <colgroup>: o HTML só admite <caption> nessa
              posição, e fora dela o parser do navegador a reposiciona ou descarta. */}
          <caption className="sr-only">{caption}</caption>
          {temLargura && (
            <colgroup>
              {selection && <col style={{ width: 34 }} />}
              {larguras.map((w, i) => (
                // Coluna sem largura declarada fica sem `<col style>` de propósito: ela é a
                // FLUIDA, e absorve a sobra. Declarar todas tira essa folga.
                <col key={i} style={typeof w === 'number' && w > 0 ? { width: w } : undefined} />
              ))}
            </colgroup>
          )}
          <thead className={dens.thead}>
            {table.getHeaderGroups().map((group) => (
              <tr key={group.id}>
                {selection && (
                  <th scope="col" className={`${dens.th} w-[34px] text-center`}>
                    <input
                      ref={caixinhaTodas}
                      type="checkbox"
                      className={CLASSE_CAIXINHA}
                      checked={selection.allState === 'all'}
                      onChange={(e) => selection.onToggleAll(e.target.checked)}
                      aria-label={selection.allLabel ?? 'Selecionar todas'}
                    />
                  </th>
                )}
                {group.headers.map((header) => {
                  const canSort = header.column.getCanSort() && !!endpoint;
                  const align = header.column.columnDef.meta?.align;
                  const ordenada = sortAtual === header.id;
                  return (
                    <th
                      key={header.id}
                      // `scope="col"` é EXPLICITAÇÃO, não conserto — e o registro importa pra
                      // ninguém depois vender isto como a correção do nome acessível.
                      // MEDIDO (axe-core 4.12.1 em jsdom, 2026-09-04): a regra
                      // `th-has-data-cells` — que só passa quando o algoritmo de tabela
                      // conseguiu associar o <th> às células de dados dele — PASSA tanto SEM
                      // `scope` quanto COM. Controle negativo (<th> órfão, sem célula de dado
                      // nenhuma) NÃO passa: volta `incomplete`. Logo o algoritmo automático do
                      // HTML já associa cabeçalho↔coluna nesta tabela de UMA linha de <th>, e
                      // `scope="col"` não cria associação que faltava. Fica porque é a técnica
                      // WCAG H63, porque é o precedente da casa (`Pages/Home/Index.tsx:424-425`
                      // usa `scope="col"`/`scope="row"` à mão) e porque blinda o dia em que
                      // alguém puser uma 2ª linha de cabeçalho aqui — não porque muda o que o
                      // leitor de tela anuncia hoje.
                      scope="col"
                      // O alinhamento é da CÉLULA. Escrever `text-right` num <span> dentro
                      // dela move o texto e deixa o cabeçalho à esquerda — número à direita
                      // sob rótulo à esquerda foi exatamente o defeito reportado.
                      className={`${CLASSE_ALINHAMENTO[align ?? 'left']} ${dens.th}`}
                      aria-sort={grid && canSort && ordenada ? (dirAtual === 'desc' ? 'descending' : 'ascending') : undefined}
                    >
                      {canSort && grid ? (
                        // Indicador do DataGrid: SEMPRE depois do rótulo — ↕ apagado quando a
                        // coluna não ordena, ↑/↓ cheio quando ordena; a coluna ativa sobe pra
                        // `--text`. O DS usa `<span onClick>`; aqui é `<button>` pra ordenação
                        // continuar no teclado. O visual é o mesmo.
                        <button
                          type="button"
                          onClick={() => handleSort(header.id)}
                          className={`inline-flex items-center gap-[3px] uppercase tracking-[inherit] ${ordenada ? 'text-[var(--text)]' : ''}`}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext()) as ReactNode}
                          <span aria-hidden className={ordenada ? '' : 'opacity-40'}>
                            {ordenada ? (dirAtual === 'desc' ? '↓' : '↑') : '↕'}
                          </span>
                        </button>
                      ) : canSort ? (
                        <button
                          type="button"
                          onClick={() => handleSort(header.id)}
                          className={`flex w-full items-center gap-1 hover:text-foreground ${CLASSE_JUSTIFICA[align ?? 'left']}`}
                        >
                          {flexRender(header.column.columnDef.header, header.getContext()) as ReactNode}
                          <ArrowUpDown size={11} className="opacity-50" />
                        </button>
                      ) : (
                        flexRender(header.column.columnDef.header, header.getContext()) as ReactNode
                      )}
                    </th>
                  );
                })}
              </tr>
            ))}
          </thead>
          <tbody className={dens.tbody}>
            {table.getRowModel().rows.length === 0 ? (
              <tr>
                <td colSpan={nColunas} className="p-0 text-sm text-muted-foreground">
                  {/* A célula tem a largura da TABELA, que pode ser bem maior que a área visível
                      (Bens: 1626px). Centralizar nela jogava o texto fora da tela — medido em prod
                      em 2026-09-30: texto em x=842..1306 com o wrapper visível em 260..1024.
                      `sticky left-0` + `100cqw` centraliza na área VISÍVEL e acompanha a rolagem. */}
                  <div data-slot="datatable-vazio" className="sticky left-0 w-[100cqw] p-12 text-center">
                    {emptyMessage}
                  </div>
                </td>
              </tr>
            ) : (
              table.getRowModel().rows.map((row, i) => {
                const marcada = selection?.isSelected(row.original) ?? false;
                // Linha marcada pela caixinha herda o estado `selected` — exceto no `grid`, que
                // pinta a seleção na `<td>` (ver `CLASSE_DENSIDADE`).
                const estado = rowState?.(row.original) ?? (marcada && !grid ? 'selected' : undefined);
                const clicavel = onRowClick !== undefined && (rowClickable?.(row.original) ?? true);
                const fundoGrid = grid ? (marcada ? GRID_SELECIONADA : i % 2 === 1 ? GRID_LISTRA : '') : '';
                const classeTd = `${dens.td}${fundoGrid ? ' ' + fundoGrid : ''}`;
                return (
                  <tr
                    key={rowKey ? rowKey(row.original) : row.id}
                    className={`${dens.tr}${estado ? ' ' + CLASSE_ESTADO[estado] : ''}${grid && !marcada ? ' ' + GRID_HOVER : ''}${
                      clicavel ? ' cursor-pointer focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring' : ''
                    }`}
                    data-selecionada={selection ? String(marcada) : undefined}
                    data-estado={estado}
                    // A linha só vira controle quando a tela pede. Sem `onRowClick` ela
                    // continua sendo uma linha de tabela — nem `role`, nem foco, nem cursor.
                    role={clicavel ? 'button' : undefined}
                    tabIndex={clicavel ? 0 : undefined}
                    onClick={clicavel ? () => onRowClick!(row.original) : undefined}
                    onKeyDown={
                      clicavel
                        ? (e) => {
                            if (e.key === 'Enter' || e.key === ' ') {
                              e.preventDefault();
                              onRowClick!(row.original);
                            }
                          }
                        : undefined
                    }
                  >
                    {selection && (
                      // A caixinha não abre a linha: clique E teclado param aqui. Sem parar o
                      // `keydown`, o Espaço na caixinha subiria até o `<tr>`, que dá
                      // `preventDefault` — a caixinha não marcaria e a linha abriria.
                      <td
                        className={`${classeTd} w-[34px] text-center`}
                        onClick={(e) => e.stopPropagation()}
                        onKeyDown={(e) => e.stopPropagation()}
                      >
                        <input
                          type="checkbox"
                          className={CLASSE_CAIXINHA}
                          checked={marcada}
                          onChange={() => selection.onToggle(row.original)}
                          aria-label={`Selecionar ${selection.rowLabel(row.original)}`}
                        />
                      </td>
                    )}
                    {row.getVisibleCells().map((cell) => {
                      const meta = cell.column.columnDef.meta;
                      const align = meta?.align;
                      return (
                        <td
                          key={cell.id}
                          className={`${CLASSE_ALINHAMENTO[align ?? 'left']} ${classeTd}${meta?.mono ? ' font-mono tabular-nums' : ''}`}
                        >
                          {flexRender(cell.column.columnDef.cell, cell.getContext()) as ReactNode}
                        </td>
                      );
                    })}
                  </tr>
                );
              })
            )}
          </tbody>
        </table>
        </Rolagem>
        {grid && pagination && <RodapeGrid pagination={pagination} totalLabel={totalLabel} />}
      </div>

      {!grid && pagination && pagination.last_page > 1 && (
        <div className="flex items-center justify-between text-xs text-muted-foreground">
          <span>
            Página {pagination.current_page} de {pagination.last_page} · {pagination.total} item(s)
          </span>
          <div className="flex gap-1">
            {pagination.links.map((link, i) => {
              const isPrev = link.label.includes('Previous') || link.label.includes('&laquo;');
              const isNext = link.label.includes('Next') || link.label.includes('&raquo;');
              const content = isPrev ? <ChevronLeft size={12} /> : isNext ? <ChevronRight size={12} /> : link.label;
              return (
                <Button
                  key={i}
                  variant={link.active ? 'default' : 'outline'}
                  size="sm"
                  className="h-7 min-w-8 px-2 text-xs"
                  disabled={!link.url}
                  onClick={() => link.url && router.visit(link.url, { preserveScroll: true, preserveState: true })}
                  dangerouslySetInnerHTML={typeof content === 'string' ? { __html: content } : undefined}
                  children={typeof content === 'string' ? undefined : content}
                />
              );
            })}
          </div>
        </div>
      )}
    </div>
  );
}
