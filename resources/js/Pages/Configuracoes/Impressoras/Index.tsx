// @memcofre
//   tela: /printers
//   module: Configuracoes
//   stories: thread sistema/playbook/04 (tela 1 de 3)
//   permissao: access_printers (o controller exige em todas as ações)
//
// Impressoras de cupom do negócio. Atrás da flag `useV2ConfiguracoesImpressoras` (PrinterController::FLAG_V2);
// com ela desligada a rota segue na Blade `printer/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-impressoras.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Impressoras()
//
// "Testar" do protótipo fica fora: não existe endpoint de cupom de teste no legado (RUNBOOK §10).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Search } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
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

export interface Impressora {
  id: number; nome: string; conexao: 'network' | 'windows' | 'linux'; perfil: string;
  caracteres_linha: string; ip: string; porta: string; caminho: string;
}
interface Props {
  impressoras?: Impressora[];
  opcoes: { conexao: Record<string, string>; perfil: Record<string, string> };
}

/** Rótulo em PT do tipo de conexão; o enum do banco é `network|windows|linux`. */
export const CONEXAO: Record<Impressora['conexao'], string> = { network: 'Rede', windows: 'Windows', linux: 'Linux' };

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** `destroy()` só responde a ajax() e devolve `{success, msg}` — igual à Blade. */
async function excluirNoServidor(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/printers/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível excluir (HTTP ${r.status}).` };
}

function ImpressorasIndex({ impressoras: impressorasProp, opcoes }: Props) {
  const impressoras = useMemo(() => impressorasProp ?? [], [impressorasProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Impressora | null>(null);
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
  const lista = useMemo(
    () => impressoras.filter((p) => !termo || [p.nome, p.ip, p.caminho].some((v) => v.toLowerCase().includes(termo))),
    [impressoras, termo],
  );

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await excluirNoServidor(excluir.id);
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['impressoras'] });
  };

  const colunas: ColumnDef<Impressora, unknown>[] = [
    { id: 'nome', header: 'Nome', cell: ({ row: { original: p } }) => <span className="font-medium">{p.nome}</span> },
    { id: 'conexao', header: 'Conexão', cell: ({ row: { original: p } }) => CONEXAO[p.conexao] ?? p.conexao },
    { id: 'perfil', header: 'Perfil', cell: ({ row: { original: p } }) => opcoes.perfil[p.perfil] ?? p.perfil },
    { id: 'cpl', header: 'Caracteres/linha', meta: { align: 'right', mono: true }, cell: ({ row: { original: p } }) => p.caracteres_linha || '—' },
    {
      id: 'destino', header: 'Endereço',
      cell: ({ row: { original: p } }) => (
        <span className="font-mono text-xs">{p.conexao === 'network' ? `${p.ip || '—'}:${p.porta || '—'}` : p.caminho || '—'}</span>
      ),
    },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: p } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${p.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(p)}>Excluir impressora</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Impressoras"
          subtitle={impressorasProp ? <><strong>{impressoras.length}</strong> {impressoras.length === 1 ? 'impressora de cupom' : 'impressoras de cupom'}</> : 'impressoras de cupom'} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar impressora, IP ou caminho…" aria-label="Buscar impressora" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="impressoras" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando impressoras…</p>}>
          {impressoras.length > 0 ? (
            <div data-contract="impressoras-table">
              <DataTable columns={colunas} data={lista} caption="Impressoras do negócio" rowKey={(p) => p.id}
                emptyMessage="Nenhuma impressora com esse termo. Limpe a busca para ver todas." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma impressora cadastrada" description="Sem impressora o cupom sai pelo diálogo do navegador." />
            </div>
          )}
        </Deferred>
      </Stack>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">Os caixas que usam esta impressora voltam a imprimir pelo navegador.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

ImpressorasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ImpressorasIndex;
