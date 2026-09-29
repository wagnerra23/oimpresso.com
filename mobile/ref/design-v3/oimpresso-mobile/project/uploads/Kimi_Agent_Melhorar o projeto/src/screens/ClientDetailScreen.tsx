import { motion } from 'framer-motion';
import { ArrowLeft, Mail, Phone, MapPin, FileText, ShoppingCart, TrendingUp } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { getClientById, ORDERS } from '@/data/mockData';
import { Card } from '@/components/shared/Card';
import { Badge } from '@/components/shared/Badge';

export function ClientDetailScreen() {
  const { state, goBack } = useAppState();
  const client = getClientById(state.selectedId || '');
  const clientOrders = ORDERS.filter(o => o.clientId === client?.id);

  if (!client) {
    return (
      <div className="flex items-center justify-center h-full">
        <p className="text-[var(--text-secondary)]">Cliente não encontrado</p>
      </div>
    );
  }

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1 truncate">Ficha do Cliente</h1>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        {/* Header */}
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 rounded-full flex items-center justify-center text-xl font-bold text-white" style={{ background: 'linear-gradient(135deg, var(--primary), #5E5CE6)' }}>
            {client.avatar}
          </div>
          <div>
            <h2 className="text-xl font-bold text-[var(--text-primary)]">{client.name}</h2>
            <Badge variant={client.status === 'active' ? 'completed' : 'neutral'} shape="pill">
              {client.status === 'active' ? 'Ativo' : 'Inativo'}
            </Badge>
          </div>
        </div>

        {/* Contact Info */}
        <Card className="space-y-3">
          <div className="flex items-center gap-3">
            <Mail size={16} className="text-[var(--primary)]" />
            <span className="text-sm text-[var(--text-primary)]">{client.email}</span>
          </div>
          <div className="flex items-center gap-3">
            <Phone size={16} className="text-[var(--primary)]" />
            <span className="text-sm text-[var(--text-primary)]">{client.phone}</span>
          </div>
          <div className="flex items-center gap-3">
            <MapPin size={16} className="text-[var(--primary)]" />
            <span className="text-sm text-[var(--text-primary)]">{client.address}, {client.city}</span>
          </div>
          <div className="flex items-center gap-3">
            <FileText size={16} className="text-[var(--primary)]" />
            <span className="text-sm text-[var(--text-primary)]">{client.document}</span>
          </div>
        </Card>

        {/* Stats */}
        <div className="grid grid-cols-2 gap-3">
          <Card className="flex flex-col items-center text-center py-4">
            <ShoppingCart size={20} className="text-[var(--primary)] mb-1" />
            <p className="text-xl font-bold text-[var(--text-primary)]">{client.ordersCount}</p>
            <p className="text-xs text-[var(--text-secondary)]">pedidos</p>
          </Card>
          <Card className="flex flex-col items-center text-center py-4">
            <TrendingUp size={20} className="text-[#30D158] mb-1" />
            <p className="text-xl font-bold text-[var(--text-primary)]">R$ {(client.totalSpent / 100).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 })}k</p>
            <p className="text-xs text-[var(--text-secondary)]">total gasto</p>
          </Card>
        </div>

        {/* Order History */}
        <div>
          <h3 className="text-sm font-semibold text-[var(--text-primary)] mb-3">Histórico de Pedidos</h3>
          <div className="space-y-2">
            {clientOrders.map(order => (
              <Card key={order.id} padding="default">
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-sm font-medium text-[var(--text-primary)]">{order.number}</p>
                    <p className="text-xs text-[var(--text-secondary)]">{order.createdAt}</p>
                  </div>
                  <p className="text-sm font-bold text-[var(--primary)]">
                    R$ {(order.total / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                  </p>
                </div>
              </Card>
            ))}
          </div>
        </div>
      </div>
    </motion.div>
  );
}
