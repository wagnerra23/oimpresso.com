// ComposerV4.tsx — composer no rodapé da thread.
//
// Replica visual `.om-input` do Cowork (inbox-page.css L558-614):
//   - botão toggle Resp/Nota (⌘⇧N)
//   - botão ⌘T (templates — US-WA-303: TemplatePicker legacy reusado, filtrado
//     por provider do canal da thread)
//   - botão / (macros — US-WA-303: dropdown via atendimento.macros.list +
//     autocomplete inline digitando "/" + apply via atendimento.inbox.apply_macro)
//   - botão {} (variáveis — US-WA-303: {{nome}}/{{telefone}}/{{operador}} com
//     preview resolvido acima do input; substituição no send)
//   - input redondo arredondado pill
//   - botão Enviar/Anotar com cor dinâmica (verde primary vs amarelo nota)
//   - desabilitado quando isPreview e modo cliente (banner já avisa)
//   - desabilitado quando isBlocked (UX consistente)
//
// Reusa POST `/atendimento/inbox/{id}/send` do legacy (US-WA-069 + ADR 0142
// notas internas) — preserva contrato backend Tier 0 enquanto coexiste com Inbox.
//
// TODO honesto US-WA-303: {{empresa}}/{{os}}/{{saldo}} do protótipo (inbox-out.jsx)
// dependem das sections OS/Saldo da sidebar que ainda são placeholder — entram
// quando aquelas integrações (Repair/Financeiro) existirem. Resolver deixa
// variável sem valor como literal (preview marca em vermelho).

import { useState, useRef, useEffect, useMemo } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import { Braces, LayoutList, Loader2, Send, FileText, Paperclip, Slash, Sparkles, X, Reply, Pencil } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { Inline, Stack } from '@/Components/layout';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/Components/ui/popover';
import MicRecorder from '@/Pages/Whatsapp/_components/MicRecorder';
import InteractiveMessageDialog from '@/Pages/Whatsapp/_components/InteractiveMessageDialog';
import TemplatePicker from '@/Pages/Whatsapp/_components/TemplatePicker';
import type { ReadyTemplate } from '@/Pages/Whatsapp/_components/helpers';

/** US-WA-303: shape do item de GET atendimento.macros.list (US-WA-048). */
interface MacroEntry {
  id: number;
  label: string;
  shortcut: string | null;
  body: string;
  used_count: number;
  body_preview: string;
}

/**
 * US-WA-303: Channel.type → WhatsappTemplate.provider. Template só aparece
 * no picker quando o provider casa com o driver do canal da thread (Meta HSM
 * não vai por Baileys e vice-versa — contrato do InboxController::send).
 */
const PROVIDER_BY_CHANNEL_TYPE: Record<string, string> = {
  whatsapp_baileys: 'baileys',
  whatsapp_meta: 'meta_cloud',
  meta_cloud: 'meta_cloud',
  whatsapp_zapi: 'zapi',
};

interface Props {
  conversationId: number;
  isPreview: boolean;
  isBlocked: boolean;
  channelShort: string;
  channelLabel: string;
  /** Wave 4-B F1: tipo do channel (whatsapp_meta libera Interactive). */
  channelType?: string;
  /** US-WA-303: templates ready do business (deferred — [] até resolver). */
  templates?: ReadyTemplate[];
  /** US-WA-303: dados da thread pro resolver de variáveis {{nome}}/{{telefone}}. */
  contactName?: string;
  contactPhone?: string;
}

/** Wave 4 F1: limite legal Tier 0 da caption (espelha InboxController::sendMedia). */
const CAPTION_MAX_CHARS = 1024;
/** M5 fix 2026-05-28: cap unificado FE+BE = `Message::MEDIA_MAX_SIZE_BYTES` = 16MB
 *  (Meta Cloud video limit; WhatsApp dropa upload > esse cap silenciosamente). */
const MEDIA_MAX_BYTES = 16 * 1024 * 1024;
/** Wave 4 F1 + M5 fix: tipos aceitos pelo daemon. Ampliado pra docx/xlsx/pptx/txt
 *  conforme audit 2026-05-28 (alinha Meta Cloud + WuzAPI suportados). */
const ACCEPT_MIME = [
  // image
  'image/jpeg', 'image/png', 'image/webp', 'image/gif',
  // audio (voz + arquivos)
  'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/webm', 'audio/wav',
  // video
  'video/mp4', 'video/3gpp', 'video/quicktime',
  // documents
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'text/plain', 'text/csv',
].join(',');

export default function ComposerV4({
  conversationId, isPreview, isBlocked, channelShort, channelLabel, channelType,
  templates = [], contactName, contactPhone,
}: Props) {
  const [internalMode, setInternalMode] = useState(false);
  const [pendingFile, setPendingFile] = useState<File | null>(null);
  const [uploading, setUploading] = useState(false);
  const [mediaError, setMediaError] = useState<string | null>(null);
  const [interactiveOpen, setInteractiveOpen] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Wave 4-B F1: só whatsapp_meta libera Interactive (List/Button)
  const supportsInteractive = channelType === 'whatsapp_meta' || channelType === 'meta_cloud';

  // ── US-WA-303: Templates ⌘T ─────────────────────────────────────────────
  const [templatePickerOpen, setTemplatePickerOpen] = useState(false);
  const [sendingTemplate, setSendingTemplate] = useState(false);
  const channelProvider = channelType ? PROVIDER_BY_CHANNEL_TYPE[channelType] : undefined;
  const channelTemplates = useMemo(
    () => (channelProvider ? templates.filter(t => t.provider === channelProvider) : []),
    [templates, channelProvider],
  );

  function sendTemplate(payload: { template_name: string; template_locale: string; template_params: string[] }) {
    if (sendingTemplate || isBlocked || isPreview) return;
    setSendingTemplate(true);
    router.post(
      route('atendimento.inbox.send', conversationId),
      { kind: 'template', ...payload },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages', 'conversations', 'stats'],
        onSuccess: () => setTemplatePickerOpen(false),
        onFinish: () => setSendingTemplate(false),
      },
    );
  }

  // ── US-WA-303: Macros "/" (dropdown + autocomplete inline) ─────────────
  // fetchMacros idempotente — busca 1x, cache local (pattern do Inbox legacy).
  const [macrosOpen, setMacrosOpen] = useState(false);
  const [macrosList, setMacrosList] = useState<MacroEntry[] | null>(null);
  const [macrosLoading, setMacrosLoading] = useState(false);
  const [applyingMacroId, setApplyingMacroId] = useState<number | null>(null);
  const [slashDismissed, setSlashDismissed] = useState(false);

  function fetchMacros() {
    if (macrosList !== null || macrosLoading) return;
    setMacrosLoading(true);
    fetch(route('atendimento.macros.list'), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    })
      .then(r => r.json())
      .then((data: { macros: MacroEntry[] }) => setMacrosList(data.macros ?? []))
      .catch(() => setMacrosList([]))
      .finally(() => setMacrosLoading(false));
  }

  function applyMacro(macro: MacroEntry) {
    if (applyingMacroId !== null || isBlocked || internalMode || isPreview) return;
    setApplyingMacroId(macro.id);
    router.post(
      route('atendimento.inbox.apply_macro', { id: conversationId, macroId: macro.id }),
      {},
      {
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages', 'conversations', 'stats'],
        onSuccess: () => {
          setMacrosOpen(false);
          form.setData('body', '');
          // Invalida cache pra refletir used_count na próxima abertura
          setMacrosList(null);
        },
        onFinish: () => setApplyingMacroId(null),
      },
    );
  }

  // ── US-WA-303: Variáveis {{nome}}/{{telefone}}/{{operador}} ────────────
  const page = usePage<{ auth?: { user?: { name?: string } | null } }>();
  const operatorName = page.props.auth?.user?.name?.trim() || '';
  const varEntries = useMemo(() => ([
    { key: 'nome', label: 'Nome do contato', value: (contactName ?? '').trim() },
    { key: 'telefone', label: 'Telefone do contato', value: (contactPhone ?? '').trim() },
    { key: 'operador', label: 'Seu nome', value: operatorName },
  ]), [contactName, contactPhone, operatorName]);

  function resolveVars(text: string): string {
    return text.replace(/\{\{(\w+)\}\}/g, (raw, key: string) => {
      const v = varEntries.find(e => e.key === key.toLowerCase())?.value;
      return v ? v : raw; // sem valor → mantém literal (preview marca em vermelho)
    });
  }

  // ── PR-9: ✦ Sugerir resposta (IA server-side; humano SEMPRE revisa) ─────
  const [suggesting, setSuggesting] = useState(false);
  function suggestReply() {
    if (suggesting || internalMode || !canType) return;
    setSuggesting(true);
    fetch(route('atendimento.inbox.ai.suggest_reply', conversationId), {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
      },
    })
      .then(async r => {
        const data = await r.json();
        if (!r.ok) throw new Error(data.error ?? 'IA indisponível.');
        form.setData('body', String(data.text ?? '').trim());
        requestAnimationFrame(() => inputRef.current?.focus());
      })
      .catch((e: Error) => setMediaError(e.message))
      .finally(() => setSuggesting(false));
  }

  function insertVar(key: string) {
    const input = inputRef.current;
    const token = `{{${key}}}`;
    const body = form.data.body;
    if (input && typeof input.selectionStart === 'number') {
      const pos = input.selectionStart;
      form.setData('body', body.slice(0, pos) + token + body.slice(input.selectionEnd ?? pos));
    } else {
      form.setData('body', body + token);
    }
    requestAnimationFrame(() => input?.focus());
  }

  const form = useForm<{
    kind: 'freeform' | 'template';
    body: string;
    is_internal_note: boolean;
  }>({
    kind: 'freeform',
    body: '',
    is_internal_note: false,
  });

  // US-WA-303 — autocomplete slash: body começando com "/" filtra macros por
  // shortcut. Enter aplica a 1ª. Esc dispensa (digitar de novo reabre).
  const slashOpen = !internalMode && !slashDismissed && form.data.body.startsWith('/');
  useEffect(() => {
    if (slashOpen) fetchMacros();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slashOpen]);
  const slashMatches = useMemo(() => {
    if (!slashOpen || !macrosList) return [];
    const q = form.data.body.slice(1).toLowerCase().trim();
    return macrosList
      .filter(m => m.shortcut && (q === '' || m.shortcut.toLowerCase().startsWith(q)))
      .slice(0, 6);
  }, [slashOpen, macrosList, form.data.body]);

  // US-WA-303 — preview de variáveis resolvidas acima do input
  const hasVars = /\{\{\w+\}\}/.test(form.data.body);

  // Atalho ⌘⇧N — toggle modo nota
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.shiftKey && e.key === 'N') {
        e.preventDefault();
        setInternalMode(v => !v);
      }
    }
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, []);

  function send() {
    if (isBlocked) return;
    if (isPreview && !internalMode) return; // preview bloqueia envio cliente; nota interna OK

    // Wave 4 F1: se há arquivo pendente, vai por send_media (FormData)
    if (pendingFile) {
      sendMedia();
      return;
    }

    if (!form.data.body.trim()) return;

    form.transform(data => ({
      ...data,
      // US-WA-303 — substitui {{nome}}/{{telefone}}/{{operador}} no envio
      // (nota interna fica literal — registro fiel do que o atendente digitou).
      body: internalMode ? data.body : resolveVars(data.body),
      is_internal_note: internalMode,
    }));

    form.post(route('atendimento.inbox.send', conversationId), {
      preserveScroll: true,
      preserveState: true,
      only: ['thread', 'messages', 'conversations', 'stats'],
      onSuccess: () => {
        form.reset('body');
      },
    });
  }

  // Wave 4 F1 — upload mídia (POST /inbox/{id}/send-media com FormData)
  // Reusa pattern do ConversationThread legacy (US-WA-042/043/072).
  function sendMedia() {
    if (!pendingFile || isBlocked || (isPreview && !internalMode)) return;
    setMediaError(null);
    setUploading(true);

    const formData = new FormData();
    formData.append('file', pendingFile);
    if (form.data.body.trim()) {
      formData.append('caption', form.data.body.slice(0, CAPTION_MAX_CHARS));
    }

    router.post(
      route('atendimento.inbox.send_media', conversationId),
      formData,
      {
        forceFormData: true,
        preserveScroll: true,
        preserveState: true,
        only: ['thread', 'messages', 'conversations', 'stats'],
        onSuccess: () => {
          setPendingFile(null);
          form.setData('body', '');
          if (fileInputRef.current) fileInputRef.current.value = '';
        },
        onError: (errors) => {
          const firstErr = Object.values(errors)[0];
          setMediaError(typeof firstErr === 'string' ? firstErr : 'Falha no upload da mídia.');
        },
        onFinish: () => setUploading(false),
      },
    );
  }

  function pickFile() {
    fileInputRef.current?.click();
  }

  // M4 fix 2026-05-28: aceita arquivo de múltiplas origens (botão click + paste
  // clipboard + drag-drop). Centralizado pra validar tamanho/mime uma vez.
  function acceptFile(file: File): boolean {
    if (file.size > MEDIA_MAX_BYTES) {
      setMediaError(`Arquivo muito grande (${(file.size / 1024 / 1024).toFixed(1)} MB). Máximo: 16 MB.`);
      return false;
    }
    setMediaError(null);
    setPendingFile(file);
    return true;
  }

  function onFilePicked(e: React.ChangeEvent<HTMLInputElement>) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (!acceptFile(file)) {
      e.target.value = '';
    }
  }

  // M4 fix — paste de imagem do clipboard (Ctrl+V de print/foto copiada).
  // Listener no window pra capturar mesmo quando focus está fora do input.
  // Filtra apenas quando NÃO há texto digitado pra evitar bater com paste texto.
  useEffect(() => {
    function onPaste(e: ClipboardEvent) {
      if (!canType || isBlocked || internalMode) return;
      const items = e.clipboardData?.items;
      if (!items) return;
      for (let i = 0; i < items.length; i++) {
        const it = items[i];
        if (it.kind === 'file') {
          const f = it.getAsFile();
          if (f && acceptFile(f)) {
            e.preventDefault();
            return;
          }
        }
      }
    }
    window.addEventListener('paste', onPaste);
    return () => window.removeEventListener('paste', onPaste);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [internalMode, isBlocked, isPreview]);

  // M4 fix — drag-drop pra área do composer + thread. Visual highlight via
  // dragActive state. Aceita 1 arquivo por vez (msg WhatsApp = 1 mídia).
  const [dragActive, setDragActive] = useState(false);
  function onDragOver(e: React.DragEvent) {
    if (!canType || isBlocked || internalMode) return;
    e.preventDefault();
    e.stopPropagation();
    setDragActive(true);
  }
  function onDragLeave(e: React.DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
  }
  function onDrop(e: React.DragEvent) {
    e.preventDefault();
    e.stopPropagation();
    setDragActive(false);
    if (!canType || isBlocked || internalMode) return;
    const files = e.dataTransfer?.files;
    if (!files || files.length === 0) return;
    acceptFile(files[0]);
  }

  function clearPendingFile() {
    setPendingFile(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  }

  // Wave 4-B F1 — envio de áudio voz (MicRecorder callback)
  // Reusa send_media com filename 'voice.ogg' (compat WhatsApp PTT).
  function handleSendVoice(blob: Blob, _durationS: number): Promise<void> {
    if (internalMode) return Promise.reject(new Error('Notas internas não suportam áudio.'));
    return new Promise<void>((resolve, reject) => {
      const isOgg = blob.type.includes('ogg');
      const filename = isOgg ? 'voice.ogg' : 'voice.webm';
      const file = new File([blob], filename, { type: blob.type || 'audio/ogg' });
      const formData = new FormData();
      formData.append('file', file);
      if (form.data.body.trim()) {
        formData.append('caption', form.data.body.slice(0, CAPTION_MAX_CHARS));
      }
      router.post(
        route('atendimento.inbox.send_media', conversationId),
        formData,
        {
          forceFormData: true,
          preserveScroll: true,
          preserveState: true,
          only: ['thread', 'messages', 'conversations', 'stats'],
          onSuccess: () => {
            form.setData('body', '');
            resolve();
          },
          onError: (errors) => {
            const firstErr = Object.values(errors)[0];
            reject(new Error(typeof firstErr === 'string' ? firstErr : 'Falha no envio do áudio.'));
          },
        },
      );
    });
  }

  const canType = !(isPreview && !internalMode) && !isBlocked;
  const placeholder = isBlocked
    ? 'Contato bloqueado — envio desabilitado'
    : internalMode
      ? 'Nota interna · só pra equipe (⌘⇧N pra voltar)'
      : isPreview
        ? `${channelShort} em homologação — envio bloqueado`
        : `Responder via ${channelShort}${channelLabel ? ` · ${channelLabel}` : ''}`;

  return (
    <div
      className={cn(
        'flex flex-col relative',
        dragActive && 'ring-2 ring-primary/60 ring-offset-2 ring-offset-background rounded-md',
      )}
      onDragOver={onDragOver}
      onDragLeave={onDragLeave}
      onDrop={onDrop}
    >
      {dragActive && (
        <div className="absolute inset-0 z-10 flex items-center justify-center bg-primary/10 backdrop-blur-sm rounded-md pointer-events-none">
          <div className="px-4 py-2 bg-primary text-primary-foreground rounded-full font-medium text-sm shadow-lg">
            Solte o arquivo aqui pra anexar
          </div>
        </div>
      )}
      {/* Wave 4 F1 — preview do arquivo selecionado (acima do composer) */}
      {pendingFile && (
        <div
          className="flex items-center justify-between gap-3 border-t bg-muted/30 px-3.5 py-2 text-[11.5px]"
          data-testid="caixa-unif-composer-media-preview"
        >
          <span className="inline-flex items-center gap-2 min-w-0">
            <Paperclip size={12} className="flex-shrink-0 text-muted-foreground" aria-hidden />
            <span className="truncate font-medium" title={pendingFile.name}>{pendingFile.name}</span>
            <span className="font-mono text-[10.5px] text-muted-foreground flex-shrink-0">
              {(pendingFile.size / 1024).toFixed(0)} KB
            </span>
          </span>
          <button
            type="button"
            onClick={clearPendingFile}
            disabled={uploading}
            className="inline-flex items-center gap-0.5 text-muted-foreground hover:text-destructive disabled:opacity-45 transition-colors"
            data-testid="caixa-unif-composer-media-clear"
            title="Remover anexo"
          >
            <X size={13} aria-hidden /> remover
          </button>
        </div>
      )}
      {mediaError && (
        <div
          className="border-t border-destructive/30 bg-destructive/10 text-destructive px-3.5 py-1.5 text-[11px]"
          role="alert"
        >
          {mediaError}
        </div>
      )}

      {/* US-WA-303 — preview de variáveis resolvidas (Cowork om-var-preview).
          Verde = resolvida, vermelho = sem valor (vai literal). */}
      {hasVars && !internalMode && (
        <Inline
          gap={2}
          align="baseline"
          className="border-t bg-muted/30 px-3.5 py-1.5 text-[11px]"
          data-testid="caixa-unif-composer-var-preview"
        >
          <small className="text-[9.5px] uppercase tracking-[0.06em] text-muted-foreground font-semibold flex-shrink-0">
            Preview
          </small>
          <span className="min-w-0 break-words">
            {form.data.body.split(/(\{\{\w+\}\})/g).map((part, i) => {
              const m = part.match(/^\{\{(\w+)\}\}$/);
              if (!m) return <span key={i}>{part}</span>;
              const v = varEntries.find(e => e.key === m[1]!.toLowerCase())?.value;
              return v ? (
                <span
                  key={i}
                  className="inline-block px-1.5 rounded-full font-medium"
                  // Verde-pastel WA — mesmo par OKLCH das bubbles outbound do V4
                  style={{ background: 'oklch(0.93 0.06 145)', color: 'oklch(0.25 0.10 145)' }}
                  title={part}
                >
                  {v}
                </span>
              ) : (
                <span
                  key={i}
                  className="inline-block px-1.5 rounded-full bg-destructive/10 text-destructive font-mono"
                  title={`Variável ${part} sem valor — vai literal`}
                >
                  {part}
                </span>
              );
            })}
          </span>
        </Inline>
      )}

      {/* US-WA-303 — autocomplete slash (Cowork om-slash-pop): digite "/" */}
      {slashOpen && slashMatches.length > 0 && (
        <div
          className="absolute bottom-full left-3.5 right-3.5 mb-1 bg-card border rounded-md shadow-lg max-h-56 overflow-y-auto z-10"
          data-testid="caixa-unif-slash-pop"
          role="listbox"
          aria-label="Macros que casam com o atalho digitado"
        >
          {slashMatches.map((m, i) => (
            <button
              type="button"
              key={m.id}
              onClick={() => applyMacro(m)}
              disabled={applyingMacroId !== null}
              data-testid={`caixa-unif-slash-item-${m.id}`}
              role="option"
              aria-selected={i === 0}
              className={cn(
                'w-full text-left px-3 py-1.5 border-b last:border-b-0 hover:bg-muted disabled:opacity-45',
                i === 0 && 'bg-muted/50',
              )}
            >
              <span className="text-[11.5px] font-medium inline-flex items-center gap-1.5">
                <span className="font-mono text-[10.5px] text-primary">/{m.shortcut}</span>
                {m.label}
                {i === 0 && <span className="text-[9.5px] text-muted-foreground ml-auto">Enter aplica</span>}
              </span>
              <span className="block text-[10.5px] text-muted-foreground truncate">{m.body_preview}</span>
            </button>
          ))}
        </div>
      )}
    <Stack
      gap={2}
      className={cn(
        'border-t px-3.5 py-2.5 transition-colors',
        !internalMode && 'bg-card',
      )}
      style={
        internalMode
          ? {
              // Cowork .om-input.internal — bg amarelo-pastel + border-top destacado
              background: 'oklch(0.97 0.03 80)',
              borderTopColor: 'oklch(0.78 0.10 80)',
            }
          : undefined
      }
      data-testid="caixa-unif-composer"
    >
      {/* C1 + a11y (2026-06-20): composer em 2 linhas (.om-composer coluna). Input + Enviar
          PRIMEIRO no DOM (1ª linha) → ordem de foco do teclado = ordem visual (WCAG 2.4.3);
          ferramentas na 2ª linha. Sem `order` (a ordem do DOM já é a visual). */}
      <Inline gap={2}>
      {/* Input */}
      <input
        ref={inputRef}
        type="text"
        value={form.data.body}
        onChange={e => {
          form.setData('body', e.target.value);
          // US-WA-303 — digitar de novo reabre autocomplete dispensado com Esc
          if (slashDismissed) setSlashDismissed(false);
        }}
        onKeyDown={e => {
          if (e.key === 'Escape' && slashOpen) {
            e.preventDefault();
            setSlashDismissed(true);
            return;
          }
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            // US-WA-303 — com autocomplete aberto, Enter aplica a 1ª macro
            if (slashOpen && slashMatches.length > 0) {
              applyMacro(slashMatches[0]!);
              return;
            }
            send();
          }
        }}
        placeholder={placeholder}
        disabled={!canType}
        data-testid="caixa-unif-composer-input"
        className={cn(
          'flex-1 h-8 px-3 text-[12.5px] rounded-full border outline-none',
          internalMode
            ? 'bg-amber-100/50 border-amber-300 focus:border-amber-500'
            : 'bg-muted/30 border-border focus:bg-card focus:border-primary',
          !canType && 'opacity-60 cursor-not-allowed',
        )}
      />

      {/* Enviar / Anotar — Wave 4 F1: aceita arquivo OR texto */}
      <button
        type="button"
        onClick={send}
        disabled={
          (!form.data.body.trim() && !pendingFile) ||
          !canType ||
          form.processing ||
          uploading
        }
        data-testid="caixa-unif-composer-send"
        className={cn(
          'h-8 px-4 rounded-full text-[12px] font-semibold transition-colors flex-shrink-0 inline-flex items-center gap-1.5',
          internalMode
            ? 'bg-amber-400 text-amber-950 hover:bg-amber-500'
            : 'bg-primary text-primary-foreground hover:bg-primary/90',
          'disabled:opacity-45 disabled:cursor-not-allowed',
        )}
      >
        {form.processing || uploading ? (
          'Enviando…'
        ) : (
          <>
            <Send size={11} aria-hidden />
            {internalMode ? 'Anotar' : (pendingFile ? 'Enviar mídia' : 'Enviar')}
          </>
        )}
      </button>
      </Inline>

      {/* Ferramentas — 2ª linha (.om-input-tools) */}
      <Inline wrap className="gap-1.5">
      {/* PR-9 — ✦ Sugerir resposta (IA preenche o input; humano revisa e envia) */}
      {!internalMode && (
        <button
          type="button"
          onClick={suggestReply}
          disabled={!canType || suggesting}
          title="IA sugere a próxima resposta — você revisa antes de enviar"
          data-testid="caixa-unif-composer-suggest"
          className="h-6 px-2 rounded-md border border-transparent inline-flex items-center gap-1 text-[11px] font-semibold text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
        >
          {suggesting ? <Loader2 size={11} className="animate-spin" aria-hidden /> : <Sparkles size={11} aria-hidden />}
          Sugerir
        </button>
      )}

      {/* C4 — divisor após Sugerir (.om-tool-div do protótipo Cowork) */}
      {!internalMode && (
        <span className="w-px h-[18px] bg-border self-center shrink-0 mx-0.5" aria-hidden />
      )}

      {/* Toggle Resp / Nota — Cowork .om-mode-btn.on tokens OKLCH §589 */}
      <button
        type="button"
        onClick={() => setInternalMode(v => !v)}
        title="Resposta cliente / Nota interna (⌘⇧N)"
        aria-label={internalMode ? 'Modo nota interna ativo (⌘⇧N pra voltar)' : 'Alternar pra nota interna (⌘⇧N)'}
        aria-pressed={internalMode}
        data-testid="caixa-unif-composer-toggle-mode"
        className={cn(
          'w-6 h-6 rounded-md border grid place-items-center transition-colors flex-shrink-0',
          !internalMode
            ? 'border-transparent text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted'
            : 'opacity-100',
        )}
        style={
          internalMode
            ? {
                background: 'oklch(0.90 0.10 80)',
                borderColor: 'oklch(0.62 0.14 80)',
                color: 'oklch(0.22 0.10 80)',
              }
            : undefined
        }
      >
        {internalMode ? <Pencil size={12} aria-hidden /> : <Reply size={12} aria-hidden />}
      </button>

      {/* US-WA-303 — Templates do canal (TemplatePicker legacy reusado) */}
      <button
        type="button"
        onClick={() => setTemplatePickerOpen(true)}
        disabled={internalMode || !canType || !channelProvider}
        title={channelProvider
          ? `Templates do canal (${channelTemplates.length} ${channelTemplates.length === 1 ? 'disponível' : 'disponíveis'})`
          : 'Canal não suporta templates'}
        data-testid="caixa-unif-composer-templates"
        className="w-6 h-6 rounded-md border border-transparent grid place-items-center text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
      >
        <FileText size={12} aria-hidden />
      </button>

      {/* US-WA-303 — Macros (dropdown via atendimento.macros.list + apply) */}
      <Popover
        open={macrosOpen}
        onOpenChange={(o) => {
          setMacrosOpen(o);
          if (o) fetchMacros();
        }}
      >
        <PopoverTrigger asChild>
          <button
            type="button"
            disabled={internalMode || !canType}
            title="Macros — atalhos / com ações (digite / no input pra autocomplete)"
            data-testid="caixa-unif-composer-macros"
            className="w-6 h-6 rounded-md border border-transparent grid place-items-center text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
          >
            <Slash size={12} aria-hidden />
          </button>
        </PopoverTrigger>
        <PopoverContent align="start" side="top" className="w-80 p-1.5">
          <div className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold px-2 pt-1 pb-1.5">
            Macros · digite <span className="font-mono">/</span> no input pra autocomplete
          </div>
          {macrosLoading ? (
            <div className="px-2 py-3 text-[11px] text-muted-foreground inline-flex items-center gap-1.5">
              <Loader2 size={12} className="animate-spin" aria-hidden /> Carregando…
            </div>
          ) : (macrosList ?? []).length === 0 ? (
            <div className="px-2 py-2 text-[11px] text-muted-foreground italic">
              Nenhuma macro cadastrada.{' '}
              <a href={route('atendimento.macros.index')} className="underline">Criar em Macros →</a>
            </div>
          ) : (
            <ul className="max-h-72 overflow-auto">
              {(macrosList ?? []).map(m => (
                <li key={m.id}>
                  <button
                    type="button"
                    onClick={() => applyMacro(m)}
                    disabled={applyingMacroId !== null}
                    data-testid={`caixa-unif-composer-macro-${m.id}`}
                    className="w-full px-2 py-1.5 text-left hover:bg-muted rounded disabled:opacity-45"
                  >
                    <span className="block text-[11.5px] font-medium">
                      <span className="inline-flex items-center gap-1.5">
                        {m.shortcut && <span className="font-mono text-[10.5px] text-primary">/{m.shortcut}</span>}
                        {m.label}
                        {applyingMacroId === m.id && <Loader2 size={11} className="animate-spin" aria-hidden />}
                      </span>
                    </span>
                    <span className="block mt-0.5 text-[10.5px] text-muted-foreground truncate w-full">{m.body_preview}</span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </PopoverContent>
      </Popover>

      {/* US-WA-303 — Variáveis {} (insere {{nome}}/{{telefone}}/{{operador}}) */}
      {!internalMode && (
        <Popover>
          <PopoverTrigger asChild>
            <button
              type="button"
              disabled={!canType}
              title="Inserir variável no texto ({{nome}}, {{telefone}}, {{operador}})"
              data-testid="caixa-unif-composer-vars"
              className="w-6 h-6 rounded-md border border-transparent grid place-items-center text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
            >
              <Braces size={12} aria-hidden />
            </button>
          </PopoverTrigger>
          <PopoverContent align="start" side="top" className="w-64 p-1.5">
            <div className="text-[10px] uppercase tracking-[0.06em] text-muted-foreground font-semibold px-2 pt-1 pb-1.5">
              Inserir variável
            </div>
            <ul>
              {varEntries.map(v => (
                <li key={v.key}>
                  <button
                    type="button"
                    onClick={() => insertVar(v.key)}
                    data-testid={`caixa-unif-composer-var-${v.key}`}
                    className="w-full inline-flex items-center justify-between gap-2 px-2 py-1.5 text-[11.5px] hover:bg-muted rounded text-left"
                  >
                    <span className="font-mono text-[10.5px] text-primary">{`{{${v.key}}}`}</span>
                    <span className="text-muted-foreground truncate">{v.value || v.label}</span>
                  </button>
                </li>
              ))}
            </ul>
          </PopoverContent>
        </Popover>
      )}

      {/* Wave 4 F1 — Anexar mídia (POST send_media via FormData) */}
      <input
        ref={fileInputRef}
        type="file"
        accept={ACCEPT_MIME}
        className="hidden"
        onChange={onFilePicked}
        data-testid="caixa-unif-composer-file-input"
      />
      <button
        type="button"
        onClick={pickFile}
        disabled={internalMode || !canType}
        title="Anexar imagem, PDF ou áudio"
        data-testid="caixa-unif-composer-attach"
        className="w-6 h-6 rounded-md border border-transparent grid place-items-center text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
      >
        <Paperclip size={12} aria-hidden />
      </button>

      {/* Wave 4-B F1 — MicRecorder (gravar áudio voz PTT) */}
      <MicRecorder
        compact
        disabled={internalMode || !canType || uploading}
        onSend={handleSendVoice}
      />

      {/* Wave 4-B F1 — Interactive (List/Button Meta) — só whatsapp_meta */}
      {supportsInteractive && (
        <button
          type="button"
          onClick={() => setInteractiveOpen(true)}
          disabled={internalMode || !canType}
          title="Enviar mensagem interativa (List/Button)"
          data-testid="caixa-unif-composer-interactive"
          className="w-6 h-6 rounded-md border border-transparent grid place-items-center text-muted-foreground opacity-70 hover:opacity-100 hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
        >
          <LayoutList size={12} aria-hidden />
        </button>
      )}
      </Inline>
    </Stack>

    {/* Wave 4-B F1 — Interactive message dialog (List/Button Meta) */}
    {supportsInteractive && (
      <InteractiveMessageDialog
        conversationId={conversationId}
        open={interactiveOpen}
        onOpenChange={setInteractiveOpen}
        driverType={channelType as 'whatsapp_meta' | 'meta_cloud'}
      />
    )}

    {/* US-WA-303 — TemplatePicker legacy reusado (kind=template no send) */}
    <TemplatePicker
      open={templatePickerOpen}
      onOpenChange={setTemplatePickerOpen}
      templates={channelTemplates}
      onSend={sendTemplate}
      sending={sendingTemplate}
    />
    </div>
  );
}
