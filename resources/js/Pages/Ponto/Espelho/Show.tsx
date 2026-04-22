import AppShell from '@/Layouts/AppShell';
import { Link, router } from '@inertiajs/react';
import {
  AlertTriangle,
  ArrowLeft,
  ArrowRight,
  ChevronLeft,
  ChevronRight,
  ClipboardList,
  Printer,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { cn, formatMinutes } from '@/Lib/utils';

interface Colaborador {
  id: number;
  matricula: string | null;
  cpf: string | null;
  nome: string;
  email: string | null;
  admissao: string | null;
  escala: string | null;
}

interface Totais {
  trabalhado: number;
  atraso: number;
  falta: number;
  he_diurna: number;
  he_noturna: number;
  adicional_not: number;
  bh_credito: number;
  bh_debito: number;
  divergencias: number;
}

interface Marcacao {
  hora: string;
  tipo: string;
  origem: string;
}

interface Linha {
  data: string;
  dow: string;
  dia: number;
  is_weekend: boolean;
  trabalhado: number;
  atraso: number;
  falta: number;
  he: number;
  divergencia: boolean;
  marcacoes: Marcacao[];
}

interface Props {
  colaborador: Colaborador;
  mes: string;
  totais: Totais;
  linhas: Linha[];
}

const tipoBadgeVariant: Record<string, 'default' | 'secondary' | 'outline' | 'destructive'> = {
  ENTRADA:       'default',
  ALMOCO_INICIO: 'secondary',
  ALMOCO_FIM:    'secondary',
  SAIDA:         'outline',
};

export default function EspelhoShow({ colaborador, mes, totais, linhas }: Props) {
  const onMesChange = (novo: string) => {
    router.get(`/ponto/espelho/${colaborador.id}`, { mes: novo }, { preserveState: true });
  };

  const navegarMes = (delta: number) => {
    const [y, m] = mes.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);
    const novo = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    onMesChange(novo);
  };

  return (
    <AppShell
      title={`Espelho · ${colaborador.nome} · ${mes}`}
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Espelho', href: '/ponto/espelho' },
        { label: colaborador.nome },
      ]}
    >
      <div className="mx-auto max-w-7xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <ClipboardList size={22} /> Espelho de Ponto
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              <strong>{colaborador.nome}</strong>
              {colaborador.matricula && <span className="ml-2">mat. {colaborador.matricula}</span>}
              {colaborador.escala && <span className="ml-2">· Escala: {colaborador.escala}</span>}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" asChild>
              <Link href="/ponto/espelho">
                <ArrowLeft size={14} className="mr-1.5" /> Voltar
              </Link>
            </Button>
            <Button size="sm" asChild>
              <a href={`/ponto/espelho/${colaborador.id}/imprimir?mes=${mes}`} target="_blank" rel="noreferrer">
                <Printer size={14} className="mr-1.5" /> Imprimir PDF
              </a>
            </Button>
          </div>
        </header>

        {/* Navegação de mês */}
        <Card>
          <CardContent className="pt-4 flex items-center justify-between gap-3">
            <Button variant="outline" size="sm" onClick={() => navegarMes(-1)}>
              <ChevronLeft size={14} className="mr-1" /> Mês anterior
            </Button>
            <Input
              type="month"
              value={mes}
              onChange={(e) => onMesChange(e.target.value)}
              className="w-40 text-center"
            />
            <Button variant="outline" size="sm" onClick={() => navegarMes(1)}>
              Próximo mês <ChevronRight size={14} className="ml-1" />
            </Button>
          </CardContent>
        </Card>

        {/* Totalizadores */}
        <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
          <Totalizador label="Trabalhado" value={formatMinutes(totais.trabalhado)} tone="blue" />
          <Totalizador label="Atraso" value={formatMinutes(totais.atraso)} tone={totais.atraso > 0 ? 'amber' : 'muted'} />
          <Totalizador label="Falta" value={formatMinutes(totais.falta)} tone={totais.falta > 0 ? 'red' : 'muted'} />
          <Totalizador label="HE diurna" value={formatMinutes(totais.he_diurna)} tone="violet" />
          <Totalizador label="HE noturna" value={formatMinutes(totais.he_noturna)} tone="violet" />
          <Totalizador label="BH +/-" value={`${formatMinutes(totais.bh_credito)} / ${formatMinutes(totais.bh_debito)}`} tone="emerald" small />
        </div>

        {/* Alerta divergências */}
        {totais.divergencias > 0 && (
          <div className="flex items-center gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3 text-sm">
            <AlertTriangle size={16} className="text-amber-600" />
            <span>
              <strong>{totais.divergencias}</strong> dia(s) com divergência detectada na apuração.
              Dias destacados em âmbar na tabela abaixo.
            </span>
          </div>
        )}

        {/* Tabela dia a dia */}
        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Dia a dia</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <table className="w-full text-xs">
                <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                  <tr>
                    <th className="text-left p-2 font-medium">Dia</th>
                    <th className="text-left p-2 font-medium">Marcações</th>
                    <th className="text-right p-2 font-medium">Trabalhado</th>
                    <th className="text-right p-2 font-medium">Atraso</th>
                    <th className="text-right p-2 font-medium">Falta</th>
                    <th className="text-right p-2 font-medium">HE</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {linhas.map((l) => (
                    <tr
                      key={l.data}
                      className={cn(
                        'hover:bg-accent/30 transition-colors',
                        l.divergencia && 'bg-amber-500/5',
                        l.is_weekend && 'text-muted-foreground',
                      )}
                    >
                      <td className="p-2 whitespace-nowrap">
                        <span className="font-semibold">{String(l.dia).padStart(2, '0')}</span>
                        <span className="ml-2 text-[10px] uppercase">{l.dow}</span>
                      </td>
                      <td className="p-2">
                        {l.marcacoes.length === 0 ? (
                          <span className="text-muted-foreground italic text-[10px]">—</span>
                        ) : (
                          <div className="flex flex-wrap gap-1">
                            {l.marcacoes.map((m, i) => (
                              <Badge
                                key={i}
                                variant={tipoBadgeVariant[m.tipo] ?? 'outline'}
                                className="text-[10px] px-1.5 py-0"
                                title={`${m.tipo} · ${m.origem}`}
                              >
                                {m.hora}
                              </Badge>
                            ))}
                          </div>
                        )}
                      </td>
                      <td className="p-2 text-right font-mono">{formatMinutes(l.trabalhado)}</td>
                      <td className={cn('p-2 text-right font-mono', l.atraso > 0 && 'text-amber-600')}>
                        {formatMinutes(l.atraso)}
                      </td>
                      <td className={cn('p-2 text-right font-mono', l.falta > 0 && 'text-red-600')}>
                        {formatMinutes(l.falta)}
                      </td>
                      <td className={cn('p-2 text-right font-mono', l.he > 0 && 'text-violet-600')}>
                        {formatMinutes(l.he)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppShell>
  );
}

function Totalizador({
  label,
  value,
  tone,
  small,
}: {
  label: string;
  value: string;
  tone: 'blue' | 'emerald' | 'amber' | 'red' | 'violet' | 'muted';
  small?: boolean;
}) {
  const toneClass: Record<typeof tone, string> = {
    blue:    'text-blue-700 dark:text-blue-400',
    emerald: 'text-emerald-700 dark:text-emerald-400',
    amber:   'text-amber-700 dark:text-amber-400',
    red:     'text-red-700 dark:text-red-400',
    violet:  'text-violet-700 dark:text-violet-400',
    muted:   'text-muted-foreground',
  };
  return (
    <Card>
      <CardContent className="pt-4 pb-4">
        <p className="text-[10px] uppercase tracking-wide text-muted-foreground">{label}</p>
        <p className={cn('font-bold font-mono mt-0.5', small ? 'text-sm' : 'text-lg', toneClass[tone])}>
          {value}
        </p>
      </CardContent>
    </Card>
  );
}
