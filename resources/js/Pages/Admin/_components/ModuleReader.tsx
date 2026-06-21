import * as React from 'react';
import { cn } from '@/Lib/utils';
import {
  BookOpen,
  ChevronLeft,
  ChevronRight,
  Plus,
  RefreshCw,
  TrendingUp,
  TrendingDown,
  Sparkles,
  Target,
} from 'lucide-react';
import SparklineTrend from './SparklineTrend';
import DimensionProgressBar from './DimensionProgressBar';
import InitiativeBadge from './InitiativeBadge';
import AiSuggestionPanel from './AiSuggestionPanel';
import {
  STATUS_TONE,
  type AiSuggestion,
  type Initiative,
  type ModuleRow,
  type ModuleScorecard,
} from './governanceV4Types';

/**
 * ModuleReader — coluna 3 do tri-pane (port de kb/NodeReader)
 *
 * Wave 29 (W29-C). Header KPIs + sparkline + 9 dimensões + Initiatives + AI panel.
 *
 * Empty state quando `module === null` (nada selecionado).
 */
interface Props {
  module: ModuleRow | null;
  scorecard: ModuleScorecard | null;
  initiatives: Initiative[];
  aiSuggestions: AiSuggestion[];
  prev?: ModuleRow | null;
  next?: ModuleRow | null;
  onPickPrev?: () => void;
  onPickNext?: () => void;
  onOpenInitiativeNew?: (module: string) => void;
  onOpenOverride?: (module: string) => void;
  onRefresh?: (module: string) => void;
  className?: string;
}

export default function ModuleReader({
  module,
  scorecard,
  initiatives,
  aiSuggestions,
  prev,
  next,
  onPickPrev,
  onPickNext,
  onOpenInitiativeNew,
  onOpenOverride,
  onRefresh,
  className,
}: Props) {
  if (!module) {
    return (
      <section
        className={cn(
          'kb-reader flex flex-col items-center justify-center gap-2 bg-background p-8 text-center',
          className,
        )}
        aria-label="Módulo não selecionado"
      >
        <BookOpen size={28} className="text-muted-foreground/60" />
        <h2 className="text-[14px] font-semibold text-foreground">
          Selecione um módulo
        </h2>
        <p className="max-w-sm text-[12px] text-muted-foreground">
          Escolha um bucket à esquerda e um módulo na lista pra ver o breakdown
          de dimensões, initiatives abertas e sinais AI baseline.
        </p>
      </section>
    );
  }

  const statusTone = STATUS_TONE[module.status];
  const delta = module.score - module.meta;
  const TrendIcon = delta >= 0 ? TrendingUp : TrendingDown;
  const trendCls = delta >= 0 ? 'text-success-fg' : 'text-destructive';

  const moduleInitiatives = initiatives.filter((i) => i.module === module.slug);
  const moduleSuggestions = aiSuggestions.filter((s) => s.module === module.slug);

  return (
    <section
      className={cn(
        'kb-reader flex flex-col overflow-hidden bg-background',
        className,
      )}
      aria-label={`Detalhe módulo ${module.name}`}
    >
      {/* Header */}
      <header className="space-y-3 border-b border-border bg-card/40 px-4 py-3">
        <div className="flex items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
              Módulo
            </div>
            <h2 className="text-[16px] font-semibold text-foreground truncate">
              {module.name}
            </h2>
            <div className="text-[11px] text-muted-foreground truncate">
              {module.slug}
            </div>
          </div>
          <div className="flex items-center gap-1">
            <button
              type="button"
              onClick={onPickPrev}
              disabled={!prev}
              className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-foreground hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-primary/40"
              title="Módulo anterior"
              aria-label="Módulo anterior"
            >
              <ChevronLeft size={14} />
            </button>
            <button
              type="button"
              onClick={onPickNext}
              disabled={!next}
              className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-foreground hover:bg-accent disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus:ring-2 focus:ring-primary/40"
              title="Próximo módulo"
              aria-label="Próximo módulo"
            >
              <ChevronRight size={14} />
            </button>
          </div>
        </div>

        {/* KPI strip */}
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
          <Kpi
            label="Nota atual"
            value={String(module.score)}
            tone={module.status}
          />
          <Kpi
            label="Meta bucket"
            value={`≥${module.meta}`}
            icon={<Target size={11} />}
          />
          <Kpi
            label="Delta vs meta"
            value={`${delta > 0 ? '+' : ''}${delta}`}
            tone={module.status}
            icon={<TrendIcon size={11} className={trendCls} />}
          />
          <Kpi
            label="Status"
            value={statusTone.label}
            tone={module.status}
          />
        </div>

        {/* Sparkline trend 30d */}
        <div className="flex items-end justify-between gap-3 rounded-md border border-border bg-background px-3 py-2">
          <div className="min-w-0">
            <div className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
              Tendência 30d
            </div>
            <div className="text-[10.5px] text-muted-foreground">
              {module.trend.length} snapshots
              {scorecard?.last_run_at && (
                <>
                  {' · last run '}
                  <time dateTime={scorecard.last_run_at}>
                    {new Date(scorecard.last_run_at).toLocaleString('pt-BR')}
                  </time>
                </>
              )}
            </div>
          </div>
          <SparklineTrend
            values={module.trend}
            width={200}
            height={36}
            className={trendCls}
            ariaLabel={`Tendência 30d ${module.name}`}
          />
        </div>

        {/* Quick actions */}
        <div className="flex flex-wrap items-center gap-1.5">
          {onOpenInitiativeNew && (
            <button
              type="button"
              onClick={() => onOpenInitiativeNew(module.slug)}
              className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-1 text-[11px] font-medium text-foreground hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/40"
            >
              <Plus size={11} />
              Initiative
            </button>
          )}
          {onOpenOverride && (
            <button
              type="button"
              onClick={() => onOpenOverride(module.slug)}
              className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-1 text-[11px] font-medium text-foreground hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/40"
            >
              Override bucket
            </button>
          )}
          {onRefresh && (
            <button
              type="button"
              onClick={() => onRefresh(module.slug)}
              className="inline-flex items-center gap-1 rounded-md border border-border bg-card px-2 py-1 text-[11px] font-medium text-foreground hover:bg-accent focus:outline-none focus:ring-2 focus:ring-primary/40"
            >
              <RefreshCw size={11} />
              Refresh now
            </button>
          )}
        </div>
      </header>

      {/* Body */}
      <div className="flex-1 overflow-y-auto px-4 py-3 space-y-4">
        {/* Dimensões D1-D9 */}
        <section>
          <h3 className="mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
            Dimensões (rubrica YAML)
          </h3>
          {!scorecard || scorecard.dimensions.length === 0 ? (
            <p className="rounded-md border border-dashed border-border bg-muted/30 px-3 py-3 text-[11.5px] italic text-muted-foreground">
              Rubrica YAML não carregada pra <code>{module.slug}</code> — verifique
              <code className="mx-1">memory/governance/scorecards/{module.slug}.yaml</code>
              ou aguarde W29-B fornecer payload <code>scorecards</code>.
            </p>
          ) : (
            <div className="space-y-2.5 rounded-md border border-border bg-card p-3">
              {scorecard.dimensions.map((d) => (
                <DimensionProgressBar key={d.id} dimension={d} />
              ))}
            </div>
          )}
        </section>

        {/* Initiatives */}
        <section>
          <div className="mb-2 flex items-center justify-between gap-2">
            <h3 className="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">
              Initiatives abertas ({moduleInitiatives.length})
            </h3>
            {onOpenInitiativeNew && (
              <button
                type="button"
                onClick={() => onOpenInitiativeNew(module.slug)}
                className="inline-flex items-center gap-0.5 text-[10.5px] text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/40"
              >
                <Plus size={10} />
                Nova
              </button>
            )}
          </div>
          {moduleInitiatives.length === 0 ? (
            <p className="rounded-md border border-dashed border-border bg-muted/30 px-3 py-3 text-[11.5px] italic text-muted-foreground">
              Nenhuma Initiative aberta pra este módulo.
            </p>
          ) : (
            <div className="space-y-1.5">
              {moduleInitiatives.map((i) => (
                <InitiativeBadge key={i.id} initiative={i} />
              ))}
            </div>
          )}
        </section>

        {/* AI Suggestions panel pra este módulo */}
        <section>
          <AiSuggestionPanel
            suggestions={moduleSuggestions}
            module={module.slug}
            emptyHint="Sem sinais AI baseline 30d pra este módulo."
          />
        </section>

        {/* Paired hint discreto */}
        {module.paired_count > 0 && (
          <section
            aria-label="Paired violations"
            className="flex items-start gap-2 rounded-md border border-warning/20 bg-warning-soft px-3 py-2 text-[11.5px] text-warning-fg"
          >
            <Sparkles size={13} className="mt-0.5 shrink-0" />
            <div>
              <strong className="font-semibold">{module.paired_count}</strong> indicador(es)
              pareado(s) em violação — anti-Goodhart Jellyfish 2025. Score pode estar inflado;
              ver YAML <code>paired_violations</code>.
            </div>
          </section>
        )}
      </div>
    </section>
  );
}

function Kpi({
  label,
  value,
  tone,
  icon,
}: {
  label: string;
  value: string;
  tone?: 'ok' | 'warn' | 'crit';
  icon?: React.ReactNode;
}) {
  const toneCls = !tone
    ? 'text-foreground'
    : tone === 'ok'
      ? 'text-success-fg'
      : tone === 'warn'
        ? 'text-warning-fg'
        : 'text-destructive';
  return (
    <div className="rounded-md border border-border bg-background px-2.5 py-1.5">
      <div className="flex items-center gap-1 text-[10px] font-medium uppercase tracking-wider text-muted-foreground">
        {icon}
        {label}
      </div>
      <div className={cn('mt-0.5 text-[15px] font-bold tabular-nums', toneCls)}>{value}</div>
    </div>
  );
}
