import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { FileText, MessageSquare, LayoutDashboard } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

interface BriefProps {
  // Controller passa businessId (session user.business_id).
  businessId?: number;
}

export default function BriefIndex({ businessId }: BriefProps) {
  return (
    <AppShellV2>
      <Head title="Brief — Jana" />
      <div className="mx-auto max-w-3xl px-4 py-8">
        <PageHeader
          icon="file-text"
          title="Brief diário"
          description={
            businessId
              ? `Resumo executivo da Jana — estado consolidado do negócio (biz ${businessId}).`
              : 'Resumo executivo da Jana — estado consolidado do negócio.'
          }
        />

        <Card className="mt-6">
          <CardContent className="flex flex-col items-center py-12 text-center">
            <span className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
              <FileText className="h-6 w-6" aria-hidden />
            </span>
            <h2 className="text-base font-semibold text-foreground">
              O resumo executivo aparecerá aqui
            </h2>
            <p className="mt-2 max-w-md text-sm text-muted-foreground">
              O brief diário é gerado pelo <code className="rounded bg-muted px-1 py-0.5">BriefDiarioAgent</code>{' '}
              com base nas últimas 24h e fica disponível via <code className="rounded bg-muted px-1 py-0.5">brief-fetch</code>.
              Peça à Jana no chat para a versão completa.
            </p>
            <div className="mt-6 flex flex-wrap justify-center gap-3">
              <Button asChild>
                <Link href="/ia/chat">
                  <MessageSquare className="h-4 w-4" aria-hidden />
                  Abrir no chat
                </Link>
              </Button>
              <Button asChild variant="outline">
                <Link href="/ia/dashboard">
                  <LayoutDashboard className="h-4 w-4" aria-hidden />
                  Ver dashboard
                </Link>
              </Button>
            </div>
          </CardContent>
        </Card>
        {/* TODO(Jana): renderizar o brief inline quando o controller passar o markdown (brief-fetch). */}
      </div>
    </AppShellV2>
  );
}
