// @memcofre
//   tela: /crm/all-contacts-login
//   module: Crm
//   stories: thread Crm/16 (ContactLoginController@allContactsLoginList · Blade → Inertia · D3)
//   permissao: crm.access_contact_login
//
// Logins do portal do contato. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: alvo crm--logins--index (A2) · prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Logins()
// Contrato: governance/design/contracts/crm-logins.contract.json
//
// Esta onda é LEITURA, atrás da flag useV2CrmLogins. Adicionar, editar e excluir login seguem nos
// modais da Blade, aberta por `?classico=1`. A senha nunca chega às props — nem para leitura.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/all-contacts-login';
const CLASSICO = `${ROTA}?classico=1`;
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Login {
  id: number; contato: string; usuario: string; nome: string; email: string;
  departamento: string | null; designacao: string | null;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  opcoes?: { contatos: Opcao[] };
  logins?: PaginatorShape<Login>;
}

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

const texto = (header: string, acc: (r: Login) => ReactNode, width = 160): ColumnDef<Login> => ({
  id: header, header, cell: ({ row }) => acc(row.original) || '—', meta: { width },
});

const COLUNAS: ColumnDef<Login>[] = [
  texto('Ação', () => <Button asChild variant="ghost" size="sm"><a href={CLASSICO}>Editar login</a></Button>, 110),
  texto('Contato', (r) => r.contato, 220),
  texto('Nome de usuário', (r) => <span className="font-mono">{r.usuario}</span>),
  texto('Nome', (r) => r.nome, 180),
  texto('E-mail', (r) => r.email, 240),
  texto('Departamento', (r) => r.departamento, 150),
  texto('Designação', (r) => r.designacao),
];

export default function LoginsIndex({ filtros, opcoes, logins }: Props) {
  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Login de contatos" subtitle="Acessos do portal do contato: quem do cliente entra e com qual usuário." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <Deferred data="opcoes" fallback={<Skeleton className="h-16 w-full" />}>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <div className="flex flex-col gap-1">
                <Label>Contato</Label>
                <Select value={filtros.contact_id || TODOS} onValueChange={(v) => filtrar(filtros, { contact_id: v === TODOS ? undefined : v })}>
                  <SelectTrigger aria-label="Contato"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value={TODOS}>Todos</SelectItem>
                    {(opcoes?.contatos ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
                  </SelectContent>
                </Select>
              </div>
            </div>
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-logins">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Login de todos os contatos</h3>
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button asChild size="sm"><a href={CLASSICO}>Adicionar</a></Button>
            </div>
          </div>

          <Deferred data="logins" fallback={<Skeleton className="h-64 w-full" />}>
            {logins ? (
              <DataTable<Login>
                caption="Login de todos os contatos"
                columns={COLUNAS}
                data={logins.data}
                pagination={logins}
                endpoint={ROTA}
                filters={filtros}
                initialSearch={filtros.q ?? ''}
                searchPlaceholder="Buscar por nome ou usuário"
                emptyMessage="Nada com esses filtros"
                rowKey={(r) => r.id}
              />
            ) : null}
          </Deferred>
        </CardContent>
      </Card>
    </div>
  );
}

LoginsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
