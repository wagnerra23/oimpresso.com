// @memcofre
//   tela: /modules/kitchen
//   module: Restaurante
//   stories: thread restaurante/playbook/04 (Cozinha)
//   permissao: sell.view
//
// Fila da cozinha. Atrás da flag `useV2RestauranteCozinha` (KitchenController::FLAG_V2); desligada, a rota segue na
// Blade `restaurant.kitchen.index`. "Marcar como preparado" chama o MESMO endpoint da Blade
// (GET /modules/kitchen/mark-as-cooked/{id}) — a tela não muda o que ele grava.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Restaurante/RUNBOOK-cozinha.md
// Âncora de design: governance/design/targets/restaurante--cozinha--index.alvo.json (A1) ← restaurante-operacao.jsx
//   RestauranteOperacaoPage view="cfg-cozinha". Contrato: governance/design/contracts/restaurante-cozinha.contract.json.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, usePage } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Check, Info, RefreshCw } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
  AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface Item { id: number; nome: string | null; quantidade: number; situacao: string }
export interface Pedido {
  id: number;
  nota: string;
  feito_em: string;
  cliente: string | null;
  mesa: string | null;
  local: string | null;
  situacao: 'received' | 'partial_cooked' | 'cooked' | 'partial_served' | 'served';
  itens: Item[];
}
interface Props { pedidos?: Pedido[] }
interface ItemDeMenu { label: string; href?: string; children?: ItemDeMenu[] }

const AQUI = '/modules/kitchen';
const ATRASO_MIN = 15;
/** As telas do Restaurante, na ordem do protótipo. Atendentes fica de fora: o índice não lhe deu dono. */
const TELAS = ['/modules/tables', '/bookings', '/modules/kitchen', '/modules/orders'];
const caminho = (href: string) => new URL(href, window.location.origin).pathname;
const achatar = (itens?: ItemDeMenu[]): ItemDeMenu[] => (itens ?? []).flatMap((i) => [i, ...achatar(i.children)]);

/** Abas derivadas do menu (cada item já sob a permissão dele): sem segundo dono da lista. */
function RestauranteAbas() {
  const { shell } = usePage<{ shell?: { menu?: ItemDeMenu[] } }>().props;
  const porRota = new Map(achatar(shell?.menu).filter((i) => i.href && i.href !== '#').map((i) => [caminho(i.href!), i.label]));
  const ghosts: PageHeaderGhost[] = TELAS.filter((r) => porRota.has(r)).map((r) => ({ key: r, label: porRota.get(r)!, href: r }));
  if (ghosts.length < 2) return null;

  return (
    <div data-contract="abas">
      <PageHeaderTabs ghosts={ghosts} activeGhostKey={AQUI} group="sistema" maxVisible={5}
        onGhostChange={(key) => { if (key !== AQUI) window.location.assign(key); }} />
    </div>
  );
}

const SITUACAO: Record<Pedido['situacao'], { rotulo: string; tom: 'info' | 'warning' | 'success' }> = {
  received: { rotulo: 'recebido', tom: 'info' },
  partial_cooked: { rotulo: 'preparado em parte', tom: 'warning' },
  cooked: { rotulo: 'preparado', tom: 'warning' },
  partial_served: { rotulo: 'servido em parte', tom: 'info' },
  served: { rotulo: 'servido', tom: 'success' },
};
const porPreparar = (p: Pedido) => p.itens.filter((i) => i.situacao === 'received');
const data = (iso: string) => new Date(iso.replace(' ', 'T'));
const espera = (p: Pedido) => Math.max(0, Math.round((Date.now() - data(p.feito_em).getTime()) / 60_000));
const feitoAs = (iso: string) => data(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).replace(',', '');

function CozinhaIndex({ pedidos }: Props) {
  // Mais antigo primeiro (protótipo). A Blade mostra o mais recente primeiro; a fila é a mesma (RUNBOOK §10).
  const fila = useMemo(() => [...(pedidos ?? [])].sort((a, b) => espera(b) - espera(a)), [pedidos]);
  const [confirmar, setConfirmar] = useState<Pedido | null>(null);
  const [marcando, setMarcando] = useState<number | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  const itensPorPreparar = fila.reduce((soma, p) => soma + porPreparar(p).reduce((s, i) => s + i.quantidade, 0), 0);
  const atrasados = fila.filter((p) => espera(p) >= ATRASO_MIN).length;
  const atualizar = () => router.reload({ only: ['pedidos'] });

  async function marcarComoPreparado(p: Pedido) {
    setConfirmar(null);
    setMarcando(p.id);
    setErro(null);
    try {
      const r = await fetch(`/modules/kitchen/mark-as-cooked/${p.id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
      const corpo = await r.json().catch(() => null);
      if (!r.ok || !corpo?.success) setErro(corpo?.msg ?? 'Não foi possível marcar o pedido como preparado.');
      atualizar();
    } finally {
      setMarcando(null);
    }
  }

  return (
    <div className="pb-8">
      <div data-contract="cabecalho">
        <PageHeader title="Cozinha"
          subtitle={pedidos ? <><strong>{fila.length}</strong> {fila.length === 1 ? 'pedido na fila' : 'pedidos na fila'}</> : 'fila da cozinha'}
          subnav={<RestauranteAbas />}
          actions={<Button variant="outline" onClick={atualizar}><RefreshCw className="size-4" /> Atualizar</Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="pedidos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando a fila…</p>}>
          <Grid data-contract="kpis" min="sm" gap={4}>
            <KpiCard label="Na fila" value={fila.length} description="pedidos com item por preparar" tone="info" />
            <KpiCard label="Itens por preparar" value={itensPorPreparar} description="somando as quantidades" />
            <KpiCard label={`Esperando há ${ATRASO_MIN} min ou mais`} value={atrasados} description="o mais antigo fica primeiro"
              tone={atrasados ? 'warning' : 'success'} />
          </Grid>

          <div data-contract="barra">
            <Alert>
              <Info className="size-4" />
              <AlertTitle>A fila da cozinha é o que falta preparar</AlertTitle>
              <AlertDescription>
                O pedido mais antigo vem primeiro. Ele sai daqui quando todos os itens estão preparados — o atendente passa
                a ver em Pedidos.
              </AlertDescription>
            </Alert>
          </div>

          {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}

          <Stack gap={2} data-contract="pedidos">
            <Inline gap={2}>
              <span className="font-medium">Pedidos na cozinha</span>
              <span className="text-sm text-muted-foreground">{fila.length} pedido(s)</span>
            </Inline>
            {fila.length > 0 ? (
              <Grid min="sm" gap={3}>
                {fila.map((p) => {
                  const falta = porPreparar(p);
                  const prontos = p.itens.length - falta.length;
                  const s = SITUACAO[p.situacao];
                  return (
                    <article key={p.id} className="rounded-lg border bg-card p-4 shadow-sm">
                      <Inline gap={2} wrap>
                        <span className="font-mono font-semibold"># {p.nota}</span>
                        <span className={espera(p) >= ATRASO_MIN ? 'text-sm text-warning-fg' : 'text-sm text-muted-foreground'}>há {espera(p)} min</span>
                        <Badge variant={s?.tom ?? 'info'} className="ml-auto">{s?.rotulo ?? p.situacao}</Badge>
                      </Inline>
                      <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                        <dt className="text-muted-foreground">Feito às</dt><dd>{feitoAs(p.feito_em)}</dd>
                        <dt className="text-muted-foreground">Cliente</dt><dd>{p.cliente ?? '—'}</dd>
                        <dt className="text-muted-foreground">Mesa</dt><dd>{p.mesa ?? '—'}</dd>
                        <dt className="text-muted-foreground">Local</dt><dd>{p.local ?? '—'}</dd>
                      </dl>
                      <ul className="mt-2 space-y-0.5 text-sm">
                        {falta.map((i) => <li key={i.id}><span className="font-mono">{i.quantidade} ×</span> {i.nome ?? '—'}</li>)}
                        {prontos > 0 && <li className="text-muted-foreground">+ {prontos} já preparado(s)</li>}
                      </ul>
                      <Button className="mt-3 w-full" size="sm" disabled={marcando === p.id} onClick={() => setConfirmar(p)}>
                        <Check className="size-4" /> Marcar como preparado
                      </Button>
                    </article>
                  );
                })}
              </Grid>
            ) : (
              <EmptyState title="Nada na fila" description="Quando um pedido com item por preparar chegar, ele aparece aqui." />
            )}
          </Stack>
        </Deferred>
      </Stack>

      <AlertDialog open={confirmar !== null} onOpenChange={(aberto) => { if (!aberto) setConfirmar(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Marcar o pedido # {confirmar?.nota} como preparado?</AlertDialogTitle>
            <AlertDialogDescription>Todos os itens ainda por preparar passam a preparado e o pedido sai da fila.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Voltar</AlertDialogCancel>
            <AlertDialogAction onClick={() => confirmar && marcarComoPreparado(confirmar)}>Marcar como preparado</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

CozinhaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CozinhaIndex;
