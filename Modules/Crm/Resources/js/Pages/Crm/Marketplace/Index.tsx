// @memcofre
//   tela: /crm/b2b-marketplace
//   module: Crm
//   stories: Marketplace B2B (CrmMarketplaceController@index · Blade → Inertia, Onda 1)
//   permissao: Admin do negócio e constants.enable_b2b_marketplace (mesma trava das três ações)
//
// Integração Exporters India: credenciais da conta, a quem atribuir o acompanhamento e a fonte dos leads importados.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Crm/RUNBOOK-marketplace.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Marketplace()
//
// Atrás da flag useV2CrmMarketplace (default ligado, decisão [F] 2026-11-27). Salva pelo mesmo
// POST /crm/save-marketplace e importa pelo mesmo GET /crm/import-leads; `?classico=1` devolve a Blade.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Grid, Inline, Stack } from '@/Components/layout';
import CrmCabecalho from '../_components/CrmCabecalho';

interface Opcao { value: string; label: string }
interface Config { chave: string; email: string; usuarios: string[]; fonte: string }
interface Props {
  config: Config | null;
  usuarios: Opcao[];
  fontes: Opcao[];
}

const SEM_FONTE = '__sem_fonte__';

export default function MarketplaceIndex({ config, usuarios, fontes }: Props) {
  const form = useForm({
    marketplace: 'exportersindia',
    site_key: config?.chave ?? '',
    site_id: config?.email ?? '',
    assigned_users: config?.usuarios ?? [],
    crm_source_id: config?.fonte ?? '',
  });

  const salvar = (e: FormEvent) => {
    e.preventDefault();
    form.post('/crm/save-marketplace', { preserveScroll: true });
  };

  const alternarUsuario = (id: string, marcado: boolean) => {
    const atual = form.data.assigned_users;
    form.setData('assigned_users', marcado ? [...atual, id] : atual.filter((u) => u !== id));
  };

  const erro = (campo: string) => (form.errors as Record<string, string | undefined>)[campo];

  return (
    <Stack gap={4}>
      <CrmCabecalho aba="Marketplace B2B" />

      <Alert>
        <AlertTitle>Integração externa</AlertTitle>
        <AlertDescription>A chave e o e-mail vêm do painel do marketplace. Os leads importados entram com a fonte escolhida e já atribuídos.</AlertDescription>
      </Alert>

      <Card data-contract="crm-marketplace">
        <CardContent className="p-4">
          <Stack asChild gap={4}>
            <form onSubmit={salvar}>
              <h3 className="text-sm font-medium">Exporters India</h3>

              <Grid min="md" gap={4}>
                <Stack gap={1}>
                  <Label htmlFor="mkt-chave">Chave *</Label>
                  <Input id="mkt-chave" value={form.data.site_key} onChange={(e) => form.setData('site_key', e.target.value)} placeholder="site_key" required />
                  {erro('site_key') ? <p className="text-xs text-destructive">{erro('site_key')}</p> : null}
                </Stack>
                <Stack gap={1}>
                  <Label htmlFor="mkt-email">E-mail *</Label>
                  <Input id="mkt-email" type="email" value={form.data.site_id} onChange={(e) => form.setData('site_id', e.target.value)} placeholder="conta@exportersindia.com" required />
                  {erro('site_id') ? <p className="text-xs text-destructive">{erro('site_id')}</p> : null}
                </Stack>
                <Stack gap={1}>
                  <Label>Acompanhamento atribuído a *</Label>
                  <Stack gap={1} className="max-h-48 overflow-y-auto rounded-md border p-2">
                    {usuarios.map((u) => (
                      <Inline key={u.value} gap={2} asChild>
                        <label className="text-sm">
                          <Checkbox checked={form.data.assigned_users.includes(u.value)} onCheckedChange={(v) => alternarUsuario(u.value, v === true)} />
                          {u.label}
                        </label>
                      </Inline>
                    ))}
                  </Stack>
                  {erro('assigned_users') || erro('assigned_users.0') ? (
                    <p className="text-xs text-destructive">{erro('assigned_users') || erro('assigned_users.0')}</p>
                  ) : null}
                </Stack>
                <Stack gap={1}>
                  <Label htmlFor="mkt-fonte">Fonte</Label>
                  <Select value={form.data.crm_source_id || SEM_FONTE} onValueChange={(v) => form.setData('crm_source_id', v === SEM_FONTE ? '' : v)}>
                    <SelectTrigger id="mkt-fonte"><SelectValue placeholder="Selecione" /></SelectTrigger>
                    <SelectContent>
                      <SafeSelectItem value={SEM_FONTE}>Sem fonte</SafeSelectItem>
                      {fontes.filter((f) => f.value).map((f) => <SafeSelectItem key={f.value} value={f.value}>{f.label}</SafeSelectItem>)}
                    </SelectContent>
                  </Select>
                  {erro('crm_source_id') ? <p className="text-xs text-destructive">{erro('crm_source_id')}</p> : null}
                </Stack>
              </Grid>

              <Inline gap={2} justify="end">
                <Button type="button" variant="outline" size="sm" disabled={!config || form.processing}
                  title={config ? undefined : 'Salve a configuração antes de importar'}
                  onClick={() => router.get('/crm/import-leads', {}, { preserveScroll: true })}>Importar leads</Button>
                <Button type="submit" size="sm" disabled={form.processing}>Salvar</Button>
              </Inline>
            </form>
          </Stack>
        </CardContent>
      </Card>
    </Stack>
  );
}

MarketplaceIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
