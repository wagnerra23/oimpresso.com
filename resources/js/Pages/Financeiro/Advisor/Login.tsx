// @memcofre tela=/advisor/login module=Financeiro
// Onda 31 (2026-05-20) #57 US-FIN-037 — Portal Advisor login isolado.
// NÃO usa AppShellV2 — advisor é entidade global, não tem sidebar POS UltimatePOS.
// Charter: Login.charter.md (status draft).

import { FormEvent } from 'react';
import { Head, useForm, usePage } from '@inertiajs/react';

import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

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
    <>
      <Head title="Portal do Contador" />

      <div className="min-h-screen flex items-center justify-center bg-background p-4">
        <div className="w-full max-w-md">
          <div className="text-center mb-6">
            <div className="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-primary mb-3">
              <svg
                className="w-7 h-7 text-primary-foreground"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            </div>
            <h1 className="text-2xl font-bold text-foreground">Portal do Contador</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Acesso somente leitura aos clientes que te concederam permissão
            </p>
          </div>

          <Card>
            <CardContent className="space-y-4">
              {flash.success && (
                <Alert>
                  <AlertDescription>{flash.success}</AlertDescription>
                </Alert>
              )}
              {flash.error && (
                <Alert variant="destructive">
                  <AlertDescription>{flash.error}</AlertDescription>
                </Alert>
              )}

              <form onSubmit={submit} className="space-y-4">
                <div className="space-y-1.5">
                  <Label htmlFor="email" variant="shadcn">
                    E-mail
                  </Label>
                  <Input
                    id="email"
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    aria-invalid={!!form.errors.email}
                    autoFocus
                    required
                  />
                  {form.errors.email && (
                    <p className="text-xs text-destructive">{form.errors.email}</p>
                  )}
                </div>

                <div className="space-y-1.5">
                  <Label htmlFor="password" variant="shadcn">
                    Senha
                  </Label>
                  <Input
                    id="password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    aria-invalid={!!form.errors.password}
                    required
                  />
                  {form.errors.password && (
                    <p className="text-xs text-destructive">{form.errors.password}</p>
                  )}
                </div>

                <div className="flex items-center gap-2">
                  <Checkbox
                    id="remember"
                    checked={form.data.remember}
                    onCheckedChange={(v) => form.setData('remember', v === true)}
                  />
                  <Label htmlFor="remember" variant="shadcn" className="font-normal cursor-pointer">
                    Lembrar de mim
                  </Label>
                </div>

                <Button type="submit" className="w-full" disabled={form.processing}>
                  {form.processing ? 'Entrando...' : 'Entrar'}
                </Button>
              </form>

              <div className="border-t border-border pt-4 text-center">
                <p className="text-xs text-muted-foreground">
                  Não tem acesso ainda? Peça ao seu cliente para te adicionar em
                  <br />
                  <code className="text-xs text-foreground">Financeiro → Configurações → Contador</code>
                </p>
              </div>
            </CardContent>
          </Card>

          <div className="text-center mt-6">
            <a href="/" className="text-xs text-muted-foreground hover:text-foreground hover:underline">
              Voltar ao oimpresso
            </a>
          </div>
        </div>
      </div>
    </>
  );
}

export default Login;
