// US-SELL-013 (P0-2 RUNBOOK paridade Sells/Create) — Modal Quick Add Product inline.
//
// Por que existe (RUNBOOK-paridade-create.md §5 P0-2):
//   - Blade legacy `/sells/create` tem botão (+) ao lado do search produto que
//     abre `quick_add_product_modal` — Lara cadastra SKU on-the-fly no meio da venda
//     (caçamba 5m³ customizada Martinho biz=164 canary 19/maio).
//   - Sem isso, Lara perde 5min/produto novo abrindo nova aba Catálogo → Produtos.
//   - Pain #1 reunião Martinho 13/maio: velocidade pra abrir venda.
//
// Como funciona:
//   - Modal Dialog (Radix) com 7 campos mínimos: name, sku, unit_id, sell_price,
//     purchase_price, category_id, type (default single).
//   - Lazy-fetch da lista de unidades ao abrir (parse HTML de /products/quick_add —
//     evita expor JSON endpoint novo, reusa controller existente Tier 0).
//   - Submit via fetch POST FormData → /products/save_quick_product (ProductController@saveQuickProduct).
//   - Backend valida business_id via session (Tier 0 ADR 0093) — esta camada só transmite.
//   - Auto-gera SKU `LEG-<timestamp>` se vazio (atalho Lara).
//   - On success: callback onProductCreated(product) → Sells/Create adiciona à lista.
//   - Atalhos: Esc fecha (Radix nativo), Ctrl/Cmd+S submete.
//
// Refs:
//   - resources/views/product/partials/quick_add_product.blade.php (espelho campos)
//   - app/Http/Controllers/ProductController.php@saveQuickProduct (linha 1826)
//   - memory/requisitos/Sells/RUNBOOK-paridade-create.md §5 P0-2
//   - memory/reference/clientes/martinho-cacambas.md (sensibilidade caçambas custom)

import { useEffect, useRef, useState } from 'react';
import { Loader2, Plus } from 'lucide-react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

export interface QuickAddedProduct {
  /** id do produto recém-criado */
  product_id: number;
  /** id da variation single (Sells/Create usa pra montar row) */
  variation_id: number | null;
  name: string;
  sku: string;
  /** Preço de venda da variation single (já com tax conforme tax_type). */
  selling_price: number;
}

interface UnitOption {
  id: string;
  label: string;
}

interface CategoryOption {
  id: string;
  label: string;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Categories vindas da prop da Page (Record<id, name>). Opcional. */
  categories?: Record<string, unknown> | false;
  /** Callback após produto criado com sucesso — Sells/Create adiciona à venda. */
  onProductCreated: (product: QuickAddedProduct) => void;
  /** Prefill do nome (vem do query atual do ProductSearchAutocomplete, opcional). */
  prefilledName?: string;
}

function normalizeCategories(
  cats: Record<string, unknown> | false | undefined,
): CategoryOption[] {
  if (!cats || cats === false) return [];
  return Object.entries(cats)
    .filter(([id]) => id !== '' && id !== 'null' && id != null)
    .map(([id, value]) => ({ id, label: String(value ?? '') }));
}

/**
 * Parse units a partir do HTML retornado por /products/quick_add (Blade modal).
 *
 * Por que parse HTML: o controller @quickAdd já retorna a view Blade com
 * <select name="unit_id"> populado com Unit::forDropdown(business_id). Reaproveitar
 * essa rota evita criar JSON endpoint novo (escopo P0-2 não inclui backend).
 *
 * Robusto a markup variation: usa DOMParser e procura select[name=unit_id] option.
 */
function parseUnitsFromHtml(html: string): UnitOption[] {
  try {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const select = doc.querySelector('select[name="unit_id"]');
    if (!select) return [];
    const options = Array.from(select.querySelectorAll('option'));
    return options
      .map((opt) => ({
        id: opt.getAttribute('value') ?? '',
        label: opt.textContent?.trim() ?? '',
      }))
      .filter((u) => u.id !== '' && u.label !== '');
  } catch {
    return [];
  }
}

function getCsrfToken(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.content ?? '';
}

export default function ProductQuickAddModal({
  open,
  onOpenChange,
  categories,
  onProductCreated,
  prefilledName = '',
}: Props) {
  const [units, setUnits] = useState<UnitOption[]>([]);
  const [loadingUnits, setLoadingUnits] = useState(false);
  const [unitsLoaded, setUnitsLoaded] = useState(false);

  const [name, setName] = useState(prefilledName);
  const [sku, setSku] = useState('');
  const [unitId, setUnitId] = useState<string>('');
  const [categoryId, setCategoryId] = useState<string>('');
  const [sellingPrice, setSellingPrice] = useState<number>(0);
  const [purchasePrice, setPurchasePrice] = useState<number>(0);

  const [submitting, setSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  const formRef = useRef<HTMLFormElement>(null);
  const nameInputRef = useRef<HTMLInputElement>(null);

  const categoryOptions = normalizeCategories(categories);

  // Sincroniza prefilledName quando muda externamente (ex: usuário busca "Caçamba")
  // e clica em + sem ter selecionado — passa o termo pra nome do produto novo.
  useEffect(() => {
    if (open) {
      setName(prefilledName);
    }
  }, [open, prefilledName]);

  // Reset error ao abrir/fechar
  useEffect(() => {
    if (open) {
      setErrorMsg(null);
    } else {
      // Limpa form ao fechar pra próxima abertura começar zerada
      setSku('');
      setUnitId('');
      setCategoryId('');
      setSellingPrice(0);
      setPurchasePrice(0);
      setErrorMsg(null);
    }
  }, [open]);

  // Lazy-fetch units quando abre primeira vez. Reusa /products/quick_add (Blade)
  // — evita criar endpoint JSON novo (escopo P0-2 sem backend).
  useEffect(() => {
    if (!open || unitsLoaded || loadingUnits) return;
    setLoadingUnits(true);
    fetch('/products/quick_add', {
      headers: {
        Accept: 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })
      .then((res) => (res.ok ? res.text() : ''))
      .then((html) => {
        const parsed = parseUnitsFromHtml(html);
        setUnits(parsed);
        // Auto-seleciona primeira unidade pra Lara não precisar abrir o select
        if (parsed.length > 0 && !unitId) {
          setUnitId(parsed[0].id);
        }
        setUnitsLoaded(true);
      })
      .catch(() => {
        // Silencioso — se falhar, modal ainda funciona (Lara digita SKU sem unidade)
      })
      .finally(() => setLoadingUnits(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  // Auto-focus no campo nome ao abrir (acessibilidade + velocidade Lara)
  useEffect(() => {
    if (open) {
      const t = setTimeout(() => nameInputRef.current?.focus(), 50);
      return () => clearTimeout(t);
    }
  }, [open]);

  // Atalho Ctrl/Cmd+S → submit (canon Sells/Create)
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        formRef.current?.requestSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open]);

  const handleSubmit = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (submitting) return;
    setErrorMsg(null);

    if (!name.trim()) {
      setErrorMsg('Nome do produto é obrigatório');
      nameInputRef.current?.focus();
      return;
    }

    // Auto-gera SKU se vazio: LEG-<timestamp> (Lara pode ajustar depois).
    // Convenção Caçambas: caçamba customizada vira "LEG-1736XXXXXX".
    const finalSku = sku.trim() || `LEG-${Date.now()}`;

    setSubmitting(true);
    try {
      const fd = new FormData();
      fd.append('name', name.trim());
      fd.append('sku', finalSku);
      fd.append('type', 'single');
      fd.append('barcode_type', 'C128');
      fd.append('tax_type', 'exclusive');
      fd.append('enable_stock', '1');
      if (unitId) fd.append('unit_id', unitId);
      if (categoryId) fd.append('category_id', categoryId);
      fd.append('single_dpp', String(purchasePrice || 0));
      fd.append('single_dpp_inc_tax', String(purchasePrice || 0));
      fd.append('single_dsp', String(sellingPrice || 0));
      fd.append('single_dsp_inc_tax', String(sellingPrice || 0));
      fd.append('profit_percent', '0');

      const res = await fetch('/products/save_quick_product', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: fd,
      });

      if (!res.ok) {
        setErrorMsg('Erro ao salvar produto. Tente novamente.');
        return;
      }

      const data = (await res.json()) as {
        success: number;
        msg?: string;
        product?: { id: number; name: string; sku: string };
        variation?: { id: number; sell_price_inc_tax?: number; default_sell_price?: number };
      };

      if (!data?.success) {
        setErrorMsg(data?.msg ?? 'Erro ao salvar produto');
        return;
      }

      const created: QuickAddedProduct = {
        product_id: data.product?.id ?? 0,
        variation_id: data.variation?.id ?? null,
        name: data.product?.name ?? name,
        sku: data.product?.sku ?? finalSku,
        selling_price: Number(
          data.variation?.sell_price_inc_tax ??
            data.variation?.default_sell_price ??
            sellingPrice ??
            0,
        ),
      };

      onProductCreated(created);
      onOpenChange(false);
    } catch {
      setErrorMsg('Falha de rede ao salvar produto.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-2xl">
        <DialogHeader>
          <DialogTitle>Novo produto rápido</DialogTitle>
          <DialogDescription>
            Cadastre um produto on-the-fly. Após salvar, ele entra direto na venda atual.
          </DialogDescription>
        </DialogHeader>

        <form ref={formRef} onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="space-y-1.5 md:col-span-2">
              <Label htmlFor="quick_name">Nome do produto *</Label>
              <Input
                id="quick_name"
                ref={nameInputRef}
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Ex: Caçamba 5m³ customizada"
                required
                autoComplete="off"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="quick_sku">SKU</Label>
              <Input
                id="quick_sku"
                value={sku}
                onChange={(e) => setSku(e.target.value)}
                placeholder="Vazio = LEG-<timestamp>"
                autoComplete="off"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="quick_unit">Unidade</Label>
              <Select value={unitId} onValueChange={setUnitId} disabled={loadingUnits}>
                <SelectTrigger id="quick_unit">
                  <SelectValue
                    placeholder={loadingUnits ? 'Carregando…' : 'Selecionar'}
                  />
                </SelectTrigger>
                <SelectContent>
                  {units.map((u) => (
                    <SelectItem key={u.id} value={u.id}>
                      {u.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="quick_sell_price">Preço de venda</Label>
              <Input
                id="quick_sell_price"
                type="number"
                inputMode="decimal"
                step="0.01"
                min="0"
                value={sellingPrice || ''}
                onChange={(e) => setSellingPrice(Number(e.target.value))}
                placeholder="R$ 0,00"
                className="tabular-nums"
              />
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="quick_purchase_price">Preço de compra</Label>
              <Input
                id="quick_purchase_price"
                type="number"
                inputMode="decimal"
                step="0.01"
                min="0"
                value={purchasePrice || ''}
                onChange={(e) => setPurchasePrice(Number(e.target.value))}
                placeholder="R$ 0,00"
                className="tabular-nums"
              />
            </div>

            {categoryOptions.length > 0 && (
              <div className="space-y-1.5 md:col-span-2">
                <Label htmlFor="quick_category">Categoria</Label>
                <Select value={categoryId} onValueChange={setCategoryId}>
                  <SelectTrigger id="quick_category">
                    <SelectValue placeholder="Selecionar (opcional)" />
                  </SelectTrigger>
                  <SelectContent>
                    {categoryOptions.map((c) => (
                      <SelectItem key={c.id} value={c.id}>
                        {c.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>

          {errorMsg && (
            <div
              className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
              role="alert"
            >
              {errorMsg}
            </div>
          )}

          <DialogFooter>
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={submitting}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting ? (
                <>
                  <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                  Salvando…
                </>
              ) : (
                <>
                  <Plus className="mr-1.5 h-4 w-4" />
                  Salvar produto
                </>
              )}
            </Button>
          </DialogFooter>

          <p className="text-[11px] text-muted-foreground">
            Atalho: Ctrl/Cmd+S salva · Esc fecha
          </p>
        </form>
      </DialogContent>
    </Dialog>
  );
}
