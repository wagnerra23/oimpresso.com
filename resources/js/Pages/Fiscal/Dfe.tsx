// @memcofre
//   tela: /fiscal/dfe
//   module: Fiscal
//   stories: US-FISCAL-008 (DF-e manifesto sub-página 4 do design KB-9.75)
//   adrs: 0093, 0094, 0101, 0104

import { Inline } from '@/Components/layout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Check, CheckCircle2, Eye, FileSearch, Info, ShieldAlert, XCircle } from 'lucide-react';
import { useState } from 'react';

import FxShell from './_components/FxShell';
import { chipCount, chipProps } from './_lib/chip-filtro';
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

/** Uma nota no relatório do lote — sempre identificada, pra saber qual refazer. */
interface LoteLinha {
  id: number;
  chave: string | null;
  emitente: string | null;
  erro: string | null;
}

/**
 * Relatório do último lote. Vem por prop (não por `flash`) porque falha parcial em
 * manifestação precisa ser NOMEADA: "3 de 10 falharam" não diz quais 3 refazer.
 */
interface LoteResultado {
  acao: LoteAction;
  pedidas: number;
  aplicadas: LoteLinha[];
  falhas: LoteLinha[];
  naoTentadas: { id: number }[];
}

interface DfeProps {
  // DS Onda 3 — aba ativa vem da rota (?tab=), whitelist server-side no DfeController.
  activeTab?: DfeTab;
  filters: Filters;
  counts: Counts;
  rows?: { data: DfeRow[]; meta: { total: number; current_page: number; last_page: number } };
  // Onda 2 — histórico de manifestações já processadas (mock no controller)
  historicoMock?: HistoricoEntry[];
  loteResultado?: LoteResultado | null;
}

const STATUS_META: Record<StatusManifestacao, { label: string; tone: 'ok' | 'warn' | 'bad' }> = {
  pendente:      { label: 'Pendente',         tone: 'warn' },
  ciencia:       { label: 'Ciência dada',     tone: 'warn' },
  confirmada:    { label: 'Confirmada',       tone: 'ok' },
  desconhecida:  { label: 'Desconhecida',     tone: 'bad' },
  nao_realizada: { label: 'Não realizada',    tone: 'bad' },
};

type ManifestAction = 'cienciar' | 'confirmar' | 'desconhecer' | 'nao_realizada';

/**
 * As três ações que existem em lote. `nao_realizada` fica fora de propósito: o protótipo
 * (`fiscal-subpages.jsx`, `data-contract="lote-dfe"`) oferece ciência, confirmação e
 * desconhecimento em massa, e mantém a quarta só na linha — é a decisão mais individual das
 * quatro. O backend valida a mesma lista.
 */
type LoteAction = 'cienciar' | 'confirmar' | 'desconhecer';

const LOTE_ROTULO: Record<LoteAction, string> = {
  cienciar: 'Dar ciência',
  confirmar: 'Confirmar operação',
  desconhecer: 'Desconhecer',
};

/** Espelha `AcoesController::LOTE_MAX_NOTAS` — o backend valida, isto só mantém o botão honesto. */
const LOTE_MAX_NOTAS = 50;

/** Só nota pendente ou com ciência dada pode ser manifestada — mesma regra do backend. */
const podeManifestarRow = (d: DfeRow): boolean =>
  d.statusManifestacao === 'pendente' || d.statusManifestacao === 'ciencia';

export default function Dfe({ activeTab, filters: initialFilters, counts, rows, historicoMock = [], loteResultado = null }: DfeProps) {
  const [filters, setFilters] = useState<Filters>(initialFilters);
  // Aba ativa dirigida pela rota (?tab=) — barra canônica navega por href (DS Onda 3).
  const tab = activeTab ?? 'pendente';
  const dataRows = rows?.data ?? [];
  const [busyId, setBusyId] = useState<number | null>(null);
  const [modal, setModal] = useState<{ id: number; acao: 'desconhecer' | 'nao_realizada' } | null>(null);
  const [justificativa, setJustificativa] = useState('');

  // ── Seleção em lote ────────────────────────────────────────────────────────────────────
  const [sel, setSel] = useState<Set<number>>(new Set());
  const [loteModal, setLoteModal] = useState<LoteAction | null>(null);
  const [loteBusy, setLoteBusy] = useState(false);

  // Só as manifestáveis DA PÁGINA CARREGADA entram na seleção — inclusive no "selecionar
  // todas", que marca o que está à vista, nunca o que a paginação ainda não trouxe.
  const manifestaveis = dataRows.filter(podeManifestarRow);
  const todasSelecionadas = manifestaveis.length > 0 && manifestaveis.every((d) => sel.has(d.id));
  const limiteEstourado = sel.size > LOTE_MAX_NOTAS;

  const toggleSel = (id: number) =>
    setSel((atual) => {
      const proximo = new Set(atual);
      proximo.has(id) ? proximo.delete(id) : proximo.add(id);
      return proximo;
    });

  const toggleTodas = () =>
    setSel(todasSelecionadas ? new Set() : new Set(manifestaveis.map((d) => d.id)));

  const dispatchLote = (acao: LoteAction, justif?: string) => {
    setLoteBusy(true);
    router.post(
      '/fiscal/acoes/dfe/lote',
      {
        ids: Array.from(sel),
        acao,
        ...(justif ? { justificativa: justif } : {}),
      },
      {
        preserveScroll: true,
        onSuccess: () => setSel(new Set()),
        onFinish: () => {
          setLoteBusy(false);
          setLoteModal(null);
          setJustificativa('');
        },
      },
    );
  };

  const confirmLote = () => {
    if (!loteModal) return;
    if (loteModal === 'desconhecer' && justificativa.trim().length < 15) return;
    dispatchLote(loteModal, loteModal === 'desconhecer' ? justificativa : undefined);
  };

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
        /* Sem ação no header de propósito. Aqui vivia um `<Button disabled>` com o rótulo
           "Manifestar selecionadas" e o title "Bulk manifestar (PR seguinte)" — afordância
           falsa: prometia uma capacidade que não existia e um PR que nunca veio. O lote agora
           existe, e mora onde a fonte o desenha: a barra `fx-bulk` que só aparece quando há
           seleção (`data-contract="lote-dfe"` em `prototipo-ui/cowork/fiscal-subpages.jsx`). */
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
        {/* `role="region"` + `aria-label` MANTIDOS: o <Alert> traz `role="alert"`
            embutido (live-region assertiva), errado pra banner informativo estático. */}
        <Alert className="mb-3" role="region" aria-label="O que é manifestação">
          <Info size={16} />
          <AlertTitle>O que é manifestação?</AlertTitle>
          <AlertDescription>
            <span>
              Toda NF-e emitida com o seu CNPJ no destinatário deve ser manifestada
              em até <b>90 dias</b>. 4 respostas: <b>ciência</b> · <b>confirmação</b> ·
              {' '}<b>desconhecimento</b> · <b>não realizada</b>. Sem manifestar, escrita
              fiscal e CIAP ficam inconsistentes.
            </span>
          </AlertDescription>
        </Alert>

        <Inline gap={2} align="center" wrap className="mb-3" data-contract="fiscal-dfe-filters">
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
              placeholder="Buscar chave (44d), CNPJ ou nome emitente…"
              aria-label="Buscar DF-e por chave, CNPJ ou nome do emitente"
              value={filters.search}
              onChange={(e) => setFilters((f) => ({ ...f, search: e.target.value }))}
              onKeyDown={(e) => e.key === 'Enter' && apply({ search: filters.search })}
            />
          </div>
          <Button
            type="button"
            {...chipProps(filters.status === 'pendentes', 'warn')}
            aria-pressed={filters.status === 'pendentes'}
            onClick={() => apply({ status: 'pendentes' })}
          >
            Pendentes <span className={chipCount(filters.status === 'pendentes')}>{counts.pendentes}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'confirmadas')}
            aria-pressed={filters.status === 'confirmadas'}
            onClick={() => apply({ status: 'confirmadas' })}
          >
            Confirmadas <span className={chipCount(filters.status === 'confirmadas')}>{counts.confirmadas}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'desconhecidas', 'danger')}
            aria-pressed={filters.status === 'desconhecidas'}
            onClick={() => apply({ status: 'desconhecidas' })}
          >
            Desconhecidas <span className={chipCount(filters.status === 'desconhecidas')}>{counts.desconhecidas}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'nao_realizadas', 'danger')}
            aria-pressed={filters.status === 'nao_realizadas'}
            onClick={() => apply({ status: 'nao_realizadas' })}
          >
            Não realizadas <span className={chipCount(filters.status === 'nao_realizadas')}>{counts.naoRealizadas}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(filters.status === 'todas')}
            aria-pressed={filters.status === 'todas'}
            onClick={() => apply({ status: 'todas' })}
          >
            Todas <span className={chipCount(filters.status === 'todas')}>{counts.total}</span>
          </Button>
        </Inline>

        {/* Relatório do último lote — nomeia CADA nota. Manifestação vai ao ambiente nacional
            da SEFAZ e é definitiva por nota: um resumo agregado ("3 falharam") não diz quais
            refazer, e é isso que torna um lote silencioso inaceitável aqui. */}
        {loteResultado && (
          <Alert
            className="mb-3"
            role="region"
            aria-label="Resultado da manifestação em lote"
            variant={loteResultado.falhas.length > 0 ? 'destructive' : 'default'}
          >
            {loteResultado.falhas.length > 0 ? <ShieldAlert size={16} /> : <CheckCircle2 size={16} />}
            <AlertTitle>
              {LOTE_ROTULO[loteResultado.acao]} · {loteResultado.aplicadas.length} de {loteResultado.pedidas} manifestada
              {loteResultado.aplicadas.length === 1 ? '' : 's'}
            </AlertTitle>
            <AlertDescription>
              {loteResultado.falhas.length > 0 && (
                <div style={{ marginTop: 6 }}>
                  <b>{loteResultado.falhas.length} não passaram:</b>
                  <ul style={{ margin: '4px 0 0', paddingLeft: 18 }}>
                    {loteResultado.falhas.map((f) => (
                      <li key={f.id} style={{ fontSize: 12 }}>
                        <b>{f.emitente || 'emitente não identificado'}</b>
                        {f.chave && <span className="fx-mono"> · {truncKey(f.chave)}</span>}
                        {' — '}
                        {f.erro}
                      </li>
                    ))}
                  </ul>
                </div>
              )}
              {loteResultado.naoTentadas.length > 0 && (
                <div style={{ marginTop: 6, fontSize: 12 }}>
                  <b>{loteResultado.naoTentadas.length} não chegaram a ser tentadas</b> — o lote atingiu o
                  limite de tempo do envio. Elas seguem como estavam; selecione e repita.
                </div>
              )}
              {loteResultado.falhas.length === 0 && loteResultado.naoTentadas.length === 0 && (
                <span style={{ fontSize: 12 }}>Todas as selecionadas foram manifestadas.</span>
              )}
            </AlertDescription>
          </Alert>
        )}

        {/* Barra de lote — aparece só com seleção, como na fonte. */}
        {sel.size > 0 && (
          <Inline gap={2} align="center" wrap className="mb-3" data-contract="lote-dfe" role="region" aria-label="Manifestação em lote">
            <span style={{ fontSize: 12.5 }}>
              <b>{sel.size}</b> DF-e selecionada{sel.size > 1 ? 's' : ''}
            </span>
            <Button type="button" variant="outline" size="cowork" disabled={loteBusy || limiteEstourado} onClick={() => setLoteModal('cienciar')}>
              Dar ciência
            </Button>
            <Button type="button" variant="outline" size="cowork" disabled={loteBusy || limiteEstourado} onClick={() => setLoteModal('confirmar')}>
              Confirmar operação
            </Button>
            <Button type="button" variant="destructive" size="cowork" disabled={loteBusy || limiteEstourado} onClick={() => setLoteModal('desconhecer')}>
              Desconhecer
            </Button>
            <Button type="button" variant="cowork-ghost" size="cowork" disabled={loteBusy} onClick={() => setSel(new Set())}>
              Limpar seleção
            </Button>
            {limiteEstourado && (
              <span style={{ fontSize: 12, color: 'var(--fx-text-dim)' }}>
                Máximo {LOTE_MAX_NOTAS} por vez — desmarque {sel.size - LOTE_MAX_NOTAS}.
              </span>
            )}
          </Inline>
        )}

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
                    <th style={{ width: 34 }}>
                      {/* Marca só o que está à vista E é manifestável — nunca a página seguinte,
                          nunca uma nota já manifestada. */}
                      <input
                        type="checkbox"
                        checked={todasSelecionadas}
                        disabled={manifestaveis.length === 0}
                        onChange={toggleTodas}
                        aria-label="Selecionar todas as DF-e manifestáveis desta página"
                      />
                    </th>
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
                    const podeManifestar = podeManifestarRow(d);
                    const isBusy = busyId === d.id;
                    return (
                      <tr key={d.id}>
                        <td onClick={(e) => e.stopPropagation()}>
                          {/* Sem checkbox na nota já manifestada: ela não é selecionável, e a
                              caixa vazia sugeriria que é. */}
                          {podeManifestar && (
                            <input
                              type="checkbox"
                              checked={sel.has(d.id)}
                              onChange={() => toggleSel(d.id)}
                              aria-label={`Selecionar DF-e de ${d.nomeEmitente || 'emitente não identificado'}`}
                            />
                          )}
                        </td>
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
                              <Button
                                type="button"
                                variant="outline"
                                size="icon-xs"
                                className="shrink-0"
                                disabled={isBusy}
                                title="Confirmar operação (210200)"
                                aria-label="Confirmar operação"
                                onClick={() => dispatchManifest(d.id, 'confirmar')}>
                                <CheckCircle2 size={11} />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon-xs"
                                className="shrink-0"
                                disabled={isBusy}
                                title="Ciência (210210)"
                                aria-label="Dar ciência"
                                onClick={() => dispatchManifest(d.id, 'cienciar')}>
                                <Eye size={11} />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon-xs"
                                className="shrink-0 text-destructive-fg"
                                disabled={isBusy}
                                title="Desconhecer (210220 — exige motivo)"
                                aria-label="Desconhecer operação"
                                onClick={() => openModal(d.id, 'desconhecer')}>
                                <XCircle size={11} />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon-xs"
                                className="shrink-0 text-warning-fg"
                                disabled={isBusy}
                                title="Não realizada (210240 — exige motivo)"
                                aria-label="Marcar não realizada"
                                onClick={() => openModal(d.id, 'nao_realizada')}>
                                <Check size={11} />
                              </Button>
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

      {/* Modal do LOTE — confirma antes de disparar N eventos definitivos na SEFAZ. */}
      {loteModal && (
        <div className="fx-drawer-bg" onClick={() => !loteBusy && setLoteModal(null)}>
          <div
            role="dialog"
            aria-label="Confirmar manifestação em lote"
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
              {LOTE_ROTULO[loteModal]} · {sel.size} DF-e
            </h3>
            <p style={{ fontSize: 12.5, color: 'var(--fx-text-dim)', margin: '0 0 14px' }}>
              A mesma manifestação vai pras {sel.size} notas selecionadas, uma requisição por nota.
            </p>

            {loteModal === 'desconhecer' && (
              <>
                <label style={{ display: 'block', fontSize: 12.5, fontWeight: 600, marginBottom: 4 }} htmlFor="lote-justificativa">
                  Justificativa (vale pra todas)
                </label>
                <textarea
                  id="lote-justificativa"
                  value={justificativa}
                  onChange={(e) => setJustificativa(e.target.value)}
                  placeholder="Ex: cargas recusadas na portaria no mesmo dia"
                  rows={3}
                  disabled={loteBusy}
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
                <div style={{ fontSize: 11, color: 'var(--fx-text-mute)', margin: '4px 0 0' }}>
                  {justificativa.length}/255 · {justificativa.trim().length < 15 ? `faltam ${15 - justificativa.trim().length} chars` : '✅ ok'}
                </div>
              </>
            )}

            <p style={{ fontSize: 12, color: 'var(--fx-text-dim)', margin: '14px 0' }}>
              <b>Manifestação é definitiva por nota — não há desfazer em lote.</b>
            </p>

            <div style={{ display: 'flex', gap: 8, justifyContent: 'flex-end' }}>
              <Button type="button" variant="cowork-ghost" onClick={() => setLoteModal(null)} disabled={loteBusy}>
                Voltar
              </Button>
              <Button
                type="button"
                variant={loteModal === 'desconhecer' ? 'destructive' : 'cowork-primary'}
                size="cowork"
                onClick={confirmLote}
                disabled={loteBusy || (loteModal === 'desconhecer' && justificativa.trim().length < 15)}
              >
                {loteBusy ? 'Enviando…' : `${LOTE_ROTULO[loteModal]} em lote`}
              </Button>
            </div>
          </div>
        </div>
      )}

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
              <Button type="button" variant="cowork-ghost" onClick={() => setModal(null)} disabled={busyId !== null}>
                Voltar
              </Button>
              {/* Tom por token semântico. O DS não tem variante `warning` no Button e
                  criar uma é soberania [W] — por isso o ramo "warn" é `outline` +
                  classes de token, não variante nova. */}
              <Button
                type="button"
                variant={modal.acao === 'desconhecer' ? 'destructive' : 'outline'}
                size="cowork"
                className={modal.acao === 'desconhecer' ? undefined : 'border-warning bg-warning text-white hover:bg-warning/90'}
                onClick={confirmModal}
                disabled={busyId !== null || justificativa.trim().length < 15}
              >
                {busyId !== null ? 'Enviando…' : 'Confirmar'}
              </Button>
            </div>
          </div>
        </div>
      )}
    </AppShellV2>
  );
}
