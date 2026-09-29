import { motion } from 'framer-motion';
import {
  ClipboardList, ShoppingCart, Factory, Users, Package, AlertTriangle,
  TrendingUp, Clock, CheckCircle, ChevronRight, ArrowUpRight
} from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { useTweaks } from '@/contexts/TweaksContext';
import { TASKS, ORDERS, PRODUCTION_ORDERS, getTenantById } from '@/data/mockData';
import { Card } from '@/components/shared/Card';
import { ProgressBar } from '@/components/shared/ProgressBar';
import { Badge } from '@/components/shared/Badge';

const container = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.06 } } };
const item = { hidden: { y: 12, opacity: 0 }, show: { y: 0, opacity: 1 } };

export function HomeScreen() {
  const { navigate, setNavTab } = useAppState();
  const { tweaks } = useTweaks();
  const tenant = getTenantById(tweaks.tenant);

  const urgentTasks = TASKS.filter(t => t.priority === 'urgent' && t.status !== 'completed');
  const pendingOrders = ORDERS.filter(o => o.status === 'pending');
  const activeProduction = PRODUCTION_ORDERS.filter(p => p.status !== 'completed' && p.status !== 'pending');
  const completedToday = TASKS.filter(t => t.status === 'completed').length;

  const kpis = [
    { label: 'Tarefas Urgentes', value: urgentTasks.length, icon: AlertTriangle, color: '#FF453A', onClick: () => setNavTab('tasks') },
    { label: 'Pedidos Pendentes', value: pendingOrders.length, icon: ShoppingCart, color: '#FF9F0A', onClick: () => setNavTab('orders') },
    { label: 'Em Produção', value: activeProduction.length, icon: Factory, color: '#0A84FF', onClick: () => setNavTab('production') },
    { label: 'Concluídas', value: completedToday, icon: CheckCircle, color: '#30D158', onClick: () => setNavTab('tasks') },
  ];

  const modules = [
    { label: 'Tarefas', icon: ClipboardList, color: '#0A84FF', tab: 'tasks' as const },
    { label: 'Pedidos', icon: ShoppingCart, color: '#FF9F0A', tab: 'orders' as const },
    { label: 'Produção', icon: Factory, color: '#30D158', tab: 'production' as const },
    { label: 'Clientes', icon: Users, color: '#5E5CE6', tab: 'clients' as const },
    { label: 'Produtos', icon: Package, color: '#FF453A', tab: 'products' as const },
    { label: 'Mais', icon: ArrowUpRight, color: '#8E8E93', tab: 'modules' as const },
  ];

  return (
    <motion.div className="p-[var(--content-padding)] space-y-[var(--section-gap)]" variants={container} initial="hidden" animate="show">
      {/* Header */}
      <motion.div variants={item} className="flex items-center justify-between">
        <div>
          <p className="text-xs text-[var(--text-secondary)] font-medium">{tenant?.name}</p>
          <h1 className="text-2xl font-bold text-[var(--text-primary)] mt-0.5">Dashboard</h1>
        </div>
        <div className="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style={{ background: tenant?.color || '#0A84FF' }}>
          {tenant?.initials}
        </div>
      </motion.div>

      {/* KPI Cards */}
      <motion.div variants={item} className="grid grid-cols-2 gap-3">
        {kpis.map(kpi => (
          <Card key={kpi.label} pressable onClick={kpi.onClick} padding="default">
            <div className="flex items-center gap-2 mb-2">
              <div className="w-8 h-8 rounded-lg flex items-center justify-center" style={{ background: `${kpi.color}20` }}>
                <kpi.icon size={16} style={{ color: kpi.color }} />
              </div>
              <TrendingUp size={14} style={{ color: kpi.color }} />
            </div>
            <p className="text-2xl font-bold text-[var(--text-primary)]">{kpi.value}</p>
            <p className="text-xs text-[var(--text-secondary)] mt-0.5">{kpi.label}</p>
          </Card>
        ))}
      </motion.div>

      {/* Quick Access Modules */}
      <motion.div variants={item}>
        <h2 className="text-lg font-semibold text-[var(--text-primary)] mb-3">Módulos</h2>
        <div className="grid grid-cols-3 gap-3">
          {modules.map(mod => (
            <motion.button
              key={mod.label}
              className="flex flex-col items-center gap-2 p-4 rounded-2xl border transition-colors"
              style={{ background: 'var(--bg-surface)', borderColor: 'var(--border-color)' }}
              onClick={() => setNavTab(mod.tab)}
              whileTap={{ scale: 0.95 }}
            >
              <div className="w-12 h-12 rounded-xl flex items-center justify-center" style={{ background: `${mod.color}18` }}>
                <mod.icon size={22} style={{ color: mod.color }} />
              </div>
              <span className="text-xs font-medium text-[var(--text-primary)]">{mod.label}</span>
            </motion.button>
          ))}
        </div>
      </motion.div>

      {/* Urgent Tasks */}
      <motion.div variants={item}>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold text-[var(--text-primary)]">Tarefas Urgentes</h2>
          <button onClick={() => setNavTab('tasks')} className="text-sm text-[var(--primary)] font-medium">Ver todas</button>
        </div>
        <div className="space-y-2">
          {urgentTasks.slice(0, 3).map(task => (
            <Card key={task.id} pressable onClick={() => navigate('taskDetail', task.id)} padding="default">
              <div className="flex items-start gap-3">
                <div className="w-2 h-2 rounded-full mt-2 flex-shrink-0" style={{ background: task.priority === 'urgent' ? '#FF453A' : '#FF9F0A' }} />
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-[var(--text-primary)] truncate">{task.title}</p>
                  <div className="flex items-center gap-2 mt-1">
                    <Badge variant={task.priority === 'urgent' ? 'urgent' : 'high'}>{task.priority === 'urgent' ? 'Urgente' : 'Alta'}</Badge>
                    <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
                      <Clock size={12} /> {task.dueDate}
                    </span>
                  </div>
                </div>
                <ChevronRight size={16} className="text-[var(--text-tertiary)] flex-shrink-0 mt-1" />
              </div>
            </Card>
          ))}
        </div>
      </motion.div>

      {/* Production Overview */}
      <motion.div variants={item}>
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-lg font-semibold text-[var(--text-primary)]">Produção Ativa</h2>
          <button onClick={() => setNavTab('production')} className="text-sm text-[var(--primary)] font-medium">Ver todas</button>
        </div>
        <div className="space-y-2">
          {activeProduction.slice(0, 2).map(po => (
            <Card key={po.id} pressable onClick={() => navigate('productionDetail', po.id)} padding="default">
              <div className="flex items-center justify-between mb-2">
                <span className="text-xs font-mono text-[var(--text-secondary)]">{po.number}</span>
                <Badge variant={po.priority === 'urgent' ? 'urgent' : po.priority === 'high' ? 'high' : 'medium'}>
                  {po.priority}
                </Badge>
              </div>
              <p className="text-sm font-medium text-[var(--text-primary)] truncate">{po.productName}</p>
              <p className="text-xs text-[var(--text-secondary)] mt-0.5">{po.clientName}</p>
              <div className="mt-3">
                <div className="flex items-center justify-between mb-1">
                  <span className="text-xs text-[var(--text-tertiary)]">Progresso</span>
                  <span className="text-xs font-mono font-semibold text-[var(--primary)]">{po.progress}%</span>
                </div>
                <ProgressBar progress={po.progress} />
              </div>
            </Card>
          ))}
        </div>
      </motion.div>
    </motion.div>
  );
}
