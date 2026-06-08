// @memcofre
//   tela: /tarefas
//   stories: a definir (Fase 4 ADR 0039 — TaskProvider/TaskRegistry)
//   adrs: 0039 (Cockpit), UI-0008 (cockpit layout), UI-0011 (sidebar single-pane)
//   layout: tasks.jsx canon Cowork 2026-04-27 (master/detail interno)
//   status: stub (placeholder visual; backend de tarefas ainda não existe)
//   module: app principal (cross-módulo)

import React, { useState, useMemo, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import { Bell, Check, Cog, Inbox, MoreHorizontal, Search } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';

interface Task {
  id: string;
  origin: 'OS' | 'CRM' | 'FIN' | 'PNT' | 'MFG';
  title: string;
  subtitle: string;
  from: string;
  when: string;
  group: 'hoje' | 'atrasadas' | 'semana';
  urgent?: boolean;
  unread?: boolean;
  viewer?: string;
}

// MOCK temporário até TaskProvider/TaskRegistry (Fase 4 ADR 0039)
const MOCK_TASKS: Task[] = [];

const FILTERS: Array<{ key: string; label: string }> = [
  { key: 'all', label: 'Todas' },
  { key: 'hoje', label: 'Hoje' },
  { key: 'atrasadas', label: 'Atrasadas' },
  { key: 'OS', label: 'OS' },
  { key: 'CRM', label: 'CRM' },
  { key: 'FIN', label: 'Financeiro' },
  { key: 'PNT', label: 'Ponto' },
];

const GROUPS: Array<{ key: 'hoje' | 'atrasadas' | 'semana'; label: string }> = [
  { key: 'hoje', label: 'Hoje' },
  { key: 'atrasadas', label: 'Atrasadas' },
  { key: 'semana', label: 'Esta semana' },
];

function TaskCard({ task, active, onClick }: { task: Task; active: boolean; onClick: () => void }) {
  return (
    <div
      className={`tk-card ${active ? 'active' : ''} ${task.urgent ? 'urgent' : ''}`}
      onClick={onClick}
    >
      <div className="tk-card-h">
        <span
          className="tk-origin"
          style={{ background: `var(--origin-${task.origin}-bg)`, color: `var(--origin-${task.origin}-fg)` }}
        >
          {task.origin}
        </span>
        {task.unread && <span className="tk-dot" />}
        <span className="tk-when">{task.when}</span>
      </div>
      <b className="tk-title">{task.title}</b>
      <small className="tk-sub">{task.subtitle}</small>
      <div className="tk-foot">
        <span className="tk-from">de {task.from}</span>
      </div>
    </div>
  );
}

function TasksList({
  tasks,
  activeId,
  onSelect,
  filter,
  onFilter,
  query,
  onQuery,
}: {
  tasks: Task[];
  activeId: string | null;
  onSelect: (id: string) => void;
  filter: string;
  onFilter: (f: string) => void;
  query: string;
  onQuery: (q: string) => void;
}) {
  const filtered = useMemo(() => {
    let out = tasks;
    if (filter !== 'all') {
      if (['OS', 'CRM', 'FIN', 'PNT', 'MFG'].includes(filter)) {
        out = out.filter((t) => t.origin === filter);
      } else {
        out = out.filter((t) => t.group === filter);
      }
    }
    if (query.trim()) {
      const q = query.toLowerCase();
      out = out.filter(
        (t) =>
          t.title.toLowerCase().includes(q) ||
          t.subtitle.toLowerCase().includes(q) ||
          (t.from || '').toLowerCase().includes(q),
      );
    }
    return out;
  }, [tasks, filter, query]);

  const kpis = useMemo(
    () => ({
      hoje: tasks.filter((t) => t.group === 'hoje').length,
      atrasadas: tasks.filter((t) => t.group === 'atrasadas').length,
      semana: tasks.filter((t) => t.group === 'semana').length,
    }),
    [tasks],
  );

  return (
    <div className="tk-list">
      <div className="tk-list-h">
        <div className="tk-list-h-row">
          <h2>Tarefas</h2>
          <span className="tk-count">{tasks.length}</span>
          <button className="tk-mini-btn" type="button" title="Configurar">
            <Cog size={12} />
          </button>
        </div>
        <p className="tk-list-h-sub">Inbox unificada de todos os módulos</p>
        <div className="tk-kpis">
          <div className={`tk-kpi ${kpis.atrasadas > 0 ? 'warn' : ''}`}>
            <b>{kpis.atrasadas}</b>
            <small>Atrasadas</small>
          </div>
          <div className="tk-kpi">
            <b>{kpis.hoje}</b>
            <small>Hoje</small>
          </div>
          <div className="tk-kpi muted">
            <b>{kpis.semana}</b>
            <small>Semana</small>
          </div>
        </div>
        <div className="tk-search">
          <Search size={12} />
          <input
            placeholder="Buscar tarefa, cliente, OS..."
            value={query}
            onChange={(e) => onQuery(e.target.value)}
          />
        </div>
      </div>
      <div className="tk-filters">
        {FILTERS.map((f) => (
          <button
            key={f.key}
            type="button"
            className={`tk-filter ${filter === f.key ? 'active' : ''}`}
            onClick={() => onFilter(f.key)}
          >
            {f.label}
          </button>
        ))}
      </div>
      <div className="tk-list-body">
        {GROUPS.map((g) => {
          const items = filtered.filter((t) => t.group === g.key);
          if (items.length === 0) return null;
          return (
            <div key={g.key} className="tk-group">
              <div className="tk-group-h">
                <span>{g.label}</span>
                <span className="tk-group-c">{items.length}</span>
              </div>
              {items.map((t) => (
                <TaskCard
                  key={t.id}
                  task={t}
                  active={t.id === activeId}
                  onClick={() => onSelect(t.id)}
                />
              ))}
            </div>
          );
        })}
        {filtered.length === 0 && (
          <div className="tk-empty">
            <div className="tk-empty-ico">
              <Check size={20} />
            </div>
            <b>Tudo em dia</b>
            <small>
              {query
                ? 'Nenhuma tarefa bate com a busca.'
                : tasks.length === 0
                ? 'Backend de tarefas ainda não implementado (Fase 4 ADR 0039).'
                : 'Nada pendente nesse filtro.'}
            </small>
          </div>
        )}
      </div>
    </div>
  );
}

function TaskDetail({
  task,
  listIndex,
  listTotal,
}: {
  task: Task | null;
  listIndex: number | null;
  listTotal: number;
}) {
  if (!task) {
    return (
      <div className="tk-detail empty">
        <div className="tk-empty-state">
          <div className="tk-empty-ico lg">
            <Inbox size={28} />
          </div>
          <b>Selecione uma tarefa</b>
          <small>Escolha uma tarefa na lista para resolvê-la inline.</small>
          <div className="tk-shortcuts">
            <span>
              <kbd>J</kbd>
              <kbd>K</kbd> navegar
            </span>
            <span>
              <kbd>E</kbd> concluir
            </span>
            <span>
              <kbd>A</kbd> adiar
            </span>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="tk-detail">
      <div className="tk-detail-h">
        <div className="tk-detail-bc">
          <span
            className="tk-origin"
            style={{ background: `var(--origin-${task.origin}-bg)`, color: `var(--origin-${task.origin}-fg)` }}
          >
            {task.origin}
          </span>
          <span className="tk-bc-sep">·</span>
          <span className="tk-bc-step">{task.subtitle.split(' · ')[0]}</span>
          {listIndex != null && listTotal > 0 && (
            <>
              <span className="tk-bc-spacer" />
              <span className="tk-bc-pos">
                {listIndex + 1} / {listTotal}
              </span>
            </>
          )}
        </div>
        <div className="tk-detail-title-row">
          <h1>{task.title}</h1>
          <div className="tk-detail-actions">
            <button className="icon-btn" type="button" title="Marcar como não lida">
              <Bell size={14} />
            </button>
            <button className="icon-btn" type="button" title="Mais opções">
              <MoreHorizontal size={14} />
            </button>
          </div>
        </div>
        <div className="tk-detail-meta">
          <span>
            <span className="t-mute">de</span> <b>{task.from}</b>
          </span>
          <span className="tk-bc-sep">·</span>
          <span>
            <span className="t-mute">para</span> <b>você</b>
          </span>
          <span className="tk-bc-sep">·</span>
          <span className={task.urgent ? 'urgent' : ''}>
            <span className="t-mute">prazo</span> <b>{task.when}</b>
          </span>
        </div>
      </div>
      <div className="tk-detail-body">
        <div className="empty">Viewer pendente: {task.viewer ?? 'genérico'}</div>
      </div>
    </div>
  );
}

export default function TarefasIndex() {
  const [activeId, setActiveId] = useState<string | null>(MOCK_TASKS[0]?.id ?? null);
  const [filter, setFilter] = useState<string>('all');
  const [query, setQuery] = useState<string>('');
  const tasks = MOCK_TASKS;

  const active = tasks.find((t) => t.id === activeId) ?? null;
  const activeIdx = tasks.findIndex((t) => t.id === activeId);

  // Atalhos J/K/E/A (canon tasks.jsx)
  useEffect(() => {
    const h = (e: KeyboardEvent) => {
      if ((e.target as HTMLElement)?.matches('input, textarea')) return;
      const idx = tasks.findIndex((t) => t.id === activeId);
      if (e.key === 'j' || e.key === 'ArrowDown') {
        e.preventDefault();
        const n = tasks[Math.min(tasks.length - 1, idx + 1)];
        if (n) setActiveId(n.id);
      } else if (e.key === 'k' || e.key === 'ArrowUp') {
        e.preventDefault();
        const n = tasks[Math.max(0, idx - 1)];
        if (n) setActiveId(n.id);
      }
      // E (concluir) / A (adiar) — implementar quando backend existir (Fase 4)
    };
    document.addEventListener('keydown', h);
    return () => document.removeEventListener('keydown', h);
  }, [activeId, tasks]);

  return (
    <>
      <Head title="Tarefas — Oimpresso" />
      <div className="tk-page">
        <TasksList
          tasks={tasks}
          activeId={activeId}
          onSelect={setActiveId}
          filter={filter}
          onFilter={setFilter}
          query={query}
          onQuery={setQuery}
        />
        <TaskDetail task={active} listIndex={activeIdx >= 0 ? activeIdx : null} listTotal={tasks.length} />
      </div>
    </>
  );
}

TarefasIndex.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Tarefas" breadcrumbItems={[{ label: 'Tarefas' }]}>
    {page}
  </AppShellV2>
);
