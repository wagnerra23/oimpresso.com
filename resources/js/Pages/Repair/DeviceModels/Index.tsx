// @memcofre tela=/repair/device-models module=Repair
// Sprint 2.5 / MWART-0002 — port Device Models (Repair) Blade → Inertia/React.
// CRUD: lista models com device + brand + checklist; criar/editar via modal Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import { Plus, Smartphone, ListChecks } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import type { ReactNode } from 'react';

interface ModelRow {
  id: number;
  name: string;
  description: string | null;
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
        title="Modelos de Dispositivo (Repair)"
        subtitle="Cadastre modelos de aparelhos atendidos com checklists de reparo"
        actions={
          <Button asChild>
            <Link href={route('device-models.create')}>
              <Plus className="mr-2 h-4 w-4" />
              Novo modelo
            </Link>
          </Button>
        }
      />

      {models.length === 0 ? (
        <EmptyState
          title="Nenhum modelo cadastrado"
          description="Cadastre os modelos de dispositivos que sua oficina atende."
          icon={<Smartphone className="h-12 w-12 text-slate-400" />}
        />
      ) : (
        <div className="rounded-lg border bg-white">
          <table className="w-full">
            <thead className="bg-slate-50 text-left text-sm">
              <tr>
                <th className="px-4 py-3 font-medium">Modelo</th>
                <th className="px-4 py-3 font-medium">Marca</th>
                <th className="px-4 py-3 font-medium">Categoria</th>
                <th className="px-4 py-3 font-medium text-center">Checklist</th>
                <th className="px-4 py-3 font-medium w-24"></th>
              </tr>
            </thead>
            <tbody className="text-sm">
              {models.map((m) => (
                <tr key={m.id} className="border-t hover:bg-slate-50">
                  <td className="px-4 py-3">
                    <div className="font-medium">{m.name}</div>
                    {m.description && (
                      <div className="text-xs text-slate-500 truncate max-w-md">{m.description}</div>
                    )}
                  </td>
                  <td className="px-4 py-3">{m.brand_name ?? '—'}</td>
                  <td className="px-4 py-3">{m.device_name ?? '—'}</td>
                  <td className="px-4 py-3 text-center">
                    {m.has_checklist ? (
                      <Badge variant="secondary">
                        <ListChecks className="mr-1 h-3 w-3" />
                        Sim
                      </Badge>
                    ) : (
                      <span className="text-slate-400">—</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button variant="ghost" size="sm" asChild>
                      <Link href={route('device-models.edit', m.id)}>Editar</Link>
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
