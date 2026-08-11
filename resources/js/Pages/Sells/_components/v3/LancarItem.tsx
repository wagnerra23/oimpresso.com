// Lançamento do item — o passo entre ESCOLHER o produto e ele ENTRAR na venda.
// Porte de `design_handoff_cadastro_venda/design/sells-lancamento.jsx` (onda 1).
//
// POR QUE ESTE PASSO EXISTE (e por que a busca não pode adicionar direto)
// Num produto dimensional a quantidade faturada NÃO é digitada: ela é derivada
// das medidas (peças × altura × largura). Sem este passo o operador teria que
// calcular a área de cabeça e digitar o resultado — que é exatamente onde nasce
// erro de quantidade, e quantidade errada é estoque errado e valor errado.
//
// TIER 0 — VALOR E ESTOQUE (memory/proibicoes.md · REGRA MESTRE)
// As fórmulas vivem em `./calculo-item.ts` (módulo puro) e vieram LITERAIS do
// handoff §"Lançamento", provadas por dois caminhos (aritmética × render medido).
// Toda entrada passa por `parseBR` e todo resultado por `submitSafe` — o guard
// do incidente `num_uf` de 2026-06-05. Esta tela NÃO grava: quem manda no valor
// é o servidor; aqui o cálculo existe pra dar retorno enquanto se digita.
//
// O QUE ESTA PEÇA NÃO FAZ (deliberado, é a onda 4 do plano)
// Drawer de detalhe do item (7 abas · tributação · DIFAL). O gatilho continua
// dizendo o que falta em vez de fingir que abre.

import { useEffect, useMemo, useState } from 'react';
import { Grid, Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { Aviso, Lbl, MoneyInput, Pill } from './primitivos';
import { abaixoDoPiso as ehAbaixoDoPiso, areaUnitaria, quantidadeFaturada, totalDoItem, unitarioLiquido } from './calculo-item';
import { brl, fmtBR, fmtQtd, num, parseBR } from './numeros';

export type ProdutoCatalogo = {
  sku: string;
  nome: string;
  un: string;
  preco: number;
  estoque: number | null;
  tipo: 'produto' | 'servico';
  ean: string | null;
  fabrica: string | null;
  categoria: string | null;
};

export type Executante = { id: string; nome: string; papel: 'funcionario' | 'tecnico' };

/** O que o lançamento devolve pra virar linha da venda. */
export type ItemLancado = {
  sku: string;
  nome: string;
  un: string;
  medidas: string | null;
  qtd: string;
  preco: string;
  desc: string;
  acr: string;
  estoque: number | null;
  executante: string | null;
  obsInterna: string;
  obsProducao: string;
  local: string;
  impressao: string;
};

/**
 * Unidades DIMENSIONAIS e quais medidas cada uma pede.
 *
 * A unidade do cadastro é o que decide se a quantidade é derivada ou digitada —
 * não um checkbox na tela. `m²` = altura × largura · `m³` acrescenta espessura ·
 * `m` usa só a largura (metro linear). Qualquer outra unidade é quantidade direta.
 */
const DIMENSIONAL: Record<string, readonly string[]> = {
  'm²': ['pecas', 'altura', 'largura'],
  'm³': ['pecas', 'altura', 'largura', 'esp'],
  m: ['pecas', 'largura'],
};

/** "Informações adicionais do produto/serviço" do legado — o que a maioria preenche. */
const LOCAIS = ['Fachada', 'Interno', 'Veículo', 'Painel', 'Vitrine', 'Totem', 'Obra'];
const IMPRESSOES = [
  'Digital — látex',
  'Digital — UV',
  'Offset',
  'Recorte eletrônico',
  'Sublimação',
  'Sem impressão',
];

export function LancarItem({
  produto,
  executantes,
  podeEditarPreco,
  onFechar,
  onConfirmar,
}: {
  produto: ProdutoCatalogo | null;
  executantes: Executante[];
  podeEditarPreco: boolean;
  onFechar: () => void;
  onConfirmar: (item: ItemLancado) => void;
}) {
  const dims = produto ? DIMENSIONAL[produto.un] : undefined;
  const servico = produto?.tipo === 'servico';

  const [pecas, setPecas] = useState('1');
  const [altura, setAltura] = useState('0,50');
  const [largura, setLargura] = useState('0,10');
  const [esp, setEsp] = useState('0,00');
  const [qtdDireta, setQtdDireta] = useState('1');
  const [preco, setPreco] = useState('0,00');
  const [desc, setDesc] = useState('0');
  const [acr, setAcr] = useState('0');
  const [executante, setExecutante] = useState('');
  const [obsInterna, setObsInterna] = useState('');
  const [obsProducao, setObsProducao] = useState('');
  const [local, setLocal] = useState('');
  const [impressao, setImpressao] = useState('');
  const [adicionaisAbertas, setAdicionaisAbertas] = useState(false);

  /* Reset ao trocar de produto: sem isso o preço do item anterior vazaria pro
     próximo lançamento — e preço vazado é valor errado, não inconveniência. */
  useEffect(() => {
    if (!produto) return;
    setPreco(fmtBR(produto.preco));
    setPecas('1');
    setQtdDireta('1');
    setDesc('0');
    setAcr('0');
    setAltura(produto.un === 'm²' || produto.un === 'm³' ? '0,50' : '0,00');
    setLargura(produto.un === 'm²' || produto.un === 'm³' || produto.un === 'm' ? '0,10' : '0,00');
    setEsp('0,00');
    setExecutante(executantes[0]?.id ?? '');
    setObsInterna('');
    setObsProducao('');
    setLocal('');
    setImpressao('');
    setAdicionaisAbertas(false);
  }, [produto, executantes]);

  /* ─── derivados (nunca em estado) ──────────────────────────────────────── */
  const nPecas = Math.max(parseBR(pecas), 0);
  const areaUn = produto ? areaUnitaria(produto.un, parseBR(altura), parseBR(largura), parseBR(esp)) : 0;
  const qtd = quantidadeFaturada(!!dims, nPecas, areaUn, parseBR(qtdDireta));
  const unitario = unitarioLiquido(parseBR(preco), parseBR(desc), parseBR(acr));
  const total = totalDoItem(qtd, unitario);

  const abaixoDoPiso = !!produto && ehAbaixoDoPiso(parseBR(preco), produto.preco);
  const semEstoque = !!produto && produto.estoque !== null && qtd > produto.estoque;
  const preenchidas = useMemo(
    () => [obsInterna, obsProducao, local, impressao].filter((v) => v.trim()).length,
    [obsInterna, obsProducao, local, impressao],
  );

  if (!produto) return null;

  const medidasResumo = dims
    ? `${num(nPecas, 0)}× ${num(parseBR(altura), 2)}×${num(parseBR(largura), 2)}${produto.un === 'm³' ? `×${num(parseBR(esp), 2)}` : ''}m`
    : null;

  const confirmar = () =>
    onConfirmar({
      sku: produto.sku,
      nome: produto.nome,
      un: produto.un,
      medidas: medidasResumo,
      qtd: fmtQtd(qtd),
      preco: fmtBR(parseBR(preco)),
      desc: String(parseBR(desc)),
      acr: String(parseBR(acr)),
      estoque: produto.estoque,
      executante: servico ? (executantes.find((e) => e.id === executante)?.nome ?? null) : null,
      obsInterna,
      obsProducao,
      local,
      impressao,
    });

  return (
    <Dialog open onOpenChange={(aberto) => !aberto && onFechar()}>
      <DialogContent className="sells-cowork max-h-[92vh] overflow-y-auto sm:max-w-[720px]">
        <DialogHeader>
          <DialogTitle className="text-[15px] leading-[1.3]">Lançar {produto.nome}</DialogTitle>
          <DialogDescription className="text-[11.5px] leading-[1.35]">
            {dims
              ? 'A quantidade faturada vem das medidas — não é digitada.'
              : 'Informe a quantidade e confira o valor antes de adicionar.'}
          </DialogDescription>
        </DialogHeader>

        <Stack gap={4}>
          <Inline gap={2} wrap>
            <Pill mono>{produto.sku}</Pill>
            <Pill tom={servico ? 'info' : 'primary'}>{servico ? 'serviço' : 'produto'}</Pill>
            <Pill mono>unidade {produto.un}</Pill>
            {produto.estoque !== null ? (
              <Pill tom={semEstoque ? 'destructive' : 'success'} mono>
                estoque {num(produto.estoque, 2)} {produto.un}
              </Pill>
            ) : (
              <Pill mono>não controla estoque</Pill>
            )}
          </Inline>

          {/* ─── medidas (só dimensional) ─────────────────────────────────── */}
          {dims && (
            <div>
              <Lbl>Medidas</Lbl>
              <Grid gap={3} className="sm:grid-cols-4">
                <MoneyInput label="Peças" prefix="qt" value={pecas} onChange={setPecas} />
                <MoneyInput label="Altura" prefix="m" value={altura} onChange={setAltura} />
                <MoneyInput label="Largura" prefix="m" value={largura} onChange={setLargura} />
                {produto.un === 'm³' ? (
                  <MoneyInput label="Espessura" prefix="m" value={esp} onChange={setEsp} />
                ) : (
                  <div>
                    <Lbl>Medida da peça</Lbl>
                    <b className="block font-mono text-[13.5px] font-semibold leading-[1.4]">
                      {num(parseBR(altura), 2)} × {num(parseBR(largura), 2)} m
                    </b>
                    <span className="block text-[11.5px] leading-[1.35] text-muted-foreground">
                      {num(areaUn, 3)} {produto.un} por peça
                    </span>
                  </div>
                )}
              </Grid>
              <Inline gap={3} wrap className="mt-3 rounded-lg border border-border bg-muted p-3">
                <span className="text-[12.5px] leading-[1.4] text-muted-foreground">
                  {num(nPecas, 0)} peça(s) de {num(parseBR(altura), 2)} × {num(parseBR(largura), 2)} m
                </span>
                <Inline gap={2} align="baseline" className="ml-auto">
                  <Lbl className="mb-0">Quantidade faturada</Lbl>
                  <b className="font-mono text-[15px] font-semibold leading-none tabular-nums">
                    {fmtQtd(qtd)} {produto.un}
                  </b>
                </Inline>
              </Inline>
            </div>
          )}

          {/* ─── quantidade direta (não dimensional) ──────────────────────── */}
          {!dims && (
            <Grid gap={3} className="sm:grid-cols-3">
              <MoneyInput
                label={produto.un === 'h' ? 'Horas' : 'Quantidade'}
                prefix={produto.un === 'h' ? 'h' : 'qt'}
                value={qtdDireta}
                onChange={setQtdDireta}
              />
              <div>
                <Lbl>Unidade</Lbl>
                <b className="font-mono text-[13.5px] font-semibold leading-[1.4]">{produto.un}</b>
              </div>
            </Grid>
          )}

          {/* ─── valores ──────────────────────────────────────────────────── */}
          <div>
            <Lbl>Valores</Lbl>
            <Grid gap={3} className="sm:grid-cols-4">
              <MoneyInput label="Valor de tabela" value={fmtBR(produto.preco)} readOnly />
              <MoneyInput
                label="Valor unitário"
                value={preco}
                onChange={setPreco}
                readOnly={!podeEditarPreco}
                help={podeEditarPreco ? undefined : 'Seu perfil não pode alterar preço'}
              />
              <MoneyInput label="Desconto" prefix="%" value={desc} onChange={setDesc} readOnly={!podeEditarPreco} />
              <MoneyInput label="Acréscimo" prefix="%" value={acr} onChange={setAcr} readOnly={!podeEditarPreco} />
            </Grid>

            {/* Resumo em `bg-muted`, não no plate escuro: o plate é o total da
                VENDA na coluna direita, e ter dois blocos escuros competindo
                confunde qual número manda (handoff usa `--bg-2` aqui). */}
            <Inline gap={4} wrap align="baseline" className="mt-3 rounded-lg border border-border bg-muted p-3">
              <div>
                <Lbl className="mb-0">Unitário líquido</Lbl>
                <b className="font-mono text-[15px] font-semibold leading-none tabular-nums">{brl(unitario)}</b>
              </div>
              <div>
                <Lbl className="mb-0">Quantidade</Lbl>
                <b className="font-mono text-[15px] font-semibold leading-none tabular-nums">
                  {fmtQtd(qtd)} {produto.un}
                </b>
              </div>
              <div className="ml-auto text-right">
                <Lbl className="mb-0 text-primary">Total do item</Lbl>
                <b className="font-mono text-[17px] font-semibold leading-none tabular-nums">{brl(total)}</b>
              </div>
            </Inline>

            {!podeEditarPreco && (
              <div className="mt-2">
                <Aviso tom="info" titulo="Preço travado pelo perfil">
                  O valor vem da tabela aplicada na venda. Peça liberação ao supervisor para alterar.
                </Aviso>
              </div>
            )}
            {podeEditarPreco && abaixoDoPiso && (
              <div className="mt-2">
                <Aviso tom="warning" titulo="Abaixo do piso de preço">
                  {brl(parseBR(preco))} está mais de 15% abaixo da tabela ({brl(produto.preco)}). Finalizar exige
                  liberação de supervisor.
                </Aviso>
              </div>
            )}
            {semEstoque && (
              <div className="mt-2">
                <Aviso tom="destructive" titulo="Quantidade acima do estoque">
                  Pedido de {fmtQtd(qtd)} {produto.un} com {num(produto.estoque ?? 0, 2)} em estoque — vai gerar saldo
                  negativo ou pedido de compra.
                </Aviso>
              </div>
            )}
          </div>

          {/* ─── execução (só serviço) ────────────────────────────────────── */}
          {servico && (
            <div>
              <Lbl>Execução do serviço</Lbl>
              <Grid gap={3} className="sm:grid-cols-2">
                <div>
                  <Label htmlFor="v3-exec" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                    Funcionário vinculado
                  </Label>
                  <Select value={executante} onValueChange={setExecutante}>
                    <SelectTrigger id="v3-exec" className="h-9 w-full text-[13px]">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {executantes.map((e) => (
                        <SafeSelectItem key={e.id} value={e.id}>
                          {e.nome} · {e.papel}
                        </SafeSelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label htmlFor="v3-prev" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                    Data prevista
                  </Label>
                  <Input id="v3-prev" type="date" className="h-9" />
                </div>
              </Grid>
              <p className="mt-2 text-[11.5px] leading-[1.5] text-muted-foreground">
                Serviço não move estoque, e o funcionário vinculado é quem entra na apuração de comissão e na ordem de
                produção — por isso o campo só aparece quando o item é serviço.
              </p>
            </div>
          )}

          {/* ─── informações adicionais (dobra) ───────────────────────────── */}
          <div>
            <button
              type="button"
              onClick={() => setAdicionaisAbertas((v) => !v)}
              aria-expanded={adicionaisAbertas}
              className="inline-flex items-center gap-1.5 border-0 bg-transparent py-1 text-[11px] font-semibold uppercase leading-none tracking-[.05em] text-muted-foreground"
            >
              <span aria-hidden className={adicionaisAbertas ? 'rotate-180 transition-transform' : 'transition-transform'}>
                ▾
              </span>
              Informações adicionais do item
              {!adicionaisAbertas && preenchidas > 0 && (
                <Pill tom="primary">
                  {preenchidas} preenchida{preenchidas > 1 ? 's' : ''}
                </Pill>
              )}
            </button>

            {adicionaisAbertas && (
              <Stack gap={3} className="mt-2">
                <Grid gap={3} className="sm:grid-cols-2">
                  <div>
                    <Label htmlFor="v3-obs-int" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                      Observação (uso interno)
                    </Label>
                    <Textarea
                      id="v3-obs-int"
                      rows={2}
                      value={obsInterna}
                      onChange={(e) => setObsInterna(e.target.value)}
                      placeholder="Cliente aprovou arte por e-mail em 27/07"
                    />
                  </div>
                  <div>
                    <Label htmlFor="v3-obs-prod" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                      Observação para a produção
                    </Label>
                    <Textarea
                      id="v3-obs-prod"
                      rows={2}
                      value={obsProducao}
                      onChange={(e) => setObsProducao(e.target.value)}
                      placeholder="Sangria de 5cm, ilhós a cada 50cm"
                    />
                  </div>
                </Grid>
                <Grid gap={3} className="sm:grid-cols-2">
                  <div>
                    <Label htmlFor="v3-local" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                      Local da aplicação
                    </Label>
                    <Select value={local} onValueChange={setLocal}>
                      <SelectTrigger id="v3-local" className="h-9 w-full text-[13px]">
                        <SelectValue placeholder="—" />
                      </SelectTrigger>
                      <SelectContent>
                        {LOCAIS.map((l) => (
                          <SafeSelectItem key={l} value={l}>
                            {l}
                          </SafeSelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="v3-impr" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
                      Tipo de impressão
                    </Label>
                    <Select value={impressao} onValueChange={setImpressao}>
                      <SelectTrigger id="v3-impr" className="h-9 w-full text-[13px]">
                        <SelectValue placeholder="—" />
                      </SelectTrigger>
                      <SelectContent>
                        {IMPRESSOES.map((i) => (
                          <SafeSelectItem key={i} value={i}>
                            {i}
                          </SafeSelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                </Grid>
                <span className="text-[11.5px] leading-[1.4] text-muted-foreground">
                  A observação de uso interno e a de produção <b>não saem</b> no documento do cliente — a de produção vai
                  na ordem de produção.
                </span>
              </Stack>
            )}
          </div>
        </Stack>

        <DialogFooter className="sm:justify-between">
          <div className="text-left">
            <Lbl className="mb-0">Total do item</Lbl>
            <b className="font-mono text-[18px] font-semibold leading-none tabular-nums">{brl(total)}</b>
          </div>
          <Inline gap={2}>
            <Button variant="outline" onClick={onFechar}>
              Cancelar
            </Button>
            <Button onClick={confirmar} disabled={qtd <= 0}>
              Adicionar à venda
            </Button>
          </Inline>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
