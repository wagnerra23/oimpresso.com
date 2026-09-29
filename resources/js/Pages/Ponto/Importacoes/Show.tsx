// @docvault
//   tela: /ponto/importacoes/show
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-011
//   rules: R-PONT-001
//   adrs: tech/0001
//   tests: Modules/PontoWr2/Tests/Feature/ImportacoesShowTest

import AppShellV2 from '@/Layouts/AppShellV2';
import PontoAreaHeader from '@/Pages/Ponto/_shared/PontoAreaHeader';
import { Inline } from '@/Components/layout';
import { Head, router } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
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
  erros_amostra: ErroAmostra[];
  created_at: string | null;
  updated_at: string | null;
  usuario: string | null;
}

interface ErroAmostra {
  linha: number | null;
  nsr: number | null;
  tipo: string | null;
  erro: string;
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
    <>
      <Head title={`Importação #${i.id}`} />
      <div className="mx-auto max-w-4xl p-6 space-y-4">
        {/* ADR 0182 PageHeader canon — Wave Ponto 2026-05-22 */}
        <PontoAreaHeader active="importacoes" />
        <Inline justify="between" align="center" gap={3}>
          <div>
            <h2 className="text-lg font-semibold">Importação #{i.id} <span className="text-stone-400 font-normal">· AFD</span></h2>
            <p className="text-sm text-muted-foreground flex items-center gap-2">
              <Badge variant={estadoVariant[i.estado] ?? 'outline'} className="text-[10px]">
                {(i.estado ?? '').replace('ESTADO_', '')}
              </Badge>
              <span>{i.nome_arquivo}</span>
            </p>
          </div>
          <Inline gap={2} align="center">
            <Button variant="outline" size="sm" asChild>
              <a href="/ponto/importacoes"><ArrowLeft size={14} className="mr-1.5" /> Voltar</a>
            </Button>
            <Button size="sm" variant="outline" asChild>
              <a href={`/ponto/importacoes/${i.id}/original`} target="_blank" rel="noreferrer">
                <Download size={14} className="mr-1.5" /> Baixar original
              </a>
            </Button>
          </Inline>
        </Inline>

        {i.erro_mensagem && (
          <Alert variant="destructive">
            <AlertTriangle size={14} />
            <AlertTitle>Erro no processamento</AlertTitle>
            <AlertDescription className="text-xs whitespace-pre-wrap">{i.erro_mensagem}</AlertDescription>
          </Alert>
        )}

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <Card data-contract="importacoes-dados-do-arquivo">
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

          <Card data-contract="importacoes-resumo-do-processamento">
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

        {/* D-IMP-EXTRAS ([W] 2026-09-14): por que as linhas falharam, sem abrir o .txt.
            Decide pelo TAMANHO — [] é truthy em JS (UC-IMPSH-05). UC-IMPSH-06. */}
        {(i.erros_amostra ?? []).length > 0 && (
          <Card data-contract="importacoes-amostra-de-erros">
            <CardHeader>
              {/* Forma do protótipo (ponto-telas.jsx, card "Amostra de erros"): título + "(N primeiros)". */}
              <CardTitle className="text-base">
                Amostra de erros{' '}
                <span className="text-xs font-normal text-muted-foreground">({i.erros_amostra.length} primeiros)</span>
              </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead className="border-b border-border bg-muted/30 text-muted-foreground">
                    <tr>
                      <th className="text-left p-3 font-medium">Linha</th>
                      <th className="text-left p-3 font-medium">NSR</th>
                      <th className="text-left p-3 font-medium">Tipo</th>
                      <th className="text-left p-3 font-medium">Mensagem</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-border">
                    {i.erros_amostra.map((e, k) => (
                      <tr key={k}>
                        <td className="p-3 font-mono">{e.linha ?? '—'}</td>
                        <td className="p-3 font-mono">{e.nsr ?? '—'}</td>
                        <td className="p-3 font-mono">{e.tipo ?? '—'}</td>
                        <td className="p-3">{e.erro}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </>
  );
}

ImportacoesShow.layout = (page: ReactNode) => (
  <AppShellV2 breadcrumbItems={[
    { label: 'Ponto WR2' },
    { label: 'Importações', href: '/ponto/importacoes' },
  ]}>
    {page}
  </AppShellV2>
);

function Row({ label, children, mono }: { label: string; children: React.ReactNode; mono?: boolean }) {
  return (
    <div className="grid grid-cols-3 gap-2 text-xs">
      <span className="text-muted-foreground">{label}</span>
      <span className={`col-span-2 ${mono ? 'font-mono' : ''}`}>{children}</span>
    </div>
  );
}
