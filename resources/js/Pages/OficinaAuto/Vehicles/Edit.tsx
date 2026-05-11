// @memcofre tela=/oficina-auto/veiculos/{id}/edit module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Edição de veículo.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-edit.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { Car, ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';

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
  notes: string | null;
}

interface Props {
  vehicle: Vehicle;
  vehicleTypes: Record<string, string>;
}

export default function VehiclesEdit({ vehicle, vehicleTypes }: Props) {
  const { data, setData, put, processing, errors } = useForm({
    plate: vehicle.plate,
    secondary_plate: vehicle.secondary_plate ?? '',
    chassis: vehicle.chassis ?? '',
    secondary_chassis: vehicle.secondary_chassis ?? '',
    vehicle_type: vehicle.vehicle_type,
    manufacture_year: vehicle.manufacture_year?.toString() ?? '',
    model_year: vehicle.model_year?.toString() ?? '',
    renavam: vehicle.renavam ?? '',
    engine: vehicle.engine ?? '',
    mileage_at_entry: vehicle.mileage_at_entry?.toString() ?? '',
    fuel_type: vehicle.fuel_type ?? '',
    color: vehicle.color ?? '',
    notes: vehicle.notes ?? '',
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    put(`/oficina-auto/veiculos/${vehicle.id}`);
  }

  return (
    <AppShellV2>
      <Head title={`Editar ${vehicle.plate} · Oficina Auto`} />
      <div className="px-4 py-6 max-w-3xl mx-auto">
        <PageHeader
          title={`Editar ${vehicle.plate}`}
          subtitle="Atualizar dados do veículo"
          icon={<Car className="size-5" />}
          actions={
            <Link href={`/oficina-auto/veiculos/${vehicle.id}`}>
              <Button variant="ghost">
                <ArrowLeft className="size-4 mr-1" />
                Voltar
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="plate">Placa principal *</Label>
              <Input id="plate" value={data.plate} onChange={(e) => setData('plate', e.target.value.toUpperCase())} maxLength={10} required />
              {errors.plate && <p className="text-sm text-destructive mt-1">{errors.plate}</p>}
            </div>
            <div>
              <Label htmlFor="secondary_plate">Placa secundária</Label>
              <Input id="secondary_plate" value={data.secondary_plate} onChange={(e) => setData('secondary_plate', e.target.value.toUpperCase())} maxLength={10} />
            </div>
            <div>
              <Label htmlFor="vehicle_type">Tipo *</Label>
              <select
                id="vehicle_type"
                value={data.vehicle_type}
                onChange={(e) => setData('vehicle_type', e.target.value)}
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              >
                {Object.entries(vehicleTypes).map(([k, label]) => (
                  <option key={k} value={k}>{label}</option>
                ))}
              </select>
            </div>
            <div>
              <Label htmlFor="color">Cor</Label>
              <Input id="color" value={data.color} onChange={(e) => setData('color', e.target.value)} maxLength={30} />
            </div>
          </div>

          <div>
            <Label htmlFor="notes">Observações</Label>
            <textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              rows={3}
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t">
            <Link href={`/oficina-auto/veiculos/${vehicle.id}`}>
              <Button variant="outline" type="button">Cancelar</Button>
            </Link>
            <Button type="submit" disabled={processing}>
              <Save className="size-4 mr-1" />
              Salvar alterações
            </Button>
          </div>
        </form>
      </div>
    </AppShellV2>
  );
}
