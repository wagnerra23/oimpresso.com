// Grade do produto variável no Novo produto (UC-PCAD-32), dentro de "Impostos e preços". Forma do
// protótipo do [W] (produto-blade-forms.jsx: formato do sub-SKU, tabela de valores, Enter abre a
// próxima linha); o nome da variação vem do modelo da empresa, como no Blade (decisão do [F] em
// 2026-11-17). A conta e o envio moram em grade.ts e precos.ts.
import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Grid, Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import NumericInputPtBR from '@/Components/ui/numeric-input-ptbr';
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { CopyCheck, ImagePlus, Plus, X } from 'lucide-react';
import { aoEscolherModelo, aplicarATodos, previaSubSku, valorNovo } from './grade';
import type { FormatoSubSku, Grade, ModeloVariacao, ValorDaGrade } from './grade';
import { LIMITE_BYTES } from './envioMidia';
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
  // O cursor vai para o Valor da linha pedida depois que ela existir na tela.
  const bloco = useRef<HTMLDivElement>(null);
  const focarLinha = useRef<number | null>(null);
  useEffect(() => {
    if (focarLinha.current === null) return;
    const n = focarLinha.current;
    focarLinha.current = null;
    bloco.current?.querySelector<HTMLInputElement>(`input[aria-label="Valor ${n}"]`)?.focus();
  });
  const acrescentar = () => {
    focarLinha.current = grade.valores.length + 1;
    mudar({ ...grade, valores: [...grade.valores, valorNovo(margemPadrao)] });
  };
  // Enter leva ao Valor da linha seguinte; na última, abre uma nova. A grade inteira sem o mouse.
  const enter = (i: number) => (e: KeyboardEvent<HTMLElement>) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    if (i === grade.valores.length - 1) {
      acrescentar();
    } else {
      bloco.current?.querySelector<HTMLInputElement>(`input[aria-label="Valor ${i + 2}"]`)?.focus();
    }
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
    <div ref={bloco} className="col-span-full" role="group" aria-label="Grade da variação">
      {/* Título e "Adicionar valor" lado a lado, como no protótipo (produto-blade-forms.jsx). */}
      <Inline gap={2} className="mb-3">
        <h4 className="m-0 text-[11px] font-medium uppercase tracking-[.06em] text-muted-foreground">Variações</h4>
        <Button type="button" variant="outline" size="sm" onClick={acrescentar}>
          <Plus className="mr-1 h-4 w-4" /> Adicionar valor
        </Button>
      </Inline>
      <Grid cols={1} gap={4} className="md:grid-cols-2">
        <div>
          <Label htmlFor="variacao_modelo">Variação *</Label>
          {/* "" e não undefined: o Select nasce controlado e mostra o placeholder até a escolha. */}
          <Select value={grade.modeloId ? String(grade.modeloId) : ''} onValueChange={(v) => mudar(aoEscolherModelo(grade, modelos.find((m) => m.id === Number(v)), margemPadrao))}>
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
            className="mt-2"
            value={grade.formato}
            onValueChange={(v) => mudar({ ...grade, formato: v as FormatoSubSku })}
            aria-label="Formato do SKU da variação"
          >
            <Inline gap={4}>
              <Inline gap={2}>
                <RadioGroupItem id="sku_type_numero" value="with_out_variation" />
                <Label htmlFor="sku_type_numero" className="text-[13px] font-normal">Número do SKU</Label>
              </Inline>
              <Inline gap={2}>
                <RadioGroupItem id="sku_type_variacao" value="with_variation" />
                <Label htmlFor="sku_type_variacao" className="text-[13px] font-normal">SKU + número da variação</Label>
              </Inline>
            </Inline>
          </RadioGroup>
        </div>
      </Grid>

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
              <th className="py-1 pr-2" aria-label="Aplicar a compra a todas" />
              <th className="py-1 pr-2 font-medium text-right">Compra com imposto</th>
              <th className="py-1 pr-2 font-medium text-right">% de lucro</th>
              <th className="py-1 pr-2" aria-label="Aplicar o lucro a todas" />
              <th className="py-1 pr-2 font-medium text-right">Venda sem imposto</th>
              <th className="py-1 pr-2 font-medium text-right">Venda com imposto</th>
              <th className="py-1 pr-2 font-medium">Fotos</th>
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
                  <td className="py-1 pr-2">
                    {/* Em branco, o servidor gera pelo formato; a prévia aparece como dica. Digitado,
                        vale o código digitado (ex.: o código de barras do fornecedor), como no Blade. */}
                    <Input
                      aria-label={`Sub-SKU ${n}`}
                      className="font-mono text-[12px]"
                      value={v.subSku ?? ''}
                      onChange={(e) => trocarValor(i, { ...v, subSku: e.target.value })}
                      onKeyDown={enter(i)}
                      placeholder={previaSubSku(sku, tipoCodigo, grade.formato, v.nome, posicao) || 'gerado ao salvar'}
                    />
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
                  <td className="py-1 pr-2">
                    <AplicarATodos rotulo={`Aplicar a compra da linha ${n} a todas`} onClick={() => mudar(aplicarATodos(grade, i, 'compra'))} />
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
                    <AplicarATodos rotulo={`Aplicar o lucro da linha ${n} a todas`} onClick={() => mudar(aplicarATodos(grade, i, 'margem'))} />
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
                  <td className="py-1 pr-2">
                    <FotosDoValor
                      n={n}
                      fotos={v.imagens ?? []}
                      mudar={(imagens) => trocarValor(i, { ...v, imagens })}
                    />
                  </td>
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
      <p className="mt-2 text-[12px] text-muted-foreground">
        Escolher o modelo traz os valores dele; tire os que este produto não tem. Enter leva à linha
        seguinte e, na última, abre uma nova. Valor novo entra no modelo ao salvar.
      </p>
      {erro && (
        <p role="alert" className="mt-1 text-[12px] text-destructive">
          {erro}
        </p>
      )}
    </div>
  );
}

// O "aplicar a todos" do Blade (product.js `.apply-all`): o valor desta linha vai para todas.
function AplicarATodos({ rotulo, onClick }: { rotulo: string; onClick: () => void }) {
  return (
    <Button type="button" variant="ghost" size="sm" className="h-8 w-8 p-0" aria-label={rotulo} title={rotulo} onClick={onClick}>
      <CopyCheck className="h-4 w-4" />
    </Button>
  );
}

// Fotos do valor (`variation_images` do Blade): só imagem, várias por valor, até 5 MB cada.
function FotosDoValor({ n, fotos, mudar }: { n: number; fotos: File[]; mudar: (f: File[]) => void }) {
  const [erro, setErro] = useState<string | null>(null);
  const id = `fotos_valor_${n}`;
  return (
    <div className="min-w-0">
      <label htmlFor={id} className="inline-flex cursor-pointer items-center gap-1 text-[12px] text-muted-foreground hover:text-foreground">
        <ImagePlus className="h-4 w-4" />
        {fotos.length > 0 ? `${fotos.length} foto${fotos.length > 1 ? 's' : ''}` : 'Escolher'}
      </label>
      <input
        id={id}
        type="file"
        accept="image/*"
        multiple
        className="sr-only"
        aria-label={`Fotos do valor ${n}`}
        onChange={(e) => {
          const escolhidas = Array.from(e.target.files ?? []);
          const grandes = escolhidas.filter((f) => f.size > LIMITE_BYTES);
          setErro(grandes.length > 0 ? 'Cada foto pode ter até 5 MB.' : null);
          mudar(escolhidas.filter((f) => f.size <= LIMITE_BYTES && f.type.startsWith('image/')));
          e.target.value = '';
        }}
      />
      {erro && (
        <p role="alert" className="text-[11px] text-destructive">
          {erro}
        </p>
      )}
    </div>
  );
}
