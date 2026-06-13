// Widget W10 — Custos Brain B 24h.

interface Props {
  data: {
    available: boolean;
    cost_brl_24h?: number;
    threshold_brl?: number;
    pct_threshold?: number;
    status?: 'green' | 'yellow' | 'red' | 'unknown';
    last_run?: string | null;
    reason?: string;
    instructions?: string;
  };
}

const fmtBrl = (n: number): string =>
  `R$ ${n.toFixed(2).replace('.', ',')}`;

const statusColor: Record<string, string> = {
  green:   'text-success-fg',
  yellow:  'text-warning-fg',
  red:     'text-destructive-fg',
  unknown: 'text-muted-foreground',
};

const statusBg: Record<string, string> = {
  green:   'bg-success/10',
  yellow:  'bg-warning/10',
  red:     'bg-destructive/10',
  unknown: 'bg-muted',
};

export default function WidgetBrainBCost({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-1">
        <p className="text-gray-600">
          Custo indisponível.{' '}
          <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
        </p>
        {data.instructions && <p className="text-xs text-gray-500">{data.instructions}</p>}
      </div>
    );
  }

  const status = data.status ?? 'unknown';
  const cost = data.cost_brl_24h ?? 0;
  const threshold = data.threshold_brl ?? 500;
  const pct = data.pct_threshold ?? 0;

  return (
    <div className="text-sm space-y-2">
      <div className={`p-3 rounded ${statusBg[status]}`}>
        <div className={`text-3xl font-semibold ${statusColor[status]}`}>{fmtBrl(cost)}</div>
        <div className="text-xs text-gray-600 mt-1">últimas 24h · Brain B (Sonnet/Opus)</div>
      </div>

      <div className="text-xs space-y-1">
        <div className="flex justify-between">
          <span>Threshold alarme</span>
          <span className="font-medium">{fmtBrl(threshold)}</span>
        </div>
        <div className="flex justify-between">
          <span>% do threshold</span>
          <span className={`font-medium ${statusColor[status]}`}>{pct.toFixed(1)}%</span>
        </div>
        {data.last_run && (
          <div className="flex justify-between">
            <span>Última leitura</span>
            <span className="text-gray-500">{new Date(data.last_run).toLocaleString('pt-BR')}</span>
          </div>
        )}
      </div>
    </div>
  );
}
