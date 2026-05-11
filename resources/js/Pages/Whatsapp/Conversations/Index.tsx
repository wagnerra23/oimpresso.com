// @memcofre
//   tela: /whatsapp/conversations[?thread=ID]
//   stories: US-WA-012 (Cockpit 3-painéis: lista | thread | sidebar)
//   adrs: 0096, 0039 (Chat Cockpit pattern), 0058 (Centrifugo CT 100)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada — Lote UI estado-da-arte + padronização DS/UX (PR #177)
//   permissao: whatsapp.access
//
// Comportamento canon (ADR 0039 Chat Cockpit):
//   - Esquerda: lista persistente com search + tabs (não recarrega ao abrir thread)
//   - Centro: thread atualiza inline via partial reload (router.get only:[thread,messages])
//   - Direita: sidebar de contexto + ações
//   - Sem ?thread=X: empty state convidativo no centro/direita
//   - Atalhos J/K (lista) + E/A (sidebar) + / (foca search) — ADR 0039 §2
//   - Persistência: oimpresso.whatsapp.{tab,q,thread} em localStorage (R-DS-012)

import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Icon } from '@/Components/Icon';

import AppShellV2 from '@/Layouts/AppShellV2';
import EmptyState from '@/Components/shared/EmptyState';
import { Card } from '@/Components/ui/card';

import ConversationList from '../_components/ConversationList';
import ConversationThread from '../_components/ConversationThread';
import ConversationSidebar from '../_components/ConversationSidebar';
import { LS, lsGet, lsSet } from '../_components/helpers';
import type {
  CentrifugoConfig,
  ListConversation,
  Message,
  ReadyTemplate,
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
  templates: ReadyTemplate[] | null;
}

export default function ConversationsIndex({
  conversations, tab, q, stats, businessId,
  thread, messages, centrifugoConfig, templates,
}: Props) {
  // Hidrata URL com state persistido em localStorage no primeiro mount.
  // Wagner exigiu (auto-mem preference_cache_estado_preservado) — F5 não pode
  // trocar a UX. Se URL vazia mas localStorage tem dados, redireciona.
  useEffect(() => {
    const url = new URL(window.location.href);
    const hasUrlState = url.searchParams.has('tab') || url.searchParams.has('q') || url.searchParams.has('thread');
    if (hasUrlState) return;

    const lsTab = lsGet(LS.TAB);
    const lsQ = lsGet(LS.Q);
    const lsThread = lsGet(LS.THREAD);

    if (lsTab || lsQ || lsThread) {
      const params: Record<string, string | number> = {};
      if (lsTab && lsTab !== 'all') params.tab = lsTab;
      if (lsQ) params.q = lsQ;
      if (lsThread) params.thread = Number(lsThread);
      router.get(route('whatsapp.conversations.index'), params, { replace: true, preserveScroll: true });
    }
  }, []);

  // Persiste thread selecionada
  useEffect(() => {
    lsSet(LS.THREAD, thread?.id ? String(thread.id) : null);
  }, [thread?.id]);

  // Sidebar direita colapsável (Wagner 2026-05-11) — monitor pequeno (1024-1366px)
  // fica apertado com lista 384 + sidebar 320 fixos. Persiste preferência em LS.
  const [sidebarCollapsed, setSidebarCollapsed] = useState(
    () => lsGet(LS.SIDEBAR_COLLAPSED) === '1',
  );
  useEffect(() => {
    lsSet(LS.SIDEBAR_COLLAPSED, sidebarCollapsed ? '1' : null);
  }, [sidebarCollapsed]);

  // Sidebar esquerda (lista conversations) colapsável — Wagner UX polish round 2.
  const [leftSidebarCollapsed, setLeftSidebarCollapsed] = useState(
    () => lsGet(LS.LEFT_SIDEBAR_COLLAPSED) === '1',
  );
  useEffect(() => {
    lsSet(LS.LEFT_SIDEBAR_COLLAPSED, leftSidebarCollapsed ? '1' : null);
  }, [leftSidebarCollapsed]);

  // Polling fallback 5s — Centrifugo CT 100 não exposto publicamente.
  // Mesmo padrão NfeBrasil (US-NFE-002). Hook abstrai a fonte; quando
  // Centrifugo for exposto via Traefik público + secrets em .env, basta
  // remover este polling e o real-time Centrifugo já implementado em
  // ConversationThread.tsx assume sozinho (preserva contrato).
  // Recarrega só conversations (lista lateral) — thread/messages têm seu
  // próprio polling em ConversationThread.tsx quando Centrifugo offline.
  useEffect(() => {
    if (centrifugoConfig) return; // futuro: Centrifugo conectado = sem polling
    const interval = setInterval(() => {
      if (document.visibilityState !== 'visible') return; // pausa em aba inativa
      router.reload({ only: ['conversations', 'stats'] });
    }, 5000);
    return () => clearInterval(interval);
  }, [centrifugoConfig]);

  function selectThread(id: number) {
    lsSet(LS.THREAD, String(id));
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
    <div className="flex flex-col flex-1 min-h-0 gap-1">
      {/* Header compacto */}
      <div className="flex items-center justify-between gap-3 shrink-0">
        <div className="flex items-center gap-2 min-w-0">
          <div className="w-8 h-8 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
            <Icon name="message-circle" size={16} />
          </div>
          <div className="min-w-0 flex items-center gap-2">
            <h1 className="font-semibold text-sm leading-tight truncate">Inbox WhatsApp</h1>
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
            <ConversationList
              conversations={conversations}
              tab={tab}
              q={q}
              stats={stats}
              selectedId={thread?.id ?? null}
              onSelect={selectThread}
              permalinkRouteName="whatsapp.conversations.show"
              routeName="whatsapp.conversations.index"
              onCollapse={() => setLeftSidebarCollapsed(true)}
            />
          </div>
        )}

        {/* Painel CENTRO — thread ou empty */}
        <div className="flex-1 min-w-0 min-h-0">
          {thread && messages !== null ? (
            <ConversationThread
              conversation={thread}
              messages={messages}
              centrifugoConfig={centrifugoConfig}
              templates={templates ?? []}
              reloadOnly={['thread', 'messages', 'templates']}
            />
          ) : (
            <Card className="h-full flex items-center justify-center bg-muted/20">
              <EmptyState
                icon="message-circle"
                variant="default"
                title="Selecione uma conversa"
                description="Escolha uma conversa na lista pra ver o histórico, responder e gerenciar status. Use J/K pra navegar pelo teclado."
              />
            </Card>
          )}
        </div>

        {/* Painel DIREITO — sidebar contexto/ações (só quando thread aberta) */}
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
              <ConversationSidebar
                conversation={thread}
                reloadOnly={['thread']}
                enableShortcuts
                onCollapse={() => setSidebarCollapsed(true)}
              />
            )}
          </div>
        )}
      </div>
    </div>
  );
}

ConversationsIndex.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;
