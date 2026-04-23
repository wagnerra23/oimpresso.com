// @docvault
//   tela: /ponto/intercorrencias
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-001
//   rules: R-PONT-001, R-PONT-004
//   adrs: 0004
//   tests: Modules/PontoWr2/Tests/Feature/IntercorrenciasIndexTest

import AppShell from '@/Layouts/AppShell';
import { useModuleNav } from '@/Hooks/usePageProps';
import { Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowRight, Inbox, Plus } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

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

const estadoVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  RASCUNHO:  'outline',
  PENDENTE:  'default',
  APROVADA:  'default',
  REJEITADA: 'destructive',
  APLICADA:  'secondary',
  CANCELADA: 'outline',
};

export default function IntercorrenciasIndex({ intercorrencias, filtros }: Props) {
  const moduleNav = useModuleNav('Ponto');

  const filter = (key: string, value: string) => {
    router.get('/ponto/intercorrencias',
      { ...filtros, [key]: value === 'ALL' ? undefined : value },
      { preserveState: true, preserveScroll: true });
  };

  return (
    <AppShell
      title="Intercorrências"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Intercorrências' }]}
      moduleNav={moduleNav}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <AlertTriangle size={22} /> Intercorrências
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Ausências, atestados, esquecimentos de marcação e outras ocorrências
              que afetam a apuração de ponto.
            </p>
          </div>
          <Button asChild>
            <Link href="/ponto/intercorrencias/create">
              <Plus size={14} className="mr-1.5" /> Nova
            </Link>
          </Button>
        </header>

        <Card>
          <CardContent className="pt-4">
            <div className="flex gap-2 flex-wrap">
              <Select value={filtros.estado ?? 'ALL'} onValueChange={(v) => filter('estado', v)}>
                <SelectTrigger className="w-40">
                  <SelectValue placeholder="Estado" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="ALL">Todos os estados</SelectItem>
                  <SelectItem value="RASCUNHO">Rascunho</SelectItem>
                  <SelectItem value="PENDENTE">Pendente</SelectItem>
                  <SelectItem value="APROVADA">Aprovada</SelectItem>
                  <SelectItem value="REJEITADA">Rejeitada</SelectItem>
                  <SelectItem value="APLICADA">Aplicada</SelectItem>
                  <SelectItem value="CANCELADA">Cancelada</SelectItem>
                </SelectContent>
              </Select>
              <Select value={filtros.tipo ?? 'ALL'} onValueChange={(v) => filter('tipo', v)}>
                <SelectTrigger className="w-52">
                  <SelectValue placeholder="Tipo" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="ALL">Todos os tipos</SelectItem>
                  <SelectItem value="CONSULTA_MEDICA">Consulta médica</SelectItem>
                  <SelectItem value="ATESTADO_MEDICO">Atestado médico</SelectItem>
                  <SelectItem value="REUNIAO_EXTERNA">Reunião externa</SelectItem>
                  <SelectItem value="VISITA_CLIENTE">Visita a cliente</SelectItem>
                  <SelectItem value="HORA_EXTRA_AUTORIZADA">Hora extra autorizada</SelectItem>
                  <SelectItem value="ESQUECIMENTO_MARCACAO">Esquecimento de marcação</SelectItem>
                  <SelectItem value="PROBLEMA_EQUIPAMENTO">Problema no equipamento</SelectItem>
                  <SelectItem value="OUTRO">Outro</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-0">
            {intercorrencias.data.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground">
                <Inbox size={32} className="mx-auto mb-2 opacity-50" />
                <p className="text-sm">Nenhuma intercorrência com esses filtros.</p>
              </div>
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
                        <td className="p-3 text-xs">{i.tipo.replace(/_/g, ' ')}</td>
                        <td className="p-3 text-xs">{i.data ?? '—'}</td>
                        <td className="p-3">
                          <Badge variant={estadoVariant[i.estado] ?? 'outline'} className="text-[10px]">
                            {i.estado}
                          </Badge>
                          {i.prioridade === 'URGENTE' && (
                            <Badge variant="destructive" className="ml-1 text-[10px]">URG</Badge>
                          )}
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
                    <Button key={i} variant={link.active ? 'default' : 'outline'} size="sm"
                            className="h-7 min-w-8 px-2 text-xs" disabled={!link.url}
                            onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true })}>
                      <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Button>
                  ))}
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}
