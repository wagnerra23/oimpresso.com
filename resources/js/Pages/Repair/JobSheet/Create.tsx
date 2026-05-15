// @memcofre tela=/repair/job-sheet/create module=Repair
// Wave 3 B6 MWART — JobSheet Create port Blade → Inertia.
// OS nasce SEM current_stage_id (legacy). Pipeline FSM iniciado opt-in no Show.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, Link, Deferred } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Save, X, Plus, Wrench } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import PageHeader from '@/Components/shared/PageHeader';

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

export default function JobSheetCreate({ options, walk_in_customer, default_status }: Props) {
  const { data, setData, processing, errors, post } = useForm({
    contact_id: walk_in_customer?.id ?? '',
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

  const onSubmit = (e: FormEvent, submitType: 'save' | 'save_and_add_parts' | 'save_and_upload_docs') => {
    e.preventDefault();
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
                <div>
                  <Label htmlFor="contact_id">Cliente</Label>
                  <Input
                    id="contact_id"
                    type="number"
                    value={data.contact_id as string | number}
                    onChange={(e) => setData('contact_id', e.target.value)}
                    placeholder={walk_in_customer ? `${walk_in_customer.id} (walk-in)` : 'ID do contato'}
                  />
                  {errors.contact_id && (
                    <p className="text-xs text-destructive mt-1">{errors.contact_id}</p>
                  )}
                </div>
                <div>
                  <Label htmlFor="service_type">Tipo de serviço</Label>
                  <Input
                    id="service_type"
                    value={data.service_type}
                    onChange={(e) => setData('service_type', e.target.value)}
                    placeholder="Ex: Reparo, Calibração"
                  />
                </div>
                <div>
                  <Label htmlFor="location_id">Local de atendimento</Label>
                  <select
                    id="location_id"
                    value={data.location_id}
                    onChange={(e) => setData('location_id', e.target.value)}
                    className="w-full rounded-md border px-3 py-2 text-sm"
                  >
                    <option value="">— Selecione —</option>
                    {options?.business_locations &&
                      Object.entries(options.business_locations).map(([id, name]) => (
                        <option key={id} value={id}>
                          {name}
                        </option>
                      ))}
                  </select>
                </div>
                <div>
                  <Label htmlFor="service_staff">Técnico</Label>
                  <select
                    id="service_staff"
                    value={data.service_staff}
                    onChange={(e) => setData('service_staff', e.target.value)}
                    className="w-full rounded-md border px-3 py-2 text-sm"
                  >
                    <option value="">— Sem responsável —</option>
                    {options?.technecians &&
                      Object.entries(options.technecians).map(([id, name]) => (
                        <option key={id} value={id}>
                          {name}
                        </option>
                      ))}
                  </select>
                </div>
                <div>
                  <Label htmlFor="status_id">Status inicial</Label>
                  <select
                    id="status_id"
                    value={data.status_id as string | number}
                    onChange={(e) => setData('status_id', e.target.value)}
                    className="w-full rounded-md border px-3 py-2 text-sm"
                  >
                    <option value="">— Status —</option>
                    {options?.repair_statuses &&
                      Object.entries(options.repair_statuses).map(([id, name]) => (
                        <option key={id} value={id}>
                          {name}
                        </option>
                      ))}
                  </select>
                </div>
                <div>
                  <Label htmlFor="delivery_date">Prazo de entrega</Label>
                  <Input
                    id="delivery_date"
                    type="date"
                    value={data.delivery_date}
                    onChange={(e) => setData('delivery_date', e.target.value)}
                  />
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
                    <select
                      id="brand_id"
                      value={data.brand_id}
                      onChange={(e) => setData('brand_id', e.target.value)}
                      className="w-full rounded-md border px-3 py-2 text-sm"
                    >
                      <option value="">— Marca —</option>
                      {Object.entries(options.brands).map(([id, name]) => (
                        <option key={id} value={id}>
                          {name}
                        </option>
                      ))}
                    </select>
                  </div>
                )}
                <div>
                  <Label htmlFor="device_id">Tipo de aparelho</Label>
                  <select
                    id="device_id"
                    value={data.device_id}
                    onChange={(e) => setData('device_id', e.target.value)}
                    className="w-full rounded-md border px-3 py-2 text-sm"
                  >
                    <option value="">— Aparelho —</option>
                    {options?.devices &&
                      Object.entries(options.devices).map(([id, name]) => (
                        <option key={id} value={id}>
                          {name}
                        </option>
                      ))}
                  </select>
                </div>
                <div>
                  <Label htmlFor="device_model_id">Modelo</Label>
                  <select
                    id="device_model_id"
                    value={data.device_model_id}
                    onChange={(e) => setData('device_model_id', e.target.value)}
                    className="w-full rounded-md border px-3 py-2 text-sm"
                  >
                    <option value="">— Modelo —</option>
                    {options?.device_models &&
                      Object.entries(options.device_models).map(([id, name]) => (
                        <option key={id} value={id}>
                          {name}
                        </option>
                      ))}
                  </select>
                </div>
                {options?.repair_settings?.show_serial_no_in_job_sheet !== false && (
                  <div>
                    <Label htmlFor="serial_no">Nº de série</Label>
                    <Input
                      id="serial_no"
                      value={data.serial_no}
                      onChange={(e) => setData('serial_no', e.target.value)}
                    />
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
                </div>
                <div>
                  <Label htmlFor="product_condition">Condição do produto recebido</Label>
                  <Textarea
                    id="product_condition"
                    value={data.product_condition}
                    onChange={(e) => setData('product_condition', e.target.value)}
                    rows={2}
                  />
                </div>
              </div>
            </section>
          </div>
        </Deferred>
      </form>
    </AppShellV2>
  );
}
