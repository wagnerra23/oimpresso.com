// Aba Evolução da ficha (US-FISIO-010): dor e dias com exercício feito nos últimos 30 dias.
// VISÃO DO PROFISSIONAL apenas — o gráfico de dor não vai ao portal do paciente (ADR 0437 §5,
// P11 da Anvisa). Só mostra o que o paciente registrou; não interpreta nem sugere conduta.
import { Chart } from '@/Components/shared/Chart';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';

export interface PontoEvolucao {
  date: string;
  pain: number | null;
  feitos: number;
  red_flags: string[];
}
export interface Evolucao {
  dias: number;
  adesao_7d: number | null;
  sessions_per_week: number;
  pontos: PontoEvolucao[];
}

const dia = (d: string) => new Date(`${d}T12:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

export interface PontoDor { date: string; pain: number }

export default function EvolucaoPaciente({ evolucao, serieDor }: { evolucao: Evolucao; serieDor: PontoDor[] }) {
  const comDor = serieDor;
  const diasFeitos = evolucao.pontos.filter((p) => p.feitos > 0).length;
  const alertas = evolucao.pontos.filter((p) => p.red_flags.length > 0);

  if (evolucao.pontos.length === 0) {
    return (
      <EmptyState
        icon="line-chart"
        title={`Nenhum registro nos últimos ${evolucao.dias} dias`}
        description="A evolução aparece quando o paciente fizer check-in pelo portal."
      />
    );
  }

  return (
    <div className="flex flex-col gap-4" data-contract="fisio-evolucao">
      <dl className="grid grid-cols-3 gap-3 text-sm">
        <div>
          <dt className="text-xs text-muted-foreground">Adesão 7 dias</dt>
          <dd className="text-lg font-semibold tabular-nums">{evolucao.adesao_7d === null ? '—' : `${evolucao.adesao_7d}%`}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted-foreground">Dias com exercício ({evolucao.dias}d)</dt>
          <dd className="text-lg font-semibold tabular-nums">{diasFeitos}</dd>
        </div>
        <div>
          <dt className="text-xs text-muted-foreground">Meta semanal</dt>
          <dd className="text-lg font-semibold tabular-nums">{evolucao.sessions_per_week}×</dd>
        </div>
      </dl>

      <div>
        <h3 className="mb-1 text-sm font-medium">Dor registrada (0 a 10)</h3>
        {comDor.length === 0 ? (
          <p className="text-sm text-muted-foreground">Nenhum registro com dor informada.</p>
        ) : (
          <Chart
            type="line"
            color="var(--color-primary)"
            height={120}
            data={comDor.map((p) => ({ label: dia(p.date), value: p.pain }))}
            formatValue={(v) => `dor ${v}`}
          />
        )}
      </div>

      <div>
        <h3 className="mb-1 text-sm font-medium">Exercícios feitos por dia</h3>
        <Chart
          type="bar"
          height={80}
          color="var(--color-muted-foreground)"
          data={evolucao.pontos.map((p) => ({ label: dia(p.date), value: p.feitos }))}
          formatValue={(v) => `${v} feito(s)`}
        />
      </div>

      {alertas.length > 0 && (
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="text-muted-foreground">Sinal de alerta marcado em:</span>
          {alertas.map((p) => <Badge key={p.date} variant="outline">{dia(p.date)}</Badge>)}
        </div>
      )}
    </div>
  );
}
