// Drawer lateral direito ServiceOrder (US-OFICINA-OS-DRAWER) — pattern Cockpit canon.
// Espelha resources/js/Pages/Sells/_components/SaleSheet.tsx (LIVE prod biz=1).
//
// Contexto: pré-reunião Martinho 13/maio precisa demonstrar fluxo end-to-end clicável.
// Click row Vehicles/ServiceOrders → drawer abre → usuário roda ações FSM (Iniciar locação,
// Recolher caçamba, Concluir, Cancelar) — sem sair da listagem.
//
// Refs: ADR 0143 (FSM Pipeline LIVE), ADR 0110 (Cockpit V2),
//       PR #717 (re-render fix — useMemo/useCallback nos handlers descendentes),
//       Wave 7-A backend ServiceOrderFsmActionController.

import { useCallback, useEffect, useState } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Edit,
  ExternalLink,
  Loader2,
  MapPin,
  Phone,
  Truck,
  User,
  Wrench,
} from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import ServiceOrderFsmActionPanel from './ServiceOrderFsmActionPanel';
import ServiceOrderTimeline from './ServiceOrderTimeline';

type OrderType = 'locacao' | 'manutencao' | null;

interface ContactRel {
  id: number;
  name: string;
  mobile?: string | null;
  email?: string | null;
}

interface VehicleRel {
  id: number;
  plate: string;
  vehicle_number?: string | null;
  vehicle_type?: string | null;
  capacity_m3?: number | string | null;
}

interface ServiceOrderDetail {
  id: number;
  number: string | null;
  status: string;
  order_type: OrderType;
  delivery_address: string | null;
  expected_return_date: string | null;
  expected_completion: string | null;
  daily_rate: number | string | null;
  dias_locacao: number | null;
  valor_receber: number | string | null;
  is_overdue?: boolean;
  entered_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  notes: string | null;
  vehicle: VehicleRel | null;
  contact: ContactRel | null;
  urls?: {
    edit?: string | null;
    show?: string | null;
  };
}

interface Props {
  serviceOrderId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Callback chamado quando OS muda (FSM transição etc) — Index pai pode refresh */
  onOrderChanged?: () => void;
}

const formatBRL = (value: number | string | null | undefined) => {
  if (value === null || value === undefined || value === '') return 'R$ [redacted Tier 0]';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (Number.isNaN(num)) return 'R$ [redacted Tier 0]';
  return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const formatDate = (iso: string | null | undefined) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
};

const formatDateOnly = (iso: string | null | undefined) => {
  if (!iso) return '—';
  const datePart = iso.length >= 10 ? iso.slice(0, 10) : iso;
  const [y, m, d] = datePart.split('-');
  if (!y || !m || !d) return iso;
  return `${d}/${m}/${y}`;
};

export default function ServiceOrderSheet({
  serviceOrderId,
  open,
  onOpenChange,
  onOrderChanged,
}: Props) {
  const [data, setData] = useState<ServiceOrderDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Wave 7-C: incrementa a cada transição FSM pra forçar Timeline re-fetch.
  const [historyVersion, setHistoryVersion] = useState(0);

  const fetchData = useCallback(async () => {
    if (!serviceOrderId) return;
    setLoading(true);
    setError(null);
    try {
      // Wave 7-A backend retorna detalhe da OS (mirror /sells/{id}/sheet-data).
      const r = await fetch(`/oficina-auto/service-orders/${serviceOrderId}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const json = await r.json();
      setData(json);
    } catch (e) {
      setError(String((e as Error)?.message || e));
    } finally {
      setLoading(false);
    }
  }, [serviceOrderId]);

  useEffect(() => {
    if (!serviceOrderId || !open) {
      setData(null);
      setError(null);
      return;
    }
    fetchData();
  }, [serviceOrderId, open, fetchData]);

  // Callback estável passada pro FsmActionPanel — evita re-render loop (lição PR #717).
  const handleFsmTransition = useCallback(() => {
    void fetchData();
    setHistoryVersion((v) => v + 1);
    onOrderChanged?.();
  }, [fetchData, onOrderChanged]);

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="w-full sm:max-w-xl flex flex-col p-0 overflow-hidden"
      >
        {loading && (
          <div className="flex-1 flex items-center justify-center">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        )}

        {error && !loading && (
          <div className="flex-1 flex items-center justify-center p-6 text-center">
            <div>
              <AlertTriangle className="h-8 w-8 text-rose-500 mx-auto mb-2" />
              <p className="text-sm text-foreground font-medium">
                Não foi possível carregar a ordem de serviço
              </p>
              <p className="text-xs text-muted-foreground mt-1">{error}</p>
            </div>
          </div>
        )}

        {!loading && data && (
          <>
            {/* Header drawer — número OS + tipo badge + cliente */}
            <SheetHeader className="px-6 pt-6 pb-4 border-b border-border space-y-2">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-mono text-xs text-muted-foreground">
                  {data.number ?? `#${data.id}`}
                </span>
                <OrderTypeBadge type={data.order_type} />
                {data.is_overdue && (
                  <span className="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
                    <AlertTriangle size={10} className="mr-0.5" />
                    Atrasada
                  </span>
                )}
              </div>
              <SheetTitle className="text-xl font-semibold tracking-tight text-foreground">
                {data.order_type === 'locacao' ? 'Locação' : 'Manutenção'}{' '}
                {data.number ?? `#${data.id}`}
              </SheetTitle>
              <SheetDescription className="text-sm text-muted-foreground">
                {data.contact ? (
                  <span>{data.contact.name}</span>
                ) : (
                  <span>Cliente não informado</span>
                )}
              </SheetDescription>
            </SheetHeader>

            {/* Conteúdo scroll — seções verticais (mesmo pattern SaleSheet) */}
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-6">
              {/* 4 KPIs mini horizontais */}
              <div className="grid grid-cols-3 gap-3">
                <MiniKpi
                  label="Diárias"
                  value={
                    data.dias_locacao != null
                      ? `${data.dias_locacao}d`
                      : '—'
                  }
                  tone={data.is_overdue ? 'warning' : undefined}
                />
                <MiniKpi
                  label="Diária"
                  value={formatBRL(data.daily_rate)}
                />
                <MiniKpi
                  label="A receber"
                  value={formatBRL(data.valor_receber)}
                  tone={
                    data.is_overdue
                      ? 'warning'
                      : Number(data.valor_receber ?? 0) > 0
                        ? 'warning'
                        : 'success'
                  }
                />
              </div>

              {/* Detalhes — campos básicos OS */}
              <Section title="Detalhes" icon={Truck}>
                <div className="space-y-2 text-sm">
                  {/* Caçamba */}
                  {data.vehicle && (
                    <div className="flex items-center gap-2 text-foreground">
                      <Truck size={13} className="text-muted-foreground" />
                      <span className="font-medium">
                        {data.vehicle.vehicle_number ?? data.vehicle.plate}
                      </span>
                      {data.vehicle.vehicle_number && data.vehicle.plate && (
                        <span className="text-xs text-muted-foreground">
                          ({data.vehicle.plate})
                        </span>
                      )}
                      {data.vehicle.capacity_m3 && (
                        <span className="text-xs text-muted-foreground">
                          · {data.vehicle.capacity_m3}m³
                        </span>
                      )}
                    </div>
                  )}

                  {/* Cliente contatos */}
                  {data.contact && (
                    <>
                      <div className="flex items-center gap-2 text-foreground">
                        <User size={13} className="text-muted-foreground" />
                        {data.contact.name}
                      </div>
                      {data.contact.mobile && (
                        <div className="flex items-center gap-2 text-muted-foreground text-xs">
                          <Phone size={12} />
                          {data.contact.mobile}
                        </div>
                      )}
                      {data.contact.email && (
                        <div className="flex items-center gap-2 text-muted-foreground text-xs">
                          <ExternalLink size={12} />
                          {data.contact.email}
                        </div>
                      )}
                    </>
                  )}

                  {/* Endereço entrega (locação) */}
                  {data.delivery_address && (
                    <div className="flex items-start gap-2 text-muted-foreground text-xs pt-1.5 border-t border-border/60">
                      <MapPin size={12} className="mt-0.5 flex-shrink-0" />
                      <span className="break-words">{data.delivery_address}</span>
                    </div>
                  )}

                  {/* Datas */}
                  <div className="grid grid-cols-2 gap-2 pt-1.5 border-t border-border/60">
                    <div className="text-xs">
                      <div className="text-muted-foreground">Início</div>
                      <div className="text-foreground tabular-nums">
                        {formatDateOnly(data.started_at ?? data.entered_at)}
                      </div>
                    </div>
                    <div className="text-xs">
                      <div className="text-muted-foreground">Prazo</div>
                      <div
                        className={
                          'tabular-nums ' +
                          (data.is_overdue
                            ? 'text-rose-700 font-medium'
                            : 'text-foreground')
                        }
                      >
                        {formatDateOnly(
                          data.expected_return_date ?? data.expected_completion,
                        )}
                      </div>
                    </div>
                  </div>

                  {data.completed_at && (
                    <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-400 text-xs pt-1.5 border-t border-border/60">
                      <CheckCircle2 size={12} />
                      Concluída em {formatDate(data.completed_at)}
                    </div>
                  )}
                </div>
              </Section>

              {/* Notas */}
              {data.notes && (
                <Section title="Observações" icon={Clock}>
                  <p className="text-sm text-muted-foreground whitespace-pre-wrap">
                    {data.notes}
                  </p>
                </Section>
              )}

              {/* Pipeline FSM — ações disponíveis (Wave 7-A backend) */}
              <Section title="Pipeline FSM" icon={CheckCircle2}>
                <ServiceOrderFsmActionPanel
                  serviceOrderId={data.id}
                  enabled={open}
                  onTransition={handleFsmTransition}
                />
              </Section>

              {/* Histórico — Wave 7-C timeline FSM auditável (gap #1 estado-da-arte). */}
              <Section title="Histórico" icon={Clock}>
                <ServiceOrderTimeline
                  key={`timeline-${data.id}-${historyVersion}`}
                  serviceOrderId={data.id}
                  enabled={open}
                />
              </Section>
            </div>

            {/* Footer ações sticky */}
            {data.urls?.edit && (
              <div className="border-t border-border px-6 py-3 bg-background flex items-center justify-end gap-2">
                <Button size="sm" asChild>
                  <a href={data.urls.edit}>
                    <Edit size={14} className="mr-1.5" />
                    Editar
                  </a>
                </Button>
              </div>
            )}
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

// ─── Subcomponents ───────────────────────────────────────────────────────────

function Section({
  title,
  icon: Icon,
  children,
}: {
  title: string;
  icon?: typeof Wrench;
  children: React.ReactNode;
}) {
  return (
    <section>
      <h3 className="text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-2 flex items-center gap-1.5">
        {Icon && <Icon size={11} />}
        {title}
      </h3>
      {children}
    </section>
  );
}

function MiniKpi({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone?: 'success' | 'warning';
}) {
  return (
    <div
      className={
        'rounded-md border p-2.5 ' +
        (tone === 'warning'
          ? 'border-amber-200 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/30'
          : tone === 'success'
            ? 'border-emerald-200 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/30'
            : 'border-border bg-muted/30')
      }
    >
      <div
        className={
          'text-[10px] font-semibold uppercase tracking-wider ' +
          (tone === 'warning'
            ? 'text-amber-700 dark:text-amber-400'
            : tone === 'success'
              ? 'text-emerald-700 dark:text-emerald-400'
              : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div
        className={
          'text-sm font-semibold tabular-nums mt-0.5 truncate ' +
          (tone === 'warning'
            ? 'text-amber-700 dark:text-amber-300'
            : tone === 'success'
              ? 'text-emerald-700 dark:text-emerald-300'
              : 'text-foreground')
        }
        title={value}
      >
        {value}
      </div>
    </div>
  );
}

function OrderTypeBadge({ type }: { type: OrderType }) {
  if (type === 'locacao') {
    return (
      <span className="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-[11px] font-medium text-blue-700 dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-300">
        <Truck size={10} className="mr-1" />
        Locação
      </span>
    );
  }
  if (type === 'manutencao') {
    return (
      <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-medium text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300">
        <Wrench size={10} className="mr-1" />
        Manutenção
      </span>
    );
  }
  return null;
}
