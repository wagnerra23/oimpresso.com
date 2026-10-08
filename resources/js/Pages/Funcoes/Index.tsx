// @memcofre
//   tela: /roles
//   module: User
//   stories: thread sistema/playbook/02
//   permissao: roles.view (lista) · roles.create · roles.update · roles.delete
//
// Funções e permissões do negócio. Atrás da flag `useV2SistemaFuncoes` (RoleController::FLAG_V2);
// com ela desligada a rota segue na Blade `role/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/User/RUNBOOK-funcoes.md
// Âncora de design: prototipo-ui/cowork/Wagner/funcoes-page.jsx → FuncoesPage (lista)
//
// Cadastrar e editar abrem a Blade: o formulário tem 153 controles do núcleo + os dos módulos, e o editor
// React (F3-2) vem depois. Descrição e cor por função do protótipo não têm fonte no banco.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Plus, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
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

export interface Funcao { id: number; nome: string; padrao: boolean; editavel: boolean; usuarios: number }
interface Props {
  funcoes?: Funcao[];
  pode: { criar: boolean; editar: boolean; excluir: boolean };
}

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** `destroy()` só responde a ajax(); devolve `{success, msg}`, e 422 quando a função está em uso. */
async function excluirNoServidor(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/roles/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && 'success' in corpo) return { success: corpo.success === true, msg: corpo.msg };
  return { success: false, msg: `Não foi possível excluir (HTTP ${r.status}).` };
}

const usuariosTxt = (n: number) => (n === 1 ? '1 usuário' : `${n} usuários`);

function FuncoesIndex({ funcoes: funcoesProp, pode }: Props) {
  const funcoes = useMemo(() => funcoesProp ?? [], [funcoesProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Funcao | null>(null);
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

  const termo = q.trim().toLowerCase();
  const lista = useMemo(() => funcoes.filter((f) => !termo || f.nome.toLowerCase().includes(termo)), [funcoes, termo]);

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await excluirNoServidor(excluir.id);
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['funcoes'] });
  };

  const colunas: ColumnDef<Funcao, unknown>[] = [
    {
      id: 'nome', header: 'Função',
      cell: ({ row: { original: f } }) => (
        <Inline gap={2}>
          <span className="font-medium">{f.nome}</span>
          {f.padrao && <Badge variant="secondary">Padrão</Badge>}
        </Inline>
      ),
    },
    { id: 'usuarios', header: 'Usuários', meta: { align: 'right', mono: true }, cell: ({ row: { original: f } }) => usuariosTxt(f.usuarios) },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: f } }) => (f.editavel && (pode.editar || pode.excluir) ? (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${f.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {pode.editar && <DropdownMenuItem asChild><a href={`/roles/${f.id}/edit`}>Editar permissões</a></DropdownMenuItem>}
            {pode.excluir && <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(f)}>Excluir função</DropdownMenuItem>}
          </DropdownMenuContent>
        </DropdownMenu>
      ) : null),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Funções e permissões"
          subtitle={funcoesProp ? <><strong>{funcoes.length}</strong> {funcoes.length === 1 ? 'função' : 'funções'} no negócio</> : 'funções do negócio'}
          actions={pode.criar ? <Button asChild><a href="/roles/create"><Plus className="size-4" /> Nova função</a></Button> : undefined} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar função…" aria-label="Buscar função" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="funcoes" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando funções…</p>}>
          {funcoes.length > 0 ? (
            <div data-contract="funcoes-table">
              <DataTable columns={colunas} data={lista} caption="Funções do negócio" rowKey={(f) => f.id}
                emptyMessage="Nenhuma função com esse nome. Limpe a busca para ver todas." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma função cadastrada" description="Crie uma função para dizer o que cada usuário pode fazer." />
            </div>
          )}
        </Deferred>
      </Stack>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir a função {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">Função com usuário não é excluída: troque a função deles antes.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

FuncoesIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default FuncoesIndex;
