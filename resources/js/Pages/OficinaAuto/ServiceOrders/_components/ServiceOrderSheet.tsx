// Drawer lateral direito ServiceOrder (US-OFICINA-OS-DRAWER) — pattern Cockpit canon.
// Espelha resources/js/Pages/Sells/_components/SaleSheet.tsx (LIVE prod biz=1).
//
// Contexto: pré-reunião Martinho 13/maio precisa demonstrar fluxo end-to-end clicável.
// Click row Vehicles/ServiceOrders → drawer abre → usuário roda ações FSM do reparo
// (Iniciar diagnóstico, Concluir, Cancelar · ADR 0265) — sem sair da listagem.
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
import VendaDerivadaCard, {
  type VendaDerivada,
} from '@/Components/shared/VendaDerivadaCard';
import ServiceOrderFsmActionPanel from './ServiceOrderFsmActionPanel';
import ServiceOrderStagePipeline from './ServiceOrderStagePipeline';
import ServiceOrderTimeline from './ServiceOrderTimeline';

type OrderType = 'manutencao' | 'mecanica' | null; // ADR 0265: reparo, sem locação

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
  // Etapa FSM atual (key + name PT) pro eyebrow do drawer — null quando o processo
  // não está seedado pro negócio (cai no fallback de status). Mesmo payload do RichSheet.
  current_stage?: { key: string; name: string } | null;
  order_type: OrderType;
  // Campos de locação (delivery_address/expected_return_date/daily_rate/dias_locacao)
  // erradicados do payload do show() — ADR 0265.
  expected_completion: string | null;
  // Soma real dos itens da OS (peças + mão-de-obra) — mesmo campo do RichSheet.
  // valor_receber (accessor sempre-0 pós-ADR 0265) saiu da UI do drawer.
  items_total?: number | string;
  is_overdue?: boolean;
  entered_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  notes: string | null;
  vehicle: VehicleRel | null;
  contact: ContactRel | null;
  // ADR 0192 — venda derivada auto-criada pelo ServiceOrderObserver (PR #1530)
  // na transição status='concluida'. Renderiza VendaDerivadaCard shared no body.
  venda_derivada?: VendaDerivada | null;
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
  if (value === null || value === undefined || value === '') return '—';
  const num = typeof value === 'string' ? parseFloat(value) : value;
  if (Number.isNaN(num)) return '—';
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

const capitalize = (s: string | null | undefined) => {
  if (!s) return '';
  return s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
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
            {/* Header drawer (canon .prod-drawer-head — MESMO vocabulário do
                RichSheet): eyebrow 11px uppercase "OS #104 · <etapa>" + badge do
                tipo ao lado (NÃO no título), h2 17px = veículo, p 12.5px = cliente. */}
            <SheetHeader className="px-6 pt-6 pb-4 border-b border-border space-y-0">
              <div className="flex items-center gap-2 flex-wrap text-[11px] font-medium uppercase tracking-[0.05em] text-muted-foreground">
                <span>
                  <span className="font-mono">OS {data.number ?? `#${data.id}`}</span>
                  {' · '}
                  {data.current_stage?.name ?? capitalize(data.status)}
                  {data.is_overdue && (
                    <span className="ml-1.5 font-semibold text-destructive">· Atrasada</span>
                  )}
                </span>
                <OrderTypeBadge type={data.order_type} />
              </div>
              <SheetTitle className="text-[17px] font-semibold leading-tight tracking-tight text-foreground mt-1 mb-0.5">
                {data.vehicle?.vehicle_type ? capitalize(data.vehicle.vehicle_type) : 'Veículo'}
                {data.vehicle?.plate ? (
                  <span className="text-[13px] font-normal font-mono text-muted-foreground ml-1.5">
                    · {data.vehicle.plate}
                  </span>
                ) : null}
              </SheetTitle>
              <SheetDescription className="text-[12.5px] text-muted-foreground">
                {data.contact ? data.contact.name : 'Cliente não informado'}
              </SheetDescription>
            </SheetHeader>

            {/* Conteúdo scroll — bloco-topo respira junto; seções seguem com
                border-top fino (canon .ofc-drawer-section, MESMO do RichSheet). */}
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <div className="space-y-4">
              {/* ADR 0192 — Integração Vendas × Oficina A1 KB-9.75.
                  Card "Esta OS gerou venda #V-NNNN" renderiza no topo quando
                  ServiceOrderObserver (PR #1530) criou Transaction derivada na
                  transição status='concluida'. Componente shared cross-módulo. */}
              {data.venda_derivada && <VendaDerivadaCard venda={data.venda_derivada} />}

              {/* Entrada/Valor — linha de meta compacta (canon: label muted +
                  valor tabular-nums à direita), não mais caixões MiniKpi. */}
              <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-[12px] px-0.5">
                <dt className="text-muted-foreground">Entrada</dt>
                <dd className="text-foreground tabular-nums text-right">
                  {formatDateOnly(data.started_at ?? data.entered_at)}
                </dd>
                <dt className="text-muted-foreground">Valor</dt>
                <dd className="tabular-nums font-semibold text-right text-success">
                  {formatBRL(data.items_total ?? 0)}
                </dd>
              </dl>
              </div>

              {/* Detalhes — campos básicos OS */}
              <Section title="Detalhes" icon={Truck}>
                <div className="space-y-2 text-sm">
                  {/* Veículo */}
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
                        {formatDateOnly(data.expected_completion)}
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

              {/* Mini-grafo horizontal stages (Wave 7-E — gap #2 estado-da-arte). */}
              <Section title="Pipeline" icon={CheckCircle2}>
                <ServiceOrderStagePipeline
                  serviceOrderId={data.id}
                  enabled={open}
                  refreshKey={historyVersion}
                />
              </Section>

              {/* Ações FSM (Wave 7-A backend) */}
              <Section title="Ações disponíveis" icon={CheckCircle2}>
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

// Canon .ofc-drawer-section (MESMO Section do RichSheet — um único vocabulário
// de sheet): seções separadas por border-top fino, h4 10.5px/600 uppercase
// ls .04em muted; sem caixas empilhadas (MiniKpi removido 2026-06-11).
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
    <section className="border-t border-border mt-2.5 pt-3.5 pb-1">
      <h3 className="text-[10.5px] font-semibold uppercase tracking-[0.04em] text-muted-foreground mb-2.5 flex items-center gap-1.5">
        {Icon && <Icon size={11} />}
        {title}
      </h3>
      {children}
    </section>
  );
}

function OrderTypeBadge({ type }: { type: OrderType }) {
  if (type === 'mecanica') {
    return (
      <span className="inline-flex items-center rounded-full border border-violet-200 bg-violet-50 px-2.5 py-0.5 text-[11px] font-medium text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/40 dark:text-violet-300">
        <Wrench size={10} className="mr-1" />
        Mecânica
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
