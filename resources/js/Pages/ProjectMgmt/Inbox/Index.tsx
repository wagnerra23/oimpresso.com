// DRAFT Onda 2 — PRECISA smoke visual + aprovação SCREENSHOT do Wagner (ADR 0107/0114) antes de merge
//
// @memcofre
//   tela: /project-mgmt/inbox
//   module: ProjectMgmt
//   stories: US-TR-304 (lista unread) · US-TR-305 (marcar lido individual + todas) · US-TR-306 (deep-link task)
//   adrs: 0070 (Jira-style PM), UI-0013 (Constituição UI v2), 0039 (cockpit)
//   permissao: copiloto.mcp.usage.all
//   paridade: lista = tool MCP `my-inbox` (user_id=me, unread por default)
//
// Marca lido otimista → PATCH /inbox/{id}/read (rollback em erro). Deep-link
// abre /project-mgmt/board?task=ID (DetailSheet da task). Agrupado por tipo.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import {
  AlertCircle, AtSign, BellOff, CheckCheck, CheckCircle2, Clock,
  MessageSquare, RefreshCw, Send, UserPlus,
} from 'lucide-react';

type InboxType =
  | 'mention' | 'assigned' | 'review_requested' | 'status_changed'
  | 'commented' | 'due_soon' | 'blocked_resolved';

interface InboxItem {
  id: number;
  type: InboxType;
  task_id: string | null;
  actor_id: number | null;
  actor_name: string;
  body: string | null;
  created_at: string | null;
  read_at: string | null;
  is_read: boolean;
}

interface Props {
  // inbox/inbox_stats chegam via Inertia::defer (InboxController) → `undefined`
  // no 1º paint. Tipados opcionais + default-guard no destructuring pra NÃO
  // crashar React antes do defer chegar (skill inertia-defer-default, Opção B;
  // espelha OficinaAuto/ServiceOrders/Index.tsx). Sintoma do bug: inbox.forEach()
  // sobre undefined → tela branca (PR #1940).
  inbox?: InboxItem[];
  inbox_stats?: { unread: number; total_30d: number };
  filters: { show_read: boolean };
}

// Default-guard pros props deferred (contadores começam zerados até o defer resolver).
const EMPTY_INBOX_STATS = { unread: 0, total_30d: 0 };

const TYPE_ICON: Record<InboxType, typeof AtSign> = {
  mention:          AtSign,
  assigned:         UserPlus,
  review_requested: Send,
  status_changed:   RefreshCw,
  commented:        MessageSquare,
  due_soon:         Clock,
  blocked_resolved: CheckCircle2,
};

const TYPE_LABEL: Record<InboxType, string> = {
  mention:          'mencionou você',
  assigned:         'atribuiu pra você',
  review_requested: 'pediu revisão',
  status_changed:   'mudou status',
  commented:        'comentou',
  due_soon:         'prazo apertando',
  blocked_resolved: 'desbloqueou',
};

// Ordem dos grupos (mentions/assignments primeiro — mais acionáveis).
const GROUP_ORDER: InboxType[] = [
  'mention', 'assigned', 'review_requested', 'status_changed',
  'commented', 'due_soon', 'blocked_resolved',
];

const GROUP_TITLE: Record<InboxType, string> = {
  mention:          'Menções',
  assigned:         'Atribuições',
  review_requested: 'Revisões pedidas',
  status_changed:   'Mudanças de status',
  commented:        'Comentários',
  due_soon:         'Prazos',
  blocked_resolved: 'Desbloqueios',
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

function InboxIndex({ inbox = [], inbox_stats = EMPTY_INBOX_STATS, filters }: Props) {
  const [optimisticRead, setOptimisticRead] = useState<Set<number>>(new Set());
  const [errorMsg, setErrorMsg] = useState<string | null>(null);
  // Item em foco pra navegação J/K (mesma mecânica do MyWork/Board).
  const [selectedId, setSelectedId] = useState<number | null>(null);

  useEffect(() => {
    if (!errorMsg) return;
    const tid = setTimeout(() => setErrorMsg(null), 5000);
    return () => clearTimeout(tid);
  }, [errorMsg]);

  // Agrupa por tipo, preservando GROUP_ORDER.
  const grouped = useMemo(() => {
    const map = new Map<InboxType, InboxItem[]>();
    inbox.forEach((item) => {
      const arr = map.get(item.type) ?? [];
      arr.push(item);
      map.set(item.type, arr);
    });
    return GROUP_ORDER
      .filter((t) => map.has(t))
      .map((t) => ({ type: t, items: map.get(t)! }));
  }, [inbox]);

  function markRead(id: number) {
    setOptimisticRead((prev) => new Set(prev).add(id));
    fetch(`/project-mgmt/inbox/${id}/read`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    })
      .then((r) => {
        if (!r.ok) {
          setOptimisticRead((prev) => { const n = new Set(prev); n.delete(id); return n; });
          setErrorMsg('Erro ao marcar lido. Tenta de novo.');
        } else {
          router.reload({ only: ['inbox', 'inbox_stats'], preserveScroll: true });
        }
      })
      .catch(() => {
        setOptimisticRead((prev) => { const n = new Set(prev); n.delete(id); return n; });
        setErrorMsg('Erro de rede. Tenta de novo.');
      });
  }

  function markAllRead() {
    fetch('/project-mgmt/inbox/read-all', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken() },
    })
      .then((r) => {
        if (!r.ok) { setErrorMsg('Erro ao marcar todas. Tenta de novo.'); return; }
        router.reload({ only: ['inbox', 'inbox_stats'], preserveScroll: true });
      })
      .catch(() => setErrorMsg('Erro de rede. Tenta de novo.'));
  }

  function openTask(item: InboxItem) {
    const wasRead = item.is_read || optimisticRead.has(item.id);
    if (!wasRead) markRead(item.id);
    if (item.task_id) {
      // Deep-link: Board abre DetailSheet via ?task=ID (US-TR-306).
      router.visit(`/project-mgmt/board?task=${item.task_id}`);
    }
  }

  // Quando a lista muda e o selecionado some, escolhe o primeiro (igual Board).
  useEffect(() => {
    if (!inbox.length) {
      setSelectedId(null);
      return;
    }
    if (selectedId && !inbox.find((i) => i.id === selectedId)) {
      setSelectedId(inbox[0]?.id ?? null);
    }
  }, [inbox, selectedId]);

  // Atalhos canônicos J/K (navegar) + Enter (abrir task no Board) + R (marcar
  // lida) — mesma mecânica inline que MyWork/Index.tsx (foco inbox) e Board.
  // ⌘K (palette global) é dono do AppShellV2 (PMG-002), não re-registramos aqui.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const tgt = e.target as HTMLElement | null;
      const isTyping = tgt && (
        tgt.tagName === 'INPUT' || tgt.tagName === 'TEXTAREA' || tgt.isContentEditable
      );
      if (isTyping) return;
      if (!inbox.length) return;

      const idx = selectedId ? inbox.findIndex((i) => i.id === selectedId) : -1;
      const cur = idx >= 0 ? inbox[idx] : null;

      if (e.key === 'j' || e.key === 'J') {
        e.preventDefault();
        const n = idx < 0 ? 0 : Math.min(inbox.length - 1, idx + 1);
        setSelectedId(inbox[n]?.id ?? null);
      } else if (e.key === 'k' || e.key === 'K') {
        e.preventDefault();
        const p = idx <= 0 ? 0 : idx - 1;
        setSelectedId(inbox[p]?.id ?? null);
      } else if (e.key === 'Enter' && cur) {
        e.preventDefault();
        openTask(cur);
      } else if (e.key === 'r' && !e.shiftKey && cur && !(cur.is_read || optimisticRead.has(cur.id))) {
        e.preventDefault();
        markRead(cur.id);
      } else if (e.key === 'R' && e.shiftKey) {
        e.preventDefault();
        markAllRead();
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [inbox, selectedId, optimisticRead]);

  // Polling leve — badge/contador re-sincroniza (igual MyWork).
  useEffect(() => {
    const reload = () => router.reload({ only: ['inbox', 'inbox_stats'], preserveScroll: true });
    const id = setInterval(reload, 30_000);
    window.addEventListener('focus', reload);
    return () => { clearInterval(id); window.removeEventListener('focus', reload); };
  }, []);

  return (
    <>
      <PageHeader
        icon="Inbox"
        title="Caixa de entrada"
        moduleNav
        description={`${inbox_stats.unread} não-lidas · ${inbox_stats.total_30d} nos últimos 30 dias`}
        action={
          <div className="flex items-center gap-2">
            <span className="hidden md:inline text-[11px] text-muted-foreground mr-1">
              <kbd className="px-1 py-0.5 rounded bg-muted">J</kbd>{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">K</kbd> navegar ·{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">Enter</kbd> abrir ·{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">R</kbd> lida ·{' '}
              <kbd className="px-1 py-0.5 rounded bg-muted">⌘K</kbd> buscar
            </span>
            <Button
              variant="ghost"
              size="sm"
              className="h-8 text-xs"
              onClick={() => router.get('/project-mgmt/inbox', { show_read: filters.show_read ? '' : '1' }, { preserveScroll: true })}
            >
              {filters.show_read ? 'só não-lidas' : 'mostrar lidas'}
            </Button>
            {inbox_stats.unread > 0 && (
              <Button variant="outline" size="sm" className="h-8 text-xs gap-1" onClick={markAllRead}>
                <CheckCheck size={14} /> marcar todas
              </Button>
            )}
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

      <KpiGrid cols={2} className="mt-4">
        <KpiCard icon="Bell" tone={inbox_stats.unread > 0 ? 'info' : 'success'} label="Não-lidas" value={String(inbox_stats.unread)} />
        <KpiCard icon="Clock" tone="default" label="Últimos 30 dias" value={String(inbox_stats.total_30d)} />
      </KpiGrid>

      {inbox.length === 0 ? (
        <div className="mt-8 text-center py-16 border-2 border-dashed rounded-lg text-muted-foreground">
          <BellOff size={28} className="mx-auto mb-3 opacity-40" />
          <p className="text-base font-medium">
            {filters.show_read ? 'Nada na caixa.' : 'Caixa de entrada vazia'}
          </p>
          <p className="text-sm mt-1">Notificações aparecem aqui quando te mencionam, atribuem ou pedem revisão.</p>
        </div>
      ) : (
        <div className="mt-4 flex flex-col gap-5">
          {grouped.map(({ type, items }) => {
            const Icon = TYPE_ICON[type] ?? AlertCircle;
            const unreadInGroup = items.filter((i) => !(i.is_read || optimisticRead.has(i.id))).length;
            return (
              <section key={type}>
                <header className="flex items-center gap-2 mb-2 px-1">
                  <Icon size={14} className="text-muted-foreground" />
                  <h2 className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                    {GROUP_TITLE[type] ?? type}
                  </h2>
                  <Badge variant={unreadInGroup > 0 ? 'default' : 'outline'} className="font-mono text-[10px]">
                    {unreadInGroup > 0 ? `${unreadInGroup} novas` : String(items.length)}
                  </Badge>
                </header>

                <div className="flex flex-col gap-1.5">
                  {items.map((item) => {
                    const wasRead = item.is_read || optimisticRead.has(item.id);
                    const isSelected = item.id === selectedId;
                    return (
                      <div
                        key={item.id}
                        aria-current={isSelected ? 'true' : undefined}
                        className={[
                          'group flex items-start gap-2 p-3 rounded-lg border bg-card transition-colors',
                          isSelected ? 'ring-1 ring-inset ring-primary/60' : '',
                          wasRead ? 'opacity-60' : 'hover:bg-muted/40',
                        ].filter(Boolean).join(' ')}
                      >
                        <button
                          type="button"
                          onClick={() => { setSelectedId(item.id); openTask(item); }}
                          className="flex-1 min-w-0 text-left"
                          title={item.task_id ? 'Abrir task no Board' : undefined}
                        >
                          <p className="text-sm leading-tight">
                            <span className="font-semibold">{item.actor_name}</span>{' '}
                            <span className="text-muted-foreground">{TYPE_LABEL[item.type] ?? item.type}</span>
                            {item.task_id && (
                              <span className="font-mono text-[10px] ml-1 px-1 py-0.5 rounded bg-muted">
                                {item.task_id}
                              </span>
                            )}
                          </p>
                          {item.body && (
                            <p className="text-xs text-muted-foreground line-clamp-2 mt-0.5">{item.body}</p>
                          )}
                          <span className="text-[10px] text-muted-foreground/70">{timeAgo(item.created_at)}</span>
                        </button>

                        {!wasRead ? (
                          <button
                            type="button"
                            onClick={(e) => { e.stopPropagation(); markRead(item.id); }}
                            className="shrink-0 inline-flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground px-1.5 py-1 rounded hover:bg-muted"
                            title="Marcar como lida"
                          >
                            <span className="w-1.5 h-1.5 rounded-full bg-primary" aria-hidden="true" />
                            marcar lida
                          </button>
                        ) : (
                          <CheckCheck size={14} className="shrink-0 mt-0.5 text-muted-foreground/50" aria-label="lida" />
                        )}
                      </div>
                    );
                  })}
                </div>
              </section>
            );
          })}
        </div>
      )}

      <p className="mt-5 text-xs text-muted-foreground">
        Mesma caixa da tool MCP <code className="font-mono">my-inbox</code>. Clicar abre a task no Board (DetailSheet).
      </p>
    </>
  );
}

InboxIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Project Mgmt — Caixa de entrada"
    breadcrumbItems={[{ label: 'Project Mgmt' }, { label: 'Inbox' }]}
  >
    {page}
  </AppShellV2>
);

export default InboxIndex;
