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
// Onda 12 (2026-05-19) — paridade 100% canon REAL (/cowork-preview/Oimpresso ERP - Chat.html):
// emoji → lucide-react nos 8 botões + Download icon adicional + remoção FinMonthDigest
// (não-canon) + summary numérica footer + KPI hero dark.
import { Search, Plus, Sparkles, CheckSquare, Play, Printer, RefreshCw, FolderOpen, Download } from 'lucide-react';
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
// Onda 6 R2 IA — anomaly + party history (pure compute, sem backend).
// FinMonthDigest REMOVIDO Onda 12 (paridade canon REAL — não tem sub-header colapsável).
import { FinAnomalyDetector } from './_components/FinAnomalyDetector';
import { FinPartyHistory } from './_components/FinPartyHistory';
// Onda 7 R3 Output — cross-link + checklist fechamento.
import { FinCrossLinkify } from './_components/FinCrossLinkify';
import { FinChecklistFechamento } from './_components/FinChecklistFechamento';
// Onda 7b — Troubleshooter dialog + PresentationMode fullscreen.
import { FinTroubleshooterDialog, FinTroubleButton } from './_components/FinTroubleshooter';
import { FinPresentationMode } from './_components/FinPresentationMode';
// Onda 7c — Folha jurídica imprimível + favoritos pessoais (atalho B).
import { FinTranscriptPDF } from './_components/FinTranscriptPDF';
import { useFinFavs, FinFavPin } from './_components/useFinFavs';
// Onda 9 — Resumo executivo do mês (narrativa compute-based · plug LLM Fase 2).
import { FinMonthResumeDialog } from './_components/FinMonthResume';
// Onda 10 (canon 100%) — Edit panel inline real (FinSubNav + FinAgeing REMOVIDOS
// 2026-05-18 Wagner: visualmente duplicados — sidebar já navega entre Fluxo/DRE/etc,
// e ageing bar é insight contextual no drawer, não strip permanente).
import { FinEditPanel } from './_components/FinEditPanel';
// Onda Edit 2026-05-18 — Sheet inline pra editar título financeiro.
import { TituloEditSheet } from './_components/TituloEditSheet';

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
  categoria_id: number | null;
  conta_bancaria: string;
  vencimento: string;            // ISO yyyy-mm-dd
  vencimento_label: string;      // "qua, 14 mai"
  liquidacao: string | null;
  valor: number;
  nfe_numero: string | null;
  canal: string | null;
  observacao: string | null;
  // Onda Edit 2026-05-18 — conferido per-user (DB-backed) + valor_mutavel pós-baixa.
  conferido_by: number | null;
  conferido_at: string | null;
  conferido_user_nome: string | null;
  valor_mutavel: boolean;
}

interface Kpi {
  saldo_previsto: number;
  recebido: { valor: number; qtd: number };
  a_receber: { valor: number; qtd: number };
  pago: { valor: number; qtd: number };
  a_pagar: { valor: number; qtd: number };
}

type TabId = 'all' | 'open' | 'rec' | 'pay' | 'received' | 'paid' | 'late';
// Onda Polish 2026-05-18 — lifecycle multi-select (gap analysis Wagner):
//  ar = A receber (kind=receivable, status aberto/parcial/atrasado/vencendo)
//  re = Recebidas (kind=receivable, status quitado)
//  ap = A pagar   (kind=payable, status aberto/parcial/atrasado/vencendo)
//  pa = Pagas     (kind=payable, status quitado)
type LifecycleId = 'ar' | 're' | 'ap' | 'pa';

interface Filters {
  tab: TabId;              // Legacy — preservado pra back-compat de bookmarks.
  lifecycle: LifecycleId[]; // Onda Polish — multi-select.
  overdue: boolean;        // Toggle "Só atrasados" independente.
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

// Onda Polish 2026-05-18 — 4 lifecycle checkboxes (multi-select) + 1 overdue toggle.
// Substitui TABS radio (1 escolha) por checkboxes lifecycle. Conforme gap analysis Wagner:
// "Eliana quer ver 'só pagas + só pagar' pra um relatório específico — radio não permite. Multi-checkbox sim."
const FILTER_LIFECYCLE: { id: LifecycleId; label: string; hue: number }[] = [
  { id: 'ar', label: 'A receber',  hue: 145 }, // verde
  { id: 're', label: 'Recebidas',  hue: 145 }, // verde (lifecycle complementar)
  { id: 'ap', label: 'A pagar',    hue: 25  }, // rose
  { id: 'pa', label: 'Pagas',      hue: 240 }, // azul (saída liquidada)
];

/**
 * Conta lançamentos por lifecycle (client-side rápido pra UX dos chips).
 * Match com a lógica de filter do backend `UnificadoController::applyLifecycleFilter`.
 */
function countByLifecycle(lc: LifecycleId, all: Lancamento[]): number {
  switch (lc) {
    case 'ar':
      return all.filter((l) => l.kind === 'receivable' && (l.status === 'aberto' || l.status === 'vencendo' || l.status === 'atrasado')).length;
    case 'ap':
      return all.filter((l) => l.kind === 'payable'    && (l.status === 'aberto' || l.status === 'vencendo' || l.status === 'atrasado')).length;
    case 're':
      return all.filter((l) => l.kind === 'receivable' && l.status === 'recebido').length;
    case 'pa':
      return all.filter((l) => l.kind === 'payable'    && l.status === 'pago').length;
  }
}

function countOverdue(all: Lancamento[]): number {
  return all.filter((l) => l.status === 'atrasado').length;
}

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
  // Onda 10 canon 100%: "Aberto" → "Pendente" (Cowork STATUS_STYLES canon ref).
  // Backend continua usando "aberto" interno (rotas/filtros legacy preservados).
  return { aberto: 'Pendente', recebido: 'Recebido', pago: 'Pago', atrasado: 'Atrasado', vencendo: 'Vencendo' }[s];
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
 * Onda 8c (2026-05-18): plugado em dados reais via /financeiro/unificado/saldo-sparkline.
 * Quando `points` recebido: gera path SVG normalizado pela faixa min/max. Fallback
 * mantém placeholder estático (loading/error/sem dados).
 */
interface SparkPoint { date: string; saldo: number; in: number; out: number }

function FinSparkline({ tone = 'pos', points }: { tone?: 'pos' | 'neg'; points?: SparkPoint[] | null }) {
  const color = tone === 'pos' ? 'oklch(0.78 0.13 145)' : 'oklch(0.65 0.18 25)';

  // Sem dados → fallback placeholder estático (Onda 8 inicial)
  if (!points || points.length < 2) {
    return (
      <svg className="fin-spark" viewBox="0 0 200 36" preserveAspectRatio="none" aria-hidden="true">
        <defs>
          <linearGradient id="finSparkG" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stopColor={color} stopOpacity="0.5" />
            <stop offset="100%" stopColor={color} stopOpacity="0" />
          </linearGradient>
        </defs>
        <path d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8 L200,36 L0,36 Z" fill="url(#finSparkG)" />
        <path d="M0,30 L15,26 L30,22 L45,20 L60,18 L75,22 L90,16 L105,18 L120,14 L135,12 L150,16 L165,10 L180,12 L200,8" stroke={color} strokeWidth="1.5" fill="none" />
        <line x1="0" y1="24" x2="200" y2="24" stroke="oklch(0.65 0.01 80)" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" />
      </svg>
    );
  }

  // Gera path a partir dos pontos reais. Normaliza Y entre [4, 32] pra margem visual.
  const W = 200;
  const H = 36;
  const PADDING_Y = 4;
  const innerH = H - PADDING_Y * 2;
  const saldos = points.map((p) => p.saldo);
  const minS = Math.min(...saldos);
  const maxS = Math.max(...saldos);
  const range = maxS - minS || 1;

  const linePath = points
    .map((p, i) => {
      const x = (i / (points.length - 1)) * W;
      const y = H - PADDING_Y - ((p.saldo - minS) / range) * innerH;
      return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`;
    })
    .join(' ');

  const fillPath = `${linePath} L${W},${H} L0,${H} Z`;
  const firstSaldo = points[0]?.saldo ?? 0;
  const baselineY = H - PADDING_Y - ((firstSaldo - minS) / range) * innerH;

  return (
    <svg className="fin-spark" viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none" aria-hidden="true">
      <defs>
        <linearGradient id="finSparkG" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.5" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={fillPath} fill="url(#finSparkG)" />
      <path d={linePath} stroke={color} strokeWidth="1.5" fill="none" strokeLinejoin="round" strokeLinecap="round" />
      <line x1="0" y1={baselineY} x2={W} y2={baselineY} stroke="oklch(0.65 0.01 80)" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" />
    </svg>
  );
}

/** Hook fetch sparkline 30d via endpoint backend (Tier 0 multi-tenant via session). */
function useSparkline30d(): SparkPoint[] | null {
  const [points, setPoints] = useState<SparkPoint[] | null>(null);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const resp = await fetch('/financeiro/unificado/saldo-sparkline', {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
        });
        if (!resp.ok) return;
        const data = await resp.json();
        if (!cancelled && Array.isArray(data?.points)) {
          setPoints(data.points);
        }
      } catch {
        // silencioso — sparkline fica em placeholder
      }
    })();
    return () => { cancelled = true; };
  }, []);

  return points;
}

function KpiBar({ kpis, onLifecycleSelect }: { kpis: Kpi; onLifecycleSelect: (lifecycle: LifecycleId[]) => void }) {
  // Onda 8 Cowork: hero card dark green com sparkline + 4 secundários canon.
  // Saldo previsto = posição final do mês (Recebido + AReceber - Pago - APagar).
  // Onda Polish: KPI clicável → lifecycle multi-select (não mais tab radio).
  // Onda 8c: sparkline alimentado por endpoint backend (30d real).
  const pendente = kpis.a_receber.valor - kpis.a_pagar.valor;
  const sparkPoints = useSparkline30d();
  return (
    <div className="fin-stats">
      <button type="button" className="fin-stat fin-stat-hero" onClick={() => onLifecycleSelect(['ar', 'ap'])} aria-label="Filtrar abertos (a receber + a pagar)">
        <small>Saldo previsto · maio</small>
        <b>{brl(kpis.saldo_previsto)}</b>
        <span className="fin-stat-hint">
          <b className="mono">{brl(kpis.recebido.valor - kpis.pago.valor)}</b> realizado · <span className={pendente >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(pendente)}</span> pendente
        </span>
        <FinSparkline tone={kpis.saldo_previsto >= 0 ? 'pos' : 'neg'} points={sparkPoints} />
      </button>

      <button type="button" className="fin-stat" onClick={() => onLifecycleSelect(['re'])} aria-label="Filtrar recebidas">
        <small>Recebido</small>
        <b className="fin-num-pos">{brl(kpis.recebido.valor)}</b>
        <span className="fin-stat-hint">{kpis.recebido.qtd} entradas confirmadas</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onLifecycleSelect(['ar'])} aria-label="Filtrar a receber">
        <small>A receber</small>
        <b>{brl(kpis.a_receber.valor)}</b>
        <span className="fin-stat-hint">{kpis.a_receber.qtd} títulos</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onLifecycleSelect(['pa'])} aria-label="Filtrar pagas">
        <small>Pago</small>
        <b className="fin-num-neg">{brl(kpis.pago.valor)}</b>
        <span className="fin-stat-hint">{kpis.pago.qtd} saídas liquidadas</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onLifecycleSelect(['ap'])} aria-label="Filtrar a pagar">
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

function LinhaTabela({ row, dens, selected, onSelect, onBaixar, conferido, comments, isFav }: {
  row: Lancamento; dens: typeof DENSITY[keyof typeof DENSITY]; selected: boolean;
  onSelect: () => void; onBaixar: () => void;
  conferido: UseFinConferidoApi; comments: UseFinCommentsApi;
  isFav: boolean;
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
      <td className="pl-4 pr-2">
        {/* Onda 8b Cowork canon: direction arrow com bg pill semântico.
            Receivable = ↘ entrada (emerald), Payable = ↗ saída (rose).
            Settled tem opacidade reduzida (cor "tomada"). */}
        <span
          className="inline-grid place-items-center rounded"
          style={{
            width: 22,
            height: 22,
            background: isIn
              ? (settled ? 'oklch(0.94 0.04 145 / 0.6)' : 'oklch(0.94 0.06 145)')
              : (settled ? 'oklch(0.95 0.03 25 / 0.6)' : 'oklch(0.95 0.04 25)'),
            color: isIn ? 'oklch(0.40 0.13 145)' : 'oklch(0.50 0.18 25)',
            fontSize: 14,
            fontWeight: 700,
          }}
          aria-label={isIn ? 'Entrada' : 'Saída'}
        >
          {isIn ? '↘' : '↗'}
        </span>
      </td>
      <td className="px-2">
        <div className="font-medium text-stone-900 truncate max-w-[260px] flex items-center gap-1.5">
          <FinCrossLinkify text={row.descricao} className="truncate" />
          <FinFavPin active={isFav} />
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

  // Cowork KB-9.75 Onda Edit (2026-05-18) — conferido per-user DB (substitui Onda 5 localStorage).
  const conferido = useFinConferido(lancamentos);
  const comments = useFinComments();
  // Cowork KB-9.75 Onda 7 R3 — trilha fechamento dialog state.
  const [checklistOpen, setChecklistOpen] = useState(false);
  // Cowork KB-9.75 Onda 7b — Troubleshooter + Presentation Mode states.
  const [troubleOpen, setTroubleOpen] = useState(false);
  const [presentOpen, setPresentOpen] = useState(false);
  // Cowork KB-9.75 Onda 7c — Folha imprimível + favoritos pessoais (localStorage).
  const [transcriptOpen, setTranscriptOpen] = useState(false);
  const [transcriptOnlyFavs, setTranscriptOnlyFavs] = useState(false);
  const favs = useFinFavs(businessName || 'default');
  // Cowork KB-9.75 Onda 9 — Resumir mês dialog (narrativa exec compute-based).
  const [resumoOpen, setResumoOpen] = useState(false);
  // Drawer Cowork v2.1 — canon align com bundle vendas-financeiro-completo
  // (Anthropic API design fetch 2026-05-18). 3 abas canônicas:
  //   detalhes (verde 145, default) / ia (roxo 295) / editar (amber 60, inline)
  // Reset pra 'detalhes' ao trocar de linha.
  const [drawerTab, setDrawerTab] = useState<'detalhes' | 'ia' | 'editar'>('detalhes');
  useEffect(() => { setDrawerTab('detalhes'); }, [selectedId]);
  // Onda Edit 2026-05-18 — Edit Sheet state (separate from detail drawer).
  const [editOpen, setEditOpen] = useState(false);

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

  // Atalhos: Cmd+K → palette, / → busca, B → toggle favorito da linha selecionada
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement;
      const inEditable = target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.isContentEditable;
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); setPaletteOpen(true); return; }
      if (e.key === '/' && !inEditable) {
        e.preventDefault(); document.getElementById('fin-search-input')?.focus(); return;
      }
      // Onda 7c — B (bookmark) toggle favorito da linha atualmente selecionada
      if (e.key === 'b' && !inEditable && selectedId !== null) {
        e.preventDefault();
        favs.toggle(selectedId);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [selectedId, favs]);

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

  // Onda 12 — Summary numérica no footer (paridade canon REAL):
  // "N lançamentos · Total entrada: R$ X · Total saída: R$ Y"
  // Compute client-side a partir de `lancamentos` prop (já tem business_id scope no controller).
  const footerSummary = useMemo(() => {
    const entrada = lancamentos
      .filter((l) => l.kind === 'receivable')
      .reduce((acc, l) => acc + (l.valor ?? 0), 0);
    const saida = lancamentos
      .filter((l) => l.kind === 'payable')
      .reduce((acc, l) => acc + (l.valor ?? 0), 0);
    return { count: lancamentos.length, entrada, saida };
  }, [lancamentos]);

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
            <Search size={13} />
            Buscar
            <kbd>⌘K</kbd>
          </button>
          <button
            type="button"
            className="fin-btn fin-btn-ai"
            title="Resumo executivo do mês (narrativa compute-based · Onda 9 v1)"
            onClick={() => setResumoOpen(true)}
          >
            <Sparkles size={13} />
            Resumir mês
          </button>
          <button
            type="button"
            className="fin-btn fin-btn-trilha"
            onClick={() => setChecklistOpen(true)}
            title="Trilha de 12 passos do fechamento mensal"
          >
            <CheckSquare size={13} />
            Fechamento
          </button>
          <button
            type="button"
            className="fin-btn fin-btn-present"
            title="Modo apresentação fullscreen (Esc fecha · 1/2/3 muda vista)"
            onClick={() => setPresentOpen(true)}
          >
            <Play size={13} />
            Apresentar
          </button>
          <button
            type="button"
            className="fin-btn"
            title={`Folha jurídica imprimível${favs.count > 0 ? ` · ${favs.count} favorito${favs.count === 1 ? '' : 's'}` : ''}`}
            onClick={() => { setTranscriptOnlyFavs(false); setTranscriptOpen(true); }}
          >
            <Printer size={13} />
            Imprimir
            {favs.count > 0 && <span className="fin-btn-badge">{favs.count}★</span>}
          </button>
          <button type="button" className="fin-btn" onClick={() => router.visit('/financeiro/extrato')}>
            <RefreshCw size={13} />
            Conciliar
          </button>
          <button type="button" className="fin-btn" onClick={() => router.visit('/financeiro/plano-contas')} title="Plano de contas — categorias contábeis">
            <FolderOpen size={13} />
            Plano de contas
          </button>
          {/* Onda 12 — Download icon-only (paridade canon REAL — botão entre Plano contas e CTA Novo).
              Onclick stub abre ⌘K palette por enquanto; handler real (Exportar XLSX/PDF) vira US futura. */}
          <button
            type="button"
            className="fin-btn"
            title="Exportar lançamentos do período (XLSX / PDF)"
            aria-label="Exportar"
            onClick={() => setPaletteOpen(true)}
            style={{ padding: '0 8px' }}
          >
            <Download size={13} />
          </button>
          <button type="button" className="fin-btn primary" onClick={() => router.visit('/financeiro/unificado/novo')}>
            <Plus size={13} />
            Novo lançamento
          </button>
        </div>
      </div>

      <KpiBar kpis={kpis} onLifecycleSelect={(lifecycle) => aplicar({ lifecycle })} />

      {/* Onda 12 (2026-05-19) — FinMonthDigest REMOVIDO (não-canon).
          Wagner pediu paridade 100% com canon REAL (/cowork-preview/Oimpresso ERP - Chat.html),
          que NÃO tem o sub-header "+ Resumo do mês" colapsável (era addition Onda 6 R2 IA).
          Componente FinMonthDigest preservado em _components/ pra possível US futura. */}

      {/* Onda Polish 2026-05-18 — gap analysis Wagner: filtros viraram 4 checkboxes
          lifecycle multi-select + toggle "Só atrasados" independente. Radio (1 escolha)
          não permitia "só pagas + só pagar" — Eliana pediu pra relatório.
          Hue por status semântico (oklch tokens canon):
            verde 145 = A receber/Recebidas (entrada cash)
            rose  25  = A pagar/Atraso (saída cash + alerta)
            azul  240 = Pagas (saída liquidada) */}
      <div className="fin-toolbar mt-4">
        <div className="fin-filter-group" role="group" aria-label="Filtros por ciclo de vida">
          {FILTER_LIFECYCLE.map((lc) => {
            const on = filters.lifecycle.includes(lc.id);
            const count = countByLifecycle(lc.id, lancamentos);
            const toggle = () => {
              const next = on
                ? filters.lifecycle.filter((x) => x !== lc.id)
                : [...filters.lifecycle, lc.id];
              aplicar({ lifecycle: next });
            };
            return (
              <label
                key={lc.id}
                className={'fin-filter-cb' + (on ? ' on' : '')}
                // Onda 12 refine — hue SEMPRE setada (mesmo OFF) pra borda semântica
                // persistir (paridade canon REAL: pills coloridos mesmo desligados).
                style={{ ['--cb-hue' as string]: lc.hue } as React.CSSProperties}
              >
                <input
                  type="checkbox"
                  name={`fin-lifecycle-${lc.id}`}
                  checked={on}
                  onChange={toggle}
                />
                <span className="fin-filter-cb-box" />
                <span>{lc.label}</span>
                {/* Onda 12 refine — count sempre visível (paridade canon: mostra 0 também). */}
                <span className="fin-filter-ct">{count}</span>
              </label>
            );
          })}
        </div>

        <span className="fin-filter-sep" />

        {/* Toggle "Só atrasados" — separado dos checkboxes lifecycle. AND multiplicativo. */}
        <label
          className={'fin-filter-cb' + (filters.overdue ? ' on' : '')}
          style={filters.overdue ? ({ ['--cb-hue' as string]: 25 } as React.CSSProperties) : undefined}
          title="AND multiplicativo: combina com lifecycle ativos"
        >
          <input
            type="checkbox"
            name="fin-overdue"
            checked={filters.overdue}
            onChange={() => aplicar({ overdue: !filters.overdue })}
          />
          <span className="fin-filter-cb-box" />
          <span>Só atrasados</span>
          {countOverdue(lancamentos) > 0 && <span className="fin-filter-ct">{countOverdue(lancamentos)}</span>}
        </label>

        <span className="fin-filter-sep" />

        <select
          className="h-7 px-2 rounded-md border border-stone-200 text-[12px] bg-white"
          value={filters.conta}
          onChange={(e) => aplicar({ conta: e.target.value })}
          aria-label="Conta bancária"
        >
          <option value="">Todas as contas</option>
          {contas.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
        </select>

        <select
          className="h-7 px-2 rounded-md border border-stone-200 text-[12px] bg-white"
          value={filters.categoria}
          onChange={(e) => aplicar({ categoria: e.target.value })}
          aria-label="Categoria"
        >
          <option value="">Todas as categorias</option>
          {categorias.map((c) => <option key={c.id} value={c.id}>{c.nome}</option>)}
        </select>

        <div className="fin-toolbar-r">
          <div className="fin-search-wrap">
            <span aria-hidden="true">🔍</span>
            <input
              id="fin-search-input"
              placeholder="Buscar lançamento…"
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              onKeyDown={(e) => e.key === 'Enter' && aplicar({ busca })}
            />
            <kbd style={{ fontSize: 10, color: 'var(--fin-text-mute)' }}>/</kbd>
          </div>

          <div className="fin-density" role="group" aria-label="Densidade">
            {(['compact', 'comfortable', 'spacious'] as const).map((d) => (
              <button
                key={d}
                type="button"
                className={filters.densidade === d ? 'on' : ''}
                onClick={() => aplicar({ densidade: d })}
                aria-pressed={filters.densidade === d}
                aria-label={d}
                title={d}
              >
                {d === 'compact' ? '◰' : d === 'comfortable' ? '▦' : '▤'}
              </button>
            ))}
          </div>
        </div>
      </div>

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
                        isFav={favs.has(r.id)}
                      />
                    ))}
                  </React.Fragment>
                );
              })}
              {grupos.length === 0 && (
                <tr><td colSpan={7} className="py-16">
                  <div className="flex flex-col items-center gap-3 text-center">
                    <div className="text-sm text-stone-600">
                      {filters.lifecycle.length === 0 && !filters.overdue && !filters.busca && filters.conta === '' && filters.categoria === ''
                        ? `Nenhum lançamento em ${periodLabel}.`
                        : 'Nenhum lançamento com os filtros atuais.'}
                    </div>
                    {filters.lifecycle.length === 0 && !filters.overdue && !filters.busca && filters.conta === '' && filters.categoria === '' && (
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

      {/* Drawer detalhe — Cowork v2 (gap report 2026-05-18):
          nav `fin-drawer-tabs` separa Detalhes (info+actions) de ✦ IA (insights). */}
      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelectedId(null)}>
        <SheetContent side="right" className="fin-drawer-wide w-[460px] sm:max-w-[460px]">
          {selected && (
            <>
              <SheetHeader>
                <SheetTitle className="text-[16px]">
                  <FinCrossLinkify text={selected.descricao} />
                </SheetTitle>
              </SheetHeader>

              {/* Nav de abas — Cowork canon V2.1 (3 abas: Detalhes/IA/Editar) */}
              <nav className="fin-drawer-tabs" role="tablist" aria-label="Visualização do título">
                <button
                  type="button"
                  role="tab"
                  aria-selected={drawerTab === 'detalhes'}
                  className={'fin-drawer-tab' + (drawerTab === 'detalhes' ? ' on' : '')}
                  onClick={() => setDrawerTab('detalhes')}
                >
                  Detalhes
                  {selected.status === 'atrasado' && (
                    <span className="fin-drawer-tab-tag" aria-label="Atrasado" title="Lançamento atrasado" />
                  )}
                  {comments.countFor(selected.id) > 0 && (
                    <span className="fin-drawer-tab-ct">{comments.countFor(selected.id)}</span>
                  )}
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={drawerTab === 'ia'}
                  className={'fin-drawer-tab fin-drawer-tab-ai' + (drawerTab === 'ia' ? ' on' : '')}
                  onClick={() => setDrawerTab('ia')}
                >
                  ✦ IA
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={drawerTab === 'editar'}
                  className={'fin-drawer-tab fin-drawer-tab-edit' + (drawerTab === 'editar' ? ' on' : '')}
                  onClick={() => setDrawerTab('editar')}
                  disabled={!selected.valor_mutavel}
                  title={!selected.valor_mutavel ? 'Valor não-mutável após baixa (ADR fin-tech/0002)' : 'Editar inline'}
                >
                  ✎ Editar
                </button>
              </nav>

              {/* Aba Detalhes — info + audit + comments + actions */}
              {drawerTab === 'detalhes' && (
                <div className="mt-3 space-y-4 text-[13px]">
                  <div className="flex items-center gap-2 fin-toggles-row">
                    <StatusPill s={selected.status} />
                    <FinPillFrescor row={{ due: selected.vencimento, paid_at: (selected.status === 'recebido' || selected.status === 'pago') ? selected.liquidacao : null, vencimento: selected.vencimento }} />
                    <span className="ml-auto font-semibold tabular-nums text-[16px]">{brl(selected.valor)}</span>
                  </div>

                  <FinConferidoToggle rowId={selected.id} conferido={conferido} />

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

                  <div className="fin-drawer-footer">
                    {(selected.status !== 'recebido' && selected.status !== 'pago') && (
                      <Button onClick={() => onBaixar(selected.id)}>
                        {selected.kind === 'receivable' ? 'Marcar recebido' : 'Marcar pago'}
                      </Button>
                    )}
                    <Button variant="outline" className="fin-edit-btn" onClick={() => setEditOpen(true)}>Editar</Button>
                    <Button
                      variant="outline"
                      onClick={() => favs.toggle(selected.id)}
                      title="Atalho: B (com a linha selecionada)"
                    >
                      {favs.has(selected.id) ? '★ Favoritado' : '☆ Favoritar'}
                    </Button>
                    <Button variant="outline" className="ml-auto">Anexar</Button>
                  </div>
                </div>
              )}

              {/* Aba IA — insights computacionais (Anomaly + Party History) */}
              {drawerTab === 'ia' && (
                <div className="mt-3 space-y-4 text-[13px] fin-ai-panel">
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

                  <p className="text-[11.5px] text-stone-500 italic pt-2 border-t border-stone-100">
                    Insights computacionais · pure compute · Fase 2 plugará JanaService LLM
                  </p>
                </div>
              )}

              {/* Aba Editar — Onda 10 canon 100%: form INLINE real (PUT /financeiro/unificado/{id}) */}
              {drawerTab === 'editar' && (
                <div className="mt-3">
                  <FinEditPanel
                    lancamento={selected}
                    categorias={categorias}
                    onClose={() => setDrawerTab('detalhes')}
                  />
                </div>
              )}
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
            <CommandItem onSelect={() => { setPaletteOpen(false); setResumoOpen(true); }}>
              ✦ Resumir mês (narrativa exec)
            </CommandItem>
            <CommandItem onSelect={() => { setPaletteOpen(false); setTranscriptOnlyFavs(false); setTranscriptOpen(true); }}>
              📄 Imprimir período (folha jurídica)
            </CommandItem>
            {favs.count > 0 && (
              <CommandItem onSelect={() => { setPaletteOpen(false); setTranscriptOnlyFavs(true); setTranscriptOpen(true); }}>
                ★ Imprimir só favoritos ({favs.count})
              </CommandItem>
            )}
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

      {/* Onda 12 (2026-05-19) — footer canon REAL: summary numérica esquerda + atalhos direita.
          Match com /cowork-preview/Oimpresso ERP - Chat.html:
          "29 lançamentos · Total entrada: R$ 18.600 · Total saída: R$ 19.392" + atalhos */}
      <div className="fin-footer-tips">
        <span className="fin-footer-summary">
          <b>{footerSummary.count}</b> lançamento{footerSummary.count === 1 ? '' : 's'}
          <span className="fin-footer-sep">·</span>
          Total entrada: <b>{brl(footerSummary.entrada)}</b>
          <span className="fin-footer-sep">·</span>
          Total saída: <b>{brl(footerSummary.saida)}</b>
        </span>
        <span className="spacer" />
        <span><kbd>⌘K</kbd> palette</span>
        <span><kbd>/</kbd> buscar</span>
        <span><kbd>J</kbd>/<kbd>K</kbd> navegar</span>
        <span><kbd>␣</kbd> marcar pago/recebido</span>
        <span><kbd>B</kbd> favoritar linha</span>
        <FinTroubleButton onClick={() => setTroubleOpen(true)} />
        {favs.count > 0 && (
          <span>{favs.count} favorito{favs.count === 1 ? '' : 's'} ★</span>
        )}
      </div>

      {/* Onda 7b — Dialogs Troubleshooter + Presentation Mode */}
      <FinTroubleshooterDialog open={troubleOpen} onClose={() => setTroubleOpen(false)} />
      <FinPresentationMode
        open={presentOpen}
        onClose={() => setPresentOpen(false)}
        kpis={kpis}
        lancamentos={lancamentos}
        periodLabel={periodLabel}
        businessName={businessName}
      />

      {/* Onda 7c — Folha jurídica imprimível (fullscreen overlay + @print) */}
      <FinTranscriptPDF
        open={transcriptOpen}
        onClose={() => setTranscriptOpen(false)}
        lancamentos={lancamentos}
        periodLabel={periodLabel}
        businessName={businessName}
        onlyFavs={transcriptOnlyFavs ? favs.favs : null}
      />

      {/* Onda 9 — Resumo executivo do mês (narrativa compute-based, plug LLM Fase 2) */}
      <FinMonthResumeDialog
        open={resumoOpen}
        onClose={() => setResumoOpen(false)}
        lancamentos={lancamentos}
        kpis={kpis}
        periodLabel={periodLabel}
        businessName={businessName}
      />

      {/* Cowork KB-9.75 Onda 7 R3 — Trilha fechamento dialog */}
      <FinChecklistFechamento
        periodLabel={periodLabel}
        open={checklistOpen}
        onClose={() => setChecklistOpen(false)}
      />

      {/* Onda Edit 2026-05-18 — Sheet inline pra editar título */}
      {selected && (
        <TituloEditSheet
          open={editOpen}
          onClose={() => setEditOpen(false)}
          lancamento={selected}
          categorias={categorias}
        />
      )}
    </div>
  );
}

FinanceiroUnificado.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Visão unificada"
    breadcrumbItems={[{ label: 'Financeiro', href: '/financeiro' }, { label: 'Visão unificada' }]}
  >
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default FinanceiroUnificado;
