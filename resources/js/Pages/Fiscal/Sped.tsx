// @memcofre
//   tela: /fiscal/sped
//   module: Fiscal
//   stories: US-FISCAL-010 (SPED placeholder), US-FISCAL-016 (gerador EFD-ICMS/IPI MVP — PR #8)
//   adrs: 0093, 0094, 0101, 0104

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head } from '@inertiajs/react';
import { Archive, CheckCircle2, Download, Eye, FileSearch, X } from 'lucide-react';
import { useMemo, useState } from 'react';

import FxShell from './_components/FxShell';
import { brl } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

interface Periodo {
  mes: string;       // 05/2026
  mesIso: string;    // 2026-05
  notasAutorizadas: number;
  valorAutorizado: number;
  status: 'aberto' | 'pronto' | 'entregue';
  prazoEntrega: string | null;
}

interface SpedProps {
  periodos: Periodo[];
  notice: string;
}

const STATUS_META: Record<Periodo['status'], { label: string; tone: 'ok' | 'warn' | 'bad' }> = {
  aberto:   { label: 'Em curso',  tone: 'warn' },
  pronto:   { label: 'Pronto',    tone: 'ok' },
  entregue: { label: 'Entregue',  tone: 'ok' },
};

type StatusFilter = 'todos' | Periodo['status'];

// EFD-ICMS/IPI download URL — preserva exatamente a lógica do PR #8:
// /fiscal/sped/icms-ipi/{ano}/{mes-int} → controller gera .txt CONFAZ v3.1.1
const efdHref = (mesIso: string): string => {
  const [ano, mes] = mesIso.split('-');
  return `/fiscal/sped/icms-ipi/${ano}/${parseInt(mes ?? '1', 10)}`;
};

export default function Sped({ periodos, notice }: SpedProps) {
  const [status, setStatus] = useState<StatusFilter>('todos');
  const [search, setSearch] = useState('');
  const [preview, setPreview] = useState<Periodo | null>(null);

  const counts = useMemo(() => ({
    todos:    periodos.length,
    aberto:   periodos.filter((p) => p.status === 'aberto').length,
    pronto:   periodos.filter((p) => p.status === 'pronto').length,
    entregue: periodos.filter((p) => p.status === 'entregue').length,
  }), [periodos]);

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return periodos.filter((p) => {
      if (status !== 'todos' && p.status !== status) return false;
      if (q && !p.mes.toLowerCase().includes(q) && !p.mesIso.includes(q)) return false;
      return true;
    });
  }, [periodos, status, search]);

  return (
    <AppShellV2>
      <Head title="Fiscal · SPED & Livros" />

      <FxShell
        route="sped"
        title="SPED & Livros"
        crumb="Apuração mensal · EFD ICMS-IPI · PIS/COFINS"
        env="em desenvolvimento"
        envTone="warn"
      >
        {/* Callout do MVP — antes hex cru (#d4f4dd/#2da764) + inline-style.
            Agora fx-callout canon (mesmo padrão Dfe/Eventos), tokens only. */}
        <div className="fx-callout" role="region" aria-label="Status do gerador SPED">
          <CheckCircle2 size={16} />
          <div>
            <b>Gerador EFD-ICMS/IPI MVP disponível (PR #8)</b>
            <small>{notice}</small>
            <small>
              <b>Próximas Waves:</b> Bloco E (apuração ICMS · saldo mês anterior) · Bloco H (inventário anual)
              {' '}· EFD-Contribuições (PIS/COFINS arquivo separado) · Entradas via DF-e manifestada.
            </small>
          </div>
        </div>

        {/* Filtro + busca — padrão fx-filters (idêntico Dfe/Eventos) */}
        <div className="fx-filters">
          <div className="fx-search">
            <FileSearch size={13} />
            <input
              type="search"
              placeholder="Buscar competência (mm/aaaa)…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <button type="button" className={`fx-chip${status === 'todos' ? ' active' : ''}`} onClick={() => setStatus('todos')}>
            Todos <span>{counts.todos}</span>
          </button>
          <button type="button" className={`fx-chip warn${status === 'aberto' ? ' active' : ''}`} onClick={() => setStatus('aberto')}>
            Em curso <span>{counts.aberto}</span>
          </button>
          <button type="button" className={`fx-chip${status === 'pronto' ? ' active' : ''}`} onClick={() => setStatus('pronto')}>
            Pronto <span>{counts.pronto}</span>
          </button>
          <button type="button" className={`fx-chip${status === 'entregue' ? ' active' : ''}`} onClick={() => setStatus('entregue')}>
            Entregue <span>{counts.entregue}</span>
          </button>
        </div>

        {filtered.length === 0 ? (
          <div className="fx-empty">
            <Archive size={20} />
            <b>Nenhuma competência no filtro</b>
            <small>Ajuste a busca ou o status para ver os períodos disponíveis.</small>
          </div>
        ) : (
          <div className="fx-table">
            <table>
              <thead>
                <tr>
                  <th style={{ width: 110 }}>Competência</th>
                  <th style={{ width: 140 }}>Status</th>
                  <th style={{ textAlign: 'right' }}>Notas autorizadas</th>
                  <th style={{ textAlign: 'right', width: 160 }}>Valor autorizado</th>
                  <th style={{ width: 120 }}>Prazo entrega</th>
                  <th style={{ width: 120, textAlign: 'center' }}>Export</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((p) => {
                  const stMeta = STATUS_META[p.status];
                  return (
                    <tr key={p.mesIso}>
                      <td className="fx-mono fx-strong">{p.mes}</td>
                      <td>
                        <span className={`fx-sefaz ${stMeta.tone}`}>
                          <span className="lbl">{stMeta.label}</span>
                        </span>
                      </td>
                      <td className="fx-mono" style={{ textAlign: 'right' }}>{p.notasAutorizadas}</td>
                      <td className="fx-mono fx-strong" style={{ textAlign: 'right' }}>{brl(p.valorAutorizado)}</td>
                      <td><small>{p.prazoEntrega ?? '—'}</small></td>
                      <td>
                        <div className="fx-dfe-acts" style={{ justifyContent: 'center' }}>
                          <button
                            type="button"
                            className="fx-dfe-act"
                            title={`Pré-visualizar competência ${p.mes}`}
                            onClick={() => setPreview(p)}
                          >
                            <Eye size={11} />
                          </button>
                          {p.notasAutorizadas > 0 ? (
                            <a
                              href={efdHref(p.mesIso)}
                              className="fx-dfe-act ok"
                              title={`Baixar EFD-ICMS-IPI ${p.mes} (.txt CONFAZ v3.1.1)`}
                              download
                            >
                              <Download size={11} /> .txt
                            </a>
                          ) : (
                            <button type="button" className="fx-dfe-act" disabled title="Sem notas autorizadas no período">
                              <Download size={11} /> .txt
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        <div className="fx-empty" style={{ marginTop: 18 }}>
          <Archive size={20} />
          <b>Livros fiscais</b>
          <small>
            Apuração ICMS · Apuração ISS · Conciliação SEFAZ × ERP — em desenvolvimento.
            Por enquanto, conferir manualmente via relatórios em /financeiro/relatorios.
          </small>
        </div>
      </FxShell>

      {/* Preview drawer — resumo da competência antes do export (tokens canon) */}
      {preview && (() => {
        const stMeta = STATUS_META[preview.status];
        return (
          <div
            className="fx-drawer-bg"
            role="button"
            tabIndex={0}
            aria-label="Fechar"
            onClick={() => setPreview(null)}
            onKeyDown={(e) => {
              if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') setPreview(null);
            }}
          >
            {/* stopPropagation evita que clique no conteúdo feche o dialog; backdrop (acima) trata fechar+teclado */}
            {/* eslint-disable-next-line jsx-a11y/no-noninteractive-element-interactions, jsx-a11y/click-events-have-key-events */}
            <div className="fx-drawer" role="dialog" aria-modal="true" aria-label={`Competência ${preview.mes}`} onClick={(e) => e.stopPropagation()}>
              <div className="fx-drawer-h">
                <div>
                  <small>EFD-ICMS/IPI · competência</small>
                  <h2>{preview.mes}</h2>
                  <span className="fx-drawer-key">{preview.mesIso}</span>
                </div>
                <button type="button" className="fx-drawer-x" onClick={() => setPreview(null)} aria-label="Fechar">
                  <X size={14} />
                </button>
              </div>

              <div className="fx-drawer-body">
                <div className="fx-drawer-sec">
                  <h4>Situação</h4>
                  <div className="fx-drawer-status-row">
                    <span className={`fx-sefaz ${stMeta.tone}`}>
                      <span className="lbl">{stMeta.label}</span>
                    </span>
                  </div>
                </div>

                <div className="fx-drawer-sec">
                  <h4>Resumo</h4>
                  <dl className="fx-kv">
                    <dt>Notas</dt>
                    <dd className="fx-mono fx-strong">{preview.notasAutorizadas}</dd>
                    <dt>Valor</dt>
                    <dd className="fx-mono fx-strong">{brl(preview.valorAutorizado)}</dd>
                    <dt>Prazo</dt>
                    <dd>{preview.prazoEntrega ?? '—'}</dd>
                  </dl>
                </div>

                <p className="fx-drawer-hint">
                  Arquivo gerado no layout CONFAZ v3.1.1. Validar no PVA antes da transmissão à SEFAZ.
                </p>
              </div>

              <div className="fx-drawer-f">
                <div className="fx-drawer-f-r">
                  <button type="button" className="fx-btn ghost" onClick={() => setPreview(null)}>Fechar</button>
                  {preview.notasAutorizadas > 0 ? (
                    <a
                      href={efdHref(preview.mesIso)}
                      className="fx-btn primary"
                      title={`Baixar EFD-ICMS-IPI ${preview.mes} (.txt CONFAZ v3.1.1)`}
                      download
                    >
                      <Download size={13} /> Baixar .txt
                    </a>
                  ) : (
                    <button type="button" className="fx-btn primary" disabled title="Sem notas autorizadas no período">
                      <Download size={13} /> Baixar .txt
                    </button>
                  )}
                </div>
              </div>
            </div>
          </div>
        );
      })()}
    </AppShellV2>
  );
}
