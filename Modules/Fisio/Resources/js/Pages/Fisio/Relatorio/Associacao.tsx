// @memcofre
//   tela: /fisio/relatorio-associacao
//   module: Fisio
//   stories: US-FISIO-062 (UC-FRA-01..05)
//   permissao: fisio.access + fisio.relatorio
//
// Relatório agregado para a associação: quem é atendido, quanto e se melhora, por período.
// Só contagem e média, nunca linha por paciente; grupo com menos de 5 pessoas sai "<5".
// Padrão de Tela PT-04 Dashboard (o protótipo do Fisio não desenha esta tela).
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import { FileDown } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';

type Celula = number | string;
interface Linha { codigo: string; rotulo: string; n: Celula }
interface Evolucao { codigo: string; rotulo: string; melhora: 'menor' | 'maior'; n: Celula; primeira: number | null; ultima: number | null; diferenca: number | null }
interface Relatorio {
  periodo: { de: string; ate: string };
  minimo: number;
  atendidos: Celula;
  suprimido: boolean;
  perfil: Record<string, Linha[]>;
  presenca: { agendados: number; presentes: number; faltas: number; taxa: number | null } | null;
  adesao: { n: Celula; media: number | null } | null;
  evolucao: Evolucao[];
}
interface Props { filtros: { de: string; ate: string }; relatorio?: Relatorio }

const PERFIL: [string, string][] = [
  ['faixa_etaria', 'Faixa etária'], ['sexo', 'Sexo'], ['municipio', 'Município'],
  ['renda_familiar', 'Renda familiar por mês'], ['situacao_trabalho', 'Situação de trabalho'],
];
const num = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR', { maximumFractionDigits: 1 }));

function Tabela({ titulo, linhas }: { titulo: string; linhas: Linha[] }) {
  return (
    <Card>
      <CardContent className="flex flex-col gap-2 p-4">
        <h3 className="text-sm font-semibold">{titulo}</h3>
        <table className="w-full text-sm">
          <tbody>
            {linhas.map((l) => (
              <tr key={l.codigo} className="border-b border-border last:border-0">
                <td className="py-1">{l.rotulo}</td>
                <td className="py-1 text-right tabular-nums">{l.n}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </CardContent>
    </Card>
  );
}

function Conteudo({ r }: { r: Relatorio }) {
  if (r.suprimido) {
    return (
      <div className="flex flex-col gap-4" data-contract="fisio-relatorio-suprimido">
        <KpiGrid cols={4}><KpiCard label="Pessoas atendidas" value={r.atendidos} icon="users" /></KpiGrid>
        <p className="text-sm text-muted-foreground">
          Com menos de {r.minimo} pessoas atendidas no período, o perfil e os resultados não são mostrados para não identificar ninguém.
        </p>
      </div>
    );
  }
  return (
    <div className="flex flex-col gap-6" data-contract="fisio-relatorio">
      <KpiGrid cols={4}>
        <KpiCard label="Pessoas atendidas" value={r.atendidos} icon="users" description="Com atendimento, registro, check-in ou questionário no período" />
        <KpiCard label="Atendimentos" value={r.presenca?.presentes ?? 0} icon="calendar" description="Da agenda, com presença" />
        <KpiCard label="Presença" value={r.presenca?.taxa === null || !r.presenca ? '—' : `${r.presenca.taxa}%`} icon="check"
          description={r.presenca ? `${r.presenca.faltas} falta(s) em ${r.presenca.agendados} agendado(s)` : undefined} />
        <KpiCard label="Adesão em casa" value={r.adesao?.media == null ? '—' : `${r.adesao.media}%`} icon="activity"
          description={`Média de ${r.adesao?.n ?? 0} pessoa(s) com programa`} />
      </KpiGrid>
      <section className="flex flex-col gap-3">
        <h2 className="text-base font-semibold">Perfil das pessoas atendidas</h2>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {PERFIL.map(([chave, titulo]) => <Tabela key={chave} titulo={titulo} linhas={r.perfil[chave] ?? []} />)}
        </div>
      </section>
      <section className="flex flex-col gap-3">
        <h2 className="text-base font-semibold">Resultados: primeira e última aplicação</h2>
        <Card>
          <CardContent className="overflow-x-auto p-4">
            <table className="w-full text-sm">
              <thead className="text-left text-xs text-muted-foreground">
                <tr><th className="py-1">Questionário ou teste</th><th>Pessoas</th><th>Média na 1ª</th><th>Média na última</th><th>Diferença</th></tr>
              </thead>
              <tbody>
                {r.evolucao.map((e) => (
                  <tr key={e.codigo} className="border-t border-border">
                    <td className="py-1.5">{e.rotulo}<div className="text-xs text-muted-foreground">melhora = número {e.melhora}</div></td>
                    <td className="tabular-nums">{e.n}</td>
                    <td className="tabular-nums">{num(e.primeira)}</td>
                    <td className="tabular-nums">{num(e.ultima)}</td>
                    <td className="tabular-nums">{e.diferenca === null ? '—' : `${e.diferenca > 0 ? '+' : ''}${num(e.diferenca)}`}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p className="mt-2 text-xs text-muted-foreground">
              Só entra quem respondeu ao menos duas vezes até o fim do período. Média e diferença só aparecem com {r.minimo} pessoas ou mais.
            </p>
          </CardContent>
        </Card>
      </section>
    </div>
  );
}

export default function RelatorioAssociacao({ filtros, relatorio }: Props) {
  const [de, setDe] = useState(filtros.de);
  const [ate, setAte] = useState(filtros.ate);
  const aplicar = (e: FormEvent) => {
    e.preventDefault();
    router.get('/fisio/relatorio-associacao', { de, ate }, { preserveState: true });
  };
  const pdf = `/fisio/relatorio-associacao.pdf?${new URLSearchParams({ de: filtros.de, ate: filtros.ate }).toString()}`;

  return (
    <div data-screen-label="Fisio · Relatório da associação">
      <PageHeader
        title="Relatório da associação"
        subtitle="Quem é atendido, quanto e se melhora — só números somados, sem nome"
        actions={<Button variant="outline" asChild><a href={pdf}><FileDown className="size-4" aria-hidden="true" /> Baixar PDF</a></Button>}
      />
      <div className="flex flex-col gap-4 p-4">
        <form onSubmit={aplicar} className="flex flex-wrap items-end gap-3" data-contract="fisio-relatorio-periodo">
          <div className="flex flex-col gap-1.5"><Label htmlFor="rel-de">De</Label><Input id="rel-de" type="date" value={de} onChange={(e) => setDe(e.target.value)} /></div>
          <div className="flex flex-col gap-1.5"><Label htmlFor="rel-ate">Até</Label><Input id="rel-ate" type="date" value={ate} onChange={(e) => setAte(e.target.value)} /></div>
          <Button type="submit">Ver período</Button>
        </form>
        <p className="text-xs text-muted-foreground">
          Grupos com menos de 5 pessoas aparecem como "&lt;5" e municípios pequenos entram em "Outros municípios" (LGPD). Nenhuma linha por paciente.
        </p>
        <Deferred data="relatorio" fallback={<Skeleton className="h-64 w-full" />}>
          {relatorio ? <Conteudo r={relatorio} /> : <span />}
        </Deferred>
      </div>
    </div>
  );
}

RelatorioAssociacao.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
