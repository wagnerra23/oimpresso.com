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
      className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/40 backdrop-blur-sm"
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="w-full max-w-sm rounded-lg bg-card shadow-xl ring-1 ring-border"
      >
        <header className="flex items-center gap-2 border-b border-border px-4 py-3">
          <Keyboard size={14} className="text-foreground" />
          <b className="flex-1 text-sm text-foreground">Atalhos · Cobrança recorrente</b>
          <button type="button" onClick={onClose} aria-label="Fechar" className="rounded p-1 hover:bg-muted">
            <X size={14} className="text-muted-foreground" />
          </button>
        </header>
        <ul className="grid grid-cols-1 divide-y divide-border">
          {SHORTCUTS.map(([k, l]) => (
            <li key={k} className="flex items-center justify-between px-4 py-2 text-xs">
              <kbd className="rounded bg-muted px-2 py-0.5 font-mono text-[11px] text-foreground ring-1 ring-border">{k}</kbd>
              <span className="text-foreground">{l}</span>
            </li>
          ))}
        </ul>
        <footer className="border-t border-border px-4 py-2 text-center text-[10px] text-muted-foreground">
          Pressione <kbd className="rounded bg-muted px-1 ring-1 ring-border">?</kbd> para abrir · <kbd className="rounded bg-muted px-1 ring-1 ring-border">Esc</kbd> para fechar
        </footer>
      </div>
    </div>
  );
}
