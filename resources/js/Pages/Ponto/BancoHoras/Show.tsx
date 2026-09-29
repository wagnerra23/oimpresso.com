// @docvault
//   tela: /ponto/banco-horas/show
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-003
//   rules: R-PONT-001
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/BancoHorasShowTest

// FORMA = protótipo: ponto-telas.jsx, símbolo BancoHoras, ramo `if (sel)` (:352-407) —
// a view que abre em "Detalhes" na lista de saldos. Eixo forma segue o protótipo (ADR UI-0029).
// As medidas vêm do RENDER do protótipo (sonda de 2026-09-29, 1728×1117 dark), não do
// ponto-page.css: os componentes do Ponto no protótipo vêm do bundle do DS e sobrescrevem
// aquele CSS (ex.: .pt-kpi diz padding 11/13, o render tem 14).
// Comportamento (validação, ledger append-only, paginação 50/pág) segue o charter e os casos.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { AlertTriangle, Check, ShieldCheck } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import { Grid, Inline, Stack } from '@/Components/layout';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import { cn, formatMinutes } from '@/Lib/utils';
import { fmtDataBr, fmtDataHoraBr } from '@/Lib/datetime-br';

interface Saldo {
  colaborador_id: number;
  matricula: string | null;
  nome: string;
  saldo_minutos: number;
  cargo: string | null;
  escala: string | null;
  atualizado_em: string | null;
}

interface Acordo {
  teto_horas: number;
  piso_horas: number;
  prazo_meses: number;
}

interface Movimento {
  id: number | string;
  minutos: number;
  tipo: string;
  data_referencia: string | null;
  observacao: string | null;
  created_at: string | null;
  created_at_human: string | null;
}

interface Paginated {
  data: Movimento[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  saldo: Saldo;
  acordo: Acordo;
  // movimentos vem via Inertia::defer — undefined no first render (saldo é eager)
  movimentos?: Paginated;
}

/**
 * Rótulo do link de página vindo do paginator do Laravel ("&laquo; Anterior",
 * "Próximo &raquo;", "2") renderizado como TEXTO — o React escapa, sem sink de XSS.
 * Só as entidades que o paginator emite são decodificadas.
 */
const rotuloPagina = (label: string) =>
  label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»').replace(/&amp;/g, '&');

/** Minutos com sinal, como o protótipo (`<Min v sinal />`): crédito ganha "+". */
const minutosComSinal = (m: number) => (m > 0 ? `+${formatMinutes(m)}` : formatMinutes(m));

/**
 * KPI do extrato — réplica LOCAL do KPI do protótipo (ADR 0388 §D-1), com as medidas do
 * render: padding 14, espaço 6, raio 12, rótulo 10,5px/600 maiúsculo (0,05em), valor
 * 22px/700 na cor de texto, linha 11,5px. O `KpiCard` compartilhado tem outras medidas por
 * dentro (rótulo 11px/0,1em, valor 20px/600) e ~40 consumidores — não se muda por uma tela.
 */
function KpiExtrato({ label, valor, linha, tom = 'default' }: {
  label: string;
  valor: ReactNode;
  linha?: string;
  tom?: 'default' | 'success' | 'danger';
}) {
  return (
    <Stack
      gap={1}
      className={cn(
        'gap-1.5 rounded-lg border p-3.5',
        tom === 'success' && 'border-success/20 bg-success/5',
        tom === 'danger' && 'border-destructive/20 bg-destructive/5',
        tom === 'default' && 'border-border bg-card',
      )}
    >
      <span className="text-[10.5px] font-semibold uppercase tracking-[0.05em] text-muted-foreground">{label}</span>
      <span className="text-[22px] font-bold leading-[1.1] tabular-nums text-foreground">{valor}</span>
      {linha && <span className="text-[11.5px] text-muted-foreground">{linha}</span>}
    </Stack>
  );
}

// Card do protótipo não tem o py-6/gap-6 do Card shadcn: cabeçalho 12/14, corpo colado.
const cardSemRespiro = 'gap-0 py-0';
// gap-0: o CardHeader é grid de 2 linhas com gap-1.5, e sem descrição a 2ª linha vazia ainda
// somava o gap. Linha de 21px = a do título do protótipo (cabeçalho medido: 46px).
const cabecalhoCard = 'gap-0 px-3.5 py-3';
const tituloCard = 'text-[13.5px] leading-[21px] font-semibold tracking-[-0.008em]';
const th = 'px-2.5 py-2 text-left text-[11px] font-semibold uppercase tracking-[0.07em] text-muted-foreground bg-card';
const td = 'px-2.5 py-[7px]';
// Campos em variante `shadcn` (utilitários) e não `cowork`: o `.cw-input`/`.cw-label` é CSS SEM
// @layer, que vence qualquer utilitário no Tailwind v4 — as medidas do protótipo (34px, 13px,
// raio 8, rótulo 10,5px/600 em --text-mute) seriam ignoradas.
const rotuloCampo = 'text-[10.5px] leading-[1.5] font-semibold uppercase tracking-[0.04em] text-[var(--text-mute)]';
const campo = 'mt-1 rounded-[8px] border-border bg-card px-2.5 py-[7px] text-[13px] shadow-none md:text-[13px] dark:bg-card';

export default function BancoHorasShow({ saldo, acordo, movimentos }: Props) {
  // Guarda defensiva (defesa dupla com o <Deferred>): movimentos é undefined no
  // first render.
  const rows = movimentos?.data ?? [];
  // TODO inertia-v3: revisar timing reset (agora so no onFinish)
  const form = useForm({
    minutos: 0,
    observacao: '',
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    if (form.data.minutos === 0) {
      toast.error('Informe minutos diferente de zero.');
      return;
    }
    if (form.data.observacao.trim().length < 5) {
      toast.error('Observação precisa ter pelo menos 5 caracteres.');
      return;
    }
    form.post(`/ponto/banco-horas/${saldo.colaborador_id}/ajuste`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Ajuste registrado no ledger.'),
      onError: () => toast.error('Falha ao ajustar.'),
      onFinish: () => form.reset(),
    });
  };

  // "0014 · Acabamento · escala Produção 5x2" — cada trecho só entra se houver dado.
  const subtitulo = [
    saldo.matricula,
    saldo.cargo,
    saldo.escala ? `escala ${saldo.escala}` : null,
  ].filter(Boolean).join(' · ');

  const tomSaldo = saldo.saldo_minutos > 0 ? 'success' : saldo.saldo_minutos < 0 ? 'danger' : 'default';

  return (
    <>
      <Head title={`BH · ${saldo.nome}`} />
      {/* Largura cheia, como o protótipo (.pt-body 18/24/32) — sem o max-w-7xl centrado. */}
      <Stack gap={4} className="px-6 pt-[18px] pb-8">
        {/* ADR 0182 PageHeader canon — shell do módulo, igual às outras telas do Ponto.
            O cabeçalho de MÓDULO do protótipo (faixa, "Ponto" 22px, abas) é das 22 telas e
            vai em PR próprio — não se conserta tela a tela. */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>Banco de horas</h1>
            <p>Extrato do colaborador — ledger append-only.</p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="banco-horas" hidePrimary />
          </div>
        </header>

        {/* Faixa do colaborador (protótipo `.pt-sub`, ponto-telas.jsx:368-371) */}
        <Inline gap={2} wrap data-contract="bancohoras-colaborador">
          {/* Só texto, sem ícone — como o protótipo (medido: 116px, 0 filhos). */}
          <Button
            variant="outline"
            size="sm"
            className="h-[26px] rounded-[8px] bg-card px-2.5 text-xs text-muted-foreground dark:bg-card"
            onClick={() => router.visit('/ponto/banco-horas')}
          >
            Voltar aos saldos
          </Button>
          <div>
            <h2 className="text-sm leading-[1.2] font-semibold text-foreground">{saldo.nome}</h2>
            {subtitulo && <span className="text-[11.5px] text-muted-foreground">{subtitulo}</span>}
          </div>
        </Inline>

        {/* 2fr 1fr, colapsa abaixo de 1101px (protótipo `.pt-cols-2`). Grid SEM `cols`:
            variante arbitrária só vence quando não há colsMap concorrente (§5 2026-09-21). */}
        <Grid gap={4} className="grid-cols-1 items-start min-[1101px]:grid-cols-[2fr_1fr]">
          <Stack gap={4}>
            <Grid gap={2} className="gap-2.5 grid-cols-[repeat(auto-fit,minmax(158px,1fr))]" data-contract="bancohoras-kpis-do-extrato">
              <KpiExtrato
                label="Saldo atual"
                valor={formatMinutes(saldo.saldo_minutos)}
                tom={tomSaldo}
                linha={saldo.atualizado_em ? `atualizado ${fmtDataHoraBr(saldo.atualizado_em)}` : undefined}
              />
              <KpiExtrato label="Lançamentos" valor={movimentos ? movimentos.total : '—'} linha="append-only" />
              <KpiExtrato label="Teto do acordo" valor={`${acordo.teto_horas}h`} linha={`piso ${acordo.piso_horas}h`} />
              <KpiExtrato label="Prazo de compensação" valor={`${acordo.prazo_meses} meses`} linha="acordo individual" />
            </Grid>

            <Card className={cardSemRespiro} data-contract="bancohoras-historico-de-movimentos">
              <CardHeader className={cabecalhoCard}>
                <CardTitle className={tituloCard}>
                  Histórico de movimentos{' '}
                  {movimentos && (
                    <span className="font-mono text-[11.5px] font-medium text-muted-foreground">
                      ({movimentos.total} lançamentos)
                    </span>
                  )}
                </CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <Deferred data="movimentos" fallback={<div className="p-4"><Skeleton className="h-64 w-full" /></div>}>
                  {rows.length === 0 ? (
                    <div className="p-8 text-center text-sm text-muted-foreground">Nenhuma movimentação registrada.</div>
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-[12.5px]">
                        <thead>
                          <tr>
                            <th className={th}>Data</th>
                            <th className={th}>Referência</th>
                            <th className={th}>Origem</th>
                            <th className={cn(th, 'text-right')}>Minutos</th>
                            <th className={th}>Observação</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                          {rows.map((m) => (
                            <tr key={m.id} className="hover:bg-accent/30">
                              {/* Absoluta, não "há X": o extrato é prova (quando foi lançado), e a
                                  relativa muda a cada leitura. A relativa fica no hover. */}
                              <td className={cn(td, 'font-mono text-[11.5px] tabular-nums')} title={m.created_at_human ?? ''}>
                                {fmtDataHoraBr(m.created_at)}
                              </td>
                              <td className={cn(td, 'font-mono text-[11.5px] tabular-nums')}>{fmtDataBr(m.data_referencia)}</td>
                              <td className={td}>
                                {/* Neutro para todo tipo, como o protótipo (ponto-telas.jsx:387): pílula
                                    PREENCHIDA sem borda, 11,5px/500 (medido no render). */}
                                <Badge variant="secondary" className="rounded-full border-transparent px-2.5 py-0.5 text-[11.5px] font-medium">
                                  {m.tipo}
                                </Badge>
                              </td>
                              {/* Cor de texto normal, como o render do protótipo: o sinal (+/−) já diz. */}
                              <td className={cn(td, 'text-right font-mono tabular-nums')}>
                                {minutosComSinal(m.minutos)}
                              </td>
                              {/* Protótipo: <small> 10,5px esmaecido, que QUEBRA linha (sem truncar). */}
                              <td className={cn(td, 'text-[10.5px] text-muted-foreground')}>
                                {m.observacao ?? '—'}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                  {/* Charter §Goals: "Histórico paginado (50/pág)". Sem este controle o
                      servidor paginava e a tela mostrava só a 1ª página — movimentos 51+
                      ficavam inalcançáveis. Mesmo idioma do Index.tsx (saldos). */}
                  {(movimentos?.last_page ?? 1) > 1 && (
                    <Inline justify="between" className="border-t border-border p-3 text-xs">
                      <span className="text-muted-foreground">
                        Página {movimentos?.current_page ?? 1} de {movimentos?.last_page ?? 1} · {movimentos?.total ?? 0} movimento(s)
                      </span>
                      <Inline gap={1} wrap>
                        {(movimentos?.links ?? []).map((link, i) => (
                          <Button
                            key={i}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            className="h-7 min-w-8 px-2 text-xs"
                            disabled={!link.url}
                            // Partial reload: só re-busca `movimentos`; o saldo do cabeçalho
                            // não viaja de novo (charter Non-Goal: a tela não recalcula saldo).
                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, only: ['movimentos'] })}
                          >
                            {rotuloPagina(link.label)}
                          </Button>
                        ))}
                      </Inline>
                    </Inline>
                  )}
                </Deferred>
              </CardContent>
            </Card>
          </Stack>

          {/* Ajuste manual — card lateral (protótipo ponto-telas.jsx:395-402) */}
          <Card className={cardSemRespiro} data-contract="bancohoras-ajuste-manual">
            <CardHeader className={cabecalhoCard}>
              <CardTitle className={tituloCard}>
                Ajuste manual{' '}
                <span className="font-mono text-[11.5px] font-medium text-muted-foreground">
                  — registra lançamento no ledger (imutável)
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent className="px-3.5 pt-3.5 pb-3.5">
              <form onSubmit={submit}>
                <Stack gap={3}>
                  <div>
                    <Label variant="shadcn" htmlFor="minutos" className={rotuloCampo}>
                      Minutos <span className="text-destructive">*</span>
                    </Label>
                    <Input
                      variant="shadcn"
                      id="minutos"
                      type="number"
                      value={form.data.minutos || ''}
                      onChange={(e) => form.setData('minutos', parseInt(e.target.value || '0', 10))}
                      placeholder="Use negativo para débito"
                      aria-describedby="minutos-ajuda"
                      className={cn(campo, 'h-[34px] font-mono')}
                    />
                    <p id="minutos-ajuda" className="mt-2 text-[11.5px] text-[var(--text-mute)]">
                      Ex.: 60 (crédito 1h), −30 (débito 30 min).
                    </p>
                    {form.errors.minutos && <p className="text-xs text-destructive mt-1">{form.errors.minutos}</p>}
                  </div>
                  <div>
                    <Label variant="shadcn" htmlFor="obs" className={rotuloCampo}>
                      Observação <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                      variant="shadcn"
                      id="obs"
                      maxLength={500}
                      value={form.data.observacao}
                      onChange={(e) => form.setData('observacao', e.target.value)}
                      placeholder="Motivo do ajuste (obrigatório)…"
                      className={cn(campo, 'field-sizing-fixed h-[75px] min-h-0')}
                    />
                    {form.errors.observacao && <p className="text-xs text-destructive mt-1">{form.errors.observacao}</p>}
                  </div>
                  <Button type="submit" disabled={form.processing} className="h-[30px] w-full gap-1.5 rounded-[8px] text-[12.5px] font-semibold">
                    <Check size={14} aria-hidden="true" />
                    {form.processing ? 'Salvando…' : 'Registrar ajuste'}
                  </Button>
                  <Inline gap={2} align="start" className="gap-[11px] rounded-[8px] border border-warning/[0.22] bg-warning/[0.06] px-3.5 py-3 text-[13px] text-foreground">
                    <AlertTriangle size={15} className="mt-px shrink-0 text-warning-fg" aria-hidden="true" />
                    <span>
                      O ajuste não apaga nem edita movimento anterior: entra como lançamento novo com o seu nome.
                      É assim que a auditoria reconstrói o saldo.
                    </span>
                  </Inline>
                </Stack>
              </form>
            </CardContent>
          </Card>
        </Grid>

        {/* Rodapé legal: centralizado, 11px, 6px de respiro (protótipo .pt-legal). */}
        <Inline gap={2} justify="center" className="gap-[7px] py-1.5 text-[11px] text-muted-foreground" data-contract="bancohoras-legal">
          <ShieldCheck size={13} aria-hidden="true" />
          <span>Movimentos de banco de horas são append-only e imutáveis (Portaria MTP 671/2021).</span>
        </Inline>
      </Stack>
    </>
  );
}

BancoHorasShow.layout = (page: ReactNode) => (
  <AppShellV2 breadcrumbItems={[
    { label: 'Ponto WR2' },
    { label: 'Banco de Horas', href: '/ponto/banco-horas' },
  ]}>
    {page}
  </AppShellV2>
);
