// @memcofre
//   tela: /project-mgmt/roadmap
//   module: ProjectMgmt
//   stories: US-TR-203 (Roadmap epics x quarters)
//   permissao: copiloto.mcp.usage.all

import AppShellV2 from '@/Layouts/AppShellV2';
import { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { CheckCircle2, Clock, Loader, Target } from 'lucide-react';

interface EpicTaskCounts { total: number; done: number; active: number; percent: number }

interface Epic {
  id: number;
  key: string;
  title: string;
  description: string | null;
  owner: string | null;
  status: 'planning' | 'active' | 'done' | 'cancelled';
  color: string | null;
  tasks: EpicTaskCounts;
}

interface QuarterBucket { key: string; epics: Epic[] }

interface Props {
  project: { id: number; key: string; name: string } | null;
  // quarters/kpis chegam via Inertia::defer (RoadmapController:40-41) → `undefined`
  // no 1º paint. Tipados opcionais + default-guard no destructuring pra NÃO crashar
  // React antes do defer chegar (skill inertia-defer-default, Opção B; espelha
  // OficinaAuto/ServiceOrders/Index.tsx). Sintoma do bug: kpis.total_epics sobre
  // undefined → tela branca.
  quarters?: QuarterBucket[];
  kpis?: { total_epics: number; active_epics: number; planning_epics: number; done_epics: number };
}

// Default-guard pro prop deferred kpis (contadores começam zerados até o defer resolver).
const EMPTY_KPIS = { total_epics: 0, active_epics: 0, planning_epics: 0, done_epics: 0 };

const STATUS_BADGE = {
  planning:  'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
  active:    'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
  done:      'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
  cancelled: 'bg-slate-200 text-slate-400 dark:bg-slate-800 dark:text-slate-500',
};
const STATUS_LABEL = {
  planning:  'planning',
  active:    'ativo',
  done:      'concluído',
  cancelled: 'cancelado',
};

function EpicCard({ e }: { e: Epic }) {
  const StatusIcon = e.status === 'done' ? CheckCircle2 : e.status === 'active' ? Loader : Clock;
  return (
    <Card
      className="bg-card border-l-4"
      style={e.color ? { borderLeftColor: e.color } : { borderLeftColor: '#3b82f6' }}
    >
      <CardContent className="p-3">
        <div className="flex items-start justify-between gap-2 mb-1">
          <span className="text-[10px] font-mono text-muted-foreground">{e.key}</span>
          <span className={`inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded ${STATUS_BADGE[e.status]}`}>
            <StatusIcon size={10} />
            {STATUS_LABEL[e.status]}
          </span>
        </div>
        <p className="text-sm font-medium leading-tight mb-2">{e.title}</p>
        {e.description && (
          <p className="text-[11px] text-muted-foreground line-clamp-2 mb-2">{e.description}</p>
        )}

        <div className="mb-2">
          <div className="flex items-center justify-between text-[10px] mb-0.5">
            <span className="text-muted-foreground font-mono">{e.tasks.done}/{e.tasks.total}</span>
            <span className="font-semibold tabular-nums">{e.tasks.percent}%</span>
          </div>
          <div className="h-1.5 rounded-full bg-muted overflow-hidden">
            <div className="h-full bg-emerald-500 transition-all" style={{ width: `${e.tasks.percent}%` }} />
          </div>
        </div>

        <div className="flex items-center gap-2 text-[10px] text-muted-foreground">
          {e.owner && <span>@{e.owner}</span>}
          {e.tasks.active > 0 && <span>· {e.tasks.active} ativas</span>}
        </div>
      </CardContent>
    </Card>
  );
}

function RoadmapIndex({ project, quarters = [], kpis = EMPTY_KPIS }: Props) {
  return (
    <>
      <PageHeader
        icon="CalendarRange"
        title={project ? `${project.name} — Roadmap` : 'Roadmap'}
        description={`${kpis.total_epics} epics · ${kpis.active_epics} ativos · ${kpis.planning_epics} em planning · ${kpis.done_epics} concluídos`}
      />

      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="Target" tone="default" label="Total epics" value={String(kpis.total_epics)} />
        <KpiCard icon="Loader" tone="info" label="Ativos" value={String(kpis.active_epics)} />
        <KpiCard icon="Clock" tone="default" label="Planning" value={String(kpis.planning_epics)} />
        <KpiCard icon="CircleCheck" tone="success" label="Concluídos" value={String(kpis.done_epics)} />
      </KpiGrid>

      {quarters.length === 0 ? (
        <Card className="mt-6">
          <CardContent className="py-12 text-center text-muted-foreground">
            <Target size={28} className="mx-auto mb-3 opacity-40" />
            <p className="text-sm">Nenhum epic cadastrado neste projeto.</p>
            <p className="text-[11px] mt-1">Use <code className="font-mono">epics-create</code> via MCP pra organizar tasks.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="mt-6 flex gap-4 overflow-x-auto pb-4">
          {quarters.map((q) => (
            <div key={q.key} className="min-w-[280px] max-w-[320px]">
              <header className="flex items-center justify-between mb-2 px-1">
                <h3 className="text-sm font-semibold uppercase tracking-wide">{q.key}</h3>
                <Badge variant="outline" className="font-mono text-[10px]">{q.epics.length}</Badge>
              </header>
              <div className="flex flex-col gap-2">
                {q.epics.map((e) => <EpicCard key={e.id} e={e} />)}
              </div>
            </div>
          ))}
        </div>
      )}

      <p className="mt-4 text-xs text-muted-foreground">
        Editar <code className="font-mono">target_quarter</code> de um epic via MCP{' '}
        <code className="font-mono">epics-update key:&lt;EPIC&gt; target_quarter:2026-Q3</code>.
      </p>
    </>
  );
}

RoadmapIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Roadmap"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Roadmap' }]}
  >
    {page}
  </AppShellV2>
);

export default RoadmapIndex;
