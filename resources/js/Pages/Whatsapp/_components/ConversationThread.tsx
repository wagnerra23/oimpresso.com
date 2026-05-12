import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
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
  Paperclip,
  FileText,
  Download,
  Loader2,
  Music,
  Image as ImageIcon,
  Video as VideoIcon,
  File as FileIcon,
  Zap,
} from 'lucide-react';

import { Card } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

import Avatar from './Avatar';
import TemplatePicker from './TemplatePicker';
import MicRecorder from './MicRecorder';
import {
  groupByDay,
  isLikelyLid,
  type CentrifugoConfig,
  type Message,
  type ReadyTemplate,
  type ThreadConversation,
} from './helpers';

/**
 * US-WA-048: shape do item retornado por GET `/atendimento/macros/list`.
 * Backend (MacrosController::list) limita a 50 ordenados por used_count.
 */
interface MacroEntry {
  id: number;
  label: string;
  shortcut: string | null;
  body: string;
  used_count: number;
  body_preview: string;
}

/**
 * US-WA-074 (ADR 0142): payload de flash slash command vindo do Controller.
 * Backend popula em `session('slash')` ao executar `/lembrar`, `/corrigir`,
 * `/lembrete`, `/config` em nota interna. Frontend lê via `usePage().props.flash.slash`.
 */
interface SlashFlashPayload {
  kind: 'success' | 'error';
  badge?: string | null;
  link_url?: string | null;
  error_message?: string | null;
  command: string;
  message_id: number;
}

/**
 * US-WA-074: comandos slash conhecidos pelo SlashCommandParser backend.
 * Mantém em paralelo a `SlashCommandParser::knownCommands()` PHP.
 */
const SLASH_COMMANDS: Array<{ name: string; description: string }> = [
  { name: 'lembrar',  description: 'Grava fato sobre o contato pra Jana lembrar' },
  { name: 'corrigir', description: 'Marca resposta do bot como errada (treino)' },
  { name: 'lembrete', description: 'Cria lembrete agendado' },
  { name: 'config',   description: 'Toggle bot per-contato (bot=on|off)' },
];

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
  // US-WA-072 — upload de mídia. `uploadingMedia` mostra spinner inline
  // no botão Paperclip enquanto o POST multipart está em flight. Não bloqueia
  // composer texto (atendente pode digitar próxima msg em paralelo).
  const [uploadingMedia, setUploadingMedia] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
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
  // US-WA-074 (ADR 0142): autocomplete `/` slash commands em nota interna.
  // Aparece quando atendente digita `/` no início e modo é `note`.
  const [showSlashAutocomplete, setShowSlashAutocomplete] = useState(false);
  // US-WA-048: macros dropdown — fetch lazy ao abrir, click apply dispara
  // POST `atendimento.inbox.apply_macro` + reload conv. Tier 0 garantido
  // no backend (Macro.business_id global scope).
  const [macrosOpen, setMacrosOpen] = useState(false);
  const [macrosList, setMacrosList] = useState<MacroEntry[] | null>(null);
  const [macrosLoading, setMacrosLoading] = useState(false);
  const [macrosFilter, setMacrosFilter] = useState('');
  const [applyingMacroId, setApplyingMacroId] = useState<number | null>(null);
  const scrollRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  // US-WA-074: lê flash.slash do backend pra renderizar badge ao lado da
  // bubble da nota recém-criada. Persistimos local pra sobreviver a partial
  // reloads que limpam props.flash mas mantemos a mensagem no thread.
  // Shape: { kind, badge?, link_url?, error_message?, command, message_id }
  const page = usePage<{ flash?: { slash?: SlashFlashPayload | null } }>();
  const flashSlash = page.props.flash?.slash ?? null;
  const [slashBadges, setSlashBadges] = useState<Record<number, SlashFlashPayload>>({});
  useEffect(() => {
    if (flashSlash && typeof flashSlash.message_id === 'number') {
      setSlashBadges((prev) => ({ ...prev, [flashSlash.message_id]: flashSlash }));
    }
  }, [flashSlash?.message_id, flashSlash?.kind]);

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

  /**
   * US-WA-072 — upload de mídia outbound. Aceita 1+ arquivos do file input
   * OU drag-and-drop. Cada upload é POST multipart separado pra
   * `/atendimento/inbox/{id}/send-media`. Tier 0 enforce no backend
   * (MIME whitelist + size max). Nota interna NÃO permite mídia (combo 422).
   */
  function handleSendMedia(files: FileList | null) {
    if (!files || files.length === 0) return;
    if (composerKind === 'note') {
      alert('Notas internas não suportam mídia nesta fase.');
      return;
    }
    setUploadingMedia(true);

    // Processa 1 arquivo por POST — pra cada arquivo o backend cria Message
    // separada com type derivado do MIME (image/audio/video/document).
    const arr = Array.from(files);
    let remaining = arr.length;

    arr.forEach((file) => {
      const formData = new FormData();
      formData.append('file', file);
      if (composerText.trim()) {
        formData.append('caption', composerText);
      }
      router.post(
        route('atendimento.inbox.send_media', conversation.id),
        formData,
        {
          forceFormData: true,
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            router.reload({ only: reloadOnly });
          },
          onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            if (firstErr) alert(`Falha no upload: ${firstErr}`);
          },
          onFinish: () => {
            remaining -= 1;
            if (remaining === 0) {
              setUploadingMedia(false);
              setComposerText('');
              if (fileInputRef.current) fileInputRef.current.value = '';
            }
          },
        },
      );
    });
  }

  /**
   * Hotfix B3 (2026-05-12) — envio de áudio gravado via MicRecorder.
   * Mesma rota do `send_media` (US-WA-072), arquivo nomeado `voice.ogg`
   * com MIME inferido do MediaRecorder. Tier 0 (ADR 0142): MicRecorder
   * já vem disabled em modo nota — defense-in-depth aqui rejeitando.
   */
  function handleSendVoice(blob: Blob, _durationS: number): Promise<void> {
    if (composerKind === 'note') {
      return Promise.reject(new Error('Notas internas não suportam áudio.'));
    }
    return new Promise<void>((resolve, reject) => {
      // Extensão default por MIME: ogg pra opus, webm fallback
      const isOgg = blob.type.includes('ogg');
      const filename = isOgg ? 'voice.ogg' : 'voice.webm';
      // Normaliza MIME pra match com Message::MEDIA_MIME_WHITELIST
      // (backend aceita audio/ogg e audio/webm pode cair em audio/mp4 mapping;
      // se o browser deu webm, força audio/ogg fallback é arriscado — manter raw).
      const file = new File([blob], filename, { type: blob.type || 'audio/ogg' });

      const formData = new FormData();
      formData.append('file', file);
      // Caption opcional — se atendente digitou texto antes de gravar
      if (composerText.trim()) {
        formData.append('caption', composerText);
      }

      router.post(
        route('atendimento.inbox.send_media', conversation.id),
        formData,
        {
          forceFormData: true,
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            setComposerText('');
            router.reload({ only: reloadOnly });
            resolve();
          },
          onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            reject(new Error(typeof firstErr === 'string' ? firstErr : 'Falha no envio.'));
          },
        },
      );
    });
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

  // US-WA-048: macros dropdown. fetchMacros é idempotente — só busca 1x,
  // re-abrir dropdown reusa cache local (atendente edita lista raramente).
  function fetchMacros() {
    if (macrosList !== null || macrosLoading) return;
    setMacrosLoading(true);
    fetch(route('atendimento.macros.list'), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then((r) => r.json())
      .then((data: { macros: MacroEntry[] }) => {
        setMacrosList(data.macros ?? []);
      })
      .catch(() => {
        // Falha silenciosa — dropdown mostra "Erro ao carregar"
        setMacrosList([]);
      })
      .finally(() => setMacrosLoading(false));
  }

  function openMacrosDropdown() {
    setMacrosOpen((open) => {
      if (!open) {
        setMacrosFilter('');
        fetchMacros();
      }
      return !open;
    });
  }

  function applyMacro(macro: MacroEntry) {
    if (applyingMacroId !== null || isBlocked || isNote) return;
    setApplyingMacroId(macro.id);
    router.post(
      route('atendimento.inbox.apply_macro', { id: conversation.id, macroId: macro.id }),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setMacrosOpen(false);
          setMacrosFilter('');
          // Invalida cache pra refletir used_count atualizado na próxima abertura
          setMacrosList(null);
          router.reload({ only: reloadOnly });
        },
        onFinish: () => setApplyingMacroId(null),
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
              {/* US-WA-093: badge LID — WhatsApp Multi-Device mascara phone real
                  (Click-to-Chat / Status / Ads). LidPhoneResolver workaround
                  ainda não cacheou este LID — hint pro atendente. */}
              {isLikelyLid(conversation.customer_phone) && (
                <Badge
                  variant="outline"
                  className="text-[9px] px-1.5 py-0 h-4 gap-1 cursor-help"
                  title="WhatsApp não enviou o número real (LID Multi-Device). Pode resolver vinculando contato CRM, ou aguardar próxima msg do cliente."
                >
                  <Lock size={9} aria-hidden />
                  número oculto
                </Badge>
              )}
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
                    slashBadge={slashBadges[m.id] ?? null}
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
        <div className="relative">
          <Textarea
            value={composerText}
            onChange={(e) => {
              const v = e.target.value;
              setComposerText(v);
              // US-WA-074: dropdown autocomplete quando atendente começa com `/`
              // em modo nota. Heurística simples — só ativa se ainda não digitou
              // espaço (sugestão é de comando, não de argumento).
              if (isNote && v.startsWith('/') && !v.includes(' ')) {
                setShowSlashAutocomplete(true);
              } else {
                setShowSlashAutocomplete(false);
              }
            }}
            placeholder={composerDisabled
              ? 'Contato bloqueado — envio desabilitado'
              : isNote
                ? 'Nota interna…  (digite / pra comandos — Enter envia)'
                : 'Mensagem freeform…  (Enter envia · Shift+Enter quebra linha · arraste arquivos pra anexar)'}
            rows={2}
            className="resize-none"
            disabled={composerDisabled}
            onKeyDown={(e) => {
              if (e.key === 'Escape' && showSlashAutocomplete) {
                e.preventDefault();
                setShowSlashAutocomplete(false);
                return;
              }
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                setShowSlashAutocomplete(false);
                handleSend();
              }
            }}
            // US-WA-072: drag-and-drop pra anexar arquivos direto no textarea
            // (só faz sentido em modo Reply — nota interna não tem mídia outbound).
            onDragOver={(e) => {
              if (!isNote && !isBlocked) e.preventDefault();
            }}
            onDrop={(e) => {
              if (isNote || isBlocked) return;
              e.preventDefault();
              handleSendMedia(e.dataTransfer.files);
            }}
            aria-label="Compositor de mensagem"
            data-testid="composer-textarea"
          />
          {/* US-WA-074: autocomplete dropdown (visual-only — não bloqueia digitar
              livre). Clicar item preenche `/<cmd> ` e foca textarea. */}
          {showSlashAutocomplete && isNote && (
            <div
              className="absolute bottom-full left-0 right-0 mb-1 bg-card border border-amber-300 dark:border-amber-700 rounded-md shadow-lg max-h-48 overflow-y-auto z-10"
              data-testid="slash-autocomplete"
              role="listbox"
              aria-label="Sugestões de comandos slash"
            >
              {SLASH_COMMANDS
                .filter((c) => {
                  const prefix = composerText.slice(1).toLowerCase();
                  return prefix === '' || c.name.startsWith(prefix);
                })
                .map((c) => (
                  <button
                    type="button"
                    key={c.name}
                    onClick={() => {
                      setComposerText(`/${c.name} `);
                      setShowSlashAutocomplete(false);
                    }}
                    className="w-full text-left px-3 py-1.5 hover:bg-amber-50 dark:hover:bg-amber-950/30 border-b last:border-b-0 border-amber-100 dark:border-amber-900"
                    data-testid={`slash-autocomplete-item-${c.name}`}
                    role="option"
                    aria-selected={false}
                  >
                    <div className="text-sm font-mono text-amber-900 dark:text-amber-200">/{c.name}</div>
                    <div className="text-[11px] text-muted-foreground">{c.description}</div>
                  </button>
                ))}
              {SLASH_COMMANDS.filter((c) => {
                const prefix = composerText.slice(1).toLowerCase();
                return prefix === '' || c.name.startsWith(prefix);
              }).length === 0 && (
                <div className="px-3 py-2 text-[11px] text-muted-foreground">
                  Nenhum comando — Enter envia como nota normal.
                </div>
              )}
            </div>
          )}
        </div>
        <div className="flex justify-between items-center gap-2">
          <span className="text-xs text-muted-foreground tabular-nums">
            {composerText.length} {composerText.length === 1 ? 'caractere' : 'caracteres'}
          </span>
          <div className="flex gap-1.5">
            {/* US-WA-072 — botão paperclip abre file input. Hidden por
                accessibility (input[type=file] feio cross-browser). */}
            <input
              ref={fileInputRef}
              type="file"
              multiple
              accept="image/jpeg,image/png,image/webp,image/gif,audio/*,video/mp4,video/webm,video/quicktime,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/csv"
              className="hidden"
              onChange={(e) => handleSendMedia(e.target.files)}
              data-testid="composer-file-input"
            />
            <Button
              variant="outline"
              size="sm"
              onClick={() => fileInputRef.current?.click()}
              disabled={isBlocked || isNote || uploadingMedia}
              className="h-8 px-2"
              title={isNote
                ? 'Notas internas não suportam mídia nesta fase'
                : isBlocked
                  ? 'Contato bloqueado — envio desabilitado'
                  : 'Anexar mídia (image/audio/document, max 16MB)'}
              data-testid="composer-attach"
              aria-label="Anexar mídia"
            >
              {uploadingMedia
                ? <Loader2 size={14} className="animate-spin" aria-hidden />
                : <Paperclip size={14} aria-hidden />}
            </Button>
            {/* Hotfix B3 (2026-05-12) — gravar áudio via MediaRecorder API.
                Disabled em nota interna (Tier 0 ADR 0142) e contato bloqueado.
                Componente separado encapsula state recording/uploading. */}
            <MicRecorder
              disabled={isBlocked || isNote || uploadingMedia}
              onSend={handleSendVoice}
            />
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
            {/* US-WA-048 — botão Macros (quick replies + ações Chatwoot pattern).
                Dropdown lazy-loaded ao abrir, busca live por shortcut/label,
                click envia msg + aplica actions (tag/status/assign) em 1 clique.
                Disabled em note/blocked (macros são cliente-facing). */}
            <div className="relative">
              <Button
                variant="outline"
                size="sm"
                onClick={openMacrosDropdown}
                disabled={isBlocked || isNote}
                className="h-8 px-2 gap-1"
                title={isNote
                  ? 'Macros não fazem sentido em nota interna'
                  : isBlocked
                    ? 'Contato bloqueado — envio desabilitado'
                    : 'Macros — respostas rápidas + ações (clique pra abrir)'}
                data-testid="composer-macros-btn"
                aria-label="Abrir macros"
                aria-expanded={macrosOpen}
              >
                <Zap size={13} aria-hidden />
                <span className="hidden sm:inline text-xs">Macros</span>
              </Button>
              {macrosOpen && (
                <div
                  className="absolute bottom-full right-0 mb-1 w-80 bg-card border border-border rounded-md shadow-lg z-20"
                  data-testid="composer-macros-dropdown"
                  role="listbox"
                >
                  <div className="p-2 border-b">
                    <input
                      type="text"
                      value={macrosFilter}
                      onChange={(e) => setMacrosFilter(e.target.value)}
                      placeholder="Buscar macro (atalho ou rótulo)…"
                      className="w-full text-xs px-2 py-1 border rounded bg-background"
                      autoFocus
                      onKeyDown={(e) => {
                        if (e.key === 'Escape') {
                          e.preventDefault();
                          setMacrosOpen(false);
                        }
                      }}
                      data-testid="composer-macros-filter"
                    />
                  </div>
                  <div className="max-h-72 overflow-y-auto">
                    {macrosLoading && (
                      <div className="px-3 py-3 text-xs text-muted-foreground">Carregando…</div>
                    )}
                    {!macrosLoading && macrosList !== null && macrosList.length === 0 && (
                      <div className="px-3 py-3 text-xs text-muted-foreground">
                        Nenhuma macro cadastrada.{' '}
                        <a
                          href={route('atendimento.macros.index')}
                          className="text-primary underline"
                        >
                          Criar em settings
                        </a>
                      </div>
                    )}
                    {!macrosLoading && macrosList !== null && macrosList.length > 0 && (
                      (() => {
                        const q = macrosFilter.trim().toLowerCase().replace(/^\//, '');
                        const filtered = q
                          ? macrosList.filter((m) =>
                              (m.shortcut ?? '').toLowerCase().includes(q) ||
                              m.label.toLowerCase().includes(q),
                            )
                          : macrosList;
                        if (filtered.length === 0) {
                          return (
                            <div className="px-3 py-3 text-xs text-muted-foreground">
                              Nenhuma macro match com "{macrosFilter}".
                            </div>
                          );
                        }
                        return filtered.map((m) => (
                          <button
                            type="button"
                            key={m.id}
                            onClick={() => applyMacro(m)}
                            disabled={applyingMacroId !== null}
                            className="w-full text-left px-3 py-2 hover:bg-muted/50 border-b last:border-b-0 border-border disabled:opacity-50"
                            data-testid={`composer-macro-${m.id}`}
                            role="option"
                            aria-selected={false}
                          >
                            <div className="flex items-center justify-between gap-2">
                              <span className="text-sm font-medium">{m.label}</span>
                              {m.shortcut && (
                                <code className="font-mono text-[10px] bg-muted px-1 py-0.5 rounded shrink-0">
                                  /{m.shortcut}
                                </code>
                              )}
                            </div>
                            <div className="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
                              {m.body_preview}
                            </div>
                          </button>
                        ));
                      })()
                    )}
                  </div>
                </div>
              )}
            </div>
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

function MessageBubble({ message, showTail, highlight = '', slashBadge = null }: {
  message: Message;
  showTail: boolean;
  /** US-WA-062: query da busca local — body matches recebem <mark> highlight */
  highlight?: string;
  /** US-WA-074 (ADR 0142): payload do slash command resultante (success/error)
   * — quando set, badge clicável aparece ao lado da bubble da nota. */
  slashBadge?: SlashFlashPayload | null;
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
          <div className="text-[10px] font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300 mb-1 inline-flex items-center gap-1 flex-wrap">
            <Lock size={10} aria-hidden />
            Nota interna
            {message.sender_user_name && (
              <span className="font-normal normal-case ml-1 text-amber-700 dark:text-amber-400">
                · {message.sender_user_name}
              </span>
            )}
            {/* US-WA-074 (ADR 0142): badge resultado slash command. Clica
                e vai pra tela de memória (lembrar) / correções (corrigir) / etc. */}
            {slashBadge && slashBadge.kind === 'success' && slashBadge.badge && (
              slashBadge.link_url ? (
                <a
                  href={slashBadge.link_url}
                  className="ml-1.5 inline-flex items-center gap-0.5 text-[10px] font-normal normal-case bg-emerald-100 dark:bg-emerald-900/40 border border-emerald-300 dark:border-emerald-700 text-emerald-900 dark:text-emerald-200 rounded px-1.5 py-0.5 hover:bg-emerald-200 dark:hover:bg-emerald-900/60 transition-colors"
                  data-testid="slash-badge-memorized"
                  title={`Comando /${slashBadge.command} executado — ver detalhes`}
                >
                  {slashBadge.badge}
                </a>
              ) : (
                <span
                  className="ml-1.5 inline-flex items-center gap-0.5 text-[10px] font-normal normal-case bg-emerald-100 dark:bg-emerald-900/40 border border-emerald-300 dark:border-emerald-700 text-emerald-900 dark:text-emerald-200 rounded px-1.5 py-0.5"
                  data-testid="slash-badge-memorized"
                  title={`Comando /${slashBadge.command} executado`}
                >
                  {slashBadge.badge}
                </span>
              )
            )}
            {slashBadge && slashBadge.kind === 'error' && slashBadge.error_message && (
              <span
                className="ml-1.5 inline-flex items-center gap-0.5 text-[10px] font-normal normal-case bg-amber-200 dark:bg-amber-900/60 border border-amber-400 dark:border-amber-600 text-amber-900 dark:text-amber-100 rounded px-1.5 py-0.5"
                data-testid="slash-badge-error"
                title={slashBadge.error_message}
              >
                ⚠ /{slashBadge.command} falhou
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
        {/* US-WA-072 — render mídia. Image=thumb clicável; Audio=<audio>+transcricao;
            Document=ícone+filename+download; Outros=fallback [mídia]. Caption (body)
            renderiza abaixo da mídia quando presente.
            Hotfix B2 (2026-05-12) — quando `media_url` é null mas `media_mime`
            está set (típico: webhook persistiu meta do Baileys mas daemon
            ainda não fez download+decrypt) renderiza placeholder semântico
            "aguardando download" em vez do fallback genérico `[mídia]`. */}
        {(message.type === 'image' || message.type === 'audio' || message.type === 'document' || message.type === 'video')
          && (message.media_url || message.media_mime) ? (
          message.media_url
            ? <MediaContent message={message} />
            : <MediaPending message={message} />
        ) : null}
        {/* Body (caption ou texto puro). Em mídia sem caption, omite o
            placeholder pq o MediaContent acima já preenche o bubble. */}
        {message.body ? (
          <div className="whitespace-pre-wrap break-words text-sm leading-snug">
            <HighlightedBody body={message.body} query={highlight} />
          </div>
        ) : (!message.media_url && !message.media_mime ? (
          <div className="whitespace-pre-wrap break-words text-sm leading-snug">
            <em className="opacity-70">[mídia]</em>
          </div>
        ) : null)}
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

/**
 * US-WA-072 — render diferenciado por type de mídia dentro da bubble.
 *
 * Image: thumbnail clicável + modal fullscreen ao clicar.
 * Audio: <audio controls> HTML5 + transcrição em itálico abaixo (cliente
 *   vê só áudio; atendente lê texto).
 * Document: ícone tipo MIME + filename + botão Download (target=_blank).
 * Video: <video controls> HTML5.
 */
function MediaContent({ message }: { message: Message }) {
  const [modalOpen, setModalOpen] = useState(false);

  if (message.type === 'image') {
    const thumb = message.media_thumbnail_url || message.media_url;
    return (
      <>
        <button
          type="button"
          className="block mb-1 rounded-md overflow-hidden cursor-pointer hover:opacity-90 transition-opacity"
          onClick={() => setModalOpen(true)}
          data-testid={`bubble-media-image-${message.id}`}
          aria-label="Abrir imagem em tamanho real"
        >
          <img
            src={thumb ?? ''}
            alt={message.media_filename ?? 'imagem'}
            className="max-w-[300px] max-h-[260px] object-cover"
            loading="lazy"
          />
        </button>
        {modalOpen && (
          <div
            className="fixed inset-0 bg-black/85 z-50 flex items-center justify-center p-4 cursor-zoom-out"
            onClick={() => setModalOpen(false)}
            role="dialog"
            aria-modal="true"
          >
            <img
              src={message.media_url ?? ''}
              alt={message.media_filename ?? 'imagem'}
              className="max-w-full max-h-full object-contain"
              onClick={(e) => e.stopPropagation()}
            />
            <button
              type="button"
              className="absolute top-3 right-3 text-white hover:opacity-80"
              onClick={() => setModalOpen(false)}
              aria-label="Fechar"
            >
              <X size={24} />
            </button>
          </div>
        )}
      </>
    );
  }

  if (message.type === 'audio') {
    return (
      <div className="mb-1 space-y-1" data-testid={`bubble-media-audio-${message.id}`}>
        <audio controls src={message.media_url ?? ''} className="max-w-[280px] h-9">
          Seu navegador não suporta áudio HTML5.
        </audio>
        {message.media_transcription && (
          <div className="text-xs italic opacity-80 max-w-[300px]">
            {message.media_transcription}
          </div>
        )}
      </div>
    );
  }

  if (message.type === 'video') {
    return (
      <video
        controls
        src={message.media_url ?? ''}
        className="max-w-[300px] max-h-[260px] rounded-md mb-1"
        data-testid={`bubble-media-video-${message.id}`}
      >
        Seu navegador não suporta vídeo HTML5.
      </video>
    );
  }

  // Document fallback
  return (
    <a
      href={message.media_url ?? '#'}
      target="_blank"
      rel="noopener noreferrer"
      className="flex items-center gap-2 px-2 py-1.5 mb-1 rounded-md bg-black/5 dark:bg-white/10 hover:bg-black/10 dark:hover:bg-white/15 transition-colors"
      data-testid={`bubble-media-document-${message.id}`}
    >
      <FileText size={20} className="shrink-0 opacity-80" aria-hidden />
      <div className="min-w-0 flex-1">
        <div className="text-xs font-medium truncate">
          {message.media_filename ?? 'documento'}
        </div>
        {message.media_size_bytes && (
          <div className="text-[10px] opacity-70">
            {formatBytes(message.media_size_bytes)}
          </div>
        )}
      </div>
      <Download size={14} className="shrink-0 opacity-70" aria-hidden />
    </a>
  );
}

function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
  return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/**
 * Hotfix B2 (2026-05-12) — placeholder semântico pra mídia inbound que
 * o webhook persistiu com meta (`media_mime`/`media_size_bytes`/
 * `media_duration_s`) mas SEM `media_url` ainda. Cenário típico em prod
 * Hostinger: payload Baileys traz URL criptografada (`.enc` + `mediaKey`)
 * que só o daemon CT 100 com SDK consegue decrypt. Enquanto daemon não
 * popular `media_url`, mostra placeholder com ícone + descrição + spinner.
 *
 * Tipos cobertos:
 *   - audio/*           → Music + duração
 *   - image/*           → Image  + tamanho
 *   - video/*           → Video  + duração
 *   - application/pdf   → FileText + nome
 *   - Outros            → File   + mime
 */
function MediaPending({ message }: { message: Message }) {
  const mime = message.media_mime ?? '';

  let Icon = FileIcon;
  let label = 'Arquivo';
  let extra: string | null = null;

  if (mime.startsWith('audio/')) {
    Icon = Music;
    label = 'Áudio';
    extra = message.media_duration_s ? `${message.media_duration_s}s` : null;
  } else if (mime.startsWith('image/')) {
    Icon = ImageIcon;
    label = 'Imagem';
    extra = message.media_size_bytes ? formatBytes(message.media_size_bytes) : null;
  } else if (mime.startsWith('video/')) {
    Icon = VideoIcon;
    label = 'Vídeo';
    extra = message.media_duration_s ? `${message.media_duration_s}s` : null;
  } else if (mime === 'application/pdf') {
    Icon = FileText;
    label = 'PDF';
    extra = message.media_filename ?? null;
  } else if (mime) {
    Icon = FileIcon;
    label = 'Arquivo';
    extra = mime;
  }

  return (
    <div
      className="flex items-center gap-2 px-2 py-1.5 mb-1 rounded-md bg-black/5 dark:bg-white/10"
      data-testid={`bubble-media-pending-${message.id}`}
      title="Webhook persistiu meta da mídia mas daemon ainda não baixou o arquivo decryptado"
    >
      <Icon size={18} className="shrink-0 opacity-80" aria-hidden />
      <div className="min-w-0 flex-1">
        <div className="text-xs font-medium truncate">
          {label}
          {extra ? <span className="font-normal opacity-70"> · {extra}</span> : null}
        </div>
        <div className="text-[10px] opacity-70">aguardando download</div>
      </div>
      <Loader2 size={12} className="shrink-0 opacity-60 animate-spin" aria-hidden />
    </div>
  );
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
