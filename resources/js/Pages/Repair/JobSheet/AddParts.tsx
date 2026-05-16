// @memcofre tela=/repair/job-sheet/add-parts/{id} module=Repair
// Wave 3 B6 MWART — JobSheet AddParts port Blade → Inertia.
// SEM FSM (ação não-transitiva). Form lista peças editável.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, Link, router } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Save, X, Plus, Trash2, Wrench } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';

interface JobSheetMin {
  id: number;
  job_sheet_no: string | null;
  contact_name: string | null;
}

interface PartRow {
  variation_id: number | null;
  variation_name: string | null;
  quantity: number;
  unit: string | null;
  unit_price?: number | null;
}

interface DropdownOption {
  [key: string]: string;
}

interface Props {
  job_sheet: JobSheetMin;
  parts: PartRow[];
  status_update_data?: {
    status_id?: number;
  } | null;
  status_dropdown: DropdownOption;
  status_template_tags: Record<string, string>;
}

export default function JobSheetAddParts({
  job_sheet,
  parts: initialParts,
  status_update_data,
  status_dropdown,
}: Props) {
  const [parts, setParts] = useState<PartRow[]>(
    initialParts && initialParts.length > 0
      ? initialParts
      : [{ variation_id: null, variation_name: '', quantity: 1, unit: null }]
  );

  const { data, setData, processing, post } = useForm({
    status_id: status_update_data?.status_id ?? '',
    update_note: '',
    send_sms: false,
    send_email: false,
    sms_body: '',
    email_body: '',
    email_subject: '',
  });

  const addRow = () => {
    setParts([...parts, { variation_id: null, variation_name: '', quantity: 1, unit: null }]);
  };

  const removeRow = (idx: number) => {
    setParts(parts.filter((_, i) => i !== idx));
  };

  const updateRow = (idx: number, field: keyof PartRow, value: string | number | null) => {
    const next = [...parts];
    next[idx] = { ...next[idx], [field]: value };
    setParts(next);
  };

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    const payload: Record<string, unknown> = {
      parts: parts
        .filter((p) => p.variation_id)
        .map((p) => ({
          variation_id: p.variation_id,
          quantity: p.quantity,
        })),
      status_id: data.status_id,
      update_note: data.update_note,
      send_sms: data.send_sms,
      send_email: data.send_email,
      sms_body: data.sms_body,
      email_body: data.email_body,
      email_subject: data.email_subject,
    };
    router.post(`/repair/job-sheet/save-parts/${job_sheet.id}`, payload);
  };

  const hasStatusUpdate = !!status_update_data?.status_id;

  return (
    <AppShellV2>
      <form onSubmit={onSubmit} className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="wrench"
          title={`Adicionar peças — OS #${job_sheet.job_sheet_no ?? job_sheet.id}`}
          description={job_sheet.contact_name ?? 'Sem cliente'}
          action={
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" asChild>
                <Link href={`/repair/job-sheet/${job_sheet.id}`}>
                  <X className="mr-1 h-4 w-4" /> Cancelar
                </Link>
              </Button>
              <Button type="submit" size="sm" disabled={processing}>
                <Save className="mr-1 h-4 w-4" /> Salvar peças
              </Button>
            </div>
          }
        />

        <section className="rounded-lg border bg-card p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold flex items-center gap-2">
              <Wrench className="h-4 w-4" /> Peças usadas
            </h2>
            <Button type="button" size="sm" variant="outline" onClick={addRow}>
              <Plus className="mr-1 h-4 w-4" /> Adicionar peça
            </Button>
          </div>

          {parts.length === 0 ? (
            <p className="text-xs text-muted-foreground italic">Nenhuma peça adicionada.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-xs text-muted-foreground">
                  <th className="py-2 px-2">Variation ID</th>
                  <th className="py-2 px-2">Descrição</th>
                  <th className="py-2 px-2 w-24">Qtd</th>
                  <th className="py-2 px-2 w-20">Unid.</th>
                  <th className="py-2 px-2 w-12"></th>
                </tr>
              </thead>
              <tbody>
                {parts.map((row, idx) => (
                  <tr key={idx} className="border-b last:border-b-0">
                    <td className="py-2 px-2">
                      <Input
                        type="number"
                        value={row.variation_id ?? ''}
                        onChange={(e) =>
                          updateRow(idx, 'variation_id', e.target.value ? Number(e.target.value) : null)
                        }
                        placeholder="ID"
                        className="h-8"
                      />
                    </td>
                    <td className="py-2 px-2">
                      <Input
                        value={row.variation_name ?? ''}
                        onChange={(e) => updateRow(idx, 'variation_name', e.target.value)}
                        placeholder="Auto-preenche ao salvar"
                        className="h-8"
                      />
                    </td>
                    <td className="py-2 px-2">
                      <Input
                        type="number"
                        min="1"
                        value={row.quantity}
                        onChange={(e) => updateRow(idx, 'quantity', Number(e.target.value))}
                        className="h-8"
                      />
                    </td>
                    <td className="py-2 px-2 text-xs text-muted-foreground">{row.unit ?? '—'}</td>
                    <td className="py-2 px-2">
                      <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => removeRow(idx)}
                        className="h-7 w-7 p-0"
                      >
                        <Trash2 className="h-3 w-3" />
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>

        {hasStatusUpdate && (
          <section className="rounded-lg border bg-card p-4 space-y-3">
            <h2 className="text-sm font-semibold">Atualizar status (pendente do fluxo)</h2>
            <div>
              <Label htmlFor="status_id">Status</Label>
              <select
                id="status_id"
                value={data.status_id as string | number}
                onChange={(e) => setData('status_id', e.target.value)}
                className="w-full rounded-md border px-3 py-2 text-sm"
              >
                <option value="">— Status —</option>
                {Object.entries(status_dropdown).map(([id, name]) => (
                  <option key={id} value={id}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
          </section>
        )}
      </form>
    </AppShellV2>
  );
}
