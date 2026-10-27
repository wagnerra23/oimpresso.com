// DespesasAnexo — card "Despesas adicionais e anexo" da compra (Create e Edit).
//
// Porta o que só existia na Blade purchase/create.blade.php: até 4 despesas (nome + valor) que
// somam ao total final, e o anexo da nota. A soma e o envio moram em compra-envio.ts.

import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import type { DespesaAdicional } from './compra-envio';

const ACEITOS = '.pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png';

interface Props {
  despesas: DespesaAdicional[];
  onDespesas: (d: DespesaAdicional[]) => void;
  documento: File | null;
  onDocumento: (f: File | null) => void;
  /** Nome do anexo já gravado (Edit). */
  documentoAtual?: string | null;
  erroDocumento?: string;
}

export default function DespesasAnexo({ despesas, onDespesas, documento, onDocumento, documentoAtual, erroDocumento }: Props) {
  const mudar = (i: number, parte: Partial<DespesaAdicional>) =>
    onDespesas(despesas.map((d, j) => (j === i ? { ...d, ...parte } : d)));

  return (
    <Card className="mt-3" data-contract="purchase-despesas-anexo">
      <CardHeader className="pb-2 pt-3 px-4">
        <h3 className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">Despesas adicionais e anexo</h3>
      </CardHeader>
      <CardContent className="px-4 pb-4 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div className="space-y-2">
          {despesas.map((d, i) => (
            <div key={i} className="grid grid-cols-[1fr_8rem] gap-2">
              <Input
                aria-label={`Despesa ${i + 1}: nome`}
                placeholder={`Despesa ${i + 1}`}
                value={d.nome}
                onChange={(e) => mudar(i, { nome: e.target.value })}
                className="h-9 text-[13px]"
              />
              <NumericInputPtBR
                aria-label={`Despesa ${i + 1}: valor`}
                value={d.valor}
                onChange={(n) => mudar(i, { valor: n })}
                className="h-9 text-[13px] tabular-nums"
              />
            </div>
          ))}
          <p className="text-[11px] text-muted-foreground">Somam ao total final da compra.</p>
        </div>
        <div>
          <Label htmlFor="purchase-documento" className="text-[12px] text-muted-foreground">Anexo da nota</Label>
          <Input
            id="purchase-documento"
            type="file"
            accept={ACEITOS}
            onChange={(e) => onDocumento(e.target.files?.[0] ?? null)}
            className="mt-1 text-[13px]"
          />
          {documento ? (
            <p className="mt-1 text-[11px] text-muted-foreground">Selecionado: {documento.name}</p>
          ) : documentoAtual ? (
            <p className="mt-1 text-[11px] text-muted-foreground">Atual: {documentoAtual}. Enviar outro substitui.</p>
          ) : null}
          {erroDocumento && <p className="mt-1 text-[11px] text-destructive">{erroDocumento}</p>}
        </div>
      </CardContent>
    </Card>
  );
}
