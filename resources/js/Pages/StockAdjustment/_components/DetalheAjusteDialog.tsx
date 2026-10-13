// "Ver" da lista de ajustes. Antes o botão fazia `router.visit('/stock-adjustments/{id}')`, e o
// `show()` devolve o fragmento de modal da tela Blade, que não é resposta Inertia. Agora o
// diálogo pede o mesmo detalhe em JSON (`Accept: application/json`) e mostra aqui.
import { useEffect, useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Skeleton } from '@/Components/ui/skeleton';

interface Linha {
  produto: string | null;
  lote: string | null;
  validade: string | null;
  quantidade: number;
  preco_unitario: number | null;
  subtotal: number | null;
}

interface Detalhe {
  ref_no: string;
  transaction_date: string;
  local: string | null;
  tipo: string;
  additional_notes: string | null;
  pode_ver_preco: boolean;
  linhas: Linha[];
  final_total: number | null;
  total_amount_recovered: number | null;
}

const brl = (v: number | null) =>
  v === null ? '—' : new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v);
const qtd = (v: number) => new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(v);
const data = (v: string | null) => (v ? new Date(v.replace(' ', 'T')).toLocaleDateString('pt-BR') : '');

export default function DetalheAjusteDialog({ id, onClose }: { id: number | null; onClose: () => void }) {
  const [detalhe, setDetalhe] = useState<Detalhe | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  useEffect(() => {
    if (id === null) return;
    let cancelado = false;
    setDetalhe(null);
    setErro(null);
    fetch(`/stock-adjustments/${id}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(async (res) => {
        if (res.status === 403) throw new Error('Você não tem permissão para ver este ajuste.');
        if (res.status === 404) throw new Error('Ajuste não encontrado.');
        if (!res.ok) throw new Error(`Não foi possível carregar o ajuste (HTTP ${res.status}).`);
        return (await res.json()) as Detalhe;
      })
      .then((d) => !cancelado && setDetalhe(d))
      .catch((e: Error) => !cancelado && setErro(e.message));
    return () => {
      cancelado = true;
    };
  }, [id]);

  return (
    <Dialog open={id !== null} onOpenChange={(aberto) => !aberto && onClose()}>
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>Ajuste de estoque {detalhe ? `#${detalhe.ref_no}` : ''}</DialogTitle>
          <DialogDescription>
            {detalhe ? `${data(detalhe.transaction_date)} · ${detalhe.local ?? '—'} · ${detalhe.tipo}` : 'Detalhes do ajuste de estoque'}
          </DialogDescription>
        </DialogHeader>

        {erro && <p className="text-sm text-destructive">{erro}</p>}
        {!erro && !detalhe && <Skeleton className="h-40 w-full" />}
        {detalhe && (
          <div className="space-y-4 text-[13px]">
            <div className="max-h-[50vh] overflow-auto border rounded-md">
              <table className="w-full">
                <thead className="bg-muted text-[12px] text-muted-foreground">
                  <tr>
                    <th className="text-left px-3 py-2">Produto</th>
                    <th className="text-right px-3 py-2">Quantidade</th>
                    <th className="text-right px-3 py-2">Preço unitário</th>
                    <th className="text-right px-3 py-2">Subtotal</th>
                  </tr>
                </thead>
                <tbody>
                  {detalhe.linhas.map((l, i) => (
                    <tr key={i} className="border-t">
                      <td className="px-3 py-2">
                        <div className="font-medium">{l.produto ?? '—'}</div>
                        {(l.lote || l.validade) && (
                          <div className="text-[12px] text-muted-foreground">
                            {[l.lote && `Lote ${l.lote}`, l.validade && `Val. ${data(l.validade)}`].filter(Boolean).join(' · ')}
                          </div>
                        )}
                      </td>
                      <td className="px-3 py-2 text-right tabular-nums">{qtd(l.quantidade)}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{brl(l.preco_unitario)}</td>
                      <td className="px-3 py-2 text-right tabular-nums">{brl(l.subtotal)}</td>
                    </tr>
                  ))}
                  {detalhe.linhas.length === 0 && (
                    <tr>
                      <td colSpan={4} className="px-3 py-6 text-center text-muted-foreground">
                        Nenhum produto neste ajuste.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>

            {detalhe.pode_ver_preco && (
              <dl className="ml-auto w-64 space-y-1 tabular-nums">
                <div className="flex justify-between font-semibold"><dt>Total</dt><dd>{brl(detalhe.final_total)}</dd></div>
                <div className="flex justify-between"><dt>Valor recuperado</dt><dd>{brl(detalhe.total_amount_recovered)}</dd></div>
              </dl>
            )}

            {detalhe.additional_notes && (
              <div>
                <div className="text-muted-foreground text-[12px]">Motivo do ajuste</div>
                <p className="whitespace-pre-line">{detalhe.additional_notes}</p>
              </div>
            )}
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
