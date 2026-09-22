// JanaCockpit — Cockpit "Analista IA" canon da Jana V2 no padrão PT-04 (/ia/dashboard).
//
// Substitui o bundle CSS paralelo `.sells-cowork .vd-insights-*` (JanaCockpitV2) pelo
// vocabulário canônico de dashboard (PT-04-Dashboard): shared KpiGrid/KpiCard + Card +
// tokens semânticos Tailwind (dark herda nativo). Zero ilha CSS — a violação R7 do
// ui:lint some com este componente (ver US-COPI-146 · PT-04 L80 · ADR UI-0013).
//
// A LÓGICA veio idêntica do JanaCockpitV2 (brief, acoes, janaKpis) — só o render mudou.
// Bifurcação decidida por [W] 2026-07-20.
//
// ⚠️ Este bloco dizia que o JanaCockpitV2 "continua servindo a tab Insights de /sells".
// Era falso — aquela tab foi removida de /sells e o V2 tinha 0 imports no repo. Ele foi
// deletado em 2026-08-10, e este componente é o único cockpit da Jana. Provas em
// memory/requisitos/Jana/RUNBOOK-components.md.
//
// Golden de referência: resources/js/Pages/governance/Dashboard.tsx
//
// ÂNCORA DE DESIGN — resolva com `node scripts/design/ancora.mjs Jana/Index`, nunca
// no olho. Ela é `prototipo-ui/cowork/Wagner/jana-merge.jsx` (declarada em
// Index.charter.md `related_prototype`). O `chat-jana` NÃO é a âncora — o §5 de
// 2026-08-10 o declarou não-âncora da Jana. Mas a âncora DEPENDE dele em runtime, e
// isso é fato medido, não opinião: `jana-merge.jsx:891` desestrutura
// `JanaHeader`/`BriefDiario`/`KPICard`/`AnaliseCard`/`AcaoRow`/`ConverseComJana` de
// `window` — objetos que o `chat-jana.jsx` publica. Citá-lo por REGRA VISUAL segue
// legítimo; derivar dele o CONJUNTO de KPIs (quais cards existem) não é, e foi assim
// que "Frota utilização" quase entrou.
//
// ⚠️ NÚMERO CORRIGIDO (2026-08-27): este bloco dizia "reusa 11 classes `.jc-*`".
// Medido: `grep -o 'jc-[a-z-]*' jana-merge.jsx | sort -u | wc -l` → **6**
// (jc-acoes · jc-grid · jc-h · jc-kpis · jc-page · jc-page--ia). Número restateado à
// mão apodrece — se precisar do valor de hoje, rode o comando, não leia esta linha.
//
// ── ESTADO DA ÂNCORA — o que era verdade em 2026-08-13 e o que mudou ───────────
// Este bloco afirmava, EM PRESENTE, que a âncora "ESTÁ DEFEITUOSA" por dois motivos
// (P-1/P-2 do pedido [CC] `JANA-MODULO-ONDAS-PR-2026-08-09.md` §1). Medido de novo
// em 2026-08-27, um fechou e o outro sobreviveu — e as duas refs de linha apodreceram:
//   · P-1 — os `Analise*Service` inexistentes: **FECHADO em 2026-08-21** (#6111, que
//     desceu o Cowork vivo pela rota fiel). O bloco `FONTE` do `JmDrillDrawer` migrou
//     de `:645-646` para **`:787-805`** e hoje cita 4× `SellsCockpitAggregator::…`,
//     que É a fonte real. A única menção que sobrou (`:790`) é um comentário
//     DOCUMENTANDO a remoção. `AnaliseFaturamentoService`: 0 ocorrências no arquivo.
//   · P-2 — `truck: "frota"`: **FECHADO, medido em 2026-08-31**. Esta linha dizia,
//     em presente, que ele "SEGUE ABERTO" em `:128`, `JM_KPI_DRILL` — e as duas
//     metades eram falsas. Medição, com controle positivo pra provar que o grep
//     alcança o arquivo: `grep -in 'frota\|truck' jana-merge.jsx` → **rc=1, zero
//     ocorrências**; `grep -c JM_KPI_DRILL jana-merge.jsx` → **2, rc=0**. E o
//     `JM_KPI_DRILL` vive em `:124` com **2** entradas (`{ coins: "fat", alert:
//     "inad" }`), sem `truck`. A frota sobrevive só no `chat-jana.jsx` (não-âncora),
//     e ali NÃO é KPI — é o ícone `truck:` e a classe `jc-an-frota`.
//     O veredito [W] de 2026-08-07 ("Frota utilização são alucinação, ninguém usa")
//     segue governando o que se CONSTRÓI aqui; o que mudou é que a âncora deixou de
//     oferecer a tentação. Este componente mapeia 2 de 3 KPIs, nenhum é frota.
// Re-localize por SÍMBOLO, nunca por número de linha: `grep -n 'truck' <arquivo>`.
//
// ⛔ NÃO reescreva isto como "a âncora está limpa" nem como "está defeituosa": as duas
// são afirmação em presente sobre um arquivo que muda no Cowork sem avisar (LC-10).
// O estado de HOJE se pergunta à porta viva: `node scripts/design/ancora.mjs Jana/Index`.
//
// ⚠️ VOCABULÁRIO DE COR NESTA ÁREA — `accent` significa DUAS coisas na mesma
// página, e a armadilha é silenciosa:
//   · shell cockpit  → `--accent` é o ROXO da marca (oklch .55 .15 295)
//   · Tailwind/shadcn → `--color-accent` é um CINZA de hover (oklch .235 .010 240)
// Logo, em `Pages/Jana/**` o roxo é SEMPRE `primary`. Só use `accent` quando
// quiser mesmo a superfície de hover cinza do shadcn — `hover:bg-accent` escrito
// pensando "accent = roxo" entrega CINZA.

import { useId, useMemo, useState, type ReactNode } from 'react';
import {
  AlertCircle,
  AlertTriangle,
  BarChart3,
  Calendar,
  ClipboardList,
  CreditCard,
  Lightbulb,
  MessageSquare,
  Search,
  Sparkles,
  Target,
  TrendingDown,
  TrendingUp,
  UserMinus,
  Volume2,
} from 'lucide-react';
import { Link } from '@inertiajs/react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import JanaKpiGrid from './JanaKpiGrid';
import JanaKpiCard from './JanaKpiCard';
import EmptyState from '@/Components/shared/EmptyState';
import { BriefValorSkeleton, KpiCardSkeleton, SparklineSkeleton } from './JanaCockpitSkeleton';
import JanaDrillDrawer, { type DrillAnalise } from './JanaDrillDrawer';
import JanaAcaoModal, { type AcaoHitl } from './JanaAcaoModal';
import { JANA_ANALISES, type JanaAnaliseId } from './useJanaConfig';

export interface JanaCockpitProps {
  /**
   * Slot renderizado LOGO APÓS os KPIs, antes das análises.
   *
   * Existe porque a âncora (`jana-merge.jsx`) põe METAS exatamente aí — entre os
   * KPIs e "ANÁLISES PRINCIPAIS" — e na tela viva o bloco de metas tinha ido pro
   * fim da página, depois das ações. Sem este slot, corrigir a ordem exigiria
   * quebrar o cockpit em dois ou duplicar seções.
   */
  aposKpis?: ReactNode;
  /**
   * Tier da Jana (`jana_pro_module`). Governa brief, análises e ações — as três
   * seções que a âncora (`jana-merge.jsx` §`JanaPage`) só desenha no Pro.
   *
   * Default `true` DE PROPÓSITO, e não `false` como o fail-safe do backend: o
   * `Chat.tsx` também monta este componente, e quem não passa a prop não pode
   * mudar de comportamento. O fail-safe vive onde o tier é LIDO
   * (`useJanaPro`/`HandleInertiaRequests::janaPlanoPro`, default `false`); aqui
   * o default só responde "ninguém me disse o tier", que é outra pergunta.
   */
  pro?: boolean;
  sellKpis: {
    total: number;
    paid: number;
    due: number;
    partial: number;
    overdue: number;
  };
  coworkAggregates?: {
    sparkline?: number[];
    deltaRevenueVsYesterday?: number | null;
    deltaTicketVsLastWeek?: number | null;
    topSeller?: { name: string; total: number } | null;
    pixHojeTotal?: number;
    faturadoHojeTotal?: number;
  };
  insightsAggregates: {
    overdueCount: number;
    overdueValue: number;
    ageingBuckets: { '0-30d': number; '30-90d': number; '90-365d': number; '>365d': number };
    methodsAgg: Array<{ method: string; total: number }>;
    topClientes: Array<{ name: string; total: number }>;
    topDevedor: { name: string; total: number } | null;
    ticketMedio: number;
    totalAReceber: number;
    churnOuro: Array<{ name: string; ltv: number; diasInativo: number; ultimaCompra: string | null }>;
  };
  userName?: string;
  /**
   * Quais análises renderizar (`JanaConfigDrawer`, persistido em
   * `localStorage['oimpresso.jana.cfg']`). Preferência de EXIBIÇÃO: o
   * aggregator apura as cinco de qualquer jeito, numa consulta só — esconder
   * card não economiza cálculo, e o drawer diz isso ao usuário.
   *
   * Opcional de propósito: `undefined` = mostra tudo. Assim o componente segue
   * montável sem a config (o `Chat.tsx` o reusa) e um storage bloqueado degrada
   * pra tela cheia, nunca pra tela vazia.
   */
  analisesVisiveis?: Partial<Record<JanaAnaliseId, boolean>>;
}

const fmtBRL = (n: number) =>
  n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const fmtShort = (n: number) =>
  n >= 1000 ? 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k' : fmtBRL(n);

const greeting = (): string => {
  const h = new Date().getHours();
  if (h < 12) return 'Bom dia';
  if (h < 18) return 'Boa tarde';
  return 'Boa noite';
};

const plural = (n: number, one: string, many: string) => (n === 1 ? one : many);

// Mapeia o tom do CTA da ação para a variante do Button canônico.
type CtaTone = 'danger' | 'violet' | 'orange' | 'dark' | 'primary';
const ctaVariant = (t: CtaTone): 'default' | 'destructive' | 'secondary' =>
  t === 'danger' ? 'destructive' : t === 'orange' || t === 'dark' ? 'secondary' : 'default';

// Seção seccionadora (H2) — RÉPLICA LOCAL da `.jc-h2` da âncora, não o h2 do golden
// governance/Dashboard (era isso até 2026-09-18, e a métrica divergia em 3 eixos).
//
// Âncora: `.jc-h2` em `prototipo-ui/cowork/Wagner/chat-jana.css` §"── H2 ──" — âncora de
// SÍMBOLO (`grep -n "jc-h2" prototipo-ui/cowork/Wagner/chat-jana.css`):
//
//   .jc-h2      700 11px/1 var(--mono) · uppercase · ls .08em · --text-3 · gap 7px
//                                                             · margin 6px 0 10px
//   .jc-h2 .ic  14px  (já batia: os dois call-sites passam `size={14}`)
//
// A CAIXA ALTA nunca foi divergência — os dois lados usam `text-transform` no CSS. O que
// divergia era `14px/600/1.4px` contra `11px/700/0.88px` (.08em × 11px = 0.88px), medido e
// registrado em `memory/requisitos/Jana/Index-visual-comparison.md:735`.
//
// Réplica LOCAL de propósito (ADR 0388 §D-1): esta função não sai do `JanaCockpit`, então o
// alinhamento não é imposto às outras telas — o mesmo caminho que o `JanaKpiCard` tomou em
// vez de mexer no `KpiCard` compartilhado. `font-mono` é tradução PROVADA, não suposta: o
// próprio espelho declara `--mono: var(--font-mono)` (`styles.css:6446`), que é o token do
// projeto.
export function SectionTitle({
  icon,
  children,
  'data-contract': dataContract,
}: {
  icon: ReactNode;
  children: ReactNode;
  /** Âncora do `contrato-de-tela`, quando a seção é pinada em `*.contract.json`.
   *
   *  Fica no próprio `<h2>` — um `<span class="sr-only">` só pra carregar o atributo
   *  duplicaria o texto para leitor de tela.
   *
   *  ⚠️ O nome da prop é o ATRIBUTO com hífen, não `dataContract` camelCase, e isso é
   *  exigência do gate, não estilo: o `contrato-de-tela` procura a string literal
   *  `data-contract` nos arquivos do `alvo` do contrato. Com a prop em camelCase o
   *  atributo chega ao DOM igual, mas o grep do gate não acha — e ele reprova com
   *  `X seção "..." sem âncora data-contract no alvo`. Medido nesta forma exata. */
  'data-contract'?: string;
}) {
  return (
    <h2
      data-contract={dataContract}
      className="mt-1.5 mb-2.5 flex items-center gap-[7px] font-mono text-[11px] leading-none font-bold uppercase tracking-[0.08em] text-muted-foreground"
    >
      <span className="inline-flex text-muted-foreground">{icon}</span>
      {children}
    </h2>
  );
}

// Card de análise (título + ícone + pill opcional + valor grande + corpo).
/**
 * Curva do sparkline de análise — o `Sparkline` da âncora (`chat-jana.jsx:271`, que o
 * `jana-merge.jsx` consome via `AnaliseCard`), portado.
 *
 * O que a âncora faz e a produção NÃO fazia — medido em 2026-09-21 com a mesma sonda nos dois
 * lados (`Index-visual-comparison.md` §Rodada MEDIDA de 2026-09-21 — GRÁFICOS, itens G1-G11):
 *  - traça CURVA Bézier (`Q`/`T`), não poligonal reta;
 *  - preenche a ÁREA sob a curva com gradiente do tom positivo (0.26 → 0);
 *  - declara `vector-effect="non-scaling-stroke"`.
 *
 * O `vector-effect` é o item caro, e não é preciosismo: com `preserveAspectRatio="none"` o traço
 * é deformado pela escala do viewBox. Medido na produção: escala x=8,971 · y=1, e o traço ocupava
 * **13,46px na horizontal contra 1,5px na vertical** (razão 8,97×, por `isPointInStroke`). Não
 * saltava aos olhos porque a série de biz=1 é plana — os 30 pontos tinham um único valor de y —,
 * e linha horizontal não exibe deformação. Apareceria no primeiro tenant com série variável.
 *
 * `useId` no gradiente porque id de `<defs>` é global no documento. A âncora usa id fixo
 * (`jcSparkGrad`) e tem uma instância só; aqui, duas instâncias colidiriam e a segunda herdaria
 * o preenchimento da primeira.
 *
 * ⚠️ A COR vem de `text-success` (token da produção, `oklch(0.68 0.13 162)`), não do literal da
 * âncora (`--pos`, `oklch(0.76 0.18 150)`). O papel semântico é o mesmo; o valor difere, e isso é
 * dívida de **Fundações** (UI-0013) — mexer no token muda a tela inteira e é decisão [W], não
 * deste porte.
 */
/**
 * Trilho e preenchimento das barras horizontais de análise — as duas famílias da âncora
 * (`chat-jana.css` §`.jc-bar-track` e §`.jc-bk-bar`), medidas em 2026-09-21
 * (`Index-visual-comparison.md` §Rodada de GRÁFICOS, itens G17-G22).
 *
 * As duas têm `height: 7px` na âncora; a produção usava `h-1.5` (6px). O que as separa é o
 * PREENCHIMENTO: `.jc-bar-track > div` usa `linear-gradient(90deg, var(--accent-hi), var(--accent))`,
 * enquanto `.jc-bk-bar > div` recebe a cor por faixa, do dado.
 *
 * ⚠️ O gradiente é derivado do TOKEN, não de literal. A produção não tem `--accent-hi`, e criar
 * token é decisão [W]; então o tom claro sai do próprio `--color-primary` com o delta de
 * luminosidade que foi MEDIDO na âncora (+0.06 em L). Conferido no runtime: a expressão abaixo
 * resolve para `linear-gradient(90deg, oklch(0.76 0.15 295), oklch(0.7 0.15 295))`, que é
 * exatamente o que o protótipo renderiza. `var(--primary)` NÃO resolve nesta base (devolve
 * transparente) — o Tailwind v4 expõe as cores do `@theme` como `--color-*`.
 */
const TRILHO_BARRA = 'h-[7px] overflow-hidden rounded-full bg-muted';

const PREENCHIMENTO_GRADIENTE =
  'linear-gradient(90deg, oklch(from var(--color-primary) calc(l + 0.06) c h), var(--color-primary))';

/**
 * Escala de severidade dos buckets de inadimplência — a âncora pinta CADA faixa de uma cor
 * (`getJanaData().analises[inad].buckets`: `--warn` → mix(warn,neg) → `--neg` → `--text-3`),
 * enquanto a produção pintava as quatro de `bg-destructive`. A leitura que se perdia é a de que
 * atraso maior não é "mais do mesmo vermelho": a faixa mais velha sai da escala quente e vira
 * cinza, porque `>365d` é candidata a baixa, não a cobrança — é o que o próprio card já diz no
 * rodapé da âncora.
 *
 * Os tons vêm dos tokens desta base, não dos literais da âncora: o papel semântico é o mesmo e o
 * valor difere, o que é dívida de Fundações (UI-0013) e não deste porte.
 */
const CORES_BUCKET = [
  'var(--color-warning)',
  'color-mix(in oklch, var(--color-warning) 70%, var(--color-destructive))',
  'var(--color-destructive)',
  'var(--color-muted-foreground)',
] as const;

function SparkArea({ dados }: { dados: number[] }) {
  const gid = useId();
  const w = 280;
  const h = 60;
  const min = Math.min(...dados);
  const max = Math.max(...dados);
  const norm = (v: number) => (max === min ? 0.5 : (v - min) / (max - min));
  // série de 1 ponto: `w/(n-1)` daria Infinity e o path sairia NaN. A âncora tem esse buraco
  // (ela nunca recebe série curta); aqui degrada para um ponto em x=0 em vez de sumir com a curva.
  const xStep = dados.length > 1 ? w / (dados.length - 1) : 0;
  const pts = dados.map((v, i) => [i * xStep, h - 4 - norm(v) * (h - 10)] as const);
  let d = `M ${pts[0]![0]} ${pts[0]![1]}`;
  for (let i = 1; i < pts.length; i++) {
    const [x0, y0] = pts[i - 1]!;
    const [x1, y1] = pts[i]!;
    const cx = (x0 + x1) / 2;
    d += ` Q ${cx} ${y0}, ${cx} ${(y0 + y1) / 2} T ${x1} ${y1}`;
  }
  const area = `${d} L ${w} ${h} L 0 ${h} Z`;

  return (
    <svg
      viewBox={`0 0 ${w} ${h}`}
      preserveAspectRatio="none"
      className="h-[60px] w-full"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="currentColor" stopOpacity="0.26" />
          <stop offset="100%" stopColor="currentColor" stopOpacity="0" />
        </linearGradient>
      </defs>
      <path d={area} fill={`url(#${gid})`} />
      <path
        d={d}
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        vectorEffect="non-scaling-stroke"
      />
    </svg>
  );
}

function AnalysisCard({
  icon,
  title,
  subtitle,
  pill,
  big,
  children,
  onClick,
}: {
  icon: ReactNode;
  title: string;
  subtitle: string;
  pill?: { label: string; tone: 'crit' | 'ok' | 'warn' };
  big: ReactNode;
  children: ReactNode;
  /** Quando passado, o card abre o drawer "de onde vem esse número". */
  onClick?: () => void;
}) {
  // Pill de ESTADO → par SOFT do `Badge` canon (`ui/badge.tsx`), nunca
  // `default`/`secondary`/`destructive`, que são fill sólido reservado a AÇÃO.
  // As três variantes abaixo consomem exatamente os mesmos tokens `-soft/-fg`
  // que este arquivo escrevia à mão — migração 1:1, sem troca de cor.
  const pillVariant: 'danger' | 'warning' | 'success' =
    pill?.tone === 'crit' ? 'danger' : pill?.tone === 'warn' ? 'warning' : 'success';

  const card = (
    <Card className={onClick ? 'h-full transition-colors hover:border-primary/40' : undefined}>
      <CardContent className="flex flex-col gap-3 p-4">
        <header className="flex items-center gap-2.5">
          <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-muted text-muted-foreground">
            {icon}
          </span>
          <div className="flex flex-1 flex-col">
            <b className="text-sm font-semibold text-foreground">{title}</b>
            <small className="text-[11px] text-muted-foreground">{subtitle}</small>
          </div>
          {pill && (
            <Badge variant={pillVariant} className="uppercase tracking-wide">
              {pill.label}
            </Badge>
          )}
        </header>
        <div className="text-2xl font-semibold tabular-nums text-foreground">{big}</div>
        {children}
      </CardContent>
    </Card>
  );

  if (!onClick) return card;

  // <button> nativo em vez de div[role=button]: o protótipo usa div + onKeyDown
  // manual, mas o botão real já traz Enter/Espaço, foco e leitura de tela de
  // graça — mesmo caminho que o KpiCard canônico escolheu (KpiCard.tsx:144).
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={`Ver de onde vem o número de ${title}`}
      className="w-full rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
    >
      {card}
    </button>
  );
}

export default function JanaCockpit({
  aposKpis,
  pro = true,
  sellKpis,
  coworkAggregates,
  insightsAggregates,
  userName,
  analisesVisiveis,
  // `businessName`/`businessId` saíram junto com o header próprio (onda de
  // fusão 2026-08-07) — quem os exibe agora é o `JanaAreaHeader`.
}: JanaCockpitProps): ReactNode {
  // `!== false` e não `?? true`: só um `false` EXPLÍCITO esconde. Sem config
  // (SSR, storage bloqueado, ou outro consumidor do componente) mostra tudo.
  const mostra = (id: JanaAnaliseId) => analisesVisiveis?.[id] !== false;
  const nenhumaAnalise = !JANA_ANALISES.some((a) => mostra(a.id));
  // ── Brief calculations (idêntico ao V2) ──────────────────────────────────
  // UC-PAINEL-08: `coworkAggregates` é DEFERIDA (`IndexController:47`). Enquanto
  // não chega, os `?? 0` abaixo produzem R$ 0 — e zero exibido como resultado é
  // o que o contrato de tela proíbe em letra ("não pode mostrar zero como se
  // fosse resultado"). Os `?? 0` FICAM (o cálculo derivado não pode quebrar, e é
  // o que mantém válida a entrada `Jana/Index` na DEFER_GUARD_ONLY_ALLOWLIST);
  // o que muda é o RENDER: com `carregandoCockpit`, o número não é pintado.
  // `undefined` é o único sinal de "ainda não chegou" — `null`/`{}` seriam
  // resposta do servidor, não ausência.
  const carregandoCockpit = coworkAggregates === undefined;
  const faturadoHoje = coworkAggregates?.faturadoHojeTotal ?? 0;
  // `pixHoje` FICA depois da saída do KPI (2026-08-31, UC-JPAIN-18): ele tem outros
  // 2 consumidores vivos neste arquivo — a ação sugerida "PIX adoção em N% — manter"
  // e a linha do brief (`· PIX <valor> (N% imediato)`). Medido antes de remover o
  // card: `rg -n pixHoje` → 19 hits no repo, 8 aqui. Não trate como órfão.
  const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;
  const deltaRev = coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = coworkAggregates?.deltaTicketVsLastWeek ?? null;
  const totalVendas = sellKpis?.total ?? 0;
  const totalPendentes = sellKpis?.due ?? 0;

  const overdueCount = insightsAggregates.overdueCount;
  const overdueValue = insightsAggregates.overdueValue;
  const totalAReceber = insightsAggregates.totalAReceber;

  // Quanto do que a empresa tem A RECEBER já venceu. Os dois campos JÁ chegavam
  // no payload e ninguém os cruzava — a tela dizia quantas vendas venceram, mas
  // não o peso delas. É a leitura que o card devia carregar: "1 venda vencida"
  // não diz se isso é irrelevante ou se é a metade do caixa.
  //
  // O arredondamento tem guarda de propósito: medido em produção (biz=1) a razão
  // real dá menos de 1%, e um `Math.round` cru viraria "0% do a receber" ao lado
  // de uma venda que ESTÁ vencida — número que contradiz o próprio card. Abaixo
  // de 1% o texto é "<1%", que é verdadeiro e não engana.
  //
  // Contenção provada: `overdueValue` é subconjunto de `totalAReceber` (o vencido
  // é parte do não-pago), então a razão nunca passa de 100%. Medido em produção:
  // overdueValue <= totalAReceber = true, totalAReceber > 0 = true.
  const pctVencido =
    totalAReceber > 0 && overdueValue > 0
      ? Math.max(1, Math.round((overdueValue / totalAReceber) * 100))
      : null;
  const pctVencidoTexto =
    pctVencido === null
      ? null
      : Math.round((overdueValue / totalAReceber) * 100) < 1
        ? '<1% do a receber'
        : `${pctVencido}% do a receber`;
  const ageingBuckets = insightsAggregates.ageingBuckets;
  const ageingTotal = Object.values(ageingBuckets).reduce((a, b) => a + b, 0);
  const methodsAggList = insightsAggregates.methodsAgg;
  const methodsTotal = methodsAggList.reduce((a, m) => a + m.total, 0);
  const topClientesList = insightsAggregates.topClientes;
  const topClientesTotal = topClientesList.reduce((a, c) => a + c.total, 0);
  // ── Business sem histórico: o corpo vira UM estado, não 6 caixas vazias ────
  // Predicado derivado do payload que o controller JÁ manda — nenhum campo novo,
  // nenhuma flag de servidor.
  //
  // `coworkAggregates` fica FORA de propósito: é `Inertia::defer`, chega depois, e
  // `undefined` ali significa "ainda não chegou", não "não tem dado". Misturar os dois
  // faria a tela piscar o empty-state durante o carregamento normal — o `carregandoCockpit`
  // continua mandando no skeleton, e este predicado só é consultado quando ele é `false`.
  //
  // Os dois `.length` são seguros: as linhas logo acima já fazem `.reduce()` direto em
  // `methodsAgg` e `topClientes` sem guard, desde sempre — se o servidor mandasse `null`,
  // a tela estaria quebrada hoje em qualquer business. Vêm `[]`, e é o comportamento vivo
  // que prova, não uma suposição do pedido.
  const semHistorico =
    totalVendas === 0 &&
    totalAReceber === 0 &&
    topClientesList.length === 0 &&
    methodsAggList.length === 0;
  const churnList = insightsAggregates.churnOuro;
  const ticketMedio = insightsAggregates.ticketMedio;
  const topDevedor = insightsAggregates.topDevedor;

  const sparkline = coworkAggregates?.sparkline ?? [];
  const sparkSum = sparkline.reduce((a, b) => a + b, 0);

  const firstName = userName?.split(' ')[0] || 'você';

  // ── Ações sugeridas ──────────────────────────────────────────────────────
  // A LÓGICA das 5 regras veio idêntica do V2. O que mudou em 2026-08-18 é o
  // CTA: era decorativo (`title="(HITL — em breve V2)"`, zero `onClick`) e agora
  // abre o `JanaAcaoModal`.
  //
  // Os rótulos mudaram junto — `Disparar`/`Preparar`/`Investigar`/`Detalhe`/
  // `Lembrar` viraram `Revisar …`. Não é cosmética: este PR REGISTRA a aprovação
  // e não envia nada, então "Disparar" abrindo um modal que não dispara trocaria
  // um botão morto por um botão que mente — o §Anti-hooks do charter ("prometer
  // no botão o que a rota não entrega") vale igual pros dois.
  //
  // Paridade com `AcaoHitlService::ACOES` (o backend valida a chave e devolve 404
  // pro que não conhece) é amarrada por teste — UC-JPAIN-12.
  type AcaoTone = 'rose' | 'violet' | 'peach' | 'grey';
  interface Acao {
    id: string;
    icon: ReactNode;
    title: string;
    sub: string;
    tone: AcaoTone;
    cta: { label: string; tone: CtaTone };
  }

  const acoes = useMemo((): Acao[] => {
    const list: Acao[] = [];
    if (overdueCount > 0) {
      list.push({
        id: 'regua-whatsapp',
        icon: <MessageSquare size={16} />,
        title: `Régua WhatsApp · ${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`,
        sub: `Potencial recuperação: ${fmtShort(overdueValue)}${topDevedor ? ` · top devedor: ${topDevedor.name}` : ''}`,
        tone: 'rose',
        cta: { label: 'Revisar régua', tone: 'danger' },
      });
    }
    if (overdueCount > 0 && overdueValue > 1000 && topDevedor) {
      list.push({
        id: 'negociar-top',
        icon: <Sparkles size={16} />,
        title: `Negociar com ${topDevedor.name}`,
        sub: `Valor ${fmtShort(topDevedor.total)} · contato direto vale mais que régua automática`,
        tone: 'violet',
        cta: { label: 'Revisar proposta', tone: 'violet' },
      });
    }
    if (deltaTicket !== null && deltaTicket <= -5) {
      list.push({
        id: 'investigar-ticket',
        icon: <TrendingDown size={16} />,
        title: 'Investigar queda ticket médio',
        sub: `${deltaTicket}% vs semana passada · pode ser mix de produto mudando`,
        tone: 'peach',
        cta: { label: 'Revisar recorte', tone: 'orange' },
      });
    }
    if (faturadoHoje > 0 && pixHoje > 0 && pixHoje / faturadoHoje > 0.5) {
      const pct = Math.round((pixHoje / faturadoHoje) * 100);
      list.push({
        id: 'pix-adocao',
        icon: <TrendingUp size={16} />,
        title: `PIX adoção em ${pct}% — manter`,
        sub: `${fmtShort(pixHoje)} de ${fmtShort(faturadoHoje)} hoje · custo zero vs maquininha`,
        tone: 'grey',
        cta: { label: 'Revisar leitura', tone: 'dark' },
      });
    }
    if (overdueCount === 0 && totalPendentes > 10) {
      list.push({
        id: 'preventivo-pendentes',
        icon: <Calendar size={16} />,
        title: `${totalPendentes} pendentes sem estourar ainda`,
        sub: 'Janela ideal pra lembrete amigável antes da régua agressiva',
        tone: 'grey',
        cta: { label: 'Revisar lembrete', tone: 'primary' },
      });
    }
    return list;
  }, [overdueCount, overdueValue, deltaTicket, faturadoHoje, pixHoje, totalPendentes, topDevedor]);

  // ── Drill-down "de onde vem esse número" ─────────────────────────────────
  // Âncora: prototipo-ui/cowork/Wagner/jana-merge.jsx :887 (`JM_KPI_DRILL`).
  //
  // A regra fina do protótipo: o KPI só vira clicável quando existe uma análise
  // do MESMO dado — "ticket médio não abre faturamento". Aqui isso deixa 2 dos 3
  // KPIs clicáveis:
  //   Receita 30 dias     → análise Faturamento   ✓ mesmo dado
  //   A receber vencido   → análise Inadimplência ✓ mesmo dado
  //   Ticket médio        → não há análise de ticket médio        ✗
  //
  // O 4º KPI (`PIX hoje`) SAIU em 2026-08-31 (UC-JPAIN-18). Ele também não abria,
  // e pela mesma razão que segue valendo pro card que ficou: "Métodos de pagamento"
  // é a quebra de TODAS as formas em 30d, não o PIX de hoje — dado e janela
  // diferentes.
  const [drill, setDrill] = useState<DrillAnalise | null>(null);

  // Ação em confirmação HITL. `null` = modal fechado. Guarda só o que o modal
  // EXIBE — a prévia ele mesmo busca no servidor, porque texto que afirma número
  // é veredito, e veredito não nasce aqui (mesma regra do farol e do drill).
  const [acaoHitl, setAcaoHitl] = useState<AcaoHitl | null>(null);

  // `leitura` só entra quando existe fato REAL pra contar. Onde não existe, o
  // drawer mostra Fonte + Escopo e cala — melhor que inventar uma frase.
  const abrirInad = () =>
    setDrill({
      id: 'inad',
      title: 'Inadimplência',
      sub: `${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`,
      leitura: topDevedor
        ? `Maior venda vencida: ${topDevedor.name} (${fmtShort(topDevedor.total)}).`
        : undefined,
    });
  const abrirFat = () => setDrill({ id: 'fat', title: 'Faturamento', sub: '30 dias' });
  const abrirConc = () => setDrill({ id: 'conc', title: 'Top 5 clientes', sub: 'concentração' });
  const abrirMetodos = () =>
    setDrill({ id: 'metodos', title: 'Métodos de pagamento', sub: `top ${methodsAggList.length}` });
  const abrirChurn = () => setDrill({ id: 'churn', title: 'Churn ouro', sub: 'maior LTV parado' });

  // ── Estado VAZIO de página (UC-JPAIN-29) ──────────────────────────────────────
  // Precedência, e a ordem importa: `carregandoCockpit` (skeleton) → `semHistorico`
  // (este ramo) → conteúdo. O early return vem DEPOIS de todos os hooks — os dois
  // `useState` acima são os últimos —, então nenhuma chamada é pulada entre renders.
  //
  // Sem isto, um business recém-onboardado vê seis caixas dizendo "Sem histórico",
  // "Sem dados de clientes", "Sem pagamentos registrados", "Ninguém de peso parou de
  // comprar" e `R$ 0,00` repetido — cada bloco sussurrando que não tem dado, nenhum
  // dizendo por quê nem o que fazer. É exatamente quem mais precisa da frase.
  //
  // ⚠️ `aposKpis` (METAS) CONTINUA: metas e vendas são eixos SEPARADOS — um business
  // pode ter meta cadastrada e zero venda, e vice-versa. Este estado cobre o eixo
  // VENDAS; a seção METAS segue com o `painel-metas-vazio` dela, cuja copy é pinada em
  // contrato. Fundir os dois apagaria copy que é lei [W].
  //
  // Os empty-states POR BLOCO ficam onde estão: eles continuam cobrindo o caso "tem
  // venda, não tem cliente top", que este ramo não alcança.
  if (!carregandoCockpit && semHistorico) {
    return (
      <div className="space-y-4">
        {/* `variant` fica no `default`. O pedido dizia `variant="first"`, que NÃO EXISTE
            neste componente — os quatro são `default | search | error | success`
            (`Components/shared/EmptyState.tsx`). `default` é o mais próximo da intenção
            declarada (primeiro uso, não erro nem filtro), e inventar uma variante nova
            pra um caso seria criar token de UI por atalho. */}
        <EmptyState
          className="rounded-lg border border-dashed border-border"
          icon="sparkles"
          title="A Jana ainda não tem histórico pra analisar"
          description="Ela precisa de pelo menos um mês de movimento pra montar o brief, os KPIs e as análises. Enquanto isso, pergunte o que quiser na aba Conversa."
          action={
            <Link href="/ia/conversa">
              <Button variant="outline" className="gap-2">
                <MessageSquare className="h-4 w-4" />
                Ir para a Conversa
              </Button>
            </Link>
          }
        />
        {aposKpis}
      </div>
    );
  }

  return (
    /* Ritmo vertical entre seções = **18px**, o da âncora. Medido em 2026-09-21 (Chrome,
       2560, dark, os dois lados na mesma janela), comparando o espaço VISUAL entre blocos
       consecutivos — não a propriedade isolada, que engana quando há padding no meio:

         de → para              âncora   prod (antes)
         brief → kpis             18        16
         kpis → metas             18        16
         metas → h2 Análises       6        16      ← vai pro outro lado; ver `mb-1.5` no Index
         h2 → grade               10        10  ✅
         grade → h2 Ações         18        16
         h2 → ações               10        10  ✅

       Na âncora o 18px não vem de um container: cada seção declara o seu
       (`.jc-brief`, `.jc-kpis`, `.jc-grid`, `.jc-acoes` — 4 ocorrências em
       `chat-jana.css`). Aqui fica no `space-y`, que é o idioma desta tela e produz o
       mesmo espaçamento com uma declaração em vez de quatro.

       ⚠️ Os `h2` continuam em 10px e isso é da âncora, não descuido: o `space-y` gera
       `:where(.space-y-* > :not(:last-child))`, de especificidade **0**, então o `mb-2.5`
       do `SectionTitle` vence sem `!important` — exatamente como a `.jc-h2` (`margin: 6px
       0 10px`) vence o ritmo do `.jc-page`. */
    <div className="space-y-[18px]">
      {/* Header do cockpit — REMOVIDO na onda de fusão (2026-08-07, US-COPI-148).
          Era a SEGUNDA barra da tela: identidade (Jana · Analista IA + business +
          biz) e ações (Atualizado / Configurar / Exportar) duplicavam o que já
          estava logo acima, e nenhuma das duas usava o `<PageHeader>` shared
          exigido por PT-04 R6. Tudo subiu pro `JanaAreaHeader`, que agora É o
          PageHeader canon — barra única, com o SubNav dentro (padrão
          `Financeiro/Caixa/Index.tsx:95-112`).

          Por isso este componente não recebe mais `businessName`/`businessId`:
          sem o header, ele não tinha mais nenhum consumidor pra esses dois. */}

      {/* Brief diário ──────────────────────────────────────────────────────── */}
      {/* Tint OPACO sobre a superfície de card, nunca translúcido sobre o fundo.
          `bg-primary/5` compõe sobre `--color-background` (dark: oklch 0.26) —
          mais ESCURO que um card (0.30) —, então o bloco mais importante da tela
          afundava em vez de subir. A âncora faz o oposto (seletor `.jc-brief` em
          chat-jana.css, consumido via `BriefDiario` no jana-merge.jsx — âncora de
          SÍMBOLO, não de linha: `grep -n "\.jc-brief{" prototipo-ui/cowork/Wagner/chat-jana.css`):
          `color-mix(in oklch, var(--accent) 9%, var(--surface))`. */}
      {/* ⚠️ O CORPO DOS DOIS RAMOS `pro` DESTE ARQUIVO (brief e grade de análises) NÃO foi
          reindentado ao ganhar o ternário. É deliberado: reindentar os 333 linhas dos dois
          blocos produziria ~660 linhas de diff e ESCONDERIA as ~20 que mudam o
          comportamento. Não há formatter obrigatório aqui — medido em 2026-09-21: nenhum
          workflow de `.github/workflows/` roda `prettier`, e o `eslint.config.js` não tem
          regra de indentação (`grep -cE '\bindent\b'` = 0, com `rules` = 6 de controle
          positivo). Quem reindentar um dia, faça em PR próprio e sozinho. */}
      {pro ? (
      <Card className="border-primary/25 bg-[color:color-mix(in_oklch,var(--color-primary)_9%,var(--color-card))]">
        <CardContent className="flex flex-col gap-3.5 p-5">
          <header className="flex flex-wrap items-center gap-3">
            <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
              <Calendar size={14} />
              <b className="font-semibold text-foreground">Brief diário</b>
              <span className="opacity-50">·</span>
              {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
            </span>
            {/* Marcador de PROVENIÊNCIA do brief ("isto foi escrito pela IA") —
                pill de estado, logo par SOFT do Badge canon. `info` é o slot
                semântico de "metadado informativo"; `default`/`secondary` são
                fill sólido de AÇÃO e não cabem num rótulo. */}
            <Badge variant="info" className="uppercase tracking-wide">
              IA
            </Badge>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="ml-auto gap-1 text-xs text-muted-foreground"
              title="Ouvir áudio do brief (em breve — TTS V2)"
            >
              <Volume2 size={11} /> Ouvir áudio
            </Button>
          </header>

          <div className="flex flex-col gap-2">
            <p className="text-sm leading-relaxed text-foreground">
              <strong className="font-semibold">
                {greeting()}
                {userName ? `, ${firstName}` : ''}.
              </strong>{' '}
              <strong className="font-semibold">{totalVendas}</strong> vendas no período
              {totalPendentes > 0 && (
                <>
                  {' · '}
                  <strong className="font-semibold">{totalPendentes}</strong> pendentes
                </>
              )}
              . Hoje somou{' '}
              {carregandoCockpit ? (
                <BriefValorSkeleton />
              ) : (
                <strong className="font-semibold">{fmtShort(faturadoHoje)}</strong>
              )}
              {!carregandoCockpit && deltaRev !== null && (
                <>
                  {' '}
                  <span
                    className={`inline-flex items-center gap-0.5 font-medium ${deltaRev >= 0 ? 'text-success' : 'text-destructive'}`}
                  >
                    {deltaRev >= 0 ? <TrendingUp size={11} /> : <TrendingDown size={11} />}
                    {deltaRev >= 0 ? '+' : ''}
                    {deltaRev}% vs ontem
                  </span>
                </>
              )}
              {pixHoje > 0 && faturadoHoje > 0 && (
                <>
                  {' · PIX '}
                  <strong className="font-semibold">{fmtShort(pixHoje)}</strong>{' '}
                  <small className="text-muted-foreground">
                    ({Math.round((pixHoje / faturadoHoje) * 100)}% imediato)
                  </small>
                </>
              )}
              .
            </p>

            {overdueCount > 0 && (
              <p className="flex items-start gap-2 rounded-md border border-destructive/25 bg-destructive/6 px-3 py-2.5 text-sm text-foreground">
                <span className="mt-0.5 inline-flex shrink-0 text-destructive">
                  <AlertCircle size={13} />
                </span>
                <span>
                  <strong className="font-semibold text-destructive">{fmtShort(overdueValue)}</strong> em{' '}
                  <strong className="font-semibold">
                    {overdueCount} {plural(overdueCount, 'venda vencida', 'vendas vencidas')}
                  </strong>
                  . Top devedor:{' '}
                  {topDevedor ? (
                    <>
                      <strong className="font-semibold">{topDevedor.name}</strong> ({fmtShort(topDevedor.total)})
                    </>
                  ) : (
                    '—'
                  )}
                  .
                </span>
              </p>
            )}

            {deltaTicket !== null && Math.abs(deltaTicket) >= 5 && (
              <p className="flex items-start gap-2 rounded-md border border-warning/25 bg-warning/6 px-3 py-2.5 text-sm text-foreground">
                <span className="mt-0.5 inline-flex shrink-0 text-warning">
                  <AlertCircle size={13} />
                </span>
                <span>
                  Ticket médio{' '}
                  <strong className={`font-semibold ${deltaTicket >= 0 ? 'text-success' : 'text-destructive'}`}>
                    {deltaTicket >= 0 ? '+' : ''}
                    {deltaTicket}%
                  </strong>{' '}
                  vs semana passada — investigar mix de produto.
                </span>
              </p>
            )}

            <div className="mt-1 flex flex-wrap gap-1.5">
              {/* Chips do brief — `Button` canon. O que era classe manual aqui
                  (bg/borda/tipo/estado de hover) já vem das variantes: `default`
                  entrega o primário `bg-primary … hover:bg-primary/90`, e
                  `outline` entrega borda + a superfície de hover. O par
                  `hover:bg-[color:color-mix(…)]` estava escrito LITERALMENTE
                  duas vezes e saiu — duplicata do que a variante cobre.
                  Sobra só a geometria de chip (`rounded-full`, `text-xs`), que
                  nenhuma variante decide. */}
              {overdueCount > 0 && (
                <Button type="button" size="sm" className="rounded-full text-xs">
                  <MessageSquare size={11} /> Disparar régua WhatsApp pros {overdueCount} atrasados
                </Button>
              )}
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="rounded-full text-xs text-muted-foreground"
              >
                <ClipboardList size={11} /> Ver top devedores
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="rounded-full text-xs text-muted-foreground"
              >
                <Search size={11} /> Investigar queda ticket médio
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
      ) : (
        /* Grátis: o brief vira upsell. `EmptyState` shared, variante `default` — `pro`
           falso é o estado de um usuário LEGÍTIMO, não erro: nada de tom `danger`, nada
           de cadeado. A âncora não tem nenhum dos dois. Copy literal do protótipo. */
        <EmptyState
          className="rounded-lg border border-dashed border-border"
          icon="calendar"
          title="O brief diário é do plano Pro"
          description="Toda manhã às 06h a Jana escreve o que aconteceu, o que está crítico e o que fazer hoje — com os números da sua empresa. No Grátis, você pergunta; no Pro, ela adianta."
          action={
            <Link href="/ia/pro">
              <Button variant="outline" className="gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                Ver Jana Pro
              </Button>
            </Link>
          }
        />
      )}

      {/* KPIs (3 cards) ────────────────────────────────────────────────────── */}
      {/* RÓTULOS — alinhados à âncora (`jana-merge.jsx` → `getJanaData().kpis` no
          `chat-jana.jsx`; re-localize com `grep -n "kpis: \[" prototipo-ui/cowork/Wagner/chat-jana.jsx`).
          Copiar RÓTULO é decisão de copy; copiar DADO seria erro — os números da
          âncora são mock do Martinho (biz=164), e nenhum deles entra aqui.

          Os 3 primeiros renomeiam porque descrevem o MESMO dado com a palavra da
          âncora — e no 2º a palavra da âncora é mais PRECISA que a nossa:
            Faturamento mês     → Receita mês
            Inadimplência total → A receber vencido   (é `overdueValue`: o que já
                                  venceu e não foi pago. "Inadimplência total"
                                  sugeria um total de inadimplência que este
                                  número não é.)
            Ticket médio        → (igual, sem mudança)

          O 4º SAIU (2026-08-31, decisão [W] · UC-JPAIN-18). Ele era `PIX hoje`, e
          o slot não tem contraparte: medido no protótipo, `getJanaData().kpis`
          publica **3** entradas (Receita mês · A receber vencido · Ticket médio) e
          a `jc-kpis` da âncora renderiza exatamente esse array
          (`jana-merge.jsx` §`data.kpis.map`). Com o 4º fora, a ORDEM dos três
          restantes passa a casar 1:1 com a do protótipo.

          ⚠️ Este bloco dizia, em presente, que "a âncora traz `Frota utilização`".
          Medido em 2026-08-31 e era FALSO: `grep -in 'frota\|truck' jana-merge.jsx`
          → rc=1, zero ocorrências, com controle positivo no mesmo arquivo
          (`grep -c JM_KPI_DRILL` → 2, rc=0). A frota sobrevive só no `chat-jana.jsx`
          (não-âncora) e ali NÃO é KPI: é um ícone (`truck:`) e uma classe CSS
          (`jc-an-frota`). Não há 4º slot na âncora pra disputar.

          ⛔ NÃO RE-PROPOR "mas o PIX podia voltar". A objeção — *o array `kpis` é
          autorado no `chat-jana.jsx`, e o mock é do Martinho, logo a ausência do PIX
          pode ser premissa deles* — foi levantada UMA vez e **REVOGADA por [W] em
          2026-08-31**, textual: *"essa ressalva deve ser por isso que não fica igual.
          deve ser revogado. que igual."*

          A regra que fica: **o conjunto de KPIs desta tela é o que a âncora RENDERIZA**
          — hoje 3. Não é decisão a re-litigar por sessão. Informar o número uma vez é
          serviço; deixá-lo pendurado como ressalva é recusa disfarçada, e foi
          exatamente isso que manteve a tela diferente do protótipo (ADR 0382 · §5
          2026-08-24 *"faça ficar igual. não é decisão minha, não me pergunte"*).

          O que segue valendo, e é regra de FONTE, não ressalva: derivar do
          `chat-jana.jsx` o que ele NÃO renderiza (a frota é o caso) continua proibido.
          Espelhar o que a âncora renderiza é o oposto disso — é paridade. */}
      {/* ── JANELA DO KPI: o rótulo diz o que o dado É ────────────────────
          Este card dizia "Receita mês" e mostrava `sparkSum` — a soma da
          SPARKLINE, que é `whereBetween(transaction_date, [hoje-29, hoje])`:
          30 dias DESLIZANTES, não o mês corrente. No dia 21 isso cobre 23/jul
          a 21/ago; os dois só coincidem no dia 30 ou 31.

          De onde veio a palavra errada (medido 2026-08-21): a âncora oficial
          desta tela (`jana-merge.jsx`, `related_prototype` do charter) NÃO tem
          este KPI. O rótulo "Receita mês" veio de `chat-jana.jsx` :87 — o
          protótipo que o §5 de 2026-08-10 declarou NÃO-âncora. E lá ele é
          coerente, porque o delta ao lado é "vs mai/25": mês contra mês. Aqui
          herdou-se a palavra sem a semântica — o dado é 30d e o delta é diário.
          É a lápide §5 2026-07-16 (importar sem checar se a premissa vale).

          O delta também passou a declarar sua janela: `deltaRevenueVsYesterday`
          compara HOJE com ONTEM, e rotulá-lo só "vs ontem" ao lado de um valor
          de 30 dias sugeria que o valor grande é que tinha variado.

          E o `|| faturadoHoje` saiu: era INALCANÇÁVEL, não um fallback. As duas
          queries têm filtros idênticos e a janela do sparkline vai até o fim de
          hoje, então `faturadoHoje ⊆ sparkSum` — se houve venda hoje, sparkSum
          já é > 0 e o `||` nunca dispara. Zero era zero de verdade.

          ⚠️ Trocar o RÓTULO não mexe em valor. Fazer o inverso — passar o
          cálculo a mês-calendário para casar a palavra antiga — mexeria, e aí
          vale a regra mestre de VALOR (dupla prova + antes→depois). É decisão
          [W], registrada no `Index.casos.md` §UC-JPAIN-14. */}
      {/* O grid é réplica local (`JanaKpiGrid`), não o `KpiGrid` compartilhado: a
          `.jc-kpis` da âncora quebra em 1100px e o `colsMap` do shared quebra em 1024/640,
          e arbitrary variant no `className` dele sai INERTE (o Tailwind 4 emite os
          arbitrários ANTES dos nomeados, então `lg:` vence). Os offsets medidos e as três
          faixas divergentes estão no docblock do componente e no `Index.casos.md`
          §UC-JPAIN-34. */}
      <JanaKpiGrid>
        {carregandoCockpit ? (
          <KpiCardSkeleton label="Receita 30 dias" />
        ) : (
          <JanaKpiCard
            label="Receita 30 dias"
            value={fmtShort(sparkSum)}
            icon="wallet"
            delta={deltaRev !== null ? { value: deltaRev, label: 'hoje vs ontem' } : null}
            /* Drill só no Pro — na âncora o `alvo` do KPI é `pro ? … : null`. `undefined`
               (e não um handler vazio) porque o `JanaKpiCard` degrada pro card inerte: sem
               `<button>`, sem `aria-label` de ação pendurado em algo que não abre nada. */
            onClick={pro ? abrirFat : undefined}
          />
        )}
        {/* `tone` só enfatiza quando HÁ alerta. O ramo `else` era `success`, que
            pintava o card de VERDE exibindo R$ 0,00 — verde afirmando "bom"
            sobre ausência de dado. A âncora enfatiza UM KPI só, e só no alerta
            (1 de 3 com `emphasize:true` no chat-jana.jsx — dizia "1 de 4", e o
            denominador estava errado: medido 2026-08-31, `grep -c emphasize` → 2
            ocorrências, sendo 1 o dado (`:94`) e 1 o render (`:256`), sobre um
            array de 3 KPIs. A REGRA visual não mudou; só o número apodrecera.
            Seletor `.jc-kpi.emph` no
            chat-jana.css — âncora de SÍMBOLO, re-localize com
            `grep -n "jc-kpi.emph" prototipo-ui/cowork/Wagner/chat-jana.css`). */}
        <JanaKpiCard
          label="A receber vencido"
          value={fmtShort(overdueValue)}
          icon="alert-triangle"
          emphasis={overdueValue > 0}
          valueTone={overdueValue > 0 ? 'negative' : 'default'}
          description={
            overdueCount > 0
              ? [
                  `${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`,
                  pctVencidoTexto,
                ]
                  .filter(Boolean)
                  .join(' · ')
              : 'tudo em dia'
          }
          onClick={pro ? abrirInad : undefined}
        />
        <JanaKpiCard
          label="Ticket médio"
          value={fmtShort(ticketMedio)}
          icon="trending-up"
          delta={deltaTicket !== null ? { value: deltaTicket, label: '7d' } : null}
        />
      </JanaKpiGrid>

      {/* Metas entram AQUI — posição da âncora. Ver §R5 de
          `memory/requisitos/Jana/Index-visual-comparison.md`. */}
      {aposKpis}

      {/* Análises principais ───────────────────────────────────────────────── */}
      <SectionTitle icon={<BarChart3 size={14} />}>
        Análises principais
        {/* Réplica do `.jc-h2 .jm-h2-sub` (`jana-merge.css:6`): `margin-left:auto` — ele vai
            pra DIREITA da faixa, não colado no título —, mono 10.5px/400, `ls .02em`, sem
            caixa alta. A copy já era byte-idêntica à âncora (medido em 2026-08-31); o que
            divergia era a métrica e a posição.
            ⚠️ A COR fica como está: a âncora usa `var(--text-dim)`, que NÃO é definido no
            escopo desta tela (`chat-jana.css`/`jana-merge.css` não o declaram — só
            `estoque-page.css` e `mockup-pages.css`, de outras telas). Sem token resolvível,
            trocar a cor seria adivinhar; fica medido e declarado em vez de inventado. */}
        {/* A sub-linha é PROMESSA DE DRILL, então acompanha o drill: no Grátis os cards
            não abrem o drawer, e prometer o clique seria o "(em breve)" que o UC-JPAIN-16
            cataloga. O TÍTULO fica nos dois planos — é ele que ancora a seção. */}
        {pro && (
        <span className="ml-auto font-mono text-[10.5px] font-normal normal-case tracking-[0.02em] text-muted-foreground/80">
          clique num card pra ver de onde vem o número
        </span>
        )}
      </SectionTitle>

      {/* Grade das análises — RÉPLICA LOCAL da `.jc-grid` da âncora.
          Medido em 2026-09-21 (Chrome, mesma janela, viewport 2560, dark, container
          2237px nos DOIS lados): âncora `repeat(3, 1fr)` + `gap: 12px`; prod estava
          em 2 colunas + 16px. Fonte: `prototipo-ui/cowork/Wagner/chat-jana.css`
          §"── Análises ──" — re-localize com
          `grep -n "jc-grid" prototipo-ui/cowork/Wagner/chat-jana.css`.

          ⚠️ Os BREAKPOINTS são os da âncora, não os do Tailwind. Ela quebra em
          `max-width: 1100px` (→2) e `max-width: 760px` (→1); o `lg:` daqui era 1024px
          e nunca chegava a 3. As `min-[761px]`/`min-[1101px]` são a tradução exata
          dessas duas queries — usar `lg:`/`xl:` aproximaria, e aproximar num
          breakpoint é o que fazia a prod parar em 2 colunas no monitor de 1280px
          da ROTA LIVRE, onde a âncora já mostra 3.

          ⚠️ O `margin-bottom` NÃO entra aqui de propósito: o 16px da prod vem do
          `space-y-4` do container da página (medido — a className da grade não
          declara margem), logo ele rege TODAS as seções (KPIs, Metas, Ações). A
          âncora usa 18px. Convergir isso é mudança de ritmo vertical da tela
          inteira, não da grade — fica medido e declarado, não corrigido de
          passagem. */}
      {pro ? (
      <div className="grid grid-cols-1 gap-3 min-[761px]:grid-cols-2 min-[1101px]:grid-cols-3">
        {/* Inadimplência buckets.
            `big` herda `text-foreground`; só o NEGATIVO vira vermelho — senão
            R$ 0,00 aparece em vermelho afirmando alerta sobre ausência de dado.
            Âncora: `.jc-kpi-v` é `--text`, e só `.jc-kpi-v.red` é `--neg` — âncora de
            SÍMBOLO no chat-jana.css, re-localize com
            `grep -n "jc-kpi-v" prototipo-ui/cowork/Wagner/chat-jana.css`. */}
        {mostra('inad') && (
        <AnalysisCard
          icon={<AlertTriangle size={16} />}
          title="Inadimplência"
          subtitle={`${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`}
          pill={{ label: overdueCount > 0 ? 'Crítico' : 'OK', tone: overdueCount > 0 ? 'crit' : 'ok' }}
          big={<span className={ageingTotal > 0 ? 'text-destructive' : undefined}>{fmtShort(ageingTotal)}</span>}
          onClick={abrirInad}
        >
          <div className="flex flex-col gap-2">
            {Object.entries(ageingBuckets).map(([label, v], i) => (
              <div key={label} className="flex flex-col gap-1">
                <div className="flex items-baseline justify-between text-xs">
                  <span className="text-muted-foreground">{label}</span>
                  <b className="font-semibold tabular-nums text-foreground">{fmtShort(v)}</b>
                </div>
                <div className={TRILHO_BARRA}>
                  <div
                    className="h-full rounded-full"
                    style={{
                      width: ageingTotal > 0 ? `${(v / ageingTotal) * 100}%` : '0%',
                      // escala de severidade por faixa, como a âncora — não um vermelho só
                      background: CORES_BUCKET[Math.min(i, CORES_BUCKET.length - 1)],
                    }}
                  />
                </div>
              </div>
            ))}
          </div>
        </AnalysisCard>
        )}

        {/* Faturamento sparkline */}
        {mostra('fat') && (
        <AnalysisCard
          icon={<TrendingUp size={16} />}
          title="Faturamento"
          subtitle="30 dias"
          pill={
            deltaRev !== null
              ? { label: `${deltaRev >= 0 ? '+' : ''}${deltaRev}% vs ontem`, tone: deltaRev >= 0 ? 'ok' : 'warn' }
              : undefined
          }
          big={carregandoCockpit ? <BriefValorSkeleton /> : <span>{fmtShort(sparkSum)}</span>}
          onClick={abrirFat}
        >
          {/* Três estados, não dois. O código antigo dizia "Carregando sparkline…"
              sempre que a série vinha vazia — então um business SEM vendas ficava
              "carregando" pra sempre, e um carregando de verdade era indistinguível
              de vazio. `carregandoCockpit` separa os dois; a copy de ausência é a
              MESMA do contrato (`painel-meta-sem-historico` → "Sem histórico"). */}
          {carregandoCockpit ? (
            <SparklineSkeleton />
          ) : sparkline.length === 0 ? (
            <div className="py-2 text-xs text-muted-foreground">Sem histórico</div>
          ) : (
            <div className="text-success">
              <SparkArea dados={sparkline} />
              <div className="flex justify-between text-[10.5px] text-muted-foreground">
                <span>D-{sparkline.length}</span>
                <span>hoje</span>
              </div>
            </div>
          )}
        </AnalysisCard>
        )}

        {/* Top clientes */}
        {mostra('conc') && (
        <AnalysisCard
          icon={<Target size={16} />}
          title="Top 5 clientes"
          subtitle="concentração"
          big={<span>{topClientesList.length}</span>}
          onClick={abrirConc}
        >
          <div className="flex flex-col gap-2">
            {topClientesList.length === 0 ? (
              <div className="py-2 text-xs text-muted-foreground">Sem dados de clientes</div>
            ) : (
              topClientesList.map((c) => (
                <div key={c.name} className="flex flex-col gap-1">
                  <div className="flex items-baseline justify-between text-xs">
                    <span className="max-w-[70%] truncate text-muted-foreground" title={c.name}>
                      {c.name}
                    </span>
                    <b className="font-semibold tabular-nums text-foreground">{fmtShort(c.total)}</b>
                  </div>
                  <div className={TRILHO_BARRA}>
                    <div
                      className="h-full rounded-full"
                      style={{
                        width: topClientesTotal > 0 ? `${(c.total / topClientesTotal) * 100}%` : '0%',
                        backgroundImage: PREENCHIMENTO_GRADIENTE,
                      }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </AnalysisCard>
        )}

        {/* Métodos de pagamento */}
        {mostra('metodos') && (
        <AnalysisCard
          icon={<CreditCard size={16} />}
          title="Métodos de pagamento"
          subtitle={`top ${methodsAggList.length}`}
          big={<span>{fmtShort(methodsTotal)}</span>}
          onClick={abrirMetodos}
        >
          <div className="flex flex-col gap-2">
            {methodsAggList.length === 0 ? (
              <div className="py-2 text-xs text-muted-foreground">Sem pagamentos registrados</div>
            ) : (
              methodsAggList.map((m) => (
                <div key={m.method} className="flex flex-col gap-1">
                  <div className="flex items-baseline justify-between text-xs">
                    <span className="text-muted-foreground">{m.method}</span>
                    <b className="font-semibold tabular-nums text-foreground">
                      {methodsTotal > 0 ? Math.round((m.total / methodsTotal) * 100) : 0}%
                    </b>
                  </div>
                  <div className={TRILHO_BARRA}>
                    <div
                      className="h-full rounded-full"
                      style={{
                        width: methodsTotal > 0 ? `${(m.total / methodsTotal) * 100}%` : '0%',
                        backgroundImage: PREENCHIMENTO_GRADIENTE,
                      }}
                    />
                  </div>
                </div>
              ))
            )}
          </div>
        </AnalysisCard>
        )}

        {/* Churn ouro */}
        {mostra('churn') && (
        <AnalysisCard
          icon={<UserMinus size={16} />}
          title="Churn ouro"
          subtitle="maior LTV parado"
          big={<span>{churnList.length}</span>}
          onClick={abrirChurn}
        >
          <div className="flex flex-col gap-2">
            {churnList.length === 0 ? (
              <div className="py-2 text-xs text-muted-foreground">Ninguém de peso parou de comprar</div>
            ) : (
              churnList.map((c) => (
                <div key={c.name} className="flex items-baseline justify-between gap-2 text-xs">
                  <span className="min-w-0 flex-1 truncate text-muted-foreground" title={c.name}>
                    {c.name}
                  </span>
                  <span className="shrink-0 tabular-nums text-muted-foreground opacity-70">{c.diasInativo}d</span>
                  <b className="shrink-0 font-semibold tabular-nums text-foreground">{fmtShort(c.ltv)}</b>
                </div>
              ))
            )}
          </div>
        </AnalysisCard>
        )}

        {/* Todas escondidas: a seção declara o estado e diz como voltar, em vez
            de deixar um título com o vazio embaixo — o usuário que escondeu tudo
            no drawer precisa achar o caminho de volta. */}
        {/* `EmptyState` shared em vez de div + dois `<p>` à mão. A copy é a MESMA,
            letra por letra: o `<span className="font-medium">` que envolvia
            "Configurar" caiu porque `description` é `string` no componente canon
            — o TEXTO RENDERIZADO não muda, só o realce perde o negrito. Trocar a
            assinatura do EmptyState pra aceitar ReactNode seria mexer num
            componente com N consumidores por causa de uma ênfase local. */}
        {nenhumaAnalise && (
          <EmptyState
            className="col-span-full rounded-lg border border-dashed border-border"
            icon="bar-chart-3"
            title="Nenhuma análise sendo exibida"
            description="Você escondeu todas em Configurar. Os dados continuam lá — reative quando quiser."
          />
        )}
      </div>
      ) : (
        /* Grátis: a grade vira upsell. `nenhumaAnalise` (todas escondidas no Configurar)
           continua valendo SÓ dentro do ramo Pro, acima — a config é preferência de
           EXIBIÇÃO de quem TEM as análises, nunca gate de plano. No Grátis quem manda é
           o upsell, e o drawer segue intocado. */
        <EmptyState
          className="rounded-lg border border-dashed border-border"
          icon="bar-chart-3"
          title="As 5 análises são do plano Pro"
          description="Inadimplência, faturamento, concentração, churn ouro e métodos de pagamento — recalculadas todo dia, com drill-down até a origem do número."
          action={
            <Link href="/ia/pro">
              <Button variant="outline" className="gap-2">
                <Sparkles className="h-4 w-4 text-primary" />
                Ver Jana Pro
              </Button>
            </Link>
          }
        />
      )}

      {/* Ações sugeridas ───────────────────────────────────────────────────── */}
      {/* No Grátis a faixa some INTEIRA — h2 junto —, como na âncora. Não vira upsell:
          dois cards de venda na mesma tela seria insistência, e o §3 do pedido marca esta
          seção como "ausente", não como "upsell". */}
      {pro && acoes.length > 0 && (
        <>
          {/* Quem sugere é a JANA, não quem está olhando a tela. Até 2026-09-18 isto
              interpolava `firstNameUpper`, derivado de `userName` — o usuário logado —, então
              a tela atribuía ao LEITOR sugestões que o servidor derivou de 5 regras sobre o
              dado dele. MEDIDO em prod (biz=1, 2026-09-18) o h2 renderizava
              `Ações que VOCÊ sugere`: o fallback `|| 'você'` de `:318` está ativo porque
              `userName` chega falsy (a saudação sai `Boa tarde.` sem nome, que é o
              discriminante — `:489` só omite o nome quando `userName` é falsy). Com
              `userName` preenchido, o mesmo código diria "AÇÕES QUE <USUÁRIO> SUGERE".
              Os dois erram o mesmo sujeito. ⚠️ A CAUSA do falsy é NÃO-MEDIDA — as hipóteses
              abertas são `auth()->user()->name` nulo ou a prop não propagar; é pendência
              separada, deste comentário não sai conclusão sobre ela. A âncora
              `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmPainel` escreve
              `AÇÕES QUE {data.person.name.toUpperCase()} SUGERE`, e `data.person` é
              `{ name: "Jana", role: "Analista IA" }` — âncora de SÍMBOLO, re-localize com
              `grep -n "person:" prototipo-ui/cowork/Wagner/chat-jana.jsx`.
              A caixa alta vem do CSS do `SectionTitle` (`uppercase`), igual à `.jc-h2`. */}
          <SectionTitle icon={<Lightbulb size={14} />}>Ações que Jana sugere</SectionTitle>

          {/* `py-0 gap-0` sobrescreve o `py-6 gap-6` do `Card` canon (`ui/card.tsx:29`)
              para casar a `.jc-acoes` da âncora, que é `padding: 0` + `overflow: hidden`
              (`chat-jana.css` §"── Ações sugeridas ──").

              ⚠️ Qual dos dois é a dívida VISUAL, medido em 2026-09-21: o `gap-6` é
              INERTE aqui — o `Card` tem UM filho só (o `CardContent`), e gap sem
              segundo filho não separa nada. Quem produzia o respiro de 24px no topo
              e na base, que a âncora não tem, é o `py-6`. A rodada de 2026-09-07
              registrou "ações · gap · normal × 24px": a medição estava certa, mas o
              `gap` era o sintoma legível, não a causa. Zero os dois porque a âncora
              tem os dois zerados — e `gap: normal` em flex É `0px`, então o par não
              reabre como divergência na próxima rodada. */}
          <Card className="gap-0 py-0">
            <CardContent className="flex flex-col divide-y divide-border p-0">
              {acoes.map((a) => (
                <div key={a.id} className="grid grid-cols-[auto_1fr_auto] items-center gap-3.5 p-3.5">
                  <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-border bg-card text-muted-foreground">
                    {a.icon}
                  </span>
                  <div className="min-w-0">
                    <b className="block text-sm font-semibold text-foreground">{a.title}</b>
                    <small className="block text-[11.5px] text-muted-foreground">{a.sub}</small>
                  </div>
                  <Button
                    variant={ctaVariant(a.cta.tone)}
                    size="sm"
                    onClick={() => setAcaoHitl({ id: a.id, title: a.title, sub: a.sub })}
                    aria-haspopup="dialog"
                  >
                    {a.cta.label}
                  </Button>
                </div>
              ))}
            </CardContent>
          </Card>
        </>
      )}

      <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
        <Lightbulb size={12} className="mt-0.5 shrink-0" />
        Insights baseados em vendas filtradas atual + agregados 30d. A aprovação de uma ação é
        registrada aqui; o disparo das mensagens entra num PR próprio.
      </p>

      {/* Drawer "de onde vem esse número" — aberto por KPI ou card de análise. */}
      <JanaDrillDrawer analise={drill} onClose={() => setDrill(null)} />

      {/* Confirmação HITL da ação sugerida — âncora §JmAcaoModal. */}
      <JanaAcaoModal acao={acaoHitl} onClose={() => setAcaoHitl(null)} />

      {/* Anti-flicker placeholder de totalAReceber pra reuso futuro do hook. */}
      <span hidden data-total-a-receber={totalAReceber} />
    </div>
  );
}
