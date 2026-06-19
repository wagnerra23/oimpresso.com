// Widget W6 — MCP server health (Sprint 2 parcial).
// Lê McpServerHealthReader: mcp_memory_documents + mcp_tokens + ping mcp.oimpresso.com.

interface Props {
  data: {
    available: boolean;
    docs_count: number;
    last_sync?: string | null;
    tokens_total: number;
    tokens_active: number;
    last_token_use?: string | null;
    ping: {
      reachable: boolean;
      status?: number;
      latency_ms?: number | null;
      error?: string;
    };
    reason?: string;
    instructions?: string;
  };
}

export default function WidgetMcpServer({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-2">
        <p className="text-gray-600">
          MCP indisponível.{' '}
          <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
        </p>
        {data.instructions && (
          <p className="text-xs text-gray-500">{data.instructions}</p>
        )}
      </div>
    );
  }

  const lastSync = data.last_sync
    ? new Date(data.last_sync).toLocaleString('pt-BR')
    : 'nunca';
  const lastUse = data.last_token_use
    ? new Date(data.last_token_use).toLocaleString('pt-BR')
    : 'nunca';

  return (
    <div className="text-sm space-y-3">
      {/* Ping status */}
      <div className="flex items-center gap-2">
        <span
          className={`inline-block w-2.5 h-2.5 rounded-full ${
            data.ping.reachable ? 'bg-success' : 'bg-destructive'
          }`}
        />
        <span className="font-medium">
          mcp.oimpresso.com{' '}
          {data.ping.reachable
            ? `(${data.ping.latency_ms}ms)`
            : `(unreachable${data.ping.status ? ` — HTTP ${data.ping.status}` : ''})`}
        </span>
      </div>

      {data.ping.error && (
        <div className="text-xs text-destructive-fg bg-destructive-soft p-2 rounded">
          {data.ping.error}
        </div>
      )}

      {/* KPIs */}
      <div className="grid grid-cols-2 gap-2">
        <div className="border rounded p-2">
          <div className="text-2xl font-semibold">{data.docs_count.toLocaleString('pt-BR')}</div>
          <div className="text-xs text-gray-500">docs sincronizados</div>
          <div className="text-xs text-gray-400">último sync: {lastSync}</div>
        </div>
        <div className="border rounded p-2">
          <div className="text-2xl font-semibold">
            {data.tokens_active}<span className="text-sm text-gray-400">/{data.tokens_total}</span>
          </div>
          <div className="text-xs text-gray-500">tokens ativos</div>
          <div className="text-xs text-gray-400">último uso: {lastUse}</div>
        </div>
      </div>
    </div>
  );
}
