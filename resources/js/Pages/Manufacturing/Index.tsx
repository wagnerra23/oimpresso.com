// @memcofre tela=/manufacturing/production module=Manufacturing
// MWART Wave J → board 2026-05-30 uplift (50 Developing → ≥70).
// Lista de produções (production_purchase) em Inertia/React no padrão PT-01
// Lista (AppShellV2 + PageHeader + KpiCard + tabela tokenizada + EmptyState).
// Coexiste com Blade legacy /manufacturing/production (Tier 0: preservado).
//
// Backend: ProductionController@indexV2 → ProductionService::listProductions/summary
// (scoped por business_id — Tier 0 ADR 0093). Filtros (location/data/finalizadas)
// via Inertia partial reload. CTA aponta pra rota legacy de create existente.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { Plus, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { Inline } from '@/Components/layout/inline';
import { Stack } from '@/Components/layout/stack';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import '../../../css/cowork-manufacturing-bundle.css';
import DataTable from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import StatusBadge from '@/Components/shared/StatusBadge';
import FabricacaoAbas from './_components/FabricacaoAbas';
import OrdemDrawer, { type OrdemDetalhe } from './_components/OrdemDrawer';

interface Production {
  id: number;
  ref_no: string | null;
  /** Já formatada `dd/mm/aaaa` pelo Service (enrichProductionRows). */
  transaction_date: string | null;
  location_name: string | null;
  /** `transactions.final_total` — valor GRAVADO na criação, não recalculado. */
  final_total: number;
  mfg_is_final: number;
  // US-MANU-004 (§4.5) — as 3 colunas novas + o que a coluna Produto mostra na 2ª linha.
  produto: string;
  unidade: string;
  n_ingredientes: number;
  criado_por: string;
  quantidade: number;
  /** `final_total / quantidade`, com guard de divisão por zero no Service. */
  custo_unitario: number;
}

interface Summary {
  total_count: number;
  final_count: number;
  pending_count: number;
  total_value: number;
}

interface FiltersState {
  location_id?: number | null;
  start_date?: string | null;
  end_date?: string | null;
  is_final?: boolean | null;
}

interface Props {
  productions: Production[];
  summary: Summary;
  /** id → nome. Pode não vir em versões antigas do payload. */
  business_locations?: Record<number, string>;
  filters?: FiltersState;
  /** Contador da aba "Receitas". Opcional: payload antigo não mandava. */
  recipes_count?: number;
  /** Detalhe da ordem aberta no painel (`?ordem=ID`, prop optional — só vem quando pedida). */
  ordem_detalhe?: OrdemDetalhe | null;
}

const ROUTE = '/manufacturing/production';
const CREATE_ROUTE = '/manufacturing/production/create';

/**
 * Filtros serializados explicitamente em string|number|undefined (RequestPayload do Inertia não
 * aceita `unknown`). is_final é flag de presença no backend (request()->has('is_final')) — só
 * envia quando true.
 */
function queryDosFiltros(f: FiltersState): Record<string, string | number | undefined> {
  return {
    location_id: f.location_id ?? undefined,
    start_date: f.start_date ?? undefined,
    end_date: f.end_date ?? undefined,
    is_final: f.is_final ? 1 : undefined,
  };
}

function applyFilter(current: FiltersState, patch: Partial<FiltersState>) {
  const next = queryDosFiltros({ ...current, ...patch });
  router.get(ROUTE, next, {
    preserveState: true,
    preserveScroll: true,
    only: ['productions', 'summary', 'filters'],
    replace: true,
  });
}

/**
 * `aaaa-mm-dd` com ano ≥ 2000. O `<input type="date">` emite `0002-09-30`, `0020-…`, `0202-…`
 * enquanto o ano é digitado — sem este guard cada tecla viraria um request.
 */
function isDataCompleta(value: string): boolean {
  const m = /^(\d{4})-\d{2}-\d{2}$/.exec(value);
  return !!m && Number(m[1]) >= 2000;
}

function formatCurrency(value: number): string {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  }).format(value ?? 0);
}

/** Quantidade produzida com 2 casas — §4.5 mostra `num(op.qtd, 2)`. */
function formatQuantity(value: number): string {
  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value ?? 0);
}

/**
 * Medidas dos filtros do `MfgProducaoView` (medição protótipo × produção de 2026-10-01, 1440 px):
 * rótulo 10,5px/600 em caixa alta; campo de data 150×36, canto 8, texto 13,5px, fundo `--surface`.
 * O canto é `rounded-[8px]` e não `rounded-lg`: neste projeto o `rounded-lg` vale 12px (medido em
 * produção em 2026-10-05, depois do #8690, que usou `rounded-lg` supondo 8).
 * O rótulo usa `--text-dim` e não o `--text-mute` do DS: texto pequeno em `--text-mute` reprova AA
 * (ADR 0410). O campo usa o `Input` na variante `shadcn`: a `cowork` passa pelo `.cw-input`, que é
 * CSS fora de camada e vence a altura/largura/texto das classes (medido: `h-9 w-[150px]` saía 133×30).
 */
const ROTULO = 'text-[10.5px] font-semibold uppercase tracking-[0.06em] text-[var(--text-dim)]';
const CAMPO_DATA =
  'h-9 w-[150px] rounded-[8px] border-[var(--border)] bg-[var(--surface)] px-2.5 text-[13.5px] md:text-[13.5px] text-foreground dark:bg-[var(--surface)]';

/**
 * As 8 colunas do `MfgProducaoView` (`manufacturing-producao.jsx`), na anatomia do `DataGrid` do
 * DS (`shared/DataTable` `density="grid"`): data, referência e números em mono, números à
 * direita. Sem ordenação e sem paginação, como o protótipo (`pagination={false}`, nenhuma
 * coluna `sortable`): a lista vem do servidor por data desc.
 */
const COLUNAS: ColumnDef<Production, unknown>[] = [
  { id: 'data', accessorFn: (p) => p.transaction_date ?? '—', header: 'Data', meta: { mono: true } },
  { id: 'ref', accessorFn: (p) => p.ref_no ?? '—', header: 'Referência', meta: { mono: true } },
  { id: 'local', accessorFn: (p) => p.location_name ?? '—', header: 'Local' },
  {
    id: 'produto',
    header: 'Produto',
    // `{primary, sub}` do DataGrid: nome 600/12,5 e a 2ª linha 11px. A 2ª linha usa `--text-dim`
    // em vez do `--text-mute` do DS: texto pequeno em `--text-mute` reprova AA (ADR 0410).
    cell: ({ row: { original: p } }) => (
      <>
        <b className="block truncate font-semibold tracking-[-0.006em]" title={p.produto}>
          {p.produto}
        </b>
        <small className="block truncate text-[11px] text-[var(--text-dim)]">
          {p.n_ingredientes} ingrediente{p.n_ingredientes === 1 ? '' : 's'}
          {p.criado_por ? ` · ${p.criado_por}` : ''}
        </small>
      </>
    ),
  },
  {
    id: 'qtd',
    header: 'Qtd',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: p } }) => `${formatQuantity(p.quantidade)}${p.unidade ? ` ${p.unidade}` : ''}`,
  },
  {
    id: 'total',
    header: 'Custo total',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: p } }) => (
      <>
        {formatCurrency(p.final_total)}
        {/* R-21 — o `fix` marca que, na ordem finalizada, este é o custo congelado na data da
            produção (não recalculado pelo preço de hoje). */}
        {p.mfg_is_final ? (
          <span
            className="ml-1 text-[10.5px] text-[var(--text-dim)]"
            title="custo congelado na data da produção"
          >
            fix
          </span>
        ) : null}
      </>
    ),
  },
  {
    id: 'unit',
    header: 'Custo unit.',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: p } }) => formatCurrency(p.custo_unitario),
  },
  {
    id: 'sit',
    header: 'Situação',
    cell: ({ row: { original: p } }) => (
      <StatusBadge kind="producao" value={p.mfg_is_final ? 'finalizada' : 'rascunho'} />
    ),
  },
];

function Index({ productions = [], summary, business_locations = {}, filters = {}, recipes_count, ordem_detalhe }: Props) {
  const [start, setStart] = useState<string>(filters.start_date ?? '');
  const [end, setEnd] = useState<string>(filters.end_date ?? '');
  // Painel da ordem (UC-OP-07): o cabeçalho abre na hora com o que a linha já sabe; o detalhe
  // chega por partial reload de `ordem_detalhe`, sem recarregar a lista.
  const [aberta, setAberta] = useState<Production | null>(null);
  const [carregando, setCarregando] = useState(false);

  const abrirOrdem = (p: Production) => {
    setAberta(p);
    setCarregando(true);
    router.get(ROUTE, { ...queryDosFiltros(filters), ordem: p.id }, {
      preserveState: true,
      preserveScroll: true,
      only: ['ordem_detalhe'],
      replace: true,
      onFinish: () => setCarregando(false),
    });
  };
  // undefined = carregando (ou a resposta ainda é de outra ordem) · null = não encontrada.
  const detalheAberto = !aberta || carregando
    ? undefined
    : ordem_detalhe && ordem_detalhe.id === aberta.id
      ? ordem_detalhe
      : ordem_detalhe === null
        ? null
        : undefined;

  const locationEntries = Object.entries(business_locations);
  const hasLocations = locationEntries.length > 0;

  // §4.5 — soma dos `final_total` das ordens LISTADAS (o mesmo conjunto que a tabela mostra).
  const custoDoPeriodo = productions.reduce((s, p) => s + (p.final_total ?? 0), 0);

  const hasActiveFilters =
    !!filters.location_id ||
    !!filters.start_date ||
    !!filters.end_date ||
    !!filters.is_final;

  const clearAll = () => {
    setStart('');
    setEnd('');
    // D-14: partial reload — limpar filtros re-busca só o que muda (espelha applyFilter).
    router.get(ROUTE, {}, {
      preserveState: true,
      preserveScroll: true,
      only: ['productions', 'summary', 'filters'],
      replace: true,
    });
  };

  // D-MFG-DATA ([W] 2026-09-25): o intervalo aplica AO ESCOLHER, como Local e "Só finalizadas"
  // — sem blur e sem botão. Só dispara quando o intervalo fica válido: os dois vazios (limpa)
  // ou os dois datas completas. Um só preenchido não aplica (mesma regra de antes).
  // Sem debounce de propósito: o input é `type="date"`, não texto — ele só emite valor com a
  // data inteira, e o guard de ano ≥ 2000 barra o `0002-…`/`0020-…` que sai enquanto o ano é
  // digitado à mão. Resultado: 1 request por data escolhida, nenhum por tecla.
  const applyDateRange = (s: string, e: string) => {
    if (s === (filters.start_date ?? '') && e === (filters.end_date ?? '')) return; // já aplicado
    if (!s && !e) {
      applyFilter(filters, { start_date: null, end_date: null });
    } else if (isDataCompleta(s) && isDataCompleta(e)) {
      applyFilter(filters, { start_date: s, end_date: e });
    }
  };

  return (
    <div className="space-y-6 p-6">
      {/* Slot 1 — PageHeader com CTA habilitado (rota legacy de create existe)

          §4.5 — o protótipo (`manufacturing-page.jsx:151`) conta receitas e ordens no
          subtítulo. A 3ª parte dele ("custo recalculado pelo preço atual dos ingredientes")
          fica de FORA de propósito: nesta tela o custo é o `final_total` GRAVADO, nunca
          recalculado (US-MANU-004 + RUNBOOK-producao.md §1). Copiar a copy literal poria uma
          afirmação FALSA na tela — o rodapé já diz a verdade ("custo congelado na data"). */}
      {/* Header canon (ADR 0409: tocar a tela acorda a dívida do header antigo). Sem ícone,
          como o protótipo (`manufacturing-page.jsx` `.os-page-h`: título · subtítulo · primário). */}
      {/* data-contract: o PageHeader não repassa props ao <header> — a âncora vai num wrapper
          (mesmo idioma de Backup/Index e Arquivos/Index). */}
      <div data-contract="cabecalho">
        <PageHeader
          title="Produção"
          subtitle={
            recipes_count === undefined
              ? 'Ordens de produção do módulo de Fabricação.'
              : `${recipes_count} receita${recipes_count === 1 ? '' : 's'} · ` +
                `${summary?.total_count ?? 0} ordens de produção`
          }
          actions={<PageHeaderPrimary label="Nova produção" href={CREATE_ROUTE} />}
        />
      </div>

      {/* Barra de abas do módulo — MESMA das 4 telas irmãs (Recipes/Report/Settings/Insumos).
          Esta tela nasceu na Wave J sem ela: era a única do módulo em React na época, então
          não havia pra onde navegar. Depois do cutover de 2026-09-04 o menu lateral passou a
          trazer o usuário pra cá e a tela virou BECO SEM SAÍDA — [M] reportou clicando e
          vendo a barra sumir. Desde 2026-09-30 a barra é o componente único `FabricacaoAbas`. */}
      {/* A âncora `data-contract="abas"` fica AQUI, no fonte da tela: o gate de contrato lê a ordem
          das âncoras dentro do alvo, e a barra vem do componente (listado no alvo só pela copy). */}
      <div data-contract="abas">
        <FabricacaoAbas
          ativa="producao"
          receitas={recipes_count}
          producao={{ total: summary?.total_count ?? 0, rascunhos: summary?.pending_count }}
        />
      </div>

      {/* Indicadores — como o `MfgProducaoView`: cada cartão com a linha de apoio. O protótipo
          explica por quê: sem ela a faixa media 75px aqui e 98px na aba Receitas, e trocar de aba
          fazia a faixa pular (medido a 1280px). Pelo mesmo motivo o tamanho é o padrão, igual ao
          da Receitas, e não o `compact`. Só "Finalizadas" filtra a lista (o MESMO filtro do
          checkbox "Só finalizadas").

          Duas descrições diferem do protótipo PORQUE o dado difere, e copiar a frase poria uma
          afirmação falsa na tela (o mesmo motivo que tira a 3ª parte do subtítulo):
           · lá os 4 números são calculados sobre local + período ("no filtro de local e data",
             "ordens do período"); aqui `ProductionService::summary($business_id)` não recebe os
             filtros — os números são de TODAS as ordens. Fazer os indicadores seguirem o filtro
             muda o valor somado na tela: é passo próprio, com a regra de valor (2 caminhos +
             antes→depois);
           · lá o rascunho entra "a preço de hoje" (ele recalcula); aqui é o `final_total` GRAVADO
             em cada ordem, nunca recalculado (US-MANU-004 + RUNBOOK-producao.md §1). */}
      {/* Forma do `MfgProducaoView` (medido em produção 2026-10-05, 1440px): os cartões de leitura
          NÃO têm ícone (o KpiCard de leitura do DS não desenha ícone) e só "Finalizadas" é cartão-filtro,
          com o ícone ao lado — como na aba Receitas. Com ícone no título dos 4, cada cartão media 125px
          contra 105px da Receitas, e a faixa pulava ao trocar de aba. Vão de 10px, o do `.mfg-kpis`. */}
      <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-4" data-contract="kpis">
        <KpiCard label="Total" value={summary?.total_count ?? 0} description="ordens cadastradas" />
        <KpiCard
          variant="filter"
          label="Finalizadas"
          value={summary?.final_count ?? 0}
          description="estoque já movimentado"
          icon="Check"
          filterTone="emerald"
          selected={!!filters.is_final}
          onClick={() => applyFilter(filters, { is_final: filters.is_final ? null : true })}
        />
        <KpiCard
          label="Pendentes"
          value={summary?.pending_count ?? 0}
          description="rascunhos, sem movimentar estoque"
        />
        <KpiCard
          label="Valor total"
          value={formatCurrency(summary?.total_value ?? 0)}
          description="todas as ordens cadastradas"
        />
      </div>

      {/* Slot 3 — filtros (local + intervalo de data), na faixa do protótipo (`.mfg-filters` do
          `MfgProducaoView`): sem cartão em volta, controles alinhados pela base, gap 12px. */}
      <div data-contract="filtros">
        {/* Rótulos LOCAL / DE / ATÉ: o protótipo (`MfgProducaoView`) põe cada controle num
            `<Campo label=…>` (medido em 2026-10-01: 10,5px/600, caixa alta — ver `ROTULO`).
            Aqui a forma é replicada com token do DS. Sem eles a barra só
            tinha `aria-label`: quem usa leitor de tela ouvia o campo, quem enxerga não lia
            nada. Os `aria-label` saem porque o `<label>` visível já nomeia o controle — manter
            os dois faria o leitor anunciar um nome diferente do que está escrito na tela.
            `items-end` alinha os controles pela base, como o `.mfg-filters` do protótipo. */}
        <Inline gap={3} align="end" wrap>
          {hasLocations && (
            <Stack gap={1} asChild>
              <label htmlFor="mfg-op-local">
                <span className={ROTULO}>
                  Local
                </span>
                {/* eslint-disable-next-line no-restricted-syntax -- select nativo: filtro simples de local, estilizado com tokens DS */}
                <select
                  id="mfg-op-local"
                  className="h-[34px] w-[180px] rounded-[8px] border border-[var(--border)] bg-[var(--surface)] px-2.5 text-[13px] text-foreground"
                  value={filters.location_id ?? ''}
                  onChange={(e) =>
                    applyFilter(filters, {
                      location_id: e.target.value ? Number(e.target.value) : null,
                    })
                  }
                >
                  <option value="">Todos os locais</option>
                  {locationEntries.map(([id, name]) => (
                    <option key={id} value={id}>
                      {String(name)}
                    </option>
                  ))}
                </select>
              </label>
            </Stack>
          )}

          <Inline gap={2} align="end">
            <Stack gap={1} asChild>
              <label htmlFor="mfg-op-data-inicial">
                <span className={ROTULO}>
                  De
                </span>
                <Input
                  variant="shadcn"
                  id="mfg-op-data-inicial"
                  type="date"
                  value={start}
                  onChange={(e) => {
                    setStart(e.target.value);
                    applyDateRange(e.target.value, end);
                  }}
                  className={CAMPO_DATA}
                />
              </label>
            </Stack>
            <Stack gap={1} asChild>
              <label htmlFor="mfg-op-data-final">
                <span className={ROTULO}>
                  Até
                </span>
                <Input
                  variant="shadcn"
                  id="mfg-op-data-final"
                  type="date"
                  value={end}
                  onChange={(e) => {
                    setEnd(e.target.value);
                    applyDateRange(start, e.target.value);
                  }}
                  className={CAMPO_DATA}
                />
              </label>
            </Stack>
          </Inline>

          {/* §4.5 — "Só finalizadas" como checkbox. O KPI "Finalizadas" continua clicável
              (os 4 KPIs não mudam nesta onda); os dois governam o MESMO filtro. */}
          {/* `Inline asChild` em vez de layout solto no próprio label: layout é composição de
              primitivos (ADR 0253). O ratchet pegou o caso na primeira tentativa — e depois
              pegou o COMENTÁRIO que citava o anti-padrão, porque o guard casa texto. */}
          <Inline gap={2} align="center" asChild>
            <label className="text-[12.5px] font-medium text-[var(--text)]" htmlFor="mfg-op-so-finalizadas">
              <Checkbox
                id="mfg-op-so-finalizadas"
                checked={!!filters.is_final}
                onCheckedChange={(v) => applyFilter(filters, { is_final: v === true ? true : null })}
              />
              Só finalizadas
            </label>
          </Inline>

          {hasActiveFilters && (
            <Button variant="ghost" size="sm" onClick={clearAll}>
              <X className="mr-1 h-3 w-3" /> Limpar
            </Button>
          )}
        </Inline>
      </div>

      {/* Slot 5 — a grade do `DataGrid` do DS (`shared/DataTable` `density="grid"`), como nas
          abas Receitas, Insumos e Relatório. A moldura é da própria tabela; o vazio continua no
          cartão. Sem lista, o protótipo não desenha a grade — só o `EmptyState`. */}
      <div data-contract="lista">
        {productions.length === 0 ? (
          <div className="rounded-lg border border-border bg-card">
            <EmptyState
              icon="factory"
              variant={hasActiveFilters ? 'search' : 'default'}
              title={hasActiveFilters ? 'Nenhuma produção no filtro' : 'Sem produções cadastradas'}
              description={
                hasActiveFilters
                  ? 'Ajuste ou limpe os filtros pra ver mais resultados.'
                  : 'Crie a primeira ordem de produção pelo botão "Nova produção".'
              }
              action={
                hasActiveFilters ? (
                  <Button variant="outline" size="sm" onClick={clearAll}>
                    <X className="mr-1 h-4 w-4" /> Limpar filtros
                  </Button>
                ) : (
                  <Button asChild size="sm">
                    <a href={CREATE_ROUTE}>
                      <Plus className="mr-2 h-4 w-4" /> Nova produção
                    </a>
                  </Button>
                )
              }
            />
          </div>
        ) : (
          <DataTable<Production>
            columns={COLUNAS}
            data={productions}
            caption="Ordens de produção"
            density="grid"
            showSearch={false}
            rowKey={(p) => p.id}
            onRowClick={abrirOrdem}
          />
        )}
      </div>

      {/* §4.5 — rodapé verbatim do protótipo. O custo somado é o GRAVADO (`final_total`),
          não o recalculado do Relatório (US-MANU-002) — ver RUNBOOK-producao.md §1. */}
      {productions.length > 0 && (
        <p className="text-xs text-muted-foreground tabular-nums">
          {productions.length} ordens · custo do período{' '}
          <span className="font-medium text-foreground">{formatCurrency(custoDoPeriodo)}</span> ·
          ordens finalizadas mostram o custo congelado na data
        </p>
      )}

      <OrdemDrawer ordem={aberta} detalhe={detalheAberto} onClose={() => setAberta(null)} />
    </div>
  );
}

// US-MANU-004 — o `StatusPill` local saiu daqui: a situação agora vem do `StatusBadge`
// canônico (`kind="producao"`, domínio adicionado no componente compartilhado). Era
// exatamente o tipo de duplicata que o `reuse-gate` existe pra impedir.

Index.layout = (page: ReactNode) => (
  <AppShellV2
    title="Produção · Fabricação"
    breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Produção' }]}
  >
    {page}
  </AppShellV2>
);

export default Index;
