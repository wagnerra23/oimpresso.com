// @memcofre tela=/oficina-auto/ordens-servico/create module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Form criação de OS.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-create.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { Wrench, ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';

interface Vehicle {
  id: number;
  plate: string;
  secondary_plate: string | null;
  vehicle_type: string;
}

interface Props {
  vehicles: Vehicle[];
  statuses: Record<string, string>;
}

export default function ServiceOrdersCreate({ vehicles, statuses }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    vehicle_id: '',
    transaction_id: '',
    mileage_at_service: '',
    status: 'aberta',
    entered_at: '',
    expected_completion: '',
    notes: '',
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    post('/oficina-auto/ordens-servico');
  }

  return (
    <AppShellV2>
      <Head title="Nova OS · Oficina Auto" />
      <div className="px-4 py-6 max-w-3xl mx-auto">
        <PageHeader
          title="Nova Ordem de Serviço"
          subtitle="V0 — vínculo OS↔Vehicle obrigatório, status livre"
          icon={<Wrench className="size-5" />}
          actions={
            <Link href="/oficina-auto/ordens-servico">
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
              <option value="">Selecione um veículo…</option>
              {vehicles.map((v) => (
                <option key={v.id} value={v.id}>
                  {v.plate}
                  {v.secondary_plate ? ` + ${v.secondary_plate}` : ''}
                  {' '}({v.vehicle_type})
                </option>
              ))}
            </select>
            {errors.vehicle_id && <p className="text-sm text-destructive mt-1">{errors.vehicle_id}</p>}
            {vehicles.length === 0 && (
              <p className="text-sm text-muted-foreground mt-1">
                Nenhum veículo cadastrado. <Link href="/oficina-auto/veiculos/create" className="underline">Cadastrar veículo</Link>
              </p>
            )}
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
              <Input
                id="mileage_at_service"
                type="number"
                value={data.mileage_at_service}
                onChange={(e) => setData('mileage_at_service', e.target.value)}
                min={0}
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="entered_at">Data entrada</Label>
              <Input
                id="entered_at"
                type="datetime-local"
                value={data.entered_at}
                onChange={(e) => setData('entered_at', e.target.value)}
              />
            </div>
            <div>
              <Label htmlFor="expected_completion">Previsão entrega</Label>
              <Input
                id="expected_completion"
                type="datetime-local"
                value={data.expected_completion}
                onChange={(e) => setData('expected_completion', e.target.value)}
              />
            </div>
          </div>

          <div>
            <Label htmlFor="notes">Defeito / Observações</Label>
            <textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              rows={4}
              className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              placeholder="Descrição do serviço solicitado, defeitos relatados, observações…"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t">
            <Link href="/oficina-auto/ordens-servico">
              <Button variant="outline" type="button">Cancelar</Button>
            </Link>
            <Button type="submit" disabled={processing || vehicles.length === 0}>
              <Save className="size-4 mr-1" />
              Criar OS
            </Button>
          </div>
        </form>
      </div>
    </AppShellV2>
  );
}
