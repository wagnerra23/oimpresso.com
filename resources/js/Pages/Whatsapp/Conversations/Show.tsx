// @memcofre
//   tela: /whatsapp/conversations/{id}
//   stories: US-WA-012 (chat painel + real-time Centrifugo)
//   adrs: 0096, 0058 (Centrifugo CT 100)
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   status: implementada Lote 2e (Centrifugo wiring esqueleto; integração JS client em PR posterior)
//   permissao: whatsapp.access

import { useEffect, useRef, useState } from 'react';
import { router, Link } from '@inertiajs/react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

interface Message {
  id: number;
  direction: 'inbound' | 'outbound';
  provider: string;
  type: string;
  body: string | null;
  status: string;
  failed_reason: string | null;
  sender_kind: 'human' | 'bot' | 'system' | null;
  created_at: string;
}

interface Conversation {
  id: number;
  customer_phone: string;
  contact_name: string;
  status: string;
  within_24h_window: boolean;
  last_inbound_at: string | null;
}

interface Props {
  conversation: Conversation;
  messages: Message[];
  centrifugoChannel: string;
}

export default function ConversationShow({ conversation, messages: initialMessages, centrifugoChannel }: Props) {
  const [messages, setMessages] = useState<Message[]>(initialMessages);
  const [composerText, setComposerText] = useState('');
  const [sending, setSending] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);

  // Auto-scroll quando nova mensagem chega
  useEffect(() => {
    if (scrollRef.current) {
      scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
    }
  }, [messages.length]);

  // Centrifugo wiring (skeleton — integração JS client final em PR posterior)
  // Quando @centrifugal/centrifuge for incluído como dep frontend:
  //
  //   const centrifuge = new Centrifuge(window.CENTRIFUGO_URL, { token: window.CENTRIFUGO_TOKEN });
  //   const sub = centrifuge.newSubscription(centrifugoChannel);
  //   sub.on('publication', (ctx) => {
  //     if (ctx.data.event === 'message_received') {
  //       setMessages((prev) => [...prev, ctx.data.message]);
  //     }
  //   });
  //   sub.subscribe();
  //   centrifuge.connect();
  //   return () => { sub.unsubscribe(); centrifuge.disconnect(); };
  useEffect(() => {
    // Console log pra debug enquanto integração Centrifugo final não acontece
    // (Lote 2f ou PR de integração separado)
    if (typeof window !== 'undefined') {
      console.info('[whatsapp.conversation] Centrifugo channel:', centrifugoChannel);
    }
  }, [centrifugoChannel]);

  function handleSend() {
    if (!composerText.trim() || sending) return;
    setSending(true);

    // Lote 2f: POST /whatsapp/conversations/{id}/send → enfileira SendWhatsappMessageJob
    // Por enquanto avisa que a feature precisa do controller send (Sprint 2)
    alert('Envio manual será habilitado no Lote 2f (controller send + Centrifugo). Por enquanto mensagens chegam só via webhooks inbound + auto-listeners (Repair etc).');
    setSending(false);
  }

  const canSendFreeform = conversation.within_24h_window;

  return (
    <div className="space-y-4 h-full flex flex-col">
      <PageHeader
        title={conversation.contact_name}
        subtitle={
          <>
            {conversation.customer_phone}
            {' · '}
            {conversation.within_24h_window ? (
              <Badge variant="outline" className="border-green-500 text-green-700">Janela 24h aberta</Badge>
            ) : (
              <Badge variant="outline" className="border-amber-500 text-amber-700">Janela 24h fechada — só template</Badge>
            )}
          </>
        }
        actions={
          <Link href={route('whatsapp.conversations.index')}>
            <Button variant="outline">← Inbox</Button>
          </Link>
        }
      />

      <Card className="flex-1 flex flex-col overflow-hidden">
        {/* Messages thread */}
        <div ref={scrollRef} className="flex-1 overflow-y-auto p-4 space-y-3 bg-muted/30">
          {messages.length === 0 ? (
            <div className="text-center text-muted-foreground py-8">
              Nenhuma mensagem nesta conversa ainda.
            </div>
          ) : (
            messages.map((m) => <MessageBubble key={m.id} message={m} />)
          )}
        </div>

        {/* Composer */}
        <div className="border-t p-3 space-y-2 bg-card">
          {!canSendFreeform && (
            <div className="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
              ⚠️ Janela 24h Meta fechada. Pra Z-API/Baileys pode mandar freeform; pra Meta Cloud
              precisa selecionar template HSM aprovado.
            </div>
          )}
          <Textarea
            value={composerText}
            onChange={(e) => setComposerText(e.target.value)}
            placeholder="Mensagem freeform..."
            rows={3}
          />
          <div className="flex justify-end gap-2">
            <Button variant="outline" disabled>Selecionar template</Button>
            <Button onClick={handleSend} disabled={!composerText.trim() || sending}>
              {sending ? 'Enviando...' : 'Enviar'}
            </Button>
          </div>
        </div>
      </Card>
    </div>
  );
}

ConversationShow.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;

function MessageBubble({ message }: { message: Message }) {
  const isOut = message.direction === 'outbound';
  const time = new Date(message.created_at).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });

  return (
    <div className={`flex ${isOut ? 'justify-end' : 'justify-start'}`}>
      <div
        className={`max-w-[70%] rounded-lg px-3 py-2 shadow-sm ${
          isOut
            ? 'bg-green-600 text-white rounded-br-sm'
            : 'bg-white text-foreground border rounded-bl-sm'
        }`}
      >
        {message.sender_kind === 'bot' && (
          <div className={`text-xs mb-1 ${isOut ? 'text-green-100' : 'text-purple-700'}`}>🤖 bot</div>
        )}
        <div className="whitespace-pre-wrap break-words">{message.body ?? <em className="opacity-70">[mídia]</em>}</div>
        <div className={`text-xs mt-1 flex items-center gap-2 ${isOut ? 'text-green-100' : 'text-muted-foreground'}`}>
          <span>{time}</span>
          {isOut && <StatusIcon status={message.status} />}
          {message.status === 'failed' && message.failed_reason && (
            <span className="text-red-200" title={message.failed_reason}>· falha</span>
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
    read: '✓✓ lido',
    failed: '⚠',
    received: '←',
  };
  return <span>{map[status] ?? status}</span>;
}
