// @docvault
//   tela: /ponto/escalas
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-005
//   rules: R-PONT-001, R-PONT-006
//   tests: Modules/PontoWr2/Tests/Feature/EscalasIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { useState, type MouseEvent, type ReactNode } from 'react';
import { CalendarDays, Info, Plus } from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatMinutes } from '@/Lib/utils';
import { Inline } from '@/Components/layout/inline';

import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import EmptyState from '@/Components/shared/EmptyState';
import Toolbar from '@/Components/shared/Toolbar';

/** Rótulos do enum `ponto_escalas.tipo` — os mesmos do protótipo (`TIPOS_ESCALA`) e do Form. */
// Pílulas da tabela na forma do protótipo (`ponto-page.jsx` → `Pill` → StatusBadge do DS),
// medida no DOM do espelho em 2026-09-29 (tema escuro, 1280): fundo SÓLIDO no tom, texto
// branco, sem dot, 11.5px/500, padding 2px 10px; "Não" em fundo neutro com texto de primeiro
// plano. Os fundos são tokens que já existem (`--color-info`, `--color-success`,
// `--color-secondary`), então light e dark seguem o tema sozinhos — nenhum token novo.
// Produção usava o tom `-soft` com dot, o padrão do Badge do DS; aqui o protótipo manda
// (ADR UI-0029, eixo forma). Réplica local de propósito (ADR 0388 §D-1): mudar o Badge
// compartilhado mexeria nas outras telas que o importam.
const pilulaBase = 'border-transparent px-2.5 text-[11.5px] font-medium';
const pilulaSolida = {
  info: `${pilulaBase} bg-info text-white`,
  success: `${pilulaBase} bg-success text-white`,
  neutra: `${pilulaBase} bg-secondary text-secondary-foreground`,
} as const;

const TIPOS_ESCALA: Record<string, string> = {
  FIXA: 'Fixa',
  FLEXIVEL: 'Flexível',
  ESCALA_12X36: '12x36',
  ESCALA_6X1: '6x1',
  ESCALA_5X2: '5x2',
};

interface Escala {
  id: number;
  nome: string;
  codigo: string | null;
  tipo: string;
  carga_diaria_minutos: number;
  carga_semanal_minutos: number;
  permite_banco_horas: boolean;
  turnos_count: number;
  /** Entrada–saída do turno de menor dia da semana ("08:00–18:00"); null = escala sem turno. */
  primeiro_turno: string | null;
  /** D-ESC-DESTROY: quantos colaboradores usam esta escala. 0 = remover liberado. */
  colaboradores_count: number;
}

interface Paginated {
  data: Escala[];
  total: number;
  from: number | null;
  to: number | null;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props { escalas: Paginated; }

export default function EscalasIndex({ escalas }: Props) {
  // Remover escala é irreversível, então pede confirmação — no diálogo do DS, não no
  // `window.confirm` nativo (D-ESC-DESTROY, [W] 2026-09-14: "window.confirm não era pergunta").
  // O guard de vínculo real vive no servidor — aqui só evito o clique acidental.
  const [remover, setRemover] = useState<Escala | null>(null);
  const [removendo, setRemovendo] = useState(false);

  const confirmarRemocao = (ev: MouseEvent) => {
    // O Action do Radix fecha o diálogo no clique; segurar aberto até a resposta é o que deixa o
    // botão visivelmente desabilitado ("Removendo…") e evita o segundo clique.
    ev.preventDefault();
    if (!remover) return;
    setRemovendo(true);
    router.delete(`/ponto/escalas/${remover.id}`, {
      preserveScroll: true,
      onSuccess: () => setRemover(null),
      onFinish: () => setRemovendo(false),
    });
  };

  return (
    <>
      {/* `ponto-root` e `pt-body` são ganchos de medição — os seletores do ALVO em
          governance/design/targets/ponto--escalas--index.secoes.json. Não têm CSS próprio. */}
      <div className="ponto-root mx-auto max-w-7xl p-6 space-y-4">
        {/* W9 (ADR 0418): header de módulo + abas do protótipo. "Nova escala" mora na barra. */}
        <PontoAreaHeader active="escalas" />

        <div className="pt-body space-y-4">
          {/* BARRA — `window.PtBarra` do protótipo: Toolbar do DS dentro da própria moldura. */}
          <div className="overflow-hidden rounded-lg border border-border bg-card">
            <Toolbar
              label="Ações das escalas"
              bordered={false}
              right={
                <Button asChild size="sm">
                  <Link href="/ponto/escalas/create">
                    <Plus size={14} className="mr-1.5" aria-hidden /> Nova escala
                  </Link>
                </Button>
              }
            >
              <span className="text-xs text-muted-foreground">
                Carga diária e semanal em minutos — 480 = 8h, 2.640 = 44h (CLT padrão).
              </span>
            </Toolbar>
          </div>

          {/* LISTA — Card "Escalas cadastradas" do protótipo, paginado no SERVIDOR (W11, ADR 0418). */}
          <div data-contract="escalas-escalas-cadastradas">
            <section aria-labelledby="escalas-cadastradas-titulo">
              <Card flush className="gap-3 py-4">
                <CardHeader>
                  <CardTitle as="h2" id="escalas-cadastradas-titulo" badge={`(${escalas.total} no business)`}>
                    <Inline gap={2} align="center" className="min-w-0">
                      <CalendarDays size={15} aria-hidden />
                      <span>Escalas cadastradas</span>
                    </Inline>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {escalas.data.length === 0 ? (
                    <EmptyState
                      icon="calendar-days"
                      title="Nenhuma escala cadastrada"
                      description="Crie a primeira escala — turnos por dia da semana, carga horária e regra de banco de horas."
                      action={
                        <Button asChild size="sm">
                          <Link href="/ponto/escalas/create">
                            <Plus size={14} className="mr-1.5" aria-hidden /> Criar escala
                          </Link>
                        </Button>
                      }
                    />
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="w-full text-[13px]">
                        <thead className="border-y border-border bg-muted/30 text-xs text-muted-foreground">
                          <tr>
                            <th scope="col" className="w-[110px] px-3 py-2 text-left font-medium">Código</th>
                            <th scope="col" className="px-3 py-2 text-left font-medium">Nome</th>
                            <th scope="col" className="px-3 py-2 text-left font-medium">Tipo</th>
                            <th scope="col" className="px-3 py-2 text-right font-medium">Carga diária</th>
                            <th scope="col" className="px-3 py-2 text-right font-medium">Carga semanal</th>
                            <th scope="col" className="px-3 py-2 text-right font-medium">Turnos</th>
                            <th scope="col" className="px-3 py-2 text-left font-medium">Banco de horas</th>
                            <th scope="col" className="w-[150px] px-3 py-2 text-right font-medium">Ação</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                          {escalas.data.map((e) => (
                            <tr key={e.id} className="hover:bg-accent/30">
                              <td className="px-3 py-2 font-mono text-[11.5px]">{e.codigo ?? '—'}</td>
                              <td className="px-3 py-2">
                                <span className="block font-semibold">{e.nome}</span>
                                <small className="block text-xs text-muted-foreground" data-testid={`escala-${e.id}-turno`}>
                                  {e.primeiro_turno ?? 'sem turno configurado'}
                                </small>
                              </td>
                              <td className="px-3 py-2">
                                <Badge variant="info" className={pilulaSolida.info}>{TIPOS_ESCALA[e.tipo] ?? e.tipo}</Badge>
                              </td>
                              <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMinutes(e.carga_diaria_minutos)}</td>
                              <td className="px-3 py-2 text-right font-mono tabular-nums">{formatMinutes(e.carga_semanal_minutos)}</td>
                              <td className="px-3 py-2 text-right font-mono tabular-nums">{e.turnos_count}</td>
                              <td className="px-3 py-2">
                                <Badge
                                  variant={e.permite_banco_horas ? 'success' : 'secondary'}
                                  className={e.permite_banco_horas ? pilulaSolida.success : pilulaSolida.neutra}
                                >
                                  {e.permite_banco_horas ? 'Permite' : 'Não'}
                                </Badge>
                              </td>
                              <td className="px-3 py-2 text-right font-mono">
                                <Inline gap={1} justify="end">
                                  <Button size="sm" variant="ghost" asChild>
                                    <Link href={`/ponto/escalas/${e.id}/edit`} className="text-[13px]">Editar</Link>
                                  </Button>
                                  {/* D-ESC-DESTROY ([W] 2026-09-14): entra na UI, mas INDISPONÍVEL com vínculo
                                      — "com o motivo escrito", porque botão desabilitado não recebe foco e o
                                      Tooltip do DS seria inalcançável por teclado. Por isso o motivo vira texto
                                      ao lado, não tooltip. A trava de verdade é no servidor
                                      (EscalaController@destroy): o botão é conveniência, a rota é pública. */}
                                  {e.colaboradores_count > 0 ? (
                                    <span className="px-2 text-muted-foreground" data-testid={`escala-${e.id}-remover-bloqueado`}>
                                      Em uso por {e.colaboradores_count}{' '}
                                      {e.colaboradores_count === 1 ? 'colaborador' : 'colaboradores'}
                                    </span>
                                  ) : (
                                    <Button
                                      size="sm"
                                      variant="ghost"
                                      className="text-[13px] text-destructive hover:text-destructive"
                                      onClick={() => setRemover(e)}
                                    >
                                      Remover
                                    </Button>
                                  )}
                                </Inline>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                  {/* Rodapé sempre visível, como o Pager do protótipo. Sem seletor de tamanho de
                      página: o servidor pagina em 20 (charter §Goals). */}
                  {escalas.total > 0 && (
                    <div className="flex items-center justify-between gap-2 border-t border-border px-3 pt-3 text-xs">
                      <span className="text-muted-foreground">
                        Mostrando <b>{escalas.from ?? 0}</b>–<b>{escalas.to ?? 0}</b> de <b>{escalas.total}</b> escalas
                      </span>
                      {escalas.last_page > 1 && (
                        <div className="flex gap-1">
                          {escalas.links.map((link, i) => (
                            <Button
                              key={i}
                              variant={link.active ? 'default' : 'outline'}
                              size="sm"
                              className="h-7 min-w-8 px-2 text-xs"
                              disabled={!link.url}
                              // D-14: partial reload — paginação só re-busca a página da lista
                              onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, only: ['escalas'] })}
                            >
                              <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            </section>
          </div>

          {/* NOTA — `Nota tom="info"` do protótipo. É aviso estático, não alerta: role="note". */}
          <div>
            <Alert role="note" className="border-info/25 bg-info/5">
              <Info aria-hidden />
              <AlertDescription>
                A gestão detalhada de turnos por dia da semana (entrada, saída para almoço, retorno, saída) é
                leitura aqui e edição em fase posterior — igual ao Blade de origem.
              </AlertDescription>
            </Alert>
          </div>
        </div>
      </div>

      {/* D-ESC-DESTROY — forma do protótipo (ponto-telas.jsx): "Remover <nome>?", Cancelar + Remover escala. */}
      <AlertDialog open={remover !== null} onOpenChange={(o) => !o && !removendo && setRemover(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Remover {remover?.nome}?</AlertDialogTitle>
            <AlertDialogDescription>
              A escala sai da lista e o histórico de jornada dos meses fechados deixa de ter referência
              de padrão. Não há colaborador vinculado a ela agora. Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel disabled={removendo}>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={confirmarRemocao} disabled={removendo}>
              {removendo ? 'Removendo…' : 'Remover escala'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

EscalasIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Escalas" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Escalas' }]}>
    {page}
  </AppShellV2>
);
