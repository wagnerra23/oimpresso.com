// Widget W1 — Brief diário (read-only).
// Lê do BriefAdapter (Modules/Admin/Services/BriefAdapter.php) que consulta
// `mcp_briefs` (Modules/Brief — ADR 0091).

interface Props {
  data: {
    available: boolean;
    brief_id?: number | null;
    created_at?: string | null;
    markdown?: string;
    reason?: string;
    token_estimate?: number | null;
  };
}

export default function WidgetBrief({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-2">
        <p className="text-gray-600">
          Brief indisponível.{' '}
          <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
        </p>
        <p className="text-xs text-gray-500">
          Gerar manualmente: <code>php artisan brief:generate</code>
        </p>
      </div>
    );
  }

  const created = data.created_at ? new Date(data.created_at).toLocaleString('pt-BR') : '?';
  const tokens = data.token_estimate ?? '?';

  return (
    <div className="text-sm">
      <div className="text-xs text-gray-500 mb-2">
        Brief #{data.brief_id} · gerado {created} · ~{tokens} tokens
      </div>
      <div className="prose prose-sm max-w-none max-h-64 overflow-y-auto whitespace-pre-wrap font-mono text-xs">
        {(data.markdown ?? '').slice(0, 2000)}
        {(data.markdown ?? '').length > 2000 && (
          <span className="text-gray-400">…(truncado)</span>
        )}
      </div>
    </div>
  );
}
