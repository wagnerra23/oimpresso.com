// US-COM-013 — receber um pedido de compra em partes (decisão coordenação 2026-10-26).
// Lista os pedidos abertos do fornecedor (GET /purchases/pedidos-abertos) e devolve à tela o pedido
// escolhido, com o que falta receber por linha. Quem grava é o store() da compra: cada linha leva o
// purchase_order_line_id e o pedido vai em purchase_order_ids.

import { useEffect, useState } from 'react';
import { PackageCheck } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { FieldError } from '@/Components/ui/field-state';

export interface LinhaDoPedido {
  purchase_order_line_id: number;
  product_id: number;
  variation_id: number;
  product_name: string;
  unit_name: string;
  pedido: number;
  recebido: number;
  a_receber: number;
  pp_without_discount: number;
  discount_percent: number;
  purchase_price: number;
  purchase_price_inc_tax: number;
  item_tax: number;
  tax_id: number | null;
}

export interface PedidoAberto {
  id: number;
  ref_no: string | null;
  transaction_date: string | null;
  delivery_date: string | null;
  status: string;
  linhas: LinhaDoPedido[];
}

interface Props {
  contactId: number | null;
  locationId: number | null;
  /** Pedidos já trazidos para esta compra (não aparecem de novo na lista). */
  jaUsados: number[];
  onPick: (pedido: PedidoAberto) => void;
}

/** Linhas da compra a partir do pedido: cada uma entra com o que falta receber e os preços do pedido. */
export function linhasDoPedido(pedido: PedidoAberto) {
  return pedido.linhas.map((l) => ({
    product_name: l.product_name,
    product_id: l.product_id,
    variation_id: l.variation_id,
    unit_id: null,
    unit_name: l.unit_name,
    quantity: l.a_receber,
    pp_without_discount: l.pp_without_discount,
    discount_percent: l.discount_percent,
    item_tax: l.item_tax,
    tax_id: l.tax_id,
    purchase_price: l.purchase_price,
    purchase_price_inc_tax: l.purchase_price_inc_tax,
    purchase_order_line_id: l.purchase_order_line_id,
    po_pedido: l.pedido,
    po_recebido: l.recebido,
  }));
}

const dataCurta = (iso: string | null) => (iso ? iso.slice(0, 10).split('-').reverse().join('/') : '');

export default function ReceberDoPedido({ contactId, locationId, jaUsados, onPick }: Props) {
  const [pedidos, setPedidos] = useState<PedidoAberto[]>([]);
  const [escolhido, setEscolhido] = useState<string>('');
  const [erro, setErro] = useState<string | null>(null);

  useEffect(() => {
    setPedidos([]);
    setEscolhido('');
    setErro(null);
    if (!contactId) return;
    const params = new URLSearchParams({ contact_id: String(contactId) });
    if (locationId) params.set('location_id', String(locationId));
    let vivo = true;
    fetch(`/purchases/pedidos-abertos?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((j: { pedidos: PedidoAberto[] }) => {
        if (vivo) setPedidos(j.pedidos ?? []);
      })
      .catch(() => {
        if (vivo) setErro('Não foi possível carregar os pedidos de compra deste fornecedor.');
      });
    return () => {
      vivo = false;
    };
  }, [contactId, locationId]);

  const disponiveis = pedidos.filter((p) => !jaUsados.includes(p.id));
  if (!contactId || (disponiveis.length === 0 && !erro)) return null;

  const trazer = () => {
    const pedido = disponiveis.find((p) => String(p.id) === escolhido);
    if (!pedido) return;
    onPick(pedido);
    setEscolhido('');
  };

  return (
    <div className="mb-3 rounded-lg border border-border bg-muted/40 p-3" data-contract="purchase-receber-pedido">
      <div className="flex items-center gap-2 mb-2">
        <PackageCheck className="h-3.5 w-3.5 text-muted-foreground" />
        <span className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium flex-1">
          Receber de um pedido de compra
        </span>
      </div>
      <FieldError className="mb-2">{erro}</FieldError>
      {disponiveis.length > 0 && (
        <div className="flex items-center gap-2">
          <Select value={escolhido} onValueChange={setEscolhido}>
            <SelectTrigger aria-label="Pedido de compra" className="flex-1 text-[13px]">
              <SelectValue placeholder="Escolha o pedido que chegou" />
            </SelectTrigger>
            <SelectContent>
              {disponiveis.map((p) => {
                const falta = p.linhas.reduce((s, l) => s + l.a_receber, 0);
                return (
                  <SelectItem key={p.id} value={String(p.id)}>
                    {(p.ref_no || `Pedido ${p.id}`) +
                      (p.transaction_date ? ` · ${dataCurta(p.transaction_date)}` : '') +
                      ` · ${falta.toLocaleString('pt-BR')} a receber` +
                      (p.status === 'partial' ? ' · parcial' : '')}
                  </SelectItem>
                );
              })}
            </SelectContent>
          </Select>
          <Button type="button" size="sm" onClick={trazer} disabled={!escolhido}>
            Trazer itens
          </Button>
        </div>
      )}
      <p className="text-[11px] text-muted-foreground mt-2">
        Os itens entram com a quantidade que falta. Ajuste para o que chegou; o resto fica no pedido.
      </p>
    </div>
  );
}
