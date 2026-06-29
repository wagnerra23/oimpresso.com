// @memcofre tela=/repair/device-models/{id}/edit module=Repair
// Blade T1 Migration C (2026-05-17) — port DeviceModel Edit Blade → Inertia/React.
// Página dedicada substitui modal Blade legacy. Charter: Edit.charter.md.
// RUNBOOK: memory/requisitos/Repair/RUNBOOK-device-models.md

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, Link } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { Save, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import PageHeader from '@/Components/shared/PageHeader';

interface DeviceModelPayload {
  id: number;
  name: string;
  brand_id: number | null;
  device_id: number | null;
  repair_checklist: string | null;
}

interface DropdownMap {
  [key: string]: string;
}

interface Props {
  model: DeviceModelPayload;
  brands: DropdownMap;
  devices: DropdownMap;
}

export default function DeviceModelEdit({ model, brands, devices }: Props) {
  const { data, setData, processing, errors, put } = useForm({
    name: model.name ?? '',
    brand_id: model.brand_id ? String(model.brand_id) : '',
    device_id: model.device_id ? String(model.device_id) : '',
    repair_checklist: model.repair_checklist ?? '',
  });

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    put(`/repair/device-models/${model.id}`);
  };

  return (
    <form onSubmit={onSubmit} className="container mx-auto p-4 space-y-4 max-w-3xl">
      <PageHeader
        icon="edit"
        title={`Editar modelo: ${model.name}`}
        description="Atualize marca, categoria ou checklist padrão"
        action={
          <div className="flex gap-2">
            <Button type="button" variant="outline" size="sm" asChild>
              <Link href="/repair/device-models">
                <X className="mr-1 h-4 w-4" /> Cancelar
              </Link>
            </Button>
            <Button type="submit" size="sm" disabled={processing}>
              <Save className="mr-1 h-4 w-4" /> Atualizar
            </Button>
          </div>
        }
      />

      <div className="rounded-lg border border-border bg-card p-5 space-y-4">
        <div>
          <Label htmlFor="name">
            Nome do modelo <span className="text-destructive">*</span>
          </Label>
          <Input
            id="name"
            name="name"
            type="text"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            required
            autoFocus
          />
          {errors.name && <p className="mt-1 text-xs text-destructive">{errors.name}</p>}
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <Label htmlFor="brand_id">Marca</Label>
            <Select
              value={String(data.brand_id || '__none__')}
              onValueChange={(v) => setData('brand_id', v === '__none__' ? '' : v)}
            >
              <SelectTrigger id="brand_id" aria-label="Marca" className="w-full">
                <SelectValue placeholder="Selecione…" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">Selecione…</SelectItem>
                {Object.entries(brands ?? {}).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.brand_id && <p className="mt-1 text-xs text-destructive">{errors.brand_id}</p>}
          </div>

          <div>
            <Label htmlFor="device_id">Categoria / Dispositivo</Label>
            <Select
              value={String(data.device_id || '__none__')}
              onValueChange={(v) => setData('device_id', v === '__none__' ? '' : v)}
            >
              <SelectTrigger id="device_id" aria-label="Categoria / Dispositivo" className="w-full">
                <SelectValue placeholder="Selecione…" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="__none__">Selecione…</SelectItem>
                {Object.entries(devices ?? {}).map(([id, name]) => (
                  <SelectItem key={id} value={id}>
                    {name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.device_id && <p className="mt-1 text-xs text-destructive">{errors.device_id}</p>}
          </div>
        </div>

        <div>
          <Label htmlFor="repair_checklist">
            Checklist de reparo{' '}
            <span className="text-xs text-muted-foreground font-normal">(separe itens com `|`)</span>
          </Label>
          <Textarea
            id="repair_checklist"
            name="repair_checklist"
            rows={3}
            value={data.repair_checklist}
            onChange={(e) => setData('repair_checklist', e.target.value)}
          />
          {errors.repair_checklist && (
            <p className="mt-1 text-xs text-destructive">{errors.repair_checklist}</p>
          )}
        </div>
      </div>
    </form>
  );
}

DeviceModelEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
