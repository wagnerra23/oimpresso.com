// Gaveta "Rastrear" do relatório de Lotes (UC-RLT-06 · US-COM-025 · UC-FLX-18).
// Só lê o JSON GET /producao/lotes/{id}/rastreio (Modules/Producao) — não calcula nada: saldo, frações e
// quantidades chegam prontos. Uma linha do relatório pode juntar mais de uma compra com o mesmo número de
// lote; cada compra é um rolo e vem numa seção própria.
import { useEffect, useState } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Inline, Stack } from '@/Components/layout';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';

export type AlvoRastreio = { ids: number[]; lote: string; produto: string; unidade: string };

type Fracao = { consumo_id: number; ordem_id: number; venda: string | null; etapa: string; quantidade: string; momento: string; apontado_por: string };
type Rastreio = {
  lote: { id: number; recebido_em: string };
  quantidades: { recebido: string; usado_em_os: string; saldo: string };
  fracoes: Fracao[];
};

type Estado = { carregando: true } | { carregando: false; rolos: Rastreio[]; erro: string | null };

export default function RastreioDoLoteSheet({ alvo, onFechar }: { alvo: AlvoRastreio | null; onFechar: () => void }) {
  const [estado, setEstado] = useState<Estado>({ carregando: true });

  useEffect(() => {
    if (!alvo) return;
    let vivo = true;
    setEstado({ carregando: true });
    Promise.all(alvo.ids.map(async id => {
      const r = await fetch(`/producao/lotes/${id}/rastreio`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      if (!r.ok) throw new Error(r.status === 403 ? 'Sem permissão para ver o rastreio.' : 'Não foi possível abrir o rastreio deste lote.');
      return (await r.json()) as Rastreio;
    }))
      .then(rolos => vivo && setEstado({ carregando: false, rolos, erro: null }))
      .catch((e: Error) => vivo && setEstado({ carregando: false, rolos: [], erro: e.message }));
    return () => { vivo = false; };
  }, [alvo]);

  const qtd = (v: string) => `${formatDecimalPtBR(Number(v), 2)} ${alvo?.unidade ?? ''}`.trim();

  return (
    <Sheet open={alvo !== null} onOpenChange={aberto => !aberto && onFechar()}>
      <SheetContent className="w-full sm:max-w-lg" data-testid="lt-rastreio">
        <SheetHeader>
          <SheetTitle>Rastreio do lote {alvo?.lote}</SheetTitle>
          <SheetDescription>{alvo?.produto} · em que OS cada fração do rolo foi usada</SheetDescription>
        </SheetHeader>
        <Stack gap={4} className="overflow-y-auto px-4 pb-4 text-sm">
          {estado.carregando ? (
            <p className="text-muted-foreground" role="status">Carregando…</p>
          ) : estado.erro ? (
            <p className="text-destructive" role="alert">{estado.erro}</p>
          ) : estado.rolos.map(rolo => (
            <section key={rolo.lote.id} className="rounded-lg border">
              <Inline asChild wrap gap={2} align="baseline" justify="between" className="border-b bg-muted px-3 py-2 text-xs"><header>
                <span>Recebido em {rolo.lote.recebido_em.slice(0, 10).split('-').reverse().join('/')} · {qtd(rolo.quantidades.recebido)}</span>
                <span className="font-semibold">Saldo {qtd(rolo.quantidades.saldo)}</span>
              </header></Inline>
              <table className="w-full">
                <caption className="sr-only">Frações usadas em OS</caption>
                <thead className="text-xs uppercase tracking-wider text-muted-foreground">
                  <tr>
                    <th scope="col" className="px-3 py-2 text-left font-semibold">OS</th>
                    <th scope="col" className="px-3 py-2 text-left font-semibold">Etapa</th>
                    <th scope="col" className="px-3 py-2 text-right font-semibold">Usado</th>
                  </tr>
                </thead>
                <tbody>
                  {rolo.fracoes.length === 0 ? (
                    <tr><td colSpan={3} className="px-3 py-4 text-center text-muted-foreground">Nenhuma fração usada em OS.</td></tr>
                  ) : rolo.fracoes.map(f => (
                    <tr key={f.consumo_id} className="border-t">
                      <td className="px-3 py-2">
                        <span className="font-mono text-xs">OS {f.ordem_id}</span>
                        {f.venda && <span className="ml-1 text-xs text-muted-foreground">· venda {f.venda}</span>}
                      </td>
                      <td className="px-3 py-2">
                        {f.etapa}
                        {f.apontado_por && <span className="block text-xs text-muted-foreground">{f.apontado_por}</span>}
                      </td>
                      <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(f.quantidade)}</td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="border-t font-semibold">
                  <tr>
                    <th scope="row" colSpan={2} className="px-3 py-2 text-left">Usado em OS</th>
                    <td className="px-3 py-2 text-right font-mono tabular-nums whitespace-nowrap">{qtd(rolo.quantidades.usado_em_os)}</td>
                  </tr>
                </tfoot>
              </table>
            </section>
          ))}
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
