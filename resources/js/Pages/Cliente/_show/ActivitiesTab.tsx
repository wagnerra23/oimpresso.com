// Onda Final.B — Tab Atividades (Spatie\Activitylog).
// Lista cronológica de eventos no contact: criação, edição, status changes, pagamentos, etc.
// Paridade com Blade legacy: resources/views/activity_log/activities.blade.php

import { Activity as ActivityIcon, Bot, Globe } from 'lucide-react';

export interface ActivityItem {
  id: number;
  created_at: string | null;
  description: string;
  description_label: string;
  causer_name: string | null;
  from_api: string | null;
  is_automatic: boolean;
  update_note: string | null;
}

export interface ActivitiesTabProps {
  activities?: ActivityItem[];
}

const formatDateTime = (iso: string | null) => {
  if (!iso) return '—';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return new Intl.DateTimeFormat('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(d);
};

export default function ActivitiesTab({ activities }: ActivitiesTabProps) {
  if (!activities) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="activities-tab-skeleton">
        Carregando atividades…
      </div>
    );
  }

  if (activities.length === 0) {
    return (
      <div className="p-8 text-center text-xs text-muted-foreground" data-testid="activities-tab-empty">
        <ActivityIcon size={20} className="mx-auto mb-2 text-muted-foreground/50" />
        Nenhuma atividade registrada.
      </div>
    );
  }

  return (
    <div className="overflow-hidden" data-testid="activities-tab-root">
      <table className="w-full text-sm">
        <thead className="bg-muted/50">
          <tr className="border-b border-border">
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Data</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Ação</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Por</th>
            <th className="text-left px-4 py-2.5 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Nota</th>
          </tr>
        </thead>
        <tbody>
          {activities.map((a) => {
            // Se description_label começa com "lang_v1." então a tradução não existe — usa o description cru.
            const actionLabel = a.description_label.startsWith('lang_v1.') ? a.description : a.description_label;
            return (
              <tr key={a.id} className="border-b border-border hover:bg-muted/40">
                <td className="px-4 py-3 text-xs text-muted-foreground tabular-nums whitespace-nowrap">
                  {formatDateTime(a.created_at)}
                </td>
                <td className="px-4 py-3 text-xs text-foreground">
                  {actionLabel}
                </td>
                <td className="px-4 py-3 text-xs">
                  <div className="flex flex-col gap-1">
                    {a.causer_name && <span className="text-foreground">{a.causer_name}</span>}
                    {a.is_automatic && (
                      <span className="inline-flex items-center gap-1 rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[10px] uppercase tracking-wider text-muted-foreground w-fit">
                        <Bot size={10} aria-hidden />
                        Automático
                      </span>
                    )}
                    {a.from_api && (
                      <span className="inline-flex items-center gap-1 rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[10px] uppercase tracking-wider text-muted-foreground w-fit">
                        <Globe size={10} aria-hidden />
                        {a.from_api}
                      </span>
                    )}
                  </div>
                </td>
                <td className="px-4 py-3 text-xs text-muted-foreground">
                  {a.update_note ?? '—'}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
      {activities.length >= 100 && (
        <div className="px-4 py-2 text-[10px] text-muted-foreground border-t border-border">
          Exibindo últimas 100 atividades.
        </div>
      )}
    </div>
  );
}
