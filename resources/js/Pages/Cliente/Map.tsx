// W1-B3 Cliente/Map — split-screen mapa de clientes Inertia/React (MWART F3).
// Divergence ADR 0149: split-screen com mapa lateral — layout divergente do Index lista.
// Mapa = iframe OpenStreetMap embed (sem chave de API), ancorado em prototipo-ui/cowork/cliente-mapa.jsx.
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

// `contacts.position` e varchar(191) que guarda "lat, lng" — provado pelo consumidor
// legado (`resources/views/contact/contact_map.blade.php:93,101`: interpola a coluna crua
// dentro de um array JS e le `contact[1]`/`contact[2]` como lat/lng), nao por suposicao.
// Parser defensivo: so aceita DOIS numeros finitos dentro da faixa geografica. Qualquer
// outra coisa (endereco livre, lixo, vazio) devolve null e a tela cai no empty state que
// ja existia — a troca de provedor nao pode inventar mapa pra dado que nao e coordenada.
function coordsDe(position: string | null): { lat: number; lon: number } | null {
  if (!position) return null;
  const partes = position.split(',');
  if (partes.length !== 2) return null;
  const [latRaw, lonRaw] = partes;
  if (latRaw === undefined || lonRaw === undefined) return null;
  const lat = Number(latRaw.trim());
  const lon = Number(lonRaw.trim());
  if (!Number.isFinite(lat) || !Number.isFinite(lon)) return null;
  if (lat < -90 || lat > 90 || lon < -180 || lon > 180) return null;
  return { lat, lon };
}

// OSM embed nao pede chave de API — e o que a ancora de design usa
// (`prototipo-ui/cowork/cliente-mapa.jsx:42,88`) e o que o anti-hook do charter pede
// ("nao envia lat,lng pra Google Maps"). `bbox` = janela pequena em volta do ponto;
// `marker` finca o pin. O DELTA equivale a ~1km de lado, perto do zoom 16 do link.
const BBOX_DELTA = 0.0045;
function osmEmbedSrc({ lat, lon }: { lat: number; lon: number }): string {
  const bbox = [lon - BBOX_DELTA, lat - BBOX_DELTA, lon + BBOX_DELTA, lat + BBOX_DELTA].join(',');
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat},${lon}`;
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

  const selectedCoords = useMemo(() => coordsDe(selected?.position ?? null), [selected]);

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
                  {selectedCoords ? (
                    <iframe
                      title={`Mapa de ${selected?.name ?? 'cliente'}`}
                      className="w-full h-full border-0"
                      src={osmEmbedSrc(selectedCoords)}
                      loading="lazy"
                      referrerPolicy="no-referrer-when-downgrade"
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
                    {selectedCoords && (
                      <a
                        href={`https://www.openstreetmap.org/?mlat=${selectedCoords.lat}&mlon=${selectedCoords.lon}#map=16/${selectedCoords.lat}/${selectedCoords.lon}`}
                        target="_blank"
                        rel="noreferrer"
                        className="text-xs text-primary hover:underline"
                      >
                        Abrir no mapa completo →
                      </a>
                    )}
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
