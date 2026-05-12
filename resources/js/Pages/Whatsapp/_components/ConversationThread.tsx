import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import { Centrifuge } from 'centrifuge';
import {
  ArrowLeft,
  ArrowDown,
  Bot,
  Check,
  CheckCheck,
  Hourglass,
  AlertTriangle,
  MessageCircle,
  Search,
  X,
  ChevronUp,
  ChevronDown,
  Ban,
  Lock,
} from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

import Avatar from './Avatar';
import TemplatePicker from './TemplatePicker';
import {
  groupByDay,
  type CentrifugoConfig,
  type Message,
  type ReadyTemplate,
  type ThreadConversation,
} from './helpers';

interface Props {
  conversation: ThreadConversation;
  messages: Message[];
  centrifugoConfig: CentrifugoConfig | null;
  /** Templates ready (LOCAL ou Meta APPROVED) pra picker do composer. */
  templates: ReadyTemplate[];
  /** Reload partial: indica quais props recarregar quando vem mensagem nova. */
  reloadOnly: string[];
  /** Botão "← inbox" só aparece em rota permalink (Show). */
  backHref?: string;
  /** Route name pro POST de envio. Default `atendimento.inbox.send`
   * pós-US-WA-091 (rotas legacy `/whatsapp/conversations*` removidas). */
  sendRouteName?: string;
}

export default function ConversationThread({
  conversation, messages, centrifugoConfig, templates, reloadOnly, backHref,
  sendRouteName = 'atendimento.inbox.send',
}: Props) {
  const [composerText, setComposerText] = useState('');
  const [sending, setSending] = useState(false);
  const [liveConnected, setLiveConnected] = useState(false);
  const [showScrollBottom, setShowScrollBottom] = useState(false);
  const [templatePickerOpen, setTemplatePickerOpen] = useState(false);
  // US-WA-071 (ADR 0142): toggle Reply / Internal Note (Chatwoot pattern).
  // 'reply' = vai pro WhatsApp · 'note' = só atendentes do business (fundo amarelo).
  // Persistido em localStorage por conversation_id pra não perder modo ao trocar.
  const [composerKind, setComposerKind] = useState<'reply' | 'note'>(() => {
    if (typeof window === 'undefined') return 'reply';
    return (localStorage.getItem(`oimpresso.whatsapp.composer_kind.${conversation.id}`) as 'reply' | 'note') ?? 'reply';
  });
  // US-WA-062: busca local na conversa (sem backend — filtra `messages`
  // client-side, mantém ordem cronológica, highlight visual <mark>).
  const [searchOpen, setSearchOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [currentMatchIdx, setCurrentMatchIdx] = useState(0);
  const scrollRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // Computa índices das msgs que match — usado pra navegação ↑/↓ + counter.
  const matchIndices = useMemo(() => {
    if (!searchQuery.trim()) return [] as number[];
    const q = searchQuery.toLowerCase();
    return messages
      .map((m, idx) => (m.body && m.body.toLowerCase().includes(q) ? idx : -1))
      .filter((idx) => idx !== -1);
  }, [messages, searchQuery]);

  // Reset cursor quando query muda
  useEffect(() => {
    setCurrentMatchIdx(0);
  }, [searchQuery]);

  // Ctrl+F atalho global dentro da thread foca search input.
  // Ctrl+/ (Cmd+/) toggle composer Reply ↔ Internal Note (US-WA-071 ADR 0142).
  useEffect(() => {
    function handler(e: KeyboardEvent) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        setSearchOpen(true);
        setTimeout(() => searchInputRef.current?.focus(), 50);
      }
      if ((e.ctrlKey || e.metaKey) && e.key === '/') {
        e.preventDefault();
        setComposerKind((k) => (k === 'reply' ? 'note' : 'reply'));
      }
      if (e.key === 'Escape' && searchOpen) {
        setSearchOpen(false);
        setSearchQuery('');
      }
    }
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [searchOpen]);

  // Persiste composer kind por conversation_id
  useEffect(() => {
    if (typeof window === 'undefined') return;
    localStorage.setItem(`oimpresso.whatsapp.composer_kind.${conversation.id}`, composerKind);
  }, [composerKind, conversation.id]);

  // Scroll automatico pro match atual
  useEffect(() => {
    if (matchIndices.length === 0) return;
    const targetMsgIdx = matchIndices[currentMatchIdx];
    if (targetMsgIdx === undefined) return;
    const targetMsg = messages[targetMsgIdx];
    if (!targetMsg) return;
    const el = document.querySelector(`[data-msg-id="${targetMsg.id}"]`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }, [currentMatchIdx, matchIndices, messages]);

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

  // Polling fallback 5s quando Centrifugo offline ou não conectado.
  // Mesmo padrão NfeBrasil — Hostinger HTTP-only (ADR 0058+0062). Quando
  // Centrifugo CT 100 for exposto via Traefik público, liveConnected vira
  // true e este polling para automaticamente.
  useEffect(() => {
    if (liveConnected) return;
    const interval = setInterval(() => {
      if (document.visibilityState !== 'visible') return; // pausa em aba inativa
      router.reload({ only: reloadOnly });
    }, 5000);
    return () => clearInterval(interval);
  }, [liveConnected, conversation.id, reloadOnly.join(',')]);

  function handleSend() {
    // US-WA-085: optimistic UI — clear composer IMEDIATAMENTE (sem esperar
    // resposta do daemon que pode levar 2-5s no Hostinger). Não bloqueia
    // sends subsequentes (atendente pode digitar próxima msg enquanto
    // anterior está em flight).
    //
    // Como o InboxController::send PERSISTE Message com status='queued'
    // ANTES de chamar daemon, o polling 5s / Centrifugo WSS traz a msg
    // pra thread quase instantâneo com hourglass icon. Daemon confirma
    // depois → status='sent' → MessageObserver updated → polling traz ✓.
    // Daemon falha → status='failed' → bubble fica vermelha com alerta.
    if (!composerText.trim()) return;
    const textToSend = composerText;
    const isInternalNote = composerKind === 'note';
    setComposerText('');           // clear immediately
    setSending(true);              // loader visual (sem bloquear)
    router.post(
      route(sendRouteName, conversation.id),
      { kind: 'freeform', body: textToSend, is_internal_note: isInternalNote },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          router.reload({ only: reloadOnly });
        },
        onError: () => {
          // Backend validação falhou — restaurar texto pro atendente
          // reenviar sem retipar. Daemon error já fica como bubble failed
          // via polling, não restaura nesse caso.
          setComposerText(textToSend);
        },
        onFinish: () => setSending(false),
      },
    );
  }

  function handleSendTemplate(payload: {
    template_name: string;
    template_locale: string;
    template_params: string[];
  }) {
    if (sending) return;
    setSending(true);
    router.post(
      route(sendRouteName, conversation.id),
      { kind: 'template', ...payload },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setTemplatePickerOpen(false);
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

  // US-WA-066: conv bloqueada = composer disabled (não dá pra enviar pra
  // contato bloqueado). Janela 24h só importa se NÃO está bloqueado.
  // US-WA-071: nota interna BYPASSA bloqueio + janela 24h (não vai pro driver).
  const isBlocked = !!conversation.is_blocked;
  const isNote = composerKind === 'note';
  const canSendFreeform = isNote || (conversation.within_24h_window && !isBlocked);
  const composerDisabled = !isNote && isBlocked;
  const groupedMessages = useMemo(() => groupByDay(messages), [messages]);

  // Quando contact_name é igual ao phone (contato sem nome cadastrado),
  // não duplica o número visualmente (Wagner UX polish round 2).
  const phoneIsName = conversation.contact_name === conversation.customer_phone;

  return (
    <Card className="flex flex-col overflow-hidden h-full min-w-0 py-0 gap-0">
      {/* Header sticky */}
      <div className="border-b px-3 py-2 flex items-center justify-between gap-3 bg-card shrink-0">
        <div className="flex items-center gap-2.5 min-w-0">
          {backHref && (
            <a href={backHref} className="shrink-0">
              <Button variant="ghost" size="sm" className="px-2 h-8" aria-label="Voltar para a inbox">
                <ArrowLeft size={16} aria-hidden />
              </Button>
            </a>
          )}
          <Avatar name={conversation.contact_name} size="sm" />
          <div className="min-w-0">
            <div className="flex items-center gap-1.5 min-w-0">
              <h2 className="font-semibold truncate text-sm">{conversation.contact_name}</h2>
              <StatusDot status={conversation.status} />
            </div>
            <div className="text-xs text-muted-foreground truncate flex items-center gap-1.5">
              {!phoneIsName && <span>{conversation.customer_phone}</span>}
              {centrifugoConfig && (
                <>
                  <span className="text-border">·</span>
                  {liveConnected ? (
                    <span className="text-emerald-600 dark:text-emerald-400 inline-flex items-center gap-1">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden />
                      live
                    </span>
                  ) : (
                    <span className="text-muted-foreground inline-flex items-center gap-1">
                      <span className="w-1.5 h-1.5 rounded-full bg-muted-foreground/50" aria-hidden />
                      conectando…
                    </span>
                  )}
                </>
              )}
            </div>
          </div>
        </div>
        <div className="shrink-0 flex items-center gap-2">
          {/* US-WA-062: ícone search abre barra local */}
          <Button
            variant="ghost"
            size="sm"
            className="h-7 w-7 p-0"
            onClick={() => {
              setSearchOpen((v) => !v);
              if (!searchOpen) setTimeout(() => searchInputRef.current?.focus(), 50);
            }}
            title={searchOpen ? 'Fechar busca (Esc)' : 'Pesquisar nesta conversa (Ctrl+F)'}
            aria-label="Pesquisar na conversa"
          >
            <Search size={14} aria-hidden />
          </Button>
          {/* US-WA-066: badge BLOQUEADO em destaque vermelho — atendente vê
              de longe que essa conv não recebe inbound novo. */}
          {conversation.is_blocked && (
            <Badge
              variant="outline"
              className="border-red-500 text-red-700 dark:text-red-400 dark:border-red-700 bg-red-50 dark:bg-red-950/30 text-[10px] inline-flex items-center gap-0.5"
            >
              <Ban size={10} aria-hidden />
              BLOQUEADO
            </Badge>
          )}
          {conversation.within_24h_window ? (
            <Badge
              variant="outline"
              className="border-emerald-500 text-emerald-700 dark:text-emerald-400 dark:border-emerald-700 bg-emerald-50 dark:bg-emerald-950/30 text-[10px]"
            >
              24h aberta
            </Badge>
          ) : (
            <Badge
              variant="outline"
              className="border-amber-500 text-amber-700 dark:text-amber-400 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/30 text-[10px] inline-flex items-center gap-0.5"
            >
              <AlertTriangle size={10} aria-hidden />
              24h fechada
            </Badge>
          )}
        </div>
      </div>

      {/* US-WA-062: search bar inline expansível abaixo do header */}
      {searchOpen && (
        <div className="border-b px-3 py-1.5 flex items-center gap-2 bg-muted/30 shrink-0">
          <Search size={12} className="text-muted-foreground shrink-0" aria-hidden />
          <input
            ref={searchInputRef}
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Buscar nesta conversa…"
            className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                if (matchIndices.length === 0) return;
                if (e.shiftKey) {
                  setCurrentMatchIdx((i) => (i - 1 + matchIndices.length) % matchIndices.length);
                } else {
                  setCurrentMatchIdx((i) => (i + 1) % matchIndices.length);
                }
              }
            }}
          />
          <span className="text-xs text-muted-foreground tabular-nums shrink-0">
            {matchIndices.length === 0 && searchQuery
              ? '0 de 0'
              : matchIndices.length > 0
              ? `${currentMatchIdx + 1} de ${matchIndices.length}`
              : ''}
          </span>
          <Button
            variant="ghost"
            size="sm"
            className="h-6 w-6 p-0"
            disabled={matchIndices.length === 0}
            onClick={() => setCurrentMatchIdx((i) => (i - 1 + matchIndices.length) % matchIndices.length)}
            title="Match anterior (Shift+Enter)"
            aria-label="Match anterior"
          >
            <ChevronUp size={12} aria-hidden />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="h-6 w-6 p-0"
            disabled={matchIndices.length === 0}
            onClick={() => setCurrentMatchIdx((i) => (i + 1) % matchIndices.length)}
            title="Próximo match (Enter)"
            aria-label="Próximo match"
          >
            <ChevronDown size={12} aria-hidden />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            className="h-6 w-6 p-0"
            onClick={() => {
              setSearchOpen(false);
              setSearchQuery('');
            }}
            title="Fechar (Esc)"
            aria-label="Fechar busca"
          >
            <X size={12} aria-hidden />
          </Button>
        </div>
      )}

      {/* Thread */}
      <div className="flex-1 relative min-h-0">
        <div
          ref={scrollRef}
          onScroll={handleScroll}
          className="absolute inset-0 overflow-y-auto p-4 space-y-2"
          style={{
            background:
              'linear-gradient(180deg, var(--thread-bg-from, transparent) 0%, var(--thread-bg-to, transparent) 100%)',
          }}
        >
          {messages.length === 0 ? (
            <div className="flex flex-col items-center justify-center h-full text-center text-muted-foreground gap-2">
              <MessageCircle size={48} className="opacity-30" aria-hidden />
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
                    highlight={searchQuery}
                  />
                ))}
              </div>
            ))
          )}
        </div>

        {/* Botão scroll bottom */}
        {showScrollBottom && (
          <Button
            type="button"
            variant="outline"
            size="icon"
            onClick={scrollToBottom}
            className="absolute bottom-3 right-3 shadow-md rounded-full h-9 w-9"
            aria-label="Rolar pra última mensagem"
          >
            <ArrowDown size={16} aria-hidden />
          </Button>
        )}
      </div>

      {/* Composer */}
      <div className={`border-t p-2.5 space-y-2 shrink-0 transition-colors ${
        isNote ? 'bg-amber-50 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800' : 'bg-card'
      }`}>
        {/* US-WA-071 (ADR 0142): toggle Reply / Nota interna estilo Chatwoot.
            Nota interna NÃO vai pro WhatsApp — fica visível só pros atendentes
            do business (Tier 0 gate no Controller). */}
        <div className="flex items-center gap-1 -mt-1">
          <button
            type="button"
            onClick={() => setComposerKind('reply')}
            className={`text-[11px] px-2.5 py-1 rounded transition-colors ${
              composerKind === 'reply'
                ? 'bg-card text-foreground font-medium shadow-sm border border-border'
                : 'text-muted-foreground hover:bg-muted/50'
            }`}
            title="Resposta — vai pro WhatsApp do cliente"
            data-testid="composer-toggle-reply"
          >
            Resposta
          </button>
          <button
            type="button"
            onClick={() => setComposerKind('note')}
            className={`text-[11px] px-2.5 py-1 rounded transition-colors inline-flex items-center gap-1 ${
              composerKind === 'note'
                ? 'bg-amber-100 dark:bg-amber-900/50 text-amber-900 dark:text-amber-100 font-medium shadow-sm border border-amber-400 dark:border-amber-600'
                : 'text-muted-foreground hover:bg-muted/50'
            }`}
            title="Nota interna — só atendentes veem (Ctrl+/ toggle)"
            data-testid="composer-toggle-note"
          >
            <Lock size={10} aria-hidden />
            Nota interna
          </button>
          <span className="text-[10px] text-muted-foreground ml-auto">
            <kbd className="px-1 py-0.5 bg-muted/50 rounded text-[9px] font-mono">Ctrl+/</kbd> toggle
          </span>
        </div>

        {isNote && (
          <div className="text-xs text-amber-900 dark:text-amber-200 bg-amber-100/60 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 rounded px-2.5 py-1.5 flex items-start gap-1.5">
            <Lock size={12} className="mt-0.5 shrink-0" aria-hidden />
            <span>Nota interna — NÃO vai pro WhatsApp. Só atendentes do business veem.</span>
          </div>
        )}
        {!isNote && isBlocked && (
          <div className="text-xs text-red-800 dark:text-red-300 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-700 rounded px-2.5 py-1.5 flex items-start gap-1.5">
            <Ban size={12} className="mt-0.5 shrink-0" aria-hidden />
            <span>Contato bloqueado. Inbound novo é descartado e envio está desabilitado. Desbloqueie no painel direito para reabrir.</span>
          </div>
        )}
        {!isNote && !isBlocked && !canSendFreeform && (
          <div className="text-xs text-amber-800 dark:text-amber-300 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-700 rounded px-2.5 py-1.5 flex items-start gap-1.5">
            <AlertTriangle size={12} className="mt-0.5 shrink-0" aria-hidden />
            <span>Janela 24h Meta fechada. Z-API/Baileys mandam freeform; Meta Cloud exige template HSM.</span>
          </div>
        )}
        <Textarea
          value={composerText}
          onChange={(e) => setComposerText(e.target.value)}
          placeholder={composerDisabled
            ? 'Contato bloqueado — envio desabilitado'
            : isNote
              ? 'Nota interna…  (visível só pros atendentes — Enter envia)'
              : 'Mensagem freeform…  (Enter envia · Shift+Enter quebra linha)'}
          rows={2}
          className="resize-none"
          disabled={composerDisabled}
          onKeyDown={(e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              handleSend();
            }
          }}
          aria-label="Compositor de mensagem"
          data-testid="composer-textarea"
        />
        <div className="flex justify-between items-center gap-2">
          <span className="text-xs text-muted-foreground tabular-nums">
            {composerText.length} {composerText.length === 1 ? 'caractere' : 'caracteres'}
          </span>
          <div className="flex gap-1.5">
            <Button
              variant="outline"
              size="sm"
              onClick={() => setTemplatePickerOpen(true)}
              disabled={templates.length === 0 || isBlocked || isNote}
              className="h-8"
              title={isNote
                ? 'Templates HSM não fazem sentido em nota interna'
                : isBlocked
                  ? 'Contato bloqueado — envio desabilitado'
                  : templates.length === 0
                    ? 'Nenhum template ready — cadastre em /whatsapp/templates'
                    : 'Enviar template HSM/LOCAL (única opção quando janela 24h Meta fechada)'}
            >
              Template
            </Button>
            {/* US-WA-085: botão NÃO desabilita em `sending` — atendente pode
                disparar próxima msg sem esperar daemon confirmar a anterior.
                Feedback visual vem do bubble com hourglass icon (status='queued'),
                que aparece via polling/Centrifugo <1s após o backend persistir.
                US-WA-066: disable em blocked (envio proibido), exceto nota interna. */}
            <Button
              size="sm"
              onClick={handleSend}
              disabled={!composerText.trim() || composerDisabled}
              className={`h-8 ${isNote ? 'bg-amber-600 hover:bg-amber-700' : ''}`}
              data-testid="composer-send"
            >
              {isNote ? 'Salvar nota' : 'Enviar'}
            </Button>
          </div>
        </div>
      </div>

      <TemplatePicker
        open={templatePickerOpen}
        onOpenChange={setTemplatePickerOpen}
        templates={templates}
        onSend={handleSendTemplate}
        sending={sending}
      />
    </Card>
  );
}

/**
 * US-WA-062: renderiza body da msg com matches da `query` envoltos em <mark>.
 * Case-insensitive, escape de regex pra query do user (sem ReDoS).
 * Exportado pra Pest test indireto via TS — funcção pura testável.
 */
export function HighlightedBody({ body, query }: { body: string; query: string }) {
  const q = query.trim();
  if (!q) return <>{body}</>;
  // Escape regex chars do user input antes de compilar — evita ReDoS
  const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const parts = body.split(new RegExp(`(${escaped})`, 'gi'));
  return (
    <>
      {parts.map((part, i) =>
        part.toLowerCase() === q.toLowerCase()
          ? <mark key={i} className="bg-yellow-300 dark:bg-yellow-600 text-black dark:text-yellow-100 rounded-sm px-0.5">{part}</mark>
          : <Fragment key={i}>{part}</Fragment>
      )}
    </>
  );
}

function MessageBubble({ message, showTail, highlight = '' }: {
  message: Message;
  showTail: boolean;
  /** US-WA-062: query da busca local — body matches recebem <mark> highlight */
  highlight?: string;
}) {
  const isOut = message.direction === 'outbound';
  const isNote = !!message.is_internal_note; // US-WA-071
  const time = new Date(message.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

  // US-WA-071: nota interna ocupa toda a largura, fundo amarelo, padding maior —
  // estilo "post-it" centralizado, distinto das bubbles de chat (Chatwoot pattern).
  if (isNote) {
    return (
      <div className="flex justify-center my-1" data-msg-id={message.id} data-internal-note="true">
        <div className="max-w-[90%] bg-amber-100 dark:bg-amber-900/40 border border-amber-300 dark:border-amber-700 rounded-md px-3 py-2 shadow-sm">
          <div className="text-[10px] font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300 mb-1 inline-flex items-center gap-1">
            <Lock size={10} aria-hidden />
            Nota interna
            {message.sender_user_name && (
              <span className="font-normal normal-case ml-1 text-amber-700 dark:text-amber-400">
                · {message.sender_user_name}
              </span>
            )}
          </div>
          <div className="whitespace-pre-wrap break-words text-sm leading-snug text-amber-950 dark:text-amber-100">
            {message.body
              ? <HighlightedBody body={message.body} query={highlight} />
              : <em className="opacity-70">[vazio]</em>}
          </div>
          <div className="text-[10px] mt-1 text-amber-700/70 dark:text-amber-400/70">
            {time}
          </div>
        </div>
      </div>
    );
  }

  const corner = isOut
    ? showTail ? 'rounded-2xl rounded-br-md' : 'rounded-2xl rounded-r-md'
    : showTail ? 'rounded-2xl rounded-bl-md' : 'rounded-2xl rounded-l-md';

  // Bubble out usa --bubble-me (consume Tweaks accentHue do AppShellV2 — ADR 0039 §5).
  // Bubble in usa --bubble-them (segue dark mode do shell).
  const bubbleStyle = isOut
    ? { background: 'var(--bubble-me)', color: 'var(--bubble-me-fg)' }
    : { background: 'var(--bubble-them)', color: 'var(--bubble-them-fg)' };

  return (
    <div className={`flex ${isOut ? 'justify-end' : 'justify-start'}`} data-msg-id={message.id}>
      <div className={`max-w-[75%] px-3 py-1.5 shadow-sm border border-transparent ${corner}`} style={bubbleStyle}>
        {message.sender_kind === 'bot' && (
          <div className="text-[10px] font-semibold mb-0.5 uppercase tracking-wide opacity-80 inline-flex items-center gap-1">
            <Bot size={10} aria-hidden />
            Bot
          </div>
        )}
        {/* US-WA-077: identifica QUAL atendente do time enviou a msg outbound
            quando vários compartilham o mesmo chip. Só renderiza quando:
            - sender_kind='human' (não bot, não system)
            - sender_user_name set (msg enviada via web UI, não chip externo)
            - isOut (bubble do nosso lado — não faz sentido em inbound) */}
        {isOut && message.sender_kind === 'human' && message.sender_user_name && (
          <div className="text-[10px] font-semibold mb-0.5 opacity-80">
            {message.sender_user_name}
          </div>
        )}
        <div className="whitespace-pre-wrap break-words text-sm leading-snug">
          {message.body
            ? <HighlightedBody body={message.body} query={highlight} />
            : <em className="opacity-70">[mídia]</em>}
        </div>
        {/* US-WA-083: removido `opacity-80` do row pra NÃO atenuar o ícone
            de status (CSS opacity é multiplicativa no stacking context, mata
            o azul vivo do ✓✓ "lida"). Atenuação só no `<span>` do tempo. */}
        <div className="text-[10px] mt-0.5 flex items-center justify-end gap-1">
          <span className="opacity-70">{time}</span>
          {isOut && <StatusIcon status={message.status} />}
          {message.status === 'failed' && message.failed_reason && (
            <span title={message.failed_reason} className="inline-flex items-center gap-0.5 opacity-80">
              <AlertTriangle size={10} aria-hidden />
              falha
            </span>
          )}
        </div>
      </div>
    </div>
  );
}

function StatusIcon({ status }: { status: string }) {
  // US-WA-083: ícones com opacity-70 padrão (segue WhatsApp Web pattern),
  // exceto `read` que precisa ser bem visível em azul WhatsApp pra
  // atendente saber claramente que cliente leu. strokeWidth=2.5 + cor
  // cyan-500 = leitura instantânea em monitor claro/escuro.
  switch (status) {
    case 'queued':    return <Hourglass size={11} className="opacity-70" aria-label="enfileirada" />;
    case 'sent':      return <Check size={11} className="opacity-70" aria-label="enviada" />;
    case 'delivered': return <CheckCheck size={11} className="opacity-70" aria-label="entregue" />;
    case 'read':      return <CheckCheck size={13} strokeWidth={2.5} className="text-cyan-500 dark:text-cyan-400" aria-label="lida" />;
    case 'failed':    return <AlertTriangle size={11} className="text-red-500 dark:text-red-400" aria-label="falhou" />;
    default:          return <span className="text-[9px] opacity-70">{status}</span>;
  }
}

function StatusDot({ status }: { status: string }) {
  // Cores fixas semânticas (R-DS-002 exceção).
  const color: Record<string, string> = {
    open: 'bg-blue-500',
    awaiting_human: 'bg-amber-500',
    resolved: 'bg-emerald-500',
    archived: 'bg-slate-400',
  };
  return (
    <span
      className={`inline-block w-2 h-2 rounded-full shrink-0 ${color[status] ?? color.open}`}
      aria-label={status}
    />
  );
}
