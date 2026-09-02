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
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Activity, Info } from 'lucide-react';
import { useState } from 'react';

import FxShell from './_components/FxShell';
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
        }
      >
        {/* Callout — janelas legais (port fiscal-page.jsx §9 EventosTab) */}
        {/* `role="region"` + `aria-label` MANTIDOS: o <Alert> traz `role="alert"`
            embutido (live-region assertiva), errado pra banner informativo estático.
            O `{...props}` dele vem depois do padrão, então o override pega. */}
        <Alert className="mb-3" role="region" aria-label="Janelas legais">
          <Info size={16} />
          <AlertTitle>Janelas legais que o sistema valida</AlertTitle>
          <AlertDescription>
            <span>
              <b>CC-e:</b> até 30 dias · máx 20 por nota · não corrige valor/CFOP/qtd.
              {' '}<b>Cancelamento:</b> até 24h (NFC-e) / 168h (NF-e se UF permitir).
              {' '}<b>Inutilização:</b> faixas de numeração não usadas.
            </span>
          </AlertDescription>
        </Alert>

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
            CC-e <span className={chipCount(filters.kind === 'cce')}>{counts.cce}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'cancel', 'danger')}
            aria-pressed={filters.kind === 'cancel'}
            onClick={() => apply({ kind: 'cancel' })}
          >
            Cancelamento <span className={chipCount(filters.kind === 'cancel')}>{counts.cancel}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'epec', 'warn')}
            aria-pressed={filters.kind === 'epec'}
            onClick={() => apply({ kind: 'epec' })}
          >
            EPEC <span className={chipCount(filters.kind === 'epec')}>{counts.epec}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.kind === 'manifest')}
            aria-pressed={filters.kind === 'manifest'}
            onClick={() => apply({ kind: 'manifest' })}
          >
            Manifesto <span className={chipCount(filters.kind === 'manifest')}>{counts.manifest}</span>
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
            <div className="fx-timeline" data-contract="fiscal-eventos-timeline">
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
