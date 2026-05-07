// @memcofre tela=/repair/device-models module=Repair
// Sprint 2.5 / MWART-0002 — port Device Models (Repair) Blade → Inertia/React.
// CRUD: lista models com device + brand + checklist; criar/editar via modal Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Icon } from '@/Components/Icon';
import type { ReactNode } from 'react';

interface ModelRow {
  id: number;
  name: string;
  device_name: string | null;
  brand_name: string | null;
  has_checklist: boolean;
}

interface PageProps {
  models: ModelRow[];
}

export default function DeviceModelsIndex({ models }: PageProps) {
  return (
    <div className="container mx-auto p-4">
      <PageHeader
        icon="smartphone"
        title="Modelos de Dispositivo (Repair)"
        description="Cadastre modelos de aparelhos atendidos com checklists de reparo"
        action={
          <Button asChild>
            <Link href="/repair/device-models/create">
              <Icon name="plus" className="mr-2 h-4 w-4" />
              Novo modelo
            </Link>
          </Button>
        }
      />

      {models.length === 0 ? (
        <EmptyState
          icon="smartphone"
          title="Nenhum modelo cadastrado"
          description="Cadastre os modelos de dispositivos que sua oficina atende."
        />
      ) : (
        <div className="rounded-lg border border-border bg-card overflow-hidden">
          <table className="w-full">
            <thead className="bg-muted/50 text-left text-sm">
              <tr>
                <th className="px-4 py-3 font-medium text-foreground">Modelo</th>
                <th className="px-4 py-3 font-medium text-foreground">Marca</th>
                <th className="px-4 py-3 font-medium text-foreground">Categoria</th>
                <th className="px-4 py-3 font-medium text-center text-foreground">Checklist</th>
                <th className="px-4 py-3 font-medium w-24"></th>
              </tr>
            </thead>
            <tbody className="text-sm">
              {models.map((m) => (
                <tr
                  key={m.id}
                  className="border-t border-border hover:bg-accent/50 transition-colors focus-within:bg-accent/50"
                >
                  <td className="px-4 py-3">
                    <div className="font-medium text-foreground">{m.name}</div>
                  </td>
                  <td className="px-4 py-3 text-foreground">{m.brand_name ?? <span className="text-muted-foreground">—</span>}</td>
                  <td className="px-4 py-3 text-foreground">{m.device_name ?? <span className="text-muted-foreground">—</span>}</td>
                  <td className="px-4 py-3 text-center">
                    {m.has_checklist ? (
                      <Badge variant="secondary" className="gap-1">
                        <Icon name="list-checks" className="h-3 w-3" />
                        Sim
                      </Badge>
                    ) : (
                      <span className="text-muted-foreground" aria-label="Sem checklist">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={`/repair/device-models/${m.id}/edit`}>Editar</Link>
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

DeviceModelsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
