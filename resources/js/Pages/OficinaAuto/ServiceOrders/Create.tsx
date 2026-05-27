// @memcofre tela=/oficina-auto/ordens-servico/create module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Form criação de OS em Sheet lateral.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-create.md
//
// 2026-05-26: refatorado de Page fullscreen pra Sheet 720px lateral (Wagner pedido).
// Consistente com ServiceOrderRichSheet padrão drawer OficinaAuto.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Wrench, Save } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';

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
  // Sheet abre por default (rota /create dedicada); fechar volta pra lista/producao
  const [open, setOpen] = useState(true);

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

  function handleClose() {
    setOpen(false);
    // Aguardar animação de fechamento antes de navegar (200ms shadcn default)
    setTimeout(() => {
      // Preferir referer pra UX (drawer abriu de Producao, volta pra Producao)
      const referer = document.referrer;
      if (referer && referer.includes('/oficina-auto/')) {
        router.visit(referer);
      } else {
        router.visit('/oficina-auto/ordens-servico');
      }
    }, 200);
  }

  return (
    <AppShellV2>
      <Head title="Nova OS · Oficina Auto" />
      <Sheet open={open} onOpenChange={(o) => !o && handleClose()}>
        <SheetContent
          side="right"
          className="w-full sm:max-w-[720px] overflow-y-auto"
        >
          <SheetHeader className="space-y-1 pb-3 border-b">
            <SheetTitle className="flex items-center gap-2 text-lg">
              <Wrench className="size-5" />
              Nova Ordem de Serviço
            </SheetTitle>
            <SheetDescription className="text-xs">
              V0 — vínculo OS↔Vehicle obrigatório, status livre
            </SheetDescription>
          </SheetHeader>

          <form onSubmit={handleSubmit} className="space-y-4 pt-4 px-1">
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
                    {v.secondary_plate ? ` + ${v.secondary_plate}` : ''}{' '}
                    ({v.vehicle_type})
                  </option>
                ))}
              </select>
              {errors.vehicle_id && (
                <p className="text-sm text-destructive mt-1">{errors.vehicle_id}</p>
              )}
              {vehicles.length === 0 && (
                <p className="text-sm text-muted-foreground mt-1">
                  Nenhum veículo cadastrado.{' '}
                  <Link href="/oficina-auto/veiculos/create" className="underline">
                    Cadastrar veículo
                  </Link>
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
                    <option key={k} value={k}>
                      {label}
                    </option>
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

            <div className="flex justify-end gap-2 pt-4 border-t sticky bottom-0 bg-background -mx-1 px-1 pb-2">
              <Button variant="outline" type="button" onClick={handleClose}>
                Cancelar
              </Button>
              <Button type="submit" disabled={processing || vehicles.length === 0}>
                <Save className="size-4 mr-1" />
                Criar OS
              </Button>
            </div>
          </form>
        </SheetContent>
      </Sheet>
    </AppShellV2>
  );
}
