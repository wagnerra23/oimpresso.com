// @memcofre
//   tela: /users
//   module: User
//   stories: thread sistema/playbook/01
//   permissao: user.view ou user.create (ver) · user.create · user.update · user.delete
//
// Usuários: quem acessa o ERP do negócio. Mesma lista da DataTable da Blade (sem comissionados).
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/User/RUNBOOK-usuarios.md
// Âncora de design: prototipo-ui/cowork/Wagner/usuarios-page.jsx → UsuariosPage()
//
// Só entra pela chave `mwart.sistema_usuarios_index` (nasce desligada) ou com X-Inertia.
// Do protótipo ficam fora (RUNBOOK §10): convite, link de redefinição, último acesso, 2 etapas,
// ativar/desativar na lista, atividade e vendas/OS no nome — o legado não tem a fonte.
// Cadastrar e editar levam às telas Blade: o update() zera o que não chega no corpo.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Select, SelectContent, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { PageHeader } from '@/Components/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import DataTable from '@/Components/shared/DataTable';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';
import type { ColumnDef } from '@tanstack/react-table';
import { Grid, Inline, Stack } from '@/Components/layout';

interface Usuario {
  id: number; usuario: string | null; nome: string; email: string | null;
  login: boolean; ativo: boolean; funcao: string | null; voce: boolean;
}
interface Pode { criar: boolean; ver: boolean; editar: boolean; excluir: boolean }
interface Props { usuarios?: Usuario[]; pode: Pode }

const TODOS = 'todos';
const parar = (e: { stopPropagation: () => void }) => e.stopPropagation();
const iniciais = (u: Usuario) => u.nome.split(/\s+/).filter(Boolean).slice(-2).map((p) => p[0]).join('').toUpperCase() || '?';

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/**
 * Mesmo pedido que o botão de excluir da Blade: verbo DELETE de verdade + AJAX (o destroy() só
 * responde a AJAX). POST com `_method=DELETE` cai em 405 nesta rota (medido no CT 100, 2026-10-07).
 */
async function excluirUsuario(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/users/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível excluir (HTTP ${r.status}).` };
}

function Situacao({ u }: { u: Usuario }) {
  return <Badge variant={u.ativo ? 'success' : 'neutral'} dot>{u.ativo ? 'Ativo' : 'Inativo'}</Badge>;
}

function UsuariosIndex({ usuarios: usuariosProp, pode }: Props) {
  const usuarios = useMemo(() => usuariosProp ?? [], [usuariosProp]);
  const [q, setQ] = useState('');
  const [fFuncao, setFFuncao] = useState(TODOS);
  const [fSituacao, setFSituacao] = useState(TODOS);
  const [detalhe, setDetalhe] = useState<Usuario | null>(null);
  const [excluir, setExcluir] = useState<Usuario | null>(null);
  const [confirmacao, setConfirmacao] = useState('');
  const [aviso, setAviso] = useState<string | null>(null);
  const busca = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if (/^(INPUT|TEXTAREA|SELECT)$/.test((e.target as HTMLElement).tagName)) return;
      if (e.key === '/') { e.preventDefault(); busca.current?.focus(); }
    };
    document.addEventListener('keydown', k);
    return () => document.removeEventListener('keydown', k);
  }, []);

  const funcoes = useMemo(() => {
    const m = new Map<string, number>();
    usuarios.forEach((u) => { if (u.funcao) m.set(u.funcao, (m.get(u.funcao) ?? 0) + 1); });
    return [...m.entries()].sort(([a], [b]) => a.localeCompare(b, 'pt-BR'));
  }, [usuarios]);
  const ativos = usuarios.filter((u) => u.ativo).length;

  const termo = q.trim().toLowerCase();
  const lista = useMemo(() => usuarios.filter((u) => {
    if (fFuncao !== TODOS && u.funcao !== fFuncao) return false;
    if (fSituacao !== TODOS && (fSituacao === 'ativo') !== u.ativo) return false;
    return !termo || [u.nome, u.usuario, u.email, u.funcao].some((v) => (v ?? '').toLowerCase().includes(termo));
  }), [usuarios, fFuncao, fSituacao, termo]);
  const filtrando = fFuncao !== TODOS || fSituacao !== TODOS;

  const podeExcluir = (u: Usuario) => pode.excluir && !u.voce;
  const abrirExcluir = (u: Usuario) => { setDetalhe(null); setConfirmacao(''); setExcluir(u); };
  // Quem tem login confirma digitando o usuário (protótipo, D5 [W] 2026-08-19).
  const liberaExclusao = !!excluir && (!excluir.usuario || confirmacao.trim() === excluir.usuario);
  const confirmarExclusao = async () => {
    if (!excluir || !liberaExclusao) return;
    const r = await excluirUsuario(excluir.id);
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['usuarios'] });
  };

  const colunas: ColumnDef<Usuario, unknown>[] = [
    {
      id: 'usuario', header: 'Usuário',
      cell: ({ row: { original: u } }) => (
        <Inline gap={3}>
          <Avatar aria-hidden className="size-8"><AvatarFallback className="text-xs font-semibold">{iniciais(u)}</AvatarFallback></Avatar>
          <Stack gap={0}>
            <span className="font-medium">{u.nome || '—'}{u.voce && <Badge variant="outline" className="ml-2">você</Badge>}</span>
            <span className="text-xs text-muted-foreground">{u.usuario ? `@${u.usuario}` : 'sem login'}</span>
          </Stack>
        </Inline>
      ),
    },
    { id: 'funcao', header: 'Função', cell: ({ row: { original: u } }) => (u.funcao ? <Badge variant="outline">{u.funcao}</Badge> : <span className="text-muted-foreground">—</span>) },
    { id: 'email', header: 'E-mail', cell: ({ row: { original: u } }) => <span className="text-muted-foreground">{u.email || '—'}</span> },
    { id: 'situacao', header: 'Situação', cell: ({ row: { original: u } }) => <Situacao u={u} /> },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      // O menu não abre o drawer junto (UC-USR-01 do protótipo): a linha é clicável e responde a
      // Enter/Espaço, e o React propaga clique e tecla do portal do menu até ela.
      cell: ({ row: { original: u } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="sm" aria-label={`Ações de ${u.nome}`} onClick={parar} onKeyDown={parar}>
              <MoreHorizontal className="size-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" onClick={parar} onKeyDown={parar}>
            <DropdownMenuItem onSelect={() => setDetalhe(u)}>Ver detalhes</DropdownMenuItem>
            {pode.editar && <DropdownMenuItem asChild><a href={`/users/${u.id}/edit`}>Editar usuário</a></DropdownMenuItem>}
            {podeExcluir(u) && (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem className="text-destructive" onSelect={() => abrirExcluir(u)}>Excluir</DropdownMenuItem>
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Usuários"
          subtitle={usuariosProp ? <><strong>{usuarios.length}</strong> usuários · {ativos} ativos · {funcoes.length} funções</> : 'quem acessa o sistema'}
          actions={<>
            <Button variant="ghost" asChild><Link href="/roles">Funções</Link></Button>
            {pode.criar && <Button asChild><a href="/users/create"><Plus className="size-4" /> Novo usuário</a></Button>}
          </>} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Deferred data="usuarios" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando usuários…</p>}>
          <>
            <div data-contract="kpis">
              <KpiGrid cols={4}>
                <KpiCard label="Total de usuários" value={usuarios.length} />
                <KpiCard label="Ativos" value={ativos} />
                <KpiCard label="Inativos" value={usuarios.length - ativos} />
                <KpiCard label="Funções" value={funcoes.length} />
              </KpiGrid>
            </div>

            <Inline data-contract="toolbar" wrap gap={2}>
              <div className="relative min-w-64 flex-1">
                <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
                  placeholder="Buscar nome, usuário, e-mail ou função…" aria-label="Buscar usuário" />
              </div>
              <Select value={fFuncao} onValueChange={setFFuncao}>
                <SelectTrigger className="w-48" aria-label="Filtrar por função"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SafeSelectItem value={TODOS}>Todas as funções</SafeSelectItem>
                  {funcoes.map(([f, n]) => <SafeSelectItem key={f} value={f}>{f} ({n})</SafeSelectItem>)}
                </SelectContent>
              </Select>
              <Select value={fSituacao} onValueChange={setFSituacao}>
                <SelectTrigger className="w-40" aria-label="Filtrar por situação"><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SafeSelectItem value={TODOS}>Todas as situações</SafeSelectItem>
                  <SafeSelectItem value="ativo">Ativos ({ativos})</SafeSelectItem>
                  <SafeSelectItem value="inativo">Inativos ({usuarios.length - ativos})</SafeSelectItem>
                </SelectContent>
              </Select>
              {filtrando && <Button variant="ghost" size="sm" onClick={() => { setFFuncao(TODOS); setFSituacao(TODOS); }}>Limpar</Button>}
              <span className="text-sm text-muted-foreground">{lista.length} de {usuarios.length}</span>
            </Inline>
            {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

            {usuarios.length > 0 ? (
              <div data-contract="usuarios-table">
                <DataTable columns={colunas} data={lista} caption="Usuários do negócio" rowKey={(u) => u.id}
                  onRowClick={(u) => setDetalhe(u)}
                  emptyMessage="Nenhum usuário encontrado. Ajuste a busca ou os filtros de função e situação." />
              </div>
            ) : (
              <div data-contract="vazio">
                <EmptyState title="Nenhum usuário ainda" description="Cadastre quem vai acessar o sistema."
                  action={pode.criar ? <Button asChild><a href="/users/create">Cadastrar o primeiro</a></Button> : undefined} />
              </div>
            )}
          </>
        </Deferred>
      </Stack>

      <Sheet open={!!detalhe} onOpenChange={(v) => !v && setDetalhe(null)}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-md" data-contract="usuario-drawer">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{detalhe?.nome || 'Usuário'}</h2></SheetTitle>
              <SheetDescription asChild>
                <p>{[detalhe?.usuario ? `@${detalhe.usuario}` : 'sem login', detalhe?.email].filter(Boolean).join(' · ')}</p>
              </SheetDescription>
            </div>
          </header>
          {detalhe && (
            <Stack gap={4} className="p-5 text-sm">
              <Stack gap={2}>
                <h3 className="text-xs font-semibold uppercase text-muted-foreground">Acesso</h3>
                <Grid cols={2} gap={3} asChild><dl>
                  <div><dt className="text-xs text-muted-foreground">Função</dt><dd>{detalhe.funcao ?? '—'}</dd></div>
                  <div><dt className="text-xs text-muted-foreground">Situação</dt><dd><Situacao u={detalhe} /></dd></div>
                  <div><dt className="text-xs text-muted-foreground">Entra no sistema</dt><dd>{detalhe.login ? 'Sim' : 'Não'}</dd></div>
                </dl></Grid>
                <Inline gap={2}><Button variant="outline" size="sm" asChild><Link href="/roles">Ver permissões das funções</Link></Button></Inline>
              </Stack>
              <Inline gap={2} justify="end" className="border-t pt-4">
                {podeExcluir(detalhe) && <Button variant="ghost" className="text-destructive" onClick={() => abrirExcluir(detalhe)}>Excluir</Button>}
                {pode.ver && <Button variant="outline" asChild><a href={`/users/${detalhe.id}`}>Abrir ficha</a></Button>}
                {pode.editar && <Button asChild><a href={`/users/${detalhe.id}/edit`}>Editar usuário</a></Button>}
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader>
            <AlertDialogTitle>{`Excluir ${excluir?.nome ?? ''}?`}</AlertDialogTitle>
          </AlertDialogHeader>
          <p className="text-sm">A pessoa perde o acesso ao sistema. A ação não tem volta pela tela.</p>
          {excluir?.usuario && (
            <Stack gap={1}>
              <label htmlFor="usr-confirma" className="text-sm">Para confirmar, digite <b>{excluir.usuario}</b>.</label>
              <Input id="usr-confirma" value={confirmacao} onChange={(e) => setConfirmacao(e.target.value)} placeholder={excluir.usuario} autoComplete="off" />
            </Stack>
          )}
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction disabled={!liberaExclusao} className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

UsuariosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default UsuariosIndex;
