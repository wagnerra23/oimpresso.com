import { useState, type FormEvent, type ReactNode } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Button } from '@/Components/ui/button';
import GoogleIcon from '@/Components/Site/GoogleIcon';
import MicrosoftIcon from '@/Components/Site/MicrosoftIcon';

interface SiteRegisterProps {
  socialEnabled?: { google?: boolean; microsoft?: boolean };
  allowRegistration?: boolean;
}

function SiteRegister({ socialEnabled, allowRegistration }: SiteRegisterProps) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });

  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/register', { preserveScroll: true });
  };

  if (allowRegistration === false) {
    return (
      <>
        <Head title="Cadastro indisponível" />
        <section className="mx-auto max-w-md px-4 py-20 text-center">
          <h1 className="text-2xl font-bold text-foreground">Cadastro indisponível</h1>
          <p className="mt-3 text-sm text-muted-foreground">
            O cadastro automático está desabilitado. Fale com o time pra liberar acesso.
          </p>
          <div className="mt-6">
            <Button asChild>
              <a href="/c/contact-us">Falar com o time</a>
            </Button>
          </div>
        </section>
      </>
    );
  }

  return (
    <>
      <Head title="Criar conta" />

      <section className="relative isolate overflow-hidden">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--color-primary)_0%,_transparent_55%)] opacity-[0.07]"
        />

        <div className="mx-auto flex min-h-[calc(100vh-200px)] max-w-md flex-col justify-center px-4 py-16 sm:px-6">
          <div className="rounded-2xl border border-border bg-card p-8 shadow-xl shadow-primary/5 sm:p-10">
            <div className="text-center">
              <h1 className="text-2xl font-bold tracking-tight text-foreground">Criar sua conta</h1>
              <p className="mt-2 text-sm text-muted-foreground">
                Em 30 segundos você está dentro. Sem cartão de crédito.
              </p>
            </div>

            <div className="mt-8 space-y-3">
              <Button type="button" variant="outline" size="lg" className="w-full justify-center gap-3" asChild>
                <a href="/auth/google/redirect" rel="nofollow">
                  <GoogleIcon className="h-5 w-5" />
                  <span>Cadastrar com Google</span>
                </a>
              </Button>
              <Button type="button" variant="outline" size="lg" className="w-full justify-center gap-3" asChild>
                <a href="/auth/microsoft/redirect" rel="nofollow">
                  <MicrosoftIcon className="h-5 w-5" />
                  <span>Cadastrar com Microsoft</span>
                </a>
              </Button>

              {!socialEnabled?.google && !socialEnabled?.microsoft && (
                <p className="text-center text-[11px] text-muted-foreground">
                  Login social ainda não configurado. Use o formulário abaixo.
                </p>
              )}
            </div>

            <div className="my-7 flex items-center gap-3 text-xs uppercase tracking-wider text-muted-foreground">
              <div className="h-px flex-1 bg-border" />
              <span>ou</span>
              <div className="h-px flex-1 bg-border" />
            </div>

            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
              <Field
                id="name"
                label="Seu nome"
                value={data.name}
                onChange={(v) => setData('name', v)}
                error={errors.name}
                autoComplete="name"
                autoFocus
              />
              <Field
                id="email"
                label="Email"
                type="email"
                value={data.email}
                onChange={(v) => setData('email', v)}
                error={errors.email}
                autoComplete="email"
              />
              <Field
                id="password"
                label="Senha (mínimo 8 caracteres)"
                type={showPassword ? 'text' : 'password'}
                value={data.password}
                onChange={(v) => setData('password', v)}
                error={errors.password}
                autoComplete="new-password"
                trailing={
                  <button
                    type="button"
                    onClick={() => setShowPassword((s) => !s)}
                    className="text-xs font-medium text-muted-foreground hover:text-foreground"
                  >
                    {showPassword ? 'Ocultar' : 'Mostrar'}
                  </button>
                }
              />
              <Field
                id="password_confirmation"
                label="Confirme a senha"
                type={showPassword ? 'text' : 'password'}
                value={data.password_confirmation}
                onChange={(v) => setData('password_confirmation', v)}
                autoComplete="new-password"
              />

              <Button type="submit" size="lg" className="w-full" disabled={processing}>
                {processing ? 'Criando conta…' : 'Criar conta'}
              </Button>
            </form>

            <p className="mt-6 text-center text-sm text-muted-foreground">
              Já tem conta?{' '}
              <Link href="/login" className="font-medium text-primary hover:underline">
                Entrar
              </Link>
            </p>
          </div>

          <p className="mt-6 text-center text-[11px] text-muted-foreground">
            Ao criar conta você concorda com nossos termos de uso e política de privacidade.
          </p>
        </div>
      </section>
    </>
  );
}

interface FieldProps {
  id: string;
  label: string;
  value: string;
  onChange: (v: string) => void;
  error?: string;
  type?: string;
  autoComplete?: string;
  autoFocus?: boolean;
  trailing?: ReactNode;
}

function Field({ id, label, value, onChange, error, type = 'text', autoComplete, autoFocus, trailing }: FieldProps) {
  return (
    <div className="relative">
      <input
        id={id}
        name={id}
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        autoComplete={autoComplete}
        autoFocus={autoFocus}
        placeholder=" "
        className={`peer h-12 w-full rounded-md border bg-background px-3 pt-3 text-sm text-foreground transition-colors placeholder-transparent focus:outline-none focus:ring-2 focus:ring-primary/40 ${
          error ? 'border-destructive' : 'border-border'
        }`}
      />
      <label
        htmlFor={id}
        className="pointer-events-none absolute left-3 top-1 text-[11px] font-medium text-muted-foreground transition-all peer-placeholder-shown:top-3.5 peer-placeholder-shown:text-sm peer-focus:top-1 peer-focus:text-[11px]"
      >
        {label}
      </label>
      {trailing && <div className="absolute right-2 top-1/2 -translate-y-1/2">{trailing}</div>}
      {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
    </div>
  );
}

SiteRegister.layout = (page: ReactNode) => <SiteLayout title="Criar conta">{page}</SiteLayout>;

export default SiteRegister;
