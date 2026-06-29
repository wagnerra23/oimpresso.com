// Widget W3 — Cycles ativos + tasks por dev.
// Lê tabelas mcp_cycles + mcp_tasks (ADR 0070 — Jira-style task management).

interface Cycle {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  goal_summary?: string;
}

interface DevTaskCount {
  owner: string;
  total: number;
  doing: number;
  done: number;
}

interface Props {
  data: {
    available: boolean;
    cycles_active: Cycle[];
    tasks_by_dev: DevTaskCount[];
    current_cycle?: number | null;
    reason?: string;
  };
}

export default function WidgetCycles({ data }: Props) {
  if (!data.available) {
    return (
      <p className="text-sm text-gray-600">
        Tabelas mcp_cycles/mcp_tasks ausentes.{' '}
        <code className="text-xs bg-gray-100 px-1 rounded">{data.reason}</code>
      </p>
    );
  }

  if (data.cycles_active.length === 0) {
    return <p className="text-sm text-gray-500">Nenhum cycle ativo no momento.</p>;
  }

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
        {data.cycles_active.map((cycle) => (
          <div
            key={cycle.id}
            className={`border rounded p-3 ${
              cycle.id === data.current_cycle ? 'bg-primary/5 border-primary/30' : 'bg-white'
            }`}
          >
            <div className="font-medium">{cycle.name}</div>
            <div className="text-xs text-gray-500">
              {cycle.start_date} → {cycle.end_date}
            </div>
            {cycle.goal_summary && (
              <div className="text-xs mt-1 text-gray-700">
                {cycle.goal_summary.slice(0, 120)}
                {cycle.goal_summary.length > 120 && '…'}
              </div>
            )}
          </div>
        ))}
      </div>

      {data.tasks_by_dev.length > 0 && (
        <div>
          <div className="text-xs text-gray-500 mb-1">Tasks por dev (cycle atual)</div>
          <table className="w-full text-sm">
            <thead className="text-xs text-gray-500">
              <tr>
                <th className="text-left pb-1">Dev</th>
                <th className="text-right pb-1">Total</th>
                <th className="text-right pb-1">Doing</th>
                <th className="text-right pb-1">Done</th>
                <th className="text-right pb-1">% Done</th>
              </tr>
            </thead>
            <tbody>
              {data.tasks_by_dev.map((dev) => {
                const pct = dev.total > 0 ? Math.round((dev.done / dev.total) * 100) : 0;
                return (
                  <tr key={dev.owner} className="border-t">
                    <td className="py-1 font-medium">{dev.owner}</td>
                    <td className="text-right">{dev.total}</td>
                    <td className="text-right text-blue-600">{dev.doing}</td>
                    <td className="text-right text-success">{dev.done}</td>
                    <td className="text-right">{pct}%</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
