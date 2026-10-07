// @memcofre
//   tela: /tax-rates
//   module: Configuracoes
//   stories: thread sistema/playbook/05 (tela 1 de 3)
//   permissao: tax_rate.view ou tax_rate.create (ver) · tax_rate.create/.update/.delete (ações)
//
// Alíquotas de imposto e grupos de imposto do negócio. Atrás da flag `useV2ConfiguracoesImpostos`
// (TaxRateController::FLAG_V2); desligada, a rota segue na Blade `tax_rate/index`.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Configuracoes/RUNBOOK-impostos.md
// Âncora de design: prototipo-ui/cowork/Wagner/configuracoes-cadastros.jsx → Impostos()
//
// A alíquota entra no imposto da venda (regra mestre de valor). A tela só MOSTRA aqui; cadastrar e editar (F3-2)
// mandam texto pt-BR e o num_uf do store/update segue único parser. Grupos são do GroupTaxController (fora do
// prefixo da thread): a tela lista, não edita.

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

export interface Aliquota { id: number; nome: string; aliquota: number; so_grupo: boolean; em_grupo: boolean }
export interface Grupo { id: number; nome: string; aliquota: number; sub_impostos: string[] }
interface Props {
  impostos?: { aliquotas: Aliquota[]; grupos: Grupo[] };
  pode: { criar: boolean; editar: boolean; excluir: boolean };
  nfe_ativo: boolean;
}

/** 18 → "18,00 %". Mesmo formato que o @num_format da Blade mostrava. */
const pct = (n: number) => `${n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 })} %`;

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** destroy() só responde a ajax() e devolve `{success, msg}` — igual à Blade. */
async function excluirNoServidor(id: number): Promise<{ success: boolean; msg?: string }> {
  const r = await fetch(`/tax-rates/${id}`, {
    method: 'DELETE',
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
  });
  const corpo = await r.json().catch(() => null);
  if (corpo && typeof corpo.success === 'boolean') return corpo;
  return { success: false, msg: `Não foi possível excluir (HTTP ${r.status}).` };
}

function ImpostosIndex({ impostos, pode, nfe_ativo }: Props) {
  const aliquotas = useMemo(() => impostos?.aliquotas ?? [], [impostos]);
  const grupos = useMemo(() => impostos?.grupos ?? [], [impostos]);
  const [q, setQ] = useState('');
  const [excluir, setExcluir] = useState<Aliquota | null>(null);
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
  const filtrar = <T extends { nome: string }>(lista: T[]) => lista.filter((x) => !termo || x.nome.toLowerCase().includes(termo));

  const confirmarExclusao = async () => {
    if (!excluir) return;
    const r = await excluirNoServidor(excluir.id);
    setExcluir(null);
    setAviso(r.msg ?? null);
    if (r.success) router.reload({ only: ['impostos'] });
  };

  const colAliquotas: ColumnDef<Aliquota, unknown>[] = [
    {
      id: 'nome', header: 'Alíquota',
      cell: ({ row: { original: a } }) => (
        <Inline gap={2}><span className="font-medium">{a.nome}</span>{a.so_grupo && <Badge variant="outline">só em grupo</Badge>}</Inline>
      ),
    },
    { id: 'aliquota', header: 'Percentual', meta: { align: 'right', mono: true }, cell: ({ row: { original: a } }) => pct(a.aliquota) },
    { id: 'uso', header: 'Uso', cell: ({ row: { original: a } }) => <span className="text-muted-foreground">{a.em_grupo ? 'Compõe um grupo' : '—'}</span> },
    {
      id: 'acoes', header: '', meta: { align: 'right' },
      cell: ({ row: { original: a } }) => pode.excluir && (
        <DropdownMenu>
          <DropdownMenuTrigger asChild><Button variant="ghost" size="sm" aria-label={`Ações de ${a.nome}`}><MoreHorizontal className="size-4" /></Button></DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {/* O destroy() recusa alíquota que compõe grupo: a tela avisa antes, pela mesma relação. */}
            {a.em_grupo
              ? <DropdownMenuItem disabled>Compõe um grupo — tire do grupo antes de excluir</DropdownMenuItem>
              : <DropdownMenuItem className="text-destructive" onSelect={() => setExcluir(a)}>Excluir alíquota</DropdownMenuItem>}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];
  const colGrupos: ColumnDef<Grupo, unknown>[] = [
    { id: 'nome', header: 'Grupo', cell: ({ row: { original: g } }) => <span className="font-medium">{g.nome}</span> },
    { id: 'aliquota', header: 'Percentual', meta: { align: 'right', mono: true }, cell: ({ row: { original: g } }) => pct(g.aliquota) },
    { id: 'sub', header: 'Composição', cell: ({ row: { original: g } }) => <span className="text-muted-foreground">{g.sub_impostos.join(' + ') || '—'}</span> },
  ];

  return (
    <div className="pb-8">
      <div data-contract="page-header">
        <PageHeader title="Impostos"
          subtitle={impostos ? <><strong>{aliquotas.length}</strong> {aliquotas.length === 1 ? 'alíquota' : 'alíquotas'} · <strong>{grupos.length}</strong> {grupos.length === 1 ? 'grupo' : 'grupos'}</> : 'alíquotas e grupos de imposto'}
          subnav={<ConfiguracoesSubNav />} />
      </div>

      <Stack gap={4} className="px-6 pt-4">
        {nfe_ativo && (
          <Stack data-contract="aviso-nfe" gap={1} className="rounded-md border p-4 text-sm">
            <strong>Configuração fiscal avançada disponível</strong>
            <span className="text-muted-foreground">Para NF-e/NFC-e, use a tributação por NCM, UF e CST/CSOSN em vez de alíquotas avulsas.</span>
            <a className="text-sm font-medium underline" href="/nfe-brasil/tributacao">Abrir a Tributação NF-e</a>
          </Stack>
        )}
        <Inline data-contract="toolbar" wrap gap={2}>
          <div className="relative min-w-64 flex-1">
            <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input ref={busca} className="cw-input-icon-left" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Buscar alíquota ou grupo…" aria-label="Buscar alíquota ou grupo" />
          </div>
        </Inline>
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}

        <Deferred data="impostos" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando impostos…</p>}>
          <Stack gap={6}>
            {aliquotas.length > 0 ? (
              <div data-contract="aliquotas-table">
                <DataTable columns={colAliquotas} data={filtrar(aliquotas)} caption="Alíquotas do negócio" rowKey={(a) => a.id}
                  emptyMessage="Nenhuma alíquota com esse termo." />
              </div>
            ) : (
              <div data-contract="vazio">
                <EmptyState title="Nenhuma alíquota cadastrada" description="Cadastre as alíquotas que a venda aplica (ICMS, ISS, PIS, COFINS…)." />
              </div>
            )}
            <Stack gap={2} data-contract="grupos-table">
              <h2 className="m-0 text-sm font-semibold">Grupos de imposto</h2>
              {grupos.length > 0
                ? <DataTable columns={colGrupos} data={filtrar(grupos)} caption="Grupos de imposto do negócio" rowKey={(g) => g.id} emptyMessage="Nenhum grupo com esse termo." />
                : <p className="text-sm text-muted-foreground">Nenhum grupo. Um grupo soma alíquotas (ex.: PIS + COFINS).</p>}
            </Stack>
          </Stack>
        </Deferred>
      </Stack>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm">A alíquota sai da lista de impostos do negócio.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

ImpostosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default ImpostosIndex;
