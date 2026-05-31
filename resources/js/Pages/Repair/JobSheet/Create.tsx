// @memcofre tela=/repair/job-sheet/create module=Repair
// Wave 3 B6 MWART — JobSheet Create port Blade → Inertia.
// OS nasce SEM current_stage_id (legacy). Pipeline FSM iniciado opt-in no Show.
//
// Score-up 2026-05-31 (board: "cliente por ID numérico e validação incompleta"):
//   - contact_id numérico → CustomerSearchAutocomplete REUSADO do Sells (busca por
//     nome/CPF-CNPJ/telefone via GET /contacts/customers + quick-add inline). Sem
//     recriar — R-DS-001 reuso sob demanda. Walk-in continua como default.
//   - Selects nativos → Select DS (shadcn compound) com SelectValue/placeholder.
//   - Validação client-side inline em TODOS os obrigatórios (contact_id required —
//     StoreJobSheetRequest exige) + indicadores `*` + foco/scroll no 1º erro. Erros
//     do servidor (errors do useForm) continuam exibidos por campo.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, Link, Deferred } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { Save, X, Plus, Wrench } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';
import CustomerSearchAutocomplete, {
  type CustomerSearchResult,
} from '@/Pages/Sells/_components/CustomerSearchAutocomplete';

interface DropdownOption {
  [key: string]: string;
}

interface Options {
  repair_statuses: DropdownOption;
  device_models: DropdownOption;
  brands: DropdownOption;
  devices: DropdownOption;
  technecians: DropdownOption;
  business_locations: DropdownOption;
  repair_settings: {
    show_serial_no_in_job_sheet?: boolean;
    enable_brand_in_job_sheet?: boolean;
  };
}

interface WalkInCustomer {
  id: number;
  name: string;
}

interface Props {
  options?: Options;
  walk_in_customer: WalkInCustomer | null;
  default_status: string | number | '';
}

// Erro inline reutilizável (server error OU client validation).
function FieldError({ message }: { message?: string }) {
  if (!message) return null;
  return (
    <p className="mt-1 text-xs text-destructive" role="alert">
      {message}
    </p>
  );
}

// Label com asterisco pra campo obrigatório.
function ReqLabel({ htmlFor, children }: { htmlFor: string; children: ReactNode }) {
  return (
    <Label htmlFor={htmlFor}>
      {children} <span className="text-destructive">*</span>
    </Label>
  );
}

export default function JobSheetCreate({ options, walk_in_customer, default_status }: Props) {
  const { data, setData, processing, errors, post } = useForm({
    contact_id: walk_in_customer?.id ?? ('' as number | ''),
    service_type: '',
    brand_id: '',
    device_id: '',
    device_model_id: '',
    security_pwd: '',
    security_pattern: '',
    serial_no: '',
    status_id: default_status ?? '',
    delivery_date: '',
    estimated_cost: '',
    product_configuration: '',
    defects: '',
    product_condition: '',
    service_staff: '',
    location_id: '',
    pick_up_on_site_addr: '',
    comment_by_ss: '',
    custom_field_1: '',
    custom_field_2: '',
    custom_field_3: '',
    custom_field_4: '',
    custom_field_5: '',
    submit_type: 'save' as 'save' | 'save_and_add_parts' | 'save_and_upload_docs',
  });

  // Erros de validação client-side (chave = nome do campo).
  const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

  const errorFor = (field: string): string | undefined =>
    clientErrors[field] ?? (errors[field as keyof typeof errors] as string | undefined);

  const handleCustomerSelect = (c: CustomerSearchResult) => {
    setData('contact_id', c.id);
    setClientErrors((prev) => {
      if (!prev.contact_id) return prev;
      const clone = { ...prev };
      delete clone.contact_id;
      return clone;
    });
  };

  const handleCustomerClear = () => {
    // Volta pro walk-in default (paridade Sells handleCustomerClear).
    setData('contact_id', walk_in_customer?.id ?? '');
  };

  // Setter de Select que também limpa erro do campo.
  const setSelect = (field: keyof typeof data, value: string) => {
    setData(field, value);
    setClientErrors((prev) => {
      if (!prev[field as string]) return prev;
      const clone = { ...prev };
      delete clone[field as string];
      return clone;
    });
  };

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    // contact_id é o ÚNICO required no StoreJobSheetRequest (rules: required|
    // integer|exists). Demais campos são nullable no backend — não bloqueamos
    // client-side pra não divergir do contrato (over-validation).
    if (data.contact_id === '' || data.contact_id === null || data.contact_id === undefined) {
      errs.contact_id = 'Selecione um cliente.';
    }
    setClientErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const onSubmit = (
    e: FormEvent,
    submitType: 'save' | 'save_and_add_parts' | 'save_and_upload_docs'
  ) => {
    e.preventDefault();
    if (!validate()) {
      // Foca/scrolla pro campo obrigatório pendente (contact_id).
      document
        .getElementById('contact_id')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    setData('submit_type', submitType);
    post('/repair/job-sheet');
  };

  return (
    <AppShellV2>
      <form onSubmit={(e) => onSubmit(e, 'save')} className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="plus"
          title="Nova Ordem de Serviço"
          description="Cadastre uma OS pra reparo"
          action={
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" asChild>
                <Link href="/repair/job-sheet">
                  <X className="mr-1 h-4 w-4" /> Cancelar
                </Link>
              </Button>
              <Button type="submit" size="sm" disabled={processing}>
                <Save className="mr-1 h-4 w-4" /> Salvar
              </Button>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                disabled={processing}
                onClick={(e) => onSubmit(e as unknown as FormEvent, 'save_and_add_parts')}
              >
                <Plus className="mr-1 h-4 w-4" /> Salvar e adicionar peças
              </Button>
            </div>
          }
        />

        <Deferred data="options" fallback={<p className="text-xs text-muted-foreground italic">Carregando opções…</p>}>
          <div className="space-y-4">
            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold">Cliente & Atendimento</h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div id="contact_id" className="scroll-mt-24">
                  <ReqLabel htmlFor="contact_id">Cliente</ReqLabel>
                  <CustomerSearchAutocomplete
                    defaultName={walk_in_customer?.name ?? 'Selecionar cliente'}
                    onSelect={handleCustomerSelect}
                    onClear={handleCustomerClear}
                    placeholder="Buscar cliente por nome, CPF/CNPJ ou telefone…"
                  />
                  <p className="mt-1 text-xs text-muted-foreground">
                    Digite ≥2 caracteres pra buscar.
                    {walk_in_customer ? ' Limpe pra voltar ao cliente padrão.' : ''}
                  </p>
                  <FieldError message={errorFor('contact_id')} />
                </div>
                <div>
                  <Label htmlFor="service_type">Tipo de serviço</Label>
                  <Input
                    id="service_type"
                    value={data.service_type}
                    onChange={(e) => setData('service_type', e.target.value)}
                    placeholder="Ex: Reparo, Calibração"
                  />
                  <FieldError message={errorFor('service_type')} />
                </div>
                <div>
                  <Label htmlFor="location_id">Local de atendimento</Label>
                  <Select value={data.location_id} onValueChange={(v) => setSelect('location_id', v)}>
                    <SelectTrigger id="location_id" className="w-full">
                      <SelectValue placeholder="— Selecione —" />
                    </SelectTrigger>
                    <SelectContent>
                      {options?.business_locations &&
                        Object.entries(options.business_locations).map(([id, name]) => (
                          <SelectItem key={id} value={id}>
                            {name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errorFor('location_id')} />
                </div>
                <div>
                  <Label htmlFor="service_staff">Técnico</Label>
                  <Select value={data.service_staff} onValueChange={(v) => setSelect('service_staff', v)}>
                    <SelectTrigger id="service_staff" className="w-full">
                      <SelectValue placeholder="— Sem responsável —" />
                    </SelectTrigger>
                    <SelectContent>
                      {options?.technecians &&
                        Object.entries(options.technecians).map(([id, name]) => (
                          <SelectItem key={id} value={id}>
                            {name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errorFor('service_staff')} />
                </div>
                <div>
                  <Label htmlFor="status_id">Status inicial</Label>
                  <Select
                    value={data.status_id as string}
                    onValueChange={(v) => setSelect('status_id', v)}
                  >
                    <SelectTrigger id="status_id" className="w-full">
                      <SelectValue placeholder="— Status —" />
                    </SelectTrigger>
                    <SelectContent>
                      {options?.repair_statuses &&
                        Object.entries(options.repair_statuses).map(([id, name]) => (
                          <SelectItem key={id} value={id}>
                            {name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errorFor('status_id')} />
                </div>
                <div>
                  <Label htmlFor="delivery_date">Prazo de entrega</Label>
                  <Input
                    id="delivery_date"
                    type="date"
                    value={data.delivery_date}
                    onChange={(e) => setData('delivery_date', e.target.value)}
                  />
                  <FieldError message={errorFor('delivery_date')} />
                </div>
              </div>
            </section>

            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold flex items-center gap-2">
                <Wrench className="h-4 w-4" /> Aparelho
              </h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {options?.repair_settings?.enable_brand_in_job_sheet && (
                  <div>
                    <Label htmlFor="brand_id">Marca</Label>
                    <Select value={data.brand_id} onValueChange={(v) => setSelect('brand_id', v)}>
                      <SelectTrigger id="brand_id" className="w-full">
                        <SelectValue placeholder="— Marca —" />
                      </SelectTrigger>
                      <SelectContent>
                        {Object.entries(options.brands).map(([id, name]) => (
                          <SelectItem key={id} value={id}>
                            {name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <FieldError message={errorFor('brand_id')} />
                  </div>
                )}
                <div>
                  <Label htmlFor="device_id">Tipo de aparelho</Label>
                  <Select value={data.device_id} onValueChange={(v) => setSelect('device_id', v)}>
                    <SelectTrigger id="device_id" className="w-full">
                      <SelectValue placeholder="— Aparelho —" />
                    </SelectTrigger>
                    <SelectContent>
                      {options?.devices &&
                        Object.entries(options.devices).map(([id, name]) => (
                          <SelectItem key={id} value={id}>
                            {name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errorFor('device_id')} />
                </div>
                <div>
                  <Label htmlFor="device_model_id">Modelo</Label>
                  <Select
                    value={data.device_model_id}
                    onValueChange={(v) => setSelect('device_model_id', v)}
                  >
                    <SelectTrigger id="device_model_id" className="w-full">
                      <SelectValue placeholder="— Modelo —" />
                    </SelectTrigger>
                    <SelectContent>
                      {options?.device_models &&
                        Object.entries(options.device_models).map(([id, name]) => (
                          <SelectItem key={id} value={id}>
                            {name}
                          </SelectItem>
                        ))}
                    </SelectContent>
                  </Select>
                  <FieldError message={errorFor('device_model_id')} />
                </div>
                {options?.repair_settings?.show_serial_no_in_job_sheet !== false && (
                  <div>
                    <Label htmlFor="serial_no">Nº de série</Label>
                    <Input
                      id="serial_no"
                      value={data.serial_no}
                      onChange={(e) => setData('serial_no', e.target.value)}
                    />
                    <FieldError message={errorFor('serial_no')} />
                  </div>
                )}
                <div>
                  <Label htmlFor="estimated_cost">Valor estimado</Label>
                  <Input
                    id="estimated_cost"
                    type="number"
                    step="0.01"
                    value={data.estimated_cost}
                    onChange={(e) => setData('estimated_cost', e.target.value)}
                  />
                  <FieldError message={errorFor('estimated_cost')} />
                </div>
              </div>
            </section>

            <section className="rounded-lg border bg-card p-4 space-y-3">
              <h2 className="text-sm font-semibold">Defeitos & Condição</h2>
              <div className="space-y-3">
                <div>
                  <Label htmlFor="defects">Defeitos relatados</Label>
                  <Textarea
                    id="defects"
                    value={data.defects}
                    onChange={(e) => setData('defects', e.target.value)}
                    rows={3}
                    placeholder="Cliente relata que..."
                  />
                  <FieldError message={errorFor('defects')} />
                </div>
                <div>
                  <Label htmlFor="product_condition">Condição do produto recebido</Label>
                  <Textarea
                    id="product_condition"
                    value={data.product_condition}
                    onChange={(e) => setData('product_condition', e.target.value)}
                    rows={2}
                  />
                  <FieldError message={errorFor('product_condition')} />
                </div>
              </div>
            </section>
          </div>
        </Deferred>
      </form>
    </AppShellV2>
  );
}
