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
// 02 e 03). As seis abas criam e editam no drawer: cinco na thread 10 (PR-a e PR-b) e Grupos de preço
// em 2026-11-03 (UC-PCADAP-23). Até então Grupos de preço abria o modal da Blade (`?classico=1`).

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, Link, router } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useRef, useState, type ReactNode } from 'react';
import { Banknote, CircleAlert, Plus, TriangleAlert } from 'lucide-react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Skeleton } from '@/Components/ui/skeleton';
import { Switch } from '@/Components/ui/switch';
import {
  AlertDialog, AlertDialogContent,
  AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { PageHeader } from '@/Components/PageHeader';
import SubNav, { type SubNavItem } from '@/Components/shared/SubNav';
import EmptyState from '@/Components/shared/EmptyState';
import { Inline, Stack } from '@/Components/layout';
import CadastroDrawer, { type Pedido } from './_components/CadastroDrawer';
import { AJUDA, CHIP, DANGER, GHOST, MD, MONO, PRIMARIO, PRIMARIO_BTN, SM, SUB, TD, TH } from './_components/estilo';
import { MOLDURA_JANELA, TITULO_JANELA } from '../_components/lista/moldura-janela';
import { cn } from '@/Lib/utils';

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
// filtra por aquilo (variação: `/products` não tem filtro por modelo) e o número não vira link.
// `filhas`: subcategorias vivas — junto com `em_uso`, é o que faz o servidor recusar a exclusão.
// `inativo`: linha esmaecida (`state: "archived"` do DataGrid, opacidade .55) — grupo de preço desativado.
type Linha = { id: number; nome: string; busca: string; em_uso: number; filhas: number; filtro: string; inativo?: boolean; celulas: ReactNode[] };

// Copy de cada aba, tirada de produto-cadastros.jsx (AbaVariacoes :214 · AbaGrupos :264 · AbaUnidades :310 ·
// AbaCategorias :374 · AbaMarcas :425 · AbaGarantias :463). `rota` recebe o DELETE (JSON do legado);
// `uso: false` = aba sem coluna Produtos; `corpo` = confirmação de excluir livre.
// `colunas`: [rótulo, largura em px] — as larguras do `columns[]` de cada aba do protótipo; sem
// largura, a coluna fica com o resto. A coluna de uso é a penúltima e alinha à direita.
const LIVRE = 'Nenhum produto usa este registro — sai limpo. Ação sem volta.';
const ABA = {
  variacoes: {
    o: 'variações', base: 'variation', rota: '/variation-templates', novo: 'Nova variação',
    busca: 'Buscar variação ou valor…', uso: true, corpo: 'O modelo deixa de aparecer no cadastro novo. Nenhum produto usa esses valores hoje.',
    ajuda: 'Modelo reaproveitado no cadastro de produto variável: escolher “Cor” já traz os valores abaixo como variações.',
    primeiro: 'Modelo de variação é o atalho do produto variável: cadastre “Cor” uma vez e todo produto novo já oferece os valores.',
    colunas: [['Variação', 200], ['Valores'], ['Em uso', 90], ['Ações', 160]],
  },
  grupos: {
    o: 'grupos de preço', base: 'product', rota: '/selling-price-group', novo: 'Novo grupo',
    busca: 'Buscar grupo…', uso: false,
    corpo: 'Os preços digitados neste grupo somem junto. Vendas já emitidas mantêm o valor praticado. Se a ideia é só esconder, desative.',
    ajuda: 'Cada grupo ativo vira uma coluna em Preços por grupo e uma opção de preço no PDV e no orçamento. Desativar esconde o grupo sem apagar os preços já digitados.',
    primeiro: 'Sem grupo cadastrado todo mundo paga o preço de tabela. Crie “Atacado” pra ter um segundo preço no PDV e no orçamento.',
    colunas: [['Grupo', 200], ['Descrição'], ['Situação', 140], ['Ações', 160]],
  },
  unidades: {
    o: 'unidades', base: 'unit', rota: '/units', novo: 'Nova unidade',
    busca: 'Buscar unidade…', uso: true, corpo: LIVRE,
    ajuda: 'Unidade decimal aceita quantidade fracionada (m², kg). Múltiplo de unidade base converte compra em caixa para venda em peça.',
    primeiro: 'Todo produto precisa de unidade. Comece pelas três da gráfica: Unidade (Un), Metro quadrado (m²) e Metro linear (m).',
    colunas: [['Unidade', 210], ['Aceita decimal', 130], ['Múltiplo da base'], ['Produtos', 100], ['Ações', 160]],
  },
  categorias: {
    o: 'categorias', base: 'category', rota: '/taxonomies', novo: 'Nova categoria',
    busca: 'Buscar categoria ou código…', uso: true, corpo: LIVRE,
    ajuda: 'Categoria e subcategoria do produto — as mesmas do filtro do índice e do relatório de lucro por categoria. Código curto entra no SKU automático.',
    primeiro: 'Categoria é o que faz o relatório de lucro por categoria existir e o SKU sair automático. Comece pelas famílias que você orça.',
    colunas: [['Categoria', 240], ['Código', 110], ['Descrição'], ['Produtos', 100], ['Ações', 160]],
  },
  marcas: {
    o: 'marcas', base: 'brand', rota: '/brands', novo: 'Nova marca',
    busca: 'Buscar marca…', uso: true, corpo: LIVRE,
    ajuda: 'Marca do produto — filtro do índice, relatório por marca e, quando marcada, lista de marcas de aparelho da Oficina.',
    primeiro: 'Marca é opcional no produto, mas é ela que faz o relatório por marca e a lista de aparelhos da Oficina.',
    colunas: [['Marca', 220], ['Descrição curta'], ['Produtos', 100], ['Ações', 160]],
  },
  garantias: {
    o: 'garantias', base: 'warranty', rota: '/warranties', novo: 'Nova garantia',
    busca: 'Buscar garantia…', uso: false, corpo: LIVRE,
    ajuda: 'Prazo de garantia do produto — imprime na OS e na nota, e serve de base pro atendimento aceitar ou recusar retorno.',
    primeiro: 'Sem prazo cadastrado o atendimento decide retorno de cabeça. Cadastre os prazos que você já pratica.',
    colunas: [['Garantia', 200], ['Descrição'], ['Duração', 140], ['Ações', 160]],
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
  const [alternando, setAlternando] = useState<number | null>(null);
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
      filtro: 'type=variable', celulas: [
        <><b className={PRIMARIO}>{v.nome}</b><span className={SUB}>{v.valores.length} valor(es)</span></>,
        <Inline wrap gap={1}>{v.valores.map((x, i) => <span key={i} className={CHIP}>{x}</span>)}</Inline>,
      ] })) ?? null
    : aba === 'grupos'
      ? grupos?.map((g) => ({ id: g.id, nome: g.nome, busca: `${g.nome} ${g.descricao}`, em_uso: 0, filhas: 0, filtro: '', inativo: !g.ativo, celulas: [
        <><b className={PRIMARIO}>{g.nome}</b><span className={SUB}>{g.ativo ? 'ativo' : 'inativo'}</span></>, g.descricao || '—',
        // Switch da AbaGrupos do protótipo: liga/desliga na própria linha (UC-PCADAP-24). A rota da tela
        // clássica alterna o estado; o servidor só aceita grupo do meu negócio e exige product.create.
        <Inline gap={2} align="center">
          <Switch id={`grupo-ativo-${g.id}`} variant="cowork" checked={g.ativo} disabled={!pode.create || alternando === g.id}
            aria-label={`${g.ativo ? 'Desativar' : 'Ativar'} o grupo ${g.nome}`} onCheckedChange={() => alternar(g.id)} />
          <label htmlFor={`grupo-ativo-${g.id}`} className="text-[12.5px] text-[var(--text)]">{g.ativo ? 'Ativo' : 'Inativo'}</label>
        </Inline>,
      ] })) ?? null
    : aba === 'garantias'
      ? garantias?.map((w) => ({ id: w.id, nome: w.nome, busca: `${w.nome} ${w.descricao}`, em_uso: 0, filhas: 0, filtro: '', celulas: [
        <><b className={PRIMARIO}>{w.nome}</b><span className={SUB}>{w.duracao ?? 'sem prazo'}</span></>,
        w.descricao || '—', <span className={MONO}>{w.duracao ?? '—'}</span>,
      ] })) ?? null
    : aba === 'unidades'
    ? unidades?.map((u) => ({ id: u.id, nome: u.nome, busca: u.nome, em_uso: u.em_uso, filhas: 0, filtro: `unit_id=${u.id}`, celulas: [
      <><b className={PRIMARIO}>{u.nome}</b><span className={SUB}>{u.simbolo}</span></>,
      u.decimal ? 'Sim' : 'Não',
      <span className={MONO}>{u.base ?? '—'}</span>,
    ] })) ?? null
    : aba === 'categorias'
      ? categorias?.map((c) => ({ id: c.id, nome: c.nome, busca: `${c.nome} ${c.codigo}`, em_uso: c.em_uso, filhas: c.filhas,
        filtro: `category_id=${c.pai_id ?? c.id}`, celulas: [
          <>
            <b className={cn(PRIMARIO, c.pai_id && 'pl-4')}>{c.pai_id ? `↳ ${c.nome}` : c.nome}</b>
            <span className={cn(SUB, c.pai_id && 'pl-4')}>
              {c.pai_id ? `em ${c.pai ?? 'categoria excluída'}` : c.filhas > 0 ? `categoria · ${c.filhas} subcategoria(s)` : 'categoria'}
            </span>
          </>,
          <span className={MONO}>{c.codigo || '—'}</span>,
          c.descricao || '—',
        ] })) ?? null
      : marcas?.map((m) => ({ id: m.id, nome: m.nome, busca: m.nome, em_uso: m.em_uso, filhas: 0, filtro: `brand_id=${m.id}`, celulas: [<><b className={PRIMARIO}>{m.nome}</b><span className={SUB}>{m.oficina ? 'também na Oficina' : 'catálogo'}</span></>, m.descricao || '—'] })) ?? null;

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
    if (aba === 'grupos') {
      const x = grupos?.find((r) => r.id === id);
      return { tipo: 'grupo', id: x?.id, v: x ? { nome: x.nome, descricao: x.descricao } : { nome: '', descricao: '' } };
    }
    if (aba === 'garantias') {
      const x = garantias?.find((r) => r.id === id);
      return { tipo: 'garantia', id: x?.id,
        v: x ? { nome: x.nome, descricao: x.descricao, duracao: x.duracao_n, tipo_prazo: x.duracao_tipo } : { nome: '', descricao: '', duracao: '', tipo_prazo: 'months' } };
    }
    return null;
  };
  const botaoEscrita = (rotulo: ReactNode, id?: number, className?: string, variant: 'default' | 'outline' = 'outline') => (
    <Button variant={variant} className={className} onClick={() => setDrawer(abrir(id))}>{rotulo}</Button>);
  const novo = <><Plus className="size-[13px]" aria-hidden />{cfg.novo}</>;

  // Drawer e janela são portais: montam dentro do `.cockpit`, onde os tokens do DS existem
  // (lápide §5 2026-10-19). No <body> o fundo e as cores dos dois saíam vazios.
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);

  const termo = busca.trim().toLowerCase();
  const filtradas = linhas?.filter((l) => !termo || l.busca.toLowerCase().includes(termo)) ?? [];
  const recusa = (l: Linha) => [
    l.em_uso > 0 ? `${l.em_uso} produto(s) usam este registro` : null,
    l.filhas > 0 ? `${l.filhas} subcategoria(s) estão dentro dela` : null,
  ].filter(Boolean).join(' e ');

  // Ativa/desativa grupo de preço (rota JSON da tela clássica, que só responde a XHR).
  const alternar = async (id: number) => {
    setAlternando(id);
    setErro(null);
    const resposta = await fetch(`/selling-price-group/activate-deactivate/${id}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then((r) => (r.ok ? (r.json() as Promise<{ success?: boolean; msg?: string }>) : null)).catch(() => null);
    setAlternando(null);
    if (resposta?.success) router.reload({ only: ['grupos'] });
    else setErro(resposta?.msg ?? 'Não foi possível mudar a situação do grupo. Nada foi alterado.');
  };

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
              <Inline wrap gap={2} align="end" className="gap-[10px]" data-contract="produto-cadastros-barra">
                <Input ref={buscaRef} className="max-w-[300px] flex-[1_1_260px]" value={busca} placeholder={cfg.busca} aria-label={cfg.busca}
                  onChange={(e) => setBusca(e.target.value)} />
                <span className={cn(AJUDA, 'pb-2 whitespace-nowrap')}><kbd>/</kbd> foca · <kbd>⌘K</kbd> troca de tela</span>
                {/* `extra` da AbaGrupos do protótipo: a tela de Atualizar preço (exporta/importa a planilha por grupo). */}
                {aba === 'grupos' && pode.update && (
                  <Button asChild variant="outline" className={cn(SM, GHOST)}>
                    <Link href="/update-product-price"><Banknote className="size-[13px]" aria-hidden />Atualizar preços em planilha</Link>
                  </Button>
                )}
                {pode.create ? (
                  botaoEscrita(novo, undefined, cn(SM, PRIMARIO_BTN, 'ml-auto'), 'default')
                ) : (
                  <Button className={cn(SM, PRIMARIO_BTN, 'ml-auto')} disabled title={`Seu papel não tem ${cfg.base}.create — quem libera é o administrador, em Papéis.`}>{novo}</Button>
                )}
              </Inline>
              <p className={AJUDA} data-contract="produto-cadastros-ajuda">{cfg.ajuda}</p>
              {erro && <p role="alert" className="text-sm text-destructive">{erro}</p>}

              <div data-contract="produto-cadastros-tabela">
                <Deferred data={aba} fallback={<Skeleton className="h-48 w-full" />}>
                  {!linhas?.length ? (
                    <EmptyState title={`Nenhum registro de ${cfg.o}`} description={cfg.primeiro}
                      action={pode.create ? botaoEscrita(novo, undefined, cn(MD, PRIMARIO_BTN), 'default') : undefined} />
                  ) : !filtradas.length ? (
                    <EmptyState variant="search" icon="search-x" title="Nada com esse termo"
                      description={`Nenhuma das ${linhas.length} ${cfg.o} bate com “${busca}”.`}
                      action={<Button variant="outline" className={cn(MD, GHOST)} onClick={() => setBusca('')}>Limpar busca</Button>} />
                  ) : (
                    <table className="w-full border-separate border-spacing-0 text-[12.5px]">
                      <thead>
                        <tr>{cfg.colunas.map(([rotulo, largura], i) => (
                          <th key={rotulo} scope="col" style={largura ? { width: largura } : undefined}
                            className={cn(TH, cfg.uso && i === cfg.colunas.length - 2 && 'text-right')}>{rotulo}</th>
                        ))}</tr>
                      </thead>
                      <tbody>
                        {filtradas.map((l) => (
                          <tr key={l.id} className="hover:[&>td]:bg-[var(--bg-2)]">
                            {l.celulas.map((c, i) => <td key={i} className={cn(TD, l.inativo && 'opacity-55')}>{c}</td>)}
                            {cfg.uso && (
                              <td className={cn(TD, 'text-right tabular-nums')}>
                                {l.em_uso > 0 && l.filtro
                                  ? <Button asChild variant="outline" className={cn(SM, GHOST)}><Link href={`/products?${l.filtro}`}>{l.em_uso}</Link></Button>
                                  : <span className={l.em_uso > 0 ? undefined : 'text-[var(--text-dim)]'}>{l.em_uso}</span>}
                              </td>
                            )}
                            <td className={TD}>
                              <Inline gap={2} className="gap-1.5">
                                {pode.update && botaoEscrita('Editar', l.id, cn(SM, GHOST))}
                                {pode.delete && <Button variant="outline" className={cn(SM, DANGER)} onClick={() => setExcluir(l)}>Excluir</Button>}
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
        <CadastroDrawer pedido={drawer} container={cockpit} onClose={() => setDrawer(null)}
          onSalvo={() => { setDrawer(null); router.reload({ only: [aba] }); }} />
      )}

      {/* `Modal` do DS (520px) com o `Alert` dentro: aviso "Ação sem volta" quando sai limpo,
          recusa do servidor quando há uso (produto-cadastros.jsx `Confirmar`). */}
      <AlertDialog open={!!excluir} onOpenChange={(o) => !o && setExcluir(null)}>
        <AlertDialogContent className={cn(MOLDURA_JANELA, 'data-[size=default]:sm:max-w-[520px]')} container={cockpit}>
          <AlertDialogHeader>
            <AlertDialogTitle className={TITULO_JANELA}>{`Excluir “${excluir?.nome ?? ''}”`}</AlertDialogTitle>
            <AlertDialogDescription asChild>
              {excluir && recusa(excluir) ? (
                <Aviso tom="perigo" titulo="O servidor recusa esta exclusão">
                  {recusa(excluir)}. {excluir.filhas > 0 ? 'Mova ou exclua as subcategorias' : 'Troque o valor nesses produtos'} primeiro
                  {excluir.em_uso > 0 && <> — pela <b>Edição em massa</b> resolve em uma passada</>}.
                </Aviso>
              ) : (
                <Aviso tom="atencao" titulo="Ação sem volta">{cfg.corpo}</Aviso>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            {/* Button próprio, não o Cancel do ui/alert-dialog: ele repassa a classe por Slot sem resolver conflito e o
                canto/respiro do shadcn venciam (medido em produção 2026-11-25: 6px e 8×16 no lugar de 8px e 0×12). */}
            <Button variant="outline" className={cn(MD, GHOST)} onClick={() => setExcluir(null)}>{excluir && recusa(excluir) ? 'Fechar' : 'Cancelar'}</Button>
            {excluir && !recusa(excluir) && <Button variant="outline" className={cn(MD, DANGER)} onClick={confirmar}>Excluir</Button>}
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </Stack>
  );
}

// `Alert` do DS: fundo 6% e borda 22% do tom, ícone numa caixa a 14%.
function Aviso({ tom, titulo, children }: { tom: 'atencao' | 'perigo'; titulo: string; children: ReactNode }) {
  const cor = tom === 'perigo' ? 'var(--neg)' : 'var(--warn)';
  const mistura = (p: number) => `color-mix(in oklab, ${cor} ${p}%, transparent)`;
  const Icone = tom === 'perigo' ? CircleAlert : TriangleAlert;
  return (
    <Inline align="start" className="gap-[11px] rounded-[8px] border px-3.5 py-3 text-left"
      style={{ background: mistura(6), borderColor: mistura(22) }}>
      <Inline asChild align="center" justify="center" className="mt-px size-[22px] flex-none rounded-[6px]">
        <span style={{ background: mistura(14), color: cor }}><Icone className="size-3.5" aria-hidden /></span>
      </Inline>
      <div className="min-w-0 flex-1 pt-px">
        <div className="text-[13px] leading-[1.3] font-semibold text-[var(--text)]">{titulo}</div>
        <div className="mt-0.5 text-[12.5px] leading-[1.45] text-[var(--text-dim)]">{children}</div>
      </div>
    </Inline>
  );
}

CadastrosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
