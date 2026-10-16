// SupplierCombobox — busca de fornecedor da nova compra.
//
// Por que existe: o campo Fornecedor da Purchase/Create era um <Input> "ID do fornecedor"
// livre. Quem lança compra não sabe o id numérico do contato; digitar o nome mandava texto
// no contact_id e o store() caía no "algo deu errado" (reclamação ROTA LIVRE 2026-10-16).
//
// Endpoint: /purchases/get_suppliers?q= (o mesmo que a Blade usa no select2) — já filtra
// por business_id da sessão, ativos e só fornecedores. Devolve { id, text, business_name,
// contact_id }. Mesmo molde do GradeProductCombobox ao lado: Command inline, busca
// server-side com debounce + AbortController.

import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, X } from 'lucide-react';
import {
  Command,
  CommandEmpty,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/Components/ui/command';

interface SupplierRow {
  id: number;
  text: string;
  business_name: string | null;
  contact_id: string | null;
}

export interface PickedSupplier {
  id: number;
  label: string;
}

interface Props {
  /** Fornecedor já escolhido (mostra o nome no lugar da busca). */
  value: PickedSupplier | null;
  onChange: (supplier: PickedSupplier | null) => void;
  disabled?: boolean;
  invalid?: boolean;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY = 2;

function rotulo(r: SupplierRow): string {
  const nome = (r.text || r.business_name || '').trim();
  return r.business_name && r.business_name !== nome ? `${nome} — ${r.business_name}` : nome;
}

export default function SupplierCombobox({ value, onChange, disabled = false, invalid = false }: Props) {
  const [query, setQuery] = useState('');
  const [options, setOptions] = useState<PickedSupplier[]>([]);
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

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
        const params = new URLSearchParams({ q: query.trim() });
        const res = await fetch(`/purchases/get_suppliers?${params.toString()}`, {
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin',
          signal: controller.signal,
        });
        if (!res.ok) {
          setOptions([]);
          return;
        }
        const data = (await res.json()) as SupplierRow[];
        setOptions((Array.isArray(data) ? data : []).slice(0, 15).map((r) => ({ id: r.id, label: rotulo(r) })));
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
  }, [query]);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const pick = useCallback(
    (s: PickedSupplier) => {
      onChange(s);
      setQuery('');
      setOptions([]);
      setOpen(false);
    },
    [onChange]
  );

  if (value) {
    return (
      <div className="mt-1 flex h-9 items-center gap-2 rounded-md border border-input bg-background px-3 text-[13px]">
        <span className="flex-1 truncate" data-testid="fornecedor-escolhido">{value.label}</span>
        <button
          type="button"
          onClick={() => onChange(null)}
          disabled={disabled}
          aria-label="Trocar fornecedor"
          className="text-muted-foreground hover:text-foreground"
        >
          <X className="size-3.5" />
        </button>
      </div>
    );
  }

  const podeBuscar = query.trim().length >= MIN_QUERY;

  return (
    <div ref={containerRef} className="relative mt-1 w-full">
      <Command
        shouldFilter={false}
        className={`overflow-visible rounded-md border bg-background ${invalid ? 'border-destructive' : 'border-input'}`}
      >
        <div className="relative">
          <CommandInput
            id="contact_id"
            value={query}
            onValueChange={setQuery}
            onFocus={() => options.length > 0 && setOpen(true)}
            onKeyDown={(e) => {
              if (e.key === 'Escape') setOpen(false);
            }}
            placeholder="Buscar fornecedor pelo nome…"
            disabled={disabled}
            aria-label="Fornecedor"
            className="text-[13px] pr-8"
          />
          {loading && (
            <Loader2 className="absolute right-2 top-1/2 -translate-y-1/2 size-3.5 animate-spin text-muted-foreground" />
          )}
        </div>
        {open && podeBuscar && (
          <CommandList className="absolute z-50 left-0 right-0 top-full mt-1 max-h-64 rounded-md border bg-popover shadow-md">
            {!loading && <CommandEmpty>Nenhum fornecedor encontrado para "{query}".</CommandEmpty>}
            {options.map((opt) => (
              <CommandItem key={opt.id} value={String(opt.id)} onSelect={() => pick(opt)} className="text-[13px]">
                <span className="truncate">{opt.label}</span>
              </CommandItem>
            ))}
          </CommandList>
        )}
      </Command>
    </div>
  );
}
