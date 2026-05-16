// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/SellingPrices.tsx
// MWART (ADR 0104) + divergência declarada blueprint Cowork (ADR 0149)
// Refs: RUNBOOK-produto-selling-prices.md · SellingPrices.charter.md
// Agent W2-C · 2026-05-15

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode, FormEvent } from 'react';
import { Save, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
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
  const initialPrices: Record<number, Record<number, VariationPriceCell>> = {};
  for (const pg of priceGroups) {
    initialPrices[pg.id] = {};
    for (const v of variations) {
      const existing = props.variationPrices[v.id]?.[pg.id];
      initialPrices[pg.id][v.id] = existing ?? { price: 0, price_type: 'fixed' };
    }
  }

  const { data, setData, post, processing } = useForm({
    product_id: product.id,
    group_prices: initialPrices,
  });

  const updateCell = (
    pgId: number,
    vId: number,
    field: keyof VariationPriceCell,
    value: number | PriceType,
  ) => {
    setData('group_prices', {
      ...data.group_prices,
      [pgId]: {
        ...data.group_prices[pgId],
        [vId]: {
          ...data.group_prices[pgId][vId],
          [field]: value,
        },
      },
    });
  };

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    post('/products/save-selling-prices');
  };

  return (
    <>
      <Head title={`Tabelas de preço · ${product.name}`} />

      <div className="min-h-screen bg-stone-50 text-stone-900">
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div className="min-w-0">
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-stone-400">›</span>
                <Link href={`/products/${product.id}`} className="hover:underline">
                  {product.name}
                </Link>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Tabelas de preço</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight">
                Tabelas de preço · {product.name}
              </h1>
              <div className="mt-1 font-mono text-[12px] text-stone-500">{product.sku}</div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button variant="outline" asChild>
                <Link href={`/products/${product.id}`}>
                  <X className="w-4 h-4 mr-1.5" />
                  Cancelar
                </Link>
              </Button>
              {props.permissions.save && (
                <Button form="selling-prices-form" type="submit" disabled={processing}>
                  <Save className="w-4 h-4 mr-1.5" />
                  Salvar tabelas
                </Button>
              )}
            </div>
          </div>
        </header>

        <main className="pb-16 px-6 mt-4">
          {priceGroups.length === 0 ? (
            <div className="rounded-md bg-white border border-stone-200 p-6 text-center text-stone-500 text-[13px]">
              Nenhum grupo de preço cadastrado. Cadastre primeiro em Inventário › Grupos de preço.
            </div>
          ) : variations.length === 0 ? (
            <div className="rounded-md bg-white border border-stone-200 p-6 text-center text-stone-500 text-[13px]">
              Produto sem variações cadastradas. Cadastre variações antes.
            </div>
          ) : (
            <form id="selling-prices-form" onSubmit={handleSubmit}>
              <div className="rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
                <div className="overflow-x-auto">
                  <table className="w-full text-left">
                    <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
                      <tr className="border-b border-stone-200 bg-stone-50/40">
                        <th className="pl-6 pr-3 py-2 sticky left-0 bg-stone-50/40">Variação</th>
                        <th className="pr-3 py-2 w-32">SKU</th>
                        <th className="pr-3 py-2 w-32 text-right">Preço padrão</th>
                        {priceGroups.map((pg) => (
                          <th key={pg.id} className="pr-3 py-2 min-w-56">
                            <div className="text-stone-700">{pg.name}</div>
                            {pg.description && (
                              <div className="text-[10px] normal-case tracking-normal text-stone-500 mt-0.5">
                                {pg.description}
                              </div>
                            )}
                          </th>
                        ))}
                      </tr>
                    </thead>
                    <tbody>
                      {variations.map((v) => (
                        <tr key={v.id} className="border-b border-stone-100">
                          <td className="pl-6 pr-3 py-2 text-[13px] font-medium sticky left-0 bg-white">
                            {v.name}
                          </td>
                          <td className="pr-3 py-2 font-mono text-[11.5px] text-stone-500">
                            {v.subSku}
                          </td>
                          <td className="pr-3 py-2 text-[12.5px] text-right tabular-nums text-stone-700">
                            {fmtBRL(v.defaultSellPrice)}
                          </td>
                          {priceGroups.map((pg) => {
                            const cell = data.group_prices[pg.id]?.[v.id] ?? {
                              price: 0,
                              price_type: 'fixed' as PriceType,
                            };
                            return (
                              <td key={pg.id} className="pr-3 py-2">
                                <div className="flex items-center gap-2">
                                  <Input
                                    type="number"
                                    step="0.01"
                                    value={cell.price}
                                    onChange={(e) =>
                                      updateCell(pg.id, v.id, 'price', Number(e.target.value))
                                    }
                                    className="tabular-nums w-28"
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
                              </td>
                            );
                          })}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </div>
              <p className="mt-3 text-[12px] text-stone-500">
                Preço tipo <strong>Fixo</strong>: valor absoluto em R$. Tipo <strong>%</strong>:
                desconto/acréscimo sobre o preço padrão da variação.
              </p>
            </form>
          )}
        </main>
      </div>
    </>
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
