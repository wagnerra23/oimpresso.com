// @memcofre
//   tela: /project-mgmt/activity
//   module: ProjectMgmt
//   stories: US-TR-205 (Activity feed timeline)
//   permissao: copiloto.mcp.usage.all

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import {
  Activity as ActivityIcon, ArrowRight, ChevronsRight, GitCommit, MessageSquare,
  Plus, RefreshCw, UserPlus,
} from 'lucide-react';

interface Event {
  id: number;
  task_id: string;
  task_title: string | null;
  event_type: string;
  from_value: string | null;
  to_value: string | null;
  author: string | null;
  note: string | null;
  created_at: string | null;
}

interface Props {
  project: { id: number; key: string; name: string } | null;
  events: Event[];
  kpis: { last_24h: number; last_7d: number; created: number; completed: number };
  authors: string[];
  event_types: string[];
  filters: { type: string | null; author: string | null; task: string | null; days: number };
}

const ALL = '__all__';

const EVENT_ICON: Record<string, any> = {
  created:        Plus,
  status_changed: ChevronsRight,
  assigned:       UserPlus,
  commented:      MessageSquare,
  field_updated:  RefreshCw,
};
const EVENT_LABEL: Record<string, string> = {
  created:        'criou',
  status_changed: 'mudou status',
  assigned:       'atribuiu',
  commented:      'comentou',
  field_updated:  'atualizou',
};

function timeAgo(iso: string | null): string {
  if (!iso) return '';
  const d = new Date(iso);
  const diff = Date.now() - d.getTime();
  const min = Math.round(diff / 60_000);
  if (min < 1) return 'agora';
  if (min < 60) return `${min}m`;
  const h = Math.round(min / 60);
  if (h < 24) return `${h}h`;
  const days = Math.round(h / 24);
  if (days < 30) return `${days}d`;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function groupByDay(events: Event[]): Record<string, Event[]> {
  const out: Record<string, Event[]> = {};
  events.forEach((e) => {
    if (!e.created_at) return;
    const day = new Date(e.created_at).toISOString().slice(0, 10);
    out[day] ??= [];
    out[day].push(e);
  });
  return out;
}

function dayLabel(iso: string): string {
  const d = new Date(iso + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  const t0 = today.getTime();
  const diff = Math.round((t0 - d.getTime()) / 86400_000);
  if (diff === 0) return 'Hoje';
  if (diff === 1) return 'Ontem';
  if (diff < 7) return `${diff} dias atrás`;
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function ActivityIndex({ project, events, kpis, authors, event_types, filters }: Props) {
  const grouped = groupByDay(events);
  const days = Object.keys(grouped).sort().reverse();

  function aplicar(patch: Record<string, string | null>) {
    const params: Record<string, string> = {};
    const merged = { ...filters, ...patch };
    if (merged.type && merged.type !== ALL) params.type = merged.type;
    if (merged.author && merged.author !== ALL) params.author = merged.author;
    if (merged.task) params.task = String(merged.task);
    if (merged.days) params.days = String(merged.days);
    router.get('/project-mgmt/activity', params, { preserveScroll: true, preserveState: true, replace: true });
  }

  useEffect(() => {
    const id = setInterval(
      () => router.reload({ only: ['events', 'kpis'], preserveScroll: true }),
      30_000,
    );
    return () => clearInterval(id);
  }, []);

  return (
    <>
      <PageHeader
        icon="Activity"
        title={project ? `${project.name} — Activity` : 'Activity'}
        description={`${events.length} eventos · últimos ${filters.days} dias`}
      />

      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="Zap" tone="default" label="Últimas 24h" value={String(kpis.last_24h)} />
        <KpiCard icon="Clock" tone="default" label="Últimos 7d" value={String(kpis.last_7d)} />
        <KpiCard icon="Plus" tone="info" label="Criadas" value={String(kpis.created)} />
        <KpiCard icon="CircleCheck" tone="success" label="Concluídas" value={String(kpis.completed)} />
      </KpiGrid>

      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="w-44">
            <Label className="text-xs">Tipo</Label>
            <Select value={filters.type ?? ALL} onValueChange={(v) => aplicar({ type: v })}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {event_types.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-40">
            <Label className="text-xs">Autor</Label>
            <Select value={filters.author ?? ALL} onValueChange={(v) => aplicar({ author: v })}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {authors.map((a) => <SelectItem key={a} value={a}>{a}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
          <div className="w-32">
            <Label className="text-xs">Período</Label>
            <Select value={String(filters.days)} onValueChange={(v) => aplicar({ days: v })}>
              <SelectTrigger className="h-8"><SelectValue /></SelectTrigger>
              <SelectContent>
                <SelectItem value="1">Últimas 24h</SelectItem>
                <SelectItem value="7">7 dias</SelectItem>
                <SelectItem value="14">14 dias</SelectItem>
                <SelectItem value="30">30 dias</SelectItem>
                <SelectItem value="90">90 dias</SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div className="w-40">
            <Label className="text-xs">Task</Label>
            <Input
              defaultValue={filters.task ?? ''}
              placeholder="ex: COPI-22"
              onBlur={(e) => aplicar({ task: e.target.value || null })}
              className="h-8 text-xs uppercase"
            />
          </div>
          {(filters.type || filters.author || filters.task || filters.days !== 7) && (
            <Button variant="ghost" onClick={() => router.get('/project-mgmt/activity', {}, { preserveScroll: true })} className="h-8 text-xs">
              Limpar
            </Button>
          )}
        </CardContent>
      </Card>

      {events.length === 0 ? (
        <Card className="mt-4">
          <CardContent className="py-12 text-center text-muted-foreground">
            <ActivityIcon size={28} className="mx-auto mb-3 opacity-40" />
            <p className="text-sm">Sem eventos no período/filtros.</p>
          </CardContent>
        </Card>
      ) : (
        <div className="mt-4 flex flex-col gap-4">
          {days.map((day) => (
            <Card key={day}>
              <CardContent className="py-3 px-4">
                <header className="flex items-center justify-between mb-3">
                  <h3 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {dayLabel(day)}
                  </h3>
                  <span className="text-[10px] font-mono text-muted-foreground">{grouped[day].length}</span>
                </header>
                <ul className="flex flex-col gap-2 border-l-2 border-muted pl-4 ml-1">
                  {grouped[day].map((e) => {
                    const Icon = EVENT_ICON[e.event_type] ?? GitCommit;
                    const labelTipo = EVENT_LABEL[e.event_type] ?? e.event_type;
                    return (
                      <li key={e.id} className="flex items-start gap-2 text-xs relative">
                        <span className="absolute -left-[22px] top-0.5 bg-background rounded-full p-0.5">
                          <Icon size={12} className="text-muted-foreground" />
                        </span>
                        <div className="flex-1 min-w-0">
                          <p className="leading-tight">
                            <span className="font-semibold">{e.author ?? 'sistema'}</span>{' '}
                            <span className="text-muted-foreground">{labelTipo}</span>{' '}
                            <span className="font-mono text-[10px] px-1 py-0.5 rounded bg-muted">{e.task_id}</span>
                            {e.task_title && <span className="text-muted-foreground"> · {e.task_title}</span>}
                          </p>
                          {e.event_type === 'status_changed' && (
                            <p className="text-[10px] text-muted-foreground mt-0.5 inline-flex items-center gap-1">
                              <span className="px-1 py-0.5 rounded bg-muted/60">{e.from_value ?? '?'}</span>
                              <ArrowRight size={9} />
                              <span className="px-1 py-0.5 rounded bg-success-soft text-success-fg border border-success/20">{e.to_value ?? '?'}</span>
                            </p>
                          )}
                          {e.note && (
                            <p className="text-[11px] text-muted-foreground mt-0.5 line-clamp-2">{e.note}</p>
                          )}
                        </div>
                        <span className="text-[10px] text-muted-foreground/70 shrink-0">{timeAgo(e.created_at)}</span>
                      </li>
                    );
                  })}
                </ul>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}

ActivityIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Activity"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Activity' }]}
  >
    {page}
  </AppShellV2>
);

export default ActivityIndex;
