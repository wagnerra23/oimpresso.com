// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/Edit.tsx
// MWART (ADR 0104) + screen-pattern reuse Create.tsx (ADR 0149)
// Refs: RUNBOOK-produto-edit.md · Edit.charter.md
// Agent W2-C · 2026-05-15

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
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

const ADVANCED_OPEN_KEY = 'oimpresso.produto.edit.advanced.open';

interface OptionMap {
  [id: number]: string;
}

interface OptionStringMap {
  [key: string]: string;
}

interface ProdutoEditModel {
  id: number;
  name: string;
  sku: string;
  type: 'single' | 'variable' | 'combo';
  brandId: number | null;
  unitId: number | null;
  subUnitIds: string | null;
  categoryId: number | null;
  subCategoryId: number | null;
  tax: number | null;
  taxType: 'inclusive' | 'exclusive';
  barcodeType: string;
  enableStock: boolean;
  alertQuantity: string | null;
  weight: number | null;
  productDescription: string | null;
  productLocations: number[];
  image: string | null;
  warrantyId: number | null;
  productCustomField1: string | null;
  productCustomField2: string | null;
  productCustomField3: string | null;
  productCustomField4: string | null;
}

export interface ProdutoEditPageProps {
  product: ProdutoEditModel;
  categories: OptionMap;
  brands: OptionMap;
  units: OptionMap;
  subUnits: OptionMap;
  taxes: OptionMap;
  taxAttributes: Record<number, Record<string, unknown>>;
  barcodeTypes: OptionStringMap;
  subCategories: OptionMap;
  businessLocations: OptionMap;
  rackDetails: Array<Record<string, unknown>> | null;
  productTypes: OptionStringMap;
  warranties: OptionMap;
  permissions: {
    opening_stock: boolean;
    delete: boolean;
  };
}

function ProdutoEdit(props: ProdutoEditPageProps) {
  const p = props.product;
  const { data, setData, put, processing, errors } = useForm({
    name: p.name,
    sku: p.sku,
    brand_id: p.brandId ?? '',
    unit_id: p.unitId ?? '',
    category_id: p.categoryId ?? '',
    sub_category_id: p.subCategoryId ?? '',
    tax: p.tax ?? '',
    tax_type: p.taxType,
    barcode_type: p.barcodeType,
    alert_quantity: p.alertQuantity ?? '',
    weight: p.weight ?? '',
    product_description: p.productDescription ?? '',
    product_locations: p.productLocations,
    warranty_id: p.warrantyId ?? '',
    product_custom_field1: p.productCustomField1 ?? '',
    product_custom_field2: p.productCustomField2 ?? '',
    product_custom_field3: p.productCustomField3 ?? '',
    product_custom_field4: p.productCustomField4 ?? '',
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
    put(`/products/${p.id}`);
  };

  return (
    <>
      <Head title={`Editar · ${p.name}`} />

      <div className="min-h-screen bg-stone-50 text-stone-900">
        <header className="sticky top-0 z-30 bg-white/85 backdrop-blur border-b border-stone-200">
          <div className="px-6 pt-4 pb-3 flex items-center justify-between">
            <div className="min-w-0">
              <div className="flex items-center gap-1.5 text-[12px] text-stone-500">
                <Link href="/products" className="hover:underline">
                  Produtos
                </Link>
                <span className="text-stone-400">›</span>
                <span className="text-stone-900 font-medium">Editar</span>
              </div>
              <h1 className="mt-1 text-[24px] font-semibold tracking-tight truncate">
                Editar · {p.name}
              </h1>
              <div className="mt-1 font-mono text-[12px] text-stone-500">{p.sku}</div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button variant="outline" asChild>
                <Link href={`/products/${p.id}`}>
                  <X className="w-4 h-4 mr-1.5" />
                  Cancelar
                </Link>
              </Button>
              <Button form="produto-edit-form" type="submit" disabled={processing}>
                <Save className="w-4 h-4 mr-1.5" />
                Salvar alterações
              </Button>
            </div>
          </div>
        </header>

        <div className="pb-16 px-6 mt-4 max-w-5xl">
          <form id="produto-edit-form" onSubmit={handleSubmit} className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Identificação</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="name">Nome *</Label>
                  <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                  />
                  {errors.name && <p className="text-[12px] text-destructive mt-1">{errors.name}</p>}
                </div>
                <div>
                  <Label htmlFor="sku">SKU</Label>
                  <Input
                    id="sku"
                    value={data.sku}
                    onChange={(e) => setData('sku', e.target.value)}
                    className="font-mono"
                  />
                </div>
                <div>
                  <Label htmlFor="type">Tipo</Label>
                  <Input
                    id="type"
                    value={props.productTypes[p.type] ?? p.type}
                    disabled
                    aria-disabled="true"
                  />
                  <p className="mt-1 text-[11px] text-stone-500">
                    Tipo não pode ser alterado após criação.
                  </p>
                </div>
                <div>
                  <Label htmlFor="unit_id">Unidade</Label>
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

            <Card>
              <CardHeader>
                <CardTitle>Estoque</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-2 gap-4">
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

ProdutoEdit.layout = (page: ReactNode) => (
  <AppShellV2
    title="Editar produto"
    breadcrumbItems={[
      { label: 'Inventário', href: '/products' },
      { label: 'Produtos', href: '/products' },
      { label: 'Editar' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default ProdutoEdit;
