// @memcofre
//   tela: /whatsapp/conversations
//   stories: US-WA-012 (Inbox Cockpit pattern)
//   adrs: 0096, 0039 (Chat Cockpit pattern)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Lote 2e (lista; chat detalhe em Show.tsx)
//   permissao: whatsapp.access

import { router, Link } from '@inertiajs/react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface Conversation {
  id: number;
  customer_phone: string;
  contact_name: string;
  status: 'open' | 'awaiting_human' | 'resolved' | 'archived';
  unread_count: number;
  bot_handling: boolean;
  last_message_at: string | null;
  last_inbound_at: string | null;
  within_24h_window: boolean;
}

interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
}

interface Props {
  conversations: Paginated<Conversation>;
  tab: 'all' | 'unread' | 'assigned' | 'bot' | 'resolved';
  stats: { unread: number; assigned: number; bot: number };
  businessId: number;
}

export default function ConversationsIndex({ conversations, tab, stats, businessId }: Props) {
  function setTab(newTab: string) {
    router.get(route('whatsapp.conversations.index'), { tab: newTab }, {
      preserveScroll: true,
      preserveState: true,
      only: ['conversations', 'tab', 'stats'],
    });
  }

  return (
    <div className="space-y-4">
      <PageHeader
        title="Conversas Whatsapp"
        subtitle={`Inbox real-time · Centrifugo channel: whatsapp:business:${businessId}`}
        actions={
          <Button variant="outline" onClick={() => router.reload({ only: ['conversations', 'stats'] })}>
            Atualizar
          </Button>
        }
      />

      {/* Tabs */}
      <div className="flex flex-wrap gap-2 border-b pb-2">
        <TabButton active={tab === 'all'} onClick={() => setTab('all')} label="Todas" count={null} />
        <TabButton active={tab === 'unread'} onClick={() => setTab('unread')} label="Não lidas" count={stats.unread} />
        <TabButton active={tab === 'assigned'} onClick={() => setTab('assigned')} label="Atribuídas a mim" count={stats.assigned} />
        <TabButton active={tab === 'bot'} onClick={() => setTab('bot')} label="Bot" count={stats.bot} />
        <TabButton active={tab === 'resolved'} onClick={() => setTab('resolved')} label="Resolvidas" count={null} />
      </div>

      {/* Lista */}
      {conversations.data.length === 0 ? (
        <Card className="p-8 text-center text-muted-foreground">
          Nenhuma conversa ainda. Webhooks Meta/Z-API entregam aqui em real-time quando ativos.
        </Card>
      ) : (
        <div className="space-y-2">
          {conversations.data.map((conv) => (
            <Link
              key={conv.id}
              href={route('whatsapp.conversations.show', conv.id)}
              className="block rounded-lg border bg-card hover:bg-accent transition p-3"
            >
              <div className="flex items-center justify-between gap-3">
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <span className="font-medium truncate">{conv.contact_name}</span>
                    {conv.unread_count > 0 && (
                      <Badge variant="default" className="bg-blue-600">{conv.unread_count}</Badge>
                    )}
                    {conv.bot_handling && (
                      <Badge variant="outline" className="border-purple-500 text-purple-700">bot</Badge>
                    )}
                    <StatusBadge status={conv.status} />
                  </div>
                  <div className="text-sm text-muted-foreground truncate">
                    {conv.customer_phone}
                  </div>
                </div>
                <div className="text-xs text-muted-foreground text-right shrink-0">
                  {conv.last_message_at && (
                    <div>{new Date(conv.last_message_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' })}</div>
                  )}
                  {conv.within_24h_window ? (
                    <Badge variant="outline" className="border-green-500 text-green-700 mt-1">Janela 24h aberta</Badge>
                  ) : (
                    <Badge variant="outline" className="border-amber-500 text-amber-700 mt-1">Janela 24h fechada</Badge>
                  )}
                </div>
              </div>
            </Link>
          ))}
        </div>
      )}

      {/* Paginação simples */}
      {conversations.last_page > 1 && (
        <div className="flex justify-center gap-2 pt-2">
          <Button
            variant="outline"
            size="sm"
            disabled={conversations.current_page === 1}
            onClick={() => router.get(route('whatsapp.conversations.index'), { tab, page: conversations.current_page - 1 })}
          >
            ← Anterior
          </Button>
          <span className="text-sm text-muted-foreground self-center">
            Página {conversations.current_page} de {conversations.last_page}
          </span>
          <Button
            variant="outline"
            size="sm"
            disabled={conversations.current_page === conversations.last_page}
            onClick={() => router.get(route('whatsapp.conversations.index'), { tab, page: conversations.current_page + 1 })}
          >
            Próxima →
          </Button>
        </div>
      )}
    </div>
  );
}

ConversationsIndex.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;

function TabButton({ active, onClick, label, count }: { active: boolean; onClick: () => void; label: string; count: number | null }) {
  return (
    <button
      type="button"
      onClick={onClick}
      className={`px-3 py-1.5 text-sm rounded-md transition ${
        active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent text-foreground'
      }`}
    >
      {label}
      {count !== null && count > 0 && (
        <span className={`ml-2 inline-flex items-center justify-center min-w-[20px] h-5 px-1.5 text-xs rounded-full ${
          active ? 'bg-white/20 text-white' : 'bg-muted text-muted-foreground'
        }`}>{count}</span>
      )}
    </button>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { label: string; className: string }> = {
    open: { label: 'aberta', className: 'border-blue-400 text-blue-700' },
    awaiting_human: { label: 'aguardando humano', className: 'border-amber-500 text-amber-700' },
    resolved: { label: 'resolvida', className: 'border-green-500 text-green-700' },
    archived: { label: 'arquivada', className: 'border-slate-400 text-slate-600' },
  };
  const conf = map[status] ?? map.open;
  return <Badge variant="outline" className={conf.className}>{conf.label}</Badge>;
}
