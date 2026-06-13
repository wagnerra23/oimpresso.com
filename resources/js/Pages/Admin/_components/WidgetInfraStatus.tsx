// Widget W9 — Infra status (5 healthchecks paralelos).

interface HostStatus {
  status: 'up' | 'down' | 'degraded';
  latency_ms?: number | null;
  http_status?: number;
  error?: string;
}

interface Props {
  data: {
    hostinger_ssh: HostStatus;
    ct100_tailscale: HostStatus;
    centrifugo: HostStatus;
    meilisearch: HostStatus;
    mysql: HostStatus;
  };
}

const labels: Record<string, string> = {
  hostinger_ssh:   'Hostinger SSH',
  ct100_tailscale: 'CT 100 (Tailscale)',
  centrifugo:      'Centrifugo',
  meilisearch:     'Meilisearch',
  mysql:           'MySQL',
};

const statusColor: Record<string, string> = {
  up:       'bg-success',
  degraded: 'bg-warning',
  down:     'bg-destructive',
};

export default function WidgetInfraStatus({ data }: Props) {
  return (
    <div className="text-sm space-y-1">
      {Object.entries(data).map(([key, host]) => (
        <div
          key={key}
          className="flex items-center justify-between border rounded px-2 py-1.5 text-xs"
        >
          <div className="flex items-center gap-2">
            <span
              className={`inline-block w-2 h-2 rounded-full ${
                statusColor[host.status] ?? 'bg-gray-400'
              }`}
            />
            <span className="font-medium">{labels[key] ?? key}</span>
          </div>
          <div className="text-muted-foreground">
            {host.status === 'up' && host.latency_ms != null
              ? `${host.latency_ms}ms`
              : host.status}
            {host.http_status && host.status !== 'up' && ` (HTTP ${host.http_status})`}
          </div>
        </div>
      ))}
    </div>
  );
}
