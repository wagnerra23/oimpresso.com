/**
 * Consulta de Produtos — índice do catálogo (`/products/unificado`).
 *
 * Origem: handoff "Consulta de Produtos" (2026-08-18), construído sob CATRACA DE REGRESSÃO
 * contra a Consulta de Clientes (`/contacts` · `Pages/Cliente/Index.tsx`), que é a golden
 * master do padrão de índice. A árvore é a mesma; só o domínio varia:
 *
 *   PageHeader → abas por TIPO → KPI-filtros → toolbar em UMA linha (filtros → contagem → busca)
 *   → cartão da tabela (altura fixa, rolagem interna, SEM rodapé) → drawer de detalhe
 *
 * As quatro regras da catraca (handoff §3):
 *   1. Sem CSS local de aparência — Tailwind + tokens do DS, como a golden master.
 *   2. A diferença é CONFIGURAÇÃO, não estilo — coluna que não aparece é coluna que não foi
 *      montada (`_components/Colunas.tsx`), nunca `display:none`.
 *   3. Sem paginação — o padrão de índice não tem rodapé; altura fixa + rolagem interna.
 *   4. Sem comportamento responsivo novo — o que existe é o do padrão.
 *
 * A regra que governa tudo: **custo e margem são AUTORIZAÇÃO, não preferência de layout.** O
 * vendedor não vê custo em superfície nenhuma — tabela, drawer ou contador de KPI. O servidor
 * nem emite a chave (`ProdutoUnificadoController`); a tela não a reintroduz.
 * Segunda invariante, do tenant: toda query escopada por `business_id` (ADR 0093).
 *
 * ⚠️ As 4 sub-telas anteriores (Categorias · Insumos·BOM · Tabelas de preço · Histórico de uso)
 * NÃO sumiram: saíram da barra de abas — que agora é do recorte por tipo — e passaram pro menu
 * de ações do cabeçalho. Mesmos gates, mesmo controller.
 */

import { Deferred, Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
  ArrowDown,
  ArrowUp,
  ArrowUpDown,
  ChevronDown,
  Download,
  History,
  Layers,
  MoreVertical,
  PackageSearch,
  Search,
  Tags,
  Upload,
  X,
} from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Button } from '@/Components/ui/button';
import { usePageProps, useBusiness } from '@/Hooks/usePageProps';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { ABAS_CATALOGO, type AbaKey, type KpiKey, type Permissoes, type ProdutoRow } from './_components/catalogo';
import { celulasDe, colunasDe, valorOrdenacao, type ColunaKey } from './_components/Colunas';
import KpiFiltros, { type KpisCatalogo } from './_components/KpiFiltros';
import FiltroTrigger, { type OpcaoFiltro } from './_components/FiltroTrigger';
import DetalheProduto from './_components/DetalheProduto';
import {
  ListaCategorias,
  ListaHistorico,
  ListaInsumos,
  ListaTabelas,
  type CategoriaRow,
  type HistoricoRow,
  type InsumoRow,
  type TabelaRow,
} from './_components/SubTelas';

type SubTela = 'produtos' | 'categorias' | 'insumos' | 'tabelas' | 'historico';

type Filtros = {
  tela: SubTela;
  aba: AbaKey;
  busca: string;
  kpi: string;
  categoria: number | null;
  tipo: string;
  unidade: number | null;
  marca: number | null;
  estoque: string;
  margem: string;
};

type Props = {
  tela: SubTela;
  filters: Filtros;
  /** Piso de margem vigente — PARÂMETRO DE NEGÓCIO servido pelo backend. A tela não o redeclara. */
  pisoMargem: number;
  diasParado: number;
  /** Teto de linhas por resposta. A tela DECLARA quando o recorte foi cortado. */
  tetoLinhas: number;
  // Opcional no TIPO porque o runtime pode não entregá-la (partial reload que não a peça);
  // o componente aplica default fail-closed. O backend sempre a envia no load completo.
  permissoes?: Permissoes;
  // Deferidas (Inertia::defer) — chegam no segundo round-trip.
  abas?: Record<AbaKey, number>;
  kpis?: KpisCatalogo;
  produtos?: ProdutoRow[];
  totalDaAba?: number;
  opcoesFiltro?: { categorias: OpcaoFiltro[]; unidades: OpcaoFiltro[]; marcas: OpcaoFiltro[] };
  categorias?: CategoriaRow[];
  insumos: InsumoRow[];
  tabelas: TabelaRow[];
  historico: HistoricoRow[];
};

const SUB_TELAS: ReadonlyArray<{ key: Exclude<SubTela, 'produtos'>; label: string; icon: typeof Tags }> = [
  { key: 'categorias', label: 'Categorias', icon: Tags },
  { key: 'insumos', label: 'Insumos · BOM', icon: Layers },
  { key: 'tabelas', label: 'Tabelas de preço', icon: PackageSearch },
  { key: 'historico', label: 'Histórico de uso', icon: History },
];

const TIPO_OPCOES: OpcaoFiltro[] = [
  { value: 'produto', label: 'Produto' },
  { value: 'servico', label: 'Serviço' },
  { value: 'materia', label: 'Matéria-prima' },
  { value: 'kit', label: 'Kit' },
];

const ESTOQUE_OPCOES: OpcaoFiltro[] = [
  { value: 'em', label: 'Em estoque' },
  { value: 'baixo', label: 'Estoque baixo' },
  { value: 'sem', label: 'Sem estoque' },
  { value: 'nao', label: 'Não estocável' },
];

const MARGEM_OPCOES: OpcaoFiltro[] = [
  { value: 'sob_piso', label: 'Sob o piso' },
  { value: 'ok', label: 'Acima do piso' },
];

function ProdutoUnificadoIndex({
  tela,
  filters,
  pisoMargem,
  diasParado,
  tetoLinhas,
  // Fail-closed: se a prop não chegar por qualquer caminho, esconde tudo em vez de
  // estourar `undefined.custo`. Ausência de permissão declarada nunca vira permissão.
  permissoes = { custo: false, preco: false, composicao: false },
  abas,
  kpis,
  produtos,
  totalDaAba,
  opcoesFiltro,
  categorias,
  insumos,
  tabelas,
  historico,
}: Props) {
  // Nome da empresa: MESMA fonte que o AppShellV2 usa na sidebar
  // (`shell.cockpit.businessNome`), que sai de uma query em `App\Business`. O `business.name`
  // do shared prop vem da SESSÃO e chega VAZIO em ambiente de teste, então serve só de
  // fallback. Sem default inventado — imprimir 'Oimpresso' aqui afirmaria um tenant que não é
  // o do usuário (Tier 0, ADR 0093). Os DOIS hooks são chamados incondicionalmente: `a ?? b`
  // não avalia `b` quando `a` tem valor, e hook em avaliação condicional quebra as Rules of Hooks.
  const shell = usePageProps().shell as ({ cockpit?: { businessNome?: string } } | undefined);
  const nomeDoShell = shell?.cockpit?.businessNome ?? null;
  const nomeDaSessao = useBusiness()?.name ?? null;
  const businessName = nomeDoShell ?? nomeDaSessao;

  const [busca, setBusca] = useState(filters.busca);
  const [abertoId, setAbertoId] = useState<number | null>(null);
  const [ordem, setOrdem] = useState<{ key: ColunaKey; dir: 'asc' | 'desc' } | null>(null);
  const [maisFiltros, setMaisFiltros] = useState(false);
  const buscaRef = useRef<HTMLInputElement>(null);

  /** Navega preservando o resto do recorte. `only` diz quais props re-rodam no servidor. */
  const irPara = (patch: Partial<Filtros>, only: string[]) =>
    router.get(route('products.unificado.index'), { ...filters, ...patch }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only,
    });

  const RECORTE = ['filters', 'produtos', 'kpis', 'totalDaAba'];

  // Busca debounced — o filtro é resolvido no SERVIDOR, junto com aba e KPI, num único `where`.
  useEffect(() => {
    if (busca === filters.busca) return;
    const t = setTimeout(() => irPara({ busca }, RECORTE), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busca]);

  useEffect(() => setBusca(filters.busca), [filters.busca]);

  // `/` foca a busca — sem roubar a tecla de quem está digitando.
  useEffect(() => {
    const onKey = (ev: KeyboardEvent) => {
      const alvo = ev.target as HTMLElement | null;
      const digitando = !!alvo && /^(INPUT|TEXTAREA|SELECT)$/.test(alvo.tagName);
      if (ev.key === '/' && !digitando) {
        ev.preventDefault();
        buscaRef.current?.focus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, []);

  // Memoizado porque `produtos` é prop deferida: sem isto, o `?? []` cria um array novo a cada
  // render e invalida os useMemo abaixo sempre.
  const linhas = useMemo(() => produtos ?? [], [produtos]);

  // DERIVADOS — nunca em estado.
  const mostraTipo = useMemo(() => new Set(linhas.map((r) => r.tipo)).size > 1, [linhas]);
  const colunas = useMemo(() => colunasDe({ perm: permissoes, mostraTipo }), [permissoes, mostraTipo]);
  const ordenadas = useMemo(() => {
    if (!ordem) return linhas;
    const fator = ordem.dir === 'asc' ? 1 : -1;
    return [...linhas].sort((a, b) => {
      const va = valorOrdenacao(a, ordem.key);
      const vb = valorOrdenacao(b, ordem.key);
      if (va === vb) return 0;
      return va > vb ? fator : -fator;
    });
  }, [linhas, ordem]);

  const produtoAberto = abertoId === null ? null : linhas.find((r) => r.id === abertoId) ?? null;
  const total = totalDaAba ?? linhas.length;
  const cortou = total > linhas.length && linhas.length >= tetoLinhas;

  const temFiltro = !!(filters.categoria || filters.unidade || filters.marca || filters.tipo || filters.estoque || filters.margem);

  // Abaixo de 780px de LARGURA DISPONÍVEL os gatilhos opcionais somem e voltam pelo
  // "Mais filtros" — comportamento do pacote 17/08 (`.f-opt` / `.f-more`).
  const opcionalCls = maisFiltros ? '' : '@max-[780px]:hidden';

  const trocarOrdem = (key: ColunaKey) =>
    setOrdem((o) => (o?.key === key ? { key, dir: o.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: 'asc' }));

  return (
    <>
      <Head title="Produtos · Catálogo" />

      <div className="flex-1 bg-page-cream py-4">
        <div className="w-full px-6 space-y-2">
          {/* ───── BLOCO 1 · CABEÇALHO ───────────────────────────────────────── */}
          <header className="border-b overflow-visible" role="banner" style={{ borderBottomColor: 'var(--border)' }}>
            <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
              <div className="flex-1 min-w-0">
                <h1 className="text-[22px] font-bold tracking-tight text-foreground leading-snug">Produtos</h1>
                {/* Subtítulo conta o CADASTRO INTEIRO, não a aba (handoff §4.1). */}
                <p className="text-xs text-muted-foreground mt-0.5 tabular-nums">
                  <strong className="text-foreground font-semibold">
                    {(abas?.todos ?? 0).toLocaleString('pt-BR')}
                  </strong>{' '}
                  cadastrados
                  {businessName ? ` · ${businessName}` : ''}
                </p>
              </div>

              <div className="flex-shrink-0 flex items-center gap-1.5">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Mais ações" title="Outras visões do catálogo" className="h-8 w-8 border-0">
                      <MoreVertical className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-56">
                    {/* As 4 sub-telas que antes eram abas. Continuam servidas pelo mesmo
                        controller, com os mesmos gates de permissão. */}
                    <div className="px-2 pt-2 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Outras visões
                    </div>
                    {SUB_TELAS.map(({ key, label, icon: Icon }) => (
                      <DropdownMenuItem
                        key={key}
                        onSelect={() => irPara({ tela: key }, ['tela', 'filters', 'categorias', 'insumos', 'tabelas', 'historico'])}
                      >
                        <Icon className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} />
                        {label}
                      </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <div className="px-2 pt-1 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Dados
                    </div>
                    <DropdownMenuItem asChild>
                      <a href="/import-products">
                        <Upload className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} />
                        Importar
                      </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                      <a href="/products/download-excel">
                        <Download className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} />
                        Exportar planilha
                      </a>
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <Button size="sm" onClick={() => router.visit('/products/create')}>
                  Novo produto
                </Button>
              </div>
            </div>

            {/* Abas por TIPO — trocam tabela, contagem E os KPIs (handoff §4.2). */}
            {tela === 'produtos' && (
              <div className="px-6">
                <nav className="flex items-center gap-1 -mb-px overflow-x-auto" aria-label="Recorte por tipo de item">
                  {ABAS_CATALOGO.map((a) => {
                    const ativa = filters.aba === a.key;
                    return (
                      <button
                        key={a.key}
                        type="button"
                        role="tab"
                        aria-selected={ativa}
                        onClick={() => irPara({ aba: a.key, kpi: '' }, RECORTE)}
                        className={
                          'inline-flex items-center gap-1.5 h-[46px] px-3 text-[12.5px] whitespace-nowrap border-b-2 transition-colors ' +
                          (ativa
                            ? 'border-primary text-foreground font-medium'
                            : 'border-transparent text-muted-foreground hover:text-foreground')
                        }
                      >
                        {a.label}
                        <span className="font-mono text-[11px] tabular-nums opacity-70">
                          {(abas?.[a.key] ?? 0).toLocaleString('pt-BR')}
                        </span>
                      </button>
                    );
                  })}
                </nav>
              </div>
            )}
          </header>

          {tela === 'produtos' ? (
            <>
              {/* ───── BLOCO 2 · KPI-FILTROS ─────────────────────────────────── */}
              <Deferred data="kpis" fallback={<EsqueletoKpis />}>
                <KpiFiltros
                  kpis={kpis ?? { ativos: 0, min: 0, zero: 0, parado: 0, total: 0 }}
                  ativo={(filters.kpi || null) as KpiKey | null}
                  // "Itens listados" é o total da aba — selecioná-lo é o mesmo que não ter
                  // recorte nenhum, então ele LIMPA em vez de aplicar um filtro que não filtra.
                  onToggle={(k) => irPara({ kpi: !k || k === 'total' ? '' : k }, RECORTE)}
                  perm={permissoes}
                  diasParado={diasParado}
                />
              </Deferred>

              {/* ───── BLOCO 3 · TOOLBAR EM UMA LINHA ──────────────────────
                  Composicao do pacote 17/08 (`.fbar`): gatilhos de filtro -> contagem de
                  registros -> busca ocupando o resto da linha, que quebra sozinha quando
                  nao cabe.

                  Isto REVERTE a composicao de duas linhas que o pacote de 18/08 tinha posto
                  no lugar (D-03/D-04). Decisao [M] 2026-08-18: a 17/08 e a oficial.

                  O container e `@container` porque o alvo mede a LARGURA DISPONIVEL, nao a da
                  janela: com a sidebar aberta ou fechada a mesma janela da larguras diferentes,
                  e e a barra que precisa decidir se cabe. */}
              <div className="@container mt-4 flex items-center flex-wrap gap-2">
                <FiltroTrigger
                  label="Categoria"
                  value={filters.categoria ? String(filters.categoria) : ''}
                  options={opcoesFiltro?.categorias ?? []}
                  onChange={(v) => irPara({ categoria: v ? Number(v) : null }, RECORTE)}
                />
                <FiltroTrigger
                  label="Tipo"
                  value={filters.tipo}
                  options={TIPO_OPCOES}
                  onChange={(v) => irPara({ tipo: v }, RECORTE)}
                />

                {/* Gatilhos opcionais: somem abaixo de 780px e voltam pelo "Mais filtros".
                    Categoria e Tipo nunca somem — sao os dois que o alvo mantem sempre. */}
                <div className={opcionalCls}>
                  <FiltroTrigger
                    label="Unidade"
                    value={filters.unidade ? String(filters.unidade) : ''}
                    options={opcoesFiltro?.unidades ?? []}
                    onChange={(v) => irPara({ unidade: v ? Number(v) : null }, RECORTE)}
                  />
                </div>
                {/* Marca FICA — decisao [M] 2026-08-18. O pacote pede "Fornecedor", que o
                    UltimatePOS nao guarda no produto (so por compra); Marca e o atributo que
                    o produto carrega e que o balcao ja usa pra procurar. */}
                <div className={opcionalCls}>
                  <FiltroTrigger
                    label="Marca"
                    value={filters.marca ? String(filters.marca) : ''}
                    options={opcoesFiltro?.marcas ?? []}
                    onChange={(v) => irPara({ marca: v ? Number(v) : null }, RECORTE)}
                  />
                </div>
                <div className={opcionalCls}>
                  <FiltroTrigger
                    label="Estoque"
                    value={filters.estoque}
                    options={ESTOQUE_OPCOES}
                    onChange={(v) => irPara({ estoque: v }, RECORTE)}
                  />
                </div>
                {/* Recorte por margem e leitura da estrutura de custo — some pra quem nao
                    pode ver custo, igual a coluna e ao card de KPI. */}
                {permissoes.custo && permissoes.preco && (
                  <div className={opcionalCls}>
                    <FiltroTrigger
                      label="Margem"
                      value={filters.margem}
                      options={MARGEM_OPCOES}
                      onChange={(v) => irPara({ margem: v }, RECORTE)}
                    />
                  </div>
                )}

                {/* "Mais filtros" so existe na largura em que os opcionais sumiram — e ABRE os
                    mesmos gatilhos, em vez de ser um botao decorativo. */}
                <button
                  type="button"
                  onClick={() => setMaisFiltros((v) => !v)}
                  aria-expanded={maisFiltros}
                  className={
                    'hidden @max-[780px]:inline-flex items-center gap-1.5 h-[30px] px-[11px] rounded-lg border text-xs transition-colors ' +
                    (maisFiltros
                      ? 'border-primary/40 bg-primary/10 text-primary'
                      : 'border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted/60')
                  }
                >
                  Mais filtros
                  <ChevronDown size={12} className="opacity-60" />
                </button>

                {/* Contagem — sans 12px no alvo, nao mono. Quando o teto corta, a tela DIZ que
                    cortou: contador que discorda da lista destroi a confianca. */}
                <span className="text-[12px] leading-[12px] text-muted-foreground whitespace-nowrap">
                  {cortou
                    ? `${linhas.length.toLocaleString('pt-BR')} de ${total.toLocaleString('pt-BR')} registros`
                    : `${total.toLocaleString('pt-BR')} ${total === 1 ? 'registro' : 'registros'}`}
                </span>

                {/* A busca fecha a linha e ocupa o que sobrar (`flex:1 1 160px` no alvo). */}
                <label className="flex-[1_1_160px] min-w-[150px] ml-2 flex items-center gap-2.5 h-[38px] px-[13px] rounded-[10px] border border-border bg-card">
                  <Search className="h-[15px] w-[15px] text-muted-foreground shrink-0" />
                  <input
                    ref={buscaRef}
                    type="search"
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    aria-label="Buscar produtos"
                    placeholder="Buscar descricao, codigo, referencia, categoria…"
                    className="flex-1 min-w-0 border-0 outline-none bg-transparent text-[13px] text-foreground placeholder:text-muted-foreground"
                  />
                  {/* So aparece com texto digitado — na tela em repouso a barra fica identica
                      ao alvo, e quem digitou ganha o atalho de limpar. */}
                  {busca && (
                    <button type="button" onClick={() => setBusca('')} aria-label="Limpar busca" className="text-muted-foreground hover:text-foreground shrink-0">
                      <X className="h-4 w-4" />
                    </button>
                  )}
                </label>
              </div>

              {cortou && (
                <p className="text-[11.5px] text-muted-foreground">
                  Mostrando os {tetoLinhas.toLocaleString('pt-BR')} primeiros — refine a busca ou os filtros pra alcançar o resto.
                </p>
              )}

              {/* ───── BLOCO 4 · CARTÃO DA TABELA ────────────────────────────── */}
              {/* Altura fixa + rolagem interna: é o que substitui a paginação (SPEC D-06). */}
              {/* raio 12 (`rounded-lg` é o teto do DS) + sombra pela utilitária `shadow-sm`,
                  a mesma do cartão da golden master — sombra crua inline quebra o dark. */}
              <div className="mt-3 rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                <Deferred data="produtos" fallback={<EsqueletoTabela />}>
                  <div className="overflow-auto" style={{ height: 460 }}>
                    <table className="w-full text-left">
                      <thead className="sticky top-0 z-10 bg-muted/60 backdrop-blur">
                        <tr className="border-b border-border">
                          {colunas.map((c) => (
                            <th
                              key={c.key}
                              scope="col"
                              style={{ width: c.width }}
                              className={
                                'px-4 py-2 text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium ' +
                                (c.align === 'right' ? 'text-right' : '')
                              }
                            >
                              {c.sortable ? (
                                <button
                                  type="button"
                                  onClick={() => trocarOrdem(c.key)}
                                  className="inline-flex items-center gap-1 hover:text-foreground transition-colors uppercase tracking-widest"
                                >
                                  {c.label}
                                  {ordem?.key === c.key
                                    ? (ordem.dir === 'asc' ? <ArrowUp size={11} /> : <ArrowDown size={11} />)
                                    : <ArrowUpDown size={11} className="opacity-40" />}
                                </button>
                              ) : (
                                c.label || <span className="sr-only">Ações</span>
                              )}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {ordenadas.length === 0 ? (
                          <tr>
                            <td colSpan={colunas.length} className="text-center py-16">
                              {/* Estado vazio explícito — o protótipo não tinha (pendência §14
                                  item 6). Tabela vazia sem explicação faz o operador achar que
                                  o cadastro está vazio e abrir chamado. */}
                              <p className="text-[13px] text-foreground font-medium">Nenhum item neste recorte</p>
                              <p className="mt-1 text-[12px] text-muted-foreground">
                                {busca || temFiltro || filters.kpi
                                  ? 'Tente limpar a busca, o filtro ou o cartão selecionado acima.'
                                  : 'Esta aba não tem itens cadastrados.'}
                              </p>
                            </td>
                          </tr>
                        ) : (
                          ordenadas.map((r) => {
                            const cells = celulasDe(r, {
                              perm: permissoes,
                              mostraTipo,
                              piso: pisoMargem,
                              onAcao: (e) => e.stopPropagation(),
                            });
                            const aberta = abertoId === r.id;
                            return (
                              <tr
                                key={r.id}
                                role="button"
                                tabIndex={0}
                                aria-label={`Abrir detalhe de ${r.name}`}
                                onClick={() => setAbertoId(r.id)}
                                onKeyDown={(e) => {
                                  if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    setAbertoId(r.id);
                                  }
                                }}
                                className={
                                  'border-b border-border/60 cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring ' +
                                  (aberta ? 'bg-primary/10' : 'hover:bg-muted/40') +
                                  // Estado de linha: urgente quando zerado, arquivada quando inativa.
                                  (r.stockQty === 0 ? ' bg-destructive-soft/30' : r.active ? '' : ' opacity-60')
                                }
                              >
                                {colunas.map((c) => (
                                  <td key={c.key} className={'px-4 py-2 ' + (c.align === 'right' ? 'text-right' : '')}>
                                    {cells[c.key] ?? null}
                                  </td>
                                ))}
                              </tr>
                            );
                          })
                        )}
                      </tbody>
                    </table>
                  </div>
                </Deferred>
              </div>
            </>
          ) : (
            <SubTelaSecundaria
              tela={tela}
              perm={permissoes}
              categorias={categorias ?? []}
              insumos={insumos}
              tabelas={tabelas}
              historico={historico}
              produtos={linhas}
              onVoltar={() => irPara({ tela: 'produtos' }, ['tela', 'filters', 'produtos', 'kpis', 'totalDaAba'])}
            />
          )}
        </div>
      </div>

      <DetalheProduto produto={produtoAberto} perm={permissoes} piso={pisoMargem} onFechar={() => setAbertoId(null)} />
    </>
  );
}

ProdutoUnificadoIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Produto — Catálogo" breadcrumbItems={[{ label: 'Produto', href: '/products' }, { label: 'Catálogo' }]}>
    {page}
  </AppShellV2>
);

export default ProdutoUnificadoIndex;

/* ─── Subcomponentes locais ───────────────────────────────────────────── */

function EsqueletoKpis() {
  return (
    <div className="grid gap-[9px] grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
      {Array.from({ length: 6 }).map((_, i) => (
        <div key={i} className="h-[66px] rounded-md border border-border bg-card animate-pulse" />
      ))}
    </div>
  );
}

function EsqueletoTabela() {
  return (
    <div className="p-4 space-y-2" style={{ height: 460 }}>
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="h-9 rounded bg-muted animate-pulse" />
      ))}
    </div>
  );
}

function SubTelaSecundaria({
  tela, perm, categorias, insumos, tabelas, historico, produtos, onVoltar,
}: {
  tela: Exclude<SubTela, 'produtos'>;
  perm: Permissoes;
  categorias: CategoriaRow[];
  insumos: InsumoRow[];
  tabelas: TabelaRow[];
  historico: HistoricoRow[];
  produtos: ProdutoRow[];
  onVoltar: () => void;
}) {
  const titulo = SUB_TELAS.find((s) => s.key === tela)?.label ?? '';
  return (
    <section className="pt-4 space-y-3">
      <div className="flex items-center gap-3">
        <h2 className="text-[15px] font-semibold text-foreground">{titulo}</h2>
        <button type="button" onClick={onVoltar} className="text-[12px] text-muted-foreground underline-offset-2 hover:underline hover:text-foreground">
          voltar ao catálogo
        </button>
      </div>
      {tela === 'categorias' && <ListaCategorias rows={categorias} />}
      {tela === 'insumos' && <ListaInsumos rows={insumos} perm={perm} />}
      {tela === 'tabelas' && <ListaTabelas rows={tabelas} produtos={produtos} perm={perm} />}
      {tela === 'historico' && <ListaHistorico rows={historico} perm={perm} />}
    </section>
  );
}
