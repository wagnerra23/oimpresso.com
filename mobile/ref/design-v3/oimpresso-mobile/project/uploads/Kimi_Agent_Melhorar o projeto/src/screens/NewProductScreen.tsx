import { useState } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, Package, Tag, DollarSign, Boxes, CheckCircle } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { Input } from '@/components/shared/Input';
import { Button } from '@/components/shared/Button';
import { SegmentedControl } from '@/components/shared/SegmentedControl';

export function NewProductScreen() {
  const { goBack, addToast } = useAppState();
  const [step, setStep] = useState(0);
  const [form, setForm] = useState({ name: '', category: '', price: '', cost: '', stock: '', unit: 'un' });

  const update = (field: string, value: string) => setForm(prev => ({ ...prev, [field]: value }));

  const handleSubmit = () => {
    addToast('Produto cadastrado com sucesso!', 'success');
    goBack();
  };

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1">Novo Produto</h1>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        <div className="flex items-center gap-2 mb-2">
          {[0, 1].map(s => (
            <div key={s} className="flex-1 h-1 rounded-full" style={{ background: s <= step ? 'var(--primary)' : 'var(--progress-track)' }} />
          ))}
        </div>

        {step === 0 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Informações Básicas</h2>
            <Input label="Nome do Produto" value={form.name} onChange={e => update('name', e.target.value)} icon={<Package size={18} />} placeholder="Ex: Cartão de Visita Couchê 300g" />
            <Input label="Categoria" value={form.category} onChange={e => update('category', e.target.value)} icon={<Tag size={18} />} placeholder="Ex: Cartões" />
            <SegmentedControl
              value={form.unit}
              onChange={v => update('unit', v)}
              options={[{ value: 'un', label: 'Unidade' }, { value: 'm2', label: 'm²' }, { value: 'kg', label: 'Kg' }]}
            />
          </motion.div>
        )}

        {step === 1 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Preços e Estoque</h2>
            <Input label="Preço de Venda (R$)" type="number" value={form.price} onChange={e => update('price', e.target.value)} icon={<DollarSign size={18} />} placeholder="0,00" />
            <Input label="Custo (R$)" type="number" value={form.cost} onChange={e => update('cost', e.target.value)} icon={<DollarSign size={18} />} placeholder="0,00" />
            <Input label="Estoque Inicial" type="number" value={form.stock} onChange={e => update('stock', e.target.value)} icon={<Boxes size={18} />} placeholder="0" />
          </motion.div>
        )}

        <div className="flex gap-3 pt-4">
          {step > 0 && <Button variant="secondary" fullWidth onClick={() => setStep(step - 1)}>Voltar</Button>}
          {step < 1 ? (
            <Button variant="primary" fullWidth onClick={() => setStep(step + 1)}>Continuar</Button>
          ) : (
            <Button variant="primary" fullWidth icon={<CheckCircle size={18} />} onClick={handleSubmit}>Cadastrar Produto</Button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
