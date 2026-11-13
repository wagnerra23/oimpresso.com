// @memcofre
//   tela: /associacoes/lista
//   module: Associacoes
//   stories: US-ASSOC-002 (UC-ASSOC-01..07)
//   permissao: associacoes.access · gravar exige associacoes.editar · criar e trocar representantes exige associacoes.rede
//
// Lista das associações da rede (PT-01) com a ficha em drawer (ADR 0451 D3/D4/D7). O servidor
// já entrega só o alcance do usuário: a diretoria vê a rede, o representante vê a associação dele.
// A coluna Diretoria mostra a pendência de ata (D7): avisa, não bloqueia.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Plus, Search } from 'lucide-react';
import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/Components/shared/DataTable';
import { PageHeader } from '@/Components/PageHeader';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import FichaAssociacao from './_components/FichaAssociacao';
import { SITUACOES, VARIANTE_PENDENCIA, VARIANTE_SITUACAO, rotuloPendencia, type Ficha, type LinhaAssociacao } from './_components/tipos';

interface Props {
  filtros: { q: string };
  pode_editar: boolean;
  pode_rede: boolean;
  prazo_dias: number;
  associacoes?: LinhaAssociacao[];
  ficha?: Ficha | null;
}

const COLUNAS: ColumnDef<LinhaAssociacao, unknown>[] = [
  {
    id: 'nome',
    accessorFn: (a) => a.nome,
    header: 'Associação',
    cell: ({ row: { original: a } }) => (
      <>
        <span className="block font-medium">{a.nome}</span>
        {a.sigla && <span className="block text-xs text-muted-foreground">{a.sigla}</span>}
      </>
    ),
  },
  { id: 'cidade', accessorFn: (a) => a.cidade, header: 'Cidade' },
  { id: 'regiao', accessorFn: (a) => a.regiao ?? '', header: 'Região', cell: ({ row: { original: a } }) => a.regiao ?? '—' },
  {
    id: 'situacao',
    accessorFn: (a) => a.situacao,
    header: 'Situação',
    cell: ({ row: { original: a } }) => <Badge variant={VARIANTE_SITUACAO[a.situacao] ?? 'neutral'}>{SITUACOES[a.situacao] ?? a.situacao}</Badge>,
  },
  { id: 'representante', accessorFn: (a) => a.representante ?? '', header: 'Representante', cell: ({ row: { original: a } }) => a.representante ?? 'sem representante' },
  {
    id: 'pendencia',
    accessorFn: (a) => a.pendencia.estado,
    header: 'Diretoria',
    cell: ({ row: { original: a } }) => (
      <Badge data-contract="assoc-pendencia" variant={VARIANTE_PENDENCIA[a.pendencia.estado]}>{rotuloPendencia(a.pendencia)}</Badge>
    ),
  },
  { id: 'documentos', accessorFn: (a) => a.documentos, header: 'Documentos', meta: { align: 'right' } },
];

export default function AssociacoesIndex({ filtros, pode_editar, pode_rede, prazo_dias, associacoes, ficha }: Props) {
  const [q, setQ] = useState(filtros.q);
  const [aberta, setAberta] = useState<'nova' | 'ficha' | null>(ficha ? 'ficha' : null);

  const buscar = (e: FormEvent) => {
    e.preventDefault();
    router.get('/associacoes/lista', q ? { q } : {}, { preserveState: true, replace: true });
  };
  const abrir = (a: LinhaAssociacao) =>
    router.reload({ data: { ver: a.id }, only: ['ficha'], onSuccess: () => setAberta('ficha') });

  return (
    <div data-screen-label="Associações">
      <div data-contract="cabecalho">
        <PageHeader
          title="Associações"
          subtitle={`Associações da rede. Mudança de diretoria sem ata nova em ${prazo_dias} dias aparece como atrasada.`}
          actions={pode_editar && pode_rede ? <Button onClick={() => setAberta('nova')}><Plus className="size-4" aria-hidden="true" /> Nova associação</Button> : undefined}
        />
      </div>
      <div className="flex flex-col gap-4 p-4">
        <div data-contract="filtros">
          <form onSubmit={buscar} className="flex max-w-md gap-2" role="search">
            <Input aria-label="Buscar associação" placeholder="Nome, sigla ou cidade" value={q} onChange={(e) => setQ(e.target.value)} />
            <Button type="submit" variant="outline"><Search className="size-4" aria-hidden="true" /> Buscar</Button>
          </form>
        </div>
        <div data-contract="lista">
          <Deferred data="associacoes" fallback={<Skeleton className="h-40 w-full" />}>
            <DataTable<LinhaAssociacao>
              columns={COLUNAS}
              data={associacoes ?? []}
              caption="Associações"
              rowKey={(a) => a.id}
              onRowClick={abrir}
              emptyMessage={filtros.q ? `Nenhuma associação com "${filtros.q}".` : 'Nenhuma associação ao seu alcance ainda.'}
            />
          </Deferred>
        </div>
      </div>
      {aberta === 'nova' && <FichaAssociacao ficha={null} podeEditar={pode_editar} podeRede={pode_rede} onClose={() => setAberta(null)} onCriada={() => setAberta('ficha')} />}
      {aberta === 'ficha' && ficha && <FichaAssociacao ficha={ficha} podeEditar={pode_editar} podeRede={pode_rede} onClose={() => setAberta(null)} />}
    </div>
  );
}

AssociacoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
