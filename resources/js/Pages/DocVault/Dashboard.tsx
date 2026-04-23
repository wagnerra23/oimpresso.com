import AppShell from '@/Layouts/AppShell';
import { Link } from '@inertiajs/react';
import {
  BookOpen,
  BookText,
  FileText,
  FolderOpen,
  Inbox,
  Lightbulb,
  Plus,
  Scale,
  Sparkles,
  Target,
  TrendingUp,
  Upload,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';

interface Stats {
  modules_total: number;
  stories_total: number;
  rules_total: number;
  dod_total: number;
  dod_done: number;
  dod_pct: number;
  sources_total: number;
  evidences_pending: number;
  evidences_triaged: number;
  evidences_applied: number;
}

interface Coverage {
  readme: boolean;
  arch: boolean;
  spec: boolean;
  changelog: boolean;
  adrs: number;
  score: number;
}

interface ModuleItem {
  name: string;
  format: 'folder' | 'flat';
  status: string;
  priority: string;
  stories_count: number;
  rules_count: number;
  dod_pct: number;
  coverage: Coverage | null;
  pages_count: number;
  trace_score: number;
  health_score: number | null;
  audit_score: number | null;
}

interface CoverageSummary {
  folder_count: number;
  flat_count: number;
  avg_score: number;
  total_adrs: number;
}

interface RecentSource {
  id: number;
  type: string;
  title: string;
  module: string | null;
  created_at: string | null;
  created_at_human: string | null;
}

interface Props {
  stats: Stats;
  modules: ModuleItem[];
  recent_sources: RecentSource[];
  coverage_summary: CoverageSummary;
  pages_total: number;
}

export default function DocVaultDashboard({ stats, modules, recent_sources, coverage_summary, pages_total }: Props) {
  return (
    <AppShell
      title="DocVault — Dashboard"
      breadcrumb={[{ label: 'DocVault' }]}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <FolderOpen size={22} /> DocVault
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Documentação viva — evidências coletadas viram user stories + regras rastreáveis.
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" asChild>
              <Link href="/docs/memoria">
                <BookText size={14} className="mr-1.5" /> Memória
              </Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href="/docs/chat">
                <Sparkles size={14} className="mr-1.5" /> Chat
              </Link>
            </Button>
            <Button variant="outline" asChild>
              <Link href="/docs/inbox">
                <Inbox size={14} className="mr-1.5" />
                Inbox
                {stats.evidences_pending > 0 && (
                  <Badge variant="destructive" className="ml-1.5 text-[10px]">
                    {stats.evidences_pending}
                  </Badge>
                )}
              </Link>
            </Button>
            <Button asChild>
              <Link href="/docs/ingest">
                <Plus size={14} className="mr-1.5" /> Nova evidência
              </Link>
            </Button>
          </div>
        </header>

        {/* KPIs */}
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Kpi
            icon={<BookOpen size={16} />}
            label="Módulos documentados"
            value={stats.modules_total}
          />
          <Kpi
            icon={<FileText size={16} />}
            label="User stories"
            value={stats.stories_total}
          />
          <Kpi
            icon={<Scale size={16} />}
            label="Regras Gherkin"
            value={stats.rules_total}
          />
          <Kpi
            icon={<Target size={16} />}
            label="DoD completos"
            value={`${stats.dod_pct}%`}
            hint={`${stats.dod_done}/${stats.dod_total}`}
          />
        </div>

        {/* Evidências */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <Upload size={16} /> Evidências
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <StatusBox label="Total fontes" value={stats.sources_total} />
              <StatusBox label="Pendentes" value={stats.evidences_pending} tone="amber" />
              <StatusBox label="Triadas" value={stats.evidences_triaged} tone="sky" />
              <StatusBox label="Aplicadas" value={stats.evidences_applied} tone="emerald" />
            </div>
          </CardContent>
        </Card>

        {/* Cobertura global */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <BookOpen size={16} /> Maturidade da documentação
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
              <StatusBox label="Formato pasta" value={coverage_summary.folder_count} tone="emerald" />
              <StatusBox label="Formato plano" value={coverage_summary.flat_count} tone="amber" />
              <StatusBox label="Score médio" value={`${coverage_summary.avg_score}%`} />
              <StatusBox label="ADRs totais" value={coverage_summary.total_adrs} tone="sky" />
              <StatusBox label="Telas rastreadas" value={pages_total} tone={pages_total > 0 ? 'emerald' : undefined} />
            </div>
          </CardContent>
        </Card>

        {/* Módulos */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base flex items-center gap-2">
              <TrendingUp size={16} /> Cobertura por módulo
            </CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <th className="text-left p-3 font-medium">Módulo</th>
                    <th className="text-left p-3 font-medium">Formato</th>
                    <th className="text-left p-3 font-medium">Doc</th>
                    <th className="text-right p-3 font-medium">Stories</th>
                    <th className="text-right p-3 font-medium">Regras</th>
                    <th className="text-right p-3 font-medium">ADRs</th>
                    <th className="text-right p-3 font-medium">Telas</th>
                    <th className="text-left p-3 font-medium">Trace</th>
                    <th className="text-left p-3 font-medium">Audit</th>
                    <th className="text-left p-3 font-medium">DoD</th>
                    <th className="text-right p-3 font-medium"></th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {modules.map((m) => (
                    <tr key={m.name} className="hover:bg-accent/30">
                      <td className="p-3 font-medium">
                        <Link href={`/docs/modulos/${m.name}`} className="hover:underline">
                          {m.name}
                        </Link>
                        <div className="mt-0.5">
                          <Badge
                            variant={m.status === 'ativo' ? 'default' : 'outline'}
                            className="text-[10px]"
                          >
                            {m.status}
                          </Badge>
                        </div>
                      </td>
                      <td className="p-3">
                        <Badge
                          variant={m.format === 'folder' ? 'default' : 'outline'}
                          className="text-[10px]"
                        >
                          {m.format === 'folder' ? 'pasta' : 'plano'}
                        </Badge>
                      </td>
                      <td className="p-3">
                        <CoverageDots coverage={m.coverage} />
                      </td>
                      <td className="p-3 text-right font-mono text-xs">{m.stories_count}</td>
                      <td className="p-3 text-right font-mono text-xs">{m.rules_count}</td>
                      <td className="p-3 text-right font-mono text-xs">
                        {m.coverage?.adrs ? (
                          <span className="inline-flex items-center gap-1">
                            <Lightbulb size={12} className="text-amber-500" />
                            {m.coverage.adrs}
                          </span>
                        ) : (
                          <span className="text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="p-3 text-right font-mono text-xs">
                        {m.pages_count > 0 ? m.pages_count : <span className="text-muted-foreground">—</span>}
                      </td>
                      <td className="p-3 w-28">
                        <div className="flex items-center gap-2">
                          <div className="flex-1 h-1.5 bg-muted rounded overflow-hidden">
                            <div
                              className={`h-full ${m.trace_score >= 80 ? 'bg-emerald-500' : m.trace_score >= 40 ? 'bg-amber-500' : 'bg-red-500'}`}
                              style={{ width: `${Math.min(100, m.trace_score)}%` }}
                            />
                          </div>
                          <span className="text-[10px] text-muted-foreground font-mono w-8">
                            {m.trace_score}%
                          </span>
                        </div>
                      </td>
                      <td className="p-3 w-28">
                        {m.audit_score !== null ? (
                          <div className="flex items-center gap-2">
                            <div className="flex-1 h-1.5 bg-muted rounded overflow-hidden">
                              <div
                                className={`h-full ${m.audit_score >= 80 ? 'bg-emerald-500' : m.audit_score >= 50 ? 'bg-amber-500' : 'bg-red-500'}`}
                                style={{ width: `${Math.min(100, m.audit_score)}%` }}
                              />
                            </div>
                            <span className="text-[10px] text-muted-foreground font-mono w-8">
                              {m.audit_score}%
                            </span>
                          </div>
                        ) : (
                          <span className="text-[10px] text-muted-foreground">—</span>
                        )}
                      </td>
                      <td className="p-3 w-28">
                        <div className="flex items-center gap-2">
                          <div className="flex-1 h-1.5 bg-muted rounded overflow-hidden">
                            <div
                              className={`h-full ${m.dod_pct >= 80 ? 'bg-emerald-500' : m.dod_pct >= 40 ? 'bg-amber-500' : 'bg-red-500'}`}
                              style={{ width: `${Math.min(100, m.dod_pct)}%` }}
                            />
                          </div>
                          <span className="text-[10px] text-muted-foreground font-mono w-8">
                            {m.dod_pct}%
                          </span>
                        </div>
                      </td>
                      <td className="p-3 text-right">
                        <Button size="sm" variant="outline" asChild>
                          <Link href={`/docs/modulos/${m.name}`} className="text-xs">
                            Ver
                          </Link>
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>

        {/* Fontes recentes */}
        {recent_sources.length > 0 && (
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Fontes recentes</CardTitle>
            </CardHeader>
            <CardContent>
              <ul className="space-y-2">
                {recent_sources.map((s) => (
                  <li key={s.id} className="flex items-center gap-3 text-sm py-1">
                    <Badge variant="secondary" className="text-[10px] min-w-16 justify-center">
                      {s.type}
                    </Badge>
                    <span className="flex-1 truncate">{s.title}</span>
                    {s.module && (
                      <Badge variant="outline" className="text-[10px]">
                        {s.module}
                      </Badge>
                    )}
                    <span className="text-xs text-muted-foreground">{s.created_at_human}</span>
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        )}
      </div>
    </AppShell>
  );
}

function Kpi({
  icon,
  label,
  value,
  hint,
}: {
  icon: React.ReactNode;
  label: string;
  value: string | number;
  hint?: string;
}) {
  return (
    <Card>
      <CardContent className="pt-4">
        <div className="text-xs text-muted-foreground flex items-center gap-1 mb-1">
          {icon} {label}
        </div>
        <div className="text-2xl font-bold">{value}</div>
        {hint && <div className="text-[10px] text-muted-foreground mt-0.5">{hint}</div>}
      </CardContent>
    </Card>
  );
}

function CoverageDots({ coverage }: { coverage: Coverage | null }) {
  if (!coverage) return <span className="text-muted-foreground text-xs">—</span>;
  const dots = [
    { label: 'README', on: coverage.readme },
    { label: 'Arquitetura', on: coverage.arch },
    { label: 'Spec', on: coverage.spec },
    { label: 'Changelog', on: coverage.changelog },
    { label: 'ADRs', on: coverage.adrs > 0 },
  ];
  return (
    <div className="flex items-center gap-1" title={`Score ${coverage.score}%`}>
      {dots.map((d, i) => (
        <div
          key={i}
          title={`${d.label}: ${d.on ? 'ok' : 'faltando'}`}
          className={`w-2 h-2 rounded-full ${d.on ? 'bg-emerald-500' : 'bg-muted'}`}
        />
      ))}
      <span className="ml-1 text-[10px] text-muted-foreground font-mono">{coverage.score}</span>
    </div>
  );
}

function StatusBox({
  label,
  value,
  tone,
}: {
  label: string;
  value: number;
  tone?: 'amber' | 'sky' | 'emerald';
}) {
  const toneCls = tone === 'amber'
    ? 'text-amber-700 dark:text-amber-300'
    : tone === 'sky'
    ? 'text-sky-700 dark:text-sky-300'
    : tone === 'emerald'
    ? 'text-emerald-700 dark:text-emerald-300'
    : '';
  return (
    <div className="border border-border rounded p-3">
      <div className="text-xs text-muted-foreground mb-1">{label}</div>
      <div className={`text-2xl font-bold ${toneCls}`}>{value}</div>
    </div>
  );
}
