import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { Input } from '@/Components/ui/input';

interface JobSheetRow {
  id: number;
  job_sheet_no: string | null;
  contact_name: string | null;
  device: string | null;
  status_label: string | null;
  created_at: string | null;
}

interface Props {
  job_sheets?: JobSheetRow[];
  counts?: Record<string, number>;
}

export default function JobSheetIndex({ job_sheets, counts }: Props) {
  const rows = job_sheets ?? [];
  const [q, setQ] = useState('');

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (!term) return rows;
    return rows.filter(
      (r) =>
        (r.job_sheet_no ?? '').toLowerCase().includes(term) ||
        (r.contact_name ?? '').toLowerCase().includes(term) ||
        (r.device ?? '').toLowerCase().includes(term) ||
        (r.status_label ?? '').toLowerCase().includes(term),
    );
  }, [rows, q]);

  const total = counts?.total ?? rows.length;
  const abertas = counts?.open ?? counts?.abertas;
  const concluidas = counts?.done ?? counts?.concluidas;

  return (
    <AppShellV2>
      <Head title="Ordens de serviço — Reparo" />
      <PageHeader
        icon="wrench"
        title="Ordens de serviço"
        description="Acompanhe as OS de reparo do balcão."
      />

      <div className="mx-auto max-w-6xl space-y-5 px-4 py-6">
        <KpiGrid cols={3}>
          <KpiCard label="Total" value={total} icon="clipboard-list" size="compact" />
          {abertas != null && <KpiCard label="Abertas" value={abertas} icon="clock" tone="warning" size="compact" />}
          {concluidas != null && <KpiCard label="Concluídas" value={concluidas} icon="check-circle-2" tone="success" size="compact" />}
        </KpiGrid>

        {rows.length > 0 && (
          <Input
            type="search"
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Buscar por OS, cliente, aparelho ou status…"
            aria-label="Buscar ordens de serviço"
            className="max-w-md"
          />
        )}

        {rows.length === 0 ? (
          <EmptyState
            icon="wrench"
            title="Nenhuma ordem de serviço"
            description="As OS de reparo criadas aparecerão aqui."
          />
        ) : filtered.length === 0 ? (
          <EmptyState
            icon="search"
            variant="search"
            title="Nada encontrado"
            description={`Nenhuma OS para “${q}”.`}
          />
        ) : (
          <div className="overflow-hidden rounded-xl border border-border">
            <table className="w-full text-sm">
              <thead className="bg-muted/50 text-left text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="px-4 py-3">OS</th>
                  <th className="px-4 py-3">Cliente</th>
                  <th className="px-4 py-3">Aparelho</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Criada</th>
                </tr>
              </thead>
              <tbody>
                {filtered.map((r) => (
                  <tr
                    key={r.id}
                    onClick={() => router.visit(`/repair/job-sheet/${r.id}`)}
                    className="cursor-pointer border-t border-border hover:bg-muted/30"
                  >
                    <td className="px-4 py-3 font-mono text-xs">
                      <Link href={`/repair/job-sheet/${r.id}`} className="text-primary hover:underline">
                        {r.job_sheet_no ?? `#${r.id}`}
                      </Link>
                    </td>
                    <td className="px-4 py-3">{r.contact_name ?? '—'}</td>
                    <td className="px-4 py-3">{r.device ?? '—'}</td>
                    <td className="px-4 py-3">{r.status_label ?? '—'}</td>
                    <td className="px-4 py-3 text-muted-foreground">{r.created_at ?? '—'}</td>
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
