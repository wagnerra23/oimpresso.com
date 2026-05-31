import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { CheckSquare, Clock, FileClock, Upload, ArrowRight } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';

interface WelcomeProps {
  business?: { name?: string | null } | null;
  usuario?: { name?: string | null } | null;
}

const SECOES = [
  { href: '/ponto/aprovacoes', icon: CheckSquare, label: 'Aprovações', desc: 'Pendências de ajuste e abono.' },
  { href: '/ponto/banco-horas', icon: Clock, label: 'Banco de horas', desc: 'Saldo de horas por colaborador.' },
  { href: '/ponto/espelho', icon: FileClock, label: 'Espelho de ponto', desc: 'Marcações por período.' },
  { href: '/ponto/importacoes', icon: Upload, label: 'Importações', desc: 'Arquivos AFD do relógio.' },
];

export default function PontoWelcome({ business, usuario }: WelcomeProps) {
  return (
    <AppShellV2>
      <Head title="Ponto — Início" />
      <div className="mx-auto max-w-4xl px-4 py-8">
        <PageHeader
          icon="clock"
          title="Ponto eletrônico"
          description={
            usuario?.name
              ? `Olá, ${usuario.name}. Acesse as áreas do controle de ponto${business?.name ? ` de ${business.name}` : ''}.`
              : 'Acesse as áreas do controle de ponto.'
          }
        />

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          {SECOES.map((s) => (
            <Link key={s.href} href={s.href} className="group">
              <Card className="h-full transition-colors hover:border-primary">
                <CardContent className="flex items-start gap-3 py-5">
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <s.icon className="h-5 w-5" aria-hidden />
                  </span>
                  <div className="min-w-0">
                    <div className="flex items-center gap-1 font-semibold text-foreground group-hover:text-primary">
                      {s.label}
                      <ArrowRight className="h-4 w-4 opacity-0 transition-opacity group-hover:opacity-100" aria-hidden />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">{s.desc}</p>
                  </div>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      </div>
    </AppShellV2>
  );
}
