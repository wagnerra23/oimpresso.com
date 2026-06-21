// InboxPresenterMode — overlay limpo da conversa pra apresentar em reunião
// (Polish V2 §8 · inbox-out.jsx). SEM IDs internos, sem notas internas, sem
// status técnico — só o diálogo. Esc fecha.

import { useEffect } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/Lib/utils';
import { Stack } from '@/Components/layout';
import type { CaixaUnifMessage, CaixaUnifThread } from './helpers';

interface Props {
  open: boolean;
  onClose: () => void;
  thread: CaixaUnifThread;
  messages: CaixaUnifMessage[];
}

export default function InboxPresenterMode({ open, onClose, thread, messages }: Props) {
  useEffect(() => {
    if (!open) return;
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose();
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  const visible = messages.filter(m => !m.is_internal_note);

  return (
    <div
      className="fixed inset-0 z-50 bg-background overflow-y-auto"
      role="dialog"
      aria-modal="true"
      aria-label="Modo apresentação da conversa"
      data-testid="caixa-unif-presenter"
    >
      <div className="max-w-2xl mx-auto px-6 py-8">
        <Stack gap={1} className="mb-6">
          <b className="text-[18px] font-semibold">{thread.contact_name || 'Conversa'}</b>
          <small className="text-[12px] text-muted-foreground">
            Modo apresentação — Esc pra sair · sem notas internas nem dados técnicos
          </small>
        </Stack>
        <Stack gap={2}>
          {visible.map(m => (
            <div
              key={m.id}
              className={cn(
                'max-w-[80%] px-4 py-2.5 rounded-lg text-[14px] leading-relaxed whitespace-pre-wrap break-words',
                m.direction === 'inbound'
                  ? 'self-start mr-auto bg-card border'
                  : 'self-end ml-auto',
              )}
              style={m.direction === 'outbound'
                ? { background: 'oklch(0.85 0.10 145)', color: 'oklch(0.18 0.10 145)' }
                : undefined}
            >
              {m.body || (m.media_url ? '[mídia]' : '')}
            </div>
          ))}
        </Stack>
      </div>
      <button
        type="button"
        onClick={onClose}
        className="fixed top-4 right-4 inline-flex items-center gap-1.5 px-3 py-1.5 text-[12px] font-medium bg-card border rounded-full hover:bg-muted transition-colors"
        title="Sair do modo apresentação (Esc)"
        data-testid="caixa-unif-presenter-close"
      >
        <X size={13} aria-hidden /> Sair (Esc)
      </button>
    </div>
  );
}
