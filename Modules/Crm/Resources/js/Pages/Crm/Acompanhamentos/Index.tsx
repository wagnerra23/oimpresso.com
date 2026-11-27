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
// aqui e gravam pelas mesmas rotas da Blade. O "Acompanhamento antecipado" (PR-c2) também: lê os
// grupos do getFollowUpGroups em JSON e grava pelo store, que valida o negócio (PR-c1).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { ChevronDown, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import CrmCabecalho from '../_components/CrmCabecalho';
import { CrmCartao, CrmDensidade, CrmFiltros, CrmPilula } from '../_components/CrmBlocos';
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
import FormAntecipado from './_components/FormAntecipado';
import DrawerAcompanhamento from './_components/DrawerAcompanhamento';
import FormChamadoGarantia from './_components/FormChamadoGarantia';
import TabelaGarantia from './_components/TabelaGarantia';
import type { Garantia } from './_components/garantia';
import { NOVO, NOVO_RECORRENTE, TOM, csrf, type Recorrente, type Valores } from './_components/acompanhamento';
import { useCockpit } from '@/Hooks/useCockpit';

const ROTA = '/crm/follow-ups';
const TODOS = '__todos';

interface Opcao { value: string; label: string }
interface Opcoes {
  contatos: Opcao[]; usuarios: Opcao[]; status: Opcao[]; tipos: Opcao[]; categorias: Opcao[]; por: Opcao[]; notificar: Opcao[];
  recorrencia: (Opcao & { grupo: string })[];
}
interface Acompanhamento {
  id: number; titulo: string; contato: string; inicio: string | null; fim: string | null;
  status: string | null; tipo: string | null; categoria: string | null; atribuidos: string[];
  descricao: string; informacao_adicional: string | null; por: string | null; em_dias: number | null;
  adicionado_por: string; adicionado_em: string | null;
  editar: Omit<Valores, 'title'> & Pick<Recorrente, 'follow_up_by' | 'follow_up_by_value' | 'recursion_days'>;
}
type Filtros = Record<string, string | undefined>;
interface Props {
  filtros: Filtros;
  opcoes?: Opcoes;
  acompanhamentos?: PaginatorShape<Acompanhamento>;
  contagem?: { total: number; status: Record<string, number>; tipo: Record<string, number> };
  /** Aba Garantia (CRM-30): nula com CRM_GARANTIA_SLA_ENABLED desligada — e aí a aba não aparece. */
  garantia?: Garantia | null;
}


const rotulo = (lista: Opcao[] | undefined, v: string | null) => lista?.find((o) => o.value === v)?.label ?? v ?? '—';
/** "Agendado: 3 · Concluído: 1" — só os valores que aparecem na consulta. */
const resumo = (n: Record<string, number> | undefined, lista: Opcao[] | undefined) =>
  Object.entries(n ?? {}).filter(([, q]) => q > 0).map(([k, q]) => `${rotulo(lista, k)}: ${q}`).join(' · ') || '—';

function filtrar(filtros: Filtros, mudanca: Filtros) {
  router.get(ROTA, { ...filtros, ...mudanca }, { preserveState: true, preserveScroll: true, replace: true });
}

export default function AcompanhamentosIndex({ filtros, opcoes, acompanhamentos, contagem, garantia }: Props) {
  const cockpit = useCockpit();
  const recorrente = filtros.is_recursive === '1';
  const [form, setForm] = useState<{ id: number | null; inicial: Valores } | null>(null);
  const [excluir, setExcluir] = useState<Acompanhamento | null>(null);
  const [rec, setRec] = useState<{ id: number | null; inicial: Recorrente } | null>(null);
  const [registro, setRegistro] = useState<Acompanhamento | null>(null);
  const [antecipado, setAntecipado] = useState(false);
  const [ver, setVer] = useState<Acompanhamento | null>(null);
  const [compacta, setCompacta] = useState(false);
  // A aba Garantia não vai ao servidor: os chamados já vêm na prop deferida `garantia`.
  const [naGarantia, setNaGarantia] = useState(false);
  const [chamado, setChamado] = useState(false);
  const comGarantia = garantia !== null;
  const recarregar = () => router.reload({ only: ['acompanhamentos', 'contagem'] });
  const acoes = {
    editar: (r: Acompanhamento) => setForm({ id: r.id, inicial: { ...r.editar, title: r.titulo } }),
    editarRecorrente: (r: Acompanhamento) => {
      const { contact_id: _c, start_datetime: _s, end_datetime: _e, ...resto } = r.editar;
      setRec({ id: r.id, inicial: { ...resto, title: r.titulo } });
    },
    registrar: setRegistro,
    excluir: setExcluir,
    ver: setVer,
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
    <div className="flex flex-col">
      <CrmCabecalho aba="Acompanhamentos" />

      {/* Corpo da página = `.pb-body` do protótipo (crm-blade.jsx · produto-blade.css): 16px 24px 28px e gap 14px.
          O cabeçalho já tem o próprio px-6; sem este corpo os blocos encostavam nas bordas do painel. */}
      <div className="flex flex-col gap-3.5 px-6 pt-4 pb-7" data-contract="crm-corpo">

      <CrmFiltros data-contract="crm-filtros">
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
      </CrmFiltros>

      <CrmCartao
        data-contract="crm-acompanhamentos"
        titulo={naGarantia ? 'Chamados de garantia' : 'Todos os acompanhamentos'}
        nota={naGarantia ? (garantia ? `${garantia.itens.length}` : null) : acompanhamentos ? `${acompanhamentos.data.length} de ${acompanhamentos.total}` : null}
        semPadding
      >
        <div className="flex flex-col gap-3 p-3">
          <div className="flex flex-wrap items-center gap-2" data-contract="crm-toolbar">
            <div className="ml-auto flex flex-wrap items-center gap-2">
              <CrmDensidade compacta={compacta} onChange={setCompacta} />
              <Button variant="outline" size="sm" onClick={() => setRec({ id: null, inicial: NOVO_RECORRENTE })}><Plus className="size-3" /> Recorrente</Button>
              <Button variant="outline" size="sm" onClick={() => setAntecipado(true)}><Plus className="size-3" /> Acompanhamento antecipado</Button>
              {naGarantia
                ? <Button size="sm" className="text-white" onClick={() => setChamado(true)}><Plus className="size-3" /> Abrir chamado de garantia</Button>
                : <Button size="sm" onClick={() => setForm({ id: null, inicial: NOVO })}><Plus className="size-3" /> Adicionar</Button>}
            </div>
          </div>

          <SubNav
            ariaLabel="Abas de acompanhamento"
            value={naGarantia ? 'garantia' : recorrente ? 'recorrente' : 'todos'}
            onChange={(v) => {
              setNaGarantia(v === 'garantia');
              if (v !== 'garantia' && (v === 'recorrente') !== recorrente) filtrar(filtros, { is_recursive: v === 'recorrente' ? '1' : undefined });
            }}
            items={[
              { value: 'todos', label: 'Acompanhamentos' },
              { value: 'recorrente', label: 'Acompanhamento recorrente' },
              ...(comGarantia ? [{ value: 'garantia', label: garantia ? `Garantia (${garantia.contagem.abertos})` : 'Garantia' }] : []),
            ]}
          />

          {naGarantia ? (
            <Deferred data="garantia" fallback={<Skeleton className="h-64 w-full" />}>
              {garantia ? <TabelaGarantia garantia={garantia} status={opcoes?.status} compacta={compacta} /> : null}
            </Deferred>
          ) : (
          <Deferred data="acompanhamentos" fallback={<Skeleton className="h-64 w-full" />}>
            {acompanhamentos ? (
              <>
                <DataTable<Acompanhamento>
                  caption={recorrente ? 'Acompanhamentos recorrentes' : 'Acompanhamentos'}
                  columns={colunas(opcoes, recorrente, acoes, cockpit)}
                  data={acompanhamentos.data}
                  pagination={acompanhamentos}
                  endpoint={ROTA}
                  filters={filtros}
                  initialSearch={filtros.q ?? ''}
                  searchPlaceholder="Buscar por título ou contato"
                  emptyMessage="Nada com esses filtros"
                  density={compacta ? 'grid' : 'grid-relaxed'}
                  zebra={false}
                  tableWrapperClassName="overflow-hidden rounded-md border border-border"
                  rowKey={(r) => r.id}
                  onRowClick={recorrente ? undefined : setVer}
                />
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground" data-contract="crm-rodape">
                  <span>Total: {acompanhamentos.total}</span>
                  {/* Contagens do protótipo, sobre a consulta filtrada inteira (não só a página). */}
                  <Deferred data="contagem" fallback={null}>
                    <span className="ml-auto">{resumo(contagem?.status, opcoes?.status)}</span>
                    <span>{resumo(contagem?.tipo, opcoes?.tipos)}</span>
                  </Deferred>
                </div>
              </>
            ) : null}
          </Deferred>
          )}
        </div>
      </CrmCartao>

      {chamado ? (
        <FormChamadoGarantia opcoes={opcoes} garantia={garantia ?? undefined} onFechar={() => setChamado(false)}
          onSalvo={() => { setChamado(false); router.reload({ only: ['garantia'] }); }} />
      ) : null}

      {form ? (
        <FormAcompanhamento key={form.id ?? 'novo'} id={form.id} inicial={form.inicial} opcoes={opcoes}
          onFechar={() => setForm(null)} onSalvo={() => { setForm(null); recarregar(); }} />
      ) : null}

      {rec ? (
        <FormRecorrente key={rec.id ?? 'novo'} id={rec.id} inicial={rec.inicial} opcoes={opcoes}
          onFechar={() => setRec(null)} onSalvo={() => { setRec(null); recarregar(); }} />
      ) : null}

      {antecipado ? (
        <FormAntecipado opcoes={opcoes} onFechar={() => setAntecipado(false)} onSalvo={() => { setAntecipado(false); recarregar(); }} />
      ) : null}

      {ver ? (
        <DrawerAcompanhamento item={ver} opcoes={opcoes} onFechar={() => setVer(null)}
          onRegistrar={() => { setRegistro(ver); setVer(null); }}
          onConcluido={() => { setVer(null); recarregar(); }} />
      ) : null}

      {registro ? (
        <FormRegistro acompanhamento={registro} opcoes={opcoes}
          onFechar={() => setRegistro(null)} onSalvo={() => { setRegistro(null); recarregar(); }} />
      ) : null}

      <AlertDialog open={!!excluir} onOpenChange={(aberto) => { if (!aberto) setExcluir(null); }}>
        <AlertDialogContent container={cockpit}>
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
    </div>
  );
}

function Filtro({ rotulo: nome, campo, lista, filtros }: { rotulo: string; campo: string; lista?: Opcao[]; filtros: Filtros }) {
  const cockpit = useCockpit();
  return (
    <div className="flex flex-col gap-1">
      <Label>{nome}</Label>
      <Select value={filtros[campo] || TODOS} onValueChange={(v) => filtrar(filtros, { [campo]: v === TODOS ? undefined : v })}>
        <SelectTrigger aria-label={nome}><SelectValue /></SelectTrigger>
        <SelectContent container={cockpit}>
          <SelectItem value={TODOS}>Todos</SelectItem>
          {(lista ?? []).map((o) => <SafeSelectItem key={o.value} value={o.value}>{o.label}</SafeSelectItem>)}
        </SelectContent>
      </Select>
    </div>
  );
}

interface Acoes {
  editar: (r: Acompanhamento) => void; editarRecorrente: (r: Acompanhamento) => void;
  registrar: (r: Acompanhamento) => void; excluir: (r: Acompanhamento) => void; ver: (r: Acompanhamento) => void;
}

function colunas(opcoes: Opcoes | undefined, recorrente: boolean, acoes: Acoes, cockpit: HTMLElement | null): ColumnDef<Acompanhamento>[] {
  const texto = (header: string, acc: (r: Acompanhamento) => ReactNode, width = 150, mono = false): ColumnDef<Acompanhamento> => ({
    id: header, header, cell: ({ row }) => acc(row.original) ?? '—', meta: { width, mono },
  });

  const acao: ColumnDef<Acompanhamento> = {
    id: 'Ação', header: 'Ação', meta: { width: 92 },
    cell: ({ row }) => (
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          {/* A linha abre o drawer; o menu não pode abrir os dois (aviso do DataTable). */}
          <Button variant="ghost" size="sm" aria-label={`Ações de ${row.original.titulo}`}
            onClick={(e) => e.stopPropagation()} onKeyDown={(e) => e.stopPropagation()}>Ações <ChevronDown className="size-3" /></Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent container={cockpit} align="start" onClick={(e) => e.stopPropagation()}>
          {/* Recorrente tem modal próprio; o registro (log) só existe no avulso, como na Blade. */}
          {recorrente
            ? <DropdownMenuItem onSelect={() => acoes.editarRecorrente(row.original)}>Editar</DropdownMenuItem>
            : <>
                <DropdownMenuItem onSelect={() => acoes.ver(row.original)}>Ver acompanhamento</DropdownMenuItem>
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
      texto('Início', (r) => r.inicio, 150, true),
      texto('Fim', (r) => r.fim, 150, true),
    ]),
    texto('Status', (r) => r.status ? <CrmPilula tom={TOM[r.status] ?? 'mute'}>{rotulo(opcoes?.status, r.status)}</CrmPilula> : '—', 126),
    texto('Tipo de acompanhamento', (r) => rotulo(opcoes?.tipos, r.tipo), 168),
    texto('Categoria', (r) => r.categoria, 138),
    ...(recorrente ? [
      texto('Acompanhamento por', (r) => rotulo(opcoes?.por, r.por), 178),
      texto('Em dias', (r) => r.em_dias, 100),
    ] : []),
    texto('Atribuído a', (r) => r.atribuidos.join(', ') || '—', 178),
    texto('Descrição', (r) => r.descricao || '—', 280),
    texto('Informação adicional', (r) => r.informacao_adicional || '—', 230),
    texto('Título', (r) => r.titulo, 200),
    texto('Adicionado por', (r) => r.adicionado_por),
    texto('Adicionado em', (r) => r.adicionado_em, 128, true),
  ];
}

AcompanhamentosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
