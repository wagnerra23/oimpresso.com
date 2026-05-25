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
import { router, usePage } from '@inertiajs/react';
import React, { useState, useMemo, useCallback, useEffect, type ReactNode } from 'react';
// Onda 12 (2026-05-19) — paridade 100% canon REAL (/cowork-preview/Oimpresso ERP - Chat.html):
// emoji → lucide-react nos 8 botões + Download icon adicional + remoção FinMonthDigest
// (não-canon) + summary numérica footer + KPI hero dark.
import { Search, Plus, Sparkles, CheckSquare, Play, Printer, RefreshCw, FolderOpen, Download, ChevronDown, TrendingUp, TrendingDown } from 'lucide-react';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import PageHeader from '@/Components/shared/PageHeader';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import FinanceiroPrimaryButton from '@/Pages/Financeiro/_shared/FinanceiroPrimaryButton';
import KpiCard from '@/Components/shared/KpiCard';
import { FinPillFrescor } from './_components/FinPillFrescor';
import { FinConferidoToggle, FinConferidoBadge, useFinConferido, type UseFinConferidoApi } from './_components/FinConferidoToggle';
import { FinCommentsThread, FinCommentsBadge, useFinComments, type UseFinCommentsApi } from './_components/FinCommentsThread';
import { FinAuditTrail } from './_components/FinAuditTrail';
// Onda 6 R2 IA — anomaly + party history (pure compute, sem backend).
// FinMonthDigest REMOVIDO Onda 12 (paridade canon REAL — não tem sub-header colapsável).
import { FinAnomalyDetector, finAnomalyDetect } from './_components/FinAnomalyDetector';
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
// US-FIN-026 (Onda 22) — painel completo de anexos no drawer (GET + upload + download + delete).
import { FinAnexosPanel } from './_components/FinAnexosPanel';
// US-FIN-029 (Onda 23) — Sheet OCR boleto (OpenAI Vision API extrai linha digitavel + valor + vencimento).
import { FinOcrBoletoSheet } from './_components/FinOcrBoletoSheet';

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
  // Onda 21 (2026-05-19) #55 — Workflow aprovação pra títulos a pagar.
  aprovacao_status: 'pendente' | 'aprovado' | 'rejeitado' | null;
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
// US-FIN-027 (Onda 22) — workflow aprovação multi-select.
//  pendente / aprovado / rejeitado / sem_workflow (NULL aprovacao_status)
type ApprovalStatusId = 'pendente' | 'aprovado' | 'rejeitado' | 'sem_workflow';

interface Filters {
  tab: TabId;              // Legacy — preservado pra back-compat de bookmarks.
  lifecycle: LifecycleId[]; // Onda Polish — multi-select.
  aprovacao_status: ApprovalStatusId[]; // US-FIN-027 (Onda 22).
  overdue: boolean;        // Toggle "Só atrasados" independente.
  busca: string;
  conta: string;
  categoria: string;
  periodo: string;
  // Onda 12.6 (2026-05-19) — Wagner: removed 'spacious' (não tinha uso real).
  densidade: 'compact' | 'comfortable';
  // Onda 8 (2026-05-20): sort por coluna via click no thead.
  sort: '' | 'vencimento' | 'valor' | 'status' | 'lancamento' | 'contraparte';
  dir: 'asc' | 'desc';
  // Onda 13 (2026-05-20): pagination.
  page: number;
  per_page: number;
}

interface Pagination {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
}

// Onda 12.7 (2026-05-19) — Plano de Contas hierárquico substitui Categorias.
interface PlanoConta {
  id: number;
  codigo: string;    // ex "1.1.01.001"
  nome: string;     // ex "Caixa"
  tipo: 'ativo'|'passivo'|'patrimonio'|'receita'|'despesa'|'custo';
  nivel: number;    // 1=raiz, 4=folha
}

interface Props {
  kpis: Kpi;
  lancamentos: Lancamento[];
  pagination?: Pagination; // Onda 13 (2026-05-20)
  filters: Filters;
  contas: { id: number; nome: string }[];
  categorias: { id: number; nome: string }[];
  planosConta: PlanoConta[];
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

// US-FIN-027 (Onda 22) — chips workflow aprovação. Hue semântico:
//  amber = pendente / emerald = aprovado / rose = rejeitado / stone = sem workflow.
const FILTER_APPROVAL: { id: ApprovalStatusId; label: string; hue: number }[] = [
  { id: 'pendente',     label: '⏳ Pendente',  hue: 60  },
  { id: 'aprovado',     label: '✓ Aprovado',   hue: 145 },
  { id: 'rejeitado',    label: '✗ Rejeitado',  hue: 25  },
  { id: 'sem_workflow', label: 'Sem workflow', hue: 220 },
];

function countByApproval(id: ApprovalStatusId, all: Lancamento[]): number {
  if (id === 'sem_workflow') return all.filter((l) => !l.aprovacao_status).length;
  return all.filter((l) => l.aprovacao_status === id).length;
}

// Onda 12.6 — apenas 2 modos (Wagner removeu 'spacious').
const DENSITY = {
  compact:     { row: 'h-8',  text: 'text-[12.5px]' },
  comfortable: { row: 'h-11', text: 'text-[13px]' },
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

/**
 * Onda 7 (2026-05-20) — Multi-select de contas bancárias.
 * Renderiza trigger texto compacto + Popover com lista de checkboxes.
 * Label dinâmico:
 *   - vazio       → "Todas as contas"
 *   - 1 selecionada → nome da conta
 *   - >1 selecionada → "N contas"
 */
/**
 * Onda 8 (2026-05-20) — Sortable header click toggle.
 * Click 1: asc · Click 2: desc · Click 3: clear (volta default vencimento asc).
 */
function SortableHeader({
  k,
  label,
  filters,
  aplicar,
  className,
  alignRight,
}: {
  k: NonNullable<Filters['sort']> | 'lancamento' | 'contraparte';
  label: string;
  filters: Filters;
  aplicar: (f: Partial<Filters>) => void;
  className?: string;
  alignRight?: boolean;
}) {
  const active = filters.sort === k;
  const dir = active ? filters.dir : null;
  const onClick = () => {
    if (!active) aplicar({ sort: k as Filters['sort'], dir: 'asc' });
    else if (filters.dir === 'asc') aplicar({ sort: k as Filters['sort'], dir: 'desc' });
    else aplicar({ sort: '', dir: 'asc' });
  };
  return (
    <th className={className}>
      <button
        type="button"
        onClick={onClick}
        className={`inline-flex items-center gap-1 ${alignRight ? 'justify-end w-full' : ''} ${active ? 'text-stone-900' : 'text-stone-500 hover:text-stone-700'} cursor-pointer select-none transition-colors`}
        aria-label={`Ordenar por ${label}`}
      >
        <span>{label}</span>
        <span className="text-[8px] opacity-70">
          {dir === 'asc' ? '▲' : dir === 'desc' ? '▼' : '⇅'}
        </span>
      </button>
    </th>
  );
}

function FinMultiSelectContas({
  contas,
  valueCSV,
  onChange,
}: {
  contas: { id: number; nome: string }[];
  valueCSV: string;
  onChange: (csv: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const ref = React.useRef<HTMLDivElement>(null);

  const selectedIds = useMemo(
    () => (valueCSV ? valueCSV.split(',').map(Number).filter(Boolean) : []),
    [valueCSV],
  );

  // Fechar ao clicar fora
  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    return () => document.removeEventListener('mousedown', onClick);
  }, [open]);

  const toggle = (id: number) => {
    const next = selectedIds.includes(id)
      ? selectedIds.filter((x) => x !== id)
      : [...selectedIds, id];
    onChange(next.join(','));
  };

  const label =
    selectedIds.length === 0
      ? 'Todas as contas'
      : selectedIds.length === 1
        ? contas.find((c) => c.id === selectedIds[0])?.nome ?? `Conta ${selectedIds[0]}`
        : `${selectedIds.length} contas`;

  return (
    <div ref={ref} className="relative inline-block">
      <button
        type="button"
        className={`h-7 px-2 rounded-md border text-[12px] bg-white flex items-center gap-1 ${selectedIds.length > 0 ? 'border-stone-300 text-stone-800' : 'border-stone-200 text-stone-600'}`}
        onClick={() => setOpen((o) => !o)}
        aria-label="Conta bancária multi-select"
        aria-expanded={open}
      >
        <span>{label}</span>
        <span className="text-stone-400 text-[10px]">▾</span>
      </button>
      {open && (
        <div className="absolute z-50 top-[110%] left-0 min-w-[220px] max-h-[320px] overflow-y-auto rounded-md border border-stone-200 bg-white shadow-lg p-1">
          <button
            type="button"
            className="w-full text-left px-2 py-1.5 text-[12px] text-stone-600 hover:bg-stone-50 rounded"
            onClick={() => onChange('')}
          >
            <span className="inline-flex items-center gap-2">
              <input type="checkbox" checked={selectedIds.length === 0} readOnly className="accent-stone-700" />
              Todas as contas
            </span>
          </button>
          <div className="h-px bg-stone-100 my-1" />
          {contas.map((c) => {
            const checked = selectedIds.includes(c.id);
            return (
              <button
                key={c.id}
                type="button"
                className="w-full text-left px-2 py-1.5 text-[12px] hover:bg-stone-50 rounded flex items-center gap-2"
                onClick={() => toggle(c.id)}
              >
                <input type="checkbox" checked={checked} readOnly className="accent-stone-700" />
                <span className={checked ? 'text-stone-900 font-medium' : 'text-stone-700'}>{c.nome}</span>
              </button>
            );
          })}
        </div>
      )}
    </div>
  );
}

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
 * US-FIN-027 (Onda 22) — Pill workflow aprovação na linha da tabela.
 * NULL invisível (back-compat — maioria dos títulos não tem workflow).
 */
function ApprovalPill({ s }: { s: 'pendente' | 'aprovado' | 'rejeitado' | null }) {
  if (!s) return null;
  const map = {
    pendente:  { cls: 'bg-amber-50 text-amber-700 border-amber-200', icon: '⏳', label: 'Aprov?' },
    aprovado:  { cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', icon: '✓', label: 'Aprov.' },
    rejeitado: { cls: 'bg-rose-50 text-rose-700 border-rose-200', icon: '✗', label: 'Rejeit.' },
  }[s];
  return (
    <span
      className={`inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded border text-[10.5px] font-medium ${map.cls}`}
      title={`Workflow aprovação: ${map.label}`}
    >
      <span>{map.icon}</span>
      <span>{map.label}</span>
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

  // Fallback placeholder estático (canon Cowork v1). Usado quando:
  //  - sem dados (points null ou < 2)
  //  - saldos todos iguais (range == 0 → linha plana invisível, ex.: biz novo
  //    sem baixas no período, ou seeder demo sem TituloBaixa). Wagner pediu
  //    refazer 2026-05-20: melhor mostrar oscilação visual canon do que linha
  //    horizontal "morta" no hero card preto.
  const renderPlaceholder = !points || points.length < 2 || (() => {
    if (!points || points.length < 2) return true;
    const saldos = points.map((p) => p.saldo);
    return Math.max(...saldos) === Math.min(...saldos);
  })();

  if (renderPlaceholder) {
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

  // Onda 6 (2026-05-20): hover tooltip via SVG <title> nativo + circles invisíveis
  // por ponto (clickable area). Mouseover → browser mostra "DD/MM · R$ X,XX (+R$ Y in / -R$ Z out)"
  const fmtBR = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 }).format(v ?? 0);
  return (
    <svg className="fin-spark" viewBox={`0 0 ${W} ${H}`} preserveAspectRatio="none">
      <defs>
        <linearGradient id="finSparkG" x1="0" x2="0" y1="0" y2="1">
          <stop offset="0%" stopColor={color} stopOpacity="0.5" />
          <stop offset="100%" stopColor={color} stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={fillPath} fill="url(#finSparkG)" aria-hidden="true" />
      <path d={linePath} stroke={color} strokeWidth="1.5" fill="none" strokeLinejoin="round" strokeLinecap="round" aria-hidden="true" />
      <line x1="0" y1={baselineY} x2={W} y2={baselineY} stroke="oklch(0.65 0.01 80)" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" aria-hidden="true" />
      {/* Hotspots invisíveis por ponto — hover mostra title nativo do browser */}
      {points.map((p, i) => {
        const x = (i / (points.length - 1)) * W;
        const y = H - PADDING_Y - ((p.saldo - minS) / range) * innerH;
        const dateStr = p.date.split('-').reverse().slice(0, 2).join('/'); // YYYY-MM-DD → DD/MM
        const ioStr = (p.in > 0 ? ` · +${fmtBR(p.in)}` : '') + (p.out > 0 ? ` · -${fmtBR(p.out)}` : '');
        return (
          <circle key={i} cx={x} cy={y} r={4} fill="transparent" style={{ pointerEvents: 'auto', cursor: 'help' }}>
            <title>{`${dateStr} · saldo ${fmtBR(p.saldo)}${ioStr}`}</title>
          </circle>
        );
      })}
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

function KpiBar({ kpis, lancamentos, onLifecycleSelect }: { kpis: Kpi; lancamentos: Lancamento[]; onLifecycleSelect: (lifecycle: LifecycleId[]) => void }) {
  // Onda 8 Cowork: hero card dark green com sparkline + 4 secundários canon.
  // Saldo previsto = posição final do mês (Recebido + AReceber - Pago - APagar).
  // Onda Polish: KPI clicável → lifecycle multi-select (não mais tab radio).
  // Onda 8c: sparkline alimentado por endpoint backend (30d real).
  // PR 2/5 (2026-05-20): breakdown rico KPIs (Wagner Fase 4 gap dim 4):
  //  - "A receber" agora mostra "R$ X em atraso" (canon: 6 entradas /
  //     R$ [redacted Tier 0] em atraso). Calc client-side filtra status=atrasado.
  //  - "A pagar" agora mostra "próx. <dia mes> · <contraparte>" (canon:
  //     "próx. 10 mai · Suprigraf"). Calc client-side ordena payables
  //     abertos por vencimento.
  const pendente = kpis.a_receber.valor - kpis.a_pagar.valor;
  const sparkPoints = useSparkline30d();

  // PR 2 — hint rico A receber: valor atrasado (subset de a_receber).
  const atrasadoReceber = useMemo(() => {
    return lancamentos
      .filter((l) => l.kind === 'receivable' && l.status === 'atrasado')
      .reduce((acc, l) => acc + (l.valor ?? 0), 0);
  }, [lancamentos]);

  // PR 2 — hint rico A pagar: próximo vencimento + contraparte.
  // Filtra payables abertos/vencendo/atrasado, ordena por vencimento ASC,
  // pega o primeiro. Atrasado conta também pq "próx" semanticamente = "próximo
  // que vc precisa pagar agora" (canon).
  const proxPagar = useMemo(() => {
    const candidates = lancamentos
      .filter((l) => l.kind === 'payable' && (l.status === 'aberto' || l.status === 'vencendo' || l.status === 'atrasado'))
      .sort((a, b) => a.vencimento.localeCompare(b.vencimento));
    const first = candidates[0];
    if (!first) return null;
    // Format "10 mai" pt-BR
    const parts = first.vencimento.split('-');
    const mm = parts[1] ?? '01';
    const dd = parts[2] ?? '01';
    const MES = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    const mesAbrev = MES[parseInt(mm, 10) - 1] ?? '???';
    return { label: `${parseInt(dd, 10)} ${mesAbrev}`, contraparte: first.contraparte };
  }, [lancamentos]);

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
        {/* PR 2 — canon hint: "R$ X em atraso" se houver atrasados; fallback genérico. */}
        <span className="fin-stat-hint">
          {atrasadoReceber > 0
            ? <><span className="fin-num-neg mono">{brl(atrasadoReceber)}</span> em atraso</>
            : <>{kpis.a_receber.qtd} títulos</>}
        </span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onLifecycleSelect(['pa'])} aria-label="Filtrar pagas">
        <small>Pago</small>
        <b className="fin-num-neg">{brl(kpis.pago.valor)}</b>
        <span className="fin-stat-hint">{kpis.pago.qtd} saídas liquidadas</span>
      </button>

      <button type="button" className="fin-stat" onClick={() => onLifecycleSelect(['ap'])} aria-label="Filtrar a pagar">
        <small>A pagar</small>
        <b>{brl(kpis.a_pagar.valor)}</b>
        {/* PR 2 — canon hint: "próx. <dia mes> · <contraparte>" do primeiro payable aberto. */}
        <span className="fin-stat-hint">
          {proxPagar
            ? <>próx. <b>{proxPagar.label}</b> · {proxPagar.contraparte}</>
            : <>{kpis.a_pagar.qtd} títulos</>}
        </span>
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

function LinhaTabela({ row, dens, selected, onSelect, onBaixar, conferido, comments, isFav, bulkSelected, onToggleBulk }: {
  row: Lancamento; dens: typeof DENSITY[keyof typeof DENSITY]; selected: boolean;
  onSelect: () => void; onBaixar: () => void;
  conferido: UseFinConferidoApi; comments: UseFinCommentsApi;
  isFav: boolean;
  // Onda 12 (2026-05-20): bulk-select checkbox state.
  bulkSelected: boolean;
  onToggleBulk: () => void;
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
      className={`${dens.row} ${dens.text} border-b border-stone-100 hover:bg-stone-50/60 cursor-pointer ${selected ? 'bg-amber-50/40' : ''} ${bulkSelected ? 'bg-blue-50/50' : ''}`}
      onClick={onSelect}
    >
      {/* Onda 12 (2026-05-20): checkbox bulk-select. stopPropagation pra nao abrir drawer. */}
      <td className="pl-4 pr-1" onClick={(e) => e.stopPropagation()}>
        <input
          type="checkbox"
          checked={bulkSelected}
          onChange={onToggleBulk}
          aria-label={`Selecionar lançamento ${row.descricao}`}
          className="accent-stone-700 cursor-pointer"
        />
      </td>
      <td className="pl-1 pr-2">
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
      {/* PR 4 (Wagner Fase 4 dim 7/8): coluna VENCIMENTO explicita
          formato canon: "dd/mm" + label temporal (paid_at / "ha N dias" / "vencendo"). */}
      <td className="px-2 text-stone-700 text-[12px] whitespace-nowrap">
        <div className="font-medium text-stone-900">
          {(() => {
            const parts = row.vencimento.split('-');
            const dd = parts[2] ?? '01';
            const mm = parts[1] ?? '01';
            return `${dd}/${mm}`;
          })()}
        </div>
        {row.liquidacao && (
          <div className="text-[10px] text-stone-500">pago {row.liquidacao}</div>
        )}
        {!row.liquidacao && (row.status === 'atrasado' || row.status === 'vencendo') && (
          <div className={`text-[10px] ${row.status === 'atrasado' ? 'text-rose-600' : 'text-amber-600'}`}>
            {row.status === 'atrasado' ? 'em atraso' : 'vencendo'}
          </div>
        )}
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
      {/* Onda 9 (2026-05-20): categoria pill com cor semantica (in=verde, out=âmbar)
          pra visual scan rapido por kind sem ler valor. */}
      <td className="px-2 truncate max-w-[140px]">
        <span className={`inline-flex items-center px-1.5 py-0.5 rounded text-[10.5px] font-medium border ${isIn ? 'bg-emerald-50/60 text-emerald-700 border-emerald-100' : 'bg-amber-50/60 text-amber-700 border-amber-100'}`}>
          {row.categoria}
        </span>
      </td>
      <td className="px-2"><div className="flex items-center gap-1.5"><StatusPill s={row.status} /><FinPillFrescor row={frescorRow} compact /><ApprovalPill s={row.aprovacao_status} /></div></td>
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
// (FinanceiroSubNav extraído pra `_shared/FinanceiroSubNav.tsx` 2026-05-21 — ADR 0180 Fase 5 propagação)

function FinanceiroUnificado({ kpis, lancamentos, pagination, filters, contas, categorias, planosConta, periodLabel, businessName }: Props) {
  // US-FIN-028 (Onda 22) — gate Spatie pra aprovar/rejeitar.
  // HOTFIX 2026-05-20: shared `auth.can` vem do HandleInertiaRequests.share como
  // OBJETO `Record<string, boolean>` (não array de strings — vide app/Http/Middleware/
  // HandleInertiaRequests.php::userPermissions). Onda 22 assumiu array → .includes()
  // em objeto crasheia ("N.includes is not a function") → tela branca prod.
  // Defesa: aceita ambas formas (objeto OU array legacy) por segurança.
  const pageProps = (usePage() as any).props ?? {};
  const userCanRaw = pageProps?.auth?.can ?? {};
  const canApprove = Array.isArray(userCanRaw)
    ? (userCanRaw.includes('financeiro.titulo.aprovar') || userCanRaw.includes('superadmin'))
    : (userCanRaw['financeiro.titulo.aprovar'] === true || userCanRaw['superadmin'] === true);

  const [busca, setBusca] = useState(filters.busca ?? '');
  const [selectedId, setSelectedId] = useState<number | null>(null);
  // Onda 12 (2026-05-20): bulk select multi-row via checkbox.
  // Canon: footer mostra "N selecionados · +totalIn / -totalOut · Editar lote · Limpar"
  // quando há items selecionados (substitui summary numérica padrão).
  const [selectedRows, setSelectedRows] = useState<Set<number>>(new Set());
  const toggleRow = useCallback((id: number) => {
    setSelectedRows((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  }, []);
  const clearSelection = useCallback(() => setSelectedRows(new Set()), []);
  // Onda 15 (2026-05-20): bulk edit categoria modal state
  const [bulkCategoriaOpen, setBulkCategoriaOpen] = useState(false);
  const [bulkCategoriaId, setBulkCategoriaId] = useState<number | null>(null);
  const submitBulkCategoria = useCallback(() => {
    if (!bulkCategoriaId || selectedRows.size === 0) return;
    router.post('/financeiro/unificado/bulk-update-categoria', {
      ids: Array.from(selectedRows),
      categoria_id: bulkCategoriaId,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setBulkCategoriaOpen(false);
        setBulkCategoriaId(null);
        clearSelection();
      },
    });
  }, [bulkCategoriaId, selectedRows, clearSelection]);
  const [paletteOpen, setPaletteOpen] = useState(false);
  // Onda 12.6 — default compact (Wagner pediu: financeiro denso).
  const dens = DENSITY[filters.densidade ?? 'compact'];

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
  // US-FIN-029 (Onda 23) — Sheet OCR boleto.
  const [ocrSheetOpen, setOcrSheetOpen] = useState(false);

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

  // Atalhos keyboard:
  //   ⌘K/Ctrl+K → palette
  //   / → busca focus
  //   J / ↓ → próxima linha
  //   K / ↑ → linha anterior
  //   ␣ (Space) → toggle baixar (pago/recebido) da linha focada
  //   Enter → abre drawer da linha focada
  //   B → toggle favorito (Onda 7c)
  //   Esc → fecha drawer / limpa bulk
  // Onda 11 (2026-05-20 Wagner): J/K/␣/Esc adicionados — Eliana-persona power-user.
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
        return;
      }
      // Onda 11 — J/K (ou ↓/↑) navegar linhas
      if (!inEditable && (e.key === 'j' || e.key === 'ArrowDown')) {
        e.preventDefault();
        const ids = lancamentos.map((l) => l.id);
        if (ids.length === 0) return;
        const currentIdx = selectedId !== null ? ids.indexOf(selectedId) : -1;
        const nextIdx = currentIdx < 0 ? 0 : Math.min(currentIdx + 1, ids.length - 1);
        setSelectedId(ids[nextIdx] ?? null);
        return;
      }
      if (!inEditable && (e.key === 'k' || e.key === 'ArrowUp')) {
        e.preventDefault();
        const ids = lancamentos.map((l) => l.id);
        if (ids.length === 0) return;
        const currentIdx = selectedId !== null ? ids.indexOf(selectedId) : -1;
        const prevIdx = currentIdx <= 0 ? 0 : currentIdx - 1;
        setSelectedId(ids[prevIdx] ?? null);
        return;
      }
      // Onda 11 — ␣ (Space) marcar pago/recebido da linha focada
      if (!inEditable && e.key === ' ' && selectedId !== null) {
        e.preventDefault();
        const row = lancamentos.find((l) => l.id === selectedId);
        if (row && (row.status === 'aberto' || row.status === 'atrasado' || row.status === 'vencendo')) {
          onBaixar(selectedId);
        }
        return;
      }
      // Onda 11 — Esc fecha drawer OU limpa bulk selection
      if (!inEditable && e.key === 'Escape') {
        if (selectedRows.size > 0) {
          e.preventDefault();
          clearSelection();
        } else if (selectedId !== null) {
          e.preventDefault();
          setSelectedId(null);
        }
        return;
      }
      // Onda 11 — Enter abre drawer da linha focada (se não tem drawer aberto)
      if (!inEditable && e.key === 'Enter' && selectedId !== null) {
        // selectedId já tá setado → drawer já abre. No-op se já aberto.
        return;
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [selectedId, favs, lancamentos, selectedRows, clearSelection, onBaixar]);

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
      {/* Onda 12.3 (2026-05-19) — markup canon EXATO `os-page-h` (DOM forensics
          canon REAL). Bundle CSS canon inteiro importado em inertia.css garante
          que classes existem (sem tree-shake como acontecia com cherry-pick). */}
      <div className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>
            Financeiro <span className="fin-hero-title-sub">· Visão unificada</span>
          </h1>
          <p>{periodLabel}{businessName ? ` · ${businessName}` : ''} · caixa unificado</p>
        </div>
        <div className="os-page-h-r fin-page-h-r">
          {/* ADR 0180 Fase 5 tweak2 Wagner 2026-05-21 — header em UMA linha:
                ghost tabs (esquerda) + ⋯ Mais (botões action features) + primary "+ Novo" (direita)
              - Ghost tabs ARIA navegação entre 13 sub-views do Financeiro
              - Primary "+ Novo título" SEPARADO no canto direito (Wagner pediu lado oposto)
              - Botões action features-específicas (Buscar/Resumir/Fechamento/Apresentar/
                Imprimir/Download/OCR) entram no overflow `⋯ Mais` (Wagner: "atuais entram no ⋯") */}
          <FinanceiroSubNav
            active="unificado"
            hidePrimary
            extraOverflowItems={[
              { key: 'buscar',     label: 'Buscar (⌘K)',     icon: <Search size={13} />,      onClick: () => setPaletteOpen(true) },
              { key: 'resumir',    label: 'Resumir mês',     icon: <Sparkles size={13} />,    onClick: () => setResumoOpen(true),                                  title: 'Resumo executivo do mês (narrativa compute-based · Onda 9 v1)' },
              { key: 'fechamento', label: 'Fechamento',      icon: <CheckSquare size={13} />, onClick: () => setChecklistOpen(true),                               title: 'Trilha de 12 passos do fechamento mensal' },
              { key: 'apresentar', label: 'Apresentar',      icon: <Play size={13} />,        onClick: () => setPresentOpen(true),                                 title: 'Modo apresentação fullscreen (Esc fecha · 1/2/3 muda vista)' },
              { key: 'imprimir',   label: favs.count > 0 ? `Imprimir (${favs.count}★)` : 'Imprimir', icon: <Printer size={13} />, onClick: () => { setTranscriptOnlyFavs(false); setTranscriptOpen(true); }, title: 'Folha jurídica imprimível' },
              { key: 'exportar',   label: 'Exportar XLSX/PDF',icon: <Download size={13} />,   onClick: () => setPaletteOpen(true),                                 title: 'Exportar lançamentos do período' },
              // OCR boleto movido pro dropdown "Novo título" (entry-point de criação,
              // não ação features) — Wagner 2026-05-21 split-button popup menu.
            ]}
          />
          {/* Primary "+ Novo título" — canto direito, hue 145 financas (ADR 0182).
              Wagner 2026-05-21: Unificado é caso especial — mostra ambos receivable+
              payable. Click do "+ Novo título" abre dropdown menu com escolha explícita
              (Receber/Pagar/OCR boleto) em vez de levar pra form genérico ambíguo. */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                type="button"
                className="os-btn primary"
                style={{ backgroundColor: 'oklch(0.55 0.15 145)', color: 'oklch(0.99 0 0)' }}
              >
                <Plus size={13} /> Novo título <ChevronDown size={11} />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
              <DropdownMenuItem onClick={() => router.visit('/financeiro/unificado/novo?kind=receivable')}>
                <TrendingUp size={13} className="mr-2 text-emerald-600" /> Novo recebimento
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => router.visit('/financeiro/unificado/novo?kind=payable')}>
                <TrendingDown size={13} className="mr-2 text-rose-600" /> Novo pagamento
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => setOcrSheetOpen(true)} title="Importar boleto via foto/PDF (OCR via IA)">
                <span className="mr-2">📷</span> Importar boleto OCR
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>

      <KpiBar kpis={kpis} lancamentos={lancamentos} onLifecycleSelect={(lifecycle) => aplicar({ lifecycle })} />

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

        {/* Onda 12.5 (2026-05-19) — Toggle "Só atrasados" usa classe `fin-filter-toggle`
            (canon REAL DOM forensics) em vez de `fin-filter-cb` que é dos lifecycle.
            Toggle = on/off independente; lifecycle = multi-select pill colorido. */}
        <label
          className={'fin-filter-toggle' + (filters.overdue ? ' on' : '')}
          title="AND multiplicativo: combina com lifecycle ativos"
        >
          <input
            type="checkbox"
            name="fin-overdue"
            checked={filters.overdue}
            onChange={() => aplicar({ overdue: !filters.overdue })}
          />
          <span>Só atrasados</span>
          <span className="fin-filter-ct">{countOverdue(lancamentos)}</span>
        </label>

        <span className="fin-filter-sep" />

        {/* US-FIN-027 (Onda 22) — Chips workflow aprovação multi-select.
            AND com lifecycle (combina filtros). Hidden se nenhum titulo
            tem aprovacao_status (zero workflow ainda usado em prod). */}
        {lancamentos.some((l) => l.aprovacao_status !== null) && (
          <>
            <div className="fin-filter-group" role="group" aria-label="Filtros por workflow de aprovação">
              {FILTER_APPROVAL.map((af) => {
                const on = filters.aprovacao_status.includes(af.id);
                const count = countByApproval(af.id, lancamentos);
                if (count === 0 && !on) return null; // esconde chip vazio
                const toggle = () => {
                  const next = on
                    ? filters.aprovacao_status.filter((x) => x !== af.id)
                    : [...filters.aprovacao_status, af.id];
                  aplicar({ aprovacao_status: next });
                };
                return (
                  <label
                    key={af.id}
                    className={'fin-filter-cb' + (on ? ' on' : '')}
                    style={{ ['--cb-hue' as string]: af.hue } as React.CSSProperties}
                  >
                    <input
                      type="checkbox"
                      name={`fin-aprov-${af.id}`}
                      checked={on}
                      onChange={toggle}
                    />
                    <span className="fin-filter-cb-box" />
                    <span>{af.label}</span>
                    <span className="fin-filter-ct">{count}</span>
                  </label>
                );
              })}
            </div>

            <span className="fin-filter-sep" />
          </>
        )}

        {/* Onda 7 (2026-05-20): multi-select de contas via Popover + Checkbox.
            Backend aceita CSV "1,3,5" via filters.conta. Frontend mostra label
            agregado: "Todas as contas" / "Conta X" / "N contas". */}
        <FinMultiSelectContas contas={contas} valueCSV={filters.conta} onChange={(csv) => aplicar({ conta: csv })} />

        {/* Onda 12.7 (2026-05-19) — Wagner: substituir 'Categorias' (tags livres) por
            'Plano de Contas' (estrutura contábil hierárquica BR). Renderiza com indent
            visual via `nivel` (4 espaços por nível) pra leitura tipo árvore.
            Mantém prop `filters.categoria` por back-compat (mesmo querystring). */}
        <select
          className="h-7 px-2 rounded-md border border-stone-200 text-[12px] bg-white"
          value={filters.categoria}
          onChange={(e) => aplicar({ categoria: e.target.value })}
          aria-label="Plano de Contas"
        >
          <option value="">Todo o plano de contas</option>
          {(planosConta ?? []).map((p) => (
            <option key={p.id} value={p.id} title={`${p.codigo} ${p.nome} (${p.tipo})`}>
              {'  '.repeat(Math.max(0, p.nivel - 1))}
              {p.codigo} · {p.nome}
            </option>
          ))}
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
            {/* Onda 12.6 (2026-05-19) — apenas 2 modos: compact (default) + comfortable. */}
            {(['compact', 'comfortable'] as const).map((d) => (
              <button
                key={d}
                type="button"
                className={filters.densidade === d ? 'on' : ''}
                onClick={() => aplicar({ densidade: d })}
                aria-pressed={filters.densidade === d}
                aria-label={d === 'compact' ? 'Compacto' : 'Médio'}
                title={d === 'compact' ? 'Compacto' : 'Médio'}
              >
                {d === 'compact' ? '◰' : '▦'}
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
                {/* Onda 12 (2026-05-20): checkbox select-all (referencia: visible rows). */}
                <th className="pl-4 pr-1 py-2 w-7">
                  <input
                    type="checkbox"
                    aria-label="Selecionar todos lançamentos visíveis"
                    className="accent-stone-700 cursor-pointer"
                    checked={lancamentos.length > 0 && lancamentos.every((l) => selectedRows.has(l.id))}
                    onChange={(e) => {
                      if (e.target.checked) setSelectedRows(new Set(lancamentos.map((l) => l.id)));
                      else clearSelection();
                    }}
                  />
                </th>
                <th className="pl-1 pr-2 py-2 w-7"></th>
                <SortableHeader k="vencimento" label="Vencimento" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium w-[110px]" />
                <SortableHeader k="lancamento" label="Lançamento" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium" />
                <SortableHeader k="contraparte" label="Contraparte" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium" />
                <th className="px-2 py-2 text-left font-medium">Categoria</th>
                <SortableHeader k="status" label="Status" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium" />
                <SortableHeader k="valor" label="Valor" filters={filters} aplicar={aplicar} className="px-2 py-2 text-right font-medium" alignRight />
                <th className="pl-2 pr-4 py-2 w-[110px] text-right font-medium"></th>
              </tr>
            </thead>
            <tbody>
              {grupos.map(([key, rows]) => {
                const [, label] = key.split('|');
                // Wagner 2026-05-20: modo compact NAO mostra agrupador (linha por data
                // ja tem coluna VENCIMENTO explicita), comfortable mantem header
                // pra orientacao macro em listas longas.
                const showGroupHeader = filters.densidade === 'comfortable';
                return (
                  <React.Fragment key={key}>
                    {showGroupHeader && (
                      <tr><td colSpan={9} className="bg-stone-50/70 border-b border-stone-200">
                        <div className="px-4 py-1.5 flex items-center text-[11px] uppercase tracking-widest text-stone-500 font-medium">
                          <span>{label}</span>
                          <span className="ml-auto text-stone-400 normal-case tracking-normal">{rows.length} {rows.length === 1 ? 'lançamento' : 'lançamentos'}</span>
                        </div>
                      </td></tr>
                    )}
                    {rows.map(r => (
                      <LinhaTabela
                        key={r.id} row={r} dens={dens}
                        selected={selectedId === r.id}
                        onSelect={() => setSelectedId(r.id)}
                        onBaixar={() => onBaixar(r.id)}
                        conferido={conferido}
                        comments={comments}
                        isFav={favs.has(r.id)}
                        bulkSelected={selectedRows.has(r.id)}
                        onToggleBulk={() => toggleRow(r.id)}
                      />
                    ))}
                  </React.Fragment>
                );
              })}
              {grupos.length === 0 && (
                <tr><td colSpan={9} className="py-16">
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
          {/* Onda 13 (2026-05-20): pagination controls (só renderiza se total > per_page) */}
          {pagination && pagination.total > pagination.per_page && (
            <div className="px-4 py-2 flex items-center justify-between border-t border-stone-200 text-[12px] text-stone-600 bg-stone-50/50">
              <span>
                Página <b>{pagination.page}</b> de <b>{pagination.total_pages}</b>
                <span className="mx-2 text-stone-400">·</span>
                <b>{pagination.total}</b> lançamentos total
                <span className="mx-2 text-stone-400">·</span>
                <span>{pagination.per_page} por página</span>
              </span>
              <span className="flex items-center gap-1">
                <Button
                  size="sm"
                  variant="outline"
                  className="h-7 px-2 text-[12px]"
                  disabled={pagination.page <= 1}
                  onClick={() => aplicar({ page: pagination.page - 1 })}
                  aria-label="Página anterior"
                >
                  ← Anterior
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  className="h-7 px-2 text-[12px]"
                  disabled={pagination.page >= pagination.total_pages}
                  onClick={() => aplicar({ page: pagination.page + 1 })}
                  aria-label="Próxima página"
                >
                  Próxima →
                </Button>
                <select
                  className="h-7 ml-2 px-1 rounded border border-stone-200 bg-white text-[11px]"
                  value={pagination.per_page}
                  onChange={(e) => aplicar({ per_page: parseInt(e.target.value, 10), page: 1 })}
                  aria-label="Itens por página"
                >
                  {[20, 50, 100, 200, 500].map((n) => <option key={n} value={n}>{n}/pág</option>)}
                </select>
              </span>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Drawer detalhe — Cowork v2 (gap report 2026-05-18):
          nav `fin-drawer-tabs` separa Detalhes (info+actions) de ✦ IA (insights). */}
      <Sheet open={!!selected} onOpenChange={(o) => !o && setSelectedId(null)}>
        {/* Onda 5 (2026-05-20): width 560px paridade canon (erp-shell/financeiro-app.jsx:738
            'w-[560px] max-w-[92vw]'). Era 460px — drawer mais espaçoso pra
            Histórico + Anexos + Conferido sem cortar texto. */}
        {/* Onda 22 (2026-05-20) — Portal Sheet renderiza fora de .fin-cowork wrapper,
            então CSS canon prefixado nao aplica (audit-trail, drawer-tabs, comments-thread,
            conferido-toggle). Adicionar `fin-cowork` aqui ativa todas regras canon
            DENTRO do drawer (CSS vars + grid layouts + spacing). Root cause descoberto
            via JS check: drawerInsideFinCowork=false, audit-row display=list-item
            (deveria ser grid). */}
        <SheetContent side="right" className="fin-cowork fin-curadoria fin-drawer-wide w-[560px] sm:max-w-[560px]">
          {selected && (
            <>
              {/* Onda 17 (2026-05-20) — Header canon match prototype financeiro-app.jsx:739-754.
                  Pre-title UPPERCASE "A receber/A pagar · #ID" + DirIcon + conferido inline.
                  Descrição main title bold 14px linkified. */}
              <SheetHeader>
                <div className="flex items-start gap-2.5">
                  <span
                    className={
                      'inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold ' +
                      (selected.kind === 'receivable'
                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                        : 'bg-stone-100 text-stone-700 border border-stone-200')
                    }
                    aria-hidden
                  >
                    {selected.kind === 'receivable' ? '↑' : '↓'}
                  </span>
                  <div className="flex-1 min-w-0">
                    <div className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium flex items-center gap-2">
                      <span>{selected.kind === 'receivable' ? 'A receber' : 'A pagar'} · #{selected.id}</span>
                      {selected.conferido_at && (
                        <span className="text-[10px] text-emerald-700 font-medium normal-case tracking-normal">✓ conferido</span>
                      )}
                    </div>
                    <SheetTitle className="text-[14px] font-semibold mt-0.5 truncate">
                      <FinCrossLinkify text={selected.descricao} />
                    </SheetTitle>
                  </div>
                </div>
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
                  <span className="fin-drawer-tab-glyph" aria-hidden>✦</span>
                  <span>IA</span>
                  {/* Onda 14 (2026-05-20): badge ! na aba IA quando há anomalia detectada
                      (ticket alto vs media historica). Permite Eliana ver alerta sem
                      precisar trocar aba primeiro. */}
                  {(() => {
                    const anom = finAnomalyDetect(selected, lancamentos);
                    if (!anom) return null;
                    return (
                      <span
                        className="fin-drawer-tab-ct"
                        title={`Anomalia ${anom.severity}: ticket ${anom.kind === 'high' ? 'acima' : 'abaixo'} da média`}
                        style={{ background: anom.kind === 'high' ? 'oklch(0.62 0.18 60)' : 'oklch(0.65 0.13 240)', color: 'white' }}
                      >
                        !
                      </span>
                    );
                  })()}
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
                  <span className="fin-drawer-tab-glyph" aria-hidden>✎</span>
                  <span>Editar</span>
                </button>
              </nav>

              {/* Aba Detalhes — info + audit + comments + actions */}
              {drawerTab === 'detalhes' && (
                <div className="mt-3 px-5 space-y-5 text-[13px]">
                  {/* Onda 17 (2026-05-20) — Hierarquia visual canon: UPPERCASE label colorido
                      por status + date 22px BIG + amount 34px BIG (verde se receivable, stone
                      se payable) + StatusPill + FrescorPill inline.
                      Match prototype financeiro-app.jsx:772-806. */}
                  {(() => {
                    const settled = selected.status === 'recebido' || selected.status === 'pago';
                    const labelTone =
                      selected.status === 'atrasado' ? 'text-rose-700'
                      : selected.status === 'vencendo' ? 'text-amber-700'
                      : 'text-stone-500';
                    return (
                      <div>
                        <div className={`text-[11px] uppercase tracking-widest font-medium ${labelTone}`}>
                          {settled ? 'Liquidado' : 'Vencimento'}
                        </div>
                        <div className="mt-1 flex items-baseline gap-2">
                          <div className="text-[22px] font-semibold tracking-tight tabular-nums">
                            {settled && selected.liquidacao ? selected.liquidacao : selected.vencimento_label}
                          </div>
                        </div>
                        <div className="mt-3 flex items-baseline gap-2 flex-wrap">
                          <div className={`text-[34px] font-semibold tracking-tight tabular-nums ${selected.kind === 'receivable' ? 'text-emerald-700' : 'text-stone-900'}`}>
                            {selected.kind === 'receivable' ? '+ ' : '− '}{brl(selected.valor)}
                          </div>
                          <StatusPill s={selected.status} />
                          <FinPillFrescor row={{ due: selected.vencimento, paid_at: settled ? selected.liquidacao : null, vencimento: selected.vencimento }} />
                        </div>
                      </div>
                    );
                  })()}

                  <FinConferidoToggle rowId={selected.id} conferido={conferido} />

                  {/* Onda 18 (2026-05-20) — Grid 2-col canon match prototype financeiro-app.jsx:808-831.
                      Cells: Contraparte / Categoria / Canal / Documento (col-1) + Conta col-span-2
                      com bank icon. Labels UPPERCASE tracking-widest text-stone-500. */}
                  <div className="border-t border-stone-100 pt-4 grid grid-cols-2 gap-y-3 gap-x-3">
                    <div>
                      <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Contraparte</div>
                      <div className="mt-0.5 font-medium text-stone-900">{selected.contraparte}</div>
                      {selected.contraparte_doc && <div className="text-[11px] text-stone-500 font-mono">{selected.contraparte_doc}</div>}
                    </div>
                    <div>
                      <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Categoria</div>
                      <div className="mt-0.5 text-stone-700">{selected.categoria || '—'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Canal</div>
                      <div className="mt-0.5 text-stone-700">{selected.canal || 'manual'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Documento</div>
                      <div className="mt-0.5 text-stone-700 font-mono text-[12px]">{selected.nfe_numero || '—'}</div>
                    </div>
                    <div className="col-span-2">
                      <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Conta</div>
                      <div className="mt-0.5 text-stone-700 flex items-center gap-1.5">
                        <span className="text-stone-400" aria-hidden>🏦</span>
                        <span>{selected.conta_bancaria || '—'}</span>
                      </div>
                    </div>
                  </div>

                  {selected.observacao && (
                    <div className="rounded-md border border-stone-200 bg-stone-50 p-3 text-[12.5px] text-stone-700">{selected.observacao}</div>
                  )}

                  {/* Onda 19 (2026-05-20) — Bloco Conciliação extrato canon match prototype
                      financeiro-app.jsx:833-851. Settled = green card "Conciliado com extrato",
                      not settled = stone card "Sem match. Ao liquidar...". */}
                  {(() => {
                    const settled = selected.status === 'recebido' || selected.status === 'pago';
                    return (
                      <div className="border-t border-stone-100 pt-4">
                        <div className="text-[11px] text-stone-500 uppercase tracking-widest font-medium">Conciliação extrato</div>
                        {settled ? (
                          <div className="mt-2 rounded-md border border-emerald-200 bg-emerald-50/60 px-3 py-2.5 flex items-start gap-2.5">
                            <span className="text-emerald-700 mt-0.5" aria-hidden>🔗</span>
                            <div className="text-[12.5px]">
                              <div className="text-emerald-800 font-medium">Conciliado com extrato bancário</div>
                              <div className="text-emerald-700/80">{selected.liquidacao || '—'} · {brl(selected.valor)} · 100% match</div>
                            </div>
                          </div>
                        ) : (
                          <div className="mt-2 rounded-md border border-stone-200 px-3 py-2.5 text-[12.5px] text-stone-600 flex items-start gap-2.5">
                            <span className="text-stone-500 mt-0.5" aria-hidden>✦</span>
                            <div>
                              Sem match no extrato. Ao liquidar, o sistema procura linhas próximas (±R$ [redacted Tier 0] e ±2 dias) e sugere conciliação automática.
                            </div>
                          </div>
                        )}
                      </div>
                    );
                  })()}

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

                  {/* Onda 21 (2026-05-19) #55 — Workflow aprovação pra títulos a pagar abertos. */}
                  {selected.kind === 'payable' && (selected.status === 'aberto' || selected.status === 'atrasado' || selected.status === 'vencendo') && (
                    <div className="border-t border-stone-200 pt-4">
                      <div className="text-[11px] uppercase tracking-widest text-stone-500 font-medium mb-2">Aprovação</div>
                      {!(selected.aprovacao_status) && (
                        <button
                          type="button"
                          className="os-btn ghost"
                          onClick={() => router.post(`/financeiro/unificado/${selected.id}/solicitar-aprovacao`, {}, { preserveScroll: true })}
                        >
                          ⏳ Solicitar aprovação
                        </button>
                      )}
                      {selected.aprovacao_status === 'pendente' && (
                        <div className="flex flex-wrap gap-2">
                          <span className="inline-block px-2 py-0.5 rounded border text-[11px] font-medium bg-amber-50 text-amber-700 border-amber-200">
                            ⏳ Pendente aprovação
                          </span>
                          {/* US-FIN-028 (Onda 22) — gate Spatie: só users com
                              `financeiro.titulo.aprovar` veem botões aprovar/rejeitar.
                              Demais users veem apenas a pill pendente. */}
                          {canApprove ? (
                            <>
                              <button
                                type="button"
                                className="os-btn ghost fin-btn-trilha"
                                onClick={() => router.post(`/financeiro/unificado/${selected.id}/aprovar`, {}, { preserveScroll: true })}
                              >
                                ✓ Aprovar
                              </button>
                              <button
                                type="button"
                                className="os-btn ghost"
                                style={{ color: 'oklch(0.55 0.10 25)' }}
                                onClick={() => {
                                  const motivo = window.prompt('Motivo da rejeição:');
                                  if (motivo) {
                                    router.post(`/financeiro/unificado/${selected.id}/rejeitar`, { motivo }, { preserveScroll: true });
                                  }
                                }}
                              >
                                ✗ Rejeitar
                              </button>
                            </>
                          ) : (
                            <span className="text-[11px] text-stone-500 italic">
                              Aguardando aprovação de quem tem permissão.
                            </span>
                          )}
                        </div>
                      )}
                      {selected.aprovacao_status === 'aprovado' && (
                        <span className="inline-block px-2 py-0.5 rounded border text-[11px] font-medium bg-emerald-50 text-emerald-700 border-emerald-200">
                          ✓ Aprovado — liberado pra pagamento
                        </span>
                      )}
                      {selected.aprovacao_status === 'rejeitado' && (
                        <span className="inline-block px-2 py-0.5 rounded border text-[11px] font-medium bg-rose-50 text-rose-700 border-rose-200">
                          ✗ Rejeitado — bloqueado pra pagamento
                        </span>
                      )}
                    </div>
                  )}

                  {/* Onda 21 (2026-05-20) — Footer canon match prototype financeiro-app.jsx:878-897.
                      Sequência: Ver NFe (se houver) → Cobrar (receivable não-quitado) →
                      Recebi/Paguei (primary verde grande) → Editar → Favoritar. */}
                  <div className="fin-drawer-footer">
                    {selected.nfe_numero && (
                      <Button variant="outline" size="sm" className="fin-foot-icon-btn" title="Ver NFe" onClick={() => router.visit(`/fiscal/nfe?numero=${selected.nfe_numero}`)}>
                        <span aria-hidden>👁</span>
                        <span className="ml-1">Ver NFe</span>
                      </Button>
                    )}
                    {selected.kind === 'receivable' && (selected.status !== 'recebido') && (
                      <Button variant="outline" size="sm" className="fin-foot-icon-btn" title="Cobrar contraparte" onClick={() => router.visit(`/cobranca/recorrente/nova?titulo=${selected.id}`)}>
                        <span aria-hidden>✉</span>
                        <span className="ml-1">Cobrar</span>
                      </Button>
                    )}
                    {(selected.status !== 'recebido' && selected.status !== 'pago') && (
                      <Button onClick={() => onBaixar(selected.id)} className="fin-foot-mark-btn">
                        <span aria-hidden>✓</span>
                        <span className="ml-1">{selected.kind === 'receivable' ? 'Recebi' : 'Paguei'}</span>
                      </Button>
                    )}
                    <Button variant="outline" size="sm" className="fin-edit-btn" onClick={() => setEditOpen(true)}>Editar</Button>
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => favs.toggle(selected.id)}
                      title="Atalho: B (com a linha selecionada)"
                    >
                      {favs.has(selected.id) ? '★ Favoritado' : '☆ Favoritar'}
                    </Button>
                  </div>

                  {/* US-FIN-026 (Onda 22) — painel completo Anexos (substitui botão upload-only Onda 20). */}
                  <FinAnexosPanel tituloId={selected.id} />
                </div>
              )}

              {/* Aba IA — insights computacionais (Anomaly + Party History)
                  Wagner 2026-05-25: section headers `<h3>` ativam CSS canon
                  `.fin-cowork .fin-ai-panel h3 { uppercase purple 295 }` que estava
                  no-op por falta de markup. Wrapper `fin-curadoria` no
                  SheetContent ativa background/border de .fin-anomaly/.fin-party-history. */}
              {drawerTab === 'ia' && (
                <div className="mt-3 px-5 text-[13px] fin-ai-panel">
                  <section>
                    <h3>Anomalia de valor</h3>
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
                  </section>

                  <section>
                    <h3>Histórico com a contraparte</h3>
                    <FinPartyHistory
                      currentRow={{ id: selected.id, contraparte: selected.contraparte }}
                      all={lancamentos}
                    />
                  </section>

                  <p className="text-[11.5px] text-stone-500 italic pt-3 mt-3 border-t border-stone-100">
                    Insights computacionais · pure compute · Fase 2 plugará JanaService LLM
                  </p>
                </div>
              )}

              {/* Aba Editar — Onda 10 canon 100%: form INLINE real (PUT /financeiro/unificado/{id}) */}
              {drawerTab === 'editar' && (
                <div className="mt-3 px-5">
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
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/conciliacao'); }}>Conciliar extrato (OFX)</CommandItem>
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/relatorios'); }}>DRE / Relatórios</CommandItem>
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/plano-contas'); }}>Plano de contas</CommandItem>
            <CommandItem onSelect={() => { setPaletteOpen(false); router.visit('/financeiro/categorias'); }}>Categorias livres</CommandItem>
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
          "29 lançamentos · Total entrada: R$ [redacted Tier 0] · Total saída: R$ [redacted Tier 0]" + atalhos */}
      <div className="fin-footer-tips">
        {/* Onda 12 (2026-05-20): footer condicional — quando há bulk select, mostra
            summary dos selecionados + ações em lote. Senão, summary do periodo. */}
        {selectedRows.size > 0 ? (
          <>
            {(() => {
              const selectedLancs = lancamentos.filter((l) => selectedRows.has(l.id));
              const totalIn = selectedLancs.filter((l) => l.kind === 'receivable').reduce((s, l) => s + (l.valor ?? 0), 0);
              const totalOut = selectedLancs.filter((l) => l.kind === 'payable').reduce((s, l) => s + (l.valor ?? 0), 0);
              return (
                <span className="fin-footer-summary">
                  <b>{selectedRows.size}</b> selecionado{selectedRows.size === 1 ? '' : 's'}
                  <span className="fin-footer-sep">·</span>
                  {totalIn > 0 && <><span className="text-emerald-700"><b>+{brl(totalIn)}</b></span>{totalOut > 0 && <span className="fin-footer-sep">·</span>}</>}
                  {totalOut > 0 && <span className="text-stone-900"><b>−{brl(totalOut)}</b></span>}
                </span>
              );
            })()}
            <span className="spacer" />
            <Button
              size="sm"
              variant="default"
              className="h-7 px-3 text-[12px]"
              onClick={() => {
                selectedRows.forEach((id) => onBaixar(id));
                clearSelection();
              }}
            >
              Marcar pago/recebido ({selectedRows.size})
            </Button>
            {/* Onda 15 (2026-05-20): bulk edit categoria em lote */}
            <Button
              size="sm"
              variant="outline"
              className="h-7 px-3 text-[12px]"
              onClick={() => setBulkCategoriaOpen(true)}
            >
              Categorizar lote
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-7 px-3 text-[12px]"
              onClick={clearSelection}
            >
              Limpar
            </Button>
          </>
        ) : (
          <>
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
          </>
        )}
      </div>

      {/* Onda 7b — Dialogs Troubleshooter + Presentation Mode */}
      <FinTroubleshooterDialog open={troubleOpen} onClose={() => setTroubleOpen(false)} />

      {/* Onda 15 (2026-05-20): Sheet modal pra bulk edit categoria em lote.
          Renderiza só quando bulkCategoriaOpen=true. */}
      <Sheet open={bulkCategoriaOpen} onOpenChange={(o) => !o && setBulkCategoriaOpen(false)}>
        <SheetContent side="right" className="fin-cowork w-[440px] sm:max-w-[440px]">
          <SheetHeader>
            <SheetTitle>Categorizar em lote</SheetTitle>
          </SheetHeader>
          <div className="px-1 py-4 space-y-4">
            <div className="text-sm text-stone-600">
              Selecione a categoria a aplicar aos <b>{selectedRows.size}</b> lançamento{selectedRows.size === 1 ? '' : 's'} selecionado{selectedRows.size === 1 ? '' : 's'}:
            </div>
            <select
              className="w-full h-9 px-2 rounded-md border border-stone-200 bg-white text-sm"
              value={bulkCategoriaId ?? ''}
              onChange={(e) => setBulkCategoriaId(e.target.value ? parseInt(e.target.value, 10) : null)}
              aria-label="Categoria"
            >
              <option value="">— escolher categoria —</option>
              {categorias.map((c) => (
                <option key={c.id} value={c.id}>{c.nome}</option>
              ))}
            </select>
            <div className="flex items-center gap-2 pt-2 border-t border-stone-100">
              <Button
                size="sm"
                disabled={!bulkCategoriaId || selectedRows.size === 0}
                onClick={submitBulkCategoria}
              >
                Aplicar ({selectedRows.size})
              </Button>
              <Button size="sm" variant="outline" onClick={() => setBulkCategoriaOpen(false)}>
                Cancelar
              </Button>
            </div>
          </div>
        </SheetContent>
      </Sheet>
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

      {/* US-FIN-029 (Onda 23) — Sheet OCR boleto KILLER */}
      <FinOcrBoletoSheet
        open={ocrSheetOpen}
        onClose={() => setOcrSheetOpen(false)}
      />
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
