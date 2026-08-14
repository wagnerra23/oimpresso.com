import { Head, router, Deferred } from '@inertiajs/react';
import { usePageProps, useBusiness } from '@/Hooks/usePageProps';
import { useState, useCallback, useEffect, useRef } from 'react';
import type { ReactNode } from 'react';
import { Sheet, SheetContent } from '@/Components/ui/sheet';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import {
  DsAlert, DsButton, DsDataTable, DsDrawerSection, DsDropdownMenu, DsEmptyState,
  DsFilterChip, DsInput, DsKpiFilterCard, DsSelect, DsSkeleton, DsToast,
} from '../_components/ds';
import type { DsColumn, DsKpiTone, DsMenuItem, DsRow } from '../_components/ds';
// 4 níveis até `resources/` (Unificado → Produto → Pages → js → resources).
// `tsc` NÃO confere import de CSS — quem pega errado aqui é só o build do Vite.
import '../../../../css/produto-catalogo.css';

/**
 * Catálogo Unificado · módulo Produto.
 * 5 sub-telas numa rota: produtos | categorias | insumos | tabelas | historico.
 *
 * FONTE DE DESIGN: `prototipo-ui/cowork/prototipos/produto/produto-unificado-v2.dc.html`
 * (charter `related_prototype`). Esta tela é porte 1:1 dele — hierarquia, escala
 * tipográfica, bordas e raios saem de lá, não de escolha local. Medidas literais
 * (`radius 12`, `gap 12`, `margin '12px 24px 0'`) são intencionais: o protótipo é a
 * régua e traduzi-las pra escala Tailwind perderia a fidelidade que [M] pediu em
 * 2026-08-14. Comparativo + medição de tokens:
 * `memory/requisitos/Produto/_telas/produto-unificado-visual-comparison.md`.
 *
 * Camadas (ADR UI-0013): Fundações (tokens DS v6 — idênticos aos do protótipo, 0
 * divergências medidas) → Shell (AppShellV2, que já aplica `.cockpit`) → PT-01 Lista.
 */

type Tela = 'produtos' | 'categorias' | 'insumos' | 'tabelas' | 'historico';

type Props = {
  tela: Tela;
  filters: {
    tela: string;
    busca: string;
    categoria: string;
    /** 'ativo' | 'inativo' | '' — o menu Situação do protótipo. */
    situacao: string;
    /** 'estoque' | 'demanda' | '' — o menu Estoque do protótipo. */
    estoque: string;
    densidade: 'compact' | 'normal' | 'comfy';
    pagina: number;
    por_pagina: number;
    /** Recorte do KPI clicado. O mesmo predicado que contou o card filtra a lista. */
    kpi: string | null;
    ordem: string;
    dir: 'asc' | 'desc';
  };
  /** Contadores das 5 abas — o badge do TabBar. */
  contagens?: Record<Tela, number>;
  /**
   * Meta da paginação. Opcional no TIPO porque partial reload que não a peça não a traz —
   * o rodapé some em vez de estourar.
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
  // o componente aplica default fail-closed.
  permissoes?: Permissoes;
};

/**
 * Quem pode ver o quê (UC-PUNI-01..04 · Index.casos.md). O backend NÃO EMITE a chave do dado
 * que o usuário não pode ver — `price`, `cost`, `margin`, `value` e `bomCount` são opcionais
 * de propósito. Esta tela usa os flags pra remover a COLUNA INTEIRA, nunca pra imprimir 0/—
 * no lugar do valor: zero é uma afirmação sobre o custo, e o contrato é ausência
 * (AR-PROD-015 — "os campos somem da tela").
 *
 * ⚠️ Não é decoração: `brl(undefined)` lança TypeError e derruba a página inteira.
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
type CategoriaRow = { id: number; slug: string; label: string; count: number; ativos: number };
type InsumoRow = { id: number; codigo: string; name: string; unit: string; cost?: number; stock: number; fornecedor: string | null };
type TabelaRow = { id: string; label: string; desc: string; vinculados: number; upd: string };
type HistoricoRow = { os: string; date: string; prodId: string; prodName: string; cat: string | null; unit: string; client: string | null; qty: number; value?: number };

const brl = (n: number) => 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pct = (n: number) => Math.round(n * 100) + '%';

/**
 * Nome de produto de gráfica identifica pelo FIM (medida e cores). Se precisar
 * cortar, corta o MEIO — nunca a ponta que diferencia "9×5cm 4×4" de "8×4cm 1×0".
 * Verbatim do protótipo (`meio()`); truncar à direita perderia justamente o que
 * distingue dois itens do catálogo.
 */
const meio = (txt: string, max: number) => {
  if (txt.length <= max) return txt;
  const fim = Math.min(14, Math.floor(max / 2));
  return txt.slice(0, max - fim - 1).trimEnd() + '…' + txt.slice(-fim).trimStart();
};

/* ─── Ícones (paths verbatim do protótipo · stroke 1.8) ───────────────────── */

const ico = (d: string[], size = 16) => (
  <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor"
       strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden>
    {d.map((p, i) => <path key={i} d={p} />)}
  </svg>
);

const ICONES = {
  caixa: ['M21 8v8a2 2 0 0 1-1 1.73l-7 4a2 2 0 0 1-2 0l-7-4A2 2 0 0 1 3 16V8a2 2 0 0 1 1-1.73l7-4a2 2 0 0 1 2 0l7 4A2 2 0 0 1 21 8z', 'm3.3 7 8.7 5 8.7-5'],
  estrela: ['m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9L12 3z'],
  parado: ['M12 3a9 9 0 1 0 9 9', 'M12 7v5l3 2'],
  margem: ['M3 17 9 11l4 4 8-8', 'M21 7v6h-6'],
  relogio: ['M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z', 'M12 7v5l3 2'],
  lista: ['M8 6h13', 'M8 12h13', 'M8 18h13', 'M3 6h.01', 'M3 12h.01', 'M3 18h.01'],
  pasta: ['M4 20h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-7.5l-2-2H4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2z'],
  frasco: ['M9 3h6', 'M10 3v6L5.5 17A2 2 0 0 0 7.2 20h9.6a2 2 0 0 0 1.7-3L14 9V3'],
  etiqueta: ['M20.6 13.4 12 22l-9-9V4a1 1 0 0 1 1-1h9l7.6 7.6a2 2 0 0 1 0 2.8z', 'M7.5 7.5h.01'],
  historico: ['M3 12a9 9 0 1 0 3-6.7', 'M3 4v4h4', 'M12 8v4l3 2'],
  teclado: ['M2 6h20v12H2z', 'M6 10h.01', 'M10 10h.01', 'M14 10h.01', 'M18 10h.01', 'M8 14h8'],
  kebab: ['M12 5h.01', 'M12 12h.01', 'M12 19h.01'],
  importar: ['M12 15V3', 'M7 8l5-5 5 5', 'M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
  exportar: ['M12 3v12', 'M7 10l5 5 5-5', 'M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2'],
  mais: ['M12 5v14', 'M5 12h14'],
  busca: ['M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z', 'm21 21-4.3-4.3'],
};

const ATALHOS = [
  { acao: 'Navegar entre linhas', tecla: 'J / K' },
  { acao: 'Abrir a ficha da linha', tecla: '↵' },
  { acao: 'Focar a busca', tecla: '/' },
  { acao: 'Novo produto', tecla: 'N' },
  { acao: 'Esta lista', tecla: '?' },
  { acao: 'Fechar ficha ou lista', tecla: 'Esc' },
];

/**
 * Os 5 recortes do strip. `chave` é o mesmo valor que o controller espera em `?kpi=` —
 * mudar aqui sem mudar o `aplicarRecorte()` quebra o par card↔lista.
 * `tone` e `icon` saem do protótipo (o "margem" é violet, não primary).
 */
const KPI_CARDS = [
  { chave: 'ativos', prop: 'catalogo_ativo' as const, label: 'Catálogo ativo', sub: 'à venda hoje', tone: 'primary' as DsKpiTone, d: ICONES.caixa },
  { chave: 'populares', prop: 'populares' as const, label: 'Populares · 30d', sub: '30+ saídas', tone: 'amber' as DsKpiTone, d: ICONES.estrela },
  { chave: 'semgiro', prop: 'sem_giro' as const, label: 'Sem giro', sub: '0 saídas em 30d', tone: 'rose' as DsKpiTone, d: ICONES.parado },
  { chave: 'margem', prop: 'margem_baixa' as const, label: 'Margem baixa', sub: 'abaixo de 30%', tone: 'violet' as DsKpiTone, d: ICONES.margem },
  { chave: 'demanda', prop: 'sob_demanda' as const, label: 'Sob demanda', sub: 'sem estoque próprio', tone: 'emerald' as DsKpiTone, d: ICONES.relogio },
];

/**
 * `icone` é NOME lucide (kebab-case) porque é isso que o `PageHeaderTabs` resolve.
 * Não é perda de fidelidade: os paths do `ICONES` do protótipo SÃO os glifos lucide
 * correspondentes — `M8 6h13 M8 12h13 M8 18h13 M3 6h.01…` é o `list`, o frasco é o
 * `flask-conical`, e assim por diante. Mesmo desenho, resolvido por nome.
 */
const ABAS: { key: Tela; label: string; icone: string }[] = [
  { key: 'produtos', label: 'Produtos', icone: 'list' },
  { key: 'categorias', label: 'Categorias', icone: 'folder' },
  { key: 'insumos', label: 'Insumos · BOM', icone: 'flask-conical' },
  { key: 'tabelas', label: 'Tabelas de preço', icone: 'tag' },
  { key: 'historico', label: 'Histórico de uso', icone: 'history' },
];

const ROTULO_KPI: Record<string, string> = {
  ativos: 'catálogo ativo', populares: 'populares', semgiro: 'sem giro',
  margem: 'margem baixa', demanda: 'sob demanda',
};

function ProdutoUnificadoIndex({
  tela, filters, kpis, produtos = [], categorias, insumos, tabelas, historico, paginacao, contagens,
  // Fail-closed: se a prop não chegar por qualquer caminho, esconde tudo em vez de
  // estourar `undefined.custo`. Ausência de permissão declarada nunca vira permissão.
  permissoes = { custo: false, preco: false, composicao: false },
}: Props) {
  // Nome da empresa: MESMA fonte que o AppShellV2 usa na sidebar (`shell.cockpit.businessNome`),
  // que sai de uma query em `App\Business`. O `business.name` do shared prop vem da SESSÃO e
  // chega VAZIO em ambiente de teste, então serve só de fallback. Sem default inventado —
  // imprimir 'Oimpresso' aqui afirmaria um tenant que não é o do usuário.
  // Os DOIS hooks são chamados incondicionalmente: `a ?? b` não avalia `b` quando `a` tem
  // valor, e hook em avaliação condicional quebra as Rules of Hooks.
  // Os DOIS hooks são chamados INCONDICIONALMENTE de propósito: `a ?? b` não avalia `b`
  // quando `a` tem valor, e hook dentro de avaliação condicional quebra as Rules of Hooks.
  const shell = usePageProps().shell as ({ cockpit?: { businessNome?: string } } | undefined);
  const nomeDoShell = shell?.cockpit?.businessNome ?? null;
  const nomeDaSessao = useBusiness()?.name ?? null;
  const businessName = nomeDoShell ?? nomeDaSessao;

  const [busca, setBusca] = useState(filters.busca ?? '');
  const [linhaAberta, setLinhaAberta] = useState<ProdutoRow | null>(null);
  const [cursor, setCursor] = useState<number | null>(null);
  const [ajuda, setAjuda] = useState(false);
  const [toast, setToast] = useState<string | null>(null);
  const buscaRef = useRef<HTMLInputElement>(null);
  const primeiraRenderizacao = useRef(true);
  const toastTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  const aviso = useCallback((msg: string) => {
    if (toastTimer.current) clearTimeout(toastTimer.current);
    setToast(msg);
    toastTimer.current = setTimeout(() => setToast(null), 2600);
  }, []);
  useEffect(() => () => { if (toastTimer.current) clearTimeout(toastTimer.current); }, []);

  /**
   * Navega preservando os demais filtros. Qualquer mudança que altere o CONJUNTO
   * (busca, categoria, situação, estoque, aba, por_pagina) volta pra página 1 — senão o
   * usuário filtra estando na página 7 e cai numa lista vazia sem entender por quê.
   */
  const irPara = useCallback((patch: Record<string, unknown>, resetarPagina = true) => {
    router.get(
      route('products.unificado.index'),
      { ...filters, ...patch, ...(resetarPagina ? { pagina: 1 } : {}) },
      { preserveState: true, preserveScroll: true, replace: true, only: ['produtos', 'paginacao', 'filters', 'contagens'] },
    );
  }, [filters]);

  // Debounce da busca: 350ms sem digitar. Sem isso, cada tecla vira um request.
  useEffect(() => {
    if (primeiraRenderizacao.current) { primeiraRenderizacao.current = false; return; }
    if (busca === (filters.busca ?? '')) return;
    const t = setTimeout(() => irPara({ busca }), 350);
    return () => clearTimeout(t);
  }, [busca, filters.busca, irPara]);

  const setSubTela = (t: Tela) =>
    // D-14: partial reload — só re-busca o que muda com a sub-tela.
    // kpis/produtos/categorias são closures no controller (não mudam com `tela`) — pulam.
    router.get(route('products.unificado.index'), { ...filters, tela: t, pagina: 1 }, {
      preserveState: true, preserveScroll: true, replace: true,
      only: ['tela', 'filters', 'insumos', 'tabelas', 'historico', 'contagens'],
    });

  const novo = useCallback(() => aviso('Cadastro abre em rota própria — /products/create'), [aviso]);
  const limparTudo = useCallback(() => {
    setBusca('');
    irPara({ busca: '', categoria: '', situacao: '', estoque: '', kpi: '' });
  }, [irPara]);

  /* Atalhos do protótipo: J/K · ↵ · / · N · ? · Esc. */
  useEffect(() => {
    const onKey = (e: globalThis.KeyboardEvent) => {
      const alvo = e.target as HTMLElement | null;
      const digitando = !!alvo && (alvo.tagName === 'INPUT' || alvo.tagName === 'TEXTAREA' || alvo.tagName === 'SELECT' || alvo.isContentEditable);

      if (e.key === 'Escape') {
        if (ajuda) { setAjuda(false); return; }
        if (linhaAberta) { setLinhaAberta(null); return; }
        if (digitando && busca) { setBusca(''); }
        return;
      }
      if (digitando) return;

      if (e.key === '/') { e.preventDefault(); buscaRef.current?.focus(); return; }
      if (e.key === '?') { e.preventDefault(); setAjuda((a) => !a); return; }
      if (e.key === 'n' || e.key === 'N') { e.preventDefault(); novo(); return; }
      if (tela !== 'produtos' || produtos.length === 0) return;

      const desce = e.key === 'j' || e.key === 'J' || e.key === 'ArrowDown';
      const sobe = e.key === 'k' || e.key === 'K' || e.key === 'ArrowUp';
      if (desce || sobe) {
        e.preventDefault();
        setCursor((c) => {
          const n = c === null ? 0 : Math.min(produtos.length - 1, Math.max(0, c + (desce ? 1 : -1)));
          // Se a ficha está aberta, J/K troca o produto exibido junto com o cursor.
          if (linhaAberta) setLinhaAberta(produtos[n] ?? null);
          return n;
        });
        return;
      }
      if (e.key === 'Enter' && cursor !== null) {
        e.preventDefault();
        const alvoLinha = produtos[cursor];
        if (alvoLinha) setLinhaAberta(alvoLinha);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [ajuda, linhaAberta, busca, tela, produtos, cursor, novo]);

  const total = paginacao?.total ?? produtos.length;
  const nomeCategoria = categorias.find((c) => String(c.id) === String(filters.categoria))?.label;

  const chips: { id: string; label: string; valor: string; limpar: Record<string, string> }[] = [];
  if (filters.kpi) chips.push({ id: 'kpi', label: 'Recorte', valor: ROTULO_KPI[filters.kpi] ?? filters.kpi, limpar: { kpi: '' } });
  if (filters.categoria) chips.push({ id: 'cat', label: 'Categoria', valor: nomeCategoria ?? filters.categoria, limpar: { categoria: '' } });
  if (filters.situacao) chips.push({ id: 'sit', label: 'Situação', valor: filters.situacao === 'ativo' ? 'à venda' : 'fora de venda', limpar: { situacao: '' } });
  if (filters.estoque) chips.push({ id: 'est', label: 'Estoque', valor: filters.estoque === 'estoque' ? 'em estoque' : 'sob demanda', limpar: { estoque: '' } });
  if (busca.trim()) chips.push({ id: 'q', label: 'Busca', valor: '“' + busca.trim() + '”', limpar: { busca: '' } });

  const onSort = (col: string) =>
    irPara({ ordem: col, dir: filters.ordem === col && filters.dir === 'asc' ? 'desc' : 'asc' }, false);

  return (
    <>
      <Head title="Catálogo · Produto" />
      {/* `.cat-v2` é o escopo do CSS de tela (rampa --fs-*, régua da tabela, cursor J/K).
          font-size 12 / line-height 1.45 / --font-sans replicam a raiz do protótipo. */}
      <div className="cat-v2" style={{
        display: 'flex', flexDirection: 'column', minHeight: '100%',
        background: 'var(--bg)', color: 'var(--text)',
        fontFamily: 'var(--font-sans)', fontSize: 12, lineHeight: 1.45,
      }}>
        <div style={{ position: 'sticky', top: 0, zIndex: 20, background: 'var(--bg)', padding: '0 24px', borderBottom: '1px solid var(--border)' }}>
          <PageHeader
            title="Catálogo"
            subtitle={
              <>
                {/* `kpis` é deferida: no 1º render ela não existe. Sem o guard, esta linha
                    derruba a página antes de a lista sequer chegar. */}
                <strong>{kpis ? kpis.catalogo_ativo.toLocaleString('pt-BR') : '…'}</strong>{' produtos · '}
                <strong>{categorias.length}</strong>{' categorias · '}
                <strong style={{ color: 'var(--color-destructive)' }}>{kpis ? kpis.sem_giro.toLocaleString('pt-BR') : '…'}</strong>{' sem giro'}
                {businessName ? ' · ' + businessName : ''}
              </>
            }
            actions={<AcoesDoCabecalho onNovo={novo} onCategorias={() => setSubTela('categorias')} aviso={aviso} />}
          />
          {/* TabBar do protótipo = `PageHeaderTabs` canon (mesmo underline `var(--accent)`,
              mesma pill `accent-soft 50%`, mesmo badge mono 10.5px — fidelidade travada por
              `tests/pageHeaderTabsFidelity.spec.tsx`). Os ícones do protótipo SÃO os glifos
              lucide correspondentes, então o nome resolve o mesmo desenho.
              `href` real preserva abrir-em-nova-aba; `onGhostChange` intercepta com o
              partial reload, que é o caminho rápido. */}
          <div className="barra">
            <PageHeaderTabs
              ghosts={ABAS.map((a) => ({
                key: a.key,
                label: a.label,
                href: `/products/unificado?tela=${a.key}`,
                icon: a.icone,
                badge: contagens?.[a.key],
              }))}
              activeGhostKey={tela}
              onGhostChange={(k) => setSubTela(k as Tela)}
            />
          </div>
        </div>

        {/* KPI strip — 5 colunas FIXAS (o protótipo usa repeat(5,1fr), não auto-fit:
            com auto-fit os cards mudam de largura conforme o texto e a régua desalinha). */}
        <Deferred data="kpis" fallback={<SkeletonKpis />}>
          <div className="kpis" style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 12, margin: '12px 24px 0' }}>
            {KPI_CARDS.map((k) => (
              <DsKpiFilterCard
                key={k.chave}
                label={k.label}
                sub={k.sub}
                tone={k.tone}
                icon={ico(k.d, 17)}
                value={(kpis?.[k.prop] ?? 0).toLocaleString('pt-BR')}
                selected={filters.kpi === k.chave}
                onClick={() => irPara({ kpi: filters.kpi === k.chave ? '' : k.chave, categoria: '', situacao: '', estoque: '', tela: 'produtos' })}
              />
            ))}
          </div>
        </Deferred>

        {/* Toolbar — só na sub-tela Produtos, a única paginada/filtrável */}
        {tela === 'produtos' && (
          <>
            <div style={{ margin: '12px 24px 0', display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, minWidth: 0 }}>
                <MenuFiltro
                  rotulo={filters.categoria ? 'Categoria · ' + (nomeCategoria ?? filters.categoria) : 'Categoria'}
                  ativo={!!filters.categoria}
                  itens={[
                    { id: 'todas', label: 'Todas as categorias', onSelect: () => irPara({ categoria: '' }) },
                    { id: 'sep', label: '', separator: true },
                    ...categorias.filter((c) => c.id).map((c) => ({
                      id: String(c.id), label: c.label, onSelect: () => irPara({ categoria: String(c.id) }),
                    })),
                  ]}
                />
                <MenuFiltro
                  rotulo={filters.situacao ? 'Situação · ' + (filters.situacao === 'ativo' ? 'à venda' : 'fora de venda') : 'Situação'}
                  ativo={!!filters.situacao}
                  itens={[
                    { id: 'todas', label: 'Qualquer situação', onSelect: () => irPara({ situacao: '' }) },
                    { id: 'ativo', label: 'À venda', onSelect: () => irPara({ situacao: 'ativo' }) },
                    { id: 'inativo', label: 'Fora de venda', onSelect: () => irPara({ situacao: 'inativo' }) },
                  ]}
                />
                <MenuFiltro
                  rotulo={filters.estoque ? 'Estoque · ' + (filters.estoque === 'estoque' ? 'em estoque' : 'sob demanda') : 'Estoque'}
                  ativo={!!filters.estoque}
                  itens={[
                    { id: 'todos', label: 'Qualquer origem', onSelect: () => irPara({ estoque: '' }) },
                    { id: 'estoque', label: 'Em estoque', onSelect: () => irPara({ estoque: 'estoque' }) },
                    { id: 'demanda', label: 'Sob demanda', onSelect: () => irPara({ estoque: 'demanda' }) },
                  ]}
                />
                <span style={{ fontSize: 11, color: 'var(--text-mute)', fontVariantNumeric: 'tabular-nums', paddingLeft: 4 }}>
                  {total.toLocaleString('pt-BR')} {total === 1 ? 'registro' : 'registros'}
                </span>
              </div>
              <div style={{ marginLeft: 'auto', flex: '0 1 300px', minWidth: 220 }}>
                <DsInput
                  value={busca}
                  onChange={setBusca}
                  inputRef={buscaRef}
                  ariaLabel="Buscar produto por nome ou SKU"
                  placeholder="Buscar por nome ou SKU…   /"
                />
              </div>
            </div>

            {chips.length > 0 && (
              <div style={{ margin: '8px 24px 0', display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                {chips.map((c) => (
                  <DsFilterChip
                    key={c.id}
                    label={c.label}
                    value={c.valor}
                    onRemove={() => { if (c.id === 'q') setBusca(''); irPara(c.limpar); }}
                  />
                ))}
                <button
                  type="button"
                  onClick={limparTudo}
                  onMouseEnter={(e) => { e.currentTarget.style.color = 'var(--text)'; }}
                  onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--text-dim)'; }}
                  style={{
                    height: 24, padding: '0 4px', border: 0, background: 'transparent', color: 'var(--text-dim)',
                    fontFamily: 'var(--font-sans)', fontSize: 11, textDecoration: 'underline',
                    textUnderlineOffset: 3, cursor: 'pointer',
                  }}
                >limpar tudo</button>
              </div>
            )}
          </>
        )}

        <div style={{ flex: 1, minHeight: 0, padding: '12px 24px 24px' }}>
          {tela === 'produtos' && (
            /* `produtos`/`paginacao` são deferidas: o <Deferred> segura este bloco com
               skeleton enquanto elas não chegam. Sem ele a tela pintaria "Nenhum produto
               cadastrado ainda" por meio segundo — dizendo ao usuário que o catálogo está
               vazio quando na verdade ainda está carregando. */
            <Deferred data={['produtos', 'paginacao']} fallback={<SkeletonTabela />}>
              <SubTelaProdutos
                rows={produtos}
                perm={permissoes}
                densidade={filters.densidade}
                cursorId={cursor === null ? null : produtos[cursor]?.id ?? null}
                abertoId={linhaAberta?.id ?? null}
                temFiltro={chips.length > 0}
                ordem={filters.ordem}
                dir={filters.dir}
                onSort={onSort}
                onOpen={setLinhaAberta}
                onLimpar={limparTudo}
                onNovo={novo}
                paginacao={paginacao}
                onPagina={(p) => irPara({ pagina: p }, false)}
                onPorPagina={(n) => irPara({ por_pagina: n })}
                onAjuda={() => setAjuda(true)}
              />
            </Deferred>
          )}
          {tela === 'categorias' && <SubTelaSimples colunas={COL_CATEGORIAS} rows={categorias.map((c) => ({
            id: c.id, cells: { label: c.label, total: String(c.count), ativos: String(c.ativos) },
          }))} />}
          {tela === 'insumos' && (
            permissoes.composicao
              ? <SubTelaSimples colunas={colInsumos(permissoes)} rows={insumos.map((i) => ({
                  id: i.id,
                  cells: {
                    codigo: i.codigo, name: i.name, unit: i.unit,
                    ...(permissoes.custo ? { cost: i.cost !== undefined ? brl(i.cost) : '' } : {}),
                    stock: i.stock.toLocaleString('pt-BR'), forn: i.fornecedor ?? '—',
                  },
                }))} />
              : <SemPermissao texto="A composição (insumos e BOM) depende do módulo de Produção estar no plano do negócio e da permissão de acessar receitas." />
          )}
          {tela === 'tabelas' && (
            permissoes.preco
              ? (
                <div style={{ border: '1px solid var(--border)', borderRadius: 12, background: 'var(--surface)', boxShadow: 'var(--sh-1)', overflow: 'hidden' }}>
                  {/* A decisão D11 (2026-08-13) reduziu a leitura desta sub-tela até sair a ADR
                      de schema do multiplicador. Sem este aviso, a decisão vive só em comentário
                      de código e a tela cala o motivo. */}
                  <div style={{ padding: '12px 12px 0' }}>
                    <DsAlert tone="info" title="Multiplicador aguarda decisão de schema">
                      Enquanto a ADR não sai, esta sub-tela lista as tabelas cadastradas sem calcular preço derivado.
                    </DsAlert>
                  </div>
                  <div style={{ overflowX: 'auto' }}>
                    <DsDataTable
                      columns={COL_TABELAS}
                      rows={tabelas.map((t) => ({
                        id: t.id, cells: { label: t.label, desc: t.desc, vinculados: String(t.vinculados), upd: t.upd },
                      }))}
                    />
                  </div>
                </div>
              )
              : <SemPermissao texto="As tabelas de preço mostram o preço de venda agrupado por perfil de cliente — elas seguem a mesma permissão de ver preço de venda." />
          )}
          {tela === 'historico' && <SubTelaSimples colunas={colHistorico(permissoes)} rows={historico.map((r, i) => ({
            id: r.os + r.prodId + i,
            cells: {
              data: r.date, os: r.os, prod: r.prodName, cliente: r.client ?? '—', qtd: String(r.qty),
              ...(permissoes.preco ? { valor: r.value !== undefined ? brl(r.value) : '' } : {}),
            },
          }))} />}
        </div>

        <DrawerProduto row={linhaAberta} perm={permissoes} onClose={() => setLinhaAberta(null)} aviso={aviso} />

        {ajuda && <ModalAtalhos onClose={() => setAjuda(false)} />}

        {toast && (
          <div style={{ position: 'fixed', left: '50%', bottom: 28, transform: 'translateX(-50%)', zIndex: 80 }}>
            <DsToast>{toast}</DsToast>
          </div>
        )}
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

/* ─── Subcomponentes ──────────────────────────────────────────────────────── */

/** Kebab (Importar · Exportar · Categorias · Etiquetas) + botão primário Novo produto. */
function AcoesDoCabecalho({ onNovo, onCategorias, aviso }: {
  onNovo: () => void; onCategorias: () => void; aviso: (m: string) => void;
}) {
  return (
    <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
      <DsDropdownMenu
        align="end"
        width={216}
        trigger={({ onClick }) => (
          <button
            type="button"
            onClick={onClick}
            aria-label="Mais ações"
            style={{
              width: 30, height: 30, display: 'grid', placeItems: 'center', border: 0, borderRadius: 6,
              background: 'transparent', color: 'var(--text-dim)', cursor: 'pointer',
            }}
          >{ico(ICONES.kebab, 16)}</button>
        )}
        items={[
          { id: 'g1', label: 'DADOS', disabled: true },
          { id: 'import', label: 'Importar', icon: ico(ICONES.importar, 14), onSelect: () => aviso('Importação abre em rota própria — /products/import') },
          { id: 'csv', label: 'Exportar CSV', icon: ico(ICONES.exportar, 14), onSelect: () => aviso('CSV do recorte atual gerado') },
          { id: 'sep', label: '', separator: true },
          { id: 'g2', label: 'CONFIGURAÇÃO', disabled: true },
          { id: 'cats', label: 'Categorias de produto', icon: ico(ICONES.pasta, 14), onSelect: onCategorias },
          { id: 'etiquetas', label: 'Imprimir etiquetas', icon: ico(ICONES.etiqueta, 14), onSelect: () => aviso('Etiquetas abrem em rota própria') },
        ]}
      />
      <DsButton variant="primary" size="sm" onClick={onNovo} kbd="N">
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>{ico(ICONES.mais, 14)}Novo produto</span>
      </DsButton>
    </span>
  );
}

/** Gatilho de filtro do protótipo: pill accent quando ativo, texto puro quando não. */
function MenuFiltro({ rotulo, ativo, itens }: { rotulo: string; ativo: boolean; itens: DsMenuItem[] }) {
  return (
    <DsDropdownMenu
      align="start"
      width={220}
      items={itens}
      trigger={({ onClick }) => (
        <button
          type="button"
          onClick={onClick}
          style={{
            display: 'inline-flex', alignItems: 'center', gap: 6, height: 32,
            padding: ativo ? '0 11px' : '0 8px',
            border: ativo ? '1px solid var(--accent)' : '1px solid transparent',
            borderRadius: 6,
            background: ativo ? 'var(--accent-soft)' : 'transparent',
            color: ativo ? 'var(--accent)' : 'var(--text-dim)',
            font: '500 12px/1 var(--font-sans)', cursor: 'pointer', whiteSpace: 'nowrap',
          }}
        >
          {rotulo}
          <span aria-hidden style={{ opacity: 0.55, fontSize: 10 }}>▾</span>
        </button>
      )}
    />
  );
}

const COL_CATEGORIAS: DsColumn[] = [
  { key: 'label', label: 'Categoria' },
  { key: 'total', label: 'Produtos', align: 'right', width: 120 },
  { key: 'ativos', label: 'À venda', align: 'right', width: 120 },
];
const COL_TABELAS: DsColumn[] = [
  { key: 'label', label: 'Tabela', width: 160 },
  { key: 'desc', label: 'Descrição' },
  { key: 'vinculados', label: 'Produtos', align: 'right', width: 120 },
  { key: 'upd', label: 'Atualizada', align: 'right', width: 140 },
];
const colInsumos = (perm: Permissoes): DsColumn[] => [
  { key: 'codigo', label: 'Código', mono: true, width: 90 },
  { key: 'name', label: 'Insumo' },
  { key: 'unit', label: 'Unidade', width: 110 },
  // UC-PUNI-01: sem direito ao custo, a COLUNA some — não vira célula vazia.
  ...(perm.custo ? [{ key: 'cost', label: 'Custo', align: 'right' as const, width: 120 }] : []),
  { key: 'stock', label: 'Estoque', align: 'right', width: 110 },
  { key: 'forn', label: 'Fornecedor', width: 200 },
];
const colHistorico = (perm: Permissoes): DsColumn[] => [
  { key: 'data', label: 'Data', mono: true, width: 110 },
  { key: 'os', label: 'OS', mono: true, width: 110 },
  { key: 'prod', label: 'Produto' },
  { key: 'cliente', label: 'Cliente', width: 200 },
  { key: 'qtd', label: 'Qtd', align: 'right', width: 80 },
  // UC-PUNI-02b: `value` é qty × preço unitário — a porta lateral do preço de venda.
  ...(perm.preco ? [{ key: 'valor', label: 'Valor', align: 'right' as const, width: 120 }] : []),
];

/** Card + tabela DS, sem paginação — o desenho das 4 sub-telas secundárias. */
function SubTelaSimples({ colunas, rows }: { colunas: DsColumn[]; rows: DsRow[] }) {
  return (
    <div style={{ border: '1px solid var(--border)', borderRadius: 12, background: 'var(--surface)', boxShadow: 'var(--sh-1)', overflow: 'hidden' }}>
      <div style={{ overflowX: 'auto' }}><DsDataTable columns={colunas} rows={rows} /></div>
    </div>
  );
}

/**
 * Sub-tela que o usuário não pode ver. Diz QUE não pode e POR QUÊ — tabela vazia sem
 * explicação faz o operador achar que o cadastro está vazio e abrir chamado.
 * Usa a variante `no-perm` do DS (âmbar), não um card cinza hand-rolado.
 */
function SemPermissao({ texto }: { texto: string }) {
  return (
    <DsEmptyState
      variant="no-perm"
      icon={ico(['M19 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2z', 'M7 11V7a5 5 0 0 1 10 0v4'], 18)}
      title="Você não tem acesso a esta informação"
      description={texto + ' Peça ao administrador do negócio pra revisar as permissões do seu papel.'}
    />
  );
}

function SubTelaProdutos({
  rows, perm, densidade, cursorId, abertoId, temFiltro, ordem, dir,
  onSort, onOpen, onLimpar, onNovo, paginacao, onPagina, onPorPagina, onAjuda,
}: {
  rows: ProdutoRow[]; perm: Permissoes; densidade: Props['filters']['densidade'];
  cursorId: number | null; abertoId: number | null; temFiltro: boolean;
  ordem: string; dir: 'asc' | 'desc';
  onSort: (c: string) => void; onOpen: (r: ProdutoRow) => void;
  onLimpar: () => void; onNovo: () => void;
  paginacao?: Props['paginacao'];
  onPagina: (p: number) => void; onPorPagina: (n: number) => void; onAjuda: () => void;
}) {
  if (rows.length === 0) {
    return temFiltro ? (
      <DsEmptyState
        variant="no-results"
        icon={ico(ICONES.busca, 18)}
        title="Nenhum produto encontrado nesse filtro"
        description="Nada bate com a busca e os filtros ativos. Tente outro termo ou limpe os filtros."
        action={<DsButton variant="ghost" size="sm" onClick={onLimpar}>Limpar tudo</DsButton>}
      />
    ) : (
      <DsEmptyState
        variant="first"
        icon={ico(ICONES.caixa, 18)}
        title="Nenhum produto cadastrado ainda"
        description="O catálogo começa vazio. Cadastre o primeiro produto ou importe a planilha que você já usa."
        action={<DsButton variant="primary" size="sm" onClick={onNovo}>Cadastrar primeiro produto</DsButton>}
      />
    );
  }

  const colunas: DsColumn[] = [
    { key: 'sku', label: 'SKU', mono: true, width: 78, sortable: true },
    { key: 'produto', label: 'Produto', sortable: true, width: 250 },
    { key: 'cat', label: 'Categoria', width: 140 },
    { key: 'situacao', label: 'Situação', width: 110 },
    // UC-PUNI-02 / UC-PUNI-01 — a coluna só existe se o dado puder ser visto.
    ...(perm.preco ? [{ key: 'preco', label: 'Preço', align: 'right' as const, width: 102, sortable: true }] : []),
    ...(perm.custo ? [{ key: 'custo', label: 'Custo/margem', align: 'right' as const, width: 146, sortable: true }] : []),
    { key: 'estoque', label: 'Estoque', align: 'right', width: 132 },
    { key: 'uses', label: '30d', align: 'right', width: 70, sortable: true },
  ];

  const linhas: DsRow[] = rows.map((p) => {
    return {
      id: p.id,
      state: p.active ? undefined : 'archived',
      cells: {
        sku: p.sku,
        produto: { primary: meio(p.name, 72), sub: p.updated ? 'atualizado ' + p.updated : '' },
        cat: <span style={{ fontSize: 12, color: 'var(--text-dim)' }}>{p.cat_label ?? '—'}</span>,
        situacao: (
          <span style={{
            display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 11, whiteSpace: 'nowrap',
            color: p.active ? 'var(--pos)' : 'var(--text-mute)',
          }}>
            <span style={{ width: 6, height: 6, borderRadius: 99, background: p.active ? 'var(--pos)' : 'var(--text-mute)' }} />
            {p.active ? 'à venda' : 'fora de venda'}
          </span>
        ),
        ...(perm.preco ? { preco: p.price !== undefined ? brl(p.price) : '' } : {}),
        ...(perm.custo ? { custo: <CelulaCusto cost={p.cost} margin={p.margin} /> } : {}),
        estoque: (
          <span style={{ fontSize: 12, color: 'var(--text-dim)' }}>
            {p.stockKind === 'sob_demanda'
              ? 'sob demanda'
              /* `—` = desconhecido. Imprimir 0 afirmaria estoque zerado. */
              : p.stockQty === null
                ? <span title="Quantidade em estoque ainda não calculada nesta tela">—</span>
                : p.stockQty.toLocaleString('pt-BR') + ' ' + p.unit}
          </span>
        ),
        uses: String(p.uses30),
      },
    };
  });

  return (
    <>
      <div style={{ border: '1px solid var(--border)', borderRadius: 12, background: 'var(--surface)', boxShadow: 'var(--sh-1)', overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto' }}>
          <DsDataTable
            columns={colunas}
            rows={linhas}
            onRowClick={(r) => {
              const alvo = rows.find((x) => x.id === r.id);
              if (alvo) onOpen(alvo);
            }}
            sortKey={ordem}
            sortDir={dir}
            onSort={onSort}
            densidade={densidade}
            focoId={abertoId ?? cursorId}
          />
        </div>
      </div>
      {paginacao && paginacao.total > 0 && (
        <Paginacao meta={paginacao} onPagina={onPagina} onPorPagina={onPorPagina} onAjuda={onAjuda} />
      )}
    </>
  );
}

function CelulaCusto({ cost, margin }: { cost?: number; margin?: number }) {
  const cor = margin === undefined ? 'var(--text-dim)'
    : margin >= 0.5 ? 'var(--pos)' : margin >= 0.3 ? 'var(--text-dim)' : 'var(--neg)';
  return (
    <span>
      {cost !== undefined && <span style={{ display: 'block', fontVariantNumeric: 'tabular-nums' }}>{brl(cost)}</span>}
      {/* `margin` só vem quando o usuário pode ver custo E preço — ela deriva dos dois.
          Sem preço, a linha de margem some e sobra o custo cru. */}
      {margin !== undefined && (
        <span style={{ display: 'block', fontSize: 11, color: cor, fontVariantNumeric: 'tabular-nums' }}>{pct(margin)}</span>
      )}
    </span>
  );
}

/**
 * Rodapé: [⌨] [?] "Mostrando X–Y de N" … "Por página" ‹select› « ‹ n/total › ».
 * NÃO numera páginas — com catálogo grande a régua numerada não fecha.
 */
function Paginacao({ meta, onPagina, onPorPagina, onAjuda }: {
  meta: NonNullable<Props['paginacao']>;
  onPagina: (p: number) => void; onPorPagina: (n: number) => void; onAjuda: () => void;
}) {
  const semAnterior = meta.pagina <= 1;
  const semProxima = meta.pagina >= meta.ultima;
  const navBtn = {
    width: 28, height: 28, display: 'grid', placeItems: 'center',
    border: '1px solid var(--border)', borderRadius: 6, background: 'var(--surface)',
    color: 'var(--text-dim)', font: '500 11px/1 var(--font-mono)', cursor: 'pointer',
  } as const;
  const ajudaBtn = {
    width: 24, height: 24, display: 'grid', placeItems: 'center',
    border: '1px solid var(--border)', borderRadius: 6, background: 'var(--surface)',
    color: 'var(--text-mute)', cursor: 'pointer',
  } as const;

  return (
    <div className="pag" style={{ marginTop: 12, display: 'flex', alignItems: 'center', gap: 10 }}>
      <button type="button" onClick={onAjuda} aria-label="Atalhos de teclado" style={ajudaBtn}>{ico(ICONES.teclado, 13)}</button>
      <button type="button" onClick={onAjuda} aria-label="Todos os atalhos" style={{ ...ajudaBtn, font: '600 11px/1 var(--font-mono)' }}>?</button>
      <span style={{ fontSize: 11, color: 'var(--text-dim)', fontVariantNumeric: 'tabular-nums' }}>
        {meta.total === 0
          ? 'Nenhum item'
          : `Mostrando ${(meta.de ?? 0).toLocaleString('pt-BR')}–${(meta.ate ?? 0).toLocaleString('pt-BR')} de ${meta.total.toLocaleString('pt-BR')}`}
      </span>
      <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
        <span style={{ fontSize: 11, color: 'var(--text-mute)' }}>Por página</span>
        <div style={{ width: 78 }}>
          <DsSelect
            value={String(meta.por_pagina)}
            ariaLabel="Itens por página"
            onChange={(v) => onPorPagina(Number(v))}
            options={meta.opcoes.map((n) => ({ value: String(n), label: String(n) }))}
            style={{ padding: '5px 10px', paddingRight: 24 }}
          />
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 3 }}>
          <button type="button" aria-label="Primeira página" disabled={semAnterior} onClick={() => onPagina(1)} style={navBtn}>«</button>
          <button type="button" aria-label="Página anterior" disabled={semAnterior} onClick={() => onPagina(meta.pagina - 1)} style={navBtn}>‹</button>
          <span style={{ minWidth: 62, textAlign: 'center', font: '500 11px/1 var(--font-mono)', color: 'var(--text)', fontVariantNumeric: 'tabular-nums' }}>
            {meta.pagina} / {meta.ultima}
          </span>
          <button type="button" aria-label="Próxima página" disabled={semProxima} onClick={() => onPagina(meta.pagina + 1)} style={navBtn}>›</button>
          <button type="button" aria-label="Última página" disabled={semProxima} onClick={() => onPagina(meta.ultima)} style={navBtn}>»</button>
        </div>
      </div>
    </div>
  );
}

/**
 * Ficha do produto — 760px, o canon de entidade cadastral (ADR 0185 / ADR 0179).
 * Em 1280 (monitor da Larissa): 760 + 260 do shell = 1020, cabe sem scroll horizontal.
 *
 * Abre com o que a LINHA já tem — não dispara request novo. Os dados do drawer são os
 * mesmos que a lista carregou; buscar de novo o que está na mão seria latência sem ganho.
 *
 * O `SheetContent` recebe `className="cat-v2"` de propósito: o Radix renderiza em PORTAL,
 * fora da árvore da tela, e sem o wrapper a rampa `--fs-*` e as regras de tabela não
 * chegam aqui (lápide §5 2026-07-10 — token consumido dentro de portal precisa do wrapper
 * reaplicado no `<*Content>`).
 *
 * O que NÃO tem aqui, e por quê: o protótipo desenha "Composição (BOM)" e "Consumo em OS".
 * O controller devolve `bomCount` = 0 literal (TODO) e não serve histórico por produto.
 * Desenhar as seções vazias afirmaria "este produto não tem composição" — que é diferente
 * de "ainda não sabemos". Elas entram quando o dado entrar.
 */
function DrawerProduto({ row, perm, onClose, aviso }: {
  row: ProdutoRow | null; perm: Permissoes; onClose: () => void; aviso: (m: string) => void;
}) {
  return (
    <Sheet open={row !== null} onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent
        side="right"
        className="cat-v2 w-[760px] sm:max-w-[760px] p-0 flex flex-col"
        style={{ background: 'var(--surface)', borderLeft: '1px solid var(--border)' }}
      >
        {row && (
          <>
            <div style={{ padding: '14px 18px', display: 'flex', alignItems: 'center', gap: 10, borderBottom: '1px solid var(--border)' }}>
              <span style={{ fontFamily: 'var(--font-mono)', fontSize: 12.5, color: 'var(--text-mute)' }}>{row.sku}</span>
              <span style={{
                display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 11,
                color: row.active ? 'var(--pos)' : 'var(--text-mute)',
              }}>
                <span style={{ width: 6, height: 6, borderRadius: 99, background: row.active ? 'var(--pos)' : 'var(--text-mute)' }} />
                {row.active ? 'à venda' : 'fora de venda'}
              </span>
            </div>
            <div style={{ padding: '16px 18px' }}>
              <h3 style={{ margin: 0, font: '600 17px/1.3 var(--font-sans)', letterSpacing: '-.01em', color: 'var(--text)' }}>
                {row.name}
              </h3>
              <p style={{ margin: '4px 0 0', fontSize: 12.5, color: 'var(--text-dim)' }}>
                {(row.cat_label ?? 'Sem categoria') + ' · preço por ' + row.unit + (row.updated ? ' · atualizado ' + row.updated : '')}
              </p>
            </div>

            <div style={{ flex: 1, overflowY: 'auto' }}>
              {(perm.preco || perm.custo) && (
                <DsDrawerSection title="Preço e margem">
                  <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 12 }}>
                    {perm.preco && <FichaNumero label="Preço" valor={row.price !== undefined ? brl(row.price) : '—'} sub={'por ' + row.unit} />}
                    {perm.custo && <FichaNumero label="Custo" valor={row.cost !== undefined ? brl(row.cost) : '—'} sub="última compra" />}
                    {row.margin !== undefined && (
                      <FichaNumero
                        label="Margem"
                        valor={pct(row.margin)}
                        sub={row.margin < 0.3 ? 'abaixo do piso' : 'dentro do piso'}
                        cor={row.margin >= 0.5 ? 'var(--pos)' : row.margin >= 0.3 ? 'var(--text)' : 'var(--neg)'}
                      />
                    )}
                    <FichaNumero
                      label="Saídas 30d"
                      valor={String(row.uses30)}
                      sub={row.uses30 === 0 ? 'sem giro' : 'via OS'}
                      cor={row.uses30 === 0 ? 'var(--neg)' : 'var(--text)'}
                    />
                  </div>
                </DsDrawerSection>
              )}

              <DsDrawerSection title="Especificações">
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '9px 20px' }}>
                  <Espec label="SKU" valor={row.sku} />
                  <Espec label="Categoria" valor={row.cat_label ?? '—'} />
                  <Espec label="Unidade de venda" valor={row.unit} />
                  <Espec label="Situação" valor={row.active ? 'à venda' : 'fora de venda'} />
                  <Espec label="Origem" valor={row.stockKind === 'estoque' ? 'estoque próprio' : 'sob demanda'} />
                  <Espec
                    label="Disponível"
                    valor={row.stockKind === 'sob_demanda' ? 'sob demanda' : row.stockQty === null ? '—' : row.stockQty.toLocaleString('pt-BR') + ' ' + row.unit}
                  />
                </div>
              </DsDrawerSection>
            </div>

            <div style={{ marginTop: 'auto', padding: '12px 18px', display: 'flex', gap: 8, justifyContent: 'flex-end', borderTop: '1px solid var(--border)' }}>
              <DsButton variant="ghost" size="sm" onClick={onClose}>Fechar</DsButton>
              <DsButton variant="primary" size="sm" onClick={() => { aviso('Abrindo a ficha completa…'); window.location.href = `/products/${row.id}`; }}>
                Abrir ficha completa
              </DsButton>
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

function FichaNumero({ label, valor, sub, cor = 'var(--text)' }: { label: string; valor: string; sub: string; cor?: string }) {
  return (
    <div style={{ border: '1px solid var(--border)', borderRadius: 8, background: 'var(--bg-2)', padding: '10px 12px' }}>
      <div style={{ font: '600 10px/1.2 var(--font-sans)', letterSpacing: '.07em', textTransform: 'uppercase', color: 'var(--text-mute)' }}>{label}</div>
      <div style={{ font: '600 18px/1.2 var(--font-sans)', fontVariantNumeric: 'tabular-nums', marginTop: 5, color: cor }}>{valor}</div>
      <div style={{ font: '400 11px/1.3 var(--font-sans)', color: 'var(--text-mute)', marginTop: 3 }}>{sub}</div>
    </div>
  );
}

function Espec({ label, valor }: { label: string; valor: string }) {
  return (
    <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', gap: 12, paddingBottom: 6, borderBottom: '1px dotted var(--border)' }}>
      <span style={{ fontSize: 11, color: 'var(--text-mute)' }}>{label}</span>
      <span style={{ fontSize: 11, color: 'var(--text)', fontVariantNumeric: 'tabular-nums', textAlign: 'right' }}>{valor}</span>
    </div>
  );
}

function ModalAtalhos({ onClose }: { onClose: () => void }) {
  return (
    /* Overlay e diálogo são IRMÃOS, não aninhados — é como o `Drawer` do DS resolve.
       Aninhar exigiria `stopPropagation` num div não-interativo, que é erro de a11y
       (o clique não teria equivalente por teclado). Aqui o backdrop é um <button> de
       verdade: fecha no clique E no Enter, sem truque. */
    <div style={{ position: 'fixed', inset: 0, zIndex: 70, display: 'grid', placeItems: 'center', padding: 24 }}>
      <button
        type="button"
        aria-label="Fechar atalhos"
        onClick={onClose}
        style={{
          position: 'absolute', inset: 0, border: 0, padding: 0,
          background: 'color-mix(in oklab, var(--text) 38%, transparent)', cursor: 'default',
        }}
      />
      <div
        role="dialog"
        aria-label="Atalhos de teclado"
        style={{
          position: 'relative',
          width: 440, maxWidth: '100%', background: 'var(--surface)', border: '1px solid var(--border)',
          borderRadius: 12, boxShadow: 'var(--shadow-pop)', overflow: 'hidden',
        }}
      >
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '12px 16px', borderBottom: '1px solid var(--border)' }}>
          <span style={{ font: '600 13px/1.3 var(--font-sans)' }}>Atalhos</span>
          <span style={{ font: '500 11px/1 var(--font-mono)', color: 'var(--text-mute)' }}>Esc fecha</span>
        </div>
        <div style={{ padding: '8px 16px 14px' }}>
          {ATALHOS.map((a) => (
            <div key={a.tecla} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 16, padding: '7px 0', borderBottom: '1px solid var(--border-2)' }}>
              <span style={{ fontSize: 11, color: 'var(--text)' }}>{a.acao}</span>
              <kbd style={{
                font: '600 11px/1 var(--font-mono)', color: 'var(--text-dim)', background: 'var(--bg-2)',
                border: '1px solid var(--border)', borderRadius: 4, padding: '4px 7px',
              }}>{a.tecla}</kbd>
            </div>
          ))}
        </div>
      </div>
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
    <div className="kpis" style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 12, margin: '12px 24px 0' }} aria-hidden>
      {[0, 1, 2, 3, 4].map((i) => (
        <div key={i} style={{ border: '1px solid var(--color-border)', borderRadius: 8, background: 'var(--color-card)', padding: 12, height: 64 }}>
          <DsSkeleton variant="text" />
        </div>
      ))}
    </div>
  );
}

function SkeletonTabela() {
  return (
    <div style={{ border: '1px solid var(--border)', borderRadius: 12, background: 'var(--surface)', padding: '14px 16px' }} aria-hidden>
      <DsSkeleton variant="row" count={8} />
    </div>
  );
}
