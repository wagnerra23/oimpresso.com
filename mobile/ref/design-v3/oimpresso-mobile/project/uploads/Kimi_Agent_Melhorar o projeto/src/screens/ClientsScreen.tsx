import { useState } from 'react';
import { motion } from 'framer-motion';
import { Users, ChevronRight, Phone, MapPin } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { CLIENTS } from '@/data/mockData';

import { Card } from '@/components/shared/Card';
import { SearchBar } from '@/components/shared/SearchBar';
import { FloatingActionButton } from '@/components/shared/FloatingActionButton';
import { Badge } from '@/components/shared/Badge';

const container = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.04 } } };
const itemAnim = { hidden: { y: 8, opacity: 0 }, show: { y: 0, opacity: 1 } };

export function ClientsScreen() {
  const { navigate } = useAppState();
  const [search, setSearch] = useState('');

  const clients = search
    ? CLIENTS.filter(c => c.name.toLowerCase().includes(search.toLowerCase()) || c.email.toLowerCase().includes(search.toLowerCase()))
    : CLIENTS;

  return (
    <div className="h-full flex flex-col">
      <div className="p-[var(--content-padding)] pb-2 space-y-3">
        <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">Clientes</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-0.5">{clients.length} clientes cadastrados</p>
        </motion.div>
        <SearchBar value={search} onChange={setSearch} placeholder="Buscar clientes..." />
      </div>

      <motion.div className="flex-1 overflow-y-auto p-[var(--content-padding)] pt-0 space-y-2" variants={container} initial="hidden" animate="show">
        {clients.map(client => (
          <motion.div key={client.id} variants={itemAnim}>
            <Card pressable onClick={() => navigate('clientDetail', client.id)} padding="default">
              <div className="flex items-center gap-3">
                <div className="w-11 h-11 rounded-full flex items-center justify-center text-sm font-bold text-white flex-shrink-0" style={{ background: 'linear-gradient(135deg, var(--primary), #5E5CE6)' }}>
                  {client.avatar}
                </div>
                <div className="flex-1 min-w-0">
                  <div className="flex items-center gap-2">
                    <p className="text-sm font-medium text-[var(--text-primary)] truncate">{client.name}</p>
                    <Badge variant={client.status === 'active' ? 'completed' : 'neutral'} shape="pill">
                      {client.status === 'active' ? 'Ativo' : 'Inativo'}
                    </Badge>
                  </div>
                  <p className="text-xs text-[var(--text-secondary)] truncate">{client.email}</p>
                  <div className="flex items-center gap-3 mt-1">
                    <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
                      <Phone size={10} /> {client.phone}
                    </span>
                    <span className="text-xs text-[var(--text-tertiary)] flex items-center gap-1">
                      <MapPin size={10} /> {client.city.split(',')[0]}
                    </span>
                  </div>
                </div>
                <ChevronRight size={16} className="text-[var(--text-tertiary)] flex-shrink-0" />
              </div>
            </Card>
          </motion.div>
        ))}

        {clients.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Users size={48} className="text-[var(--text-tertiary)] mb-4" />
            <p className="text-[var(--text-secondary)] font-medium">Nenhum cliente encontrado</p>
          </div>
        )}
      </motion.div>

      <FloatingActionButton onClick={() => navigate('newClient')} className="absolute bottom-24 right-4" />
    </div>
  );
}
