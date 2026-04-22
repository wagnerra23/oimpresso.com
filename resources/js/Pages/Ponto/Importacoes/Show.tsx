import AppShell from '@/Layouts/AppShell';
import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { AlertTriangle, ArrowLeft, Download, FileUp } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { formatBytes } from '@/Lib/utils';

interface Importacao {
  id: number;
  tipo: string;
  nome_arquivo: string;
  hash_arquivo: string;
  tamanho_bytes: number;
  estado: string;
  linhas_processadas: number;
  linhas_criadas: number;
  linhas_ignoradas: number;
  erro_mensagem: string | null;
  created_at: string | null;
  updated_at: string | null;
  usuario: string | null;
}

interface Props { importacao: Importacao; }

const estadoVariant: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  ESTADO_PENDENTE: 'outline', ESTADO_PROCESSANDO: 'default',
  ESTADO_CONCLUIDO: 'secondary', ESTADO_FALHOU: 'destructive',
};

export default function ImportacoesShow({ importacao: i }: Props) {
  // Polling automático enquanto processando
  useEffect(() => {
    if (i.estado !== 'ESTADO_PROCESSANDO' && i.estado !== 'ESTADO_PENDENTE') return;
    const id = setInterval(() => {
      router.reload({ only: ['importacao'], preserveScroll: true });
    }, 3000);
    return () => clearInterval(id);
  }, [i.estado]);

  return (
    <AppShell
      title={`Importação #${i.id}`}
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Importações', href: '/ponto/importacoes' },
        { label: `#${i.id}` },
      ]}
    >
      <div className="mx-auto max-w-4xl p-6 space-y-4">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <FileUp size={22} /> Importação #{i.id}
            </h1>
            <p className="text-sm text-muted-foreground mt-1 flex items-center gap-2">
              <Badge variant={estadoVariant[i.estado] ?? 'outline'} className="text-[10px]">
                {(i.estado ?? '').replace('ESTADO_', '')}
              </Badge>
              <span>{i.nome_arquivo}</span>
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" asChild>
              <a href="/ponto/importacoes"><ArrowLeft size={14} className="mr-1.5" /> Voltar</a>
            </Button>
            <Button size="sm" variant="outline" asChild>
              <a href={`/ponto/importacoes/${i.id}/original`} target="_blank" rel="noreferrer">
                <Download size={14} className="mr-1.5" /> Baixar original
              </a>
            </Button>
          </div>
        </header>

        {i.erro_mensagem && (
          <Alert variant="destructive">
            <AlertTriangle size={14} />
            <AlertTitle>Erro no processamento</AlertTitle>
            <AlertDescription className="text-xs whitespace-pre-wrap">{i.erro_mensagem}</AlertDescription>
          </Alert>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Arquivo</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <Row label="Nome" mono>{i.nome_arquivo}</Row>
              <Row label="Tipo">
                <Badge variant="outline">{i.tipo}</Badge>
              </Row>
              <Row label="Tamanho" mono>{formatBytes(i.tamanho_bytes)}</Row>
              <Row label="Hash SHA-256" mono>
                <span className="text-[10px] break-all">{i.hash_arquivo}</span>
              </Row>
              <Row label="Enviado por">{i.usuario ?? '—'}</Row>
              <Row label="Criado em">{i.created_at ?? '—'}</Row>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Processamento</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <Row label="Estado">
                <Badge variant={estadoVariant[i.estado] ?? 'outline'}>
                  {(i.estado ?? '').replace('ESTADO_', '')}
                </Badge>
                {(i.estado === 'ESTADO_PROCESSANDO' || i.estado === 'ESTADO_PENDENTE') && (
                  <span className="ml-2 text-xs text-muted-foreground">auto-refresh 3s…</span>
                )}
              </Row>
              <Row label="Linhas processadas" mono>{i.linhas_processadas}</Row>
              <Row label="Marcações criadas" mono>{i.linhas_criadas}</Row>
              <Row label="Linhas ignoradas" mono>{i.linhas_ignoradas}</Row>
              <Row label="Última atualização">{i.updated_at ?? '—'}</Row>
            </CardContent>
          </Card>
        </div>
      </div>
    </AppShell>
  );
}

function Row({ label, children, mono }: { label: string; children: React.ReactNode; mono?: boolean }) {
  return (
    <div className="grid grid-cols-3 gap-2 text-xs">
      <span className="text-muted-foreground">{label}</span>
      <span className={`col-span-2 ${mono ? 'font-mono' : ''}`}>{children}</span>
    </div>
  );
}
