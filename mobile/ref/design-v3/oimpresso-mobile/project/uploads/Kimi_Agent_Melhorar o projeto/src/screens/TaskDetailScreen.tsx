import { motion } from 'framer-motion';
import { ArrowLeft, Clock, User, Tag, AlertTriangle, CheckCircle, Circle } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { getTaskById } from '@/data/mockData';
import { Card } from '@/components/shared/Card';
import { Badge } from '@/components/shared/Badge';
import { Button } from '@/components/shared/Button';

export function TaskDetailScreen() {
  const { state, goBack } = useAppState();
  const task = getTaskById(state.selectedId || '');

  if (!task) {
    return (
      <div className="flex items-center justify-center h-full">
        <p className="text-[var(--text-secondary)]">Tarefa não encontrada</p>
      </div>
    );
  }

  const priorityBadge = {
    urgent: 'urgent' as const,
    high: 'high' as const,
    medium: 'medium' as const,
    low: 'low' as const,
  };

  const completedSubtasks = task.subtasks.filter(st => st.completed).length;

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      {/* Header */}
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1 truncate">Detalhe da Tarefa</h1>
        <Badge variant={priorityBadge[task.priority]}>{task.priority.toUpperCase()}</Badge>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        <div>
          <h2 className="text-xl font-bold text-[var(--text-primary)]">{task.title}</h2>
          <p className="text-sm text-[var(--text-secondary)] mt-2 leading-relaxed">{task.description}</p>
        </div>

        <div className="flex flex-wrap gap-3">
          <div className="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
            <Clock size={16} className="text-[var(--primary)]" />
            <span>Prazo: {task.dueDate}</span>
          </div>
          <div className="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
            <User size={16} className="text-[var(--primary)]" />
            <span>{task.assignee}</span>
          </div>
          <div className="flex items-center gap-2 text-sm text-[var(--text-secondary)]">
            <Tag size={16} className="text-[var(--primary)]" />
            <span>{task.category}</span>
          </div>
        </div>

        {/* Subtasks */}
        <Card>
          <div className="flex items-center justify-between mb-3">
            <h3 className="text-sm font-semibold text-[var(--text-primary)]">Subtarefas</h3>
            <span className="text-xs font-mono text-[var(--text-secondary)]">{completedSubtasks}/{task.subtasks.length}</span>
          </div>
          <div className="space-y-2">
            {task.subtasks.map(st => (
              <div key={st.id} className="flex items-center gap-3 py-2">
                {st.completed ? (
                  <CheckCircle size={20} className="text-[#30D158] flex-shrink-0" />
                ) : (
                  <Circle size={20} className="text-[var(--text-tertiary)] flex-shrink-0" />
                )}
                <span className={`text-sm ${st.completed ? 'line-through text-[var(--text-tertiary)]' : 'text-[var(--text-primary)]'}`}>
                  {st.title}
                </span>
              </div>
            ))}
          </div>
          <div className="mt-3 h-1.5 rounded-full overflow-hidden" style={{ background: 'var(--progress-track)' }}>
            <motion.div
              className="h-full rounded-full bg-[#30D158]"
              initial={{ width: 0 }}
              animate={{ width: `${task.subtasks.length > 0 ? (completedSubtasks / task.subtasks.length) * 100 : 0}%` }}
              transition={{ duration: 0.6, ease: 'easeOut' }}
            />
          </div>
        </Card>

        {/* Actions */}
        <div className="flex gap-3 pt-2">
          {task.status !== 'completed' && (
            <Button fullWidth variant="primary" icon={<CheckCircle size={18} />}>
              Marcar Concluída
            </Button>
          )}
          {task.status === 'blocked' && (
            <Button fullWidth variant="secondary" icon={<AlertTriangle size={18} />}>
              Desbloquear
            </Button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
