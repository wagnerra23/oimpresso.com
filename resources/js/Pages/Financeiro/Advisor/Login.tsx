// @memcofre tela=/advisor/login module=Financeiro
// Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor login isolado.
// NÃO usa AppShellV2 — advisor é entidade global, não tem sidebar POS UltimatePOS.

import { useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';

interface FlashShape {
  success?: string;
  error?: string;
}

function Login() {
  const { props } = usePage<{ flash?: FlashShape }>();
  const flash = props.flash ?? {};

  const form = useForm({
    email: '',
    password: '',
    remember: false,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/advisor/login', {
      preserveScroll: true,
      onError: () => form.reset('password'),
    });
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-slate-50 to-slate-100 p-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold">Portal do Contador</h1>
          <p className="text-sm text-muted-foreground mt-1">
            Acesso somente leitura aos clientes que te concederam permissão
          </p>
        </div>

        <div className="bg-white rounded-lg shadow-md p-6 space-y-4">
          {flash.success && (
            <div className="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
              {flash.success}
            </div>
          )}
          {flash.error && (
            <div className="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">
              {flash.error}
            </div>
          )}

          <form onSubmit={submit} className="space-y-4">
            <label className="block space-y-1">
              <span className="text-sm font-medium">Email</span>
              <input
                type="email"
                className="w-full rounded border px-3 py-2 text-sm"
                value={form.data.email}
                onChange={(e) => form.setData('email', e.target.value)}
                autoFocus
                required
              />
              {form.errors.email && <span className="text-xs text-red-600">{form.errors.email}</span>}
            </label>

            <label className="block space-y-1">
              <span className="text-sm font-medium">Senha</span>
              <input
                type="password"
                className="w-full rounded border px-3 py-2 text-sm"
                value={form.data.password}
                onChange={(e) => form.setData('password', e.target.value)}
                required
              />
              {form.errors.password && <span className="text-xs text-red-600">{form.errors.password}</span>}
            </label>

            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={form.data.remember}
                onChange={(e) => form.setData('remember', e.target.checked)}
              />
              Lembrar de mim
            </label>

            <button
              type="submit"
              className="w-full os-btn primary"
              disabled={form.processing}
            >
              {form.processing ? 'Entrando...' : 'Entrar'}
            </button>
          </form>

          <div className="border-t pt-4 text-center">
            <p className="text-xs text-muted-foreground">
              Não tem acesso ainda? Peça ao seu cliente para te adicionar em
              <br />
              <code className="text-xs">Financeiro → Configurações → Contador</code>
            </p>
          </div>
        </div>

        <div className="text-center mt-6">
          <a href="/" className="text-xs text-muted-foreground hover:underline">
            Voltar ao oimpresso
          </a>
        </div>
      </div>
    </div>
  );
}

export default Login;
