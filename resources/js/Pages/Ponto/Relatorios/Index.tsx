// @docvault
//   tela: /ponto/relatorios
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-012
//   rules: R-PONT-001, R-PONT-005
//   tests: Modules/PontoWr2/Tests/Feature/RelatoriosIndexTest

import AppShell from '@/Layouts/AppShell';
import { useModuleNav } from '@/Hooks/usePageProps';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
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
}

interface Props {
  relatorios: Relatorio[];
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

const corClasses: Record<CorKey, string> = {
  blue: 'bg-blue-100 text-blue-700',
  emerald: 'bg-emerald-100 text-emerald-700',
  amber: 'bg-amber-100 text-amber-700',
  red: 'bg-red-100 text-red-700',
  violet: 'bg-violet-100 text-violet-700',
};

export default function RelatoriosIndex({ relatorios }: Props) {
  const moduleNav = useModuleNav('PontoWr2');

  return (
    <AppShell
      title="Relatórios · Ponto WR2"
      breadcrumb={[{ label: 'Ponto WR2' }, { label: 'Relatórios' }]}
      moduleNav={moduleNav}
    >
      <div className="mx-auto max-w-7xl p-6">
        <header className="mb-6">
          <h1 className="text-2xl font-bold tracking-tight">Relatórios</h1>
          <p className="text-sm text-muted-foreground">
            Geração de relatórios do módulo de ponto. Clique em <strong>Gerar</strong> para emitir.
          </p>
        </header>

        <div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
          {relatorios.map((r) => {
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
                      <a href={`/ponto/relatorios/${r.chave}/gerar`}>Gerar</a>
                    ) : (
                      <span>Em desenvolvimento</span>
                    )}
                  </Button>
                </CardFooter>
              </Card>
            );
          })}
        </div>
      </div>
    </AppShell>
  );
}
