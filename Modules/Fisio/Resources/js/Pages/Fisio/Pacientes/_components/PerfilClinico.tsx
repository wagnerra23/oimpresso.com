// Campos do perfil clínico do paciente (US-FISIO-005) — usados na criação (Index) e na
// edição (Show), com a MESMA validação do servidor. Marcadores = lista fechada da config.
import type { ReactNode } from 'react';
import { Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';

export interface Opcao { value: string; label: string }

/** Campos do perfil clínico — compartilhados com a edição na ficha (mesma validação). */
export default function PerfilClinico({ form, marcadores, marcar, erro }: {
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  form: { data: any; setData: (k: any, v: any) => void };
  marcadores: Opcao[]; marcar: (v: string, on: boolean) => void; erro: (k: string) => string | undefined;
}): ReactNode {
  const goals: string[] = form.data.goals ?? [];
  return (
    <>
      <div className="flex flex-col gap-1"><Label htmlFor="pc-queixa">Queixa principal</Label>
        <Textarea id="pc-queixa" rows={3} value={form.data.main_complaint ?? ''} onChange={(e) => form.setData('main_complaint', e.target.value)} />
        {erro('main_complaint') && <p className="text-xs text-destructive">{erro('main_complaint')}</p>}</div>

      <div className="flex flex-col gap-2"><Label>Objetivos do paciente</Label>
        {goals.map((g, i) => (
          <Input key={i} aria-label={`Objetivo ${i + 1}`} placeholder="Ex.: pentear o cabelo sem dor" value={g}
            onChange={(e) => form.setData('goals', goals.map((x, j) => (j === i ? e.target.value : x)))} />
        ))}
        <Button type="button" size="sm" variant="ghost" className="self-start" onClick={() => form.setData('goals', [...goals, ''])}><Plus className="size-3.5" aria-hidden="true" /> Objetivo</Button>
      </div>

      <div className="flex flex-col gap-1"><Label htmlFor="pc-sessoes">Sessões por semana (meta de 1 a 7)</Label>
        <Input id="pc-sessoes" type="number" min={1} max={7} className="w-28 tabular-nums" value={form.data.sessions_per_week ?? ''}
          onChange={(e) => form.setData('sessions_per_week', e.target.value === '' ? '' : Number(e.target.value))} />
        {erro('sessions_per_week') && <p className="text-xs text-destructive">{erro('sessions_per_week')}</p>}</div>

      <fieldset className="flex flex-col gap-2"><legend className="mb-1 text-sm font-medium">Marcadores de condição</legend>
        <p className="text-xs text-muted-foreground">Disparam o aviso de contraindicação ao prescrever. Aviso, não bloqueio.</p>
        <div className="grid gap-2 sm:grid-cols-2">
          {marcadores.map((m) => (
            <label key={m.value} className="flex items-center gap-2 text-sm">
              <Checkbox checked={(form.data.condition_flags ?? []).includes(m.value)} onCheckedChange={(v) => marcar(m.value, v === true)} />{m.label}
            </label>
          ))}
        </div>
        {erro('condition_flags') && <p className="text-xs text-destructive">{erro('condition_flags')}</p>}
      </fieldset>
    </>
  );
}
