// @docvault
//   tela: /ponto/banco-horas/show
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-003
//   rules: R-PONT-001
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/BancoHorasShowTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { ArrowLeft, Info, PiggyBank, Save } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import { Inline } from '@/Components/layout';
import { cn, formatMinutes } from '@/Lib/utils';
import { fmtDataHoraBr } from '@/Lib/datetime-br';

interface Saldo {
  colaborador_id: number;
  matricula: string | null;
  nome: string;
  saldo_minutos: number;
}

interface Movimento {
  id: number;
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

export default function BancoHorasShow({ saldo, movimentos }: Props) {
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

  return (
    <>
      <Head title={`BH · ${saldo.nome}`} />
      <div className="mx-auto max-w-5xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <PontoAreaHeader active="banco-horas" />
        <Inline justify="between" align="center" gap={3}>
          <div>
            <h2 className="text-lg font-semibold">Banco de Horas <span className="text-stone-400 font-normal">· {saldo.nome}</span></h2>
            <p className="text-sm text-muted-foreground">
              {saldo.matricula && `Matrícula ${saldo.matricula} · `}
              Ledger append-only — cada ajuste é um novo movimento.
            </p>
          </div>
          <Inline gap={2} align="center">
            <Button variant="outline" size="sm" onClick={() => router.visit('/ponto/banco-horas')}>
              <ArrowLeft size={14} className="mr-1.5" /> Voltar
            </Button>
          </Inline>
        </Inline>

        <Card>
          <CardContent className="pt-6 pb-6 text-center">
            <p className="text-[10px] uppercase tracking-wide text-muted-foreground">Saldo atual</p>
            <p className={cn(
              'text-4xl font-bold font-mono mt-1',
              saldo.saldo_minutos > 0 && 'text-success-fg',
              saldo.saldo_minutos < 0 && 'text-destructive-fg',
            )}>
              {formatMinutes(saldo.saldo_minutos)}
            </p>
          </CardContent>
        </Card>

        {/* Ajuste manual */}
        <Card>
          <CardHeader>
            <CardTitle className="text-base">Ajuste manual</CardTitle>
            <CardDescription className="text-xs">
              Positivo credita, negativo debita. Observação obrigatória — é registrada no ledger e auditada.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={submit} className="space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <Label htmlFor="minutos">Minutos (±)</Label>
                  <Input
                    id="minutos"
                    type="number"
                    value={form.data.minutos}
                    onChange={(e) => form.setData('minutos', parseInt(e.target.value || '0', 10))}
                    placeholder="ex: 30 ou -60"
                    className="font-mono"
                  />
                  {form.errors.minutos && <p className="text-xs text-destructive mt-1">{form.errors.minutos}</p>}
                </div>
                <div className="md:col-span-2">
                  <Label htmlFor="obs">Observação</Label>
                  <Input
                    id="obs"
                    value={form.data.observacao}
                    onChange={(e) => form.setData('observacao', e.target.value)}
                    placeholder="Motivo do ajuste (mín 5 caracteres)"
                  />
                  {form.errors.observacao && <p className="text-xs text-destructive mt-1">{form.errors.observacao}</p>}
                </div>
              </div>
              <div className="flex justify-end">
                <Button type="submit" disabled={form.processing} className="gap-1.5">
                  <Save size={14} />
                  {form.processing ? 'Salvando…' : 'Registrar ajuste'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Alert>
          <Info size={14} />
          <AlertTitle>Append-only</AlertTitle>
          <AlertDescription className="text-xs">
            Este ledger nunca atualiza/remove movimentos anteriores. O "saldo" é calculado
            pela soma de todos os movimentos. Qualquer correção vira um novo movimento reverso.
          </AlertDescription>
        </Alert>

        {/* Histórico */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Histórico de movimentos</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <Deferred data="movimentos" fallback={<div className="p-4"><Skeleton className="h-64 w-full" /></div>}>
            {rows.length === 0 ? (
              <div className="p-8 text-center text-sm text-muted-foreground">Sem movimentos.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                    <tr>
                      <th className="text-left p-2 font-medium">Data ref.</th>
                      <th className="text-left p-2 font-medium">Tipo</th>
                      <th className="text-right p-2 font-medium">Minutos</th>
                      <th className="text-left p-2 font-medium">Observação</th>
                      <th className="text-left p-2 font-medium">Registrado</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {rows.map((m) => (
                      <tr key={m.id} className="hover:bg-accent/30">
                        <td className="p-2">{m.data_referencia ?? '—'}</td>
                        <td className="p-2">
                          {/* Neutro para todo tipo, como o protótipo (ponto-telas.jsx:387, Pill tom="neutral" mono):
                              o sinal crédito/débito é a cor dos MINUTOS, ao lado. Havia aqui um
                              mapa de cor por tipo com chaves (CREDITO_HE, DEBITO_FOLGA…) que o
                              enum nunca grava (CREDITO, DEBITO, AJUSTE…) — nunca casou. */}
                          <Badge variant="outline" className="font-mono text-[10px]">
                            {m.tipo}
                          </Badge>
                        </td>
                        <td className={cn(
                          'p-2 text-right font-mono font-semibold',
                          m.minutos > 0 && 'text-success-fg',
                          m.minutos < 0 && 'text-destructive-fg',
                        )}>
                          {formatMinutes(m.minutos)}
                        </td>
                        <td className="p-2 text-muted-foreground max-w-xs truncate" title={m.observacao ?? ''}>
                          {m.observacao ?? '—'}
                        </td>
                        {/* Absoluta, não "há X": o extrato é prova (quando foi lançado), e a
                            relativa muda a cada leitura. A relativa fica no hover. */}
                        <td className="p-2 font-mono tabular-nums text-muted-foreground" title={m.created_at_human ?? ''}>
                          {fmtDataHoraBr(m.created_at)}
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
      </div>
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
