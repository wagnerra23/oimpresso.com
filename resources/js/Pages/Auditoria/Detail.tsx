// Pagina /auditoria/{id} — detalhe de uma activity com diff old/new + reverter.
// US-AUDIT-009 (Sprint F3) per ADR 0127.
//
// Charter: ./Detail.charter.md
// Mission: explicar UMA alteração com diff inequívoco e permitir reverter
// quando seguro.

import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';

interface Activity {
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
  reverted_by_user_id: number | null;
  revert_reason: string | null;
  business_id: number;
  created_at: string;
  properties: {
    old?: Record<string, unknown>;
    attributes?: Record<string, unknown>;
  };
}

interface PageProps {
  activity: Activity;
}

const CAUSER_LABEL: Record<string, string> = {
  user:   'Usuário',
  agent:  'IA (Jana)',
  system: 'Sistema',
  api:    'API',
};

export default function AuditoriaDetail({ activity }: PageProps) {
  const [showRevert, setShowRevert] = useState(false);
  const [reason, setReason] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const old = activity.properties?.old ?? {};
  const newAttrs = activity.properties?.attributes ?? {};
  const allFields = Array.from(
    new Set([...Object.keys(old), ...Object.keys(newAttrs)])
  ).sort();

  function fmt(v: unknown): string {
    if (v === null || v === undefined) return '—';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
  }

  async function handleRevert(e: React.FormEvent) {
    e.preventDefault();
    if (reason.length < 10) {
      setError('Razão deve ter pelo menos 10 caracteres.');
      return;
    }
    setSubmitting(true);
    setError(null);

    try {
      router.post(
        `/auditoria/${activity.id}/revert`,
        { reason },
        {
          onSuccess: () => router.get('/auditoria'),
          onError: (errs) => {
            setError(errs.reason ?? 'Erro ao reverter.');
            setSubmitting(false);
          },
        }
      );
    } catch (err) {
      setError(String(err));
      setSubmitting(false);
    }
  }

  return (
    <AppShellV2>
      <Head title={`Auditoria #${activity.id}`} />

      <div className="container mx-auto p-4">
        <PageHeader
          icon="shield"
          title={`Atividade #${activity.id}`}
          subtitle={activity.description}
        />

        <Link href="/auditoria" className="text-sm text-blue-600 hover:underline mb-4 inline-block">
          ← Voltar para listagem
        </Link>

        {/* Metadata */}
        <Card className="mb-4">
          <CardContent className="p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
              <div className="text-xs text-gray-500">Quando</div>
              <div>{activity.created_at}</div>
            </div>
            <div>
              <div className="text-xs text-gray-500">Quem</div>
              <Badge>{CAUSER_LABEL[activity.causer_kind]}</Badge>
              {activity.agent_run_id && (
                <span className="ml-2 text-xs text-gray-500">run #{activity.agent_run_id}</span>
              )}
            </div>
            <div>
              <div className="text-xs text-gray-500">Tipo</div>
              <div className="font-mono text-xs">{activity.subject_type}</div>
              {activity.subject_id && <div className="text-xs">#{activity.subject_id}</div>}
            </div>
            <div>
              <div className="text-xs text-gray-500">Evento</div>
              <div>{activity.event}</div>
            </div>
          </CardContent>
        </Card>

        {/* Diff side-by-side */}
        <Card className="mb-4">
          <CardHeader className="border-b p-3">
            <h3 className="font-semibold">Diff old ↔ new</h3>
          </CardHeader>
          <CardContent className="p-0">
            <table className="w-full text-sm">
              <thead className="bg-gray-50 border-b">
                <tr>
                  <th className="px-3 py-2 text-left text-xs font-semibold w-1/4">Campo</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold w-3/8 bg-rose-50">Antes (old)</th>
                  <th className="px-3 py-2 text-left text-xs font-semibold w-3/8 bg-emerald-50">Depois (new)</th>
                </tr>
              </thead>
              <tbody>
                {allFields.length === 0 && (
                  <tr>
                    <td colSpan={3} className="text-center p-4 text-gray-500">
                      Sem mudanças capturadas (event={activity.event}).
                    </td>
                  </tr>
                )}
                {allFields.map((field) => (
                  <tr key={field} className="border-b">
                    <td className="px-3 py-2 font-mono text-xs">{field}</td>
                    <td className="px-3 py-2 bg-rose-50/30 font-mono text-xs">{fmt(old[field])}</td>
                    <td className="px-3 py-2 bg-emerald-50/30 font-mono text-xs">{fmt(newAttrs[field])}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>

        {/* Revert (se ainda não foi revertida) */}
        {activity.reverted_at ? (
          <Card>
            <CardContent className="p-4 bg-rose-50">
              <div className="font-semibold text-rose-900">Já revertida</div>
              <div className="text-sm">
                Em {activity.reverted_at} por user #{activity.reverted_by_user_id}
              </div>
              <div className="text-sm mt-1">
                <span className="text-gray-600">Razão:</span> {activity.revert_reason}
              </div>
            </CardContent>
          </Card>
        ) : (
          <Card>
            <CardContent className="p-4">
              {!showRevert ? (
                <Button onClick={() => setShowRevert(true)} variant="destructive">
                  Reverter esta alteração
                </Button>
              ) : (
                <form onSubmit={handleRevert} className="space-y-3">
                  <div>
                    <label className="block text-sm font-semibold mb-1">
                      Razão da reversão (mín 10 caracteres):
                    </label>
                    <textarea
                      className="w-full border rounded p-2 text-sm"
                      rows={3}
                      value={reason}
                      onChange={(e) => setReason(e.target.value)}
                      required
                      minLength={10}
                      placeholder="Ex: estoque ajustado por engano, voltando ao valor original."
                    />
                    <div className="text-xs text-gray-500 mt-1">{reason.length} chars</div>
                  </div>
                  {error && (
                    <div className="text-sm text-rose-700 bg-rose-50 p-2 rounded">{error}</div>
                  )}
                  <div className="flex gap-2">
                    <Button type="submit" variant="destructive" disabled={submitting || reason.length < 10}>
                      {submitting ? 'Revertendo...' : 'Confirmar reversão'}
                    </Button>
                    <Button type="button" variant="outline" onClick={() => setShowRevert(false)}>
                      Cancelar
                    </Button>
                  </div>
                </form>
              )}
            </CardContent>
          </Card>
        )}
      </div>
    </AppShellV2>
  );
}
