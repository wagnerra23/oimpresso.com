// US-COM-030 — MRP na tela de requisições: o que falta comprar somando as OS (reserva de insumo) e o
// estoque mínimo, por insumo e local, e até quando pedir pelo prazo do fornecedor. Só mostra: o cálculo
// é do servidor (Modules\Compras\Services\NecessidadeDeCompra) e a data chega formatada.

import type { ColumnDef } from '@tanstack/react-table';
import { Badge } from '@/Components/ui/badge';
import DataTable from '@/Components/shared/DataTable';
import { Stack } from '@/Components/layout';

export interface LinhaNecessidade {
  chave: string;
  insumo: string;
  local: string;
  fornecedor: string | null;
  saldo: number;
  comprometido: number;
  minimo: number;
  a_caminho: number;
  necessidade: number;
  prazo_dias: number | null;
  pedir_ate: string;
  atrasado: boolean;
}

const num = (n: number) => n.toLocaleString('pt-BR', { maximumFractionDigits: 4 });

const colunasNecessidade: ColumnDef<LinhaNecessidade, unknown>[] = [
  { id: 'insumo', header: 'Insumo', cell: ({ row: { original: l } }) => <span className="font-medium">{l.insumo}</span> },
  { id: 'local', header: 'Local', cell: ({ row: { original: l } }) => l.local },
  { id: 'necessidade', header: 'Comprar', meta: { mono: true }, cell: ({ row: { original: l } }) => <strong>{num(l.necessidade)}</strong> },
  { id: 'conta', header: 'Reservado + mínimo − saldo − a caminho', meta: { mono: true },
    cell: ({ row: { original: l } }) => `${num(l.comprometido)} + ${num(l.minimo)} − ${num(l.saldo)} − ${num(l.a_caminho)}` },
  { id: 'fornecedor', header: 'Fornecedor', cell: ({ row: { original: l } }) => l.fornecedor ?? '—' },
  { id: 'pedir', header: 'Pedir até', meta: { mono: true },
    cell: ({ row: { original: l } }) => (
      <span>
        {l.pedir_ate}
        {l.prazo_dias !== null && <span className="text-muted-foreground"> · {l.prazo_dias} d</span>}
        {l.atrasado && <Badge variant="danger" className="ml-2">Atrasado</Badge>}
      </span>
    ) },
];

export default function NecessidadeDeCompra({ linhas }: { linhas: LinhaNecessidade[] }) {
  if (linhas.length === 0) return null;
  const atrasados = linhas.filter((l) => l.atrasado).length;

  return (
    <Stack gap={2} data-contract="necessidade-compra">
      <h2 className="text-base font-semibold">
        Necessidade de compra{' '}
        <span className="text-sm font-normal text-muted-foreground">
          · {linhas.length} {linhas.length === 1 ? 'insumo' : 'insumos'}
          {atrasados > 0 && <> · {atrasados} {atrasados === 1 ? 'já passou' : 'já passaram'} da data de pedir</>}
        </span>
      </h2>
      <DataTable columns={colunasNecessidade} data={linhas} caption="Necessidade de compra das OS e do estoque mínimo"
        rowKey={(l) => l.chave} emptyMessage="Nada a comprar." />
      <p className="text-sm text-muted-foreground">
        Reservado são os insumos que as OS e as vendas já seguram; a caminho são as compras não recebidas e o que falta dos
        pedidos de compra. A data de pedir é a da primeira OS menos o prazo do fornecedor.
      </p>
    </Stack>
  );
}
