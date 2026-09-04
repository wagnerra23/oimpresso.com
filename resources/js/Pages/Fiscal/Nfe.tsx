// @memcofre
//   tela: /fiscal/nfe
//   module: Fiscal (cockpit unificado)
//   status: em-implementacao
//   stories: US-FISCAL-001 (cockpit NF-e · NFC-e — sub-página 2 do design KB-9.75)
//   rules: R-FIN-001 (multi-tenant), R-SEFAZ-001 (24h cancel NFC-e / 168h NF-e)
//   adrs: 0093 (multi-tenant), 0104 (MWART), 0114 (cowork-loop), 0143 (FSM cancel cascade)
//   tests: Modules/Fiscal/Tests/Feature/NfeCockpitMultiTenantTest
//
// Origem: design Cowork "Oimpresso ERP — Chat" / fiscal-page.jsx §9 (FiscalNFePage), aprovado por [W] 2026-05-20.
// Persona: Eliana (contadora) + Wagner (operador fiscal).
// Tokens: var(--fis) — acento do Fiscal; desde 2026-09-01 é o roxo canônico do DS
//   oklch(0.55 0.15 295) (ADR 0235), não mais o hue 30 herdado do port. Mais
//   var(--ok), var(--warn), var(--bad). Definidos em resources/css/fiscal-cockpit.css.
// Não-duplica: lê Modules/NfeBrasil/Models/NfeEmissao via NfeCockpitController.

import { Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router } from '@inertiajs/react';
import { Eraser, FileSearch, Plus, RefreshCw } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

import FxShell from './_components/FxShell';
import InutilizacaoModal from './_components/InutilizacaoModal';
import NotaDrawer, { type NotaRow } from './_components/NotaDrawer';
import { chipCount, chipProps } from './_lib/chip-filtro';
import {
  brl,
  formatDoc,
  juntarInfo,
  prazoCancel,
  truncKey,
  type SefazCodesMap,
} from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

interface Filters {
  search: string;
  status: 'todas' | 'autorizadas' | 'rejeitadas' | 'processando' | 'canceladas' | 'cancelaveis';
  tab: 'saida_nfe' | 'saida_nfce' | 'entrada';
  focus?: string;
}

interface Counts {
  total: number;
  nfe: number;
  nfce: number;
  autorizadas: number;
  rejeitadas: number;
  processando: number;
  canceladas: number;
  cancelaveis: number;
}

interface RowsPayload {
  data: NotaRow[];
  meta: { current_page: number; last_page: number; total: number; per_page: number };
}

interface NfeProps {
  filters: Filters;
  counts: Counts;
  sefazCodes: SefazCodesMap;
  rows?: RowsPayload;
}

export default function Nfe({ filters: initialFilters, counts, sefazCodes, rows }: NfeProps) {
  const [filters, setFilters] = useState<Filters>(initialFilters);
  const [opened, setOpened] = useState<NotaRow | null>(null);
  const [cursor, setCursor] = useState(0);
  const [inutOpen, setInutOpen] = useState(false);

  const dataRows: NotaRow[] = rows?.data ?? [];

  // Apply filter changes via partial reload (only:[rows]) — Inertia defer pattern
  const applyFilters = (next: Partial<Filters>) => {
    const merged = { ...filters, ...next };
    setFilters(merged);
    setCursor(0);
    router.visit('/fiscal/nfe', {
      data: merged as unknown as Record<string, string>,
      only: ['rows', 'counts', 'filters'],
      preserveState: true,
      preserveScroll: true,
    });
  };

  // J/K navegação na lista (skill cockpit-runbook §3)
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      const target = e.target as HTMLElement | null;
      const isTyping =
        target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable);
      if (isTyping) return;

      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault();
        setCursor(c => Math.min(dataRows.length - 1, c + 1));
      } else if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault();
        setCursor(c => Math.max(0, c - 1));
      } else if (e.key === 'Enter' && dataRows[cursor]) {
        e.preventDefault();
        setOpened(dataRows[cursor]);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [dataRows, cursor]);

  // Crumb dinâmico
  const crumb = useMemo(
    () =>
      `Modelos 55 + 65 · ${counts.nfe + counts.nfce} no mês · ${counts.rejeitadas} requerem ação`,
    [counts],
  );

  const envTone: 'ok' | 'warn' = counts.rejeitadas > 0 ? 'warn' : 'ok';
  const envLabel = `${counts.autorizadas} autorizadas · ${counts.processando} processando`;

  return (
    <AppShellV2>
      <Head title="Fiscal · NF-e · NFC-e" />

      <FxShell
        route="nfe"
        title="NF-e · NFC-e"
        crumb={crumb}
        env={envLabel}
        envTone={envTone}
        counts={{ nfe: counts.rejeitadas }}
        cheats={[
          { keys: ['J', 'K'], label: 'navegar' },
          { keys: ['⏎'],      label: 'abrir' },
          // `R` (reconsultar SEFAZ) e `X` (cancelar) foram removidos em 2026-09-04: estavam
          // anunciados aqui como "(em breve)" e não existe handler para nenhuma das duas — o
          // `keydown` desta tela trata só j/k/setas/Enter. O rótulo era honesto, mas a barra de
          // atalhos é onde o operador APRENDE as teclas: duas mortas ali ensinam errado, e ele
          // aperta e conclui que a tela travou. As duas AÇÕES existem e seguem intactas no
          // drawer (US-FISCAL-012/014). Reatalhar é decisão de produto: `X` abre um fluxo que
          // exige motivo de 15–255 chars, então não é uma tecla, é uma porta.
        ]}
        actions={
          <>
            <Button variant="cowork-ghost" disabled title="PR seguinte">
              Importar XML
            </Button>
            {/* Tom "warn" não existe como variante do Button, e NÃO uso `cowork-ghost`
                + utilitária de cor: `.cw-btn-*` mora unlayered e VENCE a Tailwind, que é
                layered (armadilha documentada em cowork-fields.css:168-176). Por isso
                aqui é `outline` + `size="cowork"` — ambos layered, então compõem. */}
            <Button
              type="button"
              variant="outline"
              size="cowork"
              className="border-warning bg-warning text-white hover:bg-warning/90"
              onClick={() => setInutOpen(true)}
              title="Inutiliza faixa numérica de NFe (SEFAZ cstat=102 — fecha buracos fiscais)"
            >
              <Eraser size={12} aria-hidden="true"/> Inutilizar faixa
            </Button>
            <Button variant="cowork-primary" disabled title="PR seguinte">
              <Plus size={12} aria-hidden="true"/> Emitir <kbd className="fx-kbd-inline">E</kbd>
            </Button>
          </>
        }
      >
        {/* Tabs internas: modelo */}
        <div className="fx-subtabs">
          <button
            type="button"
            className={`fx-subtab${filters.tab === 'saida_nfe' ? ' active' : ''}`}
            onClick={() => applyFilters({ tab: 'saida_nfe' })}
          >
            NF-e (55) <span className="n">{counts.nfe}</span>
          </button>
          <button
            type="button"
            className={`fx-subtab${filters.tab === 'saida_nfce' ? ' active' : ''}`}
            onClick={() => applyFilters({ tab: 'saida_nfce' })}
          >
            NFC-e (65) <span className="n">{counts.nfce}</span>
          </button>
          <button
            type="button"
            className={`fx-subtab${filters.tab === 'entrada' ? ' active' : ''}`}
            onClick={() => applyFilters({ tab: 'entrada' })}
          >
            Entrada (XML) <span className="n">0</span>
          </button>
        </div>

        {filters.tab === 'entrada' ? (
          <div className="fx-empty">
            <b>Importação de XML de fornecedor</b>
            <p>
              Arraste um XML aqui para vincular a um pedido em <a href="/purchases" className="fx-link">Compras</a>,
              baixar estoque e gerar título a pagar.
            </p>
            <small>Backlog F2 · depende de Modules/NfeBrasil expor o endpoint de importação.</small>
          </div>
        ) : (
          <>
            {/* Filtros chip-row */}
            <Inline gap={2} align="center" wrap className="mb-3" data-contract="fiscal-nfe-filters">
              {/* Busca na primitiva <Input> do DS. O espaço da lupa vem da utilitária
                  canon `.cw-input-icon-left` (cowork-fields.css), NÃO de `pl-*`: a
                  Tailwind é layered e perde pro `.cw-input` unlayered. Mesmo idioma de
                  Jana/Memoria.tsx e Cliente/Index.tsx. `InputGroup` não serve — ele é
                  addon em caixa AO LADO, não ícone DENTRO do campo. */}
              <div className="relative min-w-[240px] flex-1">
                <FileSearch
                  size={13}
                  className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                  aria-hidden="true"
                />
                <Input
                  type="search"
                  className="cw-input-icon-left"
                  placeholder="Buscar nº, chave (últimos 6 dígitos), ou motivo SEFAZ…"
                  aria-label="Buscar notas por número, chave ou motivo SEFAZ"
                  value={filters.search}
                  onChange={(e) => setFilters(f => ({ ...f, search: e.target.value }))}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') applyFilters({ search: filters.search });
                  }}
                />
              </div>
              <Button
                type="button"
                {...chipProps(filters.status === 'todas')}
                aria-pressed={filters.status === 'todas'}
                onClick={() => applyFilters({ status: 'todas' })}
              >Todas <span className={chipCount(filters.status === 'todas')}>{counts.total}</span></Button>
              <Button
                type="button"
                {...chipProps(filters.status === 'autorizadas')}
                aria-pressed={filters.status === 'autorizadas'}
                onClick={() => applyFilters({ status: 'autorizadas' })}
              >Autorizadas <span className={chipCount(filters.status === 'autorizadas')}>{counts.autorizadas}</span></Button>
              <Button
                type="button"
                {...chipProps(filters.status === 'rejeitadas', 'danger')}
                aria-pressed={filters.status === 'rejeitadas'}
                onClick={() => applyFilters({ status: 'rejeitadas' })}
              >Rejeitadas <span className={chipCount(filters.status === 'rejeitadas')}>{counts.rejeitadas}</span></Button>
              <Button
                type="button"
                {...chipProps(filters.status === 'cancelaveis', 'warn')}
                aria-pressed={filters.status === 'cancelaveis'}
                onClick={() => applyFilters({ status: 'cancelaveis' })}
              >
                <RefreshCw size={11} aria-hidden="true"/> Janela 24h <span className={chipCount(filters.status === 'cancelaveis')}>{counts.cancelaveis}</span>
              </Button>
              <Button
                type="button"
                {...chipProps(filters.status === 'processando')}
                aria-pressed={filters.status === 'processando'}
                onClick={() => applyFilters({ status: 'processando' })}
              >Processando <span className={chipCount(filters.status === 'processando')}>{counts.processando}</span></Button>
            </Inline>

            {/* Tabela com Deferred (Inertia partial reload) */}
            <Deferred data="rows" fallback={
              <div className="fx-empty">
                <b>Carregando notas…</b>
                <small>Buscando emissões no banco · multi-tenant scope ativo</small>
              </div>
            }>
              {dataRows.length === 0 ? (
                <div className="fx-empty">
                  <b>Nenhuma nota encontrada</b>
                  <small>Ajuste os filtros ou inicie uma emissão.</small>
                </div>
              ) : (
                <div className="fx-table" data-keyboard="true">
                  <table>
                    <thead>
                      <tr>
                        <th style={{ width: 88 }}>Número</th>
                        <th>Chave / destinatário</th>
                        <th style={{ width: 200 }}>Status SEFAZ</th>
                        <th style={{ width: 110, textAlign: 'right' }}>Valor</th>
                        <th style={{ width: 96 }}>Emissão</th>
                      </tr>
                    </thead>
                    <tbody>
                      {dataRows.map((n, idx) => {
                        const sefaz = sefazCodes[n.cstat] ?? { tone: 'warn', label: 'Status', hint: '' };
                        const cancel = prazoCancel(n);
                        const isFocus = idx === cursor;
                        return (
                          <tr
                            key={n.id}
                            className={isFocus ? 'fx-row-focus' : ''}
                            tabIndex={0}
                            aria-label={`Abrir ${n.modelo === 65 ? 'NFC-e' : 'NF-e'} ${n.num} · ${n.dest || '—'}`}
                            onClick={() => setOpened(n)}
                            onFocus={() => setCursor(idx)}
                            onKeyDown={(e) => {
                              if (e.key !== 'Enter' && e.key !== ' ') return;
                              // preventDefault: sem ele o Space ROLA a página (default do browser
                              // em elemento focável). stopPropagation: sem ele o Enter sobe pro
                              // handler global de window (o J/K acima) e o drawer abriria duas vezes.
                              e.preventDefault();
                              e.stopPropagation();
                              setOpened(n);
                            }}
                          >
                            <td className="fx-mono">
                              <b>{n.num}</b>
                              <small>{n.modelo === 65 ? 'NFC-e' : 'NF-e'} · s{n.serie}</small>
                            </td>
                            <td>
                              <div className="fx-cell-key">{truncKey(n.key)}</div>
                              <small>{juntarInfo(n.dest, formatDoc(n.cnpj, n.cpf))}</small>
                            </td>
                            <td>
                              <span className={`fx-sefaz ${sefaz.tone}`} title={sefaz.hint}>
                                <span className="code">{n.cstat || '—'}</span>
                                <span className="lbl">{sefaz.label}</span>
                              </span>
                              {cancel && (
                                <span className={`fx-timepill u-${cancel.urgency} compact`}>
                                  <RefreshCw size={9} aria-hidden="true"/>
                                  <span className="lbl"><b>{cancel.h}h</b></span>
                                </span>
                              )}
                            </td>
                            <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>
                              {brl(n.value)}
                            </td>
                            <td>
                              <small>{n.when ?? '—'}</small>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </Deferred>
          </>
        )}
      </FxShell>

      <NotaDrawer nota={opened} sefazCodes={sefazCodes} onClose={() => setOpened(null)} />
      <InutilizacaoModal
        open={inutOpen}
        onClose={() => setInutOpen(false)}
        defaultModelo={filters.tab === 'saida_nfce' ? '65' : '55'}
      />
    </AppShellV2>
  );
}
