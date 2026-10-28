// Ações de sincronização do painel do WooCommerce (thread woocommerce/playbook/01, F3-2).
//
// Chama os MESMOS endpoints JSON que a Blade `woocommerce::woocommerce.index` chama (sync-categories,
// sync-products em páginas de 100 por offset, sync-orders, reset-categories, reset-products), com o
// X-Requested-With que eles exigem. Nada de cálculo aqui: o que sobe para a loja é decidido pelo backend
// de sempre. Reiniciar pede confirmação com a mesma caixa "sim, reiniciar" da Blade.

import { useEffect, useState, type ReactNode } from 'react';
import { router } from '@inertiajs/react';
import { RefreshCw, RotateCcw } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
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
import { Grid, Inline, Stack } from '@/Components/layout';

export interface Ultimas {
  categories: string | null;
  new_products: string | null;
  all_products: string | null;
  orders: string | null;
}
export interface PodeSincronizar { sincronizar: boolean; categorias: boolean; pedidos: boolean }
type Acao = 'categorias' | 'novos' | 'todos' | 'pedidos';
type Reinicio = 'reset-categorias' | 'reset-produtos';
interface Resposta { success?: boolean | number; msg?: string; total_products?: number }

async function chamar(url: string): Promise<Resposta> {
  const r = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
  if (!r.ok) throw new Error(`HTTP ${r.status}`);
  return (await r.json()) as Resposta;
}

const recarregar = () => router.reload({ only: ['resumo', 'produtos', 'ultimas'] });

function Bloco({ titulo, ultima, children }: { titulo: string; ultima: string | null | undefined; children: ReactNode }) {
  return (
    <Stack gap={2} className="rounded-md border p-4">
      <p className="text-sm font-medium">{titulo}</p>
      <p className="text-xs text-muted-foreground">{ultima ? `Última sincronização: ${ultima}` : 'Nunca sincronizado'}</p>
      {children}
    </Stack>
  );
}

export default function SincronizarLoja({ pode, ultimas }: { pode: PodeSincronizar; ultimas?: Ultimas }) {
  const [rodando, setRodando] = useState<Acao | Reinicio | null>(null);
  const [reiniciar, setReiniciar] = useState<Reinicio | null>(null);
  const [certeza, setCerteza] = useState(false);

  // Igual à Blade: sair da página no meio da sincronização pede confirmação do navegador.
  useEffect(() => {
    if (!rodando) return;
    const segurar = (e: BeforeUnloadEvent) => { e.preventDefault(); };
    window.addEventListener('beforeunload', segurar);
    return () => window.removeEventListener('beforeunload', segurar);
  }, [rodando]);

  const fim = (r: Resposta) => (r.success ? toast.success(r.msg ?? 'Pronto.') : toast.error(r.msg ?? 'Não deu certo.'));

  const sincronizar = async (acao: Acao) => {
    setRodando(acao);
    try {
      if (acao === 'categorias') fim(await chamar('/woocommerce/sync-categories'));
      if (acao === 'pedidos') fim(await chamar('/woocommerce/sync-orders'));
      if (acao === 'novos' || acao === 'todos') {
        // Páginas de 100 até o backend responder total_products = 0, como o sync_products() da Blade.
        const tipo = acao === 'novos' ? 'new' : 'all';
        for (let offset = 0; ; offset++) {
          const r = await chamar(`/woocommerce/sync-products?type=${tipo}&offset=${offset}`);
          fim(r);
          if (!r.success || !r.total_products) break;
        }
      }
    } catch {
      toast.error('A sincronização não respondeu. Confira a conexão com a loja.');
    } finally {
      setRodando(null);
      recarregar();
    }
  };

  const confirmarReinicio = async () => {
    const qual = reiniciar;
    setReiniciar(null);
    setCerteza(false);
    if (!qual) return;
    setRodando(qual);
    try {
      fim(await chamar(qual === 'reset-categorias' ? '/woocommerce/reset-categories' : '/woocommerce/reset-products'));
    } catch {
      toast.error('Não deu para reiniciar agora.');
    } finally {
      setRodando(null);
      recarregar();
    }
  };

  const ocupado = rodando !== null;
  const rotulo = (a: Acao, txt: string) => (rodando === a ? 'Sincronizando…' : txt);

  if (!pode.categorias && !pode.sincronizar && !pode.pedidos) return null;

  return (
    <div data-contract="sincronizar">
      <Grid min="sm" gap={4}>
        {pode.categorias && (
          <Bloco titulo="Categorias" ultima={ultimas?.categories}>
            <Button onClick={() => sincronizar('categorias')} disabled={ocupado}><RefreshCw className="size-4" aria-hidden="true" /> {rotulo('categorias', 'Sincronizar categorias')}</Button>
            <Button variant="ghost" size="sm" onClick={() => setReiniciar('reset-categorias')} disabled={ocupado}><RotateCcw className="size-4" aria-hidden="true" /> Reiniciar categorias sincronizadas</Button>
          </Bloco>
        )}
        {pode.sincronizar && (
          <Bloco titulo="Produtos" ultima={ultimas?.all_products ?? ultimas?.new_products}>
            <Inline wrap gap={2}>
              <Button variant="outline" onClick={() => sincronizar('novos')} disabled={ocupado} title="Só cria na loja os produtos que ainda não estão lá.">{rotulo('novos', 'Só os novos')}</Button>
              <Button onClick={() => sincronizar('todos')} disabled={ocupado} title="Cria os novos e atualiza os que mudaram desde a última sincronização."><RefreshCw className="size-4" aria-hidden="true" /> {rotulo('todos', 'Sincronizar todos')}</Button>
            </Inline>
            <Button variant="ghost" size="sm" onClick={() => setReiniciar('reset-produtos')} disabled={ocupado}><RotateCcw className="size-4" aria-hidden="true" /> Reiniciar produtos sincronizados</Button>
          </Bloco>
        )}
        {pode.pedidos && (
          <Bloco titulo="Pedidos" ultima={ultimas?.orders}>
            <Button onClick={() => sincronizar('pedidos')} disabled={ocupado}><RefreshCw className="size-4" aria-hidden="true" /> {rotulo('pedidos', 'Sincronizar pedidos')}</Button>
          </Bloco>
        )}
      </Grid>

      <AlertDialog open={reiniciar !== null} onOpenChange={(aberto) => { if (!aberto) { setReiniciar(null); setCerteza(false); } }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{reiniciar === 'reset-categorias' ? 'Reiniciar categorias sincronizadas?' : 'Reiniciar produtos sincronizados?'}</AlertDialogTitle>
            <AlertDialogDescription>
              O vínculo com a loja é apagado no ERP e a próxima sincronização cria tudo de novo. Nada é apagado na loja.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <Inline gap={2} className="items-center">
            <Checkbox id="woo-certeza" checked={certeza} onCheckedChange={(v) => setCerteza(v === true)} />
            <Label htmlFor="woo-certeza">Sim, quero reiniciar</Label>
          </Inline>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={!certeza} onClick={confirmarReinicio}>Reiniciar</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
