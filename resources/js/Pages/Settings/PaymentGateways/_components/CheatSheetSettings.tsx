// CheatSheetSettings.tsx — overlay atalhos teclado Settings
import { useEffect } from 'react';
import { Btn } from '../../../Financeiro/Cobranca/_components/atoms';

const ATALHOS = [
  { k: 'N',     d: 'Novo gateway' },
  { k: 'J / ↓', d: 'Próxima linha' },
  { k: 'K / ↑', d: 'Linha anterior' },
  { k: 'Enter', d: 'Abrir gateway focado' },
  { k: 'Esc',   d: 'Fechar drawer/modal' },
  { k: '?',     d: 'Mostrar/ocultar atalhos' },
];

export default function CheatSheetSettings({ onClose }: { onClose: () => void }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape' || e.key === '?') {
        e.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-40 grid place-items-center p-6" onClick={onClose} role="dialog" aria-modal="true" aria-label="Atalhos do teclado">
      <div className="absolute inset-0 bg-stone-900/40" />
      <div className="relative w-[460px] bg-white rounded-lg shadow-2xl border border-stone-200 p-5" onClick={e => e.stopPropagation()}>
        <h3 className="text-[14px] font-semibold text-stone-900 mb-3">Atalhos · Gateways</h3>
        <div className="space-y-1.5 text-[12.5px]">
          {ATALHOS.map(({ k, d }) => (
            <div key={k} className="flex items-center justify-between py-1 border-b border-stone-100 last:border-b-0">
              <span className="text-stone-700">{d}</span>
              <kbd className="text-[10.5px] font-mono px-1.5 py-0.5 border border-stone-300 rounded bg-stone-50 text-stone-700">{k}</kbd>
            </div>
          ))}
        </div>
        <Btn variant="outline" className="mt-4 w-full justify-center" onClick={onClose}>Fechar (Esc)</Btn>
      </div>
    </div>
  );
}
