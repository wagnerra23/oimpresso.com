import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, Link } from '@inertiajs/react';
import { FileText, MessageSquare, LayoutDashboard } from 'lucide-react';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

interface BriefProps {
  generatedAt?: string | null;
}

export default function BriefIndex({ generatedAt }: BriefProps) {
  return (
    <AppShellV2>
      <Head title="Brief — Jana" />
      <div className="mx-auto max-w-3xl px-4 py-8">
        <PageHeader
          icon="file-text"
          title="Brief diário"
          description="Resumo executivo da Jana — estado consolidado do negócio."
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
              {generatedAt
                ? `Último brief gerado em ${generatedAt}. Abra no chat para a versão completa.`
                : 'Por enquanto, o brief completo está disponível no chat da Jana.'}
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
