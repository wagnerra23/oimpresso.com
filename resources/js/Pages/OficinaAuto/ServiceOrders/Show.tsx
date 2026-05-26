// @memcofre tela=/oficina-auto/ordens-servico/{id} module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Detalhe OS.
// Wave 5 US-OFICINA-005-bis (2026-05-26): seção "Itens da OS" com CTA Adicionar
// via Sheet 480px lateral. Depende do backend PR #1624 (ServiceOrderItemController).
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-show.md

import { useCallback, useMemo, useState } from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { Wrench, ArrowLeft, Edit, Package } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import ServiceOrderItemRow, {
  type ServiceOrderItemDto,
} from './_components/ServiceOrderItemRow';
import ServiceOrderItemFormSheet from './_components/ServiceOrderItemFormSheet';

interface ServiceOrder {
  id: number;
  status: string;
  entered_at: string | null;
  expected_completion: string | null;
  completed_at: string | null;
  delivered_at: string | null;
  mileage_at_service: number | null;
  transaction_id: number | null;
  notes: string | null;
  vehicle: {
    id: number;
    plate: string;
    secondary_plate: string | null;
    vehicle_type: string;
  } | null;
  items?: ServiceOrderItemDto[];
}

interface Props {
  order: ServiceOrder;
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

export default function ServiceOrdersShow({ order }: Props) {
  // Estado local de items (optimistic UI — espelha order.items inicial do payload Inertia).
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

  const handleEdit = useCallback((item: ServiceOrderItemDto) => {
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
      // Optimistic remove — restaura se DELETE falhar
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

  return (
    <AppShellV2>
      <Head title={`OS #${order.id} · Oficina Auto`} />
      <div className="px-4 py-6 max-w-4xl mx-auto">
        <PageHeader
          title={`OS #${order.id}`}
          subtitle={order.vehicle ? `${order.vehicle.plate} (${order.vehicle.vehicle_type})` : 'Sem veículo vinculado'}
          icon={<Wrench className="size-5" />}
          actions={
            <div className="flex gap-2">
              <Link href="/oficina-auto/ordens-servico">
                <Button variant="ghost">
                  <ArrowLeft className="size-4 mr-1" />
                  Voltar
                </Button>
              </Link>
              <Link href={`/oficina-auto/ordens-servico/${order.id}/edit`}>
                <Button>
                  <Edit className="size-4 mr-1" />
                  Editar
                </Button>
              </Link>
            </div>
          }
        />

        <div className="rounded-md border bg-card p-4">
          <dl className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
            <div>
              <dt className="text-muted-foreground">Status</dt>
              <dd className="font-medium">
                <span className="text-xs px-2 py-0.5 rounded bg-muted">{order.status}</span>
              </dd>
            </div>
            <div>
              <dt className="text-muted-foreground">KM na entrada</dt>
              <dd className="font-medium tabular-nums">{order.mileage_at_service?.toLocaleString('pt-BR') ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Entrada</dt>
              <dd>{order.entered_at ? new Date(order.entered_at).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Previsão entrega</dt>
              <dd>{order.expected_completion ? new Date(order.expected_completion).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Conclusão</dt>
              <dd>{order.completed_at ? new Date(order.completed_at).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            <div>
              <dt className="text-muted-foreground">Entrega cliente</dt>
              <dd>{order.delivered_at ? new Date(order.delivered_at).toLocaleString('pt-BR') : '—'}</dd>
            </div>
            {order.transaction_id && (
              <div className="col-span-2">
                <dt className="text-muted-foreground">Venda vinculada</dt>
                <dd className="font-mono">#{order.transaction_id}</dd>
              </div>
            )}
            {order.vehicle && (
              <div className="col-span-2">
                <dt className="text-muted-foreground">Veículo</dt>
                <dd>
                  <Link href={`/oficina-auto/veiculos/${order.vehicle.id}`} className="font-mono hover:underline">
                    {order.vehicle.plate}
                  </Link>
                  {order.vehicle.secondary_plate && <span className="text-muted-foreground"> + {order.vehicle.secondary_plate}</span>}
                </dd>
              </div>
            )}
          </dl>

          {order.notes && (
            <div className="mt-4 pt-4 border-t">
              <p className="text-sm font-medium mb-1">Observações</p>
              <p className="text-sm text-muted-foreground whitespace-pre-wrap">{order.notes}</p>
            </div>
          )}
        </div>

        {/* ──────────────────────────────────────────────────────────────────
            Seção "Itens da OS" — Wave 5 US-OFICINA-005-bis (2026-05-26).
            Lista peças/mão-de-obra/terceiros lançados via Controller PR #1624.
            CTA "Adicionar item" (PageHeaderPrimary roxo 295 ADR 0190) abre Sheet
            lateral 480px com form. Optimistic UI nos save/delete.
            ────────────────────────────────────────────────────────────────── */}
        <section className="mt-6">
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
              data-testid="add-item-button"
            />
          </div>

          {items.length > 0 ? (
            <>
              <ul className="divide-y divide-slate-100 border border-slate-200 rounded-md overflow-hidden bg-white">
                {items.map((item) => (
                  <ServiceOrderItemRow
                    key={item.id}
                    item={item}
                    onEdit={handleEdit}
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

        <ServiceOrderItemFormSheet
          serviceOrderId={order.id}
          item={editingItem}
          open={sheetOpen}
          onOpenChange={setSheetOpen}
          onSaved={handleSaved}
        />
      </div>
    </AppShellV2>
  );
}
