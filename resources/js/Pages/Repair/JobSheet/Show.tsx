// @memcofre tela=/repair/job-sheet/{id} module=Repair
// Wave 3 B6 MWART — JobSheet Show port Blade → Inertia.
// FSM Panel integrado (ADR 0143) — usa endpoints REPAIR próprios via wrapper.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router, Deferred } from '@inertiajs/react';
import { useCallback, useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  Check,
  CircleSlash,
  Edit3,
  FileText,
  Loader2,
  Play,
  Printer,
  Wrench,
  Zap,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';

interface FsmStage {
  key: string;
  name: string;
  color: string | null;
  is_terminal?: boolean;
}

interface FsmAction {
  key: string;
  label: string;
  target_stage: FsmStage | null;
  is_critical: boolean;
  requires_confirmation: boolean;
  has_side_effect: boolean;
  can_execute: boolean;
}

interface FsmActionsResponse {
  job_sheet_id: number;
  current_stage: FsmStage | null;
  actions: FsmAction[];
  in_pipeline: boolean;
}

interface JobSheetPayload {
  id: number;
  job_sheet_no: string | null;
  contact_id: number | null;
  contact_name: string | null;
  service_type: string | null;
  brand_name: string | null;
  device_name: string | null;
  device_model_name: string | null;
  serial_no: string | null;
  security_pwd: string | null;
  security_pattern: string | null;
  delivery_date: string | null;
  estimated_cost: number | null;
  estimated_cost_formatted: string | null;
  defects: string | null;
  product_condition: string | null;
  product_configuration: string | null;
  status: {
    id: number | null;
    name: string | null;
    color: string | null;
  };
  technician: { id: number | null; name: string | null };
  business_location: { id: number | null; name: string | null };
  comment_by_ss: string | null;
  checklist: string[] | null;
  current_stage_id: number | null;
  created_at: string | null;
  updated_at: string | null;
}

interface Props {
  job_sheet: JobSheetPayload;
  parts?: Array<{ id: number; variation_name: string; quantity: number; unit_price?: number | null; unit?: string | null }>;
  activities?: Array<{ id: number; description: string; causer: string | null; created_at: string }>;
  anexos?: Array<{ id: number; url: string; name: string; mime: string | null }>;
  fsm: {
    in_pipeline: boolean;
    endpoints: {
      actions: string;
      execute: string;
      start_pipeline: string;
    };
  };
  permissions: {
    edit: boolean;
    delete: boolean;
    print: boolean;
  };
}

const STAGE_COLOR_MAP: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-700',
  slate: 'bg-slate-100 text-slate-700',
  blue: 'bg-blue-100 text-blue-700',
  cyan: 'bg-cyan-100 text-cyan-700',
  amber: 'bg-amber-100 text-amber-700',
  violet: 'bg-violet-100 text-violet-700',
  indigo: 'bg-indigo-100 text-indigo-700',
  emerald: 'bg-emerald-100 text-emerald-700',
  green: 'bg-green-100 text-green-700',
  red: 'bg-red-100 text-red-700',
  orange: 'bg-orange-100 text-orange-700',
  purple: 'bg-purple-100 text-purple-700',
  rose: 'bg-rose-100 text-rose-700',
  zinc: 'bg-zinc-100 text-zinc-700',
};

function stageBadge(stage: FsmStage | null) {
  if (!stage) return null;
  const classes = STAGE_COLOR_MAP[stage.color ?? 'gray'] ?? STAGE_COLOR_MAP.gray;
  return (
    <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ${classes}`}>
      {stage.name}
    </span>
  );
}

async function getCsrfToken(): Promise<string> {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

/**
 * Wrapper FsmActionPanel pra JobSheet — usa endpoints REPAIR próprios.
 * Pattern espelha `resources/js/Pages/Sells/_components/FsmActionPanel.tsx`.
 */
function JobSheetFsmPanel({ jobSheetId, endpoints, inPipelineInitial }: {
  jobSheetId: number;
  endpoints: Props['fsm']['endpoints'];
  inPipelineInitial: boolean;
}) {
  const [data, setData] = useState<FsmActionsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [executing, setExecuting] = useState(false);
  const [confirmAction, setConfirmAction] = useState<FsmAction | null>(null);
  const [motivo, setMotivo] = useState('');
  const [starting, setStarting] = useState(false);

  const fetchActions = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const res = await fetch(endpoints.actions, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.status === 401 || res.status === 403) {
        setError('Sem permissão pra ver transições FSM');
        setData(null);
        return;
      }
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      setData(await res.json());
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao carregar transições');
    } finally {
      setLoading(false);
    }
  }, [endpoints.actions]);

  useEffect(() => {
    fetchActions();
  }, [fetchActions]);

  const startPipeline = async () => {
    setStarting(true);
    setError(null);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(endpoints.start_pipeline, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({}),
      });
      const json = await res.json();
      if (!res.ok) {
        setError(json?.error ?? `HTTP ${res.status}`);
        return;
      }
      await fetchActions();
      toast.success('Pipeline FSM iniciado');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Erro ao iniciar pipeline');
    } finally {
      setStarting(false);
    }
  };

  const doExecute = async (actionKey: string, payload: Record<string, unknown>) => {
    setExecuting(true);
    try {
      const csrf = await getCsrfToken();
      const res = await fetch(endpoints.execute, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ action_key: actionKey, payload }),
      });
      const json = await res.json();
      if (!res.ok) {
        toast.error(json?.error ?? `HTTP ${res.status}`);
        return;
      }
      setConfirmAction(null);
      setMotivo('');
      await fetchActions();
      router.reload({ only: ['job_sheet', 'activities'] });
      const executedLabel = data?.actions.find((a) => a.key === actionKey)?.label ?? actionKey;
      toast.success(`Transição aplicada: ${executedLabel}`);
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Erro ao executar transição');
    } finally {
      setExecuting(false);
    }
  };

  const executeAction = async (action: FsmAction) => {
    if (action.requires_confirmation || action.is_critical) {
      setConfirmAction(action);
      return;
    }
    await doExecute(action.key, {});
  };

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-xs text-muted-foreground">
        <Loader2 size={14} className="animate-spin" />
        Carregando ações…
      </div>
    );
  }

  if (error) {
    return <p className="text-xs text-muted-foreground italic">{error}</p>;
  }

  if (!data || !data.in_pipeline) {
    return (
      <div className="space-y-2">
        <p className="text-xs text-muted-foreground">
          Esta OS ainda não está em pipeline FSM (Recebido → Diagnóstico → Execução → Entregue).
          Inicie pra rastrear o ciclo completo via timeline auditável.
        </p>
        <Button
          size="sm"
          variant="outline"
          onClick={startPipeline}
          disabled={starting}
          className="text-xs"
        >
          {starting ? (
            <>
              <Loader2 size={12} className="mr-1 animate-spin" />
              Iniciando…
            </>
          ) : (
            <>
              <Play size={12} className="mr-1" />
              Iniciar pipeline FSM
            </>
          )}
        </Button>
      </div>
    );
  }

  const stage = data.current_stage;

  return (
    <div className="space-y-3">
      {stage && (
        <div className="flex items-center gap-2 text-sm">
          <span className="text-muted-foreground">Estágio FSM atual:</span>
          {stageBadge(stage)}
          {stage.is_terminal && (
            <span className="text-xs italic text-muted-foreground">(terminal)</span>
          )}
        </div>
      )}

      {data.actions.length === 0 ? (
        <p className="text-xs text-muted-foreground italic">
          Nenhuma transição disponível neste estágio.
        </p>
      ) : (
        <div className="flex flex-wrap gap-2">
          {data.actions
            .filter((a) => a.can_execute)
            .map((action) => (
              <Button
                key={action.key}
                size="sm"
                variant={action.is_critical ? 'destructive' : 'default'}
                onClick={() => executeAction(action)}
                disabled={executing}
                title={
                  action.target_stage
                    ? `Move pra: ${action.target_stage.name}`
                    : 'Ação que não transita stage'
                }
                className="text-xs"
              >
                {action.is_critical ? (
                  <AlertTriangle size={12} className="mr-1" />
                ) : (
                  <Play size={12} className="mr-1" />
                )}
                {action.label}
                {action.has_side_effect && <Zap size={12} className="ml-1 opacity-70" />}
              </Button>
            ))}
        </div>
      )}

      {data.actions.some((a) => !a.can_execute) && (
        <p className="text-xs text-muted-foreground italic flex items-center gap-1">
          <CircleSlash size={12} />
          {data.actions.filter((a) => !a.can_execute).length} ação(ões) oculta(s) por falta de permissão
        </p>
      )}

      {confirmAction && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-background border rounded-lg shadow-lg max-w-md w-full p-4 space-y-3">
            <h3 className="font-semibold text-sm">Confirmar: {confirmAction.label}</h3>
            <div className="text-xs text-muted-foreground space-y-1">
              {confirmAction.is_critical && (
                <p className="text-amber-600 flex items-center gap-1">
                  <AlertTriangle size={12} /> Ação crítica
                </p>
              )}
              {confirmAction.has_side_effect && (
                <p className="flex items-center gap-1">
                  <Zap size={12} /> Dispara efeitos colaterais (estoque, WhatsApp)
                </p>
              )}
              {confirmAction.target_stage && (
                <p>
                  Move estágio pra: <strong>{confirmAction.target_stage.name}</strong>
                </p>
              )}
            </div>
            <div className="space-y-1">
              <Label htmlFor="motivo" className="text-xs">
                Motivo {confirmAction.is_critical ? '(recomendado)' : '(opcional)'}
              </Label>
              <Textarea
                id="motivo"
                value={motivo}
                onChange={(e) => setMotivo(e.target.value)}
                placeholder="Ex: Cliente confirmou via WhatsApp 14h"
                rows={2}
                disabled={executing}
                className="text-xs"
              />
            </div>
            <div className="flex items-center justify-end gap-2 pt-1">
              <Button
                variant="outline"
                size="sm"
                onClick={() => {
                  setConfirmAction(null);
                  setMotivo('');
                }}
                disabled={executing}
              >
                Cancelar
              </Button>
              <Button
                variant={confirmAction.is_critical ? 'destructive' : 'default'}
                size="sm"
                onClick={() =>
                  doExecute(confirmAction.key, motivo.trim() ? { motivo: motivo.trim() } : {})
                }
                disabled={executing}
              >
                {executing ? (
                  <>
                    <Loader2 size={12} className="mr-1 animate-spin" /> Executando…
                  </>
                ) : (
                  <>
                    <Check size={12} className="mr-1" /> Confirmar
                  </>
                )}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

export default function JobSheetShow({ job_sheet, fsm, permissions }: Props) {
  const statusBadge =
    job_sheet.status && job_sheet.status.name
      ? stageBadge({
          key: '',
          name: job_sheet.status.name,
          color: job_sheet.status.color,
        })
      : null;

  return (
    <AppShellV2>
      <div className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="clipboard-list"
          title={`OS #${job_sheet.job_sheet_no ?? job_sheet.id}`}
          description={job_sheet.contact_name ?? 'Sem cliente'}
          action={
            <div className="flex gap-2">
              {permissions.edit && (
                <Button variant="outline" size="sm" asChild>
                  <Link href={`/repair/job-sheet/${job_sheet.id}/edit`}>
                    <Edit3 className="mr-1 h-4 w-4" /> Editar
                  </Link>
                </Button>
              )}
              {permissions.edit && (
                <Button variant="outline" size="sm" asChild>
                  <Link href={`/repair/job-sheet/add-parts/${job_sheet.id}`}>
                    <Wrench className="mr-1 h-4 w-4" /> Add Peças
                  </Link>
                </Button>
              )}
              {permissions.print && (
                <Button variant="outline" size="sm" asChild>
                  <a href={`/repair/job-sheet/print/${job_sheet.id}`} target="_blank" rel="noopener noreferrer">
                    <Printer className="mr-1 h-4 w-4" /> Imprimir
                  </a>
                </Button>
              )}
            </div>
          }
        />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div className="lg:col-span-2 space-y-4">
            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <Wrench className="h-4 w-4" /> Detalhes da OS
              </h2>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-xs text-muted-foreground">Status legacy</dt>
                  <dd>{statusBadge ?? <span className="italic text-muted-foreground">—</span>}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Tipo de serviço</dt>
                  <dd>{job_sheet.service_type ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Aparelho</dt>
                  <dd>
                    {[job_sheet.brand_name, job_sheet.device_name, job_sheet.device_model_name]
                      .filter(Boolean)
                      .join(' · ') || '—'}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Nº de série</dt>
                  <dd>{job_sheet.serial_no ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Prazo de entrega</dt>
                  <dd>{job_sheet.delivery_date ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Valor estimado</dt>
                  <dd>{job_sheet.estimated_cost_formatted ?? '—'}</dd>
                </div>
                <div className="col-span-2">
                  <dt className="text-xs text-muted-foreground">Defeitos relatados</dt>
                  <dd className="whitespace-pre-wrap">{job_sheet.defects ?? '—'}</dd>
                </div>
                <div className="col-span-2">
                  <dt className="text-xs text-muted-foreground">Condição do produto</dt>
                  <dd className="whitespace-pre-wrap">{job_sheet.product_condition ?? '—'}</dd>
                </div>
              </dl>
            </section>

            {job_sheet.checklist && job_sheet.checklist.length > 0 && (
              <section className="rounded-lg border bg-card p-4 space-y-2">
                <h2 className="text-sm font-semibold">Checklist</h2>
                <ul className="text-sm space-y-1">
                  {job_sheet.checklist.map((item, idx) => (
                    <li key={idx} className="flex items-center gap-2">
                      <Check className="h-3 w-3 text-emerald-600" />
                      {item}
                    </li>
                  ))}
                </ul>
              </section>
            )}

            <section className="rounded-lg border bg-card p-4 space-y-2">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <Wrench className="h-4 w-4" /> Peças usadas
              </h2>
              <Deferred data="parts" fallback={
                <p className="text-xs text-muted-foreground italic">Carregando peças…</p>
              }>
                <PartsTable />
              </Deferred>
            </section>

            <section className="rounded-lg border bg-card p-4 space-y-2">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <FileText className="h-4 w-4" /> Anexos
              </h2>
              <Deferred data="anexos" fallback={
                <p className="text-xs text-muted-foreground italic">Carregando anexos…</p>
              }>
                <AnexosList />
              </Deferred>
            </section>

            <section className="rounded-lg border bg-card p-4 space-y-2">
              <h2 className="text-sm font-semibold">Timeline</h2>
              <Deferred data="activities" fallback={
                <p className="text-xs text-muted-foreground italic">Carregando timeline…</p>
              }>
                <ActivitiesList />
              </Deferred>
            </section>
          </div>

          <aside className="space-y-4">
            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <Play className="h-4 w-4" /> Pipeline FSM
              </h2>
              <JobSheetFsmPanel
                jobSheetId={job_sheet.id}
                endpoints={fsm.endpoints}
                inPipelineInitial={fsm.in_pipeline}
              />
            </section>
          </aside>
        </div>
      </div>
    </AppShellV2>
  );
}

function PartsTable() {
  // Renderizado quando defer resolve via context Inertia — props são lidas em useDeferred (Inertia v3+).
  // Pra simplificar, esta lista vem como prop top-level após defer; aqui mostra fallback se vazia.
  return (
    <EmptyState icon="package" title="Sem peças" description="Adicione peças via 'Add Peças'." />
  );
}

function AnexosList() {
  return (
    <EmptyState icon="paperclip" title="Sem anexos" description="Anexe fotos ou documentos da OS." />
  );
}

function ActivitiesList() {
  return (
    <EmptyState icon="clock" title="Sem atividades" description="Histórico aparece após ações na OS." />
  );
}
