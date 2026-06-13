import * as React from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { AlertTriangle, Clock, TrendingUp, GhostIcon } from 'lucide-react';
import type { KbNode } from '../_lib/types';
import { fmtRelative, isNodeOutdated } from '../_lib/helpers';
import { cn } from '@/Lib/utils';

/**
 * HealthPanel — modal de saúde do KB (4 quadrantes)
 *
 * Port do `kb-page.jsx::HealthPanel` (Cowork [CC]).
 *
 * Quadrantes:
 *  - bad: marcados como desatualizados (status=outdated OU outdated_votes>=2)
 *  - warn: sem atualização há mais de 30 dias
 *  - ok: top-5 mais lidos do mês
 *  - default: solitários (reads_count < 50 e !pinned)
 */
interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  nodes: KbNode[];
  onPickNode: (id: number) => void;
}

export default function HealthPanel({ open, onOpenChange, nodes, onPickNode }: Props) {
  const buckets = React.useMemo(() => {
    const outdated = nodes.filter(isNodeOutdated);
    const now = Date.now();
    const stale = nodes
      .filter((n) => {
        if (isNodeOutdated(n)) return false;
        if (!n.updated_at) return false;
        const days = (now - new Date(n.updated_at).getTime()) / 86_400_000;
        return days > 30;
      })
      .slice(0, 6);
    const popular = [...nodes].sort((a, b) => b.reads_count - a.reads_count).slice(0, 5);
    const lonely = nodes.filter((n) => n.reads_count < 50 && !n.pinned).slice(0, 5);
    return { outdated, stale, popular, lonely };
  }, [nodes]);

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <small className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
            Diagnóstico
          </small>
          <DialogTitle>Saúde do KB</DialogTitle>
          <DialogDescription>
            Visão dos artigos que precisam de atenção, dos mais consumidos e dos
            esquecidos.
          </DialogDescription>
        </DialogHeader>
        <div className="grid sm:grid-cols-2 gap-3">
          <Quadrant
            title="Marcados como desatualizados"
            tone="bad"
            icon={<AlertTriangle size={14} />}
            items={buckets.outdated}
            onPickNode={(id) => {
              onPickNode(id);
              onOpenChange(false);
            }}
          />
          <Quadrant
            title="Sem atualização há mais de 30 dias"
            tone="warn"
            icon={<Clock size={14} />}
            items={buckets.stale}
            onPickNode={(id) => {
              onPickNode(id);
              onOpenChange(false);
            }}
          />
          <Quadrant
            title="Mais lidos do mês"
            tone="ok"
            icon={<TrendingUp size={14} />}
            items={buckets.popular}
            onPickNode={(id) => {
              onPickNode(id);
              onOpenChange(false);
            }}
          />
          <Quadrant
            title="Solitários (pouco vistos)"
            tone="default"
            icon={<GhostIcon size={14} />}
            items={buckets.lonely}
            onPickNode={(id) => {
              onPickNode(id);
              onOpenChange(false);
            }}
          />
        </div>
      </DialogContent>
    </Dialog>
  );
}

function Quadrant({
  title,
  tone,
  icon,
  items,
  onPickNode,
}: {
  title: string;
  tone: 'bad' | 'warn' | 'ok' | 'default';
  icon: React.ReactNode;
  items: KbNode[];
  onPickNode: (id: number) => void;
}) {
  const toneClass = {
    bad: 'border-destructive/30 bg-destructive/5',
    warn: 'border-warning/30 bg-warning/5',
    ok: 'border-success/30 bg-success/5',
    default: 'border-border bg-muted/20',
  };
  const titleClass = {
    bad: 'text-destructive',
    warn: 'text-warning-fg',
    ok: 'text-success-fg',
    default: 'text-muted-foreground',
  };

  return (
    <section
      className={cn(
        'rounded-md border px-3 py-2.5 flex flex-col',
        toneClass[tone],
      )}
    >
      <header className="flex items-center justify-between mb-2">
        <b className={cn('inline-flex items-center gap-1.5 text-[12px] font-semibold', titleClass[tone])}>
          {icon} {title}
        </b>
        <span className="font-mono text-[10.5px] text-muted-foreground">
          {items.length}
        </span>
      </header>
      <ul className="space-y-0.5 list-none m-0 p-0 flex-1">
        {items.length === 0 ? (
          <li className="text-[11px] text-muted-foreground italic">— nenhum —</li>
        ) : (
          items.map((n) => (
            <li key={n.id}>
              <button
                type="button"
                onClick={() => onPickNode(n.id)}
                className="w-full text-left rounded-md px-2 py-1 hover:bg-background/60"
              >
                <span className="block text-[12px] font-medium text-foreground truncate">
                  {n.title}
                </span>
                <span className="block text-[10px] text-muted-foreground">
                  {n.reads_count} leituras · {fmtRelative(n.updated_at)}
                </span>
              </button>
            </li>
          ))
        )}
      </ul>
    </section>
  );
}
