// US-SELL-P0-3 — Hint visual de atalhos (Quick Win #2 design-arte δ).
// Refs: memory/sessions/2026-05-14-design-arte-sells-create-noite.md (QW#2)
//       memory/requisitos/Sells/RUNBOOK-paridade-create.md §3.7
//
// Footer microcopy com <kbd> tags — Larissa/Lara/Dani descobrem atalhos
// sem ler manual. Render só se viewport ≥ md (mobile não tem teclado físico).

import type { ReactNode } from 'react';

interface HintItem {
  combo: ReactNode; // pode ser string ou conjunto de <kbd> compostos
  label: string;
}

const HINTS: HintItem[] = [
  { combo: '/', label: 'produto' },
  { combo: 'F9', label: 'finalizar' },
  { combo: 'Ctrl+Enter', label: 'salvar' },
  { combo: 'Esc', label: 'sair' },
];

// Tailwind <kbd>: subtle bg, monospace, rounded, ring sutil.
// Cor semântica neutra (muted) — não compete com botões primários.
function Kbd({ children }: { children: ReactNode }) {
  return (
    <kbd className="inline-flex items-center px-1.5 py-0.5 rounded border border-border bg-muted text-[10px] font-mono text-muted-foreground leading-none">
      {children}
    </kbd>
  );
}

export default function HotkeysHint() {
  return (
    <span
      className="hidden md:inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
      aria-label="Atalhos de teclado disponíveis"
    >
      {HINTS.map((hint, i) => (
        <span key={i} className="inline-flex items-center gap-1.5">
          <Kbd>{hint.combo}</Kbd>
          <span>{hint.label}</span>
        </span>
      ))}
    </span>
  );
}
