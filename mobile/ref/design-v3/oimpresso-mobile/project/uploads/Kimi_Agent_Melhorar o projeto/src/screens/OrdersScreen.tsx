import { useState } from 'react';
import { motion } from 'framer-motion';
import {
  ShoppingCart, Filter, Clock, Package,
  CheckCircle, XCircle, Play, Truck
} from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { ORDERS } from '@/data/mockData';
import type { Order } from '@/data/mockData';
import type { ScreenName } from '@/contexts/AppStateContext';
import { Card } from '@/components/shared/Card';
import { Badge } from '@/components/shared/Badge';
import { SegmentedControl } from '@/components/shared/SegmentedControl';
import { SearchBar } from '@/components/shared/SearchBar';
import { ProgressBar } from '@/components/shared/ProgressBar';
import { FloatingActionButton } from '@/components/shared/FloatingActionButton';

const container = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.05 } } };
const itemAnim = { hidden: { y: 10, opacity: 0 }, show: { y: 0, opacity: 1 } };

type StatusFilter = 'all' | Order['status'];

export function OrdersScreen() {
  const { navigate } = useAppState();
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('all');
  const [showFilters, setShowFilters] = useState(false);

  let orders = [...ORDERS];
  if (search) {
    orders = orders.filter(o => o.number.toLowerCase().includes(search.toLowerCase()) || o.clientName.toLowerCase().includes(search.toLowerCase()));
  }
  if (statusFilter !== 'all') {
    orders = orders.filter(o => o.status === statusFilter);
  }

  const statusCounts = {
    pending: ORDERS.filter(o => o.status === 'pending').length,
    approved: ORDERS.filter(o => o.status === 'approved').length,
    in_production: ORDERS.filter(o => o.status === 'in_production').length,
    ready: ORDERS.filter(o => o.status === 'ready').length,
    delivered: ORDERS.filter(o => o.status === 'delivered').length,
  };

  return (
    <div className="h-full flex flex-col">
      <div className="p-[var(--content-padding)] pb-2 space-y-3">
        <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-[var(--text-primary)]">Pedidos</h1>
              <p className="text-sm text-[var(--text-secondary)] mt-0.5">{orders.length} pedidos</p>
            </div>
            <div className="text-right">
              <p className="text-lg font-bold text-[var(--text-primary)]">
                R$ {(orders.reduce((sum, o) => sum + o.total, 0) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
              </p>
              <p className="text-xs text-[var(--text-secondary)]">valor total</p>
            </div>
          </div>
        </motion.div>

        <SearchBar value={search} onChange={setSearch} placeholder="Buscar por número ou cliente..." />

        <div className="flex items-center gap-2">
          <SegmentedControl
            value={statusFilter}
            onChange={v => setStatusFilter(v as StatusFilter)}
            options={[
              { value: 'all', label: `Todos (${ORDERS.length})` },
              { value: 'pending', label: `Pendentes (${statusCounts.pending})` },
              { value: 'in_production', label: `Produção (${statusCounts.in_production})` },
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
            <p className="text-xs font-medium text-[var(--text-secondary)] mb-2">Status</p>
            <div className="flex gap-2 flex-wrap">
              {[
                { value: 'approved', label: 'Aprovados', count: statusCounts.approved },
                { value: 'ready', label: 'Prontos', count: statusCounts.ready },
                { value: 'delivered', label: 'Entregues', count: statusCounts.delivered },
              ].map(s => (
                <button
                  key={s.value}
                  onClick={() => setStatusFilter(statusFilter === s.value ? 'all' : s.value as StatusFilter)}
                  className="px-3 py-1 rounded-full text-xs font-medium border transition-all"
                  style={{
                    background: statusFilter === s.value ? 'var(--primary-subtle)' : 'var(--bg-elevated)',
                    borderColor: statusFilter === s.value ? 'var(--primary)' : 'var(--border-color)',
                    color: statusFilter === s.value ? 'var(--primary)' : 'var(--text-secondary)',
                  }}
                >
                  {s.label} ({s.count})
                </button>
              ))}
            </div>
          </motion.div>
        )}
      </div>

      <motion.div className="flex-1 overflow-y-auto p-[var(--content-padding)] pt-0 space-y-3" variants={container} initial="hidden" animate="show">
        {orders.map(order => (
          <motion.div key={order.id} variants={itemAnim}>
            <OrderCard order={order} navigate={navigate} />
          </motion.div>
        ))}

        {orders.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <ShoppingCart size={48} className="text-[var(--text-tertiary)] mb-4" />
            <p className="text-[var(--text-secondary)] font-medium">Nenhum pedido encontrado</p>
            <p className="text-sm text-[var(--text-tertiary)] mt-1">Ajuste os filtros ou crie um novo pedido</p>
          </div>
        )}
      </motion.div>

      <FloatingActionButton onClick={() => navigate('newOrder')} className="absolute bottom-24 right-4" />
    </div>
  );
}

function OrderCard({ order, navigate }: { order: Order; navigate: (s: ScreenName, id?: string) => void }) {

  const statusConfig: Record<string, { icon: typeof CheckCircle; color: string; bg: string; label: string }> = {
    pending: { icon: Clock, color: '#FF9F0A', bg: '#FF9F0A18', label: 'Pendente' },
    approved: { icon: CheckCircle, color: '#30D158', bg: '#30D15818', label: 'Aprovado' },
    in_production: { icon: Play, color: '#0A84FF', bg: '#0A84FF18', label: 'Em Produção' },
    ready: { icon: Package, color: '#5E5CE6', bg: '#5E5CE618', label: 'Pronto' },
    delivered: { icon: Truck, color: '#30D158', bg: '#30D15818', label: 'Entregue' },
    cancelled: { icon: XCircle, color: '#FF453A', bg: '#FF453A18', label: 'Cancelado' },
  };

  const config = statusConfig[order.status];
  const StatusIcon = config.icon;

  const progressMap: Record<string, number> = {
    pending: 10, approved: 25, in_production: 50, ready: 85, delivered: 100, cancelled: 0,
  };

  return (
    <Card pressable onClick={() => navigate('orderDetail', order.id)} padding="default">
      <div className="flex items-start justify-between mb-2">
        <div className="flex items-center gap-2">
          <span className="text-xs font-mono font-semibold text-[var(--text-secondary)]">{order.number}</span>
          {order.priority === 'urgent' && <Badge variant="urgent" pulse>Urgente</Badge>}
          {order.priority === 'high' && <Badge variant="high">Alta</Badge>}
        </div>
        <div className="flex items-center gap-1.5 px-2 py-0.5 rounded-full" style={{ background: config.bg }}>
          <StatusIcon size={12} style={{ color: config.color }} />
          <span className="text-xs font-medium" style={{ color: config.color }}>{config.label}</span>
        </div>
      </div>

      <div className="flex items-center gap-3 mb-3">
        <div className="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0"
          style={{ background: `linear-gradient(135deg, var(--primary), #5E5CE6)` }}>
          {order.clientAvatar}
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-medium text-[var(--text-primary)] truncate">{order.clientName}</p>
          <p className="text-xs text-[var(--text-tertiary)]">{order.items.length} item(s)</p>
        </div>
        <div className="text-right flex-shrink-0">
          <p className="text-sm font-bold text-[var(--text-primary)]">R$ {(order.total / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</p>
        </div>
      </div>

      <div className="flex items-center gap-2 text-xs text-[var(--text-tertiary)] mb-2">
        <Clock size={12} />
        <span>Entrega: {order.deliveryDate}</span>
        <span className="mx-1">·</span>
        <span>{order.createdAt}</span>
      </div>

      <ProgressBar progress={progressMap[order.status]} size="sm" color={config.color} />
    </Card>
  );
}
