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
import { useState } from 'react';
import { Pencil, Trash2 } from 'lucide-react';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable, { type EstadoDaLinha } from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Skeleton } from '@/Components/ui/skeleton';
import { Stack, Inline, Grid } from '@/Components/layout';
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
  /** Só em `GET /asset/asset-maintenance/create`: abre o drawer "Enviar pra manutenção". */
  cadastro?: CadastroManutencao | null;
  /** Só em `GET /asset/asset-maintenance/{id}/edit`: a manutenção gravada, escopada por business. */
  edicao?: EdicaoManutencao | null;
}

interface BemOpcao {
  id: number;
  nome: string;
  codigo: string;
  /** Fim da garantia vigente (`Y-m-d`) — a leitura que o Blade mostrava ao lado do bem. */
  garantia_ate: string | null;
}

interface CadastroManutencao {
  /** Só os bens DA EMPRESA (o servidor escopa). */
  bens: BemOpcao[];
  /** `?asset_id=` pré-seleciona — e só vale se o bem estiver na lista acima. */
  bem_id: number | null;
}

interface EdicaoManutencao {
  id: number;
  codigo: string | null;
  bem: string | null;
  status: string;
  prioridade: string;
  atribuido_a: string;
  /** Nota de envio — só leitura: o `update()` não a grava (paridade com o Blade de edição). */
  nota: string | null;
  detalhes: string;
  anexos: Array<{ id: number; nome: string; url: string }>;
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
 * Ações de linha — EDITAR e EXCLUIR. Editar saiu em 2026-09-23 (o `edit` só respondia sob
 * `ajax()` e abria página em branco) e VOLTOU em 2026-09-30 (thread 19): `GET
 * /asset/asset-maintenance/{id}/edit` agora devolve esta mesma Page com o drawer aberto. É
 * botão com `router.get`, não `<a href>` — o UC-MANU-04 segue valendo pra link.
 *
 * Excluir aparece para TODA linha, sem `can()`, porque é isso que o Blade faz — o módulo não
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

  const editar = () =>
    router.get(`/asset/asset-maintenance/${manutencao.id}/edit`, {}, { preserveScroll: true });

  return (
    <Inline gap={1}>
      <button
        type="button"
        title={`Editar manutenção — ${manutencao.bem}`}
        onClick={(e) => {
          e.stopPropagation();
          editar();
        }}
        className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-transparent text-muted-foreground hover:border-border hover:bg-muted"
      >
        <Pencil size={14} aria-hidden="true" />
        <span className="sr-only">Editar manutenção — {manutencao.bem}</span>
      </button>
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
// e é assim que a rolagem horizontal do wrapper funciona em vez de espremer coluna. TODAS as
// colunas declaram largura, e o piso da tabela é a SOMA delas (default do `DataTable`).
//
// `bem` tem 250px, a largura que o protótipo declara para "Bem" (`patrimonio-page.jsx:489`).
// Até 2026-09-30 ela ficava sem largura para ser a fluida, com `minTableWidth={1280}` — mas as
// outras 9 já somavam 1332px: sob layout fixo sobrava ZERO pra ela, o mesmo defeito medido em
// produção na tela irmã de Bens ("Bategoria"). Travado por `tests/js/patrimonio-manutencoes-colunas.test.tsx`.
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
      meta: { width: 250 },
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

/* ─── Drawer de manutenção (thread 19) ───────────────────────────────────────────
 *
 * Layout do `ManutencaoForm` do protótipo (`patrimonio-forms.jsx:224`): drawer lateral, uma
 * seção, grade de 2 colunas, rodapé "Cancelar" + primário. CAMPOS do Blade, não do protótipo:
 * o protótipo desenha prestador, datas de envio/devolução e custo, e NENHUM deles tem coluna
 * em `asset_maintenances` (charter Non-Goals; custo é decisão [W] 2026-09-08). O que o `store()`
 * grava é `asset_id, status, priority, maintenance_note` + anexos; o `update()`, `status,
 * priority, details, assigned_to` + anexos. É isso que o drawer oferece — nem mais, nem menos.
 *
 * Alvo de toque ≥44px (`min-h-11`) nos controles: quem registra manutenção é o técnico, em
 * tablet/celular (thread 19 §B), não a densidade de escritório da lista.
 */

const TOQUE = 'min-h-11';
const SEM_ESCOLHA = '__nenhum__';

function CampoDrawer({
  id,
  rotulo,
  erro,
  ajuda,
  children,
}: {
  id: string;
  rotulo: string;
  erro?: string;
  ajuda?: string;
  children: React.ReactNode;
}) {
  return (
    <Stack gap={1}>
      <Label htmlFor={id}>{rotulo}</Label>
      {children}
      {erro ? (
        <small id={`${id}-erro`} role="alert" className="text-destructive">
          {erro}
        </small>
      ) : ajuda ? (
        <small className="text-muted-foreground">{ajuda}</small>
      ) : null}
    </Stack>
  );
}

function OpcoesDrawer({
  id,
  valor,
  onChange,
  opcoes,
  vazio,
  invalido,
}: {
  id: string;
  valor: string;
  onChange: (v: string) => void;
  opcoes: Record<string, string>;
  /** Rótulo da opção "nenhuma" — o Blade tinha `placeholder` e aceitava vazio. */
  vazio: string;
  invalido?: boolean;
}) {
  return (
    <Select value={valor === '' ? SEM_ESCOLHA : valor} onValueChange={(v) => onChange(v === SEM_ESCOLHA ? '' : v)}>
      <SelectTrigger id={id} className={TOQUE} aria-invalid={invalido || undefined}>
        <SelectValue placeholder={vazio} />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={SEM_ESCOLHA}>{vazio}</SelectItem>
        {Object.entries(opcoes)
          .filter(([k]) => k !== '')
          .map(([k, rotulo]) => (
            <SafeSelectItem key={k} value={String(k)}>
              {rotulo}
            </SafeSelectItem>
          ))}
      </SelectContent>
    </Select>
  );
}

function dataBR(iso: string): string {
  const [a, m, d] = iso.split('-');
  return `${d}/${m}/${a}`;
}

function ManutencaoDrawer({
  cadastro,
  edicao,
  opcoes,
}: {
  cadastro: CadastroManutencao | null;
  edicao: EdicaoManutencao | null;
  opcoes: Props['opcoes'];
}) {
  const editando = edicao !== null;
  const [bemId, setBemId] = useState(cadastro?.bem_id ? String(cadastro.bem_id) : '');
  const [status, setStatus] = useState(edicao?.status ?? '');
  const [prioridade, setPrioridade] = useState(edicao?.prioridade ?? '');
  const [responsavel, setResponsavel] = useState(edicao?.atribuido_a ?? '');
  const [nota, setNota] = useState('');
  const [detalhes, setDetalhes] = useState(edicao?.detalhes ?? '');
  const [anexos, setAnexos] = useState<File[]>([]);
  const [erros, setErros] = useState<Record<string, string>>({});
  const [enviando, setEnviando] = useState(false);

  // O drawer vive na URL (`/create` ou `/{id}/edit`) — fechar volta pra lista.
  const fechar = () => {
    if (!enviando) router.get('/asset/asset-maintenance', {}, { preserveScroll: true });
  };

  const bens = cadastro?.bens ?? [];
  const bemEscolhido = bens.find((b) => String(b.id) === bemId) ?? null;
  const opcoesBens = Object.fromEntries(bens.map((b) => [String(b.id), `${b.codigo} · ${b.nome}`]));

  const salvar = () => {
    if (enviando) return;
    if (!editando && !bemId) {
      setErros({ bem: 'Escolha o bem que vai pra manutenção.' });
      return;
    }
    setErros({});
    setEnviando(true);

    // Chaves do `store()`/`update()` como são — o `request->only(...)` do serviço as nomeia.
    const dados: Record<string, unknown> = editando
      ? { _method: 'put', status, priority: prioridade, assigned_to: responsavel, details: detalhes }
      : { asset_id: bemId, status, priority: prioridade, maintenance_note: nota };
    if (anexos.length) dados.attachments = anexos;

    router.post(
      editando ? `/asset/asset-maintenance/${edicao!.id}` : '/asset/asset-maintenance',
      dados as never,
      {
        forceFormData: true,
        preserveScroll: true,
        onError: (serverErros) => setErros({ geral: Object.values(serverErros).join(' ') }),
        onFinish: () => setEnviando(false),
      },
    );
  };

  return (
    <Sheet open onOpenChange={(o) => { if (!o) fechar(); }}>
      <SheetContent side="right" className="w-full gap-0 sm:max-w-[560px]" data-testid="manutencao-drawer">
        <SheetHeader className="border-b">
          <SheetTitle>
            {editando ? `Manutenção${edicao!.codigo ? ` ${edicao!.codigo}` : ''}` : 'Enviar pra manutenção'}
          </SheetTitle>
          <SheetDescription>
            {editando
              ? edicao!.bem ?? 'Bem removido do cadastro'
              : 'O código sai do prefixo do módulo — sequência por empresa.'}
          </SheetDescription>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto p-4">
          <Stack gap={4}>
            {erros.geral ? (
              <div role="alert" aria-live="assertive">
                <Alert variant="destructive">
                  <AlertDescription>{erros.geral}</AlertDescription>
                </Alert>
              </div>
            ) : null}

            {!editando ? (
              <CampoDrawer id="mf-bem" rotulo="Bem" erro={erros.bem}>
                <OpcoesDrawer
                  id="mf-bem"
                  valor={bemId}
                  onChange={setBemId}
                  opcoes={opcoesBens}
                  vazio="Escolha o bem"
                  invalido={!!erros.bem}
                />
                {bemEscolhido ? (
                  bemEscolhido.garantia_ate ? (
                    <Badge variant="success" dot>
                      Em garantia até {dataBR(bemEscolhido.garantia_ate)}
                    </Badge>
                  ) : (
                    <Badge variant="neutral" dot>Fora da garantia</Badge>
                  )
                ) : null}
              </CampoDrawer>
            ) : null}

            {/* `fit="sm"`: 2 colunas na largura do drawer, 1 no celular — primitivo, não grid solto. */}
            <Grid fit="sm" gap={3}>
              <CampoDrawer id="mf-status" rotulo="Situação">
                <OpcoesDrawer id="mf-status" valor={status} onChange={setStatus} opcoes={opcoes.status} vazio="Sem situação" />
              </CampoDrawer>
              <CampoDrawer id="mf-prioridade" rotulo="Prioridade">
                <OpcoesDrawer
                  id="mf-prioridade"
                  valor={prioridade}
                  onChange={setPrioridade}
                  opcoes={opcoes.prioridades}
                  vazio="Sem prioridade"
                />
              </CampoDrawer>
            </Grid>

            {editando ? (
              <>
                <CampoDrawer id="mf-responsavel" rotulo="Atribuído a">
                  <OpcoesDrawer
                    id="mf-responsavel"
                    valor={responsavel}
                    onChange={setResponsavel}
                    opcoes={opcoes.responsaveis}
                    vazio="Sem responsável"
                  />
                </CampoDrawer>
                <CampoDrawer id="mf-detalhes" rotulo="Detalhes do envio">
                  <Textarea id="mf-detalhes" rows={3} className={TOQUE} value={detalhes} onChange={(e) => setDetalhes(e.target.value)} />
                </CampoDrawer>
                <CampoDrawer id="mf-nota" rotulo="Nota da manutenção" ajuda="Registrada no envio — não muda na edição.">
                  <Textarea id="mf-nota" rows={3} readOnly value={edicao!.nota ?? ''} />
                </CampoDrawer>
                {edicao!.anexos.length ? (
                  <section aria-label="Anexos já enviados">
                    <Stack gap={1}>
                      <span className="text-sm font-medium">Anexos já enviados</span>
                      {edicao!.anexos.map((a) => (
                        <a key={a.id} href={a.url} target="_blank" rel="noreferrer" className="text-sm text-primary underline">
                          {a.nome}
                        </a>
                      ))}
                    </Stack>
                  </section>
                ) : null}
              </>
            ) : (
              <CampoDrawer id="mf-nota" rotulo="Nota da manutenção" ajuda="O que será feito e por quê.">
                <Textarea id="mf-nota" rows={3} className={TOQUE} value={nota} onChange={(e) => setNota(e.target.value)} />
              </CampoDrawer>
            )}

            <CampoDrawer id="mf-anexos" rotulo="Anexos">
              <Input
                id="mf-anexos"
                type="file"
                multiple
                className={TOQUE}
                onChange={(e) => setAnexos(Array.from(e.target.files ?? []))}
              />
            </CampoDrawer>
          </Stack>
        </div>

        <SheetFooter className="flex-row justify-end border-t">
          <Button variant="ghost" className={TOQUE} onClick={fechar} disabled={enviando}>
            Cancelar
          </Button>
          <Button className={TOQUE} onClick={salvar} disabled={enviando}>
            {enviando ? 'Salvando…' : editando ? 'Salvar manutenção' : 'Registrar manutenção'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
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

export default function Manutencoes({ manutencoes, filtros, opcoes, permissoes, cadastro = null, edicao = null }: Props) {
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
              />
            ) : null}
          </Deferred>
        </div>

        {/* Rodapé da âncora (`patrimonio-page.jsx:534-537`): o CTA de envio, sem a frase do
            "Concluir" — ela promete título a pagar no Financeiro, que não existe (Non-Goal). */}
        <div data-contract="rodape">
          <Inline gap={2} align="center" justify="end">
            <Button
              size="sm"
              variant="ghost"
              onClick={() => router.get('/asset/asset-maintenance/create', {}, { preserveScroll: true })}
            >
              + Enviar bem pra manutenção
            </Button>
          </Inline>
        </div>
      </Stack>
      {cadastro || edicao ? (
        // `key`: trocar de manutenção na URL remonta o drawer — o form não herda o anterior.
        <ManutencaoDrawer key={edicao ? `e${edicao.id}` : 'novo'} cadastro={cadastro} edicao={edicao} opcoes={opcoes} />
      ) : null}
    </AppShellV2>
  );
}
