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

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { useState } from 'react';

import FxShell from './_components/FxShell';

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
          <select
            value={filters.dias}
            onChange={(e) => apply({ dias: parseInt(e.target.value, 10) })}
            className="fx-btn ghost"
            style={{ padding: '4px 8px' }}
            aria-label="Período"
          >
            <option value={7}>Últimos 7d</option>
            <option value={30}>Últimos 30d</option>
            <option value={90}>Últimos 90d</option>
          </select>
        }
      >
        {/* Filtros por tipo */}
        <div className="fx-filters">
          <button type="button" className={`fx-chip${filters.kind === 'todos' ? ' active' : ''}`} onClick={() => apply({ kind: 'todos' })}>
            Todos <span>{counts.total}</span>
          </button>
          <button type="button" className={`fx-chip${filters.kind === 'cce' ? ' active' : ''}`} onClick={() => apply({ kind: 'cce' })}>
            CC-e <span>{counts.cce}</span>
          </button>
          <button type="button" className={`fx-chip danger${filters.kind === 'cancel' ? ' active' : ''}`} onClick={() => apply({ kind: 'cancel' })}>
            Cancelamento <span>{counts.cancel}</span>
          </button>
          <button type="button" className={`fx-chip warn${filters.kind === 'epec' ? ' active' : ''}`} onClick={() => apply({ kind: 'epec' })}>
            EPEC <span>{counts.epec}</span>
          </button>
          <button type="button" className={`fx-chip${filters.kind === 'manifest' ? ' active' : ''}`} onClick={() => apply({ kind: 'manifest' })}>
            Manifesto <span>{counts.manifest}</span>
          </button>
        </div>

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
            <div className="fx-timeline">
              {dataRows.map((ev) => (
                <div key={ev.id} className={`fx-tl-item ${ev.kind}`}>
                  <div className="fx-tl-h">
                    <span className={`fx-tl-badge ${ev.kind}`}>{ev.label}</span>
                    {ev.emissao && (
                      <a
                        className="fx-link"
                        href={`/fiscal/nfe?focus=${ev.emissao.id}`}
                        onClick={(e) => { e.preventDefault(); router.visit(`/fiscal/nfe?focus=${ev.emissao!.id}`); }}
                      >
                        {ev.emissao.modelo === 65 ? 'NFC-e' : 'NF-e'} {ev.emissao.numero}
                      </a>
                    )}
                    <b>cstat {ev.cstatEvento}</b>
                    <span className="when">{ev.when ?? '—'}</span>
                  </div>
                  {ev.justificativa && <div className="fx-tl-desc">{ev.justificativa}</div>}
                </div>
              ))}
            </div>
          )}
        </Deferred>
      </FxShell>
    </AppShellV2>
  );
}
