// ServiceOrderRichSheet — drawer rico polimórfico Produção · Oficina (origem
// CacambaProducaoSheet pré-ADR 0194 — renomeado 2026-05-26 Wave 2.2 US-OFICINA-027).
//
// Espelha 1:1 protótipo Cowork canon — locação caçamba CNAE 4581 hipotético
// (`prototipo-ui/prototipos/producao-oficina/visual-source.html`) E protótipo OS
// mecânica CNAE 4520 Martinho (screenshot Wagner 2026-05-26 · sub-vertical 4 ADR 0194).
//
// UNIFICAÇÃO 2026-06-11 (Onda 2 · [W] "drawer são os mesmos"): o CORPO rico foi
// extraído pro componente `ServiceOrderRichBody` (export nomeado) — usado por:
//   - este drawer (RichSheet = wrapper Sheet fino + body)
//   - a view Fila do workspace (detalhe inline, sem o Sheet) — ServiceOrders/Board.tsx
// Zero duplicação: 1 corpo, 2 chrome (drawer × inline). O ServiceOrderSheet simples
// foi aposentado nesta onda; este é o ÚNICO drawer de OS.
//
// 6 sections (reparo · ADR 0265):
//   1. Header KV grid: Cliente / KM / Box / Mecânico / Valor (items_total)
//   2. OBSERVAÇÃO (notes — italic se vazio)
//   3. PEÇAS & MÃO DE OBRA (data.items[] · US-OFICINA-027)
//   4. FOTOS & LAUDO (Modules/Arquivos)
//   5. PIPELINE FSM — embed ServiceOrderFsmActionPanel (REUSO PR #729)
//   6. LINHA DO TEMPO — ServiceOrderTimeline (sale_stage_history real)
//
// CRÍTICO React 19 — useMemo/useCallback nos handlers descendentes (lição PR #717).
// Multi-tenant Tier 0 [ADR 0093]: payload vem do endpoint show() (global scope).

import { useCallback, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import {
  AlertTriangle,
  Camera,
  CheckCircle2,
  ClipboardCheck,
  Clock,
  Edit,
  ExternalLink,
  FileText,
  ListChecks,
  Loader2,
  Phone,
  Truck,
  User,
} from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import MercosulPlate from '@/Components/shared/MercosulPlate';
import ServiceOrderFsmActionPanel from '../../ServiceOrders/_components/ServiceOrderFsmActionPanel';
import ServiceOrderTimeline from '../../ServiceOrders/_components/ServiceOrderTimeline';
import ServiceOrderStageGate from '../../ServiceOrders/_components/ServiceOrderStageGate';
import VendaDerivadaCard, { type VendaDerivada } from '@/Components/shared/VendaDerivadaCard';
import { MessageCircle, Printer, Package } from 'lucide-react';
import { toast } from 'sonner';
import { printServiceOrder } from '@/Lib/printServiceOrder';
import DviInlineEditor, { type DviInlineItem, type ApprovalInfo } from './DviInlineEditor';
import LaudoPhotoSection, { type LaudoPhoto } from './LaudoPhotoSection';
import ServiceOrderItemRow, {
  type ServiceOrderItemDto,
} from '../../ServiceOrders/_components/ServiceOrderItemRow';
import ServiceOrderItemFormSheet from '../../ServiceOrders/_components/ServiceOrderItemFormSheet';
import { Plus } from 'lucide-react';

type OrderType = 'manutencao' | 'mecanica' | null;

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
  current_stage?: { key: string; name: string } | null;
  order_type: OrderType;
  expected_completion: string | null;
  valor_receber: number | string | null;
  is_overdue?: boolean;
  entered_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  notes: string | null;
  vehicle: VehicleRel | null;
  contact: ContactRel | null;
  box_label?: string | null;
  assigned_user?: AssignedUserRel | null;
  mileage_at_service?: number | null;
  items?: ServiceOrderItemRel[];
  items_total?: number | string;
  dvi_items?: DviInlineItem[];
  laudo_photos?: LaudoPhoto[];
  approval?: ApprovalInfo | null;
  venda_derivada?: VendaDerivada | null;
  urls?: {
    edit?: string | null;
    show?: string | null;
  };
}

// Props do CORPO rico (compartilhado drawer × inline). `enabled` = a OS está visível
// (drawer aberto OU view Fila ativa com esta OS selecionada) → dispara o fetch e os
// sub-fetches (pipeline/timeline/gate).
interface BodyProps {
  serviceOrderId: number | null;
  enabled: boolean;
  onOrderChanged?: () => void;
}

// Props do DRAWER (wrapper Sheet). Interface inalterada (Board + Vehicles importam default).
interface Props {
  serviceOrderId: number | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Callback chamado quando OS muda (FSM transição etc) — pai pode refresh */
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

// ─── CORPO RICO (compartilhado drawer × Fila inline) ──────────────────────────
// Todo o conteúdo do drawer (fetch + estado + handlers + seções + footer), SEM o
// wrapper Sheet. O RichSheet embrulha num Sheet; a Fila renderiza inline.
export function ServiceOrderRichBody({ serviceOrderId, enabled, onOrderChanged }: BodyProps) {
  const [data, setData] = useState<ServiceOrderDetail | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [approvalSending, setApprovalSending] = useState(false);
  // Bump em toda transição FSM (incl. "Iniciar pipeline") — força o StageGate refetchar.
  const [fsmRefresh, setFsmRefresh] = useState(0);
  // F3 OS-V2-6 — lançar/editar/remover item inline.
  const [itemFormOpen, setItemFormOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<ServiceOrderItemDto | null>(null);
  const [itemBusy, setItemBusy] = useState(false);

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
    if (!serviceOrderId || !enabled) {
      setData(null);
      setError(null);
      return;
    }
    fetchData();
  }, [serviceOrderId, enabled, fetchData]);

  // Callback estável passada pro FsmActionPanel — evita re-render loop (lição PR #717).
  const handleFsmTransition = useCallback(() => {
    void fetchData();
    setFsmRefresh((n) => n + 1);
    onOrderChanged?.();
  }, [fetchData, onOrderChanged]);

  const handleItemAdd = useCallback(() => {
    setEditingItem(null);
    setItemFormOpen(true);
  }, []);

  const handleItemEdit = useCallback((item: ServiceOrderItemDto) => {
    setEditingItem(item);
    setItemFormOpen(true);
  }, []);

  const handleItemSaved = useCallback(() => {
    setItemFormOpen(false);
    setEditingItem(null);
    void fetchData();
    onOrderChanged?.();
  }, [fetchData, onOrderChanged]);

  const handleItemDelete = useCallback(
    async (item: ServiceOrderItemDto) => {
      if (!data) return;
      setItemBusy(true);
      try {
        const meta = document.querySelector('meta[name="csrf-token"]');
        const res = await fetch(
          `/oficina-auto/ordens-servico/${data.id}/items/${item.id}`,
          {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
              'X-CSRF-TOKEN': meta?.getAttribute('content') ?? '',
            },
          },
        );
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        toast.success('Item removido.');
        void fetchData();
        onOrderChanged?.();
      } catch (e) {
        toast.error(e instanceof Error ? e.message : 'Falha ao remover item.');
      } finally {
        setItemBusy(false);
      }
    },
    [data, fetchData, onOrderChanged],
  );

  const handlePedirAprovacao = useCallback(() => {
    if (!data) return;
    router.post(
      `/oficina-auto/ordens-servico/${data.id}/enviar-aprovacao`,
      {},
      {
        preserveScroll: true,
        preserveState: true,
        onStart: () => setApprovalSending(true),
        onSuccess: () => {
          toast.success('Orçamento da vistoria enviado para aprovação do cliente.');
          void fetchData();
          onOrderChanged?.();
        },
        onError: () => toast.error('Não foi possível enviar o pedido de aprovação.'),
        onFinish: () => setApprovalSending(false),
      },
    );
  }, [data, fetchData, onOrderChanged]);

  return (
    <>
      <div className="flex h-full min-h-0 flex-col">
        {loading && (
          <div className="flex-1 flex items-center justify-center">
            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
          </div>
        )}

        {error && !loading && (
          <div className="flex-1 flex items-center justify-center p-6 text-center">
            <div>
              <AlertTriangle className="h-8 w-8 text-destructive mx-auto mb-2" />
              <p className="text-sm text-foreground font-medium">
                Não foi possível carregar a OS
              </p>
              <p className="text-xs text-muted-foreground mt-1">{error}</p>
            </div>
          </div>
        )}

        {!loading && data && (
          <>
            {/* Header (canon .prod-drawer-head) — eyebrow "OS #103 · <etapa>", h2 =
                veículo/modelo, p = cliente. Plain divs (sem Sheet*) pra funcionar
                tanto no drawer quanto inline na Fila. */}
            <div className="px-5 pt-5 pb-4 border-b border-border">
              <div className="text-[11px] font-medium uppercase tracking-[0.05em] text-muted-foreground">
                <span className="font-mono">OS #{data.id}</span>
                {' · '}
                {data.current_stage?.name ?? capitalize(data.status)}
                {data.is_overdue && (
                  <span className="ml-1.5 font-semibold text-destructive">· Atrasada</span>
                )}
              </div>
              <h2 className="text-[17px] font-semibold leading-tight tracking-tight text-foreground mt-1 mb-0.5">
                {data.vehicle?.vehicle_type ? capitalize(data.vehicle.vehicle_type) : 'Veículo'}
                {data.vehicle?.model_year ? (
                  <span className="text-[13px] font-normal text-muted-foreground ml-1.5 tabular-nums">
                    · {data.vehicle.model_year}
                  </span>
                ) : null}
              </h2>
              <p className="text-[12.5px] text-muted-foreground">
                {data.contact ? data.contact.name : 'Cliente não informado'}
              </p>
            </div>

            {/* Conteúdo scroll — sections empilhadas (ordem TRAVADA · charter §1-§11). */}
            <div className="flex-1 overflow-y-auto px-5 py-5">

              <div className="space-y-4">

              {/* SEÇÃO 2 (TRAVADA): Card Vendas×Oficina (D-09) — acende só quando a OS gerou venda. */}
              {data.venda_derivada && (
                <div className="-mx-5">
                  <VendaDerivadaCard venda={data.venda_derivada} />
                </div>
              )}

              {/* SEÇÃO 3 (Hero · Card Cliente/Valor): placa Mercosul + KV de reparo. */}
              {data.vehicle && (
                <div className="rounded-[10px] border border-success/50 bg-gradient-to-br from-success/10 to-card px-[18px] py-4 grid grid-cols-[auto_1fr] gap-3.5 items-center">
                  <MercosulPlate plate={data.vehicle.plate} size="md" />
                  <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-[11.5px] self-center">
                    {data.contact && (
                      <>
                        <dt className="text-muted-foreground">Cliente</dt>
                        <dd className="text-foreground font-medium truncate text-right">
                          {data.contact.name}
                        </dd>
                      </>
                    )}
                    {data.mileage_at_service != null && (
                      <>
                        <dt className="text-muted-foreground">KM</dt>
                        <dd className="text-foreground font-medium tabular-nums text-right">
                          {data.mileage_at_service.toLocaleString('pt-BR')}
                        </dd>
                      </>
                    )}
                    {data.box_label && (
                      <>
                        <dt className="text-muted-foreground">Box</dt>
                        <dd className="text-foreground text-right">{data.box_label}</dd>
                      </>
                    )}
                    {data.assigned_user && (
                      <>
                        <dt className="text-muted-foreground">Mecânico</dt>
                        <dd className="text-foreground text-right truncate">{data.assigned_user.name}</dd>
                      </>
                    )}
                    <dt className="text-muted-foreground self-center">Valor</dt>
                    <dd className="tabular-nums font-bold text-success text-right text-[15px]">
                      {formatBRL(data.items_total ?? 0)}
                    </dd>
                  </dl>
                </div>
              )}

              {/* Datas — linha de meta compacta. */}
              <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1.5 text-[12px] px-0.5">
                <dt className="text-muted-foreground">Início</dt>
                <dd className="text-foreground tabular-nums text-right">
                  {formatDateOnly(data.started_at ?? data.entered_at)}
                </dd>
                <dt className={data.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground'}>
                  Prazo
                </dt>
                <dd
                  className={
                    'tabular-nums text-right ' +
                    (data.is_overdue ? 'text-destructive font-semibold' : 'text-foreground')
                  }
                >
                  {formatDateOnly(data.expected_completion)}
                </dd>
              </dl>

              </div>{/* /bloco-topo */}

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

              {/* SEÇÃO 2: OBSERVAÇÃO (notes) */}
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

              {/* SEÇÃO VISTORIA DIGITAL · DVI (F3 OS-V2-2) — semáforo inline editável. */}
              <Section title="Vistoria Digital · DVI" icon={ClipboardCheck}>
                <DviInlineEditor
                  key={data.id}
                  serviceOrderId={data.id}
                  initialItems={data.dvi_items ?? []}
                  onPedirAprovacao={handlePedirAprovacao}
                  approvalSending={approvalSending}
                  approval={data.approval ?? null}
                />
              </Section>

              {/* SEÇÃO FOTOS & LAUDO (F3 OS-V2-1) */}
              <Section title="Fotos & Laudo" icon={Camera}>
                <LaudoPhotoSection
                  key={data.id}
                  serviceOrderId={data.id}
                  initialPhotos={data.laudo_photos ?? []}
                />
              </Section>

              {/* SEÇÃO PEÇAS & MÃO DE OBRA (Wave 2.3 US-OFICINA-027) */}
              <Section title="Peças & Mão de obra" icon={Package}>
                {data.items && data.items.length > 0 ? (
                  <>
                    <ul className="divide-y divide-border border border-border rounded-md overflow-hidden bg-white">
                      {data.items.map((item) => (
                        <ServiceOrderItemRow
                          key={item.id}
                          item={item}
                          onEdit={handleItemEdit}
                          onDelete={handleItemDelete}
                          busy={itemBusy}
                        />
                      ))}
                    </ul>
                    <div className="mt-2 flex items-center justify-between px-1">
                      <span className="text-[10.5px] uppercase tracking-wider text-muted-foreground">
                        Total OS
                      </span>
                      <span className="text-sm tabular-nums font-semibold text-success">
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

                <Button
                  type="button"
                  size="sm"
                  variant="outline"
                  className="mt-2 h-11 w-full gap-1.5 sm:h-9"
                  onClick={handleItemAdd}
                  disabled={itemBusy}
                >
                  <Plus size={14} aria-hidden />
                  Adicionar item
                </Button>
              </Section>

              {/* SEÇÃO CHECKLIST DE ETAPA (F3 OS-V2-5) */}
              <Section title="Checklist de etapa" icon={ListChecks}>
                <ServiceOrderStageGate
                  serviceOrderId={data.id}
                  enabled={enabled}
                  onChanged={handleFsmTransition}
                  refreshToken={fsmRefresh}
                />
              </Section>

              {/* SEÇÃO 4: PIPELINE FSM (REUSO PR #729) */}
              <Section title="Pipeline FSM" icon={CheckCircle2}>
                <ServiceOrderFsmActionPanel
                  serviceOrderId={data.id}
                  enabled={enabled}
                  onTransition={handleFsmTransition}
                />
              </Section>

              {/* SEÇÃO 5: LINHA DO TEMPO FSM AUDITÁVEL (F3 OS-V2-4) */}
              <Section title="Linha do tempo" icon={Clock}>
                <ServiceOrderTimeline
                  serviceOrderId={data.id}
                  enabled={enabled}
                  fallback={
                    <TimelineSkeleton
                      enteredAt={data.entered_at}
                      expectedReturn={data.expected_completion}
                      completedAt={data.completed_at}
                      status={data.status}
                    />
                  }
                />
              </Section>
            </div>

            {/* Footer ações sticky (Wave 2.4 US-OFICINA-027) */}
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
      </div>

      {/* F3 OS-V2-6 — drawer nested pra criar/editar item (não fecha o pai). */}
      {data && (
        <ServiceOrderItemFormSheet
          serviceOrderId={data.id}
          item={editingItem}
          open={itemFormOpen}
          onOpenChange={setItemFormOpen}
          onSaved={handleItemSaved}
        />
      )}
    </>
  );
}

// ─── DRAWER (wrapper Sheet fino) ──────────────────────────────────────────────
// Mantém a interface pública (default export) que Board + Vehicles importam.
// O conteúdo é o ServiceOrderRichBody acima — o MESMO da view Fila (zero duplicação).
export default function ServiceOrderRichSheet({
  serviceOrderId,
  open,
  onOpenChange,
  onOrderChanged,
}: Props) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="w-full sm:max-w-[480px] flex flex-col p-0 overflow-hidden"
      >
        {/* SheetTitle obrigatório pra a11y do Radix Dialog; o título visível vive no
            header do body. sr-only mantém acessível sem duplicar visualmente. */}
        <SheetHeader className="sr-only">
          <SheetTitle>Ordem de serviço</SheetTitle>
        </SheetHeader>
        <ServiceOrderRichBody
          serviceOrderId={serviceOrderId}
          enabled={open}
          onOrderChanged={onOrderChanged}
        />
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
  // Canon .ofc-drawer-section — separadas por border-top fino; h4 10.5px/600 uppercase.
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

/**
 * Timeline skeleton derivada dos campos disponíveis (entered_at, prazo,
 * completed_at, status). Vocabulário de REPARO (ADR 0265).
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
      what: 'OS aberta — veículo recebido',
      state: 'done',
    });
  }

  if (enteredAt && status !== 'aberta') {
    items.push({
      when: '—',
      what: 'Serviço em andamento na oficina',
      state: 'done',
    });
  }

  if (expectedReturn) {
    items.push({
      when: formatDate(expectedReturn.length <= 10 ? expectedReturn + 'T18:00:00' : expectedReturn),
      what: 'Previsão de entrega',
      state: completedAt ? 'done' : 'future',
    });
  }

  if (completedAt) {
    items.push({
      when: formatDate(completedAt),
      what: 'Serviço concluído',
      state: 'done',
    });
  } else {
    items.push({
      when: 'agora',
      what: 'Aguardando próxima etapa…',
      state: 'now',
    });
  }

  return (
    <div className="relative pl-4 space-y-0">
      <span className="absolute top-1.5 bottom-1.5 left-1 w-px bg-border" aria-hidden="true" />
      {items.map((item, i) => (
        <div key={i} className="relative pl-2 pb-3 text-[11.5px]">
          <span
            className={
              'absolute -left-[10px] top-[5px] w-[7px] h-[7px] rounded-full border-[1.5px] ' +
              (item.state === 'done'
                ? 'bg-success border-success'
                : item.state === 'now'
                  ? 'bg-destructive border-destructive ring-2 ring-destructive/20'
                  : 'bg-white border-border')
            }
            aria-hidden="true"
          />
          <div className="text-muted-foreground/60 text-[10.5px] tabular-nums">{item.when}</div>
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
