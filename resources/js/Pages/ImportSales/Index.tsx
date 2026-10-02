// ImportSales/Index — Importação de vendas (/import-sales). Thread 05 do playbook venda-menu.
// PT-01 Lista (UI-0013). Golden: Sells/Drafts (lista dual). Protótipo: TelaImportar em
// prototipo-ui/cowork/Wagner/venda-blade.jsx. Alvo medido: governance/design/targets/vendas--importacao--index.
// D2 ([W] 2026-10-02): planilha acima de `limiteSincrono` linhas vai para a fila; esta tela recarrega
// só a prop `estado` enquanto o job anda — sem rota nova. Refs: ADR 0104 (MWART) · ADR 0093.
import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Download, Search, Trash2, Upload } from 'lucide-react';
import { PageHeader } from '@/Components/PageHeader';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';

interface Campo { key: string; label: string; instrucao: string | null }
interface Lote { lote: number; quando: string | null; criadoPor: string; faturas: string[] }
interface Estado {
  estado: 'na_fila' | 'processando' | 'concluido' | 'erro';
  arquivo?: string | null;
  feitas?: number;
  total?: number | null;
  lote?: number;
  mensagem?: string;
  em?: string;
}

export interface ImportSalesIndexProps {
  campos: Campo[];
  lotes?: Lote[]; // deferred
  estado: Estado | null;
  limiteSincrono: number;
  permissions: { importar: boolean; reverter: boolean };
  urls: { preview: string; modelo: string; reverter: string; vendas: string };
}

const PASSOS = [
  'Envie a planilha em Excel (.xlsx) ou CSV, com uma linha por item vendido.',
  'Na prévia, escolha o local do negócio e por qual coluna agrupar as linhas em vendas.',
  'Mapeie as colunas com os campos de venda — a prévia já chega pré-mapeada por semelhança de nome.',
  'Confira as linhas antes de enviar: a venda importada nasce finalizada, baixa estoque e cria o cliente que não existir.',
];

/** A lista de lotes vem inteira do servidor; o DataTable recebe uma página única. */
function paginaUnica<T>(dados: T[]): PaginatorShape<T> {
  return { data: dados, total: dados.length, current_page: 1, last_page: 1, from: dados.length ? 1 : null, to: dados.length || null, links: [] };
}

function andando(e: Estado | null): boolean {
  return e?.estado === 'na_fila' || e?.estado === 'processando';
}

export default function ImportSalesIndex({ campos, lotes, estado, limiteSincrono, permissions, urls }: ImportSalesIndexProps) {
  const { props } = usePage<{ flash?: { error?: string | null } }>();
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [enviando, setEnviando] = useState(false);
  const [busca, setBusca] = useState('');
  const [reverter, setReverter] = useState<Lote | null>(null);

  // D2: enquanto a importação anda na fila, recarrega só o estado (e os lotes, que ganham o novo
  // ao terminar). Recarga parcial do próprio GET /import-sales — nenhuma rota nova.
  useEffect(() => {
    if (!andando(estado)) return undefined;
    const id = window.setInterval(() => {
      router.reload({ only: ['estado', 'lotes'] });
    }, 4000);
    return () => window.clearInterval(id);
  }, [estado]);

  function enviar() {
    if (!arquivo) return;
    setEnviando(true);
    router.post(urls.preview, { sales: arquivo }, {
      forceFormData: true,
      onFinish: () => setEnviando(false),
    });
  }

  const termo = busca.trim().toLowerCase();
  const visiveis = useMemo(() => {
    const todos = lotes ?? [];
    return termo ? todos.filter((l) => `${l.lote} ${l.criadoPor} ${l.faturas.join(' ')}`.toLowerCase().includes(termo)) : todos;
  }, [lotes, termo]);

  const colunas: ColumnDef<Lote>[] = [
    { id: 'lote', header: 'Lote de importação', meta: { mono: true }, cell: ({ row }) => row.original.lote },
    { id: 'quando', header: 'Hora da importação', meta: { mono: true }, cell: ({ row }) => row.original.quando ?? '—' },
    { id: 'criadoPor', header: 'Criado por', cell: ({ row }) => row.original.criadoPor || '—' },
    {
      id: 'faturas', header: 'Faturas',
      cell: ({ row }) => {
        const f = row.original.faturas;
        return (
          <Stack gap={0} align="start">
            <span>{f.slice(0, 3).join(', ')}</span>
            {f.length > 3 && <span className="text-xs text-muted-foreground">e mais {f.length - 3} fatura(s)</span>}
          </Stack>
        );
      },
    },
    {
      id: 'acao', header: 'Ação', meta: { align: 'right' },
      cell: ({ row }) => (permissions.reverter ? (
        <Button variant="ghost" size="sm" onClick={() => setReverter(row.original)} aria-label={`Excluir lote ${row.original.lote}`}>
          <Trash2 className="h-3.5 w-3.5 mr-1" aria-hidden="true" />Excluir lote
        </Button>
      ) : null),
    },
  ];

  return (
    <AppShellV2>
      <Head title="Importação de vendas" />
      <div className="container mx-auto px-6 py-6 space-y-4">
        <div data-contract="cabecalho">
          <PageHeader
            title="Importação de vendas"
            subtitle="Traga vendas de fora por planilha, com prévia e mapeamento de colunas antes de gravar."
            actions={<Button variant="outline" asChild><a href={urls.vendas}>Todas as vendas</a></Button>}
          />
        </div>

        {props.flash?.error && (
          <Alert variant="destructive">
            <AlertTitle>A importação não foi feita</AlertTitle>
            <AlertDescription>{props.flash.error}</AlertDescription>
          </Alert>
        )}

        {estado && (
          <section data-contract="andamento" aria-live="polite">
            {andando(estado) && (
              <Alert>
                <AlertTitle>{estado.estado === 'na_fila' ? 'Importação na fila' : 'Importando vendas…'}</AlertTitle>
                <AlertDescription>
                  {estado.arquivo ? `${estado.arquivo} — ` : ''}
                  {estado.estado === 'na_fila'
                    ? 'o servidor começa em até um minuto. Pode fechar esta tela.'
                    : `${estado.feitas ?? 0} de ${estado.total ?? '?'} venda(s) gravada(s).`}
                </AlertDescription>
              </Alert>
            )}
            {estado.estado === 'concluido' && (
              <Alert>
                <AlertTitle>Importação concluída</AlertTitle>
                <AlertDescription>
                  {estado.arquivo ? `${estado.arquivo} — ` : ''}{estado.feitas ?? 0} venda(s) no lote {estado.lote}.
                </AlertDescription>
              </Alert>
            )}
            {estado.estado === 'erro' && (
              <Alert variant="destructive">
                <AlertTitle>A importação em fila parou — nada foi gravado</AlertTitle>
                <AlertDescription>{estado.arquivo ? `${estado.arquivo}: ` : ''}{estado.mensagem}</AlertDescription>
              </Alert>
            )}
          </section>
        )}

        <section data-contract="enviar" className="rounded-lg border border-border bg-card p-4">
          <Stack gap={3} align="stretch">
            <Inline gap={2} align="center">
              <Upload className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
              <h2 className="text-sm font-semibold">Enviar planilha</h2>
            </Inline>
            <Stack gap={1} align="stretch" className="max-w-md text-xs text-muted-foreground">
              <label htmlFor="import-sales-arquivo">Arquivo para importar</label>
              <Input
                id="import-sales-arquivo"
                variant="shadcn"
                type="file"
                accept=".xlsx,.xls,.csv"
                onChange={(e) => setArquivo(e.target.files?.[0] ?? null)}
              />
            </Stack>
            <Inline gap={2} wrap align="center">
              <span className="text-xs text-muted-foreground">
                {arquivo ? `Selecionado: ${arquivo.name}` : 'Nenhum arquivo escolhido.'}
              </span>
              <span className="text-xs text-muted-foreground">
                Até {limiteSincrono} linhas importa na hora; acima disso vai para a fila e o andamento aparece aqui.
              </span>
              <span className="flex-1" />
              <Button variant="outline" size="sm" asChild>
                <a href={urls.modelo} download><Download className="h-3.5 w-3.5 mr-1" aria-hidden="true" />Baixar arquivo modelo</a>
              </Button>
              <Button size="sm" disabled={!arquivo || enviando} onClick={enviar}>
                {enviando ? 'Enviando…' : 'Enviar e revisar'}
              </Button>
            </Inline>
          </Stack>
        </section>

        <section data-contract="instrucoes" className="rounded-lg border border-border bg-card p-4">
          <Stack gap={3} align="stretch">
            <h2 className="text-sm font-semibold">Instruções</h2>
            <ol className="list-decimal pl-5 text-sm space-y-1">
              {PASSOS.map((p) => <li key={p}>{p}</li>)}
            </ol>
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-xs text-muted-foreground">
                  <th className="py-1 pr-3 font-medium">Campos importáveis</th>
                  <th className="py-1 font-medium">Instruções</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {campos.map((c) => (
                  <tr key={c.key}>
                    <td className="py-1 pr-3 font-medium">{c.label}</td>
                    <td className="py-1 text-muted-foreground">{c.instrucao ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Stack>
        </section>

        <section data-contract="filtros" className="rounded-lg border border-border bg-card p-3">
          <div className="relative max-w-sm">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" aria-hidden="true" />
            <Input variant="shadcn" value={busca} onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar lote, fatura ou usuário…" aria-label="Buscar importações" className="pl-9" />
          </div>
        </section>

        <section data-contract="lista" className="rounded-lg border border-border bg-card overflow-hidden">
          <Inline gap={3} justify="between" className="p-3 border-b border-border">
            <h2 className="text-sm font-semibold">Importações</h2>
            <Badge variant="neutral">{(lotes ?? []).length} lote(s)</Badge>
          </Inline>
          <Deferred data="lotes" fallback={<div className="p-8 text-center text-sm text-muted-foreground">Carregando importações…</div>}>
            {visiveis.length === 0 ? (
              <EmptyState icon="file-text" title={termo ? 'Nenhum lote encontrado' : 'Nenhuma importação ainda'}
                description={termo ? 'Tente outro termo de busca.' : 'Os lotes importados aparecem aqui, do mais novo para o mais velho.'} />
            ) : (
              <DataTable<Lote>
                columns={colunas}
                data={visiveis}
                pagination={paginaUnica(visiveis)}
                endpoint="/import-sales"
                caption="Lotes de importação de vendas"
                showSearch={false}
                rowKey={(l) => l.lote}
              />
            )}
          </Deferred>
        </section>
      </div>

      <AlertDialog open={reverter !== null} onOpenChange={(aberto) => { if (!aberto) setReverter(null); }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir o lote {reverter?.lote}?</AlertDialogTitle>
            <AlertDialogDescription>
              As {reverter?.faturas.length ?? 0} venda(s) do lote são apagadas, o estoque volta e elas saem de
              “Todas as vendas”. Venda com devolução não é apagada. Não dá para desfazer.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Manter</AlertDialogCancel>
            <AlertDialogAction
              onClick={() => {
                if (reverter) router.visit(urls.reverter.replace('{lote}', String(reverter.lote)));
              }}
            >
              Excluir lote
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </AppShellV2>
  );
}
