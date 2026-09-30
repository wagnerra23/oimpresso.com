import { useState } from 'react';
import { motion } from 'framer-motion';
import {
  ClipboardList, AlertTriangle, Clock, CheckCircle, User, ChevronRight,
  Filter
} from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { useTweaks } from '@/contexts/TweaksContext';
import { TASKS } from '@/data/mockData';
import type { ScreenName } from '@/contexts/AppStateContext';
import { Card } from '@/components/shared/Card';
import { Badge } from '@/components/shared/Badge';
import { SegmentedControl } from '@/components/shared/SegmentedControl';
import { SearchBar } from '@/components/shared/SearchBar';
import { FloatingActionButton } from '@/components/shared/FloatingActionButton';

const container = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.05 } } };
const itemAnim = { hidden: { y: 10, opacity: 0 }, show: { y: 0, opacity: 1 } };

type FilterStatus = 'all' | 'pending' | 'in_progress' | 'completed' | 'blocked';
type FilterPriority = 'all' | 'urgent' | 'high' | 'medium' | 'low';

export function TasksScreen() {
  const { navigate } = useAppState();
  const { tweaks } = useTweaks();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<FilterStatus>('all');
  const [priorityFilter, setPriorityFilter] = useState<FilterPriority>('all');
  const [showFilters, setShowFilters] = useState(false);

  let tasks = tweaks.taskState === 'vazio' ? [] : tweaks.taskState === 'urgente'
    ? TASKS.filter(t => t.priority === 'urgent')
    : TASKS;

  if (search) {
    tasks = tasks.filter(t => t.title.toLowerCase().includes(search.toLowerCase()) || t.description.toLowerCase().includes(search.toLowerCase()));
  }
  if (statusFilter !== 'all') {
    tasks = tasks.filter(t => t.status === statusFilter);
  }
  if (priorityFilter !== 'all') {
    tasks = tasks.filter(t => t.priority === priorityFilter);
  }

  const grouped = {
    urgent: tasks.filter(t => t.priority === 'urgent' && t.status !== 'completed'),
    high: tasks.filter(t => t.priority === 'high' && t.status !== 'completed'),
    normal: tasks.filter(t => ['medium', 'low'].includes(t.priority) && t.status !== 'completed'),
    completed: tasks.filter(t => t.status === 'completed'),
  };

  return (
    <div className="h-full flex flex-col">
      <div className="p-[var(--content-padding)] pb-2 space-y-3">
        <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">Tarefas</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-0.5">{tasks.length} tarefas no total</p>
        </motion.div>

        <SearchBar value={search} onChange={setSearch} placeholder="Buscar tarefas..." />

        <div className="flex items-center gap-2">
          <SegmentedControl
            value={statusFilter}
            onChange={v => setStatusFilter(v as FilterStatus)}
            options={[
              { value: 'all', label: 'Todas' },
              { value: 'pending', label: 'Pendentes' },
              { value: 'in_progress', label: 'Andamento' },
              { value: 'completed', label: 'Concluídas' },
            ]}
          />
          <button
            onClick={() => setShowFilters(!showFilters)}
            className="p-2 rounded-lg border transition-colors"
            style={{
              background: showFilters ? 'var(--primary-subtle)' : 'var(--bg-surface)',
              borderColor: showFilters ? 'var(--primary)' : 'var(--border-color)',
            }}
          >
            <Filter size={18} style={{ color: showFilters ? 'var(--primary)' : 'var(--text-secondary)' }} />
          </button>
        </div>

        {showFilters && (
          <motion.div initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} className="overflow-hidden">
            <p className="text-xs font-medium text-[var(--text-secondary)] mb-2">Prioridade</p>
            <div className="flex gap-2 flex-wrap">
              {(['all', 'urgent', 'high', 'medium', 'low'] as const).map(p => (
                <button
                  key={p}
                  onClick={() => setPriorityFilter(p)}
                  className="px-3 py-1 rounded-full text-xs font-medium border transition-all"
                  style={{
                    background: priorityFilter === p ? 'var(--primary-subtle)' : 'var(--bg-elevated)',
                    borderColor: priorityFilter === p ? 'var(--primary)' : 'var(--border-color)',
                    color: priorityFilter === p ? 'var(--primary)' : 'var(--text-secondary)',
                  }}
                >
                  {p === 'all' ? 'Todas' : p === 'urgent' ? 'Urgente' : p === 'high' ? 'Alta' : p === 'medium' ? 'Média' : 'Baixa'}
                </button>
              ))}
            </div>
          </motion.div>
        )}
      </div>

      <motion.div className="flex-1 overflow-y-auto p-[var(--content-padding)] pt-0 space-y-4" variants={container} initial="hidden" animate="show">
        {grouped.urgent.length > 0 && (
          <div>
            <div className="flex items-center gap-2 mb-2 px-1">
              <AlertTriangle size={14} className="text-[#FF453A]" />
              <span className="text-xs font-semibold uppercase tracking-wider text-[#FF453A]">Urgente</span>
              <span className="text-xs text-[var(--text-tertiary)] ml-auto">{grouped.urgent.length}</span>
            </div>
            <div className="space-y-2">
              {grouped.urgent.map(task => (
                <motion.div key={task.id} variants={itemAnim}>
                  <TaskCard task={task} navigate={navigate} />
                </motion.div>
              ))}
            </div>
          </div>
        )}

        {grouped.high.length > 0 && (
          <div>
            <div className="flex items-center gap-2 mb-2 px-1">
              <AlertTriangle size={14} className="text-[#FF9F0A]" />
              <span className="text-xs font-semibold uppercase tracking-wider text-[#FF9F0A]">Alta Prioridade</span>
              <span className="text-xs text-[var(--text-tertiary)] ml-auto">{grouped.high.length}</span>
            </div>
            <div className="space-y-2">
              {grouped.high.map(task => (
                <motion.div key={task.id} variants={itemAnim}>
                  <TaskCard task={task} navigate={navigate} />
                </motion.div>
              ))}
            </div>
          </div>
        )}

        {grouped.normal.length > 0 && (
          <div>
            <div className="flex items-center gap-2 mb-2 px-1">
              <ClipboardList size={14} className="text-[var(--text-secondary)]" />
              <span className="text-xs font-semibold uppercase tracking-wider text-[var(--text-secondary)]">Normal</span>
              <span className="text-xs text-[var(--text-tertiary)] ml-auto">{grouped.normal.length}</span>
            </div>
            <div className="space-y-2">
              {grouped.normal.map(task => (
                <motion.div key={task.id} variants={itemAnim}>
                  <TaskCard task={task} navigate={navigate} />
                </motion.div>
              ))}
            </div>
          </div>
        )}

        {grouped.completed.length > 0 && (
          <div>
            <div className="flex items-center gap-2 mb-2 px-1">
              <CheckCircle size={14} className="text-[#30D158]" />
              <span className="text-xs font-semibold uppercase tracking-wider text-[#30D158]">Concluídas</span>
              <span className="text-xs text-[var(--text-tertiary)] ml-auto">{grouped.completed.length}</span>
            </div>
            <div className="space-y-2">
              {grouped.completed.map(task => (
                <motion.div key={task.id} variants={itemAnim}>
                  <TaskCard task={task} navigate={navigate} />
                </motion.div>
              ))}
            </div>
          </div>
        )}

        {tasks.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <ClipboardList size={48} className="text-[var(--text-tertiary)] mb-4" />
            <p className="text-[var(--text-secondary)] font-medium">Nenhuma tarefa encontrada</p>
            <p className="text-sm text-[var(--text-tertiary)] mt-1">Ajuste os filtros ou crie uma nova tarefa</p>
          </div>
        )}
      </motion.div>

      <FloatingActionButton onClick={() => {}} className="absolute bottom-24 right-4" />
    </div>
  );
}

function TaskCard({ task, navigate }: { task: typeof TASKS[0]; navigate: (s: ScreenName, id?: string) => void }) {
  const priorityBadge = {
    urgent: 'urgent' as const,
    high: 'high' as const,
    medium: 'medium' as const,
    low: 'low' as const,
  };

  const statusColors: Record<string, string> = {
    pending: '#FF9F0A',
    in_progress: '#0A84FF',
    completed: '#30D158',
    blocked: '#FF453A',
  };

  const completedSubtasks = task.subtasks.filter(st => st.completed).length;
  const totalSubtasks = task.subtasks.length;

  return (
    <Card pressable onClick={() => navigate('taskDetail', task.id)} padding="default">
      <div className="flex items-start gap-3">
        <div className="w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0" style={{ background: statusColors[task.status] }} />
        <div className="flex-1 min-w-0">
          <div className="flex items-start justify-between gap-2">
            <p className={`text-sm font-medium truncate ${task.status === 'completed' ? 'line-through text-[var(--text-tertiary)]' : 'text-[var(--text-primary)]'}`}>
              {task.title}
            </p>
            <Badge variant={priorityBadge[task.priority]}>{task.priority === 'urgent' ? 'U' : task.priority === 'high' ? 'A' : task.priority === 'medium' ? 'M' : 'B'}</Badge>
          </div>
          <p className="text-xs text-[var(--text-secondary)] mt-1 line-clamp-2">{task.description}</p>
          <div className="flex items-center gap-3 mt-2">
            <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
              <Clock size={12} /> {task.dueDate}
            </span>
            <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
              <User size={12} /> {task.assignee}
            </span>
            {totalSubtasks > 0 && (
              <span className="text-xs font-mono" style={{ color: completedSubtasks === totalSubtasks ? '#30D158' : 'var(--text-tertiary)' }}>
                {completedSubtasks}/{totalSubtasks}
              </span>
            )}
          </div>
        </div>
        <ChevronRight size={16} className="text-[var(--text-tertiary)] flex-shrink-0 mt-1" />
      </div>
    </Card>
  );
}
