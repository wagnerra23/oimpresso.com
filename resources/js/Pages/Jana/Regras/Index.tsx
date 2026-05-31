import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { ShieldCheck, ArrowRight } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';

interface RegrasProps {
  // Controller passa businessId (session). Lista de políticas reais vem em onda futura.
  businessId?: number;
  count?: number;
}

// Os 4 outcomes do PolicyEngine (ADR — Dual-Brain). Read-only até o backend
// passar a lista real de políticas ativas.
const OUTCOMES: Array<{ key: string; label: string; desc: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' }> = [
  { key: 'allow', label: 'ALLOW_BRAIN_A', desc: 'Decisão automática pelo Brain A (rápido/barato).', variant: 'secondary' },
  { key: 'brain_b', label: 'REQUIRE_BRAIN_B', desc: 'Escala para o Brain B (Sonnet/Opus) antes de agir.', variant: 'default' },
  { key: 'human', label: 'REQUIRE_HUMAN_REVIEW', desc: 'Exige aprovação humana (HITL) antes de executar.', variant: 'outline' },
  { key: 'block', label: 'BLOCK_ALWAYS', desc: 'Bloqueia sempre — ação proibida por política.', variant: 'destructive' },
];

export default function RegrasIndex({ count }: RegrasProps) {
  void 0; // businessId disponível p/ futura listagem por business
  return (
    <AppShellV2>
      <Head title="Regras — Jana" />
      <div className="mx-auto max-w-3xl px-4 py-8">
        <PageHeader
          icon="shield-check"
          title="Regras & Políticas"
          description={
            count != null
              ? `${count} política(s) ativa(s) no PolicyEngine.`
              : 'Os 4 resultados possíveis do PolicyEngine que governam cada decisão da Jana.'
          }
        />

        <div className="mt-6 grid gap-3">
          {OUTCOMES.map((o) => (
            <Card key={o.key}>
              <CardContent className="flex items-start gap-3 py-4">
                <span className="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                  <ShieldCheck className="h-4 w-4" aria-hidden />
                </span>
                <div className="min-w-0">
                  <Badge variant={o.variant} className="font-mono text-[11px]">{o.label}</Badge>
                  <p className="mt-1 text-sm text-muted-foreground">{o.desc}</p>
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        <div className="mt-6">
          <Button asChild variant="outline">
            <Link href="/governanca">
              Ver Governança
              <ArrowRight className="h-4 w-4" aria-hidden />
            </Link>
          </Button>
        </div>
        {/* TODO(Jana): listar as políticas reais por outcome quando o controller as passar. */}
      </div>
    </AppShellV2>
  );
}
