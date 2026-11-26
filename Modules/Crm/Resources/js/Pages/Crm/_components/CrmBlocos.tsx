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
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { useCockpit } from '@/Hooks/useCockpit';

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

/** A âncora vem da tela: o gate `contrato-de-tela` lê o `data-contract` no arquivo da Page. */
export function CrmFiltros({ 'data-contract': contrato, children }: { 'data-contract': string; children: ReactNode }) {
  const [aberto, setAberto] = useState(true);
  return (
    <CrmCartao
      data-contract={contrato}
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

/**
 * A `Mini` do protótipo (`.pb-tbl`): cabeçalho só quando há (caixa alta 10,5px/700 sobre `--bg-2`),
 * linha 12,5px com 7×10px e separador embaixo; da 2ª coluna em diante, mono à direita.
 */
export function CrmMini({ cabecalho, linhas, vazio = 'Sem dados' }: { cabecalho?: string[]; linhas: ReactNode[][]; vazio?: string }) {
  const colunas = cabecalho?.length ?? (linhas[0]?.length || 2);
  const borda = { borderBottomColor: 'var(--border-2)' };
  return (
    <table className="w-full border-separate border-spacing-0 text-[12.5px]">
      {cabecalho && (
        <thead>
          <tr>
            {cabecalho.map((c, i) => (
              <th key={c} scope="col" className={`whitespace-nowrap border-b px-2.5 py-2 text-[10.5px] font-bold uppercase tracking-[0.06em] ${i ? 'text-right' : 'text-left'}`}
                style={{ color: 'var(--text-mute)', background: 'var(--bg-2)', borderBottomColor: 'var(--border)' }}>{c}</th>
            ))}
          </tr>
        </thead>
      )}
      <tbody>
        {linhas.length === 0 && <tr><td colSpan={colunas} className="border-b px-2.5 py-[7px] text-center" style={{ ...borda, color: 'var(--text-mute)' }}>{vazio}</td></tr>}
        {linhas.map((l, i) => (
          <tr key={i}>
            {l.map((v, j) => (
              <td key={j} className={j ? 'border-b px-2.5 py-[7px] text-right font-mono text-[12px] tabular-nums' : 'border-b px-2.5 py-[7px]'} style={borda}>{v}</td>
            ))}
          </tr>
        ))}
      </tbody>
    </table>
  );
}

/**
 * A gaveta do protótipo (`Drawer` do DS, `prototipo-ui/design-system/components/Drawer`): 480px,
 * fundo `--surface` com sombra à esquerda, selo no topo, título 17px/600, subtítulo 12,5px,
 * seções separadas por linha e rodapé com botões de 30px. Medido em produção em 2026-11-25
 * (CRM-26, CRM-33): 512px, fundo da página, título 16px, seções em texto corrido e botões de 36px.
 */
export function CrmGaveta({ 'data-contract': contrato, selo, titulo, subtitulo, rodape, onFechar, children }: {
  'data-contract'?: string; selo?: ReactNode; titulo: string; subtitulo?: ReactNode;
  rodape?: ReactNode; onFechar: () => void; children: ReactNode;
}) {
  const cockpit = useCockpit();
  return (
    <Sheet open onOpenChange={(aberto) => { if (!aberto) onFechar(); }}>
      <SheetContent container={cockpit} data-contract={contrato} className="w-full gap-0 shadow-[-16px_0_40px_-12px_rgb(0_0_0/0.3)] sm:max-w-[480px]"
        style={{ background: 'var(--surface)', borderLeftColor: 'var(--border)' }}>
        <div className="flex min-h-[57px] items-center gap-2.5 border-b px-[18px] py-3.5 pr-14" style={{ borderBottomColor: 'var(--border)' }}>{selo}</div>
        <div className="px-[18px] py-4">
          <SheetTitle className="m-0 text-[17px] font-semibold leading-[1.3] tracking-[-0.01em]" style={{ color: 'var(--text)' }}>{titulo}</SheetTitle>
          {subtitulo ? <SheetDescription className="mt-1 text-[12.5px]" style={{ color: 'var(--text-dim)' }}>{subtitulo}</SheetDescription> : null}
        </div>
        <div className="flex-1 overflow-y-auto">{children}</div>
        {rodape ? (
          <div className="mt-auto flex justify-end gap-2 border-t px-[18px] py-3 [&_button]:h-[30px] [&_button]:px-3 [&_button]:text-[12.5px]" style={{ borderTopColor: 'var(--border)' }}>{rodape}</div>
        ) : null}
      </SheetContent>
    </Sheet>
  );
}

/** A `DrawerSection` do protótipo: título em caixa alta 10,5px/600 e corpo 13px, com linha em cima. */
export function CrmSecao({ 'data-contract': contrato, titulo, children }: { 'data-contract'?: string; titulo: ReactNode; children: ReactNode }) {
  return (
    <section data-contract={contrato} className="border-t px-[18px] py-3.5" style={{ borderTopColor: 'var(--border-2)' }}>
      <h4 className="m-0 mb-2 text-[10.5px] font-semibold uppercase leading-[1.4] tracking-[0.05em]" style={{ color: 'var(--text-mute)' }}>{titulo}</h4>
      <div className="text-[13px] leading-normal" style={{ color: 'var(--text)' }}>{children}</div>
    </section>
  );
}
