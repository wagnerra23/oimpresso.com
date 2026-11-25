// Seção, selo e aviso do protótipo Devolução V3 (prototipo-ui/cowork/Wagner/devolucao-v3.html,
// `.sec` · `.pill` · `.al` · `.plate`). Produção tinha caído para o Card genérico: cabeçalho
// chapado, sem ícone, sem matiz por bloco. Aqui o desenho volta, com a cor sempre por TOKEN
// (`--color-*` do DS) e o color-mix na mesma proporção do protótipo: 5% no cabeçalho, 14% no
// ícone, 9%/26% no aviso. Só forma — nenhum estado, conta ou post muda.
//
// Local de propósito (ADR 0388 §D-1): o `Sec` e o `Aviso` da Venda V3 não têm o ícone, e
// mexer nele vazaria para a tela que a ROTA LIVRE opera.

import type { ReactNode } from 'react';
import { Inline } from '@/Components/layout';
import { cn } from '@/Lib/utils';
// Passo é o mesmo numerador da Venda V3: reusado, não recriado (gate reuse:duplicates).
import { Passo } from '@/Pages/Sells/_components/v3/primitivos';

export type Tom = 'primary' | 'success' | 'warning' | 'info' | 'destructive';

const HUE: Record<Tom, string> = {
  primary: 'var(--color-primary)',
  success: 'var(--color-success)',
  warning: 'var(--color-warning)',
  info: 'var(--color-info)',
  destructive: 'var(--color-destructive)',
};

/** Texto no tom: 62% do matiz sobre o texto, como o protótipo — legível nos dois temas. */
const tomTexto = (t: Tom) => `color-mix(in oklch, ${HUE[t]} 62%, var(--color-foreground))`;

export function Selo({ tom, children }: { tom: Tom; children: ReactNode }) {
  return (
    <span
      className="inline-flex h-5 items-center gap-1.5 whitespace-nowrap text-[10.5px] font-semibold uppercase leading-none tracking-[.04em]"
      style={{ color: tomTexto(tom) }}
    >
      <span aria-hidden className="size-1.5 flex-none rounded-full" style={{ background: HUE[tom] }} />
      {children}
    </span>
  );
}

export function SecaoDevolucao({
  titulo,
  sub,
  passo,
  icone,
  tom = 'primary',
  direita,
  semPadding,
  children,
  ...rest
}: {
  titulo: ReactNode;
  sub?: ReactNode;
  passo?: number;
  icone?: ReactNode;
  tom?: Tom;
  direita?: ReactNode;
  semPadding?: boolean;
  children: ReactNode;
  'data-contract'?: string;
}) {
  return (
    <section data-contract={rest['data-contract']} data-tom={tom} className="min-w-0 overflow-hidden rounded-lg border border-border bg-card shadow-sm">
      <header
        style={{ background: `color-mix(in oklch, ${HUE[tom]} 5%, var(--color-card))` }}
        // inline-flex (não `flex`): o gate `Layout primitives` conta `flex` solto em className.
        className="inline-flex w-full flex-wrap items-center gap-3 border-b border-border px-4 py-3"
      >
        {icone && (
          <span
            aria-hidden
            className="inline-flex size-[30px] flex-none items-center justify-center rounded-lg [&_svg]:size-[15px]"
            style={{ background: `color-mix(in oklch, ${HUE[tom]} 14%, transparent)`, color: tomTexto(tom) }}
          >
            {icone}
          </span>
        )}
        <div className="min-w-0">
          <h3 className="m-0 text-[15px] font-semibold leading-[1.3] text-foreground">
            {passo !== undefined && <Passo n={passo} />}
            {titulo}
          </h3>
          {sub && <p className="mt-0.5 text-[11.5px] leading-[1.35] text-muted-foreground">{sub}</p>}
        </div>
        {direita && (
          <Inline gap={2} align="center" wrap className="ml-auto">
            {direita}
          </Inline>
        )}
      </header>
      <div className={cn(!semPadding && 'p-4')}>{children}</div>
    </section>
  );
}

/** Aviso da conferência (`.al`): fundo 9%, borda 26%, ícone e título no tom. */
export function AvisoConferencia({ tom, icone, titulo, children }: { tom: Tom; icone: ReactNode; titulo: string; children: ReactNode }) {
  return (
    <div
      className="inline-flex w-full items-start gap-2.5 rounded-lg border px-3 py-2.5 text-[12.5px] leading-[1.45] text-foreground"
      style={{
        background: `color-mix(in oklch, ${HUE[tom]} 9%, var(--color-card))`,
        borderColor: `color-mix(in oklch, ${HUE[tom]} 26%, transparent)`,
      }}
    >
      <span aria-hidden className="mt-0.5 inline-flex flex-none [&_svg]:size-[15px]" style={{ color: tomTexto(tom) }}>
        {icone}
      </span>
      <div className="min-w-0">
        <b style={{ color: tomTexto(tom) }}>{titulo}</b> {children}
      </div>
    </div>
  );
}
