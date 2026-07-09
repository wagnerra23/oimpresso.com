// @memcofre
//   tela: /team-mcp/cc-sessions
//   module: TeamMcp (split do Copiloto)
//   forja: PR-2 re-skin DS v6 — visual-comparison em
//          memory/requisitos/TeamMcp/cc-sessions-visual-comparison.md (approved [W] 2026-06-16)
//   stories: MEM-CC-UI-1 (SPEC-cc-sessions)
//   permissao: copiloto.cc.read.team
//
// Feed cronológico de sessões Claude Code do time (gramática Changelog Forja) +
// drawer lateral pra thread. Toda sessão = agente (Claude Code) em nome de humano (dev).
// Atalhos: J/K navegar · Enter/click abre drawer · / busca · Esc fecha.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { Bot, GitBranch, User } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import PageHeader from '@/Components/shared/PageHeader';
import ForjaHub from '@/Pages/team-mcp/Forja/_components/ForjaHub';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { cn } from '@/Lib/utils';
import SessionDrawer from './_components/SessionDrawer';
import { brl, fmtDuration, fmtRelative, num, sessionStatusMeta } from './_components/sessionTokens';

interface Dev { id: number; nome: string }

interface Session {
  id: number;
  session_uuid: string;
  user_id: number;
  user_nome: string;
  business_id: number | null;
  project_path: string;
  git_branch: string | null;
  cc_version: string | null;
  entrypoint: string | null;
  started_at: string | null;
  ended_at: string | null;
  total_messages: number;
  total_tokens: number;
  total_cost_brl: number;
  status: 'active' | 'closed' | 'archived';
  summary_auto: string | null;
  metadata: Record<string, unknown> | null;
}

interface Paginator<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
  from: number;
  to: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Filters {
  user_id?: number | null;
  from?: string;
  to?: string;
  q?: string;
  status?: string;
  project_path?: string;
}

interface Kpis {
  sessions_hoje: number;
  sessions_total: number;
  custo_hoje_brl: number;
  custo_30d_brl: number;
  devs_ativos_hoje: number;
  tools_top: Array<{ tool: string; count: number }>;
}

interface Props {
  // sessions/kpis/devs/projects deferidos → undefined no 1º paint (default-guard).
  sessions?: Paginator<Session>;
  filters: Filters;
  kpis?: Kpis;
  devs?: Dev[];
  projects?: string[];
  permissions: { read_all: boolean; curate: boolean };
}

const ALL = '__all__';

function CcSessionsIndex({ sessions, filters, kpis, devs, projects, permissions }: Props) {
  const isLoading = !sessions || !kpis;
  const sData = useMemo(() => sessions?.data ?? [], [sessions]);
  const k: Kpis = kpis ?? { sessions_hoje: 0, sessions_total: 0, custo_hoje_brl: 0, custo_30d_brl: 0, devs_ativos_hoje: 0, tools_top: [] };
  const devList = devs ?? [];
  const projList = projects ?? [];

  const [search, setSearch] = useState(filters.q ?? '');
  const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
  const [openUuid, setOpenUuid] = useState<string | null>(null);
  const searchRef = useRef<HTMLInputElement | null>(null);

  // Debounce busca → server (FULLTEXT summary)
  useEffect(() => {
    if (search === (filters.q ?? '')) return;
    const t = setTimeout(() => applyFilter({ q: search }), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  // Atalhos J/K/Enter/Esc//
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement | null;
      const typing = !!tgt && (tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable);
      if (e.key === '/' && !typing) { e.preventDefault(); searchRef.current?.focus(); return; }
      if (e.key === 'Escape') { if (openUuid) setOpenUuid(null); return; }
      if (typing || sData.length === 0) return;
      const idx = selectedUuid ? sData.findIndex((s) => s.session_uuid === selectedUuid) : -1;
      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const n = sData[idx < 0 ? 0 : Math.min(sData.length - 1, idx + 1)];
        if (n) setSelectedUuid(n.session_uuid);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const p = sData[idx <= 0 ? 0 : idx - 1];
        if (p) setSelectedUuid(p.session_uuid);
      } else if (e.key === 'Enter' && selectedUuid) {
        e.preventDefault();
        setOpenUuid(selectedUuid);
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [sData, selectedUuid, openUuid]);

  function applyFilter(patch: Partial<Filters>) {
    const params: Record<string, unknown> = { ...filters, ...patch, page: 1 };
    Object.keys(params).forEach((key) => {
      const v = params[key];
      if (v === '' || v === null || v === undefined) delete params[key];
    });
    router.get('/team-mcp/cc-sessions', params as Record<string, string>, {
      preserveScroll: true, preserveState: true, only: ['sessions', 'filters', 'kpis'],
    });
  }

  function openSession(uuid: string) { setSelectedUuid(uuid); setOpenUuid(uuid); }

  const hasFilters = !!(filters.q || filters.user_id || filters.from || filters.to || filters.status || filters.project_path);

  return (
    <>
      <ForjaHub active="cc" />

      <PageHeader
        icon="code-2"
        title="Atividade CC"
        description={`Sessões Claude Code do time (agente em nome do dev). ${permissions.read_all ? 'Visão admin.' : 'Visão do dev.'}`}
      />

      <KpiGrid cols={4} className="mt-4">
        <KpiCard icon="activity" tone="info" label="Sessões hoje" value={isLoading ? '—' : num(k.sessions_hoje)} description={isLoading ? undefined : `${num(k.sessions_total)} no total`} />
        <KpiCard icon="users" tone="default" label="Devs ativos hoje" value={isLoading ? '—' : num(k.devs_ativos_hoje)} description={permissions.read_all ? 'time' : 'você'} />
        <KpiCard icon="dollar-sign" tone="warning" label="Custo hoje" value={isLoading ? '—' : brl(k.custo_hoje_brl)} description={isLoading ? undefined : `30d: ${brl(k.custo_30d_brl)}`} />
        <KpiCard icon="zap" tone="default" label="Top tools" value={isLoading ? '—' : String(k.tools_top.length)} description={k.tools_top.slice(0, 3).map((t) => `${t.tool}(${t.count})`).join(' · ') || '—'} />
      </KpiGrid>

      {/* Toolbar */}
      <div className="mt-4 flex flex-wrap items-end gap-3 rounded-lg border bg-card px-3 py-3">
        <div className="min-w-[200px] flex-1">
          <Label className="text-xs">Busca <span className="text-[10px] text-muted-foreground">(/, summary)</span></Label>
          <Input ref={searchRef} placeholder="summary ou keyword…" value={search} onChange={(e) => setSearch(e.target.value)} className="h-8 text-xs" data-testid="search" />
        </div>
        {permissions.read_all && devList.length > 1 && (
          <div className="w-40">
            <Label className="text-xs">Dev</Label>
            <Select value={filters.user_id?.toString() ?? ALL} onValueChange={(v) => applyFilter({ user_id: v === ALL ? null : Number(v) })}>
              <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {devList.map((d) => <SelectItem key={d.id} value={d.id.toString()}>{d.nome}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>
        )}
        <div className="w-32">
          <Label className="text-xs">Status</Label>
          <Select value={filters.status ?? ALL} onValueChange={(v) => applyFilter({ status: v === ALL ? '' : v })}>
            <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
            <SelectContent>
              <SelectItem value={ALL}>Todos</SelectItem>
              <SelectItem value="active">Ativa</SelectItem>
              <SelectItem value="closed">Fechada</SelectItem>
              <SelectItem value="archived">Arquivada</SelectItem>
            </SelectContent>
          </Select>
        </div>
        {projList.length > 1 && (
          <div className="w-48">
            <Label className="text-xs">Projeto</Label>
            <Select value={filters.project_path ?? ALL} onValueChange={(v) => applyFilter({ project_path: v === ALL ? '' : v })}>
              <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value={ALL}>Todos</SelectItem>
                {projList.map((p) => <SafeSelectItem key={p} value={p}>{p}</SafeSelectItem>)}
              </SelectContent>
            </Select>
          </div>
        )}
        {hasFilters && (
          <Button variant="ghost" className="h-8 text-xs" onClick={() => { setSearch(''); applyFilter({ q: '', user_id: null, status: '', project_path: '' }); }}>
            Limpar
          </Button>
        )}
        <div className="ml-auto hidden text-[11px] text-muted-foreground lg:block">
          <kbd className="rounded bg-muted px-1 py-0.5">J</kbd>/<kbd className="rounded bg-muted px-1 py-0.5">K</kbd> navegar · <kbd className="rounded bg-muted px-1 py-0.5">↵</kbd> abrir · <kbd className="rounded bg-muted px-1 py-0.5">/</kbd> buscar
        </div>
      </div>

      {/* Feed */}
      {isLoading ? (
        <div className="mt-4 space-y-2" data-testid="cc-skeleton">
          {Array.from({ length: 8 }).map((_, i) => <div key={i} className="h-16 animate-pulse rounded-md bg-muted/50" />)}
        </div>
      ) : sData.length === 0 && !hasFilters ? (
        <div className="mt-4">
          <EmptyState
            icon="inbox"
            title="Nenhuma sessão Claude Code ingestada ainda"
            description="O schema mcp_cc_* está pronto; falta o watcher Node nos devs (POST /api/cc/ingest)."
            action={
              <a
                href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/requisitos/Jana/SPEC-cc-sessions.md"
                target="_blank" rel="noopener noreferrer"
                className="text-sm text-primary hover:underline"
              >Ver SPEC do watcher →</a>
            }
          />
        </div>
      ) : sData.length === 0 ? (
        <div className="mt-4">
          <EmptyState icon="search" variant="search" title="Nada bate com os filtros" description="Tenta ajustar a busca ou limpar os filtros." />
        </div>
      ) : (
        <>
          <div className="mt-4 flex items-center justify-between text-[11px] text-muted-foreground">
            <span className="tabular-nums">{num(sessions!.total)} sessões{hasFilters ? ' (filtrado)' : ''} · {sessions!.from}–{sessions!.to} · pág {sessions!.current_page}/{sessions!.last_page}</span>
          </div>
          <div className="mt-2 overflow-hidden rounded-lg border bg-card" data-testid="cc-feed">
            {sData.map((s) => {
              const sel = s.session_uuid === selectedUuid;
              const meta = sessionStatusMeta(s.status);
              return (
                <div
                  key={s.id}
                  role="button"
                  tabIndex={0}
                  data-testid="cc-feed-item"
                  onClick={() => openSession(s.session_uuid)}
                  onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openSession(s.session_uuid); } }}
                  className={cn('flex cursor-pointer gap-3 border-b border-border/60 px-3 py-2.5 last:border-b-0', sel ? 'bg-primary/10' : 'hover:bg-muted/50')}
                >
                  <span className={cn('mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full', meta.dot)} title={meta.label} />
                  <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2 text-xs">
                      <span className="inline-flex items-center gap-1 font-medium">
                        <User size={12} className="text-muted-foreground" aria-hidden /> {s.user_nome}
                      </span>
                      <span className="inline-flex items-center gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary" title="Sessão de agente (Claude Code)">
                        <Bot size={10} /> CC{s.cc_version ? ` v${s.cc_version}` : ''}
                      </span>
                      <span className="truncate font-mono text-[10px] text-muted-foreground">{s.project_path}</span>
                      {s.git_branch && (
                        <span className="hidden items-center gap-1 font-mono text-[10px] text-muted-foreground sm:inline-flex">
                          <GitBranch size={10} /> {s.git_branch}
                        </span>
                      )}
                      <span className="ml-auto shrink-0 text-[10px] text-muted-foreground" title={s.started_at ?? ''}>{fmtRelative(s.started_at)}</span>
                    </div>
                    <p className="mt-0.5 line-clamp-2 text-xs text-foreground/90">
                      {s.summary_auto ?? <span className="text-muted-foreground">(sem summary)</span>}
                    </p>
                    <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10px] text-muted-foreground tabular-nums">
                      <span>{num(s.total_messages)} msgs</span>
                      <span>{num(s.total_tokens)} tokens</span>
                      <span className="font-mono">{brl(s.total_cost_brl)}</span>
                      <span>{fmtDuration(s.started_at, s.ended_at)}</span>
                      <span className="inline-flex items-center gap-1">
                        <span className={cn('inline-block h-1.5 w-1.5 rounded-full', meta.dot)} /> {meta.label}
                      </span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {sessions!.last_page > 1 && (
            <div className="mt-3 flex flex-wrap justify-center gap-1">
              {sessions!.links.map((l, i) => (
                <Button
                  key={i}
                  variant={l.active ? 'default' : 'outline'}
                  size="sm"
                  className="h-7 px-2 text-xs"
                  disabled={!l.url}
                  onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true, preserveState: true, only: ['sessions'] })}
                  dangerouslySetInnerHTML={{ __html: l.label }}
                />
              ))}
            </div>
          )}
        </>
      )}

      <SessionDrawer sessionUuid={openUuid} onClose={() => setOpenUuid(null)} />
    </>
  );
}

CcSessionsIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="CC Sessions — Forja" breadcrumbItems={[{ label: 'Forja' }, { label: 'CC Sessions' }]}>
    {page}
  </AppShellV2>
);

export default CcSessionsIndex;
