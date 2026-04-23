// @docvault
//   tela: /ponto/espelho
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-007
//   rules: R-PONT-001, R-PONT-005
//   adrs: ui/0001
//   tests: Modules/PontoWr2/Tests/Feature/EspelhoIndexTest

import AppShell from '@/Layouts/AppShell';
import { useModuleNav } from '@/Hooks/usePageProps';
import { Link, router } from '@inertiajs/react';
import { ClipboardList, Search, ArrowRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

interface Colaborador {
  id: number;
  matricula: string | null;
  cpf: string | null;
  nome: string;
  email: string | null;
}

interface Paginated {
  data: Colaborador[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  colaboradores: Paginated;
  mes: string;
}

export default function EspelhoIndex({ colaboradores, mes }: Props) {
  const moduleNav = useModuleNav('Ponto');

  const onMesChange = (novoMes: string) => {
    router.get('/ponto/espelho', { mes: novoMes }, { preserveState: true, preserveScroll: true });
  };

  return (
    <AppShell
      title="Espelho de Ponto"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Espelho' }]}
      moduleNav={moduleNav}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <ClipboardList size={22} /> Espelho de Ponto
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Selecione um colaborador para ver o espelho mensal detalhado.
          </p>
        </header>

        <Card>
          <CardContent className="pt-4">
            <div className="flex flex-col md:flex-row gap-3">
              <div className="flex-1 flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5">
                <Search size={14} className="text-muted-foreground" />
                <Input
                  placeholder="Buscar por matrícula, nome ou CPF (em breve)"
                  disabled
                  className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0"
                />
              </div>
              <div className="flex items-center gap-2">
                <label htmlFor="mes" className="text-xs text-muted-foreground whitespace-nowrap">
                  Mês de referência:
                </label>
                <Input
                  id="mes"
                  type="month"
                  value={mes}
                  onChange={(e) => onMesChange(e.target.value)}
                  className="w-40"
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-0">
            {colaboradores.data.length === 0 ? (
              <div className="p-12 text-center text-muted-foreground text-sm">
                Nenhum colaborador com controle de ponto ativo.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Matrícula</th>
                      <th className="text-left p-3 font-medium">Colaborador</th>
                      <th className="text-left p-3 font-medium">CPF</th>
                      <th className="text-left p-3 font-medium">E-mail</th>
                      <th className="text-right p-3 font-medium">Espelho</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {colaboradores.data.map((c) => (
                      <tr key={c.id} className="hover:bg-accent/30 transition-colors">
                        <td className="p-3 font-mono text-xs">{c.matricula ?? '—'}</td>
                        <td className="p-3 font-medium">{c.nome}</td>
                        <td className="p-3 text-xs font-mono">{c.cpf ?? '—'}</td>
                        <td className="p-3 text-xs text-muted-foreground">{c.email ?? '—'}</td>
                        <td className="p-3 text-right">
                          <Button size="sm" asChild>
                            <Link href={`/ponto/espelho/${c.id}?mes=${mes}`} className="text-xs gap-1">
                              Ver {mes} <ArrowRight size={12} />
                            </Link>
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {colaboradores.last_page > 1 && (
              <div className="flex items-center justify-between border-t border-border p-3 text-xs">
                <span className="text-muted-foreground">
                  Página {colaboradores.current_page} de {colaboradores.last_page} · {colaboradores.total} colaborador(es)
                </span>
                <div className="flex gap-1">
                  {colaboradores.links.map((link, i) => (
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
    </AppShell>
  );
}
