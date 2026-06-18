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

import { useEffect, useMemo, useRef, useState } from 'react';
import { Check, CheckCheck, ClipboardCheck, FileDown, Presentation, Sparkles } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { Stack } from '@/Components/layout';
import {
  type CaixaUnifMessage,
  type CaixaUnifThread,
  type ChannelCatalogItem,
  initials,
  avatarHue,
  dayGroupLabel,
  slaState,
  slaWaitedMin,
  slaWaitedShort,
  SLA_META,
} from './helpers';
import ComposerV4 from './ComposerV4';
import InboxAiDialog, { type InboxAiMode } from './InboxAiDialog';
import InboxPresenterMode from './InboxPresenterMode';
import InboxTranscriptDialog from './InboxTranscriptDialog';
import CaptureFeedbackSheet, { type CaptureFeedbackInput } from '@/Pages/Whatsapp/_components/CaptureFeedbackSheet';
import MediaFullscreenModal from '@/Pages/Whatsapp/_components/MediaFullscreenModal';
import type { ReadyTemplate } from '@/Pages/Whatsapp/_components/helpers';
import MsgComments from './MsgComments';
import { useMsgComments } from './useMsgComments';

interface Props {
  thread: CaixaUnifThread;
  messages: CaixaUnifMessage[];
  channels: ChannelCatalogItem[];
  onResolve?: () => void;
  /** US-WA-303 — templates ready do business (picker do composer). */
  templates?: ReadyTemplate[];
}

export default function ConversationThreadV4({
  thread, messages, channels, onResolve, templates = [],
}: Props) {
  const threadRef = useRef<HTMLDivElement>(null);

  // Wagner 2026-05-27 — Voice of Customer in-app capture (ADR UI-0016).
  // Botão hover-revealed em mensagens inbound abre Sheet 760px pré-preenchido.
  const [feedbackSheetOpen, setFeedbackSheetOpen] = useState(false);
  const [feedbackInput, setFeedbackInput] = useState<CaptureFeedbackInput>({ literal: '' });

  const openCaptureFeedback = (m: CaixaUnifMessage) => {
    setFeedbackInput({
      literal: m.body || '',
      source_message_id: m.id,
      conversation_id: thread.id,
      contact_id: null,                       // tipo CaixaUnifThread não expõe contact_id (V4)
      contact_name: thread.contact_name ?? null,
      contact_phone: thread.customer_external_id ?? null,
      persona_slug: null,
      cliente_slug: null,
    });
    setFeedbackSheetOpen(true);
  };

  const channel = useMemo(
    () => channels.find(c => c.id === thread.channel_type),
    [channels, thread.channel_type],
  );

  // Polish V2 §4 — lightbox in-app (MediaFullscreenModal reusado) em vez de window.open
  const imageMessages = useMemo(
    () => messages.filter(m => m.type === 'image' && m.media_url),
    [messages],
  );
  const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

  // Polish V2 §7/§8 — transcript print-friendly + modo apresentação
  const [transcriptOpen, setTranscriptOpen] = useState(false);
  const [presenterOpen, setPresenterOpen] = useState(false);

  // PR-9 — IA na thread (Resumir / Perguntar)
  const [aiMode, setAiMode] = useState<InboxAiMode | null>(null);

  // Polish V2 §1 — SLA no header (direção da última msg não-nota vem das messages)
  const lastRealMsg = useMemo(
    () => [...messages].reverse().find(m => !m.is_internal_note),
    [messages],
  );
  const headerSla = slaState({
    last_message_direction: lastRealMsg?.direction ?? null,
    last_inbound_at: thread.last_inbound_at,
    queue: thread.queue,
  });

  // Auto-scroll para baixo no mount + nova msg
  useEffect(() => {
    if (threadRef.current) {
      threadRef.current.scrollTop = threadRef.current.scrollHeight;
    }
  }, [thread.id, messages.length]);

  const isPreview = thread.preview_only;
  const isBlocked = thread.is_blocked;

  // Notas internas por-mensagem (port inbox-cur) — localStorage per-user, "só equipe vê".
  const msgComments = useMsgComments(thread.id);

  return (
    // Fix scroll incident 2026-05-28: era <main> sem h-full → <main> aninhado dentro
    // do <main> do AppShellV2 (HTML5 inválido) + filho overflow-auto sem altura de
    // referência → conteúdo empurrava layout 375px além viewport → `.cockpit` ancestor
    // tem overflow:hidden → cortado sem scrollbar. Fix: <div> semântico + h-full.
    <div
      // Fundo da thread: tom verde-WA suave do protótipo (.om-thread-c L421
      // oklch(0.97 0.013 145)) no claro; neutro dark-aware no escuro (flipa via
      // .dark/[data-theme=dark] — ADR 0281). Antes era bg-muted/15 cinza neutro.
      className="flex flex-col bg-[oklch(0.97_0.013_145)] dark:bg-muted/15 min-h-0 min-w-0 h-full"
      aria-label="Thread da conversa"
      role="region"
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
              // Cowork .om-chip — padding 1px 7px, font-weight 500, border-radius 99px (§438)
              <span
                className="inline-block px-[7px] py-px text-[10.5px] font-medium border rounded-full bg-card"
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
        {/* Onda 2 — pill SLA no header: 4 estados (fresh/aging/late/expired) +
            dot animado (pulsa em aging/late/expired) + tempo esperando. */}
        {headerSla && (() => {
          const m = SLA_META[headerSla];
          const waited = slaWaitedMin({
            last_message_direction: lastRealMsg?.direction ?? null,
            last_inbound_at: thread.last_inbound_at,
          });
          return (
            <span
              className={cn('ml-auto inline-block font-mono text-[9.5px] font-bold px-2 py-px rounded-full border flex-shrink-0', m.pill)}
              title={`SLA ${thread.queue.sla} da fila ${thread.queue.label} — ${m.label}`}
              data-testid="caixa-unif-thread-sla"
            >
              <span className={cn('inline-block w-1.5 h-1.5 rounded-full align-middle mr-1', m.dot, m.pulse && 'animate-pulse')} aria-hidden />
              {m.label}{waited != null ? ` ${slaWaitedShort(waited)}` : ''}
            </span>
          );
        })()}
        {/* PR-9 — IA: Resumir / Perguntar (laravel/ai server-side, PII redigida) */}
        <button
          type="button"
          onClick={() => setAiMode('summarize')}
          className={cn(
            'inline-flex items-center gap-1 px-2 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors flex-shrink-0',
            headerSla === null && 'ml-auto',
          )}
          title="Resumir conversa com IA"
          data-testid="caixa-unif-thread-ai-summarize"
        >
          <Sparkles size={12} aria-hidden /> Resumir
        </button>
        <button
          type="button"
          onClick={() => setAiMode('ask')}
          className="inline-flex items-center gap-1 px-2 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors flex-shrink-0"
          title="Perguntar sobre a conversa (IA responde só com o transcript)"
          data-testid="caixa-unif-thread-ai-ask"
        >
          Perguntar
        </button>
        {/* Polish V2 §7/§8 — transcript print + modo apresentação */}
        <button
          type="button"
          onClick={() => setTranscriptOpen(true)}
          className="inline-flex items-center gap-1 px-2 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors flex-shrink-0"
          title="Transcript imprimível / PDF"
          data-testid="caixa-unif-thread-transcript"
        >
          <FileDown size={12} aria-hidden />
        </button>
        <button
          type="button"
          onClick={() => setPresenterOpen(true)}
          className="inline-flex items-center gap-1 px-2 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors flex-shrink-0"
          title="Modo apresentação (overlay limpo, Esc sai)"
          data-testid="caixa-unif-thread-presenter"
        >
          <Presentation size={12} aria-hidden />
        </button>
        {!isPreview && !isBlocked && onResolve && (
          <button
            type="button"
            onClick={onResolve}
            className="inline-flex items-center gap-1 px-2.5 py-1 text-[11.5px] font-medium text-muted-foreground hover:text-foreground hover:bg-muted rounded transition-colors flex-shrink-0"
            data-testid="caixa-unif-resolve-btn"
          >
            <Check size={12} aria-hidden /> Resolver
          </button>
        )}
      </header>

      {/* Banner "em homologação" pra canal preview (Cowork .om-preview-banner — tokens OKLCH §467) */}
      {isPreview && channel && (
        <div
          className="mx-4 mt-2.5 px-3.5 py-2.5 rounded-lg text-[11.5px] flex flex-col gap-0.5 bg-warning-soft border border-warning/25"
          role="status"
          data-testid="caixa-unif-preview-banner"
        >
          <b className="block text-[12.5px] font-semibold text-warning-fg">
            {channel.label} · em homologação.
          </b>
          <span>
            Conexão deste canal ainda não foi ativada. Esta conversa é uma prévia.{' '}
            <a
              href={route('atendimento.channels.index')}
              className="underline text-info"
            >
              Ativar canal
            </a>
          </span>
        </div>
      )}

      {/* Banner contato bloqueado */}
      {isBlocked && (
        <div
          className="mx-4 mt-2.5 px-3.5 py-2.5 bg-destructive-soft border border-destructive/20 rounded-md text-[11.5px] text-destructive-fg"
          role="status"
          data-testid="caixa-unif-blocked-banner"
        >
          <b className="block text-[12.5px] font-semibold text-destructive-fg">
            Contato bloqueado.
          </b>
          <span>Mensagens deste número são descartadas. Desbloqueie pelo painel de Contexto.</span>
        </div>
      )}

      {/* Mensagens */}
      <div
        ref={threadRef}
        className="flex-1 overflow-auto cw-scroll-thin p-4 flex flex-col gap-1"
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
              // Stack (flex-col · primitivo DS ADR 0253 · canon Cowork `.om-msg-wrap`):
              // sem pai flex o `self-start/self-end` + `ml-auto/mr-auto` da linha da
              // bolha vira no-op e as enviadas (outbound) encostavam à esquerda junto
              // das recebidas. Stack faz inbound→esquerda, outbound→direita.
              // gap={0}: espaçamento vem do `gap-1` do container + `my-3` do dia.
              <Stack key={m.id} gap={0} className="group/msg">
                {showDay && (
                  <div className="text-center my-3">
                    <span className="bg-card border rounded-full px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-[0.08em] text-muted-foreground">
                      {dayGroupLabel(m.created_at)}
                    </span>
                  </div>
                )}
                {m.is_internal_note ? (
                  // Cowork .om-internal — âmbar-pastel + dashed. Dark-aware via tokens
                  // semânticos warning-soft/warning-fg (flipam no .dark); o corpo usa
                  // text-foreground pra manter contraste nos 2 temas (erradicado o
                  // "miolo branco no escuro" — mesma disciplina Produtos/Oficina/Fin).
                  <div className="self-center w-[92%] max-w-[560px] mx-auto my-1 rounded-lg px-3 py-2 bg-warning-soft border border-dashed border-warning/40">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="text-[9.5px] uppercase tracking-[0.06em] font-semibold px-1.5 py-px rounded-full text-warning-fg bg-warning/20">
                        Nota interna
                      </span>
                      <small className="text-[10px] font-mono text-warning-fg/70">
                        {new Date(m.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })} ·
                        {m.sender_user_name ? ` ${m.sender_user_name} · ` : ' '}
                        só a equipe vê
                      </small>
                    </div>
                    <div className="text-[12.5px] whitespace-pre-wrap leading-[1.45] text-foreground">
                      {m.body}
                    </div>
                  </div>
                ) : (
                  <div
                    className={cn(
                      'group/bubble inline-flex items-start gap-1.5 max-w-[68%]',
                      m.direction === 'inbound' ? 'self-start mr-auto' : 'self-end ml-auto flex-row-reverse',
                    )}
                  >
                  {/* Wagner 2026-05-27 — botão capturar feedback (ADR UI-0016).
                      Hover-revealed em INBOUND quando body presente. */}
                  {m.direction === 'inbound' && (m.body || '').trim().length > 0 && (
                    <button
                      type="button"
                      onClick={() => openCaptureFeedback(m)}
                      className="opacity-0 group-hover/bubble:opacity-100 transition-opacity mt-1 inline-flex items-center justify-center h-6 w-6 rounded-md border border-border bg-card hover:bg-[var(--cw-accent-soft)] hover:border-[var(--cw-accent)] text-muted-foreground hover:text-[var(--cw-accent)] shrink-0"
                      title="Capturar feedback desta mensagem (Voice of Customer)"
                      aria-label="Capturar feedback"
                      data-testid="capture-feedback-btn"
                    >
                      <ClipboardCheck size={12} aria-hidden />
                    </button>
                  )}
                  <div
                    className={cn(
                      // Tokens Cowork canon (inbox-page.css §498): max-w 75%, padding 7px 11px, radius 10px, font 12.5px, line-height 1.45
                      'px-[11px] py-[7px] rounded-[10px] text-[12.5px] leading-[1.45] whitespace-pre-wrap break-words flex flex-col flex-1 min-w-0',
                      m.direction === 'inbound'
                        ? 'bg-card border border-border rounded-bl-[3px]'
                        : 'rounded-br-[3px]',
                    )}
                    style={
                      m.direction === 'outbound'
                        ? {
                            // Cowork .om-bub.me: oklch verde-pastel WA + texto verde-escuro
                            background: 'oklch(0.85 0.10 145)',
                            color: 'oklch(0.18 0.10 145)',
                          }
                        : undefined
                    }
                    data-testid={`caixa-unif-msg-${m.id}`}
                  >
                    {m.direction === 'outbound' && m.sender_user_name && (
                      <small
                        className="text-[9.5px] font-semibold mb-0.5"
                        style={{ color: 'oklch(0.35 0.10 145)' }}
                      >
                        {m.sender_user_name}
                      </small>
                    )}
                    {/* M6 fix 2026-05-28 — renderiza thumb/player quando media_url presente.
                        Antes UI mostrava body literal "[imagem]"/"[áudio]" pq backend
                        msgToUiArray não enviava media_url + frontend não checava. */}
                    {m.media_url && m.type === 'image' && (
                      <img
                        src={m.media_thumbnail_url || m.media_url}
                        alt={m.media_filename || 'imagem'}
                        // Polish V2 §4 — lightbox in-app em vez de aba nova
                        onClick={() => setLightboxIndex(imageMessages.findIndex(im => im.id === m.id))}
                        className="rounded-md max-w-full max-h-64 cursor-pointer object-cover mb-1"
                        loading="lazy"
                      />
                    )}
                    {m.media_url && m.type === 'video' && (
                      <video
                        src={m.media_url}
                        controls
                        preload="metadata"
                        className="rounded-md max-w-full max-h-64 mb-1"
                      />
                    )}
                    {m.media_url && m.type === 'audio' && (
                      <audio src={m.media_url} controls preload="metadata" className="max-w-full mb-1" />
                    )}
                    {m.media_url && (m.type === 'document' || m.type === 'pdf') && (
                      <a
                        href={m.media_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center gap-2 px-2 py-1 rounded border bg-card hover:bg-muted/50 text-xs mb-1"
                      >
                        📄 {m.media_filename || 'documento'}
                        {m.media_size_bytes && (
                          <span className="text-muted-foreground text-[10px]">
                            ({(m.media_size_bytes / 1024).toFixed(0)} KB)
                          </span>
                        )}
                      </a>
                    )}
                    {/* Body texto (caption ou só texto) */}
                    {(m.body && m.body !== '[imagem]' && m.body !== '[vídeo]' && m.body !== '[áudio]' && m.body !== '[documento]') && (
                      <span>{m.body}</span>
                    )}
                    {!m.media_url && !m.body && (
                      <em className="text-muted-foreground">[mídia]</em>
                    )}
                    <small className="text-[9.5px] opacity-60 mt-[3px] font-mono inline-flex items-center gap-1 self-start">
                      {new Date(m.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                      {m.direction === 'outbound' && (
                        m.status === 'read' ? (
                          // azul-tick WA — oklch fixo (passa R1, sem família -NNN);
                          // fica sobre a bolha verde-WA que não flipa, legível nos 2 temas.
                          <CheckCheck size={10} style={{ color: 'oklch(0.55 0.18 250)' }} aria-label="Lida" />
                        ) : m.status === 'delivered' ? (
                          <CheckCheck size={10} aria-label="Entregue" />
                        ) : m.status === 'sent' ? (
                          <Check size={10} aria-label="Enviada" />
                        ) : null
                      )}
                    </small>
                  </div>
                  </div>
                )}
                {!m.is_internal_note && (
                  <MsgComments
                    side={m.direction}
                    comments={msgComments.forMsg(i)}
                    onAdd={(t) => msgComments.add(i, t)}
                    onRemove={(idx) => msgComments.remove(i, idx)}
                  />
                )}
              </Stack>
            );
          })
        )}
      </div>

      {/* Wagner 2026-05-27 — Voice of Customer in-app capture (ADR UI-0016).
          Sheet 760px abre ao clicar botão "📋" em bubble inbound. */}
      <CaptureFeedbackSheet
        open={feedbackSheetOpen}
        onOpenChange={setFeedbackSheetOpen}
        input={feedbackInput}
      />

      {/* Polish V2 §4 — lightbox in-app (MediaFullscreenModal reusado US-WA-072) */}
      {lightboxIndex !== null && imageMessages.length > 0 && (
        <MediaFullscreenModal
          urls={imageMessages.map(m => m.media_url!)}
          filenames={imageMessages.map(m => m.media_filename ?? null)}
          currentIndex={Math.max(0, lightboxIndex)}
          onClose={() => setLightboxIndex(null)}
        />
      )}

      {/* Polish V2 §7 — transcript imprimível */}
      <InboxTranscriptDialog
        open={transcriptOpen}
        onOpenChange={setTranscriptOpen}
        thread={thread}
        messages={messages}
      />

      {/* Polish V2 §8 — modo apresentação */}
      <InboxPresenterMode
        open={presenterOpen}
        onClose={() => setPresenterOpen(false)}
        thread={thread}
        messages={messages}
      />

      {/* PR-9 — IA Resumir/Perguntar */}
      {aiMode !== null && (
        <InboxAiDialog
          open={aiMode !== null}
          onOpenChange={(o) => { if (!o) setAiMode(null); }}
          mode={aiMode}
          conversationId={thread.id}
        />
      )}

      {/* Composer */}
      <ComposerV4
        conversationId={thread.id}
        isPreview={isPreview}
        isBlocked={isBlocked}
        channelShort={channel?.short ?? thread.channel_label ?? 'Canal'}
        channelLabel={thread.channel_label ?? ''}
        channelType={thread.channel_type}
        templates={templates}
        contactName={thread.contact_name}
        contactPhone={thread.customer_external_id}
      />
    </div>
  );
}
