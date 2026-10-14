// @memcofre
//   tela: /crm/campaigns
//   module: Crm
//   stories: thread Crm/11 (CampaignController@index · Blade → Inertia, detalhe em drawer)
//   permissao: crm.access_all_campaigns | crm.access_own_campaigns
//
// Lista de campanhas do CRM, atrás da flag useV2CrmCampanhas (OFF). Charter: ./Index.charter.md
// Casos: ./Index.casos.md · Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Campanhas()
// Contrato: governance/design/contracts/crm-campanhas.contract.json
//
// Só leitura. Criar e editar levam às páginas da Blade; excluir e ENVIAR continuam na tela clássica
// (`?classico=1`). Esta tela nunca dispara notificação (RUNBOOK-campanhas §6).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type DensidadeDaTabela, type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/campaigns';
const CLASSICO = `${ROTA}?classico=1`;
const TODOS = '__todos';

interface Campanha {
  id: number; nome: string; tipo: 'sms' | 'email'; contatos: number; criado_por: string | null;
  criado_em: string | null; enviada_em: string | null; assunto: string | null; corpo: string;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  campanhas?: PaginatorShape<Campanha>;
  campanha?: Campanha | null;
}

const rotuloTipo = (t: Campanha['tipo']) => (t === 'sms' ? 'SMS' : 'E-mail');

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

function verCampanha(filtros: Filtros, id: number | null) {
  router.get(ROTA, id ? { ...filtros, campanha: String(id) } : filtros, {
    only: ['campanha'], preserveState: true, preserveScroll: true, replace: true,
  });
}

export default function CampanhasIndex({ filtros, campanhas, campanha }: Props) {
  const [densidade, setDensidade] = useState<DensidadeDaTabela>('default');

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Campanhas" subtitle="Envios de SMS e e-mail para leads e clientes." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <div className="grid gap-3 sm:grid-cols-4">
            <div className="flex flex-col gap-1">
              <Label>Tipo de campanha</Label>
              <Select
                value={filtros.campaign_type || TODOS}
                onValueChange={(v) => filtrar(filtros, { campaign_type: v === TODOS ? undefined : v })}
              >
                <SelectTrigger aria-label="Tipo de campanha"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value={TODOS}>Todos</SelectItem>
                  <SelectItem value="sms">SMS</SelectItem>
                  <SelectItem value="email">E-mail</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card data-contract="crm-campanhas">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Todas as campanhas</h3>
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button variant={densidade === 'default' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'default'} onClick={() => setDensidade('default')}>Confortável</Button>
              <Button variant={densidade === 'dense' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'dense'} onClick={() => setDensidade('dense')}>Compacto</Button>
              <Button asChild size="sm"><a href={`${ROTA}/create`}>Adicionar</a></Button>
            </div>
          </div>

          <Deferred data="campanhas" fallback={<Skeleton className="h-64 w-full" />}>
            {campanhas ? (
              <DataTable<Campanha>
                caption="Campanhas"
                columns={COLUNAS}
                data={campanhas.data}
                pagination={campanhas}
                endpoint={ROTA}
                filters={filtros}
                initialSearch={filtros.q ?? ''}
                searchPlaceholder="Buscar campanha"
                emptyMessage="Nenhuma campanha com esses filtros"
                rowKey={(r) => r.id}
                onRowClick={(r) => verCampanha(filtros, r.id)}
                density={densidade}
              />
            ) : null}
          </Deferred>
        </CardContent>
      </Card>

      <Sheet open={!!campanha} onOpenChange={(aberto) => { if (!aberto) verCampanha(filtros, null); }}>
        {campanha ? (
          <SheetContent side="right" className="w-full sm:max-w-[760px]">
            <SheetHeader>
              <Badge variant={campanha.tipo === 'sms' ? 'warning' : 'info'} className="w-fit">{rotuloTipo(campanha.tipo)}</Badge>
              <SheetTitle>{campanha.nome}</SheetTitle>
              <SheetDescription>
                {campanha.enviada_em ? `Enviada em ${campanha.enviada_em}` : 'Ainda não enviada'}
              </SheetDescription>
            </SheetHeader>
            <div className="flex flex-col gap-4 overflow-y-auto px-4">
              <dl className="grid grid-cols-[140px_1fr] gap-x-3 gap-y-1 text-sm">
                {([
                  ['Assunto', campanha.assunto], ['Contatos', String(campanha.contatos)],
                  ['Criada por', campanha.criado_por], ['Criada em', campanha.criado_em],
                ] as [string, string | null][]).map(([rotulo, valor]) => (
                  <div key={rotulo} className="contents">
                    <dt className="text-muted-foreground">{rotulo}</dt>
                    <dd>{valor || '—'}</dd>
                  </div>
                ))}
              </dl>
              <section className="flex flex-col gap-1">
                <h4 className="text-sm font-medium">{campanha.tipo === 'sms' ? 'Corpo do SMS' : 'Corpo do e-mail'}</h4>
                <p className="whitespace-pre-line text-sm text-muted-foreground">{campanha.corpo || '—'}</p>
              </section>
            </div>
            <SheetFooter className="flex-row justify-end gap-2">
              <Button asChild variant="outline"><a href={CLASSICO}>Excluir ou enviar na tela clássica</a></Button>
              {campanha.enviada_em ? null : <Button asChild><a href={`${ROTA}/${campanha.id}/edit`}>Editar</a></Button>}
            </SheetFooter>
          </SheetContent>
        ) : null}
      </Sheet>
    </div>
  );
}

const COLUNAS: ColumnDef<Campanha>[] = [
  {
    id: 'nome', header: 'Nome da campanha', meta: { width: 280 },
    cell: ({ row }) => (
      <span className="inline-flex items-center gap-2">
        {row.original.nome}
        {row.original.enviada_em ? <Badge variant="success">Enviada</Badge> : null}
      </span>
    ),
  },
  {
    id: 'tipo', header: 'Tipo de campanha', meta: { width: 150 },
    cell: ({ row }) => <Badge variant={row.original.tipo === 'sms' ? 'warning' : 'info'}>{rotuloTipo(row.original.tipo)}</Badge>,
  },
  { id: 'contatos', header: 'Contatos', meta: { width: 108 }, cell: ({ row }) => row.original.contatos },
  { id: 'criado_por', header: 'Criado por', meta: { width: 160 }, cell: ({ row }) => row.original.criado_por || '—' },
  { id: 'criado_em', header: 'Criado em', meta: { width: 160 }, cell: ({ row }) => row.original.criado_em || '—' },
];

CampanhasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
