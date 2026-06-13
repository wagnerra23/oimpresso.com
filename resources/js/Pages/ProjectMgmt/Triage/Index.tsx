// DRAFT Onda 2 — PRECISA smoke visual + aprovação SCREENSHOT do Wagner (ADR 0107/0114) antes de merge
//
// @memcofre
//   tela: /project-mgmt/triage
//   module: ProjectMgmt
//   stories: US-TR-301 (lista órfãs) · US-TR-302 (atribuir owner+prio inline) · US-TR-303 (mover cycle/epic)
//   adrs: 0070 (Jira-style PM), UI-0013 (Constituição UI v2), 0039 (cockpit)
//   permissao: copiloto.mcp.usage.all
//   paridade: lista = tool MCP `triage` (McpTask::triage scope)
//
// UI otimista: select inline → PATCH /triage/{id}/assign (reusa tasks-update,
// gera mcp_task_events + notifica owner). Rollback em erro. Task some da lista
// quando deixa de ser órfã (still_triage=false).

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { PRIORITY_BADGE, type Priority } from '@/Components/board/badges';
import { CheckCircle2, HelpCircle, Inbox as InboxIcon, Layers, UserX } from 'lucide-react';

interface TriageTask {
  task_id: string;
  identifier: string | null;
  display_id: string;
  title: string;
  module: string;
  owner: string | null;
  priority_raw: Priority | null;
  priority: Priority;
  status: string;
  type: string | null;
  epic_id: number | null;
  cycle_id: number | null;
  due_date: string | null;
  created_at: string | null;
  needs_owner: boolean;
  needs_prio: boolean;
  is_backlog: boolean;
}

interface CycleOption { id: number; key: string; name: string | null; status: string; is_active: boolean }
interface EpicOption { id: number; key: string; title: string }
interface Kpis { total: number; sem_owner: number; sem_prio: number; backlog: number }

interface Props {
  project: { id: number; key: string; name: string } | null;
  // tasks/cycles/epics/owners/kpis chegam via Inertia::defer (TriageController) →
  // `undefined` no 1º paint. Tipados opcionais + default-guard no destructuring
  // pra NÃO crashar React antes do defer chegar (skill inertia-defer-default,
  // Opção B; espelha OficinaAuto/ServiceOrders/Index.tsx). Sintoma do bug:
  // tasks.filter() sobre undefined → tela branca (PR #1940).
  tasks?: TriageTask[];
  cycles?: CycleOption[];
  epics?: EpicOption[];
  owners?: string[];
  kpis?: Kpis;
  filters: { project: string | null };
}

const NONE = '__none__';
const PRIORITIES: Priority[] = ['p0', 'p1', 'p2', 'p3'];

// Default-guard pros props deferred (kpis começa zerado até o defer resolver).
const EMPTY_KPIS: Kpis = { total: 0, sem_owner: 0, sem_prio: 0, backlog: 0 };

const PRIORITY_LABEL: Record<Priority, string> = {
  p0: 'P0 — urgente',
  p1: 'P1 — alta',
  p2: 'P2 — média',
  p3: 'P3 — baixa',
};

function csrfToken(): string {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

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

type AssignPatch = Partial<{ owner: string; priority: Priority; cycle_id: number | null; epic_id: number | null }>;

function TriageIndex({
  project,
  tasks = [],
  cycles = [],
  epics = [],
  owners = [],
  kpis = EMPTY_KPIS,
}: Props) {
  // Tasks que saíram da lista (still_triage=false) — escondidas localmente até reload.
  const [resolved, setResolved] = useState<Set<string>>(new Set());
  // Overlay otimista por task (campos já aplicados antes do servidor confirmar).
  const [optimistic, setOptimistic] = useState<Record<string, AssignPatch>>({});
  // Banner de erro inline (rollback).
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  // Tasks em voo (desabilita selects).
  const [pending, setPending] = useState<Set<string>>(new Set());
  // Linha em foco pra navegação J/K (mesma mecânica do Board/MyWork).
  const [selectedId, setSelectedId] = useState<string | null>(null);

  useEffect(() => {
    if (!errorMsg) return;
    const tid = setTimeout(() => setErrorMsg(null), 5000);
    return () => clearTimeout(tid);
  }, [errorMsg]);

  const visible = useMemo(
    () => tasks.filter((t) => !resolved.has(t.task_id)),
    [tasks, resolved],
  );

  function assign(taskId: string, patch: AssignPatch) {
    // Otimista: aplica já na UI.
    setOptimistic((prev) => ({ ...prev, [taskId]: { ...prev[taskId], ...patch } }));
    setPending((prev) => new Set(prev).add(taskId));

    fetch(`/project-mgmt/triage/${taskId}/assign`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
      body: JSON.stringify(patch),
    })
      .then(async (r) => {
        setPending((prev) => { const n = new Set(prev); n.delete(taskId); return n; });
        if (!r.ok) {
          // Rollback otimismo
          setOptimistic((prev) => { const n = { ...prev }; delete n[taskId]; return n; });
          let msg = 'Erro ao atribuir. Tenta de novo.';
          try { const d = await r.json(); if (d?.error) msg = d.error; } catch { /* noop */ }
          setErrorMsg(msg);
          return;
        }
        const data = await r.json();
        // Se deixou de ser órfã, some da lista.
        if (data?.still_triage === false) {
          setResolved((prev) => new Set(prev).add(taskId));
        }
        // Reconcilia contadores com servidor.
        router.reload({ only: ['tasks', 'kpis'], preserveScroll: true });
      })
      .catch(() => {
        setPending((prev) => { const n = new Set(prev); n.delete(taskId); return n; });
        setOptimistic((prev) => { const n = { ...prev }; delete n[taskId]; return n; });
        setErrorMsg('Erro de rede. Tenta de novo.');
      });
  }

  function effective(t: TriageTask): TriageTask {
    const o = optimistic[t.task_id];
    if (!o) return t;
    return {
      ...t,
      owner: o.owner !== undefined ? (o.owner || null) : t.owner,
      priority_raw: o.priority !== undefined ? o.priority : t.priority_raw,
      priority: o.priority !== undefined ? o.priority : t.priority,
      cycle_id: o.cycle_id !== undefined ? o.cycle_id : t.cycle_id,
      epic_id: o.epic_id !== undefined ? o.epic_id : t.epic_id,
    };
  }

  // Quando a lista muda e o selecionado some, escolhe o primeiro (igual Board).
  useEffect(() => {
    if (!visible.length) {
      setSelectedId(null);
      return;
    }
    if (selectedId && !visible.find((t) => t.task_id === selectedId)) {
      setSelectedId(visible[0]?.task_id ?? null);
    }
  }, [visible, selectedId]);

  // Atalhos canônicos J/K (navegar) + Enter (abrir no Board) — mesma mecânica
  // inline que Board/Index.tsx e MyWork/Index.tsx. ⌘K (palette global) é dono do
  // AppShellV2 (PMG-002), não re-registramos aqui.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement | null;
      const isTyping = tgt && (
        tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable
      );
      if (isTyping) return;
      if (!visible.length) return;

      const idx = selectedId ? visible.findIndex((t) => t.task_id === selectedId) : -1;
      const cur = idx >= 0 ? visible[idx] : null;

      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const n = idx < 0 ? 0 : Math.min(visible.length - 1, idx + 1);
        setSelectedId(visible[n]?.task_id ?? null);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const p = idx <= 0 ? 0 : idx - 1;
        setSelectedId(visible[p]?.task_id ?? null);
      } else if (e.key === 'Enter' && cur) {
        e.preventDefault();
        router.visit(`/project-mgmt/board?task=${cur.task_id}`);
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [visible, selectedId]);

  // Polling leve (igual MyWork/Board) — re-sincroniza órfãs com servidor.
  useEffect(() => {
    const reload = () => router.reload({ only: ['tasks', 'kpis'], preserveScroll: true });
    const id = setInterval(reload, 30_000);
    window.addEventListener('focus', reload);
    return () => { clearInterval(id); window.removeEventListener('focus', reload); };
  }, []);

  return (
    <>
      <PageHeader
        icon="Inbox"
        title={project ? `${project.name} — Triagem` : 'Triagem'}
        moduleNav
        description={
          `${kpis.total} pra triar · ${kpis.sem_owner} sem dono · ${kpis.sem_prio} sem prioridade · ${kpis.backlog} em backlog`
        }
        action={
          <div className="flex items-center gap-3">
            <span className="hidden md:inline text-[11px] text-muted-foreground">
              <kbd className="px-1 py-0.5 rounded bg-muted">J</kbd>{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">K</kbd> navegar ·{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">Enter</kbd> abrir ·{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">⌘K</kbd> buscar
            </span>
            <a href="/project-mgmt/board" className="text-[11px] text-muted-foreground hover:underline">
              Ver no Board →
            </a>
          </div>
        }
      />

      {errorMsg && (
        <div
          role="alert"
          className="mt-4 flex items-center justify-between rounded-md border border-warning/20 bg-warning-soft px-3 py-2 text-sm text-warning-fg"
        >
          <span>{errorMsg}</span>
          <button type="button" onClick={() => setErrorMsg(null)} className="text-xs font-medium underline-offset-2 hover:underline">
            ok
          </button>
        </div>
      )}

      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="Inbox" tone={kpis.total > 0 ? 'info' : 'success'} label="Pra triar" value={String(kpis.total)} />
        <KpiCard icon="UserX" tone={kpis.sem_owner > 0 ? 'warning' : 'success'} label="Sem dono" value={String(kpis.sem_owner)} />
        <KpiCard icon="HelpCircle" tone={kpis.sem_prio > 0 ? 'warning' : 'success'} label="Sem prioridade" value={String(kpis.sem_prio)} />
        <KpiCard icon="Layers" tone="default" label="Em backlog" value={String(kpis.backlog)} />
      </KpiGrid>

      {visible.length === 0 ? (
        <div className="mt-8 text-center py-16 border-2 border-dashed rounded-xl text-muted-foreground">
          <CheckCircle2 size={28} className="mx-auto mb-3 text-emerald-500/70" />
          <p className="text-base font-medium">Nada pra triar</p>
          <p className="text-sm mt-1">Toda task tem dono, prioridade e saiu do backlog.</p>
        </div>
      ) : (
        <Card className="mt-4">
          <CardContent className="p-0">
            {/* Cabeçalho de colunas (desktop) */}
            <div className="hidden md:grid grid-cols-[minmax(0,1fr)_140px_150px_150px_150px] gap-3 px-4 py-2 border-b text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
              <span>Task</span>
              <span>Dono</span>
              <span>Prioridade</span>
              <span>Cycle</span>
              <span>Epic</span>
            </div>

            <div className="divide-y">
              {visible.map((raw) => {
                const t = effective(raw);
                const isPending = pending.has(t.task_id);
                const isSelected = t.task_id === selectedId;
                return (
                  <div
                    key={t.task_id}
                    aria-current={isSelected ? 'true' : undefined}
                    className={[
                      'grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_140px_150px_150px_150px] gap-3 px-4 py-3 items-center transition-colors',
                      isSelected ? 'bg-muted/60 ring-1 ring-inset ring-primary/60' : 'hover:bg-muted/40',
                    ].join(' ')}
                  >
                    {/* Task: id + título + chips de motivo */}
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <a
                          href={`/project-mgmt/board?task=${t.task_id}`}
                          className="text-[10px] font-mono text-muted-foreground hover:underline shrink-0"
                          title="Abrir no Board"
                        >
                          {t.display_id}
                        </a>
                        <span className="text-[10px] bg-muted px-1.5 py-0.5 rounded shrink-0">{t.module}</span>
                        <span className="text-[10px] text-muted-foreground/70 shrink-0">{timeAgo(t.created_at)}</span>
                      </div>
                      <p className="text-sm font-medium leading-tight mt-0.5 line-clamp-2">{t.title}</p>
                      <div className="flex flex-wrap items-center gap-1 mt-1">
                        {t.needs_owner && (
                          <span className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            <UserX size={10} /> sem dono
                          </span>
                        )}
                        {t.needs_prio && (
                          <span className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                            <HelpCircle size={10} /> sem prioridade
                          </span>
                        )}
                        {t.is_backlog && (
                          <span className="inline-flex items-center gap-1 text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <Layers size={10} /> backlog
                          </span>
                        )}
                      </div>
                    </div>

                    {/* Owner inline */}
                    <div>
                      <span className="md:hidden block text-[10px] uppercase tracking-wide text-muted-foreground">Dono</span>
                      <Select
                        value={t.owner ?? NONE}
                        disabled={isPending}
                        onValueChange={(v) => assign(t.task_id, { owner: v === NONE ? '' : v })}
                      >
                        <SelectTrigger className={`h-8 text-xs ${t.needs_owner ? 'border-amber-300' : ''}`}>
                          <SelectValue placeholder="atribuir…" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value={NONE}>— sem dono —</SelectItem>
                          {owners.map((o) => (
                            <SelectItem key={o} value={o}>{o}</SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    {/* Priority inline */}
                    <div>
                      <span className="md:hidden block text-[10px] uppercase tracking-wide text-muted-foreground">Prioridade</span>
                      <Select
                        value={t.priority_raw ?? NONE}
                        disabled={isPending}
                        onValueChange={(v) => { if (v !== NONE) assign(t.task_id, { priority: v as Priority }); }}
                      >
                        <SelectTrigger className={`h-8 text-xs ${t.needs_prio ? 'border-amber-300' : ''}`}>
                          <SelectValue placeholder="definir…" />
                        </SelectTrigger>
                        <SelectContent>
                          {PRIORITIES.map((p) => (
                            <SelectItem key={p} value={p}>
                              <span className={`inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold mr-1 ${PRIORITY_BADGE[p]}`}>
                                {p.toUpperCase()}
                              </span>
                              {PRIORITY_LABEL[p]}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    {/* Cycle inline (opcional) */}
                    <div>
                      <span className="md:hidden block text-[10px] uppercase tracking-wide text-muted-foreground">Cycle</span>
                      <Select
                        value={t.cycle_id ? String(t.cycle_id) : NONE}
                        disabled={isPending || cycles.length === 0}
                        onValueChange={(v) => assign(t.task_id, { cycle_id: v === NONE ? null : Number(v) })}
                      >
                        <SelectTrigger className="h-8 text-xs">
                          <SelectValue placeholder={cycles.length ? 'cycle…' : '—'} />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value={NONE}>— nenhum —</SelectItem>
                          {cycles.map((c) => (
                            <SelectItem key={c.id} value={String(c.id)}>
                              {c.key}{c.name ? ` — ${c.name}` : ''}{c.is_active ? ' (ativo)' : ''}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    {/* Epic inline (opcional) */}
                    <div>
                      <span className="md:hidden block text-[10px] uppercase tracking-wide text-muted-foreground">Epic</span>
                      <Select
                        value={t.epic_id ? String(t.epic_id) : NONE}
                        disabled={isPending || epics.length === 0}
                        onValueChange={(v) => assign(t.task_id, { epic_id: v === NONE ? null : Number(v) })}
                      >
                        <SelectTrigger className="h-8 text-xs">
                          <SelectValue placeholder={epics.length ? 'epic…' : '—'} />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value={NONE}>— nenhum —</SelectItem>
                          {epics.map((e) => (
                            <SelectItem key={e.id} value={String(e.id)}>
                              {e.key} — {e.title}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                );
              })}
            </div>
          </CardContent>
        </Card>
      )}

      <p className="mt-4 text-xs text-muted-foreground">
        <InboxIcon size={12} className="inline mr-1 -mt-0.5" />
        Mesma fila da tool MCP <code className="font-mono">triage</code>. Atribuir dono/prioridade registra evento em{' '}
        <code className="font-mono">mcp_task_events</code> e notifica o dono. Task some daqui quando deixa de ser órfã.
      </p>
    </>
  );
}

TriageIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Triagem"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Triagem' }]}
  >
    {page}
  </AppShellV2>
);

export default TriageIndex;
