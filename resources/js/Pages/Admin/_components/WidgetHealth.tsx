// Widget W2 — Health Checks 5 SQL.
// Lê snapshot file (storage/app/jana-health-snapshot.json) gerado por
// `php artisan jana:health-check`.

interface HealthCheck {
  name: string;
  status: 'green' | 'yellow' | 'red' | 'unknown';
  message?: string;
  last_run?: string | null;
}

interface Props {
  data: {
    available: boolean;
    generated_at?: string | null;
    checks: HealthCheck[];
    overall_status: 'green' | 'yellow' | 'red' | 'unknown';
    reason?: string;
    instructions?: string;
  };
}

const statusColor: Record<string, string> = {
  green: 'bg-success',
  yellow: 'bg-warning',
  red: 'bg-destructive',
  unknown: 'bg-muted-foreground',
};

const statusEmoji: Record<string, string> = {
  green: '🟢',
  yellow: '🟡',
  red: '🔴',
  unknown: '⚫',
};

export default function WidgetHealth({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-2">
        <p className="text-muted-foreground">
          Snapshot ausente. <code className="text-xs bg-muted px-1 rounded">{data.reason}</code>
        </p>
        {data.instructions && (
          <p className="text-xs text-gray-500 font-mono">{data.instructions}</p>
        )}
      </div>
    );
  }

  if (data.checks.length === 0) {
    return <p className="text-sm text-gray-500">Sem checks no snapshot.</p>;
  }

  const generated = data.generated_at
    ? new Date(data.generated_at).toLocaleString('pt-BR')
    : '?';

  return (
    <div className="text-sm space-y-2">
      <div className="text-xs text-gray-500">
        Snapshot gerado em {generated}
      </div>
      <ul className="space-y-1">
        {data.checks.map((check) => (
          <li key={check.name} className="flex items-start gap-2 text-sm">
            <span className="mt-0.5">{statusEmoji[check.status] ?? '⚫'}</span>
            <div className="flex-1">
              <div className="font-medium">{check.name}</div>
              {check.message && (
                <div className="text-xs text-gray-600">{check.message}</div>
              )}
            </div>
            <span
              className={`text-xs text-white rounded px-1.5 py-0.5 ${
                statusColor[check.status] ?? 'bg-gray-400'
              }`}
            >
              {check.status}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
