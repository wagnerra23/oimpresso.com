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
import { Search, Plus, Sparkles, CheckSquare, Check, Play, Printer, RefreshCw, FolderOpen, Download, ChevronDown, TrendingUp, TrendingDown, Camera, Landmark, Eye, FileText, Percent, Link2, ShoppingBag, Wrench, Package, Receipt, Send, type LucideIcon } from 'lucide-react';
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
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { CommandDialog, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/Components/ui/command';
import { PageHeader } from '@/Components/PageHeader';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { Grid, Inline, Stack } from '@/Components/layout';
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
// Onda Edit 2026-05-18 — Sheet inline pra editar título financeiro.
import { TituloEditSheet } from './_components/TituloEditSheet';
import { TituloCreateSheet } from './_components/TituloCreateSheet';
// 2026-06-03 — diálogo de baixa (escolher valor/conta/forma/plano ao receber/pagar).
import { FinBaixaSheet } from './_components/FinBaixaSheet';
// Fidelidade protótipo ([W] 2026-06-29, ADR 0313) — PeriodBar (presets Dia/Semana/Mês/
// Ano/Tudo + Personalizado) substitui os campos dd/mm crus. Frontend-only: seta data_inicio/fim.
import FinPeriodBar from './_components/FinPeriodBar';
// US-FIN-026 (Onda 22) — painel completo de anexos no drawer (GET + upload + download + delete).
import { FinAnexosPanel } from './_components/FinAnexosPanel';
// US-FIN-029 (Onda 23) — Sheet OCR boleto (OpenAI Vision API extrai linha digitavel + valor + vencimento).
import { FinOcrBoletoSheet } from './_components/FinOcrBoletoSheet';
// 2026-06-03 — forma de pagamento (rótulo + ícone) compartilhada.
import { formaPagamentoLabel, formaPagamentoIcon, type MeioPagamento } from './_lib/forma-pagamento';

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
  plano_conta_id: number | null;
  plano_conta_codigo: string | null;
  plano_conta_nome: string | null;
  conta_bancaria: string;
  conta_bancaria_id: number | null;
  // 2026-06-03 — forma de pagamento. `forma_pagamento` = exibida (baixa realizada
  // tem prioridade, senão a prevista do título). `forma_pagamento_realizada` =
  // true quando veio da baixa → read-only (espelha valor_mutavel).
  forma_pagamento: MeioPagamento | null;
  forma_pagamento_realizada: boolean;
  // Paridade campos lançamento WR (Fase 1 — 2026-06-03). Dado já disponível
  // (coluna ou metadata.delphi_* dos migrados). null/0 quando ausente.
  emissao: string | null;          // ISO yyyy-mm-dd (dt lançamento/emissão)
  competencia_mes: string | null;  // "YYYY-MM"
  condicao_pagamento: string | null;
  desconto: number;
  juros: number;
  documento: string | null;        // = CODPEDIDO do WR (fallback nº NF)
  numero: string | null;           // nº do título (R-/P-NNNNN)
  parcela: string | null;          // "1/3" ou "1"
  pedido: string | null;           // codpedido WR
  data_pagamento: string | null;   // ISO yyyy-mm-dd (baixa) — hora vem na Fase 2
  vencimento: string;            // ISO yyyy-mm-dd
  vencimento_label: string;      // "qua, 14 mai"
  liquidacao: string | null;
  valor: number;
  valor_aberto: number;
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
  // Gerar Boleto no drawer (2026-06-08) — linha digitável após emissão Inter.
  boleto: {
    linha_digitavel: string | null;
    codigo_barras: string | null;
    nosso_numero: string | null;
    gateway: string;
    emitido_em: string;
  } | null;
}

interface Kpi {
  saldo_previsto: number;
  recebido: { valor: number; qtd: number };
  a_receber: { valor: number; qtd: number };
  pago: { valor: number; qtd: number };
  a_pagar: { valor: number; qtd: number };
  // PR H (2026-05-25) US-FIN-023 — delta_pct vs mês anterior (null se anterior=0)
  delta_pct?: {
    saldo_previsto: number | null;
    recebido: number | null;
    a_receber: number | null;
    pago: number | null;
    a_pagar: number | null;
  };
}

type TabId = 'all' | 'open' | 'rec' | 'pay' | 'received' | 'paid' | 'late';
// Onda Polish 2026-05-18 — lifecycle multi-select (gap analysis Wagner):
//  ar = A receber (kind=receivable, status aberto/parcial/atrasado/vencendo)
//  re = Recebidas (kind=receivable, status quitado)
//  ap = A pagar   (kind=payable, status aberto/parcial/atrasado/vencendo)
//  pa = Pagas     (kind=payable, status quitado)
type LifecycleId = 'ar' | 're' | 'ap' | 'pa';
// US-FIN-029 (2026-06-10) — lente do header (segmented Caixa · A receber · A pagar).
// Camada 1 do filtro grosso (direção [W] 2026-05-31, charter v14); chips lifecycle
// refinam DENTRO da lente. `?lente=` clamp default caixa (padrão ?tab= do Fluxo).
type LenteId = 'caixa' | 'receber' | 'pagar';
// US-FIN-027 (Onda 22) — workflow aprovação multi-select.
//  pendente / aprovado / rejeitado / sem_workflow (NULL aprovacao_status)
type ApprovalStatusId = 'pendente' | 'aprovado' | 'rejeitado' | 'sem_workflow';

// PR E (2026-05-25) US-FIN-022 — Aging buckets BR canon
type AgingBucketId = 'lt30' | '30-60' | '60-90' | 'gt90' | 'gt180';

interface Filters {
  tab: TabId;              // Legacy — preservado pra back-compat de bookmarks.
  lente: LenteId;          // US-FIN-029 — camada 1 do filtro (segmented header).
  lifecycle: LifecycleId[]; // Onda Polish — multi-select (refina dentro da lente).
  aprovacao_status: ApprovalStatusId[]; // US-FIN-027 (Onda 22).
  aging: AgingBucketId[];  // PR E US-FIN-022 — vencidos por bucket
  overdue: boolean;        // Toggle "Só atrasados" independente.
  arquivados: boolean;     // Toggle "Arquivados" — mostra só cancelados/inativos (Wagner 2026-06-03).
  busca: string;
  conta: string;
  categoria: string;
  periodo: string;
  // Paridade filtros WR (2026-06-03) — campo de data + intervalo explícito.
  // Espelha o WR Comercial (Emissão/Vencimento/Pagamento/Competência). Aplica
  // na TABELA. NF/Vendas do WR exigem link título→transaction (pendente).
  data_campo: 'vencimento' | 'emissao' | 'pagamento' | 'competencia';
  data_inicio: string; // YYYY-MM-DD; vazio = usa período preset
  data_fim: string;    // YYYY-MM-DD; vazio = usa período preset
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
  { id: 'pa', label: 'Pagas',      hue: 295 }, // roxo accent v4 — estado ativo = roxo (azul 240 não era semântico de status)
];

// US-FIN-029 (2026-06-10) — 3 lentes do header (direção [W] 2026-05-31, MWART
// unificado-3-lentes-visual-comparison.md). LENTE_SETS = chips lifecycle compatíveis
// por lente: chip incompatível com a lente NÃO renderiza (menos ruído, não desabilitado).
const LENTE_SETS: Record<LenteId, LifecycleId[]> = {
  caixa: ['ar', 're', 'ap', 'pa'],
  receber: ['ar', 're'],
  pagar: ['ap', 'pa'],
};
const FIN_LENTES: { id: LenteId; label: string }[] = [
  { id: 'caixa',   label: 'Caixa' },
  { id: 'receber', label: 'A receber' },
  { id: 'pagar',   label: 'A pagar' },
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
        className={`inline-flex items-center gap-1 ${alignRight ? 'justify-end w-full' : ''} ${active ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'} cursor-pointer select-none transition-colors`}
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
        className={`h-7 px-2 rounded-md border text-[12px] bg-card flex items-center gap-1 ${selectedIds.length > 0 ? 'border-border text-foreground' : 'border-border text-muted-foreground'}`}
        onClick={() => setOpen((o) => !o)}
        aria-label="Conta bancária multi-select"
        aria-expanded={open}
      >
        <span>{label}</span>
        <span className="text-muted-foreground text-[10px]">▾</span>
      </button>
      {open && (
        <div className="absolute z-50 top-[110%] left-0 min-w-[220px] max-h-[320px] overflow-y-auto rounded-md border border-border bg-card shadow-lg p-1">
          <button
            type="button"
            className="w-full text-left px-2 py-1.5 text-[12px] text-muted-foreground hover:bg-muted/40 rounded"
            onClick={() => onChange('')}
          >
            <span className="inline-flex items-center gap-2">
              <Check className={`h-3.5 w-3.5 shrink-0 ${selectedIds.length === 0 ? 'opacity-100' : 'opacity-0'}`} />
              Todas as contas
            </span>
          </button>
          <div className="h-px bg-muted my-1" />
          {contas.map((c) => {
            const checked = selectedIds.includes(c.id);
            return (
              <button
                key={c.id}
                type="button"
                className="w-full text-left px-2 py-1.5 text-[12px] hover:bg-muted/40 rounded flex items-center gap-2"
                onClick={() => toggle(c.id)}
              >
                <Check className={`h-3.5 w-3.5 shrink-0 ${checked ? 'opacity-100' : 'opacity-0'}`} />
                <span className={checked ? 'text-foreground font-medium' : 'text-foreground'}>{c.nome}</span>
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
  // [W] 2026-07-07 (fila item 7 do inventário por região): pill IGUAL ao protótipo —
  // cápsula rounded-full + font-semibold + cores SATURADAS do DS (STATUS_STYLES,
  // financeiro-page.jsx:110-122; valores do gabarito DS-v6). Tokens .cockpit --pos/
  // --warn/--neg com fallback light; fio = color-mix 22% da cor (proto :122). Sai o
  // amber-* Tailwind cru do `vencendo` (estava fora dos tokens warning).
  const cls = {
    success:     'bg-[color:var(--pos-soft,oklch(0.95_0.075_150))] text-[color:var(--pos,oklch(0.50_0.12_150))] border-[color:color-mix(in_oklch,var(--pos,oklch(0.50_0.12_150))_22%,transparent)]',
    warning:     'bg-[color:var(--warn-soft,oklch(0.955_0.072_75))] text-[color:var(--warn,oklch(0.58_0.12_70))] border-[color:color-mix(in_oklch,var(--warn,oklch(0.58_0.12_70))_22%,transparent)]',
    destructive: 'bg-[color:var(--neg-soft,oklch(0.955_0.055_25))] text-[color:var(--neg,oklch(0.55_0.18_25))] border-[color:color-mix(in_oklch,var(--neg,oklch(0.55_0.18_25))_22%,transparent)]',
    default:     'bg-muted/40 text-foreground border-border',
  }[tone];
  // Dot por status (protótipo aprovado [W] 2026-06-29 screenshot) — cor base por tom.
  const dotCls = {
    success:     'bg-[color:var(--pos,oklch(0.50_0.12_150))]',
    warning:     'bg-[color:var(--warn,oklch(0.58_0.12_70))]',
    destructive: 'bg-[color:var(--neg,oklch(0.55_0.18_25))]',
    default:     'bg-muted-foreground',
  }[tone];
  return (
    <span className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full border text-[11px] font-semibold ${cls}`}>
      <span className={`w-1.5 h-1.5 rounded-full ${dotCls}`} aria-hidden />
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
    aprovado:  { cls: 'bg-success-soft text-success-fg border-success/20', icon: '✓', label: 'Aprov.' },
    rejeitado: { cls: 'bg-destructive-soft text-destructive-fg border-destructive/20', icon: '✗', label: 'Rejeit.' },
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

/**
 * Inventário por região 2026-07-07 (fila P8a) — motivo de rejeição INLINE
 * (protótipo financeiro-ops.jsx:280), substitui o window.prompt nativo.
 * Fechado → botão "✗ Rejeitar"; aberto → input autoFocus + Confirmar/Voltar.
 */
function RejeitarInline({ tituloId }: { tituloId: number }) {
  const [aberto, setAberto] = useState(false);
  const [motivo, setMotivo] = useState('');
  if (!aberto) {
    return (
      <button type="button" className="os-btn ghost" style={{ color: 'oklch(0.55 0.10 25)' }} onClick={() => setAberto(true)}>
        ✗ Rejeitar
      </button>
    );
  }
  const confirmar = () => {
    if (!motivo.trim()) return;
    router.post(`/financeiro/unificado/${tituloId}/rejeitar`, { motivo: motivo.trim() }, { preserveScroll: true });
    setAberto(false);
    setMotivo('');
  };
  return (
    <span className="inline-flex items-center gap-1.5">
      <input
        autoFocus
        value={motivo}
        onChange={(e) => setMotivo(e.target.value)}
        onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); confirmar(); } if (e.key === 'Escape') setAberto(false); }}
        placeholder="Motivo da rejeição…"
        maxLength={255}
        className="h-7 w-44 rounded border border-border bg-background px-2 text-[12px] focus:outline-none focus:ring-1 focus:ring-destructive/50"
      />
      <button type="button" className="os-btn ghost" style={{ color: 'oklch(0.55 0.10 25)' }} disabled={!motivo.trim()} onClick={confirmar}>
        Confirmar rejeição
      </button>
      <button type="button" className="os-btn ghost" onClick={() => { setAberto(false); setMotivo(''); }}>
        Voltar
      </button>
    </span>
  );
}

function FinSparkline({ tone = 'pos', points }: { tone?: 'pos' | 'neg'; points?: SparkPoint[] | null }) {
  // Inventário por região 2026-07-07: cores eram hardcoded pro hero warm-dark ANTIGO
  // (verde-claro 0.78 ilegível no hero claro da Onda 28). Agora seguem os tokens
  // .cockpit --pos/--neg (flipam com o tema); fallback = valor light do DS.
  const color = tone === 'pos' ? 'var(--pos, oklch(0.50 0.12 150))' : 'var(--neg, oklch(0.55 0.18 25))';

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
        <line x1="0" y1="24" x2="200" y2="24" stroke="var(--text-mute, oklch(0.65 0.01 80))" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" />
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
      <line x1="0" y1={baselineY} x2={W} y2={baselineY} stroke="var(--text-mute, oklch(0.65 0.01 80))" strokeWidth="0.5" strokeDasharray="2 3" opacity="0.4" aria-hidden="true" />
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

// US-FIN-029 — KPI-click agora seta lente + lifecycle coerentes (clicar "A pagar"
// entra na lente pagar refinada em 'ap'). Drill-down ADR ui/0002 preservado.
// ── Drawer F2 (PACOTE-FINANCEIRO-F2 PR-3, [W] "aprovado" 2026-06-10) ──────────────────
// Seção "lente de domínio" do drawer: ícone em quadradinho primary/10 + título sm
// semibold + chip de status calmo à direita. Referência F1: LensSection em
// financeiro-page.jsx do protótipo Cowork. Tokens semânticos do @theme (inertia.css) —
// o drawer é portal FORA de .cockpit, então var(--accent) etc não resolvem aqui.
function DrawerLensChip({ tone, children }: { tone: 'pos' | 'warn' | 'muted'; children: ReactNode }) {
  const cls = {
    pos: 'bg-success/10 text-success-foreground',
    warn: 'bg-warning/10 text-warning-foreground',
    muted: 'bg-muted text-muted-foreground',
  }[tone];
  return <span className={`inline-flex items-center h-[19px] px-2 rounded-full text-[10.5px] font-medium ${cls}`}>{children}</span>;
}

function DrawerLens({ icon: Icon, title, status, tone = 'muted', hue = 'accent', children }: {
  icon: LucideIcon;
  title: string;
  status?: string | null;
  tone?: 'pos' | 'warn' | 'muted';
  // FA-5 R3 — cor do ícone por domínio (complementar à identidade roxa).
  // Tailwind @theme (drawer é portal fora de .cockpit — var(--pos) não resolve aqui).
  hue?: 'accent' | 'pos' | 'warn' | 'neg' | 'muted';
  children: ReactNode;
}) {
  const hueCls = {
    accent: 'bg-primary/10 text-primary',
    pos: 'bg-success/10 text-success-foreground',
    warn: 'bg-warning/10 text-warning-foreground',
    neg: 'bg-destructive/10 text-destructive',
    muted: 'bg-muted text-muted-foreground',
  }[hue];
  return (
    <section className="border-t border-border/60 pt-4">
      <Inline asChild gap={2} className="mb-2.5">
        <header>
          <span className={`w-[22px] h-[22px] rounded-md grid place-items-center shrink-0 ${hueCls}`} aria-hidden>
            <Icon size={12} />
          </span>
          <h4 className="text-[12.5px] font-semibold text-foreground">{title}</h4>
          {status && <span className="ml-auto"><DrawerLensChip tone={tone}>{status}</DrawerLensChip></span>}
        </header>
      </Inline>
      {children}
    </section>
  );
}

// FA-5 R1 (Stripe) — valor copiável: ⧉ no hover · 1 clique copia · ✓ 1.4s.
// stopPropagation pra não mexer no drawer. Cor do ✓/hover por Tailwind (success/primary).
function CopyVal({ text, children, mono = false }: { text: string; children?: ReactNode; mono?: boolean }) {
  const [ok, setOk] = useState(false);
  return (
    <button
      type="button"
      className={'fin-copyval' + (ok ? ' ok' : '')}
      title="Copiar"
      onClick={(e) => {
        e.stopPropagation();
        try { navigator.clipboard?.writeText(String(text)); } catch { /* clipboard indisponível */ }
        setOk(true);
        window.setTimeout(() => setOk(false), 1400);
      }}
    >
      <span className={'fin-copyval-txt' + (mono ? ' font-mono tabular-nums' : '')}>{children ?? text}</span>
      <span className={'fin-copyval-ic ' + (ok ? 'text-success-foreground' : 'text-muted-foreground')} aria-hidden>
        {ok ? '✓' : '⧉'}
      </span>
    </button>
  );
}

// FA-5 P5/S3 — saída no mundo real: recibo imprimível com identidade Oimpresso
// (iframe oculto · Georgia 12pt · valor 24pt mono · "documento sem valor fiscal").
// Client-side: o módulo Financeiro não tem rota server-side de recibo (verificado FA-5).
function printReciboTitulo(t: Lancamento) {
  const isIn = t.kind === 'receivable';
  const settled = t.status === 'recebido' || t.status === 'pago';
  const fmtLong = (iso: string | null | undefined) => {
    if (!iso) return '—';
    const [y, m, d] = iso.split('-');
    const MES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return `${parseInt(d ?? '1', 10)} de ${MES[parseInt(m ?? '1', 10) - 1] ?? ''} de ${y}`;
  };
  const esc = (s: string | null | undefined) =>
    String(s ?? '—').replace(/[<>&]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;' }[c] as string));
  const f = document.createElement('iframe');
  f.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
  document.body.appendChild(f);
  const d = f.contentDocument;
  if (!d) { f.remove(); return; }
  const ident = esc(t.numero || String(t.id));
  const titulo = settled ? 'Recibo' : isIn ? 'Cobrança' : 'Aviso de pagamento';
  const sinal = (t.valor ?? 0) === 0 ? '' : isIn ? '+ ' : '− ';
  d.open();
  d.write(
    `<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Recibo ${ident}</title>` +
    `<style>body{font:12pt/1.5 Georgia,serif;color:#111;margin:48px}` +
    `.brand{display:flex;justify-content:space-between;align-items:baseline;border-bottom:2px solid #111;padding-bottom:12px}` +
    `.brand b{font-size:16pt;letter-spacing:.04em}.brand span{font-size:10pt;color:#555}` +
    `h1{font-size:13pt;margin:24px 0 4px;text-transform:uppercase;letter-spacing:.08em}` +
    `.valor{font-size:24pt;font-family:ui-monospace,monospace;margin:8px 0 20px}` +
    `table{width:100%;border-collapse:collapse;font-size:11pt}` +
    `td{padding:6px 0;border-bottom:1px solid #ddd;vertical-align:top}td:first-child{color:#555;width:170px}` +
    `.foot{margin-top:36px;font-size:9pt;color:#777}</style></head><body>` +
    `<div class="brand"><b>OIMPRESSO</b><span>Comunicação Visual</span></div>` +
    `<h1>${titulo} · ${ident}</h1>` +
    `<div class="valor">${sinal}${esc(brl(t.valor ?? 0))}</div>` +
    `<table>` +
    `<tr><td>Descrição</td><td>${esc(t.descricao)}</td></tr>` +
    `<tr><td>Contraparte</td><td>${esc(t.contraparte)}</td></tr>` +
    `<tr><td>Categoria</td><td>${esc(t.categoria)}${t.canal ? ' · ' + esc(t.canal) : ''}</td></tr>` +
    `<tr><td>${settled ? 'Liquidado em' : 'Vencimento'}</td><td>${fmtLong(settled ? t.liquidacao : t.vencimento)}</td></tr>` +
    (t.nfe_numero ? `<tr><td>Nota fiscal</td><td>${esc(t.nfe_numero)}</td></tr>` : '') +
    `</table>` +
    `<div class="foot">Emitido pelo Oimpresso ERP · documento sem valor fiscal.</div>` +
    `</body></html>`,
  );
  d.close();
  window.setTimeout(() => {
    try { f.contentWindow?.focus(); f.contentWindow?.print(); } catch { /* print bloqueado */ }
    window.setTimeout(() => f.remove(), 800);
  }, 60);
}

// FA-5 — Vínculos: chips estruturados derivados dos MESMOS tokens do FinCrossLinkify
// (#V-/#OS-/#PC-/#BL- na descrição) + nfe_numero. Não inventa dado — estrutura o que já existe.
const FIN_XLINK_DEFS = [
  { kind: 'venda', re: /#V-(\d{1,8})/g, label: (n: string) => `Venda #${n}`, href: (n: string) => `/sells/${n}`, Icon: ShoppingBag, cls: 'text-primary' },
  { kind: 'os', re: /#OS-(\d{1,8})/g, label: (n: string) => `OS #${n}`, href: (n: string) => `/repair/job/${n}`, Icon: Wrench, cls: 'text-warning-foreground' },
  { kind: 'compra', re: /#PC-(\d{1,8})/g, label: (n: string) => `Compra #${n}`, href: (n: string) => `/compras/${n}`, Icon: Package, cls: 'text-success-foreground' },
  { kind: 'boleto', re: /#BL-(\d{1,8})/g, label: (n: string) => `Boleto #${n}`, href: (n: string) => `/financeiro/boletos/${n}`, Icon: Receipt, cls: 'text-muted-foreground' },
] as const;

function FinVinculosChips({ descricao, nfeNumero }: { descricao: string; nfeNumero: string | null }) {
  const chips: { key: string; label: string; href: string; Icon: LucideIcon; cls: string }[] = [];
  for (const def of FIN_XLINK_DEFS) {
    for (const m of (descricao ?? '').matchAll(def.re)) {
      chips.push({ key: `${def.kind}-${m[1]}`, label: def.label(m[1]), href: def.href(m[1]), Icon: def.Icon, cls: def.cls });
    }
  }
  if (nfeNumero) chips.push({ key: `nf-${nfeNumero}`, label: `NFe ${nfeNumero}`, href: `/fiscal/nfe?numero=${nfeNumero}`, Icon: FileText, cls: 'text-muted-foreground' });
  if (chips.length === 0) return null;
  return (
    <section className="border-t border-border pt-4">
      <div className="flex items-center gap-2 flex-wrap">
        <span className="w-[22px] h-[22px] rounded-md grid place-items-center bg-primary/10 text-primary shrink-0" aria-hidden><Link2 size={12} /></span>
        <h4 className="text-[12.5px] font-semibold text-foreground mr-1">Vínculos</h4>
        {chips.map((c) => (
          <button
            key={c.key}
            type="button"
            onClick={() => router.visit(c.href)}
            className="inline-flex items-center gap-1.5 h-7 pl-2 pr-2.5 rounded-md border border-border text-[11.5px] text-foreground transition-colors hover:bg-muted"
          >
            <c.Icon size={12} className={c.cls} aria-hidden />
            <span>{c.label}</span>
          </button>
        ))}
      </div>
    </section>
  );
}

// FA-5 — Categoria editável inline (Canal fica read-only — sem rota de save no backend,
// decisão [W] 2026-06-11). Espelha EXATAMENTE o payload do TituloEditSheet (proven) só
// trocando categoria_id, pra não nular contraparte/forma/conta (o controller fill()
// sobrescreve esses campos no update).
// Radix Select não aceita SelectItem value="" (reservado p/ limpar) — sentinela p/ "(Sem categoria)".
const CATEGORIA_NONE = '__none__';

function FinKVCategoriaInline({ selected, categorias }: { selected: Lancamento; categorias: { id: number; nome: string }[] }) {
  const onValueChange = (value: string) => {
    const categoriaId = value === CATEGORIA_NONE ? null : Number(value);
    if (categoriaId === (selected.categoria_id ?? null)) return;
    const data: Record<string, unknown> = {
      cliente_descricao: selected.contraparte === '—' ? null : selected.contraparte,
      observacoes: selected.observacao || null,
      categoria_id: categoriaId,
      plano_conta_id: selected.plano_conta_id ?? null,
      vencimento: selected.vencimento,
      conta_bancaria_id: selected.conta_bancaria_id ?? null,
    };
    if (selected.valor_mutavel) data.valor_total = selected.valor;
    if (!selected.forma_pagamento_realizada) data.forma_pagamento = selected.forma_pagamento || null;
    router.put(`/financeiro/unificado/${selected.id}`, data as Record<string, never>, { preserveScroll: true });
  };
  return (
    <Select
      value={selected.categoria_id != null ? String(selected.categoria_id) : CATEGORIA_NONE}
      onValueChange={onValueChange}
    >
      <SelectTrigger
        variant="shadcn"
        size="sm"
        className="max-w-[200px] text-[12px]"
        title="Editar categoria — salva no lançamento"
        aria-label="Categoria"
      >
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={CATEGORIA_NONE}>(Sem categoria)</SelectItem>
        {categorias.map((c) => (
          <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

function KpiBar({ kpis, lancamentos, onKpiSelect, periodLabel, lenteAtiva }: { kpis: Kpi; lancamentos: Lancamento[]; onKpiSelect: (lente: LenteId, lifecycle: LifecycleId[]) => void; periodLabel: string; lenteAtiva: LenteId }) {
  // Inventário por região 2026-07-07 (fila P1) — 3 affordances do protótipo que faltavam:
  // anel de lente ativa (fin-stat-on, financeiro-page.jsx:398), hover de elevação
  // (fin-boletos.css:65) e dot entrada/saída no label (Caso 08, fin-boletos.css:77).
  // Implementados em Tailwind (zero CSS bespoke — MANUAL-CSS-JS congela sprawl).
  // Smoke prod 2026-07-07 pegou: `ring`/`shadow` perdiam pro box-shadow bespoke de
  // .fin-stat* (fora de @layer, vence utility em layer) — anel virou OUTLINE (não
  // compete com box-shadow; provado no DOM vivo) e elevação virou só translate.
  const kpiHover = 'cursor-pointer transition-transform duration-150 hover:-translate-y-px';
  const kpiOn = (lid: LenteId) =>
    lenteAtiva === lid ? ' outline outline-[1.5px] -outline-offset-[1.5px] outline-[color:var(--accent,oklch(0.55_0.15_295))]' : '';
  const Dot = ({ tone }: { tone: 'in' | 'out' }) => (
    <span aria-hidden className={`inline-block size-1.5 rounded-full mr-1.5 align-middle ${tone === 'in' ? 'bg-success' : 'bg-destructive'}`} />
  );
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
    // FX-3 (print 06-11): o primeiro a pagar (vencimento ASC) pode já estar VENCIDO —
    // rotular "próx." (= próximo/futuro) mente. Se vencido, vira "vencida há Nd" destructive.
    const hojeISO = new Date().toISOString().slice(0, 10);
    const overdue = first.status === 'atrasado' || first.vencimento < hojeISO;
    const diasAtraso = overdue
      ? Math.max(0, Math.round((Date.parse(hojeISO) - Date.parse(first.vencimento)) / 86400000))
      : 0;
    return { label: `${parseInt(dd, 10)} ${mesAbrev}`, contraparte: first.contraparte, overdue, diasAtraso };
  }, [lancamentos]);

  // PR H (2026-05-25) US-FIN-023 — render badge delta_pct (↑+12% verde / ↓-5% rose / → 0% neutro).
  // Cores via classes canon `fin-num-pos`/`fin-num-neg`/`text-muted-foreground` (AP1 PRE-MERGE-UI).
  // FX-5 (print 06-11): suprime ruído — delta com valor atual ~0 (ex.: Pago R$ 0,00 →
  // "↓-100%") ou swing absurdo (>300%, base do mês anterior imaterial = "sem base
  // comparável") não é informação, é ruído. Vira null = badge não renderiza.
  const DeltaBadge = ({ pct, valor }: { pct: number | null | undefined; valor?: number }) => {
    if (pct === null || pct === undefined) return null;
    if (valor !== undefined && Math.abs(valor) < 0.005) return null;
    if (Math.abs(pct) > 300) return null;
    const isZero = Math.abs(pct) < 0.05;
    const isUp = pct > 0;
    const colorClass = isZero ? 'text-muted-foreground' : (isUp ? 'fin-num-pos' : 'fin-num-neg');
    const arrow = isZero ? '→' : (isUp ? '↑' : '↓');
    const sign = pct > 0 ? '+' : '';
    return (
      <span
        className={`fin-delta-pct ml-1 text-[10px] font-medium tabular-nums ${colorClass}`}
        title={`vs mês anterior (${pct > 0 ? 'subiu' : 'caiu'} ${Math.abs(pct)}%)`}
      >
        {arrow}{sign}{pct.toFixed(1).replace('.', ',')}%
      </span>
    );
  };

  return (
    <div className="fin-stats">
      <button type="button" className={`fin-stat fin-stat-hero ${kpiHover}${kpiOn('caixa')}`} onClick={() => onKpiSelect('caixa', ['ar', 'ap'])} aria-label="Filtrar abertos (a receber + a pagar)">
        {/* FX-2 (print 06-11): mês do hero vinha hardcoded "maio"; agora usa periodLabel
            (MESMA fonte do subtítulo da página — fonte única, sem drift de período). */}
        <small>
          Saldo previsto · {periodLabel}
          {/* CASO 03 (adversário Wave 1) — alarme quando a projeção do período é negativa. */}
          {kpis.saldo_previsto < 0 && <span className="fin-hero-alarm">projeção negativa</span>}
        </small>
        {/* CASO 03 — número do hero vira vermelho (canon `fin-num-neg`, já estilizado
            pro fundo warm-dark via fin-cowork.css) quando o saldo previsto é negativo. */}
        <b className={kpis.saldo_previsto < 0 ? 'fin-num-neg' : undefined}>{brl(kpis.saldo_previsto)}<DeltaBadge pct={kpis.delta_pct?.saldo_previsto} valor={kpis.saldo_previsto} /></b>
        <span className="fin-stat-hint">
          <b className="mono">{brl(kpis.recebido.valor - kpis.pago.valor)}</b> realizado · <span className={pendente >= 0 ? 'fin-num-pos' : 'fin-num-neg'}>{brl(pendente)}</span> pendente
        </span>
        <FinSparkline tone={kpis.saldo_previsto >= 0 ? 'pos' : 'neg'} points={sparkPoints} />
      </button>

      <button type="button" className={`fin-stat ${kpiHover}${kpiOn('receber')}`} onClick={() => onKpiSelect('receber', ['re'])} aria-label="Filtrar recebidas (lente A receber)">
        <small><Dot tone="in" />Recebido</small>
        <b className="fin-num-pos">{brl(kpis.recebido.valor)}<DeltaBadge pct={kpis.delta_pct?.recebido} valor={kpis.recebido.valor} /></b>
        <span className="fin-stat-hint">{kpis.recebido.qtd} entradas confirmadas</span>
      </button>

      <button type="button" className={`fin-stat ${kpiHover}${kpiOn('receber')}`} onClick={() => onKpiSelect('receber', ['ar'])} aria-label="Filtrar a receber (lente A receber)">
        <small><Dot tone="in" />A receber</small>
        <b>{brl(kpis.a_receber.valor)}<DeltaBadge pct={kpis.delta_pct?.a_receber} valor={kpis.a_receber.valor} /></b>
        {/* PR 2 — canon hint: "R$ X em atraso" se houver atrasados; fallback genérico. */}
        <span className="fin-stat-hint">
          {atrasadoReceber > 0
            ? <><span className="fin-num-neg mono">{brl(atrasadoReceber)}</span> em atraso</>
            : <>{kpis.a_receber.qtd} títulos</>}
        </span>
      </button>

      <button type="button" className={`fin-stat ${kpiHover}${kpiOn('pagar')}`} onClick={() => onKpiSelect('pagar', ['pa'])} aria-label="Filtrar pagas (lente A pagar)">
        <small><Dot tone="out" />Pago</small>
        <b className="fin-num-neg">{brl(kpis.pago.valor)}<DeltaBadge pct={kpis.delta_pct?.pago} valor={kpis.pago.valor} /></b>
        <span className="fin-stat-hint">{kpis.pago.qtd} saídas liquidadas</span>
      </button>

      <button type="button" className={`fin-stat ${kpiHover}${kpiOn('pagar')}`} onClick={() => onKpiSelect('pagar', ['ap'])} aria-label="Filtrar a pagar (lente A pagar)">
        <small><Dot tone="out" />A pagar</small>
        <b>{brl(kpis.a_pagar.valor)}<DeltaBadge pct={kpis.delta_pct?.a_pagar} valor={kpis.a_pagar.valor} /></b>
        {/* PR 2 — canon hint: "próx. <dia mes> · <contraparte>" do primeiro payable aberto. */}
        <span className="fin-stat-hint">
          {proxPagar
            ? (proxPagar.overdue
                ? <><span className="fin-num-neg">vencida há {proxPagar.diasAtraso}d</span> · {proxPagar.contraparte}</>
                : <>próx. <b>{proxPagar.label}</b> · {proxPagar.contraparte}</>)
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
  // Refino premium DirIcon ([W] 2026-06-29) — cor base da direção (pos verde / neg rose)
  // pra borda + sombra translúcidas via color-mix (mesma assinatura dos chips de filtro).
  const dirBase = isIn ? 'oklch(0.40 0.13 145)' : 'oklch(0.50 0.18 25)';
  const settled = row.status === 'recebido' || row.status === 'pago';
  // FinPillFrescor (✕/✓ compact) REMOVIDO da linha 2026-06-29 ([W] "remova") —
  // redundante com o StatusPill (Atrasado/Pago) + o label "em atraso/há Nd" da
  // coluna Vencimento; o protótipo só tem o badge de status. Drawer mantém o frescor.
  // #5 Tribunal Onda 2 (cadeira Victor/Saarinen) — acento de AÇÃO na borda esquerda da
  // linha pra achar o que pede ação sem abrir: vencido = destructive, vencendo (não pago)
  // = warning, resto = nada. box-shadow inset na 1ª <td> (border-collapse ignora
  // border-left no <tr>); var(--color-*) do @theme Tailwind v4 (token, não cor crua).
  const actAccent =
    row.status === 'atrasado' ? 'inset 3px 0 0 var(--color-destructive)'
    : (row.status === 'vencendo' && !settled) ? 'inset 3px 0 0 var(--color-warning)'
    : undefined;
  return (
    <tr
      className={`${dens.row} ${dens.text} border-b border-border hover:bg-muted/50 cursor-pointer ${selected ? 'bg-amber-50/40' : ''} ${bulkSelected ? 'bg-primary/5' : ''}`}
      onClick={onSelect}
    >
      {/* Onda 12 (2026-05-20): checkbox bulk-select. stopPropagation pra nao abrir drawer. */}
      <td className="pl-4 pr-1" style={actAccent ? { boxShadow: actAccent } : undefined} onClick={(e) => e.stopPropagation()}>
        <Checkbox
          checked={bulkSelected}
          onCheckedChange={onToggleBulk}
          aria-label={`Selecionar lançamento ${row.descricao}`}
          className="cursor-pointer"
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
            color: dirBase,
            // Refino premium ([W] 2026-06-29) — fio translúcido 22% + sombra 28% color-mix.
            border: `1px solid color-mix(in oklch, ${dirBase} 22%, transparent)`,
            boxShadow: `0 1px 3px -1px color-mix(in oklch, ${dirBase} 28%, transparent)`,
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
      <td className="px-2 text-foreground text-[12px] whitespace-nowrap">
        <div className="font-medium text-foreground">
          {(() => {
            const parts = row.vencimento.split('-');
            const dd = parts[2] ?? '01';
            const mm = parts[1] ?? '01';
            return `${dd}/${mm}`;
          })()}
        </div>
        {row.liquidacao && (
          <div className="text-[10px] text-muted-foreground">pago {row.liquidacao}</div>
        )}
        {!row.liquidacao && (row.status === 'atrasado' || row.status === 'vencendo') && (
          <div className={`text-[10px] ${row.status === 'atrasado' ? 'text-destructive' : 'text-amber-600'}`}>
            {row.status === 'atrasado' ? 'em atraso' : 'vencendo'}
          </div>
        )}
      </td>
      <td className="px-2">
        <div className="font-medium text-foreground truncate max-w-[260px] flex items-center gap-1.5">
          <FinCrossLinkify text={row.descricao} className="truncate" />
          <FinFavPin active={isFav} />
          <FinConferidoBadge rowId={row.id} conferido={conferido} />
          <FinCommentsBadge rowId={row.id} comments={comments} />
        </div>
        {row.nfe_numero && <div className="text-[11px] text-muted-foreground">NF-e {row.nfe_numero}</div>}
      </td>
      <td className="px-2 text-foreground truncate max-w-[160px]">{row.contraparte}</td>
      {/* Fidelidade protótipo ([W] 2026-06-29): categoria = dot + texto leve, não
          pill pesada. Mantém o hue in/out no dot (verde/âmbar); o scan por kind já
          é redundante com o DirIcon (↘/↗) e a cor do valor. */}
      <td className="px-2 truncate max-w-[140px]">
        <span className="inline-flex items-center gap-1.5 text-[12px] text-muted-foreground">
          <span className={`w-1.5 h-1.5 rounded-full shrink-0 ${isIn ? 'bg-success' : 'bg-amber-500'}`} aria-hidden />
          <span className="truncate">{row.categoria}</span>
        </span>
      </td>
      {/* 2026-06-03: forma de pagamento (ícone + rótulo compacto). */}
      <td className="px-2 text-[11.5px] text-muted-foreground whitespace-nowrap">
        {(() => {
          const Icon = formaPagamentoIcon(row.forma_pagamento);
          if (!Icon) return <span className="text-muted-foreground">—</span>;
          return (
            <span className="inline-flex items-center gap-1" title={formaPagamentoLabel(row.forma_pagamento)}>
              <Icon className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />
              <span className="truncate max-w-[96px]">{formaPagamentoLabel(row.forma_pagamento)}</span>
            </span>
          );
        })()}
      </td>
      {/* 2026-06-03: conta bancária (da baixa) — compacta com ícone banco. */}
      <td className="px-2 text-[11.5px] text-muted-foreground whitespace-nowrap">
        {row.conta_bancaria && row.conta_bancaria !== '—' ? (
          <span className="inline-flex items-center gap-1" title={row.conta_bancaria}>
            <Landmark className="h-3.5 w-3.5 text-muted-foreground" aria-hidden />
            <span className="truncate max-w-[120px]">{row.conta_bancaria}</span>
          </span>
        ) : (
          <span className="text-muted-foreground">—</span>
        )}
      </td>
      {/* 2026-06-04: data da baixa (liquidação) — pedido Wagner. */}
      <td className="px-2 text-[11.5px] text-muted-foreground whitespace-nowrap">
        {row.liquidacao ? row.liquidacao : <span className="text-muted-foreground">—</span>}
      </td>
      <td className="px-2"><div className="flex items-center gap-1.5"><StatusPill s={row.status} /><ApprovalPill s={row.aprovacao_status} /></div></td>
      {/* [W] 2026-07-07 (fila item 8): saída NEUTRA igual ao protótipo (financeiro-
          page.jsx:816 — "saída não grita em vermelho"; só entrada é verde). */}
      <td className={`px-2 text-right font-medium tabular-nums whitespace-nowrap ${isIn ? 'text-success' : 'text-foreground'}`}>
        {/* FX-4 (print 06-11): zero nunca leva sinal — "−0,00" vira "0,00". */}
        <span className="text-muted-foreground mr-0.5">{Math.abs(row.valor) < 0.005 ? '' : (isIn ? '+' : '−')}</span>{brl(row.valor).replace('R$', '').trim()}
      </td>
      <td className="pl-2 pr-4 text-right" onClick={(e) => e.stopPropagation()}>
        {/* Rótulo "Recebi/Paguei" (1ª pessoa + ✓) — fidelidade protótipo [W] 2026-07-06,
            eq. "marcar recebido/pago". Ação inalterada (onBaixar abre a FinBaixaSheet). */}
        {!settled ? (
          <Button size="sm" variant="outline" className="h-7 px-2 text-[11.5px]" onClick={onBaixar}>
            <span aria-hidden>✓</span>{' '}{isIn ? 'Recebi' : 'Paguei'}
          </Button>
        ) : (
          <span className="text-[11px] text-muted-foreground">{row.liquidacao}</span>
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
  // US-FIN-031 — endpoint bulk genérico (ownership Tier 0 de TODOS os ids +
  // limite 500 + audit trail no backend). Todas as ações em lote passam por ele.
  const submitBulk = useCallback((action: 'baixar' | 'categoria' | 'plano_conta' | 'cancelar', payload: Record<string, number> = {}, onDone?: () => void) => {
    if (selectedRows.size === 0) return;
    router.post('/financeiro/unificado/bulk', {
      action,
      ids: Array.from(selectedRows),
      payload,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        onDone?.();
        clearSelection();
      },
    });
  }, [selectedRows, clearSelection]);
  // Onda 15 (2026-05-20): bulk edit categoria modal state
  const [bulkCategoriaOpen, setBulkCategoriaOpen] = useState(false);
  const [bulkCategoriaId, setBulkCategoriaId] = useState<number | null>(null);
  const submitBulkCategoria = useCallback(() => {
    if (!bulkCategoriaId) return;
    submitBulk('categoria', { categoria_id: bulkCategoriaId }, () => {
      setBulkCategoriaOpen(false);
      setBulkCategoriaId(null);
    });
  }, [bulkCategoriaId, submitBulk]);
  // US-FIN-031 — plano de contas em lote (Sheet, mesmo padrão da categoria).
  const [bulkPlanoOpen, setBulkPlanoOpen] = useState(false);
  const [bulkPlanoId, setBulkPlanoId] = useState<number | null>(null);
  const submitBulkPlano = useCallback(() => {
    if (!bulkPlanoId) return;
    submitBulk('plano_conta', { plano_conta_id: bulkPlanoId }, () => {
      setBulkPlanoOpen(false);
      setBulkPlanoId(null);
    });
  }, [bulkPlanoId, submitBulk]);
  // US-FIN-031 — cancelar em lote: Sheet de confirmação destrutiva com o total
  // (REGRA MESTRE valor: apresentar o impacto ANTES de aplicar).
  const [bulkCancelOpen, setBulkCancelOpen] = useState(false);
  // US-FIN-031 — exportar CSV da seleção: POST fetch (Inertia não faz download)
  // com XSRF do cookie; backend audita e devolve text/csv attachment.
  const exportBulkCsv = useCallback(async () => {
    if (selectedRows.size === 0) return;
    const xsrf = decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
    const resp = await fetch('/financeiro/unificado/bulk', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf, Accept: 'text/csv' },
      body: JSON.stringify({ action: 'exportar_csv', ids: Array.from(selectedRows) }),
    });
    if (!resp.ok) return;
    const blob = await resp.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `lancamentos-selecionados-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }, [selectedRows]);
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
  const [drawerTab, setDrawerTab] = useState<'detalhes' | 'ia'>('detalhes');
  useEffect(() => { setDrawerTab('detalhes'); }, [selectedId]);
  // Onda Edit 2026-05-18 — Edit Sheet state (separate from detail drawer).
  const [editOpen, setEditOpen] = useState(false);
  // Onda 25 (2026-05-25) US-FIN-021 — Insert manual via TituloCreateSheet.
  // `createTipo` controla qual variante abre (receber=verde · pagar=rose).
  const [createTipo, setCreateTipo] = useState<'receber' | 'pagar' | null>(null);
  // US-FIN-029 (Onda 23) — Sheet OCR boleto.
  const [ocrSheetOpen, setOcrSheetOpen] = useState(false);
  // 2026-06-03 — diálogo de baixa (escolher valor/conta/forma/plano). Botões
  // "Recebi/Paguei" abrem este sheet; espaço/bulk seguem baixa instantânea.
  const [baixaId, setBaixaId] = useState<number | null>(null);
  const openBaixa = useCallback((id: number) => setBaixaId(id), []);

  const aplicar = useCallback((patch: Partial<Filters>) => {
    // D-14 fix (2026-07-06 [W] "reload da página inteira é o antipadrão, não pode em
    // tela nenhuma"): `only:` = PARTIAL RELOAD Inertia — só re-busca o que MUDA com
    // filtro. contas/categorias/planosConta/agingBreakdown são closures no controller
    // → nem rodam a query, nem trafegam, e o React não re-renderiza a página inteira.
    router.get('/financeiro/unificado', { ...filters, ...patch }, {
      preserveState: true, preserveScroll: true, replace: true,
      only: ['kpis', 'lancamentos', 'pagination', 'filters', 'periodLabel'],
    });
  }, [filters]);

  // US-FIN-029 — lente ativa (clamp client-side espelha o backend) + chips
  // compatíveis. Trocar de lente RE-ARMA os chips pro conjunto da lente
  // (espelha applyLente do protótipo F1 financeiro-page.jsx).
  const lente: LenteId = LENTE_SETS[filters.lente] ? filters.lente : 'caixa';
  const lenteSet = LENTE_SETS[lente];
  const applyLente = useCallback((id: LenteId, lifecycle?: LifecycleId[]) => {
    aplicar({ lente: id, lifecycle: lifecycle ?? LENTE_SETS[id] });
  }, [aplicar]);

  const onBaixar = (id: number) => {
    router.post(`/financeiro/unificado/${id}/baixar`, {}, {
      preserveScroll: true,
      onSuccess: () => { /* toast tratado no flash */ },
    });
  };

  // FA-5 N3 — navega prev/next título na lista FILTRADA sem fechar o drawer
  // (clamp, não wrap; espelha o handler J/K já existente). O cluster ↑n/N↓ do
  // header do drawer chama isto; J/K do teclado continuam funcionando igual.
  const navTitulo = useCallback((dir: 1 | -1) => {
    const ids = lancamentos.map((l) => l.id);
    if (ids.length === 0 || selectedId === null) return;
    const i = ids.indexOf(selectedId);
    if (i < 0) return;
    const j = Math.min(Math.max(i + dir, 0), ids.length - 1);
    if (j !== i) setSelectedId(ids[j] ?? null);
  }, [lancamentos, selectedId]);

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
      // FA-5 9.75 P2 — R com drawer ABERTO liquida o título selecionado (Recebi/Paguei),
      // espelhando o botão primário do footer. Sem drawer (ou já liquidado) → cai no
      // atalho de novo recebimento abaixo. Precedência resolve a colisão R-global/R-drawer.
      if (e.key === 'r' && !inEditable && selectedId !== null) {
        const row = lancamentos.find((l) => l.id === selectedId);
        if (row && (row.status === 'aberto' || row.status === 'atrasado' || row.status === 'vencendo')) {
          e.preventDefault();
          openBaixa(selectedId);
          return;
        }
      }
      // PR G (2026-05-25) G6 auditoria — N/R/P atalhos novo lançamento.
      //   N = Novo recebimento (default — mais comum em ERP gráfico)
      //   R = Receber explícito (sem título selecionado)
      //   P = Pagar explícito
      if ((e.key === 'n' || e.key === 'r') && !inEditable) {
        e.preventDefault();
        setCreateTipo('receber');
        return;
      }
      if (e.key === 'p' && !inEditable) {
        e.preventDefault();
        setCreateTipo('pagar');
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
  }, [selectedId, favs, lancamentos, selectedRows, clearSelection, onBaixar, openBaixa]);

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
      {/* Wave 4 (2026-06-18): migrado pra <PageHeader> canon v3.8 (ADR 0189/0190),
          paridade com Dashboard/Dre/ContasPagar. O conteúdo da Zona R (3 lentes
          US-FIN-029 + divisor + FinanceiroSubNav + dropdown "Novo título") é
          preservado via children (escape hatch) — mirror do pattern Dre/ContasPagar. */}
      <PageHeader
        title="Financeiro"
        suffix=" · Visão unificada"
        subtitle={<>{periodLabel}{businessName ? ` · ${businessName}` : ''} · caixa unificado</>}
      >
        <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
          {/* US-FIN-029 (2026-06-10) — segmented 3 lentes (Caixa · A receber · A pagar),
              direção [W] 2026-05-31 (charter v14 + MWART unificado-3-lentes). Camada 1
              do filtro grosso; chips lifecycle refinam DENTRO da lente. Pattern visual =
              pill segmented do Fluxo/Index.tsx (TabSwitcher), consistência declarada no
              charter do Fluxo. Deep-link ?lente= clamp caixa. */}
          <div
            className="inline-flex shrink-0 bg-muted rounded-md p-0.5 border border-border"
            role="group"
            aria-label="Lente do fluxo (camada 1 do filtro)"
          >
            {FIN_LENTES.map((l) => (
              <button
                key={l.id}
                type="button"
                onClick={() => { if (l.id !== lente) applyLente(l.id); }}
                aria-pressed={lente === l.id}
                className={
                  'h-7 px-3 rounded text-[12px] flex items-center transition tabular-nums ' +
                  (lente === l.id
                    ? 'bg-background shadow-sm font-medium text-foreground'
                    : 'text-muted-foreground hover:text-foreground')
                }
              >
                {l.label}
              </button>
            ))}
          </div>
          {/* Primary "+ Novo título" — canto direito, roxo do canon var(--accent)
              (ADR 0190 — .os-btn.primary universal roxo 295, supersede hue 145 financas ADR 0182).
              Wagner 2026-05-21: Unificado é caso especial — mostra ambos receivable+
              payable. Click do "+ Novo título" abre dropdown menu com escolha explícita
              (Receber/Pagar/OCR boleto) em vez de levar pra form genérico ambíguo. */}
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              {/* Primary roxo 295 FIXO (ADR 0190 — primary universal, NÃO magenta 330).
                  BUG corrigido 2026-07-06 (diff prod×protótipo, Wagner): este botão
                  renderiza no PageHeader, FORA do wrapper `.fin-cowork` — e a ÚNICA regra
                  de `.os-btn.primary` do build é escopada `.fin-cowork .os-btn.primary`
                  (cowork-canon-financeiro-bundle.css). Logo o `var(--accent)` nunca era
                  aplicado (botão ghost) e, quando era, herdava o `--accent` tweakável do
                  AppShellV2 (magenta 330 no browser do Wagner). Estilo inline = imune ao
                  escopo E ao Tweaks slider, travado no roxo 295 canon (== FinanceiroPrimaryButton). */}
              <button
                type="button"
                className="os-btn primary"
                style={{
                  backgroundColor: 'oklch(0.55 0.15 295)',
                  borderColor: 'oklch(0.45 0.15 295)',
                  color: 'oklch(0.99 0 0)',
                }}
              >
                <Plus size={13} /> Novo título <ChevronDown size={11} />
              </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-48">
              <DropdownMenuItem onClick={() => setCreateTipo('receber')}>
                <TrendingUp size={13} className="mr-2 text-success" /> Novo recebimento
              </DropdownMenuItem>
              <DropdownMenuItem onClick={() => setCreateTipo('pagar')}>
                <TrendingDown size={13} className="mr-2 text-destructive" /> Novo pagamento
              </DropdownMenuItem>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={() => setOcrSheetOpen(true)} title="Importar boleto via foto/PDF (OCR via IA)">
                <Camera className="mr-2 h-3.5 w-3.5" /> Importar boleto OCR
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </PageHeader>

      {/* Subnav unificada em LINHA PRÓPRIA full-width abaixo do header (fidelidade
          protótipo Cowork [W] 2026-06-29, ADR 0313). Antes vivia DENTRO da faixa do
          PageHeader junto com título+lente+primary (ADR 0180 Fase 5 "header em uma
          linha") — com 8 abas estourava e espremia o título numa coluna vertical.
          Própria linha = 8 abas cabem + título respira. Ações features (Buscar/
          Resumir/Fechamento/Apresentar/Imprimir/Exportar) seguem no overflow `⋯`. */}
      <div className="mt-1 border-b border-border/60">
        <FinanceiroSubNav
          active="unificado"
          hidePrimary
          maxVisible={8}
          extraOverflowItems={[
            { key: 'buscar',     label: 'Buscar (⌘K)',     icon: <Search size={13} />,      onClick: () => setPaletteOpen(true) },
            { key: 'resumir',    label: 'Resumir mês',     icon: <Sparkles size={13} />,    onClick: () => setResumoOpen(true),                                  title: 'Resumo executivo do mês (narrativa compute-based · Onda 9 v1)' },
            { key: 'fechamento', label: 'Fechamento',      icon: <CheckSquare size={13} />, onClick: () => setChecklistOpen(true),                               title: 'Trilha de 12 passos do fechamento mensal' },
            { key: 'apresentar', label: 'Apresentar',      icon: <Play size={13} />,        onClick: () => setPresentOpen(true),                                 title: 'Modo apresentação fullscreen (Esc fecha · 1/2/3 muda vista)' },
            { key: 'imprimir',   label: favs.count > 0 ? `Imprimir (${favs.count}★)` : 'Imprimir', icon: <Printer size={13} />, onClick: () => { setTranscriptOnlyFavs(false); setTranscriptOpen(true); }, title: 'Folha jurídica imprimível' },
            { key: 'exportar',   label: 'Exportar XLSX/PDF',icon: <Download size={13} />,   onClick: () => setPaletteOpen(true),                                 title: 'Exportar lançamentos do período' },
          ]}
        />
      </div>

      <KpiBar kpis={kpis} lancamentos={lancamentos} onKpiSelect={(l, lifecycle) => applyLente(l, lifecycle)} periodLabel={periodLabel} lenteAtiva={lente} />

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
      {/* LINHA 1 do filtro (fidelidade protótipo [W] 2026-07-06 — "o filtro deveria estar
          em duas linhas"): Filtrar-por + PeriodBar em cima; chips/contas/plano/busca na
          linha 2 (mesma ordem do proto financeiro-page.jsx). */}
      <div className="fin-toolbar mt-4">
        {/* Paridade filtros WR (2026-06-03) — filtro por CAMPO de data + intervalo.
            Espelha os filtros de data do WR Comercial (Emissão/Vencimento/Pagamento/
            Competência). O campo escolhido + intervalo aplicam na TABELA **e** nos
            CARDS de KPI (kpisCore segue o mesmo data_campo) — totais consistentes
            com o grid filtrado. Intervalo vazio = usa o período preset do header.
            NF/Vendas do WR exigem link título→transaction (origem_id), ainda
            pendente. */}
        <div className="fin-filter-group" role="group" aria-label="Filtro por data">
          {/* Segmented "Filtrar por" (fidelidade protótipo [W] 2026-07-06: era um <select>
              nativo, vira segmentado — igual ao proto financeiro-page.jsx e ao mesmo visual
              dos presets do FinPeriodBar logo à frente: `bg-muted` + ativo `bg-background
              shadow-sm`). Contrato backend INTACTO — dispara `aplicar({ data_campo })`, mesmo
              param `data_campo` (vencimento/emissao/pagamento/competencia). Bônus: some 1
              <select> nativo (ds/no-native-select). */}
          <span className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium whitespace-nowrap">Filtrar por</span>
          <div className="inline-flex items-center bg-muted rounded-md p-0.5 border border-border" role="group" aria-label="Campo de data">
            {([
              { id: 'vencimento', label: 'Vencimento' },
              { id: 'emissao', label: 'Emissão' },
              { id: 'pagamento', label: 'Pagamento' },
              { id: 'competencia', label: 'Competência' },
            ] as const).map((f) => (
              <button
                key={f.id}
                type="button"
                onClick={() => aplicar({ data_campo: f.id })}
                aria-pressed={filters.data_campo === f.id}
                title={`Filtrar pela data de ${f.label.toLowerCase()} (igual ao WR Comercial)`}
                className={
                  'h-6 px-2 rounded text-[11.5px] transition ' +
                  (filters.data_campo === f.id
                    ? 'bg-background shadow-sm font-medium text-foreground'
                    : 'text-muted-foreground hover:text-foreground')
                }
              >
                {f.label}
              </button>
            ))}
          </div>
          {/* PeriodBar (fidelidade protótipo [W] 2026-06-29, ADR 0313): presets Dia/
              Semana/Mês/Ano/Tudo + navegador de mês ‹ › + "Personalizado" que revela
              os dd/mm. Frontend-only — apenas seta data_inicio/fim (mesmo filtro de
              intervalo de antes; backend + KPIs já o seguem). "Mês" = mês atual
              reproduz o total default (periodo=mes_atual) — âncora da dual-confirmação. */}
          <FinPeriodBar
            dataInicio={filters.data_inicio}
            dataFim={filters.data_fim}
            count={pagination?.total ?? lancamentos.length}
            onChange={(ini, fim) => aplicar({ data_inicio: ini, data_fim: fim })}
          />
        </div>
      </div>

      {/* LINHA 2 do filtro — chips lifecycle + toggles + contas + plano + busca/densidade. */}
      <div className="fin-toolbar mt-2">
        {/* US-FIN-029 — chips refinam DENTRO da lente: chip incompatível com a lente
            ativa NÃO renderiza (some, não desabilitado — menos ruído, MWART dim 4). */}
        <div className="fin-filter-group" role="group" aria-label="Filtros por ciclo de vida">
          {FILTER_LIFECYCLE.filter((lc) => lenteSet.includes(lc.id)).map((lc) => {
            const on = filters.lifecycle.includes(lc.id);
            const count = countByLifecycle(lc.id, lancamentos);
            const toggle = () => {
              const next = on
                ? filters.lifecycle.filter((x) => x !== lc.id)
                : [...filters.lifecycle, lc.id];
              aplicar({ lifecycle: next });
            };
            return (
              <button
                key={lc.id}
                type="button"
                aria-pressed={on}
                className={'fin-filter-cb' + (on ? ' on' : '')}
                // Onda 12 refine — hue SEMPRE setada (mesmo OFF) pra borda semântica
                // persistir (paridade canon REAL: pills coloridos mesmo desligados).
                style={{ ['--cb-hue' as string]: lc.hue } as React.CSSProperties}
                onClick={toggle}
              >
                <span className="fin-filter-cb-box" />
                <span>{lc.label}</span>
                {/* Onda 12 refine — count sempre visível (paridade canon: mostra 0 também). */}
                <span className="fin-filter-ct">{count}</span>
              </button>
            );
          })}
        </div>

        <span className="fin-filter-sep" />

        {/* Onda 12.5 (2026-05-19) — Toggle "Só atrasados" usa classe `fin-filter-toggle`
            (canon REAL DOM forensics) em vez de `fin-filter-cb` que é dos lifecycle.
            Toggle = on/off independente; lifecycle = multi-select pill colorido. */}
        {/* Inventário 2026-07-07 P1: `.on` sozinho não tinha NENHUMA regra CSS (só a
            morta `.on.warn`) — toggle ligado ficava idêntico ao desligado. Estado ON
            em Tailwind (protótipo: neg-soft/neg — financeiro-page.jsx:632). */}
        <button
          type="button"
          aria-pressed={filters.overdue}
          className={'fin-filter-toggle' + (filters.overdue ? ' on bg-destructive-soft! text-destructive-fg! border-destructive/50!' : '')}
          title="AND multiplicativo: combina com lifecycle ativos"
          onClick={() => aplicar({ overdue: !filters.overdue })}
        >
          <span>Só atrasados</span>
          <span className="fin-filter-ct">{countOverdue(lancamentos)}</span>
        </button>

        {/* Wagner 2026-06-03 — Toggle "Arquivados": por padrão lançamentos cancelados/
            inativos ficam ESCONDIDOS (não somam). Ligado, mostra SÓ os arquivados.
            Mutuamente claro com a lista de ativos — backend troca o status filtrado. */}
        <button
          type="button"
          aria-pressed={filters.arquivados}
          className={'fin-filter-toggle' + (filters.arquivados ? ' on bg-primary/10! text-primary! border-primary/40!' : '')}
          title="Mostrar lançamentos arquivados (cancelados/inativos). Por padrão ficam escondidos e não somam no caixa."
          onClick={() => aplicar({ arquivados: !filters.arquivados })}
        >
          <span>🗄 Arquivados</span>
        </button>

        <span className="fin-filter-sep" />

        {/* Aging buckets (US-FIN-022) REMOVIDO 2026-06-29 — [W] aprovou screenshot do
            protótipo Cowork que NÃO tem faixas de aging na linha de filtros ("isso eu
            não quero"). Retorna ao F1 enxuto original do charter (status `atrasado`
            único). Backend agingBreakdown segue computado (inócuo, ignorado pela UI). */}

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
                  <button
                    key={af.id}
                    type="button"
                    aria-pressed={on}
                    className={'fin-filter-cb' + (on ? ' on' : '')}
                    style={{ ['--cb-hue' as string]: af.hue } as React.CSSProperties}
                    onClick={toggle}
                  >
                    <span className="fin-filter-cb-box" />
                    <span>{af.label}</span>
                    <span className="fin-filter-ct">{count}</span>
                  </button>
                );
              })}
            </div>

            <span className="fin-filter-sep" />
          </>
        )}

        {/* (bloco "Filtro por data" MOVIDO pra LINHA 1 acima — proto em 2 linhas, [W] 2026-07-06) */}

        {/* Onda 7 (2026-05-20): multi-select de contas via Popover + Checkbox.
            Backend aceita CSV "1,3,5" via filters.conta. Frontend mostra label
            agregado: "Todas as contas" / "Conta X" / "N contas". */}
        <FinMultiSelectContas contas={contas} valueCSV={filters.conta} onChange={(csv) => aplicar({ conta: csv })} />

        {/* Onda 12.7 (2026-05-19) — Wagner: substituir 'Categorias' (tags livres) por
            'Plano de Contas' (estrutura contábil hierárquica BR). Renderiza com indent
            visual via `nivel` (4 espaços por nível) pra leitura tipo árvore.
            Mantém prop `filters.categoria` por back-compat (mesmo querystring). */}
        <Select
          value={filters.categoria || '__none__'}
          onValueChange={(v) => aplicar({ categoria: v === '__none__' ? '' : v })}
        >
          <SelectTrigger variant="shadcn" size="sm" className="max-w-[220px] text-[12px]" aria-label="Plano de Contas">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="__none__">Todo o plano de contas</SelectItem>
          {(planosConta ?? []).map((p) => (
            <SelectItem key={p.id} value={String(p.id)} title={`${p.codigo} ${p.nome} (${p.tipo})`}>
              {'  '.repeat(Math.max(0, p.nivel - 1))}
              {p.codigo} · {p.nome}
            </SelectItem>
          ))}
          </SelectContent>
        </Select>

        <div className="fin-toolbar-r">
          <div className="fin-search-wrap">
            <Search className="h-3.5 w-3.5" aria-hidden="true" />
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
              <tr className="text-[10px] uppercase tracking-widest text-muted-foreground border-b border-border bg-muted/30">
                {/* Onda 12 (2026-05-20): checkbox select-all (referencia: visible rows). */}
                <th className="pl-4 pr-1 py-2 w-7">
                  <Checkbox
                    aria-label="Selecionar todos lançamentos visíveis"
                    className="cursor-pointer"
                    checked={lancamentos.length > 0 && lancamentos.every((l) => selectedRows.has(l.id))}
                    onCheckedChange={(checked) => {
                      if (checked === true) setSelectedRows(new Set(lancamentos.map((l) => l.id)));
                      else clearSelection();
                    }}
                  />
                </th>
                <th className="pl-1 pr-2 py-2 w-7"></th>
                <SortableHeader k="vencimento" label="Vencimento" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium w-[110px]" />
                <SortableHeader k="lancamento" label="Lançamento" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium" />
                <SortableHeader k="contraparte" label="Contraparte" filters={filters} aplicar={aplicar} className="px-2 py-2 text-left font-medium" />
                <th className="px-2 py-2 text-left font-medium">Categoria</th>
                <th className="px-2 py-2 text-left font-medium">Forma</th>
                <th className="px-2 py-2 text-left font-medium">Conta</th>
                <th className="px-2 py-2 text-left font-medium">Baixa</th>
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
                      <tr><td colSpan={12} className="bg-muted/60 border-b border-border">
                        <div className="px-4 py-1.5 flex items-center text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                          <span>{label}</span>
                          <span className="ml-auto text-muted-foreground normal-case tracking-normal">{rows.length} {rows.length === 1 ? 'lançamento' : 'lançamentos'}</span>
                        </div>
                      </td></tr>
                    )}
                    {rows.map(r => (
                      <LinhaTabela
                        key={r.id} row={r} dens={dens}
                        selected={selectedId === r.id}
                        onSelect={() => setSelectedId(r.id)}
                        onBaixar={() => openBaixa(r.id)}
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
                <tr><td colSpan={12} className="py-16">
                  <div className="flex flex-col items-center gap-3 text-center">
                    <div className="text-sm text-muted-foreground">
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
            <div className="px-4 py-2 flex items-center justify-between border-t border-border text-[12px] text-muted-foreground bg-muted/40">
              <span>
                Página <b>{pagination.page}</b> de <b>{pagination.total_pages}</b>
                <span className="mx-2 text-muted-foreground">·</span>
                <b>{pagination.total}</b> lançamentos total
                <span className="mx-2 text-muted-foreground">·</span>
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
                <Select
                  value={String(pagination.per_page)}
                  onValueChange={(v) => aplicar({ per_page: parseInt(v, 10), page: 1 })}
                >
                  <SelectTrigger variant="shadcn" size="sm" className="ml-2 text-[11px]" aria-label="Itens por página">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {[20, 50, 100, 200, 500].map((n) => <SelectItem key={n} value={String(n)}>{n}/pág</SelectItem>)}
                  </SelectContent>
                </Select>
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
                        ? 'bg-success-soft text-success-fg border border-success/20'
                        : 'bg-muted text-foreground border border-border')
                    }
                    aria-hidden
                  >
                    {selected.kind === 'receivable' ? '↑' : '↓'}
                  </span>
                  <div className="flex-1 min-w-0">
                    <div className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium flex items-center gap-2">
                      <span className="inline-flex items-center gap-1">
                        {selected.kind === 'receivable' ? 'A receber' : 'A pagar'} ·{' '}
                        <CopyVal text={String(selected.numero || selected.id)}>#{selected.numero || selected.id}</CopyVal>
                      </span>
                      {selected.conferido_at && (
                        <span className="text-[10px] text-success font-medium normal-case tracking-normal">✓ conferido</span>
                      )}
                    </div>
                    <SheetTitle className="text-[14px] font-semibold mt-0.5 truncate">
                      <FinCrossLinkify text={selected.descricao} />
                    </SheetTitle>
                  </div>
                  {/* FA-5 N3 — posição na lista FILTRADA + nav J/K (cluster ↑n/N↓).
                      mr-7 deixa folga pro X de fechar (shadcn SheetContent, absoluto). */}
                  {(() => {
                    const ids = lancamentos.map((l) => l.id);
                    const i = ids.indexOf(selected.id);
                    const total = ids.length;
                    if (total <= 1) return null;
                    const pos = i >= 0 ? i + 1 : 0;
                    return (
                      <div className="fin-dw-nav mr-7 shrink-0" title="Navegar entre títulos (J / K)">
                        <button type="button" className="fin-dw-nav-btn" disabled={i <= 0} onClick={() => navTitulo(-1)} aria-label="Título anterior (K)">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden><polyline points="18 15 12 9 6 15" /></svg>
                        </button>
                        <span className="fin-dw-pos tabular-nums">{pos > 0 ? pos : '–'}<i>/</i>{total}</span>
                        <button type="button" className="fin-dw-nav-btn" disabled={i < 0 || i >= total - 1} onClick={() => navTitulo(1)} aria-label="Próximo título (J)">
                          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden><polyline points="6 9 12 15 18 9" /></svg>
                        </button>
                      </div>
                    );
                  })()}
                </div>
              </SheetHeader>

              {/* PR-3 F2 ([W] "aprovado" 2026-06-10) — Camada 1 "O FATO" FIXA fora do
                  scroll (ordem canon: header → hero → tabs → corpo). Gabarito Prova
                  Viva 9.75 / protótipo financeiro-page.jsx Drawer: label de estado
                  uppercase (destructive se atrasado) · valor mono tabular grande com
                  prefixo/centavos pequenos (whitespace-nowrap no prefixo) · chip +
                  vencimento à direita · FSM compacto. Substitui o hero que rolava
                  junto com o corpo. */}
              {(() => {
                const settled = selected.status === 'recebido' || selected.status === 'pago';
                const isIn = selected.kind === 'receivable';
                const labelTone =
                  selected.status === 'atrasado' ? 'text-destructive'
                  : selected.status === 'vencendo' ? 'text-warning-foreground'
                  : 'text-muted-foreground';
                const [intPart, decPart] = (selected.valor ?? 0)
                  .toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                  .split(',');
                // FA-5 R3 — urgência sai daqui (era "linha da data" duplicando o frescor);
                // o chip FinPillFrescor passa a ser a única voz do tempo relativo.
                const fmtBr = (d: string | null | undefined) => (d ? d.split('-').reverse().join('/') : '—');
                // FSM compacto — etapas do ciclo do título (espelha finFsmStage do
                // protótipo: liquidado=3 · conferido=1 · lançado=0; conciliação real
                // ainda não vive no shape → estágio 2 fica no caminho, não asserido).
                const etapas = ['Lançado', 'Conferido', 'Conciliado', 'Liquidado'];
                const stage = settled ? 3 : selected.conferido_at ? 1 : 0;
                // #2 Tribunal Onda 2 (cadeira Tufte) — tira o número do isolamento: compara
                // com a média dos PARES reais (mesma categoria + mesmo kind, valor>0) no
                // conjunto carregado client-side. Anti-slop: só renderiza com ≥2 pares; tom
                // NEUTRO (seta + %), sem valência verde/vermelho. É cross-sectional, ≠ do
                // delta_pct temporal "+X% vs mês anterior" adiado em US-FIN-023 (charter).
                const vsAvg = (() => {
                  if (!selected.categoria) return null;
                  const pares = lancamentos.filter(
                    (r) => r.id !== selected.id && r.categoria === selected.categoria && r.kind === selected.kind && (r.valor ?? 0) > 0,
                  );
                  if (pares.length < 2) return null;
                  const media = pares.reduce((s, r) => s + (r.valor ?? 0), 0) / pares.length;
                  if (media <= 0) return null;
                  const pct = Math.round((((selected.valor ?? 0) - media) / media) * 100);
                  return { pct, n: pares.length + 1 };
                })();
                return (
                  <div className="shrink-0 px-5 pt-3 pb-3.5 border-b border-border fin-dw-hero">
                    <Inline align="end" justify="between" gap={3}>
                      <div className="min-w-0">
                        <div className={`text-[10.5px] uppercase tracking-[0.1em] font-semibold ${labelTone}`}>
                          {settled ? 'Liquidado' : isIn ? 'A receber' : 'A pagar'}
                        </div>
                        <Inline align="baseline" gap={0} className="mt-0.5">
                          <span className="text-[13.5px] text-muted-foreground font-mono whitespace-nowrap mr-1">{isIn ? '+ R$' : '− R$'}</span>
                          <span className={`text-[length:var(--fs-9,38px)] leading-none font-semibold tracking-tight font-mono tabular-nums ${isIn ? 'text-success-foreground' : 'text-foreground'}`}>{intPart}</span>
                          <span className="text-[13.5px] text-muted-foreground font-mono">,{decPart}</span>
                        </Inline>
                        {vsAvg && (
                          <div
                            className="mt-1 text-[11px] text-muted-foreground tabular-nums"
                            title={`Comparação com a média de ${vsAvg.n} títulos de "${selected.categoria}" (${isIn ? 'a receber' : 'a pagar'})`}
                          >
                            <span aria-hidden>{vsAvg.pct > 0 ? '↑' : vsAvg.pct < 0 ? '↓' : '→'}</span>{' '}
                            {vsAvg.pct > 0 ? '+' : ''}{vsAvg.pct}% vs média · {selected.categoria}
                          </div>
                        )}
                      </div>
                      <Stack gap={1} align="end" className="gap-1.5 shrink-0 pb-0.5">
                        {/* FA-5 R3 — estado dito 1×: o label uppercase colorido (acima) já diz o
                            estado; o frescor fica só como chip calmo e SOME quando liquidado
                            (protótipo: hero do liquidado mostra só a data). StatusPill saiu. */}
                        {!settled && (
                          <FinPillFrescor row={{ due: selected.vencimento, paid_at: null, vencimento: selected.vencimento }} />
                        )}
                        <div className="text-[12.5px] text-muted-foreground tabular-nums whitespace-nowrap">
                          {settled
                            ? <>liq. <b className="font-medium text-foreground">{selected.liquidacao || '—'}</b></>
                            : <>vence <b className="font-medium text-foreground">{fmtBr(selected.vencimento)}</b></>}
                        </div>
                      </Stack>
                    </Inline>
                    {/* #4 Tribunal Onda 2 (Victor/Rams) — título liquidado não gasta ~80px com
                        4 etapas todas marcadas: vira resumo de 1 linha. Aberto mantém o stepper
                        completo. ("no prazo/atraso" omitido de propósito: `liquidacao` chega
                        como "DD MMM" — sem data parseável pra asserir sem fabricar; a data da
                        baixa já aparece no hero acima. Suffix vira proposta se o shape expor a
                        data ISO da liquidação.) */}
                    {settled ? (
                      <Inline gap={2} className="mt-3 text-[11.5px] text-muted-foreground" role="img" aria-label={`Ciclo concluído: ${etapas.join(' → ')}`}>
                        <span className="inline-grid place-items-center w-[15px] h-[15px] rounded-full bg-primary text-primary-foreground text-[9px] font-semibold shrink-0" aria-hidden>✓</span>
                        <span><b className="font-medium text-foreground">{etapas[0]} → {etapas[etapas.length - 1]}</b> · {etapas.length} etapas</span>
                      </Inline>
                    ) : (
                      <Inline gap={0} className="mt-3" role="img" aria-label={`Etapa do ciclo: ${etapas[stage]}`}>
                        {etapas.map((lbl, i) => (
                          <React.Fragment key={lbl}>
                            {i > 0 && <span className={`h-px flex-1 mx-1.5 ${i <= stage ? 'bg-primary' : 'bg-border'}`} aria-hidden />}
                            <span className="inline-flex items-center gap-1" aria-hidden>
                              <span className={
                                'w-[15px] h-[15px] rounded-full grid place-items-center text-[9px] font-semibold border ' +
                                (i < stage
                                  ? 'bg-primary border-primary text-primary-foreground'
                                  : i === stage
                                    ? 'bg-background border-primary text-primary shadow-[0_0_0_3px] shadow-primary/15'
                                    : 'bg-background border-border text-muted-foreground')
                              }>
                                {i < stage ? '✓' : i + 1}
                              </span>
                              <span className={`text-[10.5px] ${i === stage ? 'text-primary font-semibold' : i < stage ? 'text-foreground' : 'text-muted-foreground'}`}>{lbl}</span>
                            </span>
                          </React.Fragment>
                        ))}
                      </Inline>
                    )}
                  </div>
                );
              })()}

              {/* FA-5/9.75 — Conferir + Editar campos viram BOTÕES (protótipo: edição fora das
                  abas). Linha entre o hero (fixo) e as abas. */}
              <div className="shrink-0 px-5 pt-2.5 flex items-center gap-2 fin-toggles-row">
                <FinConferidoToggle rowId={selected.id} conferido={conferido} />
                <Button variant="outline" size="sm" className="fin-edit-btn" onClick={() => setEditOpen(true)} title="Editar campos do lançamento">
                  <span aria-hidden>✎</span>
                  <span className="ml-1">Editar campos</span>
                </Button>
              </div>

              {/* Nav de abas — 9.75 (2 abas: Detalhes / ✦ IA; Editar virou botão acima) */}
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
              </nav>

              {/* Aba Detalhes — info + audit + comments + actions
                  Wagner 2026-05-25: body com flex-1 + overflow-y-auto + min-h-0
                  permite o footer (irmão, fora do scroll) ficar sticky-bottom. */}
              {drawerTab === 'detalhes' && (
                <div className="mt-3 px-5 pb-4 space-y-5 text-[13px] flex-1 overflow-y-auto min-h-0">
                  {/* #1 Veredito (Tribunal Onda 2 · cadeira Victor) — a tela conclui pelo usuário
                      antes de qualquer varredura: 1ª coisa do corpo, acima dos Vínculos, 1 linha
                      + sub. 100% derivado do estado do título (status/NF/vencimento) — sem mock.
                      Tom por token semântico (success/warning/destructive/muted). vencimento é
                      ISO YYYY-MM-DD → contagem de dias confiável. */}
                  {(() => {
                    const liq = selected.status === 'recebido' || selected.status === 'pago';
                    const temNf = !!selected.nfe_numero;
                    const hojeMs = (() => { const n = new Date(); return Date.UTC(n.getFullYear(), n.getMonth(), n.getDate()); })();
                    const vp = (selected.vencimento || '').split('-');
                    const vencMs = vp.length === 3 ? Date.UTC(+vp[0], +vp[1] - 1, +vp[2]) : null;
                    const dias = vencMs != null ? Math.round((vencMs - hojeMs) / 86400000) : null;
                    const v = liq
                      ? (temNf
                          ? { tone: 'pos' as const, glyph: '✓', head: 'Nada pendente.', sub: 'Pago, conciliado e com NF vinculada.' }
                          : { tone: 'warn' as const, glyph: '!', head: 'Pago, mas sem NF.', sub: 'Falta vincular o documento fiscal.' })
                      : selected.status === 'atrasado'
                        ? { tone: 'neg' as const, glyph: '!', head: dias != null && dias <= -1 ? `Vencida há ${Math.abs(dias)} ${Math.abs(dias) === 1 ? 'dia' : 'dias'} — cobrar.` : 'Vencida — cobrar.', sub: selected.contraparte || 'Cobrança pendente.' }
                        : selected.status === 'vencendo'
                          ? { tone: 'warn' as const, glyph: '!', head: dias != null && dias > 0 ? `Vence em ${dias} ${dias === 1 ? 'dia' : 'dias'}.` : 'Vence hoje — preparar cobrança.', sub: selected.contraparte || 'Prepare a cobrança.' }
                          : { tone: 'muted' as const, glyph: '•', head: dias != null && dias > 0 ? `Em aberto — vence em ${dias} dias.` : 'Em aberto.', sub: 'Nada urgente por agora.' };
                    const t = {
                      pos: { box: 'bg-success/10 ring-success/25', ic: 'bg-success text-white' },
                      warn: { box: 'bg-warning/10 ring-warning/30', ic: 'bg-warning text-white' },
                      neg: { box: 'bg-destructive/10 ring-destructive/25', ic: 'bg-destructive text-white' },
                      muted: { box: 'bg-muted ring-border', ic: 'bg-muted-foreground/25 text-muted-foreground' },
                    }[v.tone];
                    return (
                      <div className={`flex items-start gap-2.5 rounded-md px-3 py-2.5 ring-1 ring-inset ${t.box}`}>
                        <span className={`w-[22px] h-[22px] rounded-full grid place-items-center shrink-0 mt-px text-[12px] font-bold leading-none ${t.ic}`} aria-hidden>{v.glyph}</span>
                        <div className="min-w-0">
                          <div className="text-[13px] font-semibold text-foreground leading-snug">{v.head}</div>
                          <div className="text-[11.5px] text-muted-foreground leading-snug mt-0.5 truncate">{v.sub}</div>
                        </div>
                      </div>
                    );
                  })()}

                  {/* FA-5 — Vínculos: chips estruturados derivados dos tokens #V-/#OS- da descrição
                      + nfe_numero (mesma fonte do FinCrossLinkify; não inventa dado). */}
                  <FinVinculosChips descricao={selected.descricao} nfeNumero={selected.nfe_numero} />

                  {/* FA-5 — ficha de identificação em fin-kv-card (lavanda sutil); MANTÉM os 17
                      campos WR ([W] 2026-06-11). Onda 18 grid 2-col canon. */}
                  <div className="fin-kv-card grid grid-cols-2 gap-y-3 gap-x-3">
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Contraparte</div>
                      <div className="mt-0.5 font-medium text-foreground"><CopyVal text={selected.contraparte}>{selected.contraparte}</CopyVal></div>
                      {selected.contraparte_doc && <div className="text-[11px] text-muted-foreground font-mono">{selected.contraparte_doc}</div>}
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Categoria</div>
                      <div className="mt-0.5 text-foreground"><FinKVCategoriaInline selected={selected} categorias={categorias} /></div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Canal</div>
                      <div className="mt-0.5 text-foreground">{selected.canal || 'manual'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Documento</div>
                      <div className="mt-0.5 text-foreground font-mono text-[12px]">{selected.documento || '—'}</div>
                    </div>
                    {/* Paridade campos lançamento WR (Fase 1 — 2026-06-03) */}
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Emissão</div>
                      <div className="mt-0.5 text-foreground">{selected.emissao ? selected.emissao.split('-').reverse().join('/') : '—'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Competência</div>
                      <div className="mt-0.5 text-foreground">{selected.competencia_mes ? selected.competencia_mes.split('-').reverse().join('/') : '—'}</div>
                    </div>
                    <div className="col-span-2">
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Condição de pagamento</div>
                      <div className="mt-0.5 text-foreground">{selected.condicao_pagamento || '—'}</div>
                    </div>
                    {/* 2026-06-03: forma de pagamento. Realizada (baixa) vem com hint read-only. */}
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Forma de pagamento</div>
                      <div className="mt-0.5 text-foreground flex items-center gap-1.5">
                        {(() => {
                          const Icon = formaPagamentoIcon(selected.forma_pagamento);
                          return Icon ? <Icon className="h-4 w-4 text-muted-foreground" aria-hidden /> : null;
                        })()}
                        <span>{formaPagamentoLabel(selected.forma_pagamento)}</span>
                        {selected.forma_pagamento_realizada && (
                          <span className="text-[10px] text-muted-foreground">· da baixa</span>
                        )}
                      </div>
                    </div>
                    <div className="col-span-2">
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Conta</div>
                      <div className="mt-0.5 text-foreground flex items-center gap-1.5">
                        <Landmark className="h-4 w-4 text-muted-foreground" aria-hidden />
                        <CopyVal text={selected.conta_bancaria || '—'}>{selected.conta_bancaria || '—'}</CopyVal>
                      </div>
                    </div>
                    {/* Paridade campos WR Fase 2 (2026-06-04, sobre base Felipe). Tokens
                        semânticos (text-muted-foreground/text-foreground) p/ não disparar R1.
                        Datas em formato data — HORA completa virá no re-import (Fase 2). */}
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Vencimento</div>
                      <div className="mt-0.5 text-foreground">{selected.vencimento ? selected.vencimento.split('-').reverse().join('/') : '—'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Data de pagamento</div>
                      <div className="mt-0.5 text-foreground">{selected.data_pagamento ? selected.data_pagamento.split('-').reverse().join('/') : '—'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Número do título</div>
                      <div className="mt-0.5 text-foreground font-mono text-[12px]">{selected.numero || '—'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Valor em aberto</div>
                      <div className="mt-0.5 text-foreground tabular-nums">{brl(selected.valor_aberto)}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Parcela</div>
                      <div className="mt-0.5 text-foreground">{selected.parcela || '—'}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Pedido</div>
                      <div className="mt-0.5 text-foreground">{selected.pedido || '—'}</div>
                    </div>
                    {/* Desconto / Juros — SEMPRE visíveis (pedido Wagner; antes só >0) */}
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Desconto</div>
                      <div className="mt-0.5 fin-num-pos tabular-nums">{brl(selected.desconto)}</div>
                    </div>
                    <div>
                      <div className="text-[11px] text-muted-foreground uppercase tracking-widest font-medium">Juros / Multa</div>
                      <div className="mt-0.5 fin-num-neg tabular-nums">{brl(selected.juros)}</div>
                    </div>
                  </div>

                  {selected.observacao && (
                    <div className="rounded-md border border-border bg-muted/40 p-3 text-[12.5px] text-foreground">{selected.observacao}</div>
                  )}

                  {/* PR-3 F2 (2026-06-10) — Conciliação vira LENTE (ícone primary/10 +
                      chip de status) e o estado conciliado vira box DISCRETO (bg muted +
                      check pequeno), não banda verde — padrão F2-aprovado [W]. */}
                  {(() => {
                    const settled = selected.status === 'recebido' || selected.status === 'pago';
                    // #3 Tribunal Onda 2 (Tufte/Rams) — selo de sucesso que só re-anuncia o corpo
                    // = tinta não-dado. Liquidado: tira "100% match" do header (o box abaixo já
                    // prova). Aberto mantém "aguardando" (info real).
                    return (
                      <DrawerLens icon={Landmark} title="Conciliação extrato" status={settled ? null : 'aguardando'} tone={settled ? 'pos' : 'muted'} hue={settled ? 'pos' : 'muted'}>
                        {settled ? (
                          <Inline align="start" gap={2} className="gap-2.5 rounded-md border border-border bg-muted px-3 py-2">
                            <span className="w-[18px] h-[18px] rounded-full grid place-items-center bg-success/15 text-success-foreground shrink-0 mt-px" aria-hidden>
                              <Check size={11} />
                            </span>
                            <div className="text-[12.5px] min-w-0">
                              <div className="font-medium text-foreground">Conciliado com extrato bancário</div>
                              <div className="text-muted-foreground tabular-nums">{selected.liquidacao || '—'} · {brl(selected.valor)} · 100% match</div>
                            </div>
                          </Inline>
                        ) : (
                          <div className="rounded-md border border-border px-3 py-2.5 text-[12.5px] text-muted-foreground flex items-start gap-2.5">
                            <span className="text-muted-foreground mt-0.5" aria-hidden>✦</span>
                            <div>
                              {/* Tolerância do matcher (parâmetro de produto, não valor de negócio) — restaurada do
                                  protótipo (financeiro-page.jsx:1576) após o filter-repo da redação BRL (2026-06-08)
                                  ter reescrito esta copy de UI e o usuário passar a ver o placeholder na tela. */}
                              Sem match no extrato. Ao liquidar, o sistema procura linhas próximas (±R$ 5,00 e ±2 dias) e sugere conciliação automática.
                            </div>
                          </div>
                        )}
                      </DrawerLens>
                    );
                  })()}

                  {/* PR-3 F2 (2026-06-10) — Lente FISCAL: NF + impostos estimados (Simples
                      Nacional, regime caixa). Estimativa VISUAL — apuração oficial no módulo
                      Fiscal; guia consolidada na sub-tela Impostos & obrigações (F2 PR-2).
                      Referência F1: LenteFiscal em financeiro-page.jsx (ISS 5% serviços
                      gráficos · DAS ≈6% sobre o recebido). */}
                  {(() => {
                    const isIn = selected.kind === 'receivable';
                    const hasNf = !!selected.nfe_numero;
                    const iss = isIn ? (selected.valor ?? 0) * 0.05 : 0;
                    const das = isIn ? (selected.valor ?? 0) * 0.06 : 0;
                    // #3 — com NF: tira "NF vinculada" do header (o nº da NF aparece no corpo).
                    // Sem NF: mantém "sem NF" (warn — buraco real).
                    return (
                      <DrawerLens icon={Percent} title="Fiscal" status={hasNf ? null : 'sem NF'} tone={hasNf ? 'pos' : 'warn'} hue="warn">
                        <Grid cols={2} gap={0} className="gap-x-5">
                          <div>
                            <div className="text-[10.5px] uppercase tracking-[0.08em] text-muted-foreground">{isIn ? 'NF-e de saída' : 'Documento fiscal'}</div>
                            <div className="text-[13px] text-foreground font-medium truncate">
                              {hasNf ? <CopyVal text={selected.nfe_numero ?? ''} mono>{selected.nfe_numero}</CopyVal> : <span className="text-warning-foreground">não emitida</span>}
                            </div>
                          </div>
                          <div>
                            <div className="text-[10.5px] uppercase tracking-[0.08em] text-muted-foreground">Regime</div>
                            <div className="text-[13px] text-foreground font-medium">Simples Nacional</div>
                          </div>
                        </Grid>
                        {isIn && (
                          <div className="mt-1.5 border-t border-border/60">
                            <Inline align="baseline" justify="between" gap={0} className="py-[5px] border-b border-border/60">
                              <span className="text-[12.5px] text-muted-foreground">ISS retido · 5%</span>
                              <span className="text-[12.5px] font-mono tabular-nums font-medium">{brl(iss)}</span>
                            </Inline>
                            <Inline align="baseline" justify="between" gap={0} className="py-[5px]">
                              <span className="text-[12.5px] text-muted-foreground">No DAS do mês · ≈ 6%</span>
                              <span className="text-[12.5px] font-mono tabular-nums font-medium text-warning-foreground">{brl(das)}</span>
                            </Inline>
                          </div>
                        )}
                        <p className="text-[10.5px] text-muted-foreground pt-1.5 leading-relaxed">
                          Estimativa — apuração e guia na sub-tela{' '}
                          <button type="button" className="font-medium underline underline-offset-2 hover:text-primary" onClick={() => router.visit('/financeiro/impostos')}>
                            Impostos &amp; obrigações
                          </button>{' '}
                          · oficial no módulo Fiscal.
                        </p>
                      </DrawerLens>
                    );
                  })()}

                  {/* FA-5 — Lente Cobrança (protótipo): ciclo título⇄cobrança. Reusa o
                      endpoint de boleto Inter que já existe; sem PIX (não há gerador no live). */}
                  {(() => {
                    const settledCob = selected.status === 'recebido' || selected.status === 'pago';
                    const isInCob = selected.kind === 'receivable';
                    const hasBoleto = !!selected.boleto?.linha_digitavel;
                    // #3 — liquidado: tira "encerrada" do header (o corpo diz "Título liquidado
                    // — cobrança encerrada"). Aberto mantém "em atraso"/"boleto emitido"/"a gerar".
                    const cobStatus = settledCob ? null : selected.status === 'atrasado' ? 'em atraso' : hasBoleto ? 'boleto emitido' : 'a gerar';
                    const cobTone: 'pos' | 'warn' | 'muted' = settledCob ? 'pos' : selected.status === 'atrasado' ? 'warn' : 'muted';
                    return (
                      <DrawerLens icon={Send} title="Cobrança" status={cobStatus} tone={cobTone} hue={settledCob ? 'pos' : selected.status === 'atrasado' ? 'warn' : 'accent'}>
                        {settledCob ? (
                          <Inline gap={2} className="gap-2 text-[12.5px]">
                            <span className="w-1.5 h-1.5 rounded-full bg-success shrink-0" aria-hidden />
                            <span className="text-foreground">Título liquidado — cobrança encerrada.</span>
                          </Inline>
                        ) : !isInCob ? (
                          <div className="text-[12.5px] text-muted-foreground">Saída — registre a baixa quando pagar (sem cobrança a emitir).</div>
                        ) : hasBoleto ? (
                          <div className="text-[12.5px] text-muted-foreground">Boleto emitido (Banco Inter). Linha digitável no rodapé — “Copiar boleto”.</div>
                        ) : (
                          <div>
                            <p className="text-[12.5px] text-muted-foreground mb-2">Nenhuma cobrança emitida. Gere um boleto — o status volta pra cá quando o cliente pagar.</p>
                            <Button variant="outline" size="sm" onClick={() => router.post(`/financeiro/unificado/${selected.id}/boleto`, {}, { preserveScroll: true })}>
                              <FileText className="h-3.5 w-3.5" aria-hidden />
                              <span className="ml-1">Gerar boleto</span>
                            </Button>
                          </div>
                        )}
                      </DrawerLens>
                    );
                  })()}

                  <div className="border-t border-border pt-4">
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

                  <div className="border-t border-border pt-4">
                    <FinCommentsThread rowId={selected.id} comments={comments} />
                  </div>

                  {/* Onda 21 (2026-05-19) #55 — Workflow aprovação pra títulos a pagar abertos. */}
                  {selected.kind === 'payable' && (selected.status === 'aberto' || selected.status === 'atrasado' || selected.status === 'vencendo') && (
                    <div className="border-t border-border pt-4">
                      <div className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium mb-2">Aprovação</div>
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
                              <RejeitarInline tituloId={selected.id} />
                            </>
                          ) : (
                            <span className="text-[11px] text-muted-foreground italic">
                              Aguardando aprovação de quem tem permissão.
                            </span>
                          )}
                        </div>
                      )}
                      {selected.aprovacao_status === 'aprovado' && (
                        <span className="inline-block px-2 py-0.5 rounded border text-[11px] font-medium bg-success-soft text-success-fg border-success/20">
                          ✓ Aprovado — liberado pra pagamento
                        </span>
                      )}
                      {selected.aprovacao_status === 'rejeitado' && (
                        <span className="inline-block px-2 py-0.5 rounded border text-[11px] font-medium bg-destructive-soft text-destructive-fg border-destructive/20">
                          ✗ Rejeitado — bloqueado pra pagamento
                        </span>
                      )}
                    </div>
                  )}

                  {/* US-FIN-026 (Onda 22) — painel completo Anexos (substitui botão upload-only Onda 20). */}
                  <FinAnexosPanel tituloId={selected.id} />
                </div>
              )}

              {/* Onda 21 (2026-05-20) + Wagner 2026-05-25 — Footer sticky-bottom.
                  Irmão do body scrollable (não filho) pra ficar fixado no rodapé
                  do SheetContent flex-col h-full. Sequência canon:
                  Ver NFe → Cobrar → Recebi/Paguei → Editar → Favoritar. */}
              {drawerTab === 'detalhes' && (
                <div className="fin-drawer-footer fin-drawer-footer-sticky">
                  {/* FA-5 — troubleshooter contextual (protótipo: "Resolver..."). Reusa o
                      FinTroubleshooterDialog já montado na página (setTroubleOpen). */}
                  <FinTroubleButton onClick={() => setTroubleOpen(true)} label="? Resolver" />
                  {/* FA-5 P2 — teclas visíveis (J/K nav · R liquida). Some no mobile (<720px)
                      e quando houver troubleshooter no footer (CSS :has). */}
                  <span className="fin-dw-hint" title="J / K navegam entre títulos · R liquida · Esc fecha">
                    <kbd className="fin-kbd">J</kbd><kbd className="fin-kbd">K</kbd><em>título</em>
                  </span>
                  {selected.nfe_numero && (
                    <Button variant="outline" size="sm" className="fin-foot-icon-btn" title="Ver NFe" onClick={() => router.visit(`/fiscal/nfe?numero=${selected.nfe_numero}`)}>
                      <Eye className="h-4 w-4" aria-hidden />
                      <span className="ml-1">Ver NFe</span>
                    </Button>
                  )}
                  {/* FA-5 P5/S3 — recibo imprimível com identidade Oimpresso (client-side iframe;
                      o módulo não tem rota server-side de recibo — verificado FA-5). */}
                  <Button variant="outline" size="sm" className="fin-foot-icon-btn" title="Imprimir recibo (identidade Oimpresso)" onClick={() => printReciboTitulo(selected)}>
                    <Printer className="h-4 w-4" aria-hidden />
                    <span className="ml-1">Recibo</span>
                  </Button>
                  {/* Gerar Boleto no drawer (2026-06-08) — emite boleto Inter pro
                      título SEM sair da Visão Unificada. Quando já emitido, vira
                      "Copiar boleto" (linha digitável persistida em metadata). */}
                  {selected.kind === 'receivable' && (selected.status !== 'recebido') && (
                    selected.boleto?.linha_digitavel ? (
                      <Button
                        variant="outline"
                        size="sm"
                        className="fin-foot-icon-btn"
                        title={`Boleto Inter · ${selected.boleto?.linha_digitavel ?? ''}`}
                        onClick={() => navigator.clipboard?.writeText(selected.boleto?.linha_digitavel ?? '')}
                      >
                        <span aria-hidden>📋</span>
                        <span className="ml-1">Copiar boleto</span>
                      </Button>
                    ) : (
                      <Button
                        variant="outline"
                        size="sm"
                        className="fin-foot-icon-btn"
                        title="Gerar boleto registrado (Banco Inter)"
                        onClick={() =>
                          router.post(`/financeiro/unificado/${selected.id}/boleto`, {}, { preserveScroll: true })
                        }
                      >
                        <span aria-hidden>✉</span>
                        <span className="ml-1">Gerar boleto</span>
                      </Button>
                    )
                  )}
                  {(selected.status !== 'recebido' && selected.status !== 'pago') && (
                    <Button onClick={() => openBaixa(selected.id)} className="fin-foot-mark-btn" title={selected.kind === 'receivable' ? 'Marcar recebido (atalho R)' : 'Marcar pago (atalho R)'}>
                      <span aria-hidden>✓</span>
                      <span className="ml-1">{selected.kind === 'receivable' ? 'Recebi' : 'Paguei'}</span>
                      <kbd className="fin-kbd fin-kbd-acc">R</kbd>
                    </Button>
                  )}
                  {/* FA-5 — Editar saiu do footer (virou botão 'Editar campos' no topo, ao lado de
                      Conferir); Favoritar continua pelo atalho B. Footer enxuto = protótipo. */}
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

                  <p className="text-[11.5px] text-muted-foreground italic pt-3 mt-3 border-t border-border">
                    Insights computacionais · pure compute · Fase 2 plugará JanaService LLM
                  </p>
                </div>
              )}

              {/* FA-5/9.75 — a aba 'Editar' virou o botão 'Editar campos' (topo) que abre o
                  TituloEditSheet (editor completo, mais campos que o painel inline antigo). */}
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
              <FileText className="h-3.5 w-3.5 mr-1" /> Imprimir período (folha jurídica)
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
                <span className={l.kind === 'receivable' ? 'text-success mr-2' : 'text-muted-foreground mr-2'}>{l.kind === 'receivable' ? '↑' : '↓'}</span>
                {l.descricao} <span className="ml-auto text-muted-foreground tabular-nums">{brl(l.valor)}</span>
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
                  {totalIn > 0 && <><span className="text-success"><b>+{brl(totalIn)}</b></span>{totalOut > 0 && <span className="fin-footer-sep">·</span>}</>}
                  {totalOut > 0 && <span className="text-foreground"><b>−{brl(totalOut)}</b></span>}
                </span>
              );
            })()}
            <span className="spacer" />
            {/* US-FIN-031: baixa em lote via endpoint bulk (1 request, ownership
                Tier 0 de todos os ids + audit) — substitui o loop de N POSTs. */}
            <Button
              size="sm"
              variant="default"
              className="h-7 px-3 text-[12px]"
              onClick={() => submitBulk('baixar')}
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
            {/* US-FIN-031: plano de contas + cancelar + exportar CSV da seleção */}
            <Button
              size="sm"
              variant="outline"
              className="h-7 px-3 text-[12px]"
              onClick={() => setBulkPlanoOpen(true)}
            >
              Plano lote
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-7 px-3 text-[12px] text-destructive border-destructive/40 hover:bg-destructive/5"
              onClick={() => setBulkCancelOpen(true)}
            >
              Cancelar lote
            </Button>
            <Button
              size="sm"
              variant="outline"
              className="h-7 px-3 text-[12px]"
              onClick={() => { void exportBulkCsv(); }}
            >
              Exportar CSV
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
            <div className="text-sm text-muted-foreground">
              Selecione a categoria a aplicar aos <b>{selectedRows.size}</b> lançamento{selectedRows.size === 1 ? '' : 's'} selecionado{selectedRows.size === 1 ? '' : 's'}:
            </div>
            <Select
              value={bulkCategoriaId === null ? '__none__' : String(bulkCategoriaId)}
              onValueChange={(v) => setBulkCategoriaId(v === '__none__' ? null : parseInt(v, 10))}
            >
              <SelectTrigger className="w-full" aria-label="Categoria">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">— escolher categoria —</SelectItem>
                {categorias.map((c) => (
                  <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <div className="flex items-center gap-2 pt-2 border-t border-border">
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

      {/* US-FIN-031: Sheet pra plano de contas em lote (mesmo padrão da categoria). */}
      <Sheet open={bulkPlanoOpen} onOpenChange={(o) => !o && setBulkPlanoOpen(false)}>
        <SheetContent side="right" className="fin-cowork w-[440px] sm:max-w-[440px]">
          <SheetHeader>
            <SheetTitle>Plano de contas em lote</SheetTitle>
          </SheetHeader>
          <div className="px-1 py-4 space-y-4">
            <div className="text-sm text-muted-foreground">
              Selecione o plano de contas a aplicar aos <b>{selectedRows.size}</b> lançamento{selectedRows.size === 1 ? '' : 's'} selecionado{selectedRows.size === 1 ? '' : 's'}:
            </div>
            <Select
              value={bulkPlanoId === null ? '__none__' : String(bulkPlanoId)}
              onValueChange={(v) => setBulkPlanoId(v === '__none__' ? null : parseInt(v, 10))}
            >
              <SelectTrigger className="w-full" aria-label="Plano de contas">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">— escolher plano de contas —</SelectItem>
                {(planosConta ?? []).filter((p) => p.id).map((p) => (
                  <SelectItem key={p.id} value={String(p.id)}>{p.codigo} · {p.nome}</SelectItem>
                ))}
              </SelectContent>
            </Select>
            <Inline gap={2} className="pt-2 border-t border-border">
              <Button
                size="sm"
                disabled={!bulkPlanoId || selectedRows.size === 0}
                onClick={submitBulkPlano}
              >
                Aplicar ({selectedRows.size})
              </Button>
              <Button size="sm" variant="outline" onClick={() => setBulkPlanoOpen(false)}>
                Cancelar
              </Button>
            </Inline>
          </div>
        </SheetContent>
      </Sheet>

      {/* US-FIN-031: confirmação DESTRUTIVA do cancelar em lote — apresenta N +
          total R$ ANTES de aplicar (REGRA MESTRE valor). Append-only no backend
          (status='cancelado', nunca delete); quitados são pulados. */}
      <Sheet open={bulkCancelOpen} onOpenChange={(o) => !o && setBulkCancelOpen(false)}>
        <SheetContent side="right" className="fin-cowork w-[440px] sm:max-w-[440px]">
          <SheetHeader>
            <SheetTitle>Cancelar títulos em lote</SheetTitle>
          </SheetHeader>
          {(() => {
            const selecionados = lancamentos.filter((l) => selectedRows.has(l.id));
            const elegiveis = selecionados.filter((l) => l.status !== 'recebido' && l.status !== 'pago');
            const totalCancel = elegiveis.reduce((s, l) => s + (l.valor_aberto ?? l.valor ?? 0), 0);
            const pulados = selecionados.length - elegiveis.length;
            return (
              <div className="px-1 py-4 space-y-4">
                <div className="text-sm text-foreground">
                  Você está cancelando <b>{elegiveis.length}</b> título{elegiveis.length === 1 ? '' : 's'} totalizando <b>{brl(totalCancel)}</b>.
                </div>
                {pulados > 0 && (
                  <div className="text-[12px] text-muted-foreground">
                    {pulados} selecionado{pulados === 1 ? '' : 's'} já liquidado{pulados === 1 ? '' : 's'} — será{pulados === 1 ? '' : 'ão'} pulado{pulados === 1 ? '' : 's'} (estorno é outro fluxo).
                  </div>
                )}
                <div className="text-[12px] text-muted-foreground">
                  O cancelamento é registrado como status (append-only) — o título sai da lista e dos KPIs, e fica visível no filtro Arquivados.
                </div>
                <Inline gap={2} className="pt-2 border-t border-border">
                  <Button
                    size="sm"
                    variant="destructive"
                    disabled={elegiveis.length === 0}
                    onClick={() => submitBulk('cancelar', {}, () => setBulkCancelOpen(false))}
                  >
                    Cancelar {elegiveis.length} título{elegiveis.length === 1 ? '' : 's'}
                  </Button>
                  <Button size="sm" variant="outline" onClick={() => setBulkCancelOpen(false)}>
                    Voltar
                  </Button>
                </Inline>
              </div>
            );
          })()}
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
          planos={planosConta}
          contas={contas}
        />
      )}

      {/* 2026-06-03 — Diálogo de baixa (escolher valor/conta/forma/plano ao receber/pagar) */}
      {(() => {
        const baixaLanc = baixaId !== null ? (lancamentos.find((l) => l.id === baixaId) ?? null) : null;
        return baixaLanc ? (
          <FinBaixaSheet
            open={true}
            onClose={() => setBaixaId(null)}
            lancamento={{
              id: baixaLanc.id,
              kind: baixaLanc.kind,
              contraparte: baixaLanc.contraparte,
              valor: baixaLanc.valor,
              valor_aberto: baixaLanc.valor_aberto,
              plano_conta_id: baixaLanc.plano_conta_id,
              // Fila P6 (inventário 2026-07-07): forma PREVISTA pré-seleciona a baixa
              // (protótipo financeiro-ops.jsx:69-75). Realizada não conta — título
              // aberto ainda não tem baixa; o guard é só honestidade do dado.
              forma_pagamento: baixaLanc.forma_pagamento_realizada ? null : baixaLanc.forma_pagamento,
            }}
            contas={contas}
            planos={planosConta}
          />
        ) : null;
      })()}

      {/* Onda 25 (2026-05-25) US-FIN-021 — Sheet Insert manual (substitui stub /unificado/novo) */}
      {createTipo && (
        <TituloCreateSheet
          open={createTipo !== null}
          onClose={() => setCreateTipo(null)}
          tipo={createTipo}
          categorias={categorias}
          planos={planosConta}
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
