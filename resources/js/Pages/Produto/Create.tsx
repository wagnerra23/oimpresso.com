// Wave 2 B4 Produto · F3 FRONTEND — Pages/Produto/Create.tsx
// MWART canônico (ADR 0104) + screen-pattern reuse blueprint produto-cockpit (ADR 0149)
// Refs: RUNBOOK-produto-create.md · Create.charter.md
// Agent W2-C paralelo · 2026-05-15
// 2026-10-16: as seções do formulário mudaram para _components/formulario/, compartilhadas com
// Edit.tsx. Esta tela segue dona do cabeçalho, do useForm e do envio.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode, FormEvent } from 'react';
import { Save, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { ProdutoFormulario } from './_components/formulario/ProdutoFormulario';
import { definidorDe, opcoesDe, tipoSemEditor } from './_components/formulario/tipos';
import type { OptionMap, OptionStringMap } from './_components/formulario/tipos';
import { aliquotaDoImposto, camposDePreco, precoInicial } from './_components/formulario/precos';

const ADVANCED_OPEN_KEY = 'oimpresso.produto.create.advanced.open';

function localUnico(locais: OptionMap): number[] {
  const ids = opcoesDe(locais).map(([id]) => Number(id));
  return ids.length === 1 ? ids : [];
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
  origem_mercadoria?: number;
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
  /** `add_expiry` (validade lançada na compra) ou `add_manufacturing` (calculada pela fabricação). */
  expiryType?: string;
  enableLot: boolean;
  enableRacks: boolean;
}

function ProdutoCreate(props: ProdutoCreatePageProps) {
  const dup = props.duplicateProduct;
  // UC-PCAD-12 — a validade como no Blade (product/create.blade.php): período em meses por padrão;
  // com a validade lançada na compra (`add_expiry`) o campo fica escondido e vai como 12 meses.
  const validadeNaCompra = props.enableExpiry && props.expiryType === 'add_expiry';

  // Preço do produto simples (precos.ts). Só os valores sem imposto; os "com imposto" saem da
  // alíquota do imposto escolhido, no envio e na tela.
  const [preco, setPreco] = useState(() => precoInicial(Number(props.defaultProfitPercent)));

  const { data, setData, post, transform, processing, errors } = useForm({
    name: dup?.name ?? '',
    sku: dup?.sku ?? '',
    type: (dup?.type ?? 'single') as 'single' | 'variable' | 'combo',
    unit_id: dup?.unit_id ?? '',
    brand_id: dup?.brand_id ?? '',
    category_id: dup?.category_id ?? '',
    sub_category_id: dup?.sub_category_id ?? '',
    tax: dup?.tax ?? '',
    tax_type: (dup?.tax_type ?? 'exclusive') as 'inclusive' | 'exclusive',
    origem_mercadoria: dup?.origem_mercadoria ?? 0,
    barcode_type: dup?.barcode_type ?? props.barcodeDefault,
    enable_stock: 1,
    not_for_selling: 0,
    alert_quantity: dup?.alert_quantity ?? '',
    weight: dup?.weight ?? '',
    product_description: dup?.product_description ?? '',
    sub_unit_ids: [] as number[],
    // Um local só: já vem marcado, como no Blade (`$default_location`). Mais de um: quem cadastra escolhe.
    product_locations: localUnico(props.businessLocations),
    warranty_id: '' as number | '',
    expiry_period: validadeNaCompra ? '12' : '',
    expiry_period_type: 'months',
    enable_sr_no: 0,
    product_custom_field1: '',
    product_custom_field2: '',
    product_custom_field3: '',
    product_custom_field4: '',
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    // Variável e composto sairiam sem variação/itens (tipos.ts tipoSemEditor): não salva.
    if (tipoSemEditor(data.type)) return;
    // Variável e composto têm o próprio editor de preço; só o simples manda os cinco campos.
    transform((d) =>
      d.type === 'single' ? { ...d, ...camposDePreco(preco, aliquotaDoImposto(d.tax, props.taxAttributes)) } : d,
    );
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
              <Button form="produto-create-form" type="submit" disabled={processing || tipoSemEditor(data.type)}>
                <Save className="w-4 h-4 mr-1.5" />
                Salvar produto
              </Button>
            </div>
          </div>
        </header>

        <div className="pb-16 px-6 mt-4 max-w-5xl">
          <ProdutoFormulario
            id="produto-create-form"
            onSubmit={handleSubmit}
            modo="novo"
            data={data}
            definir={definidorDe(setData)}
            errors={errors}
            opcoes={props}
            mostrarValidade={props.enableExpiry && !validadeNaCompra}
            chaveMaisOpcoes={ADVANCED_OPEN_KEY}
            preco={
              data.type === 'single'
                ? { valor: preco, mudar: setPreco, aliquota: aliquotaDoImposto(data.tax, props.taxAttributes) }
                : undefined
            }
          />
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
