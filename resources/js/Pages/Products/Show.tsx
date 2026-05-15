// US-PROD-003 — Detalhes do Produto Inertia/React.
// Migração `product.show.blade.php` (DataTables jQuery + Bootstrap rows).
// 4 KPIs + sections (Detalhes / Estoque por location / Histórico).
//
// Refs: ADR 0104, ADR 0110, ADR 0093, RUNBOOK-products.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import { type ReactNode } from 'react';
import {
  AlertTriangle,
  ArrowLeft,
  CheckCircle2,
  Edit,
  History,
  MapPin,
  Package,
  Power,
  ShoppingCart,
  Tag,
  TrendingDown,
  TrendingUp,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface Variation {
  id: number;
  name: string;
  sub_sku: string;
  sell_price_inc_tax: number | string;
  dpp_inc_tax: number | string;
}

interface ProductData {
  id: number;
  name: string;
  sku: string | null;
  type: 'single' | 'variable' | 'combo' | string;
  image_url: string | null;
  enable_stock: boolean;
  is_inactive: boolean;
  not_for_selling: boolean;
  alert_quantity: string | number | null;
  product_description: string | null;
  product_custom_field1: string | null;
  brand: string | null;
  unit: string | null;
  category: string | null;
  sub_category: string | null;
  variations: Variation[];
}

interface StockByLocationRow {
  location_id: number;
  location_name: string;
  qty: number | string;
}

export interface ProductsShowPageProps {
  product: ProductData;
  kpis: {
    total_stock: number;
    stock_value: number;
    last_purchase_at: string | null;
    last_sell_at: string | null;
  };
  stockByLocation: StockByLocationRow[];
  permissions: {
    update: boolean;
    delete: boolean;
  };
}

function formatNumber(raw: string | number | null | undefined): string {
  if (raw === null || raw === undefined || raw === '') return '0';
  const n = typeof raw === 'string' ? parseFloat(raw) : raw;
  if (!isFinite(n)) return '0';
  return n.toLocaleString('pt-BR', { maximumFractionDigits: 3, minimumFractionDigits: 0 });
}

function formatCurrency(raw: string | number | null | undefined): string {
  if (raw === null || raw === undefined || raw === '') return 'R$ 0,00';
  const n = typeof raw === 'string' ? parseFloat(raw) : raw;
  if (!isFinite(n)) return 'R$ 0,00';
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
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

export default function ProductsShow(props: ProductsShowPageProps) {
  const { product, kpis, stockByLocation, permissions } = props;
  const alertQty = product.alert_quantity !== null ? parseFloat(String(product.alert_quantity)) : null;
  const isInAlert = product.enable_stock && alertQty !== null && kpis.total_stock < alertQty;

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      {/* Header */}
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-start gap-4">
            <div className="flex-1 min-w-0">
              <div className="flex items-center gap-2 text-xs text-muted-foreground mb-2">
                <a href="/products" className="inline-flex items-center gap-1 hover:text-foreground">
                  <ArrowLeft size={12} />
                  Produtos
                </a>
              </div>
              <h1 className="text-2xl font-semibold tracking-tight text-foreground flex items-center gap-3 flex-wrap">
                {product.name}
                {product.is_inactive && (
                  <span className="inline-flex items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
                    <Power size={11} />
                    Inativo
                  </span>
                )}
                {product.not_for_selling && (
                  <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300">
                    Não vende
                  </span>
                )}
                {isInAlert && (
                  <span className="inline-flex items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700">
                    <AlertTriangle size={11} />
                    Estoque baixo
                  </span>
                )}
              </h1>
              <div className="text-sm text-muted-foreground mt-1 flex items-center gap-3 flex-wrap">
                {product.sku && <span>SKU {product.sku}</span>}
                {product.product_custom_field1 && <span>#{product.product_custom_field1}</span>}
                {product.brand && <span>· {product.brand}</span>}
                {product.category && <span>· {product.category}</span>}
              </div>
            </div>
            <div className="flex-shrink-0 flex items-center gap-2">
              {product.enable_stock && (
                <Button asChild variant="outline">
                  <a href={`/products/stock-history/${product.id}`}>
                    <History className="mr-1.5 h-4 w-4" />
                    Histórico estoque
                  </a>
                </Button>
              )}
              {permissions.update && (
                <Button asChild>
                  <a href={`/products/${product.id}/edit`}>
                    <Edit className="mr-1.5 h-4 w-4" />
                    Editar
                  </a>
                </Button>
              )}
            </div>
          </div>

          {/* 4 KPIs */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
            <KpiCard
              label="Estoque total"
              value={product.enable_stock ? formatNumber(kpis.total_stock) : '—'}
              suffix={product.enable_stock ? product.unit ?? '' : ''}
              icon={Package}
              tone={isInAlert ? 'amber' : undefined}
            />
            <KpiCard label="Valor estoque" value={formatCurrency(kpis.stock_value)} icon={TrendingUp} />
            <KpiCard label="Última compra" value={formatDate(kpis.last_purchase_at)} icon={TrendingDown} muted />
            <KpiCard label="Última venda" value={formatDate(kpis.last_sell_at)} icon={ShoppingCart} muted />
          </div>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-7xl grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Detalhes */}
        <section className="lg:col-span-2 rounded-xl border border-border bg-background shadow-sm">
          <header className="flex items-center gap-3 px-5 py-4 border-b border-border">
            <Tag size={18} className="text-muted-foreground" strokeWidth={1.5} />
            <h2 className="text-sm font-semibold text-foreground">Detalhes</h2>
          </header>
          <div className="px-5 py-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <Field label="Tipo" value={product.type === 'single' ? 'Simples' : product.type === 'variable' ? 'Variável' : product.type === 'combo' ? 'Combo' : product.type} />
            <Field label="Unidade" value={product.unit} />
            <Field label="Categoria" value={product.category} />
            <Field label="Subcategoria" value={product.sub_category} />
            <Field label="Marca" value={product.brand} />
            <Field
              label="Controla estoque"
              value={
                <span className="inline-flex items-center gap-1">
                  {product.enable_stock ? (
                    <>
                      <CheckCircle2 size={13} className="text-emerald-600" />
                      Sim
                    </>
                  ) : (
                    'Não'
                  )}
                </span>
              }
            />
            {product.enable_stock && (
              <Field
                label="Alerta abaixo de"
                value={product.alert_quantity !== null ? `${formatNumber(product.alert_quantity)} ${product.unit ?? ''}`.trim() : 'Sem alerta'}
              />
            )}
            {product.product_description && (
              <div className="md:col-span-2">
                <dt className="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-1">Descrição</dt>
                <dd className="text-sm text-foreground whitespace-pre-line">{product.product_description}</dd>
              </div>
            )}
          </div>

          {/* Variações */}
          {product.variations.length > 0 && (
            <div className="border-t border-border px-5 py-5">
              <h3 className="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold mb-3">
                Variações ({product.variations.length})
              </h3>
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead>
                    <tr className="text-left text-muted-foreground border-b border-border">
                      <th className="py-2 pr-4 font-medium">SKU</th>
                      <th className="py-2 pr-4 font-medium">Nome</th>
                      <th className="py-2 px-4 font-medium text-right">Custo</th>
                      <th className="py-2 pl-4 font-medium text-right">Venda</th>
                    </tr>
                  </thead>
                  <tbody>
                    {product.variations.map((v) => (
                      <tr key={v.id} className="border-b border-border/60">
                        <td className="py-2 pr-4 tabular-nums text-muted-foreground">{v.sub_sku}</td>
                        <td className="py-2 pr-4">{v.name === 'DUMMY' ? '—' : v.name}</td>
                        <td className="py-2 px-4 tabular-nums text-right">{formatCurrency(v.dpp_inc_tax)}</td>
                        <td className="py-2 pl-4 tabular-nums text-right">{formatCurrency(v.sell_price_inc_tax)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </section>

        {/* Estoque por location */}
        <section className="rounded-xl border border-border bg-background shadow-sm">
          <header className="flex items-center gap-3 px-5 py-4 border-b border-border">
            <MapPin size={18} className="text-muted-foreground" strokeWidth={1.5} />
            <h2 className="text-sm font-semibold text-foreground">Estoque por local</h2>
          </header>
          <div className="px-5 py-5">
            {!product.enable_stock ? (
              <p className="text-xs text-muted-foreground">Estoque não controlado pra este produto.</p>
            ) : stockByLocation.length === 0 ? (
              <p className="text-xs text-muted-foreground">Sem estoque registrado em nenhum local.</p>
            ) : (
              <ul className="space-y-3">
                {stockByLocation.map((row) => (
                  <li key={row.location_id} className="flex items-baseline justify-between gap-3">
                    <span className="text-sm text-foreground">{row.location_name}</span>
                    <span className="text-sm tabular-nums font-medium">
                      {formatNumber(row.qty)} <span className="text-[11px] text-muted-foreground">{product.unit ?? ''}</span>
                    </span>
                  </li>
                ))}
                <li className="border-t border-border pt-3 mt-3 flex items-baseline justify-between gap-3">
                  <span className="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">Total</span>
                  <span className="text-base tabular-nums font-semibold">
                    {formatNumber(kpis.total_stock)} <span className="text-[11px] text-muted-foreground">{product.unit ?? ''}</span>
                  </span>
                </li>
              </ul>
            )}
          </div>
        </section>
      </div>
    </div>
  );
}

ProductsShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function KpiCard({
  label,
  value,
  suffix,
  icon: Icon,
  muted,
  tone,
}: {
  label: string;
  value: ReactNode;
  suffix?: string;
  icon: typeof Package;
  muted?: boolean;
  tone?: 'amber';
}) {
  const valueClass = muted ? 'text-muted-foreground' : tone === 'amber' ? 'text-amber-700 dark:text-amber-300' : 'text-foreground';
  return (
    <div className="rounded-xl border border-border bg-background p-5 shadow-sm">
      <div className="text-[11px] font-semibold uppercase tracking-widest text-muted-foreground">{label}</div>
      <div className="flex items-end justify-between mt-3">
        <div className={'text-2xl font-semibold tabular-nums ' + valueClass}>
          {value}
          {suffix && <span className="text-sm text-muted-foreground ml-1">{suffix}</span>}
        </div>
        <Icon size={22} className={muted ? 'text-muted-foreground/40' : 'text-muted-foreground/60'} strokeWidth={1.5} />
      </div>
    </div>
  );
}

function Field({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div>
      <dt className="text-[11px] uppercase tracking-wider text-muted-foreground font-semibold">{label}</dt>
      <dd className="text-sm text-foreground mt-0.5">{value || '—'}</dd>
    </div>
  );
}
