// ClienteCombobox — PR J (2026-05-25) US-FIN-024 autocomplete cliente/fornecedor.
//
// Diferente do PlanoContaCombobox (busca client-side em lista pré-carregada),
// este é server-side: busca via fetch /financeiro/unificado/buscar-cliente?q=X
// com debounce 300ms — escala pra business com 10k+ contacts.
//
// Permite seleção OU texto livre (cliente sem cadastro). Quando seleciona,
// preenche cliente_descricao automaticamente. Quando digita livre, mantém
// como texto.
//
// Onda combobox (2026-07-15, ADR proposta tab-nav-canonico + ADR 0338): MIGRADO do
// hand-roll (<input role="combobox" aria-autocomplete> + <ul role="listbox"> +
// onKeyDown/activeIdx à mão) pro CANON do papel = Command (cmdk, @/Components/ui/
// command), usado INLINE com shouldFilter={false} — sub-shape async COM TEXTO LIVRE:
// o `value` externo É o texto do campo (cliente_descricao), o CommandInput é
// controlado por ele. Cada tecla dispara onChange(text, null); selecionar uma
// sugestão faz onChange(display, contactId). O motor cmdk assume input + lista +
// navegação de teclado (↑↓ Enter) + a11y (role/aria-activedescendant) que era feita
// à mão. A busca async (debounce 300ms) e a API (props value/onChange/...) ficam
// INALTERADAS — consumidor (TituloCreateSheet) não muda.

import { useEffect, useRef, useState } from 'react';
import { Search, X, User } from 'lucide-react';
import {
  Command,
  CommandItem,
  CommandInput,
  CommandList,
} from '@/Components/ui/command';

export interface ContactHit {
  id: number;
  name: string;
  biz: string | null;
  mobile: string | null;
  type: 'customer' | 'supplier' | 'both';
  doc: string | null;
}

interface Props {
  value: string;
  onChange: (text: string, contactId?: number | null) => void;
  id?: string;
  placeholder?: string;
  disabled?: boolean;
  autoFocus?: boolean;
}

const TYPE_LABEL: Record<ContactHit['type'], string> = {
  customer: 'Cliente',
  supplier: 'Fornecedor',
  both: 'Cliente · Fornecedor',
};

export function ClienteCombobox({ value, onChange, id, placeholder, disabled, autoFocus }: Props) {
  const [open, setOpen] = useState(false);
  const [results, setResults] = useState<ContactHit[]>([]);
  const [loading, setLoading] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const baseId = id ?? 'cliente-combobox';

  // Busca server-side debounceada (INALTERADA — a navegação/highlight é do cmdk agora,
  // então some o setActiveIdx daqui).
  useEffect(() => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (value.trim().length < 2) {
      setResults([]);
      setOpen(false);
      return;
    }
    debounceRef.current = setTimeout(async () => {
      setLoading(true);
      try {
        const r = await fetch(`/financeiro/unificado/buscar-cliente?q=${encodeURIComponent(value)}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (! r.ok) return;
        const data = await r.json();
        setResults(data.contacts ?? []);
        if ((data.contacts ?? []).length > 0) setOpen(true);
      } catch { /* silent */ }
      finally { setLoading(false); }
    }, 300);
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [value]);

  useEffect(() => {
    if (! open) return;
    const handler = (e: MouseEvent) => {
      if (containerRef.current && ! containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [open]);

  const select = (c: ContactHit) => {
    const display = c.biz && c.biz !== c.name ? `${c.name} · ${c.biz}` : c.name;
    onChange(display, c.id);
    setOpen(false);
  };

  return (
    <div className="relative" ref={containerRef}>
      {/* Command inline: shouldFilter=false porque a busca é server-side E porque
          texto livre (não-sugestão) é um valor VÁLIDO — o cmdk não deve filtrar/
          esconder nada. O `value` externo controla o CommandInput. */}
      <Command
        shouldFilter={false}
        className="overflow-visible rounded-md border border-input bg-background"
      >
        <div className="relative">
          <CommandInput
            id={baseId}
            value={value}
            onValueChange={(text) => onChange(text, null)}
            onFocus={() => results.length > 0 && setOpen(true)}
            onKeyDown={(e) => {
              if (e.key === 'Escape') setOpen(false);
            }}
            disabled={disabled}
            autoFocus={autoFocus}
            placeholder={placeholder ?? 'Digite nome, telefone, CPF/CNPJ…'}
            className="text-[13px] pr-8"
          />
          {loading && (
            <Search size={13} className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground animate-pulse" />
          )}
          {value && ! loading && (
            <button
              type="button"
              aria-label="Limpar"
              onClick={() => { onChange('', null); setResults([]); setOpen(false); }}
              className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
            >
              <X size={13} />
            </button>
          )}
        </div>

        {open && results.length > 0 && (
          <CommandList className="absolute z-20 left-0 right-0 top-full mt-1 max-h-[260px] rounded-md border border-border bg-popover shadow-lg">
            {results.map((c) => (
              <CommandItem
                key={c.id}
                value={String(c.id)}
                onSelect={() => select(c)}
                className="gap-2 text-[13px]"
              >
                <User size={14} className="text-muted-foreground shrink-0" />
                <div className="flex-1 min-w-0">
                  <div className="truncate">{c.name}{c.biz && c.biz !== c.name ? <span className="text-muted-foreground"> · {c.biz}</span> : null}</div>
                  <div className="text-[11px] text-muted-foreground truncate">
                    {TYPE_LABEL[c.type]}
                    {c.mobile ? ` · ${c.mobile}` : ''}
                    {c.doc ? ` · ${c.doc}` : ''}
                  </div>
                </div>
              </CommandItem>
            ))}
          </CommandList>
        )}

        {value.trim().length >= 2 && open && results.length === 0 && ! loading && (
          <div className="absolute z-20 left-0 right-0 top-full mt-1 rounded-md border border-border bg-popover shadow-lg px-3 py-2 text-[11px] text-muted-foreground">
            Sem contact cadastrado — texto livre será salvo como cliente_descricao.
          </div>
        )}
      </Command>
    </div>
  );
}

export default ClienteCombobox;
