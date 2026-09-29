import { motion } from 'framer-motion';
import { useTweaks } from '@/contexts/TweaksContext';
import { TENANTS } from '@/data/mockData';

function TweakSection({ label }: { label: string }) {
  return (
    <div className="mt-6 first:mt-0">
      <p className="text-[10px] font-mono font-semibold tracking-[0.12em] uppercase" style={{ color: '#636366' }}>{label}</p>
      <div className="h-px mt-2 mb-4" style={{ background: '#38383A' }} />
    </div>
  );
}

function TweakRadio({ label, value, onChange, options }: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: { value: string; label: string }[];
}) {
  return (
    <div className="mb-4">
      <p className="text-[13px] font-medium text-[#f5f5f5] mb-2">{label}</p>
      <div className="flex gap-2 flex-wrap">
        {options.map(opt => (
          <button
            key={opt.value}
            onClick={() => onChange(opt.value)}
            className="px-3 py-1.5 rounded-lg text-xs font-medium border transition-all duration-200"
            style={{
              background: value === opt.value ? 'rgba(10,132,255,0.15)' : '#2c2c2e',
              color: value === opt.value ? '#0A84FF' : '#8E8E93',
              borderColor: value === opt.value ? 'rgba(10,132,255,0.3)' : '#38383A',
            }}
          >
            {opt.label}
          </button>
        ))}
      </div>
    </div>
  );
}

function TweakSelect({ label, value, onChange, options }: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  options: { value: string; label: string }[];
}) {
  return (
    <div className="mb-4">
      <p className="text-[13px] font-medium text-[#f5f5f5] mb-2">{label}</p>
      <select
        value={value}
        onChange={e => onChange(e.target.value)}
        className="w-full px-3 py-2 rounded-lg text-[13px] border outline-none appearance-none"
        style={{ background: '#2c2c2e', borderColor: '#38383A', color: '#f5f5f5' }}
      >
        {options.map(opt => (
          <option key={opt.value} value={opt.value} style={{ background: '#2c2c2e' }}>{opt.label}</option>
        ))}
      </select>
    </div>
  );
}

export function TweaksPanel() {
  const { tweaks, setTweak } = useTweaks();

  return (
    <motion.div
      className="fixed bottom-6 right-6 w-[280px] rounded-2xl p-5 z-50"
      style={{
        background: 'rgba(28,28,30,0.95)',
        backdropFilter: 'blur(20px)',
        border: '1px solid #38383A',
        boxShadow: '0 8px 32px rgba(0,0,0,0.4)',
      }}
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay: 0.5 }}
    >
      <p className="text-sm font-semibold text-[#f5f5f5] mb-1">Tweaks</p>

      <TweakSection label="Aparência" />
      <TweakRadio
        label="Tema"
        value={tweaks.theme}
        onChange={v => setTweak('theme', v as 'light' | 'dark')}
        options={[{ value: 'light', label: 'Claro' }, { value: 'dark', label: 'Escuro' }]}
      />
      <TweakRadio
        label="Densidade"
        value={tweaks.density}
        onChange={v => setTweak('density', v as 'comfy' | 'normal' | 'compact')}
        options={[{ value: 'compact', label: 'Compacto' }, { value: 'normal', label: 'Normal' }, { value: 'comfy', label: 'Confortável' }]}
      />

      <TweakSection label="Contexto" />
      <TweakSelect
        label="Empresa ativa"
        value={tweaks.tenant}
        onChange={v => setTweak('tenant', v)}
        options={TENANTS.map(t => ({ value: t.id, label: t.name }))}
      />
      <TweakRadio
        label="Tarefas"
        value={tweaks.taskState}
        onChange={v => setTweak('taskState', v as 'vazio' | 'cheio' | 'urgente')}
        options={[{ value: 'vazio', label: 'Vazio' }, { value: 'cheio', label: 'Cheio' }, { value: 'urgente', label: 'Urgente' }]}
      />
    </motion.div>
  );
}
