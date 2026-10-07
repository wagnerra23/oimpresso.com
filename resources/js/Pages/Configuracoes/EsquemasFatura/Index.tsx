// @memcofre
//   tela: /invoice-schemes
//   module: Configuracoes
//   stories: thread sistema/playbook/05 (tela 3 de 3)
//   permissao: invoice_settings.access (o controller exige em todas as ações)
//
// Esquemas de fatura (como as vendas de cada local são numeradas) e layouts de fatura do negócio. Atrás da flag
// `useV2ConfiguracoesEsquemasFatura` (InvoiceSchemeController::FLAG_V2); desligada, a Blade `invoice_scheme/index` segue.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-esquemas-fatura.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Fatura()
//
// Layouts: só lista, com os locais que usam cada um; criar/editar layout é o InvoiceLayoutController (fora do prefixo),
// que segue na Blade — o link abre a página dele.

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

export interface Esquema {
  id: number; nome: string; padrao: boolean; tipo: 'blank' | 'year'; prefixo: string; prefixo_exibido: string;
  tipo_numero: string; inicio: number | null; emitidas: number; digitos: number | null;
}
export interface Layout { id: number; nome: string; padrao: boolean; locais: string[] }
interface Props { fatura?: { esquemas: Esquema[]; layouts: Layout[] }; tipos_numero: Record<string, string> }

/** Próximo número sequencial — a mesma conta do TransactionUtil (prefixo + str_pad(start_number + invoice_count, total_digits, '0')). */
const proximo = (e: Esquema) => {
  const n = String((e.inicio ?? 0) + e.emitidas);
  return `${e.prefixo_exibido}${e.digitos ? n.padStart(e.digitos, '0') : n}`;
};

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

function EsquemasFaturaIndex({ fatura, tipos_numero }: Props) {
  const esquemas = useMemo(() => fatura?.esquemas ?? [], [fatura]);
  const layouts = useMemo(() => fatura?.layouts ?? [], [fatura]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Esquema | null>(null);
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
    () => esquemas.filter((e) => !termo || [e.nome, e.prefixo].some((v) => v.toLowerCase().includes(termo))),
    [esquemas, termo],
  );

  const depois = (r: { success: boolean; msg?: string }) => {
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['fatura'] });
  };
  const tornarPadrao = async (e: Esquema) => depois(await chamar('GET', `/invoice-schemes/set_default/${e.id}`));
  const confirmarExclusao = async () => {
    if (!excluir) return;
    const alvo = excluir;
    setExcluir(null);
    depois(await chamar('DELETE', `/invoice-schemes/${alvo.id}`));
  };

  const colunas: ColumnDef<Esquema, unknown>[] = [
    {
      id: 'nome', header: 'Esquema',
      cell: ({ row: { original: e } }) => (
        <Inline gap={2}><span className="font-medium">{e.nome}</span>{e.padrao && <Badge variant="secondary">padrão</Badge>}</Inline>
      ),
    },
    { id: 'prefixo', header: 'Prefixo', meta: { mono: true }, cell: ({ row: { original: e } }) => e.prefixo_exibido || '—' },
    { id: 'tipo', header: 'Numeração', cell: ({ row: { original: e } }) => tipos_numero[e.tipo_numero] ?? e.tipo_numero },
    { id: 'emitidas', header: 'Emitidas', meta: { align: 'right', mono: true }, cell: ({ row: { original: e } }) => e.emitidas },
    { id: 'proximo', header: 'Próximo número', meta: { mono: true }, cell: ({ row: { original: e } }) => (e.tipo_numero === 'sequential' ? proximo(e) : '—') },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: e } }) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${e.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {!e.padrao && <DropdownMenuItem onSelect={() => tornarPadrao(e)}>Tornar padrão</DropdownMenuItem>}
            {/* O padrão não pode ser excluído: o destroy() recusa, e a Blade desabilita o botão. */}
            {!e.padrao && <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(e)}>Excluir esquema</DropdownMenuItem>}
            {e.padrao && <DropdownMenuItem disabled>É o padrão — escolha outro antes de excluir</DropdownMenuItem>}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Esquemas de fatura"
          subtitle={fatura ? <><strong>{esquemas.length}</strong> {esquemas.length === 1 ? 'esquema' : 'esquemas'} · <strong>{layouts.length}</strong> {layouts.length === 1 ? 'layout' : 'layouts'}</> : 'numeração e layout das notas'}
          subnav={<ConfiguracoesSubNav />} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar esquema ou prefixo…" aria-label="Buscar esquema" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="fatura" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando esquemas…</p>}>
          <Stack gap={6}>
            {esquemas.length > 0 ? (
              <div data-contract="esquemas-table">
                <DataTable columns={colunas} data={lista} caption="Esquemas de fatura do negócio" rowKey={(e) => e.id}
                  emptyMessage="Nenhum esquema com esse termo." />
              </div>
            ) : (
              <div data-contract="vazio">
                <EmptyState title="Nenhum esquema de fatura" description="O esquema define prefixo e numeração das notas de cada local." />
              </div>
            )}
            <Stack gap={2} data-contract="layouts-lista">
              <h2 className="m-0 text-sm font-semibold">Layouts de fatura</h2>
              {layouts.length === 0 && <p className="text-sm text-muted-foreground">Nenhum layout cadastrado.</p>}
              {layouts.map((l) => (
                <Inline key={l.id} gap={3} className="rounded-md border p-3 text-sm">
                  <Stack gap={0} className="flex-1">
                    <Inline gap={2}><span className="font-medium">{l.nome}</span>{l.padrao && <Badge variant="secondary">padrão</Badge>}</Inline>
                    <span className="text-xs text-muted-foreground">{l.locais.length ? `Usado em: ${l.locais.join(', ')}` : 'Nenhum local usa este layout'}</span>
                  </Stack>
                  <a className="text-sm font-medium underline" href={`/invoice-layouts/${l.id}/edit`}>Editar layout</a>
                </Inline>
              ))}
            </Stack>
          </Stack>
        </Deferred>
      </Stack>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">O esquema sai da lista do negócio.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

EsquemasFaturaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default EsquemasFaturaIndex;
