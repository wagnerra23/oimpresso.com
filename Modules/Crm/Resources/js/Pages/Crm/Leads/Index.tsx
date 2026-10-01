// @memcofre
//   tela: /crm/leads
//   module: Crm
//   stories: thread Crm/02 (LeadController@index · Blade → Inertia, detalhe em drawer)
//   permissao: crm.access_all_leads | crm.access_own_leads
//
// Lista de leads do CRM. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade.jsx → TelaLeads()
// Contrato: governance/design/contracts/crm-leads.contract.json
//
// Esta onda é LEITURA. O formulário (D2: reusar Cliente/Create), a conversão para cliente, o
// kanban e o "local" do contato seguem na tela Blade, aberta por `?classico=1`.

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
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type DensidadeDaTabela, type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/leads';
const CLASSICO = `${ROTA}?classico=1`;
const KANBAN = `${ROTA}?lead_view=kanban`;
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Opcoes { fontes: Opcao[]; estagios: Opcao[]; usuarios: Opcao[] }
interface Lead {
  id: number; codigo: string | null; nome: string; celular: string | null; email: string | null;
  documento: string | null; fonte: string | null; estagio: string | null; atribuidos: string[];
  endereco: string; ultimo: string | null; proximo: string | null; adicionado_em: string | null;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  opcoes?: Opcoes;
  leads?: PaginatorShape<Lead>;
  lead?: Lead | null;
}

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

function verLead(filtros: Filtros, id: number | null) {
  router.get(ROTA, id ? { ...filtros, lead: String(id) } : filtros, {
    only: ['lead'], preserveState: true, preserveScroll: true, replace: true,
  });
}

export default function LeadsIndex({ filtros, opcoes, leads, lead }: Props) {
  const [densidade, setDensidade] = useState<DensidadeDaTabela>('default');

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Leads" subtitle="Contatos em prospecção: fonte, estágio de vida e quem acompanha." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <Deferred data="opcoes" fallback={<Skeleton className="h-16 w-full" />}>
            <div className="grid gap-3 sm:grid-cols-3">
              <Filtro rotulo="Fonte" campo="source" lista={opcoes?.fontes} filtros={filtros} />
              <Filtro rotulo="Estágio de vida" campo="life_stage" lista={opcoes?.estagios} filtros={filtros} />
              <Filtro rotulo="Atribuído a" campo="user_id" lista={opcoes?.usuarios} filtros={filtros} />
            </div>
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-leads">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Todos os leads</h3>
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button variant="secondary" size="sm" aria-pressed="true">Exibição de lista</Button>
              <Button asChild variant="outline" size="sm"><a href={KANBAN}>Kanban</a></Button>
              <Button variant={densidade === 'default' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'default'} onClick={() => setDensidade('default')}>Confortável</Button>
              <Button variant={densidade === 'dense' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'dense'} onClick={() => setDensidade('dense')}>Compacto</Button>
              <Button asChild size="sm"><a href={CLASSICO}>Adicionar</a></Button>
            </div>
          </div>

          <Deferred data="leads" fallback={<Skeleton className="h-64 w-full" />}>
            {leads ? (
              <>
                <DataTable<Lead>
                  caption="Leads"
                  columns={COLUNAS}
                  data={leads.data}
                  pagination={leads}
                  endpoint={ROTA}
                  filters={filtros}
                  initialSearch={filtros.q ?? ''}
                  searchPlaceholder="Buscar por nome, ID ou celular"
                  emptyMessage="Nada com esses filtros"
                  rowKey={(r) => r.id}
                  onRowClick={(r) => verLead(filtros, r.id)}
                  density={densidade}
                />
                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground" data-contract="crm-rodape">
                  <span>Total: {leads.total}</span>
                  <div className="ml-auto flex gap-2">
                    <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Adicionar ao local</a></Button>
                    <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Remover do local</a></Button>
                  </div>
                </div>
              </>
            ) : null}
          </Deferred>
        </CardContent>
      </Card>

      <Sheet open={!!lead} onOpenChange={(aberto) => { if (!aberto) verLead(filtros, null); }}>
        {lead ? (
          <SheetContent side="right" className="w-full sm:max-w-[760px]">
            <SheetHeader>
              {lead.estagio ? <Badge variant="info" className="w-fit">{lead.estagio}</Badge> : null}
              <SheetTitle>{lead.nome}</SheetTitle>
              <SheetDescription>
                {[lead.codigo, lead.fonte, lead.atribuidos.join(', ')].filter(Boolean).join(' · ') || '—'}
              </SheetDescription>
            </SheetHeader>
            <div className="flex flex-col gap-4 overflow-y-auto px-4">
              <Secao titulo="Informação do lead" linhas={[
                ['Celular', lead.celular], ['E-mail', lead.email], ['CNPJ / CPF', lead.documento],
                ['Endereço', lead.endereco], ['Adicionado em', lead.adicionado_em],
              ]} />
              <Secao titulo="Acompanhamento" linhas={[['Último', lead.ultimo], ['Próximo', lead.proximo]]} />
            </div>
            <SheetFooter className="flex-row justify-end gap-2">
              <Button asChild variant="outline"><a href={`${ROTA}/${lead.id}`}>Abrir ficha completa</a></Button>
              <Button asChild><a href={CLASSICO}>Converter para cliente</a></Button>
            </SheetFooter>
          </SheetContent>
        ) : null}
      </Sheet>
    </div>
  );
}

function Secao({ titulo, linhas }: { titulo: string; linhas: [string, string | null][] }) {
  return (
    <section className="flex flex-col gap-2">
      <h4 className="text-sm font-medium">{titulo}</h4>
      <dl className="grid grid-cols-[140px_1fr] gap-x-3 gap-y-1 text-sm">
        {linhas.map(([rotulo, valor]) => (
          <div key={rotulo} className="contents">
            <dt className="text-muted-foreground">{rotulo}</dt>
            <dd>{valor || '—'}</dd>
          </div>
        ))}
      </dl>
    </section>
  );
}

function Filtro({ rotulo: nome, campo, lista, filtros }: { rotulo: string; campo: string; lista?: Opcao[]; filtros: Filtros }) {
  return (
    <div className="flex flex-col gap-1">
      <Label>{nome}</Label>
      <Select value={filtros[campo] || TODOS} onValueChange={(v) => filtrar(filtros, { [campo]: v === TODOS ? undefined : v })}>
        <SelectTrigger aria-label={nome}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}

const texto = (header: string, acc: (r: Lead) => ReactNode, width = 150): ColumnDef<Lead> => ({
  id: header, header, cell: ({ row }) => acc(row.original) || '—', meta: { width },
});

const COLUNAS: ColumnDef<Lead>[] = [
  texto('ID do contato', (r) => r.codigo, 118),
  texto('Nome', (r) => r.nome, 210),
  texto('Celular', (r) => r.celular, 138),
  texto('E-mail', (r) => r.email, 226),
  texto('Fonte', (r) => r.fonte, 126),
  texto('Último acompanhamento', (r) => r.ultimo, 178),
  texto('Próximo acompanhamento', (r) => r.proximo, 178),
  texto('Estágio de vida', (r) => (r.estagio ? <Badge variant="neutral">{r.estagio}</Badge> : null), 150),
  texto('Atribuído a', (r) => r.atribuidos.join(', '), 150),
  texto('Endereço', (r) => r.endereco, 250),
  texto('CNPJ / CPF', (r) => r.documento, 158),
  texto('Adicionado em', (r) => r.adicionado_em, 128),
];

LeadsIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
