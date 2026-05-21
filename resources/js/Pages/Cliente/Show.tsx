// Cliente/Show — detalhe do cliente Inertia/React (MWART F3 — paridade /contacts/{id} legacy).
// Pattern reuse ADR 0149 — deriva blueprint Cowork clientes.
// Backend: ContactController::show($id) — Inertia::render dual via config('mwart.cliente_show.enabled')
//
// Wiring 5 waves (US-CRM-063..067) — 2026-05-21:
//   - LedgerTab (W-B / US-064): extrato com range + formato + export
//   - SalesTab (W-C / US-065): paginação server-side + filtros via Inertia partial reload
//   - PaymentsTab (W-A / US-063): self-fetch via AJAX /contacts/payments/{id}
//   - DocumentsTab (W-D / US-066): upload + notas, self-fetch via AJAX
//   - ActionsMenu + AddDiscountModal (W-E / US-067): dropdown ações header

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import {
  Activity,
  AlertTriangle,
  Banknote,
  ChevronLeft,
  CreditCard,
  Edit,
  FileText,
  ListChecks,
  Mail,
  MapPin,
  Phone,
  ReceiptText,
  User2,
  Users,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import PaymentsTab from './_show/PaymentsTab';
import LedgerTab from './_show/LedgerTab';
import SalesTab, { type SalesPaginator } from './_show/SalesTab';
import DocumentsTab from './_show/DocumentsTab';
import ActionsMenu from './_show/ActionsMenu';
import ContactPicker, { type ContactDropdownItem } from './_show/ContactPicker';
import ActivitiesTab, { type ActivityItem } from './_show/ActivitiesTab';
import PessoasContatoTab, { type ContactPerson } from './_show/PessoasContatoTab';

interface ContactInfo {
  id: number;
  name: string;
  supplier_business_name: string | null;
  type: 'customer' | 'supplier' | 'both';
  is_active: boolean;
  tax_number_masked: string | null;
  mobile: string | null;
  landline: string | null;
  email: string | null;
  city: string | null;
  state: string | null;
  address_line_1: string | null;
}

interface ContactStats {
  total_invoice: number;
  invoice_due: number;
  total_purchase: number;
  purchase_due: number;
  opening_balance: number;
}

interface Permissions {
  update: boolean;
  pay_due: boolean;
  delete: boolean;
  toggle_status: boolean;
  add_discount: boolean;
  upload: boolean;
  delete_document: boolean;
  edit_note: boolean;
  view_sell: boolean;
}

type TabKey = 'ledger' | 'sales' | 'payments' | 'documents' | 'activities' | 'persons';

interface ClienteShowPageProps {
  contact: ContactInfo;
  initialTab: TabKey;
  stats: ContactStats;
  sales?: SalesPaginator;
  locations: Array<{ id: number; name: string }>;
  permissions: Permissions;
  contact_dropdown?: ContactDropdownItem[];
  activities?: ActivityItem[];
  contact_persons?: ContactPerson[];
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

export default function ClienteShow(props: ClienteShowPageProps) {
  const { contact, permissions } = props;
  const [activeTab, setActiveTab] = useState<TabKey>(props.initialTab ?? 'ledger');

  const tabs: Array<{ key: TabKey; label: string; icon: typeof User2 }> = [
    { key: 'ledger', label: 'Extrato', icon: ListChecks },
    { key: 'sales', label: 'Vendas', icon: ReceiptText },
    { key: 'payments', label: 'Pagamentos', icon: Banknote },
    { key: 'documents', label: 'Documentos & Notas', icon: FileText },
    { key: 'activities', label: 'Atividades', icon: Activity },
    { key: 'persons', label: 'Pessoas', icon: Users },
  ];

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-6xl">
          <div className="flex items-center justify-between gap-3 mb-2">
            <a
              href="/cliente"
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para clientes
            </a>
            <Deferred data="contact_dropdown" fallback={<ContactPicker currentContactId={contact.id} />}>
              <ContactPicker currentContactId={contact.id} contacts={props.contact_dropdown} />
            </Deferred>
          </div>
          <div className="flex items-start gap-4">
            <div className="h-14 w-14 rounded-md bg-gradient-to-br from-stone-100 to-stone-200 dark:from-stone-800 dark:to-stone-700 flex items-center justify-center text-lg font-semibold text-stone-700 dark:text-stone-200">
              {contact.name.charAt(0).toUpperCase()}
            </div>
            <div className="flex-1 min-w-0">
              <h1 className="text-2xl font-semibold tracking-tight text-foreground">{contact.name}</h1>
              <p className="text-sm text-muted-foreground mt-1">
                {contact.tax_number_masked ?? 'Documento não informado'}
                {contact.type && (
                  <span className="ml-2 inline-flex items-center rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[10px] uppercase tracking-wider">
                    {contact.type}
                  </span>
                )}
                {!contact.is_active && (
                  <span className="ml-2 inline-flex items-center rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] uppercase tracking-wider text-amber-700 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-300">
                    Inativo
                  </span>
                )}
              </p>
            </div>
            <div className="flex items-center gap-2">
              {permissions.update && (
                <Button asChild>
                  <a href={`/contacts/${contact.id}/edit`}>
                    <Edit className="mr-1.5 h-4 w-4" />
                    Editar
                  </a>
                </Button>
              )}
              <ActionsMenu
                contactId={contact.id}
                contactName={contact.name}
                contactType={contact.type}
                isActive={contact.is_active}
                permissions={{
                  pay_due: permissions.pay_due,
                  delete: permissions.delete,
                  toggle_status: permissions.toggle_status,
                  add_discount: permissions.add_discount,
                }}
              />
            </div>
          </div>

          <Deferred data="stats" fallback={<StatsSkeleton />}>
            <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
              <StatCard label="Total vendido" value={formatBRL(props.stats?.total_invoice ?? 0)} icon={ReceiptText} />
              <StatCard
                label="A receber"
                value={formatBRL(props.stats?.invoice_due ?? 0)}
                icon={CreditCard}
                danger={(props.stats?.invoice_due ?? 0) > 0}
              />
              <StatCard label="Total comprado" value={formatBRL(props.stats?.total_purchase ?? 0)} icon={ReceiptText} />
              <StatCard label="Saldo abertura" value={formatBRL(props.stats?.opening_balance ?? 0)} icon={User2} />
            </div>
          </Deferred>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-6xl">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <aside className="md:col-span-1 space-y-4">
            <div className="rounded-lg border border-border bg-background p-5">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">Contato</h3>
              <dl className="space-y-2 text-sm">
                <ContactRow icon={Phone} label="Celular" value={contact.mobile} />
                <ContactRow icon={Phone} label="Fixo" value={contact.landline} />
                <ContactRow icon={Mail} label="E-mail" value={contact.email} />
                <ContactRow
                  icon={MapPin}
                  label="Endereço"
                  value={[contact.address_line_1, contact.city, contact.state].filter(Boolean).join(', ') || null}
                />
              </dl>
            </div>
          </aside>

          <section className="md:col-span-2">
            <div className="border-b border-border mb-4 flex gap-1 overflow-x-auto">
              {tabs.map((t) => {
                const Icon = t.icon;
                const isActive = activeTab === t.key;
                return (
                  <button
                    key={t.key}
                    type="button"
                    onClick={() => setActiveTab(t.key)}
                    className={
                      'inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 transition-colors -mb-px whitespace-nowrap ' +
                      (isActive
                        ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-400'
                        : 'border-transparent text-muted-foreground hover:text-foreground')
                    }
                    aria-selected={isActive}
                    role="tab"
                  >
                    <Icon size={16} />
                    {t.label}
                  </button>
                );
              })}
            </div>

            <div role="tabpanel" className="rounded-lg border border-border bg-background">
              {activeTab === 'ledger' && (
                <LedgerTab
                  contactId={contact.id}
                  contactName={contact.name}
                  locations={props.locations}
                />
              )}
              {activeTab === 'sales' && (
                <Deferred data="sales" fallback={<TabSkeleton />}>
                  <SalesTab
                    contactId={contact.id}
                    sales={props.sales}
                    endpoint={`/cliente/${contact.id}`}
                  />
                </Deferred>
              )}
              {activeTab === 'payments' && (
                <PaymentsTab contactId={contact.id} canViewSell={permissions.view_sell} />
              )}
              {activeTab === 'documents' && (
                <DocumentsTab
                  contactId={contact.id}
                  permissions={{
                    upload: permissions.upload,
                    delete_document: permissions.delete_document,
                    edit_note: permissions.edit_note,
                  }}
                />
              )}
              {activeTab === 'activities' && (
                <Deferred data="activities" fallback={<TabSkeleton />}>
                  <ActivitiesTab activities={props.activities} />
                </Deferred>
              )}
              {activeTab === 'persons' && (
                <Deferred data="contact_persons" fallback={<TabSkeleton />}>
                  <PessoasContatoTab contactId={contact.id} contact_persons={props.contact_persons} />
                </Deferred>
              )}
            </div>
          </section>
        </div>
      </div>
    </div>
  );
}

ClienteShow.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function StatsSkeleton() {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-4 gap-4 mt-6">
      {[0, 1, 2, 3].map((i) => (
        <div key={i} className="rounded-xl border border-border bg-background p-5 h-24 animate-pulse" />
      ))}
    </div>
  );
}

function TabSkeleton() {
  return <div className="p-8 text-center text-xs text-muted-foreground">Carregando…</div>;
}

function StatCard({
  label,
  value,
  icon: Icon,
  danger,
}: {
  label: string;
  value: string;
  icon: typeof User2;
  danger?: boolean;
}) {
  return (
    <div
      className={
        'rounded-xl border p-4 shadow-sm ' +
        (danger
          ? 'border-rose-200 bg-rose-50/50 dark:border-rose-900/40 dark:bg-rose-950/30'
          : 'border-border bg-background')
      }
    >
      <div
        className={
          'text-[11px] font-semibold uppercase tracking-widest ' +
          (danger ? 'text-rose-700 dark:text-rose-400' : 'text-muted-foreground')
        }
      >
        {label}
      </div>
      <div className="flex items-end justify-between mt-2">
        <div
          className={
            'text-xl font-semibold tabular-nums ' +
            (danger ? 'text-rose-700 dark:text-rose-300' : 'text-foreground')
          }
        >
          {value}
        </div>
        <Icon size={20} className={danger ? 'text-rose-400' : 'text-muted-foreground/60'} strokeWidth={1.5} />
      </div>
    </div>
  );
}

function ContactRow({ icon: Icon, label, value }: { icon: typeof Phone; label: string; value: string | null }) {
  return (
    <div className="flex items-start gap-2">
      <Icon size={14} className="text-muted-foreground mt-0.5 flex-shrink-0" />
      <div className="flex-1 min-w-0">
        <dt className="text-xs text-muted-foreground">{label}</dt>
        <dd className="text-sm text-foreground truncate">{value ?? '—'}</dd>
      </div>
    </div>
  );
}

// Eslint hint: AlertTriangle reservado pra futuro badge "Em atraso".
export const _kept = AlertTriangle;
