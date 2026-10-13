// Cadastro rápido de produto (Produto/Index → CadastroRapido.tsx) — o que a JANELA manda.
//
// ── POR QUE EXISTE ───────────────────────────────────────────────────────────
// É cadastro com preço. A regra do projeto: a tela nunca manda número ambíguo para o conversor do
// servidor (incidente de 2026-06-05: "204.99605" virou duzentos milhões). Aqui a janela manda os
// dois preços digitados como texto com vírgula decimal e SEM milhar, e NÃO calcula mais nada — preço
// com imposto, margem e padrões são do servidor (ProductController::cadastroRapido).
//
// ── O QUE MEDE (componente e funções REAIS) ──────────────────────────────────
//   - o pedido tem só os 8 campos da janela — nenhum preço calculado na tela;
//   - preço digitado com milhar e vírgula sai como "1234,56"; inteiro sai sem vírgula; nunca com ponto;
//   - sem nome, sem unidade ou com preço negativo: não envia e mostra o motivo;
//   - com um local a janela não pergunta; com dois ou mais exige ao menos um;
//   - "Gerenciar estoque" vem marcado e desmarcar vai no pedido;
//   - erro de campo do servidor aparece no campo e a janela continua aberta.
//
// Cobre UC-PIDX-14 (resources/js/Pages/Produto/Index.casos.md). O lado do servidor — o que é gravado
// — é o tests/Feature/Produto/ProdutoListaCadastroRapidoTest.php.
// ⚠️ Nenhuma lane de CI roda este arquivo ainda (cada lane Vitest lista os próprios specs).
// Comando: npx vitest run tests/js/produto-cadastro-rapido.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import CadastroRapidoProduto, {
  errosDoForm,
  formInicial,
  pedidoDoForm,
  type FormCadastroRapido,
  type PedidoCadastroRapido,
} from '@/Pages/Produto/_components/lista/CadastroRapido';

const UNIDADES = [{ id: '3', nome: 'Unidade (Un)' }, { id: '4', nome: 'Metro (m)' }];
const CATEGORIAS = [{ id: '9', nome: 'Camisetas' }];
const UM_LOCAL = [{ id: '1', nome: 'Matriz' }];
const DOIS_LOCAIS = [{ id: '1', nome: 'Matriz' }, { id: '2', nome: 'Filial' }];

const form = (parte: Partial<FormCadastroRapido> = {}): FormCadastroRapido => ({ ...formInicial(UNIDADES), nome: 'Camiseta básica', ...parte });

type Enviar = (p: PedidoCadastroRapido) => Promise<{ ok: true; msg: string } | { ok: false; msg: string; erros: Record<string, string> }>;

function abrir(locais = UM_LOCAL, enviar: Enviar = vi.fn(async () => ({ ok: true as const, msg: 'Produto criado pelo cadastro rápido.' }))) {
  const aoFechar = vi.fn();
  const aoCriar = vi.fn();
  render(<CadastroRapidoProduto aberto aoFechar={aoFechar} unidades={UNIDADES} categorias={CATEGORIAS} locais={locais} aoCriar={aoCriar} enviar={enviar} />);
  return { aoFechar, aoCriar, enviar: enviar as ReturnType<typeof vi.fn> };
}

/** Digita num campo de preço como a pessoa digita: foca, escreve, sai do campo. */
function digitarPreco(rotulo: RegExp, texto: string) {
  const campo = screen.getByLabelText(rotulo);
  fireEvent.focus(campo);
  fireEvent.change(campo, { target: { value: texto } });
  fireEvent.blur(campo);
}
const digitarNome = (texto: string) => fireEvent.change(screen.getByLabelText(/Nome do produto/), { target: { value: texto } });
const salvar = () => fireEvent.click(screen.getByRole('button', { name: 'Salvar' }));

afterEach(() => cleanup());

describe('UC-PIDX-14 · o pedido que vai ao servidor', () => {
  it('tem só os campos da janela — nenhum preço calculado na tela', () => {
    expect(Object.keys(pedidoDoForm(form())).sort()).toEqual(
      ['category_id', 'compra', 'enable_stock', 'name', 'product_locations', 'sku', 'unit_id', 'venda'],
    );
  });

  it('preço vai com vírgula decimal e sem milhar; inteiro vai sem vírgula; nunca com ponto', () => {
    const p = pedidoDoForm(form({ compra: 1234.56, venda: 2000 }));
    expect(p.compra).toBe('1234,56');
    expect(p.venda).toBe('2000');
    for (const n of [0, 0.1 + 0.2, 12.5, 80, 1234567.89]) {
      expect(pedidoDoForm(form({ compra: n })).compra).not.toContain('.');
    }
    expect(pedidoDoForm(form({ compra: 0.1 + 0.2 })).compra).toBe('0,3');
  });

  it('nome e SKU vão sem espaço nas pontas; sem categoria vai null; ids viram número', () => {
    const p = pedidoDoForm(form({ nome: '  Camiseta  ', sku: ' CAM-1 ', categoria: '9', locais: ['2'] }));
    expect([p.name, p.sku, p.unit_id, p.category_id, p.product_locations]).toEqual(['Camiseta', 'CAM-1', 3, 9, [2]]);
    expect(pedidoDoForm(form()).category_id).toBeNull();
  });
});

describe('UC-PIDX-14 · o que impede de salvar', () => {
  it('sem nome, sem unidade ou com preço negativo', () => {
    expect(errosDoForm(form({ nome: '   ' }), UM_LOCAL)).toHaveProperty('name');
    expect(errosDoForm(form({ unidade: '' }), UM_LOCAL)).toHaveProperty('unit_id');
    expect(errosDoForm(form({ compra: -1 }), UM_LOCAL)).toHaveProperty('compra');
    expect(errosDoForm(form({ venda: -0.01 }), UM_LOCAL)).toHaveProperty('venda');
  });

  it('preço zero é aceito (o formulário Blade também aceita)', () => {
    expect(errosDoForm(form({ compra: 0, venda: 0 }), UM_LOCAL)).toEqual({});
  });

  it('com um local não pede local; com dois ou mais exige ao menos um', () => {
    expect(errosDoForm(form(), UM_LOCAL)).toEqual({});
    expect(errosDoForm(form(), DOIS_LOCAIS)).toHaveProperty('product_locations');
    expect(errosDoForm(form({ locais: ['2'] }), DOIS_LOCAIS)).toEqual({});
  });
});

describe('UC-PIDX-14 · a janela', () => {
  it('preenchida e salva: manda o pedido certo, avisa e fecha', async () => {
    const { enviar, aoCriar, aoFechar } = abrir();
    digitarNome('Camiseta básica');
    digitarPreco(/Preço de compra/, '1.234,56');
    digitarPreco(/Preço de venda/, '2000');
    salvar();

    await waitFor(() => expect(enviar).toHaveBeenCalledTimes(1));
    expect(enviar.mock.calls[0]?.[0]).toEqual({
      name: 'Camiseta básica', sku: '', unit_id: 3, category_id: null,
      compra: '1234,56', venda: '2000', enable_stock: true, product_locations: [],
    });
    await waitFor(() => expect(aoCriar).toHaveBeenCalledWith('Produto criado pelo cadastro rápido.'));
    expect(aoFechar).toHaveBeenCalledTimes(1);
  });

  it('sem nome: não envia e diz o motivo', async () => {
    const { enviar } = abrir();
    salvar();
    expect(await screen.findByText('Informe o nome do produto.')).toBeTruthy();
    expect(enviar).not.toHaveBeenCalled();
  });

  it('"Gerenciar estoque" vem marcado; desmarcado, vai no pedido', async () => {
    const { enviar } = abrir();
    const caixa = screen.getByRole('checkbox', { name: 'Gerenciar estoque' });
    expect(caixa.getAttribute('aria-checked')).toBe('true');
    fireEvent.click(caixa);
    digitarNome('Serviço de instalação');
    salvar();
    await waitFor(() => expect(enviar).toHaveBeenCalledTimes(1));
    expect((enviar.mock.calls[0]?.[0] as PedidoCadastroRapido).enable_stock).toBe(false);
  });

  it('com um local a janela não mostra o campo de locais', () => {
    abrir(UM_LOCAL);
    expect(screen.queryByText('Locais do negócio *')).toBeNull();
  });

  it('com dois locais: sem escolher não envia; escolhendo, o local vai no pedido', async () => {
    const { enviar } = abrir(DOIS_LOCAIS);
    digitarNome('Camiseta básica');
    salvar();
    expect(await screen.findByText('Escolha ao menos um local do negócio.')).toBeTruthy();
    expect(enviar).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('checkbox', { name: 'Filial' }));
    salvar();
    await waitFor(() => expect(enviar).toHaveBeenCalledTimes(1));
    expect((enviar.mock.calls[0]?.[0] as PedidoCadastroRapido).product_locations).toEqual([2]);
  });

  it('erro de campo do servidor aparece no campo e a janela continua aberta', async () => {
    const recusa: Enviar = vi.fn(async () => ({ ok: false as const, msg: 'Confira os campos marcados.', erros: { sku: 'Este SKU já está em uso.' } }));
    const { aoFechar, aoCriar } = abrir(UM_LOCAL, recusa);
    digitarNome('Camiseta básica');
    salvar();
    expect(await screen.findByText('Este SKU já está em uso.')).toBeTruthy();
    expect(screen.getByText('Confira os campos marcados.')).toBeTruthy();
    expect(aoFechar).not.toHaveBeenCalled();
    expect(aoCriar).not.toHaveBeenCalled();
  });
});
