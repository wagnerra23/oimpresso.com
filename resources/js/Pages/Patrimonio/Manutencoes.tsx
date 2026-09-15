// @patrimonio
//   tela: /asset/asset-maintenance  (rota `asset-maintenance.index` — a URL NÃO mudou)
//   modulo: Modules/AssetManagement  (NÃO existe Modules/Patrimonio — ADR 0394)
//   adrs: 0394 (endereço de UI), 0104 (MWART), 0093 (multi-tenant Tier 0),
//         0180 (sidebar v3 · ghosts), 0253 (primitivos de layout)
//   runbook: memory/requisitos/AssetManagement/RUNBOOK-manutencoes.md
//   charter: ./Manutencoes.charter.md · casos: ./Manutencoes.casos.md
//   fonte visual: prototipo-ui/cowork/Wagner/patrimonio-page.jsx (`AbaManutencoes`, :475) — ALVO,
//                 não decisão de produto (`06-ui-bloqueada.md`)
//
// SEGUNDA tela Inertia do módulo. Não funda nada: herda o `_shared/` de Bens (#7035).
//
// PARIDADE COM O BLADE é o contrato desta onda. As 10 colunas abaixo são as 10 do
// `asset_maintenance/index.blade.php`. O protótipo desenha 3 colunas e 2 KPIs a mais — e
// nenhum deles tem fonte de dado:
//
//   • CUSTO (coluna + "Custo no ano" + "Maior conserto"): a tabela `asset_maintenances` NÃO
//     tem coluna de valor, e o Blade não mostra custo (medido 2026-09-08: 0 menções a
//     cost|custo|amount|valor|price nas 3 views, com controle positivo). Decisão [W] de
//     2026-09-08 — "o custo já foi decidido nas regras do Blade, deve ser igual". O
//     `UC-MANU-03` transforma esse Non-Goal em Pest GUARD;
//   • PRESTADOR e DEVOLVIDO: não existem no banco;
//   • "Em aberto": é contagem do conjunto INTEIRO — derivá-la da página corrente daria um
//     número que mente (mesmo critério que Bens usou pra adiar os sub-recortes).
//
// ⚠️ NÃO copiar os nomes de `getActivitylogOptions()` do Model: ele audita `start_date`,
// `end_date` e `amount`, e as TRÊS não existem na tabela (`_saida-04.md §2a-bis`).
//
// A guarda de permissão é do CONTROLLER (#7034), não daqui — lá os dois `if` sequenciais
// substituíram o `&&` insatisfazível e o `|| subscription` que anulava o gate.
//
// Layout por PRIMITIVOS (ADR 0253) — `Stack`/`Inline`, nunca `<div className="flex">` solto.

import { Deferred, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type EstadoDaLinha } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack, Inline } from '@/Components/layout';
import PatrimonioSubNav from './_shared/PatrimonioSubNav';
import type { ColumnDef } from '@tanstack/react-table';

/* ─── Contrato com o backend ──────────────────────────────────────────────────── */

interface Garantia {
  inicio: string;
  fim: string;
  vigente: boolean;
}

interface Manutencao {
  id: number;
  /** Coluna real é `maitenance_id` — o typo (sem o 2º `n`) está no schema, no Model e no nome
   *  do controller. A tela lê a coluna real e expõe o rótulo correto; renomear é migration. */
  codigo: string | null;
  bem: string;
  bem_id: number | null;
  status: string | null;
  status_label: string;
  prioridade: string | null;
  prioridade_label: string;
  garantia: Garantia | null;
  detalhes: string | null;
  nota: string | null;
  criado_em: string;
  criado_ha: string;
  atribuido_a: string | null;
  criado_por: string | null;
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
  status?: string | null;
  priority?: string | null;
  assigned_to?: string | number | null;
  sort?: string | null;
  dir?: string | null;
}

interface Props {
  /** Deferida — ausente no primeiro paint, por isso opcional. */
  manutencoes?: Paginator<Manutencao>;
  filtros: FiltrosAtivos;
  opcoes: {
    status: Record<string, string>;
    prioridades: Record<string, string>;
    responsaveis: Record<string, string>;
  };
  permissoes: {
    /** `false` ⇒ o usuário tem só `view_own_maintenance`: a lista vem recortada por dono, e a
     *  tela DIZ isso. O Blade filtrava calado. */
    vejo_todas: boolean;
  };
}

/* ─── Células ─────────────────────────────────────────────────────────────────── */

function SemValor() {
  return <span className="text-muted-foreground">—</span>;
}

// `dot` em toda pílula de estado — AP7 do PRE-MERGE-UI: cor sozinha não é o único canal.
const TOM_STATUS: Record<string, 'info' | 'warning' | 'success' | 'neutral'> = {
  new: 'info',
  in_progress: 'warning',
  completed: 'success',
  cancelled: 'neutral',
};

const TOM_PRIORIDADE: Record<string, 'danger' | 'warning' | 'success'> = {
  high: 'danger',
  medium: 'warning',
  low: 'success',
};

function SeloStatus({ chave, rotulo }: { chave: string | null; rotulo: string }) {
  if (!chave) return <SemValor />;
  return (
    <Badge variant={TOM_STATUS[chave] ?? 'neutral'} dot>
      {rotulo}
    </Badge>
  );
}

function SeloPrioridade({ chave, rotulo }: { chave: string | null; rotulo: string }) {
  if (!chave) return <SemValor />;
  return (
    <Badge variant={TOM_PRIORIDADE[chave] ?? 'neutral'} dot>
      {rotulo}
    </Badge>
  );
}

/**
 * Selo de garantia — a mesma leitura do Blade (`in_warranty` / `not_in_warranty` + janela).
 * Sem faixa de criticidade: "garantia crítica" é recorte que o servidor não tem, e derivá-lo
 * aqui seria inventar decisão de produto (§5 do RUNBOOK).
 */
function SeloGarantia({ garantia }: { garantia: Garantia | null }) {
  if (!garantia) return <Badge variant="neutral" dot>Sem garantia</Badge>;
  if (!garantia.vigente) return <Badge variant="neutral" dot>Fora da garantia</Badge>;
  return (
    <Stack gap={0}>
      <Badge variant="success" dot>Em garantia</Badge>
      <small className="text-muted-foreground">
        {garantia.inicio} ~ {garantia.fim}
      </small>
    </Stack>
  );
}

/**
 * Ações de linha — as MESMAS duas do Blade (`index()` `:214`): editar e excluir.
 *
 * Elas aparecem para TODA linha, sem `can()`, porque é isso que o Blade faz — o módulo não
 * declara permissão de escrita de manutenção, e inventar um gate aqui seria decidir produto
 * dentro do `.tsx`. O resíduo (quem tem só `view_own` pode editar a de outro) está declarado
 * no charter e no §9 do RUNBOOK: é decisão de [W], não conserto silencioso.
 *
 * Excluir usa `router.delete` porque `destroy` é rota `resource` (verbo DELETE) — link `<a>`
 * não a alcançaria, e botão que parece excluir sem excluir é afordância falsa.
 */
function AcoesDaLinha({ manutencao }: { manutencao: Manutencao }) {
  const excluir = () => {
    // Nomeia a manutenção: confirmação genérica não deixa perceber que clicou na linha errada.
    const alvo = manutencao.codigo ? `${manutencao.codigo} — ${manutencao.bem}` : manutencao.bem;
    if (!window.confirm(`Excluir a manutenção "${alvo}"? Esta ação não pode ser desfeita.`)) {
      return;
    }
    router.delete(`/asset/asset-maintenance/${manutencao.id}`, { preserveScroll: true });
  };

  return (
    <Inline gap={1}>
      <a
        href={`/asset/asset-maintenance/${manutencao.id}/edit`}
        title={`Editar manutenção — ${manutencao.bem}`}
        onClick={(e) => e.stopPropagation()}
        className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent text-muted-foreground hover:border-border hover:text-foreground"
      >
        <Pencil size={14} aria-hidden="true" />
        <span className="sr-only">Editar manutenção — {manutencao.bem}</span>
      </a>
      <button
        type="button"
        title={`Excluir manutenção — ${manutencao.bem}`}
        onClick={(e) => {
          e.stopPropagation();
          excluir();
        }}
        className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent text-destructive hover:border-border hover:bg-destructive/10"
      >
        <Trash2 size={14} aria-hidden="true" />
        <span className="sr-only">Excluir manutenção — {manutencao.bem}</span>
      </button>
    </Inline>
  );
}

/* ─── Colunas ─────────────────────────────────────────────────────────────────── */

// GEOMETRIA declarada (`meta.width`): largura declarada põe a tabela em `table-layout: fixed`,
// e é assim que a rolagem horizontal do wrapper funciona em vez de espremer coluna. `bem` fica
// SEM largura de propósito — é a fluida, que absorve a sobra. `minTableWidth` = 1280, o monitor
// do piloto.
function colunas(): ColumnDef<Manutencao, unknown>[] {
  return [
    {
      id: 'acoes',
      header: 'Ações',
      // Ação primeiro, como no protótipo (`:486`): a tabela é mais larga que a janela e o
      // controle não pode nascer fora do viewport.
      cell: ({ row }) => <AcoesDaLinha manutencao={row.original} />,
      meta: { width: 96 },
    },
    {
      id: 'codigo',
      header: 'Código',
      accessorFn: (m) => m.codigo ?? '',
      cell: ({ row }) => row.original.codigo ?? <SemValor />,
      meta: { width: 116, mono: true },
    },
    {
      id: 'bem',
      header: 'Bem',
      accessorFn: (m) => m.bem,
      cell: ({ row }) => (
        <Stack gap={0}>
          <span>{row.original.bem}</span>
          {row.original.nota ? (
            <small className="text-muted-foreground line-clamp-1">{row.original.nota}</small>
          ) : null}
        </Stack>
      ),
    },
    {
      id: 'status',
      header: 'Situação',
      accessorFn: (m) => m.status ?? '',
      cell: ({ row }) => <SeloStatus chave={row.original.status} rotulo={row.original.status_label} />,
      meta: { width: 132 },
    },
    {
      id: 'prioridade',
      header: 'Prioridade',
      accessorFn: (m) => m.prioridade ?? '',
      cell: ({ row }) => (
        <SeloPrioridade chave={row.original.prioridade} rotulo={row.original.prioridade_label} />
      ),
      meta: { width: 122 },
    },
    {
      id: 'garantia',
      header: 'Garantia',
      cell: ({ row }) => <SeloGarantia garantia={row.original.garantia} />,
      meta: { width: 168 },
    },
    {
      id: 'detalhes',
      header: 'Detalhes',
      cell: ({ row }) =>
        row.original.detalhes ? (
          <span className="line-clamp-2">{row.original.detalhes}</span>
        ) : (
          <SemValor />
        ),
      meta: { width: 220 },
    },
    {
      id: 'criado_em',
      header: 'Enviado em',
      accessorFn: (m) => m.criado_em,
      cell: ({ row }) => (
        <Stack gap={0}>
          <span>{row.original.criado_em}</span>
          <small className="text-muted-foreground">{row.original.criado_ha}</small>
        </Stack>
      ),
      meta: { width: 150 },
    },
    {
      id: 'atribuido_a',
      header: 'Atribuído a',
      cell: ({ row }) =>
        row.original.atribuido_a ? (
          row.original.atribuido_a
        ) : (
          // O Blade marca o não-atribuído em vermelho — é sinal operacional, não decoração.
          <Badge variant="danger" dot>
            Sem responsável
          </Badge>
        ),
      meta: { width: 168 },
    },
    {
      id: 'criado_por',
      header: 'Criado por',
      cell: ({ row }) => row.original.criado_por ?? <SemValor />,
      meta: { width: 160 },
    },
  ];
}

/* ─── Filtros ─────────────────────────────────────────────────────────────────── */

function limpar(filtros: FiltrosAtivos): Record<string, string> {
  const saida: Record<string, string> = {};
  for (const [chave, valor] of Object.entries(filtros)) {
    if (valor != null && valor !== '') saida[chave] = String(valor);
  }
  return saida;
}

/**
 * Sentinela do "todas" — o Radix `Select` NÃO aceita `<SelectItem value="">`: string vazia é o
 * valor que ele usa internamente para "nada selecionado", e passá-la explicitamente quebra o
 * componente (§5 2026-06-29). Mesma sentinela que `Bens.tsx` usa.
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
      {/* `htmlFor`/`id` em vez de envolver o controle: o Radix renderiza botão + portal, e é o
          vínculo explícito que o leitor de tela de fato lê. */}
      <label htmlFor={campoId} className="text-sm text-muted-foreground">
        {rotulo}
      </label>
      <Select
        value={valor != null && valor !== '' ? String(valor) : TODAS}
        onValueChange={(v) => onChange(v === TODAS ? '' : v)}
      >
        <SelectTrigger id={campoId} className="w-44">
          <SelectValue placeholder="todas" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value={TODAS}>todas</SelectItem>
          {Object.entries(opcoes)
            // Chave vazia fora: além de colidir com o "todas", `value=""` num `SelectItem` é
            // exatamente o que o Radix recusa.
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

/** Os TRÊS filtros que o Blade já oferecia, e só eles. O estado mora na URL. */
function BarraDeFiltros({ filtros, opcoes }: { filtros: FiltrosAtivos; opcoes: Props['opcoes'] }) {
  const navegar = (campo: keyof FiltrosAtivos, valor: string) => {
    router.get(
      '/asset/asset-maintenance',
      // `page: undefined` de propósito: trocar o filtro volta pra página 1, senão o usuário cai
      // numa página que o novo recorte pode nem ter.
      { ...limpar(filtros), [campo]: valor || undefined, page: undefined },
      { preserveScroll: true, preserveState: true, replace: true },
    );
  };

  return (
    <Inline gap={3} align="center" wrap>
      <SelectFiltro
        rotulo="Situação"
        valor={filtros.status}
        opcoes={opcoes.status}
        onChange={(v) => navegar('status', v)}
      />
      <SelectFiltro
        rotulo="Prioridade"
        valor={filtros.priority}
        opcoes={opcoes.prioridades}
        onChange={(v) => navegar('priority', v)}
      />
      <SelectFiltro
        rotulo="Responsável"
        valor={filtros.assigned_to}
        opcoes={opcoes.responsaveis}
        onChange={(v) => navegar('assigned_to', v)}
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

export default function Manutencoes({ manutencoes, filtros, opcoes, permissoes }: Props) {
  // Distingue "não há manutenção nenhuma" de "não há manutenção PARA ESTE RECORTE" — dois
  // vazios diferentes, e oferecer a mensagem errada a quem só filtrou demais é ruído.
  const temFiltroAtivo = Boolean(filtros.q || filtros.status || filtros.priority || filtros.assigned_to);

  return (
    <AppShellV2>
      <Stack gap={4}>
        {/* As âncoras `data-contract` são a ponte Cowork-CSS ↔ Tailwind do gate
            `contrato-de-tela.mjs` (ADR 0286) — mesmo padrão da irmã Bens (`Bens.tsx:532`).
            Quem as consome: `governance/design/contracts/patrimonio-manutencoes.contract.json`.
            Cada uma envolve elemento QUE JÁ EXISTIA; nenhum conteúdo mudou. */}
        <div data-contract="cabecalho">
          <PageHeader
            title="Manutenções"
            subtitle="O que está fora de operação, com quem e desde quando"
          />
        </div>

        {/* `hidePrimary`: o primary do menu já aparece no header do módulo — repeti-lo aqui
            daria dois botões idênticos lado a lado. */}
        <div data-contract="subnav">
          <PatrimonioSubNav active="asset-maintenance" hidePrimary />
        </div>

        {/* O aviso de escopo é GANHO sobre o Blade, que recortava calado. Fica ACIMA da tabela
            e continua visível no vazio, porque "não há manutenção" e "não há manutenção SUA"
            são respostas diferentes. */}
        {!permissoes.vejo_todas ? (
          // A âncora fica DENTRO do condicional de propósito: esta tela tem baseline de
          // pixel (`tests/Browser/visreg-screens.json`), e uma `div` vazia como filha
          // direta do `Stack` somaria um slot de `gap` quando o alerta não renderiza.
          <div data-contract="alerta">
            <Alert>
              <AlertTitle>Você vê apenas as suas manutenções</AlertTitle>
              <AlertDescription>
                Seu perfil tem <code>asset.view_own_maintenance</code> — a lista mostra só onde
                você é o responsável ou quem registrou.
              </AlertDescription>
            </Alert>
          </div>
        ) : null}

        <BarraDeFiltros filtros={filtros} opcoes={opcoes} />

        <div data-contract="tabela">
          <Deferred data="manutencoes" fallback={<EsqueletoTabela />}>
            {manutencoes && manutencoes.data.length === 0 && !temFiltroAtivo ? (
              <EmptyState
                icon="wrench"
                title={
                  permissoes.vejo_todas
                    ? 'Nenhuma manutenção registrada'
                    : 'Nenhuma manutenção sua no momento'
                }
                description={
                  permissoes.vejo_todas
                    ? 'Quando um bem for enviado para manutenção, ele aparece aqui com situação, prioridade e responsável. O envio começa na tela de Bens.'
                    : 'Quando você for o responsável por uma manutenção, ela aparece aqui.'
                }
              />
            ) : manutencoes ? (
              <DataTable<Manutencao>
                columns={colunas()}
                data={manutencoes.data}
                pagination={manutencoes}
                endpoint="/asset/asset-maintenance"
                caption="Manutenções do patrimônio"
                filters={limpar(filtros)}
                initialSearch={filtros.q ?? ''}
                searchPlaceholder="Buscar por código, bem ou detalhes..."
                emptyMessage="Nenhuma manutenção para esses filtros — tente limpar a busca ou trocar o recorte."
                rowKey={(m) => m.id}
                rowState={(m): EstadoDaLinha | undefined =>
                  m.status === 'in_progress' ? 'urgent' : undefined
                }
                minTableWidth={1280}
              />
            ) : null}
          </Deferred>
        </div>
      </Stack>
    </AppShellV2>
  );
}
