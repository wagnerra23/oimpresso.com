// @memcofre tela=/oficina-auto/veiculos/{id} module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Detalhe veículo + histórico OS.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-show.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';
import VehicleStatusBadge, { type VehicleStatus } from './_components/VehicleStatusBadge';
import ServiceOrderStatusBadge from '../ServiceOrders/_components/ServiceOrderStatusBadge';

interface ServiceOrder {
  id: number;
  status: string;
  entered_at: string | null;
  notes: string | null;
}

interface Vehicle {
  id: number;
  plate: string;
  secondary_plate: string | null;
  chassis: string | null;
  secondary_chassis: string | null;
  vehicle_type: string;
  manufacture_year: number | null;
  model_year: number | null;
  renavam: string | null;
  engine: string | null;
  mileage_at_entry: number | null;
  fuel_type: string | null;
  color: string | null;
  current_status: VehicleStatus | null;
  notes: string | null;
  legacy_id: string | null;
  service_orders: ServiceOrder[];
}

interface Props {
  vehicle: Vehicle;
}

export default function VehiclesShow({ vehicle }: Props) {
  return (
    <AppShellV2>
      <Head title={`${vehicle.plate} · Oficina Auto`} />
      <div className="px-4 py-6 max-w-5xl mx-auto">
        <PageHeader
          title={vehicle.plate}
          description={vehicle.secondary_plate ? `+ ${vehicle.secondary_plate} (reboque)` : 'Veículo cadastrado'}
          icon="car"
          action={
            <div className="flex items-center gap-2">
              {vehicle.current_status && (
                <VehicleStatusBadge status={vehicle.current_status} />
              )}
              <Link href="/oficina-auto/veiculos">
                <Button variant="ghost">
                  <ArrowLeft className="size-4 mr-1" />
                  Voltar
                </Button>
              </Link>
              <Link href={`/oficina-auto/veiculos/${vehicle.id}/edit`}>
                <Button variant="ghost">
                  <Edit className="size-4 mr-1" />
                  Editar
                </Button>
              </Link>
              <Link href={`/oficina-auto/ordens-servico/create?vehicle_id=${vehicle.id}`}>
                <Button>
                  <Plus className="size-4 mr-1" />
                  Nova OS
                </Button>
              </Link>
            </div>
          }
        />

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div className="rounded-md border bg-card p-4">
            <h3 className="font-semibold mb-3">Dados do veículo</h3>
            <dl className="space-y-2 text-sm">
              <Row label="Tipo" value={vehicle.vehicle_type} />
              <Row label="Placa principal" value={vehicle.plate} mono />
              <Row label="Placa secundária" value={vehicle.secondary_plate} mono />
              <Row label="Chassi" value={vehicle.chassis} mono />
              <Row label="Chassi secundário" value={vehicle.secondary_chassis} mono />
              <Row label="Renavam" value={vehicle.renavam} mono />
              <Row label="Ano" value={vehicle.manufacture_year ? `${vehicle.manufacture_year}${vehicle.model_year && vehicle.model_year !== vehicle.manufacture_year ? `/${vehicle.model_year}` : ''}` : null} />
              <Row label="Cor" value={vehicle.color} />
              <Row label="Motor" value={vehicle.engine} />
              <Row label="Combustível" value={vehicle.fuel_type} />
              <Row label="KM entrada" value={vehicle.mileage_at_entry?.toLocaleString('pt-BR') ?? null} />
              {vehicle.legacy_id && <Row label="Legacy ID" value={vehicle.legacy_id} mono />}
            </dl>
            {vehicle.notes && (
              <div className="mt-4 pt-4 border-t">
                <p className="text-sm font-medium mb-1">Observações</p>
                <p className="text-sm text-muted-foreground whitespace-pre-wrap">{vehicle.notes}</p>
              </div>
            )}
          </div>

          <div className="rounded-md border bg-card p-4">
            <h3 className="font-semibold mb-3">Histórico de OS ({vehicle.service_orders?.length ?? 0})</h3>
            {!vehicle.service_orders || vehicle.service_orders.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nenhuma OS registrada para este veículo.</p>
            ) : (
              <ul className="space-y-2">
                {vehicle.service_orders.map((os) => (
                  <li key={os.id} className="flex items-center gap-2 text-sm border-b pb-2 last:border-0">
                    <Link href={`/oficina-auto/ordens-servico/${os.id}`} className="font-medium hover:underline">
                      OS #{os.id}
                    </Link>
                    <ServiceOrderStatusBadge status={os.status} />
                    {os.entered_at && (
                      <span className="ml-auto text-xs text-muted-foreground">
                        {new Date(os.entered_at).toLocaleDateString('pt-BR')}
                      </span>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      </div>
    </AppShellV2>
  );
}

function Row({ label, value, mono }: { label: string; value: string | number | null; mono?: boolean }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className={mono ? 'font-mono' : ''}>{value ?? '—'}</dd>
    </div>
  );
}
