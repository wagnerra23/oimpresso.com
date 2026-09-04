// @memcofre
//   tela: /fiscal/nfse
//   module: Fiscal
//   status: em-implementacao
//   stories: US-FISCAL-005 (NFS-e sub-página 3 do design KB-9.75)
//   rules: R-FIN-001 (multi-tenant), R-FISCAL-001 (HasBusinessScope)
//   adrs: 0093, 0094, 0101, 0104
//   tests: Modules/Fiscal/Tests/Feature/NfseCockpitMultiTenantTest
//
// Origem: design Cowork fiscal-page.jsx §10 FiscalNFSePage. NFS-e nacional NT 2024-001.

import { Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { FileSearch, FileText } from 'lucide-react';
import { useState } from 'react';

import DensidadeToggle, { useDensidadeFiscal } from './_components/DensidadeToggle';
import FxShell from './_components/FxShell';
import { chipCount, chipProps } from './_lib/chip-filtro';
import { brl, formatDoc } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

interface Filters {
  search: string;
  status: 'todas' | 'autorizadas' | 'rejeitadas' | 'processando' | 'canceladas';
  mes: string; // YYYY-MM
}

interface Counts {
  total: number;
  autorizadas: number;
  rejeitadas: number;
  processando: number;
  canceladas: number;
  faturamento: number;
}

interface NfseRow {
  id: number;
  num: string;
  codigoVerificacao: string | null;
  tomador: string;
  documentoTomador: string | null;
  municipio: string | null;
  codServico: string | null;
  aliquotaIss: number;
  valueServico: number;
  valueIss: number;
  status: string;
  errorMsg: string | null;
  emittedAtIso: string | null;
  when: string | null;
}

interface RowsPayload {
  data: NfseRow[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

interface NfseProps {
  filters: Filters;
  counts: Counts;
  rows?: RowsPayload;
}

const STATUS_LABEL: Record<string, { label: string; tone: 'ok' | 'warn' | 'bad' }> = {
  authorized: { label: 'Autorizada', tone: 'ok' },
  rejected:   { label: 'Rejeitada', tone: 'bad' },
  pending:    { label: 'Pendente',  tone: 'warn' },
  sent:       { label: 'Enviada',   tone: 'warn' },
  cancelled:  { label: 'Cancelada', tone: 'bad' },
};

export default function Nfse({ filters: initialFilters, counts, rows }: NfseProps) {
  const [filters, setFilters] = useState<Filters>(initialFilters);
  const [density, setDensity] = useDensidadeFiscal();
  const dataRows: NfseRow[] = rows?.data ?? [];

  const apply = (next: Partial<Filters>) => {
    const merged = { ...filters, ...next };
    setFilters(merged);
    router.visit('/fiscal/nfse', {
      data: merged as unknown as Record<string, string>,
      only: ['rows', 'counts', 'filters'],
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <AppShellV2>
      <Head title="Fiscal · NFS-e" />

      <FxShell
        route="nfse"
        title="NFS-e"
        crumb={`Sistema Nacional NT 2024-001 · ${counts.total} no período · ${brl(counts.faturamento)} autorizado`}
        env={counts.rejeitadas > 0 ? `${counts.rejeitadas} rejeitadas` : 'tudo autorizado'}
        envTone={counts.rejeitadas > 0 ? 'warn' : 'ok'}
        cheats={[
          { keys: ['1'], label: 'Cockpit' },
          { keys: ['2'], label: 'NF-e' },
        ]}
        actions={
          <Input
            type="month"
            className="w-auto"
            value={filters.mes}
            onChange={(e) => apply({ mes: e.target.value })}
            aria-label="Filtrar competência"
          />
        }
      >
        {/* Filtros */}
        <Inline gap={2} align="center" wrap className="mb-3" data-contract="fiscal-nfse-filters">
          {/* Espaço da lupa pela utilitária canon `.cw-input-icon-left`, NÃO `pl-*`:
              a Tailwind é layered e perde pro `.cw-input` unlayered (cowork-fields.css). */}
          <div className="relative min-w-[240px] flex-1">
            <FileSearch
              size={13}
              className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              type="search"
              className="cw-input-icon-left"
              placeholder="Buscar nº NFS-e, código verificação, ou documento tomador…"
              aria-label="Buscar NFS-e por número, código de verificação ou documento do tomador"
              value={filters.search}
              onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
              onKeyDown={(e) => e.key === 'Enter' && apply({ search: filters.search })}
            />
          </div>
          <Button
            type="button"
            {...chipProps(filters.status === 'todas')}
            aria-pressed={filters.status === 'todas'}
            onClick={() => apply({ status: 'todas' })}
          >
            Todas <span className={chipCount(filters.status === 'todas')}>{counts.total}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'autorizadas')}
            aria-pressed={filters.status === 'autorizadas'}
            onClick={() => apply({ status: 'autorizadas' })}
          >
            Autorizadas <span className={chipCount(filters.status === 'autorizadas')}>{counts.autorizadas}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'rejeitadas', 'danger')}
            aria-pressed={filters.status === 'rejeitadas'}
            onClick={() => apply({ status: 'rejeitadas' })}
          >
            Rejeitadas <span className={chipCount(filters.status === 'rejeitadas')}>{counts.rejeitadas}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'processando', 'warn')}
            aria-pressed={filters.status === 'processando'}
            onClick={() => apply({ status: 'processando' })}
          >
            Processando <span className={chipCount(filters.status === 'processando')}>{counts.processando}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'canceladas')}
            aria-pressed={filters.status === 'canceladas'}
            onClick={() => apply({ status: 'canceladas' })}
          >
            Canceladas <span className={chipCount(filters.status === 'canceladas')}>{counts.canceladas}</span>
          </Button>

          <DensidadeToggle value={density} onChange={setDensity} />
        </Inline>

        {/* Tabela deferred */}
        <Deferred data="rows" fallback={
          <div className="fx-empty">
            <b>Carregando NFS-e…</b>
            <small>Buscando emissões no banco · multi-tenant scope ativo</small>
          </div>
        }>
          {dataRows.length === 0 ? (
            <div className="fx-empty">
              <FileText size={20} />
              <b>Nenhuma NFS-e encontrada</b>
              <small>Ajuste os filtros ou aguarde primeira emissão deste mês.</small>
            </div>
          ) : (
            <div className={`fx-density-${density}`}>
              <div className="fx-table">
                <table>
                  <thead>
                    <tr>
                      <th style={{ width: 96 }}>Número</th>
                      <th>Tomador</th>
                      <th style={{ width: 130 }}>Município</th>
                      <th style={{ width: 90 }}>Cód. serviço</th>
                      <th style={{ width: 140 }}>Status</th>
                      <th style={{ width: 120, textAlign: 'right' }}>Valor</th>
                      <th style={{ width: 96 }}>Emissão</th>
                    </tr>
                  </thead>
                  <tbody>
                    {dataRows.map((n) => {
                      const stMeta = STATUS_LABEL[n.status] ?? { label: n.status, tone: 'warn' as const };
                      return (
                        <tr key={n.id} title={n.errorMsg ?? undefined}>
                          <td className="fx-mono">
                            <b>{n.num}</b>
                            {n.codigoVerificacao && <small>{n.codigoVerificacao}</small>}
                          </td>
                          <td>
                            <div>{n.tomador}</div>
                            <small>{formatDoc(n.documentoTomador, null)}</small>
                          </td>
                          <td><small>{n.municipio ?? '—'}</small></td>
                          <td className="fx-mono"><small>{n.codServico ?? '—'}</small></td>
                          <td>
                            <span className={`fx-sefaz ${stMeta.tone}`}>
                              <span className="lbl">{stMeta.label}</span>
                            </span>
                            {n.aliquotaIss > 0 && (
                              <small style={{ display: 'block', color: 'var(--fx-text-mute)', marginTop: 2 }}>
                                ISS {n.aliquotaIss}%
                              </small>
                            )}
                          </td>
                          <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>
                            {brl(n.valueServico)}
                            {n.valueIss > 0 && (
                              <small style={{ display: 'block', color: 'var(--fx-text-mute)' }}>
                                ISS {brl(n.valueIss)}
                              </small>
                            )}
                          </td>
                          <td><small>{n.when ?? '—'}</small></td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </Deferred>
      </FxShell>
    </AppShellV2>
  );
}
