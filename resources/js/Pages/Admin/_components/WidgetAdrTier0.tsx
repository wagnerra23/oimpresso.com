// Widget W4 — ADRs Tier 0 violadas.
// Lê AdrAlertReader que filtra checks do snapshot mapeando pra ADRs irrevogáveis
// (multi_tenant_isolation → 0093, pii_leak → 0094, etc).

interface AdrAlert {
  check: string;
  adr: string;
  status: string;
  message?: string;
  last_run?: string | null;
}

interface Props {
  data: {
    available: boolean;
    tier_0_alerts: AdrAlert[];
    count?: number;
    reason?: string;
  };
}

export default function WidgetAdrTier0({ data }: Props) {
  if (!data.available) {
    return (
      <p className="text-sm text-gray-600">
        Snapshot ausente — não dá pra avaliar Tier 0.{' '}
        <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
      </p>
    );
  }

  if (data.tier_0_alerts.length === 0) {
    return (
      <div className="flex items-center gap-2 text-success-fg">
        <span>✅</span>
        <span className="text-sm">Nenhuma ADR Tier 0 violada — sistema saudável.</span>
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <div className="text-sm text-destructive-fg font-medium">
        {data.tier_0_alerts.length} violação(ões) detectada(s)
      </div>
      <ul className="space-y-2">
        {data.tier_0_alerts.map((alert, idx) => (
          <li
            key={`${alert.check}-${idx}`}
            className="border-l-4 border-destructive bg-destructive-soft p-3 text-sm"
          >
            <div className="flex items-center justify-between">
              <span className="font-mono text-xs text-destructive-fg">{alert.check}</span>
              <a
                href={`https://github.com/wagnerra23/oimpresso.com/blob/main/memory/decisions/${alert.adr}-*`}
                target="_blank"
                rel="noopener noreferrer"
                className="text-xs text-destructive underline hover:text-destructive-fg"
              >
                ADR {alert.adr}
              </a>
            </div>
            {alert.message && (
              <div className="text-xs text-gray-700 mt-1">{alert.message}</div>
            )}
            <div className="text-xs text-gray-500 mt-1">
              status:{' '}
              <span className="font-medium text-destructive-fg">{alert.status}</span>
              {alert.last_run && ` · último run: ${alert.last_run}`}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
}
