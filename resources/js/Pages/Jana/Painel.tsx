import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { LayoutDashboard, MessageSquare, Brain, ArrowRight } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';

interface PainelProps {
  kind?: string;
}

const TOOLS = [
  { href: '/ia/dashboard', icon: LayoutDashboard, label: 'Dashboard', desc: 'Metas, métricas e farol da Jana.' },
  { href: '/ia/chat', icon: MessageSquare, label: 'Chat', desc: 'Converse com a analista IA.' },
  { href: '/ia/cockpit', icon: Brain, label: 'Cockpit', desc: 'Análises e cockpit do analista.' },
];

export default function JanaPainel({ kind }: PainelProps) {
  return (
    <AppShellV2>
      <Head title="Painel — Jana" />
      <div className="mx-auto max-w-5xl px-4 py-8">
        <PageHeader
          icon="layout-dashboard"
          title="Painel da Jana"
          description="Acesse as superfícies da analista IA."
        />

        <div className="mt-6 grid gap-4 sm:grid-cols-3">
          {TOOLS.map((t) => (
            <Link key={t.href} href={t.href} className="group">
              <Card className="h-full transition-colors hover:border-primary">
                <CardContent className="py-5">
                  <span className="flex h-9 w-9 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <t.icon className="h-5 w-5" aria-hidden />
                  </span>
                  <div className="mt-3 flex items-center gap-1 font-semibold text-foreground group-hover:text-primary">
                    {t.label}
                    <ArrowRight className="h-4 w-4 opacity-0 transition-opacity group-hover:opacity-100" aria-hidden />
                  </div>
                  <p className="mt-1 text-sm text-muted-foreground">{t.desc}</p>
                </CardContent>
              </Card>
            </Link>
          ))}
        </div>
        {/* TODO(Jana): painel '{kind}' com sub-components reais quando o backend os fornecer. */}
        {kind ? <p className="sr-only">Painel: {kind}</p> : null}
      </div>
    </AppShellV2>
  );
}
