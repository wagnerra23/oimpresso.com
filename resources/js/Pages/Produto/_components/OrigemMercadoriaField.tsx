// Origem da mercadoria — tabela A da NF-e (`orig`, códigos 0 a 8). AR-PROD-128.
//
// Vai no XML de cada item da nota. Importado muda a alíquota de ICMS interestadual (4%,
// Resolução do Senado 13/2012), então o rótulo mostra o código E o nome da tabela.
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

export const ORIGENS_MERCADORIA: ReadonlyArray<readonly [number, string]> = [
  [0, 'Nacional'],
  [1, 'Estrangeira — importação direta'],
  [2, 'Estrangeira — adquirida no mercado interno'],
  [3, 'Nacional — conteúdo de importação acima de 40% e até 70%'],
  [4, 'Nacional — produzida conforme processos produtivos básicos'],
  [5, 'Nacional — conteúdo de importação até 40%'],
  [6, 'Estrangeira — importação direta, sem similar nacional (lista CAMEX)'],
  [7, 'Estrangeira — mercado interno, sem similar nacional (lista CAMEX)'],
  [8, 'Nacional — conteúdo de importação acima de 70%'],
];

export function OrigemMercadoriaField({
  value,
  onChange,
  error,
}: {
  value: number;
  onChange: (v: number) => void;
  error?: string;
}) {
  return (
    <div className="col-span-2">
      <Label htmlFor="origem_mercadoria">Origem da mercadoria</Label>
      <Select value={String(value)} onValueChange={(v) => onChange(Number(v))}>
        <SelectTrigger id="origem_mercadoria">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {ORIGENS_MERCADORIA.map(([codigo, nome]) => (
            <SelectItem key={codigo} value={String(codigo)}>
              {codigo} — {nome}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
      <p className="text-[12px] text-muted-foreground mt-1">
        Vai na nota fiscal de cada item. Produto importado muda a alíquota de ICMS entre estados.
      </p>
      {error && <p className="text-[12px] text-destructive mt-1">{error}</p>}
    </div>
  );
}
