// US-SELL-003 — F3 FRONTEND INCREMENTAL skeleton.
// Migra /sells/create de Blade legacy (sale_pos.create) pra Inertia/React.
// Refs: ADR 0104 (MWART canônico), RUNBOOK Sells/create.
//
// ESTE PR (skeleton):
//   - Interface TypeScript dos 27 props recebidos do controller
//   - PageHeader + AppShellV2 Persistent Layout
//   - useForm com defaults conservadores (status='final', date=defaultDatetime)
//   - Container vazio com EmptyState — produtos/pagamento/frete entram em US-SELL-004..007
//
// Próximos PRs:
//   - US-SELL-004: triagem visibilidade campos (18 → 8 visíveis + 10 colapsáveis)
//   - US-SELL-005: bloco produtos (busca + tabela + cálculos)
//   - US-SELL-006: pagamento + frete + descontos
//   - US-SELL-007: atalhos + auto-save draft + estados visuais

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Button } from '@/Components/ui/button';
import type { ReactNode } from 'react';

interface OptionMap {
  [id: number]: string;
}

interface Location {
  id: number;
  name: string;
  selling_price_group_id: number | null;
}

interface Contact {
  id: number;
  name: string;
}

interface Tax {
  id: number;
  name: string;
  amount: number;
}

interface InvoiceScheme {
  id: number;
  name: string;
}

export interface SellsCreatePageProps {
  businessLocations: OptionMap;
  blAttributes: Record<number, Record<string, unknown>>;
  defaultLocation: Location | null;
  walkInCustomer: Contact;
  paymentTypes: Record<string, string>;
  invoiceSchemes: OptionMap;
  defaultInvoiceScheme: InvoiceScheme | null;
  invoiceLayouts: OptionMap;
  taxes: Tax[];
  priceGroups: OptionMap;
  defaultPriceGroupId: number | null;
  shippingStatuses: Record<string, string>;
  defaultDatetime: string;
  commissionAgents: OptionMap;
  customerGroups: OptionMap;
  accounts: OptionMap;
  typesOfService: OptionMap;
  categories: Record<string, unknown> | false;
  brands: Record<string, unknown> | false;
  shortcuts: Record<string, string> | null;
  featuredProducts: Array<Record<string, unknown>>;
  users: OptionMap | [];
  permissions: {
    editDiscount: boolean;
    editPrice: boolean;
  };
  posSettings: Record<string, unknown>;
  subType: string | null;
}

export default function SellsCreate(props: SellsCreatePageProps) {
  // useForm com defaults — ROTA LIVRE 99% Status=final (auto-mem cliente_rotalivre)
  // transaction_date usa defaultDatetime que vem do format_now_local pra evitar shift +3h
  const { data, setData } = useForm({
    location_id: props.defaultLocation?.id ?? null,
    contact_id: props.walkInCustomer.id,
    transaction_date: props.defaultDatetime,
    status: 'final' as 'final' | 'quotation' | 'draft' | 'proforma',
    invoice_scheme_id: props.defaultInvoiceScheme?.id ?? null,
    products: [] as Array<{
      product_id: number;
      quantity: number;
      unit_price: number;
      discount: number;
    }>,
    payments: [] as Array<{
      amount: number;
      method: string;
      paid_on: string;
      account_id: number | null;
    }>,
    notes: '',
  });

  return (
    <div className="container mx-auto p-6 space-y-6">
      <PageHeader
        icon="shopping-cart"
        title="Adicionar venda"
        description="Criação de venda — versão MWART (Inertia/React) em construção"
      />

      <div className="rounded-lg border border-border bg-card p-6">
        <EmptyState
          icon="construction"
          title="Tela MWART em construção"
          description="Esqueleto carregado. Blocos de produtos, pagamento e frete chegam nas próximas entregas (US-SELL-004 a 007). Pra voltar pra tela atual, toggle useV2SellsCreate OFF em growthbook.oimpresso.com."
          action={
            <Button variant="outline" onClick={() => { window.location.href = '/sells'; }}>
              Voltar pra Sells (lista)
            </Button>
          }
        />
      </div>

      {/* Debug bloco — só pra Wagner conferir contract chegou correto. Removível em US-SELL-004. */}
      <details className="rounded-lg border border-border bg-card p-4 text-sm">
        <summary className="cursor-pointer font-medium text-foreground">
          Debug · contract recebido do controller (props)
        </summary>
        <div className="mt-3 space-y-1 text-muted-foreground">
          <div>
            <strong>defaultLocation:</strong> {props.defaultLocation?.name ?? '—'}
          </div>
          <div>
            <strong>walkInCustomer:</strong> {props.walkInCustomer.name}
          </div>
          <div>
            <strong>defaultDatetime:</strong> {props.defaultDatetime}
          </div>
          <div>
            <strong>permissions:</strong> editPrice={String(props.permissions.editPrice)},
            editDiscount={String(props.permissions.editDiscount)}
          </div>
          <div>
            <strong>businessLocations:</strong> {Object.keys(props.businessLocations).length}{' '}
            opções
          </div>
          <div>
            <strong>paymentTypes:</strong> {Object.keys(props.paymentTypes).length} opções
          </div>
          <div>
            <strong>form data atual:</strong>{' '}
            <code className="text-xs">
              location_id={String(data.location_id)}, status={data.status}, date=
              {data.transaction_date}
            </code>
          </div>
        </div>
      </details>
    </div>
  );
}

// Persistent Layout (DESIGN.md §4 + auto-mem preference_persistent_layouts)
// NUNCA envolver em <AppShell> inline — shell duplicado quebra scroll/breadcrumb.
SellsCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
