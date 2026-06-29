// Configurações Cobrança Recorrente — Page Inertia v9,75 Onda 8 (read-only stub).
// Onda 23 v9,75 — refinos Cowork: Tour onboarding (13) + CheatSheet `?` (18) + atalhos teclado.
// Charter: ./Index.charter.md
// Visual canon: prototipo-ui/prototipos/recurring/recurring-page.jsx (tab Configurações).
// Refs: ADR 0104 MWART · 0107 visual gate · 0093 multi-tenant Tier 0 · skill inertia-defer-default

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head } from '@inertiajs/react';
// Onda 23 v9,75 — sub-components Cowork refinos.
// TOUR_DONE_KEY compartilhada: user que fez tour no Index não vê aqui (e vice-versa).
// TourConfiguracoes é local: steps específicos da página (Gateways/Dunning/NFe/Webhooks).
import { TOUR_DONE_KEY } from '../_components/TourOnboarding';
import CheatSheet from '../_components/CheatSheet';
import {
  AlertTriangle,
  Banknote,
  Check,
  CheckCircle2,
  ChevronLeft,
  ChevronRight,
  Copy,
  ExternalLink,
  FileText,
  Info,
  Plus,
  Receipt,
  Settings,
  Webhook,
  X,
  XCircle,
  type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';

// ────────────────────────────────────────────────────────────────
// TIPOS — espelham ConfiguracoesController PHP
// ────────────────────────────────────────────────────────────────

type Severity = 'info' | 'warn' | 'bad';
type Banco = 'inter' | 'c6' | 'asaas';
type Ambiente = 'production' | 'sandbox';

interface ReguaItem {
  ordem: number;
  dias: number;
  rotulo: string;
  descricao: string;
  severidade: Severity;
}

interface Regua {
  descricao: string;
  retentativas: ReguaItem[];
  editavel_em: string;
}

interface NfeAuto {
  ativo: boolean;
  descricao: string;
  us_ref: string;
  editavel_em: string;
}

interface WebhookRow {
  gateway: string;
  gateway_label: string;
  rotulo: string;
  metodo: string;
  url: string;
  auth: string;
  docs_link: string;
}

interface GatewayRow {
  id: number;
  banco: Banco;
  banco_label: string;
  ambiente: Ambiente;
  ambiente_label: string;
  ativo: boolean;
  nome_display: string | null;
  conta_bancaria_id: number | null;
  criado_em: string | null;
}

interface PageProps {
  regua_dunning: Regua;
  nfe_auto: NfeAuto;
  webhooks: WebhookRow[];
  gateways?: GatewayRow[];
}

// ────────────────────────────────────────────────────────────────
// HELPERS
// ────────────────────────────────────────────────────────────────

const SEVERITY_TOKENS: Record<
  Severity,
  { bg: string; text: string; ring: string; icon: LucideIcon; iconColor: string }
> = {
  info: {
    bg: 'bg-info-soft',
    text: 'text-info-fg',
    ring: 'ring-info/20',
    icon: Info,
    iconColor: 'text-info',
  },
  warn: {
    bg: 'bg-warning-soft',
    text: 'text-warning-fg',
    ring: 'ring-warning/20',
    icon: AlertTriangle,
    iconColor: 'text-warning',
  },
  bad: {
    bg: 'bg-destructive-soft',
    text: 'text-destructive-fg',
    ring: 'ring-destructive/20',
    icon: XCircle,
    iconColor: 'text-destructive',
  },
};

const BANCO_BG: Record<Banco, string> = {
  inter: 'bg-orange-100 text-orange-800 ring-orange-200',
  c6: 'bg-stone-900 text-white ring-stone-700',
  asaas: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
};

// ────────────────────────────────────────────────────────────────
// PAGE
// ────────────────────────────────────────────────────────────────

export default function ConfiguracoesIndex(props: PageProps) {
  const { regua_dunning, nfe_auto, webhooks } = props;
  // Onda 23 v9,75 — overlays modal state (Tour 1ª vez + CheatSheet `?`)
  const [showTour, setShowTour] = useState(false);
  const [showCheatsheet, setShowCheatsheet] = useState(false);

  // Onda 23 v9,75 — Tour onboarding 1ª vez (TOUR_DONE_KEY compartilhada com Index.tsx)
  useEffect(() => {
    try {
      if (localStorage.getItem(TOUR_DONE_KEY) !== '1') {
        setShowTour(true);
      }
    } catch {
      // silently ignore (private mode etc)
    }
  }, []);

  // Onda 23 v9,75 — atalhos teclado básicos (? abre CheatSheet · Esc fecha modais).
  // Ctrl/Cmd+C deixado nativo: botões "Copiar" por webhook já existem (WebhookCard) e
  // browser default permite copiar texto selecionado do <code> sem interceptação.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const t = e.target as HTMLElement;
      const inField = t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable;
      if (inField && e.key !== 'Escape') return;
      if (e.key === '?') {
        e.preventDefault();
        setShowCheatsheet(true);
      } else if (e.key === 'Escape') {
        setShowCheatsheet(false);
        setShowTour(false);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  return (
    <>
      <Head title="Configurações · Cobrança Recorrente" />

      <div className="min-h-screen bg-stone-50 p-4 md:p-6">
        {/* ── HEADER ── */}
        <header className="mb-6">
          <div className="flex items-start justify-between gap-3">
            <div className="flex items-center gap-3">
              <span className="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                <Settings size={20} />
              </span>
              <div>
                <h1 className="text-2xl font-semibold tracking-tight text-stone-900">
                  Configurações · cobrança recorrente
                </h1>
                <div className="mt-0.5 text-sm text-stone-500">
                  Gateways de pagamento, régua de dunning, NFe automática e webhooks por gateway.
                </div>
              </div>
            </div>
            {/* Onda 23 v9,75 — hint atalho `?` (CheatSheet) */}
            <button
              type="button"
              onClick={() => setShowCheatsheet(true)}
              title="Ver atalhos de teclado"
              className="inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-2.5 py-1.5 text-xs font-medium text-stone-600 hover:bg-stone-50"
            >
              atalhos
              <kbd className="rounded bg-stone-100 px-1.5 py-0.5 font-mono text-[10px] text-stone-700 ring-1 ring-stone-200">?</kbd>
            </button>
          </div>
        </header>

        {/* ── GRID 4 SEÇÕES (cards verticais, mobile-first 1col, ≥lg 2cols) ── */}
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
          {/* a. GATEWAYS */}
          <SectionCard
            icon={Banknote}
            title="Gateways de boleto/pix"
            subtitle="Credenciais ativas e sandbox cadastradas neste business."
          >
            <Deferred data="gateways" fallback={<GatewaysSkeleton />}>
              <GatewaysContent gateways={props.gateways || []} />
            </Deferred>
          </SectionCard>

          {/* b. RÉGUA DUNNING */}
          <SectionCard
            icon={Receipt}
            title="Régua de dunning (cobrança)"
            subtitle="Cronograma de retentativas quando uma fatura falha no pagamento."
          >
            <p className="mb-4 text-sm leading-relaxed text-stone-600">
              {regua_dunning.descricao}
            </p>

            <div className="space-y-2">
              {regua_dunning.retentativas.map((r) => {
                const t = SEVERITY_TOKENS[r.severidade];
                const Icon = t.icon;
                return (
                  <div
                    key={r.ordem}
                    className={`flex items-start gap-3 rounded-lg p-3 ring-1 ${t.bg} ${t.ring}`}
                  >
                    <span className={`mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white ring-1 ${t.ring}`}>
                      <Icon size={14} className={t.iconColor} />
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center justify-between gap-2">
                        <div className={`text-sm font-semibold ${t.text}`}>{r.rotulo}</div>
                        <span className={`shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-medium tabular-nums ring-1 ${t.text} ${t.ring}`}>
                          +{r.dias}d
                        </span>
                      </div>
                      <p className={`mt-0.5 text-xs leading-relaxed ${t.text}`}>{r.descricao}</p>
                    </div>
                  </div>
                );
              })}
            </div>

            <div className="mt-3 rounded-lg bg-stone-50 px-3 py-2 text-[11px] italic text-stone-500 ring-1 ring-stone-100">
              {regua_dunning.editavel_em}
            </div>
          </SectionCard>

          {/* c. NFe AUTO */}
          <SectionCard
            icon={FileText}
            title="NFe-de-boleto-pago automática"
            subtitle="Emissão fiscal disparada automaticamente ao receber pagamento."
          >
            <div className="mb-4 flex items-center justify-between rounded-lg bg-stone-50 px-4 py-3 ring-1 ring-stone-200">
              <div className="flex items-center gap-3">
                <span
                  className={`inline-flex h-6 w-11 items-center rounded-full p-0.5 transition-colors ${
                    nfe_auto.ativo ? 'bg-success' : 'bg-stone-300'
                  }`}
                >
                  <span
                    className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform ${
                      nfe_auto.ativo ? 'translate-x-5' : 'translate-x-0'
                    }`}
                  />
                </span>
                <div>
                  <div className="text-sm font-medium text-stone-800">
                    {nfe_auto.ativo ? 'Ativada' : 'Desativada'}
                  </div>
                  <div className="text-[11px] text-stone-500">
                    Ref. <code className="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] ring-1 ring-stone-200">{nfe_auto.us_ref}</code>
                  </div>
                </div>
              </div>
              <span className="rounded-full bg-stone-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-stone-500 ring-1 ring-stone-200">
                Em breve
              </span>
            </div>

            <p className="text-sm leading-relaxed text-stone-600">{nfe_auto.descricao}</p>

            <div className="mt-3 rounded-lg bg-stone-50 px-3 py-2 text-[11px] italic text-stone-500 ring-1 ring-stone-100">
              {nfe_auto.editavel_em}
            </div>
          </SectionCard>

          {/* d. WEBHOOKS */}
          <SectionCard
            icon={Webhook}
            title="Webhooks"
            subtitle="URLs canônicas pra colar no painel admin do gateway."
          >
            <div className="space-y-3">
              {webhooks.map((w) => (
                <WebhookCard key={w.gateway} webhook={w} />
              ))}
            </div>
          </SectionCard>
        </div>
      </div>

      {/* Onda 23 v9,75 — Tour onboarding 1ª vez (4 steps Configurações) + CheatSheet (?) */}
      {showTour && <TourConfiguracoes onClose={() => setShowTour(false)} />}
      {showCheatsheet && <CheatSheet onClose={() => setShowCheatsheet(false)} />}
    </>
  );
}

// ────────────────────────────────────────────────────────────────
// TOUR INLINE — 4 steps específicos Configurações (Onda 23 v9,75)
// TOUR_DONE_KEY compartilhada com Index.tsx (user faz tour 1× pra
// toda área Cobrança Recorrente, não 1 vez por sub-rota).
// ────────────────────────────────────────────────────────────────

const TOUR_STEPS_CONFIG: Array<{ title: string; body: string }> = [
  {
    title: 'Gateways de pagamento',
    body: 'Configuram quais bancos recebem cobrança — Inter PJ, C6 Bank ou Asaas. Sandbox = teste, production = real.',
  },
  {
    title: 'Régua de dunning',
    body: 'Automatiza retentativas quando fatura falha: 3 dias (lembrete), 7 dias (alerta), 15 dias (escalação).',
  },
  {
    title: 'NFe automática (US-RB-044)',
    body: 'Emite NFe automaticamente ao receber InvoicePaid — Listener via NfeBrasil. Liga/desliga aqui em breve.',
  },
  {
    title: 'Webhooks por gateway',
    body: 'URLs canônicas pra colar no painel admin do banco/gateway. Botão "Copiar" duplica a URL para área de transferência.',
  },
];

function TourConfiguracoes({ onClose }: { onClose: () => void }) {
  const [step, setStep] = useState(0);

  function close(setDone: boolean) {
    if (setDone) {
      try {
        localStorage.setItem(TOUR_DONE_KEY, '1');
      } catch {
        /* ignore */
      }
    }
    onClose();
  }

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') close(false);
      else if (e.key === 'ArrowRight' && step < TOUR_STEPS_CONFIG.length - 1) setStep(step + 1);
      else if (e.key === 'ArrowLeft' && step > 0) setStep(step - 1);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step]);

  const cur = TOUR_STEPS_CONFIG[step] ?? TOUR_STEPS_CONFIG[0]!;
  const isLast = step === TOUR_STEPS_CONFIG.length - 1;

  return (
    <div
      role="dialog"
      aria-modal="true"
      className="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/30 backdrop-blur-sm"
    >
      <div className="w-full max-w-md rounded-lg bg-white shadow-2xl ring-1 ring-stone-200">
        <header className="flex items-center justify-between border-b border-stone-100 px-4 py-3">
          <div className="flex items-center gap-2 text-xs text-stone-500">
            <span className="rounded bg-primary/10 px-2 py-0.5 font-semibold text-primary">
              {step + 1}/{TOUR_STEPS_CONFIG.length}
            </span>
            <span>Tour · Configurações</span>
          </div>
          <button
            type="button"
            onClick={() => close(false)}
            aria-label="Fechar"
            className="rounded p-1 hover:bg-stone-100"
          >
            <X size={14} className="text-stone-500" />
          </button>
        </header>
        <div className="px-6 py-6">
          <h3 className="text-lg font-bold text-stone-900">{cur.title}</h3>
          <p className="mt-2 text-sm text-stone-600">{cur.body}</p>
        </div>
        <footer className="flex items-center justify-between gap-2 border-t border-stone-100 px-4 py-3">
          <button
            type="button"
            onClick={() => close(true)}
            className="text-xs text-stone-500 hover:text-stone-700"
          >
            Não mostrar mais
          </button>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setStep(Math.max(0, step - 1))}
              disabled={step === 0}
              className="inline-flex items-center gap-1 rounded-lg border border-stone-200 px-3 py-1.5 text-xs text-stone-700 hover:bg-stone-50 disabled:opacity-40"
            >
              <ChevronLeft size={12} /> Anterior
            </button>
            {!isLast && (
              <button
                type="button"
                onClick={() => setStep(step + 1)}
                className="inline-flex items-center gap-1 rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary"
              >
                Próximo <ChevronRight size={12} />
              </button>
            )}
            {isLast && (
              <button
                type="button"
                onClick={() => close(true)}
                className="rounded-lg bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary"
              >
                Começar
              </button>
            )}
          </div>
        </footer>
      </div>
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// SUB-COMPONENTES
// ────────────────────────────────────────────────────────────────

function SectionCard({
  icon: Icon,
  title,
  subtitle,
  children,
}: {
  icon: LucideIcon;
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  return (
    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-stone-200">
      <header className="flex items-start gap-3 border-b border-stone-100 px-5 py-4">
        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Icon size={16} />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="text-base font-semibold text-stone-900">{title}</h2>
          {subtitle && <p className="mt-0.5 text-xs text-stone-500">{subtitle}</p>}
        </div>
      </header>
      <div className="p-5">{children}</div>
    </section>
  );
}

function GatewaysContent({ gateways }: { gateways: GatewayRow[] }) {
  if (gateways.length === 0) {
    return (
      <div className="rounded-lg border-2 border-dashed border-stone-200 p-8 text-center">
        <Banknote size={28} className="mx-auto text-stone-300" />
        <div className="mt-2 text-sm font-medium text-stone-700">
          Nenhum gateway cadastrado
        </div>
        <div className="mt-1 text-xs text-stone-500">
          Cadastre uma credencial Inter PJ, C6 ou Asaas para começar a cobrar.
        </div>
        <a
          href="/financeiro/contas-bancarias"
          className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary"
        >
          <Plus size={14} />
          Adicionar gateway
        </a>
      </div>
    );
  }

  return (
    <div>
      <ul className="space-y-2">
        {gateways.map((g) => (
          <li
            key={g.id}
            className="flex items-center gap-3 rounded-lg border border-stone-200 px-3 py-2.5"
          >
            <span
              className={`inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold ring-1 ${BANCO_BG[g.banco]}`}
              title={g.banco_label}
            >
              {g.banco === 'inter' ? 'I' : g.banco === 'c6' ? 'C6' : 'A'}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <div className="truncate text-sm font-semibold text-stone-900">
                  {g.nome_display || g.banco_label}
                </div>
                {g.ambiente === 'sandbox' && (
                  <span className="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700 ring-1 ring-amber-200">
                    sandbox
                  </span>
                )}
              </div>
              <div className="mt-0.5 truncate text-[11px] text-stone-500">
                {g.banco_label} · {g.ambiente_label}
              </div>
            </div>
            {g.ativo ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-success-soft px-2 py-0.5 text-[11px] font-medium text-success-fg ring-1 ring-success/20">
                <CheckCircle2 size={11} />
                ativo
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 rounded-full bg-stone-100 px-2 py-0.5 text-[11px] font-medium text-stone-500 ring-1 ring-stone-200">
                <XCircle size={11} />
                inativo
              </span>
            )}
          </li>
        ))}
      </ul>

      <a
        href="/financeiro/contas-bancarias"
        className="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-stone-200 bg-white px-3 py-1.5 text-sm font-medium text-stone-700 hover:bg-stone-50"
        title="Cadastro fica em Contas Bancárias (Onda futura move pra cá)"
      >
        <Plus size={14} />
        Adicionar gateway
        <span className="ml-1 rounded bg-stone-100 px-1.5 py-0.5 text-[10px] text-stone-500">em breve aqui</span>
      </a>
    </div>
  );
}

function WebhookCard({ webhook }: { webhook: WebhookRow }) {
  const [copied, setCopied] = useState(false);

  function handleCopy() {
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard
        .writeText(webhook.url)
        .then(() => {
          setCopied(true);
          setTimeout(() => setCopied(false), 1500);
        })
        .catch(() => {
          // fallback silencioso — Wagner pode copiar manualmente
        });
    }
  }

  return (
    <div className="rounded-lg border border-stone-200 p-3">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <span className="rounded bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-stone-600 ring-1 ring-stone-200">
            {webhook.metodo}
          </span>
          <span className="text-sm font-semibold text-stone-900">{webhook.gateway_label}</span>
        </div>
        <a
          href={webhook.docs_link}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-[11px] font-medium text-primary hover:underline"
        >
          docs
          <ExternalLink size={10} />
        </a>
      </div>

      <div className="mt-1 text-xs text-stone-500">{webhook.rotulo}</div>

      <div className="mt-2 flex items-center gap-2">
        <code className="flex-1 truncate rounded-lg bg-stone-50 px-3 py-2 font-mono text-xs text-stone-700 ring-1 ring-stone-200">
          {webhook.url}
        </code>
        <button
          type="button"
          onClick={handleCopy}
          className={`inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition ${
            copied
              ? 'bg-success text-white'
              : 'bg-white text-stone-700 ring-1 ring-stone-200 hover:bg-stone-50'
          }`}
          title="Copiar URL pra área de transferência"
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? 'Copiado!' : 'Copiar'}
        </button>
      </div>

      <div className="mt-2 rounded-lg bg-stone-50 px-3 py-2 text-[11px] leading-relaxed text-stone-500 ring-1 ring-stone-100">
        <span className="font-semibold text-stone-600">Autenticação:</span> {webhook.auth}
      </div>
    </div>
  );
}

// ────────────────────────────────────────────────────────────────
// SKELETONS (Inertia::defer fallback)
// ────────────────────────────────────────────────────────────────

function GatewaysSkeleton() {
  return (
    <ul className="space-y-2">
      {Array.from({ length: 3 }).map((_, i) => (
        <li key={i} className="flex items-center gap-3 rounded-lg border border-stone-200 px-3 py-2.5">
          <div className="h-8 w-8 animate-pulse rounded-lg bg-stone-200" />
          <div className="flex-1 space-y-1">
            <div className="h-3 w-32 animate-pulse rounded bg-stone-200" />
            <div className="h-2 w-48 animate-pulse rounded bg-stone-100" />
          </div>
          <div className="h-5 w-14 animate-pulse rounded-full bg-stone-100" />
        </li>
      ))}
    </ul>
  );
}

ConfiguracoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
