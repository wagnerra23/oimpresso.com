// Planos — Criar (v9,75 Onda 6 + Onda 21 refinos teclado).
// Charter: ./Create.charter.md
// Refs: ADR 0104 MWART · 0093 multi-tenant Tier 0 · US-RB-001
// Onda 21 v9,75 — atalhos teclado: Esc cancela e volta, Ctrl/Cmd+Enter submit.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect, useRef, type FormEvent, type ReactNode } from 'react';

type Ciclo = 'monthly' | 'quarterly' | 'semiannual' | 'yearly' | 'custom';
type FiscalType = 'nfe' | 'nfse' | 'none';

interface Defaults {
  ciclo: Ciclo;
  trial_days: number;
  ativo: boolean;
  fiscal_type: FiscalType;
}

interface PageProps {
  defaults: Defaults;
  [key: string]: unknown;
}

const CICLO_OPTIONS: Array<{ value: Ciclo; label: string }> = [
  { value: 'monthly',    label: 'Mensal' },
  { value: 'quarterly',  label: 'Trimestral' },
  { value: 'semiannual', label: 'Semestral' },
  { value: 'yearly',     label: 'Anual' },
  { value: 'custom',     label: 'Customizado (dias)' },
];

const FISCAL_OPTIONS: Array<{ value: FiscalType; label: string }> = [
  { value: 'none', label: 'Não emite nota fiscal' },
  { value: 'nfe',  label: 'NFe (produto)' },
  { value: 'nfse', label: 'NFS-e (serviço)' },
];

export default function PlanosCreate({ defaults }: PageProps) {
  const form = useForm({
    name: '',
    slug: '',
    descricao_curta: '',
    description: '',
    valor: 0,
    ciclo: defaults.ciclo,
    ciclo_dias: 30,
    trial_days: defaults.trial_days,
    ativo: defaults.ativo,
    fiscal_type: defaults.fiscal_type,
    fiscal_cfop: '',
    fiscal_servico: '',
  });

  // Onda 21 v9,75 — Ref pro form pra trigger submit programático via atalho.
  const formRef = useRef<HTMLFormElement>(null);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    form.post('/recurring-billing/planos', {
      preserveScroll: true,
    });
  }

  // Onda 21 v9,75 — Atalhos teclado: Esc cancela e volta, Ctrl/Cmd+Enter submit.
  // Tour não é mostrado em Create — TOUR_DONE_KEY compartilhado com Index resolve
  // (se user passou pelo Index já marcou done; se não, mostra ao voltar).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        const t = e.target as HTMLElement;
        const inField = t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.isContentEditable;
        if (inField) {
          (t as HTMLInputElement).blur?.();
          return;
        }
        e.preventDefault();
        router.visit('/recurring-billing/planos');
      } else if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        formRef.current?.requestSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const showCicloDias = form.data.ciclo === 'custom';
  const showCfop      = form.data.fiscal_type === 'nfe';
  const showServico   = form.data.fiscal_type === 'nfse';

  return (
    <>
      <Head title="Novo plano · Cobrança Recorrente" />

      <div className="min-h-screen bg-zinc-50 p-4 md:p-6">
        {/* HEADER */}
        <header className="mb-4">
          <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
              <Link
                href="/recurring-billing/planos"
                className="inline-flex items-center gap-1 text-xs font-medium text-violet-700 hover:text-violet-900"
              >
                <ArrowLeft size={12} />
                Voltar pra Planos
              </Link>
              <h1 className="mt-1 text-2xl font-bold tracking-tight text-zinc-900">
                Novo plano
              </h1>
              <div className="mt-1 font-mono text-[11px] uppercase tracking-wider text-zinc-500">
                CADASTRAR · COBRANÇA RECORRENTE
              </div>
            </div>
          </div>
        </header>

        {/* FORM */}
        <form
          ref={formRef}
          onSubmit={handleSubmit}
          className="space-y-4"
        >
          {/* Card 1 — Identificação */}
          <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 className="mb-3 text-sm font-semibold text-zinc-900">Identificação</h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <Field
                label="Nome do plano"
                required
                error={form.errors.name}
                hint="Ex: Plano Anual Pro"
              >
                <input
                  type="text"
                  value={form.data.name}
                  onChange={(e) => form.setData('name', e.target.value)}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                  required
                  maxLength={150}
                />
              </Field>

              <Field
                label="Slug"
                error={form.errors.slug}
                hint="Gerado automaticamente do nome se vazio (apenas a-z 0-9 e hifens)"
              >
                <input
                  type="text"
                  value={form.data.slug}
                  onChange={(e) => form.setData('slug', e.target.value.toLowerCase())}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                  maxLength={80}
                  placeholder="plano-anual-pro"
                />
              </Field>

              <Field
                label="Descrição curta"
                error={form.errors.descricao_curta}
                hint="Aparece em listas (máx 200 caracteres)"
              >
                <input
                  type="text"
                  value={form.data.descricao_curta}
                  onChange={(e) => form.setData('descricao_curta', e.target.value)}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                  maxLength={200}
                />
              </Field>

              <Field
                label="Descrição completa"
                error={form.errors.description}
                hint="Detalhe — visível na contratação"
              >
                <textarea
                  value={form.data.description}
                  onChange={(e) => form.setData('description', e.target.value)}
                  rows={2}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                  maxLength={2000}
                />
              </Field>
            </div>
          </section>

          {/* Card 2 — Cobrança */}
          <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 className="mb-3 text-sm font-semibold text-zinc-900">Cobrança</h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <Field
                label="Valor (R$)"
                required
                error={form.errors.valor}
                hint="Por ciclo"
              >
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={form.data.valor}
                  onChange={(e) => form.setData('valor', parseFloat(e.target.value) || 0)}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm tabular-nums focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                  required
                />
              </Field>

              <Field
                label="Ciclo"
                required
                error={form.errors.ciclo}
              >
                <select
                  value={form.data.ciclo}
                  onChange={(e) => form.setData('ciclo', e.target.value as Ciclo)}
                  className="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                  {CICLO_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </select>
              </Field>

              {showCicloDias && (
                <Field
                  label="Ciclo (dias)"
                  required
                  error={form.errors.ciclo_dias}
                  hint="1 a 365"
                >
                  <input
                    type="number"
                    min="1"
                    max="365"
                    value={form.data.ciclo_dias}
                    onChange={(e) => form.setData('ciclo_dias', parseInt(e.target.value, 10) || 0)}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm tabular-nums focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                  />
                </Field>
              )}

              <Field
                label="Trial (dias)"
                error={form.errors.trial_days}
                hint="0 a 90 — período grátis antes do primeiro débito"
              >
                <input
                  type="number"
                  min="0"
                  max="90"
                  value={form.data.trial_days}
                  onChange={(e) => form.setData('trial_days', parseInt(e.target.value, 10) || 0)}
                  className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm tabular-nums focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                />
              </Field>
            </div>

            <div className="mt-4">
              <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-zinc-700">
                <input
                  type="checkbox"
                  checked={form.data.ativo}
                  onChange={(e) => form.setData('ativo', e.target.checked)}
                  className="h-4 w-4 rounded border-zinc-300 text-violet-600 focus:ring-violet-500"
                />
                Plano ativo (disponível pra novas assinaturas)
              </label>
            </div>
          </section>

          {/* Card 3 — Fiscal */}
          <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-zinc-200">
            <h2 className="mb-3 text-sm font-semibold text-zinc-900">Emissão fiscal</h2>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
              <Field
                label="Tipo"
                error={form.errors.fiscal_type}
              >
                <select
                  value={form.data.fiscal_type}
                  onChange={(e) => form.setData('fiscal_type', e.target.value as FiscalType)}
                  className="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                >
                  {FISCAL_OPTIONS.map((o) => (
                    <option key={o.value} value={o.value}>{o.label}</option>
                  ))}
                </select>
              </Field>

              {showCfop && (
                <Field
                  label="CFOP"
                  required
                  error={form.errors.fiscal_cfop}
                  hint="Código fiscal NFe (ex: 5933)"
                >
                  <input
                    type="text"
                    value={form.data.fiscal_cfop}
                    onChange={(e) => form.setData('fiscal_cfop', e.target.value)}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm tabular-nums focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    maxLength={8}
                  />
                </Field>
              )}

              {showServico && (
                <Field
                  label="Código serviço"
                  required
                  error={form.errors.fiscal_servico}
                  hint="Código NFS-e municipal"
                >
                  <input
                    type="text"
                    value={form.data.fiscal_servico}
                    onChange={(e) => form.setData('fiscal_servico', e.target.value)}
                    className="w-full rounded-lg border border-zinc-300 px-3 py-2 font-mono text-sm tabular-nums focus:border-violet-500 focus:outline-none focus:ring-1 focus:ring-violet-500"
                    maxLength={8}
                  />
                </Field>
              )}
            </div>
          </section>

          {/* Ações — Onda 21 v9,75 kbd hints */}
          <div className="flex items-center justify-end gap-2 pt-2">
            <Link
              href="/recurring-billing/planos"
              className="inline-flex items-center gap-1.5 rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 hover:bg-zinc-50"
            >
              Cancelar
              <kbd className="rounded bg-zinc-100 px-1 text-[10px] font-mono text-zinc-500 ring-1 ring-zinc-200">Esc</kbd>
            </Link>
            <button
              type="submit"
              disabled={form.processing}
              className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700 disabled:opacity-50"
            >
              <Save size={14} />
              {form.processing ? 'Salvando…' : 'Salvar plano'}
              <kbd className="ml-1 rounded bg-violet-700 px-1 text-[10px] font-mono">⌘↵</kbd>
            </button>
          </div>
        </form>
      </div>
    </>
  );
}

// ────────────────────────────────────────────────────────────────
// Field wrapper — label + hint + error
// ────────────────────────────────────────────────────────────────

function Field({
  label,
  required,
  error,
  hint,
  children,
}: {
  label: string;
  required?: boolean;
  error?: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <div>
      <label className="mb-1 block text-xs font-medium text-zinc-700">
        {label} {required && <span className="text-rose-600">*</span>}
      </label>
      {children}
      {hint && !error && <div className="mt-1 text-[11px] text-zinc-500">{hint}</div>}
      {error && <div className="mt-1 text-[11px] font-medium text-rose-600">{error}</div>}
    </div>
  );
}

PlanosCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
