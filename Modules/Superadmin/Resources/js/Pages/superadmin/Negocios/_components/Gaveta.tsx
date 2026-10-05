// Casca dos drawers da lista de negócios (detalhe e "Novo negócio"). Saiu do Index.tsx na thread
// Superadmin 02, PR-2, quando o segundo drawer precisou dela. `largo` segue o `sa-drawer--largo`
// do protótipo (`FormDrawer largo`), usado pelo formulário.

import type { ReactNode } from 'react';

function Scrim({ onFechar }: { onFechar: () => void }) {
  return <div className="fixed inset-0 z-40 bg-black/40" onClick={onFechar} aria-hidden="true" />;
}

export function Casca({
  children,
  onFechar,
  titulo,
  largo = false,
}: {
  children: ReactNode;
  onFechar: () => void;
  titulo: string;
  largo?: boolean;
}) {
  return (
    <>
      <Scrim onFechar={onFechar} />
      <aside
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
        className={`fixed inset-y-0 right-0 z-50 flex ${largo ? 'w-[min(640px,92vw)]' : 'w-[min(460px,92vw)]'} flex-col border-l bg-background shadow-2xl`}
      >
        {children}
      </aside>
    </>
  );
}
