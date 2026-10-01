import { useState } from 'react';
import { Wrench, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { dropdownEntries } from './dropdownEntries';
import { adicionarDefeitos, type ReparoForm } from './reparoVenda';

export type RepairPosProps = {
  statuses: Array<{ id: number; name: string; color: string | null }>;
  defaultStatusId: number | null;
  brands: Record<string, string>;
  devices: Record<string, string>;
  deviceModels: Record<string, string>;
  warranties: Record<string, string>;
  defeitosSugeridos: string[];
};

type Props = {
  opcoes: RepairPosProps;
  valor: ReparoForm;
  onChange: (proximo: ReparoForm) => void;
};

const SEM = '__nenhum__';

/**
 * Seção "Reparo" do Sells/Create (UC-S05) — só aparece na venda aberta como reparo.
 * Paridade de campos com o POS Blade de reparo (`repair_pos.blade.php`). Checklist e
 * senha/padrão ficam para a onda seguinte.
 */
export default function ReparoSection({ opcoes, valor, onChange }: Props) {
  const [digitando, setDigitando] = useState('');
  const set = <K extends keyof ReparoForm>(k: K, v: ReparoForm[K]) => onChange({ ...valor, [k]: v });

  const selectDeId = (
    id: string,
    rotulo: string,
    campo: 'repair_brand_id' | 'repair_device_id' | 'repair_model_id' | 'repair_warranty_id',
    lista: Record<string, string>,
  ) => (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{rotulo}</Label>
      <Select
        value={valor[campo] !== null ? String(valor[campo]) : SEM}
        onValueChange={(v) => set(campo, v === SEM ? null : Number(v))}
      >
        <SelectTrigger id={id}>
          <SelectValue placeholder="Selecionar" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={SEM}>Nenhum</SelectItem>
          {dropdownEntries(lista).map(([k, nome]) => (
            <SelectItem key={k} value={k}>
              {nome}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  );

  const confirmarDigitado = () => {
    if (digitando.trim() === '') return;
    set('defeitos', adicionarDefeitos(valor.defeitos, digitando));
    setDigitando('');
  };

  const sugestoesRestantes = opcoes.defeitosSugeridos.filter((s) => !valor.defeitos.includes(s));

  return (
    <Card id="sec-reparo" className="shadow-sm bg-background border-border scroll-mt-32">
      <CardHeader>
        <CardTitle className="text-base flex items-center gap-2">
          <Wrench className="h-4 w-4 text-muted-foreground" />
          Reparo
        </CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="space-y-1.5">
          <Label htmlFor="repair_status_id">Status do reparo *</Label>
          <Select
            value={valor.repair_status_id !== null ? String(valor.repair_status_id) : undefined}
            onValueChange={(v) => set('repair_status_id', Number(v))}
          >
            <SelectTrigger id="repair_status_id" aria-required="true">
              <SelectValue placeholder="Selecionar status" />
            </SelectTrigger>
            <SelectContent>
              {opcoes.statuses.map((s) => (
                <SelectItem key={s.id} value={String(s.id)}>
                  {s.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {valor.repair_status_id === null && (
            <p className="text-xs text-destructive" role="alert">
              Escolha o status para salvar.
            </p>
          )}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="repair_due_date">Entrega prevista</Label>
          <Input
            id="repair_due_date"
            type="datetime-local"
            value={valor.repair_due_date}
            onChange={(e) => set('repair_due_date', e.target.value)}
          />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="repair_completed_on">Concluído em</Label>
          <Input
            id="repair_completed_on"
            type="datetime-local"
            value={valor.repair_completed_on}
            onChange={(e) => set('repair_completed_on', e.target.value)}
          />
        </div>

        {Object.keys(opcoes.warranties).length > 0 &&
          selectDeId('repair_warranty_id', 'Garantia', 'repair_warranty_id', opcoes.warranties)}

        {selectDeId('repair_brand_id', 'Marca', 'repair_brand_id', opcoes.brands)}
        {selectDeId('repair_device_id', 'Aparelho', 'repair_device_id', opcoes.devices)}
        {selectDeId('repair_model_id', 'Modelo', 'repair_model_id', opcoes.deviceModels)}

        <div className="space-y-1.5">
          <Label htmlFor="repair_serial_no">Nº de série</Label>
          <Input
            id="repair_serial_no"
            value={valor.repair_serial_no}
            onChange={(e) => set('repair_serial_no', e.target.value)}
          />
        </div>

        <div className="space-y-1.5 md:col-span-2 lg:col-span-4">
          <Label htmlFor="repair_defects">Problema relatado pelo cliente</Label>
          <div className="flex gap-2">
            <Input
              id="repair_defects"
              value={digitando}
              placeholder="Digite e tecle Enter (vírgula separa vários)"
              onChange={(e) => setDigitando(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  confirmarDigitado();
                }
              }}
            />
            <Button type="button" variant="outline" onClick={confirmarDigitado}>
              Adicionar
            </Button>
          </div>
          {valor.defeitos.length > 0 && (
            <div className="flex flex-wrap gap-1.5" aria-label="Defeitos informados">
              {valor.defeitos.map((d) => (
                <span key={d} className="inline-flex items-center gap-1 rounded-full border border-border bg-muted px-2 py-0.5 text-xs">
                  {d}
                  <button
                    type="button"
                    aria-label={`Remover ${d}`}
                    onClick={() => set('defeitos', valor.defeitos.filter((x) => x !== d))}
                  >
                    <X className="h-3 w-3" />
                  </button>
                </span>
              ))}
            </div>
          )}
          {sugestoesRestantes.length > 0 && (
            <div className="flex flex-wrap gap-1.5">
              {sugestoesRestantes.map((s) => (
                <button
                  key={s}
                  type="button"
                  onClick={() => set('defeitos', adicionarDefeitos(valor.defeitos, s))}
                  className="rounded-full border border-dashed border-border px-2 py-0.5 text-xs text-muted-foreground hover:bg-muted/50"
                >
                  + {s}
                </button>
              ))}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
