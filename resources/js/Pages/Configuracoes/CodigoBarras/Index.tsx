// @memcofre
//   tela: /barcodes
//   module: Configuracoes
//   stories: thread sistema/playbook/04 (tela 2 de 3)
//   permissao: barcode_settings.access (o controller exige em todas as ações)
//
// Configurações de etiqueta (folha ou rolo) usadas na impressão de código de barras. Atrás da flag
// `useV2ConfiguracoesCodigoBarras` (BarcodeController::FLAG_V2); desligada, a rota segue na Blade `barcode/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-codigo-barras.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Barras()
//
// O protótipo pede medidas em mm; o banco e a impressão são em polegada — a tela segue em polegada até decisão [W]
// (RUNBOOK §10). "Imprimir prova" fica fora: não existe endpoint.

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

export interface Etiqueta {
  id: number; nome: string; descricao: string; padrao: boolean; continuo: boolean;
  por_linha: number | null; por_folha: number | null; medidas: Record<string, number | null>;
}
interface Props { etiquetas?: Etiqueta[] }

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** destroy() e setDefault() só respondem a ajax() e devolvem `{success, msg}` — igual à Blade. */
async function chamar(metodo: 'DELETE' | 'GET', url: string): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(url, {
    method: metodo,
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível concluir (HTTP ${r.status}).` };
}

function CodigoBarrasIndex({ etiquetas: etiquetasProp }: Props) {
  const etiquetas = useMemo(() => etiquetasProp ?? [], [etiquetasProp]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Etiqueta | null>(null);
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
    () => etiquetas.filter((e) => !termo || [e.nome, e.descricao].some((v) => v.toLowerCase().includes(termo))),
    [etiquetas, termo],
  );

  const depois = (r: { success: boolean; msg?: string }) => {
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['etiquetas'] });
  };
  const tornarPadrao = async (e: Etiqueta) => depois(await chamar('GET', `/barcodes/set_default/${e.id}`));
  const confirmarExclusao = async () => {
    if (!excluir) return;
    const alvo = excluir;
    setExcluir(null);
    depois(await chamar('DELETE', `/barcodes/${alvo.id}`));
  };

  const colunas: ColumnDef<Etiqueta, unknown>[] = [
    {
      id: 'nome', header: 'Configuração',
      cell: ({ row: { original: e } }) => (
        <Inline gap={2}><span className="font-medium">{e.nome}</span>{e.padrao && <Badge variant="secondary">padrão</Badge>}</Inline>
      ),
    },
    { id: 'descricao', header: 'Descrição', cell: ({ row: { original: e } }) => <span className="text-muted-foreground">{e.descricao || '—'}</span> },
    { id: 'papel', header: 'Papel', cell: ({ row: { original: e } }) => (e.continuo ? 'Rolo contínuo' : 'Folha') },
    { id: 'por_folha', header: 'Etiquetas por folha', meta: { align: 'right', mono: true }, cell: ({ row: { original: e } }) => e.por_folha ?? '—' },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: e } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${e.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {!e.padrao && <DropdownMenuItem onSelect={() => tornarPadrao(e)}>Tornar padrão</DropdownMenuItem>}
            {/* A padrão não pode ser excluída: o destroy() recusa, e a Blade desabilita o botão. */}
            {!e.padrao && <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(e)}>Excluir configuração</DropdownMenuItem>}
            {e.padrao && <DropdownMenuItem disabled>É a padrão — escolha outra antes de excluir</DropdownMenuItem>}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Código de barras"
          subtitle={etiquetasProp ? <><strong>{etiquetas.length}</strong> {etiquetas.length === 1 ? 'configuração de etiqueta' : 'configurações de etiqueta'}</> : 'configurações de etiqueta'} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar configuração de etiqueta…" aria-label="Buscar configuração de etiqueta" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="etiquetas" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando configurações…</p>}>
          {etiquetas.length > 0 ? (
            <div data-contract="etiquetas-table">
              <DataTable columns={colunas} data={lista} caption="Configurações de etiqueta do negócio" rowKey={(e) => e.id}
                emptyMessage="Nenhuma configuração com esse termo. Limpe a busca para ver todas." />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma configuração de etiqueta" description="Cada folha ou rolo que a loja compra vira uma configuração aqui." />
            </div>
          )}
        </Deferred>
      </Stack>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">Só a configuração sai; nenhuma etiqueta já impressa muda.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

CodigoBarrasIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CodigoBarrasIndex;
