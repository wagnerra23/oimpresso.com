// US-PROD-004 — Histórico de Estoque Inertia/React.
// Migração `product.stock_history.blade.php` + `stock_history_details.blade.php`.
// Timeline cronológica todas movimentações estoque por variation/location.
//
// Refs: ADR 0104, ADR 0110, ADR 0093, RUNBOOK-products.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useEffect, useMemo, useState, type ReactNode } from 'react';
import {
  ArrowDown,
  ArrowLeft,
  ArrowUp,
  Calendar,
  History,
  Loader2,
  MapPin,
  Package,
  ShoppingCart,
  Truck,
  Wrench,
} from 'lucide-react';

interface VariationStub {
  id: number;
  name: string;
  sub_sku: string;
}

interface ProductData {
  id: number;
  name: string;
  sku: string | null;
  type: string;
  variations: VariationStub[];
}

interface LocationOption {
  [id: number]: string;
}

export interface StockHistoryPageProps {
  product: ProductData;
  locations: LocationOption;
}

// Linhas do backend legacy (stock_history_details parcial Blade — vamos ler via fetch text + parse seletivo).
// Como o backend retorna HTML Blade pro AJAX legacy, melhor consumir um endpoint JSON simples.
// Fallback: timeline renderiza pela view legacy se fetch falhar.
//
// Pra MVP usa o mesmo endpoint /products/stock-history/{variation_id} ?ajax=1&location_id=X
// e parse manual do HTML retornado, OU melhor: criar timeline com transactions diretamente.
//
// Decisão: backend já existe e retorna JSON-friendly via getVariationStockHistory.
// Por agora renderiza simples timeline com filtro de variation + location.
// Próximo PR (US-PROD-005) — endpoint JSON dedicado /products/{id}/stock-timeline-json.

interface HistoryRow {
  transaction_id: number;
  transaction_type: 'sell' | 'purchase' | 'stock_adjustment' | 'opening_stock' | 'sell_transfer' | 'purchase_transfer' | 'sell_return' | 'purchase_return' | string;
  transaction_date: string;
  invoice_no: string | null;
  ref_no: string | null;
  qty_change: number;
  stock_after: number;
  contact_name: string | null;
  status: string;
}

function formatNumber(raw: string | number | null | undefined): string {
  if (raw === null || raw === undefined || raw === '') return '0';
  const n = typeof raw === 'string' ? parseFloat(raw) : raw;
  if (!isFinite(n)) return '0';
  return n.toLocaleString('pt-BR', { maximumFractionDigits: 3, minimumFractionDigits: 0 });
}

function formatDate(raw: string | null): string {
  if (!raw) return '—';
  try {
    const d = new Date(raw);
    if (isNaN(d.getTime())) return raw;
    return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  } catch (_) {
    return raw;
  }
}

function formatDateTime(raw: string | null): string {
  if (!raw) return '—';
  try {
    const d = new Date(raw);
    if (isNaN(d.getTime())) return raw;
    return d.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch (_) {
    return raw;
  }
}

const TYPE_META: Record<string, { label: string; icon: typeof Package; tone: 'rose' | 'emerald' | 'amber' | 'blue'; direction: 'in' | 'out' | 'adj' }> = {
  purchase: { label: 'Compra', icon: ShoppingCart, tone: 'emerald', direction: 'in' },
  sell: { label: 'Venda', icon: Truck, tone: 'rose', direction: 'out' },
  stock_adjustment: { label: 'Ajuste', icon: Wrench, tone: 'amber', direction: 'adj' },
  opening_stock: { label: 'Estoque inicial', icon: Package, tone: 'blue', direction: 'in' },
  sell_transfer: { label: 'Transferência (saída)', icon: ArrowUp, tone: 'amber', direction: 'out' },
  purchase_transfer: { label: 'Transferência (entrada)', icon: ArrowDown, tone: 'emerald', direction: 'in' },
  sell_return: { label: 'Devolução de venda', icon: ArrowDown, tone: 'emerald', direction: 'in' },
  purchase_return: { label: 'Devolução de compra', icon: ArrowUp, tone: 'rose', direction: 'out' },
};

export default function ProductsStockHistory(props: StockHistoryPageProps) {
  const { product, locations } = props;

  const locationEntries = Object.entries(locations ?? {}) as Array<[string, string]>;
  const [selectedLocationId, setSelectedLocationId] = useState<string>(() => locationEntries[0]?.[0] ?? '');
  const [selectedVariationId, setSelectedVariationId] = useState<number>(() => product.variations[0]?.id ?? 0);
  const [periodFilter, setPeriodFilter] = useState<'all' | '7d' | '30d' | '90d'>('30d');
  const [directionFilter, setDirectionFilter] = useState<'all' | 'in' | 'out' | 'adj'>('all');
  const [loading, setLoading] = useState(false);
  const [rows, setRows] = useState<HistoryRow[]>([]);
  const [error, setError] = useState<string | null>(null);

  // Fetch — chama endpoint legacy parcial Blade (HTML) ou idealmente endpoint JSON dedicado.
  // Por enquanto, parser tolerante: o legacy retorna table HTML que conseguimos ler via DOMParser.
  useEffect(() => {
    if (!selectedVariationId || !selectedLocationId) {
      setRows([]);
      return;
    }
    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(`/products/stock-history/${selectedVariationId}?location_id=${selectedLocationId}`, {
      headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => r.text())
      .then((html) => {
        if (cancelled) return;
        // Parse a tabela retornada pelo legacy. Estrutura conhecida:
        // <table><tbody><tr><td>type</td><td>date</td><td>qty</td><td>stock</td>...</tr></tbody></table>
        // Como o legacy é coupled com Blade, aceitamos parsing best-effort.
        try {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const trs = doc.querySelectorAll('table tbody tr');
          const parsed: HistoryRow[] = [];
          trs.forEach((tr, idx) => {
            const tds = tr.querySelectorAll('td');
            if (tds.length < 4) return;
            const txt = (i: number) => tds[i]?.textContent?.trim() ?? '';
            // Detecta type pelos textos PT_BR no Blade legacy
            const typeText = txt(0).toLowerCase();
            let transaction_type = 'sell';
            if (typeText.includes('compra')) transaction_type = 'purchase';
            else if (typeText.includes('venda')) transaction_type = 'sell';
            else if (typeText.includes('ajuste')) transaction_type = 'stock_adjustment';
            else if (typeText.includes('abertura') || typeText.includes('inicial')) transaction_type = 'opening_stock';
            else if (typeText.includes('transf')) transaction_type = 'sell_transfer';
            const dateText = txt(1);
            const qtyText = txt(2).replace(/[^\d.,-]/g, '').replace(',', '.');
            const stockText = txt(3).replace(/[^\d.,-]/g, '').replace(',', '.');

            parsed.push({
              transaction_id: idx,
              transaction_type,
              transaction_date: dateText,
              invoice_no: null,
              ref_no: null,
              qty_change: parseFloat(qtyText) || 0,
              stock_after: parseFloat(stockText) || 0,
              contact_name: txt(4) || null,
              status: 'final',
            });
          });
          setRows(parsed);
        } catch (e) {
          setError('Falha ao processar histórico — recarregue a página.');
          setRows([]);
        }
      })
      .catch((e) => {
        if (cancelled) return;
        setError('Erro ao carregar histórico: ' + String((e as Error)?.message || e));
        setRows([]);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [selectedVariationId, selectedLocationId]);

  // Filtros aplicados no client
  const filteredRows = useMemo(() => {
    let r = rows.slice();

    if (periodFilter !== 'all') {
      const cutoff = new Date();
      const days = periodFilter === '7d' ? 7 : periodFilter === '30d' ? 30 : 90;
      cutoff.setDate(cutoff.getDate() - days);
      r = r.filter((row) => {
        const d = new Date(row.transaction_date);
        return isFinite(d.getTime()) ? d >= cutoff : true;
      });
    }

    if (directionFilter !== 'all') {
      r = r.filter((row) => {
        const meta = TYPE_META[row.transaction_type];
        return meta?.direction === directionFilter;
      });
    }

    return r;
  }, [rows, periodFilter, directionFilter]);

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      {/* Header */}
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-6xl">
          <div className="flex items-center gap-2 text-xs text-muted-foreground mb-2">
            <a href={`/products/${product.id}`} className="inline-flex items-center gap-1 hover:text-foreground">
              <ArrowLeft size={12} />
              {product.name}
            </a>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground flex items-center gap-3">
            <History size={22} className="text-muted-foreground" strokeWidth={1.5} />
            Histórico de estoque
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Todas as movimentações (entrada · saída · ajustes) ordenadas cronologicamente.
          </p>

          {/* Filtros */}
          <div className="mt-5 flex flex-wrap items-center gap-3">
            {product.variations.length > 1 && (
              <div className="flex items-center gap-2">
                <label className="text-xs text-muted-foreground">Variação:</label>
                <select
                  value={selectedVariationId}
                  onChange={(e) => setSelectedVariationId(Number(e.target.value))}
                  className="h-9 rounded border border-border bg-background px-3 text-xs"
                >
                  {product.variations.map((v) => (
                    <option key={v.id} value={v.id}>
                      {v.name === 'DUMMY' ? v.sub_sku : `${v.name} (${v.sub_sku})`}
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div className="flex items-center gap-2">
              <MapPin size={14} className="text-muted-foreground" />
              <select
                value={selectedLocationId}
                onChange={(e) => setSelectedLocationId(e.target.value)}
                className="h-9 rounded border border-border bg-background px-3 text-xs min-w-[160px]"
                aria-label="Local"
              >
                {locationEntries.map(([id, name]) => (
                  <option key={id} value={id}>
                    {name}
                  </option>
                ))}
              </select>
            </div>

            <div className="flex items-center gap-2">
              <Calendar size={14} className="text-muted-foreground" />
              <select
                value={periodFilter}
                onChange={(e) => setPeriodFilter(e.target.value as 'all' | '7d' | '30d' | '90d')}
                className="h-9 rounded border border-border bg-background px-3 text-xs"
                aria-label="Período"
              >
                <option value="all">Todo período</option>
                <option value="7d">Últimos 7 dias</option>
                <option value="30d">Últimos 30 dias</option>
                <option value="90d">Últimos 90 dias</option>
              </select>
            </div>

            {/* Pills de direção */}
            <nav className="flex items-center gap-1.5" aria-label="Tipo de movimentação">
              {(
                [
                  ['all', 'Todas'],
                  ['in', 'Entradas'],
                  ['out', 'Saídas'],
                  ['adj', 'Ajustes'],
                ] as const
              ).map(([key, label]) => {
                const isActive = directionFilter === key;
                return (
                  <button
                    key={key}
                    type="button"
                    onClick={() => setDirectionFilter(key)}
                    className={
                      'inline-flex items-center gap-1 rounded-full px-3 py-1 text-xs font-medium transition-colors ' +
                      (isActive
                        ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                        : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                    }
                    aria-current={isActive ? 'true' : undefined}
                  >
                    {label}
                  </button>
                );
              })}
            </nav>

            {loading && <Loader2 className="h-4 w-4 animate-spin text-muted-foreground" />}
          </div>
        </div>
      </div>

      {/* Timeline */}
      <div className="container mx-auto px-8 py-6 max-w-6xl">
        {error && (
          <div className="rounded-lg border border-rose-200 bg-rose-50 dark:bg-rose-950/20 dark:border-rose-900 px-4 py-3 text-sm text-rose-700 dark:text-rose-300 mb-4">
            {error}
          </div>
        )}

        <div className="rounded-lg border border-border bg-background overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="bg-muted/50">
                <tr className="border-b border-border">
                  <Th className="w-32">Data</Th>
                  <Th className="w-40">Tipo</Th>
                  <Th>Origem</Th>
                  <Th className="w-28 text-right">Qtd</Th>
                  <Th className="w-28 text-right pr-5">Saldo</Th>
                </tr>
              </thead>
              <tbody>
                {loading && rows.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="text-center py-12 text-muted-foreground text-xs">
                      Carregando histórico…
                    </td>
                  </tr>
                ) : filteredRows.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="text-center py-12 text-muted-foreground text-xs">
                      Nenhuma movimentação encontrada no período/filtros selecionados.
                    </td>
                  </tr>
                ) : (
                  filteredRows.map((row, idx) => {
                    const meta = TYPE_META[row.transaction_type] ?? {
                      label: row.transaction_type,
                      icon: Package,
                      tone: 'blue' as const,
                      direction: 'adj' as const,
                    };
                    const Icon = meta.icon;
                    const toneClass =
                      meta.tone === 'rose'
                        ? 'text-rose-700 dark:text-rose-300'
                        : meta.tone === 'emerald'
                          ? 'text-emerald-700 dark:text-emerald-300'
                          : meta.tone === 'amber'
                            ? 'text-amber-700 dark:text-amber-300'
                            : 'text-blue-700 dark:text-blue-300';
                    const qtySign = meta.direction === 'out' ? '−' : meta.direction === 'in' ? '+' : '';
                    return (
                      <tr key={idx} className="border-b border-border/60 hover:bg-muted/30">
                        <td className="px-4 py-3 text-xs text-foreground tabular-nums">
                          {formatDateTime(row.transaction_date)}
                        </td>
                        <td className="px-4 py-3">
                          <span className={'inline-flex items-center gap-1.5 text-xs font-medium ' + toneClass}>
                            <Icon size={13} />
                            {meta.label}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-xs">
                          {row.invoice_no && <span className="tabular-nums text-foreground">{row.invoice_no}</span>}
                          {row.contact_name && (
                            <div className="text-muted-foreground mt-0.5">{row.contact_name}</div>
                          )}
                          {!row.invoice_no && !row.contact_name && (
                            <span className="text-muted-foreground/60">—</span>
                          )}
                        </td>
                        <td className={'px-4 py-3 text-right tabular-nums text-xs font-medium ' + toneClass}>
                          {qtySign}
                          {formatNumber(Math.abs(row.qty_change))}
                        </td>
                        <td className="px-4 py-3 text-right pr-5 tabular-nums text-sm text-foreground font-medium">
                          {formatNumber(row.stock_after)}
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>

        {filteredRows.length > 0 && (
          <div className="text-xs text-muted-foreground mt-3 px-1">
            {filteredRows.length.toLocaleString('pt-BR')} movimentações
          </div>
        )}
      </div>
    </div>
  );
}

ProductsStockHistory.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function Th({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <th
      className={
        'text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground ' + className
      }
    >
      {children}
    </th>
  );
}
