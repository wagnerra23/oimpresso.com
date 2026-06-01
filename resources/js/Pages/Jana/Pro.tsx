// @memcofre
//   tela: /ia/pro
//   adrs: 0140 (Jana Pro SaaS), 0110 (Cockpit V2), 0190/0235 (primary roxo), 0093 (Tier 0)
//   design: prototipos/jana-pro/Jana Pro - Paywall CC.html (gate F1.5 PASS 90)
//   status: F3 (Cowork → Inertia) — billing real é Sprint JANA-B (ADR 0140)
//   module: Jana
//
// Paywall / upgrade da Jana Pro. Tradução fiel do protótipo aprovado pelo loop
// Cowork (COMPARISON.md + critique-score.json). Shell = AppShellV2 (sidebar dark
// Cockpit V2); a página é "modo FOCO" (sem SubNav de ghosts) — decisão de compra,
// análoga a um checkout Stripe, conforme COMPARISON dim. 2 + pageheader-canon.

import { type ReactNode, useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import AppShellV2 from '@/Layouts/AppShellV2'
import {
  ArrowLeft,
  ArrowRight,
  BadgeCheck,
  Check,
  Lock,
  Shield,
  Sparkles,
  X,
} from 'lucide-react'

// ── Props (de ProController@index) ───────────────────────────────────────────
interface ProofAngles {
  bruto: number
  liquido: number
  caixa: number
}

interface Props {
  plan: 'free' | 'pro'
  pricing: { monthly: number; trialDays: number }
  proof: ProofAngles
  business: { id: number | null; name: string }
}

// ── Tokens canônicos do card de prova (espelho do Cockpit Saúde Brain A) ──────
// Cores oklch fiéis ao protótipo (--sidebar/--sidebar-ink). Inline porque é uma
// "ilha" dark dentro de página clara — mesmo padrão de JanaAreaHeader (oklch inline).
const PROOF_BG = 'oklch(0.22 0.01 285)'
const PROOF_OVERLAY = 'radial-gradient(120% 80% at 100% 0%, oklch(0.4 0.12 295 / 0.35), transparent 60%)'
const PROOF_INK = 'oklch(0.96 0.01 90)'
const PROOF_MUTE = 'oklch(0.68 0.01 285)'
const BUB_THEM = 'oklch(0.30 0.012 285)'
const BUB_JANA = 'oklch(0.31 0.02 295)'
const NUM_POS = 'oklch(0.74 0.13 150)'

const fmtBRL = (n: number) =>
  new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
  }).format(n)

// ── Botões (classes base — reúsam tokens canon, sem competir 2 roxos) ─────────
const btnGhost =
  'inline-flex items-center gap-2 rounded-md border border-border bg-card px-3.5 py-2 text-[13px] font-medium text-muted-foreground transition-colors hover:bg-muted/60 hover:border-foreground/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary'

// ── Linha da tabela de comparação ────────────────────────────────────────────
function CmpRow({
  title,
  sub,
  free,
  pro,
}: {
  title: string
  sub: string
  free: ReactNode
  pro: ReactNode
}) {
  return (
    <div className="grid grid-cols-[1fr_130px_150px] items-center border-t border-muted">
      <div className="px-[18px] py-3">
        <b className="block text-[13.5px] font-medium text-foreground">{title}</b>
        <small className="text-[11.5px] text-muted-foreground">{sub}</small>
      </div>
      <div className="px-[18px] py-3 text-center text-[12.5px] text-foreground/80">{free}</div>
      <div className="border-l border-primary/15 bg-primary/[0.06] px-[18px] py-3 text-center text-[12.5px] font-semibold text-primary">
        {pro}
      </div>
    </div>
  )
}

const IcCheck = () => <Check className="inline-block size-4 align-[-3px] text-success" strokeWidth={3} />
const IcX = () => <X className="inline-block size-[15px] align-[-2px] text-muted-foreground/45" strokeWidth={2.5} />

function ProPage({ plan, pricing, proof }: Props) {
  // Estado da CTA: idle → activating → done (fecha loop de feedback, dim. 5).
  // Billing real (Asaas) é Sprint JANA-B (ADR 0140) — aqui é mock fiel ao protótipo.
  const [state, setState] = useState<'idle' | 'activating' | 'done'>('idle')

  const activate = () => {
    if (state !== 'idle') return
    setState('activating')
    // TODO Sprint JANA-B (ADR 0140): POST assinatura Asaas (trial 14d) em vez do mock.
    window.setTimeout(() => setState('done'), 900)
  }

  const voltar = () => router.visit('/ia')

  // Atalhos teclado (Larissa = teclado): ⌘/Ctrl+Enter ativa · Esc volta ao chat.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        e.preventDefault()
        activate()
      } else if (e.key === 'Escape') {
        voltar()
      }
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [state])

  const priceLabel = fmtBRL(pricing.monthly)

  return (
    <div className="flex h-full min-h-0 flex-col bg-page-cream text-foreground">
      {/* ── Header (modo FOCO) ── */}
      <header className="flex shrink-0 items-center gap-4 border-b border-border bg-card/80 px-7 py-3.5 backdrop-blur">
        <div>
          <div className="text-xs text-muted-foreground">
            <b className="font-medium text-foreground/70">Jana</b> · Plano
          </div>
          <h1 className="m-0 flex items-center gap-2 text-lg font-semibold tracking-tight">
            Jana Pro
            <span className="rounded-full border border-primary/25 bg-primary/5 px-[7px] py-0.5 text-[10px] font-bold tracking-wider text-primary">
              UPGRADE
            </span>
          </h1>
        </div>
        <div className="flex-1" />
        <button type="button" onClick={voltar} className={btnGhost}>
          <ArrowLeft className="size-3.5" /> Voltar ao chat
        </button>
      </header>

      {/* ── Body (scroll) ── */}
      <div className="flex-1 overflow-y-auto p-7">
        <div className="mx-auto max-w-[1060px]">
          {/* ── Hero ── */}
          <div className="mb-[22px] grid grid-cols-1 gap-[22px] lg:grid-cols-[1.05fr_0.95fr]">
            {/* Hero esquerda — pitch */}
            <div className="flex flex-col justify-center rounded-lg border border-border bg-card px-[30px] py-7 shadow-sm">
              <div className="mb-3 flex items-center gap-[7px] text-[11px] font-semibold uppercase tracking-[0.12em] text-primary">
                <Sparkles className="size-[15px]" />
                A Jana já trabalha pra você
              </div>
              <h2 className="m-0 mb-3 text-[27px] font-bold leading-[1.18] tracking-[-0.02em]">
                Ela conhece o seu negócio.
                <br />O <em className="not-italic text-primary">Pro</em> tira as amarras.
              </h2>
              <p className="m-0 mb-[18px] max-w-[42ch] text-sm text-muted-foreground">
                No plano grátis a Jana responde com seus dados reais — mas esquece rápido e não age
                sozinha. O Pro dá a ela memória ilimitada, brief diário e análises que rodam no
                automático.
              </p>
              {plan === 'free' && (
                <div className="flex items-center gap-2.5 border-t border-muted pt-4 text-[12.5px] text-muted-foreground">
                  <span className="rounded-full border border-border bg-page-cream px-2 py-0.5 text-[11px] font-semibold text-foreground/70">
                    Seu plano hoje: Grátis
                  </span>
                  <span>· memória de 7 dias · 1 meta · sem brief automático</span>
                </div>
              )}
            </div>

            {/* Hero direita — card de prova (Jana lendo dados reais) */}
            <div
              className="relative flex flex-col gap-3 overflow-hidden rounded-lg p-5 shadow-md"
              style={{ background: PROOF_BG, color: PROOF_INK }}
            >
              <div aria-hidden className="pointer-events-none absolute inset-0" style={{ background: PROOF_OVERLAY }} />
              <div className="relative flex items-center gap-[9px]">
                <span
                  className="grid size-[26px] flex-none place-items-center rounded-full text-xs font-bold text-white"
                  style={{ background: 'linear-gradient(135deg, oklch(0.55 0.15 295), oklch(0.64 0.15 300))' }}
                >
                  J
                </span>
                <b className="text-[13px]">Jana</b>
                <small className="ml-auto flex items-center gap-[5px] text-[11px]" style={{ color: PROOF_MUTE }}>
                  <span
                    className="size-1.5 rounded-full"
                    style={{ background: NUM_POS, boxShadow: '0 0 0 3px oklch(0.55 0.13 150 / 0.25)' }}
                  />
                  lendo seu ERP
                </small>
              </div>

              <div
                className="relative max-w-[90%] self-end rounded-md rounded-br-[2px] px-[13px] py-2.5 text-[13px] leading-[1.5]"
                style={{ background: BUB_THEM }}
              >
                Jana, como foi meu faturamento esse mês?
              </div>

              <div
                className="relative max-w-[90%] self-start rounded-md rounded-bl-[2px] px-[13px] py-2.5 text-[13px] leading-[1.5]"
                style={{ background: BUB_JANA }}
              >
                Maio fechou acima de abril. Veja pelos 3 ângulos:
                <div className="mt-[9px] flex gap-3.5 border-t pt-[9px]" style={{ borderColor: 'oklch(0.40 0.02 285)' }}>
                  <div className="flex flex-col">
                    <small className="text-[9.5px] uppercase tracking-[0.08em]" style={{ color: PROOF_MUTE }}>
                      Bruto
                    </small>
                    <b className="font-mono text-sm tabular-nums text-white">{fmtBRL(proof.bruto)}</b>
                  </div>
                  <div className="flex flex-col">
                    <small className="text-[9.5px] uppercase tracking-[0.08em]" style={{ color: PROOF_MUTE }}>
                      Líquido
                    </small>
                    <b className="font-mono text-sm tabular-nums text-white">{fmtBRL(proof.liquido)}</b>
                  </div>
                  <div className="flex flex-col">
                    <small className="text-[9.5px] uppercase tracking-[0.08em]" style={{ color: PROOF_MUTE }}>
                      Caixa
                    </small>
                    <b className="font-mono text-sm tabular-nums" style={{ color: NUM_POS }}>
                      {fmtBRL(proof.caixa)}
                    </b>
                  </div>
                </div>
              </div>

              <div className="relative mt-0.5 flex items-center gap-[7px] text-[11px]" style={{ color: PROOF_MUTE }}>
                <Check className="size-[13px] text-primary" strokeWidth={2} />
                Números reais das suas tabelas — sem planilha, sem integração.
              </div>
            </div>
          </div>

          {/* ── Comparação Grátis vs Pro ── */}
          <h3 className="m-0 mb-[13px] flex items-center gap-[9px] text-[13px] font-semibold tracking-[0.02em]">
            Grátis vs Pro
            <span className="h-px flex-1 bg-border" />
          </h3>
          <div className="mb-[22px] overflow-hidden rounded-lg border border-border bg-card shadow-sm">
            <div className="grid grid-cols-[1fr_130px_150px] items-center">
              <div className="px-[18px] py-3.5 text-[11px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                Recurso
              </div>
              <div className="px-[18px] py-3.5 text-center text-xs font-semibold text-muted-foreground">Grátis</div>
              <div className="border-l border-primary/15 bg-primary/[0.06] px-[18px] py-3.5 text-center">
                <b className="text-[13px] font-bold tracking-[0.04em] text-primary">JANA PRO</b>
                <small className="block text-[10.5px] font-medium text-primary/80">tudo do Grátis, e mais</small>
              </div>
            </div>

            <CmpRow
              title="Brief diário às 06h"
              sub="resumo do dia pronto antes de você abrir a loja"
              free={<IcX />}
              pro={<IcCheck />}
            />
            <CmpRow
              title="Análises automáticas"
              sub="inadimplência, oportunidades, status de NF-e"
              free={<IcX />}
              pro={<IcCheck />}
            />
            <CmpRow
              title="Cockpit Saúde"
              sub="a Jana narra a saúde do negócio de hora em hora"
              free={<IcX />}
              pro={<IcCheck />}
            />
            <CmpRow
              title="Memória persistente"
              sub="lembra do seu negócio entre conversas"
              free="7 dias"
              pro={
                <>
                  Ilimitada
                  <span className="ml-[7px] rounded-full bg-primary px-1.5 py-px align-[1px] text-[10px] font-bold tracking-[0.03em] text-primary-foreground">
                    PRO
                  </span>
                </>
              }
            />
            <CmpRow
              title="Metas governadas + alertas"
              sub="acompanha e avisa quando desvia"
              free="1 meta"
              pro="Ilimitadas"
            />
            <CmpRow
              title="Chat com dados reais do ERP"
              sub="a base dos dois planos — vendas, clientes, NF-e sem integração"
              free={<IcCheck />}
              pro={<IcCheck />}
            />
          </div>

          {/* ── Preço + confiança ── */}
          <h3 className="m-0 mb-[13px] flex items-center gap-[9px] text-[13px] font-semibold tracking-[0.02em]">
            Preço honesto
            <span className="h-px flex-1 bg-border" />
          </h3>
          <div className="mb-1.5 grid grid-cols-1 gap-[22px] lg:grid-cols-[1.2fr_1fr]">
            {/* Preço */}
            <div className="rounded-lg border border-border bg-card px-[26px] py-6 shadow-sm">
              <div className="flex items-baseline gap-2.5">
                <span className="font-mono text-[38px] font-bold tracking-[-0.02em] tabular-nums">{priceLabel}</span>
                <span className="text-[13px] text-muted-foreground">/ mês · por empresa</span>
              </div>
              <p className="mt-2.5 text-[12.5px] text-muted-foreground">
                Conta Azul Numia: <s className="text-muted-foreground/70">R$ [redacted Tier 0]/mês</s> · Copilot for Finance:{' '}
                <s className="text-muted-foreground/70">R$ [redacted Tier 0]/mês</s> ·{' '}
                <b className="text-success">você economiza ~50%</b>
              </p>
              <ul className="mt-[18px] grid list-none gap-[9px] p-0">
                <li className="flex items-start gap-[9px] text-[13px] text-muted-foreground">
                  <Check className="mt-px size-4 flex-none text-success" strokeWidth={3} />
                  Sem fidelidade — cancela quando quiser
                </li>
                <li className="flex items-start gap-[9px] text-[13px] text-muted-foreground">
                  <Check className="mt-px size-4 flex-none text-success" strokeWidth={3} />
                  Custo de IA já incluso (você não paga por uso)
                </li>
                <li className="flex items-start gap-[9px] text-[13px] text-muted-foreground">
                  <Check className="mt-px size-4 flex-none text-success" strokeWidth={3} />
                  {pricing.trialDays} dias pra testar — ativa hoje, decide depois
                </li>
              </ul>
            </div>

            {/* Confiança */}
            <div className="rounded-lg border border-border bg-card px-6 py-[22px] shadow-sm">
              <h4 className="m-0 mb-3.5 text-[13px] font-semibold">Por que confiar</h4>
              <TrustRow
                icon={<Shield className="size-[17px] flex-none text-primary" strokeWidth={2} />}
                title="Seus dados são só seus"
                sub="Isolamento por empresa garantido no núcleo do sistema — ninguém vê o que é seu."
                first
              />
              <TrustRow
                icon={<BadgeCheck className="size-[17px] flex-none text-primary" strokeWidth={2} />}
                title="LGPD por padrão"
                sub="Retenção declarada por tipo de dado. Você decide o que a Jana guarda."
              />
              <TrustRow
                icon={<Lock className="size-[17px] flex-none text-primary" strokeWidth={2} />}
                title="Hospedado no Brasil"
                sub="Infra nacional, custo transparente. Sem dado saindo do país."
              />
            </div>
          </div>
        </div>
      </div>

      {/* ── Footer sticky ── */}
      <footer className="flex shrink-0 items-center gap-4 border-t border-border bg-card/85 px-7 py-3.5 backdrop-blur">
        <div className="text-[13px] text-muted-foreground">
          <b className="text-foreground">Jana Pro</b> ·{' '}
          <span className="font-mono font-semibold text-primary">{priceLabel}</span>/mês ·{' '}
          {pricing.trialDays} dias grátis
        </div>
        <div className="flex-1" />
        <button type="button" onClick={voltar} className={btnGhost}>
          Falar com a Jana sobre o Pro
        </button>
        <button
          type="button"
          onClick={activate}
          disabled={state === 'activating'}
          aria-live="polite"
          className={
            state === 'done'
              ? 'inline-flex items-center gap-2 rounded-md border border-emerald-500 bg-emerald-500 px-5 py-2.5 text-sm font-medium text-white'
              : 'inline-flex items-center gap-2 rounded-md border border-primary bg-primary px-5 py-2.5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-[0.85]'
          }
        >
          {state === 'done' ? (
            <>
              <Check className="size-3.5" strokeWidth={2.5} /> Jana Pro ativo · {pricing.trialDays} dias grátis
            </>
          ) : state === 'activating' ? (
            'Ativando…'
          ) : (
            <>
              <ArrowRight className="size-3.5" /> Ativar Jana Pro
            </>
          )}
        </button>
      </footer>
    </div>
  )
}

function TrustRow({
  icon,
  title,
  sub,
  first,
}: {
  icon: ReactNode
  title: string
  sub: string
  first?: boolean
}) {
  return (
    <div className={`flex items-start gap-[11px] py-2.5 ${first ? '' : 'border-t border-muted'}`}>
      <span className="mt-px">{icon}</span>
      <div>
        <b className="block text-[13px] font-medium">{title}</b>
        <small className="text-[11.5px] leading-[1.45] text-muted-foreground">{sub}</small>
      </div>
    </div>
  )
}

export default function Pro(props: Props) {
  return <ProPage {...props} />
}

Pro.layout = (page: ReactNode) => <AppShellV2 title="Jana Pro — Ativar">{page}</AppShellV2>
