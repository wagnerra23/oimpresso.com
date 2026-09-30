import { useState } from 'react';
import { motion } from 'framer-motion';
import { ArrowLeft, User, Mail, Phone, MapPin, FileText, Building2, CheckCircle } from 'lucide-react';
import { useAppState } from '@/contexts/AppStateContext';
import { Input } from '@/components/shared/Input';
import { Button } from '@/components/shared/Button';
import { SegmentedControl } from '@/components/shared/SegmentedControl';

export function NewClientScreen() {
  const { goBack, addToast } = useAppState();
  const [step, setStep] = useState(0);
  const [type, setType] = useState<'pf' | 'pj'>('pj');
  const [form, setForm] = useState({
    name: '', email: '', phone: '', document: '', address: '', city: '',
  });

  const update = (field: string, value: string) => setForm(prev => ({ ...prev, [field]: value }));

  const handleSubmit = () => {
    addToast('Cliente cadastrado com sucesso!', 'success');
    goBack();
  };

  return (
    <motion.div className="h-full flex flex-col" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} transition={{ type: 'spring', stiffness: 300, damping: 30 }}>
      <div className="shrink-0 flex items-center gap-3 p-[var(--content-padding)] pb-3 border-b" style={{ borderColor: 'var(--border-color)' }}>
        <button onClick={goBack} className="p-2 -ml-2 rounded-full hover:bg-[var(--bg-elevated)] transition-colors">
          <ArrowLeft size={22} className="text-[var(--text-primary)]" />
        </button>
        <h1 className="text-lg font-semibold text-[var(--text-primary)] flex-1">Novo Cliente</h1>
      </div>

      <div className="flex-1 overflow-y-auto p-[var(--content-padding)] space-y-4">
        {/* Progress */}
        <div className="flex items-center gap-2 mb-2">
          {[0, 1, 2].map(s => (
            <div key={s} className="flex-1 h-1 rounded-full" style={{ background: s <= step ? 'var(--primary)' : 'var(--progress-track)' }} />
          ))}
        </div>

        {step === 0 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Tipo de Cliente</h2>
            <SegmentedControl
              value={type}
              onChange={v => setType(v as 'pf' | 'pj')}
              options={[{ value: 'pj', label: 'Pessoa Jurídica' }, { value: 'pf', label: 'Pessoa Física' }]}
            />
            <Input label="Nome completo / Razão Social" value={form.name} onChange={e => update('name', e.target.value)} icon={<User size={18} />} placeholder="Digite o nome" />
            <Input label={type === 'pj' ? 'CNPJ' : 'CPF'} value={form.document} onChange={e => update('document', e.target.value)} icon={<FileText size={18} />} placeholder={type === 'pj' ? '00.000.000/0000-00' : '000.000.000-00'} />
          </motion.div>
        )}

        {step === 1 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Contato</h2>
            <Input label="E-mail" type="email" value={form.email} onChange={e => update('email', e.target.value)} icon={<Mail size={18} />} placeholder="email@exemplo.com" />
            <Input label="Telefone" value={form.phone} onChange={e => update('phone', e.target.value)} icon={<Phone size={18} />} placeholder="(00) 00000-0000" />
          </motion.div>
        )}

        {step === 2 && (
          <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} className="space-y-4">
            <h2 className="text-lg font-semibold text-[var(--text-primary)]">Endereço</h2>
            <Input label="Endereço" value={form.address} onChange={e => update('address', e.target.value)} icon={<MapPin size={18} />} placeholder="Rua, número, bairro" />
            <Input label="Cidade / UF" value={form.city} onChange={e => update('city', e.target.value)} icon={<Building2 size={18} />} placeholder="São Paulo, SP" />
          </motion.div>
        )}

        <div className="flex gap-3 pt-4">
          {step > 0 && <Button variant="secondary" fullWidth onClick={() => setStep(step - 1)}>Voltar</Button>}
          {step < 2 ? (
            <Button variant="primary" fullWidth onClick={() => setStep(step + 1)}>Continuar</Button>
          ) : (
            <Button variant="primary" fullWidth icon={<CheckCircle size={18} />} onClick={handleSubmit}>Cadastrar Cliente</Button>
          )}
        </div>
      </div>
    </motion.div>
  );
}
