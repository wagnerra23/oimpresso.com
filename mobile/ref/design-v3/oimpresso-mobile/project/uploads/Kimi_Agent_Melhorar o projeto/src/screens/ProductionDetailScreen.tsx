import { motion } from 'framer-motion';
import {
  ArrowLeft, Factory, Clock, User, Package,
  CheckCircle, Play, ChevronRight
} from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { getProductionOrderById } from '@/data/mockData';
import { Card } from '@/components/shared/Card';
import { Badge } from '@/components/shared/Badge';
import { CircularProgress } from '@/components/shared/CircularProgress';
import { ProgressBar } from '@/components/shared/ProgressBar';
import { Button } from '@/components/shared/Button';

export function ProductionDetailScreen() {
  const { state, goBack } = useAppState();
  const po = getProductionOrderById(state.selectedId || '');

  if (!po) {
    return (
      <div className="flex items-center justify-center h-full">
        <p className="text-[var(--text-secondary)]">Ordem não encontrada</p>
      </div>
    );
  }

  const statusConfig: Record<string, { color: string; label: string }> = {
    pending: { color: '#FF9F0A', label: 'Pendente' },
    in_preparation: { color: '#5E5CE6', label: 'Preparação' },
    in_printing: { color: '#0A84FF', label: 'Impressão' },
    in_finishing: { color: '#BF5AF2', label: 'Acabamento' },
    quality_check: { color: '#64D2FF', label: 'Controle de Qualidade' },
    completed: { color: '#30D158', label: 'Concluído' },
  };

  const config = statusConfig[po.status];

  const stagesWithIcons = [
    { ...po.stages[0], icon: Package, label: 'Preparação' },
    { ...po.stages[1], icon: Factory, label: 'Impressão' },
    { ...po.stages[2], icon: Play, label: 'Acabamento' },
    { ...po.stages[3], icon: CheckCircle, label: 'Qualidade' },
    { ...po.stages[4], icon: ChevronRight, label: 'Expedição' },
  ];

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1 truncate">{po.number}</h1>
        <Badge variant={po.priority === 'urgent' ? 'urgent' : po.priority === 'high' ? 'high' : 'medium'}>
          {po.priority}
        </Badge>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        {/* Progress Header */}
        <Card className="flex items-center gap-4">
          <CircularProgress progress={po.progress} size={64} strokeWidth={5} color={config.color} />
          <div className="flex-1">
            <p className="text-sm text-[var(--text-secondary)]">{po.productName}</p>
            <p className="text-lg font-bold text-[var(--text-primary)]">{po.progress}%</p>
            <ProgressBar progress={po.progress} color={config.color} />
          </div>
        </Card>

        {/* Info */}
        <Card>
          <div className="space-y-3">
            <div className="flex items-center gap-3">
              <User size={16} className="text-[var(--primary)]" />
              <span className="text-sm text-[var(--text-primary)]">{po.clientName}</span>
            </div>
            <div className="flex items-center gap-3">
              <Package size={16} className="text-[var(--primary)]" />
              <span className="text-sm text-[var(--text-primary)]">{po.quantity.toLocaleString()} unidades</span>
            </div>
            <div className="flex items-center gap-3">
              <Clock size={16} className="text-[var(--primary)]" />
              <span className="text-sm text-[var(--text-primary)]">Início: {po.startDate}</span>
            </div>
            <div className="flex items-center gap-3">
              <Clock size={16} className="text-[var(--warning)]" />
              <span className="text-sm text-[var(--text-primary)]">Previsão: {po.estimatedEnd}</span>
            </div>
          </div>
        </Card>

        {/* Operator */}
        <Card className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-gradient-to-br from-[var(--primary)] to-[#5E5CE6] flex items-center justify-center text-sm font-bold text-white">
            {po.operatorAvatar}
          </div>
          <div>
            <p className="text-sm font-medium text-[var(--text-primary)]">{po.operator}</p>
            <p className="text-xs text-[var(--text-secondary)]">Operador responsável</p>
          </div>
        </Card>

        {/* Stages */}
        <div>
          <h3 className="text-sm font-semibold text-[var(--text-primary)] mb-3">Fluxo de Produção</h3>
          <div className="space-y-2">
            {stagesWithIcons.map((stage, i) => {
              const StageIcon = stage.icon;
              const isCompleted = stage.status === 'completed';
              const isActive = stage.status === 'in_progress';

              return (
                <motion.div
                  key={stage.id}
                  initial={{ opacity: 0, x: -10 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: i * 0.1 }}
                >
                  <Card
                    className="flex items-center gap-3"
                    padding="default"
                    style={isActive ? { borderColor: 'var(--primary)' } : undefined}
                  >
                    <div
                      className="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                      style={{
                        background: isCompleted ? '#30D15820' : isActive ? 'var(--primary-subtle)' : 'var(--bg-elevated)',
                      }}
                    >
                      {isCompleted ? <CheckCircle size={20} className="text-[#30D158]" /> :
                       isActive ? <Play size={18} className="text-[var(--primary)]" /> :
                       <StageIcon size={18} className="text-[var(--text-tertiary)]" />}
                    </div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center gap-2">
                        <p className={`text-sm font-medium ${isCompleted || isActive ? 'text-[var(--text-primary)]' : 'text-[var(--text-tertiary)]'}`}>
                          {stage.label}
                        </p>
                        {isActive && <Badge variant="neutral" shape="pill">Em andamento</Badge>}
                        {isCompleted && <Badge variant="completed" shape="pill">Concluído</Badge>}
                      </div>
                      {stage.operator && (
                        <p className="text-xs text-[var(--text-secondary)] mt-0.5">{stage.operator}</p>
                      )}
                      {stage.notes && (
                        <p className="text-xs text-[var(--text-tertiary)] mt-0.5">{stage.notes}</p>
                      )}
                      {(stage.startDate || stage.endDate) && (
                        <p className="text-xs text-[var(--text-tertiary)] mt-0.5">
                          {stage.startDate && `Início: ${stage.startDate}`}
                          {stage.endDate && ` · Fim: ${stage.endDate}`}
                        </p>
                      )}
                    </div>
                    <div className="w-2 h-2 rounded-full flex-shrink-0" style={{
                      background: isCompleted ? '#30D158' : isActive ? 'var(--primary)' : 'var(--progress-track)',
                    }} />
                  </Card>
                </motion.div>
              );
            })}
          </div>
        </div>

        {/* Actions */}
        <div className="flex gap-3 pt-2">
          {po.status !== 'completed' && (
            <Button fullWidth variant="primary">
              Avançar Etapa
            </Button>
          )}
          {po.status === 'pending' && (
            <Button fullWidth variant="secondary">Iniciar Preparação</Button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
