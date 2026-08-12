// MsgComments — notas internas por-mensagem abaixo da bolha (port inbox-cur MsgCommentWrap).
// "+" hover-revealed via `group/msg` no Stack da mensagem → resting state da Caixa = diff=0
// (Caixa é o ouro). Notas em localStorage per-user (useMsgComments). "só equipe vê".

import { useState } from 'react';
import { MessageSquarePlus, X } from 'lucide-react';
import { Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import type { MsgComment } from './useMsgComments';

interface Props {
  side: 'inbound' | 'outbound';
  comments: MsgComment[];
  onAdd: (text: string) => void;
  onRemove: (i: number) => void;
}

export default function MsgComments({ side, comments, onAdd, onRemove }: Props) {
  const [open, setOpen] = useState(false);
  const [text, setText] = useState('');
  const submit = () => { const t = text.trim(); if (!t) return; onAdd(t); setText(''); setOpen(false); };
  const hasAny = comments.length > 0;

  return (
    <Stack
      gap={1}
      align={side === 'inbound' ? 'start' : 'end'}
      className={cn('mt-0.5 max-w-[68%]', side === 'inbound' ? 'self-start' : 'self-end')}
    >
      <button
        type="button"
        onClick={() => setOpen(o => !o)}
        className={cn(
          'inline-flex items-center gap-1 text-[10px] font-medium text-muted-foreground hover:text-foreground transition-opacity',
          hasAny || open ? 'opacity-100' : 'opacity-0 group-hover/msg:opacity-100',
        )}
        data-testid="caixa-unif-msg-comment-add"
      >
        <MessageSquarePlus size={11} aria-hidden />
        {hasAny ? `${comments.length} nota${comments.length > 1 ? 's' : ''} da equipe` : 'Anotar (só equipe)'}
      </button>

      {(hasAny || open) && (
        <Stack gap={1} className="w-full rounded-md border border-dashed border-warning/40 bg-warning-soft px-2 py-1.5">
          {comments.map((c, i) => (
            <Stack key={i} gap={0}>
              <Inline gap={1} align="center" className="text-[9.5px] text-warning-fg/80">
                <b className="font-semibold">{c.author}</b>
                <span className="font-mono">{c.when}</span>
                <button type="button" onClick={() => onRemove(i)} className="ml-auto hover:text-destructive-fg" aria-label="Remover nota">
                  <X size={10} aria-hidden />
                </button>
              </Inline>
              <p className="text-[11.5px] text-foreground whitespace-pre-wrap">{c.text}</p>
            </Stack>
          ))}
          {open && (
            <Stack gap={1}>
              <textarea
                autoFocus
                value={text}
                onChange={e => setText(e.target.value)}
                onKeyDown={e => {
                  if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) { e.preventDefault(); submit(); }
                  if (e.key === 'Escape') { setOpen(false); setText(''); }
                }}
                placeholder="Anotar essa mensagem… (⌘↵ envia)"
                rows={2}
                className="w-full resize-none rounded border bg-card px-2 py-1 text-[11.5px]"
              />
              <Inline gap={2} align="center" justify="end">
                <button type="button" onClick={() => { setOpen(false); setText(''); }} className="text-[10.5px] text-muted-foreground hover:text-foreground">Cancelar</button>
                <button type="button" onClick={submit} disabled={!text.trim()} className="text-[10.5px] font-semibold text-warning-fg disabled:opacity-40">Comentar</button>
              </Inline>
            </Stack>
          )}
        </Stack>
      )}
    </Stack>
  );
}
