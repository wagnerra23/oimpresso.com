// Onda 12 v9,75 — Overlay árvore de decisão guiada (4 problemas comuns).
import { useEffect, useMemo, useState } from 'react';
import { Banknote, CreditCard, User, Pause, X, ChevronRight, RefreshCcw } from 'lucide-react';
import { findTroubleshooter, type TroubleshooterId, type TroubleshooterIcon } from './troubleshooters-data';

const ICONS = { boleto: Banknote, card: CreditCard, user: User, pause: Pause } as const;
const HUE_BAR: Record<number, string> = { 60: 'bg-amber-400', 250: 'bg-blue-400', 25: 'bg-rose-400' };
const HUE_TAG: Record<number, string> = { 60: 'bg-amber-100 text-amber-800', 250: 'bg-blue-100 text-blue-800', 25: 'bg-rose-100 text-rose-800' };

interface Props {
  troubleId: TroubleshooterId;
  onClose: () => void;
}

export default function TroubleshooterOverlay({ troubleId, onClose }: Props) {
  const trouble = useMemo(() => findTroubleshooter(troubleId), [troubleId]);
  const [stepIdx, setStepIdx] = useState(0);
  const [path, setPath] = useState<Array<{ q: string; answer: string }>>([]);

  useEffect(() => {
    setStepIdx(0);
    setPath([]);
  }, [troubleId]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  if (!trouble) return null;

  const step = trouble.steps[stepIdx];
  const Icon = ICONS[trouble.icon as TroubleshooterIcon] ?? User;
  const barCls = HUE_BAR[trouble.hue] ?? 'bg-zinc-300';
  const tagCls = HUE_TAG[trouble.hue] ?? 'bg-zinc-100 text-zinc-700';

  function pick(opt: { label: string; next: number }) {
    if (!step) return;
    setPath((p) => [...p, { q: step.q, answer: opt.label }]);
    setStepIdx(opt.next);
  }

  function restart() {
    setStepIdx(0);
    setPath([]);
  }

  return (
    <div
      role="dialog"
      aria-modal="true"
      onClick={onClose}
      className="fixed inset-0 z-50 flex items-start justify-center bg-zinc-900/60 backdrop-blur-sm pt-20"
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="relative w-full max-w-md max-h-[85vh] overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-zinc-200 flex flex-col"
      >
        <header className="flex items-center gap-3 border-b border-zinc-100 px-4 py-3">
          <Icon size={18} className="text-zinc-700" />
          <h2 className="flex-1 text-sm font-semibold text-zinc-900">{trouble.title}</h2>
          <button type="button" onClick={onClose} aria-label="Fechar" className="rounded p-1 hover:bg-zinc-100">
            <X size={16} className="text-zinc-500" />
          </button>
        </header>
        <div className={`h-1 w-full ${barCls}`} aria-hidden="true" />
        <div className="flex-1 overflow-y-auto px-4 py-4">
          {path.length > 0 && (
            <ol className="mb-4 space-y-1.5 text-xs text-zinc-500">
              {path.map((p, i) => (
                <li key={i} className="flex gap-2">
                  <span className="text-zinc-400">{i + 1}.</span>
                  <span><b className="text-zinc-700">{p.q}</b> → {p.answer}</span>
                </li>
              ))}
            </ol>
          )}
          {step && step.opts && (
            <>
              <div className="mb-3 text-sm font-medium text-zinc-900">{step.q}</div>
              <ul className="space-y-2">
                {step.opts.map((opt) => (
                  <li key={opt.label}>
                    <button
                      type="button"
                      onClick={() => pick(opt)}
                      className="flex w-full items-center justify-between gap-2 rounded-lg border border-zinc-200 px-3 py-2 text-left text-sm text-zinc-700 hover:bg-violet-50 hover:border-violet-300"
                    >
                      {opt.label}
                      <ChevronRight size={14} className="text-zinc-400" />
                    </button>
                  </li>
                ))}
              </ul>
            </>
          )}
          {step && step.final && (
            <div>
              <div className={`mb-2 inline-block rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide ${tagCls}`}>
                Ação recomendada
              </div>
              <p className="text-sm leading-relaxed text-zinc-800">{step.final}</p>
              <div className="mt-4 flex gap-2">
                <button
                  type="button"
                  onClick={restart}
                  className="inline-flex items-center gap-1 rounded-lg border border-zinc-200 px-3 py-1.5 text-xs text-zinc-700 hover:bg-zinc-50"
                >
                  <RefreshCcw size={12} /> Recomeçar
                </button>
                <button
                  type="button"
                  onClick={onClose}
                  className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700"
                >
                  Concluído
                </button>
              </div>
            </div>
          )}
        </div>
        <footer className="border-t border-zinc-100 px-4 py-2 text-[10px] text-zinc-400">
          Esc fecha
        </footer>
      </div>
    </div>
  );
}
