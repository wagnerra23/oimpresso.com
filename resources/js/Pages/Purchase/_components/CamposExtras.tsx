// CamposExtras — card "Prazo, frete e campos da empresa" da compra (Create e Edit).
//
// Porta da Blade purchase/create.blade.php e edit.blade.php: prazo de pagamento
// (pay_term_number + pay_term_type), detalhes do frete (shipping_details) e os campos
// personalizados que a empresa configurou (custom_field_1..4 e shipping_custom_field_1..5).
// Os rótulos vêm do servidor (PurchaseController::camposExtrasDaCompra); campo sem rótulo não
// aparece, igual à Blade.

import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

export interface CampoExtra {
  campo: string;
  rotulo: string;
  obrigatorio: boolean;
}

export interface CamposExtrasConfig {
  compra: CampoExtra[];
  frete: CampoExtra[];
}

export type ValoresExtras = Record<string, string>;

/** Chaves que a tela manda no store/update. Só as dos campos que existem na tela. */
export function chavesExtras(config: CamposExtrasConfig | null | undefined): string[] {
  return [
    'pay_term_number',
    'pay_term_type',
    'shipping_details',
    ...(config?.compra ?? []).map((c) => c.campo),
    ...(config?.frete ?? []).map((c) => c.campo),
  ];
}

const SEM_PRAZO = '__sem_prazo__';

interface Props {
  config: CamposExtrasConfig | null;
  valores: ValoresExtras;
  onChange: (v: ValoresExtras) => void;
  erros?: Record<string, string | undefined>;
}

function Campo({ c, valor, onChange, erro }: { c: CampoExtra; valor: string; onChange: (v: string) => void; erro?: string }) {
  const id = `purchase-${c.campo}`;
  return (
    <div>
      <Label htmlFor={id} className="text-[12px] text-muted-foreground">
        {c.rotulo}
        {c.obrigatorio && <span aria-hidden="true"> *</span>}
      </Label>
      <Input
        id={id}
        value={valor}
        required={c.obrigatorio}
        onChange={(e) => onChange(e.target.value)}
        className="mt-1 h-9 text-[13px]"
      />
      {erro && <p className="mt-1 text-[11px] text-destructive">{erro}</p>}
    </div>
  );
}

export default function CamposExtras({ config, valores, onChange, erros = {} }: Props) {
  const set = (campo: string, v: string) => onChange({ ...valores, [campo]: v });

  return (
    <Card className="mt-3" data-contract="purchase-campos-extras">
      <CardHeader className="pb-2 pt-3 px-4">
        <h3 className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">Prazo, frete e campos da empresa</h3>
      </CardHeader>
      <CardContent className="px-4 pb-4 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
          <Label htmlFor="purchase-pay-term" className="text-[12px] text-muted-foreground">Prazo de pagamento</Label>
          <div className="mt-1 flex gap-2">
            <Input
              id="purchase-pay-term"
              type="number"
              min={0}
              value={valores.pay_term_number ?? ''}
              onChange={(e) => set('pay_term_number', e.target.value)}
              className="h-9 w-20 text-[13px] tabular-nums"
            />
            <Select
              value={valores.pay_term_type || SEM_PRAZO}
              onValueChange={(v) => set('pay_term_type', v === SEM_PRAZO ? '' : v)}
            >
              <SelectTrigger aria-label="Unidade do prazo" className="h-9 flex-1 text-[13px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SEM_PRAZO}>—</SelectItem>
                <SelectItem value="days">dias</SelectItem>
                <SelectItem value="months">meses</SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>
        <div className="col-span-2 md:col-span-3">
          <Label htmlFor="purchase-shipping-details" className="text-[12px] text-muted-foreground">Detalhes do frete</Label>
          <Input
            id="purchase-shipping-details"
            value={valores.shipping_details ?? ''}
            onChange={(e) => set('shipping_details', e.target.value)}
            className="mt-1 h-9 text-[13px]"
          />
        </div>
        {[...(config?.compra ?? []), ...(config?.frete ?? [])].map((c) => (
          <Campo key={c.campo} c={c} valor={valores[c.campo] ?? ''} onChange={(v) => set(c.campo, v)} erro={erros[c.campo]} />
        ))}
      </CardContent>
    </Card>
  );
}
