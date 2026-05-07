import { useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Centrifuge } from 'centrifuge';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

import Avatar from './Avatar';
import { groupByDay, type CentrifugoConfig, type Message, type ThreadConversation } from './helpers';

interface Props {
  conversation: ThreadConversation;
  messages: Message[];
  centrifugoConfig: CentrifugoConfig | null;
  /** Reload partial: indica quais props recarregar quando vem mensagem nova. */
  reloadOnly: string[];
  /** Botão "← inbox" só aparece em rota permalink (Show). */
  backHref?: string;
}

export default function ConversationThread({
  conversation, messages, centrifugoConfig, reloadOnly, backHref,
}: Props) {
  const [composerText, setComposerText] = useState('');
  const [sending, setSending] = useState(false);
  const [liveConnected, setLiveConnected] = useState(false);
  const [showScrollBottom, setShowScrollBottom] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  // Auto-scroll quando nova mensagem chega (ou usuário entra na thread).
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [conversation.id, messages.length]);

  // Centrifugo real-time (ADR 0058) — subscribe channel `whatsapp:business:{biz}`.
  // Quando backend publica `message_received`/`message_sent`, recarrega via partial.
  useEffect(() => {
    if (!centrifugoConfig) return;

    const c = new Centrifuge(centrifugoConfig.wsUrl, { token: centrifugoConfig.token });
    c.on('connected', () => setLiveConnected(true));
    c.on('disconnected', () => setLiveConnected(false));

    const sub = c.newSubscription(centrifugoConfig.channel);
    sub.on('publication', (ctx: { data: Record<string, unknown> }) => {
      const event = ctx.data?.event as string | undefined;
      if (event === 'message_received' || event === 'message_sent') {
        router.reload({ only: reloadOnly });
      }
    });
    sub.subscribe();
    c.connect();

    return () => {
      try { sub.unsubscribe(); } catch { /* ignore */ }
      c.disconnect();
    };
  }, [centrifugoConfig?.token, centrifugoConfig?.channel, centrifugoConfig?.wsUrl, reloadOnly.join(',')]);

  function handleSend() {
    if (!composerText.trim() || sending) return;
    setSending(true);
    router.post(
      route('whatsapp.conversations.send', conversation.id),
      { kind: 'freeform', body: composerText },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setComposerText('');
          router.reload({ only: reloadOnly });
        },
        onFinish: () => setSending(false),
      },
    );
  }

  function handleScroll() {
    const el = scrollRef.current;
    if (!el) return;
    const dist = el.scrollHeight - (el.scrollTop + el.clientHeight);
    setShowScrollBottom(dist > 200);
  }

  function scrollToBottom() {
    if (scrollRef.current) {
      scrollRef.current.scrollTo({ top: scrollRef.current.scrollHeight, behavior: 'smooth' });
    }
  }

  const canSendFreeform = conversation.within_24h_window;
  const groupedMessages = useMemo(() => groupByDay(messages), [messages]);

  return (
    <Card className="flex flex-col overflow-hidden h-full min-w-0">
      {/* Header sticky */}
      <div className="border-b px-3 py-2 flex items-center justify-between gap-3 bg-card shrink-0">
        <div className="flex items-center gap-2.5 min-w-0">
          {backHref && (
            <a href={backHref} className="shrink-0">
              <Button variant="ghost" size="sm" className="px-2">←</Button>
            </a>
          )}
          <Avatar name={conversation.contact_name} size="sm" />
          <div className="min-w-0">
            <div className="flex items-center gap-1.5 min-w-0">
              <h2 className="font-semibold truncate text-sm">{conversation.contact_name}</h2>
              <StatusDot status={conversation.status} />
            </div>
            <div className="text-[11px] text-muted-foreground truncate">
              {conversation.customer_phone}
              {centrifugoConfig && (
                <span className="ml-2">
                  {liveConnected ? (
                    <span className="text-emerald-600">● live</span>
                  ) : (
                    <span className="text-slate-400">○ conectando…</span>
                  )}
                </span>
              )}
            </div>
          </div>
        </div>
        <div className="shrink-0">
          {conversation.within_24h_window ? (
            <Badge variant="outline" className="border-emerald-500 text-emerald-700 bg-emerald-50 text-[10px]">
              24h aberta
            </Badge>
          ) : (
            <Badge variant="outline" className="border-amber-500 text-amber-700 bg-amber-50 text-[10px]">
              24h fechada
            </Badge>
          )}
        </div>
      </div>

      {/* Thread */}
      <div className="flex-1 relative min-h-0">
        <div
          ref={scrollRef}
          onScroll={handleScroll}
          className="absolute inset-0 overflow-y-auto p-4 space-y-2"
          style={{
            background: 'linear-gradient(180deg, rgba(229,221,213,0.35) 0%, rgba(229,221,213,0.20) 100%)',
          }}
        >
          {messages.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center text-muted-foreground gap-1">
              <div className="text-5xl opacity-30 mb-2">💬</div>
              <div className="text-sm font-medium">Sem mensagens ainda</div>
              <div className="text-xs">Mande a primeira ou aguarde o cliente.</div>
            </div>
          ) : (
            groupedMessages.map((group) => (
              <div key={group.label} className="space-y-1.5">
                <div className="flex justify-center my-3">
                  <span className="text-[10px] uppercase tracking-wider bg-card border rounded-full px-2.5 py-0.5 text-muted-foreground shadow-sm">
                    {group.label}
                  </span>
                </div>
                {group.messages.map((m, i) => (
                  <MessageBubble
                    key={m.id}
                    message={m}
                    showTail={i === group.messages.length - 1 || group.messages[i + 1]?.direction !== m.direction}
                  />
                ))}
              </div>
            ))
          )}
        </div>

        {/* Botão scroll bottom */}
        {showScrollBottom && (
          <button
            type="button"
            onClick={scrollToBottom}
            className="absolute bottom-3 right-3 bg-card border shadow-md rounded-full w-9 h-9 flex items-center justify-center hover:bg-accent transition"
            aria-label="Rolar pra última mensagem"
          >
            ↓
          </button>
        )}
      </div>

      {/* Composer */}
      <div className="border-t bg-card p-2.5 space-y-2 shrink-0">
        {!canSendFreeform && (
          <div className="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded px-2.5 py-1.5">
            ⚠️ Janela 24h Meta fechada. Z-API/Baileys mandam freeform; Meta Cloud exige template HSM.
          </div>
        )}
        <Textarea
          value={composerText}
          onChange={(e) => setComposerText(e.target.value)}
          placeholder="Mensagem freeform…  (Enter envia · Shift+Enter quebra linha)"
          rows={2}
          className="resize-none"
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              handleSend();
            }
          }}
        />
        <div className="flex justify-between items-center gap-2">
          <span className="text-[10px] text-muted-foreground tabular-nums">
            {composerText.length} {composerText.length === 1 ? 'caractere' : 'caracteres'}
          </span>
          <div className="flex gap-1.5">
            <Button variant="outline" size="sm" disabled className="h-8">
              Template
            </Button>
            <Button size="sm" onClick={handleSend} disabled={!composerText.trim() || sending} className="h-8">
              {sending ? 'Enviando…' : 'Enviar'}
            </Button>
          </div>
        </div>
      </div>
    </Card>
  );
}

function MessageBubble({ message, showTail }: { message: Message; showTail: boolean }) {
  const isOut = message.direction === 'outbound';
  const time = new Date(message.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

  const corner = isOut
    ? showTail ? 'rounded-2xl rounded-br-md' : 'rounded-2xl rounded-r-md'
    : showTail ? 'rounded-2xl rounded-bl-md' : 'rounded-2xl rounded-l-md';

  return (
    <div className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[75%] px-3 py-1.5 shadow-sm ${corner} ${
          isOut ? 'bg-emerald-600 text-white' : 'bg-white text-foreground border'
        }`}
      >
        {message.sender_kind === 'bot' && (
          <div className={`text-[10px] font-semibold mb-0.5 uppercase tracking-wide ${
            isOut ? 'text-emerald-100' : 'text-purple-700'
          }`}>
            🤖 Bot
          </div>
        )}
        <div className="whitespace-pre-wrap break-words text-sm leading-snug">
          {message.body ?? <em className="opacity-70">[mídia]</em>}
        </div>
        <div className={`text-[10px] mt-0.5 flex items-center justify-end gap-1 ${
          isOut ? 'text-emerald-100' : 'text-muted-foreground'
        }`}>
          <span>{time}</span>
          {isOut && <StatusIcon status={message.status} />}
          {message.status === 'failed' && message.failed_reason && (
            <span className={isOut ? 'text-red-200' : 'text-red-600'} title={message.failed_reason}>
              · falha
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

function StatusIcon({ status }: { status: string }) {
  const map: Record<string, string> = {
    queued: '⏳',
    sent: '✓',
    delivered: '✓✓',
    read: '✓✓',
    failed: '⚠',
    received: '←',
  };
  const isRead = status === 'read';
  return <span className={isRead ? 'text-sky-200 font-bold' : ''}>{map[status] ?? status}</span>;
}

function StatusDot({ status }: { status: string }) {
  const color: Record<string, string> = {
    open: 'bg-blue-500',
    awaiting_human: 'bg-amber-500',
    resolved: 'bg-emerald-500',
    archived: 'bg-slate-400',
  };
  return <span className={`inline-block w-2 h-2 rounded-full shrink-0 ${color[status] ?? color.open}`} aria-label={status} />;
}
