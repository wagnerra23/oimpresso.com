// Onda Final.A — Contact picker dropdown no header Show.
// Permite trocar contato sem voltar pra listagem.
// Paridade com Blade legacy: `<select id="sr_location_id">` que faz window.location = '/contacts/' + id.

import { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Search, ChevronDown } from 'lucide-react';

export interface ContactDropdownItem {
  id: number;
  name: string;
  contact_id: string | null;
  supplier_business_name: string | null;
}

export interface ContactPickerProps {
  currentContactId: number;
  contacts?: ContactDropdownItem[];
}

export default function ContactPicker({ currentContactId, contacts }: ContactPickerProps) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');

  const filtered = useMemo(() => {
    if (!contacts) return [];
    const q = query.trim().toLowerCase();
    if (!q) return contacts.slice(0, 50);
    return contacts
      .filter((c) => {
        const haystack = [c.name, c.supplier_business_name, c.contact_id].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(q);
      })
      .slice(0, 50);
  }, [contacts, query]);

  const handleSelect = (id: number) => {
    if (id === currentContactId) {
      setOpen(false);
      return;
    }
    router.visit(`/contacts/${id}`, { preserveScroll: false });
  };

  if (!contacts) {
    return (
      <button
        type="button"
        disabled
        className="inline-flex items-center gap-1.5 rounded-md border border-border bg-muted/30 px-2.5 py-1.5 text-xs text-muted-foreground"
        aria-label="Trocar contato (carregando)"
        data-testid="contact-picker-loading"
      >
        Trocar contato
        <ChevronDown size={12} className="opacity-50" />
      </button>
    );
  }

  return (
    <div className="relative" data-testid="contact-picker-root">
      <button
        type="button"
        onClick={() => setOpen(!open)}
        className="inline-flex items-center gap-1.5 rounded-md border border-border bg-background px-2.5 py-1.5 text-xs text-foreground hover:bg-muted/40 transition-colors"
        aria-label="Trocar contato"
        aria-expanded={open}
        data-testid="contact-picker-trigger"
      >
        Trocar contato
        <ChevronDown size={12} className={open ? 'rotate-180 transition-transform' : 'transition-transform'} />
      </button>

      {open && (
        <>
          <div
            className="fixed inset-0 z-40"
            onClick={() => setOpen(false)}
            aria-hidden
          />
          <div
            className="absolute right-0 top-full mt-1 z-50 w-80 rounded-md border border-border bg-background shadow-lg overflow-hidden"
            role="dialog"
            aria-label="Selecionar contato"
            data-testid="contact-picker-dropdown"
          >
            <div className="border-b border-border p-2">
              <div className="relative">
                <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                  type="search"
                  autoFocus
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Buscar por nome ou código..."
                  className="w-full rounded border border-border bg-background pl-8 pr-2 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-blue-500"
                  data-testid="contact-picker-search"
                />
              </div>
            </div>
            <div className="max-h-72 overflow-y-auto">
              {filtered.length === 0 ? (
                <div className="p-4 text-center text-xs text-muted-foreground" data-testid="contact-picker-empty">
                  Nenhum contato encontrado.
                </div>
              ) : (
                <ul className="py-1">
                  {filtered.map((c) => {
                    const isCurrent = c.id === currentContactId;
                    return (
                      <li key={c.id}>
                        <button
                          type="button"
                          onClick={() => handleSelect(c.id)}
                          disabled={isCurrent}
                          className={
                            'w-full text-left px-3 py-2 text-xs hover:bg-muted/40 transition-colors flex items-center justify-between ' +
                            (isCurrent ? 'bg-blue-50 dark:bg-blue-950/30 cursor-default' : '')
                          }
                          data-testid={`contact-picker-item-${c.id}`}
                        >
                          <div className="flex-1 min-w-0">
                            <div className="font-medium text-foreground truncate">
                              {c.name}
                              {c.supplier_business_name && (
                                <span className="text-muted-foreground"> · {c.supplier_business_name}</span>
                              )}
                            </div>
                            {c.contact_id && (
                              <div className="text-[10px] text-muted-foreground">({c.contact_id})</div>
                            )}
                          </div>
                          {isCurrent && (
                            <span className="text-[10px] text-blue-700 dark:text-blue-400 font-semibold uppercase tracking-wider">
                              atual
                            </span>
                          )}
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
            <div className="border-t border-border px-3 py-1.5 text-[10px] text-muted-foreground">
              {contacts.length > 50 ? `Exibindo 50 de ${contacts.length} contatos. Digite pra filtrar.` : `${contacts.length} contato${contacts.length === 1 ? '' : 's'}`}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
