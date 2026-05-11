// @memcofre tela=/oficina-auto/ordens-servico/{id} module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Detalhe OS.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-show.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { Wrench, ArrowLeft, Edit } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';

interface ServiceOrder {
  id: number;
  status: string;
  entered_at: string | null;
  expected_completion: string | null;
  completed_at: string | null;
  delivered_at: string | null;
  mileage_at_service: number | null;
  transaction_id: number | null;
  notes: string | null;
  vehicle: {
    id: number;
    plate: string;
    secondary_plate: string | null;
    vehicle_type: string;
  } | null;
}

interface Props {
  order: ServiceOrder;
}

export default function ServiceOrdersShow({ order }: Props) {
  return (
    <AppShellV2>
      <Head title={`OS #${order.id} · Oficina Auto`} />
      <div className="px-4 py-6 max-w-4xl mx-auto">
        <PageHeader
          title={`OS #${order.id}`}
          subtitle={order.vehicle ? `${order.vehicle.plate} (${order.vehicle.vehicle_type})` : 'Sem veículo vinculado'}
          icon={<Wrench className="size-5" />}
          actions={
            <div className="flex gap-2">
              <Link href="/oficina-auto/ordens-servico">
                <Button variant="ghost">
                  <ArrowLeft className="size-4 mr-1" />
                  Voltar
                </Button>
              </Link>
              <Link href={`/oficina-auto/ordens-servico/${order.id}/edit`}>
                <Button>
                  <Edit className="size-4 mr-1" />
                  Editar
                </Button>
              </Link>
            </div>
          }
        />

        <div className="rounded-md border bg-card p-4">
          <dl className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <div>
              <dt className="text-muted-foreground">Status</dt>
              <dd className="font-medium">
                <span className="text-xs px-2 py-0.5 rounded bg-muted">{order.status}</span>
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">KM na entrada</dt>
              <dd className="font-medium tabular-nums">{order.mileage_at_service?.toLocaleString('pt-BR') ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Entrada</dt>
              <dd>{order.entered_at ? new Date(order.entered_at).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Previsão entrega</dt>
              <dd>{order.expected_completion ? new Date(order.expected_completion).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Conclusão</dt>
              <dd>{order.completed_at ? new Date(order.completed_at).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Entrega cliente</dt>
              <dd>{order.delivered_at ? new Date(order.delivered_at).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            {order.transaction_id && (
              <div className="col-span-2">
                <dt className="text-muted-foreground">Venda vinculada</dt>
                <dd className="font-mono">#{order.transaction_id}</dd>
              </div>
            )}
            {order.vehicle && (
              <div className="col-span-2">
                <dt className="text-muted-foreground">Veículo</dt>
                <dd>
                  <Link href={`/oficina-auto/veiculos/${order.vehicle.id}`} className="font-mono hover:underline">
                    {order.vehicle.plate}
                  </Link>
                  {order.vehicle.secondary_plate && <span className="text-muted-foreground"> + {order.vehicle.secondary_plate}</span>}
                </dd>
              </div>
            )}
          </dl>

          {order.notes && (
            <div className="mt-4 pt-4 border-t">
              <p className="text-sm font-medium mb-1">Observações</p>
              <p className="text-sm text-muted-foreground whitespace-pre-wrap">{order.notes}</p>
            </div>
          )}
        </div>
      </div>
    </AppShellV2>
  );
}
