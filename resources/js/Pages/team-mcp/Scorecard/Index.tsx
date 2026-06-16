// @memcofre
//   tela: /team-mcp/scorecard
//   module: TeamMcp — G1 FICHA W22 (Facts + Checks)
//   forja: PR-3 — CRIA a Page (rota existia mas o componente não; route quebrada).
//          visual-comparison em memory/requisitos/TeamMcp/scorecard-visual-comparison.md
//   permissao: copiloto.mcp.usage.all
//
// Padrão Facts+Checks (ADR 0091): Facts = números sem juízo · Checks = semáforo ok/fail.
// SEM dado fantasma: projeta só o que ScorecardBuilderService retorna (sem sparkline —
// não há série temporal real; adicionar série = nova métrica, fora do escopo §3).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { AlertCircle, CheckCircle2, RefreshCw } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { PageHeader } from '@/Components/PageHeader';
import ForjaHub from '@/Pages/team-mcp/Forja/_components/ForjaHub';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { cn } from '@/Lib/utils';

interface Check { name: string; ok: boolean; detail: string }

interface Facts {
  tokens_ativos: number;
  calls_7d: number;
  cost_7d_brl: number;
  users_ativos_7d: number;
  top_tools_7d: Array<{ tool: string; count: number }>;
  audit_log_present: boolean;
  tokens_table_present: boolean;
}

interface Meta {
  generated_at: string;
  period_days: number;
  pattern: string;
  source: string;
}

interface Props {
  // facts/checks deferidos → undefined no 1º paint (default-guard).
  facts?: Facts;
  checks?: Check[];
  meta: Meta;
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function fmtWhen(iso: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function ScorecardIndex({ facts, checks, meta }: Props) {
  const isLoading = facts === undefined || checks === undefined;
  const checkList = checks ?? [];
  const okCount = checkList.filter((c) => c.ok).length;
  const allOk = checkList.length > 0 && okCount === checkList.length;

  // Atalho: R recarrega facts/checks
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement | null;
      const typing = !!tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable);
      if (e.ctrlKey || e.metaKey || e.altKey) return; // não sequestrar Ctrl/Cmd+R do browser
      if (!typing && (e.key === 'r' || e.key === 'R')) {
        e.preventDefault();
        router.reload({ only: ['facts', 'checks'] });
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  return (
    <>
      <ForjaHub active="saude" />
      <PageHeader
        title="Saúde do MCP"
        subtitle={`Facts + Checks · janela ${meta.period_days}d · fonte ${meta.source}`}
        actions={
          <Button variant="ghost" size="sm" className="h-8 text-xs" onClick={() => router.reload({ only: ['facts', 'checks'] })}>
            <RefreshCw size={13} className="mr-1" /> Atualizar
          </Button>
        }
      />

      {/* Semáforo geral */}
      <div
        role="status"
        data-testid="scorecard-semaphore"
        className={cn('mt-4 flex items-center gap-2 rounded-lg border px-4 py-3 text-sm',
          isLoading ? 'border-border bg-muted/40 text-muted-foreground'
            : allOk ? 'border-success/30 bg-success/10 text-success-fg'
              : 'border-warning/30 bg-warning-soft text-warning-fg')}
      >
        {isLoading ? (
          <span>Carregando checks…</span>
        ) : allOk ? (
          <><CheckCircle2 size={16} /> <span className="font-medium">Tudo verde — {okCount}/{checkList.length} checks OK</span></>
        ) : (
          <><AlertCircle size={16} /> <span className="font-medium">{checkList.length - okCount} de {checkList.length} checks falhando</span></>
        )}
      </div>

      {/* Facts (números — sem juízo) */}
      <h2 className="mt-6 text-sm font-semibold text-foreground">Facts <span className="font-normal text-muted-foreground">— números, sem juízo</span></h2>
      <KpiGrid cols={4} className="mt-2">
        <KpiCard icon="key-round" tone="default" label="Tokens ativos" value={isLoading ? '—' : num(facts!.tokens_ativos)} />
        <KpiCard icon="activity" tone="default" label="Calls 7d" value={isLoading ? '—' : num(facts!.calls_7d)} />
        <KpiCard icon="dollar-sign" tone="warning" label="Custo 7d" value={isLoading ? '—' : brl(facts!.cost_7d_brl)} />
        <KpiCard icon="users" tone="default" label="Devs ativos 7d" value={isLoading ? '—' : num(facts!.users_ativos_7d)} />
      </KpiGrid>

      {!isLoading && (
        <div className="mt-3 rounded-lg border bg-card p-3" data-testid="scorecard-tools">
          <div className="mb-2 text-xs font-medium text-muted-foreground">Top tools (7d)</div>
          {facts!.top_tools_7d.length === 0 ? (
            <p className="text-xs italic text-muted-foreground">Sem chamadas registradas na janela.</p>
          ) : (
            <ul className="inline-flex w-full flex-wrap gap-2">
              {facts!.top_tools_7d.map((t) => (
                <li key={t.tool} className="inline-flex items-center gap-1.5 rounded-md border bg-background px-2 py-1 text-xs">
                  <span className="font-mono">{t.tool}</span>
                  <span className="rounded-full bg-muted px-1.5 text-[10px] tabular-nums text-muted-foreground">{num(t.count)}</span>
                </li>
              ))}
            </ul>
          )}
          {(!facts!.audit_log_present || !facts!.tokens_table_present) && (
            <p className="mt-2 text-[11px] text-warning-fg">
              ⚠ {!facts!.audit_log_present && 'mcp_audit_log ausente. '}{!facts!.tokens_table_present && 'mcp_tokens ausente. '}Rodar migrations.
            </p>
          )}
        </div>
      )}

      {/* Checks (semáforo ok/fail) */}
      <h2 className="mt-6 text-sm font-semibold text-foreground">Checks <span className="font-normal text-muted-foreground">— saúde por dimensão</span></h2>
      {isLoading ? (
        <div className="mt-2 space-y-2" data-testid="scorecard-skeleton">
          {Array.from({ length: 5 }).map((_, i) => <div key={i} className="h-12 animate-pulse rounded-md bg-muted/50" />)}
        </div>
      ) : (
        <ul className="mt-2 overflow-hidden rounded-lg border bg-card" data-testid="scorecard-checks">
          {checkList.map((c, i) => (
            <li key={i} className="inline-flex w-full items-start gap-3 border-b border-border/60 px-3 py-2.5 last:border-b-0">
              {c.ok
                ? <CheckCircle2 size={16} className="mt-0.5 shrink-0 text-success" aria-label="ok" />
                : <AlertCircle size={16} className="mt-0.5 shrink-0 text-destructive" aria-label="falha" />}
              <div className="min-w-0 flex-1">
                <div className={cn('text-sm font-medium', c.ok ? 'text-foreground' : 'text-destructive')}>{c.name}</div>
                <div className="text-xs text-muted-foreground">{c.detail}</div>
              </div>
              <span className={cn('shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium',
                c.ok ? 'bg-success/15 text-success-fg' : 'bg-destructive-soft text-destructive-fg')}>
                {c.ok ? 'ok' : 'falha'}
              </span>
            </li>
          ))}
        </ul>
      )}

      <p className="mt-4 text-[11px] text-muted-foreground">
        Gerado {fmtWhen(meta.generated_at)} · pattern <code className="font-mono">{meta.pattern}</code> · <kbd className="rounded bg-muted px-1 py-0.5">R</kbd> atualiza.
        Scorecard é repo-wide (governança cross-business, superadmin) — sem filtro business_id (ADR 0093, intencional).
      </p>
    </>
  );
}

ScorecardIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Saúde — Forja" breadcrumbItems={[{ label: 'Forja' }, { label: 'Saúde' }]}>
    {page}
  </AppShellV2>
);

export default ScorecardIndex;
