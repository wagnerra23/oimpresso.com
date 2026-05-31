// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/StockHistory.tsx
// MWART (ADR 0104) + divergência declarada blueprint Cowork (ADR 0149)
// Refs: RUNBOOK-produto-stock-history.md · StockHistory.charter.md
// 2026-05-31: timeline inline real (defer+skeleton) + PageHeader canon + tokens DS.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, Deferred, router } from '@inertiajs/react';
import { useState, useMemo, useCallback } from 'react';
import type { ReactNode } from 'react';
import { ArrowLeft } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

interface VariationLite {
  id: number;
  name: string;
  subSku: string;
}

interface OptionMap {
  [id: number]: string;
}

type MovementKind = 'entrada' | 'saida' | 'ajuste';

interface Movement {
  id: string;
  kind: MovementKind;
  dateLabel: string;
  quantity: number;
  quantityLabel: string;
  balanceLabel: string;
  origin: string;
  refNo: string;
}

export interface ProdutoStockHistoryPageProps {
  product: {
    id: number;
    name: string;
    sku: string;
    type: 'single' | 'variable' | 'combo';
    unit: string | null;
  };
  variations: VariationLite[];
  businessLocations: OptionMap;
  filters: {
    variationId: string;
    locationId: string;
  };
  permissions: {
    view: boolean;
  };
  // Timeline real de movimentação, carregada via Inertia::defer (ver charter).
  movements?: Movement[];
}

const HISTORY_VARIATION_KEY = 'oimpresso.produto.stockHistory.variation';
const HISTORY_LOCATION_KEY = 'oimpresso.produto.stockHistory.location';

const KIND_META: Record<
  MovementKind,
  { label: string; badge: 'secondary' | 'destructive' | 'outline' }
> = {
  entrada: { label: 'Entrada', badge: 'secondary' },
  saida: { label: 'Saída', badge: 'destructive' },
  ajuste: { label: 'Ajuste', badge: 'outline' },
};

function ProdutoStockHistory(props: ProdutoStockHistoryPageProps) {
  const { product, variations, businessLocations, filters, movements } = props;

  // Filtros com persistência localStorage (preservado da versão anterior).
  const [variationId, setVariationId] = useState<string>(() => {
    try {
      const stored = localStorage.getItem(HISTORY_VARIATION_KEY);
      return filters.variationId || stored || String(variations[0]?.id ?? '');
    } catch {
      return filters.variationId || String(variations[0]?.id ?? '');
    }
  });

  const [locationId, setLocationId] = useState<string>(() => {
    try {
      const stored = localStorage.getItem(HISTORY_LOCATION_KEY);
      const first = Object.keys(businessLocations)[0] ?? '';
      return filters.locationId || stored || first;
    } catch {
      return filters.locationId || (Object.keys(businessLocations)[0] ?? '');
    }
  });

  // Recarrega só a timeline (partial reload) quando o filtro muda — SPA feel.
  const reloadMovements = useCallback((nextVariation: string, nextLocation: string) => {
    router.reload({
      only: ['movements', 'filters'],
      data: {
        variation_id: nextVariation || undefined,
        location_id: nextLocation || undefined,
      },
    });
  }, []);

  const persistVariation = (v: string) => {
    setVariationId(v);
    try {
      localStorage.setItem(HISTORY_VARIATION_KEY, v);
    } catch {
      // ignora
    }
    reloadMovements(v, locationId);
  };

  const persistLocation = (l: string) => {
    setLocationId(l);
    try {
      localStorage.setItem(HISTORY_LOCATION_KEY, l);
    } catch {
      // ignora
    }
    reloadMovements(variationId, l);
  };

  // Resumo entrada/saída/ajuste/saldo do período — Larissa entende divergência em <30s.
  const summary = useMemo(() => {
    if (!movements) return null;
    let inQty = 0;
    let outQty = 0;
    let adjQty = 0;
    for (const m of movements) {
      if (m.kind === 'entrada') inQty += m.quantity;
      else if (m.kind === 'saida') outQty += m.quantity; // já vem negativo
      else adjQty += m.quantity;
    }
    return { inQty, outQty, adjQty, net: inQty + outQty + adjQty, count: movements.length };
  }, [movements]);

  // Link de fallback pro relatório legado (mantido até Wave 3 cobrir saldo corrente).
  const legacyHref = `/products/stock-history/${product.id}${
    locationId ? `?location_id=${locationId}` : ''
  }`;

  return (
    <>
      <Head title={`Histórico de estoque · ${product.name}`} />

      <main className="px-6 py-6 max-w-5xl space-y-6">
        <PageHeader
          icon="history"
          title="Histórico de estoque"
          description={`${product.name} · ${product.sku}${product.unit ? ` · ${product.unit}` : ''}`}
          action={
            <Button variant="outline" asChild>
              <Link href={`/products/${product.id}`}>
                <ArrowLeft className="w-4 h-4 mr-1.5" />
                Voltar ao produto
              </Link>
            </Button>
          }
        />

        {/* Filtros */}
        <div className="flex flex-wrap items-end gap-3">
          <div className="space-y-1">
            <label htmlFor="variation_select" className="text-sm font-medium text-foreground">
              Variação
            </label>
            <Select value={variationId} onValueChange={persistVariation}>
              <SelectTrigger id="variation_select" className="w-64">
                <SelectValue placeholder="Selecione" />
              </SelectTrigger>
              <SelectContent>
                {variations.map((v) => (
                  <SelectItem key={v.id} value={String(v.id)}>
                    {v.name} · {v.subSku}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1">
            <label htmlFor="location_select" className="text-sm font-medium text-foreground">
              Local
            </label>
            <Select value={locationId} onValueChange={persistLocation}>
              <SelectTrigger id="location_select" className="w-56">
                <SelectValue placeholder="Selecione" />
              </SelectTrigger>
              <SelectContent>
                {Object.entries(businessLocations).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </div>

        {/* Timeline real de movimentação (defer + skeleton). */}
        <Deferred data="movements" fallback={<TimelineSkeleton />}>
          <>
            {summary && summary.count > 0 && (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <SummaryTile label="Entradas" value={formatQty(summary.inQty)} tone="default" />
                <SummaryTile label="Saídas" value={formatQty(summary.outQty)} tone="negative" />
                <SummaryTile label="Ajustes" value={formatQty(summary.adjQty)} tone="muted" />
                <SummaryTile label="Saldo do período" value={formatQty(summary.net)} tone="default" />
              </div>
            )}

            {!movements || movements.length === 0 ? (
              <EmptyState
                icon="package-search"
                title="Sem movimentação registrada"
                description="Não há entradas, saídas ou ajustes para a variação e o local selecionados."
              />
            ) : (
              <Card>
                <CardContent className="p-0">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b border-border text-left text-muted-foreground">
                        <th className="p-3 font-medium">Data</th>
                        <th className="p-3 font-medium">Tipo</th>
                        <th className="p-3 font-medium">Origem</th>
                        <th className="p-3 font-medium">Referência</th>
                        <th className="p-3 font-medium text-right">Quantidade</th>
                        <th className="p-3 font-medium text-right">Saldo</th>
                      </tr>
                    </thead>
                    <tbody>
                      {movements.map((m) => (
                        <tr key={m.id} className="border-b border-border/50 hover:bg-muted/50">
                          <td className="whitespace-nowrap p-3 text-muted-foreground">
                            {m.dateLabel}
                          </td>
                          <td className="p-3">
                            <Badge variant={KIND_META[m.kind].badge}>{KIND_META[m.kind].label}</Badge>
                          </td>
                          <td className="p-3 text-foreground">{m.origin}</td>
                          <td className="p-3 font-mono text-xs text-muted-foreground">{m.refNo}</td>
                          <td
                            className={
                              'whitespace-nowrap p-3 text-right font-medium tabular-nums ' +
                              qtyToneClass(m.kind)
                            }
                          >
                            {m.quantityLabel}
                          </td>
                          <td className="whitespace-nowrap p-3 text-right tabular-nums text-muted-foreground">
                            {m.balanceLabel}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </CardContent>
              </Card>
            )}
          </>
        </Deferred>

        {/* Fallback legado — link de rodapé até Wave 3 cobrir saldo corrente/transferências. */}
        <p className="text-xs text-muted-foreground">
          Precisa do saldo corrente ou de tipos ainda não migrados?{' '}
          <a
            href={legacyHref}
            target="_blank"
            rel="noopener noreferrer"
            className="text-primary hover:underline"
          >
            Abrir relatório completo no sistema legado
          </a>
        </p>
      </main>
    </>
  );
}

function qtyToneClass(kind: MovementKind): string {
  if (kind === 'saida') return 'text-destructive';
  if (kind === 'ajuste') return 'text-muted-foreground';
  return 'text-foreground';
}

function formatQty(value: number): string {
  const fixed = Math.abs(value).toLocaleString('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 4,
  });
  if (value > 0) return `+${fixed}`;
  if (value < 0) return `-${fixed}`;
  return fixed;
}

function SummaryTile({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone: 'negative' | 'muted' | 'default';
}) {
  const toneClass =
    tone === 'negative'
      ? 'text-destructive'
      : tone === 'muted'
        ? 'text-muted-foreground'
        : 'text-foreground';
  return (
    <Card>
      <CardContent className="pt-6">
        <div className="text-xs text-muted-foreground">{label}</div>
        <div className={'mt-1 text-lg font-semibold tabular-nums ' + toneClass}>{value}</div>
      </CardContent>
    </Card>
  );
}

function TimelineSkeleton() {
  return (
    <Card>
      <CardContent className="p-4 space-y-3">
        {Array.from({ length: 8 }).map((_, i) => (
          <Skeleton key={i} className="h-10 w-full" />
        ))}
      </CardContent>
    </Card>
  );
}

ProdutoStockHistory.layout = (page: ReactNode) => (
  <AppShellV2
    title="Histórico de estoque"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Histórico de estoque' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoStockHistory;
