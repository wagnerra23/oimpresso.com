// @memcofre
//   tela: /crm/settings
//   module: Crm
//   stories: thread Crm/15 (CrmSettingsController@index · Blade → Inertia)
//   permissao: superadmin | Admin#<negócio> com crm_module
//
// Configurações do CRM. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Config()
// Contrato: governance/design/contracts/crm-config.contract.json
//
// Atrás da flag useV2CrmConfiguracoes (default OFF). Grava pelo MESMO POST /crm/update-settings
// da Blade; `?classico=1` devolve o formulário Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PageHeader } from '@/Components/PageHeader';

const GRAVAR = '/crm/update-settings';

interface Props {
  configuracoes: { ativar_pedido: boolean; prefixo: string };
}

export default function ConfiguracoesIndex({ configuracoes }: Props) {
  const form = useForm({ ativar_pedido: configuracoes.ativar_pedido, prefixo: configuracoes.prefixo });

  function salvar(e: FormEvent) {
    e.preventDefault();
    // O backend lê `has('enable_order_request')`: o campo só vai quando marcado, como no form HTML.
    form.transform((d) => ({
      order_request_prefix: d.prefixo,
      ...(d.ativar_pedido ? { enable_order_request: 1 } : {}),
    }));
    form.post(GRAVAR, { preserveScroll: true });
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Configurações" subtitle="Opções do CRM deste negócio." />

      <Card data-contract="crm-config">
        <CardContent className="p-4">
          <form onSubmit={salvar} className="flex flex-col gap-4">
            <h3 className="text-sm font-medium">Configurações do CRM</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="flex items-start gap-2">
                <Checkbox
                  id="ativar_pedido"
                  checked={form.data.ativar_pedido}
                  onCheckedChange={(v) => form.setData('ativar_pedido', v === true)}
                />
                <Label htmlFor="ativar_pedido" className="flex flex-col items-start gap-1">
                  <span>Ativar solicitação de pedido</span>
                  <span className="text-xs font-normal text-muted-foreground">
                    Se ativado, o usuário de contato pode fazer uma solicitação de pedido no portal.
                  </span>
                </Label>
              </div>
              <div className="flex flex-col gap-1">
                <Label htmlFor="prefixo">Prefixo da solicitação de pedido</Label>
                <Input
                  id="prefixo"
                  value={form.data.prefixo}
                  placeholder="SO-"
                  onChange={(e) => form.setData('prefixo', e.target.value)}
                />
              </div>
            </div>
            <div className="flex justify-end">
              <Button type="submit" size="sm" disabled={form.processing}>Atualizar</Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

ConfiguracoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
