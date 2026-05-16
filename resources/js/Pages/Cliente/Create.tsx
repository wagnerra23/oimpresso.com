// W1-B3 Cliente/Create — form de cadastro Inertia/React (MWART F3).
// Pattern reuse ADR 0149 — deriva do blueprint Cowork clientes-cockpit.
// Backend: ContactController::create() — Inertia::render dual via config('mwart.cliente_create.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { type ReactNode, type FormEvent } from 'react';
import { ChevronLeft, Save, User2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';

interface ClienteCreatePageProps {
  types: Record<string, string>;
  customer_groups: Array<{ id: number; name: string }>;
  selected_type: string | null;
  prefill_name: string;
  permissions: {
    create_customer: boolean;
    create_supplier: boolean;
  };
}

type ClienteFormData = {
  type: string;
  contact_type_radio: string;
  prefix: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  supplier_business_name: string;
  tax_number: string;
  mobile: string;
  landline: string;
  email: string;
  address_line_1: string;
  city: string;
  state: string;
  zip_code: string;
  customer_group_id: string;
  opening_balance: string;
  credit_limit: string;
};

export default function ClienteCreate(props: ClienteCreatePageProps) {
  const { data, setData, post, processing, errors } = useForm<ClienteFormData>({
    type: props.selected_type ?? 'customer',
    contact_type_radio: 'person',
    prefix: '',
    first_name: props.prefill_name ?? '',
    middle_name: '',
    last_name: '',
    supplier_business_name: '',
    tax_number: '',
    mobile: '',
    landline: '',
    email: '',
    address_line_1: '',
    city: '',
    state: '',
    zip_code: '',
    customer_group_id: '',
    opening_balance: '0',
    credit_limit: '',
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    post('/contacts', {
      preserveScroll: true,
    });
  };

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-3xl">
          <div className="flex items-center gap-3 mb-2">
            <a
              href="/contacts/customer"
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para clientes
            </a>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Novo cliente</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Preencha os dados do cliente. Campos com * são obrigatórios.
          </p>
        </div>
      </div>

      <div className="container mx-auto px-8 py-6 max-w-3xl">
        <form onSubmit={handleSubmit} className="space-y-6">
          <Section title="Identificação" icon={User2}>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="Tipo" error={errors.type}>
                <select
                  value={data.type}
                  onChange={(e) => setData('type', e.target.value)}
                  className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
                  required
                >
                  {Object.entries(props.types).map(([k, v]) => (
                    <option key={k} value={k}>{v}</option>
                  ))}
                </select>
              </Field>
              <Field label="Pessoa" error={errors.contact_type_radio}>
                <div className="flex items-center gap-3 h-9">
                  <label className="inline-flex items-center gap-1.5 text-sm">
                    <input
                      type="radio"
                      name="contact_type_radio"
                      value="person"
                      checked={data.contact_type_radio === 'person'}
                      onChange={(e) => setData('contact_type_radio', e.target.value)}
                    />
                    Física
                  </label>
                  <label className="inline-flex items-center gap-1.5 text-sm">
                    <input
                      type="radio"
                      name="contact_type_radio"
                      value="business"
                      checked={data.contact_type_radio === 'business'}
                      onChange={(e) => setData('contact_type_radio', e.target.value)}
                    />
                    Jurídica
                  </label>
                </div>
              </Field>
              <Field label="Nome / Razão social *" error={errors.first_name} colSpan={2}>
                <Input
                  type="text"
                  value={data.first_name}
                  onChange={(e) => setData('first_name', e.target.value)}
                  required
                  maxLength={100}
                />
              </Field>
              <Field label="Sobrenome" error={errors.last_name}>
                <Input
                  type="text"
                  value={data.last_name}
                  onChange={(e) => setData('last_name', e.target.value)}
                  maxLength={100}
                />
              </Field>
              <Field label="CNPJ / CPF" error={errors.tax_number}>
                <Input
                  type="text"
                  value={data.tax_number}
                  onChange={(e) => setData('tax_number', e.target.value)}
                  placeholder="00.000.000/0000-00"
                />
              </Field>
            </div>
          </Section>

          <Section title="Contato">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="Celular" error={errors.mobile}>
                <Input
                  type="tel"
                  value={data.mobile}
                  onChange={(e) => setData('mobile', e.target.value)}
                  placeholder="(00) 00000-0000"
                />
              </Field>
              <Field label="Telefone fixo" error={errors.landline}>
                <Input
                  type="tel"
                  value={data.landline}
                  onChange={(e) => setData('landline', e.target.value)}
                  placeholder="(00) 0000-0000"
                />
              </Field>
              <Field label="E-mail" error={errors.email} colSpan={2}>
                <Input
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  placeholder="cliente@exemplo.com.br"
                />
              </Field>
            </div>
          </Section>

          <Section title="Endereço">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="Endereço" error={errors.address_line_1} colSpan={2}>
                <Input
                  type="text"
                  value={data.address_line_1}
                  onChange={(e) => setData('address_line_1', e.target.value)}
                />
              </Field>
              <Field label="Cidade" error={errors.city}>
                <Input
                  type="text"
                  value={data.city}
                  onChange={(e) => setData('city', e.target.value)}
                />
              </Field>
              <Field label="UF" error={errors.state}>
                <Input
                  type="text"
                  value={data.state}
                  onChange={(e) => setData('state', e.target.value)}
                  maxLength={2}
                />
              </Field>
              <Field label="CEP" error={errors.zip_code}>
                <Input
                  type="text"
                  value={data.zip_code}
                  onChange={(e) => setData('zip_code', e.target.value)}
                  placeholder="00000-000"
                />
              </Field>
            </div>
          </Section>

          {(data.type === 'customer' || data.type === 'both') && (
            <Section title="Financeiro">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Saldo inicial" error={errors.opening_balance}>
                  <Input
                    type="text"
                    value={data.opening_balance}
                    onChange={(e) => setData('opening_balance', e.target.value)}
                    placeholder="0,00"
                  />
                </Field>
                <Field label="Limite de crédito" error={errors.credit_limit}>
                  <Input
                    type="text"
                    value={data.credit_limit}
                    onChange={(e) => setData('credit_limit', e.target.value)}
                    placeholder="0,00"
                  />
                </Field>
                {props.customer_groups.length > 0 && (
                  <Field label="Grupo de clientes" error={errors.customer_group_id} colSpan={2}>
                    <select
                      value={data.customer_group_id}
                      onChange={(e) => setData('customer_group_id', e.target.value)}
                      className="h-9 w-full rounded-md border border-border bg-background px-3 text-sm"
                    >
                      <option value="">— Nenhum —</option>
                      {props.customer_groups.map((g) => (
                        <option key={g.id} value={g.id}>{g.name}</option>
                      ))}
                    </select>
                  </Field>
                )}
              </div>
            </Section>
          )}

          <div className="flex items-center justify-end gap-2 pt-4 border-t border-border">
            <Button type="button" variant="outline" asChild>
              <a href="/contacts/customer">Cancelar</a>
            </Button>
            <Button type="submit" disabled={processing}>
              <Save className="mr-1.5 h-4 w-4" />
              {processing ? 'Salvando…' : 'Salvar cliente'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}

ClienteCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

function Section({ title, icon: Icon, children }: { title: string; icon?: typeof User2; children: ReactNode }) {
  return (
    <section className="rounded-lg border border-border bg-background p-5">
      <h3 className="text-sm font-semibold text-foreground mb-4 flex items-center gap-2">
        {Icon && <Icon size={16} className="text-muted-foreground" />}
        {title}
      </h3>
      {children}
    </section>
  );
}

function Field({
  label,
  children,
  error,
  colSpan,
}: {
  label: string;
  children: ReactNode;
  error?: string;
  colSpan?: number;
}) {
  return (
    <div className={colSpan === 2 ? 'sm:col-span-2' : ''}>
      <Label className="text-xs font-medium text-muted-foreground mb-1.5 block">{label}</Label>
      {children}
      {error && <p className="text-xs text-rose-600 mt-1">{error}</p>}
    </div>
  );
}
