// @memcofre tela=/repair/repair/{id} module=Repair
// Wave 3 B6 MWART — Repair Show (venda-de-reparo, Transaction sub_type=repair).
// FSM Sells panel opcional via flag mwart.repair_show_fsm_panel.enabled.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, Deferred } from '@inertiajs/react';
import { Edit3, Printer, FileText, ShoppingCart, Wrench } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';

interface SellPayload {
  id: number;
  invoice_no: string | null;
  transaction_date: string | null;
  repair_due_date: string | null;
  contact_id: number | null;
  contact_name: string | null;
  final_total: number;
  final_total_formatted: string | null;
  payment_status: string | null;
  status: {
    id: number | null;
    name: string | null;
    color: string | null;
  };
  device_model_name: string | null;
  serial_no: string | null;
  defects: string | null;
  warranty_name: string | null;
  sell_lines: Array<{
    id: number;
    product_name: string;
    quantity: number;
    unit_price: number;
    total: number;
  }>;
  payments: Array<{
    id: number;
    method: string;
    amount: number;
    paid_on: string;
  }>;
}

interface Props {
  sell: SellPayload;
  payment_types: Record<string, string>;
  order_taxes: unknown;
  warranty_expires_in: string | null;
  is_warranty_enabled: boolean;
  checklists: string[];
  activities?: Array<{ id: number; description: string; causer: string | null; created_at: string }>;
  fsm: {
    enabled: boolean;
    sale_id: number;
  };
}

const STAGE_COLOR_MAP: Record<string, string> = {
  gray: 'bg-gray-100 text-gray-700',
  slate: 'bg-slate-100 text-slate-700',
  blue: 'bg-blue-100 text-blue-700',
  green: 'bg-green-100 text-green-700',
  amber: 'bg-amber-100 text-amber-700',
  red: 'bg-red-100 text-red-700',
};

export default function RepairShow({ sell, warranty_expires_in, is_warranty_enabled, checklists, fsm }: Props) {
  const statusClass =
    STAGE_COLOR_MAP[sell.status?.color ?? 'gray'] ?? STAGE_COLOR_MAP.gray;

  return (
    <AppShellV2>
      <div className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="wrench"
          title={`Venda de Reparo #${sell.invoice_no ?? sell.id}`}
          description={sell.contact_name ?? 'Sem cliente'}
          action={
            <div className="flex gap-2">
              <Button variant="outline" size="sm" asChild>
                <Link href={`/repair/repair/${sell.id}/edit`}>
                  <Edit3 className="mr-1 h-4 w-4" /> Editar
                </Link>
              </Button>
              <Button variant="outline" size="sm" asChild>
                <a
                  href={`/repair/print-repair/${sell.id}/customer-copy`}
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <Printer className="mr-1 h-4 w-4" /> Via do cliente
                </a>
              </Button>
            </div>
          }
        />

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          <div className="lg:col-span-2 space-y-4">
            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <ShoppingCart className="h-4 w-4" /> Detalhes da venda
              </h2>
              <dl className="grid grid-cols-2 gap-3 text-sm">
                <div>
                  <dt className="text-xs text-muted-foreground">Status</dt>
                  <dd>
                    {sell.status?.name ? (
                      <span className={`inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ${statusClass}`}>
                        {sell.status.name}
                      </span>
                    ) : (
                      '—'
                    )}
                  </dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Pagamento</dt>
                  <dd className="capitalize">{sell.payment_status ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Data da venda</dt>
                  <dd>{sell.transaction_date ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Prazo de entrega</dt>
                  <dd>{sell.repair_due_date ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Aparelho</dt>
                  <dd>{sell.device_model_name ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Nº de série</dt>
                  <dd>{sell.serial_no ?? '—'}</dd>
                </div>
                <div className="col-span-2">
                  <dt className="text-xs text-muted-foreground">Defeitos</dt>
                  <dd className="whitespace-pre-wrap">{sell.defects ?? '—'}</dd>
                </div>
                <div>
                  <dt className="text-xs text-muted-foreground">Valor total</dt>
                  <dd className="font-semibold">{sell.final_total_formatted ?? sell.final_total}</dd>
                </div>
                {is_warranty_enabled && (
                  <div>
                    <dt className="text-xs text-muted-foreground">Garantia</dt>
                    <dd>{sell.warranty_name ?? '—'} {warranty_expires_in ? `(${warranty_expires_in})` : ''}</dd>
                  </div>
                )}
              </dl>
            </section>

            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <Wrench className="h-4 w-4" /> Linhas (peças/serviços)
              </h2>
              {sell.sell_lines && sell.sell_lines.length > 0 ? (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b text-left text-xs text-muted-foreground">
                      <th className="py-2 px-2">Produto</th>
                      <th className="py-2 px-2 w-20 text-right">Qtd</th>
                      <th className="py-2 px-2 w-32 text-right">Preço</th>
                      <th className="py-2 px-2 w-32 text-right">Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    {sell.sell_lines.map((line) => (
                      <tr key={line.id} className="border-b last:border-b-0">
                        <td className="py-2 px-2">{line.product_name}</td>
                        <td className="py-2 px-2 text-right tabular-nums">{line.quantity}</td>
                        <td className="py-2 px-2 text-right tabular-nums">{line.unit_price}</td>
                        <td className="py-2 px-2 text-right tabular-nums font-medium">{line.total}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              ) : (
                <EmptyState icon="package" title="Sem itens" description="Nenhuma peça/serviço lançado." />
              )}
            </section>

            {checklists && checklists.length > 0 && (
              <section className="rounded-lg border bg-card p-4 space-y-2">
                <h2 className="text-sm font-semibold">Checklist do aparelho</h2>
                <ul className="text-sm space-y-1">
                  {checklists.map((item, idx) => (
                    <li key={idx} className="flex items-center gap-2">
                      <span className="inline-block w-3 h-3 rounded-full bg-muted" />
                      {item}
                    </li>
                  ))}
                </ul>
              </section>
            )}

            <section className="rounded-lg border bg-card p-4 space-y-2">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <FileText className="h-4 w-4" /> Timeline
              </h2>
              <Deferred data="activities" fallback={
                <p className="text-xs text-muted-foreground italic">Carregando timeline…</p>
              }>
                <ActivitiesList />
              </Deferred>
            </section>
          </div>

          <aside className="space-y-4">
            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold">Pagamentos</h2>
              {sell.payments && sell.payments.length > 0 ? (
                <ul className="text-sm space-y-2">
                  {sell.payments.map((pay) => (
                    <li key={pay.id} className="flex justify-between border-b pb-1">
                      <span className="capitalize text-xs text-muted-foreground">{pay.method}</span>
                      <span className="font-medium tabular-nums">{pay.amount}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <EmptyState icon="credit-card" title="Sem pagamentos" description="Cobrança ainda pendente." />
              )}
            </section>

            {fsm.enabled && (
              <section className="rounded-lg border bg-card p-4 space-y-3">
                <h2 className="text-sm font-semibold">Pipeline FSM (Sells)</h2>
                <p className="text-xs text-muted-foreground">
                  Panel FSM Sells via componente shared. Sale ID #{fsm.sale_id}.
                </p>
                <p className="text-xs italic text-muted-foreground">
                  (Importar `&lt;FsmActionPanel saleId={fsm.sale_id} enabled /&gt;` em iteração futura.)
                </p>
              </section>
            )}
          </aside>
        </div>
      </div>
    </AppShellV2>
  );
}

function ActivitiesList() {
  return (
    <EmptyState icon="clock" title="Sem atividades" description="Histórico aparece após mudanças na venda." />
  );
}
