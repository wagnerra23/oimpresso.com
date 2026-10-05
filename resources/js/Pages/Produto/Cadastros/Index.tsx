// @memcofre
//   tela: /units (Cadastros de apoio · abas)
//   module: Produto
//   stories: playbook Produto threads 02 e 03 (Unit/VariationTemplate/SellingPriceGroup/Warranty@index · Blade → Inertia)
//   permissao: unit.* · category.* (type=product) · brand.* · variation.* · warranty.* · product.create (grupos)
//
// Cadastros de apoio do produto. Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/produto-cadastros.jsx → ProdutoCadastros()
// Contrato: governance/design/contracts/produto-cadastros.contract.json
//
// As 6 abas são vivas: lista, contagem de uso clicável e exclusão com a recusa dita antes (threads
// 02 e 03). Cinco abas criam e editam no drawer (thread 10, PR-a e PR-b); Grupos de preço segue no modal
// da Blade (`?classico=1`) — a ficha da thread não o inclui.

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
import CadastroDrawer, { type Pedido } from './_components/CadastroDrawer';

type AbaViva = 'variacoes' | 'grupos' | 'unidades' | 'categorias' | 'marcas' | 'garantias';
interface Pode { view: boolean; create: boolean; update: boolean; delete: boolean }
interface Variacao { id: number; nome: string; valores: string[]; valor_ids: number[]; em_uso: number }
interface Grupo { id: number; nome: string; descricao: string; ativo: boolean }
interface Unidade {
  id: number; nome: string; simbolo: string; decimal: boolean; base: string | null; em_uso: number;
  base_id: number | null; multiplicador: string;
}
interface Marca { id: number; nome: string; descricao: string; oficina: boolean; em_uso: number }
interface Garantia { id: number; nome: string; descricao: string; duracao: string | null; duracao_n: string; duracao_tipo: string }
interface Categoria {
  id: number; nome: string; codigo: string; descricao: string;
  pai_id: number | null; pai: string | null; em_uso: number; filhas: number;
}
interface Props {
  aba: AbaViva;
  can: Record<AbaViva, Pode>;
  oficina: boolean;
  variacoes?: Variacao[] | null;
  grupos?: Grupo[] | null;
  unidades?: Unidade[] | null;
  categorias?: Categoria[] | null;
  marcas?: Marca[] | null;
  garantias?: Garantia[] | null;
}
// `filtro`: query do índice de produtos (subcategoria abre pela categoria pai — R3/R7). Vazio = o índice não
// filtra por aquilo (variação: `/products/unificado` não tem filtro por modelo) e o número não vira link.
// `filhas`: subcategorias vivas — junto com `em_uso`, é o que faz o servidor recusar a exclusão.
type Linha = { id: number; nome: string; busca: string; em_uso: number; filhas: number; filtro: string; celulas: ReactNode[] };

// Copy de cada aba, tirada de produto-cadastros.jsx (AbaVariacoes :214 · AbaGrupos :264 · AbaUnidades :310 ·
// AbaCategorias :374 · AbaMarcas :425 · AbaGarantias :463). `rota` recebe o DELETE (JSON do legado);
// `escrita` abre os modais da Blade; `uso: false` = aba sem coluna Produtos; `corpo` = confirmação de excluir livre.
const LIVRE = 'Nenhum produto usa este registro — sai limpo. Ação sem volta.';
const ABA = {
  variacoes: {
    o: 'variações', base: 'variation', rota: '/variation-templates', escrita: '/variation-templates?classico=1', novo: 'Nova variação',
    busca: 'Buscar variação ou valor…', uso: true, corpo: 'O modelo deixa de aparecer no cadastro novo. Nenhum produto usa esses valores hoje.',
    ajuda: 'Modelo reaproveitado no cadastro de produto variável: escolher “Cor” já traz os valores abaixo como variações.',
    primeiro: 'Modelo de variação é o atalho do produto variável: cadastre “Cor” uma vez e todo produto novo já oferece os valores.',
    colunas: ['Variação', 'Valores', 'Produtos', 'Ações'],
  },
  grupos: {
    o: 'grupos de preço', base: 'product', rota: '/selling-price-group', escrita: '/selling-price-group?classico=1', novo: 'Novo grupo',
    busca: 'Buscar grupo…', uso: false,
    corpo: 'Os preços digitados neste grupo somem junto. Vendas já emitidas mantêm o valor praticado. Se a ideia é só esconder, desative.',
    ajuda: 'Cada grupo ativo vira uma coluna em Preços por grupo e uma opção de preço no PDV e no orçamento. Desativar esconde o grupo sem apagar os preços já digitados.',
    primeiro: 'Sem grupo cadastrado todo mundo paga o preço de tabela. Crie “Atacado” pra ter um segundo preço no PDV e no orçamento.',
    colunas: ['Grupo', 'Descrição', 'Situação', 'Ações'],
  },
  unidades: {
    o: 'unidades', base: 'unit', rota: '/units', escrita: '/units?classico=1', novo: 'Nova unidade',
    busca: 'Buscar unidade…', uso: true, corpo: LIVRE,
    ajuda: 'Unidade decimal aceita quantidade fracionada (m², kg). Múltiplo de unidade base converte compra em caixa para venda em peça.',
    primeiro: 'Todo produto precisa de unidade. Comece pelas três da gráfica: Unidade (Un), Metro quadrado (m²) e Metro linear (m).',
    colunas: ['Unidade', 'Aceita decimal', 'Múltiplo da base', 'Produtos', 'Ações'],
  },
  categorias: {
    o: 'categorias', base: 'category', rota: '/taxonomies', escrita: '/taxonomies?type=product', novo: 'Nova categoria',
    busca: 'Buscar categoria ou código…', uso: true, corpo: LIVRE,
    ajuda: 'Categoria e subcategoria do produto — as mesmas do filtro do índice e do relatório de lucro por categoria. Código curto entra no SKU automático.',
    primeiro: 'Categoria é o que faz o relatório de lucro por categoria existir e o SKU sair automático. Comece pelas famílias que você orça.',
    colunas: ['Categoria', 'Código', 'Descrição', 'Produtos', 'Ações'],
  },
  marcas: {
    o: 'marcas', base: 'brand', rota: '/brands', escrita: '/brands?classico=1', novo: 'Nova marca',
    busca: 'Buscar marca…', uso: true, corpo: LIVRE,
    ajuda: 'Marca do produto — filtro do índice, relatório por marca e, quando marcada, lista de marcas de aparelho da Oficina.',
    primeiro: 'Marca é opcional no produto, mas é ela que faz o relatório por marca e a lista de aparelhos da Oficina.',
    colunas: ['Marca', 'Descrição curta', 'Produtos', 'Ações'],
  },
  garantias: {
    o: 'garantias', base: 'warranty', rota: '/warranties', escrita: '/warranties?classico=1', novo: 'Nova garantia',
    busca: 'Buscar garantia…', uso: false, corpo: LIVRE,
    ajuda: 'Prazo de garantia do produto — imprime na OS e na nota, e serve de base pro atendimento aceitar ou recusar retorno.',
    primeiro: 'Sem prazo cadastrado o atendimento decide retorno de cabeça. Cadastre os prazos que você já pratica.',
    colunas: ['Garantia', 'Descrição', 'Duração', 'Ações'],
  },
} as const;

// Ordem do protótipo: Variações · Grupos de preço · Unidades · Categorias · Marcas · Garantias.
const ORDEM: [AbaViva, string][] = [
  ['variacoes', 'Variações'], ['grupos', 'Grupos de preço'], ['unidades', 'Unidades'],
  ['categorias', 'Categorias'], ['marcas', 'Marcas'], ['garantias', 'Garantias'],
];

export default function CadastrosIndex({ aba: inicial, can, oficina, variacoes, grupos, unidades, categorias, marcas, garantias }: Props) {
  const [aba, setAba] = useState<AbaViva>(inicial);
  const [busca, setBusca] = useState('');
  const [excluir, setExcluir] = useState<Linha | null>(null);
  const [erro, setErro] = useState<string | null>(null);
  const [drawer, setDrawer] = useState<Pedido | null>(null);
  const buscaRef = useRef<HTMLInputElement>(null);
  const cfg = ABA[aba];
  const pode = can[aba];
  const dados = { variacoes, grupos, unidades, categorias, marcas, garantias };

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

  const linhas: Linha[] | null = aba === 'variacoes'
    ? variacoes?.map((v) => ({ id: v.id, nome: v.nome, busca: `${v.nome} ${v.valores.join(' ')}`, em_uso: v.em_uso, filhas: 0,
      filtro: '', celulas: [
        <Stack gap={0}><b>{v.nome}</b><span className="text-xs text-muted-foreground">{v.valores.length} valor(es)</span></Stack>,
        <Inline wrap gap={1}>{v.valores.map((x, i) => <span key={i} className="rounded border border-border px-1.5 text-xs">{x}</span>)}</Inline>,
      ] })) ?? null
    : aba === 'grupos'
      ? grupos?.map((g) => ({ id: g.id, nome: g.nome, busca: `${g.nome} ${g.descricao}`, em_uso: 0, filhas: 0, filtro: '', celulas: [
        <b className={g.ativo ? undefined : 'text-muted-foreground'}>{g.nome}</b>, g.descricao || '—', g.ativo ? 'Ativo' : 'Inativo',
      ] })) ?? null
    : aba === 'garantias'
      ? garantias?.map((w) => ({ id: w.id, nome: w.nome, busca: `${w.nome} ${w.descricao}`, em_uso: 0, filhas: 0, filtro: '', celulas: [
        <Stack gap={0}><b>{w.nome}</b><span className="text-xs text-muted-foreground">{w.duracao ?? 'sem prazo'}</span></Stack>,
        w.descricao || '—', <span className="font-mono text-xs">{w.duracao ?? '—'}</span>,
      ] })) ?? null
    : aba === 'unidades'
    ? unidades?.map((u) => ({ id: u.id, nome: u.nome, busca: u.nome, em_uso: u.em_uso, filhas: 0, filtro: `unidade=${u.id}`, celulas: [
      <><b>{u.nome}</b> <span className="text-muted-foreground">({u.simbolo})</span></>,
      u.decimal ? 'Sim' : 'Não',
      <span className="font-mono text-xs">{u.base ?? '—'}</span>,
    ] })) ?? null
    : aba === 'categorias'
      ? categorias?.map((c) => ({ id: c.id, nome: c.nome, busca: `${c.nome} ${c.codigo}`, em_uso: c.em_uso, filhas: c.filhas,
        filtro: `categoria=${c.pai_id ?? c.id}`, celulas: [
          <Stack gap={0}>
            <b className={c.pai_id ? 'pl-4' : undefined}>{c.pai_id ? `↳ ${c.nome}` : c.nome}</b>
            <span className={`text-xs text-muted-foreground${c.pai_id ? ' pl-4' : ''}`}>
              {c.pai_id ? `em ${c.pai ?? 'categoria excluída'}` : c.filhas > 0 ? `categoria · ${c.filhas} subcategoria(s)` : 'categoria'}
            </span>
          </Stack>,
          <span className="font-mono text-xs">{c.codigo || '—'}</span>,
          c.descricao || '—',
        ] })) ?? null
      : marcas?.map((m) => ({ id: m.id, nome: m.nome, busca: m.nome, em_uso: m.em_uso, filhas: 0, filtro: `marca=${m.id}`, celulas: [<b>{m.nome}</b>, m.descricao || '—'] })) ?? null;

  // Drawer (thread 10). Base válida = unidade que não é múltiplo de outra (protótipo :332); pai válido =
  // categoria principal (o mesmo filtro da lista de pais do modal clássico).
  const abrir = (id?: number): Pedido | null => {
    if (aba === 'unidades') {
      const x = unidades?.find((r) => r.id === id);
      return { tipo: 'unidade', id: x?.id, bases: (unidades ?? []).filter((r) => !r.base_id).map((r) => ({ id: r.id, rotulo: `${r.nome} (${r.simbolo})` })),
        v: x ? { nome: x.nome, simbolo: x.simbolo, decimal: x.decimal, base_id: x.base_id, multiplicador: x.multiplicador }
          : { nome: '', simbolo: '', decimal: false, base_id: null, multiplicador: '' } };
    }
    if (aba === 'marcas') {
      const x = marcas?.find((r) => r.id === id);
      return { tipo: 'marca', id: x?.id, oficina, v: x ? { nome: x.nome, descricao: x.descricao, oficina: x.oficina } : { nome: '', descricao: '', oficina: false } };
    }
    if (aba === 'categorias') {
      const x = categorias?.find((r) => r.id === id);
      return { tipo: 'categoria', id: x?.id, temFilhas: (x?.filhas ?? 0) > 0,
        pais: (categorias ?? []).filter((r) => !r.pai_id).map((r) => ({ id: r.id, rotulo: r.nome })),
        v: x ? { nome: x.nome, codigo: x.codigo, descricao: x.descricao, pai_id: x.pai_id } : { nome: '', codigo: '', descricao: '', pai_id: null } };
    }
    if (aba === 'variacoes') {
      const x = variacoes?.find((r) => r.id === id);
      return { tipo: 'variacao', id: x?.id,
        v: x ? { nome: x.nome, valores: x.valores.map((nome, i) => ({ id: x.valor_ids[i], nome })) } : { nome: '', valores: [{ nome: '' }] } };
    }
    if (aba === 'garantias') {
      const x = garantias?.find((r) => r.id === id);
      return { tipo: 'garantia', id: x?.id,
        v: x ? { nome: x.nome, descricao: x.descricao, duracao: x.duracao_n, tipo_prazo: x.duracao_tipo } : { nome: '', descricao: '', duracao: '', tipo_prazo: 'months' } };
    }
    return null;
  };
  const noDrawer = aba !== 'grupos';
  const botaoEscrita = (rotulo: string, id?: number, props: { size?: 'sm'; variant?: 'outline'; className?: string } = {}) => (noDrawer
    ? <Button {...props} onClick={() => setDrawer(abrir(id))}>{rotulo}</Button>
    : <Button asChild {...props}><a href={cfg.escrita}>{rotulo}</a></Button>);

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
    const resposta = await fetch(`${cfg.rota}/${excluir.id}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
    }).then((r) => r.json() as Promise<{ success?: boolean; msg?: string }>).catch(() => null);
    setExcluir(null);
    if (resposta?.success) router.reload({ only: [aba] });
    else setErro(resposta?.msg ?? 'Não foi possível excluir. Nada foi alterado.');
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
              items={ORDEM.map(([value, label]): SubNavItem => ({ value, label, badge: can[value].view ? dados[value]?.length : '—' }))}
            />
          </div>

          {!pode.view ? (
            <EmptyState icon="lock" title={`Você não vê ${cfg.o}`}
              description={`Seu papel não tem ${aba === 'grupos' ? 'product.create' : `${cfg.base}.view`} — quem libera é o administrador, em Papéis.`} />
          ) : (
            <>
              <Inline wrap gap={2} data-contract="produto-cadastros-barra">
                <Input ref={buscaRef} className="max-w-xs" value={busca} placeholder={cfg.busca} aria-label={cfg.busca}
                  onChange={(e) => setBusca(e.target.value)} />
                <span className="text-xs text-muted-foreground"><kbd>/</kbd> foca a busca</span>
                {pode.create ? (
                  botaoEscrita(cfg.novo, undefined, { size: 'sm', className: 'ml-auto' })
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
                      action={pode.create ? botaoEscrita(cfg.novo) : undefined} />
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
                            {cfg.uso && (
                              <td className="py-2 pr-3 text-right tabular-nums">
                                {l.em_uso > 0 && l.filtro
                                  ? <Link href={`/products/unificado?${l.filtro}`} className="text-primary underline-offset-2 hover:underline">{l.em_uso}</Link>
                                  : l.em_uso}
                              </td>
                            )}
                            <td className="py-2 pr-3">
                              <Inline gap={2}>
                                {pode.update && botaoEscrita('Editar', l.id, { variant: 'outline', size: 'sm' })}
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

      {drawer && (
        <CadastroDrawer pedido={drawer} onClose={() => setDrawer(null)}
          onSalvo={() => { setDrawer(null); router.reload({ only: [aba] }); }} />
      )}

      <AlertDialog open={!!excluir} onOpenChange={(o) => !o && setExcluir(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{excluir && recusa(excluir) ? 'O servidor recusa esta exclusão' : `Excluir “${excluir?.nome}”`}</AlertDialogTitle>
            <AlertDialogDescription>
              {excluir && recusa(excluir)
                ? `${recusa(excluir)}. ${excluir.filhas > 0 ? 'Mova ou exclua as subcategorias' : 'Troque o valor nesses produtos'} primeiro${excluir.em_uso > 0 ? ' — pela Edição em massa resolve em uma passada' : ''}.`
                : cfg.corpo}
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
