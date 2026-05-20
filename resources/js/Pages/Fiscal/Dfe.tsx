// @memcofre
//   tela: /fiscal/dfe
//   module: Fiscal
//   stories: US-FISCAL-008 (DF-e manifesto sub-página 4 do design KB-9.75)
//   adrs: 0093, 0094, 0101, 0104

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { FileSearch, ShieldAlert } from 'lucide-react';
import { useState } from 'react';

import FxShell from './_components/FxShell';
import { brl, formatDoc, truncKey } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

type StatusManifestacao = 'pendente' | 'ciencia' | 'confirmada' | 'desconhecida' | 'nao_realizada';

interface Filters {
  status: 'pendentes' | 'confirmadas' | 'desconhecidas' | 'nao_realizadas' | 'todas';
  search: string;
}

interface Counts {
  total: number;
  pendentes: number;
  confirmadas: number;
  desconhecidas: number;
  naoRealizadas: number;
  valorPendente: number;
}

interface DfeRow {
  id: number;
  chave: string;
  nsu: string | null;
  cnpjEmitente: string | null;
  nomeEmitente: string;
  valor: number;
  numProtocolo: string | null;
  dataEmissaoIso: string | null;
  when: string | null;
  statusManifestacao: StatusManifestacao;
  manifestadoEmIso: string | null;
  prazoDias: number | null;
}

interface DfeProps {
  filters: Filters;
  counts: Counts;
  rows?: { data: DfeRow[]; meta: { total: number; current_page: number; last_page: number } };
}

const STATUS_META: Record<StatusManifestacao, { label: string; tone: 'ok' | 'warn' | 'bad' }> = {
  pendente:      { label: 'Pendente',         tone: 'warn' },
  ciencia:       { label: 'Ciência dada',     tone: 'warn' },
  confirmada:    { label: 'Confirmada',       tone: 'ok' },
  desconhecida:  { label: 'Desconhecida',     tone: 'bad' },
  nao_realizada: { label: 'Não realizada',    tone: 'bad' },
};

export default function Dfe({ filters: initialFilters, counts, rows }: DfeProps) {
  const [filters, setFilters] = useState<Filters>(initialFilters);
  const dataRows = rows?.data ?? [];

  const apply = (next: Partial<Filters>) => {
    const merged = { ...filters, ...next };
    setFilters(merged);
    router.visit('/fiscal/dfe', {
      data: merged as unknown as Record<string, string>,
      only: ['rows', 'counts', 'filters'],
      preserveState: true,
      preserveScroll: true,
    });
  };

  return (
    <AppShellV2>
      <Head title="Fiscal · DF-e" />

      <FxShell
        route="dfe"
        title="Manifesto DF-e"
        crumb={`${counts.pendentes} pendentes · ${brl(counts.valorPendente)} aguardando · prazo legal 90d`}
        env={counts.pendentes > 0 ? `${counts.pendentes} aguardando` : 'tudo manifestado'}
        envTone={counts.pendentes > 10 ? 'warn' : counts.pendentes > 0 ? 'ok' : 'ok'}
      >
        <div className="fx-filters">
          <div className="fx-search">
            <FileSearch size={13} />
            <input
              type="search"
              placeholder="Buscar chave (44d), CNPJ ou nome emitente…"
              value={filters.search}
              onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
              onKeyDown={(e) => e.key === 'Enter' && apply({ search: filters.search })}
            />
          </div>
          <button type="button" className={`fx-chip warn${filters.status === 'pendentes' ? ' active' : ''}`} onClick={() => apply({ status: 'pendentes' })}>
            Pendentes <span>{counts.pendentes}</span>
          </button>
          <button type="button" className={`fx-chip${filters.status === 'confirmadas' ? ' active' : ''}`} onClick={() => apply({ status: 'confirmadas' })}>
            Confirmadas <span>{counts.confirmadas}</span>
          </button>
          <button type="button" className={`fx-chip danger${filters.status === 'desconhecidas' ? ' active' : ''}`} onClick={() => apply({ status: 'desconhecidas' })}>
            Desconhecidas <span>{counts.desconhecidas}</span>
          </button>
          <button type="button" className={`fx-chip danger${filters.status === 'nao_realizadas' ? ' active' : ''}`} onClick={() => apply({ status: 'nao_realizadas' })}>
            Não realizadas <span>{counts.naoRealizadas}</span>
          </button>
          <button type="button" className={`fx-chip${filters.status === 'todas' ? ' active' : ''}`} onClick={() => apply({ status: 'todas' })}>
            Todas <span>{counts.total}</span>
          </button>
        </div>

        <Deferred data="rows" fallback={
          <div className="fx-empty"><b>Carregando DF-e…</b><small>Busca em NfeDfeRecebido scoped</small></div>
        }>
          {dataRows.length === 0 ? (
            <div className="fx-empty">
              <ShieldAlert size={20} />
              <b>Nenhuma DF-e encontrada</b>
              <small>NF-e emitidas contra o CNPJ são captadas via NSU SEFAZ (job periódico).</small>
            </div>
          ) : (
            <div className="fx-table">
              <table>
                <thead>
                  <tr>
                    <th>Emitente</th>
                    <th style={{ width: 220 }}>Chave</th>
                    <th style={{ width: 140 }}>Status</th>
                    <th style={{ width: 90, textAlign: 'center' }}>Prazo</th>
                    <th style={{ width: 120, textAlign: 'right' }}>Valor</th>
                    <th style={{ width: 96 }}>Emissão</th>
                  </tr>
                </thead>
                <tbody>
                  {dataRows.map((d) => {
                    const stMeta = STATUS_META[d.statusManifestacao] ?? STATUS_META.pendente;
                    const prazoUrgency = d.prazoDias == null ? 'ok'
                      : d.prazoDias < 7 ? 'crit'
                      : d.prazoDias < 30 ? 'warn' : 'ok';
                    return (
                      <tr key={d.id}>
                        <td>
                          <b>{d.nomeEmitente || '—'}</b>
                          <small>{formatDoc(d.cnpjEmitente, null)}</small>
                        </td>
                        <td className="fx-mono"><small>{truncKey(d.chave)}</small></td>
                        <td>
                          <span className={`fx-sefaz ${stMeta.tone}`}>
                            <span className="lbl">{stMeta.label}</span>
                          </span>
                        </td>
                        <td style={{ textAlign: 'center' }}>
                          {d.prazoDias != null && (
                            <span className={`fx-timepill u-${prazoUrgency}`}>
                              <b>{d.prazoDias > 0 ? `${d.prazoDias}d` : 'vencido'}</b>
                            </span>
                          )}
                        </td>
                        <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>{brl(d.valor)}</td>
                        <td><small>{d.when ?? '—'}</small></td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Deferred>
      </FxShell>
    </AppShellV2>
  );
}
