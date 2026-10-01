// @memcofre
//   tela: /crm/follow-ups
//   module: Crm
//   stories: thread Crm/03 (ScheduleController@index · Blade → Inertia)
//   permissao: crm.access_all_schedule | crm.access_own_schedule
//
// Lista de acompanhamentos do CRM. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-blade.jsx → TelaAcompanhamentos()
// Contrato: governance/design/contracts/crm-acompanhamentos.contract.json
//
// Esta onda é LEITURA. Os modais de escrita (adicionar, recorrente, antecipado, editar, log)
// seguem na tela Blade, aberta por `?classico=1` — os três botões da toolbar levam pra lá.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import PageHeader from '@/Components/shared/PageHeader';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/follow-ups';
const CLASSICO = `${ROTA}?classico=1`;
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Opcoes {
  contatos: Opcao[]; usuarios: Opcao[]; status: Opcao[]; tipos: Opcao[]; categorias: Opcao[]; por: Opcao[];
}
interface Acompanhamento {
  id: number; titulo: string; contato: string; inicio: string | null; fim: string | null;
  status: string | null; tipo: string | null; categoria: string | null; atribuidos: string[];
  descricao: string; por: string | null; em_dias: number | null;
  adicionado_por: string; adicionado_em: string | null;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  opcoes?: Opcoes;
  acompanhamentos?: PaginatorShape<Acompanhamento>;
}

const TOM: Record<string, 'info' | 'warning' | 'success' | 'neutral'> = {
  scheduled: 'info', open: 'warning', completed: 'success', cancelled: 'neutral', canceled: 'neutral',
};

const rotulo = (lista: Opcao[] | undefined, v: string | null) => lista?.find((o) => o.value === v)?.label ?? v ?? '—';

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

export default function AcompanhamentosIndex({ filtros, opcoes, acompanhamentos }: Props) {
  const recorrente = filtros.is_recursive === '1';

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Acompanhamentos" description="Ligações, encontros, SMS e e-mails agendados com clientes e leads." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <Deferred data="opcoes" fallback={<Skeleton className="h-16 w-full" />}>
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
              <Filtro rotulo="Contato" campo="contact_id" lista={opcoes?.contatos} filtros={filtros} />
              <Filtro rotulo="Atribuído" campo="assgined_to" lista={opcoes?.usuarios} filtros={filtros} />
              <Filtro rotulo="Status" campo="status" lista={opcoes?.status} filtros={filtros} />
              <Filtro rotulo="Tipo de acompanhamento" campo="schedule_type" lista={opcoes?.tipos} filtros={filtros} />
              <div className="flex flex-col gap-1">
                <Label>Intervalo de datas</Label>
                <div className="flex gap-2">
                  <Input type="date" aria-label="Início do intervalo" value={filtros.start_date_time ?? ''} onChange={(e) => filtrar(filtros, { start_date_time: e.target.value || undefined })} />
                  <Input type="date" aria-label="Fim do intervalo" value={filtros.end_date_time ?? ''} onChange={(e) => filtrar(filtros, { end_date_time: e.target.value || undefined })} />
                </div>
              </div>
              <Filtro rotulo="Acompanhamento por" campo="follow_up_by" lista={opcoes?.por} filtros={filtros} />
              <Filtro rotulo="Categoria" campo="followup_category_id" lista={opcoes?.categorias} filtros={filtros} />
            </div>
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-acompanhamentos">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Todos os acompanhamentos</h3>
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Recorrente</a></Button>
              <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Acompanhamento antecipado</a></Button>
              <Button asChild size="sm"><a href={CLASSICO}>Adicionar</a></Button>
            </div>
          </div>

          <div role="tablist" aria-label="Abas de acompanhamento" className="flex gap-1 border-b">
            <Aba ativa={!recorrente} onClick={() => filtrar(filtros, { is_recursive: undefined })}>Acompanhamentos</Aba>
            <Aba ativa={recorrente} onClick={() => filtrar(filtros, { is_recursive: '1' })}>Acompanhamento recorrente</Aba>
          </div>

          <Deferred data="acompanhamentos" fallback={<Skeleton className="h-64 w-full" />}>
            {acompanhamentos ? (
              <>
                <DataTable<Acompanhamento>
                  caption={recorrente ? 'Acompanhamentos recorrentes' : 'Acompanhamentos'}
                  columns={colunas(opcoes, recorrente)}
                  data={acompanhamentos.data}
                  pagination={acompanhamentos}
                  endpoint={ROTA}
                  filters={filtros}
                  initialSearch={filtros.q ?? ''}
                  searchPlaceholder="Buscar por título ou contato"
                  emptyMessage="Nada com esses filtros"
                  rowKey={(r) => r.id}
                />
                <div className="text-xs text-muted-foreground" data-contract="crm-rodape">
                  Total: {acompanhamentos.total}
                </div>
              </>
            ) : null}
          </Deferred>
        </CardContent>
      </Card>
    </div>
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

function Aba({ ativa, onClick, children }: { ativa: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button" role="tab" aria-selected={ativa} onClick={onClick}
      className={`-mb-px border-b-2 px-3 py-1.5 text-sm ${ativa ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
    >
      {children}
    </button>
  );
}

function colunas(opcoes: Opcoes | undefined, recorrente: boolean): ColumnDef<Acompanhamento>[] {
  const texto = (header: string, acc: (r: Acompanhamento) => ReactNode, width = 150): ColumnDef<Acompanhamento> => ({
    id: header, header, cell: ({ row }) => acc(row.original) ?? '—', meta: { width },
  });

  return [
    ...(recorrente ? [] : [
      texto('Contato', (r) => r.contato, 200),
      texto('Início', (r) => r.inicio),
      texto('Fim', (r) => r.fim),
    ]),
    texto('Status', (r) => r.status ? <Badge variant={TOM[r.status] ?? 'neutral'}>{rotulo(opcoes?.status, r.status)}</Badge> : '—', 126),
    texto('Tipo de acompanhamento', (r) => rotulo(opcoes?.tipos, r.tipo), 168),
    texto('Categoria', (r) => r.categoria, 138),
    ...(recorrente ? [
      texto('Acompanhamento por', (r) => rotulo(opcoes?.por, r.por), 178),
      texto('Em dias', (r) => r.em_dias, 100),
    ] : []),
    texto('Atribuído a', (r) => r.atribuidos.join(', ') || '—', 178),
    texto('Descrição', (r) => r.descricao || '—', 280),
    texto('Título', (r) => r.titulo, 200),
    texto('Adicionado por', (r) => r.adicionado_por),
    texto('Adicionado em', (r) => r.adicionado_em, 128),
  ];
}

AcompanhamentosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
