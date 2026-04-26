import { useState, type FormEvent, type ReactNode } from 'react';
import { Head, useForm, usePage, Link } from '@inertiajs/react';
import SiteLayout from '@/Layouts/SiteLayout';
import { Button } from '@/Components/ui/button';
import GoogleIcon from '@/Components/Site/GoogleIcon';
import MicrosoftIcon from '@/Components/Site/MicrosoftIcon';

interface SiteLoginProps {
  socialEnabled?: { google?: boolean; microsoft?: boolean };
  allowRegistration?: boolean;
}

interface FlashStatus {
  success?: 0 | 1;
  msg?: string;
}

function SiteLogin({ socialEnabled, allowRegistration }: SiteLoginProps) {
  const { props } = usePage<{ status?: FlashStatus | string; errors?: Record<string, string> }>();
  const flash = (props.status ?? null) as FlashStatus | string | null;
  const initialError =
    typeof flash === 'object' && flash && flash.success === 0 ? flash.msg ?? null : null;

  const { data, setData, post, processing, errors } = useForm({
    username: '',
    password: '',
    remember: false,
  });

  const [showPassword, setShowPassword] = useState(false);

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/login', { preserveScroll: true });
  };

  return (
    <>
      <Head title="Entrar" />

      <section className="relative isolate overflow-hidden">
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_top,_var(--color-primary)_0%,_transparent_55%)] opacity-[0.07]"
        />

        <div className="mx-auto flex min-h-[calc(100vh-200px)] max-w-md flex-col justify-center px-4 py-16 sm:px-6">
          <div className="rounded-2xl border border-border bg-card p-8 shadow-xl shadow-primary/5 sm:p-10">
            <div className="text-center">
              <h1 className="text-2xl font-bold tracking-tight text-foreground">
                Bem-vindo de volta
              </h1>
              <p className="mt-2 text-sm text-muted-foreground">
                Entre com sua conta pra continuar.
              </p>
            </div>

            {initialError && (
              <div
                role="alert"
                className="mt-6 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive"
              >
                {initialError}
              </div>
            )}

            <div className="mt-8 space-y-3">
              <Button
                type="button"
                variant="outline"
                size="lg"
                className="w-full justify-center gap-3"
                asChild
              >
                <a href="/auth/google/redirect" rel="nofollow">
                  <GoogleIcon className="h-5 w-5" />
                  <span>Continuar com Google</span>
                </a>
              </Button>

              <Button
                type="button"
                variant="outline"
                size="lg"
                className="w-full justify-center gap-3"
                asChild
              >
                <a href="/auth/microsoft/redirect" rel="nofollow">
                  <MicrosoftIcon className="h-5 w-5" />
                  <span>Continuar com Microsoft</span>
                </a>
              </Button>

              {!socialEnabled?.google && !socialEnabled?.microsoft && (
                <p className="text-center text-[11px] text-muted-foreground">
                  Login social ainda não configurado. Use email/senha por enquanto.
                </p>
              )}
            </div>

            <div className="my-7 flex items-center gap-3 text-xs uppercase tracking-wider text-muted-foreground">
              <div className="h-px flex-1 bg-border" />
              <span>ou</span>
              <div className="h-px flex-1 bg-border" />
            </div>

            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
              <FloatingField
                id="username"
                label="Usuário ou email"
                value={data.username}
                onChange={(v) => setData('username', v)}
                error={errors.username}
                autoComplete="username"
                autoFocus
              />

              <FloatingField
                id="password"
                label="Senha"
                type={showPassword ? 'text' : 'password'}
                value={data.password}
                onChange={(v) => setData('password', v)}
                error={errors.password}
                autoComplete="current-password"
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

              <div className="flex items-center justify-between text-xs">
                <label className="inline-flex items-center gap-2 text-muted-foreground">
                  <input
                    type="checkbox"
                    checked={data.remember}
                    onChange={(e) => setData('remember', e.target.checked)}
                    className="h-3.5 w-3.5 rounded border-border accent-primary"
                  />
                  Lembrar de mim
                </label>
                <Link
                  href="/password/reset"
                  className="font-medium text-primary hover:underline"
                  preserveScroll
                >
                  Esqueceu a senha?
                </Link>
              </div>

              <Button type="submit" size="lg" className="w-full" disabled={processing}>
                {processing ? 'Entrando…' : 'Entrar'}
              </Button>
            </form>

            {allowRegistration !== false && (
              <p className="mt-6 text-center text-sm text-muted-foreground">
                Ainda não tem conta?{' '}
                <Link href="/register" className="font-medium text-primary hover:underline">
                  Criar conta
                </Link>
              </p>
            )}
          </div>

          <p className="mt-6 text-center text-[11px] text-muted-foreground">
            Ao entrar você concorda com nossos termos de uso e política de privacidade.
          </p>
        </div>
      </section>
    </>
  );
}

interface FloatingFieldProps {
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

function FloatingField({
  id,
  label,
  value,
  onChange,
  error,
  type = 'text',
  autoComplete,
  autoFocus,
  trailing,
}: FloatingFieldProps) {
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
      {trailing && (
        <div className="absolute right-2 top-1/2 -translate-y-1/2">{trailing}</div>
      )}
      {error && <p className="mt-1 text-xs text-destructive">{error}</p>}
    </div>
  );
}

SiteLogin.layout = (page: ReactNode) => <SiteLayout title="Entrar">{page}</SiteLayout>;

export default SiteLogin;
