// ComposerV4.tsx — composer no rodapé da thread.
//
// Replica visual `.om-input` do Cowork (inbox-page.css L558-614):
//   - botão toggle Resp/Nota (⌘⇧N)
//   - botão ⌘T (templates — placeholder, abre composer só mostra dialog futuro)
//   - botão / (macros — placeholder)
//   - input redondo arredondado pill
//   - botão Enviar/Anotar com cor dinâmica (verde primary vs amarelo nota)
//   - desabilitado quando isPreview e modo cliente (banner já avisa)
//   - desabilitado quando isBlocked (UX consistente)
//
// Reusa POST `/atendimento/inbox/{id}/send` do legacy (US-WA-069 + ADR 0142
// notas internas) — preserva contrato backend Tier 0 enquanto coexiste com Inbox.

import { useState, useRef, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Send, FileText, Slash } from 'lucide-react';
import { cn } from '@/Lib/utils';

interface Props {
  conversationId: number;
  isPreview: boolean;
  isBlocked: boolean;
  channelShort: string;
  channelLabel: string;
}

export default function ComposerV4({
  conversationId, isPreview, isBlocked, channelShort, channelLabel,
}: Props) {
  const [internalMode, setInternalMode] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const form = useForm<{
    kind: 'freeform' | 'template';
    body: string;
    is_internal_note: boolean;
  }>({
    kind: 'freeform',
    body: '',
    is_internal_note: false,
  });

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
    if (!form.data.body.trim()) return;
    if (isBlocked) return;
    if (isPreview && !internalMode) return; // preview bloqueia envio cliente; nota interna OK

    form.transform(data => ({
      ...data,
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
        'flex items-center gap-1.5 border-t px-3.5 py-2.5 transition-colors',
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
      {/* Toggle Resp / Nota — Cowork .om-mode-btn.on tokens OKLCH §589 */}
      <button
        type="button"
        onClick={() => setInternalMode(v => !v)}
        title="Resposta cliente / Nota interna (⌘⇧N)"
        data-testid="caixa-unif-composer-toggle-mode"
        className={cn(
          'h-8 px-3 rounded-full border text-[11px] font-semibold transition-colors flex-shrink-0',
          !internalMode && 'bg-card border-border text-muted-foreground hover:text-foreground hover:border-muted-foreground',
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
        {internalMode ? 'Nota' : 'Resp'}
      </button>

      {/* Templates — placeholder (TODO US-WA-XXX: dialog templates) */}
      <button
        type="button"
        disabled={internalMode}
        title="Templates (em breve)"
        className="w-8 h-8 rounded-full border bg-card grid place-items-center text-muted-foreground hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
      >
        <FileText size={12} aria-hidden />
      </button>

      {/* Macros — placeholder (TODO US-WA-XXX: dropdown /macros) */}
      <button
        type="button"
        disabled={internalMode}
        title="Macros (em breve)"
        className="w-8 h-8 rounded-full border bg-card grid place-items-center text-muted-foreground hover:text-foreground hover:bg-muted disabled:opacity-45 disabled:cursor-not-allowed flex-shrink-0"
      >
        <Slash size={12} aria-hidden />
      </button>

      {/* Input */}
      <input
        ref={inputRef}
        type="text"
        value={form.data.body}
        onChange={e => form.setData('body', e.target.value)}
        onKeyDown={e => {
          if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
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

      {/* Enviar / Anotar */}
      <button
        type="button"
        onClick={send}
        disabled={!form.data.body.trim() || !canType || form.processing}
        data-testid="caixa-unif-composer-send"
        className={cn(
          'h-8 px-4 rounded-full text-[12px] font-semibold transition-colors flex-shrink-0 inline-flex items-center gap-1.5',
          internalMode
            ? 'bg-amber-400 text-amber-950 hover:bg-amber-500'
            : 'bg-primary text-primary-foreground hover:bg-primary/90',
          'disabled:opacity-45 disabled:cursor-not-allowed',
        )}
      >
        {form.processing ? 'Enviando…' : (
          <>
            <Send size={11} aria-hidden />
            {internalMode ? 'Anotar' : 'Enviar'}
          </>
        )}
      </button>
    </div>
  );
}
