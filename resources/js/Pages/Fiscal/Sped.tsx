// @memcofre
//   tela: /fiscal/sped
//   module: Fiscal
//   stories: US-FISCAL-010 (SPED placeholder), US-FISCAL-016 (gerador EFD-ICMS/IPI MVP — PR #8)
//   adrs: 0093, 0094, 0101, 0104

import { Grid, Inline, Stack } from '@/Components/layout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router } from '@inertiajs/react';
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

/** Um bloco do arquivo, MEDIDO no golden — nunca uma lista escrita à mão. */
interface BlocoArquivo {
  id: string;
  nome: string;
  linhas: number;
  registros: string[];
}

/**
 * Estrutura do arquivo de REFERÊNCIA (o golden), medida a cada request pelo
 * `SpedReferenciaArquivoService`. Não é o arquivo do usuário: o `0000` do golden
 * declara `CI TENANT 98 (FICTICIO)` como emitente, e a tela diz isso onde o expõe.
 */
interface ReferenciaArquivo {
  disponivel: boolean;
  origem: string;
  bytes: number | null;
  linhas: number | null;
  sha256: string | null;
  blocos: BlocoArquivo[];
}

/** O que foi provado FORA daqui — cada item derivado do disco, não afirmado. */
interface ValidacaoExterna {
  golden: {
    presente: boolean;
    bytes: number | null;
    linhas: number | null;
    sha256: string | null;
    origem: string;
  };
  pvaSmoke: { executado: boolean; origem: string };
  apuracaoIcms: { noArquivo: boolean };
  backlog: string[];
}

/**
 * Bypass de superadmin (Onda 10 · Goal 2).
 *
 * `disponivel` é falso pra todo mundo que não é superadmin — e aí a tela não
 * mostra ação nenhuma: liberar a trava global é decisão de [W], não de tela.
 */
interface BypassSuperadmin {
  disponivel: boolean;
  travaGlobalLigada: boolean;
  reativadaNaSessao: boolean;
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
  referenciaArquivo: ReferenciaArquivo;
  validacaoExterna: ValidacaoExterna;
  bypassSuperadmin: BypassSuperadmin;
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

/**
 * Itens da régua de geração de UMA competência.
 *
 * As 4 checagens vêm avaliadas do servidor (`SpedController::checagens`); a
 * contagem de notas é a quinta condição que `motivoBloqueio` já somava. Aqui só
 * se renderiza — mover qualquer critério para cá faria a tela e o Service
 * divergirem sobre quando um arquivo fiscal pode sair (anti-hook do charter).
 */
function ItensDaRegua({ periodo }: { periodo: Periodo }) {
  const itens = [
    ...(periodo.checagens ?? []).map((c) => ({
      chave: c.id,
      ok: c.ok,
      rotulo: c.rotulo,
      motivo: c.motivo,
    })),
    {
      chave: 'notas',
      ok: periodo.notasAutorizadas > 0,
      rotulo: 'Notas autorizadas no período',
      motivo:
        periodo.notasAutorizadas > 0
          ? `${periodo.notasAutorizadas} nota(s) autorizada(s) entram no arquivo`
          : 'Sem notas autorizadas no período',
    },
  ];

  return (
    <Stack asChild gap={1}>
      <ul className="mt-1">
        {itens.map((item) => (
          <Inline key={item.chave} asChild align="start" gap={2}>
            <li data-checagem={item.chave} data-ok={item.ok ? 'true' : 'false'}>
              {item.ok ? (
                <CheckCircle2 size={13} aria-hidden className="mt-0.5 shrink-0" />
              ) : (
                <XCircle size={13} aria-hidden className="mt-0.5 shrink-0" />
              )}
              <span>
                <b>{item.rotulo}</b> — <span>{item.ok ? 'aprovado' : 'reprovado'}</span>
                <br />
                <small>{item.motivo}</small>
              </span>
            </li>
          </Inline>
        ))}
      </ul>
    </Stack>
  );
}

export default function Sped({
  periodos,
  notice,
  previaTxt,
  referenciaArquivo,
  validacaoExterna,
  bypassSuperadmin,
}: SpedProps) {
  const [status, setStatus] = useState<StatusFilter>('todos');
  const [search, setSearch] = useState('');
  const [preview, setPreview] = useState<Periodo | null>(null);
  const [selecionadaIso, setSelecionadaIso] = useState<string | null>(null);

  /**
   * Competência da barra de validação. Sem escolha do operador, abre na primeira
   * competência PRONTA — a que ele de fato vai gerar. Abrir no mês corrente
   * mostraria sempre a régua reprovando por "em aberto", que é o caso menos útil.
   */
  const selecionada = useMemo(() => {
    const escolhida = selecionadaIso
      ? periodos.find((p) => p.mesIso === selecionadaIso)
      : undefined;
    return escolhida ?? periodos.find((p) => p.status === 'pronto') ?? periodos[0] ?? null;
  }, [periodos, selecionadaIso]);

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

        {/* Barra de validação da competência — Goal 1 do charter do Cowork.
            Ficava DENTRO do drawer (Onda 9) e por isso só existia pra quem
            clicasse na lupa; o protótipo a tem como barra NA PÁGINA, sempre
            visível. Foi MOVIDA, não duplicada: régua em dois lugares diverge no
            primeiro ajuste. Quem decide continua sendo o servidor. */}
        {selecionada && (
          <Alert
            className="mb-3"
            data-contract="validacao-competencia"
            data-ok={motivoBloqueio(selecionada) === null ? 'true' : 'false'}
            variant={motivoBloqueio(selecionada) === null ? 'default' : 'destructive'}
            role="region"
            aria-label={`Régua de geração da competência ${selecionada.mes}`}
          >
            {motivoBloqueio(selecionada) === null ? <CheckCircle2 size={16} /> : <XCircle size={16} />}
            <AlertTitle>
              Competência {selecionada.mes} —{' '}
              {motivoBloqueio(selecionada) === null
                ? 'liberada para geração'
                : 'geração bloqueada'}
            </AlertTitle>
            <AlertDescription>
              <ItensDaRegua periodo={selecionada} />

              {/* Goal 2 — o bypass de superadmin deixa de ser silencioso.
                  Só aparece pra quem TEM o bypass: liberar a trava global é
                  decisão de [W], e oferecer o botão a quem não pode usá-lo
                  seria afordância falsa. A ação vai ao servidor (a sessão é
                  quem decide), nunca troca só o visual. */}
              {bypassSuperadmin.disponivel && bypassSuperadmin.travaGlobalLigada && (
                <Inline gap={2} align="center" wrap className="mt-3">
                  <Button
                    type="button"
                    variant={bypassSuperadmin.reativadaNaSessao ? 'cowork-primary' : 'secondary'}
                    size="cowork"
                    onClick={() =>
                      router.post(
                        '/fiscal/sped/trava',
                        { reativar: !bypassSuperadmin.reativadaNaSessao },
                        { preserveScroll: true },
                      )
                    }
                  >
                    {bypassSuperadmin.reativadaNaSessao
                      ? 'Liberar como superadmin'
                      : 'Reativar trava nesta sessão'}
                  </Button>
                  <small className="text-muted-foreground">
                    {bypassSuperadmin.reativadaNaSessao
                      ? 'Você reativou a trava para si — o download está bloqueado nesta sessão.'
                      : 'Seu perfil dispensa a trava fail-secure. A trava global segue ligada para os demais.'}
                  </small>
                </Inline>
              )}
            </AlertDescription>
          </Alert>
        )}

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
                    <tr
                      key={p.mesIso}
                      data-selecionada={selecionada?.mesIso === p.mesIso ? 'true' : undefined}
                      className={selecionada?.mesIso === p.mesIso ? 'bg-accent/40' : undefined}
                    >
                      <td className="fx-mono fx-strong">
                        {/* Trocar a competência da barra. É um botão, não a linha
                            inteira clicável: `<tr onClick>` exige handler de teclado
                            e um role que a tabela não tem, e o alvo real do operador
                            é a competência. */}
                        <Button
                          type="button"
                          variant="link"
                          size="xs"
                          className="h-auto px-0 font-mono text-xs font-semibold text-foreground"
                          aria-pressed={selecionada?.mesIso === p.mesIso}
                          title={`Ver a régua de geração de ${p.mes}`}
                          onClick={() => setSelecionadaIso(p.mesIso)}
                        >
                          {p.mes}
                        </Button>
                      </td>
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

        {/* Goals 4 e 5 do charter do Cowork. As duas superfícies são MEDIDAS no
            arquivo de referência pelo servidor — a tela não escreve estrutura nem
            estado de validação. Foi por isso que o cartão de validação não pôde
            copiar a copy do protótipo: ele diz "golden file: não existe", e o
            golden nasceu em 2026-09-03 (PR #6708), depois do charter. */}
        <Grid fit="md" gap={3} className="mt-4">
          <Card data-contract="blocos-arquivo">
            <CardHeader>
              <CardTitle>Blocos do arquivo — registros que cada um contém</CardTitle>
            </CardHeader>
            <CardContent>
              {referenciaArquivo.disponivel ? (
                <Stack gap={3}>
                  <Stack asChild gap={2}>
                    <ul>
                      {referenciaArquivo.blocos.map((bloco) => (
                        <li key={bloco.id} data-bloco={bloco.id}>
                          <Inline align="baseline" gap={2}>
                            <b className="fx-mono">{bloco.id}</b>
                            <span>{bloco.nome}</span>
                            <small className="ml-auto text-muted-foreground">
                              {bloco.linhas} linha{bloco.linhas === 1 ? '' : 's'}
                            </small>
                          </Inline>
                          <code className="fx-mono block text-[11px] text-muted-foreground">
                            {bloco.registros.join(' · ')}
                          </code>
                        </li>
                      ))}
                    </ul>
                  </Stack>
                  <small className="text-muted-foreground">
                    Medido no arquivo de referência EFD-ICMS/IPI (CONFAZ v3.1.1, perfil A) —
                    não na sua competência, que só é conhecida depois de gerada.
                  </small>
                </Stack>
              ) : (
                <small className="text-muted-foreground">
                  Arquivo de referência indisponível ({referenciaArquivo.origem}). A estrutura
                  não é exibida em vez de ser presumida.
                </small>
              )}
            </CardContent>
          </Card>

          <Card data-contract="validacao-externa">
            <CardHeader>
              <CardTitle>Validação externa</CardTitle>
            </CardHeader>
            <CardContent>
              <Stack asChild gap={2}>
                <dl>
                  <Inline align="baseline" gap={2} asChild>
                    <div>
                      <dt className="flex-1">Smoke no PVA-EFD (validador CONFAZ)</dt>
                      <dd
                        className="fx-mono"
                        data-tone={validacaoExterna.pvaSmoke.executado ? 'ok' : 'warn'}
                      >
                        {validacaoExterna.pvaSmoke.executado ? 'recibo registrado' : 'nunca executado'}
                      </dd>
                    </div>
                  </Inline>
                  <Inline align="baseline" gap={2} asChild>
                    <div>
                      <dt className="flex-1">Arquivo de referência (golden)</dt>
                      <dd className="fx-mono" data-tone={validacaoExterna.golden.presente ? 'ok' : 'warn'}>
                        {validacaoExterna.golden.presente
                          ? `existe · ${new Intl.NumberFormat('pt-BR').format(validacaoExterna.golden.bytes ?? 0)} bytes · ${validacaoExterna.golden.linhas} linhas`
                          : 'não existe'}
                      </dd>
                    </div>
                  </Inline>
                  {validacaoExterna.golden.presente && validacaoExterna.golden.sha256 && (
                    <Inline align="baseline" gap={2} asChild>
                      <div>
                        <dt className="flex-1">SHA-256 da referência</dt>
                        <dd className="fx-mono text-[11px]">
                          {validacaoExterna.golden.sha256.slice(0, 16)}…
                        </dd>
                      </div>
                    </Inline>
                  )}
                  <Inline align="baseline" gap={2} asChild>
                    <div>
                      <dt className="flex-1">Apuração do ICMS</dt>
                      <dd className="fx-mono" data-tone={validacaoExterna.apuracaoIcms.noArquivo ? 'ok' : 'warn'}>
                        {validacaoExterna.apuracaoIcms.noArquivo
                          ? 'no arquivo (Bloco E)'
                          : 'ausente do arquivo'}
                      </dd>
                    </div>
                  </Inline>
                  <Inline align="baseline" gap={2} asChild>
                    <div>
                      <dt className="flex-1">{validacaoExterna.backlog.join(' · ')}</dt>
                      <dd className="fx-mono">backlog</dd>
                    </div>
                  </Inline>
                </dl>
              </Stack>
              <small className="mt-3 block text-muted-foreground">
                O arquivo de referência prova estrutura, blocos e contadores. O PVA-EFD é
                ferramenta externa: enquanto não houver recibo do smoke, nada aqui autoriza
                chamar o gerador de validado.
              </small>
            </CardContent>
          </Card>
        </Grid>

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

                {/* Régua de geração — mesma régua da barra da página, servida pelo
                    mesmo `ItensDaRegua` e pelo mesmo payload do servidor. Ela vive
                    na página desde a Onda 10; fica repetida aqui porque o drawer é
                    o passo imediatamente anterior ao download, e mandar o operador
                    fechar o drawer pra ler POR QUE o botão ao lado está cinza seria
                    esconder a resposta na hora em que ela é pedida. Não há segunda
                    fonte: um componente, um payload. */}
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
                      <ItensDaRegua periodo={preview} />
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
