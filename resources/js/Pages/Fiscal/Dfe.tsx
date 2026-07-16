// @memcofre
//   tela: /fiscal/dfe
//   module: Fiscal
//   stories: US-FISCAL-008 (DF-e manifesto sub-página 4 do design KB-9.75)
//   adrs: 0093, 0094, 0101, 0104

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Check, CheckCircle2, Eye, FileSearch, Info, ShieldAlert, XCircle } from 'lucide-react';
import { useState } from 'react';

import FxShell from './_components/FxShell';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import { brl, formatDoc, truncKey } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

type DfeTab = 'pendente' | 'historico';

interface HistoricoEntry {
  id: number;
  chave: string;
  nomeEmitente: string;
  cnpjEmitente: string | null;
  when: string;
  ack: 'confirmada' | 'ciencia' | 'desconhecida' | 'nao_realizada';
  actor: string;
  obs: string | null;
  valor: number;
}

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
  // DS Onda 3 — aba ativa vem da rota (?tab=), whitelist server-side no DfeController.
  activeTab?: DfeTab;
  filters: Filters;
  counts: Counts;
  rows?: { data: DfeRow[]; meta: { total: number; current_page: number; last_page: number } };
  // Onda 2 — histórico de manifestações já processadas (mock no controller)
  historicoMock?: HistoricoEntry[];
}

const STATUS_META: Record<StatusManifestacao, { label: string; tone: 'ok' | 'warn' | 'bad' }> = {
  pendente:      { label: 'Pendente',         tone: 'warn' },
  ciencia:       { label: 'Ciência dada',     tone: 'warn' },
  confirmada:    { label: 'Confirmada',       tone: 'ok' },
  desconhecida:  { label: 'Desconhecida',     tone: 'bad' },
  nao_realizada: { label: 'Não realizada',    tone: 'bad' },
};

type ManifestAction = 'cienciar' | 'confirmar' | 'desconhecer' | 'nao_realizada';

export default function Dfe({ activeTab, filters: initialFilters, counts, rows, historicoMock = [] }: DfeProps) {
  const [filters, setFilters] = useState<Filters>(initialFilters);
  // Aba ativa dirigida pela rota (?tab=) — barra canônica navega por href (DS Onda 3).
  const tab = activeTab ?? 'pendente';
  const dataRows = rows?.data ?? [];
  const [busyId, setBusyId] = useState<number | null>(null);
  const [modal, setModal] = useState<{ id: number; acao: 'desconhecer' | 'nao_realizada' } | null>(null);
  const [justificativa, setJustificativa] = useState('');

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

  const dispatchManifest = (id: number, acao: ManifestAction, justif?: string) => {
    setBusyId(id);
    router.post(
      `/fiscal/acoes/dfe/${id}/${acao}`,
      justif ? { justificativa: justif } : {},
      {
        preserveScroll: true,
        onFinish: () => {
          setBusyId(null);
          setModal(null);
          setJustificativa('');
        },
      },
    );
  };

  const openModal = (id: number, acao: 'desconhecer' | 'nao_realizada') => {
    setModal({ id, acao });
    setJustificativa('');
  };

  const confirmModal = () => {
    if (!modal || justificativa.trim().length < 15) return;
    dispatchManifest(modal.id, modal.acao, justificativa);
  };

  return (
    <AppShellV2>
      <Head title="Fiscal · DF-e" />

      <FxShell
        route="dfe"
        title="Manifestação do destinatário"
        crumb={`${counts.pendentes} aguardando ciência · ${brl(counts.valorPendente)} · busca diária SEFAZ`}
        env={counts.pendentes > 0 ? `${counts.pendentes} aguardando` : 'tudo manifestado'}
        envTone={counts.pendentes > 10 ? 'warn' : counts.pendentes > 0 ? 'ok' : 'ok'}
        actions={
          <button type="button" className="fx-btn primary" disabled title="Bulk manifestar (PR seguinte)">
            Manifestar selecionadas <kbd>E</kbd>
          </button>
        }
      >
        {/* DS Onda 3 — barra de abas CANÔNICA (PageHeaderTabs) em faixa própria,
            navegando por rota (?tab=). Padroniza o visual com Clientes/Financeiro/Ponto. */}
        <div className="mb-4">
          <PageHeaderTabs
            ghosts={[
              { key: 'pendente',  label: 'Aguardando ciência', href: '/fiscal/dfe?tab=pendente',  icon: 'inbox',         badge: counts.pendentes || undefined },
              { key: 'historico', label: 'Histórico',          href: '/fiscal/dfe?tab=historico', icon: 'check-circle-2', badge: historicoMock.length || undefined },
            ]}
            activeGhostKey={tab}
            maxVisible={6}
          />
        </div>

        {tab === 'pendente' && (<>
        {/* Callout informativo (port do fiscal-page.jsx §10 FiscalDFePage) */}
        <div className="fx-callout" role="region" aria-label="O que é manifestação">
          <Info size={16} />
          <div>
            <b>O que é manifestação?</b>
            <small>
              Toda NF-e emitida com o seu CNPJ no destinatário deve ser manifestada
              em até <b>90 dias</b>. 4 respostas: <b>ciência</b> · <b>confirmação</b> ·
              {' '}<b>desconhecimento</b> · <b>não realizada</b>. Sem manifestar, escrita
              fiscal e CIAP ficam inconsistentes.
            </small>
          </div>
        </div>

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
                    <th style={{ width: 180 }}>Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {dataRows.map((d) => {
                    const stMeta = STATUS_META[d.statusManifestacao] ?? STATUS_META.pendente;
                    const prazoUrgency = d.prazoDias == null ? 'ok'
                      : d.prazoDias < 7 ? 'crit'
                      : d.prazoDias < 30 ? 'warn' : 'ok';
                    const podeManifestar = ['pendente', 'ciencia'].includes(d.statusManifestacao);
                    const isBusy = busyId === d.id;
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
                        <td onClick={(e) => e.stopPropagation()}>
                          {podeManifestar ? (
                            <div style={{ display: 'flex', gap: 4 }}>
                              <button className="fx-btn ghost" style={{ padding: '3px 6px' }}
                                disabled={isBusy}
                                title="Confirmar operação (210200)"
                                onClick={() => dispatchManifest(d.id, 'confirmar')}>
                                <CheckCircle2 size={11} />
                              </button>
                              <button className="fx-btn ghost" style={{ padding: '3px 6px' }}
                                disabled={isBusy}
                                title="Ciência (210210)"
                                onClick={() => dispatchManifest(d.id, 'cienciar')}>
                                <Eye size={11} />
                              </button>
                              <button className="fx-btn ghost" style={{ padding: '3px 6px', color: 'var(--bad)' }}
                                disabled={isBusy}
                                title="Desconhecer (210220 — exige motivo)"
                                onClick={() => openModal(d.id, 'desconhecer')}>
                                <XCircle size={11} />
                              </button>
                              <button className="fx-btn ghost" style={{ padding: '3px 6px', color: 'var(--warn)' }}
                                disabled={isBusy}
                                title="Não realizada (210240 — exige motivo)"
                                onClick={() => openModal(d.id, 'nao_realizada')}>
                                <Check size={11} />
                              </button>
                            </div>
                          ) : (
                            <small style={{ color: 'var(--fx-text-mute)' }}>manifestada</small>
                          )}
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )}
        </Deferred>
        </>)}

        {tab === 'historico' && (
          historicoMock.length === 0 ? (
            <div className="fx-empty">
              <CheckCircle2 size={20} />
              <b>Sem histórico ainda</b>
              <small>Manifestações confirmadas/desconhecidas/não-realizadas aparecem aqui após processamento SEFAZ.</small>
            </div>
          ) : (
            <div className="fx-table">
              <table>
                <thead>
                  <tr>
                    <th>Emitente</th>
                    <th style={{ width: 100 }}>Quando</th>
                    <th style={{ width: 150 }}>Manifestação</th>
                    <th style={{ width: 110 }}>Por</th>
                    <th>Observação</th>
                    <th style={{ width: 110, textAlign: 'right' }}>Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {historicoMock.map((h) => {
                    const tone = h.ack === 'confirmada' ? 'ok' : h.ack === 'ciencia' ? 'warn' : 'bad';
                    const label = h.ack === 'confirmada' ? '✓ Confirmada'
                      : h.ack === 'ciencia' ? '~ Ciência'
                      : h.ack === 'desconhecida' ? '✗ Desconhecida'
                      : '— Não realizada';
                    return (
                      <tr key={h.id}>
                        <td>
                          <b>{h.nomeEmitente}</b>
                          <small style={{ display: 'block', color: 'var(--fx-text-mute)' }}>{formatDoc(h.cnpjEmitente, null)}</small>
                        </td>
                        <td><small className="fx-mono">{h.when}</small></td>
                        <td>
                          <span className={`fx-sefaz ${tone}`}>
                            <span className="lbl">{label}</span>
                          </span>
                        </td>
                        <td><small>{h.actor}</small></td>
                        <td><small style={{ color: 'var(--fx-text-dim)' }}>{h.obs ?? '—'}</small></td>
                        <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>{brl(h.valor)}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          )
        )}
      </FxShell>

      {/* Modal motivo (desconhecer / nao_realizada) */}
      {modal && (
        <div className="fx-drawer-bg" onClick={() => busyId == null && setModal(null)}>
          <div
            role="dialog"
            aria-label="Justificar manifestação"
            onClick={(e) => e.stopPropagation()}
            style={{
              background: 'white',
              borderRadius: 10,
              padding: 22,
              width: 460,
              maxWidth: '90vw',
              margin: '15vh auto',
              boxShadow: '0 12px 40px rgba(0,0,0,.2)',
            }}
          >
            <h3 style={{ margin: '0 0 8px', fontSize: 16, fontWeight: 700 }}>
              {modal.acao === 'desconhecer' ? 'Desconhecer operação' : 'Operação não realizada'}
            </h3>
            <p style={{ fontSize: 12.5, color: 'var(--fx-text-dim)', margin: '0 0 14px' }}>
              Justificativa obrigatória (mín. 15 chars — regra SEFAZ).
              {modal.acao === 'desconhecer'
                ? ' Esta operação não foi solicitada pela empresa (ex: NF de fornecedor errado).'
                : ' A operação NÃO se concretizou (ex: mercadoria nunca chegou).'}
            </p>
            <textarea
              value={justificativa}
              onChange={(e) => setJustificativa(e.target.value)}
              placeholder={modal.acao === 'desconhecer'
                ? 'Ex: NF emitida sem solicitação, fornecedor avisou erro'
                : 'Ex: mercadoria não entregue, pedido cancelado em comum acordo'}
              rows={3}
              disabled={busyId !== null}
              autoFocus
              style={{
                width: '100%',
                padding: 10,
                fontSize: 12.5,
                border: '1px solid var(--fx-border)',
                borderRadius: 7,
                fontFamily: 'inherit',
                resize: 'vertical',
              }}
            />
            <div style={{ fontSize: 11, color: 'var(--fx-text-mute)', margin: '4px 0 14px' }}>
              {justificativa.length}/255 · {justificativa.trim().length < 15 ? `faltam ${15 - justificativa.trim().length} chars` : '✅ ok'}
            </div>
            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <button className="fx-btn ghost" onClick={() => setModal(null)} disabled={busyId !== null}>
                Voltar
              </button>
              <button
                className={`fx-btn ${modal.acao === 'desconhecer' ? 'danger' : 'warn'}`}
                onClick={confirmModal}
                disabled={busyId !== null || justificativa.trim().length < 15}
              >
                {busyId !== null ? 'Enviando…' : 'Confirmar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </AppShellV2>
  );
}
