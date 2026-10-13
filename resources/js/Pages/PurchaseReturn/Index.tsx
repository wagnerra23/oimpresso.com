// @memcofre
//   tela: /purchase-return
//   module: Compras
//   stories: thread compras/playbook/08 (Devoluções de compra)
//   permissao: purchase.view | purchase.create
//
// Devoluções de compra do negócio. Atrás da flag `useV2ComprasDevolucoes` (PurchaseReturnController::FLAG_V2);
// desligada, a rota segue na Blade `purchase_return/index`. `?legacy=1` abre a Blade com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Compras/_telas/RUNBOOK-purchase-return-index.md
// Âncora de design: prototipo-ui/cowork/Wagner/compras-extras.jsx → telaDevs() (rota cmp-devolucoes)
//
// TELA DE VALOR (regra mestre). Total e devido chegam do controller como número (ordenar) e texto já formatado pelo
// num_f (mostrar), sobre o mesmo SQL e a mesma conta da DataTable (PurchaseReturnController::consultaDevolucoes).
// A tela não calcula, não formata e não soma valor: sem total devolvido nem total devido (decisão [W]).
// Editar, pagar, ver pagamentos e excluir seguem na Blade (?legacy=1).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { ExternalLink, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';

export interface Devolucao {
  id: number;
  data: string | null;
  ref: string;
  compra_origem: string | null;
  local: string;
  fornecedor: string;
  pagamento: string;
  pagamento_rotulo: string;
  total: number;
  total_texto: string;
  devido: number;
  devido_texto: string;
}
interface Lista {
  linhas: Devolucao[];
  truncado: boolean;
  por_pagamento: { rotulo: string; quantidade: number }[];
}
interface Props {
  filtros: { location_id: string | null };
  devolucoes?: Lista;
  opcoes?: { locais: Record<string, string> };
}

const TODOS = '__todos__';
const TOM_PAGAMENTO: Record<string, 'default' | 'secondary' | 'outline'> = { paid: 'default', partial: 'secondary', due: 'outline' };

function DevolucoesIndex({ filtros, devolucoes, opcoes }: Props) {
  const linhas = useMemo(() => devolucoes?.linhas ?? [], [devolucoes]);
  const [q, setQ] = useState('');
  const busca = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, []);

  // O filtro de local é do servidor: o mesmo SQL recebe o mesmo parâmetro da Blade.
  const filtrarLocal = (v: string | null) => {
    router.get('/purchase-return', v ? { location_id: v } : {}, { only: ['devolucoes', 'filtros'], preserveState: true, preserveScroll: true, replace: true });
  };

  const termo = q.trim().toLowerCase();
  const visiveis = linhas.filter((d) => !termo || `${d.ref} ${d.compra_origem ?? ''} ${d.fornecedor}`.toLowerCase().includes(termo));

  const colunas: ColumnDef<Devolucao, unknown>[] = [
    { id: 'data', header: 'Data', meta: { mono: true }, cell: ({ row: { original: d } }) => d.data ?? '—' },
    { id: 'ref', header: 'Nº de referência', meta: { mono: true }, cell: ({ row: { original: d } }) => <span className="font-medium">{d.ref}</span> },
    { id: 'origem', header: 'Compra de origem', meta: { mono: true }, cell: ({ row: { original: d } }) => d.compra_origem ?? '—' },
    { id: 'local', header: 'Local', cell: ({ row: { original: d } }) => d.local },
    { id: 'fornecedor', header: 'Fornecedor', cell: ({ row: { original: d } }) => d.fornecedor || '—' },
    { id: 'pagamento', header: 'Status do pagamento', cell: ({ row: { original: d } }) => <Badge variant={TOM_PAGAMENTO[d.pagamento] ?? 'outline'}>{d.pagamento_rotulo}</Badge> },
    { id: 'total', header: 'Valor total', meta: { align: 'right', mono: true }, cell: ({ row: { original: d } }) => d.total_texto },
    { id: 'devido', header: 'Valor devido', meta: { align: 'right', mono: true }, cell: ({ row: { original: d } }) => d.devido_texto },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Devoluções de compra"
          subtitle={devolucoes ? <><strong>{linhas.length}</strong> {linhas.length === 1 ? 'devolução' : 'devoluções'}</> : 'o que voltou para o fornecedor'}
          actions={
            <Button asChild variant="outline">
              <a href="/purchase-return?legacy=1"><ExternalLink className="size-4" /> Editar, pagar e excluir na tela clássica</a>
            </Button>
          } />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Stack data-contract="devolucoes-aviso" gap={1} className="rounded-md border p-4 text-sm">
          <strong>Devolução de compra</strong>
          <span className="text-muted-foreground">Sai do estoque e vira crédito com o fornecedor.</span>
        </Stack>

        <Deferred data="devolucoes" fallback={null}>
          {devolucoes && devolucoes.por_pagamento.length > 0 ? (
            <Inline data-contract="devolucoes-kpis" gap={4} wrap>
              {devolucoes.por_pagamento.map((p) => (
                <Stack key={p.rotulo} gap={0} className="min-w-40 rounded-md border p-4">
                  <span className="text-sm text-muted-foreground">{p.rotulo}</span>
                  <strong className="text-2xl">{p.quantidade}</strong>
                </Stack>
              ))}
            </Inline>
          ) : null}
        </Deferred>

        <Inline data-contract="devolucoes-filtros" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar devolução, compra ou fornecedor…" aria-label="Buscar devolução, compra ou fornecedor" />
          </div>
          <Deferred data="opcoes" fallback={null}>
            <Select value={filtros.location_id ?? TODOS} onValueChange={(v) => filtrarLocal(v === TODOS ? null : v)}>
              <SelectTrigger className="w-48" aria-label="Local"><SelectValue placeholder="Local" /></SelectTrigger>
              <SelectContent>
                <SelectItem value={TODOS}>Local: todos</SelectItem>
                {Object.entries(opcoes?.locais ?? {}).filter(([v]) => v !== '').map(([v, l]) => <SelectItem key={v} value={v}>{l}</SelectItem>)}
              </SelectContent>
            </Select>
          </Deferred>
        </Inline>

        <Deferred data="devolucoes" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando devoluções…</p>}>
          {linhas.length > 0 ? (
            <Stack gap={2}>
              <div data-contract="devolucoes-table">
                <DataTable columns={colunas} data={visiveis} caption="Devoluções de compra do negócio" rowKey={(d) => d.id}
                  emptyMessage="Nenhuma devolução com esse termo." />
              </div>
              {devolucoes?.truncado && (
                <p className="text-sm">
                  A lista mostra as devoluções mais recentes. Para ver todas, <a className="font-medium underline" href="/purchase-return?legacy=1">abra a tela clássica</a>.
                </p>
              )}
            </Stack>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma devolução de compra" description="Devoluções com esse filtro aparecem aqui." />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

DevolucoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default DevolucoesIndex;
