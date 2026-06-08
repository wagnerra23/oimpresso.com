// Widget W8 — Sessões Claude Code (cross-dev cc-watcher).

interface Session {
  id: number;
  session_uuid: string;
  user_id: number;
  project_path: string;
  started_at: string;
  total_tokens: number;
  total_cost_brl: number;
  status: string;
}

interface DevAggregate {
  dev: string;
  sessions: number;
  tokens: number;
  cost_brl: number;
  last_at: string;
}

interface Props {
  data: {
    available: boolean;
    latest: Session[];
    by_dev: DevAggregate[];
    reason?: string;
    instructions?: string;
  };
}

const fmtBrl = (n: number | string): string => {
  const num = typeof n === 'string' ? parseFloat(n) : n;
  return `R$ ${num.toFixed(2).replace('.', ',')}`;
};

const fmtDate = (iso: string): string => {
  try {
    return new Date(iso).toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return iso;
  }
};

export default function WidgetSessions({ data }: Props) {
  if (!data.available) {
    return (
      <div className="text-sm space-y-1">
        <p className="text-gray-600">
          Watcher CC inativo.{' '}
          <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
        </p>
        {data.instructions && <p className="text-xs text-gray-500">{data.instructions}</p>}
      </div>
    );
  }

  return (
    <div className="text-sm space-y-3">
      {data.by_dev.length > 0 && (
        <div>
          <div className="text-xs text-gray-500 mb-1">Por dev (7 dias)</div>
          <table className="w-full text-xs">
            <thead className="text-gray-500">
              <tr>
                <th className="text-left">Dev</th>
                <th className="text-right">Sess.</th>
                <th className="text-right">Tokens</th>
                <th className="text-right">Custo</th>
                <th className="text-right">Última</th>
              </tr>
            </thead>
            <tbody>
              {data.by_dev.map((d) => (
                <tr key={d.dev} className="border-t">
                  <td className="py-1 font-medium">{d.dev}</td>
                  <td className="text-right">{d.sessions}</td>
                  <td className="text-right">{(d.tokens ?? 0).toLocaleString('pt-BR')}</td>
                  <td className="text-right">{fmtBrl(d.cost_brl ?? 0)}</td>
                  <td className="text-right text-gray-500">{fmtDate(d.last_at)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {data.latest.length === 0 && data.by_dev.length === 0 && (
        <p className="text-gray-500 text-sm">Nenhuma sessão registrada.</p>
      )}
    </div>
  );
}
