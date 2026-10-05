// Cliente/Create — cadastro Inertia/React (MWART F3 · KB-9.75 Contacts).
// PR-A (Onda F): corpo extraído pro _form/ClienteForm (compartilhado com Edit).
// Backend: ContactController::create() — Inertia::render dual via config('mwart.cliente_create.enabled').
// Thread Crm/06 (D2): o LeadController::create() renderiza esta MESMA tela com `destino` +
// `lead_opcoes`. Sem `destino` (o caso do Cliente) nada muda: grava em /contacts.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, useForm } from '@inertiajs/react';
import { type ReactNode, type FormEvent } from 'react';
import { ChevronLeft, Target } from 'lucide-react';
import { FormSection, FormGrid } from '@/Components/ui/form-section';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import { Field } from './_form/Field';
import { ClienteForm } from './_form/ClienteForm';
import type { BrasilApiCnpjData } from './_form/DadosFiscaisBRSection';
import type { ClienteFormShared, CustomerGroup } from './_form/cliente-form-types';
import { unmaskDigits } from '@/Lib/format-br';

interface ClienteCreatePageProps {
  types: Record<string, string>;
  customer_groups: CustomerGroup[];
  selected_type: string | null;
  prefill_name: string;
  permissions: {
    create_customer: boolean;
    create_supplier: boolean;
  };
  /** Onde gravar e o texto da tela. Ausente = cadastro de cliente (/contacts). */
  destino?: {
    url: string;
    voltar_href: string;
    voltar_label: string;
    titulo: string;
    subtitulo: string;
    salvar: string;
  };
  /** Opções do formulário de lead (fonte, estágio de vida, atribuído a). Deferida. */
  lead_opcoes?: {
    fontes: Opcao[];
    estagios: Opcao[];
    usuarios: Opcao[];
  };
}

interface Opcao {
  value: string;
  label: string;
}

type ClienteCreateFormData = ClienteFormShared & {
  prefix: string;
  crm_source?: string;
  crm_life_stage?: string;
  user_id?: string[];
};

// Sentinela do Radix Select (não aceita value=""), mapeado pra '' no estado.
const NENHUM = '__none__';

export default function ClienteCreate(props: ClienteCreatePageProps) {
  // `destino` só vem do LeadController::create(); a prop `lead_opcoes` é deferida e chega depois.
  const ehLead = props.destino !== undefined;
  const destino = props.destino ?? {
    url: '/contacts',
    voltar_href: '/contacts/customer',
    voltar_label: 'Voltar para clientes',
    titulo: 'Novo cliente',
    subtitulo: '',
    salvar: 'Salvar cliente',
  };
  const { data, setData, post, processing, errors, transform } = useForm<ClienteCreateFormData>({
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
    shipping_address: '',
    customer_group_id: '',
    opening_balance: '0',
    credit_limit: '',
    cpf_cnpj: '',
    rg: '',
    inscricao_estadual: '',
    inscricao_municipal: '',
    indicador_ie: '',
    nome_fantasia: '',
    consumidor_final: false,
    contribuinte: true,
    regime: '',
    suframa: '',
    ...(ehLead ? { crm_source: '', crm_life_stage: '', user_id: [] } : {}),
  });

  const isJuridica = data.contact_type_radio === 'business';

  // Slice 5a — preenche campos fora de DadosFiscaisBRData (razão social + endereço).
  const handleCnpjLookup = (api: BrasilApiCnpjData) => {
    if (api.razao_social) {
      setData('supplier_business_name', api.razao_social);
      if (!data.first_name) setData('first_name', api.razao_social);
    }
    if (api.logradouro) {
      const numero = api.numero ? `, ${api.numero}` : '';
      const bairro = api.bairro ? ` — ${api.bairro}` : '';
      setData('address_line_1', `${api.logradouro}${numero}${bairro}`);
    }
    if (api.municipio) setData('city', api.municipio);
    if (api.uf) setData('state', api.uf);
    if (api.cep) setData('zip_code', api.cep);
  };

  // Lead: o store do Crm grava o documento em `tax_number` (não lê cpf_cnpj).
  transform((payload) => {
    const cpf_cnpj = unmaskDigits(payload.cpf_cnpj);
    return ehLead
      ? { ...payload, cpf_cnpj, tax_number: payload.tax_number || cpf_cnpj }
      : { ...payload, cpf_cnpj };
  });

  const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (props.destino) {
      post(props.destino.url, { preserveScroll: true });
      return;
    }
    post('/contacts', { preserveScroll: true });
  };

  const err = errors as Record<string, string | undefined>;

  return (
    <div className="flex-1 bg-muted/30">
      <div className="border-b border-border bg-background">
        <div className="container mx-auto max-w-5xl px-8 pb-4 pt-6">
          <a
            href={destino.voltar_href}
            className="mb-2 inline-flex items-center text-xs text-muted-foreground transition-colors hover:text-foreground"
          >
            <ChevronLeft size={14} className="mr-1" />
            {destino.voltar_label}
          </a>
          <h1 className="text-2xl font-semibold tracking-tight text-foreground">{destino.titulo}</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {ehLead ? (
              destino.subtitulo
            ) : (
              <>
                Preencha os dados do cliente. Campos com <span className="cw-req">*</span> são obrigatórios.
              </>
            )}
          </p>
        </div>
      </div>

      <div className="container mx-auto max-w-5xl px-8 py-5">
        {ehLead && (
          <div className="mb-3 space-y-3">
            {err.msg && (
              <p role="alert" className="text-sm text-destructive">
                {err.msg}
              </p>
            )}
            <FormSection title="Dados do lead" icon={<Target />}>
              <Deferred data="lead_opcoes" fallback={<Skeleton className="h-16 w-full" />}>
                <FormGrid>
                  <Field label="Fonte" error={err.crm_source}>
                    <OpcaoSelect
                      rotulo="Fonte"
                      valor={data.crm_source ?? ''}
                      opcoes={props.lead_opcoes?.fontes ?? []}
                      onChange={(v) => setData('crm_source', v)}
                    />
                  </Field>
                  <Field label="Estágio de vida" error={err.crm_life_stage}>
                    <OpcaoSelect
                      rotulo="Estágio de vida"
                      valor={data.crm_life_stage ?? ''}
                      opcoes={props.lead_opcoes?.estagios ?? []}
                      onChange={(v) => setData('crm_life_stage', v)}
                    />
                  </Field>
                  <Field label="Atribuído a" error={err.user_id} fullRow>
                    <OpcaoSelect
                      rotulo="Atribuído a"
                      valor={data.user_id?.[0] ?? ''}
                      opcoes={props.lead_opcoes?.usuarios ?? []}
                      onChange={(v) => setData('user_id', v ? [v] : [])}
                    />
                  </Field>
                </FormGrid>
              </Deferred>
            </FormSection>
          </div>
        )}
        <ClienteForm
          data={data}
          setData={setData}
          errors={errors}
          types={props.types}
          customerGroups={props.customer_groups}
          isJuridica={isJuridica}
          onCnpjLookup={handleCnpjLookup}
          processing={processing}
          submitLabel={destino.salvar}
          cancelHref={destino.voltar_href}
          onSubmit={handleSubmit}
        />
      </div>
    </div>
  );
}

function OpcaoSelect({
  rotulo,
  valor,
  opcoes,
  onChange,
}: {
  rotulo: string;
  valor: string;
  opcoes: Opcao[];
  onChange: (v: string) => void;
}) {
  return (
    <Select value={valor || NENHUM} onValueChange={(v) => onChange(v === NENHUM ? '' : v)}>
      <SelectTrigger className="cw-input" aria-label={rotulo}>
        <SelectValue />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={NENHUM}>— Nenhum —</SelectItem>
        {opcoes
          .filter((o) => Boolean(o.value))
          .map((o) => (
            <SelectItem key={o.value} value={o.value}>
              {o.label}
            </SelectItem>
          ))}
      </SelectContent>
    </Select>
  );
}

ClienteCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
