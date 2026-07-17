import * as React from 'react';
import { ChevronLeft, Check } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import type { KbNode, KbPath } from '../_lib/types';
import { useKbPathProgress } from '../_lib/useKbPathProgress';
import { cn } from '@/Lib/utils';

/**
 * PathsDialog — drawer lateral com trilhas de aprendizado
 *
 * Port do `kb-paths.jsx::KBPathsDialog` (Cowork [CC]) usando shadcn `Sheet`.
 *
 * Estrutura:
 *  - Lista de trilhas (cards) com barra de progresso por trilha
 *  - Click em trilha → detalhe com lista numerada de steps + checkbox done
 *  - Click em step → abre o nó (e fecha drawer)
 *  - Back button volta pra lista
 *
 * Progresso salvo em localStorage (useKbPathProgress).
 */
interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  paths: KbPath[];
  nodes: KbNode[];
  onPickNode: (id: number) => void;
}

export default function PathsDialog({
  open,
  onOpenChange,
  paths,
  nodes,
  onPickNode,
}: Props) {
  const [activePathId, setActivePathId] = React.useState<number | null>(null);
  const { toggleStep, isStepDone, getStats } = useKbPathProgress();

  // Reset detalhe ao fechar
  React.useEffect(() => {
    if (!open) setActivePathId(null);
  }, [open]);

  const activePath = activePathId
    ? paths.find((p) => p.id === activePathId)
    : null;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-md p-0 flex flex-col">
        {activePath ? (
          <PathDetail
            path={activePath}
            nodes={nodes}
            onBack={() => setActivePathId(null)}
            onPickNode={(id) => {
              onPickNode(id);
              onOpenChange(false);
            }}
            isStepDone={(stepIdx) => isStepDone(activePath.id, stepIdx)}
            onToggleStep={(stepIdx) => toggleStep(activePath.id, stepIdx)}
          />
        ) : (
          <>
            <SheetHeader className="px-5 py-3 border-b border-border space-y-0.5">
              <small className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
                Aprendizado guiado
              </small>
              <SheetTitle className="text-[16px]">
                Trilhas de conhecimento
              </SheetTitle>
              <SheetDescription className="text-[11.5px]">
                Sequências curadas pelo time. Progresso salvo neste dispositivo.
              </SheetDescription>
            </SheetHeader>
            <div className="flex-1 overflow-y-auto px-4 py-3 space-y-2">
              {paths.length === 0 ? (
                <p className="text-[12.5px] text-muted-foreground italic text-center py-12">
                  Nenhuma trilha publicada ainda.
                </p>
              ) : (
                paths.map((p) => {
                  const stats = getStats(p.id, p.steps?.length ?? 0);
                  return (
                    <button
                      key={p.id}
                      type="button"
                      onClick={() => setActivePathId(p.id)}
                      className="kb-hue-border-l w-full text-left rounded-md border border-border border-l-[3px] bg-card px-3 py-2.5 hover:shadow-sm transition-all"
                      style={{ '--kb-hue': p.hue } as React.CSSProperties}
                    >
                      {p.audience && (
                        <small
                          className="kb-hue-label block text-[10.5px] font-medium"
                          style={{ '--kb-hue': p.hue } as React.CSSProperties}
                        >
                          {p.audience}
                        </small>
                      )}
                      <h4 className="m-0 mt-0.5 text-[13.5px] font-semibold text-foreground">
                        {p.title}
                      </h4>
                      {p.description && (
                        <p className="m-0 mt-1 text-[11.5px] text-muted-foreground line-clamp-2">
                          {p.description}
                        </p>
                      )}
                      <div className="mt-2 flex items-center gap-2">
                        <div className="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
                          <div
                            className="kb-hue-bar h-full transition-all"
                            style={{ width: `${stats.pct}%` }}
                          />
                        </div>
                        <span className="font-mono text-[10.5px] text-muted-foreground tabular-nums">
                          {stats.done}/{stats.total}
                        </span>
                      </div>
                    </button>
                  );
                })
              )}
            </div>
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

function PathDetail({
  path,
  nodes,
  onBack,
  onPickNode,
  isStepDone,
  onToggleStep,
}: {
  path: KbPath;
  nodes: KbNode[];
  onBack: () => void;
  onPickNode: (id: number) => void;
  isStepDone: (stepIdx: number) => boolean;
  onToggleStep: (stepIdx: number) => void;
}) {
  const steps = path.steps ?? [];
  const doneCount = steps.filter((_, i) => isStepDone(i)).length;
  const pct = steps.length > 0 ? Math.round((doneCount / steps.length) * 100) : 0;

  return (
    <>
      <SheetHeader className="px-5 py-3 border-b border-border space-y-0">
        <button
          type="button"
          onClick={onBack}
          className="self-start inline-flex items-center gap-1 text-[11.5px] text-muted-foreground hover:text-foreground mb-1"
        >
          <ChevronLeft size={12} /> Trilhas
        </button>
        <SheetTitle
          className="kb-hue-title text-[16px]"
          style={{ '--kb-hue': path.hue } as React.CSSProperties}
        >
          {path.title}
        </SheetTitle>
        {path.description && (
          <SheetDescription className="text-[11.5px]">
            {path.description}
          </SheetDescription>
        )}
        <div className="mt-2 flex items-center gap-2">
          <div className="flex-1 h-1.5 rounded-full bg-muted overflow-hidden">
            <div
              className="kb-hue-bar h-full transition-all"
              style={{ width: `${pct}%`, '--kb-hue': path.hue } as React.CSSProperties}
            />
          </div>
          <span className="font-mono text-[10.5px] text-muted-foreground tabular-nums">
            {doneCount}/{steps.length} · {pct}%
          </span>
        </div>
      </SheetHeader>
      <ol className="flex-1 overflow-y-auto px-4 py-3 space-y-2 list-none m-0">
        {steps.map((step, i) => {
          const node = nodes.find((n) => n.id === step.node_id);
          if (!node) return null;
          const done = isStepDone(i);
          return (
            <li key={step.id ?? i} className="flex items-start gap-2.5">
              <button
                type="button"
                onClick={() => onToggleStep(i)}
                aria-pressed={done}
                className={cn(
                  'mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-2 text-[10.5px] font-mono font-bold transition-colors',
                  done
                    ? 'border-transparent bg-success-fg text-white dark:bg-success/30 dark:text-success-foreground'
                    : 'border-border bg-card text-muted-foreground hover:border-primary',
                )}
                title={done ? 'Marcar como não feito' : 'Marcar como feito'}
              >
                {done ? <Check size={12} /> : i + 1}
              </button>
              <div className="flex-1 min-w-0">
                <button
                  type="button"
                  onClick={() => onPickNode(node.id)}
                  className="w-full text-left rounded-md p-2 -m-2 hover:bg-muted"
                >
                  <div className="flex items-center gap-2 flex-wrap">
                    <b className="text-[12.5px] font-semibold text-foreground">
                      {node.title}
                    </b>
                    <span className="text-[9.5px] font-semibold lowercase text-muted-foreground bg-muted px-1.5 py-px rounded-sm">
                      {step.step_type}
                    </span>
                  </div>
                  {step.note && (
                    <p className="m-0 mt-0.5 text-[11.5px] text-muted-foreground">
                      {step.note}
                    </p>
                  )}
                  <div className="mt-1 text-[10.5px] text-muted-foreground">
                    {node.read_time_min ? `${node.read_time_min} min` : '—'}
                    {node.author_name && ` · ${node.author_name}`}
                  </div>
                </button>
              </div>
            </li>
          );
        })}
      </ol>
    </>
  );
}
