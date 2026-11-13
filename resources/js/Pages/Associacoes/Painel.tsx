// Associacoes/Painel — painel da rede (ADR 0451 onda 5 · US-ASSOC-004). Carimbado do PT-04
// Dashboard por criar-tela.mjs (UI-0013): a estrutura vem do arquétipo, o módulo põe o conteúdo.
// Só números e nomes de associação — nenhum dado pessoal de associada (ADR 0451 D5).
import { Deferred } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';

interface Kpis {
  associacoes: number;
  porSituacao: { em_adesao: number; ativa: number; desligada: number };
  cidades: number;
  associadasAtivas: number;
  pendencias: number;
  atrasadas: number;
}

interface LinhaAssociacao { id: number; nome: string; cidade: string; ativas: number }

interface Pendencia {
  associacao_id: number;
  associacao: string;
  tipo: 'sem_estatuto' | 'sem_ata_diretoria' | 'certidao_vencida' | 'diretoria_mudou';
  situacao: 'pendente' | 'no_prazo' | 'atrasada';
  dias: number | null;
}

interface Props {
  alcance: 'rede' | 'associacao';
  prazoAtaDias: number;
  kpis?: Kpis;
  associadasPorAssociacao?: LinhaAssociacao[];
  pendencias?: Pendencia[];
}

const ROTULO_PENDENCIA: Record<Pendencia['tipo'], string> = {
  sem_estatuto: 'Sem estatuto',
  sem_ata_diretoria: 'Sem ata da diretoria',
  certidao_vencida: 'Certidão vencida',
  diretoria_mudou: 'Diretoria mudou, falta a ata nova',
};

function SeloPendencia({ p, prazo }: { p: Pendencia; prazo: number }) {
  if (p.situacao === 'atrasada') {
    return <Badge variant="danger">Atrasada há {(p.dias ?? 0) - prazo} dia(s)</Badge>;
  }
  if (p.situacao === 'no_prazo') {
    return <Badge variant="warning">No prazo · {Math.max(prazo - (p.dias ?? 0), 0)} dia(s) restantes</Badge>;
  }
  return <Badge variant="neutral">Pendente</Badge>;
}

export default function PainelDaRede({ alcance, prazoAtaDias, kpis, associadasPorAssociacao, pendencias }: Props) {
  const subtitulo = alcance === 'rede'
    ? 'Números da rede inteira e pendências de documentação'
    : 'Números da sua associação e pendências de documentação';

  return (
    <AppShellV2>
      {/* `data-contract` = âncora do contrato de tela (governance/design/contracts/). NÃO remova o
          atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
      <div data-contract="cabecalho">
        <PageHeader title="Painel da rede" subtitle={subtitulo} />
      </div>
      <div data-contract="kpis">
        <Deferred data="kpis" fallback={<Skeleton className="h-28 w-full rounded-xl" />}>
          <KpiGrid cols={4}>
            <KpiCard
              label="Associações"
              value={kpis?.associacoes ?? 0}
              icon="building"
              description={`${kpis?.porSituacao.ativa ?? 0} ativas · ${kpis?.porSituacao.em_adesao ?? 0} em adesão · ${kpis?.porSituacao.desligada ?? 0} desligadas`}
            />
            <KpiCard label="Cidades" value={kpis?.cidades ?? 0} icon="map-pin" />
            <KpiCard label="Associadas ativas" value={kpis?.associadasAtivas ?? 0} icon="users" tone="success" />
            <KpiCard
              label="Pendências de documentação"
              value={kpis?.pendencias ?? 0}
              icon="file-warning"
              tone={(kpis?.atrasadas ?? 0) > 0 ? 'danger' : (kpis?.pendencias ?? 0) > 0 ? 'warning' : 'default'}
              description={`${kpis?.atrasadas ?? 0} atrasada(s)`}
            />
          </KpiGrid>
        </Deferred>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <Card>
          <CardHeader><CardTitle>Associadas ativas por associação</CardTitle></CardHeader>
          <CardContent>
            <Deferred data="associadasPorAssociacao" fallback={<Skeleton className="h-40 w-full" />}>
              {(associadasPorAssociacao ?? []).length === 0 ? (
                <p className="text-sm text-muted-foreground">Nenhuma associação ativa no seu alcance.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-muted-foreground">
                      <th className="py-2 font-medium">Associação</th>
                      <th className="py-2 font-medium">Cidade</th>
                      <th className="py-2 text-right font-medium">Ativas</th>
                    </tr>
                  </thead>
                  <tbody>
                    {(associadasPorAssociacao ?? []).map((a) => (
                      <tr key={a.id} className="border-t border-border">
                        <td className="py-2">{a.nome}</td>
                        <td className="py-2 text-muted-foreground">{a.cidade}</td>
                        <td className="py-2 text-right tabular-nums">{a.ativas}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </Deferred>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Pendências de documentação</CardTitle></CardHeader>
          <CardContent>
            <Deferred data="pendencias" fallback={<Skeleton className="h-40 w-full" />}>
              {(pendencias ?? []).length === 0 ? (
                <p className="text-sm text-muted-foreground">Nenhuma pendência de documentação.</p>
              ) : (
                <ul className="divide-y divide-border">
                  {(pendencias ?? []).map((p) => (
                    <li key={`${p.associacao_id}-${p.tipo}`} className="flex items-center justify-between gap-3 py-2 text-sm">
                      <span>
                        <span className="font-medium">{p.associacao}</span>
                        <span className="text-muted-foreground"> · {ROTULO_PENDENCIA[p.tipo]}</span>
                      </span>
                      <SeloPendencia p={p} prazo={prazoAtaDias} />
                    </li>
                  ))}
                </ul>
              )}
            </Deferred>
          </CardContent>
        </Card>
      </div>
    </AppShellV2>
  );
}
