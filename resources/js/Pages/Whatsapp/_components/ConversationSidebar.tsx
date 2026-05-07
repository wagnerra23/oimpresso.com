import { router, usePage } from '@inertiajs/react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Separator } from '@/Components/ui/separator';

import Avatar from './Avatar';
import { formatDateTime, type ThreadConversation } from './helpers';

interface Props {
  conversation: ThreadConversation;
  /** Reload partial: indica quais props recarregar após PATCH. */
  reloadOnly: string[];
}

export default function ConversationSidebar({ conversation, reloadOnly }: Props) {
  const sharedAuth = (usePage().props as any)?.auth?.user as { id?: number } | undefined;
  const currentUserId = sharedAuth?.id ?? null;
  const isMineAssigned = !!(conversation.assigned_user && currentUserId && conversation.assigned_user.id === currentUserId);

  function patchConversation(payload: Record<string, string | number | boolean>) {
    router.patch(route('whatsapp.conversations.update_status', conversation.id), payload, {
      preserveScroll: true,
      preserveState: true,
      only: reloadOnly,
    });
  }

  return (
    <aside className="w-full lg:w-72 xl:w-80 shrink-0 space-y-3 overflow-y-auto">
      <Card className="p-4">
        <div className="flex flex-col items-center text-center gap-2">
          <Avatar name={conversation.contact_name} size="lg" />
          <div className="font-semibold leading-tight">{conversation.contact_name}</div>
          <div className="text-xs text-muted-foreground">{conversation.customer_phone}</div>
          <StatusBadge status={conversation.status} />
        </div>
      </Card>

      <Card className="p-3 space-y-2">
        <SectionLabel>Ações</SectionLabel>
        <Button
          variant={isMineAssigned ? 'default' : 'outline'}
          size="sm"
          className="w-full justify-start"
          onClick={() => patchConversation({ assigned_to_me: !isMineAssigned })}
        >
          {isMineAssigned ? '✓ Atribuída a mim' : 'Atribuir a mim'}
        </Button>
        <Button
          variant={conversation.bot_handling ? 'default' : 'outline'}
          size="sm"
          className="w-full justify-start"
          onClick={() => patchConversation({ bot_handling: !conversation.bot_handling })}
        >
          {conversation.bot_handling ? '🤖 Bot ligado' : '🤖 Ativar bot'}
        </Button>
        <Separator className="my-2" />
        {conversation.status !== 'resolved' ? (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start border-emerald-500 text-emerald-700 hover:bg-emerald-50"
            onClick={() => patchConversation({ status: 'resolved' })}
          >
            ✓ Marcar resolvida
          </Button>
        ) : (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start"
            onClick={() => patchConversation({ status: 'open' })}
          >
            ↺ Reabrir
          </Button>
        )}
        {conversation.status !== 'awaiting_human' && conversation.status !== 'resolved' && (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start border-amber-500 text-amber-700 hover:bg-amber-50"
            onClick={() => patchConversation({ status: 'awaiting_human' })}
          >
            ⏳ Aguardar humano
          </Button>
        )}
      </Card>

      <Card className="p-3">
        <SectionLabel>Janela 24h Meta</SectionLabel>
        <div className="text-xs text-muted-foreground mt-2 space-y-1">
          {conversation.within_24h_window ? (
            <p className="text-emerald-700">✓ Aberta — freeform permitido em qualquer driver.</p>
          ) : (
            <p className="text-amber-700">✕ Fechada — Meta Cloud exige template HSM aprovado. Z-API/Baileys ignoram.</p>
          )}
          {conversation.last_inbound_at && (
            <p>
              Última msg do cliente:{' '}
              <span className="font-medium">{formatDateTime(conversation.last_inbound_at)}</span>
            </p>
          )}
        </div>
      </Card>

      <Card className="p-3">
        <SectionLabel>Detalhes</SectionLabel>
        <dl className="text-xs space-y-1.5 mt-2">
          <Row label="Conversa #" value={`${conversation.id}`} />
          <Row label="Mensagens" value={`${conversation.messages_total}`} />
          {conversation.assigned_user && (
            <Row label="Atribuída a" value={conversation.assigned_user.name} />
          )}
          {conversation.created_at && (
            <Row label="Iniciada" value={formatDateTime(conversation.created_at)} />
          )}
          {conversation.last_message_at && (
            <Row label="Última msg" value={formatDateTime(conversation.last_message_at)} />
          )}
        </dl>
      </Card>
    </aside>
  );
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, { label: string; className: string }> = {
    open: { label: 'aberta', className: 'border-blue-500 text-blue-700 bg-blue-50' },
    awaiting_human: { label: 'aguardando humano', className: 'border-amber-500 text-amber-700 bg-amber-50' },
    resolved: { label: 'resolvida', className: 'border-emerald-500 text-emerald-700 bg-emerald-50' },
    archived: { label: 'arquivada', className: 'border-slate-400 text-slate-600 bg-slate-50' },
  };
  const conf = map[status] ?? map.open!;
  return <Badge variant="outline" className={conf.className}>{conf.label}</Badge>;
}

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <div className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
      {children}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between gap-2">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="font-medium text-right truncate">{value}</dd>
    </div>
  );
}
