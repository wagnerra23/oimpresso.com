// Estoque inicial em janela (Produto/Index → EstoqueInicial.tsx + estoque-inicial-dominio.ts) — o
// que a JANELA manda.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// É lançamento de estoque. Dois riscos que só a tela pode criar:
//   1. mandar número ambíguo para o conversor do servidor (incidente de 2026-06-05);
//   2. mandar pedido INCOMPLETO — a gravação antiga apaga, em cada local enviado, a linha de
//      estoque inicial que não vier, e desconta o saldo. A janela tem de reenviar TODAS as linhas
//      que recebeu, sempre.
//
// ── O QUE MEDE (componente e funções REAIS) ──────────────────────────────────
//   - variação sem linha ganha uma linha em branco com o custo do cadastro (como a tela Blade);
//   - o pedido leva todas as linhas existentes, com o id delas, mesmo sem ninguém mexer;
//   - quantidade e custo vão com vírgula decimal e sem milhar; nunca com ponto;
//   - "+" acrescenta uma linha nova (sem id) e mantém as existentes;
//   - o subtotal exibido é quantidade × custo e NÃO vai no pedido;
//   - validade, lote e segunda unidade só aparecem quando a empresa/produto usa;
//   - erro do servidor aparece e a janela continua aberta.
//
// Cobre UC-PIDX-15 (resources/js/Pages/Produto/Index.casos.md). O lado do servidor — o que é gravado
// — é o tests/Feature/Produto/EstoqueInicialJanelaTest.php.
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-estoque-inicial.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import EstoqueInicialProduto from '@/Pages/Produto/_components/lista/EstoqueInicial';
import {
  comLinhaAlterada,
  comLinhaNova,
  comLinhasEmBranco,
  erroDoEstoqueInicial,
  pedidoDoEstoqueInicial,
  subtotalDaLinha,
  totalDoLocal,
  type DadosEstoqueInicial,
  type LinhaEstoqueInicial,
  type PedidoEstoqueInicial,
  type RespostaEstoqueInicial,
} from '@/Pages/Produto/_components/lista/estoque-inicial-dominio';

const linha = (parte: Partial<LinhaEstoqueInicial> = {}): LinhaEstoqueInicial => ({
  purchase_line_id: null, quantidade: 0, custo: 5, validade: null, lote: null, data: null, observacao: null, segunda_unidade_qtd: 0, ...parte,
});

/** Matriz com uma linha lançada (id 71, 10 un. a 5, lote L-1) e Filial sem nada. */
const dados = (parte: Partial<DadosEstoqueInicial> = {}): DadosEstoqueInicial => ({
  produto: { id: 7, nome: 'Camiseta básica', unidade: 'Un', segunda_unidade: null },
  usa_validade: false,
  usa_lote: true,
  casas: { quantidade: 2, preco: 2 },
  locais: [
    { id: 1, nome: 'Matriz', variacoes: [{ id: 40, nome: '', custo_padrao: 5, linhas: [linha({ purchase_line_id: 71, quantidade: 10, lote: 'L-1', data: '2026-03-10T14:30' })] }] },
    { id: 2, nome: 'Filial', variacoes: [{ id: 40, nome: '', custo_padrao: 5, linhas: [] }] },
  ],
  ...parte,
});

function abrir(d: DadosEstoqueInicial | { erro: string } = dados(), resposta: RespostaEstoqueInicial = { ok: true, msg: 'Estoque inicial lançado.' }) {
  const aoFechar = vi.fn();
  const aoSalvar = vi.fn();
  const buscar = vi.fn(async () => d);
  const enviar = vi.fn(async (_p: PedidoEstoqueInicial) => resposta);
  render(<EstoqueInicialProduto produto={{ id: 7, nome: 'Camiseta básica' }} aoFechar={aoFechar} aoSalvar={aoSalvar} buscar={buscar} enviar={enviar} />);
  return { aoFechar, aoSalvar, buscar, enviar };
}

function digitar(rotulo: string | RegExp, texto: string) {
  const campo = screen.getByLabelText(rotulo);
  fireEvent.focus(campo);
  fireEvent.change(campo, { target: { value: texto } });
  fireEvent.blur(campo);
}
const salvar = () => fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));
const linhasDe = (p: PedidoEstoqueInicial, local: number) => p.locais.find((l) => l.location_id === local)?.variacoes[0]?.linhas ?? [];

afterEach(() => cleanup());

describe('UC-PIDX-15 · linhas e pedido', () => {
  it('variação sem linha ganha uma em branco com o custo do cadastro; a existente fica como veio', () => {
    const d = comLinhasEmBranco(dados());
    expect(d.locais[0]?.variacoes[0]?.linhas).toHaveLength(1);
    expect(d.locais[0]?.variacoes[0]?.linhas[0]?.purchase_line_id).toBe(71);
    expect(d.locais[1]?.variacoes[0]?.linhas).toEqual([linha({ custo: 5 })]);
  });

  it('o pedido leva TODAS as linhas existentes, com o id, mesmo sem ninguém mexer', () => {
    const d = dados({
      locais: [
        { id: 1, nome: 'Matriz', variacoes: [{ id: 40, nome: '', custo_padrao: 5, linhas: [linha({ purchase_line_id: 71, quantidade: 10 }), linha({ purchase_line_id: 72, quantidade: 3 })] }] },
        { id: 2, nome: 'Filial', variacoes: [{ id: 40, nome: '', custo_padrao: 5, linhas: [linha({ purchase_line_id: 80, quantidade: 8 })] }] },
      ],
    });
    const p = pedidoDoEstoqueInicial(comLinhasEmBranco(d));
    expect(p.product_id).toBe(7);
    expect(linhasDe(p, 1).map((x) => x.purchase_line_id)).toEqual([71, 72]);
    expect(linhasDe(p, 2).map((x) => x.purchase_line_id)).toEqual([80]);
  });

  it('quantidade e custo vão com vírgula decimal e sem milhar; nunca com ponto', () => {
    const d = dados({ locais: [{ id: 1, nome: 'Matriz', variacoes: [{ id: 40, nome: '', custo_padrao: 5, linhas: [linha({ quantidade: 1500.5, custo: 1234.56 })] }] }] });
    const x = linhasDe(pedidoDoEstoqueInicial(d), 1)[0];
    expect([x?.quantidade, x?.custo, x?.segunda_unidade_qtd]).toEqual(['1500,5', '1234,56', '0']);
    for (const n of [0.1 + 0.2, 12.125, 80, 1e6]) {
      const y = linhasDe(pedidoDoEstoqueInicial(comLinhaAlterada(d, 1, 40, 0, { quantidade: n, custo: n })), 1)[0];
      expect(`${y?.quantidade}${y?.custo}`).not.toContain('.');
    }
  });

  it('o subtotal é quantidade × custo, só para exibir: não vai no pedido', () => {
    expect(subtotalDaLinha(linha({ quantidade: 2.5, custo: 12 }))).toBe(30);
    const d = comLinhasEmBranco(dados());
    expect(totalDoLocal(d.locais[0]!)).toBe(50);
    const chaves = Object.keys(linhasDe(pedidoDoEstoqueInicial(d), 1)[0] ?? {}).sort();
    expect(chaves).toEqual(['custo', 'data', 'lote', 'observacao', 'purchase_line_id', 'quantidade', 'segunda_unidade_qtd', 'validade']);
  });

  it('"+" acrescenta uma linha nova, sem id, e mantém a existente', () => {
    const d = comLinhaNova(comLinhasEmBranco(dados()), 1, 40);
    expect(d.locais[0]?.variacoes[0]?.linhas.map((x) => x.purchase_line_id)).toEqual([71, null]);
    expect(d.locais[1]?.variacoes[0]?.linhas).toHaveLength(1);
  });

  it('número negativo impede de salvar', () => {
    const d = comLinhasEmBranco(dados());
    expect(erroDoEstoqueInicial(d)).toBeNull();
    expect(erroDoEstoqueInicial(comLinhaAlterada(d, 1, 40, 0, { quantidade: -1 }))).not.toBeNull();
    expect(erroDoEstoqueInicial(comLinhaAlterada(d, 2, 40, 0, { custo: -0.5 }))).not.toBeNull();
  });
});

describe('UC-PIDX-15 · a janela', () => {
  it('abre com as linhas do servidor e uma em branco onde não havia', async () => {
    const { buscar } = abrir();
    expect(await screen.findByLabelText('Quantidade — Matriz')).toHaveProperty('value', '10,00');
    expect(screen.getByLabelText('Quantidade — Filial')).toHaveProperty('value', '0,00');
    expect(screen.getByLabelText('Lote — Matriz')).toHaveProperty('value', 'L-1');
    expect(buscar).toHaveBeenCalledWith(7);
  });

  it('mexer só na Filial e salvar: a linha da Matriz vai junto, intacta', async () => {
    const { enviar, aoSalvar, aoFechar } = abrir();
    await screen.findByLabelText('Quantidade — Filial');
    digitar('Quantidade — Filial', '2,5');
    digitar('Custo unitário sem imposto — Filial', '12,125');
    salvar();

    await waitFor(() => expect(enviar).toHaveBeenCalledTimes(1));
    const p = enviar.mock.calls[0]?.[0] as PedidoEstoqueInicial;
    expect(linhasDe(p, 1)).toEqual([
      { purchase_line_id: 71, quantidade: '10', custo: '5', validade: null, lote: 'L-1', data: '2026-03-10T14:30', observacao: null, segunda_unidade_qtd: '0' },
    ]);
    expect(linhasDe(p, 2)).toEqual([
      { purchase_line_id: null, quantidade: '2,5', custo: '12,13', validade: null, lote: null, data: null, observacao: null, segunda_unidade_qtd: '0' },
    ]);
    await waitFor(() => expect(aoSalvar).toHaveBeenCalledWith('Estoque inicial lançado.'));
    expect(aoFechar).toHaveBeenCalledTimes(1);
  });

  it('"+" na Matriz cria a segunda linha, e as duas vão no pedido', async () => {
    const { enviar } = abrir();
    await screen.findByLabelText('Quantidade — Matriz');
    fireEvent.click(screen.getByRole('button', { name: 'Mais uma linha — Matriz' }));
    digitar('Quantidade — Matriz · linha 2', '3');
    salvar();

    await waitFor(() => expect(enviar).toHaveBeenCalledTimes(1));
    const p = enviar.mock.calls[0]?.[0] as PedidoEstoqueInicial;
    expect(linhasDe(p, 1).map((x) => [x.purchase_line_id, x.quantidade])).toEqual([[71, '10'], [null, '3']]);
  });

  it('validade, lote e segunda unidade só aparecem quando a empresa ou o produto usa', async () => {
    abrir(dados({ usa_lote: false }));
    await screen.findByLabelText('Quantidade — Matriz');
    expect(screen.queryByLabelText('Lote — Matriz')).toBeNull();
    expect(screen.queryByLabelText('Validade — Matriz')).toBeNull();
    expect(screen.queryByLabelText(/Quantidade em /)).toBeNull();
    cleanup();

    abrir(dados({ usa_validade: true, produto: { id: 7, nome: 'Camiseta básica', unidade: 'Un', segunda_unidade: 'Cx' } }));
    expect(await screen.findByLabelText('Validade — Matriz')).toBeTruthy();
    expect(screen.getByLabelText('Lote — Matriz')).toBeTruthy();
    expect(screen.getByLabelText('Quantidade em Cx — Matriz')).toBeTruthy();
  });

  it('produto com variações mostra a variação e a usa no nome dos campos', async () => {
    abrir(dados({ locais: [{ id: 1, nome: 'Matriz', variacoes: [
      { id: 40, nome: 'Tamanho : P', custo_padrao: 5, linhas: [] },
      { id: 41, nome: 'Tamanho : M', custo_padrao: 6, linhas: [] },
    ] }] }));
    expect(await screen.findByLabelText('Quantidade — Matriz · Tamanho : P')).toBeTruthy();
    expect(screen.getByLabelText('Custo unitário sem imposto — Matriz · Tamanho : M')).toHaveProperty('value', '6,00');
    expect(screen.getByRole('columnheader', { name: 'Variação' })).toBeTruthy();
  });

  it('erro do servidor aparece e a janela continua aberta', async () => {
    const { aoFechar, aoSalvar } = abrir(dados(), { ok: false, msg: 'Este estoque inicial foi alterado depois que a janela abriu. Feche e abra de novo.' });
    await screen.findByLabelText('Quantidade — Matriz');
    salvar();
    expect(await screen.findByText(/foi alterado depois que a janela abriu/)).toBeTruthy();
    expect(aoFechar).not.toHaveBeenCalled();
    expect(aoSalvar).not.toHaveBeenCalled();
  });

  it('produto que não abre mostra o motivo e não deixa salvar', async () => {
    abrir({ erro: 'Produto não encontrado ou sem controle de estoque.' });
    expect(await screen.findByText('Produto não encontrado ou sem controle de estoque.')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Salvar' })).toHaveProperty('disabled', true);
  });
});
