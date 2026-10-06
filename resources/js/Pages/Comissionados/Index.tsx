// @memcofre
//   tela: /sales-commission-agents
//   module: Comissao
//   stories: thread sistema/playbook/03 (= comissoes/playbook/01, ponteiro)
//   permissao: commission_agent.view (ver) · commission_agent.manage (cadastrar, editar, remover)
//
// Comissionados: quem recebe comissão sobre a venda. Um comissionado é uma linha de `users` com
// `is_cmmsn_agnt = 1` — não um cadastro à parte.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/comissionados-page.jsx → ComissionadosPage()
//
// Do protótipo ficam fora (RUNBOOK-comissionados §10): KPIs de vendas/comissão/a pagar, período,
// meta, pagamento e regra por faixa/margem — dependem do relatório (comissoes/02) e da ADR 0151.
// O percentual viaja como TEXTO pt-BR ("2,50"), igual ao input_number da Blade: o `num_uf` do
// store/update continua sendo o único parser (regra mestre de valor).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';

interface Agente {
  id: number; prefixo: string; primeiro_nome: string; sobrenome: string; email: string;
  contato: string; endereco: string; percentual: number; vendas: number;
}
interface Props { agentes?: Agente[]; pode: { gerenciar: boolean } }
interface Edicao {
  id?: number; prefixo: string; primeiro_nome: string; sobrenome: string; email: string;
  contato: string; endereco: string; percentual: string;
}

/** 2.5 → "2,50". Mesmo formato que o num_format da Blade punha no campo. */
const paraTexto = (n: number) => n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const nomeDe = (a: Agente) => [a.prefixo, a.primeiro_nome, a.sobrenome].filter(Boolean).join(' ');
const iniciais = (a: Agente) => [a.primeiro_nome, a.sobrenome].filter(Boolean).map((p) => p[0]).join('').toUpperCase() || '?';
const VAZIO: Edicao = { prefixo: '', primeiro_nome: '', sobrenome: '', email: '', contato: '', endereco: '', percentual: '' };
const CAMPOS_LINHA = [
  ['prefixo', 'Prefixo', 'Ex.: Sr.'], ['primeiro_nome', 'Primeiro nome *', ''], ['sobrenome', 'Sobrenome', ''],
  ['email', 'E-mail', ''], ['contato', 'Contato', '(31) 99999-0000'],
] as const;

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** Mesmo corpo que a Blade mandava; `X-Requested-With` porque update/destroy exigem ajax(). */
async function enviar(url: string, campos: Record<string, string>): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
    body: new URLSearchParams(campos),
  });
  // O 422 do destroy traz {success:false, msg}: lê o corpo antes de cair no erro genérico.
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function ComissionadosIndex({ agentes: agentesProp, pode }: Props) {
  const agentes = useMemo(() => agentesProp ?? [], [agentesProp]);
  const [q, setQ] = useState('');
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [remover, setRemover] = useState<Agente | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [aviso, setAviso] = useState<string | null>(null);
  const [salvando, setSalvando] = useState(false);
  const busca = useRef<HTMLInputElement>(null);

  const novo = useCallback(() => { setErro(null); setEdicao({ ...VAZIO }); }, []);
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
      if (e.key === 'n' && pode.gerenciar) { e.preventDefault(); novo(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, [pode.gerenciar, novo]);

  const termo = q.trim().toLowerCase();
  const lista = useMemo(
    () => agentes.filter((a) => !termo || [nomeDe(a), a.email].some((v) => v.toLowerCase().includes(termo))),
    [agentes, termo],
  );

  const podeSalvar = !!edicao && edicao.primeiro_nome.trim() !== '' && edicao.percentual.trim() !== '';
  const salvar = async () => {
    if (!edicao || !podeSalvar) return;
    setSalvando(true); setErro(null);
    const campos: Record<string, string> = {
      surname: edicao.prefixo.trim(), first_name: edicao.primeiro_nome.trim(), last_name: edicao.sobrenome.trim(),
      email: edicao.email.trim(), contact_no: edicao.contato.trim(), address: edicao.endereco.trim(),
      cmmsn_percent: edicao.percentual.trim(),
    };
    if (edicao.id) campos._method = 'PUT';
    const r = await enviar(edicao.id ? `/sales-commission-agents/${edicao.id}` : '/sales-commission-agents', campos);
    setSalvando(false);
    if (!r.success) { setErro(r.msg || 'Não foi possível salvar o comissionado.'); return; }
    setEdicao(null);
    setAviso(r.msg ?? null);
    router.reload({ only: ['agentes'] });
  };
  const confirmarRemocao = async () => {
    if (!remover) return;
    const r = await enviar(`/sales-commission-agents/${remover.id}`, { _method: 'DELETE' });
    setRemover(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['agentes'] });
  };
  const editar = (a: Agente) => {
    setErro(null);
    setEdicao({ id: a.id, prefixo: a.prefixo, primeiro_nome: a.primeiro_nome, sobrenome: a.sobrenome,
      email: a.email, contato: a.contato, endereco: a.endereco, percentual: paraTexto(a.percentual) });
  };
  const muda = (k: keyof Edicao, v: string) => setEdicao((e) => (e ? { ...e, [k]: v } : e));
  const bloqueado = !!remover && remover.vendas > 0;
  const colunas: ColumnDef<Agente, unknown>[] = [
    {
      id: 'comissionado', header: 'Comissionado',
      cell: ({ row: { original: a } }) => (
        <Inline gap={3}>
          <Avatar aria-hidden className="size-8"><AvatarFallback className="text-xs font-semibold">{iniciais(a)}</AvatarFallback></Avatar>
          <Stack gap={0}>
            <span className="font-medium">{nomeDe(a)}</span>
            <span className="text-xs text-muted-foreground">{a.email || '—'}</span>
          </Stack>
        </Inline>
      ),
    },
    { id: 'contato', header: 'Contato', cell: ({ row: { original: a } }) => <span className="text-muted-foreground">{a.contato || '—'}</span> },
    { id: 'comissao', header: 'Comissão', meta: { align: 'right', mono: true }, cell: ({ row: { original: a } }) => `${paraTexto(a.percentual)}%` },
    { id: 'vendas', header: 'Vendas vinculadas', meta: { align: 'right', mono: true }, cell: ({ row: { original: a } }) => a.vendas },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: a } }) => pode.gerenciar && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${nomeDe(a)}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem onSelect={() => editar(a)}>Editar comissionado</DropdownMenuItem>
            <DropdownMenuItem className="text-destructive" onSelect={() => setRemover(a)}>Remover dos comissionados</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Comissionados"
          subtitle={agentesProp ? <><strong>{agentes.length}</strong> {agentes.length === 1 ? 'agente de venda' : 'agentes de venda'} · recebem comissão sobre a venda</> : 'recebem comissão sobre a venda'}
          actions={pode.gerenciar ? <Button onClick={novo}><Plus className="size-4" /> Novo comissionado</Button> : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar comissionado…" aria-label="Buscar comissionado" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="agentes" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando comissionados…</p>}>
        {agentes.length > 0 ? (
          <div data-contract="comissionados-table">
            <DataTable columns={colunas} data={lista} caption="Comissionados do negócio" rowKey={(a) => a.id}
              emptyMessage="Nenhum comissionado com esse nome. Tente outro termo na busca." />
          </div>
        ) : (
          <div data-contract="vazio">
            <EmptyState title="Nenhum comissionado ainda" description="Cadastre quem recebe comissão sobre a venda." action={pode.gerenciar ? <Button onClick={novo}>Cadastrar o primeiro</Button> : undefined} />
          </div>
        )}
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-md">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? 'Editar comissionado' : 'Novo comissionado'}</h2></SheetTitle>
              <SheetDescription asChild><p>Agente de venda que recebe comissão sobre a venda.</p></SheetDescription>
            </div>
          </header>
          {edicao && (
            <Stack data-contract="comissionado-form" gap={3} className="p-5 text-sm">
              {CAMPOS_LINHA.map(([k, rotulo, ph]) => (
                <Stack key={k} gap={1}>
                  <label htmlFor={`cms-${k}`}>{rotulo}</label>
                  <Input id={`cms-${k}`} value={edicao[k]} placeholder={ph} onChange={(e) => muda(k, e.target.value)} />
                </Stack>
              ))}
              <Stack gap={1}>
                <label htmlFor="cms-endereco">Endereço</label>
                <Textarea id="cms-endereco" rows={3} value={edicao.endereco} onChange={(e) => muda('endereco', e.target.value)} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="cms-percentual">Comissão (%) *</label>
                <Input id="cms-percentual" inputMode="decimal" value={edicao.percentual} placeholder="Ex.: 2,50"
                  onChange={(e) => muda('percentual', e.target.value.replace(/[^0-9,.]/g, ''))} />
              </Stack>
              {!edicao.id && (
                <p className="text-xs text-muted-foreground">Se o e-mail já for de um usuário deste negócio, ele passa a ser comissionado — o cadastro dele não muda e nenhuma linha nova é criada.</p>
              )}
              {erro && <p role="alert" className="text-destructive">{erro}</p>}
              <Inline gap={2} justify="end" className="border-t pt-4">
                <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
                <Button disabled={!podeSalvar || salvando} onClick={salvar}>{edicao.id ? 'Salvar' : 'Cadastrar'}</Button>
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!remover} onOpenChange={(v) => !v && setRemover(null)}>
        <AlertDialogContent data-contract="confirm-remover">
          <AlertDialogHeader>
            <AlertDialogTitle>{bloqueado ? 'Este comissionado não pode ser removido' : `Remover ${remover ? nomeDe(remover) : ''} dos comissionados?`}</AlertDialogTitle>
          </AlertDialogHeader>
          <p className="text-sm">
            {bloqueado && remover
              ? `${remover.vendas} ${remover.vendas === 1 ? 'venda aponta' : 'vendas apontam'} para ele. Tirá-lo da lista deixaria essas vendas sem comissionado.`
              : 'Nenhuma venda aponta para ele. O cadastro da pessoa continua; ela só deixa de ser comissionada.'}
          </p>
          <AlertDialogFooter>
            <AlertDialogCancel>{bloqueado ? 'Fechar' : 'Cancelar'}</AlertDialogCancel>
            {!bloqueado && (
              <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarRemocao}>Remover</AlertDialogAction>
            )}
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

ComissionadosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ComissionadosIndex;
