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
//   - Chips horizontais de canais ACIMA da shell 3-col (vs dropdown topbar)
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

import { useEffect } from 'react';
import { router, Deferred, Head } from '@inertiajs/react';
import { Centrifuge } from 'centrifuge';
import { Inbox as InboxIcon, Loader2 } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { Card } from '@/Components/ui/card';
import EmptyState from '@/Components/shared/EmptyState';

import ChannelChipsRow from './_components/ChannelChipsRow';
import ConversationListV4 from './_components/ConversationListV4';
import ConversationThreadV4 from './_components/ConversationThreadV4';
import ContextSidebarV4 from './_components/ContextSidebarV4';
import type {
  AccountItem,
  CaixaUnifConversation,
  CaixaUnifMessage,
  CaixaUnifStats,
  CaixaUnifStatus,
  CaixaUnifThread,
  CentrifugoConfig,
  ChannelCatalogItem,
  Paginated,
  QueueConfig,
} from './_components/helpers';

interface Props {
  /** D-14 perf: defer no backend — undefined até auto-fetch async resolver. */
  conversations?: Paginated<CaixaUnifConversation>;
  stats?: CaixaUnifStats;
  availableChannels?: ChannelCatalogItem[];
  availableAccounts?: AccountItem[];
  availableTags?: { id: number; slug: string; label: string; color: string }[];

  businessId: number;
  statusFilter: CaixaUnifStatus;
  channelTypeFilter: string | null;
  accountFilter: number | null;
  queueFilter: string | null;
  q: string;
  thread: CaixaUnifThread | null;
  messages: CaixaUnifMessage[] | null;
  centrifugoConfig: CentrifugoConfig | null;
  queues: Record<string, QueueConfig>;
  defaultQueue: string;
}

export default function CaixaUnificadaIndex({
  conversations, stats, availableChannels, availableAccounts, availableTags,
  businessId: _businessId,
  statusFilter, channelTypeFilter, accountFilter, queueFilter: _queueFilter, q,
  thread, messages, centrifugoConfig,
  queues, defaultQueue: _defaultQueue,
}: Props) {
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
    // Mesma estratégia perf do Inbox legacy: `conversations` NÃO precisa rebuscar
    // ao trocar thread — só thread+messages no `only:[]`.
    router.get(
      route('atendimento.caixa-unificada.index'),
      {
        status: statusFilter,
        channel: channelTypeFilter ?? undefined,
        account_id: accountFilter ?? undefined,
        q: q || undefined,
        thread: id,
      },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages'],
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
      <div className="flex flex-col gap-2 shrink-0 px-1">
        {/* Linha 1: título */}
        <div className="flex items-center gap-2 min-w-0 flex-wrap">
          <div className="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <InboxIcon size={16} aria-hidden />
          </div>
          <div className="min-w-0">
            <h1 className="font-semibold text-sm leading-tight truncate">Caixa unificada</h1>
            <p className="text-[11px] text-muted-foreground truncate">
              {headerSub}
            </p>
          </div>
        </div>

        {/* Linha 2: topnav 4 ações (placeholders TODO US-WA-XXX) */}
        <div className="flex items-center gap-1.5 flex-wrap">
          <button
            type="button"
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors disabled:opacity-45"
            disabled
            title="Configurar filas (em breve)"
            data-testid="caixa-unif-topnav-filas"
          >
            Filas
          </button>
          <a
            href={route('atendimento.channels.index')}
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            title="Gerenciar canais"
            data-testid="caixa-unif-topnav-canais"
          >
            Canais
          </a>
          <button
            type="button"
            className="inline-flex items-center px-2.5 py-1.5 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors disabled:opacity-45"
            disabled
            title="Broadcast cross-canal (em breve)"
            data-testid="caixa-unif-topnav-broadcast"
          >
            Broadcast
          </button>
          <button
            type="button"
            className="inline-flex items-center px-3 py-1.5 text-[11.5px] font-semibold bg-primary text-primary-foreground rounded hover:bg-primary/90 transition-colors disabled:opacity-45"
            disabled
            title="Iniciar nova conversa (em breve)"
            data-testid="caixa-unif-topnav-nova"
          >
            + Nova conversa
          </button>
        </div>
      </div>

      {/* Chips horizontais de canais (filtro top) */}
      <Deferred
        data={['availableChannels', 'availableAccounts', 'conversations']}
        fallback={(
          <div className="border-b px-4 py-2 bg-muted/30 h-[42px] flex items-center">
            <span className="text-[11px] text-muted-foreground inline-flex items-center gap-1.5">
              <Loader2 size={12} className="animate-spin" aria-hidden /> Carregando canais…
            </span>
          </div>
        )}
      >
        <ChannelChipsRow
          channels={availableChannels ?? []}
          accounts={availableAccounts ?? []}
          channelTypeFilter={channelTypeFilter}
          accountFilter={accountFilter}
          totalAll={conversations?.total ?? 0}
        />
      </Deferred>

      {/* Shell 3-col */}
      <div className="flex-1 grid grid-cols-1 lg:grid-cols-[320px_1fr_300px] gap-0 min-h-0 overflow-hidden border rounded-md">
        {/* Lista esquerda */}
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
            stats={stats ?? null}
            selectedId={thread?.id ?? null}
            status={statusFilter}
            q={q}
            onSelect={selectThread}
          />
        </Deferred>

        {/* Thread central */}
        <div className="min-w-0 min-h-0">
          {thread && messages !== null ? (
            <ConversationThreadV4
              thread={thread}
              messages={messages}
              channels={availableChannels ?? []}
              onResolve={resolveThread}
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
        {thread && (
          <Deferred data="availableChannels" fallback={null}>
            <ContextSidebarV4
              thread={thread}
              channels={availableChannels ?? []}
              queues={queues}
            />
          </Deferred>
        )}
      </div>
    </div>
  );
}

CaixaUnificadaIndex.layout = (page: React.ReactElement) => <AppShellV2>{page}</AppShellV2>;
