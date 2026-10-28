// @memcofre
//   tela: /voz-do-cliente
//   module: VozDoCliente
//   stories: thread modulos-faltantes/playbook/03 (executa também telas-soltas/playbook/05)
//   permissao: vozdocliente.triar ou superadmin (decisão PERM-VOZ) — checada no servidor antes da flag
//
// Caixa da Voz do Cliente: o que as pessoas relataram de dentro do sistema, com a tela em que
// aconteceu. PT-01 Lista, variante read-mostly (Header + KPIs + chips de situação + Tabela).
// Charter: ./Caixa.charter.md · Casos: ./Caixa.casos.md · RUNBOOK: memory/requisitos/VozDoCliente/RUNBOOK-caixa.md
// Âncora de design: prototipo-ui/cowork/Wagner/voz-do-cliente-page.jsx → VozDoClientePage()
//
// Só leitura nesta onda: a triagem (Virar US / Fechar sem ação) é o PR seguinte — o "motivo do
// fechamento" do protótipo ainda não tem coluna em voz_sinais. Única tela da rota desde o
// F5 de 2026-10-27 (a Blade e a flag useV2VozDoClienteCaixa saíram).

import { Deferred, Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import KpiCard from '@/Components/shared/KpiCard';
import KpiGrid from '@/Components/shared/KpiGrid';
import { Inline, Stack } from '@/Components/layout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';

type Situacao = 'pending' | 'triaged' | 'closed';

interface Sinal {
  id: number;
  quando: string | null;
  autor: string | null;
  texto: string;
  onde: string | null;
  severidade: number | null;
  status: Situacao;
  us: string | null;
}

interface Props {
  filtro: Situacao | null;
  limiar_alta: number;
  contagem: { total: number; pending: number; triaged: number; closed: number; alta: number };
  sinais?: PaginatorShape<Sinal>;
}

const FILTROS: Array<{ id: Situacao | null; label: string }> = [
  { id: null, label: 'Todos' },
  { id: 'pending', label: 'Pendentes' },
  { id: 'triaged', label: 'Triados' },
  { id: 'closed', label: 'Fechados' },
];

const fmtQuando = (iso: string | null) =>
  iso ? new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : '—';

/** Idade do relato — o de 4 dias não é o de 2 h (charter §Goals, frescor visível). */
function idade(iso: string | null): string {
  if (!iso) return '';
  const h = Math.round((Date.now() - new Date(iso).getTime()) / 36e5);
  if (h < 1) return 'agora';
  if (h < 24) return `há ${h} h`;
  const d = Math.round(h / 24);
  return d === 1 ? 'há 1 dia' : `há ${d} dias`;
}

/** Gravidade auto-declarada 0–4 → Alta/Média/Baixa, a partir do limiar de triagem do módulo. */
function Gravidade({ valor, limiar }: { valor: number | null; limiar: number }) {
  if (valor === null) return <span className="text-muted-foreground">—</span>;
  if (valor >= limiar) return <Badge variant="danger">Alta</Badge>;
  if (valor === limiar - 1) return <Badge variant="warning">Média</Badge>;
  return <Badge variant="neutral">Baixa</Badge>;
}

function SituacaoCelula({ sinal }: { sinal: Sinal }) {
  if (sinal.status === 'pending') return <Badge variant="warning">Pendente</Badge>;
  if (sinal.status === 'triaged') {
    return (
      <Inline gap={2} align="center">
        <Badge variant="info">Triado</Badge>
        {sinal.us && <code className="text-xs">{sinal.us}</code>}
      </Inline>
    );
  }
  return <Badge variant="neutral">Fechado</Badge>;
}

export default function VozDoClienteCaixa({ filtro, limiar_alta, contagem, sinais }: Props) {
  const linhas = sinais?.data ?? [];
  const contadorDe = (id: Situacao | null) => (id === null ? contagem.total : contagem[id]);

  const colunas: ColumnDef<Sinal>[] = [
    {
      id: 'quando',
      header: 'Quando',
      meta: { width: 160 },
      cell: ({ row }) => (
        <Stack gap={0}>
          <b className="font-mono tabular-nums">{fmtQuando(row.original.quando)}</b>
          <small className="text-muted-foreground">{idade(row.original.quando)}</small>
        </Stack>
      ),
    },
    {
      id: 'quem',
      header: 'Quem',
      meta: { width: 140 },
      cell: ({ row }) => row.original.autor || <span className="text-muted-foreground">—</span>,
    },
    { id: 'disse', header: 'O que disse', meta: { width: 320 }, cell: ({ row }) => row.original.texto },
    {
      id: 'onde',
      header: 'Onde',
      meta: { width: 180 },
      cell: ({ row }) =>
        row.original.onde ? (
          <code className="break-all text-xs text-muted-foreground">{row.original.onde}</code>
        ) : (
          <span className="text-muted-foreground">—</span>
        ),
    },
    {
      id: 'sev',
      header: 'Grav.',
      meta: { width: 90 },
      cell: ({ row }) => <Gravidade valor={row.original.severidade} limiar={limiar_alta} />,
    },
    { id: 'situacao', header: 'Situação', meta: { width: 150 }, cell: ({ row }) => <SituacaoCelula sinal={row.original} /> },
  ];

  const filtrar = (id: Situacao | null) =>
    router.get('/voz-do-cliente', id ? { situacao: id } : {}, {
      only: ['sinais', 'filtro'],
      preserveScroll: true,
      preserveState: true,
      replace: true,
    });

  const sub = contagem.total
    ? `${contagem.total} relatos · ${contagem.pending} pendentes`
    : 'nenhum relato ainda';

  return (
    <AppShellV2 title="Voz do Cliente" breadcrumbItems={[{ label: 'Sistema' }, { label: 'Voz do Cliente' }]}>
      <Head title="Voz do Cliente" />

      <div className="p-6 max-w-6xl mx-auto space-y-5">
        <div data-contract="cabecalho">
          <PageHeader title="Voz do Cliente" subtitle={sub} />
          <p className="mt-1 text-sm text-muted-foreground">
            o que as pessoas relataram — de dentro do sistema, com a tela em que aconteceu
          </p>
        </div>

        {contagem.total > 0 && (
          <>
            <KpiGrid cols={4} data-contract="kpis">
              <KpiCard label="Pendentes de triagem" value={contagem.pending} description="ninguém olhou ainda" />
              <KpiCard label="Triados" value={contagem.triaged} description="já viraram US no backlog" />
              <KpiCard label="Fechados" value={contagem.closed} description="resolvidos ou descartados" />
              <KpiCard label="Gravidade alta" value={contagem.alta} description="dor que trava o trabalho" />
            </KpiGrid>

            <nav data-contract="filtros" aria-label="Situação">
              <Inline gap={2} wrap>
                {FILTROS.map((f) => (
                  <Button
                    key={f.label}
                    size="sm"
                    variant={filtro === f.id ? 'default' : 'outline'}
                    aria-pressed={filtro === f.id}
                    onClick={() => filtrar(f.id)}
                  >
                    {f.label}
                    <span className="ml-1 font-mono tabular-nums">{contadorDe(f.id)}</span>
                  </Button>
                ))}
              </Inline>
            </nav>
          </>
        )}

        <div data-contract="lista">
          {contagem.total === 0 ? (
            <EmptyState
              title="Nenhum relato ainda."
              description="Quando alguém relatar um problema por dentro do sistema, ele aparece aqui — com a tela em que aconteceu."
            />
          ) : (
            <Deferred data="sinais" fallback={<Skeleton className="h-64 w-full" />}>
              {linhas.length === 0 ? (
                <EmptyState title="Nada nesta situação." description="Troque o filtro acima para ver os outros relatos." />
              ) : (
                <DataTable
                  columns={colunas}
                  data={linhas}
                  pagination={sinais}
                  caption="Relatos da Voz do Cliente"
                  rowKey={(s) => s.id}
                  rowState={(s) =>
                    s.status === 'pending' && s.severidade !== null && s.severidade >= limiar_alta
                      ? 'urgent'
                      : s.status === 'closed'
                        ? 'archived'
                        : undefined
                  }
                />
              )}
            </Deferred>
          )}
        </div>

        <p className="text-xs text-muted-foreground">
          O relato entra por dentro do sistema e guarda a tela de origem; não abre chamado nem notifica ninguém. A
          triagem é manual.
        </p>
      </div>
    </AppShellV2>
  );
}
