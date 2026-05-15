// @memcofre
//   tela: /atendimento/inbox
//   stories: US-WA-063 + US-WA-067 (Inbox omnichannel — UI Cockpit unificada)
//   adrs: 0135 (omnichannel) + 0110 (Cockpit V2) + 0039 (Chat Cockpit pattern)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Fase 0 — reusa componentes ConversationList/Thread/Sidebar do legacy
//   permissao: whatsapp.access
//
// Substitui /whatsapp/conversations long-term — coexiste durante PR B+C.
// Driver legacy (Z-API, Meta Cloud via WhatsappBusinessConfig) ainda usa
// /whatsapp/conversations. Channel novo (Baileys, futuros Insta/Email/ML)
// aparece aqui.
//
// UX = mesma do /whatsapp/conversations (3-painéis Cockpit + sidebar colapsável
// + composer + atalhos J/K/E/A). Backend lê schema novo (channels/conversations/
// messages) via InboxController. Frontend reusa _components/ do Whatsapp legacy
// porque shape do payload é compatível (customer_phone alias customer_external_id).

import { useEffect, useState } from 'react';
import { router, Deferred } from '@inertiajs/react';
import { Centrifuge } from 'centrifuge';
import { ChevronLeft, ChevronRight, Inbox as InboxIcon, Loader2 } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import EmptyState from '@/Components/shared/EmptyState';
import { Card } from '@/Components/ui/card';

import ConversationList from '@/Pages/Whatsapp/_components/ConversationList';
import ConversationThread from '@/Pages/Whatsapp/_components/ConversationThread';
import ConversationSidebar from '@/Pages/Whatsapp/_components/ConversationSidebar';
import { LS, lsGet, lsSet } from '@/Pages/Whatsapp/_components/helpers';
import type {
  CentrifugoConfig,
  ConvTag,
  ListConversation,
  Message,
  ReadyTemplate,
  ThreadConversation,
} from '@/Pages/Whatsapp/_components/helpers';
// CYCLE-08 PR-A (US-WA-040): dropdown topbar pra alternar canal ativo
import ChannelSelector, { type AvailableChannel } from '@/Pages/Atendimento/Inbox/_components/ChannelSelector';

interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

interface Props {
  /** D-14 perf (post-#871): defer no backend — undefined até auto-fetch async resolver. */
  conversations?: Paginated<ListConversation>;
  tab: 'all' | 'unread' | 'assigned' | 'bot' | 'resolved' | 'awaiting_human' | 'archived';
  q: string;
  channelFilter: string | null;
  /** D-14 perf: defer no backend — undefined até auto-fetch async resolver. */
  stats?: { unread: number; assigned: number; bot: number; awaiting_human: number; archived: number };
  businessId: number;
  thread: ThreadConversation | null;
  messages: Message[] | null;
  /**
   * CYCLE-08 PR-A (US-WA-040): canais visíveis ao user (filtrados por ACL
   * `channel_user_access`). Inclui phone/health/unread per-canal pra
   * `ChannelSelector` renderizar dropdown rico no header.
   *
   * D-14 perf: defer no backend — undefined até auto-fetch async resolver.
   */
  availableChannels?: AvailableChannel[];
  /** CYCLE-08 PR-A: canal selecionado no dropdown topbar (null = "Todos") */
  selectedChannelId: number | null;
  /**
   * US-WA-063: catálogo de tags do business (seeds default na 1ª visita).
   * D-14 perf: defer no backend — undefined até auto-fetch async resolver.
   */
  availableTags?: ConvTag[];
  /** US-WA-063: IDs das tags ativas no filtro atual (query param `tags=`) */
  activeTagIds: number[];
  centrifugoConfig: CentrifugoConfig | null;
  /** true=dentro 24h Meta, false=fora, null=sem filtro. Tri-estado. */
  within24h: boolean | null;
  /** Filtra só convs sem Contact CRM vinculado */
  unlinked: boolean;
  /** US-WA-043 (PR-8 CYCLE-07): convs com mídia inbound nas últimas 24h */
  mediaInbound24h: boolean;
  /** Aging do último inbound do cliente: 6h/12h/24h/48h/7d ou null */
  inboundAging: '6h' | '12h' | '24h' | '48h' | '7d' | null;
  /** Ordenação default `last_message` | inbound (last_inbound_at desc) */
  orderBy: 'last_message' | 'inbound';
}

export default function InboxIndex({
  conversations, tab, q, stats,
  thread, messages, centrifugoConfig,
  availableChannels, selectedChannelId,
  availableTags, activeTagIds,
  within24h, unlinked, mediaInbound24h, inboundAging, orderBy,
}: Props) {
  // Sidebar direita colapsável — preferência LS persistida
  const [sidebarCollapsed, setSidebarCollapsed] = useState(
    () => lsGet(LS.SIDEBAR_COLLAPSED) === '1',
  );
  useEffect(() => {
    lsSet(LS.SIDEBAR_COLLAPSED, sidebarCollapsed ? '1' : null);
  }, [sidebarCollapsed]);

  // Sidebar esquerda (lista) colapsável
  const [leftSidebarCollapsed, setLeftSidebarCollapsed] = useState(
    () => lsGet(LS.LEFT_SIDEBAR_COLLAPSED) === '1',
  );
  useEffect(() => {
    lsSet(LS.LEFT_SIDEBAR_COLLAPSED, leftSidebarCollapsed ? '1' : null);
  }, [leftSidebarCollapsed]);

  // Centrifugo real-time — channel `omnichannel:business:{id}` (US-WA-059)
  useEffect(() => {
    if (!centrifugoConfig) return;

    const c = new Centrifuge(centrifugoConfig.wsUrl, { token: centrifugoConfig.token });
    const sub = c.newSubscription(centrifugoConfig.channel);

    sub.on('publication', (ctx: { data: Record<string, unknown> }) => {
      const eventType = ctx.data?.type as string | undefined;
      if (eventType !== 'message.received' && eventType !== 'message.sent') return;
      const incomingConvId = ctx.data?.conversation_id as number | undefined;

      // preserveScroll + preserveState anti-flash (US-WA-068):
      // sem isso, partial reload re-renderiza tudo + UI pisca a cada msg nova
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

  // Polling fallback 5s — defense in depth (US-WA-066).
  // Cliente real cancelou contrato 2026-05-11 por mensagem perdida quando
  // Centrifugo falhou silenciosamente. RODA SEMPRE em paralelo ao WSS.
  useEffect(() => {
    const interval = setInterval(() => {
      if (document.visibilityState !== 'visible') return;
      const only = thread
        ? ['messages', 'thread', 'conversations', 'stats']
        : ['conversations', 'stats'];
      // preserveScroll+preserveState anti-flash (US-WA-068)
      router.reload({ only, preserveScroll: true, preserveState: true });
    }, 5000);
    return () => clearInterval(interval);
  }, [thread?.id]);

  function selectThread(id: number) {
    // Perf §1(a) charter: refetch APENAS thread+messages quando troca conv.
    // `conversations` NÃO precisa rebuscar — selectedId é estado local
    // React (thread?.id), o highlight da linha é puramente client-side.
    // Antes incluía 'conversations' no only[] → 50 conv rows com N+1
    // lastMsg subquery a cada click → switch ~2s. Sem ele: ~300ms.
    router.get(
      route('atendimento.inbox.index'),
      { tab, q, thread: id },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages'],
      },
    );
  }

  return (
    <div className="flex flex-col flex-1 min-h-0 gap-1">
      {/* Header compacto */}
      <div className="flex items-center justify-between gap-3 shrink-0">
        <div className="flex items-center gap-2 min-w-0 flex-wrap">
          <div className="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <InboxIcon size={16} />
          </div>
          <div className="min-w-0 flex items-center gap-2 flex-wrap">
            <h1 className="font-semibold text-sm leading-tight truncate">Inbox Atendimento</h1>
            {/* CYCLE-08 PR-A (US-WA-040): dropdown topbar pra alternar canal ativo.
                D-14 perf: availableChannels deferred — fallback discreto enquanto async fetch resolve. */}
            <Deferred data="availableChannels" fallback={<span className="h-5 w-24 inline-block bg-muted/30 rounded animate-pulse" aria-label="Carregando canais…" />}>
              <ChannelSelector
                availableChannels={availableChannels ?? []}
                selectedChannelId={selectedChannelId}
              />
            </Deferred>
            <span className="hidden md:inline text-[11px] text-muted-foreground truncate">
              atalhos: <kbd className="px-1 py-0 border rounded text-[10px]">J</kbd>/<kbd className="px-1 py-0 border rounded text-[10px]">K</kbd> navega · <kbd className="px-1 py-0 border rounded text-[10px]">/</kbd> busca · <kbd className="px-1 py-0 border rounded text-[10px]">E</kbd> resolve · <kbd className="px-1 py-0 border rounded text-[10px]">A</kbd> aguardar
            </span>
          </div>
        </div>
      </div>

      {/* Cockpit 3-painéis */}
      <div className="flex-1 flex flex-col lg:flex-row gap-2 min-h-0">
        {/* Painel ESQUERDO — lista (colapsável) */}
        {leftSidebarCollapsed ? (
          <div className="hidden lg:flex shrink-0 min-h-0">
            <button
              type="button"
              onClick={() => setLeftSidebarCollapsed(false)}
              className="w-8 h-full border rounded bg-card hover:bg-accent flex items-start justify-center pt-3 text-muted-foreground hover:text-foreground transition-colors"
              title="Expandir lista de conversas"
              aria-label="Expandir lista"
            >
              <ChevronRight size={16} />
            </button>
          </div>
        ) : (
          <div className="lg:w-80 xl:w-96 shrink-0 min-h-0">
            {/* D-14 perf: conversations + stats deferred — skeleton ~100ms enquanto async fetch resolve. */}
            <Deferred
              data={['conversations', 'stats']}
              fallback={(
                <Card className="h-full flex flex-col">
                  <div className="border-b p-3 flex items-center gap-2">
                    <div className="h-6 w-32 bg-muted/40 rounded animate-pulse" />
                  </div>
                  <div className="flex-1 p-2 space-y-2 overflow-hidden">
                    {Array.from({ length: 8 }).map((_, i) => (
                      <div key={i} className="flex gap-2 items-center p-2">
                        <div className="h-8 w-8 rounded-full bg-muted/40 animate-pulse shrink-0" />
                        <div className="flex-1 space-y-1.5">
                          <div className="h-3 w-3/4 bg-muted/40 rounded animate-pulse" />
                          <div className="h-2 w-1/2 bg-muted/30 rounded animate-pulse" />
                        </div>
                      </div>
                    ))}
                    <div className="flex items-center justify-center pt-4 text-muted-foreground text-xs">
                      <Loader2 size={14} className="animate-spin mr-2" aria-hidden /> Carregando conversas…
                    </div>
                  </div>
                </Card>
              )}
            >
              <ConversationList
                conversations={conversations as Paginated<ListConversation>}
                tab={tab}
                q={q}
                stats={stats as { unread: number; assigned: number; bot: number; awaiting_human: number; archived: number }}
                selectedId={thread?.id ?? null}
                onSelect={selectThread}
                permalinkRouteName="atendimento.inbox.index"
                routeName="atendimento.inbox.index"
                onCollapse={() => setLeftSidebarCollapsed(true)}
                within24h={within24h}
                unlinked={unlinked}
                mediaInbound24h={mediaInbound24h}
                inboundAging={inboundAging}
                orderBy={orderBy}
              />
            </Deferred>
          </div>
        )}

        {/* Painel CENTRO — thread ou empty */}
        <div className="flex-1 min-w-0 min-h-0">
          {thread && messages !== null ? (
            <ConversationThread
              conversation={thread}
              messages={messages}
              centrifugoConfig={centrifugoConfig}
              templates={[] as ReadyTemplate[]}
              reloadOnly={['thread', 'messages']}
              sendRouteName="atendimento.inbox.send"
            />
          ) : (
            <Card className="h-full flex items-center justify-center bg-muted/20">
              <EmptyState
                icon="message-circle"
                variant="default"
                title="Selecione uma conversa"
                description={
                  conversations
                    ? `${conversations.total} conversa${conversations.total !== 1 ? 's' : ''} no schema omnichannel. Use J/K pra navegar pelo teclado.`
                    : 'Carregando inbox…'
                }
              />
            </Card>
          )}
        </div>

        {/* Painel DIREITO — sidebar contexto/ações */}
        {thread && (
          <div className="hidden lg:flex flex-col min-h-0">
            {sidebarCollapsed ? (
              <button
                type="button"
                onClick={() => setSidebarCollapsed(false)}
                className="w-8 h-full border rounded bg-card hover:bg-accent flex items-start justify-center pt-3 text-muted-foreground hover:text-foreground transition-colors"
                title="Expandir painel de contexto"
                aria-label="Expandir painel"
              >
                <ChevronLeft size={16} />
              </button>
            ) : (
              /* D-14 perf: availableTags deferred — Sidebar render com [] e completa quando async resolve. */
              <Deferred data="availableTags" fallback={null}>
                <ConversationSidebar
                  conversation={thread}
                  reloadOnly={['thread', 'conversations']}
                  enableShortcuts
                  onCollapse={() => setSidebarCollapsed(true)}
                  updateStatusRouteName="atendimento.inbox.update_status"
                  availableTags={availableTags ?? []}
                  updateTagsRouteName="atendimento.inbox.update_tags"
                  searchContactsRouteName="atendimento.inbox.contacts.search"
                  linkContactRouteName="atendimento.inbox.link_contact"
                  createContactFromPhoneRouteName="atendimento.inbox.contact.create_from_phone"
                  blockRouteName="atendimento.inbox.block"
                />
              </Deferred>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

InboxIndex.layout = (page: React.ReactElement) => <AppShellV2>{page}</AppShellV2>;
