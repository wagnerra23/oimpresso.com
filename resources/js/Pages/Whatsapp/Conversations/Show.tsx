// @memcofre
//   tela: /whatsapp/conversations/{id}  (rota permalink)
//   stories: US-WA-012
//   adrs: 0096, 0058 (Centrifugo CT 100), 0039 (Chat Cockpit pattern)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada — Lote UI estado-da-arte (refator pra shared components)
//   permissao: whatsapp.access
//
// Esta rota é o PERMALINK direto pra uma conversa (compartilhar URL, abrir
// em nova aba). O Cockpit canon (3-painéis com lista) está em
// `/whatsapp/conversations?thread=ID`. Aqui mostramos só thread + sidebar.

import AppShellV2 from '@/Layouts/AppShellV2';

import ConversationThread from '../_components/ConversationThread';
import ConversationSidebar from '../_components/ConversationSidebar';
import type {
  CentrifugoConfig,
  Message,
  ReadyTemplate,
  ThreadConversation,
} from '../_components/helpers';

interface Props {
  conversation: ThreadConversation;
  messages: Message[];
  centrifugoChannel: string;
  centrifugoConfig: CentrifugoConfig | null;
  templates: ReadyTemplate[];
}

export default function ConversationShow({
  conversation, messages, centrifugoConfig, templates,
}: Props) {
  return (
    <div className="flex flex-col lg:flex-row gap-2 h-[calc(100vh-7rem)]">
      <div className="flex-1 min-w-0 min-h-0">
        <ConversationThread
          conversation={conversation}
          messages={messages}
          centrifugoConfig={centrifugoConfig}
          templates={templates}
          reloadOnly={['messages', 'conversation', 'templates']}
          backHref={route('whatsapp.conversations.index')}
        />
      </div>
      <div className="hidden lg:block min-h-0">
        <ConversationSidebar
          conversation={conversation}
          reloadOnly={['conversation']}
        />
      </div>
    </div>
  );
}

ConversationShow.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;
