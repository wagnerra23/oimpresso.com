// Forja — aba MCP do cockpit.
//
// DUAS CAMADAS:
//   1. HANDOFFS (REAL · Fase 1 ADR 0283) — seção do topo: projeta os handoffs de
//      design (Cowork→Code, F1→F3) de `cowork_handoffs` via props deferidas
//      (`handoffs`/`heartbeat`). Status/gate/sig REAIS; sem auto-merge (o merge é
//      o 1-clique do [W]). As levers (re-disparar/devolver/supersede) POSTam em
//      /forja/handoff/{slug}/lever → HandoffLeverService (mesma mutação governada
//      do tool MCP handoff-lever, Fase 2 · PR-7b).
//   2. CONTRATO/TOKENS/AUDITORIA (MOCKADO por design) — vitrine do contrato; o
//      enforce real é do servidor TeamMcp ([CL]). Default = read + propose;
//      merge e constituicao.edit NEGADOS no contrato, não por convenção.
//
// DS v6: só tokens semânticos (primary/success/warning/info/destructive/muted/
// foreground/border), tabular-nums em números/horários, layout via inline-flex/
// inline-grid (nunca flex/grid solto), máx rounded-lg, data-testid locators.
//
// Tier 0 (ADR 0081): NUNCA exibir/logar token raw — só o nome lógico do token.

import { Deferred, router } from '@inertiajs/react';
import {
  AlertTriangle,
  ExternalLink,
  Files,
  KeyRound,
  Layers,
  Lock,
  type LucideIcon,
  Radio,
  RefreshCw,
  ScrollText,
  ShieldCheck,
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

// --- Contrato de ferramentas (estático, do protótipo aprovado) ----------------

type Perm = 'PERMITIDO' | 'PROPÕE' | 'NEGADO';

interface Tool {
  ferramenta: string;
  acao: string;
  permissao: Perm;
  // Texto-extra do contrato (ex.: "→[W] aprova", "→transporte", "(só [W2])").
  detalhe?: string;
}

const TOOLS: Tool[] = [
  { ferramenta: 'backlog.read', acao: 'ler issues/filtros', permissao: 'PERMITIDO' },
  { ferramenta: 'changelog.read', acao: 'o que shippou', permissao: 'PERMITIDO' },
  { ferramenta: 'issue.transition', acao: 'mover fase', permissao: 'PROPÕE', detalhe: '→ [W] aprova' },
  { ferramenta: 'changelog.append', acao: 'registrar entrega', permissao: 'PROPÕE', detalhe: '→ transporte' },
  { ferramenta: 'adr.propose', acao: 'cria _PROPOSTA', permissao: 'PROPÕE', detalhe: 'nunca decisions/NNNN' },
  { ferramenta: 'git.merge', acao: 'fechar PR', permissao: 'NEGADO', detalhe: 'só [W2]' },
  { ferramenta: 'constituicao.edit', acao: 'ADR/PROTOCOL/BRIEFING', permissao: 'NEGADO', detalhe: 'só [W]' },
  // PR-C (ADR 0283 · Fase 1): as tools do loop de handoff que alimentam a seção acima.
  { ferramenta: 'handoff-pending', acao: 'puxar handoff F1→F3', permissao: 'PERMITIDO', detalhe: 'assinado' },
  { ferramenta: 'handoff-ack', acao: 'confirmar aplicado + gate', permissao: 'PROPÕE', detalhe: '422 sem gate verde' },
];

// Pílula de permissão por token semântico DS v6:
//   PERMITIDO = success · PROPÕE = warning · NEGADO = destructive.
const PERM_PILL: Record<Perm, string> = {
  PERMITIDO: 'bg-success/15 text-success-fg',
  PROPÕE: 'bg-warning-soft text-warning-fg',
  NEGADO: 'bg-destructive-soft text-destructive-fg',
};

// --- Tokens ativos (estático) -------------------------------------------------
// Tier 0 (ADR 0081): só o nome LÓGICO do token — nunca o valor raw.

interface Token {
  nome: string;
  ator: string; // selo [CC]/[CL]/[CD]
  escopo: string;
  exp: string;
  uso: string;
}

const TOKENS: Token[] = [
  { nome: 'frj_cc_live', ator: 'CC', escopo: 'read + propose', exp: 'exp 30d', uso: 'uso há 2 min' },
  { nome: 'frj_cl_ci', ator: 'CL', escopo: 'read + propose', exp: 'exp 90d', uso: 'uso há 1 h' },
  { nome: 'frj_cd_rev', ator: 'CD', escopo: 'read', exp: 'exp 30d', uso: 'uso há 3 h' },
];

// --- Auditoria (estático) — toda ação de agente, regra 6 mecanizada -----------

type Resultado = 'ok' | 'pendente' | 'negado';

interface AuditRow {
  ts: string;
  ator: string;
  acao: string;
  detalhe: string;
  resultado: Resultado;
  resultadoLabel: string;
}

const AUDIT: AuditRow[] = [
  { ts: '14:21', ator: 'CC', acao: 'backlog.read', detalhe: 'onda=FA-1', resultado: 'ok', resultadoLabel: 'ok' },
  { ts: '14:19', ator: 'CC', acao: 'adr.propose', detalhe: '--origin-DEV', resultado: 'ok', resultadoLabel: 'proposta criada' },
  { ts: '13:50', ator: 'CL', acao: 'issue.transition', detalhe: 'FORJA-141 → F3', resultado: 'pendente', resultadoLabel: 'aguarda [W]' },
  { ts: '12:30', ator: 'CC', acao: 'git.merge', detalhe: 'PR 2417', resultado: 'negado', resultadoLabel: 'NEGADO — só [W2]' },
  { ts: '11:05', ator: 'CD', acao: 'changelog.read', detalhe: 'desde 09/06', resultado: 'ok', resultadoLabel: 'ok' },
  { ts: '10:02', ator: 'CC', acao: 'constituicao.edit', detalhe: 'ADR 0235', resultado: 'negado', resultadoLabel: 'NEGADO — só [W]' },
];

const RESULTADO_TONE: Record<Resultado, string> = {
  ok: 'text-success-fg',
  pendente: 'text-warning-fg',
  negado: 'text-destructive-fg',
};

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
  // gate derivado do gate_status (mesma regra verde do handoff-ack).
  gate: 'verde' | 'vermelho' | 'rodando' | 'na';
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
const HANDOFF_GATE: Record<HandoffItem['gate'], { label: string; tone: string; dot: string }> = {
  verde: { label: 'gate ok', tone: 'text-success-fg', dot: 'bg-success' },
  vermelho: { label: 'gate falhou', tone: 'text-destructive-fg', dot: 'bg-destructive' },
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
  const gateDrill = h.pr_url !== null && (h.gate === 'vermelho' || h.gate === 'verde');

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

        {/* Gate — drill: vermelho/verde linka pro PR (e pro check que falhou) */}
        {gateDrill ? (
          <a
            href={h.pr_url ?? undefined}
            target="_blank"
            rel="noopener noreferrer"
            data-testid="forja-handoff-gate"
            className={cn('inline-flex items-center gap-1 hover:underline', gate.tone)}
          >
            <span className={cn('inline-block h-1.5 w-1.5 rounded-full', gate.dot)} /> {gate.label}
          </a>
        ) : (
          <span
            data-testid="forja-handoff-gate"
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

interface Props {
  /** Handoffs reais (deferido). undefined enquanto o partial reload não chega. */
  handoffs?: HandoffItem[];
  /** Heartbeat do ingest (deferido) — distingue "ocioso" de "transporte sem sinal". */
  heartbeat?: HeartbeatInfo;
}

export default function ForjaMcp({ handoffs, heartbeat }: Props) {
  return (
    <div data-testid="forja-mcp" className="inline-flex w-full flex-col gap-6">
      {/* 1. HANDOFFS — REAL (Fase 1 · ADR 0283). Deferido: o contrato estático
          abaixo pinta na hora; só esta seção espera o partial reload. */}
      <Deferred data={['handoffs', 'heartbeat']} fallback={<HandoffsSkeleton />}>
        <HandoffsSection handoffs={handoffs} heartbeat={heartbeat} />
      </Deferred>

      {/* 2. CONTRATO / TOKENS / AUDITORIA — MOCKADO por design (vitrine do contrato). */}
      {/* Banner topo — tom muted/aviso. Estabelece o contrato mental: MOCKADO. */}
      <div className="inline-flex w-full items-start gap-2 rounded-lg border border-warning-soft bg-warning-soft px-4 py-3 text-warning-fg">
        <AlertTriangle size={16} className="mt-0.5 shrink-0" />
        <p className="text-xs leading-relaxed">
          <strong>MOCKADO</strong> — Contrato e auditoria como design — o enforce real é do servidor{' '}
          <span className="font-medium">TeamMcp</span> ([CL]). Default ={' '}
          <span className="font-mono">read + propose</span>; <span className="font-mono">merge</span> e{' '}
          <span className="font-mono">constituicao.edit</span> negados no contrato, não por convenção.
        </p>
      </div>

      {/* CONTRATO DE FERRAMENTAS */}
      <section className="inline-flex w-full flex-col gap-2">
        <div className="inline-flex items-center gap-2">
          <ShieldCheck size={14} className="text-muted-foreground" />
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground">
            CONTRATO DE FERRAMENTAS
          </h2>
        </div>

        <div className="overflow-hidden rounded-lg border">
          {/* Cabeçalho (grid de 3 zonas) */}
          <div className="inline-grid w-full grid-cols-[minmax(9rem,1.2fr)_minmax(0,2fr)_minmax(8rem,auto)] gap-3 border-b bg-muted/40 px-4 py-2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
            <span>Ferramenta</span>
            <span>Ação</span>
            <span className="text-right">Permissão</span>
          </div>

          <div className="divide-y">
            {TOOLS.map((t) => (
              <div
                key={t.ferramenta}
                className="inline-grid w-full grid-cols-[minmax(9rem,1.2fr)_minmax(0,2fr)_minmax(8rem,auto)] items-center gap-3 px-4 py-2.5"
              >
                <span className="font-mono text-xs text-foreground">{t.ferramenta}</span>
                <span className="min-w-0 truncate text-xs text-muted-foreground">{t.acao}</span>
                <span className="inline-flex items-center justify-end gap-1.5">
                  <span
                    className={cn(
                      'shrink-0 rounded px-1.5 py-0.5 text-[10px] font-semibold',
                      PERM_PILL[t.permissao],
                    )}
                    data-testid="forja-mcp-perm"
                  >
                    {t.permissao}
                  </span>
                  {t.detalhe && (
                    <span className="hidden text-[10px] text-muted-foreground sm:inline">
                      {t.detalhe}
                    </span>
                  )}
                </span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* TOKENS ATIVOS */}
      <section className="inline-flex w-full flex-col gap-2">
        <div className="inline-flex items-center gap-2">
          <KeyRound size={14} className="text-muted-foreground" />
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground">
            TOKENS ATIVOS
          </h2>
        </div>

        <div className="inline-grid w-full grid-cols-1 gap-3 sm:grid-cols-3">
          {TOKENS.map((tk) => (
            <div
              key={tk.nome}
              className="inline-flex flex-col gap-2 rounded-lg border p-3"
              data-testid="forja-mcp-token"
            >
              <div className="inline-flex items-center justify-between gap-2">
                {/* Nome LÓGICO do token — NUNCA o valor raw (Tier 0 ADR 0081). */}
                <span className="font-mono text-xs font-medium text-foreground">{tk.nome}</span>
                <span className="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-muted-foreground">
                  [{tk.ator}]
                </span>
              </div>
              <div className="inline-flex flex-wrap items-center gap-1.5 text-[10px] text-muted-foreground">
                <span className="rounded bg-muted px-1.5 py-0.5">{tk.escopo}</span>
                <span className="tabular-nums">· {tk.exp}</span>
                <span className="tabular-nums">· {tk.uso}</span>
              </div>
              <button
                type="button"
                className="mt-1 inline-flex w-fit items-center rounded-md border border-destructive/30 px-2 py-1 text-[11px] font-medium text-destructive-fg transition-colors hover:bg-destructive-soft"
                data-testid="forja-mcp-revogar"
              >
                revogar
              </button>
            </div>
          ))}
        </div>
      </section>

      {/* AUDITORIA — toda ação de agente (regra 6 mecanizada) */}
      <section className="inline-flex w-full flex-col gap-2">
        <div className="inline-flex items-center gap-2">
          <ScrollText size={14} className="text-muted-foreground" />
          <h2 className="text-xs font-semibold tracking-wide text-muted-foreground">
            AUDITORIA · TODA AÇÃO DE AGENTE (regra 6 mecanizada)
          </h2>
        </div>

        <div className="overflow-hidden rounded-lg border">
          {/* Cabeçalho */}
          <div className="inline-grid w-full grid-cols-[3rem_3rem_minmax(8rem,1.2fr)_minmax(0,1.5fr)_minmax(7rem,auto)] gap-3 border-b bg-muted/40 px-4 py-2 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
            <span>ts</span>
            <span>ator</span>
            <span>ação</span>
            <span>detalhe</span>
            <span className="text-right">resultado</span>
          </div>

          <div className="divide-y">
            {AUDIT.map((row, i) => (
              <div
                key={`${row.ts}-${row.acao}-${i}`}
                className={cn(
                  'inline-grid w-full grid-cols-[3rem_3rem_minmax(8rem,1.2fr)_minmax(0,1.5fr)_minmax(7rem,auto)] items-center gap-3 px-4 py-2 text-xs',
                  // Linhas NEGADO com tom destructive sutil.
                  row.resultado === 'negado' && 'bg-destructive-soft/40',
                )}
                data-testid="forja-mcp-audit-row"
              >
                <span className="font-mono tabular-nums text-muted-foreground">{row.ts}</span>
                <span className="font-mono text-[11px] text-muted-foreground">[{row.ator}]</span>
                <span className="font-mono text-foreground">{row.acao}</span>
                <span className="min-w-0 truncate text-muted-foreground">{row.detalhe}</span>
                <span className={cn('text-right font-medium', RESULTADO_TONE[row.resultado])}>
                  {row.resultadoLabel}
                </span>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
