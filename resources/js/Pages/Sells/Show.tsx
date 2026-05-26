// Wave 1 W1-A — MWART /sells/{id} (Detalhar venda).
// Refs: ADR 0104 (MWART canon), ADR 0149 (screen-pattern reuse Index/SaleSheet),
//       ADR 0107 (visual gate), ADR 0143 (FSM Pipeline LIVE), ADR 0093 (multi-tenant Tier 0).
//
// Layout 2 cols (8/4): esquerda headline + linhas + pagamentos + atividades;
//                       direita FSM action panel + timeline + ações.
// Detail vem DEFERRED (Inertia::defer no controller — RUNBOOK-inertia-defer-pattern).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import {
  ArrowLeft,
  CreditCard,
  Edit,
  Mail,
  MapPin,
  Package,
  Phone,
  Printer,
  User as UserIcon,
} from 'lucide-react';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { printSaleReceipt, type PrintSaleMode } from '@/Lib/printSaleReceipt';
import VdNextActionPanel from './_components/VdNextActionPanel';
import FsmActionPanel from './_components/FsmActionPanel';
import VdNfeEmitModal, { type NfeEmitVenda } from './_components/VdNfeEmitModal';
import VdNfseEmitModal, { type NfseEmitVenda } from './_components/VdNfseEmitModal';
import SaleReciboPrint80mm from './_components/SaleReciboPrint80mm';
import SaleOrcamentoA4 from './_components/SaleOrcamentoA4';
import SellsCheatSheet, { SELLS_SHOW_SHORTCUTS } from './_components/SellsCheatSheet';
import SaleTimeline from './_components/SaleTimeline';

// Modos de impressão KB-9.75 Cowork bundle 2026-05-26 P2 gaps #8 + #9
type CoworkPrintMode = 'recibo-80mm' | 'orcamento-a4' | null;

interface Customer {
  id: number;
  name: string;
  mobile: string | null;
  email: string | null;
}

interface Headline {
  id: number;
  invoice_no: string;
  transaction_date: string;
  final_total: number;
  total_paid: number;
  payment_status: 'paid' | 'due' | 'partial' | string;
  status: 'final' | 'draft' | 'quotation' | 'proforma' | string;
  current_stage_key: string | null;
  customer: Customer | null;
  location: { id: number; name: string } | null;
}

interface SaleLine {
  id: number;
  product_name: string;
  product_sku: string;
  quantity: number;
  unit_price: number;
  discount: number;
  subtotal: number;
  tax_amount: number;
  unit: string;
}

interface SalePayment {
  id: number;
  amount: number;
  method: string;
  paid_on: string | null;
  note: string | null;
}

interface Activity {
  description: string;
  causer_name: string;
  created_at: string;
}

interface ShowDetail {
  lines: SaleLine[];
  payments: SalePayment[];
  taxes: { order_taxes: Record<string, number>; line_taxes: Record<string, number> };
  activities: Activity[];
  shipping: { details: string; address: string; cost: number; status: string | null };
  notes: string | null;
  sub_type: string | null;
  sales_orders: string[];
  statuses: Record<string, string>;
  shipping_statuses: Record<string, string>;
  is_warranty_enabled: boolean;
}

export interface SellsShowPageProps {
  saleId: number;
  headline: Headline;
  detail?: ShowDetail;  // deferred
  permissions: { edit: boolean; delete: boolean; print: boolean };
  urls: { edit: string; print: string; sheet_data: string; back: string };
}

const PAYMENT_STATUS_LABEL: Record<string, string> = {
  paid: 'Pago',
  due: 'A receber',
  partial: 'Parcial',
};

const PAYMENT_STATUS_TONE: Record<string, string> = {
  paid: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300',
  due: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300',
  partial: 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-300',
};

const PAYMENT_METHOD_LABEL: Record<string, string> = {
  cash: 'Dinheiro',
  card: 'Cartão',
  bank_transfer: 'Transferência',
  custom_pay_1: 'PIX',
  custom_pay_2: 'Boleto',
};

function formatBRL(value: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function formatDateTime(input: string): string {
  if (!input) return '';
  const d = new Date(input);
  if (Number.isNaN(d.getTime())) return input;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
}

function DetailSkeleton() {
  return (
    <div className="space-y-4">
      <div className="h-32 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-48 bg-muted/40 rounded-lg animate-pulse" />
      <div className="h-32 bg-muted/40 rounded-lg animate-pulse" />
    </div>
  );
}

export default function SellsShow(props: SellsShowPageProps) {
  const { headline, urls, permissions } = props;
  const [isPrinting, setIsPrinting] = useState(false);
  // KB-9.75 P0 gaps #2/#3 — emit modals abertos via VdNextActionPanel gate.
  const [emitModalKind, setEmitModalKind] = useState<'nfe' | 'nfse' | null>(null);
  // KB-9.75 P2 gaps #8/#9 — printMode controla render dos overlays Cowork (Recibo 80mm + Orçamento A4)
  const [printMode, setPrintMode] = useState<CoworkPrintMode>(null);
  // KB-9.75 P3 gap #12 — cheat-sheet overlay '?' (Cowork bundle 2026-05-26).
  const [cheatOpen, setCheatOpen] = useState(false);
  // P4 parking lot #11 — refresh trigger pro SaleTimeline unified
  // (incrementa quando VdNextActionPanel/Emit* dispatcham CustomEvents).
  const [timelineRefreshKey, setTimelineRefreshKey] = useState(0);

  useEffect(() => {
    const bump = () => setTimelineRefreshKey((k) => k + 1);
    const events = [
      'oimpresso:venda-invoiced',
      'oimpresso:venda-paid',
      'oimpresso:venda-emitted-nfe',
      'oimpresso:venda-emitted-nfse',
    ];
    events.forEach((ev) => window.addEventListener(ev, bump));
    return () => {
      events.forEach((ev) => window.removeEventListener(ev, bump));
    };
  }, []);

  // /sells/{id}/print só responde a AJAX (SellPosController::printInvoice).
  // Render via iframe oculto + CSS legacy (Bootstrap 3 + .print_section) — pattern equivale
  // ao legacy public/js/app.js:1656 (a.print-invoice → __print_receipt). 3 modos do legacy
  // sale_pos/show.blade.php:413/416 (invoice / packing_slip / delivery_note).
  const handlePrint = useCallback(
    async (mode: PrintSaleMode = 'invoice') => {
      if (!permissions.print || isPrinting) return;
      setIsPrinting(true);
      try {
        await printSaleReceipt({ printUrl: urls.print, invoiceNo: headline.invoice_no, mode });
      } catch (err) {
        console.error('Falha ao imprimir venda', err);
        window.alert(err instanceof Error ? err.message : 'Erro ao gerar o recibo.');
      } finally {
        setIsPrinting(false);
      }
    },
    [headline.invoice_no, isPrinting, permissions.print, urls.print],
  );

  // KB-9.75 Cowork P2 gaps #8/#9 — abrir overlay print-only (não usa rota /sells/{id}/print legacy)
  const handleCoworkPrint = useCallback((mode: Exclude<CoworkPrintMode, null>) => {
    if (!permissions.print) return;
    setPrintMode(mode);
  }, [permissions.print]);

  const handleCoworkPrintClose = useCallback(() => setPrintMode(null), []);

  // Atalhos teclado E (edit) + P (print) + ? (cheat-sheet KB-9.75 gap #12) + Esc (back/close).
  // Quando cheatOpen, todos os outros atalhos ficam suprimidos — o próprio
  // SellsCheatSheet possui seu listener pra Esc/? (fecha o overlay).
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (
        e.target instanceof HTMLInputElement ||
        e.target instanceof HTMLTextAreaElement
      ) {
        return;
      }
      // Suprime atalhos enquanto cheat-sheet aberta — o overlay tem listener próprio.
      if (cheatOpen) return;
      if (e.key === '?') {
        e.preventDefault();
        setCheatOpen(true);
        return;
      }
      if (e.key === 'e' && permissions.edit) {
        e.preventDefault();
        router.visit(urls.edit);
      }
      if (e.key === 'p' && permissions.print) {
        e.preventDefault();
        handlePrint('invoice');
      }
      if (e.key === 'Escape') {
        e.preventDefault();
        router.visit(urls.back);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [cheatOpen, permissions.edit, permissions.print, urls.edit, urls.back, handlePrint]);

  const totalFalta = Math.max(0, headline.final_total - headline.total_paid);
  const paymentStatusLabel = PAYMENT_STATUS_LABEL[headline.payment_status] ?? headline.payment_status;
  const paymentStatusTone = PAYMENT_STATUS_TONE[headline.payment_status] ?? 'bg-muted/50 text-muted-foreground border-border';

  return (
    <>
      <Head title={`Venda #${headline.invoice_no}`} />

      {/* US-SELL-SHOW-COWORK marker — wrapper scoped pra CSS family sells-cowork (KB-9.75).
          NÃO altera funcionalidade nem props. Apenas habilita tokens visuais oklch/IBM Plex
          via resources/css/sells-cowork-show.css. Charter Show.charter.md preservado. */}
      <div className="sells-cowork-show container mx-auto px-6 py-6 space-y-6">
        {/* Header */}
        <div className="flex items-start justify-between gap-4 flex-wrap">
          <div className="flex items-start gap-3">
            <Button variant="ghost" size="icon" asChild aria-label="Voltar">
              <Link href={urls.back}>
                <ArrowLeft className="h-4 w-4" />
              </Link>
            </Button>
            <div>
              <h1 className="text-2xl font-semibold tracking-tight">
                Venda #{headline.invoice_no}
              </h1>
              <p className="text-sm text-muted-foreground mt-1">
                {formatDateTime(headline.transaction_date)}
                {headline.location ? ` · ${headline.location.name}` : ''}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {permissions.print && (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm" disabled={isPrinting}>
                    <Printer className="h-4 w-4 mr-2" />
                    {isPrinting ? 'Gerando…' : 'Imprimir'}
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onSelect={() => handlePrint('invoice')}>
                    Recibo / fatura (P)
                  </DropdownMenuItem>
                  <DropdownMenuItem onSelect={() => handlePrint('packing_slip')}>
                    Romaneio / packing slip
                  </DropdownMenuItem>
                  <DropdownMenuItem onSelect={() => handlePrint('delivery_note')}>
                    Nota de entrega
                  </DropdownMenuItem>
                  <DropdownMenuItem onSelect={() => handleCoworkPrint('recibo-80mm')}>
                    Recibo térmico (80mm)
                  </DropdownMenuItem>
                  <DropdownMenuItem onSelect={() => handleCoworkPrint('orcamento-a4')}>
                    Orçamento A4
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            )}
            {permissions.edit && (
              <Button variant="default" size="sm" asChild>
                <Link href={urls.edit}>
                  <Edit className="h-4 w-4 mr-2" />
                  Editar
                </Link>
              </Button>
            )}
          </div>
        </div>

        {/* KPIs grandes (4-col canon V2) */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <KpiCard
            label="Total"
            value={formatBRL(headline.final_total)}
            icon="receipt"
            tone="default"
          />
          <KpiCard
            label="Pago"
            value={formatBRL(headline.total_paid)}
            icon="credit-card"
            tone="success"
          />
          <KpiCard
            label="Falta"
            value={formatBRL(totalFalta)}
            icon="file-text"
            tone={totalFalta > 0 ? 'warning' : 'default'}
          />
          <div className="rounded-xl border border-border bg-card p-4 flex flex-col gap-2 shadow-sm">
            <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-widest">
              Status pgto
            </span>
            <span
              className={`inline-flex items-center px-2.5 py-1 rounded-md text-sm font-medium border self-start ${paymentStatusTone}`}
            >
              {paymentStatusLabel}
            </span>
          </div>
        </div>

        {/* Layout 2-col: 8/4 */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* COLUNA ESQUERDA — headline + linhas + pagamentos + activities */}
          <div className="lg:col-span-8 space-y-6">
            {/* Cliente */}
            {headline.customer && (
              <section className="rounded-lg border border-border bg-card p-5">
                <div className="flex items-center gap-2 mb-3">
                  <UserIcon className="h-4 w-4 text-muted-foreground" />
                  <h2 className="font-semibold text-sm">Cliente</h2>
                </div>
                <div className="space-y-1">
                  <p className="font-medium text-base">{headline.customer.name}</p>
                  {headline.customer.mobile && (
                    <p className="text-sm text-muted-foreground flex items-center gap-2">
                      <Phone className="h-3 w-3" />
                      {headline.customer.mobile}
                    </p>
                  )}
                  {headline.customer.email && (
                    <p className="text-sm text-muted-foreground flex items-center gap-2">
                      <Mail className="h-3 w-3" />
                      {headline.customer.email}
                    </p>
                  )}
                </div>
              </section>
            )}

            {/* Linhas + pagamentos + atividades — deferred */}
            <Deferred data="detail" fallback={<DetailSkeleton />}>
              <ShowDetailSections
                detail={props.detail}
                headline={headline}
                timelineRefreshKey={timelineRefreshKey}
              />
            </Deferred>
          </div>

          {/* COLUNA DIREITA — Próxima ação hero + Pipeline FSM completo + Atalhos */}
          <aside className="lg:col-span-4 space-y-4">
            {/* VdNextActionPanel — KB-9.75 Cowork 2026-05-26 P0 gap #1.
                Próxima ação contextual + gates fiscais ("emita NF antes de faturar").
                Glossário BR corrigido: Faturar (emit NF + título) ≠ Receber (baixa título).
                Guard removido (fix 2026-05-26): VdNextActionPanel já tem proprio early-return
                via fetch /api/sells/{id}/fsm-actions → data.in_pipeline. Headline.current_stage_key
                pode vir null mesmo quando backend pipeline FSM está ativo (current_stage_id
                derivado, não eager-loaded — pre-existing inconsistência). */}
            <VdNextActionPanel
              saleId={headline.id}
              paymentStatus={headline.payment_status}
              currentStageKey={headline.current_stage_key}
              onTransition={() => {
                // Refresh sheet — Inertia partial reload do detail
                router.reload({ only: ['detail', 'headline'] });
              }}
              onOpenEmit={(kind) => setEmitModalKind(kind)}
            />

            {/* Pipeline — todas transições disponíveis (FsmActionPanel completo).
                Mesmo guard removido — componente decide via /api/sells/{id}/fsm-actions. */}
            <section className="rounded-lg border border-border bg-card p-4">
              <h2 className="font-semibold text-sm mb-3">Todas as transições</h2>
              <FsmActionPanel
                saleId={headline.id}
                enabled={true}
                onTransition={() => {
                  router.reload({ only: ['detail', 'headline'] });
                }}
              />
            </section>

            {/* Atalhos hint */}
            <section className="rounded-lg border border-border bg-card p-4">
              <h2 className="font-semibold text-sm mb-2">Atalhos</h2>
              <div className="space-y-1.5 text-xs text-muted-foreground">
                {permissions.edit && <div><kbd className="px-1.5 py-0.5 bg-muted rounded">E</kbd> Editar</div>}
                {permissions.print && <div><kbd className="px-1.5 py-0.5 bg-muted rounded">P</kbd> Imprimir</div>}
                <div><kbd className="px-1.5 py-0.5 bg-muted rounded">Esc</kbd> Voltar</div>
              </div>
            </section>
          </aside>
        </div>

        {/* KB-9.75 P3 gap #12 — cheat-sheet overlay '?' (Cowork bundle 2026-05-26).
            Lista canon SELLS_SHOW_SHORTCUTS exporta os atalhos da tela Detalhe. */}
        <SellsCheatSheet
          open={cheatOpen}
          onClose={() => setCheatOpen(false)}
          shortcuts={SELLS_SHOW_SHORTCUTS}
          title="Atalhos · Detalhe da venda"
          footerLeft="Vendas opera sem mouse — atalhos persistem em Lista, Caixa, Detalhe e Edição."
        />
      </div>

      {/* KB-9.75 P0 gaps #2/#3 — emit modals abertos pelo gate fiscal do VdNextActionPanel.
          Stub UI 3-step (review → preview XML → mock SEFAZ/Prefeitura). Backend real
          (Modules/NfeBrasil + Modules/NfseBrasil) wire-up no próximo PR. */}
      <VdNfeEmitModal
        open={emitModalKind === 'nfe'}
        venda={
          emitModalKind === 'nfe'
            ? {
                id: headline.id,
                invoice_no: headline.invoice_no,
                customer_name: headline.customer?.name ?? null,
                customer_doc: null,
                itemsList: (props.detail?.lines ?? []).map((l): {
                  id: number;
                  produto: string;
                  ncm?: string;
                  cfop?: string;
                  cst?: string;
                  qtd: number;
                  unit: number;
                  subtotal: number;
                } => ({
                  id: l.id,
                  produto: l.product_name,
                  ncm: '00000000',
                  cfop: '5102',
                  cst: '00',
                  qtd: l.quantity,
                  unit: l.unit_price,
                  subtotal: l.subtotal,
                })),
                total: headline.final_total,
              } satisfies NfeEmitVenda
            : null
        }
        onClose={() => setEmitModalKind(null)}
        onSuccess={() => router.reload({ only: ['detail', 'headline'] })}
      />
      <VdNfseEmitModal
        open={emitModalKind === 'nfse'}
        venda={
          emitModalKind === 'nfse'
            ? {
                id: headline.id,
                invoice_no: headline.invoice_no,
                customer_name: headline.customer?.name ?? null,
                customer_doc: null,
                itemsList: (props.detail?.lines ?? []).map((l) => ({
                  id: l.id,
                  servico: l.product_name,
                  codigoServico: '1.01',
                  aliquotaIss: 5,
                  qtd: l.quantity,
                  unit: l.unit_price,
                  subtotal: l.subtotal,
                })),
                total: headline.final_total,
              } satisfies NfseEmitVenda
            : null
        }
        onClose={() => setEmitModalKind(null)}
        onSuccess={() => router.reload({ only: ['detail', 'headline'] })}
      />

      {/* KB-9.75 Cowork P2 gaps #8/#9 — overlays print-only.
          Wrapper `sells-cowork` aplica os tokens CSS canon (`.sells-cowork .vd-receipt-paper`).
          Renderizam só quando user seleciona no dropdown "Imprimir" E detail deferred já chegou. */}
      {printMode === 'recibo-80mm' && props.detail && (
        <div className="sells-cowork">
          <SaleReciboPrint80mm
            headline={headline}
            detail={{ lines: props.detail.lines, payments: props.detail.payments }}
            onClose={handleCoworkPrintClose}
          />
        </div>
      )}
      {printMode === 'orcamento-a4' && props.detail && (
        <div className="sells-cowork">
          <SaleOrcamentoA4
            headline={headline}
            detail={{ lines: props.detail.lines }}
            onClose={handleCoworkPrintClose}
          />
        </div>
      )}
    </>
  );
}

interface ShowDetailSectionsProps {
  detail?: ShowDetail;
  headline: Headline;
  /** P4 parking lot #11 — bump pro SaleTimeline unified re-fetch. */
  timelineRefreshKey?: number;
}

function ShowDetailSections({ detail, headline, timelineRefreshKey = 0 }: ShowDetailSectionsProps) {
  if (!detail) return <DetailSkeleton />;

  return (
    <>
      {/* Linhas da venda */}
      <section className="rounded-lg border border-border bg-card overflow-hidden">
        <div className="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
          <Package className="h-4 w-4 text-muted-foreground" />
          <h2 className="font-semibold text-sm">Itens da venda</h2>
          <span className="ml-auto text-xs text-muted-foreground">{detail.lines.length} item(s)</span>
        </div>
        {detail.lines.length === 0 ? (
          <EmptyState
            icon="package"
            title="Nenhum item"
            description="Esta venda não tem produtos."
          />
        ) : (
          <table className="w-full text-sm">
            <thead className="text-xs text-muted-foreground uppercase tracking-wide">
              <tr className="border-b border-border">
                <th className="text-left px-5 py-2 font-medium">Produto</th>
                <th className="text-right px-3 py-2 font-medium">Qtd</th>
                <th className="text-right px-3 py-2 font-medium">Unit</th>
                <th className="text-right px-3 py-2 font-medium">Desc.</th>
                <th className="text-right px-5 py-2 font-medium">Subtotal</th>
              </tr>
            </thead>
            <tbody>
              {detail.lines.map((line, idx) => (
                <tr
                  key={line.id}
                  className={idx % 2 === 0 ? 'bg-transparent' : 'bg-muted/20'}
                >
                  <td className="px-5 py-3">
                    <div className="font-medium">{line.product_name}</div>
                    {line.product_sku && (
                      <div className="text-xs text-muted-foreground">{line.product_sku}</div>
                    )}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    {line.quantity} {line.unit}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums">
                    {formatBRL(line.unit_price)}
                  </td>
                  <td className="px-3 py-3 text-right tabular-nums text-muted-foreground">
                    {line.discount > 0 ? formatBRL(line.discount) : '—'}
                  </td>
                  <td className="px-5 py-3 text-right tabular-nums font-semibold">
                    {formatBRL(line.subtotal)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>

      {/* Pagamentos */}
      <section className="rounded-lg border border-border bg-card overflow-hidden">
        <div className="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
          <CreditCard className="h-4 w-4 text-muted-foreground" />
          <h2 className="font-semibold text-sm">Pagamentos</h2>
          <span className="ml-auto text-xs text-muted-foreground">{detail.payments.length} lançamento(s)</span>
        </div>
        {detail.payments.length === 0 ? (
          <EmptyState
            icon="credit-card"
            title="Nenhum pagamento registrado"
            description="Venda à vista zerada ou a receber."
          />
        ) : (
          <div className="divide-y divide-border">
            {detail.payments.map((p) => (
              <div key={p.id} className="flex items-center justify-between px-5 py-3">
                <div className="space-y-0.5">
                  <div className="font-medium text-sm">
                    {PAYMENT_METHOD_LABEL[p.method] ?? p.method}
                  </div>
                  {p.paid_on && (
                    <div className="text-xs text-muted-foreground">
                      {formatDateTime(p.paid_on)}
                    </div>
                  )}
                  {p.note && (
                    <div className="text-xs text-muted-foreground italic">{p.note}</div>
                  )}
                </div>
                <div className="text-right tabular-nums font-semibold">
                  {formatBRL(p.amount)}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      {/* Frete (se houver) */}
      {detail.shipping.cost > 0 && (
        <section className="rounded-lg border border-border bg-card p-5">
          <div className="flex items-center gap-2 mb-3">
            <MapPin className="h-4 w-4 text-muted-foreground" />
            <h2 className="font-semibold text-sm">Frete</h2>
          </div>
          <div className="space-y-1 text-sm">
            {detail.shipping.address && (
              <p className="text-muted-foreground">{detail.shipping.address}</p>
            )}
            {detail.shipping.details && (
              <p className="text-muted-foreground italic">{detail.shipping.details}</p>
            )}
            <p className="font-semibold tabular-nums mt-2">
              Custo: {formatBRL(detail.shipping.cost)}
            </p>
          </div>
        </section>
      )}

      {/* Histórico unificado — P4 parking lot #11 (FSM + payments + activities
          + comments + audit num único stream cronológico reverso). */}
      <section className="rounded-lg border border-border bg-card overflow-hidden sells-cowork-show">
        <div className="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
          <h2 className="font-semibold text-sm">Histórico</h2>
          <span className="ml-auto text-xs text-muted-foreground">
            Todos os eventos (FSM, pagamentos, atividades)
          </span>
        </div>
        <div className="px-5 py-4">
          <SaleTimeline
            saleId={headline.id}
            enabled
            mode="unified"
            refreshKey={timelineRefreshKey}
          />
        </div>
      </section>
    </>
  );
}

SellsShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
