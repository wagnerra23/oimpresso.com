// @patrimonio
//   tela: /asset/assets  (rota `assets.index` — a URL NÃO mudou na migração)
//   modulo: Modules/AssetManagement  (NÃO existe Modules/Patrimonio — ADR 0394)
//   adrs: 0394 (endereço de UI do Patrimônio), 0104 (MWART), 0093 (multi-tenant Tier 0),
//         0180 (sidebar v3 · ghosts), 0253 (primitivos de layout)
//   runbook: memory/requisitos/AssetManagement/RUNBOOK-bens.md
//   charter: ./Bens.charter.md · casos: ./Bens.casos.md
//   fonte visual: prototipo-ui/cowork/patrimonio-page.jsx (aba `bens`, :355) — ALVO, não
//                 decisão de produto (`06-ui-bloqueada.md`)
//
// PRIMEIRA tela Inertia do módulo. Ela funda `_shared/` — as outras 6 herdam.
//
// ONDA 1 — o que a tela faz e o que ela deliberadamente adia está no §5 do RUNBOOK e nos
// Non-Goals do charter, com o motivo de cada um. Os três que mais saltam ao comparar com o
// protótipo lado a lado:
//
//   • sem os sub-recortes "Garantia crítica" / "Em manutenção" — pedem predicado SQL novo;
//     filtrar só a página corrente faria a pílula dizer "3" olhando 25 de N linhas;
//   • sem o total somado do rodapé — é número de VALOR, e valor exige a REGRA MESTRE
//     (prova por dois caminhos + antes→depois pro [W]). O valor POR LINHA entra, que é o
//     que o Blade já mostrava;
//   • sem seleção em lote — as duas ações em lote do protótipo não têm endpoint hoje.
//
// Nada disso é regressão vs. o Blade: tudo que a tela legada mostrava está aqui, incluindo
// as quatro ações de linha.
//
// Layout por PRIMITIVOS (ADR 0253) — `Stack`/`Inline`, nunca `<div className="flex gap-4">`
// solto; o `layout-primitives-guard` é catraca e reprova adotante novo.

import { Deferred, router } from '@inertiajs/react';
import { Eye, Pencil, Plus, Trash2, Wrench } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type EstadoDaLinha } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack, Inline } from '@/Components/layout';
import PatrimonioSubNav from './_shared/PatrimonioSubNav';
import type { ColumnDef } from '@tanstack/react-table';

/* ─── Contrato com o backend ──────────────────────────────────────────────────── */

interface Garantia {
  inicio: string;
  fim: string;
  dias_restantes: number;
}

interface Bem {
  id: number;
  asset_code: string;
  nome: string;
  modelo: string | null;
  categoria: string | null;
  local: string | null;
  quantidade: number;
  /** ⚠️ alocado − revogado. Herda o resíduo Tier 0 do §9 do RUNBOOK (as duas agregações do
   *  `index()` não filtram por `business_id`) — NÃO é número auditado até a thread dona do
   *  gêmeo fechar. Continua na tela porque o Blade já o mostrava: escondê-lo seria regressão
   *  e não consertaria o dado. */
  alocado: number;
  alocavel: boolean;
  valor_unitario: number;
  compra_em: string | null;
  garantia: Garantia | null;
  em_manutencao: number;
  imagem_url: string | null;
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
  location_id?: string | number | null;
  category_id?: string | number | null;
  purchase_type?: string | null;
  is_allocatable?: string | number | null;
  sort?: string | null;
  dir?: string | null;
}

interface Props {
  /** Deferida (contador da aba). Ausente no primeiro paint -- o pill so aparece depois. */
  abas_contadores?: Record<string, number> | null;
  /** Deferida — ausente no primeiro paint, por isso opcional. */
  bens?: Paginator<Bem>;
  filtros: FiltrosAtivos;
  opcoes: {
    locais: Record<string, string>;
    categorias: Record<string, string>;
    tipos_compra: Record<string, string>;
  };
  permissoes: {
    criar: boolean;
    editar: boolean;
    excluir: boolean;
    manutencao: boolean;
  };
}

/* ─── Formatação ──────────────────────────────────────────────────────────────── */

// Mesmo par que `Manufacturing/Index.tsx` usa — locale da casa, sem lib nova.
function moeda(valor: number): string {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(valor ?? 0);
}

function quantidade(valor: number): string {
  return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 4 }).format(valor ?? 0);
}

/* ─── Células ─────────────────────────────────────────────────────────────────── */

function SemValor() {
  return <span className="text-muted-foreground">—</span>;
}

/**
 * Selo de garantia — a mesma leitura do Blade (`in_warranty` / `not_in_warranty` + janela e
 * dias restantes). Sem faixa de criticidade: "garantia crítica" é recorte que ainda não
 * existe no servidor, e derivá-lo aqui seria inventar decisão de produto (§5 do RUNBOOK).
 */
function SeloGarantia({ garantia }: { garantia: Garantia | null }) {
  if (!garantia) return <Badge variant="neutral" dot>Sem garantia vigente</Badge>;
  return (
    <Stack gap={0}>
      <Badge variant="success" dot>Em garantia</Badge>
      <small className="text-muted-foreground">
        {garantia.inicio} ~ {garantia.fim} · {garantia.dias_restantes} dia
        {garantia.dias_restantes === 1 ? '' : 's'}
      </small>
    </Stack>
  );
}

function IconeAcao({
  href,
  titulo,
  perigo,
  children,
}: {
  href: string;
  titulo: string;
  perigo?: boolean;
  children: React.ReactNode;
}) {
  return (
    <a
      href={href}
      title={titulo}
      onClick={(e) => e.stopPropagation()}
      className={
        'inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent hover:border-border ' +
        (perigo ? 'text-destructive hover:bg-destructive/10' : 'text-muted-foreground hover:text-foreground')
      }
    >
      {children}
      <span className="sr-only">{titulo}</span>
    </a>
  );
}

function BotaoAcao({
  titulo,
  perigo,
  onClick,
  children,
}: {
  titulo: string;
  perigo?: boolean;
  onClick: () => void;
  children: React.ReactNode;
}) {
  return (
    <button
      type="button"
      title={titulo}
      onClick={(e) => {
        e.stopPropagation();
        onClick();
      }}
      className={
        'inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent hover:border-border ' +
        (perigo ? 'text-destructive hover:bg-destructive/10' : 'text-muted-foreground hover:text-foreground')
      }
    >
      {children}
      <span className="sr-only">{titulo}</span>
    </button>
  );
}

/**
 * Ações por linha — as MESMAS quatro do Blade, cada uma pra rota que já existe. A ordem é a
 * do protótipo (`:305`): alocar · manutenção · editar · excluir, destrutiva por último.
 *
 * Excluir é a única escrita da tela e usa `router.delete` com confirmação: o `destroy` é uma
 * rota `resource` (verbo DELETE), então link `<a>` não a alcançaria — botão que parece
 * excluir e não exclui é afordância falsa. Tirá-la seria regressão vs. o Blade, que a tinha.
 *
 * `stopPropagation` em todas porque a linha inteira é clicável.
 */
function AcoesDaLinha({ bem, permissoes }: { bem: Bem; permissoes: Props['permissoes'] }) {
  const excluir = () => {
    // Nome do bem no texto: confirmação genérica ("Tem certeza?") não deixa o usuário
    // perceber que clicou na linha errada.
    if (!window.confirm(`Excluir o bem "${bem.nome}" (${bem.asset_code})? Esta ação não pode ser desfeita.`)) {
      return;
    }
    router.delete(`/asset/assets/${bem.id}`, { preserveScroll: true });
  };

  return (
    <Inline gap={1}>
      {bem.alocavel && bem.quantidade - bem.alocado > 0 ? (
        <IconeAcao href={`/asset/allocation/create?asset_id=${bem.id}`} titulo={`Alocar recurso — ${bem.nome}`}>
          <Plus size={14} aria-hidden="true" />
        </IconeAcao>
      ) : null}

      {permissoes.manutencao ? (
        <IconeAcao
          href={`/asset/asset-maintenance/create?asset_id=${bem.id}`}
          titulo={`Enviar pra manutenção — ${bem.nome}`}
        >
          <Wrench size={14} aria-hidden="true" />
        </IconeAcao>
      ) : null}

      {permissoes.editar ? (
        <IconeAcao href={`/asset/assets/${bem.id}/edit`} titulo={`Editar — ${bem.nome}`}>
          <Pencil size={14} aria-hidden="true" />
        </IconeAcao>
      ) : null}

      {permissoes.excluir ? (
        <BotaoAcao titulo={`Excluir — ${bem.nome}`} perigo onClick={excluir}>
          <Trash2 size={14} aria-hidden="true" />
        </BotaoAcao>
      ) : null}
    </Inline>
  );
}

/* ─── Colunas ─────────────────────────────────────────────────────────────────── */

// GEOMETRIA declarada (`meta.width`): uma largura declarada põe a tabela em
// `table-layout: fixed`, e é assim que a rolagem horizontal do wrapper passa a funcionar em
// vez de espremer coluna. `nome` fica SEM largura de propósito — é a fluida, que absorve a
// sobra. `minTableWidth` = 1280, o monitor do piloto.
function colunas(permissoes: Props['permissoes']): ColumnDef<Bem, unknown>[] {
  return [
    {
      id: 'acoes',
      header: 'Ações',
      // A ação vem primeiro, como no protótipo (`:284`): a tabela é mais larga que a janela
      // e o botão primário não pode nascer fora do viewport.
      cell: ({ row }) => <AcoesDaLinha bem={row.original} permissoes={permissoes} />,
      meta: { width: 148 },
    },
    {
      id: 'asset_code',
      header: 'Código',
      accessorFn: (b) => b.asset_code,
      cell: ({ row }) => row.original.asset_code,
      meta: { width: 116, mono: true },
    },
    {
      id: 'imagem',
      header: 'Imagem',
      cell: ({ row }) =>
        row.original.imagem_url ? (
          <a
            href={row.original.imagem_url}
            target="_blank"
            rel="noopener noreferrer"
            title={`Ver imagem de ${row.original.nome}`}
            onClick={(e) => e.stopPropagation()}
            className="inline-flex items-center justify-center text-muted-foreground hover:text-foreground"
          >
            <Eye size={15} aria-hidden="true" />
            <span className="sr-only">Ver imagem de {row.original.nome}</span>
          </a>
        ) : (
          <SemValor />
        ),
      meta: { width: 78, align: 'center' },
    },
    {
      id: 'nome',
      header: 'Bem',
      accessorFn: (b) => b.nome,
      cell: ({ row }) => {
        const b = row.original;
        return (
          <Stack gap={0}>
            {/* `break-words` e não `truncate`: sob `table-layout: fixed` a célula não cresce,
                e o nome é a informação principal da linha — escondê-lo em reticências troca
                um problema de layout por um de leitura. */}
            <span className="font-medium break-words">{b.nome}</span>
            {b.modelo ? <small className="text-muted-foreground">{b.modelo}</small> : null}
          </Stack>
        );
      },
    },
    {
      id: 'categoria',
      header: 'Categoria',
      accessorFn: (b) => b.categoria,
      cell: ({ row }) => row.original.categoria ?? <SemValor />,
      meta: { width: 140 },
    },
    {
      id: 'local',
      header: 'Local',
      accessorFn: (b) => b.local,
      cell: ({ row }) => row.original.local ?? <SemValor />,
      meta: { width: 136 },
    },
    {
      id: 'quantidade',
      header: 'Qtd',
      accessorFn: (b) => b.quantidade,
      cell: ({ row }) => quantidade(row.original.quantidade),
      meta: { width: 84, align: 'right', mono: true },
    },
    {
      id: 'alocado',
      header: 'Alocado',
      cell: ({ row }) => {
        const b = row.original;
        if (!b.alocavel) return <SemValor />;
        return (
          <span className={b.alocado ? 'font-semibold' : 'text-muted-foreground'}>
            {quantidade(b.alocado)}
          </span>
        );
      },
      meta: { width: 92, align: 'right', mono: true },
    },
    {
      id: 'valor_unitario',
      header: 'Valor unitário',
      accessorFn: (b) => b.valor_unitario,
      // Valor POR LINHA, como o Blade fazia. O total somado é outra onda — §5 do RUNBOOK.
      cell: ({ row }) => moeda(row.original.valor_unitario),
      meta: { width: 132, align: 'right', mono: true },
    },
    {
      id: 'garantia',
      header: 'Garantia',
      cell: ({ row }) => <SeloGarantia garantia={row.original.garantia} />,
      meta: { width: 190 },
    },
    {
      id: 'compra_em',
      header: 'Compra',
      accessorFn: (b) => b.compra_em,
      cell: ({ row }) => row.original.compra_em ?? <SemValor />,
      meta: { width: 110, mono: true },
    },
    {
      id: 'situacao',
      header: 'Situação',
      cell: ({ row }) => {
        const n = row.original.em_manutencao;
        return n > 0 ? (
          <Badge variant="warning" dot>{n === 1 ? '1 em manutenção' : `${n} em manutenção`}</Badge>
        ) : (
          <Badge variant="success" dot>Operando</Badge>
        );
      },
      meta: { width: 150 },
    },
  ];
}

/* ─── Filtros ─────────────────────────────────────────────────────────────────── */

/** Tira nulo/vazio pra não empurrar `?location_id=` vazio na URL. */
function limpar(filtros: FiltrosAtivos): Record<string, string> {
  const saida: Record<string, string> = {};
  for (const [chave, valor] of Object.entries(filtros)) {
    if (valor !== null && valor !== undefined && valor !== '') saida[chave] = String(valor);
  }
  return saida;
}

/**
 * Sentinela do "todas" — o Radix `Select` NAO aceita `<SelectItem value="">`: string vazia e
 * o valor que ele usa internamente para "nada selecionado", e passa-la explicitamente quebra
 * o componente (§5 2026-06-29). Mesmo motivo do `ALL` que `Auditoria/Index.tsx` ja usa.
 */
const TODAS = '__todas__';

function SelectFiltro({
  rotulo,
  valor,
  opcoes,
  onChange,
}: {
  rotulo: string;
  valor: string | number | null | undefined;
  opcoes: Record<string, string>;
  onChange: (valor: string) => void;
}) {
  const campoId = 'filtro-' + rotulo.toLowerCase().replace(/[^a-z]+/g, '-');

  return (
    <Inline gap={1} align="center">
      {/* O `<label>` deixa de ENVOLVER o controle porque o Radix renderiza um botao mais um
          portal; o vinculo passa a ser explicito por `id`/`htmlFor`, que e o que o leitor de
          tela de fato le. */}
      <label htmlFor={campoId} className="text-sm text-muted-foreground">
        {rotulo}
      </label>
      <Select
        value={valor != null && valor !== '' ? String(valor) : TODAS}
        onValueChange={(v) => onChange(v === TODAS ? '' : v)}
      >
        <SelectTrigger id={campoId} className="w-40">
          <SelectValue placeholder="todas" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={TODAS}>todas</SelectItem>
          {Object.entries(opcoes)
            // Chave vazia fora: alem de colidir com o "todas" acima, `value=""` num
            // `SelectItem` e exatamente o que o Radix recusa (ver TODAS, acima).
            .filter(([chave]) => chave !== '' && chave != null)
            .map(([chave, texto]) => (
              <SelectItem key={chave} value={String(chave)}>
                {texto}
              </SelectItem>
            ))}
        </SelectContent>
      </Select>
    </Inline>
  );
}

/**
 * Os QUATRO filtros que o backend já servia, e só eles. Cada mudança navega — o estado de
 * filtro mora na URL, não em `useState`: link compartilhável e botão voltar honesto.
 */
function BarraDeFiltros({ filtros, opcoes }: { filtros: FiltrosAtivos; opcoes: Props['opcoes'] }) {
  const navegar = (campo: keyof FiltrosAtivos, valor: string) => {
    router.get(
      '/asset/assets',
      // `page: undefined` de propósito: trocar o filtro tem de voltar pra página 1, senão o
      // usuário cai numa página que o novo recorte pode nem ter.
      { ...limpar(filtros), [campo]: valor || undefined, page: undefined },
      { preserveScroll: true, preserveState: true, replace: true },
    );
  };

  return (
    <Inline gap={3} align="center" wrap>
      <SelectFiltro
        rotulo="Categoria"
        valor={filtros.category_id}
        opcoes={opcoes.categorias}
        onChange={(v) => navegar('category_id', v)}
      />
      <SelectFiltro
        rotulo="Local"
        valor={filtros.location_id}
        opcoes={opcoes.locais}
        onChange={(v) => navegar('location_id', v)}
      />
      <SelectFiltro
        rotulo="Tipo de compra"
        valor={filtros.purchase_type}
        opcoes={opcoes.tipos_compra}
        onChange={(v) => navegar('purchase_type', v)}
      />
      <Inline gap={1} align="center">
        <Checkbox
          id="filtro-alocaveis"
          checked={Boolean(filtros.is_allocatable)}
          onCheckedChange={(marcado) => navegar('is_allocatable', marcado ? '1' : '')}
        />
        <label htmlFor="filtro-alocaveis" className="text-sm text-muted-foreground">
          Somente alocáveis
        </label>
      </Inline>
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

export default function Bens({ abas_contadores, bens, filtros, opcoes, permissoes }: Props) {
  // Distingue "não há bem nenhum" de "não há bem PARA ESTE RECORTE" — são dois vazios
  // diferentes, e oferecer "cadastre o primeiro bem" a quem só filtrou demais é ruído.
  const temFiltroAtivo = Boolean(
    filtros.q ||
      filtros.location_id ||
      filtros.category_id ||
      filtros.purchase_type ||
      filtros.is_allocatable,
  );

  return (
    <AppShellV2>
      <Stack gap={4}>
        {/* As âncoras `data-contract` são a ponte Cowork-CSS ↔ Tailwind do gate
            `contrato-de-tela.mjs` (ADR 0286) — mesmo padrão do Painel (`Index.tsx:168`).
            Quem as consome: `prototipo-ui/contrato/patrimonio-bens.contract.json`. */}
        <div data-contract="cabecalho">
          <PageHeader
            title="Bens"
            subtitle="O patrimônio da empresa: o que a casa tem, onde está e com quem"
            actions={
              permissoes.criar ? (
                <Button size="sm" asChild>
                  <a href="/asset/assets/create">Novo ativo</a>
                </Button>
              ) : undefined
            }
          />
        </div>

        {/* `hidePrimary`: o primary do menu ("Novo ativo") já está no header acima —
            repeti-lo na barra de abas daria dois botões idênticos lado a lado. */}
        <div data-contract="subnav">
          <PatrimonioSubNav active="assets" hidePrimary badges={abas_contadores ?? undefined} />
        </div>

        <div data-contract="filtros">
          <BarraDeFiltros filtros={filtros} opcoes={opcoes} />
        </div>

        <div data-contract="tabela">
          <Deferred data="bens" fallback={<EsqueletoTabela />}>
            {bens && bens.data.length === 0 && !temFiltroAtivo ? (
              <EmptyState
                icon="boxes"
                title="Nenhum bem cadastrado ainda"
                description="O patrimônio começa pelo que já está na casa. Cadastre um bem e ele passa a aparecer aqui com valor, garantia e alocação."
                action={
                  permissoes.criar ? (
                    <Button asChild>
                      <a href="/asset/assets/create">Cadastrar o primeiro bem</a>
                    </Button>
                  ) : undefined
                }
              />
            ) : bens ? (
              <DataTable<Bem>
                columns={colunas(permissoes)}
                data={bens.data}
                pagination={bens}
                endpoint="/asset/assets"
                caption="Bens do patrimônio"
                filters={limpar(filtros)}
                initialSearch={filtros.q ?? ''}
                searchPlaceholder="Buscar bem, código, modelo, série..."
                emptyMessage="Nenhum bem para esses filtros — tente limpar a busca ou trocar o recorte."
                rowKey={(b) => b.id}
                rowState={(b): EstadoDaLinha | undefined => (b.em_manutencao > 0 ? 'urgent' : undefined)}
                minTableWidth={1280}
              />
            ) : null}
          </Deferred>
        </div>
      </Stack>
    </AppShellV2>
  );
}
