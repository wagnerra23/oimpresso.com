// ConversationThreadV4.tsx — coluna central da Caixa Unificada V4.
//
// Replica visual `.om-thread-c` / `.om-thread-h` / `.om-msgs` / `.om-bub`
// do Cowork (inbox-page.css L420-560):
//   - header com avatar + nome + canal chip + handle + status online/lastTouch
//   - banner amarelo "em homologação" pra preview channels
//   - mensagens agrupadas por dia ("Hoje" / "Ontem" / data BR)
//   - bubble verde-pastel pra me, branca pra them, dashed amarelo pra nota interna
//   - composer no rodapé (ComposerV4 separado)
//
// Empty state quando thread=null (mantém UX Cockpit V2 do legacy Inbox).

import { useEffect, useMemo, useRef } from 'react';
import { Check, CheckCheck } from 'lucide-react';
import { cn } from '@/Lib/utils';
import {
  type CaixaUnifMessage,
  type CaixaUnifThread,
  type ChannelCatalogItem,
  initials,
  avatarHue,
  dayGroupLabel,
} from './helpers';
import ComposerV4 from './ComposerV4';

interface Props {
  thread: CaixaUnifThread;
  messages: CaixaUnifMessage[];
  channels: ChannelCatalogItem[];
  onResolve?: () => void;
}

export default function ConversationThreadV4({
  thread, messages, channels, onResolve,
}: Props) {
  const threadRef = useRef<HTMLDivElement>(null);

  const channel = useMemo(
    () => channels.find(c => c.id === thread.channel_type),
    [channels, thread.channel_type],
  );

  // Auto-scroll para baixo no mount + nova msg
  useEffect(() => {
    if (threadRef.current) {
      threadRef.current.scrollTop = threadRef.current.scrollHeight;
    }
  }, [thread.id, messages.length]);

  const isPreview = thread.preview_only;
  const isBlocked = thread.is_blocked;

  return (
    <main
      className="flex flex-col bg-muted/15 min-h-0 min-w-0"
      aria-label="Thread da conversa"
    >
      {/* Header */}
      <header className="flex items-center gap-3 bg-card border-b px-4 py-2.5">
        <div className="relative w-8 h-8 flex-shrink-0">
          <div
            className="w-8 h-8 rounded-full grid place-items-center text-white text-[10.5px] font-bold"
            style={{ background: `oklch(0.60 0.12 ${avatarHue(thread.contact_name || thread.customer_external_id)})` }}
            aria-hidden
          >
            {initials(thread.contact_name || thread.customer_external_id)}
          </div>
          {channel && (
            <span
              className="absolute -bottom-0.5 -right-0.5 w-3.5 h-3.5 rounded-full grid place-items-center text-white text-[8px] font-bold border-2 border-card"
              style={{ background: `oklch(0.62 0.14 ${channel.hue})` }}
              aria-hidden
            >
              {channel.glyph}
            </span>
          )}
        </div>
        <div className="min-w-0 flex-1">
          <b
            className="block text-[13.5px] font-semibold truncate"
            data-testid="caixa-unif-thread-name"
          >
            {thread.contact_name || thread.customer_external_id}
          </b>
          <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-0.5">
            {channel && (
              <span
                className="inline-block px-1.5 py-px text-[10.5px] border rounded-full bg-card"
                style={{
                  borderColor: `oklch(0.85 0.06 ${channel.hue})`,
                  color: `oklch(0.35 0.10 ${channel.hue})`,
                }}
              >
                {channel.short}{thread.channel_label ? ` · ${thread.channel_label}` : ''}
              </span>
            )}
            <span className="text-border">·</span>
            <span className="font-mono">{thread.customer_external_id}</span>
            {thread.channel_handle && (
              <>
                <span className="text-border">·</span>
                <span className="font-mono text-[10.5px]">{thread.channel_handle}</span>
              </>
            )}
          </div>
        </div>
        {!isPreview && !isBlocked && onResolve && (
          <button
            type="button"
            onClick={onResolve}
            className="ml-auto inline-flex items-center gap-1 px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors"
            data-testid="caixa-unif-resolve-btn"
          >
            <Check size={12} aria-hidden /> Resolver
          </button>
        )}
      </header>

      {/* Banner "em homologação" pra canal preview */}
      {isPreview && channel && (
        <div
          className="mx-4 mt-2.5 px-3.5 py-2.5 bg-amber-50 border border-amber-200 rounded-md text-[11.5px] text-amber-900"
          role="status"
          data-testid="caixa-unif-preview-banner"
        >
          <b className="block text-[12.5px] font-semibold text-amber-950">
            {channel.label} · em homologação.
          </b>
          <span>
            Conexão deste canal ainda não foi ativada. Esta conversa é uma prévia.{' '}
            <a
              href={route('atendimento.channels.index')}
              className="text-blue-700 underline hover:text-blue-900"
            >
              Ativar canal
            </a>
          </span>
        </div>
      )}

      {/* Banner contato bloqueado */}
      {isBlocked && (
        <div
          className="mx-4 mt-2.5 px-3.5 py-2.5 bg-red-50 border border-red-200 rounded-md text-[11.5px] text-red-900"
          role="status"
          data-testid="caixa-unif-blocked-banner"
        >
          <b className="block text-[12.5px] font-semibold text-red-950">
            Contato bloqueado.
          </b>
          <span>Mensagens deste número são descartadas. Desbloqueie pelo painel de Contexto.</span>
        </div>
      )}

      {/* Mensagens */}
      <div
        ref={threadRef}
        className="flex-1 overflow-auto p-4 flex flex-col gap-1"
        data-testid="caixa-unif-messages"
      >
        {messages.length === 0 ? (
          <div className="flex-1 grid place-items-center text-muted-foreground text-[13px]">
            Sem mensagens nesta conversa ainda.
          </div>
        ) : (
          messages.map((m, i) => {
            const showDay = i === 0 || dayGroupLabel(messages[i - 1]!.created_at) !== dayGroupLabel(m.created_at);
            return (
              <div key={m.id}>
                {showDay && (
                  <div className="text-center my-3">
                    <span className="bg-card border rounded-full px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-wider text-muted-foreground">
                      {dayGroupLabel(m.created_at)}
                    </span>
                  </div>
                )}
                {m.is_internal_note ? (
                  <div className="self-center w-[92%] max-w-[560px] mx-auto my-1 bg-amber-50 border border-amber-200 border-dashed rounded-lg px-3 py-2">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-[9.5px] uppercase tracking-wider font-semibold text-amber-800 bg-amber-100 px-1.5 py-px rounded-full">
                        Nota interna
                      </span>
                      <small className="text-[10px] font-mono text-amber-700">
                        {new Date(m.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })} ·
                        {m.sender_user_name ? ` ${m.sender_user_name} · ` : ' '}
                        só a equipe vê
                      </small>
                    </div>
                    <div className="text-[12.5px] text-amber-950 whitespace-pre-wrap leading-relaxed">
                      {m.body}
                    </div>
                  </div>
                ) : (
                  <div
                    className={cn(
                      'max-w-[75%] px-3 py-1.5 rounded-lg text-[12.5px] leading-snug whitespace-pre-wrap break-words flex flex-col',
                      m.direction === 'inbound'
                        ? 'self-start bg-card border rounded-bl-sm mr-auto'
                        : 'self-end bg-emerald-100 text-emerald-950 rounded-br-sm ml-auto',
                    )}
                    data-testid={`caixa-unif-msg-${m.id}`}
                  >
                    {m.direction === 'outbound' && m.sender_user_name && (
                      <small className="text-[9.5px] font-semibold text-emerald-700 mb-0.5">
                        {m.sender_user_name}
                      </small>
                    )}
                    <span>{m.body ?? <em className="text-muted-foreground">[mídia]</em>}</span>
                    <small className="text-[9.5px] opacity-60 mt-1 font-mono inline-flex items-center gap-1 self-end">
                      {new Date(m.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                      {m.direction === 'outbound' && (
                        m.status === 'read' ? (
                          <CheckCheck size={10} className="text-blue-600" aria-label="Lida" />
                        ) : m.status === 'delivered' ? (
                          <CheckCheck size={10} aria-label="Entregue" />
                        ) : m.status === 'sent' ? (
                          <Check size={10} aria-label="Enviada" />
                        ) : null
                      )}
                    </small>
                  </div>
                )}
              </div>
            );
          })
        )}
      </div>

      {/* Composer */}
      <ComposerV4
        conversationId={thread.id}
        isPreview={isPreview}
        isBlocked={isBlocked}
        channelShort={channel?.short ?? thread.channel_label ?? 'Canal'}
        channelLabel={thread.channel_label ?? ''}
      />
    </main>
  );
}
