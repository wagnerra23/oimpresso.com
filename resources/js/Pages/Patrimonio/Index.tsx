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
import { Deferred } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Card, CardContent } from '@/Components/ui/card';
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
      <div className="flex items-baseline justify-between gap-2 text-xs">
        <span className="min-w-0 truncate text-muted-foreground">{rotulo}</span>
        <span className="shrink-0 tabular-nums text-foreground">{valor}</span>
      </div>
      <div className="h-1.5 overflow-hidden rounded-full bg-muted">
        <div className={`h-full ${cor}`} style={{ width: `${Math.min(100, Math.max(0, pct))}%` }} />
      </div>
    </div>
  );
}

export default function Index({ is_admin, pode, apurado_em, kpis, porCategoria, garantia, manutencoes, meusBens }: Props) {
  const apurado = new Date(apurado_em).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

  return (
    <AppShellV2 title="Patrimônio">
      <div className="mx-auto max-w-7xl space-y-4 p-6">
        <div data-contract="cabecalho">
          <PageHeader
            title="Patrimônio"
            description="O que a empresa tem, quanto vale e quem está com o quê."
            icon="boxes"
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
              <div className="flex items-baseline justify-between gap-2">
                <h2 className="text-sm font-semibold text-foreground">Resumo de hoje</h2>
                <span className="text-xs tabular-nums text-muted-foreground">{apurado}</span>
              </div>
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
          <div data-contract="analises" className="grid gap-4 lg:grid-cols-3">
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
                        <li key={m.id} className="flex items-baseline justify-between gap-2 text-xs">
                          <span className="min-w-0 truncate text-muted-foreground">
                            {m.abertaEm ? new Date(m.abertaEm).toLocaleDateString('pt-BR') : SEM_FONTE} · {m.bem ?? SEM_FONTE}
                          </span>
                          <span className="shrink-0 tabular-nums text-foreground">
                            {m.custo === null ? SEM_FONTE : brl(m.custo)}
                          </span>
                        </li>
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
          </div>
        )}

        {/* Ramo não-admin: o painel Blade já mostrava "seus bens" — capacidade preservada. */}
        {!is_admin && (
          <div data-contract="meus-bens">
            <Painel titulo="Bens alocados a você" descricao="o que está sob sua responsabilidade">
              <Deferred data="meusBens" fallback={<Esqueleto />}>
                {meusBens?.porCategoria.length ? (
                  <ul className="space-y-2">
                    {meusBens.porCategoria.map((c) => (
                      <li key={c.categoria} className="flex items-baseline justify-between gap-2 text-xs">
                        <span className="min-w-0 truncate text-muted-foreground">{c.categoria}</span>
                        <span className="shrink-0 tabular-nums text-foreground">{qtd(c.quantidade)}</span>
                      </li>
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
