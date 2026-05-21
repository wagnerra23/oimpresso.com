// W1-B3 Cliente/Edit — form de edição Inertia/React (MWART F3).
// Pattern reuse ADR 0149 — deriva blueprint Cowork clientes; family visual idêntica a Create.
// Backend: ContactController::edit($id) — Inertia::render dual via config('mwart.cliente_edit.enabled')

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { type ReactNode, type FormEvent } from 'react';
import { ChevronLeft, Save, User2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import DadosFiscaisBRSection, { type DadosFiscaisBRData } from './_form/DadosFiscaisBRSection';
import { unmaskDigits } from '@/Lib/format-br';

interface ContactInfo {
  id: number;
  type: string;
  contact_type: string | null;
  name: string;
  prefix: string | null;
  first_name: string;
  middle_name: string | null;
  last_name: string | null;
  supplier_business_name: string | null;
  tax_number: string | null;
  mobile: string | null;
  landline: string | null;
  email: string | null;
  address_line_1: string | null;
  city: string | null;
  state: string | null;
  zip_code: string | null;
  customer_group_id: number | null;
  credit_limit: string | null;
  // Campos BR (migration 2026_05_21_140000). Backend pode mandar null em legacy.
  cpf_cnpj?: string | null;
  rg?: string | null;
  inscricao_estadual?: string | null;
  inscricao_municipal?: string | null;
  indicador_ie?: number | null;
  nome_fantasia?: string | null;
  consumidor_final?: boolean | null;
  contribuinte?: boolean | null;
  regime?: string | null;
  suframa?: string | null;
}

interface ClienteEditPageProps {
  contact: ContactInfo;
  types: Record<string, string>;
  customer_groups: Array<{ id: number; name: string }>;
  opening_balance: string;
}

type ClienteFormData = DadosFiscaisBRData & {
  type: string;
  contact_type_radio: string;
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

export default function ClienteEdit(props: ClienteEditPageProps) {
  const c = props.contact;
  const { data, setData, put, processing, errors, transform } = useForm<ClienteFormData>({
    type: c.type ?? 'customer',
    contact_type_radio: c.contact_type ?? 'person',
    first_name: c.first_name ?? c.name ?? '',
    middle_name: c.middle_name ?? '',
    last_name: c.last_name ?? '',
    supplier_business_name: c.supplier_business_name ?? '',
    tax_number: c.tax_number ?? '',
    mobile: c.mobile ?? '',
    landline: c.landline ?? '',
    email: c.email ?? '',
    address_line_1: c.address_line_1 ?? '',
    city: c.city ?? '',
    state: c.state ?? '',
    zip_code: c.zip_code ?? '',
    customer_group_id: c.customer_group_id ? String(c.customer_group_id) : '',
    opening_balance: props.opening_balance ?? '0',
    credit_limit: c.credit_limit ?? '',
    // Dados Fiscais BR — pré-preenchidos do contact carregado pelo backend.
    cpf_cnpj: c.cpf_cnpj ?? '',
    rg: c.rg ?? '',
    inscricao_estadual: c.inscricao_estadual ?? '',
    inscricao_municipal: c.inscricao_municipal ?? '',
    indicador_ie: c.indicador_ie != null ? String(c.indicador_ie) : '',
    nome_fantasia: c.nome_fantasia ?? '',
    consumidor_final: c.consumidor_final === true,
    contribuinte: c.contribuinte !== false, // default true se null/undefined (legacy)
    regime: c.regime ?? '',
    suframa: c.suframa ?? '',
  });

  const isJuridica = data.contact_type_radio === 'business';

  // Mesma normalização de Create — backend Rule\BR\CpfCnpj re-aplica onlyNumbers.
  transform((payload) => ({
    ...payload,
    cpf_cnpj: unmaskDigits(payload.cpf_cnpj),
  }));

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    put(`/contacts/${c.id}`, {
      preserveScroll: true,
    });
  };

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto px-8 pt-6 pb-4 max-w-3xl">
          <div className="flex items-center gap-3 mb-2">
            <a
              href={`/contacts/${c.id}`}
              className="inline-flex items-center text-xs text-muted-foreground hover:text-foreground transition-colors"
            >
              <ChevronLeft size={14} className="mr-1" />
              Voltar para detalhe
            </a>
          </div>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Editar cliente</h1>
          <p className="text-sm text-muted-foreground mt-1">{c.name}</p>
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
                <Input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} required maxLength={100} />
              </Field>
              <Field label="Sobrenome" error={errors.last_name}>
                <Input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} maxLength={100} />
              </Field>
              <Field label="Tax number (legado UPOS)" error={errors.tax_number}>
                <Input value={data.tax_number} onChange={(e) => setData('tax_number', e.target.value)} placeholder="Use CPF / CNPJ abaixo (este campo é legacy)" />
              </Field>
            </div>
          </Section>

          <DadosFiscaisBRSection<ClienteFormData>
            data={data}
            setData={setData}
            errors={errors}
            isJuridica={isJuridica}
          />

          <Section title="Contato">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="Celular" error={errors.mobile}>
                <Input value={data.mobile} onChange={(e) => setData('mobile', e.target.value)} />
              </Field>
              <Field label="Telefone fixo" error={errors.landline}>
                <Input value={data.landline} onChange={(e) => setData('landline', e.target.value)} />
              </Field>
              <Field label="E-mail" error={errors.email} colSpan={2}>
                <Input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
              </Field>
            </div>
          </Section>

          <Section title="Endereço">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Field label="Endereço" error={errors.address_line_1} colSpan={2}>
                <Input value={data.address_line_1} onChange={(e) => setData('address_line_1', e.target.value)} />
              </Field>
              <Field label="Cidade" error={errors.city}>
                <Input value={data.city} onChange={(e) => setData('city', e.target.value)} />
              </Field>
              <Field label="UF" error={errors.state}>
                <Input value={data.state} onChange={(e) => setData('state', e.target.value)} maxLength={2} />
              </Field>
              <Field label="CEP" error={errors.zip_code}>
                <Input value={data.zip_code} onChange={(e) => setData('zip_code', e.target.value)} />
              </Field>
            </div>
          </Section>

          {(data.type === 'customer' || data.type === 'both') && (
            <Section title="Financeiro">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <Field label="Saldo inicial" error={errors.opening_balance}>
                  <Input value={data.opening_balance} onChange={(e) => setData('opening_balance', e.target.value)} />
                </Field>
                <Field label="Limite de crédito" error={errors.credit_limit}>
                  <Input value={data.credit_limit} onChange={(e) => setData('credit_limit', e.target.value)} />
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
              <a href={`/contacts/${c.id}`}>Cancelar</a>
            </Button>
            <Button type="submit" disabled={processing}>
              <Save className="mr-1.5 h-4 w-4" />
              {processing ? 'Salvando…' : 'Salvar alterações'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}

ClienteEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

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

function Field({ label, children, error, colSpan }: { label: string; children: ReactNode; error?: string; colSpan?: number }) {
  return (
    <div className={colSpan === 2 ? 'sm:col-span-2' : ''}>
      <Label className="text-xs font-medium text-muted-foreground mb-1.5 block">{label}</Label>
      {children}
      {error && <p className="text-xs text-rose-600 mt-1">{error}</p>}
    </div>
  );
}
