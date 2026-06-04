// Onda 13 v9,75 — Tour onboarding 1ª vez (4 steps).
import { useEffect, useState } from 'react';
import { X, ChevronLeft, ChevronRight } from 'lucide-react';

export const TOUR_DONE_KEY = 'oimpresso.rec.tour-done';

const STEPS: Array<{ title: string; body: string }> = [
  { title: '3 colunas canônicas', body: 'Filtros · Lista · Drawer detalhe — tudo cabe na tela sem scroll horizontal.' },
  { title: '5 estados visuais', body: 'em dia · retentando (com pips N/M) · falhou Nx · pausada · cancelada (strike).' },
  { title: 'Atalhos teclado', body: 'J/K navegar · ⌘K palette · ? cheatsheet · B favoritar · ⇧P apresentação.' },
  { title: 'Ações executáveis', body: 'No drawer: Pausar / Cancelar / Reativar com confirmação. Diagnosticar abre árvore de decisão.' },
];

interface Props { onClose: () => void }

export default function TourOnboarding({ onClose }: Props) {
  const [step, setStep] = useState(0);

  function close(setDone: boolean) {
    if (setDone) {
      try { localStorage.setItem(TOUR_DONE_KEY, '1'); } catch { /* ignore */ }
    }
    onClose();
  }

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') close(false);
      else if (e.key === 'ArrowRight' && step < STEPS.length - 1) setStep(step + 1);
      else if (e.key === 'ArrowLeft' && step > 0) setStep(step - 1);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step]);

  const cur = STEPS[step] ?? STEPS[0]!;
  const isLast = step === STEPS.length - 1;

  return (
    <div role="dialog" aria-modal="true" className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/30 backdrop-blur-sm">
      <div className="w-full max-w-md rounded-lg bg-white shadow-2xl ring-1 ring-stone-200">
        <header className="flex items-center justify-between border-b border-stone-100 px-4 py-3">
          <div className="flex items-center gap-2 text-xs text-stone-500">
            <span className="rounded bg-primary/10 px-2 py-0.5 font-semibold text-primary">{step + 1}/{STEPS.length}</span>
            <span>Tour rápido</span>
          </div>
          <button type="button" onClick={() => close(false)} aria-label="Fechar" className="rounded p-1 hover:bg-stone-100">
            <X size={14} className="text-stone-500" />
          </button>
        </header>
        <div className="px-6 py-6">
          <h3 className="text-lg font-bold text-stone-900">{cur.title}</h3>
          <p className="mt-2 text-sm text-stone-600">{cur.body}</p>
        </div>
        <footer className="flex items-center justify-between gap-2 border-t border-stone-100 px-4 py-3">
          <button type="button" onClick={() => close(true)} className="text-xs text-stone-500 hover:text-stone-700">
            Não mostrar mais
          </button>
          <div className="flex items-center gap-2">
            <button type="button" onClick={() => setStep(Math.max(0, step - 1))} disabled={step === 0} className="inline-flex items-center gap-1 rounded-lg border border-stone-200 px-3 py-1.5 text-xs text-stone-700 hover:bg-stone-50 disabled:opacity-40">
              <ChevronLeft size={12} /> Anterior
            </button>
            {!isLast && (
              <button type="button" onClick={() => setStep(step + 1)} className="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:opacity-90">
                Próximo <ChevronRight size={12} />
              </button>
            )}
            {isLast && (
              <button type="button" onClick={() => close(true)} className="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:opacity-90">
                Começar
              </button>
            )}
          </div>
        </footer>
      </div>
    </div>
  );
}
