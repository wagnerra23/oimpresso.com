// @memcofre tela=/oficina-auto/veiculos/create module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Form de criação de veículo.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-create.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { Car, ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';

interface Props {
  vehicleTypes: Record<string, string>;
}

export default function VehiclesCreate({ vehicleTypes }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    plate: '',
    secondary_plate: '',
    chassis: '',
    secondary_chassis: '',
    vehicle_type: 'automovel',
    manufacture_year: '',
    model_year: '',
    renavam: '',
    engine: '',
    mileage_at_entry: '',
    fuel_type: '',
    color: '',
    notes: '',
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    post('/oficina-auto/veiculos');
  }

  return (
    <AppShellV2>
      <Head title="Novo veículo · Oficina Auto" />
      <div className="px-4 py-6 max-w-3xl mx-auto">
        <PageHeader
          title="Novo veículo"
          subtitle="Cadastro V0 — campos completos (CRLV/FIPE em Sprint 5+)"
          icon={<Car className="size-5" />}
          actions={
            <Link href="/oficina-auto/veiculos">
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
              <Input
                id="plate"
                value={data.plate}
                onChange={(e) => setData('plate', e.target.value.toUpperCase())}
                maxLength={10}
                required
              />
              {errors.plate && <p className="text-sm text-destructive mt-1">{errors.plate}</p>}
            </div>
            <div>
              <Label htmlFor="secondary_plate">Placa secundária (cavalo+reboque)</Label>
              <Input
                id="secondary_plate"
                value={data.secondary_plate}
                onChange={(e) => setData('secondary_plate', e.target.value.toUpperCase())}
                maxLength={10}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="chassis">Chassi</Label>
              <Input
                id="chassis"
                value={data.chassis}
                onChange={(e) => setData('chassis', e.target.value)}
                maxLength={30}
              />
            </div>
            <div>
              <Label htmlFor="secondary_chassis">Chassi secundário</Label>
              <Input
                id="secondary_chassis"
                value={data.secondary_chassis}
                onChange={(e) => setData('secondary_chassis', e.target.value)}
                maxLength={30}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
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
              <Label htmlFor="manufacture_year">Ano fabricação</Label>
              <Input
                id="manufacture_year"
                type="number"
                value={data.manufacture_year}
                onChange={(e) => setData('manufacture_year', e.target.value)}
                min={1900}
                max={2100}
              />
            </div>
            <div>
              <Label htmlFor="model_year">Ano modelo</Label>
              <Input
                id="model_year"
                type="number"
                value={data.model_year}
                onChange={(e) => setData('model_year', e.target.value)}
                min={1900}
                max={2100}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="renavam">Renavam</Label>
              <Input
                id="renavam"
                value={data.renavam}
                onChange={(e) => setData('renavam', e.target.value)}
                maxLength={11}
              />
            </div>
            <div>
              <Label htmlFor="engine">Motor</Label>
              <Input
                id="engine"
                value={data.engine}
                onChange={(e) => setData('engine', e.target.value)}
                maxLength={50}
              />
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="fuel_type">Combustível</Label>
              <Input
                id="fuel_type"
                value={data.fuel_type}
                onChange={(e) => setData('fuel_type', e.target.value)}
                maxLength={30}
              />
            </div>
            <div>
              <Label htmlFor="color">Cor</Label>
              <Input
                id="color"
                value={data.color}
                onChange={(e) => setData('color', e.target.value)}
                maxLength={30}
              />
            </div>
            <div>
              <Label htmlFor="mileage_at_entry">KM entrada</Label>
              <Input
                id="mileage_at_entry"
                type="number"
                value={data.mileage_at_entry}
                onChange={(e) => setData('mileage_at_entry', e.target.value)}
                min={0}
              />
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
            <Link href="/oficina-auto/veiculos">
              <Button variant="outline" type="button">Cancelar</Button>
            </Link>
            <Button type="submit" disabled={processing}>
              <Save className="size-4 mr-1" />
              Salvar veículo
            </Button>
          </div>
        </form>
      </div>
    </AppShellV2>
  );
}
