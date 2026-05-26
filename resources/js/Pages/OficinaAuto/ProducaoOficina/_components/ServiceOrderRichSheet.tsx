// ServiceOrderRichSheet — drawer rico polimórfico Produção · Oficina (origem
// CacambaProducaoSheet pré-ADR 0194 — renomeado 2026-05-26 Wave 2.2 US-OFICINA-027).
//
// Espelha 1:1 protótipo Cowork canon — locação caçamba CNAE 4581 hipotético
// (`prototipo-ui/prototipos/producao-oficina/visual-source.html`) E protótipo OS
// mecânica CNAE 4520 Martinho (screenshot Wagner 2026-05-26 · sub-vertical 4 ADR 0194).
//
// 6 sections polimórficas (branching por `data.order_type`):
//   1. Header KV grid:
//        - locação: Cliente / Capacidade / Endereço / Diárias / Valor
//        - manutenção: KM / Box / Mecânico / Valor (items_total) — ADR 0194 mecânica pesada
//   2. OBSERVAÇÃO (notes — italic se vazio)
//   3. PEÇAS & MÃO DE OBRA — NOVA Wave 2.3 (data.items[] consumindo `oficina_service_order_items`
//      via US-OFICINA-027 backend). Renderiza linha-a-linha com ícones tipo + qty + valor unit
//      + valor total. Footer da section: TOTAL OS.
//   4. FOTOS & LAUDO — grid 3 placeholders aspect-square + "Adicionar foto" (disabled V2)
//   5. PIPELINE FSM — embed ServiceOrderFsmActionPanel (REUSO PR #729, não recria)
//   6. LINHA DO TEMPO — placeholder skeleton derivada (V2: fetch sale_stage_history real)
//
// Footer Wave 2.4: "Conversa cliente" (WhatsApp Linker) / "Imprimir OS" / "Avançar etapa" embedded.
//
// CRÍTICO React 19 — useMemo/useCallback nos handlers descendentes (lição PR #717).
//
// Consumidores: ProducaoOficina/Index.tsx — drawer abre ao clicar card kanban.
// Multi-tenant Tier 0 [ADR 0093]: payload vem do endpoint show() que respeita global scope.

import { useCallback, useEffect, useState } from 'react';
import {
  AlertTriangle,
  Camera,
  CheckCircle2,
  Clock,
  Edit,
  ExternalLink,
  FileText,
  Loader2,
  MapPin,
  Phone,
  Truck,
  User,
} from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import MercosulPlate from './MercosulPlate';
import ServiceOrderFsmActionPanel from '../../ServiceOrders/_components/ServiceOrderFsmActionPanel';
import { MessageCircle, Printer, Wrench, Package, UserCog } from 'lucide-react';
import { toast } from 'sonner';
import { printServiceOrder } from '@/Lib/printServiceOrder';

type OrderType = 'locacao' | 'manutencao' | null;

type ItemTipo = 'peca' | 'mao_obra' | 'servico_terceiro';

interface ServiceOrderItemRel {
  id: number;
  tipo: ItemTipo;
  descricao: string;
  quantidade: number | string;
  valor_unitario: number | string;
  valor_total: number | string;
  product_id: number | null;
  notes: string | null;
}

interface AssignedUserRel {
  id: number;
  name: string;
}

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
  model_year?: number | null;
  manufacture_year?: number | null;
  color?: string | null;
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
  // Wave 2.1 US-OFICINA-027 — campos modo manutenção (sub-vertical 4 ADR 0194)
  box_label?: string | null;
  assigned_user?: AssignedUserRel | null;
  mileage_at_service?: number | null;
  // Wave 2.3 US-OFICINA-027 — items lançados (peças + mão-de-obra + terceiros)
  items?: ServiceOrderItemRel[];
  items_total?: number | string;
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
  if (Number.isNaN(d.getTime())) return iso ?? '—';
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

const ITEM_TIPO_LABEL: Record<ItemTipo, string> = {
  peca: 'Peça',
  mao_obra: 'Mão de obra',
  servico_terceiro: 'Serviço terceiro',
};

const ITEM_TIPO_ICON: Record<ItemTipo, typeof Wrench> = {
  peca: Package,
  mao_obra: Wrench,
  servico_terceiro: UserCog,
};

const toFloat = (value: number | string | null | undefined): number => {
  if (value === null || value === undefined || value === '') return 0;
  const n = typeof value === 'string' ? parseFloat(value) : value;
  return Number.isNaN(n) ? 0 : n;
};

export default function ServiceOrderRichSheet({
  serviceOrderId,
  open,
  onOpenChange,
  onOrderChanged,
}: Props) {
  const [data, setData] = useState<ServiceOrderDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const fetchData = useCallback(async () => {
    if (!serviceOrderId) return;
    setLoading(true);
    setError(null);
    try {
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
                Não foi possível carregar a caçamba
              </p>
              <p className="text-xs text-muted-foreground mt-1">{error}</p>
            </div>
          </div>
        )}

        {!loading && data && (
          <>
            {/* Header drawer — número OS + status badge cliente */}
            <SheetHeader className="px-6 pt-6 pb-4 border-b border-border space-y-2">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-mono text-xs text-muted-foreground">
                  OS {data.number ?? `#${data.id}`}
                </span>
                <StatusBadge status={data.status} orderType={data.order_type} />
                {data.is_overdue && (
                  <span className="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[11px] font-medium text-rose-700">
                    <AlertTriangle size={10} className="mr-0.5" />
                    Atrasada
                  </span>
                )}
              </div>
              <SheetTitle className="text-lg font-semibold tracking-tight text-foreground">
                {data.order_type === 'manutencao' ? (
                  <>
                    {data.vehicle?.vehicle_type ? capitalize(data.vehicle.vehicle_type) : 'Veículo'}
                    {' '}
                    {data.vehicle?.plate ?? '—'}
                    {data.vehicle?.model_year ? (
                      <span className="text-sm font-normal text-muted-foreground ml-1.5">
                        · {data.vehicle.model_year}
                      </span>
                    ) : null}
                  </>
                ) : (
                  <>
                    Caçamba {data.vehicle?.vehicle_number ?? data.vehicle?.plate ?? '—'}
                    {data.vehicle?.capacity_m3 ? (
                      <span className="text-sm font-normal text-muted-foreground ml-1.5">
                        · {data.vehicle.capacity_m3}m³
                      </span>
                    ) : null}
                  </>
                )}
              </SheetTitle>
              <SheetDescription className="text-sm text-muted-foreground">
                {data.contact ? data.contact.name : 'Cliente não informado'}
              </SheetDescription>
            </SheetHeader>

            {/* Conteúdo scroll — 5 sections empilhadas */}
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">

              {/* ─── SEÇÃO 1: Veículo + KV grid polimórfico (Wave 2.2 US-OFICINA-027) ───
                  - manutenção (Martinho sub-vertical 4 CNAE 4520): KM / Box / Mecânico / Valor (items_total)
                  - locação    (sub-vertical 3 hipotético CNAE 4581): Cliente / Capacidade / Endereço / Diárias / Valor */}
              {data.vehicle && (
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 grid grid-cols-[auto_1fr] gap-3">
                  <MercosulPlate plate={data.vehicle.plate} size="md" />
                  <dl className="grid grid-cols-[auto_1fr] gap-x-2.5 gap-y-1 text-[11.5px] self-end">
                    {data.order_type === 'manutencao' ? (
                      <>
                        {data.mileage_at_service != null && (
                          <>
                            <dt className="text-muted-foreground">KM</dt>
                            <dd className="text-foreground font-medium tabular-nums">
                              {data.mileage_at_service.toLocaleString('pt-BR')}
                            </dd>
                          </>
                        )}
                        {data.box_label && (
                          <>
                            <dt className="text-muted-foreground">Box</dt>
                            <dd className="text-foreground">{data.box_label}</dd>
                          </>
                        )}
                        {data.assigned_user && (
                          <>
                            <dt className="text-muted-foreground">Mecânico</dt>
                            <dd className="text-foreground">{data.assigned_user.name}</dd>
                          </>
                        )}
                        <dt className="text-muted-foreground">Valor</dt>
                        <dd className="tabular-nums font-semibold text-emerald-700">
                          {formatBRL(data.items_total ?? 0)}
                        </dd>
                      </>
                    ) : (
                      <>
                        {data.contact && (
                          <>
                            <dt className="text-muted-foreground">Cliente</dt>
                            <dd className="text-foreground font-medium truncate">
                              {data.contact.name}
                            </dd>
                          </>
                        )}
                        {data.vehicle.capacity_m3 && (
                          <>
                            <dt className="text-muted-foreground">Capacidade</dt>
                            <dd className="text-foreground tabular-nums">
                              {data.vehicle.capacity_m3}m³
                            </dd>
                          </>
                        )}
                        {data.delivery_address && (
                          <>
                            <dt className="text-muted-foreground">Endereço</dt>
                            <dd className="text-foreground truncate" title={data.delivery_address}>
                              {data.delivery_address}
                            </dd>
                          </>
                        )}
                        <dt className="text-muted-foreground">Diárias</dt>
                        <dd className="text-foreground tabular-nums">
                          {data.dias_locacao ?? 0}d ×{' '}
                          <span className="text-muted-foreground">{formatBRL(data.daily_rate)}</span>
                        </dd>
                        <dt className="text-muted-foreground">Valor</dt>
                        <dd
                          className={
                            'tabular-nums font-semibold ' +
                            (data.is_overdue ? 'text-rose-700' : 'text-emerald-700')
                          }
                        >
                          {formatBRL(data.valor_receber)}
                        </dd>
                      </>
                    )}
                  </dl>
                </div>
              )}

              {/* Datas — início + prazo */}
              <div className="grid grid-cols-2 gap-3">
                <div className="rounded-md border border-border bg-muted/30 p-2.5">
                  <div className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                    Início
                  </div>
                  <div className="text-sm font-medium tabular-nums mt-0.5">
                    {formatDateOnly(data.started_at ?? data.entered_at)}
                  </div>
                </div>
                <div
                  className={
                    'rounded-md border p-2.5 ' +
                    (data.is_overdue
                      ? 'border-rose-200 bg-rose-50/60'
                      : 'border-border bg-muted/30')
                  }
                >
                  <div
                    className={
                      'text-[10px] font-semibold uppercase tracking-wider ' +
                      (data.is_overdue ? 'text-rose-700' : 'text-muted-foreground')
                    }
                  >
                    Prazo
                  </div>
                  <div
                    className={
                      'text-sm font-medium tabular-nums mt-0.5 ' +
                      (data.is_overdue ? 'text-rose-700' : 'text-foreground')
                    }
                  >
                    {formatDateOnly(data.expected_return_date ?? data.expected_completion)}
                  </div>
                </div>
              </div>

              {/* Cliente contatos — phone/email se houver */}
              {data.contact && (data.contact.mobile || data.contact.email) && (
                <Section title="Cliente" icon={User}>
                  <div className="space-y-1 text-sm">
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
                  </div>
                </Section>
              )}

              {/* Endereço extra (se NÃO mostrado em KV grid acima por truncate) */}
              {data.delivery_address && data.delivery_address.length > 40 && (
                <Section title="Endereço de entrega" icon={MapPin}>
                  <p className="text-sm text-muted-foreground break-words leading-relaxed">
                    {data.delivery_address}
                  </p>
                </Section>
              )}

              {/* ─── SEÇÃO 2: OBSERVAÇÃO (rental.notes) ─── */}
              <Section title="Observação" icon={FileText}>
                {data.notes ? (
                  <p className="text-sm text-foreground whitespace-pre-wrap leading-relaxed">
                    {data.notes}
                  </p>
                ) : (
                  <p className="text-sm italic text-muted-foreground">
                    — sem observação registrada
                  </p>
                )}
              </Section>

              {/* ─── SEÇÃO PEÇAS & MÃO DE OBRA (Wave 2.3 US-OFICINA-027) ───
                  Consome `data.items[]` populado pelo endpoint ServiceOrderController::show()
                  via relação ServiceOrder::items() (Wave 1.1). Lista peças + mão-de-obra +
                  serviços terceiros lançados na OS com qty × valor_unitario = valor_total.
                  Footer: TOTAL OS soma (espelha data.items_total backend) — alimentação direta
                  do ServiceOrderObserver::computeFinalTotal (Wave 1.2). */}
              <Section title="Peças & Mão de obra" icon={Package}>
                {data.items && data.items.length > 0 ? (
                  <>
                    <ul className="divide-y divide-slate-100 border border-slate-200 rounded-md overflow-hidden bg-white">
                      {data.items.map((item) => {
                        const Icon = ITEM_TIPO_ICON[item.tipo];
                        const qtd = toFloat(item.quantidade);
                        const vu = toFloat(item.valor_unitario);
                        const total = toFloat(item.valor_total);
                        return (
                          <li
                            key={item.id}
                            className="px-3 py-2 grid grid-cols-[auto_1fr_auto] gap-2 items-center text-[11.5px]"
                          >
                            <Icon size={14} className="text-muted-foreground shrink-0" aria-hidden />
                            <div className="min-w-0">
                              <div className="text-foreground font-medium truncate">
                                {item.descricao}
                              </div>
                              <div className="text-muted-foreground text-[10.5px]">
                                {ITEM_TIPO_LABEL[item.tipo]} · {qtd.toLocaleString('pt-BR', { maximumFractionDigits: 3 })}
                                {' × '}
                                {formatBRL(vu)}
                              </div>
                            </div>
                            <div className="text-foreground tabular-nums font-medium">
                              {formatBRL(total)}
                            </div>
                          </li>
                        );
                      })}
                    </ul>
                    <div className="mt-2 flex items-center justify-between px-1">
                      <span className="text-[10.5px] uppercase tracking-wider text-muted-foreground">
                        Total OS
                      </span>
                      <span className="text-sm tabular-nums font-semibold text-emerald-700">
                        {formatBRL(data.items_total ?? 0)}
                      </span>
                    </div>
                  </>
                ) : (
                  <p className="text-sm italic text-muted-foreground">
                    — nenhum item lançado ainda
                    {data.order_type === 'manutencao' ? ' (cobrança não fechará automática)' : ''}
                  </p>
                )}
              </Section>

              {/* ─── SEÇÃO 3: FOTOS & LAUDO (placeholders V2) ─── */}
              <Section title="Fotos & Laudo" icon={Camera}>
                <div className="grid grid-cols-3 gap-1.5">
                  <PhotoPlaceholder label="entrega" />
                  <PhotoPlaceholder label="local" />
                  <PhotoPlaceholder label="assinatura" />
                </div>
                <Button
                  size="sm"
                  variant="ghost"
                  className="mt-2 text-xs h-7"
                  disabled
                  title="Upload de foto — disponível em V2 (Modules/Arquivos integration)"
                >
                  <Camera size={11} className="mr-1.5" />
                  + Adicionar foto
                </Button>
              </Section>

              {/* ─── SEÇÃO 4: PIPELINE FSM (REUSO PR #729) ─── */}
              <Section title="Pipeline FSM" icon={CheckCircle2}>
                <ServiceOrderFsmActionPanel
                  serviceOrderId={data.id}
                  enabled={open}
                  onTransition={handleFsmTransition}
                />
              </Section>

              {/* ─── SEÇÃO 5: LINHA DO TEMPO (placeholder skeleton V2) ─── */}
              <Section title="Linha do tempo" icon={Clock}>
                <TimelineSkeleton
                  enteredAt={data.entered_at}
                  expectedReturn={data.expected_return_date}
                  completedAt={data.completed_at}
                  status={data.status}
                />
              </Section>
            </div>

            {/* Footer ações sticky (Wave 2.4 US-OFICINA-027) ───
                3 botões espelhando protótipo Cowork canon (screenshot Wagner 2026-05-26):
                - "Conversa cliente" → wa.me direct link (LGPD: sem mensagem pré-formatada com PII)
                - "Imprimir OS" → printServiceOrder helper (IFRAME oculto + A4 nota-fiscal-like)
                                  Gap 3 US-OFICINA-037 — substitui window.print() bare (AppShellV2 vazado).
                - "Editar OS" → fallback href edit (Avançar etapa fica no Pipeline FSM section, não duplica) */}
            <div className="border-t border-border px-6 py-3 bg-background flex items-center justify-end gap-2">
              {data.contact?.mobile && (
                <Button size="sm" variant="ghost" asChild>
                  <a
                    href={`https://wa.me/${data.contact.mobile.replace(/\D/g, '')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    title="Abrir conversa WhatsApp com cliente"
                  >
                    <MessageCircle size={14} className="mr-1.5" />
                    Conversa cliente
                  </a>
                </Button>
              )}
              <Button
                size="sm"
                variant="ghost"
                onClick={async () => {
                  try {
                    await printServiceOrder({
                      printUrl: `/oficina-auto/ordens-servico/${data.id}/print`,
                      osNumber: data.number ?? data.id,
                    });
                  } catch (e) {
                    toast.error(
                      e instanceof Error ? e.message : 'Falha ao gerar impressão.',
                    );
                  }
                }}
                title="Imprimir OS · A4 nota-fiscal-like (Ctrl+P alternativa)"
              >
                <Printer size={14} className="mr-1.5" />
                Imprimir OS
              </Button>
              {data.urls?.edit && (
                <Button size="sm" asChild>
                  <a href={data.urls.edit}>
                    <Edit size={14} className="mr-1.5" />
                    Editar OS
                  </a>
                </Button>
              )}
            </div>
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
  icon?: typeof Truck;
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

function PhotoPlaceholder({ label }: { label: string }) {
  return (
    <div
      className="aspect-square rounded border border-slate-200 grid place-items-center text-center font-mono text-[9px] text-slate-400 leading-tight p-2"
      style={{
        background:
          'repeating-linear-gradient(45deg, oklch(0.92 0.005 90) 0 8px, oklch(0.95 0.005 90) 8px 16px)',
      }}
      role="img"
      aria-label={`Placeholder foto ${label}`}
    >
      FOTO
      <br />·{label}
    </div>
  );
}

function StatusBadge({
  status,
  orderType,
}: {
  status: string;
  orderType: OrderType;
}) {
  const isLocacao = orderType === 'locacao';
  const cls = isLocacao
    ? 'border-blue-200 bg-blue-50 text-blue-700'
    : 'border-amber-200 bg-amber-50 text-amber-700';
  return (
    <span
      className={
        'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium ' +
        cls
      }
    >
      {isLocacao ? <Truck size={10} className="mr-1" /> : null}
      {status}
    </span>
  );
}

/**
 * Timeline skeleton derivada dos campos disponíveis (entered_at, expected_return,
 * completed_at, status). V2: fetch real via /oficina-auto/service-orders/{id}/fsm/history.
 */
function TimelineSkeleton({
  enteredAt,
  expectedReturn,
  completedAt,
  status,
}: {
  enteredAt: string | null;
  expectedReturn: string | null;
  completedAt: string | null;
  status: string;
}) {
  const items: Array<{ when: string; what: string; state: 'done' | 'now' | 'future' }> = [];

  if (enteredAt) {
    items.push({
      when: formatDate(enteredAt),
      what: 'Locação iniciada',
      state: 'done',
    });
  }

  // Estado intermediário (entrega presumida)
  if (enteredAt && status !== 'aberta') {
    items.push({
      when: '—',
      what: 'Caçamba entregue ao cliente',
      state: 'done',
    });
  }

  if (expectedReturn) {
    items.push({
      when: formatDate(expectedReturn + 'T18:00:00'),
      what: 'Prazo de devolução',
      state: completedAt ? 'done' : 'future',
    });
  }

  if (completedAt) {
    items.push({
      when: formatDate(completedAt),
      what: 'Locação concluída',
      state: 'done',
    });
  } else {
    items.push({
      when: 'agora',
      what: 'Aguardando recolhimento…',
      state: 'now',
    });
  }

  return (
    <div className="relative pl-4 space-y-0">
      <span className="absolute top-1.5 bottom-1.5 left-1 w-px bg-slate-200" aria-hidden="true" />
      {items.map((item, i) => (
        <div key={i} className="relative pl-2 pb-3 text-[11.5px]">
          <span
            className={
              'absolute -left-[10px] top-[5px] w-[7px] h-[7px] rounded-full border-[1.5px] ' +
              (item.state === 'done'
                ? 'bg-emerald-500 border-emerald-600'
                : item.state === 'now'
                  ? 'bg-rose-500 border-rose-600 ring-2 ring-rose-100'
                  : 'bg-white border-slate-300')
            }
            aria-hidden="true"
          />
          <div className="text-slate-400 text-[10.5px] tabular-nums">{item.when}</div>
          <div
            className={
              item.state === 'now' ? 'text-foreground font-medium' : 'text-foreground'
            }
          >
            {item.what}
          </div>
        </div>
      ))}
      <p className="text-[10.5px] italic text-muted-foreground pt-1">
        Histórico FSM completo em V2 (timeline auditável de transições).
      </p>
    </div>
  );
}
