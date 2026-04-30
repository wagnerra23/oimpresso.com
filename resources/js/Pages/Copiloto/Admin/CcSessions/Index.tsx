// @memcofre
//   tela: /copiloto/admin/cc-sessions
//   module: Copiloto
//   stories: MEM-CC-UI-1 (SPEC memory/requisitos/Copiloto/SPEC-cc-sessions.md)
//   permissao: copiloto.cc.read.team
//
// V1: lista de sessões CC do time + preview lateral (split layout) com thread.
// Reaproveita patterns de /copiloto/admin/memoria.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { Label } from '@/Components/ui/label';
import { ScrollArea } from '@/Components/ui/scroll-area';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { toast } from 'sonner';

interface Dev { id: number; nome: string; }

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
  sessions: Paginator<Session>;
  filters: Filters;
  kpis: Kpis;
  devs: Dev[];
  projects: string[];
  permissions: { read_all: boolean; curate: boolean };
}

interface Message {
  id: number;
  msg_uuid: string;
  parent_uuid: string | null;
  msg_type: 'user' | 'assistant' | 'tool_use' | 'tool_result' | 'attachment' | 'hook' | 'system';
  role: string | null;
  tool_name: string | null;
  content_text: string | null;
  tokens_in: number | null;
  tokens_out: number | null;
  cache_read: number | null;
  cost_usd: number;
  ts: string | null;
}

interface SessionDetail {
  session: Session;
  messages: Message[];
  truncated: boolean;
}

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

const num = (v: number) => new Intl.NumberFormat('pt-BR').format(v ?? 0);

function fmtDateTime(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
  });
}

function fmtRelative(iso: string | null): string {
  if (!iso) return '—';
  const diffSec = (Date.now() - new Date(iso).getTime()) / 1000;
  if (diffSec < 60) return 'agora';
  if (diffSec < 3600) return `${Math.floor(diffSec / 60)}min atrás`;
  if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h atrás`;
  if (diffSec < 86400 * 7) return `${Math.floor(diffSec / 86400)}d atrás`;
  return fmtDateTime(iso);
}

function fmtDuration(start: string | null, end: string | null): string {
  if (!start) return '—';
  const startMs = new Date(start).getTime();
  const endMs = end ? new Date(end).getTime() : Date.now();
  const sec = Math.floor((endMs - startMs) / 1000);
  if (sec < 60) return `${sec}s`;
  if (sec < 3600) return `${Math.floor(sec / 60)}m${sec % 60}s`;
  return `${Math.floor(sec / 3600)}h${Math.floor((sec % 3600) / 60)}m`;
}

function toolColor(tool: string | null): string {
  const map: Record<string, string> = {
    Bash:      'bg-green-100 text-green-800',
    Edit:      'bg-orange-100 text-orange-800',
    Read:      'bg-gray-100 text-gray-700',
    Grep:      'bg-blue-100 text-blue-800',
    Glob:      'bg-blue-100 text-blue-800',
    Write:     'bg-purple-100 text-purple-800',
    WebSearch: 'bg-cyan-100 text-cyan-800',
    WebFetch:  'bg-cyan-100 text-cyan-800',
    Agent:     'bg-pink-100 text-pink-800',
    Task:      'bg-pink-100 text-pink-800',
  };
  return map[tool ?? ''] ?? 'bg-gray-100 text-gray-700';
}

function CcSessionsIndex(props: Props) {
  const { sessions, filters, kpis, devs, projects, permissions } = props;
  const [search, setSearch] = useState(filters.q ?? '');
  const [selectedUuid, setSelectedUuid] = useState<string | null>(null);
  const [detail, setDetail] = useState<SessionDetail | null>(null);
  const [loadingDetail, setLoadingDetail] = useState(false);
  const [previewOpen, setPreviewOpen] = useState(false);
  const previewRef = useRef<HTMLDivElement | null>(null);
  const searchRef = useRef<HTMLInputElement | null>(null);

  // Debounce busca
  useEffect(() => {
    if (search === (filters.q ?? '')) return;
    const t = setTimeout(() => applyFilter({ q: search }), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search]);

  // Scroll-to-top quando muda doc
  useEffect(() => {
    if (detail && previewRef.current) previewRef.current.scrollTop = 0;
  }, [detail?.session?.session_uuid]);

  // Atalhos j/k/Esc/`/`
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const tag = (e.target as HTMLElement)?.tagName;
      const isTyping = tag === 'INPUT' || tag === 'TEXTAREA' || (e.target as HTMLElement)?.isContentEditable;
      if (e.key === '/' && !isTyping) { e.preventDefault(); searchRef.current?.focus(); return; }
      if (e.key === 'Escape' && previewOpen && !isTyping) { e.preventDefault(); closePreview(); return; }
      if (isTyping) return;
      if (e.key === 'j' || e.key === 'k') {
        e.preventDefault();
        if (sessions.data.length === 0) return;
        const idx = selectedUuid ? sessions.data.findIndex(s => s.session_uuid === selectedUuid) : -1;
        let next = idx;
        if (e.key === 'j') next = Math.min(sessions.data.length - 1, idx + 1);
        if (e.key === 'k') next = Math.max(0, idx === -1 ? 0 : idx - 1);
        if (next !== idx) openSession(sessions.data[next].session_uuid);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sessions.data, selectedUuid, previewOpen]);

  function applyFilter(patch: Partial<Filters>) {
    const params = { ...filters, ...patch, page: 1 };
    Object.keys(params).forEach((k) => {
      const v = (params as Record<string, unknown>)[k];
      if (v === '' || v === null || v === undefined) delete (params as Record<string, unknown>)[k];
    });
    router.get('/copiloto/admin/cc-sessions', params, {
      preserveScroll: true, preserveState: true, only: ['sessions', 'filters', 'kpis'],
    });
  }

  function openSession(uuid: string) {
    setSelectedUuid(uuid);
    setDetail(null);
    setPreviewOpen(true);
    setLoadingDetail(true);
    fetch(`/copiloto/admin/cc-sessions/${uuid}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
    })
      .then(r => r.json())
      .then((d: SessionDetail) => setDetail(d))
      .catch(() => toast.error('Erro ao carregar sessão'))
      .finally(() => setLoadingDetail(false));
  }

  function closePreview() {
    setPreviewOpen(false);
    setSelectedUuid(null);
    setDetail(null);
  }

  const hasFilters = !!(filters.q || filters.user_id || filters.from || filters.to || filters.status || filters.project_path);
  const isEmpty = sessions.total === 0 && !hasFilters;

  // ─── Lista ────────────────────────────────────────────────────────────
  const ListPanel = (
    <Card className="flex flex-col h-full">
      <CardHeader className="py-3 border-b flex-row items-center justify-between space-y-0 gap-2">
        <CardTitle className="text-sm flex items-center gap-2">
          <span>{num(sessions.total)} sessões</span>
          {hasFilters && <Badge variant="outline" className="text-[10px]">filtrado</Badge>}
          {sessions.total > 0 && (
            <span className="text-xs text-muted-foreground font-normal">
              {sessions.from}-{sessions.to} · pág {sessions.current_page}/{sessions.last_page}
            </span>
          )}
        </CardTitle>
        <div className="text-[10px] text-muted-foreground hidden md:flex items-center gap-2">
          <kbd className="px-1.5 py-0.5 rounded bg-muted border">j/k</kbd>
          <kbd className="px-1.5 py-0.5 rounded bg-muted border">/</kbd>
          <kbd className="px-1.5 py-0.5 rounded bg-muted border">Esc</kbd>
        </div>
      </CardHeader>
      <CardContent className="p-0 flex-1 overflow-hidden">
        {isEmpty ? (
          <div className="p-8 text-center text-muted-foreground space-y-3">
            <div className="text-4xl">📭</div>
            <div className="text-sm font-medium">Nenhuma sessão Claude Code ingestada ainda</div>
            <div className="text-xs max-w-md mx-auto">
              Schema <code className="font-mono text-[10px]">mcp_cc_*</code> está pronto, mas o
              watcher Node ainda não foi instalado pelos devs. Setup em
              {' '}
              <a href="https://github.com/wagnerra23/oimpresso.com/blob/main/memory/requisitos/Copiloto/SPEC-cc-sessions.md#us-copi-cc-040--watcher-node-ingere-sessions-jsonl-local"
                 target="_blank" rel="noopener noreferrer"
                 className="text-blue-600 hover:underline">SPEC US-COPI-CC-040</a>.
            </div>
            <div className="text-[10px] text-muted-foreground">
              Endpoint pronto: <code className="font-mono">POST /api/cc/ingest</code>
            </div>
          </div>
        ) : (
          <ScrollArea className="h-full">
            <table className="w-full text-xs">
              <thead className="sticky top-0 bg-background z-10">
                <tr className="border-b">
                  <th className="text-left py-2 px-2 font-medium">Dev / Início</th>
                  {!previewOpen && <th className="text-left py-2 px-2 font-medium">Summary</th>}
                  <th className="text-right py-2 px-2 font-medium w-16">Msgs</th>
                  <th className="text-right py-2 px-2 font-medium w-20">Custo</th>
                  {!previewOpen && <th className="text-left py-2 px-2 font-medium w-20">Status</th>}
                </tr>
              </thead>
              <tbody>
                {sessions.data.map((s) => {
                  const isSel = s.session_uuid === selectedUuid;
                  return (
                    <tr key={s.id}
                      className={`border-b cursor-pointer hover:bg-muted/40 ${isSel ? 'bg-blue-50 dark:bg-blue-950/30 border-l-2 border-l-blue-500' : ''}`}
                      onClick={() => openSession(s.session_uuid)}>
                      <td className="py-1.5 px-2 align-top">
                        <div className="font-medium text-xs leading-tight">{s.user_nome}</div>
                        <div className="text-[10px] text-muted-foreground" title={s.started_at ?? ''}>
                          {fmtRelative(s.started_at)} · {fmtDuration(s.started_at, s.ended_at)}
                        </div>
                        {!previewOpen && s.git_branch && (
                          <div className="text-[10px] text-muted-foreground font-mono mt-0.5">{s.git_branch}</div>
                        )}
                      </td>
                      {!previewOpen && (
                        <td className="py-1.5 px-2 align-top">
                          <div className="text-xs leading-tight line-clamp-2 max-w-md" title={s.summary_auto ?? ''}>
                            {s.summary_auto ?? <span className="text-muted-foreground">(sem summary)</span>}
                          </div>
                        </td>
                      )}
                      <td className="text-right py-1.5 px-2 align-top font-mono text-[10px]">{num(s.total_messages)}</td>
                      <td className="text-right py-1.5 px-2 align-top font-mono text-[10px]">{brl(s.total_cost_brl)}</td>
                      {!previewOpen && (
                        <td className="py-1.5 px-2 align-top">
                          {s.status === 'active' && <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200 text-[10px]">ativa</Badge>}
                          {s.status === 'closed' && <Badge variant="outline" className="text-[10px]">fechada</Badge>}
                          {s.status === 'archived' && <Badge variant="outline" className="bg-gray-50 text-gray-700 text-[10px]">arquivada</Badge>}
                        </td>
                      )}
                    </tr>
                  );
                })}
                {sessions.data.length === 0 && hasFilters && (
                  <tr><td colSpan={previewOpen ? 3 : 5} className="text-center py-8 text-muted-foreground">Nenhuma sessão bate com os filtros.</td></tr>
                )}
              </tbody>
            </table>
          </ScrollArea>
        )}
      </CardContent>
      {sessions.last_page > 1 && (
        <div className="border-t p-2 flex justify-center gap-1 flex-wrap">
          {sessions.links.map((l, i) => (
            <Button key={i} variant={l.active ? 'default' : 'outline'} size="sm" className="h-7 px-2 text-xs"
              disabled={!l.url}
              onClick={() => l.url && router.get(l.url, {}, { preserveScroll: true, preserveState: true, only: ['sessions'] })}
              dangerouslySetInnerHTML={{ __html: l.label }} />
          ))}
        </div>
      )}
    </Card>
  );

  // ─── Preview ──────────────────────────────────────────────────────────
  const PreviewPanel = (
    <Card className="flex flex-col h-full">
      <CardHeader className="py-3 border-b flex-row items-center justify-between space-y-0 gap-2">
        <CardTitle className="text-sm truncate flex-1 min-w-0">
          {detail ? `${detail.session.user_nome} · ${fmtRelative(detail.session.started_at)}`
            : selectedUuid ? `Carregando...`
            : 'Preview'}
        </CardTitle>
        <Button variant="ghost" size="sm" className="h-7 w-7 p-0" onClick={closePreview} title="Fechar (Esc)">✕</Button>
      </CardHeader>

      {!selectedUuid && (
        <div className="flex-1 flex items-center justify-center text-muted-foreground p-12 text-sm text-center">
          ← Selecione uma sessão.<br />
          <span className="text-[10px] mt-2 block">(j/k navega · Esc fecha · / busca)</span>
        </div>
      )}

      {selectedUuid && loadingDetail && (
        <div className="flex-1 flex items-center justify-center text-muted-foreground p-12 text-sm">Carregando...</div>
      )}

      {detail && !loadingDetail && (
        <>
          <div className="px-4 pt-3 pb-2 border-b text-xs space-y-1">
            <div className="flex flex-wrap gap-2 items-center">
              <span className="font-mono text-[10px] text-muted-foreground">{detail.session.session_uuid.slice(0, 8)}</span>
              {detail.session.cc_version && <Badge variant="outline" className="text-[10px]">cc v{detail.session.cc_version}</Badge>}
              {detail.session.entrypoint && <Badge variant="outline" className="text-[10px]">{detail.session.entrypoint}</Badge>}
              {detail.session.git_branch && <Badge variant="outline" className="text-[10px] font-mono">{detail.session.git_branch}</Badge>}
            </div>
            <div className="text-[11px] text-muted-foreground">
              📁 <span className="font-mono">{detail.session.project_path}</span>
            </div>
            <div className="text-[11px] text-muted-foreground">
              {fmtDateTime(detail.session.started_at)} → {detail.session.ended_at ? fmtDateTime(detail.session.ended_at) : 'em curso'}
              {' · '}
              {fmtDuration(detail.session.started_at, detail.session.ended_at)}
              {' · '}
              {num(detail.session.total_messages)} msgs
              {' · '}
              {num(detail.session.total_tokens)} tokens
              {' · '}
              <span className="font-mono">{brl(detail.session.total_cost_brl)}</span>
            </div>
            {detail.session.summary_auto && (
              <div className="mt-2 p-2 bg-muted/40 rounded text-[11px] leading-relaxed">
                <span className="font-medium text-muted-foreground">Summary:</span> {detail.session.summary_auto}
              </div>
            )}
          </div>
          <div className="flex-1 overflow-auto" ref={previewRef}>
            <div className="p-3 space-y-2">
              {detail.messages.map((m) => (
                <MessageBubble key={m.id} m={m} />
              ))}
              {detail.truncated && (
                <div className="text-center text-xs text-muted-foreground py-4">
                  ⚠️ Truncado em 500 mensagens. Total: {num(detail.session.total_messages)}.
                </div>
              )}
            </div>
          </div>
        </>
      )}
    </Card>
  );

  return (
    <>
      <PageHeader
        icon="code-2"
        title="CC do time"
        description={`Sessões Claude Code do time agregadas. ${permissions.read_all ? 'Visão admin' : 'Visão do dev'}.`}
      />

      <KpiGrid cols={4} className="mt-6">
        <KpiCard icon="activity" tone="info" label="Sessões hoje" value={num(kpis.sessions_hoje)} description={`${num(kpis.sessions_total)} no total`} />
        <KpiCard icon="users" tone="default" label="Devs ativos hoje" value={num(kpis.devs_ativos_hoje)} description={permissions.read_all ? 'time' : 'você'} />
        <KpiCard icon="dollar-sign" tone="warning" label="Custo hoje" value={brl(kpis.custo_hoje_brl)} description={`30d: ${brl(kpis.custo_30d_brl)}`} />
        <KpiCard icon="zap" tone="default" label="Top tools" value={kpis.tools_top.length.toString()}
          description={kpis.tools_top.slice(0, 3).map(t => `${t.tool}(${t.count})`).join(' · ') || '—'} />
      </KpiGrid>

      <Card className="mt-4">
        <CardContent className="py-3 flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[200px]">
            <Label className="text-xs">Busca <span className="text-[10px] text-muted-foreground">(/, debounce)</span></Label>
            <Input ref={searchRef} placeholder="summary ou keyword..." value={search}
              onChange={(e) => setSearch(e.target.value)} className="h-8" />
          </div>
          {permissions.read_all && devs.length > 1 && (
            <div className="w-40">
              <Label className="text-xs">Dev</Label>
              <Select value={filters.user_id?.toString() ?? '__all__'}
                onValueChange={(v) => applyFilter({ user_id: v === '__all__' ? null : Number(v) })}>
                <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">Todos</SelectItem>
                  {devs.map((d) => <SelectItem key={d.id} value={d.id.toString()}>{d.nome}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
          )}
          <div className="w-32">
            <Label className="text-xs">Status</Label>
            <Select value={filters.status ?? '__all__'}
              onValueChange={(v) => applyFilter({ status: v === '__all__' ? '' : v })}>
              <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="__all__">Todos</SelectItem>
                <SelectItem value="active">Ativa</SelectItem>
                <SelectItem value="closed">Fechada</SelectItem>
                <SelectItem value="archived">Arquivada</SelectItem>
              </SelectContent>
            </Select>
          </div>
          {projects.length > 1 && (
            <div className="w-48">
              <Label className="text-xs">Projeto</Label>
              <Select value={filters.project_path ?? '__all__'}
                onValueChange={(v) => applyFilter({ project_path: v === '__all__' ? '' : v })}>
                <SelectTrigger className="h-8"><SelectValue placeholder="Todos" /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="__all__">Todos</SelectItem>
                  {projects.map((p) => <SelectItem key={p} value={p}>{p}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
          )}
          {hasFilters && (
            <Button variant="outline" className="h-8 text-xs"
              onClick={() => { setSearch(''); applyFilter({ q: '', user_id: null, status: '', project_path: '' }); }}>
              Limpar
            </Button>
          )}
        </CardContent>
      </Card>

      <div className="mt-4" style={{ height: '78vh' }}>
        {!previewOpen ? (
          <div className="h-full">{ListPanel}</div>
        ) : (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-2 h-full">
            <div className="lg:col-span-5 h-full">{ListPanel}</div>
            <div className="lg:col-span-7 h-full">{PreviewPanel}</div>
          </div>
        )}
      </div>
    </>
  );
}

function MessageBubble({ m }: { m: Message }) {
  const [expanded, setExpanded] = useState(false);
  const text = m.content_text ?? '';
  const isLong = text.length > 300;
  const display = expanded || !isLong ? text : text.slice(0, 300);

  const typeStyle: Record<string, string> = {
    user:        'bg-blue-50 border-blue-200 dark:bg-blue-950/30',
    assistant:   'bg-gray-50 border-gray-200 dark:bg-gray-900/40',
    tool_use:    'bg-amber-50 border-amber-200 dark:bg-amber-950/20',
    tool_result: 'bg-green-50 border-green-200 dark:bg-green-950/20',
    system:      'bg-purple-50 border-purple-200 dark:bg-purple-950/20',
    hook:        'bg-pink-50 border-pink-200 dark:bg-pink-950/20',
    attachment:  'bg-cyan-50 border-cyan-200 dark:bg-cyan-950/20',
  };

  return (
    <div className={`border rounded-md p-2 ${typeStyle[m.msg_type] ?? 'bg-muted/30'}`}>
      <div className="flex items-center gap-2 text-[10px] text-muted-foreground mb-1">
        <span className="font-mono font-medium uppercase">{m.msg_type}</span>
        {m.tool_name && (
          <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium ${toolColor(m.tool_name)}`}>
            {m.tool_name}
          </span>
        )}
        {m.ts && <span>{new Date(m.ts).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</span>}
        {(m.tokens_in || m.tokens_out) && (
          <span className="ml-auto font-mono">
            ↓{m.tokens_in ?? 0} ↑{m.tokens_out ?? 0}
            {m.cache_read ? ` (${m.cache_read} cache)` : ''}
          </span>
        )}
      </div>
      {text && (
        <div className="text-xs whitespace-pre-wrap break-words font-mono leading-relaxed">
          {display}
          {isLong && !expanded && <span className="text-muted-foreground"> ...</span>}
        </div>
      )}
      {isLong && (
        <button onClick={() => setExpanded(!expanded)}
          className="text-[10px] text-blue-600 hover:underline mt-1">
          {expanded ? '▲ recolher' : `▼ ver mais (${text.length} chars)`}
        </button>
      )}
    </div>
  );
}

CcSessionsIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="CC do time — Sessões Claude Code" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Sessões CC' }]}>
    {page}
  </AppShellV2>
);

export default CcSessionsIndex;
