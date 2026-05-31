// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/SellingPrices.tsx
// MWART (ADR 0104) + divergência declarada blueprint Cowork (ADR 0149)
// Constituição UI v2 (ADR UI-0013): tokens v4 + PageHeader canon (ADR 0110)
// Refs: RUNBOOK-produto-selling-prices.md · SellingPrices.charter.md
// Agent W2-C · 2026-05-15 · upgrade DS 2026-05-31

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import type { ReactNode, FormEvent, KeyboardEvent } from 'react';
import { Save, X } from 'lucide-react';
import { toast } from 'sonner';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
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
  defaultSellPrice: number;
}

interface PriceGroup {
  id: number;
  name: string;
  description: string | null;
}

type PriceType = 'fixed' | 'percentage';

interface VariationPriceCell {
  price: number;
  price_type: PriceType;
}

type VariationPricesMap = Record<number, Record<number, VariationPriceCell>>;

export interface ProdutoSellingPricesPageProps {
  product: {
    id: number;
    name: string;
    sku: string;
    type: 'single' | 'variable' | 'combo';
  };
  variations: VariationLite[];
  priceGroups: PriceGroup[];
  variationPrices: VariationPricesMap;
  permissions: {
    save: boolean;
  };
}

const fmtBRL = (n: number): string =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function ProdutoSellingPrices(props: ProdutoSellingPricesPageProps) {
  const { product, variations, priceGroups } = props;

  // Estado inicial: matrix priceGroup × variation
  const initialPrices: VariationPricesMap = {};
  for (const pg of priceGroups) {
    const row: Record<number, VariationPriceCell> = {};
    for (const v of variations) {
      const existing = props.variationPrices[v.id]?.[pg.id];
      row[v.id] = existing ?? { price: 0, price_type: 'fixed' };
    }
    initialPrices[pg.id] = row;
  }

  const { data, setData, post, processing, errors, isDirty, recentlySuccessful } = useForm<{
    product_id: number;
    group_prices: VariationPricesMap;
  }>({
    product_id: product.id,
    group_prices: initialPrices,
  });

  // Erros do servidor chegam por chave dotada (ex.: "group_prices.3.12.price").
  // O contrato TS de useForm tipa errors só com chaves do form, então lemos por
  // string via cast controlado (Inertia injeta as chaves de validação em runtime).
  const cellErrors = errors as unknown as Record<string, string>;

  const updateCell = (
    pgId: number,
    vId: number,
    field: keyof VariationPriceCell,
    value: number | PriceType,
  ) => {
    const groupRow = data.group_prices[pgId] ?? {};
    const prevCell = groupRow[vId] ?? { price: 0, price_type: 'fixed' as PriceType };
    const nextGroupPrices: VariationPricesMap = {
      ...data.group_prices,
      [pgId]: {
        ...groupRow,
        [vId]: { ...prevCell, [field]: value },
      },
    };
    setData('group_prices', nextGroupPrices);
  };

  const navigateCell = (
    e: KeyboardEvent<HTMLInputElement>,
    rowIdx: number,
    colIdx: number,
  ) => {
    const lastRow = variations.length - 1;
    const lastCol = priceGroups.length - 1;
    let nextRow = rowIdx;
    let nextCol = colIdx;
    switch (e.key) {
      case 'ArrowDown':
      case 'Enter':
        nextRow = Math.min(rowIdx + 1, lastRow);
        break;
      case 'ArrowUp':
        nextRow = Math.max(rowIdx - 1, 0);
        break;
      case 'ArrowRight':
        if (e.currentTarget.selectionStart !== e.currentTarget.value.length) return;
        if (colIdx < lastCol) nextCol = colIdx + 1;
        else if (rowIdx < lastRow) {
          nextRow = rowIdx + 1;
          nextCol = 0;
        }
        break;
      case 'ArrowLeft':
        if (e.currentTarget.selectionStart !== 0) return;
        if (colIdx > 0) nextCol = colIdx - 1;
        else if (rowIdx > 0) {
          nextRow = rowIdx - 1;
          nextCol = lastCol;
        }
        break;
      default:
        return;
    }
    if (nextRow === rowIdx && nextCol === colIdx) return;
    e.preventDefault();
    const next = document.querySelector<HTMLInputElement>(
      `input[data-row="${nextRow}"][data-col="${nextCol}"]`,
    );
    next?.focus();
    next?.select();
  };

  const doSave = () => {
    if (processing || !isDirty) return;
    post('/products/save-selling-prices', {
      preserveScroll: true,
      onSuccess: () => toast.success('Tabelas de preço salvas'),
      onError: () => toast.error('Verifique os preços destacados'),
    });
  };

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    doSave();
  };

  // Atalho Cmd/Ctrl+S salva sem sair da tela
  useEffect(() => {
    const onKeyDown = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        if (props.permissions.save) doSave();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isDirty, processing, props.permissions.save, data]);

  return (
    <>
      <Head title={`Tabelas de preço · ${product.name}`} />

      <div className="min-h-screen bg-background text-foreground">
        <div className="sticky top-0 z-30 border-b border-border bg-card/85 backdrop-blur px-6 pt-4">
          <PageHeaderRow
            product={product}
            isDirty={isDirty}
            recentlySuccessful={recentlySuccessful}
            processing={processing}
            canSave={props.permissions.save}
          />
        </div>

        <main className="pb-16 px-6 mt-4">
          {priceGroups.length === 0 ? (
            <div className="rounded-lg border border-border bg-card p-10 text-center">
              <p className="text-sm text-muted-foreground">
                Nenhum grupo de preço cadastrado. Cadastre primeiro em Inventário › Grupos de preço.
              </p>
            </div>
          ) : variations.length === 0 ? (
            <div className="rounded-lg border border-border bg-card p-10 text-center">
              <p className="text-sm text-muted-foreground">
                Produto sem variações cadastradas. Cadastre variações antes.
              </p>
            </div>
          ) : (
            <form id="selling-prices-form" onSubmit={handleSubmit}>
              <div className="rounded-lg border border-border bg-card shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-left">
                    <thead className="text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium">
                      <tr className="border-b border-border bg-muted/40">
                        <th className="pl-6 pr-3 py-2 sticky left-0 bg-muted/40">Variação</th>
                        <th className="pr-3 py-2 w-32">SKU</th>
                        <th className="pr-3 py-2 w-32 text-right">Preço padrão</th>
                        {priceGroups.map((pg) => (
                          <th key={pg.id} className="pr-3 py-2 min-w-56">
                            <div className="text-foreground">{pg.name}</div>
                            {pg.description && (
                              <div className="text-[10px] normal-case tracking-normal text-muted-foreground mt-0.5">
                                {pg.description}
                              </div>
                            )}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {variations.map((v, rowIdx) => (
                        <tr key={v.id} className="border-b border-border last:border-0 hover:bg-muted/30">
                          <td className="pl-6 pr-3 py-2 text-[13px] font-medium sticky left-0 bg-card">
                            {v.name}
                          </td>
                          <td className="pr-3 py-2 font-mono text-[11.5px] text-muted-foreground">
                            {v.subSku}
                          </td>
                          <td className="pr-3 py-2 text-[12.5px] text-right tabular-nums text-muted-foreground">
                            {fmtBRL(v.defaultSellPrice)}
                          </td>
                          {priceGroups.map((pg, colIdx) => {
                            const cell = data.group_prices[pg.id]?.[v.id] ?? {
                              price: 0,
                              price_type: 'fixed' as PriceType,
                            };
                            const cellError =
                              cellErrors[`group_prices.${pg.id}.${v.id}.price`] ??
                              cellErrors[`group_prices.${pg.id}.${v.id}.price_type`];
                            return (
                              <td key={pg.id} className="pr-3 py-2 align-top">
                                <div className="flex items-center gap-2">
                                  <Input
                                    type="number"
                                    step="0.01"
                                    inputMode="decimal"
                                    data-row={rowIdx}
                                    data-col={colIdx}
                                    value={cell.price}
                                    onChange={(e) =>
                                      updateCell(pg.id, v.id, 'price', Number(e.target.value))
                                    }
                                    onKeyDown={(e) => navigateCell(e, rowIdx, colIdx)}
                                    aria-invalid={cellError ? true : undefined}
                                    className={`tabular-nums w-28${cellError ? ' border-destructive' : ''}`}
                                    aria-label={`Preço ${pg.name} para variação ${v.name}`}
                                  />
                                  <Select
                                    value={cell.price_type}
                                    onValueChange={(val) =>
                                      updateCell(pg.id, v.id, 'price_type', val as PriceType)
                                    }
                                  >
                                    <SelectTrigger className="w-24">
                                      <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                      <SelectItem value="fixed">Fixo</SelectItem>
                                      <SelectItem value="percentage">%</SelectItem>
                                    </SelectContent>
                                  </Select>
                                </div>
                                {cellError && (
                                  <p className="mt-1 text-[11px] text-destructive">{cellError}</p>
                                )}
                              </td>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
              <p className="mt-3 text-[12px] text-muted-foreground">
                Preço tipo <strong>Fixo</strong>: valor absoluto em R$. Tipo <strong>%</strong>:
                desconto/acréscimo sobre o preço padrão da variação.{' '}
                <kbd className="rounded border border-border bg-muted px-1.5 py-0.5 font-mono text-[10px]">
                  ⌘S
                </kbd>{' '}
                salva · setas/Enter navegam entre células.
              </p>
            </form>
          )}
        </main>
      </div>
    </>
  );
}

// Cabeçalho: breadcrumb + título + SKU + ações. Tokens v4, sem stone cru.
function PageHeaderRow({
  product,
  isDirty,
  recentlySuccessful,
  processing,
  canSave,
}: {
  product: ProdutoSellingPricesPageProps['product'];
  isDirty: boolean;
  recentlySuccessful: boolean;
  processing: boolean;
  canSave: boolean;
}) {
  return (
    <div className="pb-3 flex items-center justify-between gap-3">
      <div className="min-w-0">
        <div className="flex items-center gap-1.5 text-[12px] text-muted-foreground">
          <Link href="/products" className="hover:underline">
            Produtos
          </Link>
          <span className="text-muted-foreground/60">›</span>
          <Link href={`/products/${product.id}`} className="hover:underline">
            {product.name}
          </Link>
          <span className="text-muted-foreground/60">›</span>
          <span className="text-foreground font-medium">Tabelas de preço</span>
        </div>
        <div className="mt-1 flex items-center gap-2 min-w-0">
          <h1 className="text-xl md:text-2xl font-semibold tracking-tight text-foreground leading-tight truncate">
            Tabelas de preço · {product.name}
          </h1>
          {isDirty && <Badge variant="secondary">Não salvo</Badge>}
          {!isDirty && recentlySuccessful && <Badge variant="outline">Salvo</Badge>}
        </div>
        <div className="mt-1 font-mono text-[12px] text-muted-foreground">{product.sku}</div>
      </div>
      <div className="flex items-center gap-2 shrink-0">
        <Button variant="outline" asChild>
          <Link href={`/products/${product.id}`}>
            <X className="w-4 h-4 mr-1.5" />
            Cancelar
          </Link>
        </Button>
        {canSave && (
          <Button form="selling-prices-form" type="submit" disabled={processing || !isDirty}>
            <Save className="w-4 h-4 mr-1.5" />
            {processing ? 'Salvando…' : 'Salvar tabelas'}
          </Button>
        )}
      </div>
    </div>
  );
}

ProdutoSellingPrices.layout = (page: ReactNode) => (
  <AppShellV2
    title="Tabelas de preço"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Tabelas de preço' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoSellingPrices;
