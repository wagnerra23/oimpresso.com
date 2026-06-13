// @memcofre tela=/repair/status module=Repair
// Sprint 2.5 / MWART-0002 — port da tela de Status (Repair) Blade → Inertia/React.
// CRUD simples: lista status com cor + sort_order + flag completed; criar/editar via modal Blade
// (mantém compat com edit/create existentes — 1:1 paridade visual).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Icon } from '@/Components/Icon';
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
        icon="flag"
        title="Status de OS (Repair)"
        description="Configure os status que ordens de serviço podem assumir"
        action={
          <Button asChild>
            <Link href="/repair/status/create">
              <Icon name="plus" className="mr-2 h-4 w-4" />
              Novo status
            </Link>
          </Button>
        }
      />

      {statuses.length === 0 ? (
        <EmptyState
          icon="flag"
          title="Nenhum status configurado"
          description="Crie pelo menos 1 status pra usar no fluxo de OS."
        />
      ) : (
        <div className="rounded-lg border border-border bg-card overflow-hidden">
          <table className="w-full">
            <thead className="bg-muted/50 text-left text-sm">
              <tr>
                <th className="px-4 py-3 font-medium text-foreground">Nome</th>
                <th className="px-4 py-3 font-medium text-foreground">Cor</th>
                <th className="px-4 py-3 font-medium text-center text-foreground">Ordem</th>
                <th className="px-4 py-3 font-medium text-center text-foreground">Concluído?</th>
                <th className="px-4 py-3 font-medium w-24"></th>
              </tr>
            </thead>
            <tbody className="text-sm">
              {statuses.map((s) => (
                <tr
                  key={s.id}
                  className="border-t border-border hover:bg-accent/50 transition-colors focus-within:bg-accent/50"
                >
                  <td className="px-4 py-3 text-foreground">{s.name}</td>
                  <td className="px-4 py-3">
                    <span
                      className="inline-block h-4 w-4 rounded-full mr-2 align-middle border border-border/60"
                      style={{ backgroundColor: s.color }}
                      aria-label={`Cor ${s.color}`}
                    />
                    <span className="font-mono text-xs text-muted-foreground">{s.color}</span>
                  </td>
                  <td className="px-4 py-3 text-center text-foreground tabular-nums">{s.sort_order}</td>
                  <td className="px-4 py-3 text-center">
                    {s.is_completed_status === 1 ? (
                      <Icon
                        name="circle-check"
                        className="inline h-4 w-4 text-success-fg"
                        aria-label="Status de conclusão"
                      />
                    ) : (
                      <span className="text-muted-foreground" aria-label="Status intermediário">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/repair/status/${s.id}/edit`}>Editar</Link>
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
