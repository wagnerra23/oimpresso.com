// @docvault
//   tela: /ponto/colaboradores
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-004
//   rules: R-PONT-001, R-PONT-003
//   adrs: arq/0002
//   tests: Modules/PontoWr2/Tests/Feature/ColaboradoresIndexTest

import AppShell from '@/Layouts/AppShell';
import { useModuleNav } from '@/Hooks/usePageProps';
import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Search, Users } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';

interface C {
  id: number;
  matricula: string | null;
  cpf: string | null;
  pis: string | null;
  nome: string;
  email: string | null;
  escala: string | null;
  controla_ponto: boolean;
  usa_banco_horas: boolean;
  admissao: string | null;
  desligamento: string | null;
}

interface Props {
  colaboradores: {
    data: C[]; total: number; current_page: number; last_page: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
  };
  search: string | null;
}

export default function ColaboradoresIndex({ colaboradores, search }: Props) {
  const moduleNav = useModuleNav('PontoWr2');
  const [q, setQ] = useState(search ?? '');

  useEffect(() => {
    const h = setTimeout(() => {
      if (q !== (search ?? '')) {
        router.get('/ponto/colaboradores', q ? { q } : {}, {
          preserveState: true, preserveScroll: true, replace: true,
        });
      }
    }, 350);
    return () => clearTimeout(h);
  }, [q, search]);

  return (
    <AppShell
      title="Colaboradores"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Colaboradores' }]}
      moduleNav={moduleNav}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header>
          <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
            <Users size={22} /> Colaboradores
          </h1>
          <p className="text-sm text-muted-foreground mt-1">
            Configuração de ponto por colaborador. Nome/email vêm do HRM (UltimatePOS core).
          </p>
        </header>

        <Card>
          <CardContent className="pt-4">
            <div className="flex items-center gap-2 rounded-md border border-input bg-background px-3 py-1.5">
              <Search size={14} className="text-muted-foreground" />
              <Input value={q} onChange={(e) => setQ(e.target.value)}
                     placeholder="Buscar por matrícula, nome ou CPF…"
                     className="h-7 border-0 bg-transparent p-0 text-sm shadow-none focus-visible:ring-0" />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardContent className="p-0">
            {colaboradores.data.length === 0 ? (
              <div className="p-12 text-center text-sm text-muted-foreground">Nenhum colaborador.</div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Matrícula</th>
                      <th className="text-left p-3 font-medium">Nome</th>
                      <th className="text-left p-3 font-medium">CPF</th>
                      <th className="text-left p-3 font-medium">Escala</th>
                      <th className="text-center p-3 font-medium">Ponto</th>
                      <th className="text-center p-3 font-medium">BH</th>
                      <th className="text-right p-3 font-medium"></th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {colaboradores.data.map((c) => (
                      <tr key={c.id} className="hover:bg-accent/30">
                        <td className="p-3 font-mono text-xs">{c.matricula ?? '—'}</td>
                        <td className="p-3">
                          <div className="font-medium">{c.nome}</div>
                          {c.email && <div className="text-[10px] text-muted-foreground">{c.email}</div>}
                        </td>
                        <td className="p-3 text-xs font-mono">{c.cpf ?? '—'}</td>
                        <td className="p-3 text-xs">{c.escala ?? '—'}</td>
                        <td className="p-3 text-center">
                          {c.controla_ponto ? <Badge className="text-[10px]">Sim</Badge> : <span className="text-xs text-muted-foreground">Não</span>}
                        </td>
                        <td className="p-3 text-center">
                          {c.usa_banco_horas ? <Badge variant="secondary" className="text-[10px]">Sim</Badge> : <span className="text-xs text-muted-foreground">Não</span>}
                        </td>
                        <td className="p-3 text-right">
                          <Button size="sm" variant="outline" asChild>
                            <Link href={`/ponto/colaboradores/${c.id}/edit`} className="text-xs">Config</Link>
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
                <span className="text-muted-foreground">Página {colaboradores.current_page}/{colaboradores.last_page} · {colaboradores.total}</span>
                <div className="flex gap-1">
                  {colaboradores.links.map((link, i) => (
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
