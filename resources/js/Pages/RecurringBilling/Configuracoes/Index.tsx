// Configurações Cobrança Recorrente — Page Inertia v9,75 Onda 8 (read-only stub).
// Charter: ./Index.charter.md
// Visual canon: prototipo-ui/prototipos/recurring/recurring-page.jsx (tab Configurações).
// Refs: ADR 0104 MWART · 0107 visual gate · 0093 multi-tenant Tier 0 · skill inertia-defer-default

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head } from '@inertiajs/react';
import {
  AlertTriangle,
  Banknote,
  Check,
  CheckCircle2,
  Copy,
  ExternalLink,
  FileText,
  Info,
  Plus,
  Receipt,
  Settings,
  Webhook,
  XCircle,
  type LucideIcon,
} from 'lucide-react';
import { useState, type ReactNode } from 'react';

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
    bg: 'bg-sky-50',
    text: 'text-sky-800',
    ring: 'ring-sky-200',
    icon: Info,
    iconColor: 'text-sky-600',
  },
  warn: {
    bg: 'bg-amber-50',
    text: 'text-amber-800',
    ring: 'ring-amber-200',
    icon: AlertTriangle,
    iconColor: 'text-amber-600',
  },
  bad: {
    bg: 'bg-rose-50',
    text: 'text-rose-800',
    ring: 'ring-rose-200',
    icon: XCircle,
    iconColor: 'text-rose-600',
  },
};

const BANCO_BG: Record<Banco, string> = {
  inter: 'bg-orange-100 text-orange-800 ring-orange-200',
  c6: 'bg-zinc-900 text-white ring-zinc-700',
  asaas: 'bg-emerald-100 text-emerald-800 ring-emerald-200',
};

// ────────────────────────────────────────────────────────────────
// PAGE
// ────────────────────────────────────────────────────────────────

export default function ConfiguracoesIndex(props: PageProps) {
  const { regua_dunning, nfe_auto, webhooks } = props;

  return (
    <>
      <Head title="Configurações · Cobrança Recorrente" />

      <div className="min-h-screen bg-zinc-50 p-4 md:p-6">
        {/* ── HEADER ── */}
        <header className="mb-6">
          <div className="flex items-center gap-3">
            <span className="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-700">
              <Settings size={20} />
            </span>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-zinc-900">
                Configurações · cobrança recorrente
              </h1>
              <div className="mt-0.5 text-sm text-zinc-500">
                Gateways de pagamento, régua de dunning, NFe automática e webhooks por gateway.
              </div>
            </div>
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
            <p className="mb-4 text-sm leading-relaxed text-zinc-600">
              {regua_dunning.descricao}
            </p>

            <div className="space-y-2">
              {regua_dunning.retentativas.map((r) => {
                const t = SEVERITY_TOKENS[r.severidade];
                const Icon = t.icon;
                return (
                  <div
                    key={r.ordem}
                    className={`flex items-start gap-3 rounded-xl p-3 ring-1 ${t.bg} ${t.ring}`}
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

            <div className="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-[11px] italic text-zinc-500 ring-1 ring-zinc-100">
              {regua_dunning.editavel_em}
            </div>
          </SectionCard>

          {/* c. NFe AUTO */}
          <SectionCard
            icon={FileText}
            title="NFe-de-boleto-pago automática"
            subtitle="Emissão fiscal disparada automaticamente ao receber pagamento."
          >
            <div className="mb-4 flex items-center justify-between rounded-xl bg-zinc-50 px-4 py-3 ring-1 ring-zinc-200">
              <div className="flex items-center gap-3">
                <span
                  className={`inline-flex h-6 w-11 items-center rounded-full p-0.5 transition-colors ${
                    nfe_auto.ativo ? 'bg-emerald-500' : 'bg-zinc-300'
                  }`}
                >
                  <span
                    className={`inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform ${
                      nfe_auto.ativo ? 'translate-x-5' : 'translate-x-0'
                    }`}
                  />
                </span>
                <div>
                  <div className="text-sm font-medium text-zinc-800">
                    {nfe_auto.ativo ? 'Ativada' : 'Desativada'}
                  </div>
                  <div className="text-[11px] text-zinc-500">
                    Ref. <code className="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] ring-1 ring-zinc-200">{nfe_auto.us_ref}</code>
                  </div>
                </div>
              </div>
              <span className="rounded-full bg-zinc-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-500 ring-1 ring-zinc-200">
                Em breve
              </span>
            </div>

            <p className="text-sm leading-relaxed text-zinc-600">{nfe_auto.descricao}</p>

            <div className="mt-3 rounded-lg bg-zinc-50 px-3 py-2 text-[11px] italic text-zinc-500 ring-1 ring-zinc-100">
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
    </>
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
    <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-zinc-200">
      <header className="flex items-start gap-3 border-b border-zinc-100 px-5 py-4">
        <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-50 text-violet-700">
          <Icon size={16} />
        </span>
        <div className="min-w-0 flex-1">
          <h2 className="text-base font-semibold text-zinc-900">{title}</h2>
          {subtitle && <p className="mt-0.5 text-xs text-zinc-500">{subtitle}</p>}
        </div>
      </header>
      <div className="p-5">{children}</div>
    </section>
  );
}

function GatewaysContent({ gateways }: { gateways: GatewayRow[] }) {
  if (gateways.length === 0) {
    return (
      <div className="rounded-xl border-2 border-dashed border-zinc-200 p-8 text-center">
        <Banknote size={28} className="mx-auto text-zinc-300" />
        <div className="mt-2 text-sm font-medium text-zinc-700">
          Nenhum gateway cadastrado
        </div>
        <div className="mt-1 text-xs text-zinc-500">
          Cadastre uma credencial Inter PJ, C6 ou Asaas para começar a cobrar.
        </div>
        <a
          href="/financeiro/contas-bancarias"
          className="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-violet-700"
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
            className="flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2.5"
          >
            <span
              className={`inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold ring-1 ${BANCO_BG[g.banco]}`}
              title={g.banco_label}
            >
              {g.banco === 'inter' ? 'I' : g.banco === 'c6' ? 'C6' : 'A'}
            </span>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2">
                <div className="truncate text-sm font-semibold text-zinc-900">
                  {g.nome_display || g.banco_label}
                </div>
                {g.ambiente === 'sandbox' && (
                  <span className="rounded-full bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700 ring-1 ring-amber-200">
                    sandbox
                  </span>
                )}
              </div>
              <div className="mt-0.5 truncate text-[11px] text-zinc-500">
                {g.banco_label} · {g.ambiente_label}
              </div>
            </div>
            {g.ativo ? (
              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 ring-1 ring-emerald-200">
                <CheckCircle2 size={11} />
                ativo
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-500 ring-1 ring-zinc-200">
                <XCircle size={11} />
                inativo
              </span>
            )}
          </li>
        ))}
      </ul>

      <a
        href="/financeiro/contas-bancarias"
        className="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 hover:bg-zinc-50"
        title="Cadastro fica em Contas Bancárias (Onda futura move pra cá)"
      >
        <Plus size={14} />
        Adicionar gateway
        <span className="ml-1 rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] text-zinc-500">em breve aqui</span>
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
    <div className="rounded-xl border border-zinc-200 p-3">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <span className="rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-zinc-600 ring-1 ring-zinc-200">
            {webhook.metodo}
          </span>
          <span className="text-sm font-semibold text-zinc-900">{webhook.gateway_label}</span>
        </div>
        <a
          href={webhook.docs_link}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-flex items-center gap-1 text-[11px] font-medium text-violet-700 hover:underline"
        >
          docs
          <ExternalLink size={10} />
        </a>
      </div>

      <div className="mt-1 text-xs text-zinc-500">{webhook.rotulo}</div>

      <div className="mt-2 flex items-center gap-2">
        <code className="flex-1 truncate rounded-lg bg-zinc-50 px-3 py-2 font-mono text-xs text-zinc-700 ring-1 ring-zinc-200">
          {webhook.url}
        </code>
        <button
          type="button"
          onClick={handleCopy}
          className={`inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-medium transition ${
            copied
              ? 'bg-emerald-600 text-white'
              : 'bg-white text-zinc-700 ring-1 ring-zinc-200 hover:bg-zinc-50'
          }`}
          title="Copiar URL pra área de transferência"
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? 'Copiado!' : 'Copiar'}
        </button>
      </div>

      <div className="mt-2 rounded-lg bg-zinc-50 px-3 py-2 text-[11px] leading-relaxed text-zinc-500 ring-1 ring-zinc-100">
        <span className="font-semibold text-zinc-600">Autenticação:</span> {webhook.auth}
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
        <li key={i} className="flex items-center gap-3 rounded-xl border border-zinc-200 px-3 py-2.5">
          <div className="h-8 w-8 animate-pulse rounded-lg bg-zinc-200" />
          <div className="flex-1 space-y-1">
            <div className="h-3 w-32 animate-pulse rounded bg-zinc-200" />
            <div className="h-2 w-48 animate-pulse rounded bg-zinc-100" />
          </div>
          <div className="h-5 w-14 animate-pulse rounded-full bg-zinc-100" />
        </li>
      ))}
    </ul>
  );
}

ConfiguracoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
