// Lista de produtos em tela estreita — as colunas encaixam no espaço, a largura arrastada fica
// lembrada e a grade tem altura própria (a barra de rolar para o lado fica à vista).
//
// Cobre UC-PIDX-26 (encaixe), UC-PIDX-27 (altura da grade), a parte "lembrada no navegador" do
// UC-PIDX-16 e o UC-PIDX-34 (coluna e filtro Imposto só com alíquota) — resources/js/Pages/Produto/Index.casos.md.
//
// Origem: reclamação da ROTA LIVRE (2026-10-17) — em monitor 1366×768 as colunas não apareciam e
// era preciso estreitá-las à mão a cada visita. Medido em produção no mesmo dia, janela 1366×648,
// menu lateral aberto: 1.034px de espaço para a tabela, que exigia 1.704px; Tipo, Categoria, Marca,
// Imposto e SKU ficavam fora da tela, e a barra de rolar para o lado ficava a 4.097px do topo.
//
// Lane: Vitest do `Frontend / Vite build` (.github/workflows/ci.yml), desde 2026-10-18. Cada lane
// Vitest lista os próprios specs: a lista viva é a de lá.
// Comando: npx vitest run tests/js/produto-lista-encaixe.test.tsx

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';
import { aplicarEncaixe, colunasDaLista, filtrosParaATabela } from '@/Pages/Produto/_components/lista/colunas';
import { PISO_DA_COLUNA, encaixarColunas } from '@/Pages/Produto/_components/lista/encaixe';
import MenuColunas from '@/Pages/Produto/_components/lista/MenuColunas';
import FiltrosListaProdutos from '@/Pages/Produto/_components/lista/FiltrosLista';
import { useLargurasGuardadas } from '@/Pages/Produto/_components/lista/useLargurasGuardadas';
import {
  CHAVE_LARGURAS,
  colunasGuardadas,
  colunasOpcionais,
  faixaDePreco,
  largurasValidas,
  visibilidadePadrao,
  type LinhaProduto,
  type FiltrosLista,
  type OpcoesLista,
  type PermissoesLista,
} from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

const linha = (id: number, nome: string): LinhaProduto => ({
  id, nome, sku: `SKU-${id}`, tipo: 'single', categoria: 'Camisetas', subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
  marca: 'Hering', imposto: null, imagem: null, controla_estoque: true, estoque: 5, alerta: null, abaixo_do_alerta: false,
  inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  compra_min: 10, compra_max: 10, venda_min: 20, venda_max: 20,
});
const LINHAS = [linha(1, 'Camiseta gola polo manga curta azul marinho'), linha(2, 'Blusa')];
const PAG: PaginatorShape<LinhaProduto> = {
  data: LINHAS, total: 2, current_page: 1, last_page: 1, from: 1, to: 2,
  links: [
    { url: null, label: '&laquo; Anterior', active: false },
    { url: '/products?page=1', label: '1', active: true },
    { url: null, label: 'Próxima &raquo;', active: false },
  ],
};
const TUDO: PermissoesLista = {
  create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
  etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
};
const CONTEXTO = { empresa: 'Empresa de teste', grupos_de_preco: 1, emite_nota: true, woocommerce: false, papel: null, rotulos_campos: {} };
// O cenário medido em produção em 2026-10-17 tinha a coluna Imposto ligada: até 2026-10-26 ela
// nascia ligada (desde então nasce desligada, UC-PIDX-34). Com ela são 12 colunas, o caso mais apertado.
const PADRAO = { ...visibilidadePadrao(colunasOpcionais({})), imposto: true };

/** As colunas de saída, com a largura do protótipo — o que a tela entrega ao encaixe. */
const colunasDoPrototipo = () => colunasDaLista({ visiveis: PADRAO, densa: false, permissions: TUDO, contexto: CONTEXTO, aoEscolher: () => undefined });
const paraEncaixe = () => colunasDoPrototipo().map((c) => ({ id: c.id ?? '', largura: c.meta?.width ?? 0 }));

/** O espaço medido em 1366px (1.034px) menos a caixa de seleção (34) e a barra vertical (16). */
const DISPONIVEL_1366 = 1034 - 34 - 16;

const rotulo = (th: Element) => (th.textContent ?? '').replace(/[↕↑↓]/g, '').trim();
const cabecalho = (r: string) => {
  const th = screen.getAllByRole('columnheader').find((h) => rotulo(h) === r);
  if (!th) throw new Error(`cabeçalho "${r}" não existe`);
  return th as HTMLElement;
};
const larguras = () => Array.from(document.querySelectorAll('colgroup col')).map((c) => (c as HTMLElement).style.width);

beforeEach(() => {
  vi.clearAllMocks();
  localStorage.clear();
});
afterEach(() => {
  cleanup();
  localStorage.clear();
});

describe('UC-PIDX-26 · as colunas encaixam no espaço da tela', () => {
  it('em tela larga nada muda: valem as larguras do protótipo', () => {
    expect(encaixarColunas(paraEncaixe(), 1704)).toEqual({});
    expect(encaixarColunas(paraEncaixe(), 2400)).toEqual({});
  });

  it('em 1366px as 12 colunas de saída cabem: cada uma cede a mesma fração da folga até o piso', () => {
    const encaixe = encaixarColunas(paraEncaixe(), DISPONIVEL_1366);

    expect(encaixe).toEqual({
      produto: 114, locais: 63, compra: 92, venda: 92, estoque: 88, tipo: 96, categoria: 81, marca: 64, imposto: 60, sku: 58,
    });
    // Ação (96) e Imagem (74) não encolhem — o protótipo as fixa.
    const soma = paraEncaixe().reduce((s, c) => s + (encaixe[c.id] ?? c.largura), 0);
    expect(soma).toBe(978);
    expect(soma).toBeLessThanOrEqual(DISPONIVEL_1366);
  });

  // Medido em produção em 2026-10-18 (largura que o <th> pede, com a seta de ordenar): palavra única
  // não quebra, então o piso tem de caber o rótulo. Na 1ª versão "CATEGORIA" (79) tinha piso 66 e
  // vazava 12px sobre o rótulo de MARCA.
  it('nenhum piso fica abaixo da largura que o rótulo do cabeçalho pede', () => {
    const ROTULO_PEDE: Record<string, number> = { categoria: 79, locais: 61, marca: 64, imposto: 60, sku: 50 };
    for (const [id, pede] of Object.entries(ROTULO_PEDE)) {
      expect(PISO_DA_COLUNA[id], id).toBeGreaterThanOrEqual(pede);
    }
  });

  it('nenhuma coluna passa do piso; se nem os pisos cabem, a tabela fica nos pisos e rola', () => {
    const encaixe = encaixarColunas(paraEncaixe(), 500);
    for (const c of paraEncaixe()) {
      const piso = PISO_DA_COLUNA[c.id];
      if (piso !== undefined) expect(encaixe[c.id], c.id).toBe(piso);
    }
  });

  it('a coluna que a pessoa arrastou não encolhe; as outras cedem o espaço dela', () => {
    const encaixe = encaixarColunas(paraEncaixe(), DISPONIVEL_1366, { produto: 300 });
    expect(encaixe.produto).toBeUndefined();
    // Sem o espaço que Produto liberaria, as demais vão aos pisos.
    expect(encaixe.categoria).toBe(PISO_DA_COLUNA.categoria);
    expect(encaixe.compra).toBe(PISO_DA_COLUNA.compra);
  });

  it('na tabela: as larguras encaixadas viram o <colgroup> e a tabela cabe nos 1.034px', () => {
    const colunas = aplicarEncaixe(colunasDoPrototipo(), encaixarColunas(paraEncaixe(), DISPONIVEL_1366));
    render(
      <DataTable<LinhaProduto>
        caption="Produtos do catálogo"
        columns={colunas}
        data={LINHAS}
        pagination={PAG}
        endpoint="/products"
        filters={filtrosParaATabela({ per_page: 25 }, { sort: 'produto', dir: 'asc' })}
        showSearch={false}
        density="grid"
        resizable
        rowKey={(l) => l.id}
      />,
    );

    expect(larguras()).toEqual(['96px', '74px', '114px', '63px', '92px', '92px', '88px', '96px', '81px', '64px', '60px', '58px']);
    expect(Number.parseInt((document.querySelector('table') as HTMLElement).style.minWidth, 10)).toBeLessThanOrEqual(1034);
  });

  it('coluna encolhida deixa o rótulo quebrar em duas linhas; a que não mudou fica numa linha só', () => {
    const colunas = aplicarEncaixe(colunasDoPrototipo(), encaixarColunas(paraEncaixe(), DISPONIVEL_1366));
    render(<DataTable<LinhaProduto> caption="Produtos" columns={colunas} data={LINHAS} density="grid" rowKey={(l) => l.id} />);

    expect(cabecalho('Preço de compra un.').querySelector('.whitespace-normal')).not.toBeNull();
    expect(cabecalho('Ação').querySelector('.whitespace-normal')).toBeNull();
  });

  it('faixa de preço de produto variável quebra em duas linhas depois do traço, em vez de cortar o valor', () => {
    const variavel: LinhaProduto = { ...linha(3, 'Calça jeans'), tipo: 'variable', variacoes: 4, venda_min: 59.9, venda_max: 89.9 };
    render(<DataTable<LinhaProduto> caption="Produtos" columns={colunasDoPrototipo()} data={[variavel]} density="grid" rowKey={(l) => l.id} />);

    // O `title` leva a faixa como o servidor formata (`faixaDePreco`), com espaços comuns.
    const celula = screen.getByTitle(faixaDePreco('variable', 59.9, 89.9));
    expect(celula.className).toContain('whitespace-normal');
    // Só o espaço depois do traço quebra; dentro de cada valor o espaço é inseparável.
    expect(celula.textContent).toBe('R$\u00a059,90\u00a0– R$\u00a089,90');
  });

  it('texto cortado com "…" mostra o inteiro ao passar o mouse', () => {
    render(<DataTable<LinhaProduto> caption="Produtos" columns={colunasDoPrototipo()} data={LINHAS} density="grid" rowKey={(l) => l.id} />);

    expect(screen.getByTitle('Camiseta gola polo manga curta azul marinho')).toBeTruthy();
    expect(screen.getAllByTitle('Matriz').length).toBe(2);
    expect(screen.getAllByTitle('Hering').length).toBe(2);
    expect(screen.getByTitle('SKU-1')).toBeTruthy();
  });
});

describe('UC-PIDX-27 · a grade tem altura própria e a barra de rolar para o lado fica à vista', () => {
  const areaQueRola = () => document.querySelector('table')?.parentElement as HTMLElement;

  it('com altura, a área que rola fica limitada (o cabeçalho é fixo e a barra horizontal fica no fim da grade)', () => {
    render(<DataTable<LinhaProduto> caption="Produtos" columns={colunasDoPrototipo()} data={LINHAS} density="grid" rowKey={(l) => l.id} scrollMaxHeight={391} />);
    expect(areaQueRola().style.maxHeight).toBe('391px');
    expect(areaQueRola().className).toContain('overflow-auto');
  });

  it('sem a opção, a grade cresce com as linhas, como antes', () => {
    render(<DataTable<LinhaProduto> caption="Produtos" columns={colunasDoPrototipo()} data={LINHAS} density="grid" rowKey={(l) => l.id} />);
    expect(areaQueRola().style.maxHeight).toBe('');
  });
});

describe('UC-PIDX-16 · a largura arrastada fica lembrada no navegador', () => {
  function Sonda() {
    const [larguras, setLarguras] = useLargurasGuardadas();
    return (
      <div>
        <output data-testid="larguras">{JSON.stringify(larguras)}</output>
        <button type="button" onClick={() => setLarguras((m) => ({ ...m, produto: 350 }))}>arrastar</button>
        <button type="button" onClick={() => setLarguras({})}>desfazer</button>
      </div>
    );
  }
  const lidas = () => JSON.parse(screen.getByTestId('larguras').textContent ?? '{}');

  it('arrastar grava no navegador, e a próxima visita (F5, voltar da edição) abre com a mesma largura', () => {
    const { unmount } = render(<Sonda />);
    fireEvent.click(screen.getByText('arrastar'));
    expect(JSON.parse(localStorage.getItem(CHAVE_LARGURAS) ?? '{}')).toEqual({ produto: 350 });

    unmount();
    render(<Sonda />);
    expect(lidas()).toEqual({ produto: 350 });
  });

  it('"Voltar às larguras de saída" apaga a chave do navegador', () => {
    localStorage.setItem(CHAVE_LARGURAS, JSON.stringify({ produto: 350 }));
    render(<Sonda />);
    fireEvent.click(screen.getByText('desfazer'));
    expect(localStorage.getItem(CHAVE_LARGURAS)).toBeNull();
  });

  it('o que não é largura de verdade não sai do navegador', () => {
    expect(largurasValidas({ produto: 350, locais: 10, sku: 'x', marca: 5000, tipo: 96.4 })).toEqual({ produto: 350, tipo: 96 });
    expect(largurasValidas([1, 2])).toEqual({});
    expect(largurasValidas(null)).toEqual({});
    localStorage.setItem(CHAVE_LARGURAS, '{quebrado');
    render(<Sonda />);
    expect(lidas()).toEqual({});
  });

  it('o menu "Colunas" só oferece "Voltar às larguras de saída" quando há largura ajustada', () => {
    const aoRestaurar = vi.fn();
    const { unmount } = render(
      <MenuColunas colunas={colunasOpcionais({})} visiveis={PADRAO} aoMudar={() => undefined} larguraAjustada={false} aoRestaurarLarguras={aoRestaurar} />,
    );
    fireEvent.keyDown(screen.getByRole('button', { name: /Colunas/ }), { key: 'Enter' });
    expect(screen.queryByRole('menuitem', { name: 'Voltar às larguras de saída' })).toBeNull();
    unmount();

    render(<MenuColunas colunas={colunasOpcionais({})} visiveis={PADRAO} aoMudar={() => undefined} larguraAjustada aoRestaurarLarguras={aoRestaurar} />);
    fireEvent.keyDown(screen.getByRole('button', { name: /Colunas/ }), { key: 'Enter' });
    fireEvent.click(screen.getByRole('menuitem', { name: 'Voltar às larguras de saída' }));
    expect(aoRestaurar).toHaveBeenCalledTimes(1);
  });
});

describe('UC-PIDX-34 · coluna e filtro Imposto só com alíquota cadastrada', () => {
  // Decisão [F] 2026-10-26: o preço da lista já é o que a venda cobra, com o imposto; a coluna
  // Imposto é consulta, não precisa nascer ligada. E sem alíquota cadastrada não há o que mostrar.
  const FILTROS: FiltrosLista = {
    type: null, category_id: null, brand_id: null, unit_id: null, tax_id: null, location_id: null, active_state: null,
    not_for_selling: false, woocommerce_enabled: false, repair_model_id: null, q: '', sort: 'name', dir: 'asc',
    per_page: 25, page: 1, aba: 'lista', estoque_pagina: 1, estoque_por_pagina: 25,
  };
  const opcoes = (impostos: OpcoesLista['impostos']): OpcoesLista => ({
    categorias: [], marcas: [], unidades: [], impostos, locais: [{ id: '1', nome: 'Matriz' }], modelos_dispositivo: null,
  });
  const ICMS = [{ id: '3', nome: 'ICMS 18%' }];

  it('com alíquota, a coluna existe no menu e nasce desligada', () => {
    const imposto = colunasOpcionais({}, true).find((c) => c.id === 'imposto');
    expect(imposto).toEqual({ id: 'imposto', rotulo: 'Imposto', padrao: false });
    expect(visibilidadePadrao(colunasOpcionais({}, true)).imposto).toBe(false);
    expect(visibilidadePadrao(colunasOpcionais({}, true)).marca).toBe(true); // anti-vácuo: as outras seguem ligadas
  });

  it('sem alíquota, a coluna nem aparece no menu "Colunas"', () => {
    const colunas = colunasOpcionais({}, false);
    expect(colunas.map((c) => c.id)).not.toContain('imposto');
    render(<MenuColunas colunas={colunas} visiveis={visibilidadePadrao(colunas)} aoMudar={() => undefined} />);
    fireEvent.keyDown(screen.getByRole('button', { name: /Colunas/ }), { key: 'Enter' });
    const itens = screen.getAllByRole('menuitemcheckbox').map((i) => i.textContent);
    expect(itens).toContain('Marca');
    expect(itens).not.toContain('Imposto');
  });

  it('o "ligada" que o navegador guardou antes de 2026-10-26 era o padrão antigo: some na 1ª vez', () => {
    const salvas = { marca: false, imposto: true };
    expect(colunasGuardadas(salvas, false)).toEqual({ marca: false });
    // Depois da 1ª vez, a escolha de quem religou a coluna fica.
    expect(colunasGuardadas(salvas, true)).toEqual({ marca: false, imposto: true });
  });

  it('o filtro Imposto só existe com alíquota cadastrada', () => {
    const { unmount } = render(<FiltrosListaProdutos filtros={FILTROS} opcoes={opcoes(ICMS)} woocommerce={false} aplicar={() => undefined} />);
    expect(screen.getByRole('combobox', { name: 'Imposto' })).toBeTruthy();
    unmount();

    render(<FiltrosListaProdutos filtros={FILTROS} opcoes={opcoes([])} woocommerce={false} aplicar={() => undefined} />);
    expect(screen.getByRole('combobox', { name: 'Marca' })).toBeTruthy(); // anti-vácuo: os filtros estão lá
    expect(screen.queryByRole('combobox', { name: 'Imposto' })).toBeNull();
    expect(screen.getByText(/Mesmos filtros do índice de produtos/).textContent).not.toContain('imposto');
  });
});
