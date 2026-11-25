// @memcofre
//   tela: /fiscal/eventos
//   module: Fiscal
//   status: em-implementacao
//   stories: US-FISCAL-007 (Eventos sub-página 5 do design KB-9.75)
//   rules: R-FIN-001 (multi-tenant), R-FISCAL-001 (HasBusinessScope)
//   adrs: 0093, 0094, 0101, 0104
//   tests: Modules/Fiscal/Tests/Feature/EventosCockpitMultiTenantTest
//
// Origem: design Cowork fiscal-page.jsx §11 FiscalEventosPage. Timeline append-only.

import { Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Activity, Download } from 'lucide-react';
import { useState } from 'react';

import FxShell from './_components/FxShell';
import { btnProps } from './_lib/botao-fiscal';
import { chipCount, chipProps } from './_lib/chip-filtro';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

import '../../../css/fiscal-cockpit.css';

type EventKind = 'cce' | 'cancel' | 'epec' | 'manifest';

interface TipoMeta { kind: EventKind; label: string; }
type TiposMap = Record<string, TipoMeta>;

interface Filters {
  kind: 'todos' | EventKind;
  dias: number;
}

interface Counts {
  total: number;
  cce: number;
  cancel: number;
  epec: number;
  manifest: number;
  autorizados: number;
}

interface EventoRow {
  id: number;
  tipo: string;
  kind: EventKind;
  label: string;
  status: string;
  cstatEvento: number;
  justificativa: string;
  createdAtIso: string | null;
  when: string | null;
  emissao: { id: number; numero: number; modelo: number; chave: string } | null;
}

interface RowsPayload {
  data: EventoRow[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

interface EventosProps {
  filters: Filters;
  tipos: TiposMap;
  counts: Counts;
  rows?: RowsPayload;
}

export default function Eventos({ filters: initialFilters, counts, rows }: EventosProps) {
  const [filters, setFilters] = useState<Filters>(initialFilters);
  const dataRows: EventoRow[] = rows?.data ?? [];

  // URL do CSV — carrega os filtros ATIVOS, então o arquivo bate com o que a
  // contadora está vendo. É <a href> de propósito, não `router.visit`: Inertia
  // espera resposta JSON e não dispara download de arquivo.
  const urlExport = `/fiscal/eventos/export?kind=${encodeURIComponent(filters.kind)}&dias=${filters.dias}`;

  const apply = (next: Partial<Filters>) => {
    const merged = { ...filters, ...next };
    setFilters(merged);
    router.visit('/fiscal/eventos', {
      data: merged as unknown as Record<string, string>,
      only: ['rows', 'counts', 'filters'],
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <AppShellV2>
      <Head title="Fiscal · Eventos" />

      <FxShell
        route="fiscal_eventos"
        title="Eventos fiscais"
        crumb={`Últimos ${filters.dias}d · ${counts.total} eventos · ${counts.autorizados} autorizados SEFAZ`}
        env="append-only log"
        envTone="ok"
        cheats={[
          { keys: ['1'], label: 'Cockpit' },
          { keys: ['2'], label: 'NF-e' },
        ]}
        actions={
          <>
            <Select
              value={String(filters.dias)}
              onValueChange={(v) => apply({ dias: parseInt(v, 10) })}
            >
              <SelectTrigger size="sm" className="w-auto" aria-label="Período">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="7">Últimos 7d</SelectItem>
                <SelectItem value="30">Últimos 30d</SelectItem>
                <SelectItem value="90">Últimos 90d</SelectItem>
              </SelectContent>
            </Select>
            <Button asChild {...btnProps('ghost')}>
              <a href={urlExport} download>
                <Download size={14} aria-hidden="true" />
                Exportar CSV
              </a>
            </Button>
          </>
        }
      >
        {/* Filtros por tipo */}
        <Inline gap={2} align="center" wrap className="mb-3">
          <Button
            type="button"
            {...chipProps(filters.kind === 'todos')}
            aria-pressed={filters.kind === 'todos'}
            onClick={() => apply({ kind: 'todos' })}
          >
            Todos <span className={chipCount(filters.kind === 'todos')}>{counts.total}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'cce')}
            aria-pressed={filters.kind === 'cce'}
            onClick={() => apply({ kind: 'cce' })}
          >
            CC-e (110110) <span className={chipCount(filters.kind === 'cce')}>{counts.cce}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'cancel', 'danger')}
            aria-pressed={filters.kind === 'cancel'}
            onClick={() => apply({ kind: 'cancel' })}
          >
            Cancelamento (110111) <span className={chipCount(filters.kind === 'cancel')}>{counts.cancel}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'epec', 'warn')}
            aria-pressed={filters.kind === 'epec'}
            onClick={() => apply({ kind: 'epec' })}
          >
            EPEC (110140) <span className={chipCount(filters.kind === 'epec')}>{counts.epec}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'manifest')}
            aria-pressed={filters.kind === 'manifest'}
            onClick={() => apply({ kind: 'manifest' })}
          >
            Manifesto destinatário <span className={chipCount(filters.kind === 'manifest')}>{counts.manifest}</span>
          </Button>
        </Inline>

        {/* Timeline deferred */}
        <Deferred data="rows" fallback={
          <div className="fx-empty">
            <b>Carregando eventos…</b>
            <small>Buscando últimos {filters.dias} dias · multi-tenant scope ativo</small>
          </div>
        }>
          {dataRows.length === 0 ? (
            <div className="fx-empty">
              <Activity size={20} />
              <b>Nenhum evento no período</b>
              <small>Eventos aparecem após cancelamento, CC-e, EPEC ou manifestação.</small>
            </div>
          ) : (
            // Forma do protótipo (FxEventosPage, fiscal-subpages.jsx, medida em 2026-11-24): cartão
            // "Eventos aplicados" com quando · ponto · descrição · cStat. Os rótulos dos chips levam o
            // código do evento; o EPEC fica no lugar da Inutilização do protótipo (decisão registrada
            // no Eventos.casos.md — FK emissao_id NOT NULL).
            <div className="fx-card" data-contract="fiscal-eventos-timeline">
              <div className="fx-card-h"><span>Eventos aplicados</span><span>{dataRows.length}</span></div>
              <div className="fx-tl">
                {dataRows.map((ev) => (
                  <div key={ev.id} className="fx-tl-i" data-kind={ev.kind}>
                    <span className="fx-tl-when">{ev.when ?? '—'}</span>
                    <span className="fx-tl-dot"><i /></span>
                    <span className="fx-tl-b">
                      <b>
                        {ev.label}
                        {ev.emissao && (
                          <>
                            {' · '}
                            <a
                              className="fx-link"
                              href={`/fiscal/nfe?focus=${ev.emissao.id}`}
                              onClick={(e) => { e.preventDefault(); router.visit(`/fiscal/nfe?focus=${ev.emissao!.id}`); }}
                            >
                              {ev.emissao.modelo === 65 ? 'NFC-e' : 'NF-e'} {ev.emissao.numero}
                            </a>
                          </>
                        )}
                      </b>
                      {ev.justificativa && <small>{ev.justificativa}</small>}
                    </span>
                    <span className={`fx-sefaz ${[100, 101, 102, 135, 136].includes(ev.cstatEvento) ? 'ok' : 'warn'}`}>
                      cstat {ev.cstatEvento}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </Deferred>
        <p className="fx-nota-rodape">
          Eventos são append-only — a tela não permite edição. Emissão de CC-e, cancelamento e
          inutilização acontece nas telas de notas.
        </p>
      </FxShell>
    </AppShellV2>
  );
}
