import AppShell from '@/Layouts/AppShell';
import { Link, router } from '@inertiajs/react';
import { CalendarDays, Plus } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { formatMinutes } from '@/Lib/utils';

interface Escala {
  id: number;
  nome: string;
  codigo: string | null;
  tipo: string;
  carga_diaria_minutos: number;
  carga_semanal_minutos: number;
  permite_banco_horas: boolean;
  turnos_count: number;
}

interface Paginated {
  data: Escala[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props { escalas: Paginated; }

export default function EscalasIndex({ escalas }: Props) {
  return (
    <AppShell
      title="Escalas"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Escalas' }]}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <CalendarDays size={22} /> Escalas
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Padrões de jornada (fixa, flexível, 12x36, etc.). Cada escala tem turnos por dia da semana.
            </p>
          </div>
          <Button asChild>
            <Link href="/ponto/escalas/create"><Plus size={14} className="mr-1.5" /> Nova escala</Link>
          </Button>
        </header>

        <Card>
          <CardContent className="p-0">
            {escalas.data.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">
                Nenhuma escala cadastrada.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Nome</th>
                      <th className="text-left p-3 font-medium">Código</th>
                      <th className="text-left p-3 font-medium">Tipo</th>
                      <th className="text-right p-3 font-medium">Carga/dia</th>
                      <th className="text-right p-3 font-medium">Carga/semana</th>
                      <th className="text-center p-3 font-medium">BH</th>
                      <th className="text-center p-3 font-medium">Turnos</th>
                      <th className="text-right p-3 font-medium"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {escalas.data.map((e) => (
                      <tr key={e.id} className="hover:bg-accent/30">
                        <td className="p-3 font-medium">{e.nome}</td>
                        <td className="p-3 font-mono text-xs">{e.codigo ?? '—'}</td>
                        <td className="p-3 text-xs">
                          <Badge variant="outline" className="text-[10px]">{e.tipo.replace('_', ' ')}</Badge>
                        </td>
                        <td className="p-3 text-right font-mono text-xs">{formatMinutes(e.carga_diaria_minutos)}</td>
                        <td className="p-3 text-right font-mono text-xs">{formatMinutes(e.carga_semanal_minutos)}</td>
                        <td className="p-3 text-center">
                          {e.permite_banco_horas ? <Badge variant="default" className="text-[10px]">Sim</Badge> : <span className="text-xs text-muted-foreground">Não</span>}
                        </td>
                        <td className="p-3 text-center text-xs">{e.turnos_count}</td>
                        <td className="p-3 text-right">
                          <Button size="sm" variant="outline" asChild>
                            <Link href={`/ponto/escalas/${e.id}/edit`} className="text-xs">Editar</Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            {escalas.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">Página {escalas.current_page}/{escalas.last_page} · {escalas.total}</span>
                <div className="flex gap-1">
                  {escalas.links.map((link, i) => (
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
