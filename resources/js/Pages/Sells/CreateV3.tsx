// Venda V3 — porte do handoff `design_handoff_cadastro_venda` (Cadastro de Venda).
//
// POR QUE ESTA TELA EXISTE (Luiz [L], 2026-08-06)
// `Sells/Create.tsx` (rota /pos/create) NÃO pode mudar: a ROTA LIVRE (biz=4 —
// Larissa/Guilherme) opera nela e alteração quebra contrato comercial. E a flag
// não protege: `useV2SellsCreate` tem fallback `true` desde 2026-05-27. Então o
// redesenho nasce em arquivo NOVO + rota NOVA, sem tocar em nada que a tela viva
// consome. Racional: memory/requisitos/Sells/RUNBOOK-create-v3.md
//
// COMO O HANDOFF FOI TRADUZIDO (ele manda RECRIAR, não copiar — §1)
// O protótipo roda no bundle standalone do DS, com `style` inline sobre
// `--accent`/`--surface`/`--text-dim`. Nenhum desses token existe aqui: a camada
// deste projeto é `--color-*` (Tailwind 4), consumida por utilitário. O mapa
// medido está no cabeçalho de `_components/v3/primitivos.tsx`.
//
// O QUE ESTA TELA NÃO FAZ, E É DELIBERADO
// - não GRAVA: sem store(), sem POST, sem migration. O botão do finalizador
//   simula a transição em memória.
// - não é autoridade de VALOR: ela calcula para dar retorno imediato (é o que o
//   handoff §9 descreve), mas quem manda é o servidor. O parse pt-BR e o
//   arredondamento a 2 casas vêm literais do handoff — são o guard do incidente
//   `num_uf` de 2026-06-05 (final_total inflado ~×100.000 em 16 vendas do biz=4).
//
// FORA DESTE PASSO (cada um é arquivo próprio no handoff, e PR próprio aqui):
// lançamento do item · modal de colunas (31) · drawer de detalhe (7 abas,
// tributação, DIFAL) · parcelas · modelo de comissão. Os gatilhos existem na
// tela e dizem que ainda não abrem — botão que promete e não entrega é pior
// que botão ausente.
//
// Âncora de design: design_handoff_cadastro_venda/design/sells-create.jsx

import AppShellV2 from '@/Layouts/AppShellV2';
import { useMemo, useState, type ReactNode } from 'react';
import { Grid, Inline, Stack } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { cn } from '@/Lib/utils';
import {
  LancarItem,
  type Executante,
  type ItemLancado,
  type ProdutoCatalogo,
} from './_components/v3/LancarItem';
import EntregaFrete from './_components/v3/EntregaFrete';
import ComissaoDrawer from './_components/v3/ComissaoDrawer';
import ColunasModal from './_components/v3/ColunasModal';
import ItemDetalhe from './_components/v3/ItemDetalhe';
import ParcelasDrawer from './_components/v3/ParcelasDrawer';
import { type Parcela } from './_components/v3/parcelas-dominio';
import { type Beneficiario, type Gatilho } from './_components/v3/comissao-dominio';
import { carregarColunas, salvarColunas } from './_components/v3/colunas-dominio';
import { type Transportadora } from './_components/v3/entrega-dominio';
import { brl, fmtBR, num, parseBR, submitSafe } from './_components/v3/numeros';
import {
  Aviso,
  Chip,
  FaixaSaldo,
  Lbl,
  MoneyInput,
  Passo,
  Pill,
  Plate,
  Res,
  Sec,
} from './_components/v3/primitivos';

/* ─── contratos de cena ─────────────────────────────────────────────────── */

type Cliente = {
  cod: string;
  nome: string;
  padrao: boolean;
  doc: string;
  ie: string;
  contrib: 'sim' | 'isento' | 'nao';
  regime: string;
  fone: string;
  email: string;
  emailNfe: string;
  contato: string;
  endereco: string;
  cidade: string;
  uf: string;
  grupo: string;
  prazo: string;
  tabela: string | null;
};

type Item = {
  k: number;
  sku: string;
  nome: string;
  un: string;
  medidas: string | null;
  qtd: string;
  preco: string;
  desc: string;
  acr: string;
  estoque: number | null;
};

/* O contrato do catálogo mora em `_components/v3/LancarItem.tsx` — quem consome
   o produto por inteiro é o lançamento, não esta Page. */
type Produto = ProdutoCatalogo;

type Estagio = {
  key: string;
  label: string;
  acao: string | null;
  role: string | null;
  efeitos: string[];
};

/**
 * Estágio de fallback pra cena SEM pipeline. Não é adorno de tipagem: sem ele,
 * um `fsm: []` derrubaria a tela inteira num `undefined.role`. Com ele a venda
 * ainda abre, sem ação e sem papel — que é o comportamento fail-secure certo
 * (nada a executar > executar sem saber o quê).
 */
const SEM_PIPELINE: Estagio = {
  key: 'sem_pipeline',
  label: 'Sem pipeline',
  acao: null,
  role: null,
  efeitos: [],
};

type Props = {
  businessId: number;
  cena: {
    cliente: Cliente;
    itens: Item[];
    catalogo: Produto[];
    tabelas: string[];
    fsm: Estagio[];
    papeisDoUsuario: string[];
    executantes: Executante[];
    permissoes: { editarPrecoItem: boolean };
    /* onda 2 · CU-SELL-11 — opcional porque a cena é servida por um controller que
       pode estar numa versão anterior; o componente trata `[]` sem quebrar. */
    transportadoras?: Transportadora[];
  };
};

const METODOS_RAPIDOS = ['Dinheiro', 'PIX', 'Cartão de crédito', 'Boleto'];

/**
 * Sombra que separa a coluna fixa de Ações do conteúdo que rola por baixo dela.
 *
 * `color-mix` sobre `var(--color-foreground)` em vez de `rgba(0,0,0,…)`: cor crua
 * não acompanha o tema (no escuro, preto sobre escuro não sombreia nada) e é
 * exatamente o que o R1 do `ui:lint` barra. É o mesmo padrão que o `Sec` já usa
 * para o `hue` — o DS manda cor por token ou color-mix SOBRE token, nunca hex.
 */
const SOMBRA_COLUNA_FIXA = '-4px 0 6px -4px color-mix(in oklch, var(--color-foreground) 18%, transparent)';

/** Total da linha — mesma fórmula do handoff, com o parse pt-BR em cada fator. */
function linhaTotal(l: Item): number {
  return submitSafe(
    parseBR(l.qtd) * parseBR(l.preco) * (1 - parseBR(l.desc) / 100) * (1 + parseBR(l.acr || '0') / 100),
  );
}

/** Gatilho de recurso que ainda não existe — diz o que falta em vez de fingir. */
function AindaNao({ children, o_que }: { children: ReactNode; o_que: string }) {
  return (
    <button
      type="button"
      title={`${o_que} — não faz parte deste passo do porte`}
      aria-disabled
      onClick={(e) => e.preventDefault()}
      className="cursor-not-allowed text-[11.5px] font-semibold leading-none text-muted-foreground underline decoration-dotted underline-offset-2"
    >
      {children}
    </button>
  );
}

export default function SellsCreateV3({ cena }: Props) {
  const { cliente: cli, catalogo, tabelas, fsm, papeisDoUsuario, executantes, permissoes } = cena;

  const [itens, setItens] = useState<Item[]>(cena.itens);
  const [busca, setBusca] = useState('');
  const [lancando, setLancando] = useState<ProdutoCatalogo | null>(null);
  const [destAberto, setDestAberto] = useState(false);
  const [entregaAberta, setEntregaAberta] = useState(false);
  const [obsAberta, setObsAberta] = useState(false);
  const [tabela, setTabela] = useState<string | null>(null); // null = herda do cadastro
  const [descTipo, setDescTipo] = useState<'percentual' | 'fixo'>('percentual');
  const [descVal, setDescVal] = useState('0,00');
  const [acr, setAcr] = useState('0,00');
  const [frete, setFrete] = useState('0,00');
  const [pags, setPags] = useState<{ k: number; m: string; v: string }[]>([]);
  /* onda 3 — parcelas. Ficam em state da Page (e nao do drawer) porque o
     fechamento a direita precisa mostrar o plano de pagamento junto do total. */
  const [parcelas, setParcelas] = useState<Parcela[]>([]);
  const [parcelasAberto, setParcelasAberto] = useState(false);
  /* onda 4 — drawer de detalhe do item. Guarda o INDICE (nao a linha) pra
     navegacao Anterior/Proximo continuar valida se a lista mudar. */
  const [itemAberto, setItemAberto] = useState<number | null>(null);
  /* onda 5 — comissao. Beneficiarios e gatilho vivem na Page porque o resumo
     do fechamento os mostra junto do total. */
  const [beneficiarios, setBeneficiarios] = useState<Beneficiario[]>([]);
  const [gatilhoComissao, setGatilhoComissao] = useState<Gatilho>('recebimento');
  const [comissaoAberta, setComissaoAberta] = useState(false);
  /* onda 6 — colunas do grid. Inicializa do localStorage via lazy initializer:
     ler storage no corpo do componente rodaria a cada render. */
  const [colunas, setColunas] = useState<string[]>(() => carregarColunas());
  const [colunasAberto, setColunasAberto] = useState(false);
  const [estagio, setEstagio] = useState('rascunho');
  const [historico, setHistorico] = useState<{ acao: string; de: string; para: string }[]>([]);
  const [situacaoAberta, setSituacaoAberta] = useState(false);
  const [undo, setUndo] = useState<{ msg: string; desfazer: () => void } | null>(null);

  /* ─── derivados (nunca em estado — handoff §13) ────────────────────────── */
  const tabelaCadastro = cli.tabela ?? tabelas[0];
  const tabelaAtiva = tabela ?? tabelaCadastro;
  const tabelaTrocada = !!tabela && tabela !== tabelaCadastro;

  const subtotal = useMemo(() => submitSafe(itens.reduce((s, l) => s + linhaTotal(l), 0)), [itens]);
  const descAplicado =
    descTipo === 'percentual' ? submitSafe((subtotal * parseBR(descVal)) / 100) : submitSafe(parseBR(descVal));
  const baseTrib = submitSafe(subtotal - descAplicado);
  const vImposto = submitSafe(baseTrib * 0.18);
  const vFrete = submitSafe(parseBR(frete));
  const vAcr = submitSafe(parseBR(acr));
  const total = submitSafe(baseTrib + vImposto + vFrete + vAcr);
  const pago = submitSafe(pags.reduce((s, p) => s + parseBR(p.v), 0));
  const saldo = submitSafe(total - pago);
  const payStatus = pago <= 0 ? 'due' : saldo > 0.005 ? 'partial' : 'paid';
  const alcada = descTipo === 'percentual' ? parseBR(descVal) > 10 : descAplicado > submitSafe(subtotal * 0.1);

  const invalidas = itens.filter((l) => parseBR(l.qtd) <= 0 || parseBR(l.preco) <= 0);
  const temInvalida = invalidas.length > 0;

  const atual: Estagio = fsm.find((f) => f.key === estagio) ?? fsm[0] ?? SEM_PIPELINE;
  const idxAtual = fsm.findIndex((f) => f.key === estagio);
  const prox = idxAtual >= 0 && idxAtual < fsm.length - 1 ? fsm[idxAtual + 1] : null;
  /* Fail-secure: papel ausente NEGA, e a tela diz qual falta (handoff §6 regra 2). */
  const temPapel = !atual.role || papeisDoUsuario.includes(atual.role);
  const travada = ['producao', 'faturada', 'entregue'].includes(estagio);
  const cancelada = estagio === 'cancelada';
  const podeExecutar = temPapel && !!prox && !cancelada;

  const achados = busca
    ? catalogo.filter((p) => (p.nome + p.sku).toLowerCase().includes(busca.toLowerCase()))
    : [];

  const setLinha = (k: number, campo: keyof Item, v: string) =>
    setItens((s) => s.map((l) => (l.k === k ? { ...l, [campo]: v } : l)));

  const removerItem = (l: Item) => {
    const pos = itens.indexOf(l);
    setItens((s) => s.filter((x) => x.k !== l.k));
    setUndo({
      msg: `Item removido — ${l.nome}`,
      desfazer: () =>
        setItens((s) => {
          const c = [...s];
          c.splice(pos, 0, l);
          return c;
        }),
    });
  };

  /* O lançamento é o passo entre ESCOLHER e ENTRAR na venda (handoff, onda 1).
     A busca não adiciona direto porque em item dimensional a quantidade é
     DERIVADA das medidas — deixar o operador calcular a área de cabeça é onde
     nasce quantidade errada, e quantidade errada é estoque e valor errados. */
  const adicionarLancado = (item: ItemLancado) => {
    const k = Date.now();
    setItens((s) => [
      ...s,
      {
        k,
        sku: item.sku,
        nome: item.nome,
        un: item.un,
        medidas: item.medidas,
        qtd: item.qtd,
        preco: item.preco,
        desc: item.desc,
        acr: item.acr,
        estoque: item.estoque,
      },
    ]);
    setLancando(null);
    setBusca('');
    setUndo({
      msg: `Item adicionado — ${item.nome}`,
      desfazer: () => setItens((s) => s.filter((x) => x.k !== k)),
    });
  };

  /* Espelha `ExecuteStageActionService::execute` — nunca UPDATE direto no
     estágio (ADR 0143 · trait GuardsFsmTransitions). Aqui é memória, mas a
     FORMA é a do backend de propósito: transição só por ação nomeada. */
  const executarAcao = () => {
    if (!podeExecutar || !prox) return;
    setHistorico((h) => [...h, { acao: atual.acao ?? '—', de: atual.key, para: prox.key }]);
    setEstagio(prox.key);
  };

  /* ─── COLUNA DE TRABALHO ───────────────────────────────────────────────── */
  const esquerda = (
    <Stack gap={3} className="min-w-0">
      {cancelada ? (
        <Aviso tom="destructive" titulo="Venda cancelada">
          O cancelamento rodaria <code>LiberarReserva</code> — o estoque desta venda voltaria ao saldo. Venda
          cancelada não recebe alteração nem ação de fluxo: para retomar, <b>duplique</b>; para acertar valor
          faturado, lance uma <b>devolução</b>.
        </Aviso>
      ) : (
        travada && (
          <Aviso tom="warning" titulo={`Venda ${atual.label.toLowerCase()} — itens e valores travados`}>
            De <b>Em produção</b> em diante o estoque já está comprometido: mudar quantidade ou preço aqui
            adulteraria venda em curso. O caminho é <b>Reabrir para correção</b>, que entra no histórico.
          </Aviso>
        )
      )}

      {/* ─── Passo 1 · Cliente ───────────────────────────────────────────── */}
      <Sec
        title={
          <>
            <Passo n={1} />
            Cliente
          </>
        }
        hue="primary"
        pad={12}
        right={<AindaNao o_que="Consulta de clientes (modal 880px)">Consultar cadastro… F2</AindaNao>}
      >
        <Inline gap={3} align="end" className="flex-wrap">
          <div className="w-[104px] flex-none">
            <Label htmlFor="v3-cod" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
              Código
            </Label>
            {/* `w-full` explícito: o <Input> do DS tem largura natural ~157px e
                estourava 53px sobre o campo vizinho dentro deste wrapper de
                104px (medido no harness a 1280 — a largura da Larissa). */}
            <Input id="v3-cod" readOnly value={cli.cod} className="w-full font-mono" />
          </div>
          <div className="min-w-[240px] flex-1">
            <Label htmlFor="v3-cli" className="mb-1 block text-[10.5px] font-semibold uppercase tracking-[.04em] text-muted-foreground">
              Cliente / destinatário
            </Label>
            <Input id="v3-cli" readOnly value={cli.nome} />
          </div>
        </Inline>

        <button
          type="button"
          onClick={() => setDestAberto(!destAberto)}
          aria-expanded={destAberto}
          className="mt-2 inline-flex cursor-pointer items-center gap-2 py-1 text-[11.5px] font-semibold leading-none text-primary"
        >
          <span aria-hidden className={cn('inline-flex transition-transform', destAberto && 'rotate-180')}>
            ▾
          </span>
          Detalhes do destinatário
          {!destAberto && (
            <span className="font-normal text-muted-foreground">
              {cli.doc} · {cli.cidade}/{cli.uf}
            </span>
          )}
        </button>

        {destAberto && (
          <Grid gap={3} className="mt-3 sm:grid-cols-2 lg:grid-cols-4">
            {[
              ['CNPJ / CPF', cli.doc],
              ['Inscrição estadual', cli.ie],
              ['Regime tributário', cli.regime],
              ['Situação de ICMS', cli.contrib === 'sim' ? 'Contribuinte' : cli.contrib === 'isento' ? 'Isento' : 'Não contribuinte'],
              ['Telefone', cli.fone],
              ['E-mail', cli.email],
              ['E-mail da NF-e', cli.emailNfe],
              ['Pessoa de contato', cli.contato],
              ['Endereço', cli.endereco],
              ['Cidade / UF', `${cli.cidade}/${cli.uf}`],
              ['Grupo de preço', cli.grupo],
              ['Prazo de pagamento', cli.prazo],
            ].map(([rotulo, valor]) => (
              <div key={rotulo}>
                <Lbl>{rotulo}</Lbl>
                <span className="block truncate text-[12.5px] leading-[1.4] text-foreground" title={valor}>
                  {valor || '—'}
                </span>
              </div>
            ))}
          </Grid>
        )}

        <p className="mt-3 text-[11.5px] leading-[1.45] text-muted-foreground">
          {cli.padrao
            ? 'Venda nova começa em Consumidor final — é o padrão do balcão.'
            : `Grupo de preço, prazo e endereço de entrega vêm do cadastro de ${cli.nome}.`}
        </p>
      </Sec>

      {/* ─── Passo 2 · Itens ─────────────────────────────────────────────── */}
      <Sec
        title={
          <>
            <Passo n={2} />
            Itens
          </>
        }
        hue="primary"
        pad={0}
        right={
          <Inline gap={2} align="center">
            <Pill tom="neutro" mono>
              {itens.length === 1 ? '1 item' : `${itens.length} itens`}
            </Pill>
            <button
              type="button"
              onClick={() => setColunasAberto(true)}
              className="cursor-pointer text-[11.5px] font-semibold leading-none text-muted-foreground underline decoration-dotted underline-offset-2 hover:text-foreground"
            >
              {`Colunas (${colunas.length})…`}
            </button>
          </Inline>
        }
      >
        {!travada && !cancelada && (
          <div className="relative border-b border-border bg-muted/40 p-3">
            <Input
              value={busca}
              onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar produto por nome, SKU, lote ou código de barras…"
            />
            {achados.length > 0 && (
              <div className="absolute inset-x-3 z-10 mt-1 overflow-hidden rounded-lg border border-border bg-popover shadow-lg">
                {achados.map((p) => (
                  <button
                    key={p.sku}
                    type="button"
                    onClick={() => setLancando(p)}
                    className="inline-flex w-full cursor-pointer items-center gap-3 border-b border-border/60 px-3 py-2 text-left text-[13.5px] leading-[1.3] last:border-b-0 hover:bg-muted"
                  >
                    <b className="font-semibold">{p.nome}</b>
                    <span className="font-mono text-[11.5px] leading-none text-muted-foreground">{p.sku}</span>
                    <span className="ml-auto font-mono text-[12.5px] font-semibold leading-none">
                      {brl(p.preco)}/{p.un}
                    </span>
                    {p.estoque !== null ? (
                      <Pill tom={p.estoque > 50 ? 'success' : 'warning'} mono>
                        {num(p.estoque, 1)} {p.un}
                      </Pill>
                    ) : (
                      <Pill mono>serviço</Pill>
                    )}
                  </button>
                ))}
                <p className="border-t border-border bg-muted px-3 py-2 text-[11.5px] leading-[1.4] text-muted-foreground">
                  Escolher <b>não adiciona direto</b> — abre o lançamento, onde a quantidade de item dimensional vem
                  das medidas e o preço passa pelo piso da tabela.
                </p>
              </div>
            )}
          </div>
        )}

        <div className="overflow-x-auto">
          <table className="w-full min-w-[560px] border-separate border-spacing-0 text-[13.5px] leading-[1.4]">
            <thead>
              <tr>
                {['Produto / serviço', 'Quant.', 'R$ valor', 'Desc. %', 'Acrésc. %', 'R$ total'].map((h, i) => (
                  <th
                    key={h}
                    className={cn(
                      'whitespace-nowrap border-b border-border bg-card px-3 py-2 text-[10.5px] font-semibold uppercase leading-none tracking-[.05em] text-muted-foreground',
                      i === 0 ? 'text-left' : 'text-right',
                    )}
                  >
                    {h}
                  </th>
                ))}
                {/* A sombra à esquerda não é adorno: é o que avisa que há coluna
                    rolando POR BAIXO da coluna fixa. Vai em `style` com color-mix
                    sobre token (nunca `rgba()` cru — R1 do ui:lint, e cor crua não
                    acompanha o tema escuro). */}
                <th
                  style={{ boxShadow: SOMBRA_COLUNA_FIXA }}
                  className="sticky right-0 whitespace-nowrap border-b border-border bg-card px-3 py-2 text-center text-[10.5px] font-semibold uppercase leading-none tracking-[.05em] text-muted-foreground"
                >
                  Ações
                </th>
              </tr>
            </thead>
            <tbody>
              {itens.map((l) => {
                const ruim = parseBR(l.qtd) <= 0 || parseBR(l.preco) <= 0;
                return (
                  <tr key={l.k} className={cn(ruim && 'bg-destructive-soft/40')}>
                    <td className="border-b border-border/60 px-3 py-2">
                      <b className="font-semibold">{l.nome}</b>
                      <span className="block font-mono text-[11.5px] text-muted-foreground">
                        {l.sku} · {l.un}
                        {l.medidas ? ` · ${l.medidas}` : ''}
                      </span>
                    </td>
                    {(['qtd', 'preco', 'desc', 'acr'] as const).map((campo) => (
                      <td key={campo} className="border-b border-border/60 px-2 py-1">
                        <input
                          value={l[campo] ?? '0'}
                          readOnly={travada || cancelada}
                          inputMode="decimal"
                          aria-label={`${campo} — ${l.nome}`}
                          onChange={(e) => setLinha(l.k, campo, e.target.value)}
                          className={cn(
                            // Mesma caixa do resto da tela (34,19px derivados). No protótipo
                            // a célula editável da tabela é `cellNum`, que também usa `.dsfa`
                            // — a tabela NÃO tem campo menor; o que a compacta é o padding do
                            // `<td>` (`4px 8px` lá, `px-2 py-1` aqui — já idênticos).
                            'w-full rounded-md border border-input bg-background px-[10px] py-[7px] text-right font-mono text-[13px] leading-[1.4] tabular-nums outline-none focus:border-primary',
                            (travada || cancelada) && 'cursor-default bg-muted',
                          )}
                        />
                      </td>
                    ))}
                    <td className="border-b border-border/60 px-3 py-2 text-right font-mono font-semibold tabular-nums">
                      {fmtBR(linhaTotal(l))}
                    </td>
                    <td
                      style={{ boxShadow: SOMBRA_COLUNA_FIXA }}
                      className="sticky right-0 whitespace-nowrap border-b border-border/60 bg-card px-2 py-2 text-center"
                    >
                      <Inline gap={1} align="center" justify="center">
                        <button
                          type="button"
                          onClick={() => setItemAberto(itens.findIndex((x) => x.k === l.k))}
                          className="cursor-pointer text-[11.5px] font-semibold leading-none text-muted-foreground underline decoration-dotted underline-offset-2 hover:text-foreground"
                        >
                          Impostos
                        </button>
                        {!travada && !cancelada && (
                          <button
                            type="button"
                            title="Remover item"
                            onClick={() => removerItem(l)}
                            className="inline-flex size-7 cursor-pointer items-center justify-center rounded-md border border-border bg-card text-muted-foreground"
                          >
                            ×
                          </button>
                        )}
                      </Inline>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {temInvalida && (
          <div className="p-3">
            <Aviso
              tom="destructive"
              titulo={invalidas.length === 1 ? 'Um item está com valor inválido' : `${invalidas.length} itens com valor inválido`}
            >
              {invalidas
                .map((l) => `${l.nome}${parseBR(l.qtd) <= 0 ? ' — quantidade precisa ser maior que zero' : ' — preço unitário precisa ser maior que zero'}`)
                .join(' · ')}
            </Aviso>
          </div>
        )}

        {!itens.length && (
          <div className="px-3 py-6 text-center">
            <b className="block text-[13.5px] font-semibold">Nenhum item na venda</b>
            <span className="mt-1 block text-[12.5px] leading-[1.45] text-muted-foreground">
              Busque o produto no campo acima — o preço vem do grupo do cliente e você ajusta na linha.
            </span>
          </div>
        )}
      </Sec>

      {/* ─── Passo 3 · Entrega e frete (gaveta) ──────────────────────────── */}
      <Sec
        title={
          <>
            <Passo n={3} />
            Entrega e frete
          </>
        }
        hue="warning"
        pad={14}
        dobra
        aberta={entregaAberta}
        onToggle={() => setEntregaAberta(!entregaAberta)}
        resumo="retirada no balcão · endereço do cadastro"
      >
        <EntregaFrete
          itens={itens}
          clienteNome={cli.nome}
          enderecoDoCadastro={`${cli.endereco} · ${cli.cidade}/${cli.uf}`}
          frete={frete}
          onFreteChange={setFrete}
          transportadoras={cena.transportadoras ?? []}
        />
      </Sec>

      {/* ─── Passo 4 · Observações e produção (gaveta) ───────────────────── */}
      <Sec
        title={
          <>
            <Passo n={4} />
            Observações e produção
          </>
        }
        hue="neutro"
        pad={14}
        dobra
        aberta={obsAberta}
        onToggle={() => setObsAberta(!obsAberta)}
        resumo="notas · prazo · OS"
      >
        <Grid gap={3} className="sm:grid-cols-2">
          <div>
            <Lbl>Nota da venda (sai no documento)</Lbl>
            <textarea
              rows={3}
              placeholder="Instalação inclusa"
              className="w-full rounded-md border border-input bg-background p-2 text-[13px] outline-none focus:border-primary"
            />
          </div>
          <div>
            <Lbl>Nota interna do balcão</Lbl>
            <textarea
              rows={3}
              placeholder="Cliente pediu retorno por WhatsApp"
              className="w-full rounded-md border border-input bg-background p-2 text-[13px] outline-none focus:border-primary"
            />
          </div>
        </Grid>
        <div className="mt-3">
          <Lbl>Prazo de pagamento</Lbl>
          <span className="block text-[12.5px] leading-[1.4]">{cli.prazo}</span>
        </div>
      </Sec>
    </Stack>
  );

  /* ─── COLUNA DE FECHAMENTO ─────────────────────────────────────────────── */
  const direita = (
    <Stack gap={3} className="min-w-0 self-stretch" asChild>
      <aside>
        {/* Tabela de preço */}
        <div
          className={cn(
            'overflow-hidden rounded-lg border bg-card shadow-sm',
            tabelaTrocada ? 'border-warning/35' : 'border-border',
          )}
        >
          <Inline
            gap={2}
            align="center"
            className={cn('border-b border-border px-3 py-2', tabelaTrocada ? 'bg-warning-soft' : 'bg-muted')}
          >
            <Lbl className={cn('mb-0', tabelaTrocada && 'text-warning-fg')}>Tabela de preço</Lbl>
            <span className="ml-auto">
              {cli.tabela ? <Pill tom="primary">do cadastro</Pill> : <Pill>padrão do balcão</Pill>}
            </span>
          </Inline>
          <div className="p-3">
            <b className="mb-2 block text-[12.5px] font-semibold leading-[1.4]">{tabelaAtiva}</b>
            <Lbl>Tabela aplicada nesta venda</Lbl>
            {/*
             * SafeSelectItem, não SelectItem: as opções vêm de DADO. Item com
             * value vazio derruba o render INTEIRO do Radix — tela branca, não
             * degradação (proibicoes §5 2026-06-29 · PRs 3405 e 3411).
             *
             * O número do PR vai SEM `#` de propósito: o R1 do `ui:lint` casa
             * `#` + 3-8 dígitos hex, e `#3405` é hex válido. Ele só pula linha
             * que COMEÇA com `//`, `*` ou `/*` — daí o bloco estrelado aqui.
             */}
            <Select value={tabelaAtiva} onValueChange={setTabela}>
              <SelectTrigger className="h-9 w-full text-[13px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {tabelas.map((t) => (
                  <SafeSelectItem key={t} value={t}>
                    {t}
                  </SafeSelectItem>
                ))}
              </SelectContent>
            </Select>
            {tabelaTrocada ? (
              <Inline gap={2} align="center" className="mt-2 flex-wrap">
                <span className="text-[11.5px] leading-[1.35] text-warning-fg">
                  Trocada nesta venda — o cadastro indica <b>{tabelaCadastro}</b>.
                </span>
                <button
                  type="button"
                  onClick={() => setTabela(null)}
                  className="flex-none cursor-pointer text-[11.5px] font-semibold leading-none text-primary"
                >
                  Voltar
                </button>
              </Inline>
            ) : (
              <span className="mt-2 block text-[11.5px] leading-[1.35] text-muted-foreground">
                {cli.tabela
                  ? `Veio do cadastro de ${cli.nome}.`
                  : 'Este cliente não tem tabela indicada; vale o preço padrão do balcão.'}
              </span>
            )}
          </div>
        </div>

        {/* Fechamento — o bloco de peso visual */}
        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
          <Inline
            gap={2}
            align="center"
            className="border-b border-border px-4 py-3"
            style={{ background: 'color-mix(in oklch, var(--color-success) 5%, var(--color-card))' }}
          >
            <h3 className="m-0 text-[15px] font-semibold leading-[1.3]">Fechamento</h3>
            <span className="ml-auto">
              <Pill tom={payStatus === 'paid' ? 'success' : payStatus === 'partial' ? 'warning' : 'destructive'}>
                {payStatus === 'paid' ? 'Pago' : payStatus === 'partial' ? 'Parcial' : 'A receber'}
              </Pill>
            </span>
          </Inline>
          <div className="p-4">
            <Plate label="Total da venda" valor={brl(total)} />
            <div className="mt-3">
              <Res l="Subtotal" v={fmtBR(subtotal)} />
              <Res
                l="Desconto"
                v={descAplicado ? `− ${fmtBR(descAplicado)}` : fmtBR(0)}
                tom={descAplicado ? 'destructive' : undefined}
              />
              <Res l="Imposto" v={fmtBR(vImposto)} />
              <Res l="Acréscimo" v={vAcr ? `+ ${fmtBR(vAcr)}` : fmtBR(0)} tom={vAcr ? 'warning' : undefined} />
              <Res l="Frete" v={fmtBR(vFrete)} />
            </div>

            <Stack gap={3} className="mt-3 border-t border-border pt-3">
              <Inline gap={3} align="end">
                <div className="w-[124px] flex-none">
                  <Lbl>Tipo de desconto</Lbl>
                  <Select
                    value={descTipo}
                    onValueChange={(v) => setDescTipo(v as 'percentual' | 'fixo')}
                  >
                    <SelectTrigger className="h-9 w-full text-[13px]">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="percentual">Percentual %</SelectItem>
                      <SelectItem value="fixo">Valor R$</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="min-w-0 flex-1">
                  <MoneyInput
                    label="Desconto do pedido"
                    aria={descTipo === 'percentual' ? 'Desconto do pedido em percentual' : 'Desconto do pedido em reais'}
                    value={descVal}
                    onChange={setDescVal}
                    prefix={descTipo === 'percentual' ? '%' : 'R$'}
                  />
                </div>
              </Inline>
              <Grid gap={3} className="grid-cols-2">
                <MoneyInput label="Acréscimo" value={acr} onChange={setAcr} />
                <MoneyInput label="Frete" value={frete} onChange={setFrete} />
              </Grid>
            </Stack>

            {alcada && (
              <div className="mt-2">
                <Aviso tom="warning" titulo="Acima da alçada de 10%">
                  Precisa de liberação de supervisor para finalizar.
                </Aviso>
              </div>
            )}
          </div>
        </div>

        {/* Pagamento */}
        <div className="rounded-lg border border-border bg-card p-4 shadow-sm">
          <Lbl>Pagamento</Lbl>
          <Inline gap={2} align="center" wrap className="mb-3">
            {METODOS_RAPIDOS.map((m) => (
              <Chip
                key={m}
                onClick={() => setPags((s) => [...s, { k: Date.now(), m, v: fmtBR(Math.max(saldo, 0)) }])}
              >
                + {m}
              </Chip>
            ))}
            <Chip destaque onClick={() => setParcelasAberto(true)}>
              {parcelas.length > 0 ? `Parcelas (${parcelas.length})…` : "Parcelar…"}
            </Chip>
          </Inline>

          {pags.length > 0 && (
            <Stack gap={2} className="mb-3">
              {pags.map((p) => (
                <Inline key={p.k} gap={2} align="center">
                  <span className="min-w-0 flex-1 truncate text-[12.5px]">{p.m}</span>
                  <div className="w-[106px] flex-none">
                    <MoneyInput
                      aria={`Valor recebido em ${p.m}`}
                      value={p.v}
                      onChange={(v) => setPags((s) => s.map((x) => (x.k === p.k ? { ...x, v } : x)))}
                    />
                  </div>
                  <button
                    type="button"
                    title="Remover pagamento"
                    onClick={() => setPags((s) => s.filter((x) => x.k !== p.k))}
                    className="inline-flex size-[26px] flex-none cursor-pointer items-center justify-center rounded-md border border-border bg-card text-muted-foreground"
                  >
                    ×
                  </button>
                </Inline>
              ))}
            </Stack>
          )}

          <FaixaSaldo saldo={saldo} />
          <p className="mt-2.5 text-[11.5px] leading-[1.5] text-muted-foreground">
            Fechar sem pagamento é caminho normal do balcão: grava <code>payment_status=due</code> e não bloqueia.
          </p>
        </div>

        {/* Comissão — resumo */}
        <div className="rounded-lg border border-border bg-card p-4 shadow-sm">
          <Inline gap={2} align="center" className="mb-2">
            <Lbl className="mb-0">Comissão</Lbl>
            <span className="ml-auto">
              <button
                type="button"
                onClick={() => setComissaoAberta(true)}
                className="cursor-pointer text-[11.5px] font-semibold leading-none text-muted-foreground underline decoration-dotted underline-offset-2 hover:text-foreground"
              >
                {beneficiarios.length > 0 ? `Comissão (${beneficiarios.length})…` : 'Configurar…'}
              </button>
            </span>
          </Inline>
          <Res l="Base líquida" v={fmtBR(baseTrib)} />
          <p className="mt-2 text-[11.5px] leading-[1.45] text-muted-foreground">
            Vários beneficiários por venda, base declarada por beneficiário e gatilho de direito (emissão,
            faturamento ou a cada parcela recebida) fazem parte do porte da comissão — próximo passo.
          </p>
        </div>

        {/* Situação — FSM */}
        <div className="overflow-hidden rounded-lg border border-border bg-card shadow-sm">
          <button
            type="button"
            onClick={() => setSituacaoAberta(!situacaoAberta)}
            aria-expanded={situacaoAberta}
            className="inline-flex w-full cursor-pointer items-center gap-2 border-b border-border px-4 py-3 text-left"
          >
            <Lbl className="mb-0">Situação</Lbl>
            <span className="ml-auto inline-flex items-center gap-2">
              <Pill tom={cancelada ? 'destructive' : 'info'}>{atual.label}</Pill>
              {!cancelada && (
                <span className="font-mono text-[11.5px] text-muted-foreground">
                  {idxAtual + 1}/{fsm.length - 1}
                </span>
              )}
            </span>
          </button>
          {situacaoAberta && (
            <Stack gap={0} className="p-4">
              {fsm
                .filter((f) => f.key !== 'cancelada')
                .map((f, i) => {
                  const cumprida = i < idxAtual;
                  const agora = f.key === estagio;
                  return (
                    <Inline key={f.key} gap={3} align="center" className="py-1.5">
                      <span
                        aria-hidden
                        className={cn(
                          'inline-flex size-2 flex-none rounded-full',
                          cumprida ? 'bg-success' : agora ? 'bg-primary' : 'bg-border',
                        )}
                      />
                      <span
                        className={cn(
                          'text-[12.5px] leading-[1.35]',
                          agora ? 'font-semibold text-foreground' : 'text-muted-foreground',
                        )}
                      >
                        {f.label}
                      </span>
                      {cumprida && <span className="ml-auto text-[11.5px] text-success-fg">✓</span>}
                    </Inline>
                  );
                })}
              {historico.length > 0 && (
                <div className="mt-3 border-t border-border pt-3">
                  <Lbl>Histórico (append-only)</Lbl>
                  {historico.map((h, i) => (
                    <span key={i} className="block font-mono text-[11.5px] leading-[1.5] text-muted-foreground">
                      {h.acao}: {h.de} → {h.para}
                    </span>
                  ))}
                </div>
              )}
            </Stack>
          )}
        </div>

        {/* Finalizador — sempre visível */}
        <div className="sticky bottom-3 z-30 rounded-lg border border-primary/25 bg-card p-3 shadow-lg">
          <Stack gap={2}>
            <Inline gap={2} align="baseline" wrap>
              <Lbl className="mb-0">Total da venda</Lbl>
              <b className="font-mono text-[20px] font-semibold leading-none tabular-nums">{brl(total)}</b>
              <span className="ml-auto inline-flex items-center gap-1.5">
                {itens.length > 0 && (
                  <span className="text-[11.5px] leading-[1.3] text-muted-foreground">
                    {itens.length === 1 ? '1 item' : `${itens.length} itens`}
                  </span>
                )}
              </span>
            </Inline>

            {saldo > 0.005 && (
              <Inline gap={1} align="baseline" className="text-[11.5px] leading-[1.3] text-warning-fg">
                <span>Falta receber</span>
                <b className="font-mono">{brl(saldo)}</b>
              </Inline>
            )}

            <Button
              size="lg"
              className="w-full"
              disabled={!itens.length || !podeExecutar || temInvalida}
              onClick={executarAcao}
            >
              {atual.acao ?? 'Sem ação disponível'}
            </Button>

            {prox && prox.efeitos.length > 0 && (
              <Inline gap={1} align="center" justify="center" wrap>
                {prox.efeitos.map((e) => (
                  <Pill key={e} tom="warning" mono>
                    {e}
                  </Pill>
                ))}
              </Inline>
            )}

            {!podeExecutar && !cancelada && (
              <span className="block text-center text-[11.5px] leading-[1.35] text-muted-foreground">
                {prox ? `Exige o papel ${atual.role}` : 'Venda no fim do fluxo'}
              </span>
            )}
            {temInvalida && (
              <span className="block text-center text-[11.5px] leading-[1.35] text-destructive-fg">
                Há item com dado inválido — corrija antes de fechar.
              </span>
            )}
            <span className="block text-center text-[11.5px] leading-[1.3] text-muted-foreground">
              Preview de design — não grava.
            </span>
          </Stack>
        </div>
      </aside>
    </Stack>
  );

  // `venda-v3` é o wrapper de escopo da caixa de campo do protótipo
  // (resources/css/venda-v3.css). Mesmo padrão de `.fin-cowork`/`.sells-cowork`.
  // Os drawers renderizam em PORTAL, fora daqui — cada `<DialogContent>` repete
  // a classe, senão o campo do drawer fica 30px enquanto a página fica 34,19px.
  return (
    <Stack gap={4} className="venda-v3 mx-auto max-w-[1400px] p-4">
      {/* Marcador honesto: quem abrir por engano precisa saber em 1 segundo. */}
      <Inline gap={3} align="center" wrap className="rounded-lg border border-warning/30 bg-warning-soft px-4 py-3">
        <div className="min-w-0 text-sm">
          <b className="font-semibold">Preview de design — não é a tela de produção.</b>{' '}
          <span className="text-muted-foreground">
            Dados de cena, não do banco. Não salva e não move estoque. A venda real continua em{' '}
            <code className="rounded bg-muted px-1 py-0.5 text-xs">/pos/create</code>.
          </span>
        </div>
      </Inline>

      <Inline gap={3} align="center" wrap>
        {/* 22px/1.3 + tracking = o h1 do `PageHeader` do DS vivo (DesignSync
            019dd02f), que é o que o protótipo renderiza. `text-xl` dava 20px. */}
        <h1 className="text-[22px] font-semibold leading-[1.3] tracking-[-.015em]">Nova venda</h1>
        <Pill tom="primary" mono>
          V3
        </Pill>
        <p className="text-sm text-muted-foreground">Cliente, itens, pagamento. O resto tem valor padrão.</p>
      </Inline>

      <Grid gap={4} className="items-start lg:grid-cols-[minmax(0,1fr)_336px]">
        {esquerda}
        {direita}
      </Grid>

      <LancarItem
        produto={lancando}
        executantes={executantes}
        podeEditarPreco={permissoes.editarPrecoItem}
        onFechar={() => setLancando(null)}
        onConfirmar={adicionarLancado}
      />

      <ColunasModal
        aberto={colunasAberto}
        onFechar={() => setColunasAberto(false)}
        ativas={colunas}
        onAtivasChange={(c) => {
          setColunas(c);
          salvarColunas(c);
        }}
      />

      <ComissaoDrawer
        aberto={comissaoAberta}
        onFechar={() => setComissaoAberta(false)}
        totais={{ bruto: subtotal, liquido: baseTrib, margem: submitSafe(baseTrib * 0.3) }}
        beneficiarios={beneficiarios}
        onBeneficiariosChange={setBeneficiarios}
        gatilho={gatilhoComissao}
        onGatilhoChange={setGatilhoComissao}
        parcelas={parcelas}
        totalDaVenda={total}
        pessoas={executantes.map((e) => ({ id: e.id, nome: e.nome, papel: e.papel }))}
      />

      <ItemDetalhe
        linha={itemAberto !== null ? (itens[itemAberto] ?? null) : null}
        indice={itemAberto ?? 0}
        total={itens.length}
        onFechar={() => setItemAberto(null)}
        onNavegar={(delta) =>
          setItemAberto((i) => (i === null ? null : Math.max(0, Math.min(itens.length - 1, i + delta))))
        }
        abaInicial="tributacao"
      />

      <ParcelasDrawer
        aberto={parcelasAberto}
        onFechar={() => setParcelasAberto(false)}
        total={total}
        parcelas={parcelas}
        onParcelasChange={setParcelas}
        documentoBase="VD-2026"
      />

      {undo && (
        <div
          role="status"
          className="fixed bottom-5 left-1/2 z-[60] inline-flex -translate-x-1/2 items-center gap-4 rounded-lg bg-foreground px-4 py-3 text-[12.5px] leading-[1.3] text-background shadow-xl"
        >
          <span>{undo.msg}</span>
          <button
            type="button"
            onClick={() => {
              undo.desfazer();
              setUndo(null);
            }}
            className="cursor-pointer font-semibold text-background underline"
          >
            Desfazer
          </button>
        </div>
      )}
    </Stack>
  );
}

// Persistent Layout — mesma convenção de Sells/Create.tsx.
SellsCreateV3.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
