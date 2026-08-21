/**
 * Consulta de Produtos — índice do catálogo (`/products/unificado`).
 *
 * Origem: handoff "Consulta de Produtos" (2026-08-18), construído sob CATRACA DE REGRESSÃO
 * contra a Consulta de Clientes (`/contacts` · `Pages/Cliente/Index.tsx`), que é a golden
 * master do padrão de índice. A árvore é a mesma; só o domínio varia:
 *
 *   PageHeader → abas por TIPO → KPI-filtros → toolbar em UMA linha (filtros → contagem → busca)
 *   → cartão da tabela (altura fixa, rolagem interna, SEM rodapé) → drawer de detalhe
 *
 * As quatro regras da catraca (handoff §3):
 *   1. Sem CSS local de aparência — Tailwind + tokens do DS, como a golden master.
 *   2. A diferença é CONFIGURAÇÃO, não estilo — coluna que não aparece é coluna que não foi
 *      montada (`_components/Colunas.tsx`), nunca `display:none`.
 *   3. Sem paginação — o padrão de índice não tem rodapé; altura fixa + rolagem interna.
 *   4. Sem comportamento responsivo novo — o que existe é o do padrão.
 *
 * A regra que governa tudo: **custo e margem são AUTORIZAÇÃO, não preferência de layout.** O
 * vendedor não vê custo em superfície nenhuma — tabela, drawer ou contador de KPI. O servidor
 * nem emite a chave (`ProdutoUnificadoController`); a tela não a reintroduz.
 * Segunda invariante, do tenant: toda query escopada por `business_id` (ADR 0093).
 *
 * ⚠️ As 4 sub-telas anteriores (Categorias · Insumos·BOM · Tabelas de preço · Histórico de uso)
 * NÃO sumiram: saíram da barra de abas — que agora é do recorte por tipo — e passaram pro menu
 * de ações do cabeçalho. Mesmos gates, mesmo controller.
 */

import { Deferred, Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import {
  ArrowDown,
  ArrowDownUp,
  ArrowUp,
  ArrowUpDown,
  Check,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  ChevronsLeft,
  ChevronsRight,
  Download,
  History,
  Layers,
  MoreVertical,
  PackageSearch,
  Search,
  Tags,
  Upload,
  X,
} from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Inline } from '@/Components/layout';
import { Button } from '@/Components/ui/button';
import { usePageProps, useBusiness } from '@/Hooks/usePageProps';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { toast } from 'sonner';
import { ABAS_CATALOGO, brl, type AbaKey, type KpiKey, type Permissoes, type ProdutoRow } from './_components/catalogo';
import { celulasDe, colunasDe, larguraMinima, type ColunaKey } from './_components/Colunas';
import KpiFiltros, { type KpisCatalogo } from './_components/KpiFiltros';
import FiltroTrigger, { type OpcaoFiltro } from './_components/FiltroTrigger';
import DetalheProduto from './_components/DetalheProduto';
import {
  ListaCategorias,
  ListaHistorico,
  ListaInsumos,
  ListaTabelas,
  type CategoriaRow,
  type HistoricoRow,
  type InsumoRow,
  type TabelaRow,
} from './_components/SubTelas';

type SubTela = 'produtos' | 'categorias' | 'insumos' | 'tabelas' | 'historico';

type Filtros = {
  tela: SubTela;
  aba: AbaKey;
  busca: string;
  kpi: string;
  categoria: number | null;
  tipo: string;
  unidade: number | null;
  marca: number | null;
  estoque: string;
  margem: string;
  /** Coluna de ordenação. `''` = padrão do servidor (nome). Lista branca no controller. */
  ordem: string;
  dir: 'asc' | 'desc';
  pagina: number;
  porPagina: number;
};

type Props = {
  tela: SubTela;
  filters: Filtros;
  /** Piso de margem vigente — PARÂMETRO DE NEGÓCIO servido pelo backend. A tela não o redeclara. */
  pisoMargem: number;
  diasParado: number;
  /** Teto duro de `porPagina` no servidor. A tela não o consome — quem o aplica é o
   *  controller ao validar `porPagina` contra a lista de opções. Fica no contrato porque o
   *  teste de contrato exige a prop na resposta. */
  tetoLinhas: number;
  /** Opções do "Por página" do rodapé. Vem do servidor pra tela e backend não divergirem. */
  porPaginaOpcoes: number[];
  // Opcional no TIPO porque o runtime pode não entregá-la (partial reload que não a peça);
  // o componente aplica default fail-closed. O backend sempre a envia no load completo.
  permissoes?: Permissoes;
  // Deferidas (Inertia::defer) — chegam no segundo round-trip.
  abas?: Record<AbaKey, number>;
  kpis?: KpisCatalogo;
  produtos?: ProdutoRow[];
  totalDaAba?: number;
  /**
   * Totais do recorte pro rodapé (§4.6). `null` quando o perfil não pode ver custo — a chave
   * chega, o valor não. Somar dinheiro do catálogo revela a estrutura de custo por outro
   * caminho, então o gate da coluna vale igual pro agregado.
   */
  totaisDoRecorte?: { emEstoque: number; repor: number } | null;
  opcoesFiltro?: { categorias: OpcaoFiltro[]; unidades: OpcaoFiltro[]; marcas: OpcaoFiltro[] };
  categorias?: CategoriaRow[];
  insumos: InsumoRow[];
  tabelas: TabelaRow[];
  historico: HistoricoRow[];
};

const SUB_TELAS: ReadonlyArray<{ key: Exclude<SubTela, 'produtos'>; label: string; icon: typeof Tags }> = [
  { key: 'categorias', label: 'Categorias', icon: Tags },
  { key: 'insumos', label: 'Insumos · BOM', icon: Layers },
  { key: 'tabelas', label: 'Tabelas de preço', icon: PackageSearch },
  { key: 'historico', label: 'Histórico de uso', icon: History },
];

const TIPO_OPCOES: OpcaoFiltro[] = [
  { value: 'produto', label: 'Produto' },
  { value: 'servico', label: 'Serviço' },
  { value: 'materia', label: 'Matéria-prima' },
  { value: 'kit', label: 'Kit' },
];

/**
 * Recorte por disponibilidade. Rótulos do handoff de 21/08 §4.2 — o gatilho passou a se
 * chamar "Disponível" e as opções falam do que dá pra VENDER, não do que tem no depósito.
 */
const ESTOQUE_OPCOES: OpcaoFiltro[] = [
  { value: 'em', label: 'Com saldo' },
  { value: 'baixo', label: 'Abaixo do mínimo' },
  { value: 'sem', label: 'Sem saldo' },
  { value: 'nao', label: 'Não estocável' },
];

/**
 * Chaves de ordenação oferecidas pelo gatilho "Ordem" (handoff 21/08 §4.2).
 *
 * Existir um gatilho — e não só o clique no cabeçalho — é o que torna a ordem CORRENTE
 * explícita: o rótulo mostra "Código ↑" antes de qualquer interação, então quem abre a tela
 * sabe por que a lista está naquela sequência em vez de supor que é aleatória.
 */
const ORDEM_OPCOES: ReadonlyArray<{ key: ColunaKey; label: string; precisaCusto?: boolean; precisaPreco?: boolean }> = [
  { key: 'cod', label: 'Código' },
  { key: 'prod', label: 'Produto' },
  { key: 'est', label: 'Disponível' },
  { key: 'preco', label: 'Preço', precisaPreco: true },
  { key: 'margem', label: 'Margem', precisaCusto: true, precisaPreco: true },
];

/**
 * Onde as preferências de apresentação moram. Versionada no nome (`.v1`): mudar o formato do
 * que é gravado vira `.v2` em vez de tentar migrar — preferência é barata de recriar, e ler
 * um formato antigo com código novo é como a tela quebra sem ninguém perceber.
 */
const CHAVE_PREFS = 'oi.produtos.prefs.v1';

/** Colunas que o operador pode esconder pelo menu ⋯ (§3.1). Código e Produto nunca somem. */
const COLUNAS_OCULTAVEIS: ReadonlyArray<{ key: ColunaKey; label: string }> = [
  { key: 'tipo', label: 'Tipo' },
  { key: 'custo', label: 'Custo' },
  { key: 'preco', label: 'Preço de venda' },
  { key: 'margem', label: 'Margem' },
];

const MARGEM_OPCOES: OpcaoFiltro[] = [
  { value: 'sob_piso', label: 'Sob o piso' },
  { value: 'ok', label: 'Acima do piso' },
];

function ProdutoUnificadoIndex({
  tela,
  filters,
  pisoMargem,
  diasParado,
  porPaginaOpcoes,
  // Fail-closed: se a prop não chegar por qualquer caminho, esconde tudo em vez de
  // estourar `undefined.custo`. Ausência de permissão declarada nunca vira permissão.
  permissoes = { custo: false, preco: false, composicao: false },
  abas,
  kpis,
  produtos,
  totalDaAba,
  totaisDoRecorte,
  opcoesFiltro,
  categorias,
  insumos,
  tabelas,
  historico,
}: Props) {
  // Nome da empresa: MESMA fonte que o AppShellV2 usa na sidebar
  // (`shell.cockpit.businessNome`), que sai de uma query em `App\Business`. O `business.name`
  // do shared prop vem da SESSÃO e chega VAZIO em ambiente de teste, então serve só de
  // fallback. Sem default inventado — imprimir 'Oimpresso' aqui afirmaria um tenant que não é
  // o do usuário (Tier 0, ADR 0093). Os DOIS hooks são chamados incondicionalmente: `a ?? b`
  // não avalia `b` quando `a` tem valor, e hook em avaliação condicional quebra as Rules of Hooks.
  const shell = usePageProps().shell as ({ cockpit?: { businessNome?: string } } | undefined);
  const nomeDoShell = shell?.cockpit?.businessNome ?? null;
  const nomeDaSessao = useBusiness()?.name ?? null;
  const businessName = nomeDoShell ?? nomeDaSessao;

  const [busca, setBusca] = useState(filters.busca);
  const [abertoId, setAbertoId] = useState<number | null>(null);
  const [maisFiltros, setMaisFiltros] = useState(false);
  const buscaRef = useRef<HTMLInputElement>(null);

  /**
   * Preferências de APRESENTAÇÃO — densidade e colunas escondidas (handoff 21/08 §4.7).
   *
   * Ficam em `localStorage`, não na URL: não descrevem o recorte (que aba, que filtro), e sim
   * como esta pessoa gosta de ler a lista nesta máquina. Mandá-las pro servidor faria um link
   * compartilhado carregar o gosto de quem mandou.
   *
   * O recorte em si (aba, KPI, filtros, ordem, página) continua na URL — lá ele PRECISA
   * viajar, porque colar o endereço no WhatsApp tem que abrir a mesma lista do outro lado.
   */
  const [densa, setDensa] = useState(false);
  const [colsOcultas, setColsOcultas] = useState<ColunaKey[]>([]);

  useEffect(() => {
    try {
      const bruto = localStorage.getItem(CHAVE_PREFS);
      if (!bruto) return;
      const r = JSON.parse(bruto) as { densa?: unknown; colsOcultas?: unknown };
      setDensa(!!r.densa);
      if (Array.isArray(r.colsOcultas)) setColsOcultas(r.colsOcultas as ColunaKey[]);
    } catch {
      // Preferência ilegível (JSON corrompido, cota, modo privado): a tela abre no padrão.
      // Nada aqui vale derrubar a consulta do balcão.
    }
  }, []);

  useEffect(() => {
    try {
      localStorage.setItem(CHAVE_PREFS, JSON.stringify({ densa, colsOcultas }));
    } catch {
      // Cota cheia: segue sem persistir.
    }
  }, [densa, colsOcultas]);

  const copiar = (texto: string, rotulo: string) => {
    navigator.clipboard?.writeText(texto).then(
      () => toast.success(`${rotulo} copiado`),
      () => toast.error('Não foi possível copiar')
    );
  };

  /** Navega preservando o resto do recorte. `only` diz quais props re-rodam no servidor. */
  const irPara = (patch: Partial<Filtros>, only: string[]) =>
    router.get(route('products.unificado.index'), { ...filters, ...patch }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only,
    });

  const RECORTE = ['filters', 'produtos', 'kpis', 'totalDaAba', 'totaisDoRecorte'];

  // Busca debounced — o filtro é resolvido no SERVIDOR, junto com aba e KPI, num único `where`.
  useEffect(() => {
    if (busca === filters.busca) return;
    const t = setTimeout(() => irPara({ busca }, RECORTE), 350);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busca]);

  useEffect(() => setBusca(filters.busca), [filters.busca]);

  // `/` foca a busca — sem roubar a tecla de quem está digitando.
  useEffect(() => {
    const onKey = (ev: KeyboardEvent) => {
      const alvo = ev.target as HTMLElement | null;
      const digitando = !!alvo && /^(INPUT|TEXTAREA|SELECT)$/.test(alvo.tagName);
      if (ev.key === '/' && !digitando) {
        ev.preventDefault();
        buscaRef.current?.focus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, []);

  // Memoizado porque `produtos` é prop deferida: sem isto, o `?? []` cria um array novo a cada
  // render e invalida os useMemo abaixo sempre.
  const linhas = useMemo(() => produtos ?? [], [produtos]);

  // DERIVADOS — nunca em estado.
  const mostraTipo = useMemo(() => new Set(linhas.map((r) => r.tipo)).size > 1, [linhas]);
  const colunasPermitidas = useMemo(() => colunasDe({ perm: permissoes, mostraTipo }), [permissoes, mostraTipo]);
  /**
   * Esconder coluna é PREFERÊNCIA de leitura; não montar é AUTORIZAÇÃO. As duas se aplicam em
   * ordem: `colunasDe` decide o que o perfil pode ver, e só depois o operador tira da vista o
   * que não quer ler agora. Uma coluna que a permissão não montou não aparece no menu.
   */
  const colunas = useMemo(
    () => colunasPermitidas.filter((c) => !colsOcultas.includes(c.key)),
    [colunasPermitidas, colsOcultas]
  );
  /**
   * `min-width` CALCULADO (handoff 21/08 §3.1), não fixo em 1000px. Com o número fixo, esconder
   * Custo e Margem não tirava a barra de rolagem — a tabela continuava reservando a largura de
   * colunas que não estavam mais lá, e o seletor de colunas virava enfeite. Somando o que
   * realmente está na tela, esconder coluna elimina a rolagem de verdade.
   */
  const minWidth = useMemo(() => larguraMinima(colunas), [colunas]);
  /**
   * Ordem vem do SERVIDOR, não de estado local (handoff V2 §9).
   *
   * Até o pacote 18/08 a tela ordenava o array recebido. Sem paginação isso bastava — o array
   * ERA o recorte. Com página, ordenar a fatia responde a pergunta errada: clicar em "Preço"
   * na página 1 daria "o mais caro entre estes 25", não o mais caro do catálogo. Agora o
   * clique navega e o `ORDER BY` roda sobre o recorte inteiro, antes do `LIMIT`.
   */
  const ordem = filters.ordem
    ? { key: filters.ordem as ColunaKey, dir: filters.dir }
    : null;

  /**
   * Ordem CORRENTE pro rótulo do gatilho. Quando o servidor não recebeu `ordem`, ele ordena
   * por código ascendente — e é isso que o gatilho precisa imprimir. Um rótulo genérico
   * ("Ordenar") enquanto a lista está por código faz o operador procurar uma explicação que a
   * tela já tem.
   */
  const ordemAtual = ordem ?? { key: 'cod' as ColunaKey, dir: 'asc' as const };
  const ordemDisponivel = useMemo(
    () => ORDEM_OPCOES.filter((o) =>
      (!o.precisaCusto || permissoes.custo) && (!o.precisaPreco || permissoes.preco)
    ),
    [permissoes]
  );
  const rotuloOrdem =
    (ordemDisponivel.find((o) => o.key === ordemAtual.key)?.label ?? 'Código') +
    (ordemAtual.dir === 'asc' ? ' ↑' : ' ↓');

  const indiceAberto = abertoId === null ? -1 : linhas.findIndex((r) => r.id === abertoId);
  const produtoAberto = indiceAberto < 0 ? null : linhas[indiceAberto]!;

  /**
   * Esteira do painel (handoff 21/08 §5). Anda dentro da PÁGINA carregada, não do recorte
   * inteiro: as linhas vizinhas são as que já estão no cliente. Ir além da fatia exigiria uma
   * visita ao servidor no meio de uma comparação — o painel piscaria com a lista recarregando
   * atrás dele, e quem estava comparando dois itens perderia o fio.
   */
  const irParaVizinho = (delta: -1 | 1) => {
    const alvo = linhas[indiceAberto + delta];
    if (alvo) setAbertoId(alvo.id);
  };

  /**
   * Total AUTORITATIVO do recorte — vem do servidor (`totalDaAba`), não de `linhas.length`
   * (handoff V2 §9). Com página, `linhas` é a fatia: contar ela diria "25 registros" num
   * recorte de 1.300 e o rodapé mentiria pro operador.
   *
   * Enquanto a prop deferida não chegou, cai no tamanho da fatia. É o único valor honesto
   * disponível nesse instante, e o rodapé todo já está em esqueleto junto com a tabela.
   */
  const total = totalDaAba ?? linhas.length;
  const porPagina = filters.porPagina;
  const paginas = Math.max(1, Math.ceil(total / porPagina));
  const pagina = Math.min(Math.max(1, filters.pagina), paginas);
  const primeiraDaPagina = total === 0 ? 0 : (pagina - 1) * porPagina + 1;
  const ultimaDaPagina = Math.min(pagina * porPagina, total);

  // Fechar o drawer ao paginar: o item aberto não está mais na tela, e um painel de detalhe
  // sobre uma linha que sumiu é o tipo de estado que faz o operador desconfiar do que lê.
  const irParaPagina = (p: number) => {
    setAbertoId(null);
    irPara({ pagina: Math.min(Math.max(1, p), paginas) }, RECORTE);
  };

  const temFiltro = !!(filters.categoria || filters.unidade || filters.marca || filters.tipo || filters.estoque || filters.margem);

  /**
   * O gatilho de filtro JÁ É o estado — não há chips (handoff 21/08 §4.2).
   *
   * A tela teve uma segunda linha de chips ("Categoria: Insumos ×") até o pacote de 18/08.
   * Ela existia porque o gatilho era neutro e o filtro aplicado não saltava da faixa. Nas 27
   * ondas o gatilho passou a imprimir o valor no próprio rótulo, e aí o chip virou o MESMO
   * texto duas vezes na mesma tela, uma linha acima da outra — com dois "×" diferentes pra
   * tirar o mesmo filtro. Um botão "Limpar" ao lado dos gatilhos faz o que a linha de chips
   * fazia de útil, sem repetir nada.
   */

  /** Zera busca, KPI e os seis gatilhos numa visita só — não seis idas ao servidor. */
  const limparFiltros = () => {
    setBusca('');
    irPara(
      { busca: '', kpi: '', categoria: null, tipo: '', unidade: null, marca: null, estoque: '', margem: '' },
      RECORTE
    );
  };

  // Abaixo de 780px de LARGURA DISPONÍVEL os gatilhos opcionais somem e voltam pelo
  // "Mais filtros" — comportamento do pacote 17/08 (`.f-opt` / `.f-more`).
  const opcionalCls = maisFiltros ? '' : '@max-[780px]:hidden';

  // Clicar de novo na coluna ativa inverte; coluna nova começa ascendente. Volta pra página 1:
  // manter a página 7 depois de reordenar mostraria linhas que nunca estiveram lá.
  const trocarOrdem = (key: ColunaKey) =>
    irPara(
      { ordem: key, dir: ordem?.key === key && ordem.dir === 'asc' ? 'desc' : 'asc', pagina: 1 },
      RECORTE
    );

  return (
    <>
      <Head title="Produtos · Catálogo" />

      <div className="flex-1 bg-page-cream py-4">
        <div className="w-full px-6 space-y-2">
          {/* ───── BLOCO 1 · CABEÇALHO ───────────────────────────────────────── */}
          <header className="border-b overflow-visible" role="banner" style={{ borderBottomColor: 'var(--border)' }}>
            <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
              <div className="flex-1 min-w-0">
                <h1 className="text-[22px] font-bold tracking-tight text-foreground leading-snug">Produtos</h1>
                {/* Subtítulo conta o CADASTRO INTEIRO, não a aba (handoff §4.1). */}
                <p className="text-xs text-muted-foreground mt-0.5 tabular-nums">
                  <strong className="text-foreground font-semibold">
                    {(abas?.todos ?? 0).toLocaleString('pt-BR')}
                  </strong>{' '}
                  cadastrados
                  {businessName ? ` · ${businessName}` : ''}
                </p>
              </div>

              <div className="flex-shrink-0 flex items-center gap-1.5">
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" aria-label="Mais ações" title="Outras visões do catálogo" className="h-8 w-8 border-0">
                      <MoreVertical className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-60">
                    {/* ───── APRESENTAÇÃO (handoff 21/08 §3.2 e §3.1) ─────────
                        Densidade e colunas moram aqui, não numa barra própria: são ajustes
                        que a pessoa faz UMA vez e não volta a mexer. Ocupar largura fixa na
                        toolbar com controle de uso raro rouba espaço da busca, que é de uso
                        constante. */}
                    <div className="px-2 pt-2 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Apresentação
                    </div>
                    <DropdownMenuItem onSelect={(e) => { e.preventDefault(); setDensa((v) => !v); }}>
                      <Check className={'mr-2 h-3.5 w-3.5 shrink-0 ' + (densa ? 'opacity-0' : '')} />
                      Linhas confortáveis
                    </DropdownMenuItem>
                    {/* Só as colunas que a PERMISSÃO montou aparecem aqui. Listar "Custo" pra
                        quem não pode ver custo anunciaria a existência do dado — o gate é de
                        autorização, e ele vem antes da preferência. */}
                    {COLUNAS_OCULTAVEIS.filter((c) => colunasPermitidas.some((p) => p.key === c.key)).map((c) => (
                      <DropdownMenuItem
                        key={c.key}
                        onSelect={(e) => {
                          e.preventDefault();
                          setColsOcultas((v) => (v.includes(c.key) ? v.filter((k) => k !== c.key) : [...v, c.key]));
                        }}
                      >
                        <Check className={'mr-2 h-3.5 w-3.5 shrink-0 ' + (colsOcultas.includes(c.key) ? 'opacity-0' : '')} />
                        Coluna {c.label.toLowerCase()}
                      </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />

                    {/* As 4 sub-telas que antes eram abas. Continuam servidas pelo mesmo
                        controller, com os mesmos gates de permissão. */}
                    <div className="px-2 pt-1 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Outras visões
                    </div>
                    {SUB_TELAS.map(({ key, label, icon: Icon }) => (
                      <DropdownMenuItem
                        key={key}
                        onSelect={() => irPara({ tela: key }, ['tela', 'filters', 'categorias', 'insumos', 'tabelas', 'historico'])}
                      >
                        <Icon className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} />
                        {label}
                      </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <div className="px-2 pt-1 pb-1 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">
                      Dados
                    </div>
                    <DropdownMenuItem asChild>
                      <a href="/import-products">
                        <Upload className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} />
                        Importar
                      </a>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                      <a href="/products/download-excel">
                        <Download className="mr-2 h-4 w-4 shrink-0" strokeWidth={1.75} />
                        Exportar planilha
                      </a>
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                <Button size="sm" onClick={() => router.visit('/products/create')}>
                  Novo produto
                </Button>
              </div>
            </div>

            {/* Abas por TIPO — trocam tabela, contagem E os KPIs (handoff §4.2). */}
            {tela === 'produtos' && (
              <div className="px-6">
                <nav className="flex items-center gap-1 -mb-px overflow-x-auto" aria-label="Recorte por tipo de item">
                  {ABAS_CATALOGO.map((a) => {
                    const ativa = filters.aba === a.key;
                    return (
                      <button
                        key={a.key}
                        type="button"
                        role="tab"
                        aria-selected={ativa}
                        onClick={() => irPara({ aba: a.key, kpi: '' }, RECORTE)}
                        className={
                          'inline-flex items-center gap-1.5 py-[7px] px-3 -mb-px text-[13.5px] whitespace-nowrap ' +
                          'border-0 border-b-2 transition-[color,background-color,border-color] duration-150 ' +
                          (ativa
                            ? 'border-primary text-foreground font-semibold bg-[var(--idx-tab-ativa-bg)]'
                            : 'border-transparent text-muted-foreground font-medium hover:text-foreground hover:bg-[var(--idx-tab-hover-bg)]')
                        }
                      >
                        {a.label}
                        {/* Badge arredondado, não número solto: a contagem é um segundo dado
                            dentro da aba, e sem a cápsula ela lê como parte do rótulo. Ativa
                            usa o accent cheio; inativa usa o par escuro da referência. */}
                        <span
                          className={
                            'inline-block min-w-[18px] rounded-full px-1.5 text-center font-mono text-[10.5px] font-semibold tabular-nums leading-[1.4] ' +
                            (ativa
                              ? 'bg-primary text-primary-foreground'
                              : 'bg-[var(--idx-badge-cont-bg)] text-[var(--idx-badge-cont-fg)]')
                          }
                        >
                          {(abas?.[a.key] ?? 0).toLocaleString('pt-BR')}
                        </span>
                      </button>
                    );
                  })}
                </nav>
              </div>
            )}
          </header>

          {tela === 'produtos' ? (
            <>
              {/* ───── BLOCO 2 · KPI-FILTROS ─────────────────────────────────── */}
              <Deferred data="kpis" fallback={<EsqueletoKpis />}>
                <KpiFiltros
                  kpis={kpis ?? { min: 0, zero: 0, parado: 0, total: 0 }}
                  ativo={(filters.kpi || null) as KpiKey | null}
                  // "Itens listados" é o total da aba — selecioná-lo é o mesmo que não ter
                  // recorte nenhum, então ele LIMPA em vez de aplicar um filtro que não filtra.
                  onToggle={(k) => irPara({ kpi: !k || k === 'total' ? '' : k }, RECORTE)}
                  perm={permissoes}
                  diasParado={diasParado}
                />
              </Deferred>

              {/* ───── BLOCO 3 · TOOLBAR EM UMA LINHA ──────────────────────
                  Composicao do pacote 17/08 (`.fbar`): gatilhos de filtro -> contagem de
                  registros -> busca ocupando o resto da linha, que quebra sozinha quando
                  nao cabe.

                  Isto REVERTE a composicao de duas linhas que o pacote de 18/08 tinha posto
                  no lugar (D-03/D-04). Decisao [M] 2026-08-18: a 17/08 e a oficial.

                  O container e `@container` porque o alvo mede a LARGURA DISPONIVEL, nao a da
                  janela: com a sidebar aberta ou fechada a mesma janela da larguras diferentes,
                  e e a barra que precisa decidir se cabe. */}
              <div className="@container mt-4 flex items-center flex-wrap gap-2">
                <FiltroTrigger
                  label="Categoria"
                  value={filters.categoria ? String(filters.categoria) : ''}
                  options={opcoesFiltro?.categorias ?? []}
                  onChange={(v) => irPara({ categoria: v ? Number(v) : null }, RECORTE)}
                />
                <FiltroTrigger
                  label="Tipo"
                  value={filters.tipo}
                  options={TIPO_OPCOES}
                  onChange={(v) => irPara({ tipo: v }, RECORTE)}
                />

                {/* Gatilhos opcionais: somem abaixo de 780px e voltam pelo "Mais filtros".
                    Categoria e Tipo nunca somem — sao os dois que o alvo mantem sempre. */}
                <div className={opcionalCls}>
                  <FiltroTrigger
                    label="Unidade"
                    value={filters.unidade ? String(filters.unidade) : ''}
                    options={opcoesFiltro?.unidades ?? []}
                    onChange={(v) => irPara({ unidade: v ? Number(v) : null }, RECORTE)}
                  />
                </div>
                {/* Marca FICA — decisao [M] 2026-08-18. O pacote pede "Fornecedor", que o
                    UltimatePOS nao guarda no produto (so por compra); Marca e o atributo que
                    o produto carrega e que o balcao ja usa pra procurar. */}
                <div className={opcionalCls}>
                  <FiltroTrigger
                    label="Marca"
                    value={filters.marca ? String(filters.marca) : ''}
                    options={opcoesFiltro?.marcas ?? []}
                    onChange={(v) => irPara({ marca: v ? Number(v) : null }, RECORTE)}
                  />
                </div>
                <div className={opcionalCls}>
                  <FiltroTrigger
                    label="Disponível"
                    value={filters.estoque}
                    options={ESTOQUE_OPCOES}
                    onChange={(v) => irPara({ estoque: v }, RECORTE)}
                  />
                </div>
                {/* Recorte por margem e leitura da estrutura de custo — some pra quem nao
                    pode ver custo, igual a coluna e ao card de KPI. */}
                {permissoes.custo && permissoes.preco && (
                  <div className={opcionalCls}>
                    <FiltroTrigger
                      label="Margem"
                      value={filters.margem}
                      options={MARGEM_OPCOES}
                      onChange={(v) => irPara({ margem: v }, RECORTE)}
                    />
                  </div>
                )}

                {/* "Mais filtros" so existe na largura em que os opcionais sumiram — e ABRE os
                    mesmos gatilhos, em vez de ser um botao decorativo. */}
                <button
                  type="button"
                  onClick={() => setMaisFiltros((v) => !v)}
                  aria-expanded={maisFiltros}
                  className={
                    'hidden @max-[780px]:inline-flex items-center gap-1.5 h-[30px] px-[11px] rounded-lg border text-xs transition-colors ' +
                    (maisFiltros
                      ? 'border-primary/40 bg-primary/10 text-primary'
                      : 'border-border bg-card text-muted-foreground hover:text-foreground hover:bg-muted/60')
                  }
                >
                  Mais filtros
                  <ChevronDown size={12} className="opacity-60" />
                </button>

                {/* ───── ORDEM ─────────────────────────────────────────────
                    Gatilho próprio, ao lado dos filtros (handoff 21/08 §4.2). O clique no
                    cabeçalho da coluna continua ordenando — os dois escrevem no MESMO estado
                    e o rótulo daqui reflete o que a tabela está fazendo. A diferença é que
                    este gatilho responde ANTES de qualquer interação: sem ele, a lista abre
                    ordenada por código e nada na tela diz isso. */}
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <button
                      type="button"
                      aria-label={`Ordenar — atualmente ${rotuloOrdem}`}
                      className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-transparent px-2.5 text-xs text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
                    >
                      <ArrowDownUp size={12} className="opacity-60" />
                      <span className="whitespace-nowrap">{rotuloOrdem}</span>
                      <ChevronDown size={12} className="opacity-60" />
                    </button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="start" className="w-56">
                    {ordemDisponivel.map((o) => (
                      <DropdownMenuItem key={o.key} onSelect={() => irPara({ ordem: o.key, dir: 'asc', pagina: 1 }, RECORTE)}>
                        <Check className={'mr-2 h-3.5 w-3.5 shrink-0 ' + (ordemAtual.key === o.key ? '' : 'opacity-0')} />
                        {o.label}
                      </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                      onSelect={() => irPara({ ordem: ordemAtual.key, dir: ordemAtual.dir === 'asc' ? 'desc' : 'asc', pagina: 1 }, RECORTE)}
                    >
                      {ordemAtual.dir === 'asc' ? 'Inverter (maior primeiro)' : 'Inverter (menor primeiro)'}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>

                {/* "Limpar" só existe quando há o que limpar. Substitui a linha de chips: um
                    alvo pra soltar o recorte inteiro, sem repetir cada filtro por escrito. */}
                {(temFiltro || filters.kpi || filters.busca) && (
                  <Button variant="ghost" size="sm" onClick={limparFiltros} className="h-8 px-2.5 text-xs">
                    Limpar
                  </Button>
                )}

                {/* Contagem — sans 12px no alvo, nao mono. É o total do RECORTE (todas as
                    páginas), não o da fatia: o intervalo da página quem diz é o rodapé. */}
                <span className="text-[12px] leading-[12px] text-muted-foreground whitespace-nowrap">
                  {`${total.toLocaleString('pt-BR')} ${total === 1 ? 'registro' : 'registros'}`}
                </span>

                {/* A busca fecha a linha e ocupa o que sobrar (`flex:1 1 160px` no alvo). */}
                <label className="flex-[1_1_160px] min-w-[150px] ml-2 flex items-center gap-2.5 h-[38px] px-[13px] rounded-[10px] border border-border bg-card">
                  <Search className="h-[15px] w-[15px] text-muted-foreground shrink-0" />
                  <input
                    ref={buscaRef}
                    type="search"
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    aria-label="Buscar produtos"
                    aria-keyshortcuts="/"
                    placeholder="Buscar descrição, código, referência…"
                    className="flex-1 min-w-0 border-0 outline-none bg-transparent text-[13px] text-foreground placeholder:text-muted-foreground"
                  />
                  {/* So aparece com texto digitado — na tela em repouso a barra fica identica
                      ao alvo, e quem digitou ganha o atalho de limpar. */}
                  {busca ? (
                    <button type="button" onClick={() => setBusca('')} aria-label="Limpar busca" className="text-muted-foreground hover:text-foreground shrink-0">
                      <X className="h-4 w-4" />
                    </button>
                  ) : (
                    /* A tecla "/" já focava a busca desde o pacote de 17/08 — em silêncio.
                       Atalho que ninguém descobre não existe; o `kbd` é o que o torna real
                       (handoff 21/08 §4.2). Some assim que há texto: aí o alvo útil é o × . */
                    <kbd
                      aria-hidden="true"
                      className="shrink-0 rounded border border-border bg-muted px-[5px] py-[3px] font-mono text-[10.5px] leading-none text-muted-foreground"
                    >
                      /
                    </kbd>
                  )}
                </label>
              </div>

              {/* ───── BLOCO 4 · GRID ────────────────────────────────────────── */}
              {/* Contêiner SEM RAIO (handoff V2 §3.1) — decisão do produto, difere do cartão
                  `rounded-xl` do DS. Motivo: com raio, o cabeçalho sticky precisa de clipe de
                  canto, e `backdrop-filter` no cabeçalho quebra esse clipe (achado do LAUDO).
                  Sem raio, o cabeçalho pode ser opaco e simples, que é o que ele precisa ser
                  pra a linha não vazar por baixo dele durante a rolagem.

                  O `min-width` é CALCULADO a partir das colunas realmente montadas (§3.1):
                  soma das larguras declaradas, com a coluna Produto contando pelo seu piso de
                  340px. Era um 1000px fixo — e com ele, esconder Custo e Margem não tirava a
                  barra de rolagem, porque a tabela seguia reservando largura de coluna que não
                  estava mais lá. Abaixo da soma a tabela rola na horizontal em vez de truncar:
                  a tela é de cockpit desktop declarado. */}
              <div className="mt-3 border border-border bg-card overflow-hidden">
                <Deferred data="produtos" fallback={<EsqueletoTabela />}>
                  {/* `overflow-x-auto` SÓ na horizontal: a rolagem vertical volta a ser da
                      página (handoff V2 §3.1). Altura fixa era o que substituía a paginação
                      no pacote 18/08 — agora que o rodapé existe, ela só criaria duas barras
                      de rolagem concorrentes na mesma tela. */}
                  <div className="overflow-x-auto cw-scroll-thin">
                    <table className="w-full text-left" style={{ minWidth: `${minWidth}px` }}>
                      {/* Fundo OPACO, sem `backdrop-blur`: translúcido deixava o texto da
                          linha aparecer por trás do rótulo da coluna durante a rolagem. */}
                      <thead className="sticky top-0 z-10 bg-[var(--idx-grid-head-bg)]">
                        <tr className="border-b border-border">
                          {colunas.map((c) => (
                            <th
                              key={c.key}
                              scope="col"
                              style={{ width: c.width }}
                              className={
                                'px-4 py-2 text-[10.5px] uppercase tracking-widest text-muted-foreground font-medium ' +
                                (c.align === 'right' ? 'text-right ' : '') +
                                // 58 = 16 (padding) + 32 (avatar) + 10 (gap): o rótulo PRODUTO
                                // alinha com o nome, não com o avatar (handoff V2 §3.1).
                                (c.key === 'prod' ? '!pl-[58px]' : '')
                              }
                            >
                              {c.sortable ? (
                                <button
                                  type="button"
                                  onClick={() => trocarOrdem(c.key)}
                                  className="inline-flex items-center gap-1 hover:text-foreground transition-colors uppercase tracking-widest"
                                >
                                  {c.label}
                                  {ordem?.key === c.key
                                    ? (ordem.dir === 'asc' ? <ArrowUp size={11} /> : <ArrowDown size={11} />)
                                    : <ArrowUpDown size={11} className="opacity-40" />}
                                </button>
                              ) : (
                                c.label || <span className="sr-only">Ações</span>
                              )}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {linhas.length === 0 ? (
                          <tr>
                            <td colSpan={colunas.length} className="text-center py-16">
                              {/* Estado vazio explícito — o protótipo não tinha (pendência §14
                                  item 6). Tabela vazia sem explicação faz o operador achar que
                                  o cadastro está vazio e abrir chamado. */}
                              <p className="text-[13px] text-foreground font-medium">Nenhum item neste recorte</p>
                              <p className="mt-1 text-[12px] text-muted-foreground">
                                {busca || temFiltro || filters.kpi
                                  ? 'Tente limpar a busca, o filtro ou o cartão selecionado acima.'
                                  : 'Esta aba não tem itens cadastrados.'}
                              </p>
                            </td>
                          </tr>
                        ) : (
                          linhas.map((r) => {
                            const cells = celulasDe(r, {
                              perm: permissoes,
                              mostraTipo,
                              piso: pisoMargem,
                              densa,
                              onAcao: (e) => e.stopPropagation(),
                              onCopiar: copiar,
                            });
                            const aberta = abertoId === r.id;
                            return (
                              <tr
                                key={r.id}
                                role="button"
                                tabIndex={0}
                                aria-label={`Abrir detalhe de ${r.name}`}
                                onClick={() => setAbertoId(r.id)}
                                onKeyDown={(e) => {
                                  if (e.key === 'Enter' || e.key === ' ') {
                                    e.preventDefault();
                                    setAbertoId(r.id);
                                  }
                                }}
                                className={
                                  'border-b border-border/60 cursor-pointer transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring ' +
                                  (aberta ? 'bg-[var(--idx-row-sel-bg)]' : 'hover:bg-muted/40') +
                                  // Estado de linha: urgente quando zerado, arquivada quando inativa.
                                  (r.stockQty === 0 ? ' bg-destructive-soft/30' : r.active ? '' : ' opacity-60')
                                }
                              >
                                {colunas.map((c) => (
                                  <td key={c.key} className={'px-4 ' + (densa ? 'py-1 ' : 'py-2 ') + (c.align === 'right' ? 'text-right' : '')}>
                                    {cells[c.key] ?? null}
                                  </td>
                                ))}
                              </tr>
                            );
                          })
                        )}
                      </tbody>
                    </table>
                  </div>

                  {/* ───── RODAPÉ DE PAGINAÇÃO ─────────────────────────────
                      Faixa própria, IRMÃ do wrapper que rola — não filha (handoff V2 §4.8).
                      Dentro do wrapper ela herdaria a rolagem horizontal e o "Por página"
                      sumiria pra direita junto com as colunas. Fora, acompanha a largura
                      visível.

                      O DS não cobre este rodapé: o `Pagination` dele não tem primeira/última
                      nem indicador "N / M", só lista de números — que com 1.300 itens vira
                      uma régua inútil. Exceção AP2 registrada na ADR 0402 do pacote; quando o
                      DS absorver os dois, esta faixa morre. */}
                  <Inline gap={3} wrap className="border-t border-border bg-[var(--idx-grid-head-bg)] px-4 py-2">
                    {/* ───── TOTAIS DO RECORTE (handoff 21/08 §4.6) ───────
                        Do RECORTE inteiro, não da página — "quanto vale o que está nesta tela"
                        não é pergunta que alguém faça. As duas que se fazem são "quanto tenho
                        parado" e "quanto preciso desembolsar pra voltar ao mínimo".

                        "Repor" só aparece quando é maior que zero: com o catálogo em dia, um
                        "R$ 0,00" permanente treina o olho a ignorar a faixa justamente onde
                        ela vai gritar no dia em que houver o que repor. */}
                    {totaisDoRecorte && (
                      <Inline gap={3} wrap className="gap-x-3.5 gap-y-1" align="baseline">
                        <Inline gap={1} align="baseline" className="gap-1.5">
                          <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                            Valor em estoque (recorte, físico)
                          </span>
                          <span className="font-mono text-[12px] font-semibold tabular-nums text-foreground">
                            {brl(totaisDoRecorte.emEstoque)}
                          </span>
                        </Inline>
                        {totaisDoRecorte.repor > 0 && (
                          <Inline gap={1} align="baseline" className="gap-1.5">
                            <span className="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                              Repor até o mínimo
                            </span>
                            <span className="font-mono text-[12px] font-semibold tabular-nums text-destructive-fg">
                              {brl(totaisDoRecorte.repor)}
                            </span>
                          </Inline>
                        )}
                      </Inline>
                    )}

                    <span className="text-[11.5px] text-muted-foreground tabular-nums">
                      {total === 0
                        ? 'Nenhum registro'
                        : `Mostrando ${primeiraDaPagina.toLocaleString('pt-BR')}–${ultimaDaPagina.toLocaleString('pt-BR')} de ${total.toLocaleString('pt-BR')}`}
                    </span>

                    <Inline gap={3} className="ml-auto">
                      {/* `Select` do DS, com a MESMA anatomia do rodapé da golden master
                          (`Pages/Cliente/Index.tsx`): trigger sm, h-7, w-fit. */}
                      <Inline gap={1} className="gap-1.5 text-[11.5px] text-muted-foreground">
                        <span>Por página</span>
                        <Select
                          value={String(porPagina)}
                          onValueChange={(v) => {
                            // Volta pra página 1: a página 7 de 25-em-25 não é a página 7 de
                            // 100-em-100, e manter o número levaria o operador pra outro lugar
                            // do catálogo sem ele ter pedido.
                            setAbertoId(null);
                            irPara({ porPagina: Number(v), pagina: 1 }, RECORTE);
                          }}
                        >
                          <SelectTrigger variant="shadcn" size="sm" className="h-7 w-fit text-xs" aria-label="Itens por página">
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            {porPaginaOpcoes.map((n) => (
                              <SelectItem key={n} value={String(n)}>{n}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </Inline>

                      <Inline gap={1}>
                        <BotaoPagina rotulo="Primeira página" desabilitado={pagina === 1} onClick={() => irParaPagina(1)}>
                          <ChevronsLeft size={14} />
                        </BotaoPagina>
                        <BotaoPagina rotulo="Página anterior" desabilitado={pagina === 1} onClick={() => irParaPagina(pagina - 1)}>
                          <ChevronLeft size={14} />
                        </BotaoPagina>
                        <span className="px-2 text-[11.5px] text-muted-foreground tabular-nums" aria-live="polite">
                          {pagina} / {paginas}
                        </span>
                        <BotaoPagina rotulo="Próxima página" desabilitado={pagina === paginas} onClick={() => irParaPagina(pagina + 1)}>
                          <ChevronRight size={14} />
                        </BotaoPagina>
                        <BotaoPagina rotulo="Última página" desabilitado={pagina === paginas} onClick={() => irParaPagina(paginas)}>
                          <ChevronsRight size={14} />
                        </BotaoPagina>
                      </Inline>
                    </Inline>
                  </Inline>
                </Deferred>
              </div>
            </>
          ) : (
            <SubTelaSecundaria
              tela={tela}
              perm={permissoes}
              categorias={categorias ?? []}
              insumos={insumos}
              tabelas={tabelas}
              historico={historico}
              produtos={linhas}
              onVoltar={() => irPara({ tela: 'produtos' }, ['tela', 'filters', 'produtos', 'kpis', 'totalDaAba', 'totaisDoRecorte'])}
            />
          )}
        </div>
      </div>

      <DetalheProduto
        produto={produtoAberto}
        perm={permissoes}
        piso={pisoMargem}
        onFechar={() => setAbertoId(null)}
        onVizinho={irParaVizinho}
        temAnterior={indiceAberto > 0}
        temProximo={indiceAberto >= 0 && indiceAberto < linhas.length - 1}
        posicao={indiceAberto >= 0 ? `${primeiraDaPagina + indiceAberto} de ${total.toLocaleString('pt-BR')}` : ''}
        onCopiar={copiar}
      />
    </>
  );
}

ProdutoUnificadoIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Produto — Catálogo" breadcrumbItems={[{ label: 'Produto', href: '/products' }, { label: 'Catálogo' }]}>
    {page}
  </AppShellV2>
);

export default ProdutoUnificadoIndex;

/* ─── Subcomponentes locais ───────────────────────────────────────────── */

/**
 * Botão de navegação do rodapé. 28×28, desabilitado a 50% de opacidade (handoff V2 §4.8).
 *
 * `disabled` de verdade, não só opacidade: na primeira página o "anterior" precisa sair da
 * ordem de tabulação, senão o teclado passa por dois alvos mortos a cada volta.
 */
function BotaoPagina({
  rotulo, desabilitado, onClick, children,
}: { rotulo: string; desabilitado: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      disabled={desabilitado}
      aria-label={rotulo}
      title={rotulo}
      className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-border bg-card text-muted-foreground transition-colors hover:text-foreground hover:bg-muted disabled:opacity-50 disabled:pointer-events-none"
    >
      {children}
    </button>
  );
}

function EsqueletoKpis() {
  return (
    <div className="grid gap-[9px] grid-cols-2 sm:grid-cols-3 lg:grid-cols-6">
      {Array.from({ length: 6 }).map((_, i) => (
        <div key={i} className="h-[66px] rounded-md border border-border bg-card animate-pulse" />
      ))}
    </div>
  );
}

function EsqueletoTabela() {
  return (
    <div className="p-4 space-y-2">
      {Array.from({ length: 8 }).map((_, i) => (
        <div key={i} className="h-9 rounded bg-muted animate-pulse" />
      ))}
    </div>
  );
}

function SubTelaSecundaria({
  tela, perm, categorias, insumos, tabelas, historico, produtos, onVoltar,
}: {
  tela: Exclude<SubTela, 'produtos'>;
  perm: Permissoes;
  categorias: CategoriaRow[];
  insumos: InsumoRow[];
  tabelas: TabelaRow[];
  historico: HistoricoRow[];
  produtos: ProdutoRow[];
  onVoltar: () => void;
}) {
  const titulo = SUB_TELAS.find((s) => s.key === tela)?.label ?? '';
  return (
    <section className="pt-4 space-y-3">
      <div className="flex items-center gap-3">
        <h2 className="text-[15px] font-semibold text-foreground">{titulo}</h2>
        <button type="button" onClick={onVoltar} className="text-[12px] text-muted-foreground underline-offset-2 hover:underline hover:text-foreground">
          voltar ao catálogo
        </button>
      </div>
      {tela === 'categorias' && <ListaCategorias rows={categorias} />}
      {tela === 'insumos' && <ListaInsumos rows={insumos} perm={perm} />}
      {tela === 'tabelas' && <ListaTabelas rows={tabelas} produtos={produtos} perm={perm} />}
      {tela === 'historico' && <ListaHistorico rows={historico} perm={perm} />}
    </section>
  );
}
