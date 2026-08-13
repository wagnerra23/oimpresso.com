import { Head, router, Deferred } from '@inertiajs/react';
import { usePageProps, useBusiness } from '@/Hooks/usePageProps';
import { useState, useCallback, useEffect, useRef } from 'react';
import type { ReactNode, KeyboardEvent, RefObject } from 'react';
import {
  SlidersHorizontal, Search, X,
  ChevronsLeft, ChevronLeft, ChevronRight, ChevronsRight,
  Package, Star, Moon, TrendingUp, Clock, ArrowUpDown,
} from 'lucide-react';
import { Input } from '@/Components/ui/input';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Grid, Inline } from '@/Components/layout';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

/**
 * Catálogo Unificado · módulo Produto · Cockpit V2.
 * 5 sub-telas em uma rota: produtos | categorias | insumos | tabelas | historico.
 * Persona-foco: Larissa · 1280×1024 · density-first.
 *
 * Camadas (ADR UI-0013): Fundações (tokens) → Shell (AppShellV2) → PT-01 Lista densa.
 * Controles via Design System (@/Components/ui/*); cores só via tokens.
 */

type Props = {
  tela: 'produtos' | 'categorias' | 'insumos' | 'tabelas' | 'historico';
  filters: {
    tela: string;
    tab: string;
    busca: string;
    categoria: string;
    view: 'table' | 'grid';
    densidade: 'compact' | 'comfortable' | 'cozy';
    pagina: number;
    por_pagina: number;
    /** Recorte do KPI clicado. O mesmo predicado que contou o card filtra a lista. */
    kpi: string | null;
    ordem: string;
    dir: 'asc' | 'desc';
  };
  /**
   * Meta da paginação. Opcional no TIPO porque partial reload que não a peça não a traz —
   * o rodapé some em vez de estourar. `produtos` continua sendo a LISTA crua (os UCs do
   * contrato fazem `firstWhere('id', …)` nela); a meta viaja separada de propósito.
   */
  paginacao?: {
    total: number;
    pagina: number;
    ultima: number;
    por_pagina: number;
    de: number | null;
    ate: number | null;
    opcoes: number[];
  };
  /**
   * DEFERIDAS (Inertia::defer no controller): NÃO chegam no 1º render — a página pinta
   * primeiro e elas vêm depois. Por isso são opcionais no tipo e têm default aqui embaixo:
   * ler `.length` de prop deferida antes da hora derruba a tela inteira.
   */
  kpis?: {
    catalogo_ativo: number;
    populares: number;
    sem_giro: number;
    margem_baixa: number;
    sob_demanda: number;
  };
  produtos?: ProdutoRow[];
  categorias: CategoriaRow[];
  insumos: InsumoRow[];
  tabelas: TabelaRow[];
  historico: HistoricoRow[];
  // Opcional no TIPO porque o runtime pode não entregá-la (partial reload que não a peça);
  // o componente aplica default fail-closed. O backend sempre a envia no load completo.
  permissoes?: Permissoes;
};

/**
 * Quem pode ver o quê (UC-PUNI-01..04 · Index.casos.md). O backend NÃO EMITE a chave do dado
 * que o usuário não pode ver — `price`, `cost`, `margin`, `value` e `bomCount` são opcionais
 * de propósito. Esta tela usa os flags pra remover a COLUNA INTEIRA, nunca pra imprimir 0/—
 * no lugar do valor: zero é uma afirmação sobre o custo, e o contrato é ausência
 * (AR-PROD-015 — "os campos somem da tela").
 *
 * ⚠️ Não é decoração: `fmtBRL(undefined)` lança TypeError (`n.toLocaleString`) e derruba a
 * página inteira. Renderizar a coluna sem o dado troca um vazamento por uma tela branca.
 */
type Permissoes = { custo: boolean; preco: boolean; composicao: boolean };

type ProdutoRow = {
  id: number;
  sku: string;
  name: string;
  cat: string | null;
  cat_label: string | null;
  unit: string;
  price?: number;
  cost?: number;
  margin?: number;
  stockKind: 'estoque' | 'sob_demanda';
  stockQty: number | null;
  uses30: number;
  active: boolean;
  updated: string | null;
  bomCount?: number;
};
type CategoriaRow  = { id: number; slug: string; label: string; count: number };
type InsumoRow     = { id: number; name: string; unit: string; cost?: number; stock: number; fornecedor: string | null };
type TabelaRow     = { id: string; label: string; desc: string; mult: number };
type HistoricoRow  = { os: string; date: string; prodId: string; prodName: string; cat: string | null; unit: string; client: string | null; qty: number; value?: number };

type Tweaks = { density: 'compact' | 'comfortable' | 'cozy'; view: 'table' | 'grid'; showCost: boolean };

const STORAGE_KEY = 'oimpresso.produto.tweaks';

const fmtBRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const fmtPct = (n: number) => Math.round(n * 100) + '%';

function ProdutoUnificadoIndex({
  tela, filters, kpis, produtos = [], categorias, insumos, tabelas, historico, paginacao,
  // Fail-closed: se a prop não chegar por qualquer caminho, esconde tudo em vez de
  // estourar `undefined.custo`. Ausência de permissão declarada nunca vira permissão.
  permissoes = { custo: false, preco: false, composicao: false },
}: Props) {
  // Nome da empresa: MESMA fonte que o AppShellV2 usa na sidebar
  // (`shell.cockpit.businessNome`, AppShellV2.tsx:273), que sai de uma query em
  // `App\Business` — robusta. O `business.name` do shared prop vem da SESSÃO e
  // chega VAZIO em ambiente de teste, então serve só de fallback: a 1ª versão
  // deste fix usava só ele e o cabeçalho ficou sem nome nenhum na baseline de
  // 2026-08-07, enquanto a sidebar ao lado mostrava o tenant certo.
  // Sem default inventado — `AppShellV2` cai em 'Oimpresso' quando não sabe, e
  // imprimir isso aqui seria afirmar um tenant que não é o do usuário. Se as duas
  // fontes falharem, o sufixo some.
  // Até 2026-08-07 esta linha era "ROTA LIVRE" ESCRITO FIXO: toda empresa via o
  // nome de uma cliente real no cabeçalho (Tier 0, ADR 0093).
  // Os DOIS hooks são chamados incondicionalmente de propósito: `a ?? b` não avalia
  // `b` quando `a` tem valor, e hook em avaliação condicional quebra as Rules of Hooks.
  // `ShellProps` (types/index.ts:59) ainda não declara `cockpit` — o próprio
  // AppShellV2 (:244) contorna com tipo local. Mesmo contorno aqui, escopado, em
  // vez de mexer no tipo compartilhado dentro de um PR de tela.
  const shell = usePageProps().shell as ({ cockpit?: { businessNome?: string } } | undefined);
  const nomeDoShell = shell?.cockpit?.businessNome ?? null;
  const nomeDaSessao = useBusiness()?.name ?? null;
  const businessName = nomeDoShell ?? nomeDaSessao;
  // Tweaks persistidos (densidade, view, mostrar custo).
  const [tweaks, setTweaksState] = useState<Tweaks>(() => {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (raw) return { density: 'comfortable', view: 'table', showCost: true, ...JSON.parse(raw) };
    } catch {}
    return { density: filters.densidade, view: filters.view, showCost: true };
  });
  const setTweak = useCallback((edits: Partial<Tweaks>) => {
    setTweaksState((prev) => {
      const next = { ...prev, ...edits };
      try { localStorage.setItem(STORAGE_KEY, JSON.stringify(next)); } catch {}
      return next;
    });
  }, []);

  // ── Slot 3 · Toolbar (busca + filtros + paginação) ────────────────────────────
  // `filters.busca` e `filters.categoria` JÁ chegavam do controller e morriam sem UI:
  // a tela não tinha como achar um produto. O estado local existe só pro debounce —
  // a verdade é a querystring, então voltar/avançar no navegador continua funcionando.
  const [busca, setBusca] = useState(filters.busca ?? '');
  // Drawer: guarda a LINHA, não o id. A ficha mostra o que a lista já carregou —
  // buscar de novo o que está na mão seria latência sem ganho.
  const [linhaAberta, setLinhaAberta] = useState<ProdutoRow | null>(null);
  const buscaRef = useRef<HTMLInputElement>(null);
  const primeiraRenderizacao = useRef(true);

  /**
   * Navega preservando os demais filtros. Qualquer mudança que altere o CONJUNTO
   * (busca, categoria, aba, por_pagina) volta pra página 1 — senão o usuário filtra
   * estando na página 7 e cai numa lista vazia sem entender por quê.
   */
  const irPara = useCallback((patch: Record<string, unknown>, resetarPagina = true) => {
    router.get(
      route('products.unificado.index'),
      { ...filters, ...patch, ...(resetarPagina ? { pagina: 1 } : {}) },
      { preserveState: true, preserveScroll: true, replace: true, only: ['produtos', 'paginacao', 'filters'] },
    );
  }, [filters]);

  // Debounce da busca: 350ms sem digitar. Sem isso, cada tecla vira um request.
  useEffect(() => {
    if (primeiraRenderizacao.current) { primeiraRenderizacao.current = false; return; }
    if (busca === (filters.busca ?? '')) return;
    const t = setTimeout(() => irPara({ busca }), 350);
    return () => clearTimeout(t);
  }, [busca, filters.busca, irPara]);

  // `/` foca a busca (atalho canônico PT-01). Não sequestra a tecla quando o usuário
  // já está digitando em outro campo.
  useEffect(() => {
    // `globalThis.KeyboardEvent` explícito: o arquivo importa o KeyboardEvent do React
    // (tipo de evento sintético), e usá-lo aqui não casa com o addEventListener do window.
    const onKey = (e: globalThis.KeyboardEvent) => {
      const alvo = e.target as HTMLElement | null;
      const digitando = alvo && (alvo.tagName === 'INPUT' || alvo.tagName === 'TEXTAREA' || alvo.isContentEditable);
      if (e.key === '/' && !digitando) { e.preventDefault(); buscaRef.current?.focus(); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const setSubTela = (t: Props['tela']) =>
    // D-14: partial reload — só re-busca o que muda com a sub-tela.
    // kpis/produtos/categorias são closures no controller (não mudam com `tela`) — pulam.
    router.get(route('products.unificado.index'), { ...filters, tela: t }, {
      preserveState: true, preserveScroll: true, replace: true,
      only: ['tela', 'filters', 'insumos', 'tabelas', 'historico'],
    });

  return (
    <>
      <Head title="Catálogo · Produto" />
      <div className="min-h-screen bg-background text-foreground">
        <header className="sticky top-0 z-30 bg-card/85 backdrop-blur border-b border-border">
          <div className="px-6 h-14 flex items-center gap-4">
            <div className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
              <span>Produto</span>
              <span className="text-muted-foreground/60">›</span>
              <span className="text-foreground font-medium">Catálogo</span>
            </div>
          </div>
          <div className="px-6 pt-4 pb-3 flex items-baseline gap-3">
            <h1 className="text-[22px] font-bold tracking-tight leading-snug">Catálogo</h1>
            <span className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              {/* `kpis` é deferida: no 1º render ela não existe. Sem o guard, esta linha
                  derruba a página antes de a lista sequer chegar. */}
              {kpis ? `${kpis.catalogo_ativo} ${kpis.catalogo_ativo === 1 ? 'produto' : 'produtos'}` : '…'}
              {businessName ? ` · ${businessName}` : ''}
            </span>
          </div>

          <nav className="px-6 pb-2 flex items-center gap-1 text-[12px]" aria-label="Sub-telas do catálogo">
            {([
              ['produtos',   'Produtos'],
              ['categorias', 'Categorias'],
              ['insumos',    'Insumos · BOM'],
              ['tabelas',    'Tabelas de preço'],
              ['historico',  'Histórico de uso'],
            ] as const).map(([id, label]) => (
              <button
                key={id}
                type="button"
                aria-current={tela === id ? 'page' : undefined}
                onClick={() => setSubTela(id)}
                className={`h-7 px-3 rounded-md transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                  tela === id
                    ? 'bg-primary text-primary-foreground font-medium'
                    : 'text-muted-foreground hover:bg-muted'
                }`}
              >
                {label}
              </button>
            ))}
          </nav>
        </header>

        <div className="pb-16">
          {/* Slot · KPI strip CLICÁVEL — cada card é um recorte da lista, não um enfeite.
              Clicar filtra; clicar de novo desliga. O count do card e a lista filtrada saem
              do MESMO predicado no controller (aplicarRecorte), então não divergem.
              A placa roxa cheia saiu: roxo agora significa "filtro ativo", como na /contacts. */}
          <Deferred data="kpis" fallback={<SkeletonKpis />}>
            <Grid min="sm" gap={3} className="mx-6 mt-4">
              {KPI_CARDS.map((k) => (
                <KpiFiltro
                  key={k.chave}
                  {...k}
                  value={kpis?.[k.prop] ?? 0}
                  ativo={filters.kpi === k.chave}
                  onClick={() => irPara({ kpi: filters.kpi === k.chave ? '' : k.chave })}
                />
              ))}
            </Grid>
          </Deferred>

          {/* Slot 3 · Toolbar — só na sub-tela Produtos, que é a única paginada/filtrável */}
          {tela === 'produtos' && (
            <Toolbar
              busca={busca}
              setBusca={setBusca}
              buscaRef={buscaRef}
              categorias={categorias}
              categoriaAtual={filters.categoria}
              onCategoria={(c) => irPara({ categoria: c })}
              total={paginacao?.total ?? produtos.length}
              kpiLabel={KPI_CARDS.find((k) => k.chave === filters.kpi)?.label ?? null}
              onRemoverKpi={() => irPara({ kpi: '' })}
              onLimpar={() => { setBusca(''); irPara({ busca: '', categoria: '', kpi: '' }); }}
            />
          )}

          {/* Conteúdo por sub-tela.
              Estado "busca sem resultado" é obrigatório no PT-01 — sem ele a tela some a
              lista e não diz por quê, e o usuário acha que o produto não existe. */}
          {/* `produtos`/`paginacao` são deferidas: o <Deferred> segura este bloco com
              skeleton enquanto elas não chegam. Sem ele a tela pintaria "Nenhum produto
              cadastrado ainda" por meio segundo — dizendo ao usuário que o catálogo está
              vazio quando na verdade ainda está carregando. */}
          {tela === 'produtos' && (
            <Deferred data={['produtos', 'paginacao']} fallback={<SkeletonTabela />}>
              <>
          {produtos.length === 0 && (
            <div className="mx-6 mt-3 rounded-md border border-border bg-card px-6 py-10 text-center">
              <p className="text-[13px] text-foreground">
                {busca || filters.categoria
                  ? 'Nenhum produto encontrado nesse filtro.'
                  : 'Nenhum produto cadastrado ainda.'}
              </p>
              {(busca || filters.categoria) && (
                <button
                  type="button"
                  onClick={() => { setBusca(''); irPara({ busca: '', categoria: '' }); }}
                  className="mt-2 text-[12px] text-primary underline underline-offset-2"
                >
                  limpar os filtros
                </button>
              )}
            </div>
          )}
          {produtos.length > 0 && <TabelaProdutos
              rows={produtos}
              tweaks={tweaks}
              perm={permissoes}
              onOpen={(r) => setLinhaAberta(r)}
              ordem={filters.ordem}
              dir={filters.dir}
              onOrdenar={(col) => irPara({ ordem: col, dir: filters.ordem === col && filters.dir === 'asc' ? 'desc' : 'asc' }, false)}
            />}
              </>
            </Deferred>
          )}
          {tela === 'categorias' && <ListaCategorias rows={categorias} />}
          {tela === 'insumos'    && <ListaInsumos rows={insumos} perm={permissoes} />}
          {tela === 'tabelas'    && <ListaTabelas rows={tabelas} produtos={produtos} perm={permissoes} />}
          {tela === 'historico'  && <ListaHistorico rows={historico} perm={permissoes} />}

          {/* Rodapé de paginação — formato da referência /contacts: "Mostrando X–Y de N"
              à esquerda, "Por página" + « ‹ pág/total › » à direita. NÃO numera páginas:
              com catálogo grande a régua numerada não fecha (a /contacts tem 269). */}
          {tela === 'produtos' && paginacao && paginacao.total > 0 && (
            <Paginacao
              meta={paginacao}
              onPagina={(p) => irPara({ pagina: p }, false)}
              onPorPagina={(n) => irPara({ por_pagina: n })}
            />
          )}
        </div>

        {/* Tweaks panel (canto inferior direito) */}
        <TweaksPanel tweaks={tweaks} setTweak={setTweak} perm={permissoes} />

        <DrawerProduto row={linhaAberta} perm={permissoes} onClose={() => setLinhaAberta(null)} />
      </div>
    </>
  );
}

ProdutoUnificadoIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produto — Catálogo"
    breadcrumbItems={[{ label: 'Produto', href: '/products' }, { label: 'Catálogo' }]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoUnificadoIndex;

/* ─── Subcomponentes ────────────────────────────────────────────────── */

/**
 * Slot 3 · Toolbar — filtros à esquerda, busca à direita, chips de filtro ativo embaixo.
 * Mesma anatomia da /contacts (`Cliente/Index.tsx`), que é a referência do PT-01.
 *
 * ⚠️ Nenhum `<SelectItem value="">`: valor vazio explode o Radix em runtime (§5 2026-06-29).
 * O "todas" usa a sentinela TODAS, e a lista de categorias é filtrada por id truthy.
 */
const TODAS = 'todas';

/**
 * Os 5 recortes do strip. `chave` é o mesmo valor que o controller espera em `?kpi=` —
 * mudar aqui sem mudar o `aplicarRecorte()` quebra o par card↔lista.
 *
 * Tipografia igual à referência /contacts (`KpiStripClickable`): quadrado 36px (h-9),
 * label 10px caixa-alta, VALOR text-lg (18px) tabular-nums, legenda 10px. O valor de 28px
 * que estava aqui era o degrau mais fora de escala da tela.
 */
const KPI_CARDS = [
  { chave: 'ativos',    prop: 'catalogo_ativo' as const, label: 'Catálogo ativo', sub: 'à venda hoje',        Icon: Package,    cor: 'text-primary'          },
  { chave: 'populares', prop: 'populares'      as const, label: 'Populares · 30d', sub: '30+ saídas',         Icon: Star,       cor: 'text-warning-fg'       },
  { chave: 'semgiro',   prop: 'sem_giro'       as const, label: 'Sem giro',        sub: '0 saídas em 30d',    Icon: Moon,       cor: 'text-destructive-fg'   },
  { chave: 'margem',    prop: 'margem_baixa'   as const, label: 'Margem baixa',    sub: 'abaixo de 30%',      Icon: TrendingUp, cor: 'text-primary'          },
  { chave: 'demanda',   prop: 'sob_demanda'    as const, label: 'Sob demanda',     sub: 'sem estoque próprio', Icon: Clock,     cor: 'text-success-fg'       },
];

/**
 * Slot 6 · Drawer da ficha do produto — 760px, o canon de entidade cadastral
 * (ADR 0185 / ADR 0179). Em 1280 (monitor da Larissa): 760 + 260 do shell = 1020,
 * cabe sem scroll horizontal. A referência /contacts usa exatamente esta largura.
 *
 * Abre com o que a LINHA já tem — não dispara request novo. Isso é decisão, não
 * preguiça: os dados do drawer (sku, nome, categoria, preço, custo, margem, estoque)
 * são os mesmos que a lista carregou. Buscar de novo pra mostrar o que já está na mão
 * seria latência sem ganho.
 *
 * O que NÃO tem aqui, e por quê: a v2 desenha seções de "Composição (BOM)" e
 * "Histórico do produto". O controller devolve `bomCount` = 0 literal (TODO) e não
 * serve histórico por produto. Desenhar as seções vazias afirmaria "este produto não
 * tem composição" — que é diferente de "ainda não sabemos". Elas entram quando o dado
 * entrar; seção vazia com cara de resposta é pior que seção ausente.
 */
function DrawerProduto({ row, perm, onClose }: { row: ProdutoRow | null; perm: Permissoes; onClose: () => void }) {
  return (
    <Sheet open={row !== null} onOpenChange={(o) => { if (! o) onClose(); }}>
      <SheetContent side="right" className="cw-sheet w-[760px] sm:max-w-[760px] p-0 flex flex-col">
        {row && (
          <>
            <SheetHeader className="px-6 py-4 border-b border-border">
              <Inline gap={2} align="center">
                <span className="font-mono text-[11px] text-muted-foreground">{row.sku}</span>
                <Badge variant={row.active ? 'default' : 'secondary'}>{row.active ? 'à venda' : 'inativo'}</Badge>
              </Inline>
              <SheetTitle className="text-[16px] font-semibold leading-snug text-left">{row.name}</SheetTitle>
              <SheetDescription className="text-[11px] text-muted-foreground text-left">
                {row.cat_label ?? 'Sem categoria'} · unidade {row.unit}
              </SheetDescription>
            </SheetHeader>

            <div className="flex-1 overflow-y-auto px-6 py-4 space-y-5">
              {(perm.preco || perm.custo) && (
                <section>
                  <h3 className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">Preço e margem</h3>
                  <Grid cols={3} gap={3}>
                    {perm.preco && <FichaValor rotulo="Preço de venda" valor={row.price !== undefined ? fmtBRL(row.price) : '—'} />}
                    {perm.custo && <FichaValor rotulo="Custo" valor={row.cost !== undefined ? fmtBRL(row.cost) : '—'} />}
                    {row.margin !== undefined && <FichaValor rotulo="Margem" valor={fmtPct(row.margin)} />}
                  </Grid>
                </section>
              )}

              <section>
                <h3 className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-2">Estoque</h3>
                <Grid cols={3} gap={3}>
                  <FichaValor
                    rotulo="Controle"
                    valor={row.stockKind === 'estoque' ? 'Estoque próprio' : 'Sob demanda'}
                  />
                  {/* `stockQty` é null fixo no controller (TODO: somar variation_location_details).
                      "—" = desconhecido. Imprimir 0 afirmaria estoque zerado. */}
                  <FichaValor rotulo="Quantidade" valor={row.stockQty !== null ? `${row.stockQty} ${row.unit}` : '—'} />
                  <FichaValor rotulo="Atualizado" valor={row.updated ?? '—'} />
                </Grid>
              </section>
            </div>

            <div className="border-t border-border px-6 py-3">
              <a
                href={`/products/${row.id}`}
                className="text-[12px] text-primary underline underline-offset-2"
              >
                Abrir ficha completa
              </a>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

function FichaValor({ rotulo, valor }: { rotulo: string; valor: string }) {
  return (
    <div className="rounded-md border border-border bg-card px-3 py-2">
      <div className="text-[10px] uppercase tracking-wider text-muted-foreground">{rotulo}</div>
      <div className="text-[14px] font-semibold tabular-nums mt-0.5">{valor}</div>
    </div>
  );
}

/**
 * Esqueletos das props deferidas. Preenchem o MESMO espaço que o conteúdo real vai
 * ocupar — skeleton menor que o conteúdo faz a página pular quando o dado chega, e o
 * pulo é pior que a espera.
 */
function SkeletonKpis() {
  return (
    <Grid min="sm" gap={3} className="mx-6 mt-4" aria-hidden>
      {[0, 1, 2, 3, 4].map((i) => (
        <div key={i} className="rounded-md border border-border bg-card p-3">
          <Inline gap={2} align="center">
            <span className="h-9 w-9 rounded-md bg-muted animate-pulse shrink-0" />
            <span className="flex-1 min-w-0">
              <span className="block h-2 w-20 rounded bg-muted animate-pulse" />
              <span className="block h-4 w-12 rounded bg-muted animate-pulse mt-1.5" />
              <span className="block h-2 w-16 rounded bg-muted animate-pulse mt-1.5" />
            </span>
          </Inline>
        </div>
      ))}
    </Grid>
  );
}

function SkeletonTabela() {
  return (
    <div className="mx-6 mt-3 rounded-md border border-border bg-card overflow-hidden" aria-hidden>
      <div className="h-9 border-b border-border bg-muted/40" />
      {Array.from({ length: 8 }).map((_, i) => (
        <Inline key={i} align="center" className="h-11 border-b border-border/60 px-4">
          <span className="h-2.5 w-full max-w-[42%] rounded bg-muted animate-pulse" />
        </Inline>
      ))}
    </div>
  );
}

/**
 * Cabeçalho ordenável. Só existe nas colunas que o servidor SABE ordenar
 * (`ORDENAVEIS` no controller: sku e name). Nas outras o `<th>` é comum — pôr seta
 * onde o clique não faz nada é o mesmo erro do toggle "Grade" que a gente tirou da v2.
 */
function ThOrdenavel({ col, ordem, dir, onOrdenar, className = '', children }: {
  col: string;
  ordem: string;
  dir: 'asc' | 'desc';
  onOrdenar: (col: string) => void;
  className?: string;
  children: ReactNode;
}) {
  const ativo = ordem === col;
  return (
    <th scope="col" className={`px-4 py-2.5 ${className}`} aria-sort={ativo ? (dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
      <button
        type="button"
        onClick={() => onOrdenar(col)}
        className="inline-flex items-center gap-1 uppercase tracking-wider font-semibold hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
      >
        {children}
        <ArrowUpDown size={11} className={ativo ? 'text-foreground' : 'opacity-40'} aria-hidden />
      </button>
    </th>
  );
}

function KpiFiltro({ label, sub, value, Icon, cor, ativo, onClick }: {
  label: string;
  sub: string;
  value: number;
  Icon: typeof Package;
  cor: string;
  ativo: boolean;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={ativo}
      className={`text-left rounded-md border p-3 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
        ativo ? 'border-primary bg-primary/5' : 'border-border bg-card hover:bg-muted/50'
      }`}
    >
      <Inline gap={2} align="center">
        <span className={`h-9 w-9 rounded-md grid place-items-center shrink-0 ${ativo ? 'bg-primary/15' : 'bg-muted'} ${cor}`}>
          <Icon className="h-4 w-4" />
        </span>
        <span className="flex-1 min-w-0">
          <span className="block text-[10px] font-semibold tracking-wider uppercase text-muted-foreground truncate leading-none">{label}</span>
          <span className="block text-lg font-semibold text-foreground tabular-nums leading-tight mt-1">{value.toLocaleString('pt-BR')}</span>
          <span className="block text-[10px] text-muted-foreground truncate leading-none mt-0.5">{sub}</span>
        </span>
      </Inline>
    </button>
  );
}

function Toolbar({ busca, setBusca, buscaRef, categorias, categoriaAtual, onCategoria, total, kpiLabel, onRemoverKpi, onLimpar }: {
  busca: string;
  setBusca: (v: string) => void;
  buscaRef: RefObject<HTMLInputElement | null>;
  categorias: CategoriaRow[];
  categoriaAtual: string;
  onCategoria: (c: string) => void;
  total: number;
  kpiLabel: string | null;
  onRemoverKpi: () => void;
  onLimpar: () => void;
}) {
  const temFiltro = Boolean(busca) || Boolean(categoriaAtual) || Boolean(kpiLabel);
  const nomeCategoria = categorias.find((c) => String(c.id) === String(categoriaAtual))?.label;

  return (
    <>
      <Inline gap={2} align="center" wrap className="mx-6 mt-3">
        <Select
          value={categoriaAtual ? String(categoriaAtual) : TODAS}
          onValueChange={(v) => onCategoria(v === TODAS ? '' : v)}
        >
          <SelectTrigger className="h-8 w-[180px] text-[12px]">
            <SelectValue placeholder="Categoria" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={TODAS}>Todas as categorias</SelectItem>
            {categorias.filter((c) => c.id).map((c) => (
              <SelectItem key={c.id} value={String(c.id)}>{c.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>

        <span className="text-[11px] text-muted-foreground tabular-nums pl-1">
          {total.toLocaleString('pt-BR')} {total === 1 ? 'registro' : 'registros'}
        </span>

        <div className="ml-auto relative w-[300px] min-w-[220px]">
          <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" aria-hidden />
          <Input
            ref={buscaRef}
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            placeholder="Buscar por nome ou SKU…   /"
            aria-label="Buscar produto por nome ou SKU"
            className="h-8 pl-8 text-[12px]"
          />
        </div>
      </Inline>

      {temFiltro && (
        <Inline gap={2} align="center" wrap className="mx-6 mt-2">
          {busca && <Chip label={`Busca: ${busca}`} onRemove={() => { setBusca(''); onCategoria(categoriaAtual); }} />}
          {categoriaAtual && <Chip label={`Categoria: ${nomeCategoria ?? categoriaAtual}`} onRemove={() => onCategoria('')} />}
          {kpiLabel && <Chip label={kpiLabel} onRemove={onRemoverKpi} />}
          <button
            type="button"
            onClick={onLimpar}
            className="h-6 px-1 text-[11px] text-muted-foreground underline underline-offset-2 hover:text-foreground"
          >
            limpar tudo
          </button>
        </Inline>
      )}
    </>
  );
}

function Chip({ label, onRemove }: { label: string; onRemove: () => void }) {
  return (
    <Inline gap={1} align="center" className="h-6 pl-2 pr-1 rounded border border-border bg-muted/40 text-[11px]">
      {label}
      <button type="button" onClick={onRemove} aria-label={`Remover filtro ${label}`} className="p-0.5 hover:text-destructive-fg">
        <X size={12} />
      </button>
    </Inline>
  );
}

/**
 * Rodapé de paginação no formato da referência: "Mostrando X–Y de N" à esquerda,
 * "Por página" + « ‹ pág/total › » à direita. Sem numerar páginas — a /contacts tem
 * 269 e a régua numerada não fecha em catálogo grande.
 */
function Paginacao({ meta, onPagina, onPorPagina }: {
  meta: NonNullable<Props['paginacao']>;
  onPagina: (p: number) => void;
  onPorPagina: (n: number) => void;
}) {
  const semAnterior = meta.pagina <= 1;
  const semProxima = meta.pagina >= meta.ultima;
  const btn = 'w-7 h-7 grid place-items-center rounded border border-border bg-card text-muted-foreground disabled:opacity-40 disabled:cursor-not-allowed hover:text-foreground';

  return (
    <Inline gap={3} align="center" className="mx-6 mt-3 mb-6">
      <span className="text-[11px] text-muted-foreground tabular-nums">
        Mostrando {(meta.de ?? 0).toLocaleString('pt-BR')}–{(meta.ate ?? 0).toLocaleString('pt-BR')} de {meta.total.toLocaleString('pt-BR')}
      </span>

      <Inline gap={2} align="center" className="ml-auto">
        <span className="text-[11px] text-muted-foreground">Por página</span>
        <Select value={String(meta.por_pagina)} onValueChange={(v) => onPorPagina(Number(v))}>
          <SelectTrigger className="h-7 w-[74px] text-[11px]"><SelectValue /></SelectTrigger>
          <SelectContent>
            {meta.opcoes.map((n) => <SelectItem key={n} value={String(n)}>{n}</SelectItem>)}
          </SelectContent>
        </Select>

        <Inline gap={1} align="center">
          <button type="button" className={btn} aria-label="Primeira página" disabled={semAnterior} onClick={() => onPagina(1)}><ChevronsLeft size={14} /></button>
          <button type="button" className={btn} aria-label="Página anterior" disabled={semAnterior} onClick={() => onPagina(meta.pagina - 1)}><ChevronLeft size={14} /></button>
          <span className="min-w-[62px] text-center text-[11px] font-medium tabular-nums">{meta.pagina} / {meta.ultima}</span>
          <button type="button" className={btn} aria-label="Próxima página" disabled={semProxima} onClick={() => onPagina(meta.pagina + 1)}><ChevronRight size={14} /></button>
          <button type="button" className={btn} aria-label="Última página" disabled={semProxima} onClick={() => onPagina(meta.ultima)}><ChevronsRight size={14} /></button>
        </Inline>
      </Inline>
    </Inline>
  );
}

function TabelaProdutos({ rows, tweaks, perm, onOpen, ordem, dir, onOrdenar }: {
  rows: ProdutoRow[]; tweaks: Tweaks; perm: Permissoes; onOpen: (r: ProdutoRow) => void;
  ordem: string; dir: 'asc' | 'desc'; onOrdenar: (col: string) => void;
}) {
  const rowH = tweaks.density === 'compact' ? 36 : tweaks.density === 'cozy' ? 56 : 44;
  // O switch "Mostrar custo" é preferência de exibição; `perm.custo` é direito. A coluna só
  // existe quando os DOIS valem — e a preferência nunca ressuscita o que a permissão negou.
  const mostrarCusto = perm.custo && tweaks.showCost;
  const onRowKey = (e: KeyboardEvent<HTMLTableRowElement>, r: ProdutoRow) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onOpen(r);
    }
  };
  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border shadow-sm overflow-hidden">
      <table className="w-full text-left text-sm">
        <thead className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">
          <tr className="border-b border-border bg-muted/40">
            <ThOrdenavel col="sku" ordem={ordem} dir={dir} onOrdenar={onOrdenar} className="w-20">SKU</ThOrdenavel>
            <ThOrdenavel col="name" ordem={ordem} dir={dir} onOrdenar={onOrdenar}>Produto</ThOrdenavel>
            <th scope="col" className="px-4 py-2.5 w-32">Categoria</th>
            {perm.preco && <th scope="col" className="px-4 py-2.5 w-24 text-right">Preço</th>}
            {mostrarCusto && <th scope="col" className="px-4 py-2.5 w-24 text-right">Custo · margem</th>}
            <th scope="col" className="px-4 py-2.5 w-24 text-right">Estoque</th>
            <th scope="col" className="px-4 py-2.5 w-20 text-right">30d</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr
              key={r.id}
              role="button"
              tabIndex={0}
              aria-label={`Abrir produto ${r.name}`}
              onClick={() => onOpen(r)}
              onKeyDown={(e) => onRowKey(e, r)}
              className="border-b border-border/60 hover:bg-muted/60 cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
              style={{ height: rowH }}
            >
              <td className="px-4 py-2.5 font-mono text-[11px] text-muted-foreground">{r.sku}</td>
              <td className="px-4 py-2.5 font-medium leading-tight">{r.name}</td>
              <td className="px-4 py-2.5 text-[11px] text-muted-foreground">{r.cat_label}</td>
              {perm.preco && (
                <td className="px-4 py-2.5 text-right font-semibold tabular-nums">
                  {r.price !== undefined ? fmtBRL(r.price) : null}
                </td>
              )}
              {mostrarCusto && (
                <td className="px-4 py-2.5 text-right text-[11px] tabular-nums">
                  {r.cost !== undefined && <div className="text-foreground">{fmtBRL(r.cost)}</div>}
                  {/* `margin` só vem quando o usuário pode ver custo E preço — ela deriva dos dois.
                      Sem preço, a linha de margem some e sobra o custo cru. */}
                  {r.margin !== undefined && (
                    <div className={r.margin >= 0.5 ? 'text-success-fg text-[11px]' : r.margin >= 0.3 ? 'text-muted-foreground text-[11px]' : 'text-destructive-fg text-[11px]'}>
                      {fmtPct(r.margin)}
                    </div>
                  )}
                </td>
              )}
              <td className="px-4 py-2.5 text-right text-foreground tabular-nums">
                {/* `stockQty` é `null` fixo no ProdutoUnificadoController (TODO: somar
                    variation_location_details.qty_available). Até existir a soma, a coluna
                    mostra "—" (desconhecido) — NUNCA 0, que seria afirmar estoque zerado.
                    Antes de 2026-08-07 o template imprimia o próprio null: "null UNID". */}
                {r.stockKind !== 'estoque'
                  ? 'sob demanda'
                  : r.stockQty === null
                    ? <span title="Quantidade em estoque ainda não calculada nesta tela">—</span>
                    : `${r.stockQty} ${r.unit}`}
              </td>
              <td className="pr-6 text-[12px] text-right tabular-nums">{r.uses30}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ListaCategorias({ rows }: { rows: CategoriaRow[] }) {
  return (
    <div className="mx-6 mt-3 grid grid-cols-3 gap-3">
      {rows.map((c) => (
        <div key={c.id} className="p-4 rounded-md bg-card border border-border">
          <div className="text-[14px] font-semibold">{c.label}</div>
          <div className="mt-1 text-[12px] text-muted-foreground">
            {c.count} {c.count === 1 ? 'produto' : 'produtos'}
          </div>
        </div>
      ))}
    </div>
  );
}

function ListaInsumos({ rows, perm }: { rows: InsumoRow[]; perm: Permissoes }) {
  // UC-PUNI-04 — sem módulo Manufacturing no pacote + `manufacturing.access_recipe`, o backend
  // devolve `[]`. Sem esta mensagem a sub-tela pareceria um catálogo de insumos vazio.
  if (!perm.composicao) {
    return <SubTelaSemPermissao texto="A composição (insumos e BOM) depende do módulo de Produção estar no plano do negócio e da permissão de acessar receitas." />;
  }

  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border shadow-sm overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 py-2">Insumo</th>
            <th scope="col" className="py-2 w-20">Unid.</th>
            {perm.custo && <th scope="col" className="py-2 w-28 text-right">Custo</th>}
            <th scope="col" className="py-2 w-24 text-right">Estoque</th>
            <th scope="col" className="pr-6 py-2 w-44">Fornecedor</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((i) => (
            <tr key={i.id} className="border-b border-border/60" style={{ height: 40 }}>
              <td className="pl-6 text-[13px] font-medium">{i.name}</td>
              <td className="text-[12px] text-muted-foreground">{i.unit}</td>
              {perm.custo && (
                <td className="text-[12px] text-right tabular-nums">
                  {i.cost !== undefined ? fmtBRL(i.cost) : null}
                </td>
              )}
              <td className="text-[12px] text-right tabular-nums">{i.stock}</td>
              <td className="pr-6 text-[12px] text-muted-foreground truncate">{i.fornecedor ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/**
 * Sub-tela que o usuário não pode ver. Diz QUE não pode e POR QUÊ — tabela vazia sem explicação
 * faz o operador achar que o cadastro está vazio e abrir chamado.
 */
function SubTelaSemPermissao({ texto }: { texto: string }) {
  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border p-6">
      <div className="text-[13px] font-medium text-foreground">Você não tem acesso a esta informação</div>
      <p className="mt-1.5 text-[12px] text-muted-foreground max-w-2xl">{texto}</p>
      <p className="mt-2 text-[11px] text-muted-foreground">Peça ao administrador do negócio pra revisar as permissões do seu papel.</p>
    </div>
  );
}

function ListaTabelas({ rows, produtos, perm }: { rows: TabelaRow[]; produtos: ProdutoRow[]; perm: Permissoes }) {
  // Hooks antes de qualquer return condicional (Rules of Hooks) — mesmo motivo do par de
  // hooks incondicionais do nome da empresa lá em cima.
  const [tableId, setTableId] = useState(rows[0]?.id ?? '');
  const cur = rows.find((t) => t.id === tableId);

  // UC-PUNI-03 — tabela de preço É preço de venda agrupado: mesmo dado, mesmo gate. O backend
  // devolve `[]`; sem esta mensagem a sub-tela pareceria "nenhuma tabela cadastrada".
  if (!perm.preco) {
    return <SubTelaSemPermissao texto="As tabelas de preço mostram o preço de venda agrupado por perfil de cliente — elas seguem a mesma permissão de ver preço de venda." />;
  }

  return (
    <div className="px-6 mt-3 space-y-4">
      <div className="grid grid-cols-4 gap-3">
        {rows.map((t) => {
          const active = t.id === tableId;
          return (
            <button
              key={t.id}
              type="button"
              aria-pressed={active}
              onClick={() => setTableId(t.id)}
              className={`text-left p-4 rounded-md border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${active ? 'bg-primary text-primary-foreground border-primary' : 'bg-card border-border hover:bg-muted/60'}`}
            >
              <div className={`text-[10px] uppercase tracking-widest ${active ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>Tabela</div>
              <div className="mt-1 text-[16px] font-semibold">{t.label}</div>
              <div className={`mt-1.5 text-[12px] ${active ? 'text-primary-foreground/80' : 'text-muted-foreground'}`}>{t.desc}</div>

            </button>
          );
        })}
      </div>
      {cur && (
        <div className="rounded-md bg-card border border-border overflow-hidden">
          <table className="w-full text-left">
            <thead className="text-[10px] uppercase tracking-widest text-muted-foreground font-medium">
              <tr className="border-b border-border bg-muted/40">
                <th scope="col" className="pl-6 py-2 w-20">SKU</th>
                <th scope="col" className="py-2">Produto</th>
                <th scope="col" className="py-2 w-28 text-right">Balcão</th>
                <th scope="col" className="py-2 w-28 text-right">Esta tabela</th>
                {/* Margem = (preço da tabela − custo) / preço da tabela. Sem direito ao custo,
                    a coluna some — calcular com `?? 0` imprimiria 100% e afirmaria custo zero. */}
                {perm.custo && <th scope="col" className="pr-6 py-2 w-24 text-right">Margem</th>}
              </tr>
            </thead>
            <tbody>
              {produtos.filter((p) => p.active).map((p) => {
                const base = p.price ?? 0;
                // D11 (decisão 2026-08-13): a sub-tela REDUZ A LEITURA até sair a ADR de schema.
                // O multiplicador não existe nativamente no UltimatePOS — o backend devolve
                // `mult` = 1.00 chumbado com TODO, então multiplicar aqui só ficava certo POR
                // ACIDENTE. Enquanto a decisão não sai, mostra o preço base e não inventa derivado.
                const tab = base;
                const m = p.cost !== undefined && tab > 0 ? (tab - p.cost) / tab : undefined;
                return (
                  <tr key={p.id} className="border-b border-border/60" style={{ height: 40 }}>
                    <td className="pl-6 font-mono text-[11px] text-muted-foreground">{p.sku}</td>
                    <td className="text-[13px] font-medium">{p.name}</td>
                    <td className="text-[12px] text-right text-muted-foreground tabular-nums">{p.price !== undefined ? fmtBRL(p.price) : null}</td>
                    <td className="text-[13px] text-right font-semibold tabular-nums">{p.price !== undefined ? fmtBRL(tab) : null}</td>
                    {perm.custo && (
                      <td className={`pr-6 text-[12px] text-right tabular-nums ${m === undefined ? '' : m >= 0.4 ? 'text-success-fg' : m >= 0.15 ? 'text-foreground' : 'text-destructive-fg'}`}>
                        {m !== undefined ? fmtPct(m) : null}
                      </td>
                    )}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function ListaHistorico({ rows, perm }: { rows: HistoricoRow[]; perm: Permissoes }) {
  return (
    <div className="mx-6 mt-3 rounded-md bg-card border border-border overflow-hidden">
      <table className="w-full text-left">
        <thead className="text-[10px] uppercase tracking-widest text-muted-foreground font-medium">
          <tr className="border-b border-border bg-muted/40">
            <th scope="col" className="pl-6 py-2 w-24">Data</th>
            <th scope="col" className="py-2 w-24">OS</th>
            <th scope="col" className="py-2">Produto</th>
            <th scope="col" className="py-2 w-44">Cliente</th>
            <th scope="col" className="py-2 w-16 text-right">Qtd</th>
            {/* UC-PUNI-02b — `value` é qty × preço unitário: a porta lateral do preço de venda. */}
            {perm.preco && <th scope="col" className="pr-6 py-2 w-28 text-right">Valor</th>}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, idx) => (
            <tr key={r.os + r.prodId + idx} className="border-b border-border/60" style={{ height: 36 }}>
              <td className="pl-6 text-[12px] tabular-nums">{r.date}</td>
              <td>
                <Badge variant="secondary" className="font-mono text-[11px] font-normal">{r.os}</Badge>
              </td>
              <td className="text-[12px] font-medium">{r.prodName}</td>
              <td className="text-[12px] text-muted-foreground">{r.client ?? '—'}</td>
              <td className="text-[12px] text-right tabular-nums">{r.qty}</td>
              {perm.preco && (
                <td className="pr-6 text-[12px] text-right font-medium tabular-nums">
                  {r.value !== undefined ? fmtBRL(r.value) : null}
                </td>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function TweaksPanel({ tweaks, setTweak, perm }: { tweaks: Tweaks; setTweak: (e: Partial<Tweaks>) => void; perm: Permissoes }) {
  return (
    <div className="fixed bottom-4 right-4 z-40">
      <Popover>
        <PopoverTrigger asChild>
          <button
            type="button"
            aria-label="Ajustes de exibição"
            className="inline-flex items-center gap-2 h-9 px-3 rounded-md bg-card border border-border shadow-lg text-[12px] font-medium text-foreground hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          >
            <SlidersHorizontal className="w-4 h-4" aria-hidden="true" />
            Ajustes
          </button>
        </PopoverTrigger>
        <PopoverContent align="end" side="top" className="w-64">
          <div className="text-[10px] uppercase tracking-widest text-muted-foreground font-medium mb-3">Ajustes</div>

          <div className="flex items-center justify-between mb-3 gap-3">
            <Label htmlFor="tweak-density" className="font-normal">Densidade</Label>
            <Select value={tweaks.density} onValueChange={(v) => setTweak({ density: v as Tweaks['density'] })}>
              <SelectTrigger id="tweak-density" className="h-8 w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="compact">Compacto</SelectItem>
                <SelectItem value="comfortable">Confortável</SelectItem>
                <SelectItem value="cozy">Espaçoso</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div className="flex items-center justify-between mb-3 gap-3">
            <Label htmlFor="tweak-view" className="font-normal">Visualização</Label>
            <Select value={tweaks.view} onValueChange={(v) => setTweak({ view: v as Tweaks['view'] })}>
              <SelectTrigger id="tweak-view" className="h-8 w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="table">Tabela</SelectItem>
                <SelectItem value="grid">Grade</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {/* O switch some pra quem não pode ver custo — oferecer um controle que não liga nada
              é dizer que a informação existe e o app está com defeito. */}
          {perm.custo && (
            <div className="flex items-center justify-between gap-3">
              <Label htmlFor="tweak-cost" className="font-normal">Mostrar custo</Label>
              <Switch
                id="tweak-cost"
                checked={tweaks.showCost}
                onCheckedChange={(v) => setTweak({ showCost: v })}
              />
            </div>
          )}
        </PopoverContent>
      </Popover>
    </div>
  );
}
