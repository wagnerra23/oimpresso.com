// GradeProductCombobox — picker NÍVEL-PRODUTO pra abrir a grade de compra (US-COM-005).
//
// Diferente do ProductSearchAutocomplete da Sells (que seleciona UMA variação pra
// venda), aqui só precisamos do PRODUTO PAI: a grade (GradeMatrixInput) cuida das
// variações depois, via GET /purchases/grade-matrix?product_id=X.
//
// Endpoint: /purchases/get_products (domínio de compra — não aplica filtros sells-only).
// Retorna rows { id, text, product_id, variation_id } (variation_id=0 = linha "pai"
// do produto variável). Agrupamos por product_id; o nome canônico vem depois do
// grade-matrix (product_name), então o label do dropdown só precisa ser legível.
//
// Não vira shared ainda (R-DS-001 — reutilização sob demanda).

import { useCallback, useEffect, useRef, useState } from 'react';
import { Search, Loader2, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';

interface GetProductsRow {
  id: number;
  text: string;
  product_id: number;
  variation_id: number;
}

export interface PickedProduct {
  product_id: number;
  label: string;
}

interface Props {
  /** Filial selecionada — restringe estoque/local (igual fluxo Sells). */
  locationId: number | null;
  onPick: (product: PickedProduct) => void;
  disabled?: boolean;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY = 2;

// Limpa o `text` do getProducts ("Nome (P/Preto) - SUB-SKU") pra exibir só o nome.
// O nome canônico real chega depois no grade-matrix (product_name); aqui é só display.
function cleanLabel(text: string): string {
  return text
    .replace(/\s*\(.*?\)\s*/g, ' ')
    .replace(/\s+-\s+[^-]*$/, '')
    .trim() || text;
}

function groupByProduct(rows: GetProductsRow[]): PickedProduct[] {
  const map = new Map<number, PickedProduct>();
  for (const r of rows) {
    if (!r.product_id) continue;
    const existing = map.get(r.product_id);
    const label = cleanLabel(r.text);
    // Prefere a linha "pai" (variation_id===0) como fonte do label.
    if (!existing || r.variation_id === 0) {
      map.set(r.product_id, { product_id: r.product_id, label });
    }
  }
  return Array.from(map.values()).slice(0, 12);
}

export default function GradeProductCombobox({ locationId, onPick, disabled = false }: Props) {
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState<PickedProduct[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [highlight, setHighlight] = useState(-1);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Busca debounceada com cancelamento de request stale.
  useEffect(() => {
    if (query.trim().length < MIN_QUERY) {
      setOptions([]);
      setOpen(false);
      return;
    }
    const controller = new AbortController();
    const handle = setTimeout(async () => {
      setLoading(true);
      try {
        const params = new URLSearchParams({ term: query.trim() });
        if (locationId) params.set('location_id', String(locationId));
        const res = await fetch(`/purchases/get_products?${params.toString()}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          signal: controller.signal,
        });
        if (!res.ok) {
          setOptions([]);
          return;
        }
        const data = (await res.json()) as GetProductsRow[];
        const grouped = groupByProduct(Array.isArray(data) ? data : []);
        setOptions(grouped);
        setOpen(true);
        setHighlight(-1);
      } catch (err) {
        if ((err as Error).name !== 'AbortError') setOptions([]);
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }, DEBOUNCE_MS);
    return () => {
      clearTimeout(handle);
      controller.abort();
    };
  }, [query, locationId]);

  // Click-fora fecha o dropdown.
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const pick = useCallback(
    (p: PickedProduct) => {
      onPick(p);
      setQuery('');
      setOptions([]);
      setOpen(false);
      setHighlight(-1);
      inputRef.current?.blur();
    },
    [onPick]
  );

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setOpen(false);
      setHighlight(-1);
      return;
    }
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!open && options.length > 0) setOpen(true);
      setHighlight((h) => Math.min(h + 1, options.length - 1));
      return;
    }
    if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlight((h) => Math.max(h - 1, 0));
      return;
    }
    if (e.key === 'Enter') {
      e.preventDefault();
      const target = highlight >= 0 ? options[highlight] : options[0];
      if (target) pick(target);
    }
  };

  return (
    <div ref={containerRef} className="relative w-full">
      <div className="relative">
        <Search className="absolute left-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-stone-400 pointer-events-none" />
        <Input
          ref={inputRef}
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => options.length > 0 && setOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={locationId ? 'Buscar produto pra abrir a grade…' : 'Selecione a filial primeiro'}
          disabled={disabled || !locationId}
          className="pl-7 pr-8 h-9 text-[13px]"
          aria-label="Buscar produto pra grade"
          aria-expanded={open}
          autoComplete="off"
        />
        {loading && (
          <Loader2 className="absolute right-2 top-1/2 -translate-y-1/2 h-3.5 w-3.5 animate-spin text-stone-400" />
        )}
        {!loading && query.length > 0 && (
          <button
            type="button"
            onClick={() => { setQuery(''); setOptions([]); setOpen(false); inputRef.current?.focus(); }}
            aria-label="Limpar"
            className="absolute right-2 top-1/2 -translate-y-1/2 text-stone-400 hover:text-stone-700"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        )}
      </div>

      {open && options.length > 0 && (
        <div
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-64 overflow-auto rounded-md border border-stone-200 bg-white shadow-md"
        >
          {options.map((opt, idx) => (
            <button
              key={opt.product_id}
              type="button"
              role="option"
              aria-selected={idx === highlight}
              onMouseEnter={() => setHighlight(idx)}
              onClick={() => pick(opt)}
              className={`flex w-full items-center px-3 py-2 text-left text-[13px] ${
                idx === highlight ? 'bg-stone-100 text-stone-900' : 'hover:bg-stone-50 text-stone-700'
              }`}
            >
              <span className="truncate">{opt.label}</span>
            </button>
          ))}
        </div>
      )}

      {open && query.trim().length >= MIN_QUERY && options.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-stone-200 bg-white px-3 py-2 text-[13px] text-stone-500 shadow-md">
          Nenhum produto encontrado para "{query}".
        </div>
      )}
    </div>
  );
}
