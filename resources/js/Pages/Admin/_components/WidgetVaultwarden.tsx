// Widget W7 — Vaultwarden cofre.

interface Props {
  data: {
    available: boolean;
    reachable: boolean;
    latency_ms?: number;
    ciphers_total?: number;
    expiring_30d?: number;
    url?: string;
    reason?: string;
    instructions?: string;
  };
}

export default function WidgetVaultwarden({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-1">
        <p className="text-gray-600">
          Vaultwarden indisponível.{' '}
          <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
        </p>
        {data.instructions && <p className="text-xs text-gray-500">{data.instructions}</p>}
      </div>
    );
  }

  return (
    <div className="text-sm space-y-2">
      <div className="flex items-center gap-2">
        <span
          className={`inline-block w-2.5 h-2.5 rounded-full ${
            data.reachable ? 'bg-success' : 'bg-destructive'
          }`}
        />
        <span className="font-medium">{data.url}</span>
        {data.latency_ms != null && (
          <span className="text-xs text-gray-500">({data.latency_ms}ms)</span>
        )}
      </div>
      <div className="grid grid-cols-2 gap-2">
        <div className="border rounded p-2">
          <div className="text-2xl font-semibold">{data.ciphers_total ?? 0}</div>
          <div className="text-xs text-gray-500">itens no vault</div>
        </div>
        <div className="border rounded p-2">
          <div
            className={`text-2xl font-semibold ${
              (data.expiring_30d ?? 0) > 0 ? 'text-warning-fg' : 'text-success-fg'
            }`}
          >
            {data.expiring_30d ?? 0}
          </div>
          <div className="text-xs text-gray-500">vencendo 30d</div>
        </div>
      </div>
    </div>
  );
}
