// Grade do produto variável no Novo produto (UC-PCAD-32), dentro de "Impostos e preços". Forma do
// protótipo do [W] (produto-blade-forms.jsx: formato do sub-SKU, tabela de valores, Enter abre a
// próxima linha); o nome da variação vem do modelo da empresa, como no Blade (decisão do [F] em
// 2026-11-17). A conta e o envio moram em grade.ts e precos.ts.
import type { KeyboardEvent } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { Plus, X } from 'lucide-react';
import { previaSubSku, valorNovo } from './grade';
import type { FormatoSubSku, Grade, ModeloVariacao, ValorDaGrade } from './grade';
import { CASAS_PRECO, arredondar, mudarCompra, mudarMargem, mudarVenda, somarPercentual } from './precos';

interface Props {
  grade: Grade;
  mudar: (g: Grade) => void;
  modelos: ModeloVariacao[];
  aliquota: number;
  margemPadrao: number;
  sku: string;
  tipoCodigo: string;
  erro?: string;
}

export function BlocoGrade({ grade, mudar, modelos, aliquota, margemPadrao, sku, tipoCodigo, erro }: Props) {
  const modelo = modelos.find((m) => m.id === grade.modeloId);
  const exibir = (n: number) => formatDecimalPtBR(arredondar(n), CASAS_PRECO);
  const trocarValor = (i: number, v: ValorDaGrade) => mudar({ ...grade, valores: grade.valores.map((x, j) => (j === i ? v : x)) });
  const acrescentar = () => mudar({ ...grade, valores: [...grade.valores, valorNovo(margemPadrao)] });
  // Enter em qualquer campo da última linha abre a seguinte: a grade inteira sem o mouse.
  const enter = (i: number) => (e: KeyboardEvent<HTMLElement>) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    if (i === grade.valores.length - 1) acrescentar();
  };
  let posicao = 0;

  if (modelos.length === 0) {
    return (
      <div className="col-span-full" role="group" aria-label="Grade da variação">
        <p role="alert" className="text-[12px] text-destructive">
          A empresa ainda não tem modelo de variação (ex.: Cor, Tamanho).{' '}
          <a href="/variation-templates" className="underline">
            Cadastrar em Cadastros → Variações
          </a>
        </p>
      </div>
    );
  }

  return (
    <div className="col-span-full" role="group" aria-label="Grade da variação">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <Label htmlFor="variacao_modelo">Variação *</Label>
          <Select value={grade.modeloId ? String(grade.modeloId) : undefined} onValueChange={(v) => mudar({ ...grade, modeloId: Number(v) })}>
            <SelectTrigger id="variacao_modelo">
              <SelectValue placeholder="Escolha o modelo (ex.: Cor)" />
            </SelectTrigger>
            <SelectContent>
              {modelos.map((m) => (
                <SelectItem key={m.id} value={String(m.id)}>
                  {m.nome}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <span className="cw-label">Formato do SKU da variação</span>
          <RadioGroup
            className="mt-2 flex gap-4"
            value={grade.formato}
            onValueChange={(v) => mudar({ ...grade, formato: v as FormatoSubSku })}
            aria-label="Formato do SKU da variação"
          >
            <div className="flex items-center gap-2">
              <RadioGroupItem id="sku_type_numero" value="with_out_variation" />
              <Label htmlFor="sku_type_numero" className="text-[13px] font-normal">Número do SKU</Label>
            </div>
            <div className="flex items-center gap-2">
              <RadioGroupItem id="sku_type_variacao" value="with_variation" />
              <Label htmlFor="sku_type_variacao" className="text-[13px] font-normal">SKU + número da variação</Label>
            </div>
          </RadioGroup>
        </div>
      </div>

      <datalist id="variacao_valores">
        {(modelo?.valores ?? []).map((v) => (
          <option key={v.id} value={v.nome} />
        ))}
      </datalist>
      <div className="mt-3 overflow-x-auto">
        <table className="w-full text-[13px]" aria-label="Valores da variação">
          <thead>
            <tr className="text-left text-[12px] text-muted-foreground">
              <th className="py-1 pr-2 font-medium">Valor</th>
              <th className="py-1 pr-2 font-medium">Sub-SKU</th>
              <th className="py-1 pr-2 font-medium text-right">Compra sem imposto</th>
              <th className="py-1 pr-2 font-medium text-right">Compra com imposto</th>
              <th className="py-1 pr-2 font-medium text-right">% de lucro</th>
              <th className="py-1 pr-2 font-medium text-right">Venda sem imposto</th>
              <th className="py-1 pr-2 font-medium text-right">Venda com imposto</th>
              <th className="py-1" />
            </tr>
          </thead>
          <tbody>
            {grade.valores.map((v, i) => {
              if (v.nome.trim() !== '') posicao += 1;
              const n = i + 1;
              return (
                <tr key={i}>
                  <td className="py-1 pr-2">
                    <Input
                      aria-label={`Valor ${n}`}
                      list="variacao_valores"
                      value={v.nome}
                      onChange={(e) => trocarValor(i, { ...v, nome: e.target.value })}
                      onKeyDown={enter(i)}
                      placeholder="Ex.: Azul"
                    />
                  </td>
                  <td className="py-1 pr-2 font-mono text-[12px] text-muted-foreground">
                    {previaSubSku(sku, tipoCodigo, grade.formato, v.nome, posicao) || '—'}
                  </td>
                  <td className="py-1 pr-2">
                    <NumericInputPtBR
                      aria-label={`Compra sem imposto ${n}`}
                      value={v.preco.compra}
                      onChange={(x) => trocarValor(i, { ...v, preco: mudarCompra(v.preco, x) })}
                      onKeyDown={enter(i)}
                      precision={CASAS_PRECO}
                      className="text-right"
                    />
                  </td>
                  <td className="py-1 pr-2 text-right tabular-nums">{exibir(somarPercentual(v.preco.compra, aliquota))}</td>
                  <td className="py-1 pr-2">
                    <NumericInputPtBR
                      aria-label={`% de lucro ${n}`}
                      value={v.preco.margem}
                      onChange={(x) => trocarValor(i, { ...v, preco: mudarMargem(v.preco, x) })}
                      onKeyDown={enter(i)}
                      precision={CASAS_PRECO}
                      className="text-right"
                    />
                  </td>
                  <td className="py-1 pr-2">
                    <NumericInputPtBR
                      aria-label={`Venda sem imposto ${n}`}
                      value={v.preco.venda}
                      onChange={(x) => trocarValor(i, { ...v, preco: mudarVenda(v.preco, x) })}
                      onKeyDown={enter(i)}
                      precision={CASAS_PRECO}
                      className="text-right"
                    />
                  </td>
                  <td className="py-1 pr-2 text-right tabular-nums">{exibir(somarPercentual(v.preco.venda, aliquota))}</td>
                  <td className="py-1">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      aria-label={`Remover valor ${n}`}
                      disabled={grade.valores.length === 1}
                      onClick={() => mudar({ ...grade, valores: grade.valores.filter((_, j) => j !== i) })}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="mt-2 flex items-center gap-3">
        <Button type="button" variant="outline" size="sm" onClick={acrescentar}>
          <Plus className="mr-1 h-4 w-4" /> Adicionar valor
        </Button>
        <span className="text-[12px] text-muted-foreground">Enter na última linha abre a seguinte. Valor novo entra no modelo ao salvar.</span>
      </div>
      {erro && (
        <p role="alert" className="mt-1 text-[12px] text-destructive">
          {erro}
        </p>
      )}
    </div>
  );
}
