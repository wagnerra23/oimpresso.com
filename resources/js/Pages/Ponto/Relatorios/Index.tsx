// @docvault
//   tela: /ponto/relatorios
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-012
//   rules: R-PONT-001, R-PONT-005
//   tests: Modules/PontoWr2/Tests/Feature/RelatoriosIndexTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoSubNav from '@/Pages/Ponto/_shared/PontoSubNav';
import { useMemo, useState, type ReactNode } from 'react';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  AlertCircle,
  ClipboardList,
  Clock,
  FileCheck,
  FileSpreadsheet,
  FileText,
  PiggyBank,
  Send,
  type LucideIcon,
} from 'lucide-react';

type IconKey =
  | 'FileText'
  | 'FileCheck'
  | 'FileSpreadsheet'
  | 'ClipboardList'
  | 'Clock'
  | 'PiggyBank'
  | 'AlertCircle'
  | 'Send';

type CorKey = 'blue' | 'emerald' | 'amber' | 'red' | 'violet';

interface Relatorio {
  chave: string;
  titulo: string;
  descricao: string;
  icone: IconKey;
  cor: CorKey;
  disponivel: boolean;
  /** Categoria opcional vinda do backend; default agrupa em "Geral". */
  categoria?: string;
}

interface Colaborador {
  id: number | string;
  nome: string;
}

interface Props {
  relatorios: Relatorio[];
  /** Opcional — quando o backend envia a lista, o filtro de colaborador aparece. */
  colaboradores?: Colaborador[];
}

const iconMap: Record<IconKey, LucideIcon> = {
  FileText,
  FileCheck,
  FileSpreadsheet,
  ClipboardList,
  Clock,
  PiggyBank,
  AlertCircle,
  Send,
};

// Avatar do card por "cor" lógica do backend → tokens do DS (sem cor crua).
// Azul (hue 240) é PROIBIDO no DS roxo: 'blue' cai no accent primary roxo.
const corClasses: Record<CorKey, string> = {
  blue: 'bg-primary/10 text-primary',
  emerald: 'bg-secondary text-secondary-foreground',
  amber: 'bg-muted text-muted-foreground',
  red: 'bg-destructive/10 text-destructive',
  violet: 'bg-accent text-accent-foreground',
};

const SEM_CATEGORIA = 'Geral';

function periodoAtual(): string {
  const agora = new Date();
  const mes = String(agora.getMonth() + 1).padStart(2, '0');
  return `${agora.getFullYear()}-${mes}`;
}

export default function RelatoriosIndex({ relatorios, colaboradores }: Props) {
  const listaColaboradores = colaboradores ?? [];
  const [periodo, setPeriodo] = useState<string>(periodoAtual);
  const [colaborador, setColaborador] = useState<string>('todos');

  // Agrupa cards por categoria preservando a ordem de chegada.
  const grupos = useMemo(() => {
    const mapa = new Map<string, Relatorio[]>();
    for (const r of relatorios) {
      const cat = r.categoria ?? SEM_CATEGORIA;
      const atual = mapa.get(cat) ?? [];
      atual.push(r);
      mapa.set(cat, atual);
    }
    return Array.from(mapa.entries());
  }, [relatorios]);

  // Monta a URL de geração (rota ponto.relatorios.gerar = /relatorios/{chave})
  // com período/colaborador como query params.
  const hrefGerar = (chave: string): string => {
    const params = new URLSearchParams();
    if (periodo) params.set('periodo', periodo);
    if (colaborador && colaborador !== 'todos') params.set('colaborador', colaborador);
    const qs = params.toString();
    return `/ponto/relatorios/${chave}${qs ? `?${qs}` : ''}`;
  };

  return (
    <>
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>Relatórios <span className="text-stone-400 font-normal">· Geração de documentos</span></h1>
            <p>Defina <strong>período</strong> e <strong>colaborador</strong>, depois clique em <strong>Gerar</strong>.</p>
          </div>
          <div className="os-page-h-r">
            <PontoSubNav active="relatorios" hidePrimary />
          </div>
        </header>

        {/* Filtros aplicados a todos os relatórios antes de gerar */}
        <Card>
          <CardContent className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div className="space-y-1.5">
              <Label htmlFor="periodo">Período</Label>
              <Input
                id="periodo"
                type="month"
                value={periodo}
                onChange={(e) => setPeriodo(e.target.value)}
              />
            </div>

            {listaColaboradores.length > 0 && (
              <div className="space-y-1.5">
                <Label htmlFor="colaborador">Colaborador</Label>
                <Select value={colaborador} onValueChange={setColaborador}>
                  <SelectTrigger id="colaborador">
                    <SelectValue placeholder="Todos os colaboradores" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="todos">Todos os colaboradores</SelectItem>
                    {listaColaboradores.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.nome}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            )}
          </CardContent>
        </Card>

        {grupos.map(([categoria, itens]) => (
          <section key={categoria} className="space-y-3">
            <div className="flex items-center gap-2">
              <h2 className="text-sm font-semibold text-foreground">{categoria}</h2>
              <Badge variant="secondary">{itens.length}</Badge>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
              {itens.map((r) => {
                const Icon = iconMap[r.icone];
                return (
                  <Card key={r.chave} className="flex flex-col">
                    <CardHeader>
                      <div className="flex items-start justify-between gap-2">
                        <div className={`flex size-10 items-center justify-center rounded-lg ${corClasses[r.cor]}`}>
                          <Icon className="size-5" />
                        </div>
                        {r.disponivel ? (
                          <Badge variant="default">Disponível</Badge>
                        ) : (
                          <Badge variant="secondary">Em breve</Badge>
                        )}
                      </div>
                      <CardTitle className="mt-3 text-base">{r.titulo}</CardTitle>
                      <CardDescription>{r.descricao}</CardDescription>
                    </CardHeader>

                    <CardContent className="flex-1" />

                    <CardFooter>
                      <Button
                        size="sm"
                        variant={r.disponivel ? 'default' : 'outline'}
                        disabled={!r.disponivel}
                        asChild={r.disponivel}
                        className="w-full"
                      >
                        {r.disponivel ? (
                          <a href={hrefGerar(r.chave)}>Gerar</a>
                        ) : (
                          <span>Em desenvolvimento</span>
                        )}
                      </Button>
                    </CardFooter>
                  </Card>
                );
              })}
            </div>
          </section>
        ))}
      </div>
    </>
  );
}

RelatoriosIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Relatórios · Ponto WR2" breadcrumbItems={[{ label: 'Ponto WR2' }, { label: 'Relatórios' }]}>
    {page}
  </AppShellV2>
);
