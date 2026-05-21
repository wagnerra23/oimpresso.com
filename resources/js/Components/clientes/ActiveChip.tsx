// Wave Z-2.1 — Components/clientes/ActiveChip.tsx
//
// Chip removível pra filtros ativos na listagem. Alinhado ao protótipo Cowork
// `clientes-listagem.jsx::ActiveChip` — mostra "Label: valor ×" e dispara
// onRemove ao clicar no X.
//
// Refs:
//   - ADR 0179 (drawer 760 paradigma cadastral)
//   - prototipo-ui/prototipos/clientes/clientes-listagem.jsx::ActiveChip
//   - visual-comparison cliente-drawer-760 dim 4 (filtros + ActiveChip)
//
// Uso:
//   {filtros.tipo && (
//     <ActiveChip label="Tipo" value={filtros.tipo} onRemove={() => setFiltroTipo('')} />
//   )}

import { X } from 'lucide-react';

export interface ActiveChipProps {
  /** Rótulo do filtro (ex: "Tipo", "Status", "UF"). */
  label: string;
  /** Valor selecionado (ex: "PJ", "Ativo", "SC"). Array vira "x, y, z". */
  value: string | string[];
  /** Callback ao clicar no X. Limpa o filtro. */
  onRemove: () => void;
  /** Cor accent (default azul). Match Cowork. */
  variant?: 'default' | 'danger';
}

export function ActiveChip({ label, value, onRemove, variant = 'default' }: ActiveChipProps) {
  const display = Array.isArray(value) ? value.join(', ') : value;
  if (!display) return null;

  const colors =
    variant === 'danger'
      ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/40'
      : 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-900/40';

  return (
    <span
      className={
        'inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium ' +
        colors
      }
    >
      <span className="opacity-70">{label}:</span>
      <span>{display}</span>
      <button
        type="button"
        onClick={onRemove}
        aria-label={`Remover filtro ${label}`}
        className="ml-0.5 -mr-0.5 inline-flex h-3.5 w-3.5 items-center justify-center rounded-full hover:bg-black/10 dark:hover:bg-white/10 transition-colors"
      >
        <X size={10} />
      </button>
    </span>
  );
}

export default ActiveChip;
