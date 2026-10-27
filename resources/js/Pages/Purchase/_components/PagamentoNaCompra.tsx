// PagamentoNaCompra — card "Pagamento" da Nova compra (opcional). Porta o bloco de pagamento da
// Blade purchase/create.blade.php: o que for pago já na entrada vira uma linha em
// transaction_payments pelo próprio store (createOrUpdatePaymentLines). Vazio = compra a prazo.

import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

export interface PagamentoRascunho {
  valor: number;
  forma: string;
  data: string; // 'Y-m-d H:i', o que o datetime-local produz
  conta: string;
}

interface Props {
  valor: PagamentoRascunho;
  onChange: (p: PagamentoRascunho) => void;
  total: number;
  formas: Record<string, string>;
  /** id → nome. O Create recebe o mapa com chave numérica (accountsDropdown). */
  contas: { [id: string]: string } | { [id: number]: string };
  erro?: string;
}

const SEM_CONTA = '__sem_conta__';
const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);

export default function PagamentoNaCompra({ valor, onChange, total, formas, contas, erro }: Props) {
  const opcoesFormas = Object.entries(formas).filter(([k]) => k);
  const opcoesContas = Object.entries(contas).filter(([k]) => k);
  const acima = valor.valor > Math.round(total * 100) / 100;

  return (
    <Card className="mt-3" data-contract="purchase-pagamento">
      <CardHeader className="pb-2 pt-3 px-4">
        <h3 className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">Pagamento (opcional)</h3>
      </CardHeader>
      <CardContent className="px-4 pb-4 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div>
          <Label htmlFor="pc-valor" className="text-[12px] text-muted-foreground">Valor pago agora</Label>
          <NumericInputPtBR
            id="pc-valor"
            value={valor.valor}
            onChange={(n) => onChange({ ...valor, valor: n })}
            aria-invalid={acima || !!erro}
            className="mt-1 h-9 text-[13px] tabular-nums"
          />
          <p className="mt-1 text-[11px] text-muted-foreground">Total da compra: {brl(total)}</p>
        </div>
        <div>
          <Label className="text-[12px] text-muted-foreground">Forma de pagamento</Label>
          <Select value={valor.forma} onValueChange={(v) => onChange({ ...valor, forma: v })}>
            <SelectTrigger aria-label="Forma de pagamento" className="mt-1 w-full h-9 text-[13px]">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {opcoesFormas.map(([k, label]) => (
                <SelectItem key={k} value={k}>{label}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor="pc-data" className="text-[12px] text-muted-foreground">Data</Label>
          <Input
            id="pc-data"
            type="datetime-local"
            value={valor.data.replace(' ', 'T').slice(0, 16)}
            onChange={(e) => onChange({ ...valor, data: e.target.value.replace('T', ' ') })}
            className="mt-1 h-9 text-[13px]"
          />
        </div>
        {opcoesContas.length > 0 && (
          <div>
            <Label className="text-[12px] text-muted-foreground">Conta</Label>
            <Select
              value={valor.conta || SEM_CONTA}
              onValueChange={(v) => onChange({ ...valor, conta: v === SEM_CONTA ? '' : v })}
            >
              <SelectTrigger aria-label="Conta" className="mt-1 w-full h-9 text-[13px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={SEM_CONTA}>Sem conta</SelectItem>
                {opcoesContas.map(([k, label]) => (
                  <SelectItem key={k} value={k}>{label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        )}
        {(acima || erro) && (
          <p className="col-span-full text-[12px] text-destructive">
            {acima ? 'O pagamento passa do total da compra.' : erro}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
