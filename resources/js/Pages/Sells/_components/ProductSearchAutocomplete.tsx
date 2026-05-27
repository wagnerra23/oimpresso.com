// US-SELL-005 — ProductSearchAutocomplete (componente local da Sells/Create).
//
// Reusa endpoint legado /products/list (ProductController@getProducts → ProductUtil@filterProduct)
// que retorna N rows pra produto type='variable' (uma por variação).
//
// Dor 1 Larissa — R6 paridade Blade + agrupamento (2026-05-27):
//   PR #1755 R4 incrementou qty no carrinho quando user clica 2x na MESMA variação,
//   mas o dropdown continuava mostrando N linhas com nome IDÊNTICO ("Camiseta",
//   "Camiseta", "Camiseta") pra produto variable — Larissa não distinguia tamanhos
//   e via "muita quantidade de produtos repetidos".
//
//   Blade legacy (pos.js:284-286) renderiza `${item.name}-${item.variation}` quando
//   type==='variable' (ex "Camiseta-G"). NÃO agrupa — mostra N linhas distinguíveis.
//
//   Esta PR vai ALÉM da paridade Blade quando há ≥2 variações: agrupa por product_id
//   no dropdown e abre Popover de seleção (estado da arte 2026 — Shopify POS, Toast).
//   Quando há 1 variação só: renderiza linha simples com `-{variation}` (paridade Blade).
//
// Dor 3 Larissa — R5 search_fields (2026-05-27):
//   Mantém envio de `search_fields[]=name,sku,lot` (paridade Blade default pos.js:3076).
//   Sem isso, backend cai em fallback ['name','sku'] e perde busca por lote.
//
// Não vira shared ainda — extrair pra @/Components/shared só quando 2ª tela usar.
// Princípio R-DS-001 (reutilização sob demanda, não especulativa).
//
// TODO V3: modal "Configurar busca" (paridade configure_search_modal.blade.php)
// pra usuário marcar/desmarcar product_custom_field1..4 + persistir em localStorage.

import { useState, useEffect, useRef, useMemo } from 'react';
import { Search, Loader2, X, ChevronRight, Package } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import {
  Popover,
  PopoverTrigger,
  PopoverContent,
} from '@/Components/ui/popover';

// Backend devolve estes campos por row (ProductUtil@filterProduct:1715-1734).
// AP-12 F3 audit (memory/sessions/2026-05-27-audit-sells-create-vs-blade-larissa.md):
// tipo explícito > `[k: string]: unknown` pra detectar drift backend cedo.
export interface ProductSearchResult {
  product_id: number;
  variation_id: number;
  name: string;
  type?: 'variable' | 'single' | 'modifier' | 'combo';
  enable_stock?: 0 | 1;
  variation?: string; // variations.name (ex "P", "M", "G")
  sub_sku?: string;
  sku: string;
  selling_price?: number;
  variation_group_price?: number; // quando price_group filtra
  qty_available?: number;
  unit?: string;
  // Quando search_fields inclui 'lot':
  purchase_line_id?: number;
  lot_number?: string;
}

interface Props {
  locationId: number | null;
  onSelect: (product: ProductSearchResult) => void;
  placeholder?: string;
  disabled?: boolean;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY_LENGTH = 2;

// Paridade Blade legacy (pos.js:3076 — set_search_fields default):
// `['name', 'sku', 'lot']`. Backend (ProductUtil@filterProduct) auto-adiciona
// `sub_sku` quando `sku` está presente (ProductController@getProducts:1522).
const DEFAULT_SEARCH_FIELDS = ['name', 'sku', 'lot'] as const;

// Agrupamento dropdown: 1 entrada por product_id. Quando >1 variação,
// abre Popover com lista. Quando 1 só, render direto com `-{variation}`
// se type==='variable' (paridade Blade pos.js:285-286).
type ProductGroup = {
  product_id: number;
  name: string;
  type: ProductSearchResult['type'];
  variations: ProductSearchResult[];
  total_qty_available: number;
  display_price?: number;
};

function groupResults(results: ProductSearchResult[]): ProductGroup[] {
  const map = new Map<number, ProductGroup>();
  for (const row of results) {
    const existing = map.get(row.product_id);
    const rowPrice = row.variation_group_price ?? row.selling_price;
    if (existing) {
      existing.variations.push(row);
      existing.total_qty_available += Number(row.qty_available ?? 0);
    } else {
      map.set(row.product_id, {
        product_id: row.product_id,
        name: row.name,
        type: row.type,
        variations: [row],
        total_qty_available: Number(row.qty_available ?? 0),
        display_price: rowPrice !== undefined ? Number(rowPrice) : undefined,
      });
    }
  }
  return Array.from(map.values());
}

function formatBRL(value: number) {
  return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ProductSearchAutocomplete({
  locationId,
  onSelect,
  placeholder = 'Buscar por nome, SKU, código de barras ou lote…',
  disabled = false,
}: Props) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<ProductSearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const [expandedProductId, setExpandedProductId] = useState<number | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  const groups = useMemo(() => groupResults(results).slice(0, 10), [results]);

  // Debounce + fetch
  useEffect(() => {
    if (query.length < MIN_QUERY_LENGTH) {
      setResults([]);
      return;
    }

    const handle = setTimeout(async () => {
      setLoading(true);
      try {
        const params = new URLSearchParams({ term: query });
        if (locationId) params.set('location_id', String(locationId));

        // Dor 3 R5 — search_fields[] array (paridade Blade default).
        DEFAULT_SEARCH_FIELDS.forEach((field) => {
          params.append('search_fields[]', field);
        });

        const res = await fetch(`/products/list?${params.toString()}`, {
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          credentials: 'same-origin',
        });

        if (!res.ok) {
          setResults([]);
          return;
        }

        const data = (await res.json()) as ProductSearchResult[];
        setResults(Array.isArray(data) ? data : []);
        setOpen(true);
        setExpandedProductId(null);
      } catch {
        setResults([]);
      } finally {
        setLoading(false);
      }
    }, DEBOUNCE_MS);

    return () => clearTimeout(handle);
  }, [query, locationId]);

  // Click fora fecha dropdown
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
        setExpandedProductId(null);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setOpen(false);
      setExpandedProductId(null);
      inputRef.current?.blur();
    }
  };

  const handleClear = () => {
    setQuery('');
    setResults([]);
    setOpen(false);
    setExpandedProductId(null);
    inputRef.current?.focus();
  };

  const handleSelectVariation = (variation: ProductSearchResult) => {
    onSelect(variation);
    setQuery('');
    setResults([]);
    setOpen(false);
    setExpandedProductId(null);
    inputRef.current?.focus();
  };

  const handleGroupClick = (group: ProductGroup) => {
    // 1 variação → adiciona direto (mesmo se variable, sem popover overhead).
    const only = group.variations[0];
    if (group.variations.length === 1 && only) {
      handleSelectVariation(only);
      return;
    }
    // >1 variação → toggla popover (open/close em click)
    setExpandedProductId((prev) => (prev === group.product_id ? null : group.product_id));
  };

  return (
    <div ref={containerRef} className="relative w-full">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          ref={inputRef}
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => groups.length > 0 && setOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled || !locationId}
          className="pl-9 pr-9"
          aria-label="Buscar produto"
          aria-expanded={open}
          aria-haspopup="listbox"
          autoComplete="off"
        />
        {loading && (
          <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-muted-foreground" />
        )}
        {!loading && query.length > 0 && (
          <button
            type="button"
            onClick={handleClear}
            aria-label="Limpar busca"
            className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {!locationId && (
        <p className="mt-1 text-xs text-muted-foreground">
          Selecione um local antes de buscar produtos.
        </p>
      )}

      {locationId && query.length === 0 && (
        <p className="mt-1 text-xs text-muted-foreground">
          Digite ou bipe: nome, SKU, código de barras ou lote.
        </p>
      )}

      {open && groups.length > 0 && (
        <div
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-72 overflow-auto rounded-md border border-border bg-popover shadow-md"
        >
          {groups.map((group) => {
            const isExpanded = expandedProductId === group.product_id;
            const firstVar = group.variations[0];
            // group.variations sempre tem ≥1 (garantido pelo groupResults).
            // Type guard explícito pro TS narrow.
            if (!firstVar) return null;
            const hasMultiple = group.variations.length > 1;

            // 1 variação — render direto. Mostra `-{variation}` se variable (paridade Blade).
            if (!hasMultiple) {
              const isVariable = group.type === 'variable' && firstVar.variation;
              const displayName = isVariable ? `${group.name} - ${firstVar.variation}` : group.name;
              const skuLabel = firstVar.sub_sku ?? firstVar.sku;
              return (
                <button
                  key={`g-${group.product_id}-${firstVar.variation_id}`}
                  type="button"
                  role="option"
                  onClick={() => handleSelectVariation(firstVar)}
                  className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
                >
                  <div className="flex flex-col min-w-0">
                    <span className="font-medium truncate">{displayName}</span>
                    <span className="text-xs text-muted-foreground">
                      SKU {skuLabel}
                      {firstVar.lot_number && <> · lote {firstVar.lot_number}</>}
                      {firstVar.qty_available !== undefined && (
                        <> · est. {firstVar.qty_available}{firstVar.unit ? firstVar.unit : ''}</>
                      )}
                    </span>
                  </div>
                  {firstVar.selling_price !== undefined && (
                    <span className="ml-3 shrink-0 text-sm tabular-nums">
                      {formatBRL(Number(firstVar.variation_group_price ?? firstVar.selling_price))}
                    </span>
                  )}
                </button>
              );
            }

            // >1 variações — linha agrupada + Popover side=right com lista
            return (
              <Popover
                key={`g-${group.product_id}`}
                open={isExpanded}
                onOpenChange={(o) => setExpandedProductId(o ? group.product_id : null)}
              >
                <PopoverTrigger asChild>
                  <button
                    type="button"
                    role="option"
                    aria-expanded={isExpanded}
                    aria-haspopup="listbox"
                    onClick={() => handleGroupClick(group)}
                    className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <Package className="h-4 w-4 text-muted-foreground shrink-0" aria-hidden />
                      <div className="flex flex-col min-w-0">
                        <span className="font-medium truncate">{group.name}</span>
                        <span className="text-xs text-muted-foreground">
                          {group.variations.length} tamanhos
                          {group.total_qty_available > 0 && (
                            <> · est. total {group.total_qty_available}</>
                          )}
                        </span>
                      </div>
                    </div>
                    <div className="flex items-center gap-2 shrink-0">
                      {group.display_price !== undefined && (
                        <span className="text-sm tabular-nums">{formatBRL(group.display_price)}</span>
                      )}
                      <ChevronRight
                        className={`h-4 w-4 text-muted-foreground transition-transform ${
                          isExpanded ? 'rotate-90' : ''
                        }`}
                        aria-hidden
                      />
                    </div>
                  </button>
                </PopoverTrigger>
                <PopoverContent
                  side="right"
                  align="start"
                  sideOffset={8}
                  className="w-80 p-0"
                  // Evita roubar foco do input principal — popover lida com seu próprio teclado
                  onOpenAutoFocus={(e) => e.preventDefault()}
                >
                  <div className="border-b border-border px-3 py-2">
                    <p className="text-xs font-medium text-muted-foreground">
                      Escolha o tamanho de {group.name}
                    </p>
                  </div>
                  <ul role="listbox" aria-label={`Variações de ${group.name}`} className="max-h-72 overflow-auto">
                    {group.variations.map((v) => (
                      <li key={`v-${v.variation_id}`}>
                        <button
                          type="button"
                          role="option"
                          onClick={() => handleSelectVariation(v)}
                          className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
                        >
                          <div className="flex flex-col min-w-0">
                            <span className="font-medium truncate">
                              {v.variation ?? 'Padrão'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                              SKU {v.sub_sku ?? v.sku}
                              {v.lot_number && <> · lote {v.lot_number}</>}
                              {v.qty_available !== undefined && (
                                <> · est. {v.qty_available}{v.unit ?? ''}</>
                              )}
                            </span>
                          </div>
                          {v.selling_price !== undefined && (
                            <span className="ml-3 shrink-0 text-sm tabular-nums">
                              {formatBRL(Number(v.variation_group_price ?? v.selling_price))}
                            </span>
                          )}
                        </button>
                      </li>
                    ))}
                  </ul>
                </PopoverContent>
              </Popover>
            );
          })}
        </div>
      )}

      {open && query.length >= MIN_QUERY_LENGTH && groups.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-md">
          Nenhum produto encontrado para "{query}".
        </div>
      )}
    </div>
  );
}
