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
import { areaUnitaria, totalDoItem, unitarioLiquido } from './calculo-item';
import { BASES, TIPOS_BENEFICIARIO } from './comissao-dominio';
import { brl, fmtBR, fmtQtd, parseBR } from './numeros';
import {
  ABAS,
  CST_ICMS,
  IMPOSTOS,
  LOCAIS_APLICACAO,
  ROTULO_DA_ABA,
  TIPOS_IMPRESSAO,
  erroDeCoerencia,
  errosFiscais,
  validarAliquota,
  validarCbenef,
  validarCest,
  validarCfop,
  validarGtin,
  validarNcm,
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

  /* produção */
  const [local, setLocal] = useState(LOCAIS_APLICACAO[0]!);
  const [impressao, setImpressao] = useState(TIPOS_IMPRESSAO[0]!);
  const [observacao, setObservacao] = useState('');

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
            <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-3">
              <Escolha label="Local de aplicação" value={local} onChange={setLocal} options={LOCAIS_APLICACAO} />
              <Escolha label="Tipo de impressão" value={impressao} onChange={setImpressao} options={TIPOS_IMPRESSAO} />
              <Texto label="Acabamento" value="" placeholder="Ilhós, bastão, corte especial…" onChange={() => {}} />
            </Grid>
          )}

          {aba === 'fluxo' && (
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
                  </tr>
                </thead>
                <tbody>
                  {ETAPAS_PADRAO.map((e) => (
                    <tr key={e.etapa} className="border-t border-border">
                      <td className="px-3 py-2">{e.etapa}</td>
                      <td className="px-3 py-2">{e.resp}</td>
                      <td className="px-3 py-2 text-muted-foreground">{e.setor}</td>
                      <td className="px-3 py-2">
                        <Pill tom={tomDoStatus(e.st)}>{e.st}</Pill>
                      </td>
                      <td className="px-3 py-2 tabular-nums">{e.prev}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {aba === 'tributacao' && (
            <Stack gap={3}>
              <Lbl className="mb-0">Classificação fiscal</Lbl>
              <Grid gap={3} className="sm:grid-cols-2 lg:grid-cols-4">
                <Texto label="NCM" value={ncm} onChange={setNcm} erro={validarNcm(ncm)} placeholder="8 dígitos" />
                <Texto label="CFOP" value={cfop} onChange={setCfop} erro={validarCfop(cfop)} placeholder="4 dígitos" />
                <Texto label="CEST" value={cest} onChange={setCest} erro={validarCest(cest)} placeholder="sem CEST" />
                <Texto label="GTIN" value={gtin} onChange={setGtin} erro={validarGtin(gtin)} placeholder="sem GTIN" />
                <Escolha label="CST / CSOSN" value={cst} onChange={setCst} options={CST_ICMS} />
                <Texto
                  label="Alíquota ICMS (%)"
                  value={aliquota}
                  onChange={setAliquota}
                  erro={validarAliquota(aliquota)}
                />
                <Texto label="cBenef" value={cbenef} onChange={setCbenef} erro={validarCbenef(cbenef)} placeholder="SC123456" />
              </Grid>

              {/* a incoerência é o achado que a validação campo-a-campo NÃO pega */}
              {incoerencia && (
                <div className="rounded-lg border border-destructive/40 bg-destructive-soft px-3 py-2">
                  <b className="block text-[12.5px] text-destructive-fg">Incoerência fiscal — a SEFAZ rejeita</b>
                  <span className="block text-[11.5px] leading-snug text-muted-foreground">{incoerencia}</span>
                </div>
              )}

              <Lbl className="mb-0">Impostos do item</Lbl>
              <div className="overflow-x-auto rounded-lg border border-border">
                <table className="w-full text-[12.5px]">
                  <thead className="bg-muted/60 text-left text-[11px] tracking-wide text-muted-foreground uppercase">
                    <tr>
                      <th className="px-3 py-2 font-medium">Imposto</th>
                      <th className="px-3 py-2 text-right font-medium">Base de cálculo</th>
                      <th className="px-3 py-2 text-right font-medium">Alíquota</th>
                    </tr>
                  </thead>
                  <tbody>
                    {IMPOSTOS.map((imp) => (
                      <tr key={imp.k} className="border-t border-border">
                        <td className="px-3 py-1.5">
                          {imp.l}
                          {(imp.k === 'ibs' || imp.k === 'cbs') && (
                            <span className="ml-2 text-[10.5px] text-muted-foreground">reforma</span>
                          )}
                        </td>
                        <td className="px-3 py-1.5 text-right tabular-nums">{fmtBR(baseDeCalculo)}</td>
                        <td className="px-3 py-1.5 text-right tabular-nums">
                          {fmtBR(imp.k === 'icms' ? parseBR(aliquota) : imp.aliq)}%
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="text-[11px] leading-snug text-muted-foreground">
                Os valores por imposto são calculados no servidor na emissão — esta tela confere o
                <b> preenchimento</b>, não apura tributo.
              </p>
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
            <div className="rounded-lg border border-dashed border-border p-6 text-center">
              <span className="block text-[12.5px] text-muted-foreground">
                Arte, prova e comprovante do item. O upload não faz parte deste passo do porte — a
                tela de preview não grava arquivo.
              </span>
            </div>
          )}

          {aba === 'observacao' && (
            <Campo label="Observação do item (sai na OS e no documento)">
              <textarea
                rows={4}
                value={observacao}
                onChange={(e) => setObservacao(e.target.value)}
                placeholder="Instrução de produção, cuidado de manuseio, referência do cliente…"
                /* `<textarea>` CRU (não o `<Textarea>` do DS), então não passa por
                   `.cw-input` nem pela regra escopada — aqui as classes VALEM e
                   precisam trazer a caixa na mão. Valores do Textarea do DS vivo
                   (`controlStyle()` + `{ lineHeight: 1.5 }`): 13px/1.5, padding 7/10. */
                className="w-full rounded-md border border-input bg-background px-[10px] py-[7px] text-[13px] leading-[1.5] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40"
              />
            </Campo>
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
