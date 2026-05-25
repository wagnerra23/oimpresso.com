// ClienteCombobox — PR J (2026-05-25) US-FIN-024 autocomplete cliente/fornecedor.
//
// Diferente do PlanoContaCombobox (busca client-side em lista pré-carregada),
// este é server-side: busca via fetch /financeiro/unificado/buscar-cliente?q=X
// com debounce 300ms — escala pra business com 10k+ contacts.
//
// Permite seleção OU texto livre (cliente sem cadastro). Quando seleciona,
// preenche cliente_descricao automaticamente. Quando digita livre, mantém
// como texto.

import { useEffect, useRef, useState } from 'react';
import { Search, X, User } from 'lucide-react';

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
  const [activeIdx, setActiveIdx] = useState(0);
  const containerRef = useRef<HTMLDivElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const baseId = id ?? 'cliente-combobox';

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
        setActiveIdx(0);
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
      <div className="relative">
        <input
          id={baseId}
          type="text"
          role="combobox"
          aria-autocomplete="list"
          aria-expanded={open}
          aria-controls={`${baseId}-list`}
          aria-activedescendant={results[activeIdx] ? `${baseId}-opt-${activeIdx}` : undefined}
          autoFocus={autoFocus}
          disabled={disabled}
          value={value}
          onChange={(e) => onChange(e.target.value, null)}
          onFocus={() => results.length > 0 && setOpen(true)}
          onKeyDown={(e) => {
            if (! open) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); setActiveIdx((i) => Math.min(i + 1, results.length - 1)); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActiveIdx((i) => Math.max(0, i - 1)); }
            else if (e.key === 'Enter') {
              if (results[activeIdx]) { e.preventDefault(); select(results[activeIdx]); }
            }
            else if (e.key === 'Escape') { e.preventDefault(); setOpen(false); }
          }}
          placeholder={placeholder ?? 'Digite nome, telefone, CPF/CNPJ…'}
          className="w-full h-9 rounded-md border border-input bg-background px-3 pr-8 text-[13px]"
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
        <ul
          id={`${baseId}-list`}
          role="listbox"
          className="absolute z-20 left-0 right-0 mt-1 rounded-md border border-border bg-popover shadow-lg max-h-[260px] overflow-y-auto"
        >
          {results.map((c, idx) => {
            const isActive = idx === activeIdx;
            return (
              <li
                key={c.id}
                id={`${baseId}-opt-${idx}`}
                role="option"
                aria-selected={isActive}
                onClick={() => select(c)}
                onMouseEnter={() => setActiveIdx(idx)}
                className={`flex items-center gap-2 px-3 py-1.5 text-[13px] cursor-pointer ${isActive ? 'bg-accent' : ''}`}
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
              </li>
            );
          })}
        </ul>
      )}

      {value.trim().length >= 2 && open && results.length === 0 && ! loading && (
        <div className="absolute z-20 left-0 right-0 mt-1 rounded-md border border-border bg-popover shadow-lg px-3 py-2 text-[11px] text-muted-foreground">
          Sem contact cadastrado — texto livre será salvo como cliente_descricao.
        </div>
      )}
    </div>
  );
}

export default ClienteCombobox;
