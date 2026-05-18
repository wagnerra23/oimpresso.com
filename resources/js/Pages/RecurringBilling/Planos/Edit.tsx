// Planos — Editar (v9,75 Onda 6).
// Charter: ./Edit.charter.md
// Refs: ADR 0104 MWART · 0093 multi-tenant Tier 0 · US-RB-001

import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { type FormEvent, type ReactNode } from 'react';

type Ciclo = 'monthly' | 'quarterly' | 'semiannual' | 'yearly' | 'custom';
type FiscalType = 'nfe' | 'nfse' | 'none';

interface PlanData {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  descricao_curta: string | null;
  valor: number;
  ciclo: Ciclo;
  ciclo_dias: number | null;
  trial_days: number;
  ativo: boolean;
  fiscal_type: FiscalType;
  fiscal_cfop: string | null;
  fiscal_servico: string | null;
}

interface PageProps {
  plan: PlanData;
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

export default function PlanosEdit({ plan }: PageProps) {
  const form = useForm({
    name: plan.name,
    slug: plan.slug,
    descricao_curta: plan.descricao_curta ?? '',
    description: plan.description ?? '',
    valor: plan.valor,
    ciclo: plan.ciclo,
    ciclo_dias: plan.ciclo_dias ?? 30,
    trial_days: plan.trial_days,
    ativo: plan.ativo,
    fiscal_type: plan.fiscal_type,
    fiscal_cfop: plan.fiscal_cfop ?? '',
    fiscal_servico: plan.fiscal_servico ?? '',
  });

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    form.put(`/recurring-billing/planos/${plan.id}`, {
      preserveScroll: true,
    });
  }

  const showCicloDias = form.data.ciclo === 'custom';
  const showCfop      = form.data.fiscal_type === 'nfe';
  const showServico   = form.data.fiscal_type === 'nfse';
  const slugChanged   = form.data.slug !== plan.slug;

  return (
    <>
      <Head title={`Editar ${plan.name} · Planos`} />

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
                Editar plano
              </h1>
              <div className="mt-1 font-mono text-[11px] uppercase tracking-wider text-zinc-500">
                {plan.name} · #{plan.id}
              </div>
            </div>
          </div>
        </header>

        {/* FORM */}
        <form
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
                hint={
                  slugChanged
                    ? '⚠️ alterar slug pode quebrar URLs/integrações que referenciam o plano antigo'
                    : 'apenas a-z 0-9 e hifens'
                }
              >
                <input
                  type="text"
                  value={form.data.slug}
                  onChange={(e) => form.setData('slug', e.target.value.toLowerCase())}
                  className={`w-full rounded-lg border px-3 py-2 font-mono text-sm focus:outline-none focus:ring-1 ${
                    slugChanged
                      ? 'border-amber-400 focus:border-amber-500 focus:ring-amber-500'
                      : 'border-zinc-300 focus:border-violet-500 focus:ring-violet-500'
                  }`}
                  maxLength={80}
                />
              </Field>

              <Field
                label="Descrição curta"
                error={form.errors.descricao_curta}
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
                hint="0 a 90"
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

          {/* Ações */}
          <div className="flex items-center justify-end gap-2 pt-2">
            <Link
              href="/recurring-billing/planos"
              className="rounded-lg bg-white px-4 py-2 text-sm font-medium text-zinc-700 shadow-sm ring-1 ring-zinc-200 hover:bg-zinc-50"
            >
              Cancelar
            </Link>
            <button
              type="submit"
              disabled={form.processing}
              className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-violet-700 disabled:opacity-50"
            >
              <Save size={14} />
              {form.processing ? 'Salvando…' : 'Salvar alterações'}
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

PlanosEdit.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
