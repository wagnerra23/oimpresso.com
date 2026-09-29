import { useState } from 'react';
import { motion } from 'framer-motion';
import { Package } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { PRODUCTS } from '@/data/mockData';
import { Card } from '@/components/shared/Card';
import { SearchBar } from '@/components/shared/SearchBar';
import { FloatingActionButton } from '@/components/shared/FloatingActionButton';
import { Badge } from '@/components/shared/Badge';

const container = { hidden: { opacity: 0 }, show: { opacity: 1, transition: { staggerChildren: 0.04 } } };
const itemAnim = { hidden: { y: 8, opacity: 0 }, show: { y: 0, opacity: 1 } };

export function ProductsScreen() {
  const { navigate } = useAppState();
  const [search, setSearch] = useState('');

  const products = search
    ? PRODUCTS.filter(p => p.name.toLowerCase().includes(search.toLowerCase()) || p.sku.toLowerCase().includes(search.toLowerCase()))
    : PRODUCTS;

  const categories = [...new Set(PRODUCTS.map(p => p.category))];

  return (
    <div className="h-full flex flex-col">
      <div className="p-[var(--content-padding)] pb-2 space-y-3">
        <motion.div initial={{ opacity: 0, y: -10 }} animate={{ opacity: 1, y: 0 }}>
          <h1 className="text-2xl font-bold text-[var(--text-primary)]">Produtos</h1>
          <p className="text-sm text-[var(--text-secondary)] mt-0.5">{products.length} produtos em {categories.length} categorias</p>
        </motion.div>
        <SearchBar value={search} onChange={setSearch} placeholder="Buscar produtos ou SKU..." />
      </div>

      <motion.div className="flex-1 overflow-y-auto p-[var(--content-padding)] pt-0 space-y-2" variants={container} initial="hidden" animate="show">
        {products.map(product => (
          <motion.div key={product.id} variants={itemAnim}>
            <Card pressable onClick={() => {}} padding="default">
              <div className="flex items-center gap-3">
                <div className="w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0" style={{ background: 'var(--primary-subtle)' }}>
                  <Package size={20} style={{ color: 'var(--primary)' }} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-[var(--text-primary)] truncate">{product.name}</p>
                  <div className="flex items-center gap-2 mt-0.5">
                    <span className="text-xs font-mono text-[var(--text-secondary)]">{product.sku}</span>
                    <Badge variant="neutral" shape="pill">{product.category}</Badge>
                  </div>
                </div>
                <div className="text-right flex-shrink-0">
                  <p className="text-sm font-semibold text-[var(--text-primary)]">R$ {product.price.toFixed(2)}</p>
                  <p className={`text-xs ${product.stock <= product.minStock ? 'text-[var(--danger)]' : 'text-[var(--text-tertiary)]'}`}>
                    Est: {product.stock.toLocaleString()}
                  </p>
                </div>
              </div>
            </Card>
          </motion.div>
        ))}

        {products.length === 0 && (
          <div className="flex flex-col items-center justify-center py-16 text-center">
            <Package size={48} className="text-[var(--text-tertiary)] mb-4" />
            <p className="text-[var(--text-secondary)] font-medium">Nenhum produto encontrado</p>
          </div>
        )}
      </motion.div>

      <FloatingActionButton onClick={() => navigate('newProduct')} className="absolute bottom-24 right-4" />
    </div>
  );
}
