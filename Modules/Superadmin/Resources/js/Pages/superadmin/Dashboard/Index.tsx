// @memcofre
//   tela: /superadmin
//   module: Superadmin
//   stories: SA-O1 (Blade/AdminLTE → Inertia)
//   permissao: superadmin
//
// Visão geral da plataforma. Responde uma pergunta: "está crescendo ou vazando?".
// Charter: ./Index.charter.md (draft) · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/superadmin-page.jsx → ViewVisao() (L599-757)
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-dashboard.md
//
// Os blocos MRR, funil trial→pago, churn e receita por pacote NÃO estão aqui de propósito:
// não têm query no backend, e o protótipo os desenha com mock. Renderizar mock em produção
// seria fabricar número (UC-SADASH-05). Entram na SA-O1b, com query real.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';
import PageHeader from '@/Components/shared/PageHeader';

interface Janela {
  inicio: string;
  fim: string;
  rotulo: string;
}

interface StatsPeriodo {
  new_subscriptions: number | string | null;
  new_registrations: number | string | null;
}

interface PontoTendencia {
  label: string;
  value: number;
}

interface NegocioRecente {
  id: number;
  nome: string;
  criado: string;
  assinatura: string;
}

interface Mrr {
  mrr: number;
  assinaturas: number;
  canceladas: number;
  fonte: string;
}

interface Props {
  periodo: string;
  janela: Janela;
  statsPeriodo?: StatsPeriodo;
  semAssinatura?: number;
  mrr?: Mrr;
  tendencia?: PontoTendencia[];
  recentes?: NegocioRecente[];
}

const PERIODOS = [
  { id: 'hoje', label: 'Hoje' },
  { id: 'semana', label: 'Semana' },
  { id: 'mes', label: 'Mês' },
  { id: 'ano', label: 'Ano' },
] as const;

const brl = (v: number) =>
  v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 0, maximumFractionDigits: 0 });

/** Plural PT-BR sem gambiarra de string (o charter cobra "1 negócio / 2 negócios"). */
const plural = (n: number, sing: string, plur: string) => `${n} ${n === 1 ? sing : plur}`;

function Kpi({ valor, rotulo, nota, tom }: { valor: ReactNode; rotulo: string; nota: string; tom?: 'accent' | 'danger' | 'ok' }) {
  const corValor =
    tom === 'accent'
      ? 'text-primary'
      : tom === 'danger'
        ? 'text-destructive'
        : tom === 'ok'
          ? 'text-emerald-600 dark:text-emerald-400'
          : 'text-foreground';

  return (
    <Card>
      <CardContent className="flex flex-col gap-1 p-4">
        <span className={`text-2xl font-semibold leading-tight tabular-nums ${corValor}`}>{valor}</span>
        <span className="text-xs text-muted-foreground">{rotulo}</span>
        <span className="mt-0.5 text-[11px] text-muted-foreground/80">{nota}</span>
      </CardContent>
    </Card>
  );
}

function KpiEsqueleto() {
  return (
    <Card>
      <CardContent className="flex flex-col gap-2 p-4">
        <Skeleton className="h-7 w-24" />
        <Skeleton className="h-3 w-32" />
        <Skeleton className="h-3 w-40" />
      </CardContent>
    </Card>
  );
}

function SuperadminDashboard({ periodo, janela, statsPeriodo, semAssinatura, mrr, tendencia, recentes }: Props) {
  // Partial reload: só os KPIs do período e a janela voltam do servidor. `semAssinatura`,
  // `tendencia` e `recentes` não dependem do período e ficam de fora do `only`.
  const trocarPeriodo = (novo: string) => {
    if (novo === periodo) return;
    router.reload({ only: ['statsPeriodo', 'janela', 'periodo'], data: { periodo: novo } });
  };

  return (
    <div className="pb-8">
      <PageHeader title="Superadmin" subtitle="Visão geral da plataforma" />

      <div className="flex flex-wrap items-center gap-3 px-6 pt-4" data-contract="superadmin.dashboard.periodo">
        <div className="flex gap-0.5 rounded-md border bg-muted p-0.5" role="group" aria-label="Período">
          {PERIODOS.map((p) => (
            <button
              key={p.id}
              type="button"
              onClick={() => trocarPeriodo(p.id)}
              aria-pressed={periodo === p.id}
              className={
                'h-7 rounded px-3.5 text-xs transition-colors ' +
                (periodo === p.id
                  ? 'bg-background font-semibold text-foreground shadow-sm'
                  : 'text-muted-foreground hover:text-foreground')
              }
            >
              {p.label}
            </button>
          ))}
        </div>
        <span className="text-[11px] text-muted-foreground">{janela.rotulo}</span>
      </div>

      <div className="grid grid-cols-1 gap-3 px-6 pt-4 sm:grid-cols-2 lg:grid-cols-4" data-contract="superadmin.dashboard.kpis">
        <Deferred data="statsPeriodo" fallback={<><KpiEsqueleto /><KpiEsqueleto /></>}>
          <KpisDoPeriodo statsPeriodo={statsPeriodo} />
        </Deferred>

        <Deferred data="semAssinatura" fallback={<KpiEsqueleto />}>
          <KpiSemAssinatura semAssinatura={semAssinatura} />
        </Deferred>

        <Deferred data="mrr" fallback={<KpiEsqueleto />}>
          <KpiMrr mrr={mrr} />
        </Deferred>
      </div>

      <div className="px-6 pt-4" data-contract="superadmin.dashboard.tendencia">
        <Deferred data="tendencia" fallback={<Card><CardContent className="p-4"><Skeleton className="h-44 w-full" /></CardContent></Card>}>
          <Tendencia tendencia={tendencia} />
        </Deferred>
      </div>

      <div className="px-6 pt-4" data-contract="superadmin.dashboard.recentes">
        <Deferred data="recentes" fallback={<Card><CardContent className="p-4"><Skeleton className="h-40 w-full" /></CardContent></Card>}>
          <Recentes recentes={recentes} />
        </Deferred>
      </div>
    </div>
  );
}

/*
 * O <Deferred> controla QUANDO renderizar (segura o filho até a prop chegar) — ele NÃO
 * injeta a prop. Por isso cada bloco recebe o valor explicitamente, como em
 * resources/js/Pages/Auditoria/Index.tsx:355. A prop segue opcional porque o primeiro
 * paint acontece antes de ela existir.
 */

function KpisDoPeriodo({ statsPeriodo }: { statsPeriodo?: StatsPeriodo }) {
  const receita = Number(statsPeriodo?.new_subscriptions ?? 0);
  const cadastros = Number(statsPeriodo?.new_registrations ?? 0);

  return (
    <>
      <Kpi valor={brl(receita)} rotulo="Novas assinaturas" nota="soma do valor contratado na janela" tom="accent" />
      <Kpi valor={cadastros} rotulo="Novos cadastros" nota="cadastro próprio + criados pelo superadmin" />
    </>
  );
}

function KpiSemAssinatura({ semAssinatura }: { semAssinatura?: number }) {
  const n = Number(semAssinatura ?? 0);

  return <Kpi valor={n} rotulo="Sem assinatura" nota="cadastrou e não assinou" tom="danger" />;
}

function KpiMrr({ mrr }: { mrr?: Mrr }) {
  const valor = Number(mrr?.mrr ?? 0);
  const ativas = Number(mrr?.assinaturas ?? 0);
  const canceladas = Number(mrr?.canceladas ?? 0);
  const indisponivel = mrr?.fonte === 'indisponivel';

  // O número vem da cobrança recorrente (rb_subscriptions), não do licenciamento legado
  // (packages/subscriptions) — que está zerado e não cobra ninguém. A nota diz o que
  // sustenta o valor, e denuncia quando a fonte não pôde ser lida.
  const nota = indisponivel
    ? 'fonte de cobrança indisponível neste ambiente'
    : valor > 0
      ? `${plural(ativas, 'assinatura ativa', 'assinaturas ativas')}` +
        (canceladas > 0 ? ` · ${canceladas} cancelada${canceladas === 1 ? '' : 's'} em 30 dias` : '')
      : 'nenhuma assinatura ativa na cobrança recorrente';

  return <Kpi valor={brl(valor)} rotulo="Receita recorrente (MRR)" nota={nota} tom={valor > 0 ? 'ok' : undefined} />;
}

function Tendencia({ tendencia }: { tendencia?: PontoTendencia[] }) {
  const serie = tendencia ?? [];
  const max = serie.reduce((a, p) => Math.max(a, p.value), 0);
  const ultimo = serie.length ? serie[serie.length - 1] : null;

  return (
    <Card>
      <CardContent className="p-0">
        <header className="flex items-baseline justify-between gap-3 border-b px-4 py-3.5">
          <h2 className="text-sm font-semibold">Tendência mensal de assinaturas</h2>
          <span className="text-[11px] text-muted-foreground">
            {serie.length ? `últimos ${plural(serie.length, 'mês', 'meses')}` : 'sem dado no período'}
            {ultimo ? ` · ${brl(ultimo.value)} em ${ultimo.label}` : ''}
          </span>
        </header>

        {serie.length === 0 ? (
          <p className="px-4 py-10 text-center text-sm text-muted-foreground">
            Nenhuma assinatura registrada nos últimos 12 meses.
          </p>
        ) : (
          <div className="flex h-44 items-end gap-2.5 px-4 pb-2 pt-4">
            {serie.map((p, i) => (
              <div key={p.label} className="flex h-full flex-1 flex-col items-center justify-end gap-1.5">
                <div
                  className={'w-full max-w-[34px] rounded-t ' + (i === serie.length - 1 ? 'bg-primary' : 'bg-primary/25')}
                  style={{ height: max > 0 ? `${Math.max((p.value / max) * 100, 2)}%` : '2%' }}
                  title={`${p.label}: ${brl(p.value)}`}
                />
                <span className="text-[10.5px] text-muted-foreground">{p.label}</span>
              </div>
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

function Recentes({ recentes }: { recentes?: NegocioRecente[] }) {
  const linhas = recentes ?? [];

  return (
    <Card>
      <CardContent className="p-0">
        <header className="flex items-baseline justify-between gap-3 border-b px-4 py-3.5">
          <h2 className="text-sm font-semibold">Cadastros recentes</h2>
          <span className="text-[11px] text-muted-foreground">{plural(linhas.length, 'negócio', 'negócios')}</span>
        </header>

        {linhas.length === 0 ? (
          <p className="px-4 py-10 text-center text-sm text-muted-foreground">Nenhum negócio cadastrado ainda.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-xs text-muted-foreground">
                <th className="px-4 py-2 font-medium">Negócio</th>
                <th className="px-4 py-2 font-medium">Assinatura</th>
                <th className="px-4 py-2 font-medium">Cadastro</th>
              </tr>
            </thead>
            <tbody>
              {linhas.map((n) => (
                <tr key={n.id} className="border-b last:border-0">
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="font-medium">{n.nome}</span>
                      <span className="text-[11px] text-muted-foreground">negócio #{n.id}</span>
                    </div>
                  </td>
                  <td className="px-4 py-2.5 text-muted-foreground">{n.assinatura}</td>
                  <td className="px-4 py-2.5 tabular-nums text-muted-foreground">{n.criado}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </CardContent>
    </Card>
  );
}

SuperadminDashboard.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default SuperadminDashboard;
