// Modo Suporte (ADR 0305/0308) — Log de acessos: leitura da trilha append-only
// `support_access_logs` (RF3). PT-01 Lista, variante read-only lean (Header + Tabela +
// EmptyState; sem BulkBar/Drawer/filtros). Fonte visual: `LogAcessos` em
// prototipo-ui/cowork/Wagner/suporte-page.jsx. Lei: Log.charter.md · contrato: Log.casos.md.
//
// A tela NÃO oferece escrita nem correção — não existe rota de escrita e o Model barra
// update/delete. As colunas "Motivo declarado" e "Duração" do protótipo ficam fora: o
// schema não grava nenhum dos dois (ver charter §Lacunas).

import { Deferred, Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Lock } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton';

interface LinhaLog {
  id: number;
  quando: string | null;
  agente: string;
  alvo: string | null;
  acao: string;
  empresa: string;
  empresa_id: number;
}

interface Props {
  logs?: PaginatorShape<LinhaLog>;
}

const fmtQuando = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

/** "Acessou como": quem o agente virou, só leitura (entrou na Visão) ou negado. */
function AcessouComo({ linha }: { linha: LinhaLog }) {
  if (linha.acao === 'negado') {
    return <Badge variant="danger">{linha.alvo ? `negado · ${linha.alvo}` : 'negado'}</Badge>;
  }
  if (linha.acao === 'acessou_como' && linha.alvo) {
    return <Badge variant="warning">{linha.alvo}</Badge>;
  }
  return <span className="text-muted-foreground">só leitura</span>;
}

const colunas: ColumnDef<LinhaLog>[] = [
  {
    id: 'quando',
    header: 'Quando',
    meta: { width: 170, mono: true },
    cell: ({ row }) => fmtQuando(row.original.quando),
  },
  {
    id: 'agente',
    header: 'Agente',
    meta: { width: 170, mono: true },
    cell: ({ row }) => <b>{row.original.agente}</b>,
  },
  {
    id: 'alvo',
    header: 'Acessou como',
    meta: { width: 190 },
    cell: ({ row }) => <AcessouComo linha={row.original} />,
  },
  {
    id: 'empresa',
    header: 'Empresa',
    cell: ({ row }) => (
      <span className="inline-flex items-baseline gap-2">
        <b>{row.original.empresa}</b>
        <small className="font-mono tabular-nums text-muted-foreground">#{row.original.empresa_id}</small>
      </span>
    ),
  },
];

export default function Log({ logs }: Props) {
  const linhas = logs?.data ?? [];

  return (
    <AppShellV2 title="Suporte" breadcrumbItems={[{ label: 'Suporte' }, { label: 'Log de acessos' }]}>
      <Head title="Suporte · log de acessos" />

      <div className="p-6 max-w-6xl mx-auto space-y-5">
        <Link
          href="/suporte/empresas"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
          <ArrowLeft className="size-3.5" aria-hidden /> Suporte · empresas
        </Link>

        <div data-contract="cabecalho">
          <PageHeader
            title="Log de acessos de suporte"
            subtitle="support_access_logs · append-only — não existe caminho de escrita nem de correção"
          />
        </div>

        <div data-contract="log">
          <Deferred data="logs" fallback={<Skeleton className="h-64 w-full" />}>
            {linhas.length === 0 ? (
              <EmptyState
                title="Nenhum acesso de suporte registrado."
                description="Cada entrada numa empresa-cliente e cada “Acessar como” aparecem aqui assim que acontecem."
              />
            ) : (
              <DataTable
                columns={colunas}
                data={linhas}
                pagination={logs}
                endpoint="/suporte/log"
                caption="Log de acessos de suporte"
                rowKey={(l) => l.id}
              />
            )}
          </Deferred>
        </div>

        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Lock className="size-3" aria-hidden />
          Alterar uma linha daqui é incidente P0 (ADR 0084): a tabela é append-only. A tela nem oferece o botão.
        </p>
      </div>
    </AppShellV2>
  );
}
