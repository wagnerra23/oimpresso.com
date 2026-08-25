// @memcofre
//   tela: /superadmin/superadmin-subscription
//   module: Superadmin
//   stories: SA-O4a (Blade/AdminLTE + DataTables → Inertia)
//   permissao: superadmin
//
// Lista de assinaturas da plataforma. Responde: "o dinheiro entrou?".
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/superadmin-page.jsx → ViewAssinaturas() (L1044)
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-assinaturas.md
//
// Paginação e ordenação são SERVER-SIDE. O protótipo ordena no cliente porque trabalha com um
// mock de 10 linhas; em produção são todas as assinaturas de todos os negócios — trazer tudo
// pro browser seria a mesma dívida que o DataTables tinha.
//
// O que esta tela NÃO faz, e é decisão e não esquecimento: ela não ESCREVE. "Vencida" é rótulo
// derivado da data, não status gravado. As ações do kebab do F1 (status, vigência, cancelar)
// são a SA-O4b e passam pelo SubscriptionLifecycleService, que é quem deixa trilha.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router, useForm } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Select, plural, tomDaAssinatura } from '../_components/assinatura';

const ROTA = '/superadmin/superadmin-subscription';

interface Filtros {
  pacote: string | null;
  status: string | null;
  periodo: string | null;
  ordem: string;
  dir: 'asc' | 'desc';
}

interface PacoteOpcao {
  id: number;
  nome: string;
}

interface Kpis {
  ativas: number;
  trial: number;
  pendentes: number;
  vencidas_canceladas: number;
  /** `declined` — FORA dos 4 KPI do F1 de propósito. Ver charter §Anti-hooks. */
  bloqueadas: number;
}

interface Linha {
  id: number;
  negocio_id: number;
  negocio: string;
  pacote: string;
  /** Já traduzido pelo back (`RotuloAssinatura`). O enum cru nunca chega aqui. */
  situacao: string;
  criado: string | null;
  inicio: string | null;
  fim: string | null;
  trial_fim: string | null;
  preco: number;
  via: string | null;
  transacao: string | null;
}

interface Pagina {
  linhas: Linha[];
  total: number;
  pagina: number;
  paginas: number;
  por_pagina: number;
}

interface Props {
  filtros: Filtros;
  pacotes?: PacoteOpcao[];
  kpis?: Kpis;
  assinaturas?: Pagina;
}

/** Rótulos de TELA. O front não conhece `declined` — quem traduz é o back (RUNBOOK §3). */
const STATUS = [
  { v: '', label: 'Status: todos' },
  { v: 'ativa', label: 'Ativa' },
  { v: 'trial', label: 'Em trial' },
  { v: 'pendente', label: 'Pendente' },
  { v: 'vencida', label: 'Vencida' },
  { v: 'cancelada', label: 'Cancelada' },
  { v: 'bloqueada', label: 'Bloqueada' },
];

const PERIODOS = [
  { v: '', label: 'Criada em: qualquer período' },
  { v: '7d', label: 'Últimos 7 dias' },
  { v: '30d', label: 'Últimos 30 dias' },
  { v: 'mes', label: 'Este mês' },
];

/** Cabeçalhos ordenáveis. A chave TEM que existir na whitelist do controller (`ORDENS`). */
const COLUNAS: { id: string | null; label: string; alinha?: string }[] = [
  { id: null, label: 'Assinatura' },
  { id: 'negocio', label: 'Negócio' },
  { id: 'status', label: 'Status' },
  { id: 'inicio', label: 'Vigência' },
  { id: 'preco', label: 'Valor', alinha: 'text-right' },
  { id: null, label: 'Pagamento' },
];

// Formatador de moeda: o VALOR vem do payload, sempre. Não existe literal monetário neste
// arquivo — Tier 0 (memory/proibicoes.md).
const moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

function AssinaturasIndex({ filtros, pacotes, kpis, assinaturas }: Props) {
  const filtrosAtuais = () => ({
    pacote: filtros.pacote || undefined,
    status: filtros.status || undefined,
    periodo: filtros.periodo || undefined,
    ordem: filtros.ordem || undefined,
    dir: filtros.dir || undefined,
  });

  /** Toda navegação preserva os demais filtros — trocar um não zera os outros. */
  const irPara = (mudanca: Record<string, string | number | undefined>) => {
    router.get(
      ROTA,
      { ...filtrosAtuais(), ...mudanca },
      { only: ['assinaturas', 'kpis', 'filtros'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  /** Clicar na coluna já ordenada inverte a direção; em outra, começa desc. */
  const ordenarPor = (col: string) =>
    irPara({ ordem: col, dir: filtros.ordem === col && filtros.dir === 'desc' ? 'asc' : 'desc', page: undefined });

  // A gaveta de acao e ESTADO LOCAL, nao rota: ela nao sobrevive a refresh de proposito.
  // Formulario de escrita meio-preenchido restaurado por URL e convite a aplicar por engano
  // uma acao que o operador ja tinha abandonado.
  const [acao, setAcao] = useState<{ linha: Linha; modo: 'status' | 'vigencia' } | null>(null);

  const temFiltro = !!filtros.pacote || !!filtros.status || !!filtros.periodo;

  return (
    <div className="pb-8">
      <PageHeader title="Assinaturas" moduleNav description="Cobrança de todos os negócios da plataforma" />

      {/*
        A âncora do contrato fica AQUI, na posição de render, e não dentro de `KpiLinha`: o gate
        `contrato-de-tela` lê a ORDEM NO FONTE, e um componente declarado no fim do arquivo
        apareceria depois dos filtros — ordem divergente, ainda que a tela desenhe certo.
      */}
      <div className="px-6 pt-4" data-contract="superadmin.assinaturas.kpis">
        <Deferred data="kpis" fallback={<KpisEsqueleto />}>
          <KpiLinha kpis={kpis} />
        </Deferred>
      </div>

      <div className="flex flex-wrap items-center gap-2 px-6 pt-4" data-contract="superadmin.assinaturas.filtros">
        <Deferred data="pacotes" fallback={<Skeleton className="h-9 w-40" />}>
          <FiltroPacote
            pacotes={pacotes}
            valor={filtros.pacote ?? ''}
            onChange={(v) => irPara({ pacote: v || undefined, page: undefined })}
          />
        </Deferred>

        <Select
          rotulo="Status"
          valor={filtros.status ?? ''}
          opcoes={STATUS}
          onChange={(v) => irPara({ status: v || undefined, page: undefined })}
        />
        <Select
          rotulo="Criada em"
          valor={filtros.periodo ?? ''}
          opcoes={PERIODOS}
          onChange={(v) => irPara({ periodo: v || undefined, page: undefined })}
        />

        {temFiltro && (
          <Button
            variant="ghost"
            size="sm"
            className="h-9 text-xs"
            onClick={() =>
              router.get(
                ROTA,
                {},
                { only: ['assinaturas', 'kpis', 'filtros'], preserveState: true, preserveScroll: true, replace: true },
              )
            }
          >
            Limpar
          </Button>
        )}
      </div>

      <div className="px-6 pt-4">
        <Deferred
          data="assinaturas"
          fallback={
            <Card>
              <CardContent className="p-4">
                <Skeleton className="h-64 w-full" />
              </CardContent>
            </Card>
          }
        >
          <Tabela
            assinaturas={assinaturas}
            filtros={filtros}
            temFiltro={temFiltro}
            irPara={irPara}
            ordenarPor={ordenarPor}
            onAcao={(linha, modo) => setAcao({ linha, modo })}
          />
        </Deferred>
      </div>

      {acao && (
        <GavetaDeAcao
          linha={acao.linha}
          modo={acao.modo}
          onFechar={() => setAcao(null)}
        />
      )}
    </div>
  );
}

/* O <Deferred> segura o filho até a prop chegar; ele NÃO injeta — cada bloco recebe o valor. */

function KpisEsqueleto() {
  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      {[0, 1, 2, 3].map((i) => (
        <Skeleton key={i} className="h-20 w-full" />
      ))}
    </div>
  );
}

function Kpi({ valor, rotulo, nota }: { valor: number; rotulo: string; nota?: string }) {
  return (
    <Card>
      <CardContent className="p-4">
        <div className="text-2xl font-semibold tabular-nums">{valor}</div>
        <div className="text-xs text-muted-foreground">{rotulo}</div>
        {nota && <div className="pt-1 text-[11px] text-muted-foreground/80">{nota}</div>}
      </CardContent>
    </Card>
  );
}

function KpiLinha({ kpis }: { kpis?: Kpis }) {
  const k = kpis;
  if (!k) return <KpisEsqueleto />;

  return (
    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <Kpi valor={k.ativas} rotulo="Ativas" />
      <Kpi valor={k.trial} rotulo="Em trial" nota="conta pelo fim do teste, não pelo status" />
      <Kpi valor={k.pendentes} rotulo="Pendentes" />
      {/*
        O recorte é DITO, não escondido: `bloqueada` (inadimplência) não é `cancelada` (pedido do
        cliente), e o F1 só previu quatro cartões. Somar as duas daria um número redondo e errado.
      */}
      <Kpi
        valor={k.vencidas_canceladas}
        rotulo="Vencidas ou canceladas"
        nota={k.bloqueadas > 0 ? `${plural(k.bloqueadas, 'bloqueada', 'bloqueadas')} por inadimplência, fora desta conta` : undefined}
      />
    </div>
  );
}

function FiltroPacote({
  pacotes,
  valor,
  onChange,
}: {
  pacotes?: PacoteOpcao[];
  valor: string;
  onChange: (v: string) => void;
}) {
  const opcoes = [{ v: '', label: 'Pacote: todos' }, ...(pacotes ?? []).map((p) => ({ v: String(p.id), label: p.nome }))];

  return <Select rotulo="Pacote" valor={valor} opcoes={opcoes} onChange={onChange} />;
}

function Cabecalho({
  coluna,
  filtros,
  ordenarPor,
}: {
  coluna: { id: string | null; label: string; alinha?: string };
  filtros: Filtros;
  ordenarPor: (col: string) => void;
}) {
  const classe = `px-4 py-2 font-medium ${coluna.alinha ?? ''}`;

  if (!coluna.id) {
    return <th className={classe}>{coluna.label}</th>;
  }

  const ativa = filtros.ordem === coluna.id;

  return (
    <th className={classe} aria-sort={ativa ? (filtros.dir === 'asc' ? 'ascending' : 'descending') : 'none'}>
      <button type="button" onClick={() => ordenarPor(coluna.id!)} className="inline-flex items-center gap-1 hover:text-foreground">
        {coluna.label}
        {ativa && <span aria-hidden="true">{filtros.dir === 'asc' ? '↑' : '↓'}</span>}
      </button>
    </th>
  );
}

function Tabela({
  assinaturas,
  filtros,
  temFiltro,
  irPara,
  ordenarPor,
  onAcao,
}: {
  assinaturas?: Pagina;
  filtros: Filtros;
  temFiltro: boolean;
  irPara: (m: Record<string, string | number | undefined>) => void;
  ordenarPor: (col: string) => void;
  onAcao: (linha: Linha, modo: 'status' | 'vigencia') => void;
}) {
  const p = assinaturas;
  const linhas = p?.linhas ?? [];

  if (linhas.length === 0) {
    return (
      <Card>
        <CardContent className="p-0">
          <EmptyState
            title={temFiltro ? 'Nenhuma assinatura com esses filtros' : 'Nenhuma assinatura cadastrada'}
            description={
              temFiltro
                ? 'Esse cruzamento de pacote, status e período não tem registro. Volte um filtro e tente de novo.'
                : 'Quando um negócio assinar um pacote, a assinatura aparece aqui.'
            }
          />
        </CardContent>
      </Card>
    );
  }

  const inicio = (p!.pagina - 1) * p!.por_pagina + 1;
  const fim = Math.min(p!.pagina * p!.por_pagina, p!.total);

  return (
    <Card>
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm" data-contract="superadmin.assinaturas.tabela">
            <thead>
              <tr className="border-b text-left text-xs text-muted-foreground">
                {COLUNAS.map((c) => (
                  <Cabecalho key={c.label} coluna={c} filtros={filtros} ordenarPor={ordenarPor} />
                ))}
                <th className="px-4 py-2" aria-label="Ações" />
              </tr>
            </thead>
            <tbody>
              {linhas.map((s) => (
                <tr key={s.id} className="border-b last:border-0 hover:bg-muted/50">
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="font-medium tabular-nums">#{s.id}</span>
                      <span className="text-[11px] text-muted-foreground">criada {s.criado ?? '—'}</span>
                    </div>
                  </td>
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="font-medium">{s.negocio}</span>
                      <span className="text-[11px] text-muted-foreground">{s.pacote}</span>
                    </div>
                  </td>
                  <td className="px-4 py-2.5">
                    <Badge variant={tomDaAssinatura(s.situacao)}>{s.situacao}</Badge>
                  </td>
                  {/*
                    Vigência FUNDIDA numa célula (`início → fim`), como o F1 pede. Em duas colunas
                    separadas a leitura de "até quando isso vale" some — foi o que tornou a tela
                    legada ilegível.
                  */}
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="tabular-nums">
                        {s.inicio ?? '—'} → {s.fim ?? '—'}
                      </span>
                      <span className="text-[11px] text-muted-foreground">
                        {s.trial_fim ? `trial até ${s.trial_fim}` : 'sem trial'}
                      </span>
                    </div>
                  </td>
                  <td className="px-4 py-2.5 text-right tabular-nums">{moeda.format(s.preco)}</td>
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="text-muted-foreground">{s.via ?? '—'}</span>
                      {s.transacao && <span className="text-[11px] text-muted-foreground/80 tabular-nums">{s.transacao}</span>}
                    </div>
                  </td>
                  <td className="px-4 py-2.5 text-right">
                    <Kebab linha={s} onAcao={onAcao} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div
          className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3"
          data-contract="superadmin.assinaturas.paginacao"
        >
          <span className="text-[11px] text-muted-foreground">
            {inicio}–{fim} de {plural(p!.total, 'assinatura', 'assinaturas')}
          </span>
          <div className="flex gap-1.5">
            <Button
              variant="outline"
              size="sm"
              className="h-8 text-xs"
              disabled={p!.pagina <= 1}
              onClick={() => irPara({ page: p!.pagina - 1 })}
            >
              Anterior
            </Button>
            <Button
              variant="outline"
              size="sm"
              className="h-8 text-xs"
              disabled={p!.pagina >= p!.paginas}
              onClick={() => irPara({ page: p!.pagina + 1 })}
            >
              Próxima
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

/**
 * Kebab da linha. As ações de ESCRITA que o backend sustenta, mais o atalho de leitura.
 *
 * O F1 desenha 5 itens; "Baixar comprovante" não está aqui porque comprovante NÃO EXISTE no
 * sistema — `payment_transaction_id` é uma string do gateway, não um documento (medido: zero
 * rota, zero storage, zero geração de PDF no módulo). Botão que promete arquivo e não entrega
 * é pior que botão ausente.
 */
function Kebab({ linha, onAcao }: { linha: Linha; onAcao: (l: Linha, m: 'status' | 'vigencia') => void }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="sm" className="h-8 px-2 text-xs" aria-label={'Ações da assinatura #' + linha.id}>
          ⋯
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <DropdownMenuItem onSelect={() => router.get('/superadmin/business', { q: String(linha.negocio_id) })}>
          Ver negócio
        </DropdownMenuItem>
        <DropdownMenuSeparator />
        <DropdownMenuItem onSelect={() => onAcao(linha, 'status')}>Mudar status</DropdownMenuItem>
        <DropdownMenuItem onSelect={() => onAcao(linha, 'vigencia')}>Editar vigência</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}

/** Categorias de cancelamento — espelham o enum de `subscriptions.cancel_reason`. */
const MOTIVOS = [
  { v: '', label: 'Sem motivo declarado' },
  { v: 'preco', label: 'Preço' },
  { v: 'sem_uso', label: 'Parou de usar' },
  { v: 'trocou_sistema', label: 'Trocou de sistema' },
  { v: 'fechou', label: 'Fechou' },
  { v: 'inadimplencia', label: 'Inadimplência' },
  { v: 'outro', label: 'Outro' },
];

/**
 * As 3 AÇÕES que existem — e por que não são os 5 status do F1.
 *
 * O `SubscriptionLifecycleService` modela três transições. Os outros dois valores do enum não
 * são escolha de operador: `waiting` é o estado inicial de quem não teve baixa (voltar pra lá
 * seria des-aprovar, que o serviço não modela) e `declined` é gravado por evento quando a
 * cobrança vence. Oferecer os cinco exigiria escrita direta em `status` — exatamente o que esta
 * onda veio remover, porque é o caminho que não deixa trilha.
 */
const ACOES = [
  {
    v: 'aprovar',
    label: 'Aprovar',
    nota: 'Libera o acesso agora e calcula a vigência pelo pacote. Só vale para assinatura pendente.',
  },
  {
    v: 'vencer',
    label: 'Marcar como vencida',
    nota: 'Só se aplica quando a vigência já passou.',
  },
  {
    v: 'cancelar',
    label: 'Cancelar',
    nota: 'Para de renovar no fim da vigência. O acesso continua até lá e o registro fica.',
  },
];

/**
 * Gaveta de ação. Dois modos, um por pergunta — misturar status e datas num formulário só faz
 * o operador mudar uma coisa querendo mudar a outra.
 */
function GavetaDeAcao({
  linha,
  modo,
  onFechar,
}: {
  linha: Linha;
  modo: 'status' | 'vigencia';
  onFechar: () => void;
}) {
  const status = useForm({ acao: 'aprovar', motivo: '', nota: '' });
  const vigencia = useForm({
    subscription_id: linha.id,
    start_date: linha.inicio ?? '',
    end_date: linha.fim ?? '',
    trial_end_date: linha.trial_fim ?? '',
  });

  const enviarStatus = () => status.put(ROTA + '/' + linha.id, { preserveScroll: true, onSuccess: onFechar });

  const enviarVigencia = () =>
    vigencia.post('/superadmin/update-subscription', { preserveScroll: true, onSuccess: onFechar });

  const escolhida = ACOES.find((a) => a.v === status.data.acao);

  return (
    <Sheet open onOpenChange={(aberto) => !aberto && onFechar()}>
      <SheetContent className="w-full sm:max-w-md" data-contract="superadmin.assinaturas.form">
        <SheetHeader>
          <SheetTitle>{modo === 'status' ? 'Mudar status da assinatura' : 'Editar vigência'}</SheetTitle>
          <SheetDescription>
            #{linha.id} · {linha.negocio} · {linha.pacote}
          </SheetDescription>
        </SheetHeader>

        {modo === 'status' ? (
          <div className="flex flex-col gap-4 px-4">
            <div className="flex flex-col gap-1.5">
              <Label>Ação</Label>
              <Select
                rotulo="Ação"
                valor={status.data.acao}
                opcoes={ACOES.map((a) => ({ v: a.v, label: a.label }))}
                onChange={(v) => status.setData('acao', v)}
              />
              {/* O efeito é dito ANTES de aplicar, não depois — é o que o UC-SA-008 pede. */}
              {escolhida && <p className="text-[11px] text-muted-foreground">{escolhida.nota}</p>}
            </div>

            {status.data.acao === 'cancelar' && (
              <>
                <div className="flex flex-col gap-1.5">
                  <Label>Motivo</Label>
                  <Select
                    rotulo="Motivo"
                    valor={status.data.motivo}
                    opcoes={MOTIVOS}
                    onChange={(v) => status.setData('motivo', v)}
                  />
                  {/*
                    "Sem motivo declarado" é opção de verdade, não preguiça: a regra R10 do F1
                    manda deixar a saída sem motivo FORA do gráfico de churn e dizê-la em texto.
                    Forçar uma categoria inventaria dado que ninguém informou.
                  */}
                  <p className="text-[11px] text-muted-foreground">
                    Sem motivo, o cancelamento fica fora do gráfico de churn — e isso é dito lá.
                  </p>
                </div>

                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="nota">Observação</Label>
                  <Textarea
                    id="nota"
                    value={status.data.nota}
                    onChange={(e) => status.setData('nota', e.target.value)}
                    placeholder="Fica no registro da assinatura."
                    rows={3}
                  />
                </div>
              </>
            )}
          </div>
        ) : (
          <div className="flex flex-col gap-4 px-4">
            <div className="grid grid-cols-2 gap-3">
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="inicio">Início</Label>
                <Input
                  id="inicio"
                  value={vigencia.data.start_date}
                  onChange={(e) => vigencia.setData('start_date', e.target.value)}
                  placeholder="dd/mm/aaaa"
                  className="tabular-nums"
                />
              </div>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="fim">Fim</Label>
                <Input
                  id="fim"
                  value={vigencia.data.end_date}
                  onChange={(e) => vigencia.setData('end_date', e.target.value)}
                  placeholder="dd/mm/aaaa"
                  className="tabular-nums"
                />
              </div>
            </div>

            <div className="flex flex-col gap-1.5">
              <Label htmlFor="trial">Fim do trial</Label>
              <Input
                id="trial"
                value={vigencia.data.trial_end_date}
                onChange={(e) => vigencia.setData('trial_end_date', e.target.value)}
                placeholder="dd/mm/aaaa"
                className="tabular-nums"
              />
              <p className="text-[11px] text-muted-foreground">Em branco = assinatura sem período de teste.</p>
            </div>

            {/* UC-SA-009: a pergunta na cabeça de quem prorroga é "isso vai cobrar de novo?". */}
            <p className="text-[11px] text-muted-foreground">
              Esticar o fim prorroga o acesso sem gerar cobrança nova.
            </p>
          </div>
        )}

        <SheetFooter>
          <Button variant="ghost" size="sm" onClick={onFechar}>
            Fechar
          </Button>
          <Button
            size="sm"
            disabled={modo === 'status' ? status.processing : vigencia.processing}
            onClick={modo === 'status' ? enviarStatus : enviarVigencia}
          >
            {modo === 'status' ? 'Aplicar' : 'Salvar datas'}
          </Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  );
}

AssinaturasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default AssinaturasIndex;
