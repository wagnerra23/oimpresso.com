// @memcofre tela=/oficina-auto/veiculos/create module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Form de criação de veículo.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-create.md
// Charter: Create.charter.md (paridade de campos + erros em todos os campos com Edit).

import * as React from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';

interface Props {
  vehicleTypes: Record<string, string>;
}

// Ordem de foco/scroll quando o servidor retorna erros de validação.
const FIELD_ORDER = [
  'plate',
  'secondary_plate',
  'chassis',
  'secondary_chassis',
  'vehicle_type',
  'manufacture_year',
  'model_year',
  'renavam',
  'engine',
  'fuel_type',
  'color',
  'mileage_at_entry',
  'notes',
] as const;

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

  // Foca + rola até o primeiro campo inválido quando o servidor responde com erros.
  React.useEffect(() => {
    const firstInvalid = FIELD_ORDER.find((field) => errors[field]);
    if (!firstInvalid) return;
    const el = document.getElementById(firstInvalid);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el.focus({ preventScroll: true });
    }
  }, [errors]);

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
          description="Cadastro V0 — campos completos (CRLV/FIPE em Sprint 5+)"
          icon="car"
          action={
            <Link href="/oficina-auto/veiculos">
              <Button variant="ghost">
                <ArrowLeft className="size-4 mr-1" />
                Voltar
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit} className="space-y-4 pt-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="plate">Placa principal *</Label>
              <Input
                id="plate"
                value={data.plate}
                onChange={(e) => setData('plate', e.target.value.toUpperCase())}
                maxLength={10}
                required
                aria-invalid={!!errors.plate}
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
                aria-invalid={!!errors.secondary_plate}
              />
              {errors.secondary_plate && (
                <p className="text-sm text-destructive mt-1">{errors.secondary_plate}</p>
              )}
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
                aria-invalid={!!errors.chassis}
              />
              {errors.chassis && <p className="text-sm text-destructive mt-1">{errors.chassis}</p>}
            </div>
            <div>
              <Label htmlFor="secondary_chassis">Chassi secundário</Label>
              <Input
                id="secondary_chassis"
                value={data.secondary_chassis}
                onChange={(e) => setData('secondary_chassis', e.target.value)}
                maxLength={30}
                aria-invalid={!!errors.secondary_chassis}
              />
              {errors.secondary_chassis && (
                <p className="text-sm text-destructive mt-1">{errors.secondary_chassis}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="vehicle_type">Tipo *</Label>
              <Select value={data.vehicle_type} onValueChange={(v) => setData('vehicle_type', v)}>
                <SelectTrigger id="vehicle_type" aria-invalid={!!errors.vehicle_type}>
                  <SelectValue placeholder="Selecione o tipo" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(vehicleTypes).map(([k, label]) => (
                    <SelectItem key={k} value={k}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.vehicle_type && (
                <p className="text-sm text-destructive mt-1">{errors.vehicle_type}</p>
              )}
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
                aria-invalid={!!errors.manufacture_year}
              />
              {errors.manufacture_year && (
                <p className="text-sm text-destructive mt-1">{errors.manufacture_year}</p>
              )}
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
                aria-invalid={!!errors.model_year}
              />
              {errors.model_year && (
                <p className="text-sm text-destructive mt-1">{errors.model_year}</p>
              )}
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
                aria-invalid={!!errors.renavam}
              />
              {errors.renavam && <p className="text-sm text-destructive mt-1">{errors.renavam}</p>}
            </div>
            <div>
              <Label htmlFor="engine">Motor</Label>
              <Input
                id="engine"
                value={data.engine}
                onChange={(e) => setData('engine', e.target.value)}
                maxLength={50}
                aria-invalid={!!errors.engine}
              />
              {errors.engine && <p className="text-sm text-destructive mt-1">{errors.engine}</p>}
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
                aria-invalid={!!errors.fuel_type}
              />
              {errors.fuel_type && (
                <p className="text-sm text-destructive mt-1">{errors.fuel_type}</p>
              )}
            </div>
            <div>
              <Label htmlFor="color">Cor</Label>
              <Input
                id="color"
                value={data.color}
                onChange={(e) => setData('color', e.target.value)}
                maxLength={30}
                aria-invalid={!!errors.color}
              />
              {errors.color && <p className="text-sm text-destructive mt-1">{errors.color}</p>}
            </div>
            <div>
              <Label htmlFor="mileage_at_entry">KM entrada</Label>
              <Input
                id="mileage_at_entry"
                type="number"
                value={data.mileage_at_entry}
                onChange={(e) => setData('mileage_at_entry', e.target.value)}
                min={0}
                aria-invalid={!!errors.mileage_at_entry}
              />
              {errors.mileage_at_entry && (
                <p className="text-sm text-destructive mt-1">{errors.mileage_at_entry}</p>
              )}
            </div>
          </div>

          <div>
            <Label htmlFor="notes">Observações</Label>
            <Textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              rows={3}
              aria-invalid={!!errors.notes}
            />
            {errors.notes && <p className="text-sm text-destructive mt-1">{errors.notes}</p>}
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
