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
import React, { useState, useMemo, useCallback, useEffect, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { FinPillFrescor } from './_components/FinPillFrescor';
import { FinConferidoToggle, FinConferidoBadge, useFinConferido, type UseFinConferidoApi } from './_components/FinConferidoToggle';
import { FinCommentsThread, FinCommentsBadge, useFinComments, type UseFinCommentsApi } from './_components/FinCommentsThread';
import { FinAuditTrail } from './_components/FinAuditTrail';
// Onda 6 R2 IA — anomaly + party history + month digest (pure compute, sem backend).
import { FinAnomalyDetector } from './_components/FinAnomalyDetector';
import { FinPartyHistory } from './_components/FinPartyHistory';
import { FinMonthDigest } from './_components/FinMonthDigest';
// Onda 7 R3 Output — cross-link + checklist fechamento.
import { FinCrossLinkify } from './_components/FinCrossLinkify';
import { FinChecklistFechamento } from './_components/FinChecklistFechamento';

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
  periodLabel: string;
  businessName: string;
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

/**
 * Onda 8 KB-9.75 — KPI bar Cowork canon: hero "Saldo previsto" com sparkline
 * SVG inline + 4 cards secundários. Substitui shadcn KpiCard flat antigo.
 *
 * Sparkline mostra trajetória do saldo ao longo do mês (placeholder estático
 * agora — Onda 8b plugará dados reais via /api/financeiro/saldo-sparkline).
 */
function FinSparkline({ tone = 'pos' }: { tone?: 'pos' | 'neg' }) {
  // Path placeholder estático — Onda 8b: data prop com 30d real.
  const color = tone === 'pos' ? 'oklch(0.78 0.13 145)' : 'oklch(0.65 0.18 25)';
  return (
    <svg className="fin-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="finSparkG" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.5" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path
        d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8 L200,36 L0,36 Z"
        fill="url(#finSparkG)"
      />
      <path
        d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8"
        stroke={color}
        strokeWidth="1.5"
        fill="none"
      />
      <line x1="0" y1="24" x2="200" y2="24" stroke="oklch(0.65 0.01 80)" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" />
    </svg>
  );
}

function KpiBar({ kpis, onTabClick }: { kpis: Kpi; onTabClick: (tab: TabId) => void }) {
  // Onda 8 Cowork: hero card dark green com sparkline + 4 secundários canon.
  // Saldo previsto = posição final do mês (Recebido + AReceber - Pago - APagar).
  const pendente = kpis.a_receber.valor - kpis.a_pagar.valor;
  return (
    <div className="fin-stats">
      <button type="button" className="fin-stat fin-stat-hero" onClick={() => onTabClick('open')} aria-label="Filtrar abertos">
        <small>Saldo previsto · maio</small>
        <b>{brl(kpis.saldo_previsto)}</b>
        <span className="fin-stat-hint">
          <b className="mono">{brl(kpis.recebido.valor - kpis.pago.valor)}</b> realizado · <span className={pendente >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(pendente)}</span> pendente
        </span>
        <FinSparkline tone={kpis.saldo_previsto >= 0 ? 'pos' : 'neg'} />
      </button>

      <button type="button" className="fin-stat" onClick={() => onTabClick('received')} aria-label="Filtrar recebidas">
        <small>Recebido</small>
        <b className="fin-num-pos">{brl(kpis.recebido.valor)}</b>
        <span className="fin-stat-hint">{kpis.recebido.qtd} entradas confirmadas</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onTabClick('rec')} aria-label="Filtrar a receber">
        <small>A receber</small>
        <b>{brl(kpis.a_receber.valor)}</b>
        <span className="fin-stat-hint">{kpis.a_receber.qtd} títulos</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onTabClick('paid')} aria-label="Filtrar pagas">
        <small>Pago</small>
        <b className="fin-num-neg">{brl(kpis.pago.valor)}</b>
        <span className="fin-stat-hint">{kpis.pago.qtd} saídas liquidadas</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onTabClick('pay')} aria-label="Filtrar a pagar">
        <small>A pagar</small>
        <b>{brl(kpis.a_pagar.valor)}</b>
        <span className="fin-stat-hint">{kpis.a_pagar.qtd} títulos</span>
      </button>
    </div>
  );
}

// Versão antiga (preservada como referência — pode ser removida na Onda 8b polish).
function _KpiBarLegacy({ kpis, onTabClick }: { kpis: Kpi; onTabClick: (tab: TabId) => void }) {
  return (
    <div className="mt-4 grid gap-3 grid-cols-1 sm:grid-cols-2 lg:grid-cols-5">
      <KpiCard
        icon="wallet"
        tone={kpis.saldo_previsto >= 0 ? 'success' : 'danger'}
        label="Saldo previsto"
        value={brl(kpis.saldo_previsto)}
        description="Final do período"
        onClick={() => onTabClick('open')}
      />
      <KpiCard
        icon="arrow-down-circle"
        tone="success"
        label="Recebido"
        value={brl(kpis.recebido.valor)}
        description={`${kpis.recebido.qtd} baixas`}
        onClick={() => onTabClick('received')}
      />
      <KpiCard
        icon="clock"
        tone="default"
        label="A receber"
        value={brl(kpis.a_receber.valor)}
        description={`${kpis.a_receber.qtd} títulos`}
        onClick={() => onTabClick('rec')}
      />
      <KpiCard
        icon="check-circle-2"
        tone="default"
        label="Pago"
        value={brl(kpis.pago.valor)}
        description={`${kpis.pago.qtd} baixas`}
        onClick={() => onTabClick('paid')}
      />
      <KpiCard
        icon="arrow-up-circle"
        tone="warning"
        label="A pagar"
        value={brl(kpis.a_pagar.valor)}
        description={`${kpis.a_pagar.qtd} títulos`}
        onClick={() => onTabClick('pay')}
      />
    </div>
  );
}

function LinhaTabela({ row, dens, selected, onSelect, onBaixar, conferido, comments }: {
  row: Lancamento; dens: typeof DENSITY[keyof typeof DENSITY]; selected: boolean;
  onSelect: () => void; onBaixar: () => void;
  conferido: UseFinConferidoApi; comments: UseFinCommentsApi;
}) {
  const isIn = row.kind === 'receivable';
  const settled = row.status === 'recebido' || row.status === 'pago';
  // FinPillFrescor consome `due`/`paid_at` — adapta de `vencimento`/`liquidacao`.
  const frescorRow = {
    due: row.vencimento,
    paid_at: settled ? row.liquidacao : null,
    vencimento: row.vencimento,
  };
  return (
    <tr
      className={`${dens.row} ${dens.text} border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer ${selected ? 'bg-amber-50/40' : ''}`}
      onClick={onSelect}
    >
      <td className="pl-4 pr-2"><span className={`text-[14px] ${isIn ? 'text-emerald-600' : 'text-stone-500'}`}>{isIn ? '↑' : '↓'}</span></td>
      <td className="px-2">
        <div className="font-medium text-stone-900 truncate max-w-[260px] flex items-center gap-1.5">
          <FinCrossLinkify text={row.descricao} className="truncate" />
          <FinConferidoBadge rowId={row.id} conferido={conferido} />
          <FinCommentsBadge rowId={row.id} comments={comments} />
        </div>
        {row.nfe_numero && <div className="text-[11px] text-stone-500">NF-e {row.nfe_numero}</div>}
      </td>
      <td className="px-2 text-stone-700 truncate max-w-[160px]">{row.contraparte}</td>
      <td className="px-2 text-stone-500 truncate max-w-[140px]">{row.categoria}</td>
      <td className="px-2"><div className="flex items-center gap-1.5"><StatusPill s={row.status} /><FinPillFrescor row={frescorRow} compact /></div></td>
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

function FinanceiroUnificado({ kpis, lancamentos, filters, contas, categorias, periodLabel, businessName }: Props) {
  const [busca, setBusca] = useState(filters.busca ?? '');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [paletteOpen, setPaletteOpen] = useState(false);
  const dens = DENSITY[filters.densidade ?? 'comfortable'];

  // Cowork KB-9.75 Onda 5 R1 Curadoria — hooks localStorage compartilhados pela página.
  const conferido = useFinConferido();
  const comments = useFinComments();
  // Cowork KB-9.75 Onda 7 R3 — trilha fechamento dialog state.
  const [checklistOpen, setChecklistOpen] = useState(false);

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
    <div className="fin-curadoria">
      {/* Onda 8 Cowork: page header canon com h1 + breadcrumb + 7 botões.
          Substitui PageHeader shadcn legacy (mantido em _KpiBarLegacy + comentário). */}
      <div className="fin-page-h">
        <div className="fin-page-h-l">
          <h1>
            Financeiro <span className="fin-hero-title-sub">· Visão unificada</span>
          </h1>
          <p>{periodLabel}{businessName ? ` · ${businessName}` : ''} · caixa unificado</p>
        </div>
        <div className="fin-page-h-r">
          <button type="button" className="fin-btn" onClick={() => setPaletteOpen(true)}>
            🔍 Buscar
            <kbd>⌘K</kbd>
          </button>
          <button
            type="button"
            className="fin-btn fin-btn-ai"
            title="Resumo executivo do mês com IA (Onda 9 — em construção)"
            onClick={() => alert('Resumir mês: feature Onda 9 — JanaService agent (em construção)')}
          >
            ✦ Resumir mês
          </button>
          <button
            type="button"
            className="fin-btn fin-btn-trilha"
            onClick={() => setChecklistOpen(true)}
            title="Trilha de 12 passos do fechamento mensal"
          >
            ☑ Fechamento
          </button>
          <button
            type="button"
            className="fin-btn fin-btn-present"
            title="Modo apresentação fullscreen (Onda 7b — em construção)"
            onClick={() => alert('Apresentar: feature Onda 7b — FinPresentationMode (em construção)')}
          >
            ▶ Apresentar
          </button>
          <button type="button" className="fin-btn" onClick={() => router.visit('/financeiro/extrato')}>
            ↺ Conciliar
          </button>
          <button type="button" className="fin-btn" onClick={() => router.visit('/financeiro/plano-contas')} title="Plano de contas — categorias contábeis">
            📁 Plano de contas
          </button>
          <button type="button" className="fin-btn primary" onClick={() => router.visit('/financeiro/unificado/novo')}>
            + Novo lançamento
          </button>
        </div>
      </div>

      <KpiBar kpis={kpis} onTabClick={(tab) => aplicar({ tab })} />

      {/* Cowork KB-9.75 Onda 6 R2 IA — Resumo executivo do mês (Eliana 5min sexta) */}
      <FinMonthDigest
        lancamentos={lancamentos}
        kpis={kpis}
        conferidoSet={new Set(lancamentos.filter((l) => conferido.has(l.id)).map((l) => String(l.id)))}
        periodLabel={periodLabel}
      />

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
                        conferido={conferido}
                        comments={comments}
                      />
                    ))}
                  </React.Fragment>
                );
              })}
              {grupos.length === 0 && (
                <tr><td colSpan={7} className="py-16">
                  <div className="flex flex-col items-center gap-3 text-center">
                    <div className="text-sm text-stone-600">
                      {filters.tab === 'all' && !filters.busca && filters.conta === '' && filters.categoria === ''
                        ? `Nenhum lançamento em ${periodLabel}.`
                        : 'Nenhum lançamento com os filtros atuais.'}
                    </div>
                    {filters.tab === 'all' && !filters.busca && filters.conta === '' && filters.categoria === '' && (
                      <Button size="sm" onClick={() => router.visit('/financeiro/unificado/novo')}>
                        + Adicionar primeiro lançamento
                      </Button>
                    )}
                  </div>
                </td></tr>
              )}
            </tbody>
          </table>
        </CardContent>
      </Card>

      {/* Drawer detalhe */}
      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelectedId(null)}>
        <SheetContent side="right" className="w-[460px] sm:max-w-[460px]">
          {selected && (
            <>
              <SheetHeader>
                <SheetTitle className="text-[16px]">
                  <FinCrossLinkify text={selected.descricao} />
                </SheetTitle>
              </SheetHeader>
              <div className="mt-4 space-y-4 text-[13px]">
                <div className="flex items-center gap-2">
                  <StatusPill s={selected.status} />
                  <FinPillFrescor row={{ due: selected.vencimento, paid_at: (selected.status === 'recebido' || selected.status === 'pago') ? selected.liquidacao : null, vencimento: selected.vencimento }} />
                  <span className="ml-auto font-semibold tabular-nums text-[16px]">{brl(selected.valor)}</span>
                </div>

                <FinConferidoToggle rowId={selected.id} conferido={conferido} />

                {/* Cowork KB-9.75 Onda 6 R2 IA — Anomaly + Party History */}
                <FinAnomalyDetector
                  row={{
                    id: selected.id,
                    contraparte: selected.contraparte,
                    categoria: selected.categoria,
                    valor: selected.valor,
                    vencimento: selected.vencimento,
                    liquidacao: selected.liquidacao,
                    status: selected.status,
                    kind: selected.kind,
                  }}
                  all={lancamentos}
                />

                <FinPartyHistory
                  currentRow={{ id: selected.id, contraparte: selected.contraparte }}
                  all={lancamentos}
                />

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

                <div className="border-t border-stone-200 pt-4">
                  <FinAuditTrail row={{
                    id: selected.id,
                    descricao: selected.descricao,
                    contraparte: selected.contraparte,
                    categoria: selected.categoria,
                    conta_bancaria: selected.conta_bancaria,
                    canal: selected.canal ?? undefined,
                    valor: selected.valor,
                    status: selected.status,
                    kind: selected.kind,
                    paid_at: (selected.status === 'recebido' || selected.status === 'pago') ? selected.liquidacao : null,
                    due: selected.vencimento,
                  }} />
                </div>

                <div className="border-t border-stone-200 pt-4">
                  <FinCommentsThread rowId={selected.id} comments={comments} />
                </div>

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

      {/* Onda 8 Cowork: footer atalhos canon (oklch tokens via .fin-footer-tips) */}
      <div className="fin-footer-tips">
        <span><kbd>⌘K</kbd> palette</span>
        <span><kbd>/</kbd> buscar</span>
        <span><kbd>J</kbd>/<kbd>K</kbd> navegar</span>
        <span><kbd>␣</kbd> marcar pago/recebido</span>
        <span className="spacer" />
        <span>Densidade: <strong>{filters.densidade}</strong></span>
      </div>

      {/* Cowork KB-9.75 Onda 7 R3 — Trilha fechamento dialog */}
      <FinChecklistFechamento
        periodLabel={periodLabel}
        open={checklistOpen}
        onClose={() => setChecklistOpen(false)}
      />
    </div>
  );
}

FinanceiroUnificado.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Visão unificada"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Visão unificada' }]}
  >
    {page}
  </AppShellV2>
);

export default FinanceiroUnificado;
