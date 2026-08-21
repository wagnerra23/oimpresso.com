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
// ÂNCORA DE DESIGN — resolva com `node prototipo-ui/ancora.mjs Jana/Index`, nunca
// no olho. Ela é `prototipo-ui/cowork/jana-merge.jsx` (declarada em
// Index.charter.md `related_prototype`). Este bloco dizia `chat-jana.jsx`, o que
// contradiz o charter e a porta viva. O `chat-jana` NÃO é a âncora — mas é onde
// as regras `.jc-*` moram, e citá-lo por isso é legítimo: `jana-merge.jsx:725`
// importa `BriefDiario`/`KPICard`/`AnaliseCard` dele via `window` e reusa 11
// classes `.jc-*`. Ou seja: âncora = jana-merge; implementação = chat-jana.
//
// ⛔ MAS A ÂNCORA ESTÁ DEFEITUOSA — não derive dela sem conferir. O `ancora.mjs`
// devolve `✓` e cala sobre isso; quem cataloga é o pedido [CC] de 2026-08-09
// (`cowork-inbox/JANA-MODULO-ONDAS-PR-2026-08-09.md` §1 P-1/P-2 + §7), cujo
// PR 0.5 de conserto NUNCA rodou. Medido de novo aqui em 2026-08-13, ambos vivos:
//   · `jana-merge.jsx:89`      `truck: "frota"` — [W] MATOU a análise Frota em
//     2026-08-07; o Non-Goal já está no Index.charter.md. Este componente resiste
//     por conta própria (mapeia 2 de 4 KPIs, nenhum é frota) — mantenha assim.
//   · `jana-merge.jsx:645-646` cita `AnaliseInadimplenciaService` /
//     `AnaliseFaturamentoService`. NÃO EXISTEM (`git grep` rc=1). A fonte real é
//     `app/Services/Sells/SellsCockpitAggregator.php` — é o que o
//     `JanaDrillDrawer` já usa, e o anti-hook do charter proíbe o contrário.
// Regra prática: as regras VISUAIS (`.jc-*` do chat-jana) são confiáveis; o que
// o jana-merge diz sobre DADO e FONTE não é.
//
// ⚠️ VOCABULÁRIO DE COR NESTA ÁREA — `accent` significa DUAS coisas na mesma
// página, e a armadilha é silenciosa:
//   · shell cockpit  → `--accent` é o ROXO da marca (oklch .55 .15 295)
//   · Tailwind/shadcn → `--color-accent` é um CINZA de hover (oklch .235 .010 240)
// Logo, em `Pages/Jana/**` o roxo é SEMPRE `primary`. Só use `accent` quando
// quiser mesmo a superfície de hover cinza do shadcn — `hover:bg-accent` escrito
// pensando "accent = roxo" entrega CINZA.

import { useMemo, useState, type ReactNode } from 'react';
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
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
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
   * aggregator apura as quatro de qualquer jeito, numa consulta só — esconder
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

// Seção seccionadora (H2) — mesmo estilo do golden governance/Dashboard.
function SectionTitle({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <h2 className="mt-2 flex items-center gap-2 text-sm font-semibold uppercase tracking-widest text-muted-foreground">
      <span className="inline-flex text-muted-foreground">{icon}</span>
      {children}
    </h2>
  );
}

// Card de análise (título + ícone + pill opcional + valor grande + corpo).
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
  const pillTone =
    pill?.tone === 'crit'
      ? 'bg-destructive-soft text-destructive-fg'
      : pill?.tone === 'warn'
        ? 'bg-warning-soft text-warning-fg'
        : 'bg-success-soft text-success-fg';

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
            <span className={`rounded-full px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide ${pillTone}`}>
              {pill.label}
            </span>
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
  const pixHoje = coworkAggregates?.pixHojeTotal ?? 0;
  const deltaRev = coworkAggregates?.deltaRevenueVsYesterday ?? null;
  const deltaTicket = coworkAggregates?.deltaTicketVsLastWeek ?? null;
  const totalVendas = sellKpis?.total ?? 0;
  const totalPendentes = sellKpis?.due ?? 0;

  const overdueCount = insightsAggregates.overdueCount;
  const overdueValue = insightsAggregates.overdueValue;
  const totalAReceber = insightsAggregates.totalAReceber;
  const ageingBuckets = insightsAggregates.ageingBuckets;
  const ageingTotal = Object.values(ageingBuckets).reduce((a, b) => a + b, 0);
  const methodsAggList = insightsAggregates.methodsAgg;
  const methodsTotal = methodsAggList.reduce((a, m) => a + m.total, 0);
  const topClientesList = insightsAggregates.topClientes;
  const topClientesTotal = topClientesList.reduce((a, c) => a + c.total, 0);
  const churnList = insightsAggregates.churnOuro;
  const ticketMedio = insightsAggregates.ticketMedio;
  const topDevedor = insightsAggregates.topDevedor;

  const sparkline = coworkAggregates?.sparkline ?? [];
  const sparkMax = Math.max(...sparkline, 1);
  const sparkSum = sparkline.reduce((a, b) => a + b, 0);

  const firstName = userName?.split(' ')[0] || 'você';
  const firstNameUpper = firstName.toUpperCase();

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
  // pro que não conhece) é amarrada por teste — UC-COPI-PAINEL-12.
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
  // Âncora: prototipo-ui/cowork/jana-merge.jsx :887 (`JM_KPI_DRILL`).
  //
  // A regra fina do protótipo: o KPI só vira clicável quando existe uma análise
  // do MESMO dado — "ticket médio não abre faturamento". Aqui isso deixa 2 dos 4
  // KPIs clicáveis:
  //   Faturamento mês     → análise Faturamento   ✓ mesmo dado
  //   Inadimplência total → análise Inadimplência ✓ mesmo dado
  //   Ticket médio        → não há análise de ticket médio        ✗
  //   PIX hoje            → "Métodos de pagamento" é a quebra de TODAS as
  //                         formas em 30d, não o PIX de hoje — dado e janela
  //                         diferentes, então não abre.            ✗
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

  return (
    <div className="space-y-4">
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
          SÍMBOLO, não de linha: `grep -n "\.jc-brief{" prototipo-ui/cowork/chat-jana.css`):
          `color-mix(in oklch, var(--accent) 9%, var(--surface))`. */}
      <Card className="border-primary/25 bg-[color:color-mix(in_oklch,var(--color-primary)_9%,var(--color-card))]">
        <CardContent className="flex flex-col gap-3.5 p-5">
          <header className="flex flex-wrap items-center gap-3">
            <span className="inline-flex items-center gap-1.5 text-sm text-muted-foreground">
              <Calendar size={14} />
              <b className="font-semibold text-foreground">Brief diário</b>
              <span className="opacity-50">·</span>
              {new Date().toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
            </span>
            <span className="rounded-full bg-primary/10 px-2 py-0.5 text-[10.5px] font-bold uppercase tracking-wide text-primary">
              IA
            </span>
            <button
              type="button"
              className="ml-auto inline-flex items-center gap-1 rounded px-1.5 py-1 text-xs text-muted-foreground hover:text-foreground"
              title="Ouvir áudio do brief (em breve — TTS V2)"
            >
              <Volume2 size={11} /> Ouvir áudio
            </button>
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
              {overdueCount > 0 && (
                <button
                  type="button"
                  className="inline-flex items-center gap-1.5 rounded-full border border-primary bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground hover:bg-primary/90"
                >
                  <MessageSquare size={11} /> Disparar régua WhatsApp pros {overdueCount} atrasados
                </button>
              )}
              <button
                type="button"
                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground hover:border-primary/40 hover:bg-[color:color-mix(in_oklch,var(--color-primary)_8%,var(--color-card))] hover:text-foreground"
              >
                <ClipboardList size={11} /> Ver top devedores
              </button>
              <button
                type="button"
                className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1.5 text-xs font-medium text-muted-foreground hover:border-primary/40 hover:bg-[color:color-mix(in_oklch,var(--color-primary)_8%,var(--color-card))] hover:text-foreground"
              >
                <Search size={11} /> Investigar queda ticket médio
              </button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* KPIs (4 cards) ────────────────────────────────────────────────────── */}
      {/* RÓTULOS — alinhados à âncora (`jana-merge.jsx` → `getJanaData().kpis` no
          `chat-jana.jsx`; re-localize com `grep -n "kpis: \[" prototipo-ui/cowork/chat-jana.jsx`).
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

          O 4º NÃO foi renomeado. A âncora traz `Frota utilização`, e o slot vivo é
          `PIX hoje` — dados DIFERENTES, não sinônimos. Renomear seria mentir sobre
          o que o número mede; construir a Frota exige decidir de onde vem o dado
          (`Vehicle` é do `Modules/OficinaAuto`, e a `/ia` do núcleo atende ROTA
          LIVRE, vestuário). Isso é decisão [W], não implementação — o Non-Goal que
          proibia a Frota saiu no charter v7, mas a AUSÊNCIA DE FONTE não saiu com
          ele. */}
      <KpiGrid cols={4}>
        {carregandoCockpit ? (
          <KpiCardSkeleton label="Receita mês" />
        ) : (
          <KpiCard
            label="Receita mês"
            value={fmtShort(sparkSum || faturadoHoje)}
            icon="wallet"
            tone="default"
            delta={deltaRev !== null ? { value: deltaRev, label: 'vs ontem' } : undefined}
            onClick={abrirFat}
          />
        )}
        {/* `tone` só enfatiza quando HÁ alerta. O ramo `else` era `success`, que
            pintava o card de VERDE exibindo R$ 0,00 — verde afirmando "bom"
            sobre ausência de dado. A âncora enfatiza UM KPI só, e só no alerta
            (1 de 4 com `emphasize:true` no chat-jana.jsx; seletor `.jc-kpi.emph` no
            chat-jana.css — âncora de SÍMBOLO, re-localize com
            `grep -n "jc-kpi.emph" prototipo-ui/cowork/chat-jana.css`). */}
        <KpiCard
          label="A receber vencido"
          value={fmtShort(overdueValue)}
          icon="alert-triangle"
          tone={overdueValue > 0 ? 'danger' : 'default'}
          description={
            overdueCount > 0
              ? `${overdueCount} ${plural(overdueCount, 'venda vencida', 'vendas vencidas')}`
              : 'tudo em dia'
          }
          onClick={abrirInad}
        />
        <KpiCard
          label="Ticket médio"
          value={fmtShort(ticketMedio)}
          icon="trending-up"
          tone="default"
          delta={deltaTicket !== null ? { value: deltaTicket, label: '7d' } : undefined}
        />
        {carregandoCockpit ? (
          <KpiCardSkeleton label="PIX hoje" />
        ) : (
          <KpiCard
            label="PIX hoje"
            value={fmtShort(pixHoje)}
            icon="zap"
            tone="default"
            description={
              faturadoHoje > 0
                ? `${Math.round((pixHoje / faturadoHoje) * 100)}% do faturado`
                : '— sem faturamento hoje'
            }
          />
        )}
      </KpiGrid>

      {/* Metas entram AQUI — posição da âncora. Ver §R5 de
          `memory/requisitos/Jana/Index-visual-comparison.md`. */}
      {aposKpis}

      {/* Análises principais ───────────────────────────────────────────────── */}
      <SectionTitle icon={<BarChart3 size={14} />}>
        Análises principais
        <span className="ml-1 text-[11px] font-normal normal-case tracking-normal text-muted-foreground/80">
          clique num card pra ver de onde vem o número
        </span>
      </SectionTitle>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Inadimplência buckets.
            `big` herda `text-foreground`; só o NEGATIVO vira vermelho — senão
            R$ 0,00 aparece em vermelho afirmando alerta sobre ausência de dado.
            Âncora: `.jc-kpi-v` é `--text`, e só `.jc-kpi-v.red` é `--neg` — âncora de
            SÍMBOLO no chat-jana.css, re-localize com
            `grep -n "jc-kpi-v" prototipo-ui/cowork/chat-jana.css`. */}
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
            {Object.entries(ageingBuckets).map(([label, v]) => (
              <div key={label} className="flex flex-col gap-1">
                <div className="flex items-baseline justify-between text-xs">
                  <span className="text-muted-foreground">{label}</span>
                  <b className="font-semibold tabular-nums text-foreground">{fmtShort(v)}</b>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                  <div
                    className="h-full rounded-full bg-destructive"
                    style={{ width: ageingTotal > 0 ? `${(v / ageingTotal) * 100}%` : '0%' }}
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
            <div className="text-primary">
              <svg viewBox={`0 0 ${sparkline.length * 4} 40`} preserveAspectRatio="none" className="h-10 w-full">
                <polyline
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  points={sparkline.map((v, i) => `${i * 4},${40 - (v / sparkMax) * 38 - 1}`).join(' ')}
                />
              </svg>
              <div className="flex justify-between text-[10px] text-muted-foreground">
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
                  <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-primary"
                      style={{ width: topClientesTotal > 0 ? `${(c.total / topClientesTotal) * 100}%` : '0%' }}
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
                  <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full rounded-full bg-success"
                      style={{ width: methodsTotal > 0 ? `${(m.total / methodsTotal) * 100}%` : '0%' }}
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
        {nenhumaAnalise && (
          <div className="col-span-full rounded-lg border border-dashed border-border p-6 text-center">
            <p className="text-sm font-medium text-foreground">Nenhuma análise sendo exibida</p>
            <p className="mt-1 text-xs text-muted-foreground">
              Você escondeu todas em <span className="font-medium text-foreground">Configurar</span>.
              Os dados continuam lá — reative quando quiser.
            </p>
          </div>
        )}
      </div>

      {/* Ações sugeridas ───────────────────────────────────────────────────── */}
      {acoes.length > 0 && (
        <>
          <SectionTitle icon={<Lightbulb size={14} />}>Ações que {firstNameUpper} sugere</SectionTitle>

          <Card>
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
