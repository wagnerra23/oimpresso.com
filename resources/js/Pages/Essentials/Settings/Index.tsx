// @docvault
//   tela: /hrm/settings
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   adrs: arq/0001
//   tests: Modules/Essentials/Tests/Feature/SettingsIndexTest

import AppShell from '@/Layouts/AppShell';
import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { toast } from 'sonner';
import { Clock, Hash, MapPin, Save, Settings as SettingsIcon, Target } from 'lucide-react';
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
  grace_before_checkin: string;
  grace_after_checkin: string;
  grace_before_checkout: string;
  grace_after_checkout: string;
  is_location_required: boolean;
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
    <AppShell
      title="Configurações do Essentials"
      breadcrumb={[{ label: 'HRM' }, { label: 'Configurações' }]}
    >
      <div className="mx-auto max-w-3xl p-6 space-y-4">
        <header>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <SettingsIcon size={22} /> Configurações
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Prefixos, janelas de tolerância do ponto e preferências do módulo.
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

          {/* Grace period */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base flex items-center gap-2">
                <Clock size={16} /> Tolerâncias de ponto (minutos)
              </CardTitle>
              <CardDescription className="text-xs">
                Janela permitida antes/depois do horário esperado de entrada e saída.
              </CardDescription>
            </CardHeader>
            <CardContent className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div className="space-y-1">
                <Label htmlFor="g-before-in" className="text-xs">Antes de entrar</Label>
                <Input
                  id="g-before-in"
                  value={form.data.grace_before_checkin}
                  onChange={(e) => form.setData('grace_before_checkin', e.target.value)}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="g-after-in" className="text-xs">Depois de entrar</Label>
                <Input
                  id="g-after-in"
                  value={form.data.grace_after_checkin}
                  onChange={(e) => form.setData('grace_after_checkin', e.target.value)}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="g-before-out" className="text-xs">Antes de sair</Label>
                <Input
                  id="g-before-out"
                  value={form.data.grace_before_checkout}
                  onChange={(e) => form.setData('grace_before_checkout', e.target.value)}
                />
              </div>
              <div className="space-y-1">
                <Label htmlFor="g-after-out" className="text-xs">Depois de sair</Label>
                <Input
                  id="g-after-out"
                  value={form.data.grace_after_checkout}
                  onChange={(e) => form.setData('grace_after_checkout', e.target.value)}
                />
              </div>
            </CardContent>
          </Card>

          {/* Flags */}
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Comportamentos</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-start gap-3">
                <Switch
                  id="sw-location"
                  checked={form.data.is_location_required}
                  onCheckedChange={(v) => form.setData('is_location_required', v)}
                />
                <div>
                  <Label htmlFor="sw-location" className="flex items-center gap-1 cursor-pointer">
                    <MapPin size={12} /> Exigir localidade no ponto
                  </Label>
                  <p className="text-xs text-muted-foreground">
                    Colaborador deve selecionar loja ao bater ponto.
                  </p>
                </div>
              </div>
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
    </AppShell>
  );
}
