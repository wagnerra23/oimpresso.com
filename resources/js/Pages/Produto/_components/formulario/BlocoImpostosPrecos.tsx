// Seção "Impostos e preços" do formulário de produto (Novo e Editar), na forma do protótipo
// (produto-blade-forms.jsx): o imposto, se ele já está no preço, o tipo do produto e o preço.
// O NCM, o CEST e a origem foram para a seção "Fiscal". A conta do preço segue em precos.ts.

import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Grid } from '@/Components/layout';
import { CamposPreco } from './CamposPreco';
import { metodoDaEmpresa, opcoesDoMetodo } from './metodoCusto';
import type { MetodoCustoDoFormulario } from './metodoCusto';
import type { PrecoSimples } from './precos';
import type { CamposProduto, DefinirCampo, ModoFormulario, OpcoesProduto, TipoProduto } from './tipos';
import { URL_CADASTRO_CLASSICO, opcoesDe, tipoSemEditor } from './tipos';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  opcoes: OpcoesProduto;
  /** Editar: o rótulo do tipo, que não muda depois de criado. */
  tipoAtual?: string;
  /** Produto simples: o preço. Sem ele (variável, composto) o card não mostra preço. */
  preco?: { valor: PrecoSimples; mudar: (p: PrecoSimples) => void; aliquota: number };
  /** Método de custo (US-COM-024): só quando o módulo Compras está no pacote da empresa. */
  metodoCusto?: { dados: MetodoCustoDoFormulario; valor: string; mudar: (v: string) => void };
}

/**
 * "Nenhum imposto" é uma escolha de verdade. O formulário guarda '' (o que o servidor espera), e
 * o Select do Radix não aceita item com valor vazio: no Select vale este sentinela.
 */
const SEM_IMPOSTO = 'sem-imposto';

export function BlocoImpostosPrecos({ modo, data, definir, opcoes, tipoAtual, preco, metodoCusto }: Props) {
  const novo = modo === 'novo';

  return (
    <Card>
      <CardHeader>
        <CardTitle>Impostos e preços</CardTitle>
      </CardHeader>
      <CardContent>
        <Grid cols={3} gap={4}>
        <div>
          <Label htmlFor="tax">Taxa de imposto</Label>
          <Select
            value={data.tax === '' || data.tax == null ? SEM_IMPOSTO : String(data.tax)}
            onValueChange={(v) => definir('tax', v === SEM_IMPOSTO ? '' : Number(v))}
          >
            <SelectTrigger id="tax">
              <SelectValue placeholder="Nenhuma" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={SEM_IMPOSTO}>Nenhuma</SelectItem>
              {opcoesDe(opcoes.taxes).map(([k, v]) => (
                <SelectItem key={k} value={k}>
                  {v}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor="tax_type">Imposto inclui no preço?</Label>
          <Select
            value={data.tax_type}
            onValueChange={(v) => definir('tax_type', v as 'inclusive' | 'exclusive')}
          >
            <SelectTrigger id="tax_type">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="exclusive">Exclusivo (somar imposto)</SelectItem>
              <SelectItem value="inclusive">Inclusivo (já incluído)</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <div>
          <Label htmlFor="type">{novo ? 'Tipo *' : 'Tipo'}</Label>
          {novo ? (
            <Select value={data.type} onValueChange={(v) => definir('type', v as TipoProduto)}>
              <SelectTrigger id="type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {opcoesDe(opcoes.productTypes).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {v}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          ) : (
            <>
              <Input id="type" value={tipoAtual} disabled aria-disabled="true" />
              <p className="mt-1 text-[11px] text-muted-foreground">
                Tipo não pode ser alterado após criação.
              </p>
            </>
          )}
          {novo && tipoSemEditor(data.type) && (
            // Sem o editor de grade/composição, o store() cria o variável sem variação nenhuma e o
            // composto sem itens: o produto não entra em venda, compra, OS nem fabricação.
            <p role="alert" className="mt-1 text-[12px] text-destructive">
              Produto {data.type === 'variable' ? 'variável' : 'composto'} ainda não tem editor nesta tela.{' '}
              {/* Página inteira (não Link do Inertia): com ?classico=1 o create() abre o Blade. */}
              <a href={URL_CADASTRO_CLASSICO} className="underline">
                Cadastrar no formulário clássico
              </a>
            </p>
          )}
        </div>
        {metodoCusto && (
          <div>
            <Label htmlFor="compras_metodo_custo">Método de custo</Label>
            {/* Sem purchase.update o campo fica desabilitado e não vai no envio (metodoCusto.ts). */}
            <Select value={metodoCusto.valor} onValueChange={metodoCusto.mudar} disabled={!metodoCusto.dados.editavel}>
              <SelectTrigger id="compras_metodo_custo">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {opcoesDoMetodo(metodoCusto.dados.atual).map((o) => (
                  <SelectItem key={o.valor} value={o.valor}>
                    {o.rotulo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="mt-1 text-[12px] text-muted-foreground">
              Vale quando a empresa escolheu “por insumo” em Fluxos → Compra. Vale para todas as variações do
              produto. Hoje a empresa usa: {metodoDaEmpresa(metodoCusto.dados.empresa)}.
            </p>
          </div>
        )}
        {preco && <CamposPreco valor={preco.valor} mudar={preco.mudar} aliquota={preco.aliquota} />}
        </Grid>
      </CardContent>
    </Card>
  );
}
