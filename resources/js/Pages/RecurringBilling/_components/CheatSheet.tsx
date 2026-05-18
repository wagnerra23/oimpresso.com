// Onda 18 v9,75 — Overlay flutuante atalhos teclado (atalho `?`).
import { useEffect } from 'react';
import { Keyboard, X } from 'lucide-react';

const SHORTCUTS: Array<[string, string]> = [
  ['J / K', 'navegar lista'],
  ['↵', 'abrir detalhe'],
  ['B', 'favoritar / desfavoritar'],
  ['R', 'retentar cobrança'],
  ['P', 'pausar / reativar'],
  ['E', 'editar plano'],
  ['N', 'nova assinatura'],
  ['/', 'focar busca'],
  ['⌘K', 'command palette'],
  ['⇧P', 'modo apresentação'],
  ['⇧E', 'imprimir extrato'],
  ['1 2 3 4', 'alternar sub-rotas'],
  ['Esc', 'fechar'],
];

interface Props { onClose: () => void }

export default function CheatSheet({ onClose }: Props) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div
      role="dialog"
      aria-modal="true"
      onClick={onClose}
      className="fixed inset-0 z-50 flex items-center justify-center bg-zinc-900/40 backdrop-blur-sm"
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="w-full max-w-sm rounded-2xl bg-white shadow-xl ring-1 ring-zinc-200"
      >
        <header className="flex items-center gap-2 border-b border-zinc-100 px-4 py-3">
          <Keyboard size={14} className="text-zinc-600" />
          <b className="flex-1 text-sm text-zinc-900">Atalhos · Cobrança recorrente</b>
          <button type="button" onClick={onClose} aria-label="Fechar" className="rounded p-1 hover:bg-zinc-100">
            <X size={14} className="text-zinc-500" />
          </button>
        </header>
        <ul className="grid grid-cols-1 divide-y divide-zinc-100">
          {SHORTCUTS.map(([k, l]) => (
            <li key={k} className="flex items-center justify-between px-4 py-2 text-xs">
              <kbd className="rounded bg-zinc-100 px-2 py-0.5 font-mono text-[11px] text-zinc-700 ring-1 ring-zinc-200">{k}</kbd>
              <span className="text-zinc-700">{l}</span>
            </li>
          ))}
        </ul>
        <footer className="border-t border-zinc-100 px-4 py-2 text-center text-[10px] text-zinc-400">
          Pressione <kbd className="rounded bg-zinc-100 px-1 ring-1 ring-zinc-200">?</kbd> para abrir · <kbd className="rounded bg-zinc-100 px-1 ring-1 ring-zinc-200">Esc</kbd> para fechar
        </footer>
      </div>
    </div>
  );
}
