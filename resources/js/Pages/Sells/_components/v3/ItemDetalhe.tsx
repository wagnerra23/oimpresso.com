/**
 * ItemDetalhe — onda 4 do preview `/sells/create-v3`.
 *
 * Porte de `prototipo-ui/cowork/venda-v3/sells-item-detail.jsx` — o drawer de detalhe
 * do item, com 7 abas. As regras fiscais moram em `item-fiscal-dominio.ts` e estão
 * provadas em `tests/js/item-fiscal-dominio.test.ts` (18/18).
 *
 * O QUE ESTA ONDA ENTREGA, E O QUE FICA DECLARADO
 * A fonte tem 464 linhas — mais que o dobro de qualquer outra onda. Entram aqui as
 * 7 abas navegáveis, o fluxo de produção (etapa · responsável · setor · previsão) e a
 * **aba Tributação inteira**, que é onde mora o risco: NCM, CFOP, CEST, GTIN, cBenef,
 * CST, alíquota e redução, com validação de formato E de coerência.
 *
 * ⚠️ Erro fiscal sai desta tela direto para a NF-e. Por isso a validação não é
 * cosmética: a aba mostra os erros e o `Confirmar item` fica desabilitado enquanto
 * houver incoerência — deixar salvar um CST 40 com alíquota 18% seria empurrar para
 * a SEFAZ uma rejeição que o sistema já sabia prever.
 *
 * "Responsável é PESSOA, setor é ONDE" — a fonte registra que misturar os dois numa
 * coluna só foi o defeito apontado na revisão do desenho. Ficam separados.
 *
 * TIER 0: **não calcula dinheiro**. Nenhum total, subtotal ou imposto é multiplicado
 * aqui — a aba Preço mostra o que a linha já tem, e o cálculo segue em `calculo-item.ts`.
 * A tela continua sem gravar.
 */

import { useMemo, useRef, useState, type ReactNode } from 'react';

import { cn } from '@/Lib/utils';
import { Grid, Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Input } from '@/Components/ui/input';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import SubNav from '@/Components/shared/SubNav';

import { Checkbox } from '@/Components/ui/checkbox';
import { Label } from '@/Components/ui/label';
import { areaUnitaria, totalDoItem, unitarioLiquido } from './calculo-item';
import { BASES, TIPOS_BENEFICIARIO } from './comissao-dominio';
import { brl, fmtBR, fmtQtd, parseBR } from './numeros';
import {
  ABAS,
  CLASSIFICACAO_TRIBUTARIA,
  CST_ICMS,
  GRUPOS_PRODUTO,
  IMPOSTOS,
  LOCAIS_APLICACAO,
  MUNICIPIOS_ISSQN,
  ORIGEM_MERCADORIA,
  ROTULO_DA_ABA,
  TIPOS_IMPRESSAO,
  UFS_DIFAL,
  VIAS_TRANSPORTE,
  aliquotaDe,
  cstDoImposto,
  difalDoItem,
  erroDeCoerencia,
  errosFiscais,
  impostoDe,
  somaDosImpostos,
  validarAliquota,
  validarCbenef,
  validarCest,
  validarCfop,
  validarGtin,
  validarNcm,
  validarReducao,
  type Aba,
  TIPOS_PRECO,
  UNIDADES,
} from './item-fiscal-dominio';
import { Lbl, Pill, Sec } from './primitivos';

export type LinhaDoItem = {
  k: number;
  sku: string;
  nome: string;
  un: string;
  medidas: string | null;
  qtd: string;
  preco: string;
  desc: string;
  acr: string;
};

const ETAPAS_PADRAO = [
  { etapa: 'Arte / pré-impressão', resp: 'Kamila Reis', setor: 'Criação', st: 'concluída', prev: '28/07' },
  { etapa: 'Impressão digital', resp: 'Guilherme Sato', setor: 'Impressão', st: 'em execução', prev: '29/07' },
  { etapa: 'Acabamento — ilhós', resp: 'Equipe interna — box 2', setor: 'Acabamento', st: 'pendente', prev: '30/07' },
  { etapa: 'Expedição', resp: 'Larissa Prado', setor: 'Balcão', st: 'pendente', prev: '31/07' },
];

/* Cena da aba Produção. A âncora lê de `window.SD` (equipamentos, locais de estoque);
   aqui viram constante, igual `ETAPAS_PADRAO` — preview não consulta banco. */
const ACABAMENTOS = ['Sem acabamento', 'Ilhós a cada 50cm', 'Bastão + corda', 'Solda perimetral', 'Laminação'];
const PRIORIDADES = ['Normal', 'Urgente', 'Programada'];
const EQUIPAMENTOS = ['—', 'Plotter Roland · Impressão', 'Mesa de corte · Acabamento', 'Laminadora · Acabamento'];
const LOCAIS_ESTOQUE = ['Não requisita (serviço)', 'Depósito central', 'Balcão', 'Box 2'];
const EM_PRODUCAO = ['Não', 'Sim'];

/* Anexos de cena — os mesmos dois do `DataTable` da âncora. */
const ANEXOS = [
  { nome: 'lona-prefeitura-v3.pdf', tipo: 'arte final', enviado: '27/07/2026' },
  { nome: 'aprovacao-email.png', tipo: 'aprovação', enviado: '27/07/2026' },
];

const tomDoStatus = (st: string) => (st === 'concluída' ? 'success' : st === 'em execução' ? 'warning' : 'neutro');

function Campo({ label, children, erro }: { label: string; children: ReactNode; erro?: string | null }) {
  return (
    <div>
      <Lbl>{label}</Lbl>
      {children}
      {erro && <span className="mt-0.5 block text-[11px] leading-tight text-destructive-fg">{erro}</span>}
    </div>
  );
}

function Texto({
  label,
  value,
  onChange,
  placeholder,
  erro,
}: {
  label: string;
  value: string;
  onChange?: (v: string) => void;
  placeholder?: string;
  erro?: string | null;
}) {
  return (
    <Campo label={label} erro={erro}>
      <Input
        className={erro ? 'border-destructive' : undefined}
        value={value}
        placeholder={placeholder}
        readOnly={!onChange}
        onChange={(e) => onChange?.(e.target.value)}
        aria-invalid={!!erro}
      />
    </Campo>
  );
}

function Escolha({
  label,
  value,
  onChange,
  options,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: string[];
}) {
  return (
    <Campo label={label}>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-full">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((o) => (
            <SafeSelectItem key={o} value={o} className="text-[12.5px]">
              {o}
            </SafeSelectItem>
          ))}
        </SelectContent>
      </Select>
    </Campo>
  );
}

/**
 * `<textarea>` com a caixa do DS na mão. Mesma razão já registrada na aba Observação:
 * é `<textarea>` CRU (não o `<Textarea>` do DS), então não passa pela regra escopada
 * e as classes precisam trazer a caixa. Existe como helper porque três abas usam.
 */
function AreaTexto({
  label,
  value,
  onChange,
  rows = 4,
  placeholder,
  help,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  rows?: number;
  placeholder?: string;
  help?: string;
}) {
  return (
    <Campo label={label}>
      <textarea
        rows={rows}
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-input bg-background px-[10px] py-[7px] text-[13px] leading-[1.5] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40"
      />
      {help && <span className="text-[11px] leading-snug text-muted-foreground">{help}</span>}
    </Campo>
  );
}

/**
 * Checkbox com rótulo clicável — `id` + `htmlFor`, o par que o `jsx-a11y` exige e
 * que faz o clique no texto alcançar o controle. Existe como helper porque a aba
 * Tributação usa quatro deles (não recalcular · monofasia · ISS retido · DIFAL), e
 * quatro `<label>` hand-rolled foi exatamente o que as catracas de layout e a11y
 * mordoram — cada uma pelo seu lado, no mesmo defeito.
 */
function Marca({
  id,
  rotulo,
  sub,
  checked,
  onChange,
}: {
  id: string;
  rotulo: string;
  sub?: string;
  checked: boolean;
  onChange: (v: boolean) => void;
}) {
  return (
    <Inline gap={2} align={sub ? 'start' : 'center'}>
      <Checkbox id={id} checked={checked} onCheckedChange={(v) => onChange(v === true)} className={sub ? 'mt-0.5' : undefined} />
      <Label htmlFor={id} className="cursor-pointer text-[12.5px] font-normal">
        {sub ? <b>{rotulo}</b> : rotulo}
        {sub && <span className="block text-[11px] font-normal text-muted-foreground">{sub}</span>}
      </Label>
    </Inline>
  );
}

export default function ItemDetalhe({
  linha,
  indice,
  total,
  onFechar,
  onNavegar,
  abaInicial = 'geral',
}: {
  linha: LinhaDoItem | null;
  indice: number;
  total: number;
  onFechar: () => void;
  onNavegar?: (delta: number) => void;
  abaInicial?: Aba;
}) {
  const [aba, setAba] = useState<Aba>(abaInicial);

  /* fiscal */
  const [ncm, setNcm] = useState('39199090');
  const [cfop, setCfop] = useState('5102');
  const [cest, setCest] = useState('');
  const [gtin, setGtin] = useState('');
  const [cbenef, setCbenef] = useState('');
  const [cst, setCst] = useState(CST_ICMS[0]!);
  const [aliquota, setAliquota] = useState('18');

  /* Os campos da aba Tributação vivem num registro só, e não em ~20 `useState`.
     A âncora faz igual (`d` + `dv`/`set`): são campos heterogêneos, opcionais e
     quase todos de cena — um estado por campo aqui viraria ruído sem ganho. */
  const [fiscal, setFiscal] = useState<Record<string, string>>({});
  const fv = (k: string, padrao = '') => fiscal[k] ?? padrao;
  const setFv = (k: string) => (v: string) => setFiscal((s) => ({ ...s, [k]: v }));

  /* Qual imposto está expandido no acordeão — `null` = todos fechados. */
  const [impostoAberto, setImpostoAberto] = useState<string | null>(null);
  const [difalLigado, setDifalLigado] = useState(false);
  const [naoRecalcular, setNaoRecalcular] = useState(false);
  const [monofasia, setMonofasia] = useState(false);
  const [issRetido, setIssRetido] = useState(false);

  /* produção */
  const [local, setLocal] = useState(LOCAIS_APLICACAO[0]!);
  const [impressao, setImpressao] = useState(TIPOS_IMPRESSAO[0]!);
  const [observacao, setObservacao] = useState('');
  const [obsInterna, setObsInterna] = useState('');
  /* Aba Produção: a âncora tem 10 campos + a seção "Arquivo de arte"; o porte
     da onda 4 trouxe 3. O resto entra aqui. */
  const [emProducao, setEmProducao] = useState(EM_PRODUCAO[0]!);
  const [acabamento, setAcabamento] = useState(ACABAMENTOS[0]!);
  const [equipamento, setEquipamento] = useState(EQUIPAMENTOS[0]!);
  const [prioridade, setPrioridade] = useState(PRIORIDADES[0]!);
  const [localEstoque, setLocalEstoque] = useState(LOCAIS_ESTOQUE[0]!);
  const [prazoEquipe, setPrazoEquipe] = useState('');
  const [prazoEtapa, setPrazoEtapa] = useState('');
  const [obsProducao, setObsProducao] = useState('');
  const [caminhoArte, setCaminhoArte] = useState('');
  const [etapas, setEtapas] = useState(ETAPAS_PADRAO);

  /* o objeto nasce DENTRO do useMemo: montá-lo fora faria a identidade mudar a cada
     render e o exhaustive-deps reclamar com razão. */
  const erros = useMemo(
    () => errosFiscais({ ncm, cfop, cest, gtin, cbenef, cst, aliquota, reducao: '0' }),
    [ncm, cfop, cest, gtin, cbenef, cst, aliquota],
  );
  const incoerencia = erroDeCoerencia(cst, aliquota);

  /* ─── aba Geral (onda D2) ─────────────────────────────────────────────────
     Os campos nascem do que a linha já traz; o que é DERIVADO não vira estado —
     é calculado das mesmas funções que a tabela usa, para não existirem duas
     verdades sobre a mesma medida ou o mesmo total. */
  const [descricao, setDescricao] = useState(linha?.nome ?? '');
  const [un, setUn] = useState(linha?.un ?? 'un');
  const [pecas, setPecas] = useState('1');
  const [altura, setAltura] = useState('0,00');
  const [largura, setLargura] = useState('0,00');
  const [espessura, setEspessura] = useState('0,00');
  const [tipoPreco, setTipoPreco] = useState<string>(TIPOS_PRECO[0]!);

  const [comissiona, setComissiona] = useState(true);
  const [tipoComissionado, setTipoComissionado] = useState<string>(TIPOS_BENEFICIARIO[0]!.l);
  const [executante, setExecutante] = useState('—');
  const [baseComissao, setBaseComissao] = useState<string>(BASES[0]!.label);
  const [percentual, setPercentual] = useState('3,00');

  /* DERIVADOS — `calculo-item.ts`, o mesmo módulo provado em `tests/js/`. */
  const areaCalculada = areaUnitaria(un, parseBR(altura), parseBR(largura), parseBR(espessura));
  const unitLiquido = unitarioLiquido(
    parseBR(linha?.preco ?? '0'),
    parseBR(linha?.desc ?? '0'),
    parseBR(linha?.acr ?? '0'),
  );
  const totalDaLinha = totalDoItem(parseBR(linha?.qtd ?? '0'), unitLiquido);
  /* O desconto em VALOR é a diferença entre o bruto e o líquido — não um campo
     próprio. Assim ele nunca discorda do percentual que o operador digitou. */
  const descontoEmValor =
    parseBR(linha?.qtd ?? '0') * parseBR(linha?.preco ?? '0') - totalDaLinha;
  const comissaoDoItem = comissiona ? (totalDaLinha * parseBR(percentual)) / 100 : 0;

  /* Alteração não confirmada — o slot que a âncora tem no rodapé
     (`sells-item-detail.jsx:199`). Guardo os valores de PARTIDA num ref e comparo
     com os atuais: sem isso o pill acenderia já no primeiro render, porque o valor
     inicial do `useState` também é "um valor". A CONFIRMAÇÃO ao navegar com edição
     pendente é da onda D8 — aqui entra só a indicação, que é o que a D1 pede. */
  const inicial = useRef({ ncm, cfop, cest, gtin, cbenef, cst, aliquota, local, impressao, observacao });
  const sujo =
    inicial.current.ncm !== ncm ||
    inicial.current.cfop !== cfop ||
    inicial.current.cest !== cest ||
    inicial.current.gtin !== gtin ||
    inicial.current.cbenef !== cbenef ||
    inicial.current.cst !== cst ||
    inicial.current.aliquota !== aliquota ||
    inicial.current.local !== local ||
    inicial.current.impressao !== impressao ||
    inicial.current.observacao !== observacao;

  if (!linha) return null;

  const baseDeCalculo = parseBR(linha.qtd) * parseBR(linha.preco);

  /* Derivados da aba Tributacao — nunca em estado (handoff §13): duas verdades
     sobre o mesmo imposto e que fabricam divergencia entre a linha e o total. */
  const totalDosImpostos = somaDosImpostos(baseDeCalculo, aliquota, cst);
  const difal = difalDoItem(
    baseDeCalculo,
    fv('difal_inter', '12,00'),
    fv('difal_dest', '18,00'),
    fv('difal_fcp', '2,00'),
  );

  return (
    <Sheet open={!!linha} onOpenChange={(v) => !v && onFechar()}>
      {/* `venda-v3` FICA no SheetContent, e não some numa "limpeza": o Radix
          portala o conteúdo pro <body>, FORA do wrapper da Page — sem a classe
          aqui, as regras de `resources/css/venda-v3.css` não alcançam nada do
          que está dentro (§5 proibicoes, 2026-07-10). */}
      <SheetContent
        side="right"
        className="venda-v3 w-full overflow-hidden p-0 sm:max-w-[880px]"
      >
        {/* a coluna é `Stack`, não `flex flex-col` na mão: o `layout:check`
            (ADR 0253) cobra a primitiva, e ela dá o mesmo eixo vertical. */}
        <Stack gap={0} className="h-full">
        <SheetHeader className="shrink-0 border-b border-border px-5 py-4 text-left">
          <SheetTitle className="text-[15px] leading-tight">
            Item {indice + 1} · {linha.nome}
          </SheetTitle>
          <SheetDescription className="text-[12px] text-muted-foreground">
            {linha.sku} · {linha.un}
            {linha.medidas ? ` · ${linha.medidas}` : ''} · {brl(baseDeCalculo)}
          </SheetDescription>
        </SheetHeader>

        {/* ─── abas ────────────────────────────────────────────────────── */}
        {/* `<SubNav>` do DS, não tablist hand-rolado: é switch in-page controlado
            (value/onChange, sem URL) — o `ds/no-inline-tablist` aponta exatamente
            este caso, e usar o canon resolve junto o flex solto do `layout:check`. */}
        <SubNav
          items={ABAS.map((a) => ({
            value: a,
            label: ROTULO_DA_ABA[a],
            ...(a === 'tributacao' && erros.length > 0 ? { badge: erros.length } : {}),
            /* A âncora conta etapas e anexos no rótulo da aba — o operador vê que há
               conteúdo lá sem precisar abrir. */
            ...(a === 'fluxo' && etapas.length > 0 ? { badge: etapas.length } : {}),
            ...(a === 'anexos' ? { badge: ANEXOS.length } : {}),
          }))}
          value={aba}
          onChange={(v) => setAba(v as Aba)}
          ariaLabel="Detalhe do item"
        />

        <Stack gap={4} className="min-h-0 flex-1 overflow-auto px-5 py-4">
          {aba === 'geral' && (
            <Stack gap={4}>
              {/* As 3 seções da âncora (`sells-item-detail.jsx` — DrawerSections
                  "Identificação e medidas" · "Valores da linha" · "Comissão deste
                  item"). O que existia eram 6 campos só-leitura soltos. */}
              <Sec title="Identificação e medidas">
                <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                  <Texto label="Código do produto" value={linha.sku} />
                  <Texto label="Descrição na venda" value={descricao} onChange={setDescricao} />
                  <Escolha label="Unidade" value={un} onChange={setUn} options={UNIDADES} />
                  <Texto label="Peças" value={pecas} onChange={setPecas} />
                  <Texto label="Altura" value={altura} onChange={setAltura} />
                  <Texto label="Largura" value={largura} onChange={setLargura} />
                  <Texto label="Espessura" value={espessura} onChange={setEspessura} />
                  <Campo label="Área calculada">
                    {/* Somente leitura E derivada: quem manda é `areaUnitaria` do
                        `calculo-item.ts`, provado em teste. Campo editável abriria
                        um segundo caminho para a medida. */}
                    <Input readOnly value={fmtQtd(areaCalculada)} className="bg-muted/40" />
                    <span className="mt-0.5 block text-[11px] text-muted-foreground">
                      peças × altura × largura
                    </span>
                  </Campo>
                </Grid>
              </Sec>

              <Sec title="Valores da linha">
                <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                  <Texto label="Quantidade" value={linha.qtd} />
                  <Texto label="Valor unitário" value={linha.preco} />
                  <Escolha label="Tipo de preço" value={tipoPreco} onChange={setTipoPreco} options={TIPOS_PRECO} />
                  <Texto label="% desconto" value={linha.desc} />
                  <Texto label="Desconto R$" value={fmtQtd(descontoEmValor)} />
                  <Texto label="% acréscimo" value={linha.acr} />
                  <Campo label="Total deste item">
                    {/* Derivado de `unitarioLiquido` + `totalDoItem` — as MESMAS
                        funções que a linha da tabela usa. Nenhuma conta nova. */}
                    <Input readOnly value={brl(totalDaLinha)} className="bg-muted/40" />
                    <span className="mt-0.5 block text-[11px] text-muted-foreground">
                      quantidade × valor unitário, com desconto e acréscimo
                    </span>
                  </Campo>
                </Grid>
              </Sec>

              <Sec title="Comissão deste item">
                <Stack gap={3}>
                  <Inline gap={2} align="center">
                    <Checkbox
                      id="v3-item-comissiona"
                      checked={comissiona}
                      onCheckedChange={(v) => setComissiona(v === true)}
                    />
                    <label htmlFor="v3-item-comissiona" className="cursor-pointer leading-tight">
                      <span className="block text-[12px] font-medium">Comissiona este item</span>
                      <span className="block text-[11px] text-muted-foreground">
                        desligado, o item sai da base de comissão da venda
                      </span>
                    </label>
                  </Inline>

                  <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                    <Escolha
                      label="Tipo de comissionado"
                      value={tipoComissionado}
                      onChange={setTipoComissionado}
                      options={TIPOS_BENEFICIARIO.map((t) => t.l)}
                    />
                    <Escolha label="Quem executa / vende" value={executante} onChange={setExecutante} options={['—']} />
                    <Escolha
                      label="Base de cálculo"
                      value={baseComissao}
                      onChange={setBaseComissao}
                      options={BASES.map((b) => b.label)}
                    />
                    <Texto label="Percentual" value={percentual} onChange={setPercentual} />
                  </Grid>

                  {/* Faixa de resumo — `Plate` NÃO serve aqui: ele é o KPI grande
                      de `label`+`valor`, não um container. Uso a caixa neutra do
                      DS, que é o que a âncora desenha nesse bloco. */}
                  <div className="rounded-lg border border-border bg-muted/30 px-4 py-3">
                    <Inline gap={4} align="center" className="flex-wrap">
                      <div>
                        <Lbl className="mb-0">Base do item</Lbl>
                        <b className="text-[14px] tabular-nums">{brl(totalDaLinha)}</b>
                      </div>
                      <div>
                        <Lbl className="mb-0">Comissão do item</Lbl>
                        <b className="text-[14px] tabular-nums">{brl(comissaoDoItem)}</b>
                      </div>
                      <span className="min-w-[220px] flex-1 text-[11px] leading-tight text-muted-foreground">
                        Somada à comissão da venda e apurada para <b>quem executou</b> — não
                        para quem digitou. É por aqui que serviço com técnico próprio recebe
                        percentual diferente do produto.
                      </span>
                    </Inline>
                  </div>
                </Stack>
              </Sec>
            </Stack>
          )}

          {aba === 'producao' && (
            <Stack gap={4}>
              {/* As 2 seções da âncora (`sells-item-detail.jsx` — DrawerSections
                  "Instruções de produção" · "Arquivo de arte"). A onda 4 portou 3
                  dos 10 campos e nenhuma das seções. */}
              <Sec title="Instruções de produção">
                <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-3">
                  <Escolha label="Em produção" value={emProducao} onChange={setEmProducao} options={EM_PRODUCAO} />
                  <Escolha label="Tipo de impressão" value={impressao} onChange={setImpressao} options={TIPOS_IMPRESSAO} />
                  <Escolha label="Acabamento" value={acabamento} onChange={setAcabamento} options={ACABAMENTOS} />
                  <Escolha label="Local de aplicação" value={local} onChange={setLocal} options={LOCAIS_APLICACAO} />
                  <Escolha label="Equipamento / setor" value={equipamento} onChange={setEquipamento} options={EQUIPAMENTOS} />
                  <Escolha label="Prioridade" value={prioridade} onChange={setPrioridade} options={PRIORIDADES} />
                  <Campo label="Requisitar do estoque">
                    <Select value={localEstoque} onValueChange={setLocalEstoque}>
                      <SelectTrigger className="w-full">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        {LOCAIS_ESTOQUE.map((o) => (
                          <SafeSelectItem key={o} value={o} className="text-[12.5px]">
                            {o}
                          </SafeSelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    <span className="text-[11px] leading-snug text-muted-foreground">
                      de onde a produção retira o material
                    </span>
                  </Campo>
                  <Texto label="Prazo da equipe (produção)" value={prazoEquipe} onChange={setPrazoEquipe} placeholder="dd/mm/aaaa" />
                  <Texto label="Prazo da etapa" value={prazoEtapa} onChange={setPrazoEtapa} placeholder="dd/mm/aaaa" />
                </Grid>
                <div className="mt-3">
                  <AreaTexto
                    label="Observação de produção (vai na OP, não sai no documento do cliente)"
                    rows={3}
                    value={obsProducao}
                    onChange={setObsProducao}
                    placeholder="Sangria de 5cm. Cliente aprovou arte por e-mail em 27/07."
                  />
                </div>
              </Sec>

              <Sec title="Arquivo de arte">
                <Inline gap={3} align="center" wrap>
                  <span className="text-[12.5px] text-muted-foreground">Caminho do arquivo na rede</span>
                  <div className="min-w-0 flex-1">
                    <Input
                      value={caminhoArte}
                      onChange={(e) => setCaminhoArte(e.target.value)}
                      aria-label="Caminho do arquivo na rede"
                      placeholder="servidor\arte\2026\07\lona-prefeitura-v3.pdf"
                    />
                  </div>
                </Inline>
                {/* A âncora tem "Anexar arquivo" aqui. O preview não grava arquivo
                    (mesma razão da aba Anexos), e botão que promete e não entrega é
                    pior que botão ausente — charter, Goals. Fica só o caminho. */}
              </Sec>
            </Stack>
          )}

          {aba === 'fluxo' && (
            <Sec title="Fluxo de produção deste item">
            <div className="overflow-x-auto rounded-lg border border-border">
              <table className="w-full text-[12.5px]">
                <thead className="bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                  <tr>
                    <th className="px-3 py-2 font-medium">Etapa</th>
                    {/* responsável é PESSOA, setor é ONDE — separados de propósito */}
                    <th className="px-3 py-2 font-medium">Responsável</th>
                    <th className="px-3 py-2 font-medium">Setor</th>
                    <th className="px-3 py-2 font-medium">Situação</th>
                    <th className="px-3 py-2 font-medium">Previsão</th>
                    {/* 6ª coluna da âncora: remover a etapa da linha */}
                    <th className="px-3 py-2 text-center font-medium" />
                  </tr>
                </thead>
                <tbody>
                  {etapas.map((e, i) => (
                    <tr key={e.etapa} className="border-t border-border">
                      <td className="px-3 py-2">{e.etapa}</td>
                      <td className="px-3 py-2">{e.resp}</td>
                      <td className="px-3 py-2 text-muted-foreground">{e.setor}</td>
                      <td className="px-3 py-2">
                        <Pill tom={tomDoStatus(e.st)}>{e.st}</Pill>
                      </td>
                      <td className="px-3 py-2 tabular-nums">{e.prev}</td>
                      <td className="px-3 py-2 text-center">
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          aria-label={`Remover etapa ${e.etapa}`}
                          onClick={() => setEtapas(etapas.filter((_, j) => j !== i))}
                        >
                          ×
                        </Button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {etapas.length === 0 && (
              /* EmptyState da âncora: produto sem fluxo configurado não some da tela
                 sem dizer o que fazer. */
              <div className="rounded-lg border border-dashed border-border p-6 text-center">
                <span className="block text-[12.5px] text-muted-foreground">
                  Nenhuma etapa neste item. Este produto não tem fluxo de produção configurado —
                  aplique o fluxo padrão do cadastro.
                </span>
              </div>
            )}
            <Inline gap={2} align="center" wrap className="mt-3">
              <Button type="button" variant="outline" size="sm" onClick={() => setEtapas(ETAPAS_PADRAO)}>
                Aplicar fluxo padrão do produto
              </Button>
              {/* A âncora tem também "Adicionar etapa", num modal de 4 campos. Não
                  entra nesta leva: o modal é onda própria, e botão que abre nada é
                  pior que botão ausente. */}
            </Inline>
            </Sec>
          )}

          {aba === 'tributacao' && (
            <Stack gap={4}>
              <Stack gap={3}>
                <Lbl className="mb-0">Classificação fiscal</Lbl>
                <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                  <Escolha
                    label="Grupo do produto"
                    value={fv('grupo', GRUPOS_PRODUTO[0]!)}
                    onChange={setFv('grupo')}
                    options={GRUPOS_PRODUTO}
                  />
                  <Texto label="NCM" value={ncm} onChange={setNcm} erro={validarNcm(ncm)} placeholder="8 dígitos" />
                  <Texto label="CEST" value={cest} onChange={setCest} erro={validarCest(cest)} placeholder="sem CEST" />
                  <Texto label="CFOP" value={cfop} onChange={setCfop} erro={validarCfop(cfop)} placeholder="4 dígitos" />
                  <Escolha
                    label="Origem da mercadoria"
                    value={fv('origem', ORIGEM_MERCADORIA[0]!)}
                    onChange={setFv('origem')}
                    options={ORIGEM_MERCADORIA}
                  />
                  <Texto
                    label="Cód. de fábrica"
                    value={fv('cod_fabrica')}
                    onChange={setFv('cod_fabrica')}
                    placeholder="não informado"
                  />
                  <Texto label="Cód. EAN / GTIN" value={gtin} onChange={setGtin} erro={validarGtin(gtin)} placeholder="sem GTIN" />
                  <Texto label="cBenef" value={cbenef} onChange={setCbenef} erro={validarCbenef(cbenef)} placeholder="SC123456" />
                </Grid>
                <Inline gap={3} align="center" wrap>
                  <Marca
                    id="nao-recalcular"
                    rotulo="Não recalcular impostos na impressão da nota"
                    checked={naoRecalcular}
                    onChange={setNaoRecalcular}
                  />
                  <Button size="sm" variant="outline" className="ml-auto">
                    Recalcular impostos
                  </Button>
                </Inline>
              </Stack>

              {/* a incoerência é o achado que a validação campo-a-campo NÃO pega */}
              {incoerencia && (
                <div className="rounded-lg border border-destructive/40 bg-destructive-soft px-3 py-2">
                  <b className="block text-[12.5px] text-destructive-fg">Incoerência fiscal — a SEFAZ rejeita</b>
                  <span className="block text-[11.5px] leading-snug text-muted-foreground">{incoerencia}</span>
                </div>
              )}

              <Stack gap={2}>
                <Lbl className="mb-0">Impostos do item</Lbl>
                <span className="text-[11.5px] leading-snug text-muted-foreground">
                  Um imposto por linha — abra a setinha para ver e editar os campos. O ponto verde
                  marca o que tem valor nesta venda.
                </span>

                <div className="overflow-hidden rounded-lg border border-border">
                  <Grid gap={2} className="grid-cols-[1fr_repeat(3,minmax(0,7rem))_2rem] border-b border-border bg-muted/60 px-3 py-2 text-[10.5px] font-semibold tracking-[.05em] text-muted-foreground uppercase">
                    <span>Imposto</span>
                    <span className="text-right">Base de cálculo</span>
                    <span className="text-right">Alíquota</span>
                    <span className="text-right">Valor</span>
                    <span />
                  </Grid>

                  {IMPOSTOS.map((imp) => {
                    const aberto = impostoAberto === imp.k;
                    const cstDele = imp.k === 'icms' ? cst : fv(`cst_${imp.k}`, cstDoImposto(imp.k)[0]!);
                    const aliqDele =
                      imp.k === 'icms' ? aliquota : fv(`aliq_${imp.k}`, fmtBR(imp.aliq));
                    const valor = impostoDe(imp, baseDeCalculo, aliquota, cst);
                    const temValor = valor > 0.005;
                    const erroDele = validarAliquota(aliqDele) ?? erroDeCoerencia(cstDele, aliqDele);

                    return (
                      <div key={imp.k} className={cn('border-b border-border/60', aberto && 'bg-muted/40')}>
                        <Grid asChild gap={2} className="grid-cols-[1fr_repeat(3,minmax(0,7rem))_2rem] w-full items-center px-3 py-2 text-left text-[13.5px] hover:bg-muted/30">
                          <button
                            type="button"
                            onClick={() => setImpostoAberto(aberto ? null : imp.k)}
                            aria-expanded={aberto}
                          >
                          <Inline gap={2} align="center" className="min-w-0">
                            <b className="font-semibold">{imp.l}</b>
                            {erroDele ? (
                              <span className="flex-none text-[11px] font-semibold text-destructive-fg">
                                pendência
                              </span>
                            ) : (
                              temValor && (
                                <span
                                  aria-label="tem valor nesta venda"
                                  className="size-1.5 flex-none rounded-full bg-success"
                                />
                              )
                            )}
                            {imp.k === 'icms' &&
                              (difalLigado ? (
                                <Pill tom="warning">DIFAL ligado</Pill>
                              ) : (
                                <Pill>tem DIFAL</Pill>
                              ))}
                          </Inline>
                          <span className="text-right font-mono tabular-nums text-muted-foreground">
                            {fmtBR(baseDeCalculo)}
                          </span>
                          <span
                            className={cn(
                              'text-right font-mono tabular-nums',
                              erroDele ? 'text-destructive-fg' : temValor ? 'text-foreground' : 'text-muted-foreground',
                            )}
                          >
                            {fmtBR(aliquotaDe(imp, aliquota))}%
                          </span>
                          <span
                            className={cn(
                              'text-right font-mono font-semibold tabular-nums',
                              temValor ? 'text-foreground' : 'text-muted-foreground',
                            )}
                          >
                            {fmtBR(valor)}
                          </span>
                          <span aria-hidden className="text-center text-muted-foreground">
                            {aberto ? '▲' : '▼'}
                            </span>
                          </button>
                        </Grid>

                        {aberto && (
                          <Stack gap={3} className="px-3 pb-4">
                            <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                              <Escolha
                                label={`CST / situação — ${imp.l}`}
                                value={cstDele}
                                onChange={imp.k === 'icms' ? setCst : setFv(`cst_${imp.k}`)}
                                options={cstDoImposto(imp.k)}
                              />
                              <Texto label="Base de cálculo" value={fmtBR(baseDeCalculo)} />
                              <Texto
                                label="Alíquota (%)"
                                value={aliqDele}
                                onChange={imp.k === 'icms' ? setAliquota : setFv(`aliq_${imp.k}`)}
                                erro={erroDele}
                              />
                              <Texto label="Valor do imposto" value={fmtBR(valor)} />

                              {imp.k === 'icms' && (
                                <>
                                  <Texto
                                    label="Redução de base (%)"
                                    value={fv('red_icms', '0,00')}
                                    onChange={setFv('red_icms')}
                                    erro={validarReducao(fv('red_icms', '0,00'))}
                                  />
                                  <Texto
                                    label="MVA / margem ST (%)"
                                    value={fv('mva', '0,00')}
                                    onChange={setFv('mva')}
                                    erro={validarReducao(fv('mva', '0,00'))}
                                  />
                                  <Texto label="Base ST" value={fmtBR(0)} />
                                  <Texto label="ICMS ST" value={fmtBR(0)} />
                                </>
                              )}

                              {(imp.k === 'ibs' || imp.k === 'cbs') && (
                                <>
                                  <Escolha
                                    label="Classificação tributária (cClassTrib)"
                                    value={fv(`class_${imp.k}`, CLASSIFICACAO_TRIBUTARIA[0]!)}
                                    onChange={setFv(`class_${imp.k}`)}
                                    options={CLASSIFICACAO_TRIBUTARIA}
                                  />
                                  <Texto
                                    label="Alíquota efetiva (%)"
                                    value={fv(`aliq_ef_${imp.k}`, fmtBR(imp.aliq))}
                                    onChange={setFv(`aliq_ef_${imp.k}`)}
                                  />
                                  <Texto label="Crédito presumido" value={fmtBR(0)} />
                                  <Inline align="end" className="pb-2">
                                    <Marca
                                      id={`monofasia-${imp.k}`}
                                      rotulo="Monofasia"
                                      sub="Reforma tributária — transição 2026"
                                      checked={monofasia}
                                      onChange={setMonofasia}
                                    />
                                  </Inline>
                                </>
                              )}

                              {imp.k === 'issqn' && (
                                <>
                                  <Texto
                                    label="Código do serviço (LC 116)"
                                    value={fv('cod_servico')}
                                    onChange={setFv('cod_servico')}
                                    placeholder="17.06"
                                  />
                                  <Escolha
                                    label="Município de incidência"
                                    value={fv('municipio', MUNICIPIOS_ISSQN[0]!)}
                                    onChange={setFv('municipio')}
                                    options={MUNICIPIOS_ISSQN}
                                  />
                                  <Inline align="end" className="pb-2">
                                    <Marca
                                      id="iss-retido"
                                      rotulo="ISS retido na fonte"
                                      checked={issRetido}
                                      onChange={setIssRetido}
                                    />
                                  </Inline>
                                  <Texto label="Base reduzida" value={fmtBR(baseDeCalculo)} />
                                </>
                              )}
                            </Grid>

                            {imp.k === 'icms' && (
                              <div
                                className={cn(
                                  'rounded-lg border p-3',
                                  difalLigado ? 'border-warning/40 bg-warning-soft' : 'border-border bg-muted/40',
                                )}
                              >
                                <Inline gap={3} align="center" wrap className={difalLigado ? 'mb-3' : undefined}>
                                  <Marca
                                    id="difal"
                                    rotulo="DIFAL — diferencial de alíquota"
                                    sub="venda interestadual para não contribuinte (EC 87/2015)"
                                    checked={difalLigado}
                                    onChange={setDifalLigado}
                                  />
                                  {difalLigado && (
                                    <span className="ml-auto">
                                      <Lbl>Total DIFAL + FCP</Lbl>
                                      <b className="font-mono text-[14px]">{brl(difal.total)}</b>
                                    </span>
                                  )}
                                </Inline>

                                {difalLigado && (
                                  <Stack gap={3}>
                                    <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                                      <Escolha
                                        label="UF de destino"
                                        value={fv('difal_uf', UFS_DIFAL[0]!)}
                                        onChange={setFv('difal_uf')}
                                        options={UFS_DIFAL}
                                      />
                                      <Texto
                                        label="Alíquota interestadual (%)"
                                        value={fv('difal_inter', '12,00')}
                                        onChange={setFv('difal_inter')}
                                      />
                                      <Texto
                                        label="Alíquota interna do destino (%)"
                                        value={fv('difal_dest', '18,00')}
                                        onChange={setFv('difal_dest')}
                                        erro={
                                          difal.invertido
                                            ? 'menor que a interestadual — não há DIFAL a recolher'
                                            : null
                                        }
                                      />
                                      <Texto
                                        label="FCP do destino (%)"
                                        value={fv('difal_fcp', '2,00')}
                                        onChange={setFv('difal_fcp')}
                                      />
                                    </Grid>
                                    <Inline gap={4} wrap className="rounded-lg border border-border bg-card p-3">
                                      {(
                                        [
                                          ['Base do DIFAL', baseDeCalculo],
                                          ['ICMS UF remetente', difal.remetente],
                                          ['ICMS UF destino', difal.destino],
                                          ['FCP UF destino', difal.fcp],
                                        ] as const
                                      ).map(([rotulo, v]) => (
                                        <div key={rotulo}>
                                          <Lbl>{rotulo}</Lbl>
                                          <b className="font-mono text-[13px]">{fmtBR(v)}</b>
                                        </div>
                                      ))}
                                      <span className="ml-auto max-w-[300px] text-[11.5px] leading-snug text-muted-foreground">
                                        Partilha <b>100% para o destino</b> desde 2019. Sai na NF-e como{' '}
                                        <code>vICMSUFDest</code>, <code>vICMSUFRemet</code> e <code>vFCPUFDest</code>.
                                      </span>
                                    </Inline>
                                  </Stack>
                                )}
                              </div>
                            )}
                          </Stack>
                        )}
                      </div>
                    );
                  })}

                  <Grid gap={2} className="grid-cols-[1fr_repeat(3,minmax(0,7rem))_2rem] items-center bg-muted/60 px-3 py-2.5">
                    <span className="col-span-3 text-right text-[11px] font-semibold tracking-[.04em] text-muted-foreground uppercase">
                      Total de impostos do item
                    </span>
                    <span className="text-right font-mono text-[15px] font-semibold tabular-nums">
                      {fmtBR(totalDosImpostos)}
                    </span>
                    <span />
                  </Grid>
                </div>

                <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                  <Texto label="IBPT nacional (%)" value={fv('ibpt_nac', '0,00')} onChange={setFv('ibpt_nac')} />
                  <Texto label="IBPT importação (%)" value={fv('ibpt_imp', '0,00')} onChange={setFv('ibpt_imp')} />
                  <Texto label="IBPT estadual (%)" value={fv('ibpt_est', '0,00')} onChange={setFv('ibpt_est')} />
                  <Texto label="IBPT municipal (%)" value={fv('ibpt_mun', '0,00')} onChange={setFv('ibpt_mun')} />
                  <Texto label="Peso líquido (kg)" value={fv('peso', '0,00')} onChange={setFv('peso')} />
                  <Texto label="Peso bruto (kg)" value={fv('peso_bruto', fv('peso', '0,00'))} onChange={setFv('peso_bruto')} />
                  <Texto label="Despesas acessórias" value={fv('desp', '0,00')} onChange={setFv('desp')} />
                  <Texto label="Frete do item" value={fv('frete_item', '0,00')} onChange={setFv('frete_item')} />
                </Grid>
              </Stack>

              <Stack gap={3}>
                <Lbl className="mb-0">Importação</Lbl>
                <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-3">
                  <Texto label="Nº da DI / DUIMP" value={fv('di')} onChange={setFv('di')} placeholder="produto nacional" />
                  <Texto label="Data do desembaraço" value={fv('desembaraco')} onChange={setFv('desembaraco')} placeholder="dd/mm/aaaa" />
                  <Texto label="Local do desembaraço" value={fv('local_desemb')} onChange={setFv('local_desemb')} placeholder="não se aplica" />
                  <Texto label="Valor aduaneiro" value={fv('aduaneiro', '0,00')} onChange={setFv('aduaneiro')} />
                  <Texto label="AFRMM" value={fv('afrmm', '0,00')} onChange={setFv('afrmm')} />
                  <Escolha
                    label="Via de transporte"
                    value={fv('via', VIAS_TRANSPORTE[0]!)}
                    onChange={setFv('via')}
                    options={VIAS_TRANSPORTE}
                  />
                </Grid>
              </Stack>

              <Stack gap={2}>
                <Lbl className="mb-0">Descrição na NF-e</Lbl>
                <textarea
                  rows={4}
                  value={fv('desc_nfe', linha?.nome ?? '')}
                  onChange={(e) => setFv('desc_nfe')(e.target.value)}
                  aria-label="Descrição do produto como sai na NF-e"
                  className="w-full rounded-lg border border-border bg-card px-3 py-2 text-[13px]"
                />
                <span className="text-[11px] leading-snug text-muted-foreground">
                  É este texto que o cliente lê na nota — não a descrição interna.
                </span>
              </Stack>
            </Stack>
          )}

          {aba === 'preco' && (
            <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
              <Texto label="Valor unitário" value={linha.preco} />
              <Texto label="Desconto (%)" value={linha.desc} />
              <Texto label="Acréscimo (%)" value={linha.acr} />
              <Texto label="Base de cálculo" value={fmtBR(baseDeCalculo)} />
            </Grid>
          )}

          {aba === 'anexos' && (
            <Sec title="Anexos do item">
              <div className="rounded-lg border border-dashed border-border p-6 text-center">
                <span className="block text-[12.5px] text-muted-foreground">
                  Arte, prova e comprovante do item. O upload não faz parte deste passo do porte — a
                  tela de preview não grava arquivo.
                </span>
              </div>
              {/* A tabela da âncora (`DataTable` Arquivo · Tipo · Enviado · ação).
                  Os dois anexos são cena, como as etapas do fluxo. */}
              <div className="mt-3 overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-[12.5px]">
                  <thead className="bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                    <tr>
                      <th className="px-3 py-2 font-medium">Arquivo</th>
                      <th className="px-3 py-2 font-medium">Tipo</th>
                      <th className="px-3 py-2 font-medium">Enviado</th>
                      <th className="px-3 py-2 text-center font-medium" />
                    </tr>
                  </thead>
                  <tbody>
                    {ANEXOS.map((a) => (
                      <tr key={a.nome} className="border-t border-border">
                        <td className="px-3 py-2">{a.nome}</td>
                        <td className="px-3 py-2">
                          <Pill tom="neutro">{a.tipo}</Pill>
                        </td>
                        <td className="px-3 py-2 tabular-nums">{a.enviado}</td>
                        <td className="px-3 py-2 text-center">
                          {/* sem handler: o preview não serve arquivo */}
                          <Button type="button" variant="outline" size="sm" disabled title="o preview não grava nem serve arquivo">
                            Baixar
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Sec>
          )}

          {aba === 'observacao' && (
            <Sec title="Observações do produto">
              {/* DUAS observações, separadas de propósito — a âncora registra que
                  unificá-las foi o que gerou a reclamação de nota interna vazando no
                  documento do cliente (CU-SELL-12). O porte tinha só uma, e o rótulo
                  dizia que ela saía no documento: era a interna que sumia. */}
              <AreaTexto
                label="Observação geral do produto (sai no documento do cliente)"
                rows={4}
                value={observacao}
                onChange={setObservacao}
                placeholder="Lona com 5cm de sangria em cada lado, ilhós a cada 50cm."
              />
              <div className="mt-3">
                <AreaTexto
                  label="Observação interna (não sai no documento)"
                  rows={3}
                  value={obsInterna}
                  onChange={setObsInterna}
                  placeholder="Cliente reclamou da cor na última compra — conferir perfil ICC."
                />
              </div>
              <p className="mt-2.5 text-[11.5px] leading-relaxed text-muted-foreground">
                Duas observações separadas de propósito: a do cliente vai pro PDF/NF-e, a interna
                fica na OP.
              </p>
            </Sec>
          )}
        </Stack>

        <SheetFooter className="shrink-0 flex-row items-center justify-between gap-2 border-t border-border px-5 py-3">
          <Inline gap={2} align="center">
            <Button type="button" variant="outline" size="sm" disabled={indice <= 0} onClick={() => onNavegar?.(-1)}>
              ‹ Anterior
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={indice >= total - 1}
              onClick={() => onNavegar?.(1)}
            >
              Próximo ›
            </Button>
            <span className="text-[11.5px] text-muted-foreground">
              {indice + 1}/{total}
            </span>
            {/* Os dois selos do rodapé da âncora (`sells-item-detail.jsx:199-200`):
                o que avisa que há edição não confirmada e o que conta pendência
                fiscal. Ficam ao lado da navegação porque é ali que o operador olha
                antes de trocar de item. */}
            {sujo && <Pill tom="warning">alteração não confirmada</Pill>}
            {erros.length > 0 && (
              <Pill tom="destructive">
                {erros.length === 1 ? '1 pendência fiscal' : `${erros.length} pendências fiscais`}
              </Pill>
            )}
          </Inline>

          <Inline gap={2} align="center">
            <Button type="button" variant="outline" onClick={onFechar}>
              Cancelar
            </Button>
            {/* salvar item com incoerência fiscal empurraria pra SEFAZ uma rejeição
                que o sistema já sabia prever */}
            <Button
              type="button"
              onClick={onFechar}
              disabled={erros.length > 0}
              title={erros.length > 0 ? `${erros.length} pendência(s) fiscal(is) nesta aba` : undefined}
            >
              Confirmar item
            </Button>
          </Inline>
        </SheetFooter>
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
