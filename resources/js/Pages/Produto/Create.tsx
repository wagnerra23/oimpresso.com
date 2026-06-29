// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/Create.tsx
// MWART canônico (ADR 0104) + screen-pattern reuse blueprint produto-cockpit (ADR 0149)
// Refs: RUNBOOK-produto-create.md · Create.charter.md
// Agent W2-C paralelo · 2026-05-15

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode, FormEvent } from 'react';
import { Save, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

const ADVANCED_OPEN_KEY = 'oimpresso.produto.create.advanced.open';

interface OptionMap {
  [id: number]: string;
}

interface OptionStringMap {
  [key: string]: string;
}

interface ProdutoDuplicate {
  id: number;
  name: string;
  brand_id: number | null;
  unit_id: number | null;
  category_id: number | null;
  sub_category_id: number | null;
  tax: number | null;
  tax_type: 'inclusive' | 'exclusive' | null;
  barcode_type: string | null;
  sku: string;
  alert_quantity: number | null;
  weight: number | null;
  product_description: string | null;
  type: 'single' | 'variable' | 'combo';
}

export interface ProdutoCreatePageProps {
  categories: OptionMap;
  brands: OptionMap;
  units: OptionMap;
  taxes: OptionMap;
  taxAttributes: Record<number, Record<string, unknown>>;
  barcodeTypes: OptionStringMap;
  barcodeDefault: string;
  defaultProfitPercent: number;
  businessLocations: OptionMap;
  duplicateProduct: ProdutoDuplicate | null;
  subCategories: OptionMap;
  rackDetails: Array<Record<string, unknown>> | null;
  sellingPriceGroupCount: number;
  productTypes: OptionStringMap;
  warranties: OptionMap;
  commonSettings: Record<string, unknown>;
  enableExpiry: boolean;
  enableLot: boolean;
  enableRacks: boolean;
}

function ProdutoCreate(props: ProdutoCreatePageProps) {
  const dup = props.duplicateProduct;

  const { data, setData, post, processing, errors } = useForm({
    name: dup?.name ?? '',
    sku: dup?.sku ?? '',
    type: (dup?.type ?? 'single') as 'single' | 'variable' | 'combo',
    unit_id: dup?.unit_id ?? '',
    brand_id: dup?.brand_id ?? '',
    category_id: dup?.category_id ?? '',
    sub_category_id: dup?.sub_category_id ?? '',
    tax: dup?.tax ?? '',
    tax_type: (dup?.tax_type ?? 'exclusive') as 'inclusive' | 'exclusive',
    barcode_type: dup?.barcode_type ?? props.barcodeDefault,
    enable_stock: 1,
    not_for_selling: 0,
    alert_quantity: dup?.alert_quantity ?? '',
    weight: dup?.weight ?? '',
    product_description: dup?.product_description ?? '',
    sub_unit_ids: [] as number[],
    product_locations: [] as number[],
    warranty_id: '' as number | '',
    expiry_period: '',
    expiry_period_type: '',
    enable_sr_no: 0,
    product_custom_field1: '',
    product_custom_field2: '',
    product_custom_field3: '',
    product_custom_field4: '',
  });

  const [advancedOpen, setAdvancedOpen] = useState<boolean>(() => {
    try {
      return localStorage.getItem(ADVANCED_OPEN_KEY) === '1';
    } catch {
      return false;
    }
  });

  const persistAdvanced = (next: boolean) => {
    setAdvancedOpen(next);
    try {
      localStorage.setItem(ADVANCED_OPEN_KEY, next ? '1' : '0');
    } catch {
      // ignora
    }
  };

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    post('/products');
  };

  return (
    <>
      <Head title="Novo produto · Catálogo" />

      <div className="min-h-screen bg-stone-50 text-stone-900">
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div>
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <span>Inventário</span>
                <span className="text-stone-400">›</span>
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Novo</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight">Novo produto</h1>
            </div>
            <div className="flex items-center gap-2">
              <Button variant="outline" asChild>
                <Link href="/products">
                  <X className="w-4 h-4 mr-1.5" />
                  Cancelar
                </Link>
              </Button>
              <Button form="produto-create-form" type="submit" disabled={processing}>
                <Save className="w-4 h-4 mr-1.5" />
                Salvar produto
              </Button>
            </div>
          </div>
        </header>

        <div className="pb-16 px-6 mt-4 max-w-5xl">
          <form id="produto-create-form" onSubmit={handleSubmit} className="space-y-6">
            {/* Identificação */}
            <Card>
              <CardHeader>
                <CardTitle>Identificação</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="name">Nome do produto *</Label>
                  <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                  />
                  {errors.name && (
                    <p className="text-[12px] text-destructive mt-1">{errors.name}</p>
                  )}
                </div>
                <div>
                  <Label htmlFor="sku">SKU (deixe em branco pra gerar automático)</Label>
                  <Input
                    id="sku"
                    value={data.sku}
                    onChange={(e) => setData('sku', e.target.value)}
                    className="font-mono"
                  />
                </div>
                <div>
                  <Label htmlFor="type">Tipo *</Label>
                  <Select
                    value={data.type}
                    onValueChange={(v) => setData('type', v as typeof data.type)}
                  >
                    <SelectTrigger id="type">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(props.productTypes).map(([k, v]) => (
                        <SelectItem key={k} value={k}>
                          {v}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="unit_id">Unidade *</Label>
                  <Select
                    value={String(data.unit_id ?? '')}
                    onValueChange={(v) => setData('unit_id', Number(v))}
                  >
                    <SelectTrigger id="unit_id">
                      <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(props.units).map(([k, v]) => (
                        <SelectItem key={k} value={k}>
                          {v}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="category_id">Categoria</Label>
                  <Select
                    value={String(data.category_id ?? '')}
                    onValueChange={(v) => setData('category_id', Number(v))}
                  >
                    <SelectTrigger id="category_id">
                      <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(props.categories).map(([k, v]) => (
                        <SelectItem key={k} value={k}>
                          {v}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="brand_id">Marca</Label>
                  <Select
                    value={String(data.brand_id ?? '')}
                    onValueChange={(v) => setData('brand_id', Number(v))}
                  >
                    <SelectTrigger id="brand_id">
                      <SelectValue placeholder="Selecione" />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(props.brands).map(([k, v]) => (
                        <SelectItem key={k} value={k}>
                          {v}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>

            {/* Preço & Imposto */}
            <Card>
              <CardHeader>
                <CardTitle>Preço & Imposto</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="tax">Taxa de imposto</Label>
                  <Select
                    value={String(data.tax ?? '')}
                    onValueChange={(v) => setData('tax', Number(v))}
                  >
                    <SelectTrigger id="tax">
                      <SelectValue placeholder="Nenhuma" />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(props.taxes).map(([k, v]) => (
                        <SelectItem key={k} value={k}>
                          {v}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="tax_type">Imposto inclui no preço?</Label>
                  <Select
                    value={data.tax_type}
                    onValueChange={(v) => setData('tax_type', v as 'inclusive' | 'exclusive')}
                  >
                    <SelectTrigger id="tax_type">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="exclusive">Exclusivo (somar imposto)</SelectItem>
                      <SelectItem value="inclusive">Inclusivo (já incluído)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>

            {/* Estoque */}
            <Card>
              <CardHeader>
                <CardTitle>Estoque</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-4">
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={data.enable_stock === 1}
                    onChange={(e) => setData('enable_stock', e.target.checked ? 1 : 0)}
                    className="rounded border-stone-300"
                  />
                  <span className="text-[13px]">Controlar estoque</span>
                </label>
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={data.not_for_selling === 1}
                    onChange={(e) => setData('not_for_selling', e.target.checked ? 1 : 0)}
                    className="rounded border-stone-300"
                  />
                  <span className="text-[13px]">Não está à venda (apenas insumo)</span>
                </label>
                <div>
                  <Label htmlFor="alert_quantity">Quantidade de alerta</Label>
                  <Input
                    id="alert_quantity"
                    type="number"
                    step="0.01"
                    value={String(data.alert_quantity ?? '')}
                    onChange={(e) => setData('alert_quantity', e.target.value)}
                    className="tabular-nums"
                  />
                </div>
                <div>
                  <Label htmlFor="barcode_type">Tipo de código de barras</Label>
                  <Select
                    value={data.barcode_type ?? ''}
                    onValueChange={(v) => setData('barcode_type', v)}
                  >
                    <SelectTrigger id="barcode_type">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {Object.entries(props.barcodeTypes).map(([k, v]) => (
                        <SelectItem key={k} value={k}>
                          {v}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>

            {/* Avançado colapsável */}
            <details
              open={advancedOpen}
              onToggle={(e) => persistAdvanced(e.currentTarget.open)}
              className="rounded-md bg-white border border-stone-200"
            >
              <summary className="px-6 py-3 cursor-pointer text-[14px] font-medium select-none">
                Mais opções
              </summary>
              <div className="px-6 pb-4 grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="weight">Peso</Label>
                  <Input
                    id="weight"
                    type="number"
                    step="0.001"
                    value={String(data.weight ?? '')}
                    onChange={(e) => setData('weight', e.target.value)}
                    className="tabular-nums"
                  />
                </div>
                <div className="col-span-2">
                  <Label htmlFor="product_description">Descrição</Label>
                  <Textarea
                    id="product_description"
                    rows={3}
                    value={data.product_description ?? ''}
                    onChange={(e) => setData('product_description', e.target.value)}
                  />
                </div>
                <div>
                  <Label htmlFor="product_custom_field1">Campo personalizado 1</Label>
                  <Input
                    id="product_custom_field1"
                    value={data.product_custom_field1}
                    onChange={(e) => setData('product_custom_field1', e.target.value)}
                  />
                </div>
                <div>
                  <Label htmlFor="product_custom_field2">Campo personalizado 2</Label>
                  <Input
                    id="product_custom_field2"
                    value={data.product_custom_field2}
                    onChange={(e) => setData('product_custom_field2', e.target.value)}
                  />
                </div>
                {props.enableExpiry && (
                  <div>
                    <Label htmlFor="expiry_period">Validade</Label>
                    <Input
                      id="expiry_period"
                      type="number"
                      value={data.expiry_period}
                      onChange={(e) => setData('expiry_period', e.target.value)}
                    />
                  </div>
                )}
                {props.warranties && Object.keys(props.warranties).length > 0 && (
                  <div>
                    <Label htmlFor="warranty_id">Garantia</Label>
                    <Select
                      value={String(data.warranty_id ?? '')}
                      onValueChange={(v) => setData('warranty_id', Number(v))}
                    >
                      <SelectTrigger id="warranty_id">
                        <SelectValue placeholder="Nenhuma" />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(props.warranties).map(([k, v]) => (
                          <SelectItem key={k} value={k}>
                            {v}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                )}
              </div>
            </details>
          </form>
        </div>
      </div>
    </>
  );
}

ProdutoCreate.layout = (page: ReactNode) => (
  <AppShellV2
    title="Novo produto"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Novo' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoCreate;
