import * as React from 'react';
import { ChevronLeft, X, Sparkles } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
} from '@/Components/ui/dialog';
import type { MockTroubleshooter, MockTreeStepFlat } from '../_lib/mockData';
import { kbLinkifyText } from '../_lib/helpers';
import { cn } from '@/Lib/utils';

/**
 * TroubleshooterDialog — diagnóstico guiado Q→Sim/Não→Fix
 *
 * Port do `kb-trouble-lib.jsx::KBTroubleshooterDialog` (Cowork [CC]).
 *
 * Estrutura:
 *  - Lista de troubleshooters (cards) com hue
 *  - Click → wizard: pergunta + botões Sim/Não + histórico + dots progress
 *  - Resposta SIM/NÃO leva pra próxima pergunta OU fix
 *  - Fix mostra solução com kbLinkifyText (citações #kb-NNN)
 *  - Fallback "Perguntar IA" no rodapé da lista
 *
 * NOTE V1: usa MockTroubleshooter flat_steps. Quando Agent A entregar JSON real
 * de kb_decision_trees + kb_decision_tree_steps, normalizar pra mesma estrutura
 * em _lib/adapter.ts (TODO[CL]).
 */

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  troubleshooters: MockTroubleshooter[];
  onPickByRef: (ref: string) => void;
  onAskAI?: () => void;
  /** TODO[CL]: passar callback de criar novo troubleshoot quando ONDA 3 entregar editor */
  onCreateNew?: () => void;
}

interface AnswerEntry {
  question: string;
  answer: boolean;
}

export default function TroubleshooterDialog({
  open,
  onOpenChange,
  troubleshooters,
  onPickByRef,
  onAskAI,
}: Props) {
  const [activeId, setActiveId] = React.useState<number | null>(null);
  const [position, setPosition] = React.useState(0);
  const [path, setPath] = React.useState<AnswerEntry[]>([]);
  const [fix, setFix] = React.useState<string | null>(null);

  // Reset ao fechar
  React.useEffect(() => {
    if (!open) {
      setActiveId(null);
      setPosition(0);
      setPath([]);
      setFix(null);
    }
  }, [open]);

  const active = activeId
    ? troubleshooters.find((t) => t.id === activeId)
    : null;

  const backToList = () => {
    setActiveId(null);
    setPosition(0);
    setPath([]);
    setFix(null);
  };

  const restart = () => {
    setPosition(0);
    setPath([]);
    setFix(null);
  };

  const answer = (ans: boolean) => {
    if (!active) return;
    const step = active.flat_steps.find((s) => s.position === position);
    if (!step) return;
    setPath((p) => [...p, { question: step.question, answer: ans }]);
    if (ans) {
      if (step.yes_fix) setFix(step.yes_fix);
      else if (typeof step.yes_next === 'number') setPosition(step.yes_next);
    } else {
      if (step.no_fix) setFix(step.no_fix);
      else if (typeof step.no_next === 'number') setPosition(step.no_next);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg p-0 gap-0">
        {!active ? (
          <ListView
            troubleshooters={troubleshooters}
            onPick={setActiveId}
            onAskAI={() => {
              onOpenChange(false);
              onAskAI?.();
            }}
          />
        ) : (
          <WizardView
            tree={active}
            position={position}
            path={path}
            fix={fix}
            onAnswer={answer}
            onRestart={restart}
            onBackToList={backToList}
            onClose={() => onOpenChange(false)}
            onPickByRef={onPickByRef}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

function ListView({
  troubleshooters,
  onPick,
  onAskAI,
}: {
  troubleshooters: MockTroubleshooter[];
  onPick: (id: number) => void;
  onAskAI?: () => void;
}) {
  return (
    <>
      <DialogHeader className="px-5 py-3 border-b border-border space-y-0.5">
        <small className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground">
          Diagnóstico guiado
        </small>
        <DialogTitle className="text-[16px]">
          Troubleshooter — escolha o problema
        </DialogTitle>
        <DialogDescription className="text-[11.5px]">
          Responda Sim ou Não às perguntas até chegar na solução sugerida.
        </DialogDescription>
      </DialogHeader>
      <div className="max-h-[60vh] overflow-y-auto px-4 py-3 space-y-2">
        {troubleshooters.length === 0 ? (
          <p className="text-[12.5px] text-muted-foreground italic text-center py-8">
            Nenhum troubleshooter publicado.
          </p>
        ) : (
          troubleshooters.map((t) => (
            <button
              key={t.id}
              type="button"
              onClick={() => onPick(t.id)}
              className="kb-hue-border-l w-full text-left rounded-md border border-border border-l-[3px] bg-card px-3 py-2.5 hover:shadow-sm transition-all"
              style={{ '--kb-hue': t.hue } as React.CSSProperties}
            >
              {t.equip && (
                <small
                  className="kb-hue-label block text-[10.5px] font-medium"
                  style={{ '--kb-hue': t.hue } as React.CSSProperties}
                >
                  {t.equip !== '—' ? t.equip : 'fiscal'}
                </small>
              )}
              <h4 className="m-0 mt-0.5 text-[13.5px] font-semibold text-foreground">
                {t.title}
              </h4>
              {t.when_to_use && (
                <p className="m-0 mt-1 text-[11.5px] text-muted-foreground">
                  Use quando {t.when_to_use}.
                </p>
              )}
              <span className="mt-1.5 inline-block text-[10.5px] font-mono text-muted-foreground">
                {t.flat_steps.length} perguntas
              </span>
            </button>
          ))
        )}
      </div>
      {onAskAI && (
        <footer className="border-t border-border px-4 py-2.5 flex items-center justify-between gap-2">
          <small className="text-[11px] text-muted-foreground">
            Não achou seu caso?
          </small>
          <button
            type="button"
            onClick={onAskAI}
            className="inline-flex items-center gap-1.5 text-[11.5px] text-primary hover:underline"
          >
            <Sparkles size={11} /> Perguntar à IA
          </button>
        </footer>
      )}
    </>
  );
}

function WizardView({
  tree,
  position,
  path,
  fix,
  onAnswer,
  onRestart,
  onBackToList,
  onClose,
  onPickByRef,
}: {
  tree: MockTroubleshooter;
  position: number;
  path: AnswerEntry[];
  fix: string | null;
  onAnswer: (ans: boolean) => void;
  onRestart: () => void;
  onBackToList: () => void;
  onClose: () => void;
  onPickByRef: (ref: string) => void;
}) {
  const current = tree.flat_steps.find(
    (s: MockTreeStepFlat) => s.position === position,
  );

  return (
    <>
      <DialogHeader className="px-5 py-3 border-b border-border space-y-0">
        <button
          type="button"
          onClick={onBackToList}
          className="self-start inline-flex items-center gap-1 text-[11.5px] text-muted-foreground hover:text-foreground mb-1"
        >
          <ChevronLeft size={12} /> Troubleshooters
          <span className="mx-1 text-border">·</span>
          <span>{tree.equip !== '—' ? tree.equip : 'fiscal'}</span>
        </button>
        <DialogTitle className="text-[16px]">{tree.title}</DialogTitle>
      </DialogHeader>

      <div className="px-5 py-4 space-y-3 max-h-[60vh] overflow-y-auto">
        {/* histórico */}
        {path.length > 0 && (
          <div className="space-y-1 rounded-md bg-muted/40 px-3 py-2">
            {path.map((p, i) => (
              <div
                key={i}
                className="flex items-start justify-between gap-2 text-[11.5px]"
              >
                <span className="text-muted-foreground line-clamp-1 flex-1">
                  {p.question}
                </span>
                <span
                  className={cn(
                    'font-semibold shrink-0',
                    p.answer
                      ? 'text-success'
                      : 'text-warning-fg',
                  )}
                >
                  {p.answer ? 'Sim' : 'Não'}
                </span>
              </div>
            ))}
          </div>
        )}

        {!fix && current ? (
          <>
            <div className="flex items-start gap-3">
              <span
                className="kb-hue-chip inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[12px] font-bold font-mono"
                style={{ '--kb-hue': tree.hue } as React.CSSProperties}
              >
                {path.length + 1}
              </span>
              <p className="m-0 pt-1 text-[14.5px] leading-relaxed text-foreground">
                {current.question}
              </p>
            </div>
            <div className="flex items-center gap-2 pt-1">
              <button
                type="button"
                onClick={() => onAnswer(true)}
                className="flex-1 rounded-md bg-success-fg hover:bg-success-fg/90 dark:bg-success/30 dark:hover:bg-success/40 px-3 py-2 text-sm font-semibold text-white dark:text-success-foreground"
              >
                Sim
              </button>
              <button
                type="button"
                onClick={() => onAnswer(false)}
                className="flex-1 rounded-md bg-warning-fg hover:bg-warning-fg/90 dark:bg-warning/30 dark:hover:bg-warning/40 px-3 py-2 text-sm font-semibold text-white dark:text-warning-foreground"
              >
                Não
              </button>
            </div>
            <div className="flex justify-center gap-1">
              {tree.flat_steps.map((_, i) => (
                <span
                  key={i}
                  className={cn(
                    'h-1.5 w-1.5 rounded-full transition-colors',
                    i <= position
                      ? 'kb-hue-step-on opacity-100'
                      : 'bg-border opacity-30',
                  )}
                  style={{ '--kb-hue': tree.hue } as React.CSSProperties}
                  aria-hidden
                />
              ))}
            </div>
          </>
        ) : (
          <div
            className="kb-fix-box rounded-md border px-3 py-2.5"
            style={{ '--kb-hue': tree.hue } as React.CSSProperties}
          >
            <small className="kb-hue-label block text-[10px] font-bold uppercase tracking-wider mb-1">
              Solução sugerida
            </small>
            <p className="m-0 text-[13.5px] leading-relaxed text-foreground">
              {kbLinkifyText(fix, onPickByRef)}
            </p>
          </div>
        )}
      </div>

      <footer className="border-t border-border px-4 py-2.5 flex items-center gap-2">
        {fix && (
          <button
            type="button"
            onClick={onRestart}
            className="text-[11.5px] text-muted-foreground hover:text-foreground"
          >
            Recomeçar
          </button>
        )}
        <button
          type="button"
          onClick={onBackToList}
          className="text-[11.5px] text-muted-foreground hover:text-foreground"
        >
          Outro problema
        </button>
        <div className="ml-auto">
          <button
            type="button"
            onClick={onClose}
            className={cn(
              'rounded-md px-3 py-1 text-[11.5px] font-medium',
              fix
                ? 'bg-primary text-primary-foreground hover:bg-primary/90'
                : 'text-muted-foreground hover:bg-muted',
            )}
          >
            {fix ? 'Resolvi, obrigado' : (
              <span className="inline-flex items-center gap-1">
                <X size={11} /> Fechar
              </span>
            )}
          </button>
        </div>
      </footer>
    </>
  );
}
