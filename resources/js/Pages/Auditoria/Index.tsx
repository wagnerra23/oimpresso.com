// Pagina /auditoria — listagem de activity_log com filtros + drill-down detail.
// US-AUDIT-009 (Sprint F3) per ADR 0127.
//
// Charter: ./Index.charter.md
// Mission: dar visibilidade de TODA alteracao em registros de negocio com
// filtros rapidos. Distinguir IA vs humano em 1 clique.

import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent } from '@/Components/ui/card';

interface ActivityRow {
  id: number;
  log_name: string;
  description: string;
  subject_type: string | null;
  subject_id: number | null;
  causer_id: number | null;
  causer_kind: 'user' | 'agent' | 'system' | 'api';
  agent_run_id: number | null;
  event: string | null;
  reverted_at: string | null;
  business_id: number;
  created_at: string;
}

interface PageProps {
  activities: {
    data: ActivityRow[];
    current_page: number;
    last_page: number;
    total: number;
  };
  filters: {
    causer_kind?: string;
    subject_type?: string;
    event?: string;
  };
}

const CAUSER_BADGE: Record<string, { label: string; cls: string }> = {
  user:   { label: 'Usuário',  cls: 'bg-blue-100 text-blue-800' },
  agent:  { label: 'IA',       cls: 'bg-purple-100 text-purple-800' },
  system: { label: 'Sistema',  cls: 'bg-gray-100 text-gray-800' },
  api:    { label: 'API',      cls: 'bg-amber-100 text-amber-800' },
};

const EVENT_LABEL: Record<string, string> = {
  created:  'Criado',
  updated:  'Atualizado',
  deleted:  'Excluído',
  reverted: 'Revertido',
};

export default function AuditoriaIndex({ activities, filters }: PageProps) {
  const [localFilters, setLocalFilters] = useState(filters);

  function applyFilters() {
    router.get('/auditoria', localFilters, { preserveState: true });
  }

  function clearFilters() {
    setLocalFilters({});
    router.get('/auditoria');
  }

  function shortSubject(type: string | null): string {
    if (!type) return '—';
    const parts = type.split('\\');
    return parts[parts.length - 1] ?? type;
  }

  return (
    <AppShellV2>
      <Head title="Auditoria — Histórico de alterações" />

      <div className="container mx-auto p-4">
        <PageHeader
          icon="shield"
          title="Auditoria"
          subtitle="Quem mudou o quê, quando e por que. Reverter quando seguro."
        />

        {/* Filtros */}
        <Card className="mb-4">
          <CardContent className="p-4">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-3">
              <select
                className="border rounded px-2 py-1"
                value={localFilters.causer_kind ?? ''}
                onChange={(e) =>
                  setLocalFilters({ ...localFilters, causer_kind: e.target.value || undefined })
                }
              >
                <option value="">Quem fez (todos)</option>
                <option value="user">Usuário</option>
                <option value="agent">IA (Jana)</option>
                <option value="system">Sistema</option>
                <option value="api">API</option>
              </select>

              <select
                className="border rounded px-2 py-1"
                value={localFilters.event ?? ''}
                onChange={(e) =>
                  setLocalFilters({ ...localFilters, event: e.target.value || undefined })
                }
              >
                <option value="">Evento (todos)</option>
                <option value="created">Criado</option>
                <option value="updated">Atualizado</option>
                <option value="deleted">Excluído</option>
                <option value="reverted">Revertido</option>
              </select>

              <input
                className="border rounded px-2 py-1"
                placeholder="Tipo (ex: Transaction)"
                value={localFilters.subject_type ?? ''}
                onChange={(e) =>
                  setLocalFilters({ ...localFilters, subject_type: e.target.value || undefined })
                }
              />

              <div className="flex gap-2">
                <Button onClick={applyFilters} className="flex-1">Aplicar</Button>
                <Button onClick={clearFilters} variant="outline">Limpar</Button>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Tabela */}
        <Card>
          <CardContent className="p-0">
            <table className="w-full">
              <thead className="bg-gray-50 border-b">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-semibold">Quando</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">Quem</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">Evento</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">Tipo</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">Descrição</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold">Status</th>
                </tr>
              </thead>
              <tbody>
                {activities.data.length === 0 && (
                  <tr>
                    <td colSpan={6} className="text-center p-8 text-gray-500">
                      Sem alterações no período/filtros selecionados.
                    </td>
                  </tr>
                )}
                {activities.data.map((a) => {
                  const causerBadge = CAUSER_BADGE[a.causer_kind] ?? CAUSER_BADGE.user;
                  return (
                    <tr key={a.id} className="border-b hover:bg-gray-50">
                      <td className="px-3 py-2 text-sm">{a.created_at}</td>
                      <td className="px-3 py-2">
                        <Badge className={causerBadge.cls}>
                          {causerBadge.label}
                          {a.causer_kind === 'agent' && a.agent_run_id ? ` #${a.agent_run_id}` : ''}
                        </Badge>
                      </td>
                      <td className="px-3 py-2 text-sm">
                        {EVENT_LABEL[a.event ?? ''] ?? a.event ?? '—'}
                      </td>
                      <td className="px-3 py-2 text-sm font-mono">{shortSubject(a.subject_type)}</td>
                      <td className="px-3 py-2 text-sm">{a.description}</td>
                      <td className="px-3 py-2">
                        {a.reverted_at ? (
                          <Badge className="bg-rose-100 text-rose-800">Revertida</Badge>
                        ) : (
                          <Link href={`/auditoria/${a.id}`} className="text-blue-600 hover:underline text-sm">
                            Detalhes →
                          </Link>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </CardContent>
        </Card>

        {/* Paginação simples */}
        <div className="mt-4 flex justify-between items-center text-sm text-gray-600">
          <span>
            Página {activities.current_page} de {activities.last_page} — {activities.total} entradas
          </span>
        </div>
      </div>
    </AppShellV2>
  );
}
