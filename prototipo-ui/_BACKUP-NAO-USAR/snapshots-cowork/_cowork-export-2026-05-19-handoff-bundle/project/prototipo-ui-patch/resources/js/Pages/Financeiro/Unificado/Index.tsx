// @memcofre
//   tela: /financeiro/unificado
//   module: Financeiro
//   status: em-implementacao
//   stories: US-FIN-013, US-FIN-020 (visao-unificada-cockpit-v2)
//   rules: R-FIN-001 (multi-tenant), R-FIN-002 (audit), R-FIN-007 (1-click-baixa)
//   adrs: ui/0114 (cockpit-v2), arq/0005 (modulo-financeiro)
//   tests: Modules/Financeiro/Tests/Feature/UnificadoControllerTest
//
// Origem: prototipo Cowork "Visao Unificada" (Financeiro.html), aprovado por [W] 2026-05-09.
// Persona: Eliana [E] — financeiro escritorio, densidade alta, atalhos teclado.
// Tokens: emerald=entrada/recebido, rose=saida/atrasado, amber=vencendo, stone=neutro.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState, useMemo, useCallback, useEffect, type ReactNode } from 'react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Command, CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

// ---------- Tipos ----------

type LancamentoKind = 'receivable' | 'payable';
type LancamentoStatus = 'aberto' | 'recebido' | 'pago' | 'atrasado' | 'vencendo';

interface Lancamento {
  id: number;
  kind: LancamentoKind;
  status: LancamentoStatus;
  descricao: string;
  contraparte: string;
  contraparte_doc: string | null;
  categoria: string;
  conta_bancaria: string;
  vencimento: string;            // ISO yyyy-mm-dd
  vencimento_label: string;      // "qua, 14 mai"
  liquidacao: string | null;
  valor: number;
  nfe_numero: string | null;
  canal: string | null;
  observacao: string | null;
}

interface Kpi {
  saldo_previsto: number;
  recebido: { valor: number; qtd: number };
  a_receber: { valor: number; qtd: number };
  pago: { valor: number; qtd: number };
  a_pagar: { valor: number; qtd: number };
}

type TabId = 'all' | 'open' | 'rec' | 'pay' | 'received' | 'paid' | 'late';

interface Filters {
  tab: TabId;
  busca: string;
  conta: string;
  categoria: string;
  periodo: string;
  densidade: 'compact' | 'comfortable' | 'spacious';
}

interface Props {
  kpis: Kpi;
  lancamentos: Lancamento[];
  filters: Filters;
  contas: { id: number; nome: string }[];
  categorias: { id: number; nome: string }[];
}

// ---------- Helpers ----------

const brl = (v: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 }).format(v ?? 0);

const TABS: { id: TabId; label: string }[] = [
  { id: 'all',      label: 'Todas' },
  { id: 'open',     label: 'Aberto' },
  { id: 'rec',      label: 'Receber' },
  { id: 'pay',      label: 'Pagar' },
  { id: 'received', label: 'Recebidas' },
  { id: 'paid',     label: 'Pagas' },
  { id: 'late',     label: 'Atraso' },
];

const DENSITY = {
  compact:     { row: 'h-8',  text: 'text-[12.5px]' },
  comfortable: { row: 'h-11', text: 'text-[13px]' },
  spacious:    { row: 'h-14', text: 'text-[13.5px]' },
};

function statusTone(s: LancamentoStatus): 'success' | 'default' | 'warning' | 'destructive' {
  if (s === 'recebido' || s === 'pago') return 'success';
  if (s === 'vencendo') return 'warning';
  if (s === 'atrasado') return 'destructive';
  return 'default';
}

function statusLabel(s: LancamentoStatus): string {
  return { aberto: 'Aberto', recebido: 'Recebido', pago: 'Pago', atrasado: 'Atrasado', vencendo: 'Vencendo' }[s];
}

// ---------- Componentes ----------

function StatusPill({ s }: { s: LancamentoStatus }) {
  const tone = statusTone(s);
  const cls = {
    success:     'bg-emerald-50 text-emerald-700 border-emerald-200',
    warning:     'bg-amber-50 text-amber-800 border-amber-200',
    destructive: 'bg-rose-50 text-rose-700 border-rose-200',
    default:     'bg-stone-50 text-stone-700 border-stone-200',
  }[tone];
  return (
    <span className={`inline-flex items-center px-1.5 py-0.5 rounded border text-[11px] font-medium ${cls}`}>
      {statusLabel(s)}
    </span>
  );
}

function KpiBar({ kpis }: { kpis: Kpi }) {
  return (
    <KpiGrid cols={5} className="mt-4">
      <KpiCard
        icon="wallet"
        tone={kpis.saldo_previsto >= 0 ? 'success' : 'destructive'}
        label="Saldo previsto"
        value={brl(kpis.saldo_previsto)}
        description="Final do período"
      />
      <KpiCard
        icon="arrow-down-circle"
        tone="success"
        label="Recebido"
        value={brl(kpis.recebido.valor)}
        description={`${kpis.recebido.qtd} baixas`}
      />
      <KpiCard
        icon="clock"
        tone="default"
        label="A receber"
        value={brl(kpis.a_receber.valor)}
        description={`${kpis.a_receber.qtd} títulos`}
      />
      <KpiCard
        icon="check-circle-2"
        tone="default"
        label="Pago"
        value={brl(kpis.pago.valor)}
        description={`${kpis.pago.qtd} baixas`}
      />
      <KpiCard
        icon="arrow-up-circle"
        tone="warning"
        label="A pagar"
        value={brl(kpis.a_pagar.valor)}
        description={`${kpis.a_pagar.qtd} títulos`}
      />
    </KpiGrid>
  );
}

function LinhaTabela({ row, dens, selected, onSelect, onBaixar }: {
  row: Lancamento; dens: typeof DENSITY[keyof typeof DENSITY]; selected: boolean;
  onSelect: () => void; onBaixar: () => void;
}) {
  const isIn = row.kind === 'receivable';
  const settled = row.status === 'recebido' || row.status === 'pago';
  return (
    <tr
      className={`${dens.row} ${dens.text} border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer ${selected ? 'bg-amber-50/40' : ''}`}
      onClick={onSelect}
    >
      <td className="pl-4 pr-2"><span className={`text-[14px] ${isIn ? 'text-emerald-600' : 'text-stone-500'}`}>{isIn ? '↑' : '↓'}</span></td>
      <td className="px-2"><div className="font-medium text-stone-900 truncate max-w-[260px]">{row.descricao}</div>{row.nfe_numero && <div className="text-[11px] text-stone-500">NF-e {row.nfe_numero}</div>}</td>
      <td className="px-2 text-stone-700 truncate max-w-[160px]">{row.contraparte}</td>
      <td className="px-2 text-stone-500 truncate max-w-[140px]">{row.categoria}</td>
      <td className="px-2"><StatusPill s={row.status} /></td>
      <td className={`px-2 text-right font-medium tabular-nums whitespace-nowrap ${isIn ? 'text-emerald-700' : 'text-stone-900'}`}>
        <span className="text-stone-400 mr-0.5">{isIn ? '+' : '−'}</span>{brl(row.valor).replace('R$', '').trim()}
      </td>
      <td className="pl-2 pr-4 text-right" onClick={(e) => e.stopPropagation()}>
        {!settled ? (
          <Button size="sm" variant="outline" className="h-7 px-2 text-[11.5px]" onClick={onBaixar}>
            {isIn ? '✓ Recebi' : '✓ Paguei'}
          </Button>
        ) : (
          <span className="text-[11px] text-stone-400">{row.liquidacao}</span>
        )}
      </td>
    </tr>
  );
}

// ---------- Página principal ----------

function FinanceiroUnificado({ kpis, lancamentos, filters, contas, categorias }: Props) {
  const [busca, setBusca] = useState(filters.busca ?? '');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const dens = DENSITY[filters.densidade ?? 'comfortable'];

  const aplicar = useCallback((patch: Partial<Filters>) => {
    router.get('/financeiro/unificado', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
    });
  }, [filters]);

  const onBaixar = (id: number) => {
    router.post(`/financeiro/unificado/${id}/baixar`, {}, {
      preserveScroll: true,
      onSuccess: () => { /* toast tratado no flash */ },
    });
  };

  // Atalhos: Cmd+K → palette, / → busca
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); setPaletteOpen(true); }
      if (e.key === '/' && (e.target as HTMLElement)?.tagName !== 'INPUT') {
        e.preventDefault(); document.getElementById('fin-search-input')?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  // Agrupamento por data de vencimento
  const grupos = useMemo(() => {
    const m = new Map<string, Lancamento[]>();
    for (const r of lancamentos) {
      const k = `${r.vencimento}|${r.vencimento_label}`;
      if (!m.has(k)) m.set(k, []);
      m.get(k)!.push(r);
    }
    return Array.from(m.entries()).sort((a, b) => a[0].localeCompare(b[0]));
  }, [lancamentos]);

  const selected = lancamentos.find(l => l.id === selectedId) ?? null;

  return (
    <>
      <PageHeader
        icon="coins"
        title="Financeiro · Visão unificada"
        description={`Maio 2026 · ROTA LIVRE`}
        action={
          <div className="flex gap-2">
            <Button variant="outline" size="sm" onClick={() => router.visit('/financeiro/extrato')}>
              Conciliar
            </Button>
            <Button size="sm" onClick={() => router.visit('/financeiro/unificado/novo')}>
              + Novo
            </Button>
          </div>
        }
      />

      <KpiBar kpis={kpis} />

      {/* Tabs + filtros sticky */}
      <Card className="mt-6 sticky top-14 z-10">
        <CardContent className="p-3 flex flex-wrap items-center gap-2">
          <div className="inline-flex rounded-md border border-stone-200 bg-stone-50 p-0.5">
            {TABS.map(t => (
              <button
                key={t.id}
                onClick={() => aplicar({ tab: t.id })}
                className={`px-2.5 py-1 text-[12.5px] rounded ${filters.tab === t.id ? 'bg-white shadow-sm text-stone-900 font-medium' : 'text-stone-600 hover:text-stone-900'}`}
              >{t.label}</button>
            ))}
          </div>

          <select className="h-8 px-2 rounded-md border border-stone-200 text-[12.5px]"
                  value={filters.conta} onChange={(e) => aplicar({ conta: e.target.value })}>
            <option value="">Todas as contas</option>
            {contas.map(c => <option key={c.id} value={c.id}>{c.nome}</option>)}
          </select>

          <select className="h-8 px-2 rounded-md border border-stone-200 text-[12.5px]"
                  value={filters.categoria} onChange={(e) => aplicar({ categoria: e.target.value })}>
            <option value="">Todas as categorias</option>
            {categorias.map(c => <option key={c.id} value={c.id}>{c.nome}</option>)}
          </select>

          <Input
            id="fin-search-input"
            placeholder="Buscar lançamento…  /"
            value={busca}
            onChange={(e) => setBusca(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && aplicar({ busca })}
            className="h-8 w-[200px] text-[12.5px]"
          />

          <div className="ml-auto inline-flex rounded-md border border-stone-200 p-0.5 text-[11.5px]">
            {(['compact','comfortable','spacious'] as const).map(d => (
              <button key={d} onClick={() => aplicar({ densidade: d })}
                      className={`px-2 py-0.5 rounded ${filters.densidade === d ? 'bg-stone-900 text-white' : 'text-stone-600'}`}>
                {d === 'compact' ? '◰' : d === 'comfortable' ? '▦' : '▤'}
              </button>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* Tabela agrupada */}
      <Card className="mt-3">
        <CardContent className="p-0">
          <table className="w-full border-collapse">
            <thead>
              <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
                <th className="pl-4 pr-2 py-2 w-8"></th>
                <th className="px-2 py-2 text-left font-medium">Lançamento</th>
                <th className="px-2 py-2 text-left font-medium">Contraparte</th>
                <th className="px-2 py-2 text-left font-medium">Categoria</th>
                <th className="px-2 py-2 text-left font-medium">Status</th>
                <th className="px-2 py-2 text-right font-medium">Valor</th>
                <th className="pl-2 pr-4 py-2 w-[110px] text-right font-medium"></th>
              </tr>
            </thead>
            <tbody>
              {grupos.map(([key, rows]) => {
                const [, label] = key.split('|');
                return (
                  <React.Fragment key={key}>
                    <tr><td colSpan={7} className="bg-stone-50/70 border-b border-stone-200">
                      <div className="px-4 py-1.5 flex items-center text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                        <span>{label}</span>
                        <span className="ml-auto text-stone-400 normal-case tracking-normal">{rows.length} {rows.length === 1 ? 'lançamento' : 'lançamentos'}</span>
                      </div>
                    </td></tr>
                    {rows.map(r => (
                      <LinhaTabela
                        key={r.id} row={r} dens={dens}
                        selected={selectedId === r.id}
                        onSelect={() => setSelectedId(r.id)}
                        onBaixar={() => onBaixar(r.id)}
                      />
                    ))}
                  </React.Fragment>
                );
              })}
              {grupos.length === 0 && (
                <tr><td colSpan={7} className="py-12 text-center text-sm text-stone-500">
                  Nenhum lançamento com os filtros atuais.
                </td></tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      {/* Drawer detalhe */}
      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelectedId(null)}>
        <SheetContent side="right" className="w-[420px] sm:max-w-[420px]">
          {selected && (
            <>
              <SheetHeader>
                <SheetTitle className="text-[16px]">{selected.descricao}</SheetTitle>
              </SheetHeader>
              <div className="mt-4 space-y-4 text-[13px]">
                <div className="flex items-center gap-2"><StatusPill s={selected.status} />
                  <span className="ml-auto font-semibold tabular-nums text-[16px]">{brl(selected.valor)}</span></div>
                <dl className="grid grid-cols-2 gap-y-2 text-[12.5px]">
                  <dt className="text-stone-500">Contraparte</dt><dd>{selected.contraparte}{selected.contraparte_doc && <span className="block text-stone-500">{selected.contraparte_doc}</span>}</dd>
                  <dt className="text-stone-500">Categoria</dt><dd>{selected.categoria}</dd>
                  <dt className="text-stone-500">Conta</dt><dd>{selected.conta_bancaria}</dd>
                  <dt className="text-stone-500">Vencimento</dt><dd>{selected.vencimento_label}</dd>
                  {selected.liquidacao && <><dt className="text-stone-500">Liquidação</dt><dd>{selected.liquidacao}</dd></>}
                  {selected.nfe_numero && <><dt className="text-stone-500">NF-e</dt><dd>{selected.nfe_numero}</dd></>}
                  {selected.canal && <><dt className="text-stone-500">Canal</dt><dd>{selected.canal}</dd></>}
                </dl>
                {selected.observacao && (
                  <div className="rounded-md border border-stone-200 bg-stone-50 p-3 text-[12.5px] text-stone-700">{selected.observacao}</div>
                )}
                <div className="flex gap-2 pt-2 border-t border-stone-200">
                  {(selected.status !== 'recebido' && selected.status !== 'pago') && (
                    <Button onClick={() => onBaixar(selected.id)}>
                      {selected.kind === 'receivable' ? 'Marcar recebido' : 'Marcar pago'}
                    </Button>
                  )}
                  <Button variant="outline">Editar</Button>
                  <Button variant="outline" className="ml-auto">Anexar</Button>
                </div>
              </div>
            </>
          )}
        </SheetContent>
      </Sheet>

      {/* ⌘K Palette */}
      <CommandDialog open={paletteOpen} onOpenChange={setPaletteOpen}>
        <CommandInput placeholder="Buscar lançamento, contraparte ou ação…" />
        <CommandList>
          <CommandEmpty>Sem resultados.</CommandEmpty>
          <CommandGroup heading="Ações">
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/unificado/novo'); }}>Novo lançamento</CommandItem>
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/extrato'); }}>Conciliar extrato</CommandItem>
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/relatorios'); }}>DRE / Relatórios</CommandItem>
          </CommandGroup>
          <CommandGroup heading="Lançamentos">
            {lancamentos.slice(0, 15).map(l => (
              <CommandItem key={l.id} onSelect={() => { setPaletteOpen(false); setSelectedId(l.id); }}>
                <span className={l.kind === 'receivable' ? 'text-emerald-600 mr-2' : 'text-stone-500 mr-2'}>{l.kind === 'receivable' ? '↑' : '↓'}</span>
                {l.descricao} <span className="ml-auto text-stone-500 tabular-nums">{brl(l.valor)}</span>
              </CommandItem>
            ))}
          </CommandGroup>
        </CommandList>
      </CommandDialog>

      {/* Footer sticky com atalhos */}
      <div className="fixed bottom-0 left-0 right-0 border-t border-stone-200 bg-white/95 backdrop-blur px-6 py-2 text-[11.5px] text-stone-500 flex gap-4">
        <span><kbd className="px-1 rounded border border-stone-200 bg-stone-50">⌘K</kbd> palette</span>
        <span><kbd className="px-1 rounded border border-stone-200 bg-stone-50">/</kbd> buscar</span>
        <span><kbd className="px-1 rounded border border-stone-200 bg-stone-50">J</kbd>/<kbd className="px-1 rounded border border-stone-200 bg-stone-50">K</kbd> navegar</span>
        <span><kbd className="px-1 rounded border border-stone-200 bg-stone-50">␣</kbd> selecionar</span>
        <span className="ml-auto">Densidade: <strong>{filters.densidade}</strong></span>
      </div>
    </>
  );
}

// React import for Fragment usage above
import React from 'react';

FinanceiroUnificado.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Visão unificada"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Visão unificada' }]}
  >
    {page}
  </AppShellV2>
);

export default FinanceiroUnificado;
