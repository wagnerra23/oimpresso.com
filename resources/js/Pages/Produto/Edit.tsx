// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/Edit.tsx
// MWART (ADR 0104) + screen-pattern reuse Create.tsx (ADR 0149)
// Refs: RUNBOOK-produto-edit.md · Edit.charter.md
// Agent W2-C · 2026-05-15
// 2026-10-16: as seções do formulário mudaram para _components/formulario/, compartilhadas com
// Create.tsx. Esta tela segue dona do cabeçalho, do useForm e do envio.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import type { ReactNode, FormEvent } from 'react';
import { Save, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { ProdutoFormulario } from './_components/formulario/ProdutoFormulario';
import { definidorDe } from './_components/formulario/tipos';
import type { OptionMap, OptionStringMap } from './_components/formulario/tipos';

const ADVANCED_OPEN_KEY = 'oimpresso.produto.edit.advanced.open';

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
  /** Origem da mercadoria, tabela A da NF-e (0–8). */
  origemMercadoria?: number;
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
    origem_mercadoria: p.origemMercadoria ?? 0,
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
          <ProdutoFormulario
            id="produto-edit-form"
            onSubmit={handleSubmit}
            modo="editar"
            data={data}
            definir={definidorDe(setData)}
            errors={errors}
            opcoes={props}
            tipoAtual={props.productTypes[p.type] ?? p.type}
            chaveMaisOpcoes={ADVANCED_OPEN_KEY}
          />
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
