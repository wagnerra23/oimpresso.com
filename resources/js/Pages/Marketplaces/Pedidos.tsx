// @memcofre
//   tela: /marketplaces/pedidos
//   module: Marketplaces
//   stories: US-MKT-008 (UC-PEDIDO-00..04)
//   permissao: marketplaces.access · escolher local e conectar exige marketplaces.account.connect
//
// Marketplaces/Pedidos — carimbado do PT-01 Lista por criar-tela.mjs (UI-0013) e preenchido pelo
// designer-agente (ADR 0282/0438: módulo novo, sem protótipo). Contas conectadas no topo (o local
// de cada conta decide onde a venda entra — decisão [W] 2026-10-26) e a lista de pedidos.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Plug, Search } from 'lucide-react';
import type { ColumnDef } from '@tanstack/react-table';
import { PageHeader } from '@/Components/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import { Inline, Stack } from '@/Components/layout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { fmtDataHoraBr } from '@/Lib/datetime-br';

interface Conta { id: number; marketplace: string | null; vendedor: string; situacao: string; local_id: number | null }
interface Local { id: number; nome: string }
interface Pedido {
  id: number;
  numero: string;
  marketplace: string | null;
  data: string | null;
  situacao_ml: string | null;
  total: number | null;
  venda_id: number | null;
}
interface Props {
  filtros: { q: string; situacao: string };
  pode_conectar: boolean;
  contas?: Conta[];
  locais?: Local[];
  pedidos?: Pedido[];
}

/** Sentinela do item fixo "Todos" — o Radix Select não aceita value vazio. */
const TODOS = '__todos__';

const SITUACAO_ML: Record<string, string> = {
  paid: 'Pago',
  confirmed: 'Confirmado',
  payment_required: 'Aguardando pagamento',
  payment_in_process: 'Pagamento em análise',
  cancelled: 'Cancelado',
};

const COLUNAS: ColumnDef<Pedido, unknown>[] = [
  {
    id: 'numero',
    accessorFn: (p) => p.numero,
    header: 'Pedido',
    cell: ({ row: { original: p } }) => (
      <>
        <span className="block font-medium tabular-nums">{p.numero}</span>
        <span className="block text-xs text-muted-foreground">{p.marketplace ?? 'Marketplace'}</span>
      </>
    ),
  },
  {
    id: 'data',
    accessorFn: (p) => p.data ?? '',
    header: 'Data',
    cell: ({ row: { original: p } }) => (p.data ? fmtDataHoraBr(p.data.slice(0, 16).replace('T', ' ')) : '—'),
  },
  {
    id: 'situacao',
    accessorFn: (p) => p.situacao_ml ?? '',
    header: 'Situação no marketplace',
    cell: ({ row: { original: p } }) => <Badge variant="outline">{SITUACAO_ML[p.situacao_ml ?? ''] ?? p.situacao_ml ?? '—'}</Badge>,
  },
  {
    id: 'total',
    accessorFn: (p) => p.total ?? 0,
    header: 'Total',
    meta: { align: 'right' },
    cell: ({ row: { original: p } }) => (p.total !== null ? `R$ ${formatDecimalPtBR(p.total, 2)}` : '—'),
  },
  {
    id: 'venda',
    accessorFn: (p) => p.venda_id ?? 0,
    header: 'Venda',
    cell: ({ row: { original: p } }) =>
      p.venda_id !== null ? (
        <Link href={`/sells/${p.venda_id}`} className="font-medium hover:underline">Ver venda</Link>
      ) : (
        <span className="text-muted-foreground">Ainda não virou venda</span>
      ),
  },
];

function Contas({ contas, locais, podeConectar }: { contas: Conta[]; locais: Local[]; podeConectar: boolean }) {
  const definirLocal = (conta: number, local: string) =>
    router.patch(`/marketplaces/contas/${conta}/local`, { location_id: Number(local) }, { preserveScroll: true, only: ['contas'] });

  if (contas.length === 0) {
    return <p className="text-sm text-muted-foreground">Nenhuma conta conectada. Conecte a conta do Mercado Livre para os pedidos chegarem aqui.</p>;
  }

  return (
    <Stack asChild gap={2}>
      <ul>
        {contas.map((c) => (
          <Inline asChild key={c.id} wrap align="center" gap={3} className="rounded-md border p-3">
            <li>
              <span className="font-medium">{c.marketplace ?? 'Marketplace'} · {c.vendedor}</span>
              {c.situacao !== 'active' && <Badge variant="warning">Reconectar</Badge>}
              <Inline asChild align="center" gap={2} className="ml-auto text-sm">
                <div>
                  <span>Local de venda</span>
                  <Select
                    value={c.local_id !== null ? String(c.local_id) : undefined}
                    disabled={!podeConectar}
                    onValueChange={(v) => definirLocal(c.id, v)}
                  >
                    <SelectTrigger className="w-48" aria-label={`Local de venda da conta ${c.vendedor}`}>
                      <SelectValue placeholder="Escolha o local" />
                    </SelectTrigger>
                    <SelectContent>
                      {locais.map((l) => <SafeSelectItem key={l.id} value={String(l.id)}>{l.nome}</SafeSelectItem>)}
                    </SelectContent>
                  </Select>
                </div>
              </Inline>
              {c.local_id === null && <span className="w-full text-xs text-muted-foreground">Sem local escolhido, os pedidos desta conta não viram venda.</span>}
            </li>
          </Inline>
        ))}
      </ul>
    </Stack>
  );
}

export default function Pedidos({ filtros, pode_conectar, contas, locais, pedidos }: Props) {
  const [q, setQ] = useState(filtros.q);
  const visitar = (busca: string, situacao: string) =>
    router.get('/marketplaces/pedidos', { ...(busca ? { q: busca } : {}), ...(situacao ? { situacao } : {}) }, { preserveState: true, replace: true });
  const buscar = (e: FormEvent) => {
    e.preventDefault();
    visitar(q, filtros.situacao);
  };

  return (
    <div data-screen-label="Marketplaces · Pedidos">
      {/* `data-contract` = âncora do contrato de tela (governance/design/contracts/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <PageHeader
          title="Pedidos dos marketplaces"
          subtitle="Pedidos recebidos do Mercado Livre e a venda gerada no oimpresso"
          actions={pode_conectar ? (
            <Button asChild>
              <a href="/marketplaces/contas/mercado-livre/conectar"><Plug className="size-4" aria-hidden="true" /> Conectar Mercado Livre</a>
            </Button>
          ) : undefined}
        />
      </div>
      <Stack gap={4} className="p-4">
        <Stack asChild gap={2}>
          <section aria-label="Contas conectadas">
            <h2 className="text-sm font-semibold">Contas conectadas</h2>
            <Deferred data={['contas', 'locais']} fallback={<Skeleton className="h-16 w-full" />}>
              <Contas contas={contas ?? []} locais={locais ?? []} podeConectar={pode_conectar} />
            </Deferred>
          </section>
        </Stack>
        <Inline data-contract="filtros" wrap align="center" gap={2}>
          <Inline asChild gap={2} className="max-w-md">
            <form onSubmit={buscar} role="search">
              <Input aria-label="Buscar pelo número do pedido" placeholder="Número do pedido" value={q} onChange={(e) => setQ(e.target.value)} />
              <Button type="submit" variant="outline"><Search className="size-4" aria-hidden="true" /> Buscar</Button>
            </form>
          </Inline>
          <Select value={filtros.situacao || TODOS} onValueChange={(v) => visitar(filtros.q, v === TODOS ? '' : v)}>
            <SelectTrigger className="w-48" aria-label="Filtrar por venda">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={TODOS}>Todos os pedidos</SelectItem>
              <SelectItem value="sem_venda">Ainda sem venda</SelectItem>
              <SelectItem value="com_venda">Com venda</SelectItem>
            </SelectContent>
          </Select>
        </Inline>
        <div data-contract="lista">
          <Deferred data="pedidos" fallback={<Skeleton className="h-40 w-full" />}>
            <DataTable<Pedido>
              columns={COLUNAS}
              data={pedidos ?? []}
              caption="Pedidos dos marketplaces"
              rowKey={(p) => p.id}
              emptyMessage={filtros.q ? `Nenhum pedido com "${filtros.q}".` : 'Nenhum pedido recebido ainda.'}
            />
          </Deferred>
        </div>
      </Stack>
    </div>
  );
}

Pedidos.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
