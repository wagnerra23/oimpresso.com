// @memcofre
//   tela: /types-of-service
//   module: Configuracoes
//   stories: thread sistema/playbook/05 (tela 2 de 3)
//   permissao: access_types_of_service (o controller exige em todas as ações)
//
// Tipos de serviço da venda (balcão, entrega, montagem…), com taxa de embalagem e tabela de preço por local.
// Atrás da flag `useV2ConfiguracoesTiposServico` (TypesOfServiceController::FLAG_V2); desligada, a Blade segue.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-tipos-servico.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Servicos()
//
// A taxa entra no total da venda (regra mestre de valor). A tela só MOSTRA aqui; cadastrar/editar (F3-2) mandam texto
// pt-BR e o num_uf do store/update segue único parser.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { MoreHorizontal, Search } from 'lucide-react';
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
import ConfiguracoesSubNav from '@/Pages/Configuracoes/_shared/ConfiguracoesSubNav';

export interface TipoServico {
  id: number; nome: string; descricao: string; taxa: number; tipo_taxa: 'fixed' | 'percent';
  campos_personalizados: boolean; precos_por_local: { local: string; tabela: string }[];
}
interface Props { tipos?: TipoServico[] }

/** Taxa como a Blade mostrava: valor em pt-BR, com "%" no percentual. */
const taxaTexto = (t: TipoServico) => {
  const n = t.taxa.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  return t.tipo_taxa === 'percent' ? `${n} %` : `R$ ${n}`;
};

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** destroy() só responde a ajax() e devolve `{success, msg}` — igual à Blade. */
async function excluirNoServidor(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/types-of-service/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível excluir (HTTP ${r.status}).` };
}

function TiposServicoIndex({ tipos: tiposProp }: Props) {
  const tipos = useMemo(() => tiposProp ?? [], [tiposProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<TipoServico | null>(null);
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
    () => tipos.filter((t) => !termo || [t.nome, t.descricao].some((v) => v.toLowerCase().includes(termo))),
    [tipos, termo],
  );

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await excluirNoServidor(excluir.id);
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['tipos'] });
  };

  const colunas: ColumnDef<TipoServico, unknown>[] = [
    {
      id: 'nome', header: 'Tipo de serviço',
      cell: ({ row: { original: t } }) => (
        <Stack gap={0}>
          <Inline gap={2}><span className="font-medium">{t.nome}</span>{t.campos_personalizados && <Badge variant="outline">campos extras</Badge>}</Inline>
          <span className="text-xs text-muted-foreground">{t.descricao || '—'}</span>
        </Stack>
      ),
    },
    { id: 'taxa', header: 'Taxa de embalagem', meta: { align: 'right', mono: true }, cell: ({ row: { original: t } }) => taxaTexto(t) },
    {
      id: 'precos', header: 'Tabela de preço por local',
      cell: ({ row: { original: t } }) => (
        <span className="text-muted-foreground">{t.precos_por_local.map((p) => `${p.local}: ${p.tabela}`).join(' · ') || 'Padrão'}</span>
      ),
    },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: t } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${t.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(t)}>Excluir tipo de serviço</DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Tipos de serviço"
          subtitle={tiposProp ? <><strong>{tipos.length}</strong> {tipos.length === 1 ? 'tipo de serviço' : 'tipos de serviço'}</> : 'balcão, entrega, montagem…'}
          subnav={<ConfiguracoesSubNav />} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar tipo de serviço…" aria-label="Buscar tipo de serviço" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="tipos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando tipos de serviço…</p>}>
          {tipos.length > 0 ? (
            <div data-contract="tipos-table">
              <DataTable columns={colunas} data={lista} caption="Tipos de serviço do negócio" rowKey={(t) => t.id}
                emptyMessage="Nenhum tipo de serviço com esse termo." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhum tipo de serviço" description="Cada forma de atender (balcão, entrega, montagem) vira um tipo aqui, com taxa e tabela de preço próprias." />
            </div>
          )}
        </Deferred>
      </Stack>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">O tipo de serviço sai da lista do negócio.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

TiposServicoIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default TiposServicoIndex;
