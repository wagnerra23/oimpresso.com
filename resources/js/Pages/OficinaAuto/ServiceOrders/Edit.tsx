// @memcofre tela=/oficina-auto/ordens-servico/{id}/edit module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Edição de OS.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-edit.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { Wrench, ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';

interface ServiceOrder {
  id: number;
  vehicle_id: number;
  transaction_id: number | null;
  status: string;
  mileage_at_service: number | null;
  entered_at: string | null;
  expected_completion: string | null;
  completed_at: string | null;
  delivered_at: string | null;
  notes: string | null;
}

interface Vehicle {
  id: number;
  plate: string;
  secondary_plate: string | null;
  vehicle_type: string;
}

interface Props {
  order: ServiceOrder;
  vehicles: Vehicle[];
  statuses: Record<string, string>;
}

function toLocalInput(value: string | null): string {
  if (!value) return '';
  return new Date(value).toISOString().slice(0, 16);
}

export default function ServiceOrdersEdit({ order, vehicles, statuses }: Props) {
  const { data, setData, put, processing, errors } = useForm({
    vehicle_id: String(order.vehicle_id),
    transaction_id: order.transaction_id ? String(order.transaction_id) : '',
    mileage_at_service: order.mileage_at_service?.toString() ?? '',
    status: order.status,
    entered_at: toLocalInput(order.entered_at),
    expected_completion: toLocalInput(order.expected_completion),
    completed_at: toLocalInput(order.completed_at),
    delivered_at: toLocalInput(order.delivered_at),
    notes: order.notes ?? '',
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    put(`/oficina-auto/ordens-servico/${order.id}`);
  }

  return (
    <AppShellV2>
      <Head title={`Editar OS #${order.id} · Oficina Auto`} />
      <div className="px-4 py-6 max-w-3xl mx-auto">
        <PageHeader
          title={`Editar OS #${order.id}`}
          subtitle="Atualizar status, datas, observações"
          icon={<Wrench className="size-5" />}
          actions={
            <Link href={`/oficina-auto/ordens-servico/${order.id}`}>
              <Button variant="ghost">
                <ArrowLeft className="size-4 mr-1" />
                Voltar
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <Label htmlFor="vehicle_id">Veículo *</Label>
            <select
              id="vehicle_id"
              value={data.vehicle_id}
              onChange={(e) => setData('vehicle_id', e.target.value)}
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              required
            >
              {vehicles.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.plate}{v.secondary_plate ? ` + ${v.secondary_plate}` : ''} ({v.vehicle_type})
                </option>
              ))}
            </select>
            {errors.vehicle_id && <p className="text-sm text-destructive mt-1">{errors.vehicle_id}</p>}
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="status">Status *</Label>
              <select
                id="status"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              >
                {Object.entries(statuses).map(([k, label]) => (
                  <option key={k} value={k}>{label}</option>
                ))}
              </select>
            </div>
            <div>
              <Label htmlFor="mileage_at_service">KM na entrada</Label>
              <Input id="mileage_at_service" type="number" value={data.mileage_at_service} onChange={(e) => setData('mileage_at_service', e.target.value)} min={0} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="entered_at">Entrada</Label>
              <Input id="entered_at" type="datetime-local" value={data.entered_at} onChange={(e) => setData('entered_at', e.target.value)} />
            </div>
            <div>
              <Label htmlFor="expected_completion">Previsão</Label>
              <Input id="expected_completion" type="datetime-local" value={data.expected_completion} onChange={(e) => setData('expected_completion', e.target.value)} />
            </div>
            <div>
              <Label htmlFor="completed_at">Concluída em</Label>
              <Input id="completed_at" type="datetime-local" value={data.completed_at} onChange={(e) => setData('completed_at', e.target.value)} />
            </div>
            <div>
              <Label htmlFor="delivered_at">Entregue em</Label>
              <Input id="delivered_at" type="datetime-local" value={data.delivered_at} onChange={(e) => setData('delivered_at', e.target.value)} />
            </div>
          </div>

          <div>
            <Label htmlFor="notes">Observações</Label>
            <textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              rows={4}
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t">
            <Link href={`/oficina-auto/ordens-servico/${order.id}`}>
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
