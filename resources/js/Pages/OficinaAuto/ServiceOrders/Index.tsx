// @memcofre tela=/oficina-auto/ordens-servico module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Lista de OS.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-index.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { Wrench, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';

interface ServiceOrder {
  id: number;
  status: string;
  entered_at: string | null;
  expected_completion: string | null;
  vehicle: {
    id: number;
    plate: string;
    vehicle_type: string;
  } | null;
  mileage_at_service: number | null;
  created_at: string;
}

interface Props {
  orders: ServiceOrder[];
  filters: { status: string };
}

const STATUS_LABEL: Record<string, string> = {
  aberta: 'Aberta',
  orcamento: 'Em Orçamento',
  aprovada: 'Aprovada',
  em_servico: 'Em Serviço',
  em_producao: 'Em Produção',
  concluida: 'Concluída',
  entregue: 'Entregue',
  cancelada: 'Cancelada',
};

export default function ServiceOrdersIndex({ orders, filters }: Props) {
  function handleStatusChange(value: string) {
    router.get('/oficina-auto/ordens-servico', value ? { status: value } : {}, { preserveState: true, preserveScroll: true });
  }

  return (
    <AppShellV2>
      <Head title="Ordens de Serviço · Oficina Auto" />
      <div className="px-4 py-6 max-w-7xl mx-auto">
        <PageHeader
          title="Ordens de Serviço"
          subtitle="OS em curso e concluídas (status livre V0; FSM canônica em US-OFICINA-003)"
          icon={<Wrench className="size-5" />}
          actions={
            <Link href="/oficina-auto/ordens-servico/create">
              <Button>
                <Plus className="size-4 mr-1" />
                Nova OS
              </Button>
            </Link>
          }
        />

        <div className="mb-4">
          <select
            value={filters.status ?? ''}
            onChange={(e) => handleStatusChange(e.target.value)}
            className="rounded-md border bg-background px-3 py-2 text-sm max-w-xs"
          >
            <option value="">Todos os status</option>
            {Object.entries(STATUS_LABEL).map(([k, label]) => (
              <option key={k} value={k}>{label}</option>
            ))}
          </select>
        </div>

        {orders.length === 0 ? (
          <EmptyState
            icon={<Wrench className="size-12" />}
            title="Nenhuma OS encontrada"
            description="Crie a primeira ordem de serviço para acompanhar o fluxo da oficina."
            action={
              <Link href="/oficina-auto/ordens-servico/create">
                <Button>
                  <Plus className="size-4 mr-1" />
                  Criar OS
                </Button>
              </Link>
            }
          />
        ) : (
          <div className="rounded-md border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b bg-muted/50">
                <tr>
                  <th className="px-3 py-2 text-left">OS</th>
                  <th className="px-3 py-2 text-left">Veículo</th>
                  <th className="px-3 py-2 text-left">Status</th>
                  <th className="px-3 py-2 text-left">Entrada</th>
                  <th className="px-3 py-2 text-left">Previsão</th>
                  <th className="px-3 py-2 text-right">KM</th>
                  <th className="px-3 py-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {orders.map((o) => (
                  <tr key={o.id} className="border-b last:border-0 hover:bg-muted/30">
                    <td className="px-3 py-2 font-mono font-semibold">#{o.id}</td>
                    <td className="px-3 py-2 font-mono">{o.vehicle?.plate ?? '—'}</td>
                    <td className="px-3 py-2">
                      <span className="text-xs px-2 py-0.5 rounded bg-muted">{STATUS_LABEL[o.status] ?? o.status}</span>
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">
                      {o.entered_at ? new Date(o.entered_at).toLocaleDateString('pt-BR') : '—'}
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">
                      {o.expected_completion ? new Date(o.expected_completion).toLocaleDateString('pt-BR') : '—'}
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums">
                      {o.mileage_at_service ? o.mileage_at_service.toLocaleString('pt-BR') : '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <Link href={`/oficina-auto/ordens-servico/${o.id}`}>
                        <Button variant="ghost" size="sm">Ver</Button>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </AppShellV2>
  );
}
