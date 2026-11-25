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
import { RadioGroup, RadioGroupItem } from '@/Components/ui/radio-group';
import { Grid } from '@/Components/layout';
import { BlocoGrade } from './BlocoGrade';
import { CamposPreco } from './CamposPreco';
import type { Grade } from './grade';
import { metodoDaEmpresa, opcoesDoMetodo } from './metodoCusto';
import type { MetodoCustoDoFormulario } from './metodoCusto';
import { OPCOES_DE_POLITICA } from './politicaPreco';
import type { EstadoPolitica, PoliticaPreco } from './politicaPreco';
import type { PrecoSimples } from './precos';
import type { CamposProduto, DefinirCampo, ModoFormulario, OpcoesProduto, TipoProduto } from './tipos';
import { ROTULO_TIPO, URL_CADASTRO_CLASSICO, opcoesDe, tipoSemEditor } from './tipos';

interface Props {
  modo: ModoFormulario;
  data: CamposProduto;
  definir: DefinirCampo;
  opcoes: OpcoesProduto;
  /** Editar: o rótulo do tipo, que não muda depois de criado. */
  tipoAtual?: string;
  /** Produto simples: o preço. Sem ele (variável, composto) o card não mostra preço. */
  preco?: { valor: PrecoSimples; mudar: (p: PrecoSimples) => void; aliquota: number; margemMinima?: number | null };
  /** UC-PCAD-32: a grade do produto variável no Novo. */
  grade?: { valor: Grade; mudar: (g: Grade) => void; aliquota: number; margemPadrao: number; erro?: string };
  /** Método de custo (US-COM-024): só quando o módulo Compras está no pacote da empresa. */
  metodoCusto?: { dados: MetodoCustoDoFormulario; valor: string; mudar: (v: string) => void };
  /** Política de preço (CU-PROD-21) e os erros do servidor para os dois campos. */
  politica?: { valor: EstadoPolitica; mudar: (e: EstadoPolitica) => void; erros?: { politica?: string; margem?: string } };
}

/**
 * "Nenhum imposto" é uma escolha de verdade. O formulário guarda '' (o que o servidor espera), e
 * o Select do Radix não aceita item com valor vazio: no Select vale este sentinela.
 */
const SEM_IMPOSTO = 'sem-imposto';

const reais = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/**
 * O exemplo embaixo da pergunta do imposto (protótipo): quanto o cliente paga numa venda de
 * R$ 100,00. Só texto: a conta do preço que vai para o servidor segue em precos.ts.
 */
export function exemploDoImposto(tipo: 'inclusive' | 'exclusive', aliquota: number, sigla: string): string {
  if (tipo === 'exclusive') {
    return `Exemplo: venda de R$ 100,00 → o cliente paga R$ ${reais(100 * (1 + aliquota / 100))}.`;
  }
  return `Exemplo: venda de R$ 100,00 → o cliente paga R$ 100,00, com R$ ${reais(100 - 100 / (1 + aliquota / 100))} de ${sigla} dentro.`;
}

export function BlocoImpostosPrecos({ modo, data, definir, opcoes, tipoAtual, preco, grade, metodoCusto, politica }: Props) {
  const novo = modo === 'novo';
  const semImposto = data.tax === '' || data.tax == null;
  const nomeImposto = semImposto ? '' : (opcoes.taxes?.[data.tax as number] ?? '');
  const sigla = nomeImposto.split(' ')[0] || 'imposto';
  // A alíquota vem do preço (simples) ou da grade (variável); sem nenhum dos dois, sem exemplo.
  const aliquota = preco?.aliquota ?? grade?.aliquota;

  return (
    <Card>
      <CardHeader>
        <CardTitle>
          Impostos e preços
          {nomeImposto && (
            <span className="ml-2 font-mono text-[11px] font-normal uppercase text-muted-foreground">
              imposto {nomeImposto}
            </span>
          )}
        </CardTitle>
      </CardHeader>
      <CardContent>
        <Grid cols={3} gap={4}>
        <div>
          <Label htmlFor="tax">Imposto aplicável</Label>
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
        {semImposto ? (
          <div>
            <Label>O preço de venda já tem o imposto?</Label>
            <p className="mt-1 text-[11px] text-muted-foreground">Sem imposto escolhido, não há o que perguntar aqui.</p>
          </div>
        ) : (
          <div>
            <Label id="tax_type_rotulo">O preço de venda já tem o {sigla}?</Label>
            <RadioGroup
              aria-labelledby="tax_type_rotulo"
              value={data.tax_type}
              onValueChange={(v) => definir('tax_type', v as 'inclusive' | 'exclusive')}
              className="flex min-h-[30px] flex-wrap items-center gap-4"
            >
              <Label htmlFor="tax_type_exclusive" className="flex items-center gap-1.5 text-[12.5px] font-normal">
                <RadioGroupItem id="tax_type_exclusive" value="exclusive" /> Não, somar ao preço
              </Label>
              <Label htmlFor="tax_type_inclusive" className="flex items-center gap-1.5 text-[12.5px] font-normal">
                <RadioGroupItem id="tax_type_inclusive" value="inclusive" /> Sim, já está no preço
              </Label>
            </RadioGroup>
            {aliquota !== undefined && (
              <p data-testid="exemplo-imposto" className="mt-1 text-[11px] text-muted-foreground">
                {exemploDoImposto(data.tax_type, aliquota, sigla)}
              </p>
            )}
          </div>
        )}
        <div>
          <Label htmlFor="type">{novo ? 'Tipo de produto *' : 'Tipo de produto'}</Label>
          {novo ? (
            <Select value={data.type} onValueChange={(v) => definir('type', v as TipoProduto)}>
              <SelectTrigger id="type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {opcoesDe(opcoes.productTypes).map(([k, v]) => (
                  <SelectItem key={k} value={k}>
                    {ROTULO_TIPO[k as TipoProduto] ?? v}
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
            // Sem o editor de composição, o store() cria o composto sem itens: o produto não entra em
            // venda, compra, OS nem fabricação (tipos.ts tipoSemEditor).
            <p role="alert" className="mt-1 text-[12px] text-destructive">
              Produto composto ainda não tem editor nesta tela.{' '}
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
        {politica && (
          <div data-testid="campo-politica-preco">
            <Label htmlFor="politica_preco">Política de preço</Label>
            <Select
              value={politica.valor.politica}
              onValueChange={(v) => politica.mudar({ ...politica.valor, politica: v as PoliticaPreco })}
            >
              <SelectTrigger id="politica_preco">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {OPCOES_DE_POLITICA.map((o) => (
                  <SelectItem key={o.valor} value={o.valor}>
                    {o.rotulo}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <p className="mt-1 text-[12px] text-muted-foreground">
              O que acontece com o preço de venda quando o custo dos insumos muda na compra. Se o preço novo entra
              sozinho ou vai para aprovação, quem decide é a regra da compra em Fluxos.
            </p>
            {politica.erros?.politica && (
              <p role="alert" className="mt-1 text-[12px] text-destructive">
                {politica.erros.politica}
              </p>
            )}
          </div>
        )}
        {politica?.valor.politica === 'margem' && (
          <div>
            <Label htmlFor="margem_alvo_pct">Margem alvo (%)</Label>
            <Input
              id="margem_alvo_pct"
              inputMode="decimal"
              className="text-right tabular-nums"
              value={politica.valor.margemAlvo}
              onChange={(e) => politica.mudar({ ...politica.valor, margemAlvo: e.target.value })}
              aria-invalid={politica.erros?.margem ? true : undefined}
            />
            <p className="mt-1 text-[12px] text-muted-foreground">Sobre o preço de venda, não sobre o custo.</p>
            {politica.erros?.margem && (
              <p role="alert" className="mt-1 text-[12px] text-destructive">
                {politica.erros.margem}
              </p>
            )}
          </div>
        )}
        {preco && (
          <CamposPreco valor={preco.valor} mudar={preco.mudar} aliquota={preco.aliquota} margemMinima={preco.margemMinima} />
        )}
        {grade && (
          <BlocoGrade
            grade={grade.valor}
            mudar={grade.mudar}
            modelos={opcoes.modelosVariacao ?? []}
            aliquota={grade.aliquota}
            margemPadrao={grade.margemPadrao}
            sku={data.sku}
            tipoCodigo={String(data.barcode_type ?? '')}
            erro={grade.erro}
          />
        )}
        </Grid>
      </CardContent>
    </Card>
  );
}
