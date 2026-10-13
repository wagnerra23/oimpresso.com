// @memcofre
//   tela: /modules/orders
//   module: Restaurante
//   stories: thread restaurante/playbook/05 (Pedidos)
//   permissao: nenhuma no controller (gate comentado desde o legado — RUNBOOK-pedidos §2)
//
// Pedidos do atendente. Atrás da flag `useV2RestaurantePedidos` (OrderController::FLAG_V2); desligada, a rota segue na
// Blade `restaurant.orders.index`. Os dois "Marcar como servido" chamam os MESMOS endpoints da Blade
// (mark-as-served/{pedido} e mark-line-order-as-served/{item}) — a tela não muda o que eles gravam.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Restaurante/RUNBOOK-pedidos.md
// Âncora de design: governance/design/targets/restaurante--pedidos--index.alvo.json (A1) ← restaurante-operacao.jsx
//   RestauranteOperacaoPage view="cfg-pedidos-rest". Contrato: governance/design/contracts/restaurante-pedidos.contract.json.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Check, RefreshCw } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter,
  AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';
import EmptyState from '@/Components/shared/EmptyState';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface ItemPorServir {
  id: number; pedido_id: number; nota: string; feito_em: string; cliente: string | null; mesa: string | null;
  local: string | null; produto: string | null; quantidade: number; observacao: string | null; situacao: string;
}
export interface Pedido {
  id: number; nota: string; feito_em: string; cliente: string | null; mesa: string | null; local: string | null;
  situacao: 'received' | 'partial_cooked' | 'cooked' | 'partial_served' | 'served';
  itens: { id: number; nome: string | null; quantidade: number; situacao: string }[];
}
interface Props {
  e_atendente: boolean;
  atendente_escolhido: number | null;
  atendentes: { id: number; nome: string }[];
  itens?: ItemPorServir[];
  pedidos?: Pedido[];
}
interface ItemDeMenu { label: string; href?: string; children?: ItemDeMenu[] }
type Acao = { tipo: 'pedido'; id: number; nota: string } | { tipo: 'item'; id: number; nota: string };

const AQUI = '/modules/orders';
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

const SITUACAO: Record<string, { rotulo: string; tom: 'info' | 'warning' | 'success' }> = {
  received: { rotulo: 'recebido', tom: 'info' },
  partial_cooked: { rotulo: 'preparado em parte', tom: 'warning' },
  cooked: { rotulo: 'preparado', tom: 'warning' },
  partial_served: { rotulo: 'servido em parte', tom: 'info' },
  served: { rotulo: 'servido', tom: 'success' },
};
const selo = (s: string) => <Badge variant={SITUACAO[s]?.tom ?? 'info'}>{SITUACAO[s]?.rotulo ?? s}</Badge>;
const data = (iso: string) => new Date(iso.replace(' ', 'T'));
const espera = (iso: string) => Math.max(0, Math.round((Date.now() - data(iso).getTime()) / 60_000));
const feitoAs = (iso: string) => data(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }).replace(',', '');

function PedidosIndex({ e_atendente, atendente_escolhido, atendentes, itens, pedidos }: Props) {
  const [confirmar, setConfirmar] = useState<Acao | null>(null);
  const [marcando, setMarcando] = useState<string | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const semAtendente = !e_atendente && atendente_escolhido === null;
  const atualizar = () => router.reload({ only: ['itens', 'pedidos'] });

  async function marcarComoServido(a: Acao) {
    setConfirmar(null);
    setMarcando(`${a.tipo}-${a.id}`);
    setErro(null);
    const url = a.tipo === 'pedido' ? `/modules/orders/mark-as-served/${a.id}` : `/modules/orders/mark-line-order-as-served/${a.id}`;
    try {
      const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } });
      const corpo = await r.json().catch(() => null);
      if (!r.ok || !corpo?.success) setErro(corpo?.msg ?? 'Não foi possível marcar como servido.');
      atualizar();
    } finally {
      setMarcando(null);
    }
  }

  return (
    <div className="pb-8">
      <div data-contract="cabecalho">
        <PageHeader title="Pedidos"
          subtitle={e_atendente ? 'os seus pedidos ainda não servidos' : 'pedidos por atendente'}
          subnav={<RestauranteAbas />}
          actions={<Button variant="outline" onClick={atualizar}><RefreshCw className="size-4" /> Atualizar</Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        {!e_atendente && (
          <Inline data-contract="filtros" gap={2}>
            <label htmlFor="atendente" className="text-sm font-medium">Atendente</label>
            <select id="atendente" className="h-9 min-w-64 rounded-md border border-input bg-background px-2 text-sm"
              value={atendente_escolhido ?? ''}
              onChange={(e) => router.get(AQUI, e.target.value ? { service_staff: e.target.value } : {}, { preserveScroll: true })}>
              <option value="">Escolha o atendente</option>
              {atendentes.filter((a) => Boolean(a.id)).map((a) => <option key={a.id} value={a.id}>{a.nome}</option>)}
            </select>
          </Inline>
        )}

        {erro && <Alert variant="destructive" role="alert"><AlertDescription>{erro}</AlertDescription></Alert>}

        {semAtendente ? (
          <EmptyState title="Escolha o atendente" description="Os pedidos e os itens por servir aparecem para o atendente escolhido." />
        ) : (
          <Deferred data={['itens', 'pedidos']} fallback={<p className="py-6 text-sm text-muted-foreground">Carregando pedidos…</p>}>
            <Stack gap={2} data-contract="prontos">
              <Inline gap={2}>
                <span className="font-medium">Itens por servir</span>
                <span className="text-sm text-muted-foreground">{(itens ?? []).length} item(ns)</span>
              </Inline>
              {(itens ?? []).length > 0 ? (
                <ul className="divide-y rounded-lg border bg-card">
                  {(itens ?? []).map((i) => (
                    <li key={i.id} className="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2 text-sm">
                      <span className="font-medium">{i.mesa ?? '—'}</span>
                      <span className="text-muted-foreground">{i.cliente ?? '—'}</span>
                      <span className="font-mono"># {i.nota}</span>
                      <span><span className="font-mono">{i.quantidade} ×</span> {i.produto ?? '—'}</span>
                      {i.observacao && <span className="text-muted-foreground">{i.observacao}</span>}
                      <span className="text-muted-foreground">há {espera(i.feito_em)} min</span>
                      {selo(i.situacao)}
                      <Button className="ml-auto" size="sm" variant="outline" disabled={marcando === `item-${i.id}`}
                        onClick={() => setConfirmar({ tipo: 'item', id: i.id, nota: i.nota })}>
                        <Check className="size-4" /> Marcar como servido
                      </Button>
                    </li>
                  ))}
                </ul>
              ) : (
                <EmptyState title="Nenhum item esperando" description="Os itens do atendente ainda não servidos aparecem aqui." />
              )}
            </Stack>

            <Stack gap={2} data-contract="todos">
              <Inline gap={2}>
                <span className="font-medium">Todos os seus pedidos</span>
                <span className="text-sm text-muted-foreground">{(pedidos ?? []).length} pedido(s)</span>
              </Inline>
              <Grid min="sm" gap={3}>
                {(pedidos ?? []).map((p) => (
                  <article key={p.id} className="rounded-lg border bg-card p-4 shadow-sm">
                    <Inline gap={2} wrap>
                      <span className="font-mono font-semibold"># {p.nota}</span>
                      <span className="ml-auto">{selo(p.situacao)}</span>
                    </Inline>
                    <dl className="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-sm">
                      <dt className="text-muted-foreground">Feito às</dt><dd>{feitoAs(p.feito_em)}</dd>
                      <dt className="text-muted-foreground">Cliente</dt><dd>{p.cliente ?? '—'}</dd>
                      <dt className="text-muted-foreground">Mesa</dt><dd>{p.mesa ?? '—'}</dd>
                      <dt className="text-muted-foreground">Local</dt><dd>{p.local ?? '—'}</dd>
                    </dl>
                    <ul className="mt-2 space-y-0.5 text-sm">
                      {p.itens.map((i) => <li key={i.id}><span className="font-mono">{i.quantidade} ×</span> {i.nome ?? '—'}</li>)}
                    </ul>
                    <Button className="mt-3 w-full" size="sm" disabled={marcando === `pedido-${p.id}`}
                      onClick={() => setConfirmar({ tipo: 'pedido', id: p.id, nota: p.nota })}>
                      <Check className="size-4" /> Marcar como servido
                    </Button>
                  </article>
                ))}
              </Grid>
            </Stack>
          </Deferred>
        )}
      </Stack>

      <AlertDialog open={confirmar !== null} onOpenChange={(aberto) => { if (!aberto) setConfirmar(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {confirmar?.tipo === 'pedido' ? `Marcar o pedido # ${confirmar.nota} como servido?` : `Marcar este item do pedido # ${confirmar?.nota} como servido?`}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {confirmar?.tipo === 'pedido' ? 'Todos os itens do pedido passam a servido.' : 'Só este item passa a servido.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Voltar</AlertDialogCancel>
            <AlertDialogAction onClick={() => confirmar && marcarComoServido(confirmar)}>Marcar como servido</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

PedidosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default PedidosIndex;
