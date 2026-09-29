import { motion } from 'framer-motion';
import {
  ArrowLeft, Clock, Package, Truck, CheckCircle, Play, XCircle,
  FileText
} from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { getOrderById } from '@/data/mockData';

import { Card } from '@/components/shared/Card';
import { ProgressBar } from '@/components/shared/ProgressBar';
import { Button } from '@/components/shared/Button';

export function OrderDetailScreen() {
  const { state, goBack } = useAppState();
  const order = getOrderById(state.selectedId || '');

  if (!order) {
    return (
      <div className="flex items-center justify-center h-full">
        <p className="text-[var(--text-secondary)]">Pedido não encontrado</p>
      </div>
    );
  }

  const statusConfig: Record<string, { icon: typeof CheckCircle; color: string; label: string }> = {
    pending: { icon: Clock, color: '#FF9F0A', label: 'Pendente' },
    approved: { icon: CheckCircle, color: '#30D158', label: 'Aprovado' },
    in_production: { icon: Play, color: '#0A84FF', label: 'Em Produção' },
    ready: { icon: Package, color: '#5E5CE6', label: 'Pronto' },
    delivered: { icon: Truck, color: '#30D158', label: 'Entregue' },
    cancelled: { icon: XCircle, color: '#FF453A', label: 'Cancelado' },
  };

  const config = statusConfig[order.status];
  const StatusIcon = config.icon;
  const progressMap: Record<string, number> = {
    pending: 10, approved: 25, in_production: 50, ready: 85, delivered: 100, cancelled: 0,
  };

  const timeline = [
    { label: 'Pedido Recebido', date: order.createdAt, completed: true, active: false },
    { label: 'Aprovação', date: order.status !== 'pending' ? order.createdAt : '', completed: order.status !== 'pending', active: order.status === 'pending' },
    { label: 'Produção', date: ['in_production', 'ready', 'delivered'].includes(order.status) ? order.createdAt : '', completed: ['ready', 'delivered'].includes(order.status), active: order.status === 'in_production' },
    { label: 'Pronto', date: ['ready', 'delivered'].includes(order.status) ? order.deliveryDate : '', completed: order.status === 'delivered', active: order.status === 'ready' },
    { label: 'Entregue', date: order.status === 'delivered' ? order.deliveryDate : '', completed: order.status === 'delivered', active: false },
  ];

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1 truncate">{order.number}</h1>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        {/* Status Card */}
        <Card className="flex items-center gap-4">
          <div className="w-14 h-14 rounded-2xl flex items-center justify-center" style={{ background: `${config.color}20` }}>
            <StatusIcon size={28} style={{ color: config.color }} />
          </div>
          <div className="flex-1">
            <p className="text-lg font-semibold text-[var(--text-primary)]">{config.label}</p>
            <ProgressBar progress={progressMap[order.status]} color={config.color} />
          </div>
        </Card>

        {/* Client Info */}
        <Card>
          <h3 className="text-sm font-semibold text-[var(--text-primary)] mb-3">Cliente</h3>
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-full flex items-center justify-center text-base font-bold text-white" style={{ background: 'linear-gradient(135deg, var(--primary), #5E5CE6)' }}>
              {order.clientAvatar}
            </div>
            <div>
              <p className="text-base font-medium text-[var(--text-primary)]">{order.clientName}</p>
              <p className="text-xs text-[var(--text-secondary)] flex items-center gap-1 mt-0.5">
                <Clock size={12} /> Entrega: {order.deliveryDate}
              </p>
            </div>
          </div>
        </Card>

        {/* Items */}
        <Card>
          <h3 className="text-sm font-semibold text-[var(--text-primary)] mb-3">Itens do Pedido</h3>
          <div className="space-y-3">
            {order.items.map(item => (
              <div key={item.id} className="flex items-center justify-between py-2 border-b last:border-0" style={{ borderColor: 'var(--border-color)' }}>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-[var(--text-primary)] truncate">{item.productName}</p>
                  <p className="text-xs text-[var(--text-tertiary)]">{item.quantity}x R$ {(item.unitPrice / 100).toFixed(2)}</p>
                </div>
                <p className="text-sm font-semibold text-[var(--text-primary)] ml-3">
                  R$ {(item.total / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                </p>
              </div>
            ))}
          </div>
          <div className="flex items-center justify-between mt-3 pt-3 border-t" style={{ borderColor: 'var(--border-color)' }}>
            <span className="text-sm font-semibold text-[var(--text-primary)]">Total</span>
            <span className="text-lg font-bold text-[var(--primary)]">
              R$ {(order.total / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
            </span>
          </div>
        </Card>

        {/* Timeline */}
        <Card>
          <h3 className="text-sm font-semibold text-[var(--text-primary)] mb-4">Timeline</h3>
          <div className="space-y-0">
            {timeline.map((step, i) => (
              <div key={i} className="flex gap-3">
                <div className="flex flex-col items-center">
                  <div
                    className="w-6 h-6 rounded-full flex items-center justify-center"
                    style={{
                      background: step.completed ? '#30D158' : step.active ? 'var(--primary)' : 'var(--progress-track)',
                    }}
                  >
                    {step.completed ? <CheckCircle size={14} className="text-white" /> : step.active ? <Play size={12} className="text-white ml-0.5" /> : <div className="w-2 h-2 rounded-full bg-[var(--text-tertiary)]" />}
                  </div>
                  {i < timeline.length - 1 && (
                    <div className="w-0.5 flex-1 my-1" style={{ background: step.completed ? '#30D158' : 'var(--progress-track)' }} />
                  )}
                </div>
                <div className="pb-5">
                  <p className={`text-sm font-medium ${step.completed || step.active ? 'text-[var(--text-primary)]' : 'text-[var(--text-tertiary)]'}`}>{step.label}</p>
                  {step.date && <p className="text-xs text-[var(--text-tertiary)]">{step.date}</p>}
                </div>
              </div>
            ))}
          </div>
        </Card>

        {order.notes && (
          <Card>
            <div className="flex items-center gap-2 mb-2">
              <FileText size={16} className="text-[var(--text-secondary)]" />
              <h3 className="text-sm font-semibold text-[var(--text-primary)]">Observações</h3>
            </div>
            <p className="text-sm text-[var(--text-secondary)]">{order.notes}</p>
          </Card>
        )}

        <div className="flex gap-3 pt-2">
          {order.status === 'pending' && (
            <Button fullWidth variant="primary">Aprovar Pedido</Button>
          )}
          {order.status === 'approved' && (
            <Button fullWidth variant="primary" icon={<Play size={18} />}>Iniciar Produção</Button>
          )}
          {order.status === 'in_production' && (
            <Button fullWidth variant="secondary">Marcar como Pronto</Button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
