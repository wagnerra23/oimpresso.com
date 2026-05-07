// @memcofre tela=/repair/status module=Repair
// Sprint 2.5 / MWART-0002 — port da tela de Status (Repair) Blade → Inertia/React.
// CRUD simples: lista status com cor + sort_order + flag completed; criar/editar via modal Blade
// (mantém compat com edit/create existentes — 1:1 paridade visual).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import { Plus, CheckCircle2 } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import type { ReactNode } from 'react';

interface StatusRow {
  id: number;
  name: string;
  color: string;
  sort_order: number;
  is_completed_status: number;
}

interface PageProps {
  statuses: StatusRow[];
}

export default function StatusIndex({ statuses }: PageProps) {
  return (
    <div className="container mx-auto p-4">
      <PageHeader
        title="Status de OS (Repair)"
        subtitle="Configure os status que ordens de serviço podem assumir"
        actions={
          <Button asChild>
            <Link href={route('status.create')}>
              <Plus className="mr-2 h-4 w-4" />
              Novo status
            </Link>
          </Button>
        }
      />

      {statuses.length === 0 ? (
        <EmptyState
          title="Nenhum status configurado"
          description="Crie pelo menos 1 status pra usar no fluxo de OS."
        />
      ) : (
        <div className="rounded-lg border bg-white">
          <table className="w-full">
            <thead className="bg-slate-50 text-left text-sm">
              <tr>
                <th className="px-4 py-3 font-medium">Nome</th>
                <th className="px-4 py-3 font-medium">Cor</th>
                <th className="px-4 py-3 font-medium text-center">Ordem</th>
                <th className="px-4 py-3 font-medium text-center">Concluído?</th>
                <th className="px-4 py-3 font-medium w-24"></th>
              </tr>
            </thead>
            <tbody className="text-sm">
              {statuses.map((s) => (
                <tr key={s.id} className="border-t hover:bg-slate-50">
                  <td className="px-4 py-3">{s.name}</td>
                  <td className="px-4 py-3">
                    <span
                      className="inline-block h-4 w-4 rounded-full mr-2 align-middle"
                      style={{ backgroundColor: s.color }}
                    />
                    <span className="font-mono text-xs text-slate-600">{s.color}</span>
                  </td>
                  <td className="px-4 py-3 text-center">{s.sort_order}</td>
                  <td className="px-4 py-3 text-center">
                    {s.is_completed_status === 1 && (
                      <CheckCircle2 className="inline h-4 w-4 text-green-600" />
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={route('status.edit', s.id)}>Editar</Link>
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

StatusIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
