// Peças das telas do CRM na forma do protótipo (`prototipo-ui/cowork/Wagner/crm-blade.jsx`).
//
// O protótipo monta todas as abas com as mesmas três peças do PBUI (`produto-blade.jsx`):
//  - `Widget` (`.pb-widget`): título em caixa alta 11px/700, espaçamento .07em, cor `--text-mute`,
//    divisória `--border-2` (12×16px) e corpo 14×16px — aqui `CrmCartao`;
//  - `Filtros`: um `Widget` com o ícone de busca, "Recolher/Expandir" e "recolhidos" quando
//    fechado — aqui `CrmFiltros`;
//  - `Toolbar`: o seletor "Confortável / Compacto" da tabela — aqui `CrmDensidade`.
// Medido em produção em 2026-11-25 (pendências CRM-13, CRM-20, CRM-31): o `Card` do DS soma 24px de
// padding próprio + `gap-6`, título em texto normal, filtros sem recolher, sem seletor de densidade.

import { useState, type ReactNode } from 'react';
import { Icon } from '@/Components/Icon';
import { Button } from '@/Components/ui/button';
import { Segmented } from '@/Components/ui/segmented';

export function CrmCartao({ 'data-contract': contrato, titulo, nota, acao, semPadding, children }: {
  'data-contract'?: string;
  titulo?: ReactNode;
  /** O `nota` do Widget: texto mono pequeno ao lado do título. */
  nota?: ReactNode;
  acao?: ReactNode;
  /** O `flush` do Widget: corpo sem padding (tabela encostada na borda). */
  semPadding?: boolean;
  children: ReactNode;
}) {
  return (
    <section data-contract={contrato} className="rounded-lg border bg-card shadow-sm">
      {titulo && (
        <h3 className="m-0 flex items-center gap-2 border-b px-4 py-3 text-[11px] font-bold uppercase tracking-[0.07em]" style={{ color: 'var(--text-mute)', borderBottomColor: 'var(--border-2)' }}>
          {titulo}
          {nota && <span className="font-mono text-[10.5px] normal-case tracking-normal" style={{ color: 'var(--text-dim)' }}>{nota}</span>}
          {acao && <span className="ml-auto normal-case tracking-normal">{acao}</span>}
        </h3>
      )}
      <div className={semPadding ? '' : 'overflow-x-auto px-4 py-3.5'}>{children}</div>
    </section>
  );
}

export function CrmFiltros({ children }: { children: ReactNode }) {
  const [aberto, setAberto] = useState(true);
  return (
    <CrmCartao
      data-contract="crm-filtros"
      titulo={<><Icon name="search" size={13} aria-hidden /> Filtros</>}
      nota={aberto ? null : 'recolhidos'}
      acao={
        <Button variant="ghost" size="sm" aria-expanded={aberto} onClick={() => setAberto((a) => !a)}>
          {aberto ? 'Recolher' : 'Expandir'}
        </Button>
      }
    >
      {aberto ? children : null}
    </CrmCartao>
  );
}

/** "Confortável / Compacto" — a densidade do `DataTablePro` do DS: no `DataTable`, `grid-relaxed` / `grid`. */
export function CrmDensidade({ compacta, onChange }: { compacta: boolean; onChange: (compacta: boolean) => void }) {
  return (
    <Segmented
      aria-label="Densidade da tabela"
      value={compacta ? 'comp' : 'conf'}
      onValueChange={(v) => v && onChange(v === 'comp')}
      options={[{ value: 'conf', label: 'Confortável' }, { value: 'comp', label: 'Compacto' }]}
    />
  );
}

export type TomPilula = 'ok' | 'warn' | 'neg' | 'info' | 'mute';
const COR_PILULA: Record<TomPilula, [string, string]> = {
  ok: ['var(--pos)', 'var(--pos-soft)'],
  warn: ['var(--warn)', 'var(--warn-soft)'],
  neg: ['var(--neg)', 'var(--neg-soft)'],
  info: ['var(--accent)', 'var(--accent-soft)'],
  mute: ['var(--text-mute)', 'var(--bg-2)'],
};

/**
 * Selo de status do protótipo (`Pill` do `crm-blade.jsx`): 10,5px/600, 2×7px, cor e fundo do tom,
 * borda na cor a 25%. O "info" é o roxo de destaque — por isso "Agendado" sai roxo no protótipo
 * e saía azul em produção com o `Badge variant="info"` (pendência CRM-32).
 */
export function CrmPilula({ tom = 'mute', children }: { tom?: TomPilula; children: ReactNode }) {
  const [cor, fundo] = COR_PILULA[tom];
  return (
    <span
      className="inline-flex items-center gap-1 whitespace-nowrap rounded-full border px-[7px] py-0.5 text-[10.5px] font-semibold"
      style={{ color: cor, background: fundo, borderColor: `color-mix(in oklch, ${cor} 25%, transparent)` }}
    >
      {children}
    </span>
  );
}
