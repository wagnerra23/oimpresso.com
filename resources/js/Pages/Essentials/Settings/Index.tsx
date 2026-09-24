// @docvault
//   tela: /hrm/settings
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   adrs: arq/0001
//   tests: Modules/Essentials/Tests/Feature/SettingsIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Hash, Save, Settings as SettingsIcon, Target } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

interface Settings {
  leave_ref_no_prefix: string;
  leave_instructions: string;
  payroll_ref_no_prefix: string;
  essentials_todos_prefix: string;
  calculate_sales_target_commission_without_tax: boolean;
}

interface Props {
  settings: Settings;
}

export default function SettingsIndex({ settings }: Props) {
  const form = useForm<Settings>(settings);

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/hrm/settings', {
      onSuccess: () => toast.success('Configurações atualizadas.'),
      onError: () => toast.error('Verifique os campos destacados.'),
    });
  };

  return (
    <>
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header>
          <h1 className="text-2xl font-semibold tracking-tight flex items-center gap-2">
            <SettingsIcon size={22} /> Configurações
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Prefixos, instruções de afastamento e preferências do módulo.
          </p>
        </header>

        <form onSubmit={submit} className="space-y-4">
          {/* Prefixos */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Hash size={16} /> Prefixos de referência
              </CardTitle>
              <CardDescription className="text-xs">
                Usados em códigos automáticos (tarefas, folhas, afastamentos).
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="todo-prefix">Prefixo de tarefas</Label>
                  <Input
                    id="todo-prefix"
                    value={form.data.essentials_todos_prefix}
                    onChange={(e) => form.setData('essentials_todos_prefix', e.target.value)}
                    placeholder="Ex: TASK"
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="leave-prefix">Prefixo de afastamentos</Label>
                  <Input
                    id="leave-prefix"
                    value={form.data.leave_ref_no_prefix}
                    onChange={(e) => form.setData('leave_ref_no_prefix', e.target.value)}
                    placeholder="Ex: LV"
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="payroll-prefix">Prefixo de folha</Label>
                  <Input
                    id="payroll-prefix"
                    value={form.data.payroll_ref_no_prefix}
                    onChange={(e) => form.setData('payroll_ref_no_prefix', e.target.value)}
                    placeholder="Ex: PY"
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Afastamentos */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Instruções para afastamentos</CardTitle>
              <CardDescription className="text-xs">
                Texto exibido para colaboradores ao solicitar afastamento.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <Textarea
                rows={4}
                value={form.data.leave_instructions}
                onChange={(e) => form.setData('leave_instructions', e.target.value)}
                placeholder="Ex: Solicitações devem ser feitas com 48h de antecedência…"
              />
            </CardContent>
          </Card>

          {/* Tolerâncias de ponto: aposentadas em 2026-09-24 — a jornada é do Ponto (ADR 0014 emenda). */}

          {/* Flags */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Comportamentos</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-start gap-3">
                <Switch
                  id="sw-sales"
                  checked={form.data.calculate_sales_target_commission_without_tax}
                  onCheckedChange={(v) => form.setData('calculate_sales_target_commission_without_tax', v)}
                />
                <div>
                  <Label htmlFor="sw-sales" className="flex items-center gap-1 cursor-pointer">
                    <Target size={12} /> Meta de vendas sem impostos
                  </Label>
                  <p className="text-xs text-muted-foreground">
                    Calcula comissão sobre valor líquido (sem tributos).
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end">
            <Button type="submit" disabled={form.processing} className="gap-1.5">
              <Save size={14} /> Salvar configurações
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

SettingsIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Configurações do Essentials" breadcrumbItems={[{ label: 'HRM' }, { label: 'Configurações' }]}>
    {page}
  </AppShellV2>
);
