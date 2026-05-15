// US-PROD-002 — Edição de Produto Inertia/React.
// Reusa estrutura de Create.tsx (3 sections collapsible) — submete PUT /products/{id}.
//
// Refs: ADR 0104, ADR 0110, ADR 0093, RUNBOOK-products.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Loader2, Save, Tag, Package, Settings2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

interface DropdownOption {
  [id: number]: string;
}

interface ProductData {
  id: number;
  name: string;
  sku: string | null;
  type: 'single' | 'variable' | 'combo' | string;
  unit_id: number | null;
  category_id: number | null;
  sub_category_id: number | null;
  brand_id: number | null;
  tax: number | null;
  tax_type: string;
  enable_stock: boolean;
  is_inactive: boolean;
  not_for_selling: boolean;
  alert_quantity: string | number | null;
  weight: string | number | null;
  product_description: string | null;
  product_custom_field1: string | null;
  barcode_type: string | null;
}

export interface ProductsEditPageProps {
  product: ProductData;
  categories: DropdownOption;
  brands: DropdownOption;
  units: DropdownOption | Array<{ id: number; name: string }>;
  taxes: DropdownOption | Record<number, string>;
  productTypes: Record<string, string>;
  permissions: {
    update: boolean;
  };
}

const ADVANCED_OPEN_KEY = 'oimpresso.products.edit.advanced.open';

function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <p className="text-xs text-destructive mt-1" role="alert">
      {message}
    </p>
  );
}

function dropdownEntries(opt: DropdownOption | Record<number, string> | Array<{ id: number; name: string }>): Array<[string, string]> {
  if (Array.isArray(opt)) {
    return opt.map((o) => [String(o.id), o.name]);
  }
  return Object.entries(opt as Record<string, string>);
}

export default function ProductsEdit(props: ProductsEditPageProps) {
  const { product, categories, brands, units, taxes, productTypes } = props;

  const { data, setData, put, processing, errors } = useForm({
    name: product.name ?? '',
    sku: product.sku ?? '',
    type: product.type as 'single' | 'variable' | 'combo',
    unit_id: product.unit_id,
    category_id: product.category_id,
    sub_category_id: product.sub_category_id,
    brand_id: product.brand_id,
    tax: product.tax,
    tax_type: (product.tax_type ?? 'exclusive') as 'inclusive' | 'exclusive',
    enable_stock: product.enable_stock,
    alert_quantity: product.alert_quantity ?? '',
    weight: product.weight ?? '',
    product_description: product.product_description ?? '',
    product_custom_field1: product.product_custom_field1 ?? '',
    barcode_type: product.barcode_type ?? 'C128',
  });

  const [advancedOpen, setAdvancedOpen] = useState<boolean>(() => {
    if (typeof window === 'undefined') return false;
    try {
      return window.localStorage.getItem(ADVANCED_OPEN_KEY) === '1';
    } catch (_) {
      return false;
    }
  });

  useEffect(() => {
    try {
      window.localStorage.setItem(ADVANCED_OPEN_KEY, advancedOpen ? '1' : '0');
    } catch (_) {
      /* */
    }
  }, [advancedOpen]);

  // Atalhos
  const formRef = useRef<HTMLFormElement>(null);
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        formRef.current?.requestSubmit();
        return;
      }
      if (e.key === 'Escape') {
        if (confirm('Cancelar edição? As alterações não salvas serão perdidas.')) {
          window.location.href = `/products/${product.id}`;
        }
      }
    }
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, [product.id]);

  // Auto-open Avançado se houver erro em campo colapsado
  useEffect(() => {
    const advancedFields = ['barcode_type', 'weight', 'product_description', 'tax', 'tax_type'];
    if (Object.keys(errors).some((k) => advancedFields.includes(k))) {
      setAdvancedOpen(true);
    }
  }, [errors]);

  const onSubmit: React.FormEventHandler<HTMLFormElement> = (e) => {
    e.preventDefault();
    put(`/products/${product.id}`, {
      preserveScroll: true,
    });
  };

  const categoryEntries = dropdownEntries(categories);
  const brandEntries = dropdownEntries(brands);
  const unitEntries = dropdownEntries(units);
  const taxEntries = dropdownEntries(taxes);

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <form ref={formRef} onSubmit={onSubmit} noValidate>
        {/* Header sticky */}
        <div className="sticky top-0 z-10 border-b border-border bg-background">
          <div className="container mx-auto px-8 pt-6 pb-4 max-w-5xl">
            <div className="flex items-start gap-4">
              <div className="flex-1 min-w-0">
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">Editar produto</h1>
                <p className="text-sm text-muted-foreground mt-1">
                  {product.name} · SKU {product.sku ?? '—'}
                </p>
              </div>
              <div className="flex-shrink-0 flex items-center gap-2">
                <Button type="button" variant="outline" onClick={() => (window.location.href = `/products/${product.id}`)}>
                  Cancelar
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Save className="mr-1.5 h-4 w-4" />}
                  Salvar alterações
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div className="container mx-auto px-8 py-6 max-w-5xl space-y-6">
          {/* Section: Identificação */}
          <Section icon={Tag} title="Identificação">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <Label htmlFor="name">Nome do produto *</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                <FieldError message={errors.name} />
              </div>

              <div>
                <Label htmlFor="sku">SKU</Label>
                <Input id="sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
                <FieldError message={errors.sku} />
              </div>

              <div>
                <Label htmlFor="product_custom_field1">Código legacy (OfficeImpresso)</Label>
                <Input
                  id="product_custom_field1"
                  value={data.product_custom_field1}
                  onChange={(e) => setData('product_custom_field1', e.target.value)}
                />
              </div>

              <div>
                <Label htmlFor="unit_id">Unidade *</Label>
                <select
                  id="unit_id"
                  value={data.unit_id ?? ''}
                  onChange={(e) => setData('unit_id', e.target.value ? Number(e.target.value) : null)}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  <option value="">Selecionar…</option>
                  {unitEntries.map(([id, name]) => (
                    <option key={id} value={id}>
                      {name}
                    </option>
                  ))}
                </select>
                <FieldError message={errors.unit_id} />
              </div>

              <div>
                <Label htmlFor="type">Tipo</Label>
                <select
                  id="type"
                  value={data.type}
                  disabled
                  className="w-full h-9 rounded border border-border bg-muted/30 px-3 text-sm text-muted-foreground cursor-not-allowed"
                  title="Tipo não pode ser alterado depois do cadastro"
                >
                  {Object.entries(productTypes).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <Label htmlFor="category_id">Categoria</Label>
                <select
                  id="category_id"
                  value={data.category_id ?? ''}
                  onChange={(e) => setData('category_id', e.target.value ? Number(e.target.value) : null)}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  <option value="">Sem categoria</option>
                  {categoryEntries.map(([id, name]) => (
                    <option key={id} value={id}>
                      {name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <Label htmlFor="brand_id">Marca</Label>
                <select
                  id="brand_id"
                  value={data.brand_id ?? ''}
                  onChange={(e) => setData('brand_id', e.target.value ? Number(e.target.value) : null)}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  <option value="">Sem marca</option>
                  {brandEntries.map(([id, name]) => (
                    <option key={id} value={id}>
                      {name}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </Section>

          {/* Section: Estoque */}
          <Section icon={Package} title="Estoque">
            <div className="flex items-center gap-4 flex-wrap">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={data.enable_stock}
                  onChange={(e) => setData('enable_stock', e.target.checked)}
                  className="h-4 w-4"
                />
                <span className="text-sm">Controlar estoque</span>
              </label>

              {data.enable_stock && (
                <div className="flex items-center gap-2">
                  <Label htmlFor="alert_quantity" className="text-sm">
                    Alerta abaixo de:
                  </Label>
                  <Input
                    id="alert_quantity"
                    type="number"
                    step="0.01"
                    value={data.alert_quantity ?? ''}
                    onChange={(e) => setData('alert_quantity', e.target.value)}
                    className="w-32 h-8 text-sm"
                  />
                </div>
              )}
            </div>
            <p className="text-xs text-muted-foreground mt-3">
              Preços de venda são editados em <strong>cada variação</strong> via a tela legada de detalhes (Avançado).
            </p>
          </Section>

          {/* Section: Avançado */}
          <Section
            icon={Settings2}
            title="Avançado"
            collapsible
            open={advancedOpen}
            onToggle={() => setAdvancedOpen((v) => !v)}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="tax">Imposto</Label>
                <select
                  id="tax"
                  value={data.tax ?? ''}
                  onChange={(e) => setData('tax', e.target.value ? Number(e.target.value) : null)}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  <option value="">Sem imposto</option>
                  {taxEntries.map(([id, name]) => (
                    <option key={id} value={id}>
                      {name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <Label htmlFor="tax_type">Tipo de imposto</Label>
                <select
                  id="tax_type"
                  value={data.tax_type}
                  onChange={(e) => setData('tax_type', e.target.value as 'inclusive' | 'exclusive')}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  <option value="exclusive">Exclusivo</option>
                  <option value="inclusive">Inclusivo</option>
                </select>
              </div>

              <div>
                <Label htmlFor="weight">Peso (kg)</Label>
                <Input
                  id="weight"
                  type="number"
                  step="0.001"
                  value={data.weight ?? ''}
                  onChange={(e) => setData('weight', e.target.value)}
                />
              </div>

              <div>
                <Label htmlFor="barcode_type">Tipo de código de barras</Label>
                <select
                  id="barcode_type"
                  value={data.barcode_type ?? 'C128'}
                  onChange={(e) => setData('barcode_type', e.target.value)}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  <option value="C128">Code 128 (padrão)</option>
                  <option value="C39">Code 39</option>
                  <option value="EAN13">EAN-13</option>
                  <option value="EAN8">EAN-8</option>
                  <option value="UPC">UPC</option>
                </select>
              </div>

              <div className="md:col-span-2">
                <Label htmlFor="product_description">Descrição</Label>
                <Textarea
                  id="product_description"
                  value={data.product_description ?? ''}
                  onChange={(e) => setData('product_description', e.target.value)}
                  rows={3}
                />
              </div>
            </div>
          </Section>

          <div className="text-[11px] text-muted-foreground/70 text-center pt-2 pb-8">
            Atalhos: <kbd className="px-1 py-0.5 rounded border border-border bg-muted text-[10px]">Ctrl+S</kbd> salva ·{' '}
            <kbd className="px-1 py-0.5 rounded border border-border bg-muted text-[10px]">Esc</kbd> cancela
          </div>
        </div>
      </form>
    </div>
  );
}

ProductsEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function Section({
  icon: Icon,
  title,
  subtitle,
  children,
  collapsible = false,
  open = true,
  onToggle,
}: {
  icon: typeof Tag;
  title: string;
  subtitle?: string;
  children: ReactNode;
  collapsible?: boolean;
  open?: boolean;
  onToggle?: () => void;
}) {
  return (
    <section className="rounded-xl border border-border bg-background shadow-sm">
      <header
        className={'flex items-center gap-3 px-5 py-4 ' + (collapsible ? 'cursor-pointer select-none' : '')}
        onClick={collapsible ? onToggle : undefined}
      >
        <Icon size={18} className="text-muted-foreground flex-shrink-0" strokeWidth={1.5} />
        <div className="flex-1 min-w-0">
          <h2 className="text-sm font-semibold text-foreground">{title}</h2>
          {subtitle && <p className="text-xs text-muted-foreground mt-0.5">{subtitle}</p>}
        </div>
        {collapsible && (
          <button
            type="button"
            onClick={onToggle}
            className="h-7 w-7 inline-flex items-center justify-center rounded text-muted-foreground hover:bg-muted"
            aria-label={open ? 'Recolher seção' : 'Expandir seção'}
            aria-expanded={open}
          >
            {open ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
          </button>
        )}
      </header>
      {(!collapsible || open) && <div className="px-5 pb-5 pt-1 border-t border-border/60">{children}</div>}
    </section>
  );
}
