// Produto/Index — lista de produtos (/products) no desenho do protótipo.
//
// Âncora: prototipo-ui/cowork/Felipe/produto-blade.jsx, vista `lista` (TelaLista) — charter v3
// (Index.charter.md). NÃO é a `TelaCatalogo` de cards do mesmo arquivo.
// Dados: ProductController::buildProdutoListaPaginada — a MESMA consulta da lista Blade
// (`consultaListaProdutos`), paginada e buscada no servidor. Nada de corte em 200.
// CSS: resources/css/cowork-produto-bundle.css (cópia na íntegra do protótipo).
//
// Componentes pelo registro (governance/design/component-registry.json):
// DataTablePro → shared/DataTable (grid) · Kebab → ui/dropdown-menu · BulkBar →
// shared/BulkActionBar · Modal → ui/dialog / ui/alert-dialog · FilterChip → shared/PageFilters.
//
// Destinos (Editar, Novo produto, Duplicar, Histórico, Preços, Rótulos, Edição em massa) abrem
// com navegação de página INTEIRA, como na lista Blade: o cadastro da ROTA LIVRE segue no Blade
// (US-PROD-029) e esta tela não muda isso.
//
// Tier 0: business_id no servidor; PT-BR; localStorage com prefixo oimpresso.produto.*.

import '../../../css/cowork-produto-bundle.css';
import { Deferred, Head, router } from '@inertiajs/react';
import { useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { Download, FileSpreadsheet, Package, Plus, RefreshCw, Search, Tag } from 'lucide-react';
import { toast } from 'sonner';
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import PageHeaderTabs from '@/Components/shared/PageHeaderTabs';
import DataTable, { type EstadoDaLinha } from '@/Components/shared/DataTable';
import BulkActionBar from '@/Components/shared/BulkActionBar';
import { FilterChip } from '@/Components/shared/PageFilters';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Segmented } from '@/Components/ui/segmented';
import { Skeleton } from '@/Components/ui/skeleton';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import type { AcaoProduto } from './_components/lista/AcoesProduto';
import CadastroRapidoProduto from './_components/lista/CadastroRapido';
import EstoqueInicialProduto from './_components/lista/EstoqueInicial';
import { aplicarEncaixe, colunasDaLista, filtrosParaATabela } from './_components/lista/colunas';
import { encaixarColunas } from './_components/lista/encaixe';
import { useLargurasGuardadas } from './_components/lista/useLargurasGuardadas';
import { useTamanho } from './_components/lista/useTamanho';
import FiltrosListaProdutos from './_components/lista/FiltrosLista';
import IndicadoresLista from './_components/lista/IndicadoresLista';
import PainelDetalheProduto from './_components/lista/PainelDetalhe';
import RelatorioEstoqueAba from './_components/lista/RelatorioEstoqueAba';
import MenuColunas from './_components/lista/MenuColunas';
import {
  CHAVE_COLUNAS,
  CHAVE_DENSIDADE,
  CHAVE_IMPOSTO_DESLIGADO,
  POR_PAGINA,
  chipsDosFiltros,
  colunasGuardadas,
  colunasOpcionais,
  visibilidadePadrao,
  type ContextoLista,
  type FiltrosLista,
  type KpisLista,
  type AbaDoDetalhe,
  type DetalheProdutoDados,
  type RelatorioEstoqueLista,
  type LinhaProduto,
  type ListaPaginada,
  type OpcoesLista,
  type PermissoesLista,
} from './_components/lista/tipos';

interface ProdutoIndexPageProps {
  filtros: FiltrosLista;
  lista?: ListaPaginada;
  kpis?: KpisLista;
  /** Só com `stock_report.view`; `undefined` enquanto a aba não foi aberta. */
  estoque?: RelatorioEstoqueLista | null;
  /** Painel de detalhe (`?ver={id}`); `undefined` enquanto não foi aberto, `null` se o produto não é da empresa. */
  detalhe?: DetalheProdutoDados | null;
  opcoes: OpcoesLista;
  contexto: ContextoLista;
  permissions: PermissoesLista;
}


const csrf = () => document.head.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

/** Endpoints JSON da lista Blade ({success, msg}) — mesmo padrão de fetch do módulo. */
async function chamar(url: string, metodo: 'GET' | 'POST' | 'DELETE', corpo?: Record<string, unknown>): Promise<string> {
  const r = await fetch(url, {
    method: metodo,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': csrf(),
    },
    body: corpo ? JSON.stringify(corpo) : undefined,
  });
  const j = (await r.json().catch(() => null)) as { success?: boolean | number; msg?: string } | null;
  if (!r.ok || !j || !j.success) throw new Error(j?.msg || 'Não foi possível concluir a ação.');
  return j.msg ?? 'Feito.';
}

/** POST de formulário de página inteira — como os formulários da lista Blade. */
function enviarFormulario(url: string, campos: Record<string, string>) {
  const form = document.createElement('form');
  form.method = 'POST';
  form.action = url;
  const todos: Record<string, string> = { _token: csrf(), ...campos };
  for (const [k, v] of Object.entries(todos)) {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = k;
    input.value = v;
    form.appendChild(input);
  }
  document.body.appendChild(form);
  form.submit();
}

const irPara = (url: string) => window.location.assign(url);

function lerLocal<T>(chave: string, padrao: T): T {
  try {
    const v = localStorage.getItem(chave);
    return v === null ? padrao : (JSON.parse(v) as T);
  } catch {
    return padrao;
  }
}

function gravarLocal(chave: string, valor: unknown) {
  try {
    localStorage.setItem(chave, JSON.stringify(valor));
  } catch {
    // navegador sem storage: a escolha vale só nesta visita
  }
}

/** Parâmetros da URL: só o que difere do vazio. */
function paraUrl(f: FiltrosLista): Record<string, string | number> {
  const out: Record<string, string | number> = {};
  const texto: Array<keyof FiltrosLista> = ['type', 'category_id', 'brand_id', 'unit_id', 'tax_id', 'location_id', 'active_state', 'repair_model_id', 'natureza_fiscal'];
  for (const k of texto) {
    const v = f[k];
    if (typeof v === 'string' && v !== '') out[k] = v;
  }
  if (f.not_for_selling) out.not_for_selling = 1;
  if (f.woocommerce_enabled) out.woocommerce_enabled = 1;
  if (f.q) out.q = f.q;
  if (f.sort && f.sort !== 'produto') out.sort = f.sort;
  if (f.dir === 'desc') out.dir = 'desc';
  out.per_page = f.per_page;
  if (f.page > 1) out.page = f.page;
  if (f.aba === 'estoque') {
    out.aba = 'estoque';
    if (f.estoque_pagina > 1) out.estoque_pagina = f.estoque_pagina;
    out.estoque_por_pagina = f.estoque_por_pagina;
  }
  return out;
}

interface Confirmacao {
  titulo: string;
  corpo: string;
  aviso?: string;
  cta: string;
  perigo: boolean;
  executar: () => Promise<void> | void;
}

const hhmm = () => new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

function ProdutoIndex({ filtros, lista, kpis, estoque, detalhe, opcoes, contexto, permissions }: ProdutoIndexPageProps) {
  // Coluna e filtro Imposto só em empresa com alíquota cadastrada (decisão [F] 2026-10-26).
  const temAliquota = opcoes.impostos.length > 0;
  const opcionais = useMemo(() => colunasOpcionais(contexto.rotulos_campos, temAliquota), [contexto.rotulos_campos, temAliquota]);
  const [visiveis, setVisiveis] = useState<Record<string, boolean>>(() => ({
    ...visibilidadePadrao(opcionais),
    ...colunasGuardadas(lerLocal<Record<string, boolean>>(CHAVE_COLUNAS, {}), lerLocal<boolean>(CHAVE_IMPOSTO_DESLIGADO, false)),
  }));
  const [densa, setDensa] = useState<boolean>(() => lerLocal<boolean>(CHAVE_DENSIDADE, false));
  const [busca, setBusca] = useState(filtros.q);
  const [selecionados, setSelecionados] = useState<number[]>([]);
  // Larguras que o usuário arrastou. Moram AQUI, e não na tabela, porque a tabela vive dentro de
  // `<Deferred data="lista">` e é desmontada em toda visita não-parcial (ordenar, trocar de página,
  // filtrar). O "Atualizar a lista" é recarga parcial e não desmonta. E ficam no navegador
  // (`useLargurasGuardadas`): sem isso, voltar da edição de um produto ou apertar F5 desfazia o ajuste.
  const [largurasArrastadas, setLargurasArrastadas] = useLargurasGuardadas();
  const [confirmar, setConfirmar] = useState<Confirmacao | null>(null);
  // Os dois diálogos abaixo são portais. No <body>, fora do `.cockpit`, `--warn` e `--text`
  // chegam vazios. Medido em produção em 2026-10-19: no "Adicionar ao local", com a janela real,
  // `--text` vazio; no aviso da exclusão, por simulação (mesma classe no <body>), branco em vez
  // de laranja. Montam dentro do `.cockpit`, como o painel de detalhe (lápide §5 2026-10-19).
  const [cockpit, setCockpit] = useState<HTMLElement | null>(null);
  useLayoutEffect(() => setCockpit(document.querySelector<HTMLElement>('.cockpit')), []);
  const [localModal, setLocalModal] = useState<'add' | 'remove' | null>(null);
  const [locaisEscolhidos, setLocaisEscolhidos] = useState<string[]>([]);
  const [wooModal, setWooModal] = useState(false);
  const [rapido, setRapido] = useState(false);
  const [inicial, setInicial] = useState<{ id: number; nome: string } | null>(null);
  const [hora, setHora] = useState(hhmm);
  // Painel de detalhe: o produto aberto (a URL `?ver={id}` abre direto, e serve de link).
  const [verId, setVerId] = useState<number | null>(() => {
    const v = Number(new URLSearchParams(window.location.search).get('ver'));
    return v > 0 ? v : null;
  });
  // Aba em que o painel abre. Mora aqui porque o painel fica montado entre uma abertura e outra:
  // guardada nele, a aba da vez anterior valeria para o próximo produto.
  const [abaDoPainel, setAbaDoPainel] = useState<AbaDoDetalhe>('dados');
  const abrirDetalhe = (id: number, aba: AbaDoDetalhe = 'dados') => {
    setAbaDoPainel(aba);
    setVerId(id);
    router.reload({ only: ['detalhe'], data: { ver: id } });
  };
  const fecharDetalhe = () => {
    setVerId(null);
    router.get('/products', paraUrl(filtros), { preserveState: true, preserveScroll: true, replace: true, only: ['filtros'] });
  };
  const buscaRef = useRef<HTMLInputElement>(null);

  useEffect(() => gravarLocal(CHAVE_COLUNAS, visiveis), [visiveis]);
  useEffect(() => gravarLocal(CHAVE_IMPOSTO_DESLIGADO, true), []);
  useEffect(() => gravarLocal(CHAVE_DENSIDADE, densa), [densa]);

  // Espaço da página e da tabela: as colunas encaixam na largura da tabela (UC-PIDX-26) e a grade
  // ganha altura própria, como no protótipo, pra a barra de rolar para o lado ficar à vista
  // (UC-PIDX-27). Os dois elementos ficam fora do `<Deferred>`: não somem ao recarregar a lista.
  const corpoRef = useRef<HTMLDivElement>(null);
  const tabelaRef = useRef<HTMLDivElement>(null);
  const corpo = useTamanho(corpoRef);
  const areaDaTabela = useTamanho(tabelaRef);

  const pode = (p: keyof PermissoesLista) => permissions[p] === true;
  const linhas = lista?.data ?? [];

  const ir = (patch: Partial<FiltrosLista>) => {
    setSelecionados([]);
    const proximo: FiltrosLista = { ...filtros, page: 1, estoque_pagina: 1, ...patch };
    router.get('/products', paraUrl(proximo), { preserveState: true, preserveScroll: true, replace: true });
  };

  // Busca no servidor (a mesma regra da lista Blade), com espera de 300ms entre teclas.
  useEffect(() => {
    if (busca === filtros.q) return;
    const t = setTimeout(() => ir({ q: busca.trim() }), 300);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [busca]);

  // Atalhos do protótipo: "/" foca a busca · "n" abre o cadastro.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const ativo = document.activeElement as HTMLElement | null;
      const tag = (ativo?.tagName ?? '').toUpperCase();
      if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || ativo?.isContentEditable) return;
      if (e.key === '/') {
        e.preventDefault();
        buscaRef.current?.focus();
      }
      if ((e.key === 'n' || e.key === 'N') && permissions.create === true && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        irPara('/products/create');
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [permissions.create]);

  const recarregar = () => {
    setSelecionados([]);
    // Refaz também o valor do estoque, que fica guardado por 10 min no servidor.
    router.reload({
      only: filtros.aba === 'estoque' ? ['lista', 'kpis', 'estoque'] : ['lista', 'kpis'],
      headers: { 'X-Produto-Recalcular': '1' },
    });
    setHora(hhmm());
  };

  const executarJson = async (url: string, metodo: 'GET' | 'POST' | 'DELETE', corpo?: Record<string, unknown>) => {
    try {
      toast.success(await chamar(url, metodo, corpo));
      recarregar();
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'Não foi possível concluir a ação.');
    }
  };

  const aoEscolher = (acao: AcaoProduto, l: LinhaProduto): void => {
    switch (acao) {
      case 'ver':
        abrirDetalhe(l.id);
        return;
      case 'fiscal':
        abrirDetalhe(l.id, 'fiscal');
        return;
      case 'editar':
        irPara(`/products/${l.id}/edit`);
        return;
      case 'historico':
        irPara(`/products/stock-history/${l.id}`);
        return;
      case 'duplicar':
        irPara(`/products/create?d=${l.id}`);
        return;
      case 'rotulos':
        irPara(`/labels/show?product_id=${l.id}`);
        return;
      case 'precos':
        irPara(`/products/add-selling-prices/${l.id}`);
        return;
      case 'anexo':
        if (l.anexo) window.open(l.anexo.url, '_blank', 'noopener');
        return;
      case 'estoque_inicial':
        // Abre em janela, sem sair da lista (protótipo: ModalEstoqueInicial).
        setInicial({ id: l.id, nome: l.nome });
        return;
      case 'ativar':
        void executarJson(`/products/activate/${l.id}`, 'GET');
        return;
      case 'desativar':
        setConfirmar({
          titulo: 'Desativar produto',
          corpo: `“${l.nome}” sai da busca do PDV e dos orçamentos novos. O histórico, o estoque e as vendas antigas ficam intactos.`,
          cta: 'Desativar',
          perigo: true,
          executar: () => executarJson('/products/mass-deactivate', 'POST', { selected_products: String(l.id) }),
        });
        return;
      case 'excluir':
        setConfirmar({
          titulo: 'Excluir produto',
          corpo: `Excluir “${l.nome}” (${l.sku}) apaga o cadastro, as variações e os preços por grupo.`,
          aviso:
            l.controla_estoque && (l.estoque ?? 0) > 0
              ? 'Este produto tem estoque — o servidor pode recusar a exclusão. Desative em vez de excluir.'
              : 'Esta ação não tem volta.',
          cta: 'Excluir',
          perigo: true,
          executar: () => executarJson(`/products/${l.id}`, 'DELETE'),
        });
        return;
    }
  };

  // ── Colunas: as do protótipo, definidas em _components/lista/colunas.tsx ──
  const colunasDoPrototipo = useMemo(
    // Sem alíquota a coluna não existe, nem que o navegador a tenha guardado ligada.
    () => colunasDaLista({ visiveis: temAliquota ? visiveis : { ...visiveis, imposto: false }, densa, permissions, contexto, aoEscolher }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [visiveis, densa, permissions, contexto, temAliquota],
  );
  // Tela estreita: as colunas encolhem até caber (UC-PIDX-26). Do espaço da tabela saem a caixa de
  // seleção (34px) e a barra de rolagem vertical que a grade passa a ter (16px).
  const colunas = useMemo(() => {
    const disponivel = areaDaTabela.largura - 34 - 16;
    const larguras = encaixarColunas(
      colunasDoPrototipo.map((c) => ({ id: c.id ?? '', largura: c.meta?.width ?? 0 })),
      disponivel,
      largurasArrastadas,
    );
    return aplicarEncaixe(colunasDoPrototipo, larguras);
  }, [colunasDoPrototipo, areaDaTabela.largura, largurasArrastadas]);
  // Altura da grade como no protótipo (`ListaProdutos`): 72% da área que rola, nunca menos de 260px.
  const alturaDaGrade = corpo.altura > 0 ? Math.max(260, Math.round(corpo.altura * 0.72)) : undefined;

  const estadoDaLinha = (l: LinhaProduto): EstadoDaLinha | undefined =>
    l.inativo ? 'archived' : l.abaixo_do_alerta ? 'urgent' : undefined;

  const idsDaPagina = linhas.map((l) => l.id);
  const marcadasNaPagina = idsDaPagina.filter((id) => selecionados.includes(id)).length;
  const chips = chipsDosFiltros(filtros, opcoes);
  const total = lista?.total ?? 0;
  const ids = selecionados.join(',');
  const locaisReais = opcoes.locais.filter((l) => l.id !== 'none');

  const contextoLinha = [
    contexto.empresa,
    locaisReais.length === 1 ? locaisReais[0]?.nome : `${locaisReais.length} locais`,
    lista ? `${total} ${total === 1 ? 'produto' : 'produtos'}` : null,
    `${contexto.grupos_de_preco} ${contexto.grupos_de_preco === 1 ? 'grupo de preço' : 'grupos de preço'}`,
    contexto.papel ? `papel: ${contexto.papel}` : null,
  ]
    .filter(Boolean)
    .join(' · ');

  const limparTudo = () => {
    setBusca('');
    ir({
      type: null,
      category_id: null,
      brand_id: null,
      unit_id: null,
      tax_id: null,
      location_id: null,
      active_state: null,
      repair_model_id: null,
      natureza_fiscal: null,
      not_for_selling: false,
      woocommerce_enabled: false,
      q: '',
    });
  };

  return (
    <>
      <Head title="Produtos" />
      <div className="pb-root" data-screen-label="Produto · Catálogo">
        <PageHeader
          leading={<Package size={18} aria-hidden />}
          title="Produtos"
          suffix=" · Catálogo"
          subtitle={<span className="font-mono text-[11px] tracking-[.02em]">{contextoLinha}</span>}
          actions={
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={recarregar}
                className="inline-flex items-center gap-1.5 text-[11.5px] text-[var(--text-dim)] hover:text-[var(--text)]"
                title="Atualizar a lista"
              >
                <span aria-hidden className="h-1.5 w-1.5 rounded-full bg-[var(--pos)]" /> Atualizado {hora}
                <RefreshCw size={11} aria-hidden />
              </button>
              <Button variant="outline" size="sm" onClick={() => irPara('/products/download-excel')}>
                <FileSpreadsheet size={13} aria-hidden /> Baixar Excel
              </Button>
              {pode('create') && (
                <Button size="sm" onClick={() => irPara('/products/create')}>
                  <Plus size={13} aria-hidden /> Novo produto
                </Button>
              )}
            </div>
          }
        />

        <div className="pb-body" ref={corpoRef}>
          <div className="pb-kpis" data-contract="produto-kpis">
            <Deferred
              data="kpis"
              fallback={[0, 1, 2, 3].map((i) => (
                <Skeleton key={i} className="h-[92px] rounded-xl" />
              ))}
            >
              {kpis ? <IndicadoresLista kpis={kpis} /> : null}
            </Deferred>
          </div>

          <FiltrosListaProdutos filtros={filtros} opcoes={opcoes} woocommerce={contexto.woocommerce} naturezaFiscal={contexto.natureza_fiscal === true} aplicar={ir} />

          <section
            className="pb-widget"
            data-contract="produto-lista"
            aria-label={filtros.aba === 'estoque' ? 'Relatório de estoque' : 'Todos os produtos'}
          >
            <h3>
              <Package size={13} aria-hidden /> {filtros.aba === 'estoque' ? 'Relatório de estoque' : 'Todos os produtos'}{' '}
              {filtros.aba === 'lista' && lista && (
                <span className="n">
                  {linhas.length} de {total}
                </span>
              )}
            </h3>

            {permissions.stock_report === true && (
              <div data-contract="produto-abas" className="px-3">
                <PageHeaderTabs
                  density="compact"
                  activeGhostKey={filtros.aba}
                  onGhostChange={(k) => ir({ aba: k === 'estoque' ? 'estoque' : 'lista' })}
                  ghosts={[
                    {
                      key: 'lista',
                      label: 'Todos os produtos',
                      href: '/products',
                      ...(lista ? { badge: total } : {}),
                    },
                    { key: 'estoque', label: 'Relatório de estoque', href: '/products?aba=estoque' },
                  ]}
                />
              </div>
            )}

            {filtros.aba === 'lista' && (
            <div className="pb-toolbar" data-contract="produto-toolbar">
              <div className="pb-busca">
                <Search size={12} aria-hidden />
                <input
                  ref={buscaRef}
                  value={busca}
                  onChange={(e) => setBusca(e.target.value)}
                  placeholder="Buscar nome ou SKU…"
                  aria-label="Buscar produtos por nome, SKU ou código"
                />
                <kbd>/</kbd>
              </div>
              <div className="sp" />
              <Segmented
                value={densa ? 'compacto' : 'confortavel'}
                onValueChange={(v) => setDensa(v === 'compacto')}
                options={[
                  { value: 'confortavel', label: 'Confortável' },
                  { value: 'compacto', label: 'Compacto' },
                ]}
              />
              <MenuColunas
                colunas={opcionais}
                visiveis={visiveis}
                aoMudar={setVisiveis}
                larguraAjustada={Object.keys(largurasArrastadas).length > 0}
                aoRestaurarLarguras={() => setLargurasArrastadas({})}
              />
              {permissions.create === true && (
                <Button variant="outline" size="sm" onClick={() => setRapido(true)}>
                  <Plus size={12} aria-hidden /> Cadastro rápido
                </Button>
              )}
            </div>
            )}

            {chips.length > 0 && (
              <div className="pb-chips" data-contract="produto-chips">
                <span className="pb-chips-l">Filtrando por</span>
                {chips.map((c) => (
                  <FilterChip
                    key={c.chave}
                    label={c.rotulo}
                    onRemove={() => {
                      if (c.chave === 'q') setBusca('');
                      const vazio = c.chave === 'not_for_selling' || c.chave === 'woocommerce_enabled' ? false : c.chave === 'q' ? '' : null;
                      ir({ [c.chave]: vazio } as Partial<FiltrosLista>);
                    }}
                  />
                ))}
                <button type="button" className="pb-chip limpar" onClick={limparTudo}>
                  Limpar tudo
                </button>
              </div>
            )}

            {filtros.aba === 'estoque' ? (
              <Deferred
                data="estoque"
                fallback={
                  <div className="space-y-2 px-4 py-3" aria-busy="true">
                    {Array.from({ length: 8 }).map((_, i) => (
                      <Skeleton key={i} className="h-7 w-full" />
                    ))}
                  </div>
                }
              >
                {estoque ? (
                  <RelatorioEstoqueAba
                    dados={estoque}
                    irParaPagina={(p) => ir({ estoque_pagina: p })}
                    aoMudarPorPagina={(n) => ir({ estoque_por_pagina: n })}
                  />
                ) : (
                  <></>
                )}
              </Deferred>
            ) : (
            <>
            <div data-contract="produto-tabela" ref={tabelaRef}>
              <Deferred
                data="lista"
                fallback={
                  <div className="space-y-2 px-4 py-3" aria-busy="true">
                    {Array.from({ length: 8 }).map((_, i) => (
                      <Skeleton key={i} className="h-7 w-full" />
                    ))}
                  </div>
                }
              >
                <DataTable<LinhaProduto>
                  caption="Produtos do catálogo"
                  columns={colunas}
                  data={linhas}
                  pagination={lista}
                  endpoint="/products"
                  filters={filtrosParaATabela(paraUrl(filtros), filtros)}
                  showSearch={false}
                  density="grid"
                  resizable
                  columnWidths={largurasArrastadas}
                  onColumnResize={(id, largura) =>
                    setLargurasArrastadas((m) => (m[id] === largura ? m : { ...m, [id]: largura }))
                  }
                  scrollMaxHeight={alturaDaGrade}
                  tableWrapperClassName="pb-grid-pro"
                  totalLabel="produtos"
                  rowKey={(l) => l.id}
                  rowState={estadoDaLinha}
                  onRowClick={pode('view') ? (l) => abrirDetalhe(l.id) : undefined}
                  emptyMessage="Nenhum produto com esses filtros. Remova um filtro pelos chips acima — ou cadastre o produto que está faltando."
                  selection={{
                    isSelected: (l) => selecionados.includes(l.id),
                    onToggle: (l) => setSelecionados((s) => (s.includes(l.id) ? s.filter((x) => x !== l.id) : [...s, l.id])),
                    allState: marcadasNaPagina === 0 ? 'none' : marcadasNaPagina === idsDaPagina.length ? 'all' : 'some',
                    onToggleAll: (marcar) =>
                      setSelecionados((s) =>
                        marcar ? Array.from(new Set([...s, ...idsDaPagina])) : s.filter((x) => !idsDaPagina.includes(x)),
                      ),
                    rowLabel: (l) => l.nome,
                    allLabel: 'Selecionar todos desta página',
                  }}
                />
              </Deferred>
            </div>

            <div className="pb-pag" data-contract="produto-rodape-lista">
              <span>
                {lista ? `${total} ${total === 1 ? 'produto' : 'produtos'} na lista` : 'Carregando produtos…'} · clique no cabeçalho pra
                ordenar, arraste a borda pra redimensionar
              </span>
              <div className="sp" />
              {/* Exportar filtrados (protótipo): as MESMAS linhas da lista, todas as páginas, em CSV. */}
              <Button
                variant="outline"
                size="sm"
                onClick={() => irPara(`/products?${new URLSearchParams({ ...Object.fromEntries(Object.entries(paraUrl(filtros)).map(([k, v]) => [k, String(v)])), exportar: 'csv' }).toString()}`)}
              >
                <Download size={12} aria-hidden /> Exportar filtrados
              </Button>
              <div className="flex items-center gap-[5px]">
                Mostrar
                <Select value={String(filtros.per_page)} onValueChange={(v) => ir({ per_page: Number(v) })}>
                  <SelectTrigger className="h-6 w-[88px]" aria-label="Produtos por página">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {POR_PAGINA.map((n) => (
                      <SelectItem key={n} value={String(n)}>
                        {n === -1 ? 'Todos' : n}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                por página
              </div>
            </div>
            </>
            )}
          </section>

          {selecionados.length > 0 && (
            <BulkActionBar selectedCount={selecionados.length} onClear={() => setSelecionados([])} label="produto(s) selecionado(s)">
              {pode('update') && (
                <Button variant="outline" size="sm" onClick={() => enviarFormulario('/products/bulk-edit', { selected_products: ids })}>
                  Edição em massa
                </Button>
              )}
              {pode('update') && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setLocaisEscolhidos([]);
                    setLocalModal('add');
                  }}
                >
                  Adicionar ao local
                </Button>
              )}
              {pode('update') && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    setLocaisEscolhidos([]);
                    setLocalModal('remove');
                  }}
                >
                  Remover do local
                </Button>
              )}
              {pode('etiquetas') && (
                <Button variant="outline" size="sm" onClick={() => irPara(`/labels/show?product_ids=${selecionados.join(',')}`)}>
                  <Tag size={12} aria-hidden /> Etiquetas
                </Button>
              )}
              {contexto.woocommerce && (
                <Button variant="outline" size="sm" onClick={() => setWooModal(true)}>
                  WooCommerce Sync
                </Button>
              )}
              {pode('update') && (
                <Button
                  variant="outline"
                  size="sm"
                  onClick={() =>
                    setConfirmar({
                      titulo: 'Desativar selecionados',
                      corpo: `${selecionados.length} produto(s) saem da venda e do orçamento novo, mantendo histórico e estoque.`,
                      cta: `Desativar ${selecionados.length}`,
                      perigo: true,
                      executar: () => executarJson('/products/mass-deactivate', 'POST', { selected_products: ids }),
                    })
                  }
                >
                  Desativar selecionados
                </Button>
              )}
              {pode('delete') && (
                <Button
                  variant="outline"
                  size="sm"
                  className="text-destructive"
                  onClick={() =>
                    setConfirmar({
                      titulo: 'Excluir selecionados',
                      corpo: `Vai apagar ${selecionados.length} cadastro(s), com variações e preços por grupo.`,
                      aviso: 'Produtos com movimento de estoque são recusados pelo servidor.',
                      cta: `Excluir ${selecionados.length}`,
                      perigo: true,
                      executar: () => enviarFormulario('/products/mass-delete', { selected_rows: ids }),
                    })
                  }
                >
                  Excluir selecionados
                </Button>
              )}
            </BulkActionBar>
          )}
        </div>
      </div>

      <AlertDialog open={confirmar !== null} onOpenChange={(o) => !o && setConfirmar(null)}>
        {confirmar && (
          <AlertDialogContent container={cockpit}>
            <AlertDialogHeader>
              <AlertDialogTitle>{confirmar.titulo}</AlertDialogTitle>
              <AlertDialogDescription>{confirmar.corpo}</AlertDialogDescription>
            </AlertDialogHeader>
            {confirmar.aviso && <p className="text-[12px] text-[var(--warn)]">{confirmar.aviso}</p>}
            <AlertDialogFooter>
              <AlertDialogCancel>Cancelar</AlertDialogCancel>
              <AlertDialogAction
                className={confirmar.perigo ? 'bg-destructive text-white hover:bg-destructive/90' : undefined}
                onClick={() => {
                  const c = confirmar;
                  setConfirmar(null);
                  void c.executar();
                }}
              >
                {confirmar.cta}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        )}
      </AlertDialog>

      <Dialog open={localModal !== null} onOpenChange={(o) => !o && setLocalModal(null)}>
        <DialogContent container={cockpit}>
          <DialogHeader>
            <DialogTitle>{localModal === 'add' ? 'Adicionar ao local' : 'Remover do local'}</DialogTitle>
            <DialogDescription>
              {selecionados.length} produto(s) selecionado(s).{' '}
              {localModal === 'add'
                ? 'O local passa a vender e estocar estes produtos.'
                : 'Os produtos deixam de aparecer no local — o estoque já lançado não é apagado.'}
            </DialogDescription>
          </DialogHeader>
          <div className="flex flex-col gap-2">
            {locaisReais.map((l) => (
              <div key={l.id} className="pb-chk">
                <Checkbox
                  id={`local-${l.id}`}
                  checked={locaisEscolhidos.includes(l.id)}
                  onCheckedChange={(v) => setLocaisEscolhidos((s) => (v === true ? [...s, l.id] : s.filter((x) => x !== l.id)))}
                />
                <label htmlFor={`local-${l.id}`}>
                  <b>{l.nome}</b>
                </label>
              </div>
            ))}
          </div>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setLocalModal(null)}>
              Cancelar
            </Button>
            <Button
              disabled={locaisEscolhidos.length === 0}
              onClick={() => {
                const tipo = localModal;
                setLocalModal(null);
                void executarJson('/products/bulk-update-location', 'POST', {
                  products: ids,
                  product_location: locaisEscolhidos,
                  update_type: tipo,
                });
              }}
            >
              Atualizar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={wooModal} onOpenChange={setWooModal}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>WooCommerce Sync</DialogTitle>
            <DialogDescription>{selecionados.length} produto(s) selecionado(s).</DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setWooModal(false);
                void executarJson('/products/toggle-woocommerce-sync', 'POST', { woocommerce_products_sync: ids, woocommerce_disable_sync: 1 });
              }}
            >
              Desligar sincronização
            </Button>
            <Button
              onClick={() => {
                setWooModal(false);
                void executarJson('/products/toggle-woocommerce-sync', 'POST', { woocommerce_products_sync: ids, woocommerce_disable_sync: 0 });
              }}
            >
              Ligar sincronização
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {permissions.create === true && (
        <CadastroRapidoProduto
          aberto={rapido}
          aoFechar={() => setRapido(false)}
          unidades={opcoes.unidades}
          categorias={opcoes.categorias}
          locais={locaisReais}
          aoCriar={(msg) => {
            toast.success(msg);
            recarregar();
          }}
        />
      )}

      {permissions.opening_stock === true && (
        <EstoqueInicialProduto
          produto={inicial}
          aoFechar={() => setInicial(null)}
          aoSalvar={(msg) => {
            toast.success(msg);
            recarregar();
          }}
        />
      )}

      <PainelDetalheProduto
        aberto={verId !== null}
        detalhe={detalhe && detalhe.id === verId ? detalhe : undefined}
        aba={abaDoPainel}
        aoMudarAba={setAbaDoPainel}
        aoFechar={fecharDetalhe}
        podeEditar={pode('update')}
        podePrecos={pode('create') && contexto.grupos_de_preco > 0}
        emiteNota={contexto.emite_nota}
        irPara={irPara}
      />
    </>
  );
}

// Layout persistente (padrão do repo e da tela anterior): o shell não remonta a cada filtro.
ProdutoIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Produtos — Catálogo" breadcrumbItems={[{ label: 'Produtos', href: '/products' }, { label: 'Catálogo' }]}>
    {page}
  </AppShellV2>
);

export default ProdutoIndex;
