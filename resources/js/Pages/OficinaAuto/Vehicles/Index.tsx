// @memcofre tela=/oficina-auto/veiculos module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Lista de veículos.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-index.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Car, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';

interface Vehicle {
  id: number;
  plate: string;
  secondary_plate: string | null;
  chassis: string | null;
  vehicle_type: string;
  manufacture_year: number | null;
  model_year: number | null;
  color: string | null;
  mileage_at_entry: number | null;
  contact_id: number | null;
  created_at: string;
}

interface Props {
  vehicles: Vehicle[];
  filters: { q: string };
}

const VEHICLE_TYPE_LABEL: Record<string, string> = {
  caminhao: 'Caminhão',
  cavalo: 'Cavalo',
  semi_reboque: 'Semi-reboque',
  cacamba_estacionaria: 'Caçamba',
  automovel: 'Automóvel',
  motocicleta: 'Motocicleta',
  outro: 'Outro',
};

export default function VehiclesIndex({ vehicles, filters }: Props) {
  const [q, setQ] = useState(filters.q ?? '');

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    router.get('/oficina-auto/veiculos', { q }, { preserveState: true, preserveScroll: true });
  }

  return (
    <AppShellV2>
      <Head title="Veículos · Oficina Auto" />
      <div className="px-4 py-6 max-w-7xl mx-auto">
        <PageHeader
          title="Veículos"
          subtitle="Cadastro de veículos da oficina (multi-placa para cavalo+reboque)"
          icon={<Car className="size-5" />}
          actions={
            <Link href="/oficina-auto/veiculos/create">
              <Button>
                <Plus className="size-4 mr-1" />
                Novo veículo
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit} className="flex gap-2 mb-4">
          <Input
            placeholder="Buscar por placa ou chassi…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            className="max-w-sm"
          />
          <Button type="submit" variant="outline">
            <Search className="size-4 mr-1" />
            Buscar
          </Button>
        </form>

        {vehicles.length === 0 ? (
          <EmptyState
            icon={<Car className="size-12" />}
            title="Nenhum veículo cadastrado"
            description="Cadastre o primeiro veículo para começar a registrar ordens de serviço."
            action={
              <Link href="/oficina-auto/veiculos/create">
                <Button>
                  <Plus className="size-4 mr-1" />
                  Cadastrar veículo
                </Button>
              </Link>
            }
          />
        ) : (
          <div className="rounded-md border bg-card">
            <table className="w-full text-sm">
              <thead className="border-b bg-muted/50">
                <tr>
                  <th className="px-3 py-2 text-left">Placa</th>
                  <th className="px-3 py-2 text-left">Placa 2</th>
                  <th className="px-3 py-2 text-left">Tipo</th>
                  <th className="px-3 py-2 text-left">Ano</th>
                  <th className="px-3 py-2 text-left">Cor</th>
                  <th className="px-3 py-2 text-right">KM entrada</th>
                  <th className="px-3 py-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {vehicles.map((v) => (
                  <tr key={v.id} className="border-b last:border-0 hover:bg-muted/30">
                    <td className="px-3 py-2 font-mono font-semibold">{v.plate}</td>
                    <td className="px-3 py-2 font-mono text-muted-foreground">{v.secondary_plate ?? '—'}</td>
                    <td className="px-3 py-2">{VEHICLE_TYPE_LABEL[v.vehicle_type] ?? v.vehicle_type}</td>
                    <td className="px-3 py-2 text-muted-foreground">
                      {v.manufacture_year ?? '—'}
                      {v.model_year && v.model_year !== v.manufacture_year ? `/${v.model_year}` : ''}
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">{v.color ?? '—'}</td>
                    <td className="px-3 py-2 text-right tabular-nums">
                      {v.mileage_at_entry ? v.mileage_at_entry.toLocaleString('pt-BR') : '—'}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <Link href={`/oficina-auto/veiculos/${v.id}`}>
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
