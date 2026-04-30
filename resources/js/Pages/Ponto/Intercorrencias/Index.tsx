// @docvault
//   tela: /ponto/intercorrencias
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-001
//   rules: R-PONT-001, R-PONT-004
//   adrs: 0004
//   tests: Modules/PontoWr2/Tests/Feature/IntercorrenciasIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link, router } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { ArrowRight, Plus } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

import PageHeader from '@/Components/shared/PageHeader';
import PageFilters from '@/Components/shared/PageFilters';
import StatusBadge from '@/Components/shared/StatusBadge';
import EmptyState from '@/Components/shared/EmptyState';

interface Row {
  id: number | string;
  codigo: string;
  tipo: string;
  estado: string;
  prioridade: string;
  data: string | null;
  justificativa: string;
  created_at_human: string | null;
  colaborador: { nome: string; matricula: string | null };
}

interface Paginated {
  data: Row[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  intercorrencias: Paginated;
  filtros: { estado: string | null; tipo: string | null };
}

const estadoLabelMap: Record<string, string> = {
  RASCUNHO: 'Rascunho', PENDENTE: 'Pendente', APROVADA: 'Aprovada',
  REJEITADA: 'Rejeitada', APLICADA: 'Aplicada', CANCELADA: 'Cancelada',
};

const tipoOptions = [
  { value: 'CONSULTA_MEDICA', label: 'Consulta médica' },
  { value: 'ATESTADO_MEDICO', label: 'Atestado médico' },
  { value: 'REUNIAO_EXTERNA', label: 'Reunião externa' },
  { value: 'VISITA_CLIENTE', label: 'Visita a cliente' },
  { value: 'HORA_EXTRA_AUTORIZADA', label: 'Hora extra autorizada' },
  { value: 'ESQUECIMENTO_MARCACAO', label: 'Esquecimento de marcação' },
  { value: 'PROBLEMA_EQUIPAMENTO', label: 'Problema no equipamento' },
  { value: 'OUTRO', label: 'Outro' },
];

export default function IntercorrenciasIndex({ intercorrencias, filtros }: Props) {
  const filter = (key: string, value: string) => {
    router.get(
      '/ponto/intercorrencias',
      { ...filtros, [key]: value === 'ALL' ? undefined : value },
      { preserveState: true, preserveScroll: true },
    );
  };

  const resetFilters = () => router.get('/ponto/intercorrencias', {}, { preserveScroll: true });

  const tipoLabel = (v: string) => tipoOptions.find((o) => o.value === v)?.label ?? v.replace(/_/g, ' ');

  const activeChips = [
    ...(filtros.estado
      ? [{ label: `Estado: ${estadoLabelMap[filtros.estado] ?? filtros.estado}`, onRemove: () => filter('estado', 'ALL') }]
      : []),
    ...(filtros.tipo
      ? [{ label: `Tipo: ${tipoLabel(filtros.tipo)}`, onRemove: () => filter('tipo', 'ALL') }]
      : []),
  ];

  const hasFilters = activeChips.length > 0;

  return (
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <PageHeader
          icon="alert-triangle"
          title="Intercorrências"
          description="Ausências, atestados, esquecimentos de marcação e outras ocorrências que afetam a apuração de ponto."
          action={
            <Button asChild>
              <Link href="/ponto/intercorrencias/create">
                <Plus size={14} className="mr-1.5" /> Nova
              </Link>
            </Button>
          }
        />

        <PageFilters activeChips={activeChips} onReset={hasFilters ? resetFilters : undefined} cols={2}>
          <div>
            <label className="text-xs font-medium text-muted-foreground mb-1 block">Estado</label>
            <Select value={filtros.estado ?? 'ALL'} onValueChange={(v) => filter('estado', v)}>
              <SelectTrigger><SelectValue placeholder="Estado" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="ALL">Todos os estados</SelectItem>
                {Object.entries(estadoLabelMap).map(([v, l]) => (
                  <SelectItem key={v} value={v}>{l}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div>
            <label className="text-xs font-medium text-muted-foreground mb-1 block">Tipo</label>
            <Select value={filtros.tipo ?? 'ALL'} onValueChange={(v) => filter('tipo', v)}>
              <SelectTrigger><SelectValue placeholder="Tipo" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="ALL">Todos os tipos</SelectItem>
                {tipoOptions.map((o) => (
                  <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
        </PageFilters>

        <Card>
          <CardContent className="p-0">
            {intercorrencias.data.length === 0 ? (
              <EmptyState
                icon={hasFilters ? 'search-x' : 'inbox'}
                title={hasFilters ? 'Nenhum resultado' : 'Sem intercorrências'}
                description={
                  hasFilters
                    ? 'Nenhuma intercorrência com esses filtros. Tente limpar a busca.'
                    : 'Nenhuma intercorrência registrada. Colaboradores podem submeter pelo app mobile ou você pode criar manualmente.'
                }
                variant={hasFilters ? 'search' : 'default'}
                action={
                  hasFilters ? (
                    <Button variant="outline" size="sm" onClick={resetFilters}>Limpar filtros</Button>
                  ) : (
                    <Button asChild size="sm">
                      <Link href="/ponto/intercorrencias/create">
                        <Plus size={14} className="mr-1.5" /> Criar primeira
                      </Link>
                    </Button>
                  )
                }
              />
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Código</th>
                      <th className="text-left p-3 font-medium">Colaborador</th>
                      <th className="text-left p-3 font-medium">Tipo</th>
                      <th className="text-left p-3 font-medium">Data</th>
                      <th className="text-left p-3 font-medium">Estado</th>
                      <th className="text-left p-3 font-medium">Criada</th>
                      <th className="text-right p-3 font-medium"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {intercorrencias.data.map((i) => (
                      <tr key={i.id} className="hover:bg-accent/30">
                        <td className="p-3 font-mono text-xs">{i.codigo}</td>
                        <td className="p-3">
                          <div className="font-medium">{i.colaborador.nome}</div>
                          {i.colaborador.matricula && (
                            <div className="text-[10px] text-muted-foreground">{i.colaborador.matricula}</div>
                          )}
                        </td>
                        <td className="p-3 text-xs">{tipoLabel(i.tipo)}</td>
                        <td className="p-3 text-xs">{i.data ?? '—'}</td>
                        <td className="p-3">
                          <div className="flex items-center gap-1">
                            <StatusBadge kind="intercorrencia" value={i.estado} />
                            {i.prioridade === 'URGENTE' && (
                              <StatusBadge kind="prioridade" value="urgente" />
                            )}
                          </div>
                        </td>
                        <td className="p-3 text-xs text-muted-foreground">{i.created_at_human ?? '—'}</td>
                        <td className="p-3 text-right">
                          <Button size="sm" variant="outline" asChild>
                            <Link href={`/ponto/intercorrencias/${i.id}`} className="text-xs gap-1">
                              Ver <ArrowRight size={12} />
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {intercorrencias.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {intercorrencias.current_page} de {intercorrencias.last_page} · {intercorrencias.total} item(s)
                </span>
                <div className="flex gap-1">
                  {intercorrencias.links.map((link, i) => (
                    <Button
                      key={i}
                      variant={link.active ? 'default' : 'outline'}
                      size="sm"
                      className="h-7 min-w-8 px-2 text-xs"
                      disabled={!link.url}
                      onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}
                    >
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

IntercorrenciasIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Intercorrências" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Intercorrências' }]}>
    {page}
  </AppShellV2>
);
