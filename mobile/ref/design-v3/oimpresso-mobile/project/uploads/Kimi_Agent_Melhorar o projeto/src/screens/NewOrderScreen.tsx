import { useState } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, Package, Plus, Minus, CheckCircle, Trash2 } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { CLIENTS, PRODUCTS } from '@/data/mockData';
import { Button } from '@/components/shared/Button';
import { Card } from '@/components/shared/Card';
import { SearchBar } from '@/components/shared/SearchBar';

interface OrderItem {
  productId: string;
  productName: string;
  quantity: number;
  unitPrice: number;
}

export function NewOrderScreen() {
  const { goBack, addToast } = useAppState();
  const [step, setStep] = useState(0);
  const [selectedClient, setSelectedClient] = useState('');
  const [items, setItems] = useState<OrderItem[]>([]);
  const [searchClient, setSearchClient] = useState('');
  const [searchProduct, setSearchProduct] = useState('');

  const clients = searchClient
    ? CLIENTS.filter(c => c.name.toLowerCase().includes(searchClient.toLowerCase()))
    : CLIENTS;

  const availableProducts = PRODUCTS.filter(p => !items.find(i => i.productId === p.id))
    .filter(p => !searchProduct || p.name.toLowerCase().includes(searchProduct.toLowerCase()));

  const addItem = (product: typeof PRODUCTS[0]) => {
    setItems(prev => [...prev, { productId: product.id, productName: product.name, quantity: 1, unitPrice: product.price }]);
    setSearchProduct('');
  };

  const updateQty = (productId: string, delta: number) => {
    setItems(prev => prev.map(i => i.productId === productId ? { ...i, quantity: Math.max(1, i.quantity + delta) } : i));
  };

  const removeItem = (productId: string) => {
    setItems(prev => prev.filter(i => i.productId !== productId));
  };

  const total = items.reduce((sum, i) => sum + i.unitPrice * i.quantity, 0);

  const handleSubmit = () => {
    addToast(`Pedido criado com ${items.length} item(s)!`, 'success');
    goBack();
  };

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1">Novo Pedido</h1>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        <div className="flex items-center gap-2 mb-2">
          {[0, 1, 2].map(s => (
            <div key={s} className="flex-1 h-1 rounded-full" style={{ background: s <= step ? 'var(--primary)' : 'var(--progress-track)' }} />
          ))}
        </div>

        {step === 0 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Selecionar Cliente</h2>
            <SearchBar value={searchClient} onChange={setSearchClient} placeholder="Buscar cliente..." />
            <div className="space-y-2">
              {clients.map(client => (
                <Card
                  key={client.id}
                  pressable
                  onClick={() => { setSelectedClient(client.id); setStep(1); }}
                  className={selectedClient === client.id ? 'border-[var(--primary)]' : ''}
                  padding="default"
                >
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold text-white" style={{ background: 'linear-gradient(135deg, var(--primary), #5E5CE6)' }}>
                      {client.avatar}
                    </div>
                    <div className="flex-1">
                      <p className="text-sm font-medium text-[var(--text-primary)]">{client.name}</p>
                      <p className="text-xs text-[var(--text-secondary)]">{client.email}</p>
                    </div>
                    {selectedClient === client.id && <CheckCircle size={20} className="text-[var(--primary)]" />}
                  </div>
                </Card>
              ))}
            </div>
          </motion.div>
        )}

        {step === 1 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Adicionar Itens</h2>
            <SearchBar value={searchProduct} onChange={setSearchProduct} placeholder="Buscar produtos..." />

            {items.length > 0 && (
              <div className="space-y-2">
                <p className="text-xs font-semibold uppercase tracking-wider text-[var(--text-secondary)]">Itens do pedido</p>
                {items.map(item => (
                  <Card key={item.productId} padding="default">
                    <div className="flex items-center justify-between">
                      <div>
                        <p className="text-sm font-medium text-[var(--text-primary)]">{item.productName}</p>
                        <p className="text-xs text-[var(--text-secondary)]">R$ {item.unitPrice.toFixed(2)} / un</p>
                      </div>
                      <div className="flex items-center gap-2">
                        <button onClick={() => updateQty(item.productId, -1)} className="w-8 h-8 rounded-lg bg-[var(--bg-elevated)] flex items-center justify-center">
                          <Minus size={14} className="text-[var(--text-primary)]" />
                        </button>
                        <span className="text-sm font-semibold text-[var(--text-primary)] w-8 text-center">{item.quantity}</span>
                        <button onClick={() => updateQty(item.productId, 1)} className="w-8 h-8 rounded-lg bg-[var(--bg-elevated)] flex items-center justify-center">
                          <Plus size={14} className="text-[var(--text-primary)]" />
                        </button>
                        <button onClick={() => removeItem(item.productId)} className="w-8 h-8 rounded-lg flex items-center justify-center ml-1">
                          <Trash2 size={14} className="text-[var(--danger)]" />
                        </button>
                      </div>
                    </div>
                  </Card>
                ))}
              </div>
            )}

            {availableProducts.length > 0 && (
              <div className="space-y-2">
                <p className="text-xs font-semibold uppercase tracking-wider text-[var(--text-secondary)]">Produtos disponíveis</p>
                {availableProducts.slice(0, 5).map(product => (
                  <Card key={product.id} pressable onClick={() => addItem(product)} padding="default">
                    <div className="flex items-center gap-3">
                      <div className="w-9 h-9 rounded-lg flex items-center justify-center" style={{ background: 'var(--primary-subtle)' }}>
                        <Package size={16} style={{ color: 'var(--primary)' }} />
                      </div>
                      <div className="flex-1">
                        <p className="text-sm font-medium text-[var(--text-primary)]">{product.name}</p>
                        <p className="text-xs text-[var(--text-secondary)]">R$ {product.price.toFixed(2)}</p>
                      </div>
                      <Plus size={18} className="text-[var(--primary)]" />
                    </div>
                  </Card>
                ))}
              </div>
            )}
          </motion.div>
        )}

        {step === 2 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Resumo</h2>
            <Card>
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-[var(--text-secondary)]">Cliente</span>
                  <span className="text-[var(--text-primary)] font-medium">{CLIENTS.find(c => c.id === selectedClient)?.name}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-[var(--text-secondary)]">Itens</span>
                  <span className="text-[var(--text-primary)] font-medium">{items.length}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-[var(--text-secondary)]">Unidades</span>
                  <span className="text-[var(--text-primary)] font-medium">{items.reduce((s, i) => s + i.quantity, 0)}</span>
                </div>
              </div>
              <div className="mt-3 pt-3 border-t flex justify-between" style={{ borderColor: 'var(--border-color)' }}>
                <span className="text-base font-semibold text-[var(--text-primary)]">Total</span>
                <span className="text-lg font-bold text-[var(--primary)]">R$ {total.toFixed(2)}</span>
              </div>
            </Card>
          </motion.div>
        )}

        <div className="flex gap-3 pt-4">
          {step > 0 && <Button variant="secondary" fullWidth onClick={() => setStep(step - 1)}>Voltar</Button>}
          {step < 2 ? (
            <Button variant="primary" fullWidth onClick={() => setStep(step + 1)} disabled={step === 1 && items.length === 0}>Continuar</Button>
          ) : (
            <Button variant="primary" fullWidth icon={<CheckCircle size={18} />} onClick={handleSubmit}>Criar Pedido</Button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
