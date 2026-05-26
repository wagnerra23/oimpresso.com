// @memcofre tela=/oficina-auto/ordens-servico/{id}/edit module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Edição de OS em Sheet lateral.
// Wave 5 US-OFICINA-005-bis (2026-05-26): section inline "Itens da OS".
// 2026-05-26: refatorado de Page fullscreen → Sheet 720px lateral (Wagner pedido —
// layout validado cliente Martinho). Espelha Create #1676 pattern drawer.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-edit.md

import { useCallback, useMemo, useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Wrench, Save, Package } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import ServiceOrderItemRow, {
  type ServiceOrderItemDto,
} from './_components/ServiceOrderItemRow';
import ServiceOrderItemFormSheet from './_components/ServiceOrderItemFormSheet';

interface ServiceOrder {
  id: number;
  vehicle_id: number;
  transaction_id: number | null;
  status: string;
  mileage_at_service: number | null;
  entered_at: string | null;
  expected_completion: string | null;
  completed_at: string | null;
  delivered_at: string | null;
  notes: string | null;
  items?: ServiceOrderItemDto[];
}

interface Vehicle {
  id: number;
  plate: string;
  secondary_plate: string | null;
  vehicle_type: string;
}

interface Props {
  order: ServiceOrder;
  vehicles: Vehicle[];
  statuses: Record<string, string>;
}

function toLocalInput(value: string | null): string {
  if (!value) return '';
  return new Date(value).toISOString().slice(0, 16);
}

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

function toFloat(value: number | string | null | undefined): number {
  if (value === null || value === undefined || value === '') return 0;
  const n = typeof value === 'string' ? parseFloat(value) : value;
  return Number.isNaN(n) ? 0 : n;
}

function formatBRL(value: number | string | null | undefined): string {
  return toFloat(value).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ServiceOrdersEdit({ order, vehicles, statuses }: Props) {
  // Sheet abre por default (rota /edit dedicada); fechar volta pra Show ou Producao
  const [open, setOpen] = useState(true);

  const { data, setData, put, processing, errors } = useForm({
    vehicle_id: String(order.vehicle_id),
    transaction_id: order.transaction_id ? String(order.transaction_id) : '',
    mileage_at_service: order.mileage_at_service?.toString() ?? '',
    status: order.status,
    entered_at: toLocalInput(order.entered_at),
    expected_completion: toLocalInput(order.expected_completion),
    completed_at: toLocalInput(order.completed_at),
    delivered_at: toLocalInput(order.delivered_at),
    notes: order.notes ?? '',
  });

  // ─── Items section state (Wave 5 US-OFICINA-005-bis) ─────────────────────
  const [items, setItems] = useState<ServiceOrderItemDto[]>(order.items ?? []);
  const [sheetOpen, setSheetOpen] = useState(false);
  const [editingItem, setEditingItem] = useState<ServiceOrderItemDto | null>(null);
  const [busyItemId, setBusyItemId] = useState<number | null>(null);

  const totalOs = useMemo(
    () => items.reduce((sum, it) => sum + toFloat(it.valor_total), 0),
    [items],
  );

  const handleAdd = useCallback(() => {
    setEditingItem(null);
    setSheetOpen(true);
  }, []);

  const handleEditItem = useCallback((item: ServiceOrderItemDto) => {
    setEditingItem(item);
    setSheetOpen(true);
  }, []);

  const handleSaved = useCallback((saved: ServiceOrderItemDto) => {
    setItems((prev) => {
      const idx = prev.findIndex((it) => it.id === saved.id);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = saved;
        return next;
      }
      return [...prev, saved];
    });
  }, []);

  const handleDelete = useCallback(
    async (item: ServiceOrderItemDto) => {
      if (!window.confirm(`Excluir item "${item.descricao}"?`)) return;
      setBusyItemId(item.id);
      const previousItems = items;
      setItems((prev) => prev.filter((it) => it.id !== item.id));
      try {
        const res = await fetch(
          `/oficina-auto/ordens-servico/${order.id}/items/${item.id}`,
          {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
              Accept: 'application/json',
              'X-CSRF-TOKEN': getCsrfToken(),
              'X-Requested-With': 'XMLHttpRequest',
            },
          },
        );
        if (!res.ok) {
          setItems(previousItems);
          const json = await res.json().catch(() => ({}));
          toast.error(json?.message ?? `Erro HTTP ${res.status}`);
          return;
        }
        toast.success('Item excluído.');
      } catch (e) {
        setItems(previousItems);
        toast.error(e instanceof Error ? e.message : 'Erro de rede.');
      } finally {
        setBusyItemId(null);
      }
    },
    [items, order.id],
  );

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    put(`/oficina-auto/ordens-servico/${order.id}`);
  }

  function handleClose() {
    setOpen(false);
    // Aguardar animação de fechamento antes de navegar (200ms shadcn default)
    setTimeout(() => {
      // Preferir referer (drawer abriu de Producao/Show, volta pra origem)
      const referer = document.referrer;
      if (referer && referer.includes('/oficina-auto/')) {
        router.visit(referer);
      } else {
        router.visit(`/oficina-auto/ordens-servico/${order.id}`);
      }
    }, 200);
  }

  return (
    <AppShellV2>
      <Head title={`Editar OS #${order.id} · Oficina Auto`} />
      <Sheet open={open} onOpenChange={(o) => !o && handleClose()}>
        <SheetContent
          side="right"
          className="w-full sm:max-w-[720px] overflow-y-auto"
        >
          <SheetHeader className="space-y-1 pb-3 border-b">
            <SheetTitle className="flex items-center gap-2 text-lg">
              <Wrench className="size-5" />
              Editar OS #{order.id}
            </SheetTitle>
            <SheetDescription className="text-xs">
              Atualizar status, datas, observações e itens
            </SheetDescription>
          </SheetHeader>

          <form onSubmit={handleSubmit} className="space-y-4 pt-4 px-1 pb-24">
            <div>
              <Label htmlFor="vehicle_id">Veículo *</Label>
              <select
                id="vehicle_id"
                value={data.vehicle_id}
                onChange={(e) => setData('vehicle_id', e.target.value)}
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                required
              >
                {vehicles.map((v) => (
                  <option key={v.id} value={v.id}>
                    {v.plate}
                    {v.secondary_plate ? ` + ${v.secondary_plate}` : ''}{' '}
                    ({v.vehicle_type})
                  </option>
                ))}
              </select>
              {errors.vehicle_id && (
                <p className="text-sm text-destructive mt-1">{errors.vehicle_id}</p>
              )}
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="status">Status *</Label>
                <select
                  id="status"
                  value={data.status}
                  onChange={(e) => setData('status', e.target.value)}
                  className="w-full rounded-md border bg-background px-3 py-2 text-sm"
                >
                  {Object.entries(statuses).map(([k, label]) => (
                    <option key={k} value={k}>
                      {label}
                    </option>
                  ))}
                </select>
              </div>
              <div>
                <Label htmlFor="mileage_at_service">KM na entrada</Label>
                <Input
                  id="mileage_at_service"
                  type="number"
                  value={data.mileage_at_service}
                  onChange={(e) => setData('mileage_at_service', e.target.value)}
                  min={0}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label htmlFor="entered_at">Entrada</Label>
                <Input
                  id="entered_at"
                  type="datetime-local"
                  value={data.entered_at}
                  onChange={(e) => setData('entered_at', e.target.value)}
                />
              </div>
              <div>
                <Label htmlFor="expected_completion">Previsão</Label>
                <Input
                  id="expected_completion"
                  type="datetime-local"
                  value={data.expected_completion}
                  onChange={(e) => setData('expected_completion', e.target.value)}
                />
              </div>
              <div>
                <Label htmlFor="completed_at">Concluída em</Label>
                <Input
                  id="completed_at"
                  type="datetime-local"
                  value={data.completed_at}
                  onChange={(e) => setData('completed_at', e.target.value)}
                />
              </div>
              <div>
                <Label htmlFor="delivered_at">Entregue em</Label>
                <Input
                  id="delivered_at"
                  type="datetime-local"
                  value={data.delivered_at}
                  onChange={(e) => setData('delivered_at', e.target.value)}
                />
              </div>
            </div>

            <div>
              <Label htmlFor="notes">Observações</Label>
              <textarea
                id="notes"
                value={data.notes}
                onChange={(e) => setData('notes', e.target.value)}
                rows={4}
                className="w-full rounded-md border bg-background px-3 py-2 text-sm"
              />
            </div>

            {/* ───── Section inline "Itens da OS" (Wave 5 US-OFICINA-005-bis) ───── */}
            <section className="pt-2">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Package className="size-4 text-muted-foreground" />
                  <h2 className="text-sm font-semibold text-foreground">Itens da OS</h2>
                  {items.length > 0 && (
                    <span className="text-[11px] text-muted-foreground tabular-nums">
                      ({items.length})
                    </span>
                  )}
                </div>
                <PageHeaderPrimary
                  label="Adicionar item"
                  icon={Package}
                  onClick={handleAdd}
                  data-testid="add-item-button-edit"
                />
              </div>

              {items.length > 0 ? (
                <>
                  <ul className="divide-y divide-slate-100 border border-slate-200 rounded-md overflow-hidden bg-white">
                    {items.map((item) => (
                      <ServiceOrderItemRow
                        key={item.id}
                        item={item}
                        onEdit={handleEditItem}
                        onDelete={handleDelete}
                        busy={busyItemId === item.id}
                      />
                    ))}
                  </ul>
                  <div className="mt-2 flex items-center justify-between px-1">
                    <span className="text-[10.5px] uppercase tracking-wider text-muted-foreground">
                      Total OS
                    </span>
                    <span className="text-sm tabular-nums font-semibold text-emerald-700">
                      {formatBRL(totalOs)}
                    </span>
                  </div>
                </>
              ) : (
                <div className="rounded-md border border-dashed border-slate-200 p-6 text-center">
                  <Package className="size-5 mx-auto text-muted-foreground mb-2" />
                  <p className="text-sm text-muted-foreground">
                    Nenhum item lançado ainda.
                  </p>
                  <p className="text-[11px] text-muted-foreground/80 mt-1">
                    Use "Adicionar item" pra lançar peças, mão-de-obra ou serviços.
                  </p>
                </div>
              )}
            </section>

            {/* Footer sticky bottom — cockpit pattern (Create #1676) */}
            <div className="flex justify-end gap-2 pt-4 border-t sticky bottom-0 bg-background -mx-1 px-1 pb-2">
              <Button variant="outline" type="button" onClick={handleClose}>
                Cancelar
              </Button>
              <Button type="submit" disabled={processing}>
                <Save className="size-4 mr-1" />
                Salvar alterações
              </Button>
            </div>
          </form>
        </SheetContent>
      </Sheet>

      <ServiceOrderItemFormSheet
        serviceOrderId={order.id}
        item={editingItem}
        open={sheetOpen}
        onOpenChange={setSheetOpen}
        onSaved={handleSaved}
      />
    </AppShellV2>
  );
}
