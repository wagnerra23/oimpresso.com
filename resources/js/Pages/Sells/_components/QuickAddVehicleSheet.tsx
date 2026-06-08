// QuickAddVehicleSheet — ADR 0251. Cadastro rápido de veículo SEM perder a venda.
//
// Espelha o QuickAddCustomerSheet (Onda R6): Sheet lateral in-place + fetch direto
// (NÃO Inertia router, que faria redirect + recarregaria a página e perderia o
// draft da venda). Após salvar, o Sheet fecha e o veículo novo entra selecionado
// no seletor de veículo da venda via onCreated.
//
// Form mínimo (quick-add) — placa + tipo obrigatórios; resto opcional:
//   - Placa (obrigatória)
//   - Tipo de veículo (obrigatório — props.vehicleTypes)
//   - Placa secundária (cavalo+reboque · opcional)
//   - Ano / Cor (opcional)
//
// Cadastro completo (RENAVAM, chassi, hodômetro, etc.) continua em
// /oficina-auto/veiculos/create.
//
// Backend: POST /oficina-auto/veiculos (VehicleController@store) — branch JSON
// quando wantsJson() && !X-Inertia (ADR 0251). business_id + contact_id setados
// server-side (Tier 0 ADR 0093). contact_id vem do cliente já selecionado na venda.

import { useEffect, useState, type FormEvent } from 'react';
import { Truck, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import type { VehicleOption } from './CustomerSearchAutocomplete';

interface Props {
  open: boolean;
  onClose: () => void;
  /** Cliente selecionado na venda — o veículo é cadastrado pra ele. */
  contactId: number | null;
  /** Tipos de veículo (props.vehicleTypes do SellController). */
  vehicleTypes: Record<string | number, string>;
  /** Placa pré-preenchida (vem da busca — "Cadastrar 'ABC1D23'"). */
  prefillPlate?: string;
  /** Callback quando o veículo é criado — o pai adiciona no seletor + seleciona. */
  onCreated: (vehicle: VehicleOption) => void;
}

interface QuickAddErrors {
  plate?: string;
  vehicle_type?: string;
  msg?: string;
}

function getCsrfToken(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.content ?? '';
}

export default function QuickAddVehicleSheet({
  open,
  onClose,
  contactId,
  vehicleTypes,
  prefillPlate = '',
  onCreated,
}: Props) {
  const typeEntries = Object.entries(vehicleTypes);
  const [plate, setPlate] = useState(prefillPlate);
  const [vehicleType, setVehicleType] = useState<string>(typeEntries[0]?.[0] ?? '');
  const [secondaryPlate, setSecondaryPlate] = useState('');
  const [year, setYear] = useState('');
  const [color, setColor] = useState('');
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<QuickAddErrors>({});

  useEffect(() => {
    if (open) {
      setPlate(prefillPlate);
      setVehicleType(typeEntries[0]?.[0] ?? '');
      setSecondaryPlate('');
      setYear('');
      setColor('');
      setErrors({});
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, prefillPlate]);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    if (saving) return;

    const localErrors: QuickAddErrors = {};
    if (!plate.trim()) localErrors.plate = 'Placa é obrigatória';
    if (!vehicleType) localErrors.vehicle_type = 'Selecione o tipo';
    if (Object.keys(localErrors).length > 0) {
      setErrors(localErrors);
      return;
    }

    setSaving(true);
    setErrors({});

    try {
      const res = await fetch('/oficina-auto/veiculos', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          plate: plate.trim().toUpperCase(),
          vehicle_type: vehicleType,
          secondary_plate: secondaryPlate.trim() ? secondaryPlate.trim().toUpperCase() : null,
          manufacture_year: year.trim() ? Number(year.trim()) : null,
          color: color.trim() || null,
          contact_id: contactId,
        }),
      });

      if (res.status === 422) {
        const data = await res.json().catch(() => ({}));
        const ve = (data?.errors ?? {}) as Record<string, string[]>;
        const flat: QuickAddErrors = {};
        if (ve.plate?.[0]) flat.plate = ve.plate[0];
        if (ve.vehicle_type?.[0]) flat.vehicle_type = ve.vehicle_type[0];
        if (Object.keys(flat).length === 0) {
          flat.msg = data?.message ?? 'Falha na validação. Confira os campos.';
        }
        setErrors(flat);
        setSaving(false);
        return;
      }

      if (res.status === 403) {
        setErrors({ msg: 'Você não tem permissão pra cadastrar veículo. Peça ao admin.' });
        setSaving(false);
        return;
      }

      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setErrors({ msg: data?.msg ?? data?.message ?? 'Não foi possível cadastrar. Tente novamente.' });
        setSaving(false);
        return;
      }

      const data = await res.json().catch(() => ({}));
      const created = data?.data ?? data;
      const newId = Number(created?.id ?? 0);
      if (!newId) {
        setErrors({ msg: 'Cadastro feito mas não recebemos ID. Recarregue e busque manualmente.' });
        setSaving(false);
        return;
      }

      onCreated({
        id: newId,
        plate: String(created?.plate ?? plate.trim().toUpperCase()),
        secondary_plate: created?.secondary_plate ?? null,
        vehicle_type: created?.vehicle_type ?? vehicleType,
      });
      setSaving(false);
      onClose();
    } catch {
      setErrors({ msg: 'Erro de rede. Verifique sua conexão.' });
      setSaving(false);
    }
  };

  return (
    <Sheet open={open} onOpenChange={(o) => !o && !saving && onClose()}>
      <SheetContent side="right" className="w-[420px] sm:max-w-[420px] flex flex-col p-0">
        <header className="border-b border-border px-5 py-4">
          <SheetTitle asChild>
            <h2 className="m-0 flex items-center gap-2 text-base font-semibold">
              <Truck size={18} className="text-primary" />
              Cadastrar veículo
            </h2>
          </SheetTitle>
          <SheetDescription asChild>
            <p className="mt-1 text-xs text-muted-foreground">
              Cadastro rápido. RENAVAM, chassi e hodômetro ficam em <i>/oficina-auto/veiculos</i>.
            </p>
          </SheetDescription>
        </header>

        <form onSubmit={submit} className="flex-1 overflow-y-auto p-5 space-y-4 text-[13px]">
          {errors.msg && (
            <div className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-[12px] text-destructive">
              {errors.msg}
            </div>
          )}

          <div className="space-y-1.5">
            <label htmlFor="qav-plate" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Placa <span className="text-destructive">*</span>
            </label>
            <Input
              id="qav-plate"
              type="text"
              value={plate}
              onChange={(e) => setPlate(e.target.value.toUpperCase())}
              placeholder="ABC1D23"
              autoFocus
              required
              maxLength={10}
              className="uppercase"
              data-testid="quickadd-vehicle-plate"
            />
            {errors.plate && <p className="text-[11px] text-destructive">{errors.plate}</p>}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="qav-type" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Tipo de veículo <span className="text-destructive">*</span>
            </label>
            <Select value={vehicleType} onValueChange={setVehicleType}>
              <SelectTrigger id="qav-type" data-testid="quickadd-vehicle-type">
                <SelectValue placeholder="Selecionar tipo" />
              </SelectTrigger>
              <SelectContent>
                {typeEntries.map(([k, label]) => (
                  <SelectItem key={k} value={k}>
                    {label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {errors.vehicle_type && <p className="text-[11px] text-destructive">{errors.vehicle_type}</p>}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="qav-secplate" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Placa secundária (cavalo+reboque)
            </label>
            <Input
              id="qav-secplate"
              type="text"
              value={secondaryPlate}
              onChange={(e) => setSecondaryPlate(e.target.value.toUpperCase())}
              placeholder="(opcional)"
              maxLength={10}
              className="uppercase"
              data-testid="quickadd-vehicle-secplate"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label htmlFor="qav-year" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                Ano
              </label>
              <Input
                id="qav-year"
                type="number"
                inputMode="numeric"
                value={year}
                onChange={(e) => setYear(e.target.value)}
                placeholder="2020"
                min={1900}
                max={2100}
                data-testid="quickadd-vehicle-year"
              />
            </div>
            <div className="space-y-1.5">
              <label htmlFor="qav-color" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                Cor
              </label>
              <Input
                id="qav-color"
                type="text"
                value={color}
                onChange={(e) => setColor(e.target.value)}
                placeholder="Branco"
                maxLength={30}
                data-testid="quickadd-vehicle-color"
              />
            </div>
          </div>

          {!contactId && (
            <p className="text-[11px] text-muted-foreground pt-2">
              Sem cliente selecionado o veículo fica sem dono — selecione o cliente
              antes pra vincular automaticamente.
            </p>
          )}
        </form>

        <footer className="border-t border-border px-5 py-3 flex items-center justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose} disabled={saving} data-testid="quickadd-vehicle-cancel">
            Cancelar
          </Button>
          <Button
            type="button"
            onClick={submit}
            disabled={saving || !plate.trim() || !vehicleType}
            data-testid="quickadd-vehicle-submit"
          >
            {saving ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Salvando…
              </>
            ) : (
              'Cadastrar e usar'
            )}
          </Button>
        </footer>
      </SheetContent>
    </Sheet>
  );
}
