// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/BulkEdit.tsx
// MWART (ADR 0104) + divergência declarada (ADR 0149 §"casos que NÃO se qualificam")
// Refs: RUNBOOK-produto-bulk-edit.md · BulkEdit.charter.md
// Agent W2-C · 2026-05-15

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode, FormEvent } from 'react';
import { AlertTriangle, Save, X } from 'lucide-react';
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
  defaultPurchasePrice: number;
  defaultSellPrice: number;
}

interface ProdutoBulkRow {
  id: number;
  name: string;
  sku: string;
  categoryId: number | null;
  subCategoryId: number | null;
  brandId: number | null;
  tax: number | null;
  productLocations: number[];
  variations: VariationLite[];
}

interface OptionMap {
  [id: number]: string;
}

export interface ProdutoBulkEditPageProps {
  products: ProdutoBulkRow[];
  categories: OptionMap;
  subCategories: Record<number, OptionMap>;
  brands: OptionMap;
  taxes: OptionMap;
  taxAttributes: Record<number, Record<string, unknown>>;
  priceGroups: OptionMap;
  businessLocations: OptionMap;
}

const fmtBRL = (n: number): string =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

function ProdutoBulkEdit(props: ProdutoBulkEditPageProps) {
  const [showConfirm, setShowConfirm] = useState(false);

  const initialProducts: Record<
    number,
    {
      category_id: number | null;
      sub_category_id: number | null;
      brand_id: number | null;
      tax: number | null;
      product_locations: number[];
      variations: Record<number, { default_purchase_price: number; default_sell_price: number }>;
    }
  > = {};

  for (const p of props.products) {
    initialProducts[p.id] = {
      category_id: p.categoryId,
      sub_category_id: p.subCategoryId,
      brand_id: p.brandId,
      tax: p.tax,
      product_locations: p.productLocations,
      variations: Object.fromEntries(
        p.variations.map((v) => [
          v.id,
          {
            default_purchase_price: v.defaultPurchasePrice,
            default_sell_price: v.defaultSellPrice,
          },
        ]),
      ),
    };
  }

  const { data, setData, post, processing } = useForm({
    products: initialProducts,
  });

  const updateProduct = (
    productId: number,
    field: 'category_id' | 'sub_category_id' | 'brand_id' | 'tax',
    value: number | null,
  ) => {
    setData('products', {
      ...data.products,
      [productId]: {
        ...data.products[productId],
        [field]: value,
      },
    });
  };

  const updateVariationPrice = (
    productId: number,
    variationId: number,
    field: 'default_purchase_price' | 'default_sell_price',
    value: number,
  ) => {
    setData('products', {
      ...data.products,
      [productId]: {
        ...data.products[productId],
        variations: {
          ...data.products[productId].variations,
          [variationId]: {
            ...data.products[productId].variations[variationId],
            [field]: value,
          },
        },
      },
    });
  };

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (!showConfirm) {
      setShowConfirm(true);
      return;
    }
    post('/products/mass-update');
  };

  const productCount = props.products.length;

  return (
    <>
      <Head title={`Edição em massa · ${productCount} produtos`} />

      <div className="min-h-screen bg-stone-50 text-stone-900">
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div>
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Edição em massa</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight">
                Edição em massa · {productCount}{' '}
                {productCount === 1 ? 'produto' : 'produtos'}
              </h1>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button variant="outline" asChild>
                <Link href="/products">
                  <X className="w-4 h-4 mr-1.5" />
                  Cancelar
                </Link>
              </Button>
              <Button
                form="bulk-edit-form"
                type="submit"
                disabled={processing}
                className={showConfirm ? 'bg-rose-700 hover:bg-rose-800' : ''}
              >
                <Save className="w-4 h-4 mr-1.5" />
                {showConfirm
                  ? `Confirmar (${productCount})`
                  : `Atualizar ${productCount} produtos`}
              </Button>
            </div>
          </div>
        </header>

        <main className="pb-16 px-6 mt-4">
          {/* Banner aviso destrutivo */}
          <div className="rounded-md bg-amber-50 border border-amber-200 p-4 flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-amber-700 shrink-0 mt-0.5" />
            <div>
              <h3 className="text-[13px] font-medium text-amber-900">
                Estas alterações afetam {productCount}{' '}
                {productCount === 1 ? 'produto simultaneamente' : 'produtos simultaneamente'}.
              </h3>
              <p className="text-[12.5px] text-amber-800 mt-1">
                Revise cada linha antes de confirmar. Não há desfazer automático — apenas re-edição
                manual.
              </p>
            </div>
          </div>

          <form id="bulk-edit-form" onSubmit={handleSubmit} className="mt-4">
            <div className="rounded-md bg-white border border-stone-200 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-left">
                  <thead className="text-[10.5px] uppercase tracking-widest text-stone-500 font-medium">
                    <tr className="border-b border-stone-200 bg-stone-50/40">
                      <th className="pl-6 pr-3 py-2 min-w-48">Produto</th>
                      <th className="pr-3 py-2 min-w-44">Categoria</th>
                      <th className="pr-3 py-2 min-w-44">Sub-categoria</th>
                      <th className="pr-3 py-2 min-w-40">Marca</th>
                      <th className="pr-3 py-2 min-w-32">Imposto</th>
                      <th className="pr-6 py-2 min-w-72">Preços variações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {props.products.map((p) => {
                      const row = data.products[p.id];
                      const subs = row.category_id
                        ? props.subCategories[row.category_id] ?? {}
                        : {};
                      return (
                        <tr key={p.id} className="border-b border-stone-100 align-top">
                          <td className="pl-6 pr-3 py-3 text-[13px]">
                            <div className="font-medium truncate max-w-44">{p.name}</div>
                            <div className="font-mono text-[11.5px] text-stone-500">{p.sku}</div>
                          </td>
                          <td className="pr-3 py-3">
                            <Select
                              value={String(row.category_id ?? '')}
                              onValueChange={(v) =>
                                updateProduct(p.id, 'category_id', Number(v) || null)
                              }
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="—" />
                              </SelectTrigger>
                              <SelectContent>
                                {Object.entries(props.categories).map(([k, v]) => (
                                  <SelectItem key={k} value={k}>
                                    {v}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="pr-3 py-3">
                            <Select
                              value={String(row.sub_category_id ?? '')}
                              onValueChange={(v) =>
                                updateProduct(p.id, 'sub_category_id', Number(v) || null)
                              }
                              disabled={Object.keys(subs).length === 0}
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="—" />
                              </SelectTrigger>
                              <SelectContent>
                                {Object.entries(subs).map(([k, v]) => (
                                  <SelectItem key={k} value={k}>
                                    {v}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="pr-3 py-3">
                            <Select
                              value={String(row.brand_id ?? '')}
                              onValueChange={(v) =>
                                updateProduct(p.id, 'brand_id', Number(v) || null)
                              }
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="—" />
                              </SelectTrigger>
                              <SelectContent>
                                {Object.entries(props.brands).map(([k, v]) => (
                                  <SelectItem key={k} value={k}>
                                    {v}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="pr-3 py-3">
                            <Select
                              value={String(row.tax ?? '')}
                              onValueChange={(v) =>
                                updateProduct(p.id, 'tax', Number(v) || null)
                              }
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="—" />
                              </SelectTrigger>
                              <SelectContent>
                                {Object.entries(props.taxes).map(([k, v]) => (
                                  <SelectItem key={k} value={k}>
                                    {v}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </td>
                          <td className="pr-6 py-3 space-y-2">
                            {p.variations.map((v) => {
                              const cell = row.variations[v.id] ?? {
                                default_purchase_price: 0,
                                default_sell_price: 0,
                              };
                              return (
                                <div key={v.id} className="flex items-center gap-2">
                                  <span className="text-[11.5px] font-mono text-stone-500 w-20 truncate">
                                    {v.subSku}
                                  </span>
                                  <Input
                                    type="number"
                                    step="0.01"
                                    value={cell.default_purchase_price}
                                    onChange={(e) =>
                                      updateVariationPrice(
                                        p.id,
                                        v.id,
                                        'default_purchase_price',
                                        Number(e.target.value),
                                      )
                                    }
                                    className="w-24 tabular-nums text-[12px]"
                                    aria-label={`Preço compra ${v.name}`}
                                  />
                                  <Input
                                    type="number"
                                    step="0.01"
                                    value={cell.default_sell_price}
                                    onChange={(e) =>
                                      updateVariationPrice(
                                        p.id,
                                        v.id,
                                        'default_sell_price',
                                        Number(e.target.value),
                                      )
                                    }
                                    className="w-24 tabular-nums text-[12px]"
                                    aria-label={`Preço venda ${v.name}`}
                                  />
                                </div>
                              );
                            })}
                            {p.variations.length > 0 && (
                              <div className="text-[10.5px] uppercase tracking-widest text-stone-500 mt-1">
                                SKU · compra · venda
                              </div>
                            )}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          </form>
        </main>
      </div>
    </>
  );
}

ProdutoBulkEdit.layout = (page: ReactNode) => (
  <AppShellV2
    title="Edição em massa"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Edição em massa' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoBulkEdit;
