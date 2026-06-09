// @memcofre tela=/oficina-auto/veiculos/create module=OficinaAuto
// V0 scaffold (US-OFICINA-001) — ADR 0137. Form de criação de veículo.
// RUNBOOK: memory/requisitos/OficinaAuto/RUNBOOK-create.md
// Charter: Create.charter.md (paridade de campos + erros em todos os campos com Edit).

import * as React from 'react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Loader2, Save, Search } from 'lucide-react';
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
import { Inline } from '@/Components/layout';

interface Props {
  vehicleTypes: Record<string, string>;
}

// Ordem de foco/scroll quando o servidor retorna erros de validação.
const FIELD_ORDER = [
  'plate',
  'secondary_plate',
  'chassis',
  'secondary_chassis',
  'vehicle_type',
  'manufacture_year',
  'model_year',
  'renavam',
  'engine',
  'fuel_type',
  'color',
  'mileage_at_entry',
  'notes',
] as const;

export default function VehiclesCreate({ vehicleTypes }: Props) {
  const { data, setData, post, processing, errors } = useForm({
    plate: '',
    secondary_plate: '',
    chassis: '',
    secondary_chassis: '',
    vehicle_type: 'automovel',
    manufacture_year: '',
    model_year: '',
    renavam: '',
    engine: '',
    mileage_at_entry: '',
    fuel_type: '',
    color: '',
    notes: '',
  });

  // Consulta de placa (charter v2): digita placa → busca dados técnicos do veículo.
  // Só dados técnicos — proprietário NÃO é consultado nem preenchido (escopo LGPD).
  const [lookupLoading, setLookupLoading] = React.useState(false);
  const [lookupFeedback, setLookupFeedback] = React.useState<{
    kind: 'success' | 'info' | 'error';
    text: string;
  } | null>(null);

  async function handlePlateLookup() {
    const plate = data.plate.trim();
    if (!plate) {
      setLookupFeedback({ kind: 'error', text: 'Digite a placa antes de buscar.' });
      return;
    }

    setLookupLoading(true);
    setLookupFeedback(null);

    try {
      const csrf =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

      const response = await fetch('/oficina-auto/veiculos/consulta-placa', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ plate }),
      });

      const json = await response.json().catch(() => null);

      if (!json) {
        setLookupFeedback({ kind: 'error', text: 'Consulta indisponível. Preencha os dados manualmente.' });
        return;
      }

      if (!json.found) {
        setLookupFeedback({
          kind: response.ok ? 'info' : 'error',
          text: json.message ?? 'Nenhum dado encontrado para esta placa.',
        });
        return;
      }

      // Auto-preenche os campos técnicos retornados (só colunas existentes).
      const fields = (json.data?.fields ?? {}) as Record<string, string | number>;
      const updates: Record<string, string> = {};
      Object.entries(fields).forEach(([key, value]) => {
        updates[key] = String(value);
      });

      const label: string | undefined = json.data?.brand_model_label ?? undefined;

      setData((previous) => ({
        ...previous,
        ...(updates as Partial<typeof previous>),
        // Marca/modelo não têm coluna V0 — registra em notes só se estiver vazio.
        notes: previous.notes || label || previous.notes,
      }));

      setLookupFeedback({
        kind: 'success',
        text: label ? `Dados encontrados: ${label}.` : 'Dados do veículo preenchidos.',
      });
    } catch {
      setLookupFeedback({ kind: 'error', text: 'Erro de rede na consulta. Preencha os dados manualmente.' });
    } finally {
      setLookupLoading(false);
    }
  }

  // Foca + rola até o primeiro campo inválido quando o servidor responde com erros.
  React.useEffect(() => {
    const firstInvalid = FIELD_ORDER.find((field) => errors[field]);
    if (!firstInvalid) return;
    const el = document.getElementById(firstInvalid);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'center' });
      el.focus({ preventScroll: true });
    }
  }, [errors]);

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    post('/oficina-auto/veiculos');
  }

  return (
    <AppShellV2>
      <Head title="Novo veículo · Oficina Auto" />
      <div className="px-4 py-6 max-w-3xl mx-auto">
        <PageHeader
          title="Novo veículo"
          description="Cadastro V0 — campos completos (CRLV/FIPE em Sprint 5+)"
          icon="car"
          action={
            <Link href="/oficina-auto/veiculos">
              <Button variant="ghost">
                <ArrowLeft className="size-4 mr-1" />
                Voltar
              </Button>
            </Link>
          }
        />

        <form onSubmit={handleSubmit} className="space-y-4 pt-4">
          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="plate">Placa principal *</Label>
              <Inline gap={2} align="start">
                <Input
                  id="plate"
                  value={data.plate}
                  onChange={(e) => setData('plate', e.target.value.toUpperCase())}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      void handlePlateLookup();
                    }
                  }}
                  maxLength={10}
                  required
                  className="flex-1"
                  aria-invalid={!!errors.plate}
                />
                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => void handlePlateLookup()}
                  disabled={lookupLoading || !data.plate}
                  title="Buscar dados do veículo pela placa"
                >
                  {lookupLoading ? (
                    <Loader2 className="size-4 animate-spin" />
                  ) : (
                    <Search className="size-4" />
                  )}
                  <span className="ml-1">Buscar</span>
                </Button>
              </Inline>
              {errors.plate && <p className="text-sm text-destructive mt-1">{errors.plate}</p>}
              {lookupFeedback && (
                <p
                  className={
                    'text-sm mt-1 ' +
                    (lookupFeedback.kind === 'success'
                      ? 'text-success'
                      : lookupFeedback.kind === 'error'
                        ? 'text-destructive'
                        : 'text-muted-foreground')
                  }
                  role="status"
                  aria-live="polite"
                >
                  {lookupFeedback.text}
                </p>
              )}
            </div>
            <div>
              <Label htmlFor="secondary_plate">Placa secundária (cavalo+reboque)</Label>
              <Input
                id="secondary_plate"
                value={data.secondary_plate}
                onChange={(e) => setData('secondary_plate', e.target.value.toUpperCase())}
                maxLength={10}
                aria-invalid={!!errors.secondary_plate}
              />
              {errors.secondary_plate && (
                <p className="text-sm text-destructive mt-1">{errors.secondary_plate}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="chassis">Chassi</Label>
              <Input
                id="chassis"
                value={data.chassis}
                onChange={(e) => setData('chassis', e.target.value)}
                maxLength={30}
                aria-invalid={!!errors.chassis}
              />
              {errors.chassis && <p className="text-sm text-destructive mt-1">{errors.chassis}</p>}
            </div>
            <div>
              <Label htmlFor="secondary_chassis">Chassi secundário</Label>
              <Input
                id="secondary_chassis"
                value={data.secondary_chassis}
                onChange={(e) => setData('secondary_chassis', e.target.value)}
                maxLength={30}
                aria-invalid={!!errors.secondary_chassis}
              />
              {errors.secondary_chassis && (
                <p className="text-sm text-destructive mt-1">{errors.secondary_chassis}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="vehicle_type">Tipo *</Label>
              <Select value={data.vehicle_type} onValueChange={(v) => setData('vehicle_type', v)}>
                <SelectTrigger id="vehicle_type" aria-invalid={!!errors.vehicle_type}>
                  <SelectValue placeholder="Selecione o tipo" />
                </SelectTrigger>
                <SelectContent>
                  {Object.entries(vehicleTypes).map(([k, label]) => (
                    <SelectItem key={k} value={k}>
                      {label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.vehicle_type && (
                <p className="text-sm text-destructive mt-1">{errors.vehicle_type}</p>
              )}
            </div>
            <div>
              <Label htmlFor="manufacture_year">Ano fabricação</Label>
              <Input
                id="manufacture_year"
                type="number"
                value={data.manufacture_year}
                onChange={(e) => setData('manufacture_year', e.target.value)}
                min={1900}
                max={2100}
                aria-invalid={!!errors.manufacture_year}
              />
              {errors.manufacture_year && (
                <p className="text-sm text-destructive mt-1">{errors.manufacture_year}</p>
              )}
            </div>
            <div>
              <Label htmlFor="model_year">Ano modelo</Label>
              <Input
                id="model_year"
                type="number"
                value={data.model_year}
                onChange={(e) => setData('model_year', e.target.value)}
                min={1900}
                max={2100}
                aria-invalid={!!errors.model_year}
              />
              {errors.model_year && (
                <p className="text-sm text-destructive mt-1">{errors.model_year}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <Label htmlFor="renavam">Renavam</Label>
              <Input
                id="renavam"
                value={data.renavam}
                onChange={(e) => setData('renavam', e.target.value)}
                maxLength={11}
                aria-invalid={!!errors.renavam}
              />
              {errors.renavam && <p className="text-sm text-destructive mt-1">{errors.renavam}</p>}
            </div>
            <div>
              <Label htmlFor="engine">Motor</Label>
              <Input
                id="engine"
                value={data.engine}
                onChange={(e) => setData('engine', e.target.value)}
                maxLength={50}
                aria-invalid={!!errors.engine}
              />
              {errors.engine && <p className="text-sm text-destructive mt-1">{errors.engine}</p>}
            </div>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div>
              <Label htmlFor="fuel_type">Combustível</Label>
              <Input
                id="fuel_type"
                value={data.fuel_type}
                onChange={(e) => setData('fuel_type', e.target.value)}
                maxLength={30}
                aria-invalid={!!errors.fuel_type}
              />
              {errors.fuel_type && (
                <p className="text-sm text-destructive mt-1">{errors.fuel_type}</p>
              )}
            </div>
            <div>
              <Label htmlFor="color">Cor</Label>
              <Input
                id="color"
                value={data.color}
                onChange={(e) => setData('color', e.target.value)}
                maxLength={30}
                aria-invalid={!!errors.color}
              />
              {errors.color && <p className="text-sm text-destructive mt-1">{errors.color}</p>}
            </div>
            <div>
              <Label htmlFor="mileage_at_entry">KM entrada</Label>
              <Input
                id="mileage_at_entry"
                type="number"
                value={data.mileage_at_entry}
                onChange={(e) => setData('mileage_at_entry', e.target.value)}
                min={0}
                aria-invalid={!!errors.mileage_at_entry}
              />
              {errors.mileage_at_entry && (
                <p className="text-sm text-destructive mt-1">{errors.mileage_at_entry}</p>
              )}
            </div>
          </div>

          <div>
            <Label htmlFor="notes">Observações</Label>
            <Textarea
              id="notes"
              value={data.notes}
              onChange={(e) => setData('notes', e.target.value)}
              rows={3}
              aria-invalid={!!errors.notes}
            />
            {errors.notes && <p className="text-sm text-destructive mt-1">{errors.notes}</p>}
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t">
            <Link href="/oficina-auto/veiculos">
              <Button variant="outline" type="button">Cancelar</Button>
            </Link>
            <Button type="submit" disabled={processing}>
              <Save className="size-4 mr-1" />
              Salvar veículo
            </Button>
          </div>
        </form>
      </div>
    </AppShellV2>
  );
}
