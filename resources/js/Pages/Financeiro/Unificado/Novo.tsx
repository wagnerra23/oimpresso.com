import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { ArrowDownCircle, ArrowUpCircle, ArrowRight } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { Card, CardContent } from '@/Components/ui/card';

interface Props {
  tipos?: Array<{ value: string; label: string }>;
}

const OPCOES = [
  {
    href: '/financeiro/contas-receber/novo',
    icon: ArrowDownCircle,
    title: 'Conta a receber',
    desc: 'Registrar uma entrada — venda, serviço ou recebimento.',
  },
  {
    href: '/financeiro/contas-pagar/novo',
    icon: ArrowUpCircle,
    title: 'Conta a pagar',
    desc: 'Registrar uma saída — despesa, compra ou pagamento.',
  },
];

export default function Novo(_props: Props) {
  return (
    <AppShellV2>
      <Head title="Novo lançamento — Financeiro" />
      <PageHeader
        title="Novo lançamento"
        subtitle="Escolha o tipo de título a registrar."
        subnav={<FinanceiroSubNav active="unificado" hidePrimary />}
      />
      <div className="mx-auto max-w-3xl px-4 py-6">
        <div className="grid gap-4 sm:grid-cols-2">
          {OPCOES.map((o) => (
            <Link key={o.href} href={o.href} className="group">
              <Card className="h-full transition-colors hover:border-primary">
                <CardContent className="py-6">
                  <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    <o.icon className="h-5 w-5" aria-hidden />
                  </span>
                  <div className="mt-3 flex items-center gap-1 text-lg font-semibold text-foreground group-hover:text-primary">
                    {o.title}
                    <ArrowRight className="h-4 w-4 opacity-0 transition-opacity group-hover:opacity-100" aria-hidden />
                  </div>
                  <p className="mt-1 text-sm text-muted-foreground">{o.desc}</p>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
      </div>
    </AppShellV2>
  );
}
