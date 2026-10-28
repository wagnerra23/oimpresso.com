// Manufacturing/Recipes — a tela de Fabricação em `/manufacturing/recipe`.
//
// FONTE DE DESIGN (âncora declarada no charter): `prototipo-ui/cowork/Wagner/manufacturing-page.jsx`,
// resolvida por `node scripts/design/ancora.mjs Manufacturing/Recipes`. O espelho foi conferido
// contra o handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" e é IDÊNTICO (6 arquivos, 0 linhas de
// diferença) — este porte segue o protótipo, não o inventa.
//
// NORMATIVO: o README do handoff. §4.2 consulta · §4.3 drawer · §7 modelo de custo ·
// §8 ficha PT-07 · §11 acessibilidade medida · §16 mapa de campos · §17 R-01..R-24.
// F1 PLAN: memory/requisitos/Manufacturing/RUNBOOK-recipes.md
//
// PT-01 Lista (UI-0013). A grade é o `shared/DataTable` na anatomia `grid` — o par React do
// `DataGrid` do DS, que é a grade do protótipo (HANDOFF.md §4). Até 2026-10-02 a tela filtrava,
// ordenava e paginava no NAVEGADOR sobre a lista inteira e tinha tabela própria; agora isso
// acontece no SERVIDOR (`RecipeController@index` + `RecipeBomService::filtrarOrdenar`), como
// decidido no playbook ds-atomos (D-GRADE) e no item 2 da ADR de design 0412 do handoff. O custo
// de cada receita continua o mesmo `presentRecipe` — só mudou onde a lista é cortada.
//
// CSS: `cowork-manufacturing-bundle.css` aplicado INTEIRO (proibicoes.md §"Design System /
// Pacote Cowork novo" — 1ª aplicação nunca é cherry-pick). Escopo `.mfg-root`.

import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { Pencil, Plus, Printer, Search } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Button } from '@/Components/ui/button';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import KpiCard from '@/Components/shared/KpiCard';
import { Segmented } from '@/Components/ui/segmented';
import StatusBadge from '@/Components/shared/StatusBadge';
import FichaPrint from './_components/FichaPrint';
import { faixaMargem, fmt, num, rotuloCustoExtra } from './_lib/formato';
import type { ContadoresProducao, Permissoes, Receita } from './_lib/tipos';
import FabricacaoAbas from './_components/FabricacaoAbas';
import NovaReceitaDialog from './_components/NovaReceitaDialog';
import Gaveta, { BOTAO_PRIMARIO, BOTAO_SECUNDARIO, SecaoGaveta } from './_components/Gaveta';
import '../../../css/cowork-manufacturing-bundle.css';

/** Filtros da URL — o servidor devolve o que entendeu, já normalizado. */
interface Filtros {
  q: string | null;
  cat: string | null;
  kpi: 'margem' | null;
  sort: string;
  dir: 'asc' | 'desc';
}

interface Props {
  recipes: PaginatorShape<Receita>;
  filtros: Filtros;
  /** Sobre TODAS as receitas do business, nunca sobre a página nem o filtro (§4.2). */
  kpis: { total: number; custo_medio: number; margem_baixa: number };
  categorias: string[];
  /** R-08 — os ids de TODAS as filtradas, de todas as páginas. */
  ids_filtrados: number[];
  /** Só chega no reload parcial que a impressão pede (`Inertia::optional`). */
  fichas?: Receita[];
  /** "Copiar de outra receita" da Nova receita — só no reload parcial que a janela pede ao abrir. */
  receitas_copia?: { id: number; nome: string }[];
  permissions: Permissoes;
  producao: ContadoresProducao;
  settings: { enable_updating_product_price: boolean };
}

const ROTA = '/manufacturing/recipe';

/** R-10 — faixa da margem → tom do `StatusBadge` (≥55 sucesso · ≥45 atenção · abaixo perigo). */
const TOM_MARGEM = { ok: 'success', warn: 'warning', bad: 'danger' } as const;

/**
 * Telas que escrevem — a lista aponta, não reimplementa.
 * A "Nova receita" abre a `NovaReceitaDialog`, que entrega para o editor de ingredientes.
 * "Editar ingredientes" abre o editor React (US-MANU-006) — é nele que se exclui a receita (regra 6
 * do §5). Desde o cutover de 2026-10-27 ele é a única tela de `/add-ingredient`; o `&tela=nova` ficou
 * no link por compatibilidade e não muda nada. "Produzir" ainda vai para a ordem Blade.
 */
const editarIngredientes = (variationId: number) => `/manufacturing/add-ingredient?variation_id=${variationId}&tela=nova`;
const ROTA_PRODUZIR = '/manufacturing/production/create';

/**
 * Colunas do `manufacturing-page.jsx`. O `id` de cada uma é a chave de ordenação que o servidor
 * entende (`RecipeBomService::ORDENAVEIS`) — é ele que o `DataTable` manda em `?sort=`.
 */
const COLUNAS: ColumnDef<Receita, unknown>[] = [
  {
    id: 'name',
    accessorFn: (r) => r.name,
    header: 'Receita',
    cell: ({ row: { original: r } }) => (
      <>
        <span className="block truncate font-semibold tracking-[-.006em]">{r.name}</span>
        <span className="block truncate text-[11px] text-[var(--text-dim)]">
          {r.sku} · {r.n_ingredientes} ingrediente{r.n_ingredientes === 1 ? '' : 's'}
        </span>
      </>
    ),
  },
  {
    id: 'cat',
    accessorFn: (r) => `${r.cat} / ${r.sub}`,
    header: 'Categoria',
  },
  {
    // R-09 — a coluna DECLARA a unidade que está exibindo. Com sub-unidade de saída, mostra a
    // quantidade convertida COM o rótulo da sub-unidade.
    id: 'qtd',
    accessorFn: (r) => r.qtd,
    header: 'Quantidade',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: r } }) =>
      `${r.sub_un && r.sub_fator ? num(r.qtd * r.sub_fator, 2) : num(r.qtd, 2)} ${r.sub_un ?? r.un}`,
  },
  {
    id: 'total',
    accessorFn: (r) => r.custos.total,
    header: 'Custo total',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: r } }) => fmt(r.custos.total),
  },
  {
    id: 'unit',
    accessorFn: (r) => r.custos.unit,
    header: 'Custo unitário',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: r } }) => fmt(r.custos.unit),
  },
  {
    id: 'venda',
    accessorFn: (r) => r.venda,
    header: 'Venda',
    meta: { align: 'right', mono: true },
    cell: ({ row: { original: r } }) => fmt(r.venda),
  },
  {
    id: 'margem',
    accessorFn: (r) => r.custos.margem,
    header: 'Margem',
    meta: { align: 'right' },
    // R-10 — 3 faixas, no `StatusBadge` do DS como no protótipo.
    cell: ({ row: { original: r } }) => (
      <StatusBadge
        kind="margem"
        value={faixaMargem(r.custos.margem)}
        label={`${num(r.custos.margem, 0)}%`}
        tone={TOM_MARGEM[faixaMargem(r.custos.margem)]}
      />
    ),
  },
];

export default function Recipes({
  recipes,
  filtros,
  kpis,
  categorias,
  ids_filtrados,
  permissions,
  producao,
  settings,
}: Props) {
  const [q, setQ] = useState(filtros.q ?? '');
  const [sel, setSel] = useState<number[]>([]);
  const [openId, setOpenId] = useState<number | null>(null);
  const [imprimir, setImprimir] = useState<{ itens: Receita[]; semCusto: boolean } | null>(null);
  const [novaAberta, setNovaAberta] = useState(false);
  const buscaRef = useRef<HTMLInputElement>(null);

  // R-04 — `/` foca a busca; `/` digitado DENTRO de campo continua sendo `/`.
  // R-14 — `esc` fecha o drawer.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const alvo = e.target as HTMLElement | null;
      const emCampo = /^(INPUT|TEXTAREA|SELECT)$/.test(alvo?.tagName ?? '');
      if (e.key === '/' && !emCampo) {
        e.preventDefault();
        buscaRef.current?.focus();
      }
      if (e.key === 'Escape') setOpenId(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Toda mudança de filtro volta pra página 1 (R-06): a página não entra no pedido. A seleção
  // fica — `preserveState` mantém o estado da tela entre as visitas.
  const filtrar = (troca: Partial<Filtros>) => {
    const f = { ...filtros, q: q || null, ...troca };
    router.get(
      ROTA,
      { q: f.q || undefined, cat: f.cat || undefined, kpi: f.kpi || undefined, sort: f.sort, dir: f.dir },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  };

  // Busca com espera de 300ms, como a do `DataTable` — não manda um pedido por tecla.
  useEffect(() => {
    if (q === (filtros.q ?? '')) return;
    const t = setTimeout(() => filtrar({ q: q || null }), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [q]);

  const CATS = ['Todas', ...categorias];
  const cat = filtros.cat ?? 'Todas';
  const trocarCat = (c: string) => filtrar({ cat: c === 'Todas' ? null : c });
  const trocarKpi = (k: 'margem') => filtrar({ kpi: filtros.kpi === k ? null : k });

  // R-08 — "selecionar todas" marca as FILTRADAS de todas as páginas, não só as visíveis.
  const marcadasNoFiltro = ids_filtrados.filter((id) => sel.includes(id)).length;
  const allState =
    ids_filtrados.length > 0 && marcadasNoFiltro === ids_filtrados.length
      ? 'all'
      : marcadasNoFiltro > 0
        ? 'some'
        : 'none';
  const aberta = recipes.data.find((r) => r.id === openId) ?? null;

  // As marcadas podem estar em OUTRAS páginas — a ficha delas vem num reload parcial que só
  // calcula `fichas`. `preserveUrl` não deixa o `?fichas=` sujar o endereço da tela.
  const imprimirSelecionadas = () =>
    router.reload({
      only: ['fichas'],
      data: { fichas: sel.join(',') },
      preserveUrl: true,
      onSuccess: (page) => {
        const itens = (page.props as unknown as Props).fichas ?? [];
        if (itens.length) setImprimir({ itens, semCusto: false });
      },
    });

  return (
    <div className="mfg-root" data-screen-label="Fabricação · Receitas">
      <div className="os-page-h" data-contract="cabecalho">
        <div className="os-page-h-l">
          <h1>Fabricação</h1>
          <p>
            {kpis.total} receita{kpis.total === 1 ? '' : 's'} · {producao.total} ordem
            {producao.total === 1 ? '' : 's'} de produção · custo recalculado pelo preço atual dos
            ingredientes
          </p>
        </div>
        <div className="os-page-h-r">
          {permissions.criar && (
            <Button size="sm" onClick={() => setNovaAberta(true)}>
              <Plus className="mr-1.5 h-3.5 w-3.5" /> Nova receita
            </Button>
          )}
        </div>
      </div>

      {/* §4.1 — abas do módulo. Cada uma navega pra uma tela que EXISTE hoje.
          "Insumos" passou a existir na US-MANU-005 (`usosDoInsumo` no RecipeBomService) — o
          §18.3 do handoff dizia "sem backend, a aba não sai", e o backend saiu. */}
      <FabricacaoAbas ativa="receitas" receitas={kpis.total} producao={producao} podeProduzir={permissions.prod} />

      {/* §4.2 — 3 KPIs; o 2º FILTRA (liga/desliga), o 1º e o 3º são leitura (R-05). Até
          2026-10-25 eram 4: o "Desperdício ≥ 8%" saiu com o % da receita (D4 · decisão [W]
          2026-10-23: desperdício é somente na Produção), e o protótipo passou a grade para 3
          colunas do mesmo jeito. Os cartões são o `KpiCard` do DS, como no protótipo
          (`manufacturing-page.jsx`): os de leitura na variante padrão; o que filtra na
          `variant="filter"`, com a placa de ícone e o tom âmbar. O protótipo pinta o ÍCONE, não
          o número (medido com a sonda nos dois lados, 2026-09-30). */}
      <div className="mfg-kpis" data-contract="kpis" style={{ gridTemplateColumns: 'repeat(3, 1fr)' }}>
        <KpiCard
          label="Custo médio / unidade"
          value={fmt(kpis.custo_medio)}
          description={`média das ${kpis.total} receita${kpis.total === 1 ? '' : 's'}`}
        />
        <KpiCard
          variant="filter"
          label="Margem abaixo de 45%"
          value={kpis.margem_baixa}
          description="preço de venda desatualizado"
          icon="Scale"
          filterTone="amber"
          selected={filtros.kpi === 'margem'}
          onClick={() => trocarKpi('margem')}
        />
        <KpiCard
          label="Produção do mês"
          value={producao.mes_final}
          description={`${producao.mes_rascunho} rascunho${producao.mes_rascunho === 1 ? '' : 's'} em aberto`}
        />
      </div>

      <div className="mfg-bar" data-contract="filtros">
        {/* Busca nas medidas do `SearchInput` do protótipo (34px, canto 8, texto 13, ícone 15) —
            o `SearchInput` do DS não tem par React (gap no component-registry), então a peça
            local segue. O "(tecla /)" no placeholder é copy do CONTRATO da tela
            (`manufacturing-recipes.contract.json`, citado do handoff normativo) e fica: o
            protótipo o tirou, mas copy de contrato é decisão [W], não de forma. */}
        <div className="mfg-s">
          <Search size={15} className="ic" aria-hidden />
          <input
            ref={buscaRef}
            placeholder="Buscar receita por nome, SKU, categoria…  (tecla /)"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            aria-label="Buscar receita"
          />
        </div>
        {/* Categorias no `Segmented` do DS, como no protótipo. O próprio protótipo declara que o
            Segmented aceita 2–5 opções e que "a sexta pede outra peça" — sem dizer qual. Aqui as
            categorias vêm do cadastro da empresa, então acima de 5 opções ficam as pílulas de
            antes (NÃO DEFINIDO NO PROTÓTIPO; não se inventa a peça). */}
        {CATS.length <= 5 ? (
          <Segmented
            aria-label="Categoria"
            value={cat}
            onValueChange={trocarCat}
            options={CATS.map((c) => ({ value: c, label: c }))}
          />
        ) : (
          <div className="mfg-chips" role="group" aria-label="Categoria">
            {CATS.map((c) => (
              <button
                type="button"
                key={c}
                className={`mfg-chip${cat === c ? ' act' : ''}`}
                aria-pressed={cat === c}
                onClick={() => trocarCat(c)}
              >
                {c}
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="mfg-tablewrap" data-contract="lista">
        <DataTable<Receita>
          columns={COLUNAS}
          data={recipes.data}
          pagination={recipes}
          endpoint={ROTA}
          filters={{ q: filtros.q, cat: filtros.cat, kpi: filtros.kpi, sort: filtros.sort, dir: filtros.dir }}
          caption="Receitas"
          density="grid"
          totalLabel="receitas"
          showSearch={false}
          rowKey={(r) => r.id}
          onRowClick={(r) => setOpenId(r.id)}
          emptyMessage="Nenhuma receita encontrada. Ajuste a busca, troque a categoria ou limpe o filtro de KPI."
          selection={{
            isSelected: (r) => sel.includes(r.id),
            onToggle: (r) => setSel((s) => (s.includes(r.id) ? s.filter((x) => x !== r.id) : [...s, r.id])),
            allState,
            onToggleAll: (marcar) => setSel(marcar ? ids_filtrados : []),
            rowLabel: (r) => r.name,
          }}
        />
      </div>

      {/* §4.2 BulkBar. A 3ª ação do protótipo — "Atualizar preço de venda do produto" — NÃO
          entra: §18.1 diz literalmente "Não implemente esse fator 2" e a regra de markup real
          não foi decidida. Escrever em N preços é Tier 0 de VALOR (proibicoes.md §REGRA MESTRE),
          exige dupla prova + antes→depois + aprovação [W]. Fica declarado, não silenciado.
          A flag `enable_updating_product_price` vem no payload esperando essa decisão. */}
      {sel.length > 0 && (
        <div className="mfg-bulk">
          <b>
            {sel.length} receita{sel.length > 1 ? 's' : ''} selecionada{sel.length > 1 ? 's' : ''}
          </b>
          <span className="sp" />
          <Button variant="ghost" size="sm" onClick={() => setSel([])}>
            Limpar
          </Button>
          <Button variant="ghost" size="sm" onClick={imprimirSelecionadas}>
            <Printer className="mr-1.5 h-3.5 w-3.5" /> Imprimir fichas
          </Button>
        </div>
      )}

      {aberta && (
        <RecipeDrawer
          r={aberta}
          perms={permissions}
          precoEmMassaLiberado={settings.enable_updating_product_price}
          onClose={() => setOpenId(null)}
          onImprimir={(semCusto) => setImprimir({ itens: [aberta], semCusto })}
        />
      )}

      {imprimir && (
        <FichaPrint
          itens={imprimir.itens}
          semCusto={imprimir.semCusto}
          onDone={() => setImprimir(null)}
        />
      )}

      {permissions.criar && <NovaReceitaDialog open={novaAberta} onOpenChange={setNovaAberta} />}
    </div>
  );
}

/**
 * §4.3 — gaveta de LEITURA da receita: grupos com subtotal + cartão de custo com a nota. A moldura
 * é a de `Gaveta.tsx` (a do `RecipeDrawer` do protótipo: Drawer + DrawerSection do DS).
 *
 * Diferenças de propósito em relação ao protótipo:
 *  · sem "Quando o insumo encarece" e "Nota no faturamento": a receita não guarda política de preço
 *    nem natureza fiscal — esses campos só existem no dado de mentira do protótipo;
 *  · "Produzir" aparece só com `prod`: aqui criar ordem exige `manufacturing.access_production` e
 *    nada mais (`ProductionController@create`), e o protótipo pede também `criar`.
 */
function RecipeDrawer({
  r,
  perms,
  precoEmMassaLiberado,
  onClose,
  onImprimir,
}: {
  r: Receita;
  perms: Permissoes;
  /** `business.manufacturing_settings.enable_updating_product_price` — hoje só informa. */
  precoEmMassaLiberado: boolean;
  onClose: () => void;
  onImprimir: (semCusto: boolean) => void;
}) {
  return (
    <Gaveta
      aberta
      onClose={onClose}
      titulo={r.name}
      subtitulo={
        `${r.sku} · ${r.cat} / ${r.sub} · rende ${num(r.qtd, 2)} ${r.un}` +
        (r.atualizado ? ` · atualizado ${r.atualizado}` : '')
      }
      rodape={
        <>
          <Button variant="outline" className={BOTAO_SECUNDARIO} onClick={onClose}>
            Fechar
          </Button>
          <Button variant="outline" className={BOTAO_SECUNDARIO} onClick={() => onImprimir(false)}>
            <Printer size={13} className="size-[13px]" aria-hidden /> Ficha com custo
          </Button>
          <Button variant="outline" className={BOTAO_SECUNDARIO} onClick={() => onImprimir(true)}>
            Via de produção
          </Button>
          {perms.prod && (
            <Button asChild variant="outline" className={BOTAO_SECUNDARIO}>
              <a href={ROTA_PRODUZIR}>Produzir</a>
            </Button>
          )}
          {perms.editar && (
            <Button asChild className={BOTAO_PRIMARIO}>
              <a href={editarIngredientes(r.variation_id)}>
                <Pencil size={13} className="size-[13px]" aria-hidden /> Editar ingredientes
              </a>
            </Button>
          )}
        </>
      }
    >
      {/* No protótipo os grupos ficam direto sobre o corpo (um `div` com padding "0 18px 8px"),
          fora de cartão — só o custo é `DrawerSection`. A altura de linha é a que eles herdam do
          corpo do protótipo (1,45); dentro do cartão vale a do `DrawerSection` (1,5). */}
      <div className="px-[18px] pb-2 leading-[1.45]">
        {r.grupos.map((g) => (
          <div className="mfg-grp" key={g.g}>
            <div className="mfg-grp-h">
              <b>{g.g}</b>
              <span className="v">{fmt(g.subtotal)}</span>
            </div>
            {g.itens.map((i) => (
              <div className="mfg-ing" key={i.id}>
                <span className="n">
                  {i.nome}
                  <small>
                    {i.sku}
                    {i.multiplicador > 1
                      ? ` · ${num(i.quantidade * i.multiplicador, 3)} ${i.unidade_base}`
                      : ''}
                  </small>
                </span>
                <span className="m">
                  {num(i.quantidade, i.quantidade < 1 ? 3 : 2)} {i.unidade}
                </span>
                <span className="m">{fmt(i.custo_unitario)}</span>
                <span className="m tot">{fmt(i.subtotal)}</span>
              </div>
            ))}
            {g.itens.length === 0 && <p className="mfg-pick-empty">Grupo sem ingredientes.</p>}
          </div>
        ))}
      </div>

      <SecaoGaveta titulo="Custo">
        <dl className="mfg-tot">
          <dt>Ingredientes</dt>
          <dd>{fmt(r.custos.ingredientes)}</dd>
          <dt>Custo extra ({rotuloCustoExtra(r.custo_tipo, r.extra, r.un)})</dt>
          <dd>{fmt(r.custos.extra)}</dd>
          {r.sub_un && r.sub_fator ? (
            <>
              <dt>Sub-unidade de saída</dt>
              <dd>
                {num(r.qtd * r.sub_fator, 2)} {r.sub_un}
              </dd>
            </>
          ) : null}
          <hr />
          <dt className="mfg-tot-big-dt">Custo por {r.un}</dt>
          <dd className="mfg-tot-big-dd">{fmt(r.custos.unit)}</dd>
          <dt>Preço de venda atual</dt>
          <dd>{fmt(r.venda)}</dd>
          <dt>Margem</dt>
          <dd>{num(r.custos.margem, 1)}%</dd>
        </dl>

        {/* Texto verbatim §4.3 — é a frase que explica por que o número muda sozinho. */}
        <p className="mfg-note">
          O custo é recalculado a cada leitura a partir do preço atual dos ingredientes — a
          receita não guarda valor congelado. Uma compra de insumo salva em{' '}
          <button type="button" className="mfg-link" onClick={() => router.visit('/purchases')}>
            Compras
          </button>{' '}
          muda este número.
          {precoEmMassaLiberado
            ? ' A configuração de atualizar o preço do produto está ligada e vale para a produção;' +
              ' a atualização em massa a partir desta lista ainda não foi liberada.'
            : ''}
        </p>
      </SecaoGaveta>
    </Gaveta>
  );
}

Recipes.layout = (page: ReactNode) => (
  <AppShellV2
    title="Receitas · Fabricação"
    breadcrumbItems={[{ label: 'Fabricação' }, { label: 'Receitas' }]}
  >
    {page}
  </AppShellV2>
);
