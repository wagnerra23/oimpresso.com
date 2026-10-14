// @memcofre
//   tela: /bookings
//   module: Restaurante
//   stories: thread restaurante/playbook/03 (Reservas)
//   permissao: crud_all_bookings | crud_own_bookings
//
// Reservas do negócio. Atrás da flag `useV2RestauranteReservas` (BookingController::FLAG_V2); desligada, a rota segue na
// Blade `restaurant.booking.index`. `?classico=1` abre a Blade com a flag ligada — é lá que se cria, muda a situação e
// exclui. Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Restaurante/RUNBOOK-reservas.md
// Âncora de design: governance/design/targets/restaurante--reservas--index.alvo.json (A1) ← restaurante-operacao.jsx
//   RestauranteOperacaoPage view="cfg-reservas". Contrato: governance/design/contracts/restaurante-reservas.contract.json.
//
// Quem vê quais reservas é decidido no controller (mesma regra do calendário da Blade). A tela só filtra o que vem.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, usePage } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { ExternalLink, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import KpiCard from '@/Components/shared/KpiCard';
import type { ColumnDef } from '@tanstack/react-table';
import { Grid, Inline, Stack } from '@/Components/layout';

export interface Reserva {
  id: number;
  inicio: string;
  fim: string;
  cliente: string;
  mesa: string | null;
  local_id: number;
  local: string | null;
  atendente: string | null;
  situacao: 'waiting' | 'booked' | 'completed' | 'cancelled' | string;
}
interface Props {
  reservas?: Reserva[];
  locais: Record<string, string>;
}
interface ItemDeMenu { label: string; href?: string; children?: ItemDeMenu[] }

const AQUI = '/bookings';
const CLASSICA = '/bookings?classico=1';
/** As telas do Restaurante, na ordem do protótipo. Atendentes fica de fora: o índice não lhe deu dono. */
const TELAS = ['/modules/tables', '/bookings', '/modules/kitchen', '/modules/orders'];
const caminho = (href: string) => new URL(href, window.location.origin).pathname;
const achatar = (itens?: ItemDeMenu[]): ItemDeMenu[] => (itens ?? []).flatMap((i) => [i, ...achatar(i.children)]);

/** Abas derivadas do menu (cada item já sob a permissão dele): sem segundo dono da lista. */
function RestauranteAbas() {
  const { shell } = usePage<{ shell?: { menu?: ItemDeMenu[] } }>().props;
  const porRota = new Map(achatar(shell?.menu).filter((i) => i.href && i.href !== '#').map((i) => [caminho(i.href!), i.label]));
  const ghosts: PageHeaderGhost[] = TELAS.filter((r) => porRota.has(r)).map((r) => ({ key: r, label: porRota.get(r)!, href: r }));
  if (ghosts.length < 2) return null;

  return (
    <div data-contract="abas">
      <PageHeaderTabs ghosts={ghosts} activeGhostKey={AQUI} group="sistema" maxVisible={5}
        onGhostChange={(key) => { if (key !== AQUI) window.location.assign(key); }} />
    </div>
  );
}

const SITUACAO: Record<string, { rotulo: string; tom: 'info' | 'warning' | 'success' | 'outline' }> = {
  booked: { rotulo: 'Reservado', tom: 'info' },
  waiting: { rotulo: 'Aguardando', tom: 'warning' },
  completed: { rotulo: 'Concluída', tom: 'success' },
  cancelled: { rotulo: 'Cancelada', tom: 'outline' },
};
/** Sentinela do "todos": o Select do Radix não aceita item de valor vazio (§5 2026-06-29). */
const TODOS_LOCAIS = '__todos__';
const ABERTA = (r: Reserva) => r.situacao === 'booked' || r.situacao === 'waiting';
type Filtro = 'abertas' | 'concluidas' | 'canceladas' | 'todas';
const FILTROS: { k: Filtro; rotulo: string; casa: (r: Reserva) => boolean }[] = [
  { k: 'abertas', rotulo: 'Em aberto', casa: ABERTA },
  { k: 'concluidas', rotulo: 'Concluídas', casa: (r) => r.situacao === 'completed' },
  { k: 'canceladas', rotulo: 'Canceladas', casa: (r) => r.situacao === 'cancelled' },
  { k: 'todas', rotulo: 'Todas', casa: () => true },
];

/** "2026-10-14 10:00:00" → "Amanhã · 10:00" / "14/10 · 10:00", no fuso do navegador como a Blade. */
function quando(iso: string): string {
  const d = new Date(iso.replace(' ', 'T'));
  const hora = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  const dias = Math.round((new Date(d).setHours(0, 0, 0, 0) - new Date().setHours(0, 0, 0, 0)) / 86_400_000);
  const dia = dias === 0 ? 'Hoje' : dias === 1 ? 'Amanhã' : d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  return `${dia} · ${hora}`;
}
const hojeOuDepois = (iso: string) => new Date(iso.replace(' ', 'T')).setHours(0, 0, 0, 0) >= new Date().setHours(0, 0, 0, 0);

function ReservasIndex({ reservas, locais }: Props) {
  const todas = useMemo(() => reservas ?? [], [reservas]);
  const [filtro, setFiltro] = useState<Filtro>('abertas');
  const [local, setLocal] = useState('');
  const opcoesLocal = Object.entries(locais ?? {}).filter(([id]) => Boolean(id));

  const doLocal = todas.filter((r) => !local || String(r.local_id) === local);
  const visiveis = doLocal.filter(FILTROS.find((f) => f.k === filtro)!.casa);
  const adiante = todas.filter((r) => ABERTA(r) && hojeOuDepois(r.inicio)).length;
  const aguardando = todas.filter((r) => r.situacao === 'waiting').length;
  const concluidas = todas.filter((r) => r.situacao === 'completed').length;

  const colunas: ColumnDef<Reserva, unknown>[] = [
    { id: 'inicio', header: 'Início', cell: ({ row: { original: r } }) => <span className="whitespace-nowrap">{quando(r.inicio)}</span> },
    { id: 'cliente', header: 'Cliente', cell: ({ row: { original: r } }) => <span className="font-medium">{r.cliente}</span> },
    { id: 'mesa', header: 'Mesa', cell: ({ row: { original: r } }) => r.mesa ?? '—' },
    { id: 'local', header: 'Local', cell: ({ row: { original: r } }) => r.local ?? '—' },
    { id: 'atendente', header: 'Atendente', cell: ({ row: { original: r } }) => r.atendente ?? '—' },
    {
      id: 'situacao', header: 'Situação',
      cell: ({ row: { original: r } }) => { const s = SITUACAO[r.situacao]; return <Badge variant={s?.tom ?? 'outline'}>{s?.rotulo ?? r.situacao}</Badge>; },
    },
    {
      id: 'acoes', header: 'Ações',
      cell: () => <Button asChild size="sm" variant="ghost"><a href={CLASSICA}>Abrir na tela clássica</a></Button>,
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="cabecalho">
        <PageHeader title="Reservas"
          subtitle={reservas ? <><strong>{adiante}</strong> {adiante === 1 ? 'reserva em aberto' : 'reservas em aberto'}</> : 'reservas do restaurante'}
          subnav={<RestauranteAbas />}
          actions={<Button asChild><a href={CLASSICA}><Plus className="size-4" /> Nova reserva</a></Button>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="reservas" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando reservas…</p>}>
          <Grid data-contract="kpis" min="sm" gap={4}>
            <KpiCard label="Hoje e adiante" value={adiante} description="reservado + aguardando" tone="info" />
            <KpiCard label="Aguardando confirmação" value={aguardando} description="cliente ainda não confirmou" tone={aguardando ? 'warning' : 'success'} />
            <KpiCard label="Concluídas" value={concluidas} description="atendimento feito" tone="success" />
          </Grid>

          {todas.length > 0 ? (
            <Stack gap={2} data-contract="tabela">
              <Inline wrap gap={2}>
                <span className="font-medium">Todas as reservas</span>
                <span className="text-sm text-muted-foreground">{visiveis.length} reserva(s)</span>
                <Inline gap={1} className="ml-auto" role="group" aria-label="Filtrar por situação">
                  {FILTROS.map((f) => (
                    <Button key={f.k} size="sm" variant={filtro === f.k ? 'default' : 'outline'} aria-pressed={filtro === f.k}
                      onClick={() => setFiltro(f.k)}>
                      {f.rotulo} <span className="ml-1 tabular-nums opacity-70">{doLocal.filter(f.casa).length}</span>
                    </Button>
                  ))}
                </Inline>
                {opcoesLocal.length > 1 && (
                  <Select value={local || TODOS_LOCAIS} onValueChange={(v) => setLocal(v === TODOS_LOCAIS ? '' : v)}>
                    <SelectTrigger size="sm" className="w-48" aria-label="Filtrar por local"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value={TODOS_LOCAIS}>Todos os locais</SelectItem>
                      {opcoesLocal.map(([id, nome]) => <SelectItem key={id} value={id}>{nome}</SelectItem>)}
                    </SelectContent>
                  </Select>
                )}
              </Inline>
              <DataTable columns={colunas} data={visiveis} caption="Todas as reservas" rowKey={(r) => r.id}
                emptyMessage="Nenhuma reserva nesta situação." />
            </Stack>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma reserva neste mês ou depois"
                description="As reservas criadas na tela clássica aparecem aqui, da mais próxima para a mais distante."
                action={<Button asChild variant="outline"><a href={CLASSICA}><ExternalLink className="size-4" /> Abrir na tela clássica</a></Button>} />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

ReservasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ReservasIndex;
