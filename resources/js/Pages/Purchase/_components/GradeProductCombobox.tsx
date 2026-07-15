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
// Onda combobox (2026-07-15, ADR proposta tab-nav-canonico + ADR 0338): MIGRADO do
// hand-roll (Input + <div role="listbox"> + <button role="option"> + onKeyDown +
// highlight à mão) pro CANON do papel = Command (cmdk, @/Components/ui/command),
// usado INLINE — sub-shape async do combobox (input sempre visível, busca no
// servidor). shouldFilter={false}: o motor NÃO filtra; os itens vêm do fetch
// debounceado (a busca é server-side). O cmdk assume o input + a lista + navegação
// de teclado (↑↓ Enter) + a11y (role=combobox/listbox/option, aria-activedescendant)
// que o componente reimplementava. TODA a lógica de fetch (debounce 250ms +
// AbortController + groupByProduct + cleanLabel) e a API (props locationId/onPick/
// disabled) ficam INALTERADAS — consumidor (Purchase/Create) não muda.
//
// Não vira shared ainda (R-DS-001 — reutilização sob demanda).

import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, X } from 'lucide-react';
import {
  Command,
  CommandEmpty,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';

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
  const containerRef = useRef<HTMLDivElement>(null);

  // Busca debounceada com cancelamento de request stale (INALTERADA do hand-roll —
  // a navegação/highlight é do cmdk agora, então some o setHighlight daqui).
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
    },
    [onPick]
  );

  const podeBuscar = query.trim().length >= MIN_QUERY;

  return (
    <div ref={containerRef} className="relative w-full">
      {/* Command inline: shouldFilter=false porque a busca é server-side (os itens já
          vêm filtrados do fetch). O motor cmdk dá input + lista + teclado + a11y. */}
      <Command
        shouldFilter={false}
        className="overflow-visible rounded-md border border-input bg-background"
      >
        <div className="relative">
          <CommandInput
            value={query}
            onValueChange={setQuery}
            onFocus={() => options.length > 0 && setOpen(true)}
            onKeyDown={(e) => {
              if (e.key === 'Escape') setOpen(false);
            }}
            placeholder={locationId ? 'Buscar produto pra abrir a grade…' : 'Selecione a filial primeiro'}
            disabled={disabled || !locationId}
            aria-label="Buscar produto pra grade"
            className="text-[13px] pr-8"
          />
          {loading && (
            <Loader2 className="absolute right-2 top-1/2 -translate-y-1/2 size-3.5 animate-spin text-muted-foreground" />
          )}
          {!loading && query.length > 0 && (
            <button
              type="button"
              onClick={() => {
                setQuery('');
                setOptions([]);
                setOpen(false);
              }}
              aria-label="Limpar"
              className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            >
              <X className="size-3.5" />
            </button>
          )}
        </div>
        {open && podeBuscar && (
          <CommandList className="absolute z-50 left-0 right-0 top-full mt-1 max-h-64 rounded-md border bg-popover shadow-md">
            {!loading && <CommandEmpty>Nenhum produto encontrado para "{query}".</CommandEmpty>}
            {options.map((opt) => (
              <CommandItem
                key={opt.product_id}
                value={String(opt.product_id)}
                onSelect={() => pick(opt)}
                className="text-[13px]"
              >
                <span className="truncate">{opt.label}</span>
              </CommandItem>
            ))}
          </CommandList>
        )}
      </Command>
    </div>
  );
}
