// W1-B3 Cliente/Map — split-screen mapa de clientes Inertia/React (MWART F3).
// Divergence ADR 0149: split-screen com mapa lateral — layout divergente do Index lista.
// Mapa renderizado server-side via lib mPDF/Leaflet OR placeholder até Wagner aprovar lib.
// Backend: ContactController::contactMap() — Inertia::render dual via config('mwart.cliente_map.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { useMemo, useState, type ReactNode } from 'react';
import { ChevronLeft, MapPin, Search } from 'lucide-react';
import { Input } from '@/Components/ui/input';

interface MapContact {
  id: number;
  name: string;
  position: string | null;
  city: string | null;
  state: string | null;
  mobile: string | null;
}

interface ClienteMapPageProps {
  contacts: MapContact[];
  all_contacts: MapContact[];
}

export default function ClienteMap(props: ClienteMapPageProps) {
  const [search, setSearch] = useState('');
  const [selectedId, setSelectedId] = useState<number | null>(
    props.contacts[0]?.id ?? null,
  );

  const filtered = useMemo(() => {
    if (!search) return props.all_contacts;
    const q = search.toLowerCase();
    return props.all_contacts.filter((c) =>
      c.name.toLowerCase().includes(q) ||
      (c.city ?? '').toLowerCase().includes(q),
    );
  }, [search, props.all_contacts]);

  const selected = useMemo(
    () => props.all_contacts.find((c) => c.id === selectedId) ?? null,
    [props.all_contacts, selectedId],
  );

  return (
    <div className="flex-1 bg-muted/30">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-7xl">
          <div className="flex items-center gap-3 mb-2">
            <a
              href="/contacts/customer"
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para clientes
            </a>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Mapa de clientes</h1>
          <p className="text-sm text-muted-foreground mt-1">
            {props.contacts.length} cliente{props.contacts.length === 1 ? '' : 's'} com posição registrada de {props.all_contacts.length} total.
          </p>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-7xl">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          <aside className="md:col-span-1 space-y-3">
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground pointer-events-none" />
              <Input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Buscar cliente ou cidade…"
                className="cw-input-icon-left"
              />
            </div>

            <div className="rounded-lg border border-border bg-background overflow-hidden max-h-[calc(100vh-16rem)] overflow-y-auto">
              {filtered.length === 0 ? (
                <div className="p-6 text-center text-xs text-muted-foreground">
                  Nenhum cliente encontrado.
                </div>
              ) : (
                <ul className="divide-y divide-border">
                  {filtered.map((contact) => {
                    const hasPosition = Boolean(contact.position);
                    const isSelected = contact.id === selectedId;
                    return (
                      <li key={contact.id}>
                        <button
                          type="button"
                          onClick={() => setSelectedId(contact.id)}
                          className={
                            'w-full text-left p-3 transition-colors ' +
                            (isSelected ? 'bg-primary/10' : 'hover:bg-muted/40')
                          }
                          disabled={!hasPosition}
                        >
                          <div className="flex items-start gap-2">
                            <MapPin
                              size={14}
                              className={
                                hasPosition
                                  ? isSelected
                                    ? 'text-primary mt-0.5 flex-shrink-0'
                                    : 'text-success mt-0.5 flex-shrink-0'
                                  : 'text-muted-foreground/40 mt-0.5 flex-shrink-0'
                              }
                            />
                            <div className="flex-1 min-w-0">
                              <div className="text-sm font-medium text-foreground truncate">{contact.name}</div>
                              <div className="text-xs text-muted-foreground truncate">
                                {[contact.city, contact.state].filter(Boolean).join(', ') || (hasPosition ? '—' : 'Sem posição')}
                              </div>
                            </div>
                          </div>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              )}
            </div>
          </aside>

          <div className="md:col-span-2">
            <div className="rounded-lg border border-border bg-background overflow-hidden h-[calc(100vh-12rem)]">
              <div className="h-full flex flex-col">
                <div className="px-4 py-3 border-b border-border bg-muted/30">
                  <h3 className="text-sm font-semibold text-foreground">
                    {selected?.name ?? 'Selecione um cliente'}
                  </h3>
                  {selected && (
                    <p className="text-xs text-muted-foreground mt-0.5">
                      {[selected.city, selected.state].filter(Boolean).join(', ') || 'Sem localização cadastrada'}
                    </p>
                  )}
                </div>

                <div className="flex-1 flex items-center justify-center bg-gradient-to-br from-stone-50 to-stone-100 dark:from-stone-900 dark:to-stone-800 relative">
                  {selected?.position ? (
                    <iframe
                      title={`Mapa de ${selected.name}`}
                      className="w-full h-full border-0"
                      src={`https://maps.google.com/maps?q=${encodeURIComponent(selected.position)}&output=embed`}
                      loading="lazy"
                    />
                  ) : (
                    <div className="text-center text-muted-foreground p-6">
                      <MapPin size={32} className="mx-auto mb-2 opacity-40" />
                      <p className="text-sm">
                        {selected ? 'Cliente sem coordenadas registradas' : 'Selecione um cliente na lista'}
                      </p>
                      <p className="text-xs mt-1">
                        {selected && 'Edite o cadastro pra adicionar latitude/longitude.'}
                      </p>
                    </div>
                  )}
                </div>

                {selected && (
                  <div className="px-4 py-3 border-t border-border bg-background flex items-center gap-2">
                    <a
                      href={`/contacts/${selected.id}`}
                      className="text-xs text-primary hover:underline"
                    >
                      Ver detalhes →
                    </a>
                    {selected.mobile && (
                      <span className="text-xs text-muted-foreground">
                        • {selected.mobile}
                      </span>
                    )}
                  </div>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

ClienteMap.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
