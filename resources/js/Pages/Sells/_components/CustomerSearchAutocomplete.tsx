// US-SELL-CUST-SEARCH — busca de cliente na Sells/Create.
//
// Endpoint legado: GET /contacts/customers?q=TERM (ContactController@getCustomers).
// Retorna array com { id, text, mobile, balance, ... }. Walk-in customer fica
// como default no Create — esse autocomplete só TROCA pra um cliente real.
//
// Dor 5 (Larissa, auditoria 2026-05-27): expor `balance` no dropdown + abaixo
// do input após select. Larissa não enxergava saldo devedor antes da venda.
// Convenção UPOS: backend devolve `balance` (string|number, R$). Quando >0
// (CRM frame: cliente nos deve) → badge text-destructive.
//
// Não vira shared ainda — extrair pra @/Components/shared só quando 2ª tela usar.

import { useState, useEffect, useRef } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Search, Loader2, X, UserPlus } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import QuickAddCustomerSheet from './QuickAddCustomerSheet';

/**
 * ADR 0251 — veículo do catálogo do cliente (`vehicles`, Modules/OficinaAuto).
 * Alimenta o seletor de veículo na venda direta de oficina (Sells/Create).
 */
export interface VehicleOption {
  id: number;
  plate: string;
  secondary_plate?: string | null;
  vehicle_type?: string | null;
}

export interface CustomerSearchResult {
  id: number;
  text: string;
  mobile?: string | null;
  city?: string | null;
  /** Saldo devedor cliente (R$). >0 = devedor — exibe badge vermelho. */
  balance?: number | string | null;
  // R8 (2026-05-28) — campos pra auto-aplicar prazo + grupo de preço ao trocar cliente.
  // Backend ContactController@getCustomers (linhas 2150-2176) já devolve todos.
  // Bug 2 hotfix Larissa 2026-05-13 era: "preço diferenciado" — cliente VIP com
  // grupo de preço cobrava balcão. Fix: ao onSelect, parent reaplica.
  /** Grupo de preço (cg.selling_price_group_id via customer_group join). */
  selling_price_group_id?: number | null;
  /** Prazo de pagamento (qtd unidades) — pré-fill no campo "pay_term_number". */
  pay_term_number?: number | string | null;
  /** Tipo prazo — 'days' | 'months'. */
  pay_term_type?: 'days' | 'months' | string | null;
  /** Endereço entrega — pré-fill no campo shipping. */
  shipping_address?: string | null;
  /** ADR 0251 — veículos do cliente (catálogo OficinaAuto), pro seletor na venda. */
  vehicles?: VehicleOption[] | null;
}

/**
 * Normaliza balance do payload (controller pode mandar string MySQL
 * decimal, número ou null) pra Number. NaN/null → 0.
 */
const parseBalance = (raw: number | string | null | undefined): number => {
  if (raw === null || raw === undefined || raw === '') return 0;
  const n = typeof raw === 'number' ? raw : parseFloat(String(raw));
  return Number.isFinite(n) ? n : 0;
};

const BRL = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

interface Props {
  defaultName: string;
  onSelect: (customer: CustomerSearchResult) => void;
  onClear?: () => void;
  placeholder?: string;
  disabled?: boolean;
  /** Selecionar cliente externamente (ex: via postMessage da aba de cadastro). */
  forcedValue?: CustomerSearchResult | null;
}

const DEBOUNCE_MS = 250;
const MIN_QUERY_LENGTH = 2;

export default function CustomerSearchAutocomplete({
  defaultName,
  onSelect,
  onClear,
  placeholder = 'Buscar cliente por nome, CPF/CNPJ ou telefone…',
  disabled = false,
  forcedValue,
}: Props) {
  const [query, setQuery] = useState('');
  // Onda 4 (ADR 0211) — termo debounceado que alimenta a queryKey do useQuery.
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [selectedLabel, setSelectedLabel] = useState<string>(defaultName);
  const [selectedCustomer, setSelectedCustomer] = useState<CustomerSearchResult | null>(null);
  const [results, setResults] = useState<CustomerSearchResult[]>([]);
  const [open, setOpen] = useState(false);
  const [highlightedIndex, setHighlightedIndex] = useState<number>(-1);
  // R6 (2026-05-27) — Sheet in-place pra quick-add cliente (Dor 4 Larissa).
  // Substitui nova aba + postMessage. Quando user clica "Cadastrar 'X'", abre
  // Sheet lateral com 5 campos. Após salvar, fecha + seleciona no autocomplete.
  const [quickAddOpen, setQuickAddOpen] = useState(false);
  const [quickAddPrefill, setQuickAddPrefill] = useState('');
  const containerRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Seleção externa via postMessage (aba de cadastro retorna contato criado).
  useEffect(() => {
    if (forcedValue) handleSelect(forcedValue);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [forcedValue]);

  // Onda 4 (ADR 0211) — debounce do termo. Só atualiza a queryKey; o fetch +
  // cancelamento de request stale agora são responsabilidade do TanStack Query.
  useEffect(() => {
    if (query.length < MIN_QUERY_LENGTH) {
      setDebouncedQuery('');
      return;
    }
    const handle = setTimeout(() => setDebouncedQuery(query), DEBOUNCE_MS);
    return () => clearTimeout(handle);
  }, [query]);

  // Onda 4 (ADR 0211) — useQuery substitui o setTimeout+fetch manual. Este
  // componente NÃO tinha AbortController; useQuery resolve cancelamento de
  // request stale nativamente via `signal` injetado no queryFn (R7 raiz).
  //  - staleTime/gcTime/retry herdam o default do QueryClient (app.tsx)
  const customerQuery = useQuery({
    queryKey: ['customers', debouncedQuery],
    queryFn: async ({ signal }): Promise<CustomerSearchResult[]> => {
      const params = new URLSearchParams({ q: debouncedQuery });
      const res = await fetch(`/contacts/customers?${params.toString()}`, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        signal,
      });
      if (!res.ok) return [];
      const data = await res.json();
      const list: CustomerSearchResult[] = Array.isArray(data) ? data : (data?.data ?? []);
      return list.slice(0, 10);
    },
    enabled: debouncedQuery.length >= MIN_QUERY_LENGTH,
  });

  const loading = customerQuery.isFetching;

  // Espelha o resultado do useQuery em `results` (consumido pelo dropdown +
  // navegação por teclado). Abre o dropdown quando há dados frescos.
  useEffect(() => {
    if (customerQuery.data === undefined) return;
    setResults(customerQuery.data);
    setOpen(true);
    setHighlightedIndex(-1);
  }, [customerQuery.data]);

  // Limpa results + fecha dropdown quando o termo cai abaixo do mínimo.
  useEffect(() => {
    if (debouncedQuery.length < MIN_QUERY_LENGTH) {
      setResults([]);
      setOpen(false);
    }
  }, [debouncedQuery]);

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
        const selected = results[idx];
        if (selected) handleSelect(selected);
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
    setSelectedCustomer(null);
    onClear?.();
    inputRef.current?.focus();
  };

  const handleSelect = (customer: CustomerSearchResult) => {
    onSelect(customer);
    setSelectedLabel(customer.text);
    setSelectedCustomer(customer);
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
          onChange={(e) => {
            // Wagner 2026-05-27 HOTFIX: quando o value mostra `selectedLabel`
            // ("Cliente padrão" antes da primeira busca), o user digitava
            // e o texto era CONCATENADO ao label visual (e.target.value
            // virava "Cliente padrãowagner"), disparando fetch quebrado
            // `q=Cliente+padr%C3%A3owagner`. Smoke prod Wagner 2026-05-27
            // 10:25 BRT via Chrome MCP detectou o bug.
            // Fix: ao digitar com selectedLabel ainda no campo, extrair APENAS
            // o suffix adicionado pelo usuário.
            const raw = e.target.value;
            if (query.length === 0 && raw.startsWith(selectedLabel) && raw.length > selectedLabel.length) {
              setQuery(raw.slice(selectedLabel.length));
            } else {
              setQuery(raw);
            }
          }}
          onFocus={(e) => {
            // Wagner 2026-05-27 HOTFIX: seleciona conteúdo no focus pra
            // primeira digitação substituir o `selectedLabel` (defesa
            // em profundidade pro caso de o user digitar do meio).
            if (query.length === 0) {
              e.currentTarget.select();
            }
            if (results.length > 0) setOpen(true);
          }}
          onKeyDown={handleKeyDown}
          placeholder={placeholder}
          disabled={disabled}
          // variant=shadcn pra permitir pl-9 (ícone-prefix). Default cowork tem
          // padding hardcoded `0 8px` no .cw-input que sobrepõe utilities Tailwind
          // — ícone Search fica em cima do texto. ADR UI-0015 default cowork 2026-05-27.
          variant="shadcn"
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
          {results.map((c, index) => {
            const cBalance = parseBalance(c.balance);
            const isDevedor = cBalance > 0;
            return (
              <button
                key={c.id}
                type="button"
                role="option"
                aria-selected={index === highlightedIndex}
                onClick={() => handleSelect(c)}
                className={`flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm focus:outline-none ${
                  index === highlightedIndex
                    ? 'bg-accent text-accent-foreground'
                    : 'hover:bg-accent hover:text-accent-foreground'
                }`}
              >
                <div className="flex flex-col min-w-0 flex-1">
                  <span className="font-medium truncate">{c.text}</span>
                  {(c.mobile || c.city) && (
                    <span className="text-xs text-muted-foreground truncate">
                      {[c.mobile, c.city].filter(Boolean).join(' · ')}
                    </span>
                  )}
                </div>
                {isDevedor && (
                  <span
                    className="shrink-0 text-xs font-semibold text-destructive whitespace-nowrap"
                    data-testid="customer-balance-badge"
                    aria-label={`Cliente devedor: ${BRL.format(cBalance)}`}
                  >
                    {BRL.format(cBalance)} devedor
                  </span>
                )}
              </button>
            );
          })}
        </div>
      )}

      {/* Dor 5 — após selecionar cliente, expõe saldo devedor pra Larissa
          decidir se cobra/avisa antes de prosseguir com a venda. */}
      {selectedCustomer && !open && parseBalance(selectedCustomer.balance) > 0 && (
        <p
          className="mt-1 text-xs font-medium text-destructive"
          data-testid="customer-balance-hint"
        >
          Cliente vencido: {BRL.format(parseBalance(selectedCustomer.balance))}
        </p>
      )}

      {open && query.length >= MIN_QUERY_LENGTH && results.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover shadow-md">
          <div className="px-3 py-2 text-sm text-muted-foreground">
            Nenhum cliente encontrado para "{query}".
          </div>
          {/* R6 (2026-05-27) Dor 4 Larissa — cadastro in-place via Sheet lateral.
              Substitui o legacy `<a target=_blank href=/contacts/create-page>`
              que abria nova aba e dependia de postMessage pra devolver. Sheet
              fica no MESMO contexto da venda (draft preservado). */}
          <button
            type="button"
            onClick={() => {
              setQuickAddPrefill(query);
              setQuickAddOpen(true);
              setOpen(false);
            }}
            className="flex w-full items-center gap-2 border-t border-border bg-primary/5 px-3 py-2 text-left text-sm font-medium text-primary hover:bg-primary/10 focus:bg-primary/10 focus:outline-none"
            data-testid="customer-cadastrar-inline"
          >
            <UserPlus className="h-4 w-4" />
            Cadastrar "{query}" como novo cliente
          </button>
        </div>
      )}

      {/* QuickAdd Sheet — montado sempre, controlado por `quickAddOpen`. */}
      <QuickAddCustomerSheet
        open={quickAddOpen}
        onClose={() => setQuickAddOpen(false)}
        prefillName={quickAddPrefill}
        onCreated={(customer) => {
          // Mesmo fluxo de seleção do dropdown — mantém compat com `forcedValue`
          // externo (Sells/Create.tsx ainda escuta postMessage da aba legacy
          // até retirada plena; ambos caminhos chegam aqui).
          handleSelect(customer);
        }}
      />
    </div>
  );
}
