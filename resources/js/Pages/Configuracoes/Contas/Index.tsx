// @memcofre
//   tela: /account/account
//   module: Configuracoes
//   stories: thread sistema/playbook/06 (Contas)
//   permissao: account.access
//
// Contas de pagamento do negócio. Atrás da flag `useV2ConfiguracoesContas` (AccountController::FLAG_V2);
// desligada, a rota segue na Blade `account/index`. `?legacy=1` abre a Blade com a flag ligada.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-contas.md
// Âncora de design: prototipo-ui/cowork/Wagner/financeiro-legado.jsx → telaContas() (rota fin-bancos)
//
// O saldo é valor (regra mestre). A tela não calcula nem formata saldo: mostra o `saldo_texto` que o controller
// formata com o mesmo num_f da Blade, sobre o mesmo SQL (AccountController::consultaContas). Sem total no rodapé
// nem KPIs de soma — somar saldo é decisão [W] (contas-parity #9). Cadastrar, editar, depositar, transferir e
// encerrar seguem nos modais da Blade (?legacy=1) até o drawer da F3-2.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { BookOpen, ExternalLink, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface Conta {
  id: number;
  nome: string;
  numero: string;
  observacao: string | null;
  tipo: string | null;
  subtipo: string | null;
  saldo: number;
  saldo_texto: string;
  detalhes: { rotulo: string; valor: string }[];
  adicionado_por: string;
  encerrada: boolean;
}
interface Props {
  contas?: Conta[];
  pagamentos_sem_conta?: number;
}

type Situacao = 'ativas' | 'encerradas';

function ContasIndex({ contas, pagamentos_sem_conta }: Props) {
  const todas = useMemo(() => contas ?? [], [contas]);
  const [situacao, setSituacao] = useState<Situacao>('ativas');
  const [q, setQ] = useState('');
  const busca = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, []);

  const termo = q.trim().toLowerCase();
  const visiveis = todas.filter((c) => c.encerrada === (situacao === 'encerradas'))
    .filter((c) => !termo || `${c.nome} ${c.numero}`.toLowerCase().includes(termo));
  const ativas = todas.filter((c) => !c.encerrada).length;

  const colunas: ColumnDef<Conta, unknown>[] = [
    {
      id: 'nome', header: 'Nome',
      cell: ({ row: { original: c } }) => (
        <Inline gap={2}><span className="font-medium">{c.nome}</span>{c.encerrada && <Badge variant="outline">encerrada</Badge>}</Inline>
      ),
    },
    { id: 'tipo', header: 'Tipo de conta', cell: ({ row: { original: c } }) => c.tipo ?? '—' },
    { id: 'subtipo', header: 'Subtipo', cell: ({ row: { original: c } }) => c.subtipo ?? '—' },
    { id: 'numero', header: 'Número da conta', meta: { mono: true }, cell: ({ row: { original: c } }) => c.numero },
    { id: 'observacao', header: 'Observação', cell: ({ row: { original: c } }) => <span className="text-muted-foreground">{c.observacao || '—'}</span> },
    { id: 'saldo', header: 'Saldo', meta: { align: 'right', mono: true }, cell: ({ row: { original: c } }) => c.saldo_texto },
    {
      id: 'detalhes', header: 'Detalhes da conta',
      cell: ({ row: { original: c } }) => c.detalhes.length > 0
        ? <Stack gap={0}>{c.detalhes.map((d) => <span key={d.rotulo}>{d.rotulo}: {d.valor}</span>)}</Stack>
        : <span className="text-muted-foreground">—</span>,
    },
    { id: 'quem', header: 'Adicionado por', cell: ({ row: { original: c } }) => c.adicionado_por || '—' },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: c } }) => (
        <Button asChild variant="ghost" size="sm">
          <a href={`/account/account/${c.id}`} aria-label={`Livro da conta ${c.nome}`}><BookOpen className="size-4" /> Livro</a>
        </Button>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Contas de pagamento"
          subtitle={contas ? <><strong>{ativas}</strong> {ativas === 1 ? 'conta ativa' : 'contas ativas'}</> : 'contas e saldos do negócio'}
          subnav={<ConfiguracoesSubNav />}
          actions={
            <Button asChild variant="outline">
              <a href="/account/account?legacy=1"><ExternalLink className="size-4" /> Cadastrar e movimentar na tela clássica</a>
            </Button>
          } />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="pagamentos_sem_conta" fallback={null}>
          {(pagamentos_sem_conta ?? 0) > 0 ? (
            <Stack data-contract="aviso-sem-conta" gap={1} className="rounded-md border p-4 text-sm">
              <strong>{pagamentos_sem_conta} {pagamentos_sem_conta === 1 ? 'pagamento sem conta vinculada' : 'pagamentos sem conta vinculada'}</strong>
              <a className="text-sm font-medium underline" href="/account/payment-account-report">Ver detalhes</a>
            </Stack>
          ) : null}
        </Deferred>

        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar conta…" aria-label="Buscar conta pelo nome ou número" />
          </div>
          <Inline data-contract="filtro-situacao" gap={1} role="group" aria-label="Situação da conta">
            {(['ativas', 'encerradas'] as const).map((s) => (
              <Button key={s} variant={situacao === s ? 'default' : 'outline'} size="sm" aria-pressed={situacao === s} onClick={() => setSituacao(s)}>
                {s === 'ativas' ? 'Ativas' : 'Encerradas'}
              </Button>
            ))}
          </Inline>
        </Inline>

        <Deferred data="contas" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando contas…</p>}>
          {todas.length > 0 ? (
            <div data-contract="contas-table">
              <DataTable columns={colunas} data={visiveis} caption="Contas de pagamento do negócio" rowKey={(c) => c.id}
                emptyMessage={situacao === 'ativas' ? 'Nenhuma conta ativa com esse termo.' : 'Nenhuma conta encerrada com esse termo.'} />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma conta cadastrada" description="Cadastre o caixa e as contas bancárias que recebem e pagam."
                action={<Button asChild><a href="/account/account?legacy=1">Cadastrar na tela clássica</a></Button>} />
            </div>
          )}
        </Deferred>
      </Stack>
    </div>
  );
}

ContasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ContasIndex;
