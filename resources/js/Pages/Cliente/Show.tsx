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
  Car,
  ChevronLeft,
  CreditCard,
  Edit,
  FileText,
  Gift,
  ListChecks,
  Mail,
  MapPin,
  Phone,
  ReceiptText,
  Recycle,
  User2,
  Users,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import PaymentsTab from './_show/PaymentsTab';
import LedgerTab from './_show/LedgerTab';
import SalesTab, { type SalesPaginator } from './_show/SalesTab';
import VehiclesTab, { type VehiclesPaginator } from './_show/VehiclesTab';
import DocumentsTab from './_show/DocumentsTab';
import ActionsMenu from './_show/ActionsMenu';
import ContactPicker, { type ContactDropdownItem } from './_show/ContactPicker';
import ActivitiesTab, { type ActivityItem } from './_show/ActivitiesTab';
import PessoasContatoTab, { type ContactPerson } from './_show/PessoasContatoTab';
import SubscriptionsTab, { type SubscriptionItem } from './_show/SubscriptionsTab';
import RewardPointsTab, { type RewardPointsPayload } from './_show/RewardPointsTab';
import RiscoClienteCard from './_show/RiscoClienteCard';

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
  // Dados Fiscais BR — payload do backend (migration 2026_05_21_140000).
  // cpf_cnpj entregue MASCARADO via maskTaxNumber (Show.charter Anti-hook LGPD).
  cpf_cnpj_masked?: string | null;
  inscricao_estadual?: string | null;
  inscricao_municipal?: string | null;
  indicador_ie?: number | null;
  nome_fantasia?: string | null;
  consumidor_final?: boolean;
  contribuinte?: boolean;
  regime?: string | null;
  suframa?: string | null;
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

type TabKey = 'ledger' | 'sales' | 'payments' | 'documents' | 'activities' | 'persons' | 'subscriptions' | 'rewards' | 'vehicles';

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
  subscriptions?: SubscriptionItem[];
  reward_points?: RewardPointsPayload;
  // Onda 1 PR D 2026-05-26 — Veículos do cliente (frota Martinho).
  modules?: { oficinaauto_enabled: boolean };
  vehicles?: VehiclesPaginator | null;
}

const formatBRL = (value: number) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);

export default function ClienteShow(props: ClienteShowPageProps) {
  const { contact, permissions } = props;
  const [activeTab, setActiveTab] = useState<TabKey>(props.initialTab ?? 'ledger');

  // Onda 1 PR D 2026-05-26 — tab Veículos só aparece se OficinaAuto está instalado
  // pro business. Pedido Daniela (Martinho) — frota direto no cadastro do cliente.
  const oficinaAutoEnabled = props.modules?.oficinaauto_enabled === true;

  const tabs: Array<{ key: TabKey; label: string; icon: typeof User2 }> = [
    { key: 'ledger', label: 'Extrato', icon: ListChecks },
    { key: 'sales', label: 'Vendas', icon: ReceiptText },
    { key: 'payments', label: 'Pagamentos', icon: Banknote },
    { key: 'documents', label: 'Documentos & Notas', icon: FileText },
    { key: 'activities', label: 'Atividades', icon: Activity },
    { key: 'persons', label: 'Pessoas', icon: Users },
    ...(oficinaAutoEnabled ? [{ key: 'vehicles' as const, label: 'Veículos', icon: Car }] : []),
    { key: 'subscriptions', label: 'Assinaturas', icon: Recycle },
    { key: 'rewards', label: 'Pontos', icon: Gift },
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
                <Button asChild variant="cowork-primary">
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

            <DadosFiscaisBRCard contact={contact} />

            <RiscoClienteCard contact={contact} stats={props.stats} />
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
              {activeTab === 'subscriptions' && (
                <Deferred data="subscriptions" fallback={<TabSkeleton />}>
                  <SubscriptionsTab subscriptions={props.subscriptions} />
                </Deferred>
              )}
              {activeTab === 'rewards' && (
                <Deferred data="reward_points" fallback={<TabSkeleton />}>
                  <RewardPointsTab reward_points={props.reward_points} />
                </Deferred>
              )}
              {activeTab === 'vehicles' && oficinaAutoEnabled && (
                <Deferred data="vehicles" fallback={<TabSkeleton />}>
                  <VehiclesTab contactId={contact.id} vehicles={props.vehicles} />
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

/**
 * Bloco "Dados Fiscais BR" — Slice 3 da restauração dos campos BR.
 *
 * Charter Show.tsx Anti-hook LGPD: cpf_cnpj exibido MASCARADO via
 * `cpf_cnpj_masked` (backend aplica `maskTaxNumber`). Frontend nunca recebe
 * `cpf_cnpj` plain.
 *
 * Não renderiza se contato não tem nenhum campo BR populado (mantém UI
 * limpa pra clientes legacy que ainda só têm tax_number genérico).
 */
function DadosFiscaisBRCard({ contact }: { contact: ContactInfo }) {
  const hasAnyField =
    contact.cpf_cnpj_masked ||
    contact.inscricao_estadual ||
    contact.inscricao_municipal ||
    contact.indicador_ie != null ||
    contact.nome_fantasia ||
    contact.regime ||
    contact.suframa;

  if (!hasAnyField) return null;

  const indicadorIeLabel: Record<number, string> = {
    1: 'Contribuinte ICMS',
    2: 'Isento de IE',
    9: 'Não contribuinte',
  };

  const regimeLabel: Record<string, string> = {
    simples: 'Simples Nacional',
    presumido: 'Lucro Presumido',
    real: 'Lucro Real',
    mei: 'MEI',
  };

  return (
    <div className="rounded-lg border border-border bg-background p-5">
      <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
        Dados fiscais BR
      </h3>
      <dl className="space-y-2 text-sm">
        {contact.cpf_cnpj_masked && (
          <FiscalRow label="CPF / CNPJ" value={contact.cpf_cnpj_masked} mono />
        )}
        {contact.nome_fantasia && (
          <FiscalRow label="Nome fantasia" value={contact.nome_fantasia} />
        )}
        {contact.inscricao_estadual && (
          <FiscalRow label="IE" value={contact.inscricao_estadual} mono />
        )}
        {contact.inscricao_municipal && (
          <FiscalRow label="IM" value={contact.inscricao_municipal} mono />
        )}
        {contact.indicador_ie != null && (
          <FiscalRow
            label="Indicador IE"
            value={`${contact.indicador_ie} — ${indicadorIeLabel[contact.indicador_ie] ?? '—'}`}
          />
        )}
        {contact.regime && (
          <FiscalRow label="Regime" value={regimeLabel[contact.regime] ?? contact.regime} />
        )}
        {contact.suframa && <FiscalRow label="SUFRAMA" value={contact.suframa} mono />}
        <FiscalFlags
          contribuinte={contact.contribuinte ?? true}
          consumidorFinal={contact.consumidor_final ?? false}
        />
      </dl>
    </div>
  );
}

function FiscalRow({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="flex items-start justify-between gap-3">
      <dt className="text-xs text-muted-foreground flex-shrink-0">{label}</dt>
      <dd className={'text-sm text-foreground text-right truncate ' + (mono ? 'tabular-nums' : '')}>
        {value}
      </dd>
    </div>
  );
}

function FiscalFlags({
  contribuinte,
  consumidorFinal,
}: {
  contribuinte: boolean;
  consumidorFinal: boolean;
}) {
  return (
    <div className="pt-2 mt-2 border-t border-border flex flex-wrap gap-2">
      <span
        className={
          'inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ' +
          (contribuinte
            ? 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/30 dark:text-sky-300'
            : 'border-stone-200 bg-stone-50 text-stone-700 dark:border-stone-700 dark:bg-stone-900/60 dark:text-stone-300')
        }
      >
        {contribuinte ? '✓ Contribuinte ICMS' : '✗ Não contribuinte'}
      </span>
      {consumidorFinal && (
        <span className="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
          Consumidor final
        </span>
      )}
    </div>
  );
}

// Eslint hint: AlertTriangle reservado pra futuro badge "Em atraso".
export const _kept = AlertTriangle;
