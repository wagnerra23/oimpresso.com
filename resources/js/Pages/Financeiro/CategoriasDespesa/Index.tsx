// @memcofre
//   tela: /expense-categories
//   module: Financeiro
//   stories: US-MWART-018, parte 2 (thread financeiro/13)
//   permissao: expense.add ou expense.edit (ExpenseCategoryController — o legado não separa ver de editar)
//
// Categorias de despesa atrás da flag `useV2FinanceiroDespesas` (a mesma da lista de despesas); desligada,
// `/expense-categories` segue na Blade `expense_category.index`. `?classico=1` força a Blade.
// Charter: ./Index.charter.md · Casos: ./Index.casos.md · RUNBOOK: memory/requisitos/Financeiro/RUNBOOK-despesas.md
// Âncora de design: prototipo-ui/cowork/Wagner/financeiro-legado.jsx → telaCategorias()
//
// Grava pelos MESMOS endpoints da Blade (store/update/destroy). Excluir leva as subcategorias junto, como hoje.
// As colunas "Lançamentos" e "Gasto no mês" do protótipo não existem na tela antiga e ficam para decisão [W].

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useMemo, useState, type ReactNode } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Checkbox } from '@/Components/ui/checkbox';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { PageHeader, PageHeaderPrimary } from '@/Components/PageHeader';
import DataTable from '@/Components/shared/DataTable';
import EmptyState from '@/Components/shared/EmptyState';
import type { ColumnDef } from '@tanstack/react-table';
import { Inline, Stack } from '@/Components/layout';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';

export interface Categoria { id: number; nome: string; codigo: string | null; pai: number | null }
interface Props { categorias?: Categoria[] }
interface Edicao { id?: number; nome: string; codigo: string; sub: boolean; pai: string }

const NENHUM = '__nenhum';

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}

/** store/update/destroy devolvem `{success, msg}` no ajax, como o modal da Blade. */
async function enviar(url: string, metodo: 'POST' | 'PUT' | 'DELETE', corpo?: URLSearchParams): Promise<{ success: boolean; msg?: string }> {
  try {
    if (corpo && metodo === 'PUT') corpo.append('_method', 'PUT');
    const r = await fetch(url, {
      method: metodo === 'PUT' ? 'POST' : metodo,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json', ...(corpo ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {}) },
      body: corpo,
    });
    return r.ok ? await r.json() : { success: false };
  } catch {
    return { success: false };
  }
}

function CategoriasDespesaIndex({ categorias }: Props) {
  const lista = useMemo(() => categorias ?? [], [categorias]);
  const nomes = useMemo(() => Object.fromEntries(lista.map((c) => [c.id, c.nome])), [lista]);
  // Cada categoria de topo seguida das suas subcategorias, como o "--" da Blade.
  const linhas = useMemo(() => lista.filter((c) => !c.pai).flatMap((c) => [c, ...lista.filter((s) => s.pai === c.id)]), [lista]);
  const topo = lista.filter((c) => !c.pai);
  const [edicao, setEdicao] = useState<Edicao | null>(null);
  const [excluir, setExcluir] = useState<Categoria | null>(null);
  const [aviso, setAviso] = useState('');
  const [salvando, setSalvando] = useState(false);

  const colunas: ColumnDef<Categoria>[] = [
    { accessorKey: 'nome', header: 'Nome da categoria', cell: ({ row: { original: c } }) => c.pai ? <span className="pl-5">↳ {c.nome}</span> : c.nome },
    { accessorKey: 'codigo', header: 'Código', meta: { mono: true } },
    { id: 'pai', header: 'Subcategoria de', cell: ({ row: { original: c } }) => (c.pai ? nomes[c.pai] ?? '—' : '—') },
    {
      id: 'acoes', header: 'Ações', cell: ({ row: { original: c } }) => (
        <Inline gap={1}>
          <Button size="sm" variant="outline" onClick={() => setEdicao({ id: c.id, nome: c.nome, codigo: c.codigo ?? '', sub: !!c.pai, pai: c.pai ? String(c.pai) : '' })}>Editar</Button>
          <Button size="sm" variant="outline" onClick={() => setExcluir(c)}>Excluir</Button>
        </Inline>
      ),
    },
  ];

  async function salvar() {
    if (!edicao) return;
    setSalvando(true);
    const corpo = new URLSearchParams({ name: edicao.nome, code: edicao.codigo });
    if (edicao.sub && edicao.pai) { corpo.append('add_as_sub_cat', '1'); corpo.append('parent_id', edicao.pai); }
    const r = await enviar(edicao.id ? `/expense-categories/${edicao.id}` : '/expense-categories', edicao.id ? 'PUT' : 'POST', corpo);
    setSalvando(false);
    setAviso(r.msg ?? (r.success ? 'Categoria salva.' : 'Não foi possível salvar a categoria.'));
    if (r.success) { setEdicao(null); router.reload({ only: ['categorias'] }); }
  }

  async function confirmarExclusao() {
    if (!excluir) return;
    const r = await enviar(`/expense-categories/${excluir.id}`, 'DELETE');
    setExcluir(null);
    setAviso(r.msg ?? (r.success ? 'Categoria excluída.' : 'Não foi possível excluir a categoria.'));
    if (r.success) router.reload({ only: ['categorias'] });
  }

  return (
    <div className="fin-curadoria">
      <div data-contract="cabecalho">
        <PageHeader title="Categorias de despesa" subtitle={<>Categorias e subcategorias que classificam as despesas</>}>
          <Inline className="ml-auto flex-shrink-0">
            <FinanceiroSubNav active="categorias-despesa" hidePrimary />
            <PageHeaderPrimary label="Nova categoria" onClick={() => setEdicao({ nome: '', codigo: '', sub: false, pai: '' })} />
          </Inline>
        </PageHeader>
      </div>

      <Stack gap={4} className="px-6 pt-4">
        {aviso && <p role="status" className="text-sm text-muted-foreground">{aviso}</p>}
        <Deferred data="categorias" fallback={<p className="py-6 text-sm text-muted-foreground">Carregando categorias…</p>}>
          {lista.length > 0 ? (
            <div data-contract="lista">
              <DataTable columns={colunas} data={linhas} caption="Categorias de despesa do negócio" rowKey={(c) => c.id} />
            </div>
          ) : (
            <div data-contract="vazio">
              <EmptyState title="Nenhuma categoria de despesa" description="Crie a primeira para classificar as despesas." />
            </div>
          )}
        </Deferred>
      </Stack>

      <Sheet open={!!edicao} onOpenChange={(v) => !v && setEdicao(null)}>
        <SheetContent data-contract="form" className="w-full overflow-y-auto p-0 sm:max-w-xl">
          <header className="os-drawer-head">
            <div className="os-drawer-head-l">
              <SheetTitle asChild><h2 className="m-0">{edicao?.id ? `Editar ${edicao.nome}` : 'Nova categoria'}</h2></SheetTitle>
              <SheetDescription asChild><p>Categoria de despesa do negócio.</p></SheetDescription>
            </div>
          </header>
          {edicao && (
            <Stack gap={4} className="p-5 text-sm">
              <Stack gap={1}>
                <label htmlFor="cd-nome">Nome *</label>
                <Input id="cd-nome" value={edicao.nome} onChange={(e) => setEdicao({ ...edicao, nome: e.target.value })} />
              </Stack>
              <Stack gap={1}>
                <label htmlFor="cd-codigo">Código</label>
                <Input id="cd-codigo" value={edicao.codigo} onChange={(e) => setEdicao({ ...edicao, codigo: e.target.value })} />
              </Stack>
              <Inline gap={2}>
                <Checkbox id="cd-sub" checked={edicao.sub} onCheckedChange={(v) => setEdicao({ ...edicao, sub: v === true })} />
                <label htmlFor="cd-sub">É subcategoria</label>
              </Inline>
              {edicao.sub && (
                <Stack gap={1}>
                  <label htmlFor="cd-pai">Subcategoria de *</label>
                  <Select value={edicao.pai || NENHUM} onValueChange={(v) => setEdicao({ ...edicao, pai: v === NENHUM ? '' : v })}>
                    <SelectTrigger id="cd-pai"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      <SelectItem value={NENHUM}>Escolha…</SelectItem>
                      {topo.filter((c) => c.id !== edicao.id).map((c) => <SelectItem key={c.id} value={String(c.id)}>{c.nome}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Stack>
              )}
              <Inline gap={2} justify="end" className="border-t pt-4">
                <Button variant="ghost" onClick={() => setEdicao(null)}>Cancelar</Button>
                <Button disabled={!edicao.nome.trim() || (edicao.sub && !edicao.pai) || salvando} onClick={salvar}>{edicao.id ? 'Salvar' : 'Cadastrar'}</Button>
              </Inline>
            </Stack>
          )}
        </SheetContent>
      </Sheet>

      <AlertDialog open={!!excluir} onOpenChange={(v) => !v && setExcluir(null)}>
        <AlertDialogContent data-contract="confirm-excluir">
          <AlertDialogHeader><AlertDialogTitle>Excluir {excluir?.nome}?</AlertDialogTitle></AlertDialogHeader>
          <p className="text-sm text-muted-foreground">As subcategorias dela saem junto. As despesas lançadas continuam no lugar.</p>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancelar</AlertDialogCancel>
            <AlertDialogAction className="bg-destructive text-white hover:bg-destructive/90" onClick={confirmarExclusao}>Excluir categoria</AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}

CategoriasDespesaIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default CategoriasDespesaIndex;
