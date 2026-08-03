// governance/TestLanes — carimbado do PT-01 Lista por criar-tela.mjs (UI-0013).
// Herda o Padrão de Tela: NÃO reinvente a estrutura — preencha os {/* TODO */}.
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';

interface Row { id: number /* TODO: campos da linha */ }
interface Props { paginator: { data: Row[] } & Record<string, unknown> /* Inertia paginator */ }

export default function TestLanes({ paginator }: Props) {
  // TODO: defina as colunas reais da lista.
  const columns: ColumnDef<Row>[] = [
    { accessorKey: 'id', header: 'ID' },
  ];
  return (
    <AppShellV2>
      <PageHeader title="TestLanes" description="TODO: descrição da lista" />
      {/* TODO: filtros da lista (SellsDateFilter / busca / status) acima da tabela */}
      <DataTable columns={columns} data={paginator.data} pagination={paginator as never} />
    </AppShellV2>
  );
}
