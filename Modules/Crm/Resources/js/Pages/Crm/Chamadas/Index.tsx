// @memcofre
//   tela: /crm/call-log
//   module: Crm
//   stories: thread Crm/12 (CallLogController@index · Blade → Inertia)
//   permissao: crm.view_all_call_log | crm.view_own_call_log (+ config enable_crm_call_log)
//
// Registro de chamadas do CRM, atrás da flag useV2CrmChamadas (OFF). Charter: ./Index.charter.md
// Casos: ./Index.casos.md · Âncora de design: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx → Chamadas()
// Contrato: governance/design/contracts/crm-chamadas.contract.json
//
// Os registros chegam pelo app (API do Connector); a tela só lista e, para o admin, exclui em massa
// pela MESMA rota da Blade (`POST /crm/mass-delete-call-log`).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type DensidadeDaTabela, type PaginatorShape } from '@/Components/shared/DataTable';

const ROTA = '/crm/call-log';
const EXCLUIR = '/crm/mass-delete-call-log';
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Chamada {
  id: number; inicio: string | null; fim: string | null; duracao: string | null; tipo: string | null;
  numero: string; nome_no_celular: string | null; contato: string | null; usuario: string | null; criado_por: string | null;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  pode_excluir: boolean;
  opcoes?: { contatos: Opcao[]; usuarios: Opcao[] };
  chamadas?: PaginatorShape<Chamada>;
}

const TIPO: Record<string, { rotulo: string; variante: 'info' | 'success' | 'danger' }> = {
  incoming: { rotulo: 'Recebida', variante: 'info' },
  outgoing: { rotulo: 'Efetuada', variante: 'success' },
  missed: { rotulo: 'Perdida', variante: 'danger' },
};

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

export default function ChamadasIndex({ filtros, pode_excluir, opcoes, chamadas }: Props) {
  const [densidade, setDensidade] = useState<DensidadeDaTabela>('default');
  const [marcadas, setMarcadas] = useState<number[]>([]);
  const pagina = chamadas?.data ?? [];
  const todasMarcadas = pagina.length > 0 && pagina.every((c) => marcadas.includes(c.id));

  function excluir() {
    if (!window.confirm(`Excluir ${marcadas.length} registro(s) de chamada? Esta ação não pode ser desfeita.`)) return;
    router.post(EXCLUIR, { selected_rows: marcadas.join(',') }, { preserveScroll: true, onSuccess: () => setMarcadas([]) });
  }

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="Registro de chamadas" subtitle="Ligações registradas pelo app, por contato e por usuário." />

      <Card data-contract="crm-filtros">
        <CardContent className="flex flex-col gap-3 p-4">
          <h3 className="text-sm font-medium">Filtros</h3>
          <Deferred data="opcoes" fallback={<Skeleton className="h-16 w-full" />}>
            <div className="grid gap-3 sm:grid-cols-4">
              <Filtro rotulo="Contato" campo="contact_id" lista={opcoes?.contatos} filtros={filtros} />
              <Filtro rotulo="Registro criado por" campo="user_id" lista={opcoes?.usuarios} filtros={filtros} />
              <div className="flex flex-col gap-1">
                <Label htmlFor="cha-de">Intervalo de datas — de</Label>
                <Input id="cha-de" type="date" value={filtros.start_time ?? ''} onChange={(e) => filtrar(filtros, { start_time: e.target.value || undefined })} />
              </div>
              <div className="flex flex-col gap-1">
                <Label htmlFor="cha-ate">até</Label>
                <Input id="cha-ate" type="date" value={filtros.end_time ?? ''} onChange={(e) => filtrar(filtros, { end_time: e.target.value || undefined })} />
              </div>
            </div>
          </Deferred>
        </CardContent>
      </Card>

      <Card data-contract="crm-chamadas">
        <CardContent className="flex flex-col gap-3 p-4">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-sm font-medium">Registro de chamadas</h3>
            <div className="ml-auto flex flex-wrap gap-2" data-contract="crm-toolbar">
              <Button variant={densidade === 'default' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'default'} onClick={() => setDensidade('default')}>Confortável</Button>
              <Button variant={densidade === 'dense' ? 'secondary' : 'outline'} size="sm" aria-pressed={densidade === 'dense'} onClick={() => setDensidade('dense')}>Compacto</Button>
            </div>
          </div>

          <Deferred data="chamadas" fallback={<Skeleton className="h-64 w-full" />}>
            {chamadas ? (
              <>
                <DataTable<Chamada>
                  caption="Registro de chamadas"
                  columns={COLUNAS}
                  data={chamadas.data}
                  pagination={chamadas}
                  endpoint={ROTA}
                  filters={filtros}
                  initialSearch={filtros.q ?? ''}
                  searchPlaceholder="Buscar por contato ou número"
                  emptyMessage="Nenhum registro com esses filtros"
                  rowKey={(r) => r.id}
                  density={densidade}
                  selection={pode_excluir ? {
                    isSelected: (r) => marcadas.includes(r.id),
                    onToggle: (r) => setMarcadas((m) => (m.includes(r.id) ? m.filter((x) => x !== r.id) : [...m, r.id])),
                    allState: todasMarcadas ? 'all' : marcadas.length ? 'some' : 'none',
                    onToggleAll: (marcar) => setMarcadas(marcar ? pagina.map((c) => c.id) : []),
                    rowLabel: (r) => `chamada de ${r.inicio ?? r.numero}`,
                  } : undefined}
                />
                {pode_excluir ? (
                  <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground" data-contract="crm-rodape">
                    <span>{marcadas.length ? `${marcadas.length} registro(s) selecionado(s)` : 'Selecione linhas para excluir em massa.'}</span>
                    <Button className="ml-auto" variant="outline" size="sm" disabled={!marcadas.length} onClick={excluir}>Excluir selecionados</Button>
                  </div>
                ) : null}
              </>
            ) : null}
          </Deferred>
        </CardContent>
      </Card>
    </div>
  );
}

function Filtro({ rotulo, campo, lista, filtros }: { rotulo: string; campo: string; lista?: Opcao[]; filtros: Filtros }) {
  return (
    <div className="flex flex-col gap-1">
      <Label>{rotulo}</Label>
      <Select value={filtros[campo] || TODOS} onValueChange={(v) => filtrar(filtros, { [campo]: v === TODOS ? undefined : v })}>
        <SelectTrigger aria-label={rotulo}><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}

const texto = (header: string, acc: (r: Chamada) => ReactNode, width = 150): ColumnDef<Chamada> => ({
  id: header, header, cell: ({ row }) => acc(row.original) || '—', meta: { width },
});

const COLUNAS: ColumnDef<Chamada>[] = [
  texto('Hora de início', (r) => r.inicio, 150),
  texto('Hora de término', (r) => r.fim, 150),
  texto('Duração da chamada', (r) => r.duracao, 156),
  texto('Tipo de chamada', (r) => {
    const t = r.tipo ? TIPO[r.tipo] : undefined;
    return t ? <Badge variant={t.variante}>{t.rotulo}</Badge> : r.tipo;
  }, 140),
  texto('Nº do contato', (r) => (r.nome_no_celular ? `${r.numero} (${r.nome_no_celular})` : r.numero), 168),
  texto('Contato', (r) => r.contato, 210),
  texto('Usuário', (r) => r.usuario, 160),
  texto('Registro criado por', (r) => r.criado_por, 170),
];

ChamadasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
