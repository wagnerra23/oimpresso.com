import * as React from 'react';
import { Check, AlertTriangle, X, Info } from 'lucide-react';
import { kbLinkifyText } from '../_lib/helpers';
import type { KbBlock, KbCalloutTone } from '../_lib/types';
import { cn } from '@/Lib/utils';

/**
 * BlockRenderer — renderiza array de body_blocks (kb_nodes.body_blocks)
 *
 * Port consolidado do leitor do Cowork (`kb-page.jsx::ArticleReader body.map`)
 * + `kb-images-print.jsx::KBImageBlockView`.
 *
 * Suporta:
 *  - para (parágrafo) com linkify de #kb-N
 *  - h2 (renderizado como h3 visual — h2 da página é o título do artigo)
 *  - list (ol estilizada)
 *  - callout (4 tons: info|ok|warn|bad) com ícone
 *  - image (figure + alt + caption)
 */
interface Props {
  blocks: KbBlock[] | null;
  /** chamado quando user clica em link #kb-XXX dentro do conteúdo */
  onPickRef?: (ref: string) => void;
  /** opcional: por bloco, conteúdo extra (ex: comentários inline em ONDA 3) */
  renderAfterBlock?: (block: KbBlock, idx: number) => React.ReactNode;
  className?: string;
}

const calloutToneIcon: Record<KbCalloutTone, typeof Info> = {
  info: Info,
  ok: Check,
  warn: AlertTriangle,
  bad: X,
};

const calloutToneClass: Record<KbCalloutTone, string> = {
  info: 'border-blue-500/30 bg-blue-500/5 text-blue-900 dark:text-blue-100',
  ok: 'border-emerald-500/30 bg-emerald-500/5 text-emerald-900 dark:text-emerald-100',
  warn: 'border-amber-500/30 bg-amber-500/5 text-amber-900 dark:text-amber-100',
  bad: 'border-destructive/40 bg-destructive/5 text-foreground',
};

const calloutIconClass: Record<KbCalloutTone, string> = {
  info: 'text-blue-600 dark:text-blue-400',
  ok: 'text-emerald-600 dark:text-emerald-400',
  warn: 'text-amber-600 dark:text-amber-400',
  bad: 'text-destructive',
};

export default function BlockRenderer({
  blocks,
  onPickRef = () => {},
  renderAfterBlock,
  className,
}: Props) {
  if (!blocks || blocks.length === 0) {
    return (
      <p className="text-sm text-muted-foreground italic">
        Sem conteúdo ainda.
      </p>
    );
  }

  return (
    <div className={cn('kb-art-body space-y-4', className)}>
      {blocks.map((block, idx) => {
        let node: React.ReactNode = null;

        switch (block.kind) {
          case 'para':
            node = (
              <p className="text-[14px] leading-relaxed text-foreground/90">
                {kbLinkifyText(block.t, onPickRef)}
              </p>
            );
            break;

          case 'h2':
            node = (
              <h3 className="text-base font-semibold tracking-tight text-foreground mt-6 mb-2">
                {block.t}
              </h3>
            );
            break;

          case 'list':
            node = (
              <ol className="list-decimal pl-6 space-y-1.5 text-[14px] leading-relaxed text-foreground/90 marker:text-muted-foreground marker:font-mono marker:text-xs">
                {block.items.map((item, j) => (
                  <li key={j}>{kbLinkifyText(item, onPickRef)}</li>
                ))}
              </ol>
            );
            break;

          case 'callout': {
            const Icon = calloutToneIcon[block.tone];
            node = (
              <div
                role="note"
                className={cn(
                  'flex gap-3 rounded-md border-l-2 border px-3 py-2.5 text-[13.5px] leading-relaxed',
                  calloutToneClass[block.tone],
                )}
              >
                <Icon
                  size={16}
                  className={cn('shrink-0 mt-0.5', calloutIconClass[block.tone])}
                  aria-hidden
                />
                <p className="flex-1 m-0">
                  {kbLinkifyText(block.t, onPickRef)}
                </p>
              </div>
            );
            break;
          }

          case 'image':
            node = block.src ? (
              <figure className="my-4">
                <img
                  src={block.src}
                  alt={block.alt ?? ''}
                  className="rounded-md border border-border max-w-full h-auto"
                />
                {block.caption && (
                  <figcaption className="text-xs text-muted-foreground mt-2 text-center italic">
                    {block.caption}
                  </figcaption>
                )}
              </figure>
            ) : (
              <div className="my-4 flex items-center justify-center rounded-md border border-dashed border-border bg-muted/30 p-6 text-xs text-muted-foreground">
                imagem (sem fonte)
              </div>
            );
            break;

          default:
            node = null;
        }

        if (!node) return null;
        return (
          <React.Fragment key={idx}>
            {node}
            {renderAfterBlock?.(block, idx)}
          </React.Fragment>
        );
      })}
    </div>
  );
}
