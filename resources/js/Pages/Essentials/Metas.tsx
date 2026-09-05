// @docvault
//   tela: /hrm/sales-target
//   module: Essentials
//   status: implementada
//   tests: Modules/Essentials/Tests/Feature/HrmMetasTest
//
// Essentials/Metas — carimbada do PT-01 Lista por criar-tela.mjs (UI-0013).
// Onda 9 do EXPORT-HRM-2026-09-04 / PR-9 do PEDIDO-CL-hrm. F1 = RUNBOOK-metas.md.
//
// ESTA TELA TOCA VALOR — e o que ela faz com ele:
//   NÃO calcula. Nenhuma aritmética de comissão acontece aqui. As faixas são exibidas como
//   estão gravadas em essentials_user_sales_targets e, ao salvar, voltam ao servidor como
//   TEXTO pt-BR (formatDecimalPtBR, 2 casas) — a MESMA forma que o modal Blade legado envia
//   via @num_format. Quem interpreta o número segue sendo Util::num_uf; quem valida as faixas
//   segue sendo SalesTargetFaixaValidator (PR #6799). O pipeline de valor é o mesmo.
//
//   Por que TEXTO e não float: num_uf lê "1.234,56" como mil duzentos e trinta e quatro
//   vírgula cinquenta e seis, mas leria o float cru 204.99605 como vinte milhões — 1 ponto
//   seguido de mais de 2 dígitos é separador de milhar na heurística pt-BR. É o incidente de
//   2026-06-05 (regra mestre de valor, memory/proibicoes.md).
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import { Deferred, router } from '@inertiajs/react';
import { useState } from 'react';
import type { ColumnDef } from '@tanstack/react-table';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Label } from '@/Components/ui/label';
import { Skeleton } from '@/Components/ui/skeleton';
import { NumericInputPtBR } from '@/Components/ui/numeric-input-ptbr';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { formatDecimalPtBR } from '@/Lib/numberPtBR';
import { Info, Plus, Target, Trash2 } from 'lucide-react';

interface Faixa {
  /** id da linha em essentials_user_sales_targets; ausente = faixa nova (ainda não gravada). */
  id?: number;
  inicio: number;
  fim: number;
  percentual: number;
}

interface Colaborador {
  id: number;
  nome: string;
  faixas: Faixa[];
}

interface Paginator {
  data: Colaborador[];
  total: number;
  current_page: number;
  last_page: number;
  from: number | null;
  to: number | null;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
  filtros: { q: string };
  sem_imposto: boolean;
  /** Chega depois do primeiro render — é `Inertia::defer` (prop cara: paginate + whereIn). */
  paginator?: Paginator;
}

const dinheiro = (n: number) => `R$ ${formatDecimalPtBR(n, 2)}`;
const percentual = (n: number) => `${formatDecimalPtBR(n, 2)}%`;
const Traco = () => <span className="text-muted-foreground">—</span>;

/** Menor início e maior fim do conjunto — leitura das faixas gravadas, não derivação de valor novo. */
const extremos = (faixas: Faixa[]) => ({
  inicio: Math.min(...faixas.map((f) => f.inicio)),
  fim: Math.max(...faixas.map((f) => f.fim)),
});

export default function Metas({ filtros, sem_imposto, paginator }: Props) {
  const [emEdicao, setEmEdicao] = useState<Colaborador | null>(null);

  const columns: ColumnDef<Colaborador>[] = [
    {
      accessorKey: 'nome',
      header: 'Colaborador',
      cell: ({ row }) => <span className="font-medium">{row.original.nome}</span>,
    },
    {
      id: 'faixas',
      header: 'Faixas',
      meta: { align: 'right', mono: true, width: 90 },
      cell: ({ row }) => row.original.faixas.length || <Traco />,
    },
    {
      id: 'inicio',
      header: 'Meta inicial',
      meta: { align: 'right', mono: true, width: 150 },
      cell: ({ row }) =>
        row.original.faixas.length ? dinheiro(extremos(row.original.faixas).inicio) : <Traco />,
    },
    {
      id: 'fim',
      header: 'Meta final',
      meta: { align: 'right', mono: true, width: 150 },
      cell: ({ row }) =>
        row.original.faixas.length ? dinheiro(extremos(row.original.faixas).fim) : <Traco />,
    },
    {
      id: 'comissao',
      header: 'Comissão',
      meta: { align: 'right', mono: true, width: 150 },
      cell: ({ row }) => {
        const pcts = row.original.faixas.map((f) => f.percentual);
        if (!pcts.length) return <Traco />;
        const min = Math.min(...pcts);
        const max = Math.max(...pcts);
        return min === max ? percentual(min) : `${percentual(min)} – ${percentual(max)}`;
      },
    },
    {
      id: 'situacao',
      header: 'Situação',
      meta: { width: 130 },
      cell: ({ row }) =>
        row.original.faixas.length ? (
          <Badge variant="secondary">com meta</Badge>
        ) : (
          <Badge variant="outline">sem meta</Badge>
        ),
    },
    {
      id: 'acao',
      header: '',
      meta: { align: 'right', width: 160 },
      cell: ({ row }) => (
        <Button variant="ghost" size="sm" onClick={() => setEmEdicao(row.original)}>
          <Target className="mr-2 size-4" aria-hidden="true" />
          {row.original.faixas.length ? 'Editar faixas' : 'Definir meta'}
        </Button>
      ),
    },
  ];

  return (
    <AppShellV2>
      <div data-contract="cabecalho">
        <PageHeader
          title="Metas de venda"
          subtitle="Faixas de valor vendido e o percentual de comissão de cada colaborador."
        />
      </div>

      <div data-contract="filtros" className="mb-4">
        <Alert>
          <Info className="size-4" aria-hidden="true" />
          <AlertTitle>Esta tela cadastra a meta — não apura o resultado</AlertTitle>
          <AlertDescription>
            Quanto cada colaborador vendeu no mês, e quanto isso vira de comissão, não é
            calculado aqui: quem apura é a folha de pagamento. A base configurada no módulo é{' '}
            <strong>{sem_imposto ? 'sem imposto' : 'com imposto'}</strong> — o valor vendido
            entra {sem_imposto ? 'sem' : 'com'} tributo quando a folha faz essa conta.
          </AlertDescription>
        </Alert>
      </div>

      <div data-contract="lista">
        <Deferred data="paginator" fallback={<Skeleton className="h-96 w-full" />}>
          {paginator ? (
            <DataTable
              columns={columns}
              data={paginator.data}
              pagination={paginator}
              endpoint="/hrm/sales-target"
              caption="Colaboradores e as faixas de meta de venda cadastradas"
              showSearch
              initialSearch={filtros.q}
              searchPlaceholder="Buscar colaborador..."
              emptyMessage="Nenhum colaborador encontrado."
              rowKey={(c) => c.id}
            />
          ) : null}
        </Deferred>
      </div>

      {emEdicao && <DialogoFaixas colaborador={emEdicao} onFechar={() => setEmEdicao(null)} />}
    </AppShellV2>
  );
}

/**
 * Editor das faixas de um colaborador — equivalente Inertia do sales_target_modal.blade.php.
 *
 * Os nomes dos campos enviados são IDÊNTICOS aos do Blade (edit_target[id][...] para as faixas
 * já gravadas; sales_amount_start[]/sales_amount_end[]/commission[] para as novas), porque
 * SalesTargetController::montarFaixas lê exatamente esses nomes. Trocar qualquer um quebraria
 * o salvamento em silêncio.
 */
function DialogoFaixas({
  colaborador,
  onFechar,
}: {
  colaborador: Colaborador;
  onFechar: () => void;
}) {
  const [faixas, setFaixas] = useState<Faixa[]>(colaborador.faixas);
  const [enviando, setEnviando] = useState(false);

  const alterar = (i: number, campo: 'inicio' | 'fim' | 'percentual', valor: number) =>
    setFaixas((atual) => atual.map((f, j) => (j === i ? { ...f, [campo]: valor } : f)));

  const salvar = () => {
    // TEXTO pt-BR com 2 casas — nunca o float cru. Ver o cabeçalho do arquivo.
    const texto = (n: number) => formatDecimalPtBR(n, 2);

    const edit_target: Record<string, Record<string, string>> = {};
    const sales_amount_start: string[] = [];
    const sales_amount_end: string[] = [];
    const commission: string[] = [];

    for (const f of faixas) {
      if (f.id) {
        edit_target[String(f.id)] = {
          target_start: texto(f.inicio),
          target_end: texto(f.fim),
          commission_percent: texto(f.percentual),
        };
      } else {
        sales_amount_start.push(texto(f.inicio));
        sales_amount_end.push(texto(f.fim));
        commission.push(texto(f.percentual));
      }
    }

    setEnviando(true);
    router.post(
      '/hrm/save-sales-target',
      { user_id: colaborador.id, edit_target, sales_amount_start, sales_amount_end, commission },
      { onFinish: () => setEnviando(false), onSuccess: onFechar, preserveScroll: true },
    );
  };

  return (
    <Dialog open onOpenChange={(aberto) => !aberto && onFechar()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Meta de venda de {colaborador.nome}</DialogTitle>
          <DialogDescription>
            Cada faixa paga o percentual quando o total vendido cai dentro dela. As faixas não
            podem se sobrepor, nem encostar ponta com ponta — o servidor recusa e diz o motivo.
          </DialogDescription>
        </DialogHeader>

        {faixas.length === 0 ? (
          <p className="py-4 text-sm text-muted-foreground">
            Nenhuma faixa cadastrada. Sem faixa, a comissão de meta deste colaborador é zero.
          </p>
        ) : (
          <div className="space-y-3">
            {faixas.map((f, i) => (
              <div key={f.id ?? `nova-${i}`} className="grid grid-cols-[1fr_1fr_1fr_auto] gap-3">
                <div>
                  <Label htmlFor={`inicio-${i}`}>Vendido de</Label>
                  <NumericInputPtBR
                    id={`inicio-${i}`}
                    value={f.inicio}
                    onChange={(n) => alterar(i, 'inicio', n)}
                  />
                </div>
                <div>
                  <Label htmlFor={`fim-${i}`}>até</Label>
                  <NumericInputPtBR
                    id={`fim-${i}`}
                    value={f.fim}
                    onChange={(n) => alterar(i, 'fim', n)}
                  />
                </div>
                <div>
                  <Label htmlFor={`pct-${i}`}>Comissão (%)</Label>
                  <NumericInputPtBR
                    id={`pct-${i}`}
                    value={f.percentual}
                    onChange={(n) => alterar(i, 'percentual', n)}
                  />
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  className="self-end"
                  aria-label={`Remover faixa ${i + 1}`}
                  onClick={() => setFaixas((atual) => atual.filter((_, j) => j !== i))}
                >
                  <Trash2 className="size-4" aria-hidden="true" />
                </Button>
              </div>
            ))}
          </div>
        )}

        <DialogFooter className="sm:justify-between">
          <Button
            variant="outline"
            onClick={() => setFaixas((atual) => [...atual, { inicio: 0, fim: 0, percentual: 0 }])}
          >
            <Plus className="mr-2 size-4" aria-hidden="true" />
            Adicionar faixa
          </Button>
          <div className="flex gap-2">
            <Button variant="ghost" onClick={onFechar}>
              Cancelar
            </Button>
            <Button onClick={salvar} disabled={enviando}>
              {enviando ? 'Salvando...' : 'Salvar faixas'}
            </Button>
          </div>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
