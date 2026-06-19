// @memcofre tela=/financeiro/configuracoes/contador module=Financeiro
// Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor contadores parceiros (Fase 1 MVP).
// Wave 4 (2026-05-31): migrado pra DS canon — PageHeader (shared) + FinanceiroSubNav +
// Input/Label/Button/Card/Alert/AlertDialog @/Components/ui. Removidos inputs hand-rolled,
// flash bg-blue cru e window.confirm nativo. Consent LGPD preservado (Art. 7º, II).

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, router, usePage } from '@inertiajs/react';
import { useState, FormEvent, ReactNode } from 'react';
import { UserPlus } from 'lucide-react';

import PageHeader from '@/Components/shared/PageHeader';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Alert, AlertDescription } from '@/Components/ui/alert';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';

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

function Contador({ accesses }: Props) {
  const { props } = usePage<{ flash?: FlashShape }>();
  const flash = props.flash ?? {};
  const [showForm, setShowForm] = useState(false);
  const [revokeTarget, setRevokeTarget] = useState<AccessRow | null>(null);

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

  const confirmRevoke = () => {
    if (!revokeTarget) return;
    router.delete(`/financeiro/configuracoes/contador/${revokeTarget.id}`, {
      preserveScroll: true,
      onFinish: () => setRevokeTarget(null),
    });
  };

  return (
    <div className="fin-curadoria p-6 max-w-5xl mx-auto space-y-6">
      <PageHeader
        icon="user-cog"
        title="Contador Parceiro"
        description="Adicione o contador da sua empresa pra ele acompanhar relatórios e visão unificada num portal próprio. Zero credenciais compartilhadas. Revogue a qualquer momento."
        action={
          <div className="flex items-center gap-1.5">
            <FinanceiroSubNav active="contador" hidePrimary />
            {!showForm && (
              <Button onClick={() => setShowForm(true)}>
                <UserPlus className="h-4 w-4" />
                Adicionar contador
              </Button>
            )}
          </div>
        }
      />

      {flash.success && (
        <Alert>
          <AlertDescription>{flash.success}</AlertDescription>
        </Alert>
      )}
      {flash.info && (
        <Alert>
          <AlertDescription>{flash.info}</AlertDescription>
        </Alert>
      )}
      {flash.error && (
        <Alert variant="destructive">
          <AlertDescription>{flash.error}</AlertDescription>
        </Alert>
      )}

      {showForm && (
        <Card>
          <CardContent>
            <form onSubmit={submit} className="space-y-4">
              <h2 className="text-base font-semibold">Conceder novo acesso</h2>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label htmlFor="cnpj_contador">CNPJ do contador (14 dígitos)</Label>
                  <Input
                    id="cnpj_contador"
                    type="text"
                    value={form.data.cnpj_contador}
                    onChange={(e) =>
                      form.setData('cnpj_contador', e.target.value.replace(/\D/g, '').slice(0, 14))
                    }
                    placeholder="00000000000000"
                    maxLength={14}
                    aria-invalid={!!form.errors.cnpj_contador}
                    required
                  />
                  {form.errors.cnpj_contador && (
                    <p className="text-xs text-destructive">{form.errors.cnpj_contador}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="email">Email</Label>
                  <Input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    aria-invalid={!!form.errors.email}
                    required
                  />
                  {form.errors.email && (
                    <p className="text-xs text-destructive">{form.errors.email}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="nome">Nome completo</Label>
                  <Input
                    id="nome"
                    type="text"
                    value={form.data.nome}
                    onChange={(e) => form.setData('nome', e.target.value)}
                    aria-invalid={!!form.errors.nome}
                    required
                  />
                  {form.errors.nome && (
                    <p className="text-xs text-destructive">{form.errors.nome}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="telefone">Telefone (opcional)</Label>
                  <Input
                    id="telefone"
                    type="tel"
                    value={form.data.telefone}
                    onChange={(e) => form.setData('telefone', e.target.value)}
                    placeholder="(00) 00000-0000"
                  />
                </div>
              </div>

              <fieldset className="space-y-2">
                <legend className="text-sm font-medium">Escopo de acesso</legend>
                <div className="flex items-center gap-2 text-sm">
                  <Checkbox
                    id="can_view_unificado"
                    checked={form.data.can_view_unificado}
                    onCheckedChange={(v) => form.setData('can_view_unificado', v === true)}
                  />
                  <Label htmlFor="can_view_unificado" variant="shadcn" className="font-normal cursor-pointer">
                    Pode ver Visão Unificada
                  </Label>
                </div>
                <div className="flex items-center gap-2 text-sm">
                  <Checkbox
                    id="can_view_reports"
                    checked={form.data.can_view_reports}
                    onCheckedChange={(v) => form.setData('can_view_reports', v === true)}
                  />
                  <Label htmlFor="can_view_reports" variant="shadcn" className="font-normal cursor-pointer">
                    Pode ver Relatórios (DRE / Fluxo)
                  </Label>
                </div>
              </fieldset>

              <div className="flex items-start gap-2 text-sm border-t pt-4">
                <Checkbox
                  id="consent_lgpd"
                  className="mt-0.5"
                  checked={form.data.consent_lgpd}
                  onCheckedChange={(v) => form.setData('consent_lgpd', v === true)}
                  required
                />
                <label htmlFor="consent_lgpd" className="cursor-pointer">
                  <strong>Consentimento LGPD:</strong> declaro estar ciente que estou compartilhando
                  dados financeiros desta empresa com o contador acima, exclusivamente para fins
                  contábeis. O acesso é somente leitura, revogável a qualquer momento, e a ação fica
                  registrada em log de auditoria. (LGPD Art. 7º, II.)
                </label>
              </div>
              {form.errors.consent_lgpd && (
                <p className="text-xs text-destructive">{form.errors.consent_lgpd}</p>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => {
                    form.reset();
                    setShowForm(false);
                  }}
                  disabled={form.processing}
                >
                  Cancelar
                </Button>
                <Button type="submit" disabled={form.processing}>
                  {form.processing ? 'Concedendo...' : 'Conceder acesso'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Card className="py-0 gap-0 overflow-hidden">
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
                    {a.can_view_unificado && (
                      <span className="mr-1 inline-block rounded bg-success-soft px-2 py-0.5 text-success-fg">
                        Unificado
                      </span>
                    )}
                    {a.can_view_reports && (
                      <span className="mr-1 inline-block rounded bg-muted px-2 py-0.5 text-foreground">
                        Relatórios
                      </span>
                    )}
                    {!a.has_consent && (
                      <span className="inline-block rounded bg-warning-soft px-2 py-0.5 text-warning-fg">
                        Sem consent
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      className="text-destructive hover:text-destructive"
                      onClick={() => setRevokeTarget(a)}
                    >
                      Revogar
                    </Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Card>

      <AlertDialog open={revokeTarget !== null} onOpenChange={(open) => !open && setRevokeTarget(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Revogar acesso do contador?</AlertDialogTitle>
            <AlertDialogDescription>
              {revokeTarget?.advisor_nome ?? 'O contador'} perderá acesso a este negócio
              imediatamente. A ação fica registrada em log de auditoria (LGPD) e pode ser
              concedida novamente depois.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction variant="destructive" onClick={confirmRevoke}>
              Revogar acesso
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
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
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);

export default Contador;
