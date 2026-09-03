// @memcofre
//   tela: /fiscal/sped
//   module: Fiscal
//   stories: US-FISCAL-010 (SPED placeholder), US-FISCAL-016 (gerador EFD-ICMS/IPI MVP — PR #8)
//   adrs: 0093, 0094, 0101, 0104

import { Inline, Stack } from '@/Components/layout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head } from '@inertiajs/react';
import { Archive, CheckCircle2, Download, Eye, FileSearch, X, XCircle } from 'lucide-react';
import { useMemo, useState } from 'react';

import FxShell from './_components/FxShell';
import { chipCount, chipProps } from './_lib/chip-filtro';
import { brl } from './_lib/fiscal-helpers';

import '../../../css/fiscal-cockpit.css';

interface Checagem {
  id: 'ano-minimo' | 'nao-futura' | 'fechada' | 'trava';
  ok: boolean;
  rotulo: string;
  motivo: string;
}

interface Periodo {
  mes: string;       // 05/2026
  mesIso: string;    // 2026-05
  notasAutorizadas: number;
  valorAutorizado: number;
  status: 'aberto' | 'pronto' | 'entregue';
  prazoEntrega: string | null;
  /** As 4 checagens da régua, avaliadas no SERVIDOR (SpedController::checagens). */
  checagens: Checagem[];
}

interface SpedProps {
  periodos: Periodo[];
  notice: string;
  /**
   * Prévia do conteúdo do TXT. Hoje é sempre `null` — ausência DECLARADA, não
   * amostra fabricada: gerar o arquivo só pra pré-visualizar exigiria rodar o
   * gerador inteiro em request síncrono, o que contornaria a trava fail-secure
   * `fiscal.sped_simples_only_lock`. Ver Sped.charter.md §Contrato destilado.
   */
  previaTxt: string | null;
}

/**
 * O gate único do download: notas no período E as 4 checagens aprovadas.
 * O motivo devolvido é o que vai pro `title` do controle desabilitado — foi o
 * padrão que a tela já usava com "Sem notas autorizadas no período", agora
 * estendido em vez de duplicado.
 */
const motivoBloqueio = (p: Periodo): string | null => {
  if (p.notasAutorizadas === 0) return 'Sem notas autorizadas no período';
  const reprovada = (p.checagens ?? []).find((c) => !c.ok);
  return reprovada ? reprovada.motivo : null;
};

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

export default function Sped({ periodos, notice, previaTxt }: SpedProps) {
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
        {/* Callout do MVP na primitiva <Alert> do DS (era a classe hand-rolled
            `fx-callout`). O `role="region"` + `aria-label` são MANTIDOS de propósito:
            o <Alert> traz `role="alert"` embutido, que é live-region assertiva — errado
            pra um banner informativo estático. Como o `{...props}` do Alert vem DEPOIS
            do role padrão, passar `role` aqui sobrescreve. */}
        <Alert
          className="mb-3"
          data-contract="fiscal-sped-status"
          role="region"
          aria-label="Status do gerador SPED"
        >
          <CheckCircle2 size={16} />
          <AlertTitle>Gerador EFD-ICMS/IPI MVP disponível (PR #8)</AlertTitle>
          <AlertDescription>
            <span>{notice}</span>
            <span>
              <b>Próximas Waves:</b> Bloco E (apuração ICMS · saldo mês anterior) · Bloco H (inventário anual)
              {' '}· EFD-Contribuições (PIS/COFINS arquivo separado) · Entradas via DF-e manifestada.
            </span>
          </AlertDescription>
        </Alert>

        {/* Filtro + busca — primitivas do DS (<Inline> + <Input>). Dfe/Eventos ainda
            usam a classe hand-rolled `fx-filters`; migram nas levas seguintes da Onda 1. */}
        <Inline gap={2} align="center" wrap className="mb-3">
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
              placeholder="Buscar competência (mm/aaaa)…"
              aria-label="Buscar competência (mm/aaaa)"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <Button
            type="button"
            {...chipProps(status === 'todos')}
            aria-pressed={status === 'todos'}
            onClick={() => setStatus('todos')}
          >
            Todos <span className={chipCount(status === 'todos')}>{counts.todos}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(status === 'aberto', 'warn')}
            aria-pressed={status === 'aberto'}
            onClick={() => setStatus('aberto')}
          >
            Em curso <span className={chipCount(status === 'aberto')}>{counts.aberto}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(status === 'pronto')}
            aria-pressed={status === 'pronto'}
            onClick={() => setStatus('pronto')}
          >
            Pronto <span className={chipCount(status === 'pronto')}>{counts.pronto}</span>
          </Button>
          <Button
            type="button"
            {...chipProps(status === 'entregue')}
            aria-pressed={status === 'entregue'}
            onClick={() => setStatus('entregue')}
          >
            Entregue <span className={chipCount(status === 'entregue')}>{counts.entregue}</span>
          </Button>
        </Inline>

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
                          {motivoBloqueio(p) === null ? (
                            <a
                              href={efdHref(p.mesIso)}
                              className="fx-dfe-act ok"
                              title={`Baixar EFD-ICMS-IPI ${p.mes} (.txt CONFAZ v3.1.1)`}
                              download
                            >
                              <Download size={11} /> .txt
                            </a>
                          ) : (
                            <button type="button" className="fx-dfe-act" disabled title={motivoBloqueio(p) ?? undefined}>
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

                {/* Régua de geração — as 4 checagens vêm avaliadas do servidor
                    (SpedController::checagens). A tela renderiza; não decide. */}
                <div className="fx-drawer-sec">
                  <h4>Régua de geração</h4>
                  <Alert variant={motivoBloqueio(preview) === null ? 'default' : 'destructive'}>
                    {motivoBloqueio(preview) === null ? <CheckCircle2 size={16} /> : <XCircle size={16} />}
                    <AlertTitle>
                      {motivoBloqueio(preview) === null
                        ? 'Competência liberada para geração'
                        : 'Geração bloqueada'}
                    </AlertTitle>
                    <AlertDescription>
                      <Stack asChild gap={1}>
                        <ul className="mt-1">
                          {(preview.checagens ?? []).map((c) => (
                            <Inline key={c.id} asChild align="start" gap={2}>
                              <li>
                                {c.ok ? (
                                  <CheckCircle2 size={13} aria-hidden className="mt-0.5 shrink-0" />
                                ) : (
                                  <XCircle size={13} aria-hidden className="mt-0.5 shrink-0" />
                                )}
                                <span>
                                  <b>{c.rotulo}</b> — <span>{c.ok ? 'aprovado' : 'reprovado'}</span>
                                  <br />
                                  <small>{c.motivo}</small>
                                </span>
                              </li>
                            </Inline>
                          ))}
                          {preview.notasAutorizadas === 0 && (
                            <Inline asChild align="start" gap={2}>
                              <li>
                                <XCircle size={13} aria-hidden className="mt-0.5 shrink-0" />
                                <span>
                                  <b>Notas autorizadas no período</b> — <span>reprovado</span>
                                  <br />
                                  <small>Sem notas autorizadas no período</small>
                                </span>
                              </li>
                            </Inline>
                          )}
                        </ul>
                      </Stack>
                    </AlertDescription>
                  </Alert>
                </div>

                {/* Prévia do TXT — ausência DECLARADA. Ver Sped.charter.md
                    §Contrato destilado: gerar o arquivo só pra pré-visualizar
                    exigiria rodar o gerador inteiro em request síncrono, o que
                    contornaria a trava fail-secure. Decisão pendente. */}
                <div className="fx-drawer-sec">
                  <h4>Prévia do arquivo</h4>
                  {previaTxt === null ? (
                    <p className="fx-drawer-hint">
                      Prévia do conteúdo indisponível nesta versão — o conteúdo do arquivo só é
                      conhecido depois de gerado. O que já está definido pelo layout:
                      EFD-ICMS/IPI CONFAZ v3.1.1, perfil A, registro de abertura 0000 com
                      COD_VER 018 e COD_FIN 0 (original).
                    </p>
                  ) : (
                    <>
                      <p className="fx-drawer-hint">
                        <b>Amostra</b> — trecho inicial do arquivo, não o arquivo completo.
                      </p>
                      <pre className="fx-mono overflow-x-auto whitespace-pre text-xs">{previaTxt}</pre>
                    </>
                  )}
                </div>

                <p className="fx-drawer-hint">
                  Arquivo gerado no layout CONFAZ v3.1.1. Validar no PVA antes da transmissão à SEFAZ.
                </p>
              </div>

              <div className="fx-drawer-f">
                <div className="fx-drawer-f-r">
                  <Button type="button" variant="cowork-ghost" onClick={() => setPreview(null)}>Fechar</Button>
                  {motivoBloqueio(preview) === null ? (
                    <Button asChild variant="cowork-primary">
                      <a
                        href={efdHref(preview.mesIso)}
                        title={`Baixar EFD-ICMS-IPI ${preview.mes} (.txt CONFAZ v3.1.1)`}
                        download
                      >
                        <Download size={13} /> Baixar .txt
                      </a>
                    </Button>
                  ) : (
                    <Button type="button" variant="cowork-primary" disabled title={motivoBloqueio(preview) ?? undefined}>
                      <Download size={13} /> Baixar .txt
                    </Button>
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
