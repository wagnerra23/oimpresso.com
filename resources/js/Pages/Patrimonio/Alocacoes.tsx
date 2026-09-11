// @patrimonio
//   tela: /asset/allocation  (rota `allocation.index` — a URL NÃO mudou na migração)
//   modulo: Modules/AssetManagement  (NÃO existe Modules/Patrimonio — ADR 0394)
//   adrs: 0394 (endereço de UI do Patrimônio), 0104 (MWART), 0093 (multi-tenant Tier 0),
//         0180 (sidebar v3 · ghosts), 0253 (primitivos de layout)
//   runbook: memory/requisitos/AssetManagement/RUNBOOK-alocacoes.md
//   charter: ./Alocacoes.charter.md · casos: ./Alocacoes.casos.md
//   fonte visual: prototipo-ui/cowork/Wagner/patrimonio-page.jsx (aba `alocacoes`, :409) — ALVO,
//                 não decisão de produto (`06-ui-bloqueada.md`)
//
// TERCEIRA tela Inertia do módulo. Ela REUSA o `_shared/PatrimonioSubNav` que a Bens fundou.
//
// ONDA 1 — leitura pura. O que ela adia e por quê está no §5 do RUNBOOK e nos Non-Goals do
// charter. Os três que mais saltam ao comparar com o protótipo lado a lado:
//
//   • sem o rodapé "N unidades alocadas" — é SOMA DE QUANTIDADE, e quantidade é REGRA MESTRE
//     Tier 0 (prova por dois caminhos + antes→depois pro [W]). O número POR LINHA entra;
//   • sem contagem nas pílulas das sub-abas — contar sobre o conjunto pede agregação extra, e
//     contar a página corrente faria a pílula dizer "4" olhando 25 de N linhas;
//   • sem avatar/papel de quem recebeu — `users` não tem "papel na alocação"; o protótipo
//     desenha um campo que o modelo não tem.
//
// ─── Por que NÃO há botão de editar/alocar/excluir por linha ─────────────────────────
//
// MEDIDO, não presumido: `AssetAllocationController::{create,edit}` só respondem sob
// `request()->ajax()` (`:170`, `:243`) — fora dele o método cai no fim e devolve corpo VAZIO.
// E as views (`asset_allocation/{create,edit}.blade.php`) são FRAGMENTOS de modal jQuery
// (`<div class="modal-dialog">`, zero `@extends`): não sobrevivem a uma navegação direta.
// Um `<a href="/asset/allocation/5/edit">` levaria a uma página em branco — afordância falsa,
// que é o que o charter proíbe. Trazer esses formulários pra cá significa convertê-los em
// drawer React, e isso é ESCRITA DE QUANTIDADE: REGRA MESTRE Tier 0, outra onda.
//
// O que a tela oferece no lugar é o caminho que de fato funciona: **Devoluções**, que é aba
// PRÓPRIA (`/asset/revocation`, ghost `revocation`) e cujo `index()` devolve view de verdade
// (`RevokeAllocatedAssetController:108`, sem gate de ajax) — medido.
//
// Layout por PRIMITIVOS (ADR 0253) — `Stack`/`Inline`, nunca `<div className="flex gap-4">`
// solto; o `layout-primitives-guard` é catraca e reprova adotante novo.

import { Deferred, router } from '@inertiajs/react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type EstadoDaLinha } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Segmented } from '@/Components/ui/segmented';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack, Inline } from '@/Components/layout';
import PatrimonioSubNav from './_shared/PatrimonioSubNav';
import type { ColumnDef } from '@tanstack/react-table';

/* ─── Contrato com o backend ──────────────────────────────────────────────────── */

/** Situação da linha — decidida no SERVIDOR (§9 do RUNBOOK), nunca derivada aqui. */
type SituacaoLinha = 'em_uso' | 'parcial' | 'devolvida';

interface Alocacao {
  id: number;
  ref_no: string;
  bem: string | null;
  modelo: string | null;
  categoria: string | null;
  recebido_por: string;
  alocado_por: string;
  quantidade: number;
  /** ⚠️ Herda o resíduo Tier 0 do §9 do RUNBOOK: o `leftJoin` de `PT` não filtra
   *  `business_id`, então este número agrega devolução de qualquer empresa quando a
   *  pré-condição existe. NÃO é número auditado até a thread dona fechar. Continua na tela
   *  porque o Blade já o mostrava — escondê-lo seria regressão e não consertaria o dado. */
  devolvido: number;
  alocado_em: string | null;
  prazo: string | null;
  motivo: string | null;
  situacao: SituacaoLinha;
  /** Comparação de data feita no servidor, com o relógio dele. */
  vencido: boolean;
}

interface Paginator<T> {
  data: T[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface FiltrosAtivos {
  q?: string | null;
  situacao?: string | null;
}

interface Props {
  /** Deferida — ausente no primeiro paint, por isso opcional. */
  alocacoes?: Paginator<Alocacao>;
  filtros: FiltrosAtivos;
  permissoes: {
    alocar: boolean;
    editar: boolean;
    excluir: boolean;
    devolver: boolean;
  };
}

/* ─── Formatação ──────────────────────────────────────────────────────────────── */

// Mesmo formatador que a irmã Bens usa — locale da casa, sem lib nova. Até 4 casas porque
// `asset_transactions.quantity` é DECIMAL(22,4).
function quantidade(valor: number): string {
  return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(valor ?? 0);
}

function SemValor() {
  return <span className="text-muted-foreground">—</span>;
}

/* ─── Selo de situação ────────────────────────────────────────────────────────── */

/**
 * Traduz a situação que o SERVIDOR decidiu. Não recalcula nada: `vencido` já chegou pronto
 * (a comparação com hoje é do relógio do servidor — fazê-la aqui faria o veredito depender
 * do fuso da máquina de quem olha).
 */
function SeloSituacao({ alocacao }: { alocacao: Alocacao }) {
  if (alocacao.situacao === 'devolvida') {
    return <Badge variant="neutral" dot>Devolvida</Badge>;
  }

  if (alocacao.vencido) {
    return <Badge variant="danger" dot>Prazo vencido</Badge>;
  }

  if (alocacao.situacao === 'parcial') {
    return <Badge variant="warning" dot>Devolvida em parte</Badge>;
  }

  return <Badge variant="success" dot>Em uso</Badge>;
}

/* ─── Colunas ─────────────────────────────────────────────────────────────────── */

// A ordem espelha o protótipo (`:422`): a tabela é mais larga que a janela, e informação de
// identificação precisa nascer à esquerda, dentro do viewport.
function colunas(): ColumnDef<Alocacao>[] {
  return [
    {
      id: 'ref_no',
      header: 'Código',
      accessorFn: (a) => a.ref_no,
      cell: ({ row }) => <span className="font-medium">{row.original.ref_no}</span>,
      meta: { width: 130, mono: true },
    },
    {
      id: 'bem',
      header: 'Bem',
      accessorFn: (a) => a.bem,
      cell: ({ row }) => {
        const a = row.original;
        return (
          <Stack gap={0}>
            {/* `break-words` e não `truncate`: sob largura fixa a célula não cresce, e o nome
                do bem é a informação principal da linha — escondê-lo em reticências troca um
                problema de layout por um de leitura. */}
            <span className="font-medium break-words">{a.bem ?? '—'}</span>
            {a.modelo ? <small className="text-muted-foreground">{a.modelo}</small> : null}
          </Stack>
        );
      },
      meta: { width: 230 },
    },
    {
      id: 'recebido_por',
      header: 'Alocado a',
      accessorFn: (a) => a.recebido_por,
      cell: ({ row }) => {
        const nome = row.original.recebido_por;
        return nome ? <span className="break-words">{nome}</span> : <SemValor />;
      },
      meta: { width: 190 },
    },
    {
      id: 'alocado_em',
      header: 'Alocado em',
      accessorFn: (a) => a.alocado_em,
      cell: ({ row }) => row.original.alocado_em ?? <SemValor />,
      meta: { width: 140, mono: true },
    },
    {
      id: 'prazo',
      header: 'Até',
      cell: ({ row }) =>
        row.original.prazo ?? <span className="text-muted-foreground">indeterminado</span>,
      meta: { width: 120, mono: true },
    },
    {
      id: 'quantidade',
      header: 'Qtd',
      accessorFn: (a) => a.quantidade,
      cell: ({ row }) => quantidade(row.original.quantidade),
      meta: { width: 80, align: 'right', mono: true },
    },
    {
      id: 'devolvido',
      header: 'Devolvido',
      cell: ({ row }) => {
        const a = row.original;
        return (
          <span className={a.devolvido ? 'font-semibold' : 'text-muted-foreground'}>
            {quantidade(a.devolvido)}
          </span>
        );
      },
      meta: { width: 106, align: 'right', mono: true },
    },
    {
      id: 'situacao',
      header: 'Situação',
      cell: ({ row }) => <SeloSituacao alocacao={row.original} />,
      meta: { width: 170 },
    },
    {
      id: 'categoria',
      header: 'Categoria',
      accessorFn: (a) => a.categoria,
      cell: ({ row }) => row.original.categoria ?? <SemValor />,
      meta: { width: 140 },
    },
    {
      id: 'alocado_por',
      header: 'Entregue por',
      accessorFn: (a) => a.alocado_por,
      cell: ({ row }) => {
        const nome = row.original.alocado_por;
        return nome ? <span className="break-words">{nome}</span> : <SemValor />;
      },
      meta: { width: 180 },
    },
    {
      id: 'motivo',
      header: 'Motivo',
      accessorFn: (a) => a.motivo,
      cell: ({ row }) => {
        const motivo = row.original.motivo;
        return motivo ? <span className="break-words">{motivo}</span> : <SemValor />;
      },
      meta: { width: 220 },
    },
  ];
}

/* ─── Recorte por situação ────────────────────────────────────────────────────── */

const SITUACOES = [
  { value: 'ativas', label: 'Ativas' },
  { value: 'devolvidas', label: 'Devolvidas' },
  { value: 'todas', label: 'Todas' },
];

/** Tira nulo/vazio pra não empurrar `?q=` vazio na URL. */
function limpar(filtros: FiltrosAtivos): Record<string, string> {
  const saida: Record<string, string> = {};
  for (const [chave, valor] of Object.entries(filtros)) {
    if (valor !== null && valor !== undefined && valor !== '') saida[chave] = String(valor);
  }
  return saida;
}

function BarraDeFiltros({ filtros }: { filtros: FiltrosAtivos }) {
  const situacao = filtros.situacao ?? 'ativas';

  // O recorte vive na URL: link compartilhável e botão voltar honesto. `only` recarrega só a
  // prop deferida — o resto da página não repinta.
  function navegar(valor: string) {
    router.get('/asset/allocation', limpar({ ...filtros, situacao: valor }), {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['alocacoes', 'filtros'],
    });
  }

  return (
    <Inline gap={3} align="center" wrap>
      {/* Sem contagem nas opções, de propósito: contar sobre o conjunto pede agregação extra,
          e contar a página corrente faria a pílula mentir (§5 do RUNBOOK). */}
      <Segmented
        aria-label="Situação das alocações"
        value={situacao}
        onValueChange={navegar}
        options={SITUACOES}
      />
    </Inline>
  );
}

/* ─── Tela ────────────────────────────────────────────────────────────────────── */

function EsqueletoTabela() {
  return (
    <Stack gap={2}>
      <Skeleton className="h-9 w-full" />
      {Array.from({ length: 8 }).map((_, i) => (
        <Skeleton key={i} className="h-11 w-full" />
      ))}
    </Stack>
  );
}

export default function Alocacoes({ alocacoes, filtros, permissoes }: Props) {
  // Distingue "não há alocação nenhuma" de "não há alocação PARA ESTE RECORTE" — são dois
  // vazios diferentes. O recorte default é `ativas`, então uma casa que já devolveu tudo cai
  // no vazio filtrado, e não no absoluto: oferecer "registre a primeira" ali seria mentira.
  const situacao = filtros.situacao ?? 'ativas';
  const temFiltroAtivo = Boolean(filtros.q) || situacao !== 'todas';

  return (
    <AppShellV2>
      <Stack gap={4}>
        {/* As âncoras `data-contract` são a ponte Cowork-CSS ↔ Tailwind do gate
            `contrato-de-tela.mjs` (ADR 0286) — mesmo padrão da irmã Bens (`Bens.tsx:532`).
            Quem as consome: `governance/design/contracts/patrimonio-alocacoes.contract.json`. */}
        <div data-contract="cabecalho">
          <PageHeader
            title="Alocações"
            subtitle="O que está na mão de quem — desde quando, até quando, e o que já voltou"
            actions={
              permissoes.devolver ? (
                // Navegação para a aba PRÓPRIA de Devoluções. É o único caminho de escrita que
                // sobrevive fora do modal jQuery — medido (`RevokeAllocatedAssetController:108`
                // devolve view sem gate de ajax, ao contrário de `create`/`edit` daqui).
                <Button size="sm" variant="outline" asChild>
                  <a href="/asset/revocation">Devoluções</a>
                </Button>
              ) : undefined
            }
          />
        </div>

        {/* `hidePrimary`: o primary do menu do módulo já aparece no header das telas irmãs —
            repeti-lo aqui daria dois botões concorrentes na mesma faixa. */}
        <div data-contract="subnav">
          <PatrimonioSubNav active="allocation" hidePrimary />
        </div>

        <div data-contract="filtros">
          <BarraDeFiltros filtros={filtros} />
        </div>

        <div data-contract="tabela">
          <Deferred data="alocacoes" fallback={<EsqueletoTabela />}>
            {alocacoes && alocacoes.data.length === 0 && !temFiltroAtivo ? (
              <EmptyState
                icon="boxes"
                title="Nenhuma alocação registrada"
                description="Quando um bem do patrimônio for entregue a alguém, a entrega aparece aqui — com prazo, quantidade e o que já voltou."
              />
            ) : alocacoes ? (
              <DataTable<Alocacao>
                columns={colunas()}
                data={alocacoes.data}
                pagination={alocacoes}
                endpoint="/asset/allocation"
                caption="Alocações do patrimônio"
                filters={limpar(filtros)}
                initialSearch={filtros.q ?? ''}
                searchPlaceholder="Buscar por código, bem, modelo ou pessoa..."
                emptyMessage="Nenhuma alocação para esses filtros — tente outro recorte ou limpe a busca."
                rowKey={(a) => a.id}
                rowState={(a): EstadoDaLinha | undefined =>
                  a.situacao === 'devolvida' ? 'archived' : a.vencido ? 'urgent' : undefined
                }
                minTableWidth={1500}
              />
            ) : null}
          </Deferred>
        </div>
      </Stack>
    </AppShellV2>
  );
}
