// @memcofre tela=/repair/job-sheet/{id}/edit module=Repair
// Wave 3 B6 MWART — JobSheet Edit port Blade → Inertia.
// FSM transitions ficam no Show (não aqui).

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, Link, Deferred } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Save, X, Wrench, FileText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import PageHeader from '@/Components/shared/PageHeader';

interface JobSheetPayload {
  id: number;
  job_sheet_no: string | null;
  contact_id: number | null;
  service_type: string | null;
  brand_id: number | null;
  device_id: number | null;
  device_model_id: number | null;
  security_pwd: string | null;
  security_pattern: string | null;
  serial_no: string | null;
  status_id: number | null;
  delivery_date: string | null;
  estimated_cost: number | null;
  product_configuration: string | null;
  defects: string | null;
  product_condition: string | null;
  service_staff: number | null;
  pick_up_on_site_addr: string | null;
  comment_by_ss: string | null;
  custom_field_1: string | null;
  custom_field_2: string | null;
  custom_field_3: string | null;
  custom_field_4: string | null;
  custom_field_5: string | null;
  checklist: string[] | null;
}

interface DropdownOption {
  [key: string]: string;
}

interface Options {
  repair_statuses: DropdownOption;
  device_models: DropdownOption;
  brands: DropdownOption;
  devices: DropdownOption;
  technecians: DropdownOption;
  customer_groups: DropdownOption;
  repair_settings: {
    show_serial_no_in_job_sheet?: boolean;
    show_password_field_in_job_sheet?: boolean;
    show_pattern_field_in_job_sheet?: boolean;
    enable_brand_in_job_sheet?: boolean;
  };
}

interface Props {
  job_sheet: JobSheetPayload;
  options?: Options;
}

type TabId = 'cliente' | 'aparelho' | 'defeitos' | 'checklist';

export default function JobSheetEdit({ job_sheet, options }: Props) {
  const [activeTab, setActiveTab] = useState<TabId>('cliente');

  const { data, setData, processing, errors, put } = useForm({
    contact_id: job_sheet.contact_id ?? '',
    service_type: job_sheet.service_type ?? '',
    brand_id: job_sheet.brand_id ?? '',
    device_id: job_sheet.device_id ?? '',
    device_model_id: job_sheet.device_model_id ?? '',
    security_pwd: job_sheet.security_pwd ?? '',
    security_pattern: job_sheet.security_pattern ?? '',
    serial_no: job_sheet.serial_no ?? '',
    status_id: job_sheet.status_id ?? '',
    delivery_date: job_sheet.delivery_date ?? '',
    estimated_cost: job_sheet.estimated_cost ?? '',
    product_configuration: job_sheet.product_configuration ?? '',
    defects: job_sheet.defects ?? '',
    product_condition: job_sheet.product_condition ?? '',
    service_staff: job_sheet.service_staff ?? '',
    pick_up_on_site_addr: job_sheet.pick_up_on_site_addr ?? '',
    comment_by_ss: job_sheet.comment_by_ss ?? '',
    custom_field_1: job_sheet.custom_field_1 ?? '',
    custom_field_2: job_sheet.custom_field_2 ?? '',
    custom_field_3: job_sheet.custom_field_3 ?? '',
    custom_field_4: job_sheet.custom_field_4 ?? '',
    custom_field_5: job_sheet.custom_field_5 ?? '',
    repair_checklist: job_sheet.checklist ?? [],
  });

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    put(`/repair/job-sheet/${job_sheet.id}`);
  };

  const tabs: Array<{ id: TabId; label: string; hasError?: boolean }> = [
    { id: 'cliente', label: 'Cliente', hasError: !!(errors.contact_id || errors.service_staff) },
    {
      id: 'aparelho',
      label: 'Aparelho',
      hasError: !!(errors.brand_id || errors.device_id || errors.device_model_id || errors.serial_no),
    },
    { id: 'defeitos', label: 'Defeitos', hasError: !!(errors.defects || errors.product_condition) },
    { id: 'checklist', label: 'Checklist' },
  ];

  return (
    <AppShellV2>
      <form onSubmit={onSubmit} className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="edit"
          title={`Editar OS #${job_sheet.job_sheet_no ?? job_sheet.id}`}
          description="Atualize dados da ordem de serviço"
          action={
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" asChild>
                <Link href={`/repair/job-sheet/${job_sheet.id}`}>
                  <X className="mr-1 h-4 w-4" /> Cancelar
                </Link>
              </Button>
              <Button type="submit" size="sm" disabled={processing}>
                <Save className="mr-1 h-4 w-4" /> Salvar
              </Button>
            </div>
          }
        />

        <div className="flex gap-2 border-b">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={`px-3 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
                activeTab === tab.id
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              } ${tab.hasError ? 'text-destructive' : ''}`}
            >
              {tab.label}
              {tab.hasError && <span className="ml-1 text-destructive">!</span>}
            </button>
          ))}
        </div>

        <Deferred data="options" fallback={<p className="text-xs text-muted-foreground italic">Carregando opções…</p>}>
          <div className="rounded-lg border bg-card p-4 space-y-4">
            {activeTab === 'cliente' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                  <Label htmlFor="contact_id">Cliente</Label>
                  <Input
                    id="contact_id"
                    type="number"
                    value={data.contact_id as string | number}
                    onChange={(e) => setData('contact_id', e.target.value)}
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
                  />
                </div>
                <div>
                  <Label htmlFor="service_staff">Técnico responsável</Label>
                  <select
                    id="service_staff"
                    value={data.service_staff as string | number}
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
                  <Label htmlFor="status_id">Status (legacy)</Label>
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
              </div>
            )}

            {activeTab === 'aparelho' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {options?.repair_settings?.enable_brand_in_job_sheet && (
                  <div>
                    <Label htmlFor="brand_id">Marca</Label>
                    <select
                      id="brand_id"
                      value={data.brand_id as string | number}
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
                    value={data.device_id as string | number}
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
                    value={data.device_model_id as string | number}
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
                  <Label htmlFor="delivery_date">Prazo de entrega</Label>
                  <Input
                    id="delivery_date"
                    type="date"
                    value={data.delivery_date}
                    onChange={(e) => setData('delivery_date', e.target.value)}
                  />
                </div>
                <div>
                  <Label htmlFor="estimated_cost">Valor estimado</Label>
                  <Input
                    id="estimated_cost"
                    type="number"
                    step="0.01"
                    value={data.estimated_cost as string | number}
                    onChange={(e) => setData('estimated_cost', e.target.value)}
                  />
                </div>
              </div>
            )}

            {activeTab === 'defeitos' && (
              <div className="space-y-3">
                <div>
                  <Label htmlFor="defects">Defeitos relatados</Label>
                  <Textarea
                    id="defects"
                    value={data.defects}
                    onChange={(e) => setData('defects', e.target.value)}
                    rows={4}
                  />
                </div>
                <div>
                  <Label htmlFor="product_condition">Condição do produto</Label>
                  <Textarea
                    id="product_condition"
                    value={data.product_condition}
                    onChange={(e) => setData('product_condition', e.target.value)}
                    rows={3}
                  />
                </div>
                <div>
                  <Label htmlFor="product_configuration">Configuração</Label>
                  <Textarea
                    id="product_configuration"
                    value={data.product_configuration}
                    onChange={(e) => setData('product_configuration', e.target.value)}
                    rows={2}
                  />
                </div>
                <div>
                  <Label htmlFor="comment_by_ss">Comentário interno (técnico)</Label>
                  <Textarea
                    id="comment_by_ss"
                    value={data.comment_by_ss}
                    onChange={(e) => setData('comment_by_ss', e.target.value)}
                    rows={2}
                  />
                </div>
              </div>
            )}

            {activeTab === 'checklist' && (
              <div className="space-y-2">
                <p className="text-xs text-muted-foreground">
                  Checklist herda configuração do modelo selecionado. Marque itens validados.
                </p>
                {Array.isArray(data.repair_checklist) && data.repair_checklist.length > 0 ? (
                  data.repair_checklist.map((item, idx) => (
                    <label key={idx} className="flex items-center gap-2 text-sm">
                      <input type="checkbox" defaultChecked readOnly />
                      <span>{item}</span>
                    </label>
                  ))
                ) : (
                  <p className="text-xs text-muted-foreground italic">Sem checklist definido.</p>
                )}
              </div>
            )}
          </div>
        </Deferred>
      </form>
    </AppShellV2>
  );
}
