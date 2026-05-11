import { useEffect } from 'react';
import { router, usePage } from '@inertiajs/react';
import {
  UserCheck,
  Bot,
  Check,
  RotateCcw,
  Hourglass,
  Clock,
  AlertTriangle,
  ChevronRight,
} from 'lucide-react';

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
  /** Quando true, registra atalhos E (resolver) e A (aguardar humano) globais. */
  enableShortcuts?: boolean;
  /** Quando fornecido, renderiza botão pra colapsar a sidebar (chama callback). */
  onCollapse?: () => void;
}

export default function ConversationSidebar({ conversation, reloadOnly, enableShortcuts = false, onCollapse }: Props) {
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

  // Atalhos teclado E (resolver) e A (aguardar humano) — ADR 0039 §2.
  useEffect(() => {
    if (!enableShortcuts) return;
    function handler(e: KeyboardEvent) {
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement ||
        (e.target instanceof HTMLElement && e.target.isContentEditable)
      ) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;

      if (e.key === 'e' || e.key === 'E') {
        if (conversation.status === 'resolved') return;
        e.preventDefault();
        patchConversation({ status: 'resolved' });
      }
      if (e.key === 'a' || e.key === 'A') {
        if (conversation.status === 'awaiting_human' || conversation.status === 'resolved') return;
        e.preventDefault();
        patchConversation({ status: 'awaiting_human' });
      }
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [enableShortcuts, conversation.id, conversation.status]);

  return (
    <aside className="w-full lg:w-72 xl:w-80 shrink-0 space-y-3 overflow-y-auto" aria-label="Contexto da conversa">
      <Card className="p-4 relative">
        {onCollapse && (
          <button
            type="button"
            onClick={onCollapse}
            className="absolute top-2 right-2 w-6 h-6 rounded hover:bg-accent text-muted-foreground hover:text-foreground flex items-center justify-center transition-colors"
            title="Minimizar painel"
            aria-label="Minimizar painel de contexto"
          >
            <ChevronRight size={14} />
          </button>
        )}
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
          className="w-full justify-start gap-2"
          onClick={() => patchConversation({ assigned_to_me: !isMineAssigned })}
          title={isMineAssigned ? 'Clique pra liberar a conversa' : 'Atribuir esta conversa a mim'}
        >
          <UserCheck size={14} aria-hidden />
          {isMineAssigned ? 'Atribuída a mim' : 'Atribuir a mim'}
        </Button>
        <Button
          variant={conversation.bot_handling ? 'default' : 'outline'}
          size="sm"
          className="w-full justify-start gap-2"
          onClick={() => patchConversation({ bot_handling: !conversation.bot_handling })}
          title={conversation.bot_handling ? 'Desligar bot Jana' : 'Ligar bot Jana (HITL)'}
        >
          <Bot size={14} aria-hidden />
          {conversation.bot_handling ? 'Bot ligado' : 'Ativar bot'}
        </Button>
        <Separator className="my-2" />
        {conversation.status !== 'resolved' ? (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start gap-2 border-emerald-500 text-emerald-700 dark:text-emerald-400 dark:border-emerald-700 hover:bg-emerald-50 dark:hover:bg-emerald-950/30"
            onClick={() => patchConversation({ status: 'resolved' })}
            title={enableShortcuts ? 'Atalho: E' : 'Marcar conversa como resolvida'}
          >
            <Check size={14} aria-hidden />
            Marcar resolvida
            {enableShortcuts && <kbd className="ml-auto text-[10px] opacity-60">E</kbd>}
          </Button>
        ) : (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start gap-2"
            onClick={() => patchConversation({ status: 'open' })}
            title="Reabrir conversa"
          >
            <RotateCcw size={14} aria-hidden />
            Reabrir
          </Button>
        )}
        {conversation.status !== 'awaiting_human' && conversation.status !== 'resolved' && (
          <Button
            variant="outline"
            size="sm"
            className="w-full justify-start gap-2 border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 hover:bg-amber-50 dark:hover:bg-amber-950/30"
            onClick={() => patchConversation({ status: 'awaiting_human' })}
            title={enableShortcuts ? 'Atalho: A' : 'Marcar como aguardando atendimento humano'}
          >
            <Hourglass size={14} aria-hidden />
            Aguardar humano
            {enableShortcuts && <kbd className="ml-auto text-[10px] opacity-60">A</kbd>}
          </Button>
        )}
      </Card>

      <Card className="p-3">
        <SectionLabel>Janela 24h Meta</SectionLabel>
        <div className="text-xs text-muted-foreground mt-2 space-y-1">
          {conversation.within_24h_window ? (
            <p className="text-emerald-700 dark:text-emerald-400 inline-flex items-center gap-1">
              <Check size={12} aria-hidden />
              Aberta — freeform permitido em qualquer driver.
            </p>
          ) : (
            <p className="text-amber-700 dark:text-amber-400 inline-flex items-start gap-1">
              <AlertTriangle size={12} className="mt-0.5 shrink-0" aria-hidden />
              <span>Fechada — Meta Cloud exige template HSM aprovado. Z-API/Baileys ignoram.</span>
            </p>
          )}
          {conversation.last_inbound_at && (
            <p className="inline-flex items-center gap-1">
              <Clock size={12} aria-hidden />
              <span>
                Última msg do cliente:{' '}
                <span className="font-medium">{formatDateTime(conversation.last_inbound_at)}</span>
              </span>
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
    open: { label: 'aberta', className: 'border-blue-500 text-blue-700 dark:text-blue-400 dark:border-blue-700 bg-blue-50 dark:bg-blue-950/30' },
    awaiting_human: { label: 'aguardando humano', className: 'border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30' },
    resolved: { label: 'resolvida', className: 'border-emerald-500 text-emerald-700 dark:text-emerald-400 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/30' },
    archived: { label: 'arquivada', className: 'border-slate-400 text-slate-600 dark:text-slate-400 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/30' },
  };
  const conf = map[status] ?? map.open!;
  return <Badge variant="outline" className={conf.className}>{conf.label}</Badge>;
}

function SectionLabel({ children }: { children: React.ReactNode }) {
  return (
    <div className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
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
