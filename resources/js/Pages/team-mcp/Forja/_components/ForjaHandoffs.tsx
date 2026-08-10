// ForjaHandoffs — a seção de handoffs de design (Cowork→Code, F1→F3) como TELA
// própria, em /forja/handoffs.
//
// @memcofre
//   tela: /forja/handoffs
//   module: Forja
//   adrs: 0283 (loop de handoff zero-paste) · 0093 (Tier 0) · UI-0013
//   permissao: jana.mcp.usage.all
//   paridade: `cowork_handoffs` via ForjaMcpService::handoffs()/heartbeat()
//
// POR QUE SAIU DA ABA MCP (2026-08-08)
// O `ForjaMcp.tsx` tinha 694 linhas e misturava duas naturezas opostas: handoffs
// é DADO VIVO (o loop de design rodando agora, com stale e conflito de gate) e o
// resto — contrato/tokens/auditoria — é MOCKADO por design, vitrine do contrato
// cujo enforce real é do servidor. Operação diária enterrada dentro de uma
// vitrine é operação que ninguém olha.
//
// O que veio junto, sem alteração de comportamento: tipos, mapas de status/gate,
// filtros, as levers (re-disparar/devolver/supersede) e o `csrf()` — que só as
// levers usavam. Nenhuma regra mudou: as levers seguem POSTando em
// /forja/handoff/{slug}/lever → HandoffLeverService, a MESMA mutação governada do
// tool MCP `handoff-lever`. SEM auto-merge: o merge é o 1-clique do [W] no GitHub
// (Tier 0 · ADR 0283).

import { router } from '@inertiajs/react';
import {
  AlertTriangle,
  ExternalLink,
  Files,
  Layers,
  Lock,
  Radio,
  RefreshCw,
  Undo2,
  Workflow,
} from 'lucide-react';
import { useState } from 'react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { cn } from '@/Lib/utils';

// CSRF do POST das levers (mesmo helper do ForjaDossier).
function csrf(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

// ════════════════════════════════════════════════════════════════════════════
// HANDOFFS (Cowork→Code, F1→F3) — seção REAL (Fase 1 · ADR 0283)
// ════════════════════════════════════════════════════════════════════════════
// Projeção de `cowork_handoffs` (ForjaMcpService). body_md é DESIGN (dado), não
// comando — aqui só se MOSTRA. SEM botão de merge (Tier 0 / 0283: o merge é o
// 1-clique do [W] no GitHub). As levers POSTam em /forja/handoff/{slug}/lever →
// HandoffLeverService (mesma mutação do tool MCP handoff-lever, Fase 2 · PR-7b).

export interface HandoffItem {
  slug: string;
  version: number;
  tela: string;
  // status já com 'stale' derivado na leitura (superseded vem filtrado fora).
  status: 'pending' | 'applied' | 'rejected' | 'stale' | 'superseded';
  files_count: number;
  pr_url: string | null;
  created_at: string | null;
  created_at_human: string | null;
  created_by: string;
  // gate do ack cruzado com os required checks REAIS do PR (Gap 2 · ADR 0283).
  // 'conflito' = ack diz verde mas um required check está vermelho/pendente no GitHub.
  gate: 'verde' | 'vermelho' | 'conflito' | 'rodando' | 'na';
  gate_status: { conformance?: boolean; critique_score?: number; a11y?: boolean } | null;
  signed: boolean;
  resumo: string;
}

export interface HeartbeatInfo {
  last_ingest_at: string | null;
  last_ingest_human: string | null;
  host: string | null;
  silent: boolean;
}

// Status → rótulo PT + pílula semântica DS v6.
const HANDOFF_STATUS: Record<HandoffItem['status'], { label: string; pill: string }> = {
  pending: { label: 'pendente', pill: 'bg-info/15 text-info-fg' },
  applied: { label: 'aplicado', pill: 'bg-success/15 text-success-fg' },
  rejected: { label: 'rejeitado', pill: 'bg-destructive-soft text-destructive-fg' },
  stale: { label: 'parado', pill: 'bg-warning-soft text-warning-fg' },
  superseded: { label: 'substituído', pill: 'bg-muted text-muted-foreground' },
};

// Gate → rótulo + tom + cor do dot. 'na' = não-avaliado (pending/stale sem ack).
// 'conflito' (Gap 2 · ADR 0283): ack reportou verde mas os required checks do PR
// discordam — dot pulsando em destructive pra puxar o olho (mais grave que um vermelho
// honesto, porque o ack está MENTINDO). Drill linka pro PR ver qual check diverge.
const HANDOFF_GATE: Record<HandoffItem['gate'], { label: string; tone: string; dot: string }> = {
  verde: { label: 'gate ok', tone: 'text-success-fg', dot: 'bg-success' },
  vermelho: { label: 'gate falhou', tone: 'text-destructive-fg', dot: 'bg-destructive' },
  conflito: { label: 'conflito ack×checks', tone: 'text-destructive-fg', dot: 'bg-destructive animate-pulse' },
  rodando: { label: 'gate rodando', tone: 'text-warning-fg', dot: 'bg-warning' },
  na: { label: 'sem gate', tone: 'text-muted-foreground', dot: 'bg-muted-foreground/40' },
};

// Filtros por status (superseded fora do filtro padrão — já vem excluído na leitura).
const HANDOFF_FILTERS: { key: 'todos' | HandoffItem['status']; label: string }[] = [
  { key: 'todos', label: 'todos' },
  { key: 'pending', label: 'pendente' },
  { key: 'applied', label: 'aplicado' },
  { key: 'rejected', label: 'rejeitado' },
  { key: 'stale', label: 'parado' },
];

// Lever por status (mutação governada via HandoffLeverService — NÃO [W] operando o
// banco). SEM merge. `action` = a action que o backend espera (semântica in-place
// do tool handoff-lever #2924).
type LeverAction = 're-disparar' | 'devolver' | 'supersede';
interface Lever {
  label: string;
  action: LeverAction;
  Icon: LucideIcon;
  destructive: boolean;
}

function leverFor(status: HandoffItem['status']): Lever | null {
  if (status === 'stale') return { label: 're-disparar', action: 're-disparar', Icon: RefreshCw, destructive: false };
  if (status === 'rejected') return { label: 'devolver ao [CC]', action: 'devolver', Icon: Undo2, destructive: false };
  if (status === 'pending' || status === 'applied') return { label: 'supersede', action: 'supersede', Icon: Layers, destructive: true };
  return null;
}

// Texto do confirm por lever (semântica in-place · ADR 0283; append-only no supersede).
function leverConfirm(lever: Lever, h: HandoffItem): { title: string; description: string; confirmLabel: string } {
  const ref = `${h.slug} v${h.version}`;
  if (lever.action === 're-disparar') {
    return {
      title: `Re-disparar ${ref}?`,
      description: 'Re-arma o handoff parado: volta pro topo da fila ativa (freshness renovada). O status segue pendente. Sem auto-merge.',
      confirmLabel: 'Re-disparar',
    };
  }
  if (lever.action === 'devolver') {
    return {
      title: `Devolver ${ref} ao [CC]?`,
      description: 'Reabre o handoff rejeitado pro [CC] retrabalhar: volta a pendente e limpa o ack (PR/gate/aplicado). Sem auto-merge.',
      confirmLabel: 'Devolver',
    };
  }
  return {
    title: `Supersede ${ref}?`,
    description: 'Marca esta versão como obsoleta (substituída) — sai da fila ativa. Append-only: nada é deletado; a substituta chega depois pelo Cowork.',
    confirmLabel: 'Supersede',
  };
}

function HandoffsSkeleton() {
  return (
    <section
      data-testid="forja-mcp-handoffs"
      className="inline-flex w-full items-center justify-center rounded-lg border border-dashed py-12 text-sm text-muted-foreground"
    >
      Carregando handoffs…
    </section>
  );
}

// Heartbeat: "último ingest há Xmin" — vira ALERTA quando o transporte está mudo.
function HeartbeatLine({ heartbeat }: { heartbeat?: HeartbeatInfo }) {
  const silent = heartbeat?.silent ?? true;
  const when = heartbeat?.last_ingest_human ?? 'sem sinal';

  return (
    <div
      data-testid="forja-mcp-heartbeat"
      className={cn(
        'inline-flex items-center gap-1.5 text-[11px]',
        silent ? 'text-warning-fg' : 'text-muted-foreground',
      )}
    >
      {silent ? (
        <AlertTriangle size={12} className="shrink-0" />
      ) : (
        <Radio size={12} className="shrink-0" />
      )}
      <span>
        {silent ? 'transporte sem sinal' : 'transporte ok'} · último ingest{' '}
        <span className="tabular-nums">{when}</span>
        {heartbeat?.host ? <span className="text-muted-foreground"> · {heartbeat.host}</span> : null}
      </span>
    </div>
  );
}

function HandoffRow({
  h,
  busy,
  onLever,
}: {
  h: HandoffItem;
  busy: boolean;
  onLever: (h: HandoffItem, lever: Lever) => void;
}) {
  const status = HANDOFF_STATUS[h.status] ?? HANDOFF_STATUS.pending;
  const gate = HANDOFF_GATE[h.gate] ?? HANDOFF_GATE.na;
  const lever = leverFor(h.status);
  const gateDrill =
    h.pr_url !== null && (h.gate === 'vermelho' || h.gate === 'verde' || h.gate === 'conflito');
  // 'conflito' precisa explicar a divergência no hover (o badge sozinho é críptico).
  const gateHint =
    h.gate === 'conflito'
      ? 'Ack reportou verde, mas um required check do PR está vermelho/pendente no GitHub — abra o PR pra ver qual diverge.'
      : undefined;

  return (
    <div data-testid="forja-handoff-item" className="inline-flex w-full flex-col gap-1.5 px-4 py-3">
      {/* Linha 1: slug vN · tela · status */}
      <div className="inline-flex w-full items-center gap-2">
        <span className="font-mono text-xs font-medium text-foreground">{h.slug}</span>
        <span className="rounded bg-muted px-1 py-0.5 font-mono text-[10px] tabular-nums text-muted-foreground">
          v{h.version}
        </span>
        <span className="min-w-0 truncate text-xs text-muted-foreground">{h.tela}</span>
        <span
          className={cn('ml-auto shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold', status.pill)}
        >
          {status.label}
        </span>
      </div>

      {/* Linha 2: resumo (1ª linha do body_md = DESIGN, não comando) */}
      {h.resumo ? <p className="text-xs leading-relaxed text-foreground/80">{h.resumo}</p> : null}

      {/* Linha 3: sig · arq · gate · PR · idade + lever */}
      <div className="inline-flex w-full flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted-foreground">
        {h.signed ? (
          <span className="inline-flex items-center gap-1" title="assinatura validada no ingest">
            <Lock size={11} className="text-success-fg" /> sig
          </span>
        ) : (
          <span className="inline-flex items-center gap-1 text-warning-fg" title="sem assinatura">
            <Lock size={11} /> sem sig
          </span>
        )}

        <span className="inline-flex items-center gap-1">
          <Files size={11} /> <span className="tabular-nums">{h.files_count}</span> arq
        </span>

        {/* Gate — drill: vermelho/verde/conflito linka pro PR (e pro check que diverge) */}
        {gateDrill ? (
          <a
            href={h.pr_url ?? undefined}
            target="_blank"
            rel="noopener noreferrer"
            data-testid="forja-handoff-gate"
            title={gateHint}
            className={cn('inline-flex items-center gap-1 hover:underline', gate.tone)}
          >
            <span className={cn('inline-block h-1.5 w-1.5 rounded-full', gate.dot)} /> {gate.label}
          </a>
        ) : (
          <span
            data-testid="forja-handoff-gate"
            title={gateHint}
            className={cn('inline-flex items-center gap-1', gate.tone)}
          >
            <span className={cn('inline-block h-1.5 w-1.5 rounded-full', gate.dot)} /> {gate.label}
          </span>
        )}

        {/* PR drill */}
        {h.pr_url ? (
          <a
            href={h.pr_url}
            target="_blank"
            rel="noopener noreferrer"
            data-testid="forja-handoff-pr"
            className="inline-flex items-center gap-1 text-info-fg hover:underline"
          >
            PR <ExternalLink size={11} />
          </a>
        ) : null}

        <span className="tabular-nums">{h.created_at_human ?? '—'}</span>
        <span className="text-muted-foreground/70">por {h.created_by}</span>

        {/* Lever (mutação governada via HandoffLeverService — Fase 2 · ADR 0283).
            SEM merge. Pede confirmação antes de operar. */}
        {lever ? (
          <button
            type="button"
            disabled={busy}
            onClick={() => onLever(h, lever)}
            data-testid="forja-handoff-lever"
            data-lever={lever.action}
            title={`${lever.label} — mutação governada e auditada (ADR 0283), sem auto-merge`}
            className={cn(
              'ml-auto inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-[11px] font-medium transition-colors',
              busy ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
              lever.destructive
                ? 'border-destructive/30 text-destructive-fg hover:bg-destructive-soft'
                : 'border-border text-foreground hover:bg-muted',
            )}
          >
            <lever.Icon size={11} /> {lever.label}
          </button>
        ) : null}
      </div>
    </div>
  );
}

function HandoffsSection({ handoffs, heartbeat }: { handoffs?: HandoffItem[]; heartbeat?: HeartbeatInfo }) {
  const items = handoffs ?? [];
  const [filter, setFilter] = useState<'todos' | HandoffItem['status']>('todos');

  // Lever em confirmação + estado da mutação (busy/erro). POSTa em
  // /forja/handoff/{slug}/lever → HandoffLeverService (a MESMA mutação governada
  // do tool MCP handoff-lever, ADR 0283). Sem auto-merge.
  const [pending, setPending] = useState<{ h: HandoffItem; lever: Lever } | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  function runLever(h: HandoffItem, lever: Lever) {
    setBusy(true);
    setError(null);
    fetch(`/forja/handoff/${encodeURIComponent(h.slug)}/lever`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ action: lever.action, version: h.version }),
    })
      .then(async (r) => {
        const d = await r.json().catch(() => ({}));
        setBusy(false);
        if (!r.ok) {
          setError(d?.error ?? `Erro ${r.status}`);
          return;
        }
        // Reflete na hora: recarrega só as props deferidas da seção (sem cache).
        router.reload({ only: ['handoffs', 'heartbeat'] });
      })
      .catch(() => {
        setBusy(false);
        setError('Erro de rede.');
      });
  }

  const countFor = (key: 'todos' | HandoffItem['status']) =>
    key === 'todos' ? items.length : items.filter((h) => h.status === key).length;

  const visible = filter === 'todos' ? items : items.filter((h) => h.status === filter);
  const confirm = pending ? leverConfirm(pending.lever, pending.h) : null;

  return (
    <section data-testid="forja-mcp-handoffs" className="inline-flex w-full flex-col gap-3">
      {/* Título */}
      <div className="inline-flex items-center gap-2">
        <Workflow size={14} className="text-primary" />
        <h2 className="text-xs font-semibold tracking-wide text-foreground">
          Handoffs F1 → F3 · Cowork → Code
        </h2>
        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] tabular-nums text-muted-foreground">
          {items.length}
        </span>
      </div>

      {/* Filtros por status com contagem */}
      <div className="inline-flex flex-wrap items-center gap-1.5">
        {HANDOFF_FILTERS.map((f) => {
          const active = filter === f.key;
          return (
            <button
              key={f.key}
              type="button"
              onClick={() => setFilter(f.key)}
              data-testid="forja-handoff-filter"
              aria-pressed={active}
              className={cn(
                'inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-medium transition-colors',
                active
                  ? 'border-primary bg-primary/10 text-primary'
                  : 'border-transparent text-muted-foreground hover:bg-muted',
              )}
            >
              {f.label}
              <span className="tabular-nums opacity-70">{countFor(f.key)}</span>
            </button>
          );
        })}
      </div>

      {/* Lista OU empty-state (= heartbeat) */}
      {visible.length === 0 ? (
        <div className="inline-flex w-full flex-col items-start gap-2 rounded-lg border border-dashed px-4 py-8">
          <p className="text-sm text-muted-foreground">
            {items.length === 0 ? 'Nenhum handoff na fila.' : 'Nenhum handoff neste filtro.'}
          </p>
          <HeartbeatLine heartbeat={heartbeat} />
        </div>
      ) : (
        <>
          <div className="inline-flex w-full flex-col divide-y overflow-hidden rounded-lg border">
            {visible.map((h) => (
              <HandoffRow
                key={`${h.slug}-${h.version}`}
                h={h}
                busy={busy}
                onLever={(hh, lever) => setPending({ h: hh, lever })}
              />
            ))}
          </div>
          {/* Heartbeat de rodapé — leitura "viva" mesmo com a fila cheia. */}
          <HeartbeatLine heartbeat={heartbeat} />
        </>
      )}

      {/* Erro da última lever (recusa do "409"/drift OU rede). */}
      {error ? (
        <div
          data-testid="forja-handoff-lever-error"
          className="inline-flex w-full items-start gap-2 rounded-md border border-destructive/20 bg-destructive-soft px-3 py-2 text-xs text-destructive-fg"
        >
          <AlertTriangle size={13} className="mt-0.5 shrink-0" />
          <span>{error}</span>
        </div>
      ) : null}

      {/* Confirmação antes de operar (espelha o AlertDialog do ForjaDossier). */}
      <AlertDialog open={pending !== null} onOpenChange={(o) => { if (!o && !busy) setPending(null); }}>
        <AlertDialogContent data-testid="forja-handoff-lever-confirm">
          <AlertDialogHeader>
            <AlertDialogTitle>{confirm?.title}</AlertDialogTitle>
            <AlertDialogDescription>{confirm?.description}</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction
              variant={pending?.lever.destructive ? 'destructive' : 'default'}
              onClick={() => {
                if (pending) runLever(pending.h, pending.lever);
                setPending(null);
              }}
            >
              {confirm?.confirmLabel ?? 'Confirmar'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  );
}

/** A seção exportada como componente de tela (mesmo corpo, sem mudança de comportamento). */
export default HandoffsSection;
export { HandoffsSkeleton };
