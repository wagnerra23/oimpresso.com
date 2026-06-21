// Widget W5 — Curador / Modules/Arquivos backbone (Sprint 2 parcial).
// Lê CuradorStatsReader que consulta arquivos + arquivos_audit_log + arquivos_dedupe.

interface Props {
  data: {
    available: boolean;
    total_active: number;
    by_bucket: Record<string, number>;
    sensitive_count: number;
    audit_24h: Record<string, number>;
    dedupe_rate_pct: number;
    unique_md5?: number;
    total_occurrences?: number;
    reason?: string;
    instructions?: string;
  };
}

const bucketColors: Record<string, string> = {
  active:    'bg-blue-100 text-blue-800',
  sensitive: 'bg-red-100 text-red-800',
  memory:    'bg-purple-100 text-purple-800',
  user:      'bg-indigo-100 text-indigo-800',
  spec:      'bg-teal-100 text-teal-800',
  ambiguous: 'bg-amber-100 text-amber-800',
  discard:   'bg-gray-200 text-gray-700',
};

export default function WidgetCurador({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-2">
        <p className="text-gray-600">
          Tabelas Modules/Arquivos ausentes.{' '}
          <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
        </p>
        {data.instructions && (
          <p className="text-xs text-gray-500 font-mono">{data.instructions}</p>
        )}
      </div>
    );
  }

  if (data.total_active === 0) {
    return <p className="text-sm text-gray-500">Nenhum arquivo ainda. Anexe via trait HasArquivos.</p>;
  }

  return (
    <div className="text-sm space-y-3">
      {/* Header com KPIs principais */}
      <div className="grid grid-cols-3 gap-2 text-center">
        <div className="border rounded p-2">
          <div className="text-2xl font-semibold">{data.total_active}</div>
          <div className="text-xs text-gray-500">total ativo</div>
        </div>
        <div className="border rounded p-2">
          <div className="text-2xl font-semibold text-destructive-fg">{data.sensitive_count}</div>
          <div className="text-xs text-gray-500">sensitive vault</div>
        </div>
        <div className="border rounded p-2">
          <div className="text-2xl font-semibold">{data.dedupe_rate_pct}%</div>
          <div className="text-xs text-gray-500">dedupe rate</div>
        </div>
      </div>

      {/* Distribuição por bucket */}
      <div>
        <div className="text-xs text-gray-500 mb-1">Por bucket</div>
        <div className="flex flex-wrap gap-1">
          {Object.entries(data.by_bucket).map(([bucket, count]) => (
            <span
              key={bucket}
              className={`text-xs px-2 py-0.5 rounded ${bucketColors[bucket] ?? 'bg-gray-100 text-gray-700'}`}
            >
              {bucket}: {count}
            </span>
          ))}
        </div>
      </div>

      {/* Audit 24h */}
      {Object.keys(data.audit_24h).length > 0 && (
        <div>
          <div className="text-xs text-gray-500 mb-1">Audit 24h</div>
          <div className="flex flex-wrap gap-1">
            {Object.entries(data.audit_24h).map(([action, count]) => (
              <span key={action} className="text-xs px-2 py-0.5 rounded bg-slate-100 text-slate-700">
                {action}: {count}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Dedupe stats */}
      {(data.unique_md5 ?? 0) > 0 && (
        <div className="text-xs text-gray-500 pt-1 border-t">
          {data.unique_md5} arquivos únicos · {data.total_occurrences} ocorrências totais
        </div>
      )}
    </div>
  );
}
