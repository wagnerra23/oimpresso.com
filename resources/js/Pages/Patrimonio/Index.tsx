// Patrimonio/Index — Painel do Patrimônio (PT-04 Dashboard · UI-0013).
//   rota:    GET /asset/dashboard  →  AssetController::dashboard()
//   fonte:   prototipo-ui/cowork/patrimonio-page.jsx  aba "Painel" (painelData :149)
//   runbook: memory/requisitos/AssetManagement/RUNBOOK-patrimonio-index.md
//   adrs:    0394 (endereço Pages/Patrimonio/**) · 0104 (MWART) · 0093 (multi-tenant)
//
// Dois números do protótipo NÃO renderizam e mostram `—`: valor residual (a depreciação
// nunca é calculada, e a regra é decisão [W] em aberto) e custo de manutenção (a tabela
// não tem coluna de valor). Ver RUNBOOK §3 — não invente fórmula pra preenchê-los.
import * as React from 'react';
import { Deferred, router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { useBusiness } from '@/Hooks/usePageProps';
import { PageHeader } from '@/Components/PageHeader';
import { Icon } from '@/Components/Icon';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
// ADR 0253 — layout é COMPOSIÇÃO destes primitivos, nunca `<div className="flex gap-2">` solto.
import { Inline, Grid, Stack } from '@/Components/layout';
import PatrimonioSubNav from './_shared/PatrimonioSubNav';

interface Kpis {
  bruto: number;
  /** `null` quando não há fonte — a depreciação não é calculada (RESÍDUO 6, decisão [W]). */
  valorResidual: number | null;
  unidades: number;
  totalBens: number;
  alocados: number;
  alocaveis: number;
  garantiaCritica: number;
}
interface Categoria { categoria: string; unidades: number; valor: number }
type BaldeKey = 'vigente' | 'vencendo' | 'vencida' | 'sem';
interface Balde { balde: BaldeKey; bens: number; valor: number }
interface Manutencao {
  id: number; bem: string | null; codigo: string | null;
  status: string | null; abertaEm: string | null;
  /** `null` sempre, hoje: `asset_maintenances` não tem coluna de custo (RESÍDUO 3). */
  custo: number | null;
}
interface MeusBens { alocado: number; porCategoria: Array<{ categoria: string; quantidade: number }> }

interface Props {
  is_admin: boolean;
  pode: { ver: boolean; criar: boolean };
  apurado_em: string;
  kpis?: Kpis | null;
  porCategoria?: Categoria[] | null;
  garantia?: Balde[] | null;
  manutencoes?: Manutencao[] | null;
  meusBens?: MeusBens | null;
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

/** `quantity` é decimal(22,4) e estes cards somam QUANTIDADE, não contam registros —
 *  arredondar pra inteiro perderia dado real (fração de unidade existe no cadastro). */
const qtd = (v: number) => v.toLocaleString('pt-BR', { maximumFractionDigits: 4 });

/** O traço do número sem fonte. Ver o cabeçalho deste arquivo. */
const SEM_FONTE = '—';

const BALDE_ROTULO: Record<BaldeKey, string> = {
  vigente: 'Na garantia',
  vencendo: 'Vence em 30 dias',
  vencida: 'Vencida',
  // Charter R3: bem sem registro de garantia NÃO é "vencida" — é outra coisa.
  sem: 'Sem garantia',
};

const BALDE_BARRA: Record<BaldeKey, string> = {
  vigente: 'bg-success',
  vencendo: 'bg-warning',
  vencida: 'bg-destructive',
  sem: 'bg-muted-foreground',
};

function Esqueleto({ linhas = 3 }: { linhas?: number }) {
  return (
    <div className="space-y-2" aria-hidden>
      {Array.from({ length: linhas }).map((_, i) => (
        <div key={i} className="h-4 rounded bg-muted animate-pulse" />
      ))}
    </div>
  );
}

function Painel({ titulo, descricao, children }: { titulo: string; descricao: string; children: React.ReactNode }) {
  return (
    <Card>
      <CardContent className="space-y-3 p-4">
        <div>
          <h2 className="text-sm font-semibold text-foreground">{titulo}</h2>
          <p className="text-xs text-muted-foreground">{descricao}</p>
        </div>
        {children}
      </CardContent>
    </Card>
  );
}

/** Barra proporcional — o protótipo desenha barras, não gráfico de biblioteca. */
function Barra({ rotulo, pct, valor, cor = 'bg-primary' }: { rotulo: string; pct: number; valor: string; cor?: string }) {
  return (
    <div className="space-y-1">
      <Inline align="baseline" justify="between" gap={2} className="text-xs">
        <span className="min-w-0 truncate text-muted-foreground">{rotulo}</span>
        <span className="shrink-0 tabular-nums text-foreground">{valor}</span>
      </Inline>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted">
        <div className={`h-full ${cor}`} style={{ width: `${Math.min(100, Math.max(0, pct))}%` }} />
      </div>
    </div>
  );
}

/** Linha do "O que fazer primeiro" — espelha o `AcaoRow` do protótipo
 *  (`chat-jana.jsx:403`, consumido por `MP.Acoes`): ícone · (título + sub) · CTA à direita. */
function Acao({
  titulo,
  sub,
  icone,
  cta,
  href,
}: {
  titulo: string;
  sub: string;
  icone: string;
  cta: string;
  href: string;
}) {
  return (
    <Card>
      <CardContent className="p-3">
        <Inline gap={3} align="center" justify="between">
          <Inline gap={3} align="center" className="min-w-0">
            <span
              aria-hidden
              className="inline-flex size-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"
            >
              <Icon name={icone} size={16} strokeWidth={1.8} />
            </span>
            {/* `min-w-0` não é enfeite: sem ele o flex não encolhe abaixo do min-content e o
                título longo vaza pra fora do card (§5 2026-08-24). */}
            <div className="min-w-0">
              <p className="truncate text-sm font-medium text-foreground">{titulo}</p>
              <p className="truncate text-xs text-muted-foreground">{sub}</p>
            </div>
          </Inline>
          <Button variant="outline" size="sm" asChild className="shrink-0">
            <a href={href}>{cta}</a>
          </Button>
        </Inline>
      </CardContent>
    </Card>
  );
}

/**
 * Eyebrow de contexto — o `contexto` do `MP.Header` (`modulo-padrao.jsx:18`), que o
 * `CliPageHead` renderiza como `<p>` IRMÃO ACIMA do header quando `contextoWrap` é pedido
 * (`cli-pagehead.jsx:89`), e o Patrimônio pede (`modulo-padrao.jsx:32`). É irmão porque o
 * slot `context` do DS é `nowrap`+`ellipsis` e ENGOLIA a contagem operacional — o docblock
 * do `CliPageHead` mede a perda ("14 folhas · 11 pendentes" sumindo) e conclui que a
 * contagem é conteúdo de domínio, não decoração. O `PageHeader` canon daqui não tem slot
 * nenhum pra isso, então a mesma solução: irmão, não override do canon.
 *
 * Tokens do protótipo (`cli-pagehead.jsx:90-92`) traduzidos pro vocabulário do repo:
 * mono 11px/500, uppercase, tracking .04em, cor dim, `text-wrap: pretty`.
 *
 * `-mb-5` compensa o `pt-6` (24px) do `PageHeader` canon, como o `-11px` do protótipo
 * compensa os 14px do `<header>` do DS: sem isso o eyebrow fica a 24px do título, contra
 * os ~3px que o legado tinha (`chat-jana.css:45`).
 */
function LinhaDeContexto({ partes }: { partes: Array<string | null | undefined> }) {
  // O filtro é do protótipo, não conveniência: `contexto.filter(c => c != null && c !== '')`
  // (`cli-pagehead.jsx:79`). Pedaço ausente SAI do join em vez de virar separador órfão —
  // é o que sustenta a contagem de bens chegar depois (prop deferida) sem quebrar a linha.
  const linha = partes.filter((p): p is string => p != null && p !== '').join(' · ');
  if (!linha) return null;

  return (
    <p className="-mb-5 font-mono text-[11px] font-medium uppercase tracking-[0.04em] text-muted-foreground [text-wrap:pretty]">
      {linha}
    </p>
  );
}

/**
 * Pílula de frescor — o `atualizadoAs` + `onRefresh` do `MP.Header`. Vai como PRIMEIRO item
 * de `actions`, e a posição não é escolha minha: o `CliPageHead` monta `{frescor}{acoes}`
 * (`:159`) depois de medir que pô-la no slot `freshness` do DS roubava largura do título
 * permanentemente (`:30-37` — o h1 de `repair` truncava em 218×173).
 *
 * Clicável porque o protótipo passa `onRefresh` (`patrimonio-page.jsx:822`), e aqui existe
 * o equivalente honesto: `router.reload()` refaz o request, e o `apurado_em` é
 * `now()->toIso8601String()` no controller (`AssetController:643`) — a hora do selo muda
 * porque a apuração aconteceu de verdade, não porque um `setState` a reescreveu.
 * A copy do título é a literal do protótipo (`modulo-padrao.jsx:34` `refreshTitle`).
 */
function PilulaFrescor({ hora }: { hora: string }) {
  return (
    <button
      type="button"
      onClick={() => router.reload()}
      title="Reapurar agora"
      className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted px-2 py-0.5 text-[11px] text-muted-foreground transition-colors hover:text-foreground"
    >
      <span aria-hidden className="size-1.5 rounded-full bg-success" />
      Atualizado {hora}
    </button>
  );
}

export default function Index({ is_admin, pode, apurado_em, kpis, porCategoria, garantia, manutencoes, meusBens }: Props) {
  const apurado = new Date(apurado_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  // Só HH:MM no selo do header — é o formato do protótipo (`patrimonio-page.jsx:822`,
  // `toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'})`). O carimbo COMPLETO
  // segue no "Resumo de hoje": o protótipo tem os dois (`MP.Resumo quando=` :215), e um
  // não substitui o outro.
  const apuradoHora = new Date(apurado_em).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

  // Nome do negócio: prop COMPARTILHADA do shell, eager (`HandleInertiaRequests:85` — valor
  // direto, não closure). Pelo hook canon, não `usePage()` cru: é o idioma do repo
  // (`Hooks/usePageProps.ts:12`, usado por `Ponto/Welcome.tsx:20`) e o que tipa `SharedProps`.
  // Optional chaining porque o payload é `null` sem sessão de business.
  const negocio = useBusiness()?.name;

  return (
    <AppShellV2 title="Patrimônio">
      <div className="mx-auto max-w-7xl space-y-4 p-6">
        <div data-contract="cabecalho">
          {/* O `contexto` do protótipo tem TRÊS pedaços — `["OFFICEIMPRESSO", locais, "N bens"]`
              (`patrimonio-page.jsx:820`). Descem DOIS, e a ausência do terceiro é medida, não
              esquecimento: `permitted_locations()` existe no backend (o índice de Bens o usa,
              `AssetController:349`) mas NÃO chega a esta página — 0 ocorrências de local/locais
              em todo o `share()` do `HandleInertiaRequests` (medido: `grep -ic` sobre :46-205).
              E buscá-lo seria pior que omiti-lo: os KPIs deste painel filtram só `business_id`
              (`painelKpis:653`), então escrever "TODOS OS LOCAIS" afirmaria um escopo de
              permissão que a tela não aplica, e escrever os locais restritos do usuário MENTIRIA
              sobre a abrangência de números que somam o business inteiro. É a mesma recusa que
              esta tela já fez 3× (chip `Auditoria`, `?garantia=`, subtítulo do drill).
              Reabrir isso é fechar a R4 do contrato Cowork nos KPIs primeiro — decisão [W].

              A contagem de bens vem de prop DEFERIDA: no 1º paint `kpis` é `undefined`, o pedaço
              sai do join (o `filter` que o protótipo já tem) e a linha nasce com o negócio só. */}
          <LinhaDeContexto partes={[negocio, kpis ? `${kpis.totalBens} bens` : null]} />
          {/* Canon v3.8 (ADR 0189/0190). O ícone entra por `leading` — o slot existe no canon
              justamente porque o PT-04 R6 descreve o header como "ícone · título · descrição"
              (`PageHeader.tsx:49`), e a Jana PERDEU o dot da área ao migrar sem ele. Idioma
              copiado da irmã de PT-04 `Home/Index.tsx:252`, não inventado aqui. */}
          <PageHeader
            leading={
              <span
                aria-hidden
                className="mr-2 inline-flex translate-y-[1px] align-middle text-muted-foreground"
              >
                {/* `database` NÃO é escolha minha: é o `glyph` que o contrato de tela do
                    Cowork declara pra esta seção (`contrato/patrimonio.contract.json`,
                    seção `header`, `copy.glyph`). O `boxes` que estava aqui era herança do
                    header antigo, e sobreviveu à migração porque eu portei o ícone sem
                    conferir a especificação — que nunca tinha descido pro repo. */}
                <Icon name="database" size={18} strokeWidth={1.8} />
              </span>
            }
            title="Patrimônio"
            subtitle="O que a empresa tem, quanto vale e quem está com o quê."
            /* Os TRÊS elementos que o contrato de tela do Cowork declara pra seção `header`
               (`contrato/patrimonio.contract.json` → `elementos`), na ordem dele:
                 · busca    placeholder literal do contrato → `/asset/assets?q=`. O índice de
                            Bens LÊ `q` (`AssetController:271` e `:385`): a busca navega.
                 · alocar   `Button variant=ghost`, perm `allocate`.
                 · novo     `Button variant=primary`, perm `create` → `/asset/assets/create`,
                            a mesma rota que `Bens.tsx` já usa.

               DESVIO DECLARADO no destino do `alocar` — o contrato fixa copy, variante e
               permissão, não a rota. O destino óbvio (`/asset/allocation/create`) NÃO serve:
               `AssetAllocationController::create()` só responde dentro de
               `if (request()->ajax())` e cai em `null` fora dele (`:307-323`), ou seja,
               devolve página em branco — é o mesmo achado que `Alocacoes.tsx` já registra.
               Aponta então pra LISTA de alocações, que navega de verdade. O formulário chega
               com o `D-FORMS` ([W]); até lá o botão leva ao lugar certo sem prometer o form. */
            actions={
              <Inline gap={2} align="center">
                {/* PRIMEIRO item de `actions` — ordem do `CliPageHead:159` (`{frescor}{acoes}`),
                    não preferência minha. Antes da busca e dos dois botões. */}
                <PilulaFrescor hora={apuradoHora} />
                <form method="GET" action="/asset/assets" role="search">
                  <Input
                    type="search"
                    name="q"
                    aria-label="Buscar bem, código ou número de série"
                    placeholder="Buscar bem, código, série..."
                    className="h-8 w-56"
                  />
                </form>
                <Button variant="ghost" size="sm" asChild>
                  <a href="/asset/allocation">Alocar recurso</a>
                </Button>
                {pode.criar ? (
                  <Button size="sm" asChild>
                    <a href="/asset/assets/create">Adicionar recurso</a>
                  </Button>
                ) : null}
              </Inline>
            }
            /* `-mx-6` NÃO é enfeite, e remover reintroduz o defeito: o canon tem `px-6`
               PRÓPRIO no div interno e este container já é `p-6`, então os dois SOMAM. O
               header ANTIGO não tinha padding horizontal nenhum, e por isso alinhava sem
               ajuste — a migração, sozinha, empurrou o título 24px pra dentro. MEDIDO no
               render real (getBoundingClientRect), não no olho: sem isto o h1 nasce em 48px
               contra 24px dos KPIs; com isto, delta 0 em 1280 E em 1600, sem scroll
               horizontal, e a linha divisora fica full-width — que é o que o canon flat
               v3.8 desenha. */
            className="-mx-6"
          />
        </div>

        {/* Linha própria full-width, como no protótipo (e como a Unificada, ADR 0313). */}
        <div data-contract="subnav" className="border-b border-border pb-1">
          <PatrimonioSubNav active="dashboard" />
        </div>

        {/* Resumo de hoje. A 2ª frase do protótipo cita equipamentos e um custo por peça
            que não existem no banco (cenário do mock) — omitida, não imitada. RUNBOOK §4. */}
        <div data-contract="resumo">
          <Card>
            <CardContent className="space-y-1 p-4">
              <Inline align="baseline" justify="between" gap={2}>
                <h2 className="text-sm font-semibold text-foreground">Resumo de hoje</h2>
                <span className="text-xs tabular-nums text-muted-foreground">{apurado}</span>
              </Inline>
              <Deferred data="kpis" fallback={<Esqueleto linhas={2} />}>
                {kpis ? (
                  <p className="text-sm leading-relaxed text-muted-foreground">
                    <b className="text-foreground">{kpis.totalBens}</b> bens cadastrados,{' '}
                    <b className="text-foreground">{qtd(kpis.unidades)} unidades</b> e{' '}
                    <b className="text-foreground">{brl(kpis.bruto)}</b> de patrimônio bruto. O valor
                    residual depois da depreciação não é calculado pelo sistema ({SEM_FONTE}).{' '}
                    {kpis.garantiaCritica > 0 ? (
                      <>Hoje pesa a <b className="text-foreground">cobertura</b>: {kpis.garantiaCritica} bens
                      com garantia vencida ou vencendo em até 30 dias.</>
                    ) : (
                      <>Nenhum bem com garantia vencida ou vencendo nos próximos 30 dias.</>
                    )}
                  </p>
                ) : (
                  <p className="text-sm text-muted-foreground">
                    Resumo disponível para quem administra o patrimônio.
                  </p>
                )}
              </Deferred>

              {/* Chips do protótipo (`patrimonio-page.jsx:224-229`), atrás do mesmo separador
                  que ele desenha (`modulo-padrao.jsx:58` `jc-brief-sep`). São TRÊS, não quatro:
                  o 4º do protótipo é `Auditoria`, e o charter declara em Non-Goals que a rota
                  NÃO existe (`D-AUDITORIA`) — renderizá-lo seria a afordância falsa que a tela
                  de Bens já recusou ao derivar as abas do menu. Os três aqui navegam de fato;
                  as rotas estão em `Routes/web.php:13-22`.
                  `Garantia crítica` leva à lista de Bens sem pré-filtro: o filtro por garantia
                  do protótipo não existe no índice (`AssetController` lê `q`, `location_id`,
                  `category_id`, `purchase_type`, `is_allocatable` — não garantia). Levar ao
                  lugar certo sem filtrar é honesto; inventar `?garantia=` seria um parâmetro
                  que o backend ignora em silêncio. */}
              {is_admin ? (
                <>
                  <div className="mt-3 border-t border-border" />
                  <Inline gap={2} align="center" className="flex-wrap pt-3">
                    <Button variant="outline" size="sm" asChild>
                      <a href="/asset/assets">Garantia crítica</a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                      <a href="/asset/asset-maintenance">Em manutenção</a>
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                      <a href="/asset/allocation">Alocados</a>
                    </Button>
                  </Inline>
                </>
              ) : null}
            </CardContent>
          </Card>
        </div>

        {/* ── 4 KPIs ───────────────────────────────────────────────────────────────── */}
        <div data-contract="kpis">
          <Deferred data="kpis" fallback={<Esqueleto linhas={4} />}>
            {kpis ? (
              <KpiGrid cols={4}>
                <KpiCard
                  label="Patrimônio bruto"
                  value={brl(kpis.bruto)}
                  icon="coins"
                  description={`${qtd(kpis.unidades)} unidades em ${kpis.totalBens} bens`}
                />
                <KpiCard
                  label="Valor residual"
                  value={kpis.valorResidual === null ? SEM_FONTE : brl(kpis.valorResidual)}
                  icon="trending-down"
                  description="a regra de depreciação ainda não foi definida"
                />
                <KpiCard
                  label="Alocados"
                  value={`${qtd(kpis.alocados)} de ${qtd(kpis.alocaveis)}`}
                  icon="target"
                  description={`${qtd(Math.max(0, kpis.alocaveis - kpis.alocados))} unidades livres pra alocar`}
                />
                <KpiCard
                  label="Garantia vencida ou vencendo"
                  value={kpis.garantiaCritica}
                  icon="triangle-alert"
                  tone={kpis.garantiaCritica > 0 ? 'warning' : 'success'}
                  description={kpis.garantiaCritica > 0 ? 'precisa de cotação de contrato' : 'tudo coberto'}
                />
              </KpiGrid>
            ) : (
              <Deferred data="meusBens" fallback={<Esqueleto linhas={2} />}>
                <KpiGrid cols={2}>
                  <KpiCard label="Bens alocados a você" value={qtd(meusBens?.alocado ?? 0)} icon="target" />
                  <KpiCard label="Categorias" value={meusBens?.porCategoria.length ?? 0} icon="list" />
                </KpiGrid>
              </Deferred>
            )}
          </Deferred>
        </div>

        {/* ── 3 blocos de análise ──────────────────────────────────────────────────── */}
        {is_admin && (
          <Stack gap={3}>
            {/* Cabeçalho de seção do protótipo (`patrimonio-page.jsx:231` · `MP.Secao`, que
                renderiza `<h2 class="jc-h2">` com ícone + título). O SUBTÍTULO dele — "clique
                num card pra ver de onde vem o número" — NÃO desce: ele promete o drill
                (`MP.Drill`, `:234`), e a produção não tem essa tela. Copy que promete
                interação inexistente é afordância falsa em forma de texto. */}
            <Inline gap={2} align="center">
              <span aria-hidden className="inline-flex text-muted-foreground">
                <Icon name="chart-bar" size={15} strokeWidth={1.8} />
              </span>
              <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                Análises do módulo
              </h2>
            </Inline>
            <Grid data-contract="analises" cols={3} gap={4}>
            <Painel titulo="Patrimônio por categoria" descricao="valor unitário × quantidade, por categoria">
              <Deferred data="porCategoria" fallback={<Esqueleto />}>
                {porCategoria?.length ? (
                  <div className="space-y-2.5">
                    {porCategoria.map((c) => (
                      <Barra
                        key={c.categoria}
                        rotulo={c.categoria}
                        pct={kpis?.bruto ? (c.valor / kpis.bruto) * 100 : 0}
                        valor={brl(c.valor)}
                      />
                    ))}
                  </div>
                ) : (
                  <EmptyState
                    icon="boxes"
                    title="Nenhum bem cadastrado"
                    description="O painel passa a mostrar valor assim que o primeiro bem entrar."
                  />
                )}
              </Deferred>
            </Painel>

            <Painel titulo="Situação da garantia" descricao="bem sem registro não conta como vencido">
              <Deferred data="garantia" fallback={<Esqueleto />}>
                {garantia?.some((b) => b.bens > 0) ? (
                  <div className="space-y-2.5">
                    {garantia.map((b) => {
                      const total = garantia.reduce((s, x) => s + x.bens, 0);
                      return (
                        <Barra
                          key={b.balde}
                          rotulo={BALDE_ROTULO[b.balde]}
                          pct={total ? (b.bens / total) * 100 : 0}
                          valor={`${b.bens} · ${brl(b.valor)}`}
                          cor={BALDE_BARRA[b.balde]}
                        />
                      );
                    })}
                  </div>
                ) : (
                  <EmptyState
                    icon="shield"
                    title="Sem bens para avaliar"
                    description="A situação da garantia aparece quando houver bem cadastrado."
                  />
                )}
              </Deferred>
            </Painel>

            <Painel titulo="Manutenção em aberto" descricao="o que está fora de operação ou agendado">
              <Deferred data="manutencoes" fallback={<Esqueleto />}>
                {manutencoes?.length ? (
                  <>
                    <ul className="space-y-2">
                      {manutencoes.map((m) => (
                        <Inline key={m.id} asChild align="baseline" justify="between" gap={2} className="text-xs">
                          <li>
                            <span className="min-w-0 truncate text-muted-foreground">
                              {m.abertaEm ? new Date(m.abertaEm).toLocaleDateString('pt-BR') : SEM_FONTE} · {m.bem ?? SEM_FONTE}
                            </span>
                            <span className="shrink-0 tabular-nums text-foreground">
                              {m.custo === null ? SEM_FONTE : brl(m.custo)}
                            </span>
                          </li>
                        </Inline>
                      ))}
                    </ul>
                    {/* Sem coluna de custo em asset_maintenances: o total é `—`, não zero —
                        zero afirmaria que não se gastou nada, e isso não é o que se sabe. */}
                    <p className="border-t border-border pt-2 text-xs text-muted-foreground">
                      custo de manutenção no ano <b className="tabular-nums text-foreground">{SEM_FONTE}</b>
                      <span className="block text-[11px]">o sistema ainda não registra o custo de cada manutenção</span>
                    </p>
                  </>
                ) : (
                  <EmptyState
                    icon="settings"
                    title="Nada em manutenção"
                    description="Nenhuma manutenção aberta ou em andamento."
                  />
                )}
              </Deferred>
            </Painel>
            </Grid>

            {/* ── O QUE FAZER PRIMEIRO (protótipo `:233` + `MP.Acoes` `:111`) ──────────
                A AFORDÂNCIA desce; a COPY do protótipo NÃO. Lá o texto nomeia um equipamento
                específico e crava o custo da peça em reais — é cenário do mock (e citar o
                valor aqui reprovaria no `brl-scan`, com razão: número em real não entra no
                git). O `Index-visual-comparison.md` já fixou o precedente ao recusar a
                2ª frase do Resumo pelo mesmo motivo: prosa de protótipo que cita número
                específico é dado de mock até prova em contrário. Aqui cada linha é derivada
                dos MESMOS agregados que os KPIs usam.
                As três rotas existem (`Routes/web.php:13-22`) — é o que separa este bloco
                do chip `Auditoria`, que ficou de fora. */}
            <Stack gap={3} data-contract="acoes">
              <Inline gap={2} align="center">
                <span aria-hidden className="inline-flex text-muted-foreground">
                  <Icon name="lightbulb" size={15} strokeWidth={1.8} />
                </span>
                <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  O que fazer primeiro
                </h2>
              </Inline>
              <Deferred data="kpis" fallback={<Esqueleto linhas={3} />}>
                {kpis ? (
                  <Stack gap={2}>
                    <Acao
                      titulo={
                        kpis.garantiaCritica > 0
                          ? `${kpis.garantiaCritica} ${kpis.garantiaCritica === 1 ? 'bem' : 'bens'} sem cobertura de garantia`
                          : 'Nenhum bem com garantia vencida ou vencendo'
                      }
                      sub={
                        kpis.garantiaCritica > 0
                          ? 'Sem contrato, cada conserto sai integral do caixa.'
                          : 'Tudo coberto nos próximos 30 dias.'
                      }
                      icone="shield"
                      cta="Ver bens"
                      href="/asset/assets"
                    />
                    <Acao
                      titulo={`${manutencoes?.length ?? 0} ${(manutencoes?.length ?? 0) === 1 ? 'manutenção' : 'manutenções'} em aberto`}
                      sub="O que está fora de operação não aparece como disponível pra alocar."
                      icone="wrench"
                      cta="Ver manutenções"
                      href="/asset/asset-maintenance"
                    />
                    <Acao
                      titulo={`${qtd(Math.max(0, kpis.alocaveis - kpis.alocados))} unidades alocáveis paradas`}
                      sub="Equipamento sem alocação não tem responsável registrado."
                      icone="target"
                      cta="Ver alocações"
                      href="/asset/allocation"
                    />
                  </Stack>
                ) : null}
              </Deferred>
            </Stack>
          </Stack>
        )}

        {/* Ramo não-admin: o painel Blade já mostrava "seus bens" — capacidade preservada. */}
        {!is_admin && (
          <div data-contract="meus-bens">
            <Painel titulo="Bens alocados a você" descricao="o que está sob sua responsabilidade">
              <Deferred data="meusBens" fallback={<Esqueleto />}>
                {meusBens?.porCategoria.length ? (
                  <ul className="space-y-2">
                    {meusBens.porCategoria.map((c) => (
                      <Inline key={c.categoria} asChild align="baseline" justify="between" gap={2} className="text-xs">
                        <li>
                          <span className="min-w-0 truncate text-muted-foreground">{c.categoria}</span>
                          <span className="shrink-0 tabular-nums text-foreground">{qtd(c.quantidade)}</span>
                        </li>
                      </Inline>
                    ))}
                  </ul>
                ) : (
                  <EmptyState
                    icon="target"
                    title="Nenhum bem alocado a você"
                    description="Quando alguém alocar um bem no seu nome, ele aparece aqui."
                  />
                )}
              </Deferred>
            </Painel>
            {!pode.ver && (
              <p className="pt-2 text-xs text-muted-foreground">
                Você vê apenas os bens alocados a você. Para ver o patrimônio completo é preciso a
                permissão de visualizar bens.
              </p>
            )}
          </div>
        )}
      </div>
    </AppShellV2>
  );
}
