// @memcofre tela=/financeiro/configuracoes/contador module=Financeiro
// Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor contadores parceiros (Fase 1 MVP).
// Charter Onda 32: visual-comparison.md + charter.md pendentes (MWART F0 stub).

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, router, usePage } from '@inertiajs/react';
import { useState, FormEvent, ReactNode } from 'react';

interface AccessRow {
  id: number;
  advisor_nome: string | null;
  advisor_email: string | null;
  advisor_cnpj_mascarado: string | null;
  granted_at: string | null;
  granted_at_label: string | null;
  can_view_unificado: boolean;
  can_view_reports: boolean;
  has_consent: boolean;
}

interface Props {
  accesses: AccessRow[];
  breadcrumbs: Array<{ label: string; href: string | null }>;
}

interface FlashShape {
  success?: string;
  error?: string;
  info?: string;
}

function Contador({ accesses, breadcrumbs }: Props) {
  const { props } = usePage<{ flash?: FlashShape }>();
  const flash = props.flash ?? {};
  const [showForm, setShowForm] = useState(false);

  const form = useForm({
    cnpj_contador: '',
    nome: '',
    email: '',
    telefone: '',
    consent_lgpd: false,
    can_view_unificado: true,
    can_view_reports: true,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/financeiro/configuracoes/contador/grant', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        setShowForm(false);
      },
    });
  };

  const revoke = (accessId: number, nome: string | null) => {
    if (!confirm(`Revogar acesso de ${nome ?? 'contador'}? Ele perderá acesso imediatamente.`)) return;
    router.delete(`/financeiro/configuracoes/contador/${accessId}`, {
      preserveScroll: true,
    });
  };

  return (
    <div className="fin-curadoria p-6 max-w-5xl mx-auto space-y-6">
      <header className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>Contador Parceiro <span className="fin-hero-title-sub">· Acesso somente leitura ao Financeiro</span></h1>
          <p>
            Adicione o contador da sua empresa pra ele acompanhar relatórios e visão unificada
            num portal próprio. Zero credenciais compartilhadas. Revogue a qualquer momento.
          </p>
        </div>
        <div className="os-page-h-r fin-page-h-r">
          {!showForm && (
            <button type="button" className="os-btn primary" onClick={() => setShowForm(true)}>
              + Adicionar contador
            </button>
          )}
        </div>
      </header>

      {flash.success && (
        <div className="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
          {flash.success}
        </div>
      )}
      {flash.error && (
        <div className="rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-900">
          {flash.error}
        </div>
      )}
      {flash.info && (
        <div className="rounded-md border border-blue-300 bg-blue-50 px-4 py-3 text-sm text-blue-900">
          {flash.info}
        </div>
      )}

      {showForm && (
        <form onSubmit={submit} className="rounded-md border bg-white p-5 space-y-4">
          <h2 className="text-base font-semibold">Conceder novo acesso</h2>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label className="space-y-1">
              <span className="text-sm font-medium">CNPJ do contador (14 dígitos)</span>
              <input
                type="text"
                className="w-full rounded border px-3 py-2 text-sm"
                value={form.data.cnpj_contador}
                onChange={(e) => form.setData('cnpj_contador', e.target.value.replace(/\D/g, '').slice(0, 14))}
                placeholder="00000000000000"
                maxLength={14}
                required
              />
              {form.errors.cnpj_contador && <span className="text-xs text-red-600">{form.errors.cnpj_contador}</span>}
            </label>

            <label className="space-y-1">
              <span className="text-sm font-medium">Email</span>
              <input
                type="email"
                className="w-full rounded border px-3 py-2 text-sm"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                required
              />
              {form.errors.email && <span className="text-xs text-red-600">{form.errors.email}</span>}
            </label>

            <label className="space-y-1">
              <span className="text-sm font-medium">Nome completo</span>
              <input
                type="text"
                className="w-full rounded border px-3 py-2 text-sm"
                value={form.data.nome}
                onChange={(e) => form.setData('nome', e.target.value)}
                required
              />
              {form.errors.nome && <span className="text-xs text-red-600">{form.errors.nome}</span>}
            </label>

            <label className="space-y-1">
              <span className="text-sm font-medium">Telefone (opcional)</span>
              <input
                type="tel"
                className="w-full rounded border px-3 py-2 text-sm"
                value={form.data.telefone}
                onChange={(e) => form.setData('telefone', e.target.value)}
                placeholder="(00) 00000-0000"
              />
            </label>
          </div>

          <fieldset className="space-y-2">
            <legend className="text-sm font-medium">Escopo de acesso</legend>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.data.can_view_unificado}
                onChange={(e) => form.setData('can_view_unificado', e.target.checked)}
              />
              Pode ver Visão Unificada
            </label>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.data.can_view_reports}
                onChange={(e) => form.setData('can_view_reports', e.target.checked)}
              />
              Pode ver Relatórios (DRE / Fluxo)
            </label>
          </fieldset>

          <label className="flex items-start gap-2 text-sm border-t pt-4">
            <input
              type="checkbox"
              checked={form.data.consent_lgpd}
              onChange={(e) => form.setData('consent_lgpd', e.target.checked)}
              required
            />
            <span>
              <strong>Consentimento LGPD:</strong> declaro estar ciente que estou compartilhando
              dados financeiros desta empresa com o contador acima, exclusivamente para fins
              contábeis. O acesso é somente leitura, revogável a qualquer momento, e a ação fica
              registrada em log de auditoria. (LGPD Art. 7º, II.)
            </span>
          </label>
          {form.errors.consent_lgpd && <p className="text-xs text-red-600">{form.errors.consent_lgpd}</p>}

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              className="os-btn ghost"
              onClick={() => { form.reset(); setShowForm(false); }}
              disabled={form.processing}
            >
              Cancelar
            </button>
            <button type="submit" className="os-btn primary" disabled={form.processing}>
              {form.processing ? 'Concedendo...' : 'Conceder acesso'}
            </button>
          </div>
        </form>
      )}

      <section className="rounded-md border bg-white">
        <header className="border-b px-5 py-3">
          <h2 className="text-base font-semibold">Acessos ativos ({accesses.length})</h2>
        </header>
        {accesses.length === 0 ? (
          <p className="px-5 py-8 text-center text-sm text-muted-foreground">
            Nenhum contador com acesso ainda. Clique em "Adicionar contador" para começar.
          </p>
        ) : (
          <table className="w-full text-sm">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Nome</th>
                <th className="px-4 py-2 font-medium">Email</th>
                <th className="px-4 py-2 font-medium">CNPJ</th>
                <th className="px-4 py-2 font-medium">Acesso desde</th>
                <th className="px-4 py-2 font-medium">Escopo</th>
                <th className="px-4 py-2 font-medium text-right">Ações</th>
              </tr>
            </thead>
            <tbody>
              {accesses.map((a) => (
                <tr key={a.id} className="border-t">
                  <td className="px-4 py-3">{a.advisor_nome ?? '—'}</td>
                  <td className="px-4 py-3 text-muted-foreground">{a.advisor_email ?? '—'}</td>
                  <td className="px-4 py-3 font-mono text-xs">{a.advisor_cnpj_mascarado ?? '—'}</td>
                  <td className="px-4 py-3">{a.granted_at_label ?? '—'}</td>
                  <td className="px-4 py-3 text-xs">
                    {a.can_view_unificado && <span className="mr-1 inline-block rounded bg-emerald-100 px-2 py-0.5 text-emerald-900">Unificado</span>}
                    {a.can_view_reports && <span className="mr-1 inline-block rounded bg-blue-100 px-2 py-0.5 text-blue-900">Relatórios</span>}
                    {!a.has_consent && <span className="inline-block rounded bg-amber-100 px-2 py-0.5 text-amber-900">Sem consent</span>}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <button
                      type="button"
                      className="text-xs text-red-600 hover:underline"
                      onClick={() => revoke(a.id, a.advisor_nome)}
                    >
                      Revogar
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </section>
    </div>
  );
}

Contador.layout = (page: ReactNode) => (
  <AppShellV2
    title="Financeiro — Contador Parceiro"
    breadcrumbItems={[
      { label: 'Financeiro', href: '/financeiro' },
      { label: 'Configurações' },
      { label: 'Contador' },
    ]}
  >
    {page}
  </AppShellV2>
);

export default Contador;
