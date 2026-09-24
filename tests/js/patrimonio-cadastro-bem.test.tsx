// UC-BENS-05 · Patrimonio/Bens — o drawer "Adicionar recurso" posta valor e quantidade sem
// ambiguidade.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// O cadastro grava VALOR (`unit_price`) e QUANTIDADE (`quantity`) — REGRA MESTRE Tier 0
// (`memory/proibicoes.md`): dupla confirmação por dois caminhos independentes.
//   • Caminho 1 (ESTE arquivo): o que o front MONTA — as strings exatas do POST.
//   • Caminho 2 (`Modules/AssetManagement/Tests/Feature/BensContratoTest.php`, UC-BENS-05):
//     posta AS MESMAS strings no `store()` real e lê o que o banco gravou.
// Se um dos dois mudar sozinho, o par diverge e um deles cai.
//
// ── A ARMADILHA QUE ELE DEFENDE ──────────────────────────────────────────────
// `Util::num_uf` é pt-BR: vírgula decimal, ponto milhar. Um front que mandasse `1234.56`
// dependeria da tolerância "1 ponto + ≤2 dígitos"; um que mandasse `204.99605` (float cru) foi
// o incidente ROTA LIVRE de 2026-06-05 — lido como milhar, valor ×100k. Por isso o contrato
// aqui é: vírgula decimal, SEM separador de milhar, arredondado nas casas da coluna.
//
// @see resources/js/Pages/Patrimonio/_shared/cadastroBem.ts
// @see resources/js/Pages/Patrimonio/Bens.casos.md (UC-BENS-05)

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';
import {
  montarPayloadCadastro,
  paraNumUf,
  paraFormatoDoNegocio,
  validarCadastroBem,
  type FormCadastroBem,
} from '@/Pages/Patrimonio/_shared/cadastroBem';

const post = vi.fn();
vi.mock('@inertiajs/react', () => ({
  router: { post: (...a: unknown[]) => post(...a) },
}));

import CadastroBemDrawer from '@/Pages/Patrimonio/_shared/CadastroBemDrawer';

afterEach(() => {
  cleanup();
  post.mockReset();
});

const BASE: FormCadastroBem = {
  nome: 'Plotter de corte',
  categoriaId: '7',
  localId: '3',
  modelo: 'D60',
  serie: 'SN-1',
  compraEm: '2026-09-10',
  tipoCompra: 'owned',
  valorUnitario: 1234.56,
  quantidade: 2,
  depreciacao: null,
  alocavel: false,
  garantiaMeses: '',
  garantiaInicio: '2026-09-10',
  garantiaNota: '',
  descricao: '',
  imagem: null,
};

describe('UC-BENS-05 · serialização de valor e quantidade pro num_uf', () => {
  it('UC-BENS-05: vírgula decimal, sem milhar, arredondado nas casas da coluna', () => {
    expect(paraNumUf(1234.56, 2)).toBe('1234,56');
    expect(paraNumUf(1234567.8, 2)).toBe('1234567,8');
    expect(paraNumUf(2, 4)).toBe('2');
    expect(paraNumUf(1.5, 4)).toBe('1,5');
    // O float cru do incidente ROTA LIVRE: arredonda, nunca vai com 5 casas.
    expect(paraNumUf(204.99605, 2)).toBe('205');
    expect(paraNumUf(0.1 + 0.2, 2)).toBe('0,3');
    // Quantidade usa as MESMAS 2 casas que a tela mostra — 1,4999 digitado aparece "1,50" e
    // tem de gravar 1,5, não 1,4999.
    expect(montarPayloadCadastro({ ...BASE, quantidade: 1.4999 }, 'd/m/Y').quantity).toBe('1,5');
  });

  it('UC-BENS-05: data ISO vira o formato do negócio que o uf_date espera', () => {
    expect(paraFormatoDoNegocio('2026-09-10', 'd/m/Y')).toBe('10/09/2026');
    expect(paraFormatoDoNegocio('2026-09-10', 'm/d/Y')).toBe('09/10/2026');
    expect(paraFormatoDoNegocio('2026-09-10', 'Y-m-d')).toBe('2026-09-10');
  });

  it('UC-BENS-05: o payload do cadastro — as strings que o Pest posta de volta no store()', () => {
    expect(
      montarPayloadCadastro(
        { ...BASE, alocavel: true, depreciacao: 10, garantiaMeses: '12', garantiaNota: 'Contrato RC-1' },
        'd/m/Y',
      ),
    ).toEqual({
      asset_code: '',
      name: 'Plotter de corte',
      category_id: '7',
      location_id: '3',
      model: 'D60',
      serial_no: 'SN-1',
      purchase_date: '10/09/2026',
      purchase_type: 'owned',
      unit_price: '1234,56',
      quantity: '2',
      depreciation: '10',
      description: '',
      is_allocatable: '1',
      start_dates: ['10/09/2026'],
      months: ['12'],
      additional_cost: ['0'],
      additional_note: ['Contrato RC-1'],
    });
  });

  it('UC-BENS-05: sem garantia, não manda os arrays de garantia (e não marca atribuível)', () => {
    const p = montarPayloadCadastro(BASE, 'd/m/Y');
    expect(p).not.toHaveProperty('start_dates');
    expect(p).not.toHaveProperty('months');
    expect(p).not.toHaveProperty('is_allocatable');
    expect(p.depreciation).toBe('');
  });

  it('UC-BENS-05: bloqueia envio sem valor, com quantidade < 1 ou garantia sem início', () => {
    const e = validarCadastroBem({ ...BASE, valorUnitario: 0, quantidade: 0, garantiaMeses: '6', garantiaInicio: '' });
    expect(Object.keys(e).sort()).toEqual(['garantiaInicio', 'quantidade', 'valorUnitario']);
    expect(validarCadastroBem(BASE)).toEqual({});
  });
});

describe('UC-BENS-05 · o drawer envia o que o serializador monta', () => {
  it('UC-BENS-05: form vazio não posta; o erro aparece no campo', () => {
    render(
      <CadastroBemDrawer aberto onClose={() => {}} locais={{ 3: 'Matriz' }} categorias={{ 7: 'Máquinas' }}
        tiposCompra={{ owned: 'Próprio' }} formatoData="d/m/Y" />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar bem' }));

    expect(post).not.toHaveBeenCalled();
    expect(screen.getByText('O nome do recurso é obrigatório.')).toBeTruthy();
    expect(screen.getByText('Valor unitário precisa ser maior que zero.')).toBeTruthy();
  });

  it('UC-BENS-05: sem categoria escolhida (mais de uma opção), não posta', () => {
    render(
      <CadastroBemDrawer aberto onClose={() => {}} locais={{ 3: 'Matriz' }} categorias={{ 7: 'Máquinas', 8: 'Veículos' }}
        tiposCompra={{ owned: 'Próprio' }} formatoData="d/m/Y" />,
    );
    fireEvent.change(screen.getByLabelText('Nome do recurso'), { target: { value: 'Plotter de corte' } });
    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar bem' }));
    expect(post).not.toHaveBeenCalled();
    expect(screen.getByText('Escolha a categoria.')).toBeTruthy();
  });

  it('UC-BENS-05: empresa sem categoria de ativo — o drawer diz e aponta pra onde cadastrar (e não posta)', () => {
    render(
      <CadastroBemDrawer aberto onClose={() => {}} locais={{ 3: 'Matriz' }} categorias={{}}
        tiposCompra={{ owned: 'Próprio' }} formatoData="d/m/Y" />,
    );
    const aviso = screen.getByTestId('sem-categoria');
    expect(aviso.textContent).toContain('Nenhuma categoria de ativo cadastrada.');
    expect(aviso.querySelector('a')?.getAttribute('href')).toBe('/taxonomies?type=asset');
    fireEvent.change(screen.getByLabelText('Nome do recurso'), { target: { value: 'Plotter de corte' } });
    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar bem' }));
    expect(post).not.toHaveBeenCalled();
  });

  it('UC-BENS-05: com categoria disponível, o aviso de "sem categoria" não aparece', () => {
    render(
      <CadastroBemDrawer aberto onClose={() => {}} locais={{ 3: 'Matriz' }} categorias={{ 7: 'Máquinas' }}
        tiposCompra={{ owned: 'Próprio' }} formatoData="d/m/Y" />,
    );
    expect(screen.queryByTestId('sem-categoria')).toBeNull();
  });

  it('UC-BENS-05: com o form válido, posta em /asset/assets com FormData e o valor digitado em pt-BR vira "1234,56"', () => {
    render(
      <CadastroBemDrawer aberto onClose={() => {}} locais={{ 3: 'Matriz' }} categorias={{ 7: 'Máquinas' }}
        tiposCompra={{ owned: 'Próprio' }} formatoData="d/m/Y" />,
    );

    fireEvent.change(screen.getByLabelText('Nome do recurso'), { target: { value: 'Plotter de corte' } });
    fireEvent.change(screen.getByLabelText('Data da compra'), { target: { value: '2026-09-10' } });
    // Digitado como a Larissa digita: pt-BR, COM milhar. É o componente do DS que canoniza.
    const valor = screen.getByLabelText('Valor unitário (R$)');
    fireEvent.focus(valor);
    fireEvent.change(valor, { target: { value: '1.234,56' } });
    fireEvent.blur(valor);
    const qtd = screen.getByLabelText('Quantidade');
    fireEvent.focus(qtd);
    fireEvent.change(qtd, { target: { value: '2' } });
    fireEvent.blur(qtd);

    fireEvent.click(screen.getByRole('button', { name: 'Cadastrar bem' }));

    expect(post).toHaveBeenCalledTimes(1);
    const [url, corpo, opcoes] = post.mock.calls[0] as [string, Record<string, unknown>, Record<string, unknown>];
    expect(url).toBe('/asset/assets');
    expect(opcoes.forceFormData).toBe(true);
    expect(corpo).toMatchObject({
      name: 'Plotter de corte',
      category_id: '7',
      location_id: '3',
      purchase_date: '10/09/2026',
      unit_price: '1234,56',
      quantity: '2',
    });
  });
});
