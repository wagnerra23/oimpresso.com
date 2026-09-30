import { useState } from 'react';
import { motion } from 'framer-motion';
import { Eye, EyeOff, LogIn } from 'lucide-react';
import { Input } from '@/components/shared/Input';
import { Button } from '@/components/shared/Button';

export function LoginScreen() {
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  return (
    <div className="h-full flex flex-col relative overflow-hidden">
      {/* Background Image */}
      <div className="absolute inset-0 z-0">
        <img src="/login-bg.jpg" alt="" className="w-full h-full object-cover" />
        <div className="absolute inset-0 bg-gradient-to-b from-black/40 via-black/60 to-black/90" />
      </div>

      {/* Content */}
      <div className="relative z-10 flex-1 flex flex-col justify-end p-[var(--content-padding)] pb-10">
        <motion.div
          initial={{ opacity: 0, y: 30 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, ease: 'easeOut' }}
          className="space-y-6"
        >
          {/* Logo */}
          <div className="text-center mb-8">
            <div className="w-16 h-16 rounded-2xl mx-auto mb-4 flex items-center justify-center" style={{ background: 'linear-gradient(135deg, #0A84FF, #5856D6)' }}>
              <span className="text-2xl font-bold text-white">O</span>
            </div>
            <h1 className="text-3xl font-bold text-white tracking-tight">Oimpresso</h1>
            <p className="text-sm text-white/60 mt-1">ERP Gráfico Inteligente</p>
          </div>

          {/* Form */}
          <div className="space-y-4">
            <Input
              label="E-mail"
              type="email"
              value={email}
              onChange={e => setEmail(e.target.value)}
              placeholder="seu@email.com"
              className="bg-white/10 border-white/20 text-white placeholder:text-white/40"
            />
            <div className="relative">
              <Input
                label="Senha"
                type={showPassword ? 'text' : 'password'}
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder="••••••••"
                className="bg-white/10 border-white/20 text-white placeholder:text-white/40"
              />
              <button
                onClick={() => setShowPassword(!showPassword)}
                className="absolute right-3 top-[38px] text-white/40 hover:text-white/60"
              >
                {showPassword ? <EyeOff size={18} /> : <Eye size={18} />}
              </button>
            </div>
          </div>

          <Button variant="primary" fullWidth icon={<LogIn size={18} />}>
            Entrar
          </Button>

          <p className="text-center text-xs text-white/40">
            Não tem conta? <span className="text-[#0A84FF] cursor-pointer">Solicitar acesso</span>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
