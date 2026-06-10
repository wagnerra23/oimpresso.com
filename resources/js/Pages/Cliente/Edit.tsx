// Cliente/Edit — edição Inertia/React (MWART F3). Espelho do Create.
// PR-A (Onda F): corpo extraído pro _form/ClienteForm (compartilhado).
// Backend: ContactController::edit($id) — Inertia::render dual via config('mwart.cliente_edit.enabled').

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { type ReactNode, type FormEvent } from 'react';
import { ChevronLeft } from 'lucide-react';
import { ClienteForm } from './_form/ClienteForm';
import type { BrasilApiCnpjData } from './_form/DadosFiscaisBRSection';
import type { ClienteFormShared, CustomerGroup } from './_form/cliente-form-types';
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
  shipping_address: string | null;
  customer_group_id: number | null;
  credit_limit: string | null;
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
  customer_groups: CustomerGroup[];
  opening_balance: string;
}

export default function ClienteEdit(props: ClienteEditPageProps) {
  const c = props.contact;
  const { data, setData, put, processing, errors, transform } = useForm<ClienteFormShared>({
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
    shipping_address: c.shipping_address ?? '',
    customer_group_id: c.customer_group_id ? String(c.customer_group_id) : '',
    opening_balance: props.opening_balance ?? '0',
    credit_limit: c.credit_limit ?? '',
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

  // No Edit, preservar o que existe é mais sensível — só sobrescreve com valor da API.
  const handleCnpjLookup = (api: BrasilApiCnpjData) => {
    if (api.razao_social) setData('supplier_business_name', api.razao_social);
    if (api.logradouro) {
      const numero = api.numero ? `, ${api.numero}` : '';
      const bairro = api.bairro ? ` — ${api.bairro}` : '';
      setData('address_line_1', `${api.logradouro}${numero}${bairro}`);
    }
    if (api.municipio) setData('city', api.municipio);
    if (api.uf) setData('state', api.uf);
    if (api.cep) setData('zip_code', api.cep);
  };

  transform((payload) => ({ ...payload, cpf_cnpj: unmaskDigits(payload.cpf_cnpj) }));

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    put(`/contacts/${c.id}`, { preserveScroll: true });
  };

  return (
    <div className="flex-1 bg-muted/30">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto max-w-5xl px-8 pb-4 pt-6">
          <a
            href={`/contacts/${c.id}`}
            className="mb-2 inline-flex items-center text-xs text-muted-foreground transition-colors hover:text-foreground"
          >
            <ChevronLeft size={14} className="mr-1" />
            Voltar para detalhe
          </a>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">Editar cliente</h1>
          <p className="mt-1 text-sm text-muted-foreground">{c.name}</p>
        </div>
      </div>

      <div className="container mx-auto max-w-5xl px-8 py-5">
        <ClienteForm
          data={data}
          setData={setData}
          errors={errors}
          types={props.types}
          customerGroups={props.customer_groups}
          isJuridica={isJuridica}
          onCnpjLookup={handleCnpjLookup}
          processing={processing}
          submitLabel="Salvar alterações"
          cancelHref={`/contacts/${c.id}`}
          onSubmit={handleSubmit}
        />
      </div>
    </div>
  );
}

ClienteEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
