// @memcofre
//   tela: /fisio
//   module: Fisio
//   stories: US-FISIO-010 (UC-FPN-02..06)
//   permissao: fisio.access
//
// Painel do profissional (PT-04 Dashboard). Mostra adesão, dor e quem precisa de atenção, a partir
// do que o paciente registrou. Só sinaliza: não sugere conduta, não muda programa e não fala com o
// paciente (ADR 0437 §5). Os números vêm do servidor (calculadores puros); nada é calculado aqui.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import PageHeader from '@/Components/shared/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Skeleton } from '@/Components/ui/skeleton';

type Motivo = 'dor_subiu' | 'dor_alta' | 'sem_registro' | 'sinal_de_alerta';

interface Kpis {
  adesao_media_7d: number | null;
  pacientes_ativos: number;
  dor_media: number | null;
  checkins_7d: number;
}
interface ItemAtencao {
  patient_id: number;
  nome: string | null;
  motivos: Motivo[];
  clinico: Motivo[];
  abandono: Motivo[];
  ultima_dor: number | null;
  ultimo_registro: string | null;
}
interface LinhaPaciente {
  id: number;
  nome: string | null;
  sessions_per_week: number;
  programa_ativo: boolean;
  adesao_7d: number | null;
  ultima_dor: number | null;
  ultimo_registro: string | null;
  nivel: number;
  sequencia: number;
  em_pausa: boolean;
}
interface Regras {
  subida_minima_dor: number;
  limiar_dor_padrao: number;
  dias_sem_registro: number;
}
interface Props {
  regras: Regras;
  kpis?: Kpis;
  atencao?: ItemAtencao[];
  pacientes?: LinhaPaciente[];
}

const dataCurta = (d: string | null) =>
  d ? new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' }) : '—';

function rotuloMotivo(m: Motivo, r: Regras): string {
  switch (m) {
    case 'dor_subiu': return `Dor subiu ${r.subida_minima_dor}+ pontos`;
    case 'dor_alta': return 'Dor alta no último registro';
    case 'sem_registro': return `${r.dias_sem_registro}+ dias sem registro`;
    case 'sinal_de_alerta': return 'Sinal de alerta marcado';
  }
}

function Kpis({ kpis }: { kpis: Kpis }) {
  return (
    <KpiGrid cols={4}>
      <KpiCard
        label="Adesão média 7 dias"
        value={kpis.adesao_media_7d === null ? '—' : `${kpis.adesao_media_7d}%`}
        icon="activity"
        description="Dias com exercício feito ÷ meta semanal"
      />
      <KpiCard label="Pacientes ativos" value={kpis.pacientes_ativos} icon="users" description="Sem os bloqueados" />
      <KpiCard
        label="Dor média 7 dias"
        value={kpis.dor_media === null ? '—' : kpis.dor_media.toLocaleString('pt-BR', { minimumFractionDigits: 1 })}
        icon="thermometer"
        description="Último registro de cada paciente (0 a 10)"
      />
      <KpiCard label="Registros em 7 dias" value={kpis.checkins_7d} icon="clipboard-check" description="Check-ins enviados pelo portal" />
    </KpiGrid>
  );
}

function FilaItem({ item, regras, motivos }: { item: ItemAtencao; regras: Regras; motivos: Motivo[] }) {
  return (
    <li className="flex flex-wrap items-center gap-2 py-3">
      <Link href={`/fisio/pacientes/${item.patient_id}`} className="min-w-0 flex-1 text-sm font-medium hover:underline">
        {item.nome ?? 'Contato sem nome'}
      </Link>
      {motivos.map((m) => (
        <Badge key={m} variant="outline">{rotuloMotivo(m, regras)}</Badge>
      ))}
      <span className="text-xs text-muted-foreground tabular-nums">
        último registro {dataCurta(item.ultimo_registro)}{item.ultima_dor !== null ? ` · dor ${item.ultima_dor}` : ''}
      </span>
    </li>
  );
}

function Fila({ titulo, descricao, itens, regras, eixo, vazio }: {
  titulo: string; descricao: string; itens: ItemAtencao[]; regras: Regras; eixo: 'clinico' | 'abandono'; vazio: string;
}) {
  const daFila = itens.filter((i) => i[eixo].length > 0);
  return (
    <Card>
      <CardContent className="flex flex-col gap-2 p-4">
        <div>
          <h2 className="text-base font-semibold">{titulo}</h2>
          <p className="text-xs text-muted-foreground">{descricao}</p>
        </div>
        {daFila.length === 0 ? (
          <EmptyState icon="check-circle" title={vazio} className="py-6" />
        ) : (
          <ul className="divide-y divide-border" data-contract={`fisio-painel-fila-${eixo}`}>
            {daFila.map((i) => <FilaItem key={i.patient_id} item={i} regras={regras} motivos={i[eixo]} />)}
          </ul>
        )}
      </CardContent>
    </Card>
  );
}

function Tabela({ pacientes }: { pacientes: LinhaPaciente[] }) {
  if (pacientes.length === 0) {
    return (
      <EmptyState
        icon="users"
        title="Nenhum paciente ativo"
        description="Cadastre um paciente a partir de um contato para ele aparecer aqui."
        action={<Button asChild variant="outline"><Link href="/fisio/pacientes">Ir para Pacientes</Link></Button>}
      />
    );
  }
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm" data-contract="fisio-painel-tabela">
        <thead>
          <tr className="border-b border-border text-left text-xs text-muted-foreground">
            <th scope="col" className="py-2 pr-3 font-medium">Paciente</th>
            <th scope="col" className="py-2 pr-3 font-medium">Adesão 7d</th>
            <th scope="col" className="py-2 pr-3 font-medium">Última dor</th>
            <th scope="col" className="py-2 pr-3 font-medium">Último registro</th>
            <th scope="col" className="py-2 pr-3 font-medium">Nível</th>
            <th scope="col" className="py-2 font-medium">Sequência</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-border">
          {pacientes.map((p) => (
            <tr key={p.id}>
              <td className="py-2 pr-3">
                <Link href={`/fisio/pacientes/${p.id}`} className="font-medium hover:underline">{p.nome ?? 'Contato sem nome'}</Link>
                <span className="block text-xs text-muted-foreground">
                  {p.programa_ativo ? `meta ${p.sessions_per_week}× por semana` : 'sem programa ativo'}
                </span>
              </td>
              <td className="py-2 pr-3 tabular-nums">{p.adesao_7d === null ? '—' : `${p.adesao_7d}%`}</td>
              <td className="py-2 pr-3 tabular-nums">{p.ultima_dor ?? '—'}</td>
              <td className="py-2 pr-3 tabular-nums">{dataCurta(p.ultimo_registro)}</td>
              <td className="py-2 pr-3 tabular-nums">{p.nivel}</td>
              <td className="py-2 tabular-nums">
                {p.sequencia} {p.sequencia === 1 ? 'dia' : 'dias'}
                {p.em_pausa && <Badge variant="outline" className="ml-2">em pausa</Badge>}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function FisioPainel({ regras, kpis, atencao, pacientes }: Props) {
  const semRegistro = kpis !== undefined && kpis.checkins_7d === 0 && (pacientes?.length ?? 0) > 0;
  return (
    <div data-screen-label="Fisio · Painel" className="mx-auto flex max-w-7xl flex-col gap-4 p-4">
      <PageHeader
        title="Painel"
        icon="layout-dashboard"
        description="Adesão, dor e quem precisa de atenção, a partir do que os pacientes registraram no portal."
      />

      <Deferred data="kpis" fallback={<Skeleton className="h-28 w-full" />}>
        {kpis ? <Kpis kpis={kpis} /> : <span />}
      </Deferred>

      {semRegistro && (
        <p className="rounded-lg border border-border bg-muted/40 p-3 text-sm text-muted-foreground" data-contract="fisio-painel-sem-registro">
          Nenhum paciente registrou o dia nos últimos 7 dias. Os números de adesão e dor aparecem quando os check-ins do portal chegarem.
        </p>
      )}

      <Deferred data="atencao" fallback={<Skeleton className="h-40 w-full" />}>
        <div className="grid gap-4 lg:grid-cols-2">
          <Fila
            titulo="Alerta"
            descricao={`Sinal de alerta, dor ≥ ${regras.limiar_dor_padrao} (ou o limiar do programa) ou dor que subiu ${regras.subida_minima_dor}+ pontos.`}
            itens={atencao ?? []}
            regras={regras}
            eixo="clinico"
            vazio="Nenhum alerta no momento"
          />
          <Fila
            titulo="Risco de abandono"
            descricao={`Programa ativo e ${regras.dias_sem_registro} dias ou mais sem registro.`}
            itens={atencao ?? []}
            regras={regras}
            eixo="abandono"
            vazio="Todos registraram nos últimos dias"
          />
        </div>
      </Deferred>

      <Card>
        <CardContent className="flex flex-col gap-3 p-4">
          <h2 className="text-base font-semibold">Pacientes</h2>
          <Deferred data="pacientes" fallback={<Skeleton className="h-40 w-full" />}>
            <Tabela pacientes={pacientes ?? []} />
          </Deferred>
        </CardContent>
      </Card>
    </div>
  );
}

FisioPainel.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
