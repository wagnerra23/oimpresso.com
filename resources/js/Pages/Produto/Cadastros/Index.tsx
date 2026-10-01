// @memcofre
//   tela: /units (Cadastros de apoio · abas)
//   module: Produto
//   stories: playbook Produto thread 02 (UnitController@index · Blade → Inertia)
//   permissao: unit.* · category.* (type=product) · brand.* (cada aba pela sua)
//
// Cadastros de apoio do produto. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/produto-cadastros.jsx → ProdutoCadastros()
// Contrato: governance/design/contracts/produto-cadastros.contract.json
//
// Thread 02 traz Unidades, Categorias e Marcas: lista, contagem de uso clicável e exclusão com a
// recusa dita antes. Criar e editar seguem nos modais da Blade (`?classico=1`; Categorias em
// `/taxonomies?type=product`). Variações, Grupos de preço e Garantias abrem a tela atual (thread 03).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import {
  AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { PageHeader } from '@/Components/PageHeader';
import SubNav, { type SubNavItem } from '@/Components/shared/SubNav';
import EmptyState from '@/Components/shared/EmptyState';
import { Inline, Stack } from '@/Components/layout';

type AbaViva = 'unidades' | 'categorias' | 'marcas';
interface Pode { view: boolean; create: boolean; update: boolean; delete: boolean }
interface Unidade { id: number; nome: string; simbolo: string; decimal: boolean; base: string | null; em_uso: number }
interface Marca { id: number; nome: string; descricao: string; em_uso: number }
interface Categoria {
  id: number; nome: string; codigo: string; descricao: string;
  pai_id: number | null; pai: string | null; em_uso: number; filhas: number;
}
interface Props {
  aba: AbaViva;
  can: Record<AbaViva, Pode>;
  unidades?: Unidade[] | null;
  categorias?: Categoria[] | null;
  marcas?: Marca[] | null;
}
// `filtro`: id que o índice de produtos recebe (subcategoria abre pela categoria pai — R3/R7).
// `filhas`: subcategorias vivas — junto com `em_uso`, é o que faz o servidor recusar a exclusão.
type Linha = { id: number; nome: string; busca: string; em_uso: number; filhas: number; filtro: number; celulas: ReactNode[] };

// Copy de cada aba, tirada de produto-cadastros.jsx (AbaUnidades :290 · AbaCategorias :375 · AbaMarcas :405).
// `rota` recebe o DELETE (JSON do legado); `escrita` abre os modais de criar/editar da Blade.
const ABA = {
  unidades: {
    o: 'unidades', base: 'unit', rota: '/units', escrita: '/units?classico=1', filtro: 'unidade', novo: 'Nova unidade',
    busca: 'Buscar unidade…',
    ajuda: 'Unidade decimal aceita quantidade fracionada (m², kg). Múltiplo de unidade base converte compra em caixa para venda em peça.',
    primeiro: 'Todo produto precisa de unidade. Comece pelas três da gráfica: Unidade (Un), Metro quadrado (m²) e Metro linear (m).',
    colunas: ['Unidade', 'Aceita decimal', 'Múltiplo da base', 'Produtos', 'Ações'],
  },
  categorias: {
    o: 'categorias', base: 'category', rota: '/taxonomies', escrita: '/taxonomies?type=product', filtro: 'categoria', novo: 'Nova categoria',
    busca: 'Buscar categoria ou código…',
    ajuda: 'Categoria e subcategoria do produto — as mesmas do filtro do índice e do relatório de lucro por categoria. Código curto entra no SKU automático.',
    primeiro: 'Categoria é o que faz o relatório de lucro por categoria existir e o SKU sair automático. Comece pelas famílias que você orça.',
    colunas: ['Categoria', 'Código', 'Descrição', 'Produtos', 'Ações'],
  },
  marcas: {
    o: 'marcas', base: 'brand', rota: '/brands', escrita: '/brands?classico=1', filtro: 'marca', novo: 'Nova marca',
    busca: 'Buscar marca…',
    ajuda: 'Marca do produto — filtro do índice, relatório por marca e, quando marcada, lista de marcas de aparelho da Oficina.',
    primeiro: 'Marca é opcional no produto, mas é ela que faz o relatório por marca e a lista de aparelhos da Oficina.',
    colunas: ['Marca', 'Descrição curta', 'Produtos', 'Ações'],
  },
} as const;

// Abas que ainda vivem na tela atual (thread 03).
const [VARIACOES, GRUPOS, GARANTIAS]: [SubNavItem, SubNavItem, SubNavItem] = [
  { value: 'variacoes', label: 'Variações', href: '/variation-templates' },
  { value: 'grupos', label: 'Grupos de preço', href: '/selling-price-group' },
  { value: 'garantias', label: 'Garantias', href: '/warranties' },
];

export default function CadastrosIndex({ aba: inicial, can, unidades, categorias, marcas }: Props) {
  const [aba, setAba] = useState<AbaViva>(inicial);
  const [busca, setBusca] = useState('');
  const [excluir, setExcluir] = useState<Linha | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const buscaRef = useRef<HTMLInputElement>(null);
  const cfg = ABA[aba];
  const pode = can[aba];

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const alvo = e.target as HTMLElement;
      if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(alvo.tagName) && !alvo.isContentEditable) {
        e.preventDefault();
        buscaRef.current?.focus();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const linhas: Linha[] | null = aba === 'unidades'
    ? unidades?.map((u) => ({ id: u.id, nome: u.nome, busca: u.nome, em_uso: u.em_uso, filhas: 0, filtro: u.id, celulas: [
      <><b>{u.nome}</b> <span className="text-muted-foreground">({u.simbolo})</span></>,
      u.decimal ? 'Sim' : 'Não',
      <span className="font-mono text-xs">{u.base ?? '—'}</span>,
    ] })) ?? null
    : aba === 'categorias'
      ? categorias?.map((c) => ({ id: c.id, nome: c.nome, busca: `${c.nome} ${c.codigo}`, em_uso: c.em_uso, filhas: c.filhas,
        filtro: c.pai_id ?? c.id, celulas: [
          <Stack gap={0}>
            <b className={c.pai_id ? 'pl-4' : undefined}>{c.pai_id ? `↳ ${c.nome}` : c.nome}</b>
            <span className={`text-xs text-muted-foreground${c.pai_id ? ' pl-4' : ''}`}>
              {c.pai_id ? `em ${c.pai ?? 'categoria excluída'}` : c.filhas > 0 ? `categoria · ${c.filhas} subcategoria(s)` : 'categoria'}
            </span>
          </Stack>,
          <span className="font-mono text-xs">{c.codigo || '—'}</span>,
          c.descricao || '—',
        ] })) ?? null
      : marcas?.map((m) => ({ id: m.id, nome: m.nome, busca: m.nome, em_uso: m.em_uso, filhas: 0, filtro: m.id, celulas: [<b>{m.nome}</b>, m.descricao || '—'] })) ?? null;

  const termo = busca.trim().toLowerCase();
  const filtradas = linhas?.filter((l) => !termo || l.busca.toLowerCase().includes(termo)) ?? [];
  const recusa = (l: Linha) => [
    l.em_uso > 0 ? `${l.em_uso} produto(s) usam este registro` : null,
    l.filhas > 0 ? `${l.filhas} subcategoria(s) estão dentro dela` : null,
  ].filter(Boolean).join(' e ');

  const confirmar = async () => {
    if (!excluir) return;
    // A rota legada devolve JSON (não Inertia) e já escopa por business_id; a recusa de uso é dela.
    const csrf = document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
    const dados = await fetch(`${cfg.rota}/${excluir.id}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
    }).then((r) => r.json() as Promise<{ success?: boolean; msg?: string }>).catch(() => null);
    setExcluir(null);
    if (dados?.success) router.reload({ only: [aba] });
    else setErro(dados?.msg ?? 'Não foi possível excluir. Nada foi alterado.');
  };

  return (
    <Stack gap={4}>
      <div data-contract="produto-cadastros-header">
        <PageHeader title="Cadastros" subtitle="Unidades, marcas e os demais cadastros que o produto usa." />
      </div>

      <Card data-contract="produto-cadastros-widget">
        <CardContent className="p-4">
          <Stack gap={3}>
          <h2 className="text-sm font-medium">Cadastros de apoio</h2>

          <div data-contract="produto-cadastros-abas">
            <SubNav
              ariaLabel="Cadastros de apoio"
              value={aba}
              onChange={(v) => { setAba(v as AbaViva); setBusca(''); setErro(null); }}
              items={[
                VARIACOES, GRUPOS,
                { value: 'unidades', label: 'Unidades', badge: can.unidades.view ? unidades?.length : '—' },
                { value: 'categorias', label: 'Categorias', badge: can.categorias.view ? categorias?.length : '—' },
                { value: 'marcas', label: 'Marcas', badge: can.marcas.view ? marcas?.length : '—' },
                GARANTIAS,
              ]}
            />
          </div>

          {!pode.view ? (
            <EmptyState icon="lock" title={`Você não vê ${cfg.o}`}
              description={`Seu papel não tem ${cfg.base}.view — quem libera é o administrador, em Papéis.`} />
          ) : (
            <>
              <Inline wrap gap={2} data-contract="produto-cadastros-barra">
                <Input ref={buscaRef} className="max-w-xs" value={busca} placeholder={cfg.busca} aria-label={cfg.busca}
                  onChange={(e) => setBusca(e.target.value)} />
                <span className="text-xs text-muted-foreground"><kbd>/</kbd> foca a busca</span>
                {pode.create ? (
                  <Button asChild size="sm" className="ml-auto"><a href={cfg.escrita}>{cfg.novo}</a></Button>
                ) : (
                  <Button size="sm" className="ml-auto" disabled title={`Seu papel não tem ${cfg.base}.create`}>{cfg.novo}</Button>
                )}
              </Inline>
              <p className="text-sm text-muted-foreground" data-contract="produto-cadastros-ajuda">{cfg.ajuda}</p>
              {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}

              <div data-contract="produto-cadastros-tabela">
                <Deferred data={aba} fallback={<Skeleton className="h-48 w-full" />}>
                  {!linhas?.length ? (
                    <EmptyState title={`Nenhum registro de ${cfg.o}`} description={cfg.primeiro}
                      action={pode.create ? <Button asChild><a href={cfg.escrita}>{cfg.novo}</a></Button> : undefined} />
                  ) : !filtradas.length ? (
                    <EmptyState variant="search" icon="search-x" title="Nada com esse termo"
                      description={`Nenhuma das ${linhas.length} ${cfg.o} bate com “${busca}”.`}
                      action={<Button variant="outline" onClick={() => setBusca('')}>Limpar busca</Button>} />
                  ) : (
                    <table className="w-full text-sm">
                      <thead className="text-left text-xs text-muted-foreground">
                        <tr>{cfg.colunas.map((c) => <th key={c} className="border-b border-border py-2 pr-3 font-medium">{c}</th>)}</tr>
                      </thead>
                      <tbody>
                        {filtradas.map((l) => (
                          <tr key={l.id} className="border-b border-border">
                            {l.celulas.map((c, i) => <td key={i} className="py-2 pr-3">{c}</td>)}
                            <td className="py-2 pr-3 text-right tabular-nums">
                              {l.em_uso > 0
                                ? <Link href={`/products/unificado?${cfg.filtro}=${l.filtro}`} className="text-primary underline-offset-2 hover:underline">{l.em_uso}</Link>
                                : 0}
                            </td>
                            <td className="py-2 pr-3">
                              <Inline gap={2}>
                                {pode.update && <Button asChild variant="outline" size="sm"><a href={cfg.escrita}>Editar</a></Button>}
                                {pode.delete && <Button variant="outline" size="sm" onClick={() => setExcluir(l)}>Excluir</Button>}
                              </Inline>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </Deferred>
              </div>
            </>
          )}
          </Stack>
        </CardContent>
      </Card>

      <AlertDialog open={!!excluir} onOpenChange={(o) => !o && setExcluir(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{excluir && recusa(excluir) ? 'O servidor recusa esta exclusão' : `Excluir “${excluir?.nome}”`}</AlertDialogTitle>
            <AlertDialogDescription>
              {excluir && recusa(excluir)
                ? `${recusa(excluir)}. ${excluir.filhas > 0 ? 'Mova ou exclua as subcategorias' : 'Troque o valor nesses produtos'} primeiro${excluir.em_uso > 0 ? ' — pela Edição em massa resolve em uma passada' : ''}.`
                : 'Nenhum produto usa este registro — sai limpo. Ação sem volta.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{excluir && recusa(excluir) ? 'Entendi' : 'Cancelar'}</AlertDialogCancel>
            {excluir && !recusa(excluir) && <AlertDialogAction onClick={confirmar}>Excluir</AlertDialogAction>}
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Stack>
  );
}

CadastrosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
