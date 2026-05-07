// @memcofre
//   tela: /whatsapp/conversations[?thread=ID]
//   stories: US-WA-012 (Cockpit 3-painéis: lista | thread | sidebar)
//   adrs: 0096, 0039 (Chat Cockpit pattern), 0058 (Centrifugo CT 100)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada — Lote UI estado-da-arte
//   permissao: whatsapp.access
//
// Comportamento canon (ADR 0039 Chat Cockpit):
//   - Esquerda: lista persistente com search + tabs (não recarrega ao abrir thread)
//   - Centro: thread atualiza inline via partial reload (router.get only:[thread,messages])
//   - Direita: sidebar de contexto + ações
//   - Sem ?thread=X: empty state convidativo no centro/direita

import { router } from '@inertiajs/react';

import AppShellV2 from '@/Layouts/AppShellV2';
import { Card } from '@/Components/ui/card';

import ConversationList from '../_components/ConversationList';
import ConversationThread from '../_components/ConversationThread';
import ConversationSidebar from '../_components/ConversationSidebar';
import type {
  CentrifugoConfig,
  ListConversation,
  Message,
  ThreadConversation,
} from '../_components/helpers';

interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

interface Props {
  conversations: Paginated<ListConversation>;
  tab: 'all' | 'unread' | 'assigned' | 'bot' | 'resolved';
  q: string;
  stats: { unread: number; assigned: number; bot: number };
  businessId: number;
  thread: ThreadConversation | null;
  messages: Message[] | null;
  centrifugoConfig: CentrifugoConfig | null;
  centrifugoChannel: string | null;
}

export default function ConversationsIndex({
  conversations, tab, q, stats, businessId,
  thread, messages, centrifugoConfig,
}: Props) {
  function selectThread(id: number) {
    router.get(
      route('whatsapp.conversations.index'),
      { tab, q, thread: id },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages', 'centrifugoConfig', 'centrifugoChannel', 'conversations'],
      },
    );
  }

  return (
    <div className="flex flex-col h-[calc(100vh-7rem)] gap-2">
      {/* Header compacto */}
      <div className="flex items-center justify-between gap-3 shrink-0">
        <div className="flex items-center gap-2 min-w-0">
          <div className="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg shrink-0">
            💬
          </div>
          <div className="min-w-0">
            <h1 className="font-semibold text-base leading-tight truncate">Conversas WhatsApp</h1>
            <div className="text-[11px] text-muted-foreground truncate">
              Inbox real-time · canal Centrifugo whatsapp:business:{businessId}
            </div>
          </div>
        </div>
      </div>

      {/* Cockpit 3-painéis */}
      <div className="flex-1 flex flex-col lg:flex-row gap-2 min-h-0">
        {/* Painel ESQUERDO — lista */}
        <div className="lg:w-80 xl:w-96 shrink-0 min-h-0">
          <ConversationList
            conversations={conversations}
            tab={tab}
            q={q}
            stats={stats}
            selectedId={thread?.id ?? null}
            onSelect={selectThread}
            permalinkRouteName="whatsapp.conversations.show"
            routeName="whatsapp.conversations.index"
          />
        </div>

        {/* Painel CENTRO — thread ou empty */}
        <div className="flex-1 min-w-0 min-h-0">
          {thread && messages !== null ? (
            <ConversationThread
              conversation={thread}
              messages={messages}
              centrifugoConfig={centrifugoConfig}
              reloadOnly={['thread', 'messages']}
            />
          ) : (
            <Card className="h-full flex flex-col items-center justify-center text-center p-8 bg-muted/20">
              <div className="text-7xl opacity-20 mb-4">💬</div>
              <div className="font-medium mb-1">Selecione uma conversa</div>
              <div className="text-sm text-muted-foreground max-w-xs">
                Escolha uma conversa na lista pra ver o histórico, responder e gerenciar status.
              </div>
            </Card>
          )}
        </div>

        {/* Painel DIREITO — sidebar contexto/ações (só quando thread aberta) */}
        {thread && (
          <div className="hidden lg:block min-h-0">
            <ConversationSidebar
              conversation={thread}
              reloadOnly={['thread']}
            />
          </div>
        )}
      </div>
    </div>
  );
}

ConversationsIndex.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;
