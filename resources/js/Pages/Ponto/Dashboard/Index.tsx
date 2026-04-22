import AppShell from '@/Layouts/AppShell';
import { Link } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowRight,
  CheckCheck,
  Clock,
  LogIn,
  LogOut,
  UserMinus,
  Users,
} from 'lucide-react';
import { Icon } from '@/Components/Icon';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { cn, formatMinutes } from '@/Lib/utils';

interface Kpis {
  colaboradores_ativos: number;
  presentes_agora: number;
  atrasos_hoje: number;
  faltas_hoje: number;
  he_mes_minutos: number;
  aprovacoes_pendentes: number;
}

interface Aprovacao {
  id: number;
  tipo: string;
  prioridade: string;
  data_inicio: string | null;
  data_fim: string | null;
  justificativa: string;
  estado: string;
  created_at: string | null;
  colaborador: { id: number | null; nome: string; matricula: string | null };
}

interface Marcacao {
  id: number;
  tipo: string;
  momento: string | null;
  origem: string;
  colaborador: { nome: string };
  rep: { identificador: string | null; tipo: string | null };
}

interface SeriePonto {
  data: string;
  label: string;
  trabalhado: number;
  he: number;
}

interface Props {
  kpis: Kpis;
  aprovacoes: Aprovacao[];
  atividade_recente: Marcacao[];
  serie_7dias: SeriePonto[];
}

const prioridadeConfig: Record<string, { variant: 'default' | 'secondary' | 'destructive' | 'outline'; label: string }> = {
  URGENTE:  { variant: 'destructive', label: 'Urgente' },
  ALTA:     { variant: 'destructive', label: 'Alta' },
  NORMAL:   { variant: 'secondary',   label: 'Normal' },
  BAIXA:    { variant: 'outline',     label: 'Baixa' },
};

const tipoMarcacaoIcon: Record<string, { icon: string; color: string }> = {
  ENTRADA:        { icon: 'LogIn',  color: 'text-emerald-600 dark:text-emerald-400' },
  ALMOCO_INICIO:  { icon: 'Coffee', color: 'text-amber-600 dark:text-amber-400' },
  ALMOCO_FIM:     { icon: 'Coffee', color: 'text-amber-600 dark:text-amber-400' },
  SAIDA:          { icon: 'LogOut', color: 'text-red-600 dark:text-red-400' },
  ANULACAO:       { icon: 'XCircle', color: 'text-muted-foreground' },
};

export default function DashboardIndex({ kpis, aprovacoes, atividade_recente, serie_7dias }: Props) {
  return (
    <AppShell
      title="Dashboard · Ponto WR2"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Dashboard' }]}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-6">
        <header>
          <h1 className="text-2xl font-bold tracking-tight">Dashboard</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Visão geral do ponto eletrônico — hoje, {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
          </p>
        </header>

        {/* KPIs */}
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <StatCard label="Colaboradores" value={kpis.colaboradores_ativos} icon={Users} tone="blue" href="/ponto/colaboradores" />
          <StatCard label="Presentes hoje" value={kpis.presentes_agora} icon={LogIn} tone="emerald" />
          <StatCard label="Atrasos hoje" value={kpis.atrasos_hoje} icon={Clock} tone={kpis.atrasos_hoje > 0 ? 'amber' : 'muted'} />
          <StatCard label="Faltas hoje" value={kpis.faltas_hoje} icon={UserMinus} tone={kpis.faltas_hoje > 0 ? 'red' : 'muted'} />
          <StatCard label="HE do mês" value={formatMinutes(kpis.he_mes_minutos)} icon={Clock} tone="violet" small />
          <StatCard label="Aprovações pendentes" value={kpis.aprovacoes_pendentes} icon={CheckCheck} tone={kpis.aprovacoes_pendentes > 0 ? 'red' : 'muted'} href="/ponto/aprovacoes" />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          {/* Série 7 dias */}
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle className="text-base">Últimos 7 dias</CardTitle>
              <CardDescription className="text-xs">Minutos trabalhados + horas extras por dia</CardDescription>
            </CardHeader>
            <CardContent>
              <BarChart7Days serie={serie_7dias} />
            </CardContent>
          </Card>

          {/* Aprovações pendentes */}
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <div>
                <CardTitle className="text-base flex items-center gap-1.5">
                  <CheckCheck size={16} className="text-primary" /> Aprovações
                </CardTitle>
                <CardDescription className="text-xs">Intercorrências pendentes</CardDescription>
              </div>
              <Button variant="ghost" size="sm" asChild>
                <Link href="/ponto/aprovacoes" className="text-xs">
                  Ver todas <ArrowRight size={12} className="ml-1" />
                </Link>
              </Button>
            </CardHeader>
            <CardContent className="space-y-2">
              {aprovacoes.length === 0 ? (
                <p className="text-xs text-muted-foreground text-center py-6">
                  Nenhuma pendência 🎉
                </p>
              ) : (
                aprovacoes.map((a) => (
                  <ApprovalRow key={a.id} item={a} />
                ))
              )}
            </CardContent>
          </Card>
        </div>

        {/* Atividade recente */}
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <div>
              <CardTitle className="text-base">Atividade recente</CardTitle>
              <CardDescription className="text-xs">Últimas 10 marcações</CardDescription>
            </div>
            <Button variant="ghost" size="sm" asChild>
              <Link href="/ponto/espelho" className="text-xs">
                Ver espelho <ArrowRight size={12} className="ml-1" />
              </Link>
            </Button>
          </CardHeader>
          <CardContent>
            {atividade_recente.length === 0 ? (
              <p className="text-sm text-muted-foreground text-center py-6">
                Nenhuma marcação recente
              </p>
            ) : (
              <div className="divide-y divide-border">
                {atividade_recente.map((m) => (
                  <MarcacaoRow key={m.id} item={m} />
                ))}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}

// ============================================================================
// StatCard
// ============================================================================

type Tone = 'blue' | 'emerald' | 'amber' | 'red' | 'violet' | 'muted';

const toneBgClass: Record<Tone, string> = {
  blue:    'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
  emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  amber:   'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
  red:     'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
  violet:  'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
  muted:   'bg-muted text-muted-foreground',
};

function StatCard({
  label,
  value,
  icon: IconComp,
  tone,
  href,
  small,
}: {
  label: string;
  value: number | string;
  icon: React.ComponentType<{ size?: number; className?: string }>;
  tone: Tone;
  href?: string;
  small?: boolean;
}) {
  const card = (
    <Card className={cn('h-full', href && 'hover:border-primary/50 transition-colors cursor-pointer')}>
      <CardContent className="pt-4 pb-4">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <p className="text-[10px] uppercase tracking-wide text-muted-foreground truncate">{label}</p>
            <p className={cn('font-bold mt-0.5', small ? 'text-lg' : 'text-2xl')}>{value}</p>
          </div>
          <div className={cn('flex size-9 items-center justify-center rounded-lg shrink-0', toneBgClass[tone])}>
            <IconComp size={16} />
          </div>
        </div>
      </CardContent>
    </Card>
  );
  return href ? <Link href={href}>{card}</Link> : card;
}

// ============================================================================
// Bar chart simples (sem dep — CSS puro)
// ============================================================================

function BarChart7Days({ serie }: { serie: SeriePonto[] }) {
  const max = Math.max(...serie.map((d) => d.trabalhado + d.he), 1);
  return (
    <div className="flex items-end justify-between gap-1.5 h-40">
      {serie.map((d) => {
        const totalPct = ((d.trabalhado + d.he) / max) * 100;
        const hePct = d.he > 0 ? (d.he / (d.trabalhado + d.he)) * totalPct : 0;
        const regPct = totalPct - hePct;
        return (
          <div key={d.data} className="flex flex-col items-center flex-1 group">
            <div className="w-full flex flex-col items-center mb-1">
              <span className="text-[9px] text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity">
                {formatMinutes(d.trabalhado + d.he)}
              </span>
            </div>
            <div className="w-full max-w-[40px] flex flex-col justify-end h-28 relative">
              {d.he > 0 && (
                <div
                  className="bg-amber-500 dark:bg-amber-600 rounded-t"
                  style={{ height: `${hePct}%` }}
                  title={`HE: ${formatMinutes(d.he)}`}
                />
              )}
              <div
                className={cn(
                  'bg-primary/80',
                  d.he === 0 && 'rounded-t',
                )}
                style={{ height: `${regPct}%` }}
                title={`Trabalhado: ${formatMinutes(d.trabalhado)}`}
              />
            </div>
            <span className="text-[10px] text-muted-foreground mt-1.5">{d.label}</span>
          </div>
        );
      })}
    </div>
  );
}

// ============================================================================
// Linhas de lista
// ============================================================================

function ApprovalRow({ item }: { item: Aprovacao }) {
  const prio = prioridadeConfig[item.prioridade] ?? prioridadeConfig.NORMAL;
  return (
    <Link
      href={`/ponto/intercorrencias/${item.id}`}
      className="flex items-start gap-2 p-2 -mx-2 rounded hover:bg-accent transition-colors"
    >
      <AlertTriangle size={14} className="text-amber-500 mt-0.5 shrink-0" />
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-1.5">
          <span className="text-sm font-medium truncate">{item.colaborador.nome}</span>
          <Badge variant={prio.variant} className="text-[9px] px-1 py-0">
            {prio.label}
          </Badge>
        </div>
        <p className="text-xs text-muted-foreground truncate">{item.tipo} · {item.data_inicio}</p>
        <p className="text-[10px] text-muted-foreground">{item.created_at}</p>
      </div>
    </Link>
  );
}

function MarcacaoRow({ item }: { item: Marcacao }) {
  const config = tipoMarcacaoIcon[item.tipo] ?? { icon: 'Circle', color: 'text-muted-foreground' };
  return (
    <div className="flex items-center gap-3 py-2.5">
      <div className={cn('size-8 rounded-full flex items-center justify-center bg-muted', config.color)}>
        <Icon name={config.icon} size={14} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-sm font-medium truncate">{item.colaborador.nome}</p>
        <p className="text-xs text-muted-foreground">
          {item.tipo.replace('_', ' ').toLowerCase()} · {item.momento}
          {item.rep.identificador && <span> · REP {item.rep.identificador}</span>}
        </p>
      </div>
      <Badge variant="outline" className="text-[10px] shrink-0">
        {item.origem}
      </Badge>
    </div>
  );
}
