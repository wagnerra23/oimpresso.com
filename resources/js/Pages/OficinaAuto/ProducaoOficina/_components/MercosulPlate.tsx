// Placa estilo Mercosul (visual, não literal) — espelha .ofc-plate do
// protótipo Cowork canon (prototipo-ui/prototipos/producao-oficina/visual-source.html).
//
// Componente puro display — sem state, sem fetch. Reusável em qualquer card
// que mostre placa de veículo.
//
// Estrutura visual:
//   ┌─────────────────┐
//   │ BR · MERCOSUL   │ ← top: blue Mercosul (#3b5fa9)
//   ├─────────────────┤
//   │   ABC-1D23      │ ← num: white bg, mono bold
//   └─────────────────┘
//
// Tamanhos:
//   - sm: 76px min-width (default Kanban card)
//   - md: 96px (drawer header)
//   - lg: 120px (Show page hero)

import { memo } from 'react';

type Size = 'sm' | 'md' | 'lg';

interface Props {
  plate: string;
  size?: Size;
  className?: string;
}

const SIZES: Record<Size, { wrapper: string; top: string; num: string }> = {
  sm: { wrapper: 'min-w-[76px]', top: 'text-[7px] py-0.5',  num: 'text-[13px] py-1 px-1.5' },
  md: { wrapper: 'min-w-[96px]', top: 'text-[8px] py-1',    num: 'text-[16px] py-1.5 px-2' },
  lg: { wrapper: 'min-w-[120px]', top: 'text-[10px] py-1.5', num: 'text-[20px] py-2 px-3' },
};

function MercosulPlateImpl({ plate, size = 'sm', className = '' }: Props) {
  const s = SIZES[size];
  return (
    <span
      className={`inline-flex flex-col rounded border-[1.5px] border-slate-700 overflow-hidden bg-white ${s.wrapper} ${className}`}
      style={{ fontFamily: 'ui-monospace, "Cascadia Code", Menlo, monospace', lineHeight: 1 }}
      role="img"
      aria-label={`Placa ${plate}`}
    >
      <span
        className={`text-white text-center font-semibold ${s.top}`}
        style={{ background: 'oklch(0.45 0.13 250)', letterSpacing: '0.12em' }}
      >
        BR · MERCOSUL
      </span>
      <span
        className={`text-center font-bold text-slate-900 ${s.num}`}
        style={{ letterSpacing: '0.04em' }}
      >
        {plate}
      </span>
    </span>
  );
}

export default memo(MercosulPlateImpl);
