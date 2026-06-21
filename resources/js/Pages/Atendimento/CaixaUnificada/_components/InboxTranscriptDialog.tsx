// InboxTranscriptDialog — transcript print-friendly da conversa (Polish V2 §7
// · inbox-out.jsx). window.print() com CSS @media print isolando o conteúdo.
//
// Notas internas FICAM FORA por default (toggle consciente — transcript vai
// pro cliente/arquivo; nota interna é da equipe). Header Oimpresso simples.

import { useState } from 'react';
import { Printer } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Inline, Stack } from '@/Components/layout';
import type { CaixaUnifMessage, CaixaUnifThread } from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  thread: CaixaUnifThread;
  messages: CaixaUnifMessage[];
}

export default function InboxTranscriptDialog({ open, onOpenChange, thread, messages }: Props) {
  const [includeNotes, setIncludeNotes] = useState(false);
  const visible = messages.filter(m => includeNotes || !m.is_internal_note);

  function print() {
    window.print();
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg" data-testid="caixa-unif-transcript">
        {/* Esconde o resto do app no print — só o transcript sai no papel */}
        <style>{`@media print {
          body * { visibility: hidden; }
          [data-print-transcript], [data-print-transcript] * { visibility: visible; }
          [data-print-transcript] { position: absolute; inset: 0; padding: 24px; }
        }`}</style>
        <DialogHeader>
          <DialogTitle>Transcript da conversa</DialogTitle>
          <DialogDescription>
            Pronto pra imprimir/salvar como PDF (Ctrl+P usa o diálogo do navegador).
          </DialogDescription>
        </DialogHeader>

        <Inline gap={2} align="center" justify="between">
          <Inline gap={1} align="center">
            <Checkbox
              id="transcript-include-notes"
              checked={includeNotes}
              onCheckedChange={(v) => setIncludeNotes(v === true)}
              data-testid="caixa-unif-transcript-notes-toggle"
            />
            <label
              htmlFor="transcript-include-notes"
              className="text-[11.5px] text-muted-foreground cursor-pointer"
            >
              Incluir notas internas (padrão: fora — transcript é cliente-facing)
            </label>
          </Inline>
          <Button size="sm" onClick={print} className="gap-1.5" data-testid="caixa-unif-transcript-print">
            <Printer size={13} aria-hidden /> Imprimir / PDF
          </Button>
        </Inline>

        <div className="max-h-[55vh] overflow-y-auto border rounded-md p-4 bg-card" data-print-transcript>
          <Stack gap={1} className="border-b pb-2 mb-3">
            <b className="text-[14px] font-semibold">Oimpresso · Transcript de atendimento</b>
            <small className="text-[11px] text-muted-foreground">
              {thread.contact_name || thread.customer_external_id} · {thread.customer_external_id}
              {thread.channel_label ? ` · ${thread.channel_label}` : ''}
            </small>
          </Stack>
          <Stack gap={2}>
            {visible.map(m => (
              <Stack key={m.id} gap={0}>
                <small className="text-[10px] font-mono text-muted-foreground">
                  {new Date(m.created_at).toLocaleString('pt-BR')}
                  {' · '}
                  {m.is_internal_note
                    ? `NOTA INTERNA${m.sender_user_name ? ` (${m.sender_user_name})` : ''}`
                    : m.direction === 'inbound'
                      ? (thread.contact_name || 'Cliente')
                      : (m.sender_user_name || 'Atendimento')}
                </small>
                <span className="text-[12.5px] whitespace-pre-wrap break-words">
                  {m.body || (m.media_url ? '[mídia]' : '')}
                </span>
              </Stack>
            ))}
          </Stack>
        </div>
      </DialogContent>
    </Dialog>
  );
}
