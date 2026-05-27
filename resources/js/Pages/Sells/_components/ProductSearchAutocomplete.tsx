// US-SELL-005 — ProductSearchAutocomplete (componente local da Sells/Create).
//
// Reusa endpoint legado /products/list (ProductController@getProducts)
// que aceita ?term=X&location_id=Y&search_fields[]=name&search_fields[]=sku...
// e retorna array de produtos com variations.
//
// Comportamento:
//   - Input com debounce 250ms
//   - Dropdown com até 10 resultados
//   - Click na linha → onSelect(product) chama callback do parent
//   - Tecla Esc fecha dropdown
//   - Tecla Down/Up navega resultados (futuro — US-SELL-007)
//
// Dor 3 (Larissa) — R5 auditoria 2026-05-27:
//   V1 (Blade legacy) buscava por nome, SKU, sub_sku, **lot_number** e
//   código de barras (que cai em sku/sub_sku). V2 só mandava `term=`, perdendo
//   paridade com a Blade — vendedor não conseguia bipar produto nem buscar lote.
//   Fix: passar `search_fields[]` array (mesmo default da Blade — name, sku,
//   sub_sku, lot) pro endpoint, que JÁ suporta via ProductUtil@filterProduct.
//   Backend NÃO mudou — só passamos os params que ele já entende.
//
// Não vira shared ainda — extrair pra @/Components/shared só quando 2ª tela usar.
// Princípio R-DS-001 (reutilização sob demanda, não especulativa).
//
// TODO V3: modal "Configurar busca" (paridade configure_search_modal.blade.php)
// pra usuário marcar/desmarcar product_custom_field1..4 + persistir em
// localStorage como a Blade fazia ('pos_search_fields').

import { useState, useEffect, useRef } from 'react';
import { Search, Loader2, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';

export interface ProductSearchResult {
  product_id: number;
  variation_id: number;
  name: string;
  sku: string;
  sub_sku?: string;
  selling_price?: number;
  qty_available?: number;
  unit?: string;
  // Quando search_fields inclui 'lot', backend adiciona estes campos
  // (ProductUtil@filterProduct L1732-1734):
  purchase_line_id?: number;
  lot_number?: string;
  // Outros campos do filterProduct podem aparecer; mantemos flexível.
  [k: string]: unknown;
}

interface Props {
  locationId: number | null;
  onSelect: (product: ProductSearchResult) => void;
  placeholder?: string;
  disabled?: boolean;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY_LENGTH = 2;

// Paridade Blade legacy (pos.js linha 3076 — set_search_fields default):
// `['name', 'sku', 'lot']`. Backend (ProductUtil@filterProduct) auto-adiciona
// `sub_sku` quando `sku` está presente (ver ProductController@getProducts L1522).
// Mantemos a lista igual à Blade pra zero drift comportamental.
const DEFAULT_SEARCH_FIELDS = ['name', 'sku', 'lot'] as const;

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
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

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

        // Dor 3 R5 — passa search_fields[] array (paridade Blade default).
        // Sem isso, backend cai no fallback ['name', 'sku'] (L1521 ProductController)
        // e perde busca por lote. Código de barras já cai em sku/sub_sku.
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
        setResults(Array.isArray(data) ? data.slice(0, 10) : []);
        setOpen(true);
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
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  // Esc fecha + Limpar
  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setOpen(false);
      inputRef.current?.blur();
    }
  };

  const handleClear = () => {
    setQuery('');
    setResults([]);
    setOpen(false);
    inputRef.current?.focus();
  };

  const handleSelect = (product: ProductSearchResult) => {
    onSelect(product);
    setQuery('');
    setResults([]);
    setOpen(false);
    inputRef.current?.focus();
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
          onFocus={() => results.length > 0 && setOpen(true)}
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

      {open && results.length > 0 && (
        <div
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-72 overflow-auto rounded-md border border-border bg-popover shadow-md"
        >
          {results.map((p) => (
            <button
              key={`${p.product_id}-${p.variation_id ?? 'novar'}`}
              type="button"
              role="option"
              onClick={() => handleSelect(p)}
              className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
            >
              <div className="flex flex-col min-w-0">
                <span className="font-medium truncate">{p.name}</span>
                <span className="text-xs text-muted-foreground">
                  SKU {p.sku}
                  {p.lot_number && (
                    <> · lote {p.lot_number}</>
                  )}
                  {p.qty_available !== undefined && (
                    <> · est. {p.qty_available}</>
                  )}
                </span>
              </div>
              {p.selling_price !== undefined && (
                <span className="ml-3 shrink-0 text-sm tabular-nums">
                  {Number(p.selling_price).toLocaleString('pt-BR', {
                    style: 'currency',
                    currency: 'BRL',
                  })}
                </span>
              )}
            </button>
          ))}
        </div>
      )}

      {open && query.length >= MIN_QUERY_LENGTH && results.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-md">
          Nenhum produto encontrado para "{query}".
        </div>
      )}
    </div>
  );
}
