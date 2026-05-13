// US-SELL-CUST-SEARCH — busca de cliente na Sells/Create.
//
// Endpoint legado: GET /contacts/customers?q=TERM (ContactController@getCustomers).
// Retorna array com { id, text, mobile, ... }. Walk-in customer fica como
// default no Create — esse autocomplete só TROCA pra um cliente real.
//
// Não vira shared ainda — extrair pra @/Components/shared só quando 2ª tela usar.

import { useState, useEffect, useRef } from 'react';
import { Search, Loader2, X, UserPlus } from 'lucide-react';
import { Input } from '@/Components/ui/input';

export interface CustomerSearchResult {
  id: number;
  text: string;
  mobile?: string | null;
  city?: string | null;
}

interface Props {
  defaultName: string;
  onSelect: (customer: CustomerSearchResult) => void;
  onClear?: () => void;
  placeholder?: string;
  disabled?: boolean;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY_LENGTH = 2;

export default function CustomerSearchAutocomplete({
  defaultName,
  onSelect,
  onClear,
  placeholder = 'Buscar cliente por nome, CPF/CNPJ ou telefone…',
  disabled = false,
}: Props) {
  const [query, setQuery] = useState('');
  const [selectedLabel, setSelectedLabel] = useState<string>(defaultName);
  const [results, setResults] = useState<CustomerSearchResult[]>([]);
  const [loading, setLoading] = useState(false);
  const [open, setOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState<number>(-1);
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (query.length < MIN_QUERY_LENGTH) {
      setResults([]);
      setOpen(false);
      return;
    }

    const handle = setTimeout(async () => {
      setLoading(true);
      try {
        const params = new URLSearchParams({ q: query });
        const res = await fetch(`/contacts/customers?${params.toString()}`, {
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

        const data = await res.json();
        const list: CustomerSearchResult[] = Array.isArray(data) ? data : (data?.data ?? []);
        setResults(list.slice(0, 10));
        setOpen(true);
        setHighlightedIndex(-1);
      } catch {
        setResults([]);
      } finally {
        setLoading(false);
      }
    }, DEBOUNCE_MS);

    return () => clearTimeout(handle);
  }, [query]);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape') {
      setOpen(false);
      inputRef.current?.blur();
      return;
    }

    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!open && results.length > 0) setOpen(true);
      setHighlightedIndex((prev) => Math.min(prev + 1, results.length - 1));
      return;
    }

    if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlightedIndex((prev) => Math.max(prev - 1, -1));
      return;
    }

    if (e.key === 'Enter') {
      // Sempre bloqueia submit do form quando dropdown está ativo ou tem query
      e.preventDefault();
      if (open && results.length > 0) {
        const idx = highlightedIndex >= 0 ? highlightedIndex : 0;
        handleSelect(results[idx]);
      }
      // Se não há resultados, não navega pro cadastro via Enter —
      // o link de cadastro é clicável mas Enter não auto-abre.
      return;
    }
  };

  const handleClear = () => {
    setQuery('');
    setResults([]);
    setOpen(false);
    setHighlightedIndex(-1);
    setSelectedLabel(defaultName);
    onClear?.();
    inputRef.current?.focus();
  };

  const handleSelect = (customer: CustomerSearchResult) => {
    onSelect(customer);
    setSelectedLabel(customer.text);
    setQuery('');
    setResults([]);
    setOpen(false);
    setHighlightedIndex(-1);
  };

  return (
    <div ref={containerRef} className="relative w-full">
      <div className="relative">
        <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
        <Input
          ref={inputRef}
          type="text"
          value={query.length > 0 ? query : selectedLabel}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          className="pl-9 pr-9"
          aria-label="Buscar cliente"
          aria-expanded={open}
          aria-haspopup="listbox"
          autoComplete="off"
        />
        {loading && (
          <Loader2 className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-muted-foreground" />
        )}
        {!loading && (query.length > 0 || selectedLabel !== defaultName) && (
          <button
            type="button"
            onClick={handleClear}
            aria-label="Limpar cliente"
            className="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground hover:text-foreground"
          >
            <X className="h-4 w-4" />
          </button>
        )}
      </div>

      {open && results.length > 0 && (
        <div
          role="listbox"
          className="absolute z-50 mt-1 w-full max-h-72 overflow-auto rounded-md border border-border bg-popover shadow-md"
        >
          {results.map((c, index) => (
            <button
              key={c.id}
              type="button"
              role="option"
              aria-selected={index === highlightedIndex}
              onClick={() => handleSelect(c)}
              className={`flex w-full items-center justify-between px-3 py-2 text-left text-sm focus:outline-none ${
                index === highlightedIndex
                  ? 'bg-accent text-accent-foreground'
                  : 'hover:bg-accent hover:text-accent-foreground'
              }`}
            >
              <div className="flex flex-col min-w-0">
                <span className="font-medium truncate">{c.text}</span>
                {(c.mobile || c.city) && (
                  <span className="text-xs text-muted-foreground truncate">
                    {[c.mobile, c.city].filter(Boolean).join(' · ')}
                  </span>
                )}
              </div>
            </button>
          ))}
        </div>
      )}

      {open && query.length >= MIN_QUERY_LENGTH && results.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
          <div className="px-3 py-2 text-sm text-muted-foreground">
            Nenhum cliente encontrado para "{query}".
          </div>
          {/* Cadastro inline — abre página completa de criação de contato.
              Usa /contacts/create-page (rota standalone com layout completo)
              em vez de /contacts/create (fragmento modal sem CSS). */}
          <a
            href={`/contacts/create-page?type=customer&prefill_name=${encodeURIComponent(query)}`}
            target="_blank"
            rel="noopener noreferrer"
            className="flex w-full items-center gap-2 border-t border-border bg-primary/5 px-3 py-2 text-left text-sm font-medium text-primary hover:bg-primary/10 focus:bg-primary/10 focus:outline-none"
            data-testid="customer-cadastrar-inline"
          >
            <UserPlus className="h-4 w-4" />
            Cadastrar "{query}" como novo cliente
          </a>
        </div>
      )}
    </div>
  );
}
