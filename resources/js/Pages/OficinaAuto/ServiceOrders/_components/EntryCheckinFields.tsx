// EntryCheckinFields — check-in de entrada da OS (US-OFICINA-038 + US-OFICINA-039).
// Delta do protótipo Cowork "Nova OS" (oficina-os-page.jsx seção "Check-in do veículo").
// Compartilhado por Create.tsx e Edit.tsx pra não duplicar (DRY).
//
// Captura o estado de entrada que protege oficina e cliente:
//  - fuel_level_at_entry: nível de combustível 0–100% (barra)
//  - entry_damages: avarias marcadas na entrada (chips)
// O "relato do cliente" reusa o campo `notes` existente — não entra aqui.

import { useState } from 'react';
import { Fuel, Plus, X } from 'lucide-react';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';

const DAMAGE_PRESETS = [
  'Risco',
  'Amassado',
  'Para-brisa trincado',
  'Farol quebrado',
  'Retrovisor',
  'Pneu careca',
];

interface Props {
  fuelLevel: string;
  damages: string[];
  onFuelChange: (value: string) => void;
  onDamagesChange: (value: string[]) => void;
  fuelError?: string;
}

export default function EntryCheckinFields({
  fuelLevel,
  damages,
  onFuelChange,
  onDamagesChange,
  fuelError,
}: Props) {
  const [draft, setDraft] = useState('');

  const fuelPct = Math.max(0, Math.min(100, Number(fuelLevel) || 0));

  function addDamage(label: string) {
    const clean = label.trim().slice(0, 80);
    if (!clean) return;
    if (damages.some((d) => d.toLowerCase() === clean.toLowerCase())) return;
    onDamagesChange([...damages, clean]);
    setDraft('');
  }

  function removeDamage(index: number) {
    onDamagesChange(damages.filter((_, i) => i !== index));
  }

  return (
    <div className="rounded-md border bg-muted/30 p-3 space-y-4">
      <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider">
        Check-in de entrada
      </p>

      {/* Combustível — US-OFICINA-039 */}
      <div data-field="fuel_level_at_entry">
        <Label htmlFor="fuel_level_at_entry" className="flex items-center gap-1.5">
          <Fuel className="size-3.5" />
          Combustível na entrada
        </Label>
        <div className="flex items-center gap-3 mt-1">
          <Input
            id="fuel_level_at_entry"
            type="number"
            min={0}
            max={100}
            value={fuelLevel}
            onChange={(e) => onFuelChange(e.target.value)}
            aria-invalid={!!fuelError}
            className="w-24"
            placeholder="%"
          />
          <div
            className="flex-1 h-2 rounded-full bg-muted overflow-hidden"
            role="progressbar"
            aria-valuenow={fuelPct}
            aria-valuemin={0}
            aria-valuemax={100}
          >
            <div
              className="h-full bg-primary transition-all"
              style={{ width: `${fuelPct}%` }}
            />
          </div>
          <span className="text-xs tabular-nums text-muted-foreground w-10 text-right">
            {fuelLevel === '' ? '—' : `${fuelPct}%`}
          </span>
        </div>
        {fuelError && <p className="text-sm text-destructive mt-1">{fuelError}</p>}
      </div>

      {/* Avarias na entrada — US-OFICINA-038 */}
      <div>
        <Label className="block mb-1">Avarias na entrada</Label>
        {damages.length > 0 && (
          <div className="flex flex-wrap gap-1.5 mb-2">
            {damages.map((d, i) => (
              <span
                key={`${d}-${i}`}
                className="inline-flex items-center gap-1 rounded-full bg-secondary text-secondary-foreground text-xs px-2 py-0.5"
              >
                {d}
                <button
                  type="button"
                  onClick={() => removeDamage(i)}
                  aria-label={`Remover ${d}`}
                  className="hover:text-destructive"
                >
                  <X className="size-3" />
                </button>
              </span>
            ))}
          </div>
        )}
        <div className="flex gap-2">
          <Input
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault();
                addDamage(draft);
              }
            }}
            placeholder="Descreva a avaria e tecle Enter…"
            maxLength={80}
          />
          <Button type="button" variant="outline" onClick={() => addDamage(draft)}>
            <Plus className="size-4" />
          </Button>
        </div>
        <div className="flex flex-wrap gap-1.5 mt-2">
          {DAMAGE_PRESETS.filter(
            (p) => !damages.some((d) => d.toLowerCase() === p.toLowerCase()),
          ).map((p) => (
            <button
              key={p}
              type="button"
              onClick={() => addDamage(p)}
              className="rounded-full border border-dashed text-xs px-2 py-0.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground"
            >
              + {p}
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
