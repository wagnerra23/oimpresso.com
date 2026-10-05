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
// Escrita (thread Crm/07): adicionar, editar, excluir (PR-a), recorrente e registro (PR-b) abrem
// aqui e gravam pelas mesmas rotas da Blade. Só o "Acompanhamento antecipado" segue na Blade
// (`?classico=1`): ele monta grupos por fatura via getFollowUpGroups, que devolve HTML.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { MoreHorizontal } from 'lucide-react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { PageHeader } from '@/Components/PageHeader';
import SubNav from '@/Components/shared/SubNav';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import FormAcompanhamento from './_components/FormAcompanhamento';
import FormRecorrente from './_components/FormRecorrente';
import FormRegistro from './_components/FormRegistro';
import { NOVO, NOVO_RECORRENTE, csrf, type Recorrente, type Valores } from './_components/acompanhamento';

const ROTA = '/crm/follow-ups';
const CLASSICO = `${ROTA}?classico=1`;
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Opcoes {
  contatos: Opcao[]; usuarios: Opcao[]; status: Opcao[]; tipos: Opcao[]; categorias: Opcao[]; por: Opcao[]; notificar: Opcao[];
  recorrencia: (Opcao & { grupo: string })[];
}
interface Acompanhamento {
  id: number; titulo: string; contato: string; inicio: string | null; fim: string | null;
  status: string | null; tipo: string | null; categoria: string | null; atribuidos: string[];
  descricao: string; por: string | null; em_dias: number | null;
  adicionado_por: string; adicionado_em: string | null;
  editar: Omit<Valores, 'title'> & Pick<Recorrente, 'follow_up_by' | 'follow_up_by_value' | 'recursion_days'>;
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
  const [form, setForm] = useState<{ id: number | null; inicial: Valores } | null>(null);
  const [excluir, setExcluir] = useState<Acompanhamento | null>(null);
  const [rec, setRec] = useState<{ id: number | null; inicial: Recorrente } | null>(null);
  const [registro, setRegistro] = useState<Acompanhamento | null>(null);
  const recarregar = () => router.reload({ only: ['acompanhamentos'] });
  const acoes = {
    editar: (r: Acompanhamento) => setForm({ id: r.id, inicial: { ...r.editar, title: r.titulo } }),
    editarRecorrente: (r: Acompanhamento) => {
      const { contact_id: _c, start_datetime: _s, end_datetime: _e, ...resto } = r.editar;
      setRec({ id: r.id, inicial: { ...resto, title: r.titulo } });
    },
    registrar: setRegistro,
    excluir: setExcluir,
  };

  async function confirmarExclusao() {
    if (!excluir) return;
    // DELETE /crm/follow-ups/{id}: o destroy só responde a ajax e devolve `{success, msg}`.
    const r = await fetch(`/crm/follow-ups/${excluir.id}`, {
      method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
    }).catch(() => null);
    const json = r ? await r.json().catch(() => ({})) : {};
    if (r?.ok && json.success) { toast.success('Acompanhamento excluído.'); recarregar(); } else toast.error(json.msg || 'Não foi possível excluir.');
    setExcluir(null);
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Acompanhamentos" subtitle="Ligações, encontros, SMS e e-mails agendados com clientes e leads." />

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
              <Button variant="outline" size="sm" onClick={() => setRec({ id: null, inicial: NOVO_RECORRENTE })}>Recorrente</Button>
              <Button asChild variant="outline" size="sm"><a href={CLASSICO}>Acompanhamento antecipado</a></Button>
              <Button size="sm" onClick={() => setForm({ id: null, inicial: NOVO })}>Adicionar</Button>
            </div>
          </div>

          <SubNav
            ariaLabel="Abas de acompanhamento"
            value={recorrente ? 'recorrente' : 'todos'}
            onChange={(v) => filtrar(filtros, { is_recursive: v === 'recorrente' ? '1' : undefined })}
            items={[
              { value: 'todos', label: 'Acompanhamentos' },
              { value: 'recorrente', label: 'Acompanhamento recorrente' },
            ]}
          />

          <Deferred data="acompanhamentos" fallback={<Skeleton className="h-64 w-full" />}>
            {acompanhamentos ? (
              <>
                <DataTable<Acompanhamento>
                  caption={recorrente ? 'Acompanhamentos recorrentes' : 'Acompanhamentos'}
                  columns={colunas(opcoes, recorrente, acoes)}
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

      {form ? (
        <FormAcompanhamento key={form.id ?? 'novo'} id={form.id} inicial={form.inicial} opcoes={opcoes}
          onFechar={() => setForm(null)} onSalvo={() => { setForm(null); recarregar(); }} />
      ) : null}

      {rec ? (
        <FormRecorrente key={rec.id ?? 'novo'} id={rec.id} inicial={rec.inicial} opcoes={opcoes}
          onFechar={() => setRec(null)} onSalvo={() => { setRec(null); recarregar(); }} />
      ) : null}

      {registro ? (
        <FormRegistro acompanhamento={registro} opcoes={opcoes}
          onFechar={() => setRegistro(null)} onSalvo={() => { setRegistro(null); recarregar(); }} />
      ) : null}

      <AlertDialog open={!!excluir} onOpenChange={(aberto) => { if (!aberto) setExcluir(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir acompanhamento?</AlertDialogTitle>
            <AlertDialogDescription>{excluir?.titulo} — esta ação não pode ser desfeita.</AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
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

interface Acoes {
  editar: (r: Acompanhamento) => void; editarRecorrente: (r: Acompanhamento) => void;
  registrar: (r: Acompanhamento) => void; excluir: (r: Acompanhamento) => void;
}

function colunas(opcoes: Opcoes | undefined, recorrente: boolean, acoes: Acoes): ColumnDef<Acompanhamento>[] {
  const texto = (header: string, acc: (r: Acompanhamento) => ReactNode, width = 150): ColumnDef<Acompanhamento> => ({
    id: header, header, cell: ({ row }) => acc(row.original) ?? '—', meta: { width },
  });

  const acao: ColumnDef<Acompanhamento> = {
    id: 'Ação', header: 'Ação', meta: { width: 92 },
    cell: ({ row }) => (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="ghost" size="sm" aria-label={`Ações de ${row.original.titulo}`}><MoreHorizontal className="size-4" /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="start">
          {/* Recorrente tem modal próprio; o registro (log) só existe no avulso, como na Blade. */}
          {recorrente
            ? <DropdownMenuItem onSelect={() => acoes.editarRecorrente(row.original)}>Editar</DropdownMenuItem>
            : <>
                <DropdownMenuItem onSelect={() => acoes.editar(row.original)}>Editar</DropdownMenuItem>
                <DropdownMenuItem onSelect={() => acoes.registrar(row.original)}>Adicionar registro</DropdownMenuItem>
              </>}
          <DropdownMenuItem variant="destructive" onSelect={() => acoes.excluir(row.original)}>Excluir</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    ),
  };

  return [
    acao,
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
