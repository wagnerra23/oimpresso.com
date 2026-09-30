import { useState } from 'react';
import { motion } from 'framer-motion';
import {
  Factory, Users, Package, ChevronRight
} from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { PRODUCTION_ORDERS } from '@/data/mockData';
import type { ProductionOrder } from '@/data/mockData';
import type { ScreenName } from '@/contexts/AppStateContext';
import { Card } from '@/components/shared/Card';
import { Badge } from '@/components/shared/Badge';
import { CircularProgress } from '@/components/shared/CircularProgress';

const container = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.06 } } };
const itemAnim = { hidden: { y: 10, opacity: 0 }, show: { y: 0, opacity: 1 } };

type FilterType = 'all' | 'active' | 'completed' | 'urgent';

export function ProductionScreen() {
  const { navigate } = useAppState();
  const [filter, setFilter] = useState<FilterType>('all');

  const all = PRODUCTION_ORDERS;
  const active = all.filter(p => p.status !== 'completed' && p.status !== 'pending');
  const completed = all.filter(p => p.status === 'completed');
  const urgent = all.filter(p => p.priority === 'urgent' || p.priority === 'high');

  let filtered = all;
  if (filter === 'active') filtered = active;
  if (filter === 'completed') filtered = completed;
  if (filter === 'urgent') filtered = urgent;

  const avgProgress = Math.round(all.reduce((sum, p) => sum + p.progress, 0) / all.length);
  const totalUnits = all.reduce((sum, p) => sum + p.quantity, 0);

  return (
    <div className="h-full flex flex-col">
      <div className="p-[var(--content-padding)] pb-2 space-y-4">
        <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">Produção</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-0.5">{active.length} ordens ativas</p>
        </motion.div>

        {/* Metrics Row */}
        <motion.div className="grid grid-cols-3 gap-3" variants={container} initial="hidden" animate="show">
          <motion.div variants={itemAnim}>
            <Card className="flex flex-col items-center text-center">
              <CircularProgress progress={avgProgress} size={56} strokeWidth={5} />
              <p className="text-xs text-[var(--text-secondary)] mt-2">Média Geral</p>
            </Card>
          </motion.div>
          <motion.div variants={itemAnim}>
            <Card className="flex flex-col items-center text-center py-5">
              <Package size={24} className="text-[var(--primary)] mb-1" />
              <p className="text-xl font-bold text-[var(--text-primary)]">{totalUnits.toLocaleString()}</p>
              <p className="text-xs text-[var(--text-secondary)]">unidades</p>
            </Card>
          </motion.div>
          <motion.div variants={itemAnim}>
            <Card className="flex flex-col items-center text-center py-5">
              <Users size={24} className="text-[#30D158] mb-1" />
              <p className="text-xl font-bold text-[var(--text-primary)]">{new Set(all.map(p => p.operator)).size}</p>
              <p className="text-xs text-[var(--text-secondary)]">operadores</p>
            </Card>
          </motion.div>
        </motion.div>

        {/* Status Summary */}
        <motion.div variants={itemAnim} className="flex gap-2 overflow-x-auto pb-1">
          {[
            { key: 'all' as const, label: 'Todas', count: all.length },
            { key: 'active' as const, label: 'Ativas', count: active.length },
            { key: 'urgent' as const, label: 'Urgentes', count: urgent.length },
            { key: 'completed' as const, label: 'Concluídas', count: completed.length },
          ].map(f => (
            <button
              key={f.key}
              onClick={() => setFilter(f.key)}
              className="flex-shrink-0 px-4 py-2 rounded-xl border text-xs font-medium transition-all"
              style={{
                background: filter === f.key ? 'var(--primary-subtle)' : 'var(--bg-surface)',
                borderColor: filter === f.key ? 'var(--primary)' : 'var(--border-color)',
                color: filter === f.key ? 'var(--primary)' : 'var(--text-secondary)',
              }}
            >
              {f.label} ({f.count})
            </button>
          ))}
        </motion.div>
      </div>

      {/* Production Orders List */}
      <motion.div className="flex-1 overflow-y-auto p-[var(--content-padding)] pt-0 space-y-3" variants={container} initial="hidden" animate="show">
        {filtered.map(po => (
          <motion.div key={po.id} variants={itemAnim}>
            <ProductionCard po={po} navigate={navigate} />
          </motion.div>
        ))}

        {filtered.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Factory size={48} className="text-[var(--text-tertiary)] mb-4" />
            <p className="text-[var(--text-secondary)] font-medium">Nenhuma ordem encontrada</p>
          </div>
        )}
      </motion.div>
    </div>
  );
}

function ProductionCard({ po, navigate }: { po: ProductionOrder; navigate: (s: ScreenName, id?: string) => void }) {

  const statusConfig: Record<string, { color: string; bg: string; label: string }> = {
    pending: { color: '#FF9F0A', bg: '#FF9F0A18', label: 'Pendente' },
    in_preparation: { color: '#5E5CE6', bg: '#5E5CE618', label: 'Preparação' },
    in_printing: { color: '#0A84FF', bg: '#0A84FF18', label: 'Impressão' },
    in_finishing: { color: '#BF5AF2', bg: '#BF5AF218', label: 'Acabamento' },
    quality_check: { color: '#64D2FF', bg: '#64D2FF18', label: 'Qualidade' },
    completed: { color: '#30D158', bg: '#30D15818', label: 'Concluído' },
  };

  const config = statusConfig[po.status];

  const stages = [
    { name: 'Prep', completed: po.stages[0].status === 'completed', active: po.stages[0].status === 'in_progress' },
    { name: 'Imp', completed: po.stages[1].status === 'completed', active: po.stages[1].status === 'in_progress' },
    { name: 'Acab', completed: po.stages[2].status === 'completed', active: po.stages[2].status === 'in_progress' },
    { name: 'QC', completed: po.stages[3].status === 'completed', active: po.stages[3].status === 'in_progress' },
    { name: 'Exp', completed: po.stages[4].status === 'completed', active: po.stages[4].status === 'in_progress' },
  ];

  return (
    <Card pressable onClick={() => navigate('productionDetail', po.id)} padding="default">
      <div className="flex items-start justify-between mb-2">
        <div className="flex items-center gap-2">
          <span className="text-xs font-mono font-semibold text-[var(--text-secondary)]">{po.number}</span>
          <Badge variant={po.priority === 'urgent' ? 'urgent' : po.priority === 'high' ? 'high' : 'medium'}>
            {po.priority === 'urgent' ? 'Urgente' : po.priority === 'high' ? 'Alta' : 'Média'}
          </Badge>
        </div>
        <div className="flex items-center gap-1.5 px-2 py-0.5 rounded-full" style={{ background: config.bg }}>
          <span className="text-xs font-medium" style={{ color: config.color }}>{config.label}</span>
        </div>
      </div>

      <p className="text-sm font-semibold text-[var(--text-primary)] truncate">{po.productName}</p>
      <p className="text-xs text-[var(--text-secondary)]">{po.clientName} · {po.quantity.toLocaleString()} un</p>

      {/* Stage Pipeline */}
      <div className="flex items-center gap-1 mt-3 mb-2">
        {stages.map((stage, i) => (
          <div key={i} className="flex-1 flex flex-col items-center gap-1">
            <div
              className="w-full h-2 rounded-full transition-all"
              style={{
                background: stage.completed ? '#30D158' : stage.active ? 'var(--primary)' : 'var(--progress-track)',
              }}
            />
            <span className="text-[9px] font-medium" style={{
              color: stage.completed ? '#30D158' : stage.active ? 'var(--primary)' : 'var(--text-tertiary)',
            }}>
              {stage.name}
            </span>
          </div>
        ))}
      </div>

      <div className="flex items-center justify-between mt-2">
        <div className="flex items-center gap-2">
          <div className="w-6 h-6 rounded-full bg-gradient-to-br from-[var(--primary)] to-[#5E5CE6] flex items-center justify-center text-[10px] font-bold text-white">
            {po.operatorAvatar}
          </div>
          <span className="text-xs text-[var(--text-secondary)]">{po.operator}</span>
        </div>
        <div className="flex items-center gap-2">
          <CircularProgress progress={po.progress} size={32} strokeWidth={3} showText />
          <ChevronRight size={16} className="text-[var(--text-tertiary)]" />
        </div>
      </div>
    </Card>
  );
}
