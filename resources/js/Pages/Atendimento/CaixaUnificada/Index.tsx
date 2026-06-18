// @memcofre
//   tela: /atendimento/caixa-unificada
//   stories: US-WA-XXX (Caixa Unificada V4 — Cowork redesign omnichannel)
//   adrs: 0114 (Cowork loop) · 0135 (omnichannel) · 0093 (multi-tenant Tier 0) · 0107 (visual gate F3)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   visual-comparison: memory/requisitos/Whatsapp/CaixaUnificadaV4-visual-comparison.md
//   prototipo: prototipo-ui/prototipos/caixa-unificada/inbox-page.jsx
//   status: implementada PR-D wave fix — coexiste com /atendimento/inbox durante canary 7d
//   permissao: whatsapp.access
//
// Caixa Unificada V4 — redesign Cowork da Inbox omnichannel.
//
// Diferenças vs /atendimento/inbox (legacy Cockpit V2):
//   - Filtro de canal/conta no popover "Filtros" da lista (faixa horizontal removida — Onda 1/2 2026-06-16)
//   - 4 status canônicos no dropdown da lista (Abertas/Pendentes/Aguardando/Resolvidas)
//   - Banner amarelo "em homologação" pra canais preview-only
//   - Sidebar direita 8 sections (Fila/Atribuído/Canal/Tags/OS/Saldo/Histórico/Último/Ações)
//   - Composer com toggle Resp/Nota inline (⌘⇧N)
//   - Topnav direita: Filas | Canais | Broadcast | + Nova conversa (placeholders TODO)
//
// Coexiste com /atendimento/inbox durante canary 7d. Cutover (substituir
// rota Inbox legacy) em PR seguinte após Wagner aprovar a tela.
//
// Reusa endpoints backend do legacy: POST /atendimento/inbox/{id}/send,
// PATCH /atendimento/inbox/{id}, etc — sem duplicar contrato.

import { useEffect, useState } from 'react';
import { router, Deferred, Head } from '@inertiajs/react';
import { Centrifuge } from 'centrifuge';
import { ChevronDown, Loader2, MessageSquareText, Sparkles } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { Card } from '@/Components/ui/card';
import EmptyState from '@/Components/shared/EmptyState';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';

import BroadcastSheet from './_components/BroadcastSheet';
import InboxCheatSheet from './_components/InboxCheatSheet';
import InboxGuiaDialog from './_components/InboxGuiaDialog';
import InboxMobileTabs, { type MobileView } from './_components/InboxMobileTabs';
import ChannelsDrawer from './_components/ChannelsDrawer';
import NewConversationDialog from './_components/NewConversationDialog';
import QueuesSheet from './_components/QueuesSheet';
import ConversationListV4 from './_components/ConversationListV4';
import ConversationThreadV4 from './_components/ConversationThreadV4';
import ContextSidebarV4 from './_components/ContextSidebarV4';
import type {
  AccountItem,
  AssigneeItem,
  CaixaUnifConversation,
  CaixaUnifMessage,
  CaixaUnifStats,
  CaixaUnifStatus,
  CaixaUnifTab,
  CaixaUnifThread,
  CentrifugoConfig,
  ChannelCatalogItem,
  Paginated,
  QueueConfig,
  CustomerContext,
  UnhealthyChannel,
} from './_components/helpers';

interface Props {
  /** D-14 perf: defer no backend — undefined até auto-fetch async resolver. */
  conversations?: Paginated<CaixaUnifConversation>;
  stats?: CaixaUnifStats;
  availableChannels?: ChannelCatalogItem[];
  availableAccounts?: AccountItem[];
  availableTags?: { id: number; slug: string; label: string; color: string }[];
  /** US-WA-302 — operadores atribuíveis (assignee picker da sidebar). */
  availableAssignees?: AssigneeItem[];
  /** US-WA-303 — templates ready do business (picker ⌘T do composer). */
  availableTemplates?: import('@/Pages/Whatsapp/_components/helpers').ReadyTemplate[];
  /** US-WA-301 (ADR 0267) — rows completas pro painel Filas (Sheet CRUD). */
  queuesAdmin?: import('./_components/helpers').QueueAdminItem[];
  canManageQueues?: boolean;

  businessId: number;
  /**
   * Wave 2 F1: `statusFilter` carrega valor de `tab` (CaixaUnifTab 7-valor) ou
   * CaixaUnifStatus legacy (4-valor). Backend mapeia ambos. Front trata como Tab.
   */
  statusFilter: CaixaUnifStatus | CaixaUnifTab;
  channelTypeFilter: string | null;
  // Wave 5 F1 — filtros power-user (sincronizam URL)
  within24h?: boolean | null;
  unlinked?: boolean;
  mediaInbound24h?: boolean;
  inboundAging?: '6h' | '12h' | '24h' | '48h' | '7d' | null;
  orderBy?: 'last_message' | 'inbound';
  activeTagIds?: number[];
  accountFilter: number | null;
  queueFilter: string | null;
  q: string;
  thread: CaixaUnifThread | null;
  messages: CaixaUnifMessage[] | null;
  customerContext: CustomerContext | null;
  centrifugoConfig: CentrifugoConfig | null;
  /** US-WA-308 — canais ativos com sessão caída (banner "religar"). Eager. */
  unhealthyChannels?: UnhealthyChannel[];
  queues: Record<string, QueueConfig>;
  defaultQueue: string;
}

export default function CaixaUnificadaIndex({
  conversations, stats, availableChannels, availableAccounts, availableTags, availableAssignees, availableTemplates,
  queuesAdmin, canManageQueues,
  businessId: _businessId,
  statusFilter, channelTypeFilter, accountFilter, queueFilter, q,
  thread, messages, customerContext, centrifugoConfig, unhealthyChannels,
  queues, defaultQueue: _defaultQueue,
  within24h, unlinked, mediaInbound24h, inboundAging, orderBy, activeTagIds,
}: Props) {
  // US-WA-301 — painel Filas (Sheet in-place)
  const [filasOpen, setFilasOpen] = useState(false);
  // US-WA-304 — drawer Canais e contas (Sheet in-place, charter §5)
  const [canaisOpen, setCanaisOpen] = useState(false);
  // US-WA-307 — dialog + Nova conversa
  const [novaConvOpen, setNovaConvOpen] = useState(false);
  // US-WA-306 — broadcast fase 1 (pre-flight + draft; disparo é fase 2 ADR 0268)
  const [broadcastOpen, setBroadcastOpen] = useState(false);
  // Polish V2 §3 — cheat-sheet "?" de atalhos
  const [cheatOpen, setCheatOpen] = useState(false);
  // Guia (port inbox-cur) — troubleshooters + trilhas de onboarding
  const [guiaOpen, setGuiaOpen] = useState(false);
  // Polish V2 §5 — mobile tabs (<lg mostra 1 coluna por vez; desktop intacto)
  const [mobileView, setMobileView] = useState<MobileView>('list');
  // Contexto recolhível (canon Cowork `.om-ctx`) — auto-recolhe < 1440px, lembra a escolha
  const [ctxOpen, setCtxOpen] = useState(() => {
    if (typeof window === 'undefined') return true;
    const saved = localStorage.getItem('oimpresso.caixa-unif.ctx-open');
    if (saved !== null) return saved === '1';
    return window.innerWidth >= 1440;
  });
  useEffect(() => {
    try { localStorage.setItem('oimpresso.caixa-unif.ctx-open', ctxOpen ? '1' : '0'); } catch { /* ignore */ }
  }, [ctxOpen]);

  // Centrifugo real-time (US-WA-068 anti-flash com preserveScroll + preserveState)
  useEffect(() => {
    if (!centrifugoConfig) return;

    const c = new Centrifuge(centrifugoConfig.wsUrl, { token: centrifugoConfig.token });
    const sub = c.newSubscription(centrifugoConfig.channel);

    sub.on('publication', (ctx: { data: Record<string, unknown> }) => {
      const eventType = ctx.data?.type as string | undefined;
      if (eventType !== 'message.received' && eventType !== 'message.sent') return;
      const incomingConvId = ctx.data?.conversation_id as number | undefined;

      if (thread && incomingConvId === thread.id) {
        router.reload({
          only: ['messages', 'thread', 'conversations', 'stats'],
          preserveScroll: true,
          preserveState: true,
        });
      } else {
        router.reload({
          only: ['conversations', 'stats'],
          preserveScroll: true,
          preserveState: true,
        });
      }
    });

    sub.subscribe();
    c.connect();

    return () => {
      try { sub.unsubscribe(); } catch { /* ignore */ }
      c.disconnect();
    };
  }, [centrifugoConfig?.token, centrifugoConfig?.channel, centrifugoConfig?.wsUrl, thread?.id]);

  // Polling fallback 5s — defense in depth (US-WA-066)
  // SEMPRE roda em paralelo ao WSS; cliente real cancelou contrato por msg perdida.
  useEffect(() => {
    const interval = setInterval(() => {
      if (document.visibilityState !== 'visible') return;
      const only = thread
        ? ['messages', 'thread', 'conversations', 'stats']
        : ['conversations', 'stats'];
      router.reload({ only, preserveScroll: true, preserveState: true });
    }, 5000);
    return () => clearInterval(interval);
  }, [thread?.id]);

  function selectThread(id: number) {
    // Polish V2 §5 — no mobile, abrir conversa salta pra tab Thread
    setMobileView('thread');
    // Mesma estratégia perf do Inbox legacy: `conversations` NÃO precisa rebuscar
    // ao trocar thread — só thread+messages no `only:[]`.
    router.get(
      route('atendimento.caixa-unificada.index'),
      {
        // Wave 2 F1 — usa `tab` (7-valor) em vez de `status` (4-valor)
        tab: statusFilter,
        channel: channelTypeFilter ?? undefined,
        account_id: accountFilter ?? undefined,
        q: q || undefined,
        thread: id,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages', 'customerContext'],
      },
    );
  }

  function resolveThread() {
    if (!thread) return;
    router.patch(
      route('atendimento.inbox.update_status', thread.id),
      { status: 'resolved' },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'conversations', 'stats'],
      },
    );
  }

  function setAwaitingHuman() {
    if (!thread) return;
    if (thread.status === 'awaiting_human' || thread.status === 'resolved') return;
    router.patch(
      route('atendimento.inbox.update_status', thread.id),
      { status: 'awaiting_human' },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'conversations', 'stats'],
      },
    );
  }

  // Wave 1 F1 paridade Inbox legacy — atalhos teclado J/K/E/A + "/" (ADR 0039 §2).
  // J/K navega lista · "/" foca busca · E resolve · A aguardando humano.
  // Filtra eventos em inputs/textareas/contentEditable e ignora com modifier keys.
  useEffect(() => {
    function handler(e: KeyboardEvent) {
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement ||
        (e.target instanceof HTMLElement && e.target.isContentEditable)
      ) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;

      if (e.key === '/') {
        e.preventDefault();
        const input = document.querySelector<HTMLInputElement>('[data-caixa-unif-search]');
        input?.focus();
        return;
      }

      const list = conversations?.data ?? [];

      if (e.key === 'j' || e.key === 'J') {
        if (list.length === 0) return;
        e.preventDefault();
        const idx = thread ? list.findIndex((c) => c.id === thread.id) : -1;
        const next = list[Math.min(idx + 1, list.length - 1)];
        if (next) selectThread(next.id);
        return;
      }
      if (e.key === 'k' || e.key === 'K') {
        if (list.length === 0) return;
        e.preventDefault();
        const idx = thread ? list.findIndex((c) => c.id === thread.id) : -1;
        const prev = list[Math.max(idx - 1, 0)];
        if (prev) selectThread(prev.id);
        return;
      }
      if (e.key === 'e' || e.key === 'E') {
        if (!thread || thread.status === 'resolved') return;
        e.preventDefault();
        resolveThread();
        return;
      }
      if (e.key === 'a' || e.key === 'A') {
        e.preventDefault();
        setAwaitingHuman();
        return;
      }
      // Polish V2 §3 — "?" abre/fecha o guia de atalhos
      if (e.key === '?') {
        e.preventDefault();
        setCheatOpen(v => !v);
        return;
      }
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [thread?.id, thread?.status, conversations?.data]);

  // Header sub: "3 contas ativas · 5 filas · 8 abertas · 1 não lidas"
  const headerSub = stats
    ? [
        `${stats.active_accounts} ${stats.active_accounts === 1 ? 'conta ativa' : 'contas ativas'}`,
        `${stats.queues_count} ${stats.queues_count === 1 ? 'fila' : 'filas'}`,
        `${stats.abertas} ${stats.abertas === 1 ? 'aberta' : 'abertas'}`,
        ...(stats.unread > 0 ? [`${stats.unread} ${stats.unread === 1 ? 'não lida' : 'não lidas'}`] : []),
      ].join(' · ')
    : 'Carregando…';

  return (
    <div className="flex flex-col flex-1 min-h-0 gap-1" data-testid="caixa-unif-page">
      <Head title="Caixa Unificada" />

      {/* Header da página */}
      <div className="flex items-center justify-between gap-3 shrink-0 px-1">
        {/* Sem ícone-caixa — canon Cowork `os-page-h-l` é só título + subtítulo. */}
        <div className="flex items-center gap-2 min-w-0 flex-wrap">
          <div className="min-w-0">
            <h1 className="font-semibold text-[14px] leading-tight truncate">Caixa unificada</h1>
            <p className="text-[12.5px] text-muted-foreground truncate">
              {headerSub}
            </p>
          </div>
        </div>

        {/* Topnav direita: 5 ações (Templates funcional, 3 placeholders TODO US-WA-XXX) */}
        <div className="flex items-center gap-1.5 flex-wrap">
          {/* Templates — dropdown que agrupa Jana + HSM (rotas existentes) */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
                title="Templates Jana + HSM Meta"
                data-testid="caixa-unif-topnav-templates"
              >
                Templates
                <ChevronDown size={12} aria-hidden />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-56">
              <DropdownMenuLabel className="text-[10.5px] uppercase tracking-wide text-muted-foreground">
                Bibliotecas de templates
              </DropdownMenuLabel>
              <DropdownMenuSeparator />
              <DropdownMenuItem asChild>
                <a
                  href="/atendimento/canais/jana-templates"
                  className="flex items-center gap-2 cursor-pointer"
                  data-testid="caixa-unif-topnav-templates-jana"
                >
                  <Sparkles size={14} className="text-primary" aria-hidden />
                  <div className="flex flex-col">
                    <span className="text-[12px] font-medium">Templates Jana</span>
                    <span className="text-[10.5px] text-muted-foreground">Prompts internos IA</span>
                  </div>
                </a>
              </DropdownMenuItem>
              <DropdownMenuItem asChild>
                <a
                  href="/whatsapp/templates"
                  className="flex items-center gap-2 cursor-pointer"
                  data-testid="caixa-unif-topnav-templates-hsm"
                >
                  <MessageSquareText size={14} className="text-emerald-600" aria-hidden />
                  <div className="flex flex-col">
                    <span className="text-[12px] font-medium">Templates HSM</span>
                    <span className="text-[10.5px] text-muted-foreground">Meta-aprovados (fora 24h)</span>
                  </div>
                </a>
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
          {/* US-WA-301 (ADR 0267) — painel Filas (Sheet CRUD, DB whatsapp_queues) */}
          <button
            type="button"
            onClick={() => setFilasOpen(true)}
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            title="Configurar filas de atendimento"
            data-testid="caixa-unif-topnav-filas"
          >
            Filas
          </button>
          {/* US-WA-304 — vira drawer in-place (Sheet); página completa fica no link
              "Gerenciar" dentro do drawer */}
          <button
            type="button"
            onClick={() => setCanaisOpen(true)}
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            title="Canais e contas (drawer)"
            data-testid="caixa-unif-topnav-canais"
          >
            Canais
          </button>
          {/* US-WA-306 — fase 1: pre-flight + rascunho (disparo = fase 2 ADR 0268) */}
          <button
            type="button"
            onClick={() => setBroadcastOpen(true)}
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            title="Broadcast cross-canal — audiência + rascunho (disparo na fase 2)"
            data-testid="caixa-unif-topnav-broadcast"
          >
            Broadcast
          </button>
          {/* Guia (port inbox-cur) — troubleshooters + trilhas de onboarding */}
          <button
            type="button"
            onClick={() => setGuiaOpen(true)}
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            title="Guia — diagnóstico guiado de atendimento + trilhas de onboarding"
            data-testid="caixa-unif-topnav-guia"
          >
            Guia
          </button>
          {/* US-WA-307 — abre dialog (find-or-create + thread aberta) */}
          <button
            type="button"
            onClick={() => setNovaConvOpen(true)}
            className="inline-flex items-center px-3 py-1.5 text-[11.5px] font-semibold bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors"
            title="Iniciar nova conversa"
            data-testid="caixa-unif-topnav-nova"
          >
            + Nova conversa
          </button>
        </div>
      </div>

      {/* Polish V2 §5 — tabs mobile (abaixo de lg; desktop 3-col intacto) */}
      <InboxMobileTabs
        view={mobileView}
        onChange={setMobileView}
        hasThread={thread !== null}
        unread={stats?.unread ?? 0}
      />

      {/* Shell 3-col */}
      <div className={`flex-1 grid grid-cols-1 gap-0 min-h-0 overflow-hidden border rounded-md ${
        thread ? (ctxOpen ? 'lg:grid-cols-[320px_1fr_300px]' : 'lg:grid-cols-[320px_1fr_44px]') : 'lg:grid-cols-[320px_1fr]'
      }`}>
        {/* Lista esquerda — no mobile só aparece na tab Conversas */}
        <div className={mobileView === 'list' ? 'min-h-0 h-full' : 'min-h-0 h-full hidden lg:block'}>
        <Deferred
          data={['conversations', 'stats']}
          fallback={(
            <Card className="h-full flex flex-col rounded-none border-r border-l-0 border-y-0">
              <div className="border-b p-3 flex items-center gap-2">
                <div className="h-6 w-32 bg-muted/40 rounded animate-pulse" />
              </div>
              <div className="flex-1 p-2 space-y-2 overflow-hidden">
                {Array.from({ length: 8 }).map((_, i) => (
                  <div key={i} className="flex gap-2 items-center p-2">
                    <div className="h-9 w-9 rounded-full bg-muted/40 animate-pulse shrink-0" />
                    <div className="flex-1 space-y-1.5">
                      <div className="h-3 w-3/4 bg-muted/40 rounded animate-pulse" />
                      <div className="h-2 w-1/2 bg-muted/30 rounded animate-pulse" />
                    </div>
                  </div>
                ))}
                <div className="flex items-center justify-center pt-4 text-muted-foreground text-xs">
                  <Loader2 size={14} className="animate-spin mr-2" aria-hidden />
                  Carregando conversas…
                </div>
              </div>
            </Card>
          )}
        >
          <ConversationListV4
            conversations={conversations as Paginated<CaixaUnifConversation>}
            channels={availableChannels ?? []}
            accounts={availableAccounts ?? []}
            channelTypeFilter={channelTypeFilter}
            accountFilter={accountFilter}
            queues={queues}
            queueFilter={queueFilter}
            stats={stats ?? null}
            selectedId={thread?.id ?? null}
            status={statusFilter}
            q={q}
            onSelect={selectThread}
            within24h={within24h}
            unlinked={unlinked}
            mediaInbound24h={mediaInbound24h}
            inboundAging={inboundAging}
            orderBy={orderBy}
            availableTags={availableTags ?? []}
            activeTagIds={activeTagIds ?? []}
            unhealthyChannels={unhealthyChannels ?? []}
          />
        </Deferred>
        </div>

        {/* Thread central — no mobile só aparece na tab Thread */}
        <div className={mobileView === 'thread' ? 'min-w-0 min-h-0' : 'min-w-0 min-h-0 hidden lg:block'}>
          {thread && messages !== null ? (
            <ConversationThreadV4
              thread={thread}
              messages={messages}
              channels={availableChannels ?? []}
              onResolve={resolveThread}
              templates={availableTemplates ?? []}
            />
          ) : (
            <div className="h-full flex items-center justify-center bg-muted/15">
              <EmptyState
                icon="message-circle"
                variant="default"
                title="Selecione uma conversa"
                description={
                  conversations
                    ? `${conversations.total} ${conversations.total === 1 ? 'conversa' : 'conversas'} na caixa.`
                    : 'Carregando caixa unificada…'
                }
              />
            </div>
          )}
        </div>

        {/* Sidebar direita — só quando thread aberta */}
        {/* US-WA-301 — painel Filas (Sheet CRUD whatsapp_queues) */}
        <QueuesSheet
          open={filasOpen}
          onOpenChange={setFilasOpen}
          queues={queuesAdmin ?? []}
          availableTags={availableTags ?? []}
          canManage={canManageQueues ?? false}
        />

        {/* US-WA-304 — drawer Canais e contas (reusa payloads já carregados) */}
        <ChannelsDrawer
          open={canaisOpen}
          onOpenChange={setCanaisOpen}
          channels={availableChannels ?? []}
          accounts={availableAccounts ?? []}
          canManageChannels={canManageQueues ?? false}
        />

        {/* US-WA-307 — + Nova conversa (find-or-create + abre thread) */}
        <NewConversationDialog
          open={novaConvOpen}
          onOpenChange={setNovaConvOpen}
          accounts={availableAccounts ?? []}
        />

        {/* US-WA-306 — broadcast fase 1 (ADR 0268) */}
        <BroadcastSheet
          open={broadcastOpen}
          onOpenChange={setBroadcastOpen}
          accounts={availableAccounts ?? []}
          templates={availableTemplates ?? []}
        />

        {thread && (
          <div className={mobileView === 'context' ? 'min-h-0 h-full' : 'min-h-0 h-full hidden lg:block'}>
          <Deferred data="availableChannels" fallback={null}>
            <ContextSidebarV4
              thread={thread}
              customerContext={customerContext}
              channels={availableChannels ?? []}
              queues={queues}
              availableTags={availableTags ?? []}
              availableAssignees={availableAssignees ?? []}
              open={ctxOpen}
              onToggle={() => setCtxOpen((v) => !v)}
            />
          </Deferred>
          </div>
        )}
      </div>

      {/* Polish V2 §3 — cheat-sheet de atalhos ("?") */}
      <InboxCheatSheet open={cheatOpen} onOpenChange={setCheatOpen} />
      {/* Guia (port inbox-cur) — troubleshooters + trilhas */}
      <InboxGuiaDialog open={guiaOpen} onOpenChange={setGuiaOpen} />
    </div>
  );
}

CaixaUnificadaIndex.layout = (page: React.ReactElement) => <AppShellV2 hideTopbar>{page}</AppShellV2>;
