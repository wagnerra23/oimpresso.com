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
// Comportamento (validação, ledger append-only, paginação 50/pág) segue o charter e os casos.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { AlertTriangle, ArrowLeft, Check, ShieldCheck } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import { Grid, Inline, Stack } from '@/Components/layout';
import KpiCard from '@/Components/shared/KpiCard';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
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
      <Stack gap={4} className="mx-auto max-w-7xl p-6">
        {/* W9 (ADR 0418): header de módulo + abas do protótipo, igual às outras telas do Ponto */}
        <PontoAreaHeader active="banco-horas" />

        {/* Faixa do colaborador (protótipo `.pt-sub`, ponto-telas.jsx:368-371) */}
        <Inline gap={2} wrap data-contract="bancohoras-colaborador">
          <Button variant="outline" size="sm" onClick={() => router.visit('/ponto/banco-horas')}>
            <ArrowLeft size={14} className="mr-1.5" /> Voltar aos saldos
          </Button>
          <div>
            <h2 className="text-sm font-semibold text-foreground">{saldo.nome}</h2>
            {subtitulo && <span className="text-xs text-muted-foreground">{subtitulo}</span>}
          </div>
        </Inline>

        {/* 2fr 1fr, colapsa abaixo de 1101px (protótipo `.pt-cols-2`). Grid SEM `cols`:
            variante arbitrária só vence quando não há colsMap concorrente (§5 2026-09-21). */}
        <Grid gap={4} className="grid-cols-1 items-start min-[1101px]:grid-cols-[2fr_1fr]">
          <Stack gap={4}>
            <Grid gap={3} className="grid-cols-[repeat(auto-fit,minmax(158px,1fr))]" data-contract="bancohoras-kpis-do-extrato">
              <KpiCard
                label="Saldo atual"
                value={formatMinutes(saldo.saldo_minutos)}
                tone={tomSaldo}
                size="compact"
                description={saldo.atualizado_em ? `atualizado ${fmtDataHoraBr(saldo.atualizado_em)}` : undefined}
              />
              <KpiCard
                label="Lançamentos"
                value={movimentos ? movimentos.total : '—'}
                size="compact"
                description="append-only"
              />
              <KpiCard
                label="Teto do acordo"
                value={`${acordo.teto_horas}h`}
                size="compact"
                description={`piso ${acordo.piso_horas}h`}
              />
              <KpiCard
                label="Prazo de compensação"
                value={`${acordo.prazo_meses} meses`}
                size="compact"
                description="acordo individual"
              />
            </Grid>

            <Card data-contract="bancohoras-historico-de-movimentos">
              <CardHeader className="pb-3">
                <CardTitle className="text-base">
                  Histórico de movimentos{' '}
                  {movimentos && (
                    <span className="font-mono text-xs font-normal text-muted-foreground">
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
                      <table className="w-full text-xs">
                        <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                          <tr>
                            <th className="text-left p-2 font-medium">Data</th>
                            <th className="text-left p-2 font-medium">Referência</th>
                            <th className="text-left p-2 font-medium">Origem</th>
                            <th className="text-right p-2 font-medium">Minutos</th>
                            <th className="text-left p-2 font-medium">Observação</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                          {rows.map((m) => (
                            <tr key={m.id} className="hover:bg-accent/30">
                              {/* Absoluta, não "há X": o extrato é prova (quando foi lançado), e a
                                  relativa muda a cada leitura. A relativa fica no hover. */}
                              <td className="p-2 font-mono tabular-nums" title={m.created_at_human ?? ''}>
                                {fmtDataHoraBr(m.created_at)}
                              </td>
                              <td className="p-2 font-mono tabular-nums">{fmtDataBr(m.data_referencia)}</td>
                              <td className="p-2">
                                {/* Neutro para todo tipo, como o protótipo (ponto-telas.jsx:387, Pill tom="neutral" mono):
                                    o sinal crédito/débito é a cor dos MINUTOS, ao lado. */}
                                <Badge variant="outline" className="font-mono text-[10px]">
                                  {m.tipo}
                                </Badge>
                              </td>
                              <td className={cn(
                                'p-2 text-right font-mono font-semibold tabular-nums',
                                m.minutos > 0 && 'text-success-fg',
                                m.minutos < 0 && 'text-destructive-fg',
                              )}>
                                {minutosComSinal(m.minutos)}
                              </td>
                              <td className="p-2 text-muted-foreground max-w-xs truncate" title={m.observacao ?? ''}>
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
          <Card data-contract="bancohoras-ajuste-manual">
            <CardHeader className="pb-3">
              <CardTitle className="text-base">
                Ajuste manual{' '}
                <span className="font-mono text-xs font-normal text-muted-foreground">
                  — registra lançamento no ledger (imutável)
                </span>
              </CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit}>
                <Stack gap={3}>
                  <div>
                    <Label htmlFor="minutos">Minutos <span className="text-destructive">*</span></Label>
                    <Input
                      id="minutos"
                      type="number"
                      value={form.data.minutos || ''}
                      onChange={(e) => form.setData('minutos', parseInt(e.target.value || '0', 10))}
                      placeholder="Use negativo para débito"
                      aria-describedby="minutos-ajuda"
                      className="font-mono"
                    />
                    <p id="minutos-ajuda" className="text-xs text-muted-foreground mt-1">
                      Ex.: 60 (crédito 1h), −30 (débito 30 min).
                    </p>
                    {form.errors.minutos && <p className="text-xs text-destructive mt-1">{form.errors.minutos}</p>}
                  </div>
                  <div>
                    <Label htmlFor="obs">Observação <span className="text-destructive">*</span></Label>
                    <Textarea
                      id="obs"
                      maxLength={500}
                      value={form.data.observacao}
                      onChange={(e) => form.setData('observacao', e.target.value)}
                      placeholder="Motivo do ajuste (obrigatório)…"
                    />
                    {form.errors.observacao && <p className="text-xs text-destructive mt-1">{form.errors.observacao}</p>}
                  </div>
                  <Button type="submit" disabled={form.processing} className="w-full gap-1.5">
                    <Check size={14} />
                    {form.processing ? 'Salvando…' : 'Registrar ajuste'}
                  </Button>
                  <Inline gap={2} align="start" className="rounded-md border border-warning/30 bg-warning/10 p-3 text-xs text-foreground">
                    <AlertTriangle size={14} className="shrink-0 text-warning-fg" aria-hidden="true" />
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

        <Inline gap={2} justify="center" className="text-xs text-muted-foreground" data-contract="bancohoras-legal">
          <ShieldCheck size={14} aria-hidden="true" />
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
