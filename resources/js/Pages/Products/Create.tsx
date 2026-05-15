// US-PROD-002 — Cadastro de Produto Inertia/React.
// Migração `product.create.blade.php` (4+ telas scroll, 23 custom_fields visíveis)
// pra Cockpit V2 (ADR 0110): 3 sections collapsible, 6 campos obrigatórios visíveis.
//
// Persona canary: Lara Caçambas — não-técnica, monitor 1280px.
// Pain-point #1: velocidade pra cadastrar produto novo no meio do dia.
//
// Refs: ADR 0104, ADR 0110, ADR 0093, RUNBOOK-products.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Loader2, Save, Tag, Package, Settings2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';

interface DropdownOption {
  [id: number]: string;
}

interface DuplicateProduct {
  id: number;
  name: string;
  sku: string | null;
  type: string;
  unit_id: number | null;
  category_id: number | null;
  brand_id: number | null;
  tax: number | null;
  tax_type: string;
  enable_stock: boolean;
  alert_quantity: string | number | null;
  product_description: string | null;
}

export interface ProductsCreatePageProps {
  categories: DropdownOption;
  brands: DropdownOption;
  units: DropdownOption | Array<{ id: number; name: string }>;
  taxes: DropdownOption | Record<number, string>;
  productTypes: Record<string, string>;
  defaultProfitPercent: number | null;
  prefill: {
    name: string;
    sku: string;
    product_custom_field1: string;
  };
  duplicate: DuplicateProduct | null;
  permissions: {
    create: boolean;
  };
}

const ADVANCED_OPEN_KEY = 'oimpresso.products.create.advanced.open';
const PRICE_OPEN_KEY = 'oimpresso.products.create.price.open';

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

export default function ProductsCreate(props: ProductsCreatePageProps) {
  const { categories, brands, units, taxes, productTypes, defaultProfitPercent, prefill, duplicate } = props;

  const { data, setData, post, processing, errors } = useForm({
    name: duplicate?.name ?? prefill.name ?? '',
    sku: duplicate?.sku ?? prefill.sku ?? '',
    type: (duplicate?.type ?? 'single') as 'single' | 'variable' | 'combo',
    unit_id: duplicate?.unit_id ?? null,
    category_id: duplicate?.category_id ?? null,
    sub_category_id: null as number | null,
    brand_id: duplicate?.brand_id ?? null,
    tax: duplicate?.tax ?? null,
    tax_type: (duplicate?.tax_type ?? 'exclusive') as 'inclusive' | 'exclusive',
    enable_stock: duplicate?.enable_stock ?? true,
    alert_quantity: (duplicate?.alert_quantity ?? '') as string | number,
    product_description: duplicate?.product_description ?? '',
    product_custom_field1: prefill.product_custom_field1 ?? '',
    // Preço (campos single only — vão pro store backend)
    single_dpp: 0,
    single_dpp_inc_tax: 0,
    profit_percent: defaultProfitPercent ?? 25,
    single_dsp: 0,
    single_dsp_inc_tax: 0,
    barcode_type: 'C128',
    weight: '',
  });

  const [priceOpen, setPriceOpen] = useState<boolean>(() => {
    if (typeof window === 'undefined') return true;
    try {
      const v = window.localStorage.getItem(PRICE_OPEN_KEY);
      return v === null ? true : v === '1';
    } catch (_) {
      return true;
    }
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
      window.localStorage.setItem(PRICE_OPEN_KEY, priceOpen ? '1' : '0');
    } catch (_) {
      /* */
    }
  }, [priceOpen]);

  useEffect(() => {
    try {
      window.localStorage.setItem(ADVANCED_OPEN_KEY, advancedOpen ? '1' : '0');
    } catch (_) {
      /* */
    }
  }, [advancedOpen]);

  // Recalcular preço venda a partir de preço compra + % lucro (auto-fill conveniência)
  function applyMarginAuto() {
    const cost = Number(data.single_dpp) || 0;
    const margin = Number(data.profit_percent) || 0;
    if (cost > 0 && margin > 0) {
      const sell = cost * (1 + margin / 100);
      setData('single_dsp', Number(sell.toFixed(2)));
      setData('single_dsp_inc_tax', Number(sell.toFixed(2)));
    }
  }

  // Atalhos: Cmd/Ctrl+S salva, Esc cancela
  const formRef = useRef<HTMLFormElement>(null);
  useEffect(() => {
    function handleKey(e: KeyboardEvent) {
      // Cmd/Ctrl + S → submit
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        formRef.current?.requestSubmit();
        return;
      }
      // Esc → cancelar (volta pra listagem)
      if (e.key === 'Escape') {
        if (confirm('Cancelar cadastro? As alterações serão perdidas.')) {
          window.location.href = '/products';
        }
      }
    }
    window.addEventListener('keydown', handleKey);
    return () => window.removeEventListener('keydown', handleKey);
  }, []);

  // Auto-open Avançado se houver erro em campo colapsado
  useEffect(() => {
    const advancedFields = ['barcode_type', 'weight', 'product_description'];
    if (Object.keys(errors).some((k) => advancedFields.includes(k))) {
      setAdvancedOpen(true);
    }
  }, [errors]);

  const onSubmit: React.FormEventHandler<HTMLFormElement> = (e) => {
    e.preventDefault();
    post('/products', {
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
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                  {duplicate ? 'Duplicar produto' : 'Novo produto'}
                </h1>
                <p className="text-sm text-muted-foreground mt-1">
                  Cadastro essencial — Identificação obrigatória, Preço/Estoque e Avançado opcionais.
                </p>
              </div>
              <div className="flex-shrink-0 flex items-center gap-2">
                <Button type="button" variant="outline" onClick={() => (window.location.href = '/products')}>
                  Cancelar
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? (
                    <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
                  ) : (
                    <Save className="mr-1.5 h-4 w-4" />
                  )}
                  Salvar produto
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div className="container mx-auto px-8 py-6 max-w-5xl space-y-6">
          {/* Section: Identificação (sempre aberta) */}
          <Section icon={Tag} title="Identificação" subtitle="Como esse produto aparece no catálogo e na busca">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="md:col-span-2">
                <Label htmlFor="name">Nome do produto *</Label>
                <Input
                  id="name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="Ex: Caçamba Estacionária 4m³"
                  autoFocus
                />
                <FieldError message={errors.name} />
              </div>

              <div>
                <Label htmlFor="sku">SKU (código)</Label>
                <Input
                  id="sku"
                  value={data.sku}
                  onChange={(e) => setData('sku', e.target.value)}
                  placeholder="Gerado automaticamente se vazio"
                />
                <FieldError message={errors.sku} />
              </div>

              <div>
                <Label htmlFor="product_custom_field1">Código legacy (OfficeImpresso)</Label>
                <Input
                  id="product_custom_field1"
                  value={data.product_custom_field1}
                  onChange={(e) => setData('product_custom_field1', e.target.value)}
                  placeholder="Opcional — código vindo do sistema antigo"
                />
                <FieldError message={errors.product_custom_field1} />
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
                  onChange={(e) => setData('type', e.target.value as 'single' | 'variable' | 'combo')}
                  className="w-full h-9 rounded border border-border bg-background px-3 text-sm"
                >
                  {Object.entries(productTypes).map(([key, label]) => (
                    <option key={key} value={key}>
                      {label}
                    </option>
                  ))}
                </select>
                {data.type !== 'single' && (
                  <p className="text-[11px] text-amber-700 dark:text-amber-300 mt-1">
                    Variações e combos ainda usam o cadastro completo legado — clique em Salvar e abra o produto pra
                    configurar variations/combo.
                  </p>
                )}
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
                <FieldError message={errors.category_id} />
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
                <FieldError message={errors.brand_id} />
              </div>
            </div>
          </Section>

          {/* Section: Preço e estoque (collapsible, default aberto) */}
          <Section
            icon={Package}
            title="Preço e estoque"
            subtitle="Preço de venda, custo, margem e controle de estoque"
            collapsible
            open={priceOpen}
            onToggle={() => setPriceOpen((v) => !v)}
          >
            {data.type === 'single' && (
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                  <Label htmlFor="single_dpp">Preço de compra (custo)</Label>
                  <Input
                    id="single_dpp"
                    type="number"
                    step="0.01"
                    value={data.single_dpp || ''}
                    onChange={(e) => setData('single_dpp', Number(e.target.value))}
                    onBlur={applyMarginAuto}
                  />
                  <FieldError message={errors.single_dpp} />
                </div>

                <div>
                  <Label htmlFor="profit_percent">% Lucro</Label>
                  <Input
                    id="profit_percent"
                    type="number"
                    step="0.01"
                    value={data.profit_percent || ''}
                    onChange={(e) => setData('profit_percent', Number(e.target.value))}
                    onBlur={applyMarginAuto}
                  />
                </div>

                <div>
                  <Label htmlFor="single_dsp">Preço de venda</Label>
                  <Input
                    id="single_dsp"
                    type="number"
                    step="0.01"
                    value={data.single_dsp || ''}
                    onChange={(e) => {
                      const v = Number(e.target.value);
                      setData('single_dsp', v);
                      setData('single_dsp_inc_tax', v);
                    }}
                  />
                  <FieldError message={errors.single_dsp} />
                </div>
              </div>
            )}

            <div className="mt-4 flex items-center gap-4 flex-wrap">
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
                    value={data.alert_quantity}
                    onChange={(e) => setData('alert_quantity', e.target.value)}
                    className="w-32 h-8 text-sm"
                  />
                </div>
              )}
            </div>
          </Section>

          {/* Section: Avançado (collapsible, default fechado) */}
          <Section
            icon={Settings2}
            title="Avançado"
            subtitle="Imposto, peso, descrição e código de barras"
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
                  value={data.weight}
                  onChange={(e) => setData('weight', e.target.value)}
                  placeholder="Opcional"
                />
                <FieldError message={errors.weight} />
              </div>

              <div>
                <Label htmlFor="barcode_type">Tipo de código de barras</Label>
                <select
                  id="barcode_type"
                  value={data.barcode_type}
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
                  placeholder="Detalhes do produto (opcional)"
                />
                <FieldError message={errors.product_description} />
              </div>
            </div>
          </Section>

          {/* Hint atalhos pra Lara */}
          <div className="text-[11px] text-muted-foreground/70 text-center pt-2 pb-8">
            Atalhos: <kbd className="px-1 py-0.5 rounded border border-border bg-muted text-[10px]">Ctrl+S</kbd> salva ·{' '}
            <kbd className="px-1 py-0.5 rounded border border-border bg-muted text-[10px]">Esc</kbd> cancela
          </div>
        </div>
      </form>
    </div>
  );
}

ProductsCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ─── Section component ──────────────────────────────────────────────────────

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
        className={
          'flex items-center gap-3 px-5 py-4 ' + (collapsible ? 'cursor-pointer select-none' : '')
        }
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
