// Forja PR-2 — drawer de thread da tela /team-mcp/cc-sessions.
//   Endpoint: GET /team-mcp/cc-sessions/{uuid} (CcSessionsController@show, read-only).
//   Thread ≤500 msgs + flag truncated. Largura 640px (conversa precisa largura).
//   DS v6: bolha por tipo via tokens semânticos; tool chip neutro. data-testid locators.

import { useEffect, useRef, useState } from 'react';
import { Bot, FolderOpen, GitBranch, Loader2, User } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { cn } from '@/Lib/utils';
import { brl, fmtDateTime, fmtDuration, msgBubbleClass, num } from './sessionTokens';

interface SessionMeta {
  session_uuid: string;
  user_id: number;
  user_nome: string;
  project_path: string;
  git_branch: string | null;
  cc_version: string | null;
  entrypoint: string | null;
  started_at: string | null;
  ended_at: string | null;
  total_messages: number;
  total_tokens: number;
  total_cost_brl: number;
  status: string;
  summary_auto: string | null;
  metadata: Record<string, unknown> | null;
}

interface Message {
  id: number;
  msg_uuid: string;
  parent_uuid: string | null;
  msg_type: string;
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
  session: SessionMeta;
  messages: Message[];
  truncated: boolean;
}

interface Props {
  sessionUuid: string | null;
  onClose: () => void;
}

function MessageBubble({ m }: { m: Message }) {
  const [expanded, setExpanded] = useState(false);
  const text = m.content_text ?? '';
  const isLong = text.length > 300;
  const display = expanded || !isLong ? text : text.slice(0, 300);

  return (
    <div className={cn('rounded-md border p-2', msgBubbleClass(m.msg_type))} data-testid="cc-message">
      <div className="mb-1 inline-flex w-full items-center gap-2 text-[10px] text-muted-foreground">
        <span className="font-mono font-medium uppercase">{m.msg_type}</span>
        {m.tool_name && (
          <span className="inline-flex items-center rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] text-foreground">
            {m.tool_name}
          </span>
        )}
        {m.ts && <span>{new Date(m.ts).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}</span>}
        {(m.tokens_in || m.tokens_out) && (
          <span className="ml-auto font-mono tabular-nums">
            ↓{m.tokens_in ?? 0} ↑{m.tokens_out ?? 0}{m.cache_read ? ` (${m.cache_read} cache)` : ''}
          </span>
        )}
      </div>
      {text && (
        <div className="whitespace-pre-wrap break-words font-mono text-xs leading-relaxed">
          {display}{isLong && !expanded && <span className="text-muted-foreground"> …</span>}
        </div>
      )}
      {isLong && (
        <button type="button" onClick={() => setExpanded(!expanded)} className="mt-1 text-[10px] text-primary hover:underline">
          {expanded ? '▲ recolher' : `▼ ver mais (${text.length} chars)`}
        </button>
      )}
    </div>
  );
}

export default function SessionDrawer({ sessionUuid, onClose }: Props) {
  const [detail, setDetail] = useState<SessionDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const bodyRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    if (!sessionUuid) return;
    setLoading(true);
    setError(null);
    setDetail(null);

    const ctrl = new AbortController();
    fetch(`/team-mcp/cc-sessions/${encodeURIComponent(sessionUuid)}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      signal: ctrl.signal,
    })
      .then((r) => {
        if (r.status === 403) throw new Error('Sem permissão para ver esta sessão.');
        if (r.status === 404) throw new Error('Sessão não encontrada.');
        if (!r.ok) throw new Error(`Erro ${r.status}`);
        return r.json();
      })
      .then((d: SessionDetail) => { setDetail(d); setLoading(false); })
      .catch((err: Error) => { if (err.name === 'AbortError') return; setError(err.message); setLoading(false); });

    return () => ctrl.abort();
  }, [sessionUuid]);

  useEffect(() => { if (bodyRef.current) bodyRef.current.scrollTop = 0; }, [detail?.session?.session_uuid]);

  const open = !!sessionUuid;
  const s = detail?.session;

  return (
    <Sheet open={open} onOpenChange={(v) => { if (!v) onClose(); }}>
      <SheetContent side="right" className="inline-flex w-full flex-col overflow-hidden p-0 sm:max-w-[640px]" data-testid="session-drawer">
        <SheetHeader className="border-b px-4">
          <div className="inline-flex w-full items-center gap-2">
            <User size={14} className="text-muted-foreground" aria-hidden />
            <SheetTitle className="text-base" data-testid="drawer-dev">
              {s?.user_nome ?? (loading ? 'Carregando…' : 'Sessão')}
            </SheetTitle>
            {s?.cc_version && (
              <span className="inline-flex items-center gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary" title="Sessão de agente (Claude Code)">
                <Bot size={11} /> Claude Code v{s.cc_version}
              </span>
            )}
          </div>
          {s && (
            <SheetDescription className="space-y-1 text-xs">
              <span className="inline-flex w-full flex-wrap items-center gap-2">
                <span className="font-mono text-[10px] text-muted-foreground">{s.session_uuid.slice(0, 8)}</span>
                {s.entrypoint && <span className="rounded bg-muted px-1.5 py-0.5 text-[10px]">{s.entrypoint}</span>}
                {s.git_branch && (
                  <span className="inline-flex items-center gap-1 font-mono text-[10px] text-muted-foreground">
                    <GitBranch size={10} /> {s.git_branch}
                  </span>
                )}
              </span>
              <span className="inline-flex w-full items-center gap-1 text-muted-foreground">
                <FolderOpen size={11} className="shrink-0" /> <span className="truncate font-mono">{s.project_path}</span>
              </span>
              <span className="block text-[11px] text-muted-foreground tabular-nums">
                {fmtDateTime(s.started_at)} → {s.ended_at ? fmtDateTime(s.ended_at) : 'em curso'}
                {' · '}{fmtDuration(s.started_at, s.ended_at)}
                {' · '}{num(s.total_messages)} msgs · {num(s.total_tokens)} tokens · <span className="font-mono">{brl(s.total_cost_brl)}</span>
              </span>
            </SheetDescription>
          )}
        </SheetHeader>

        {s?.summary_auto && (
          <div className="border-b bg-muted/40 px-4 py-2 text-[11px] leading-relaxed">
            <span className="font-medium text-muted-foreground">Resumo:</span> {s.summary_auto}
          </div>
        )}

        <div className="flex-1 overflow-auto px-3 py-3" ref={bodyRef}>
          {loading && (
            <div className="inline-flex w-full items-center justify-center py-12 text-sm text-muted-foreground">
              <Loader2 className="mr-2 h-4 w-4 animate-spin" /> carregando thread…
            </div>
          )}
          {error && (
            <div className="my-6 rounded-md border border-destructive/20 bg-destructive-soft px-3 py-2 text-sm text-destructive-fg" data-testid="drawer-error">
              {error}
            </div>
          )}
          {!loading && !error && detail && (
            <div className="space-y-2">
              {detail.messages.map((m) => <MessageBubble key={m.id} m={m} />)}
              {detail.messages.length === 0 && (
                <p className="py-8 text-center text-sm italic text-muted-foreground">Sessão sem mensagens ingestadas.</p>
              )}
              {detail.truncated && (
                <div className="py-4 text-center text-xs text-muted-foreground">
                  ⚠️ Truncado em 500 mensagens. Total: {num(detail.session.total_messages)}.
                </div>
              )}
            </div>
          )}
        </div>
      </SheetContent>
    </Sheet>
  );
}
