/**
 * Abas Preço e Anexos do drawer de item — a PONTE entre o domínio e a tela.
 *
 * @covers-us UC-V377 UC-V378
 *
 * Por que RENDER, e não só domínio: `item-alcada.test.ts` prova a aritmética da
 * alçada (8 testes, dois caminhos) e passaria inteiro com a aba Preço mostrando
 * os 4 campos read-only que ela mostrava antes — nenhum deles falando de piso.
 * O cálculo certo com a tela muda é exatamente o vão que os testes de domínio
 * não enxergam; foi o mesmo vão do grid de colunas (UC-V367) e da tributação
 * (UC-V373).
 *
 * A âncora medida é o artefato "Cadastro de venda v3" (prancheta `Main.dc.html`),
 * renderizado e sondado — não o código-fonte lido.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
  // o `SubNav` das abas lê `usePage().url` — sem isto o drawer morre no 1º render
  usePage: () => ({ url: '/sells/create-v3', props: {} }),
  Link: ({ children }: { children: React.ReactNode }) => children,
}));

import ItemDetalhe, { type LinhaDoItem } from '@/Pages/Sells/_components/v3/ItemDetalhe';

/** A linha da cena do controller: Lona 440g, tabela 68,90 no catálogo. */
const linha: LinhaDoItem = {
  k: 1,
  sku: 'LON-440-BR',
  nome: 'Lona 440g branca fosca',
  un: 'm²',
  medidas: '5× 0,50x5,00m',
  qtd: '12,50',
  preco: '68,90',
  desc: '0',
  acr: '0',
};

const abrirPreco = (preco = '68,90') =>
  render(
    <ItemDetalhe
      linha={{ ...linha, preco }}
      indice={0}
      total={2}
      onFechar={() => {}}
      abaInicial="preco"
      precoDeTabela={68.9}
    />,
  );

describe('UC-V377 · a aba Preço mostra a ALÇADA, não uma cópia da aba Geral', () => {
  it('os 3 campos da âncora existem — e os 4 que se repetiam com a Geral, não', () => {
    abrirPreco();
    for (const rotulo of ['Preço de tabela', 'Menor preço permitido', 'Preço nesta venda']) {
      expect(screen.getByText(rotulo)).toBeTruthy();
    }
    // "Base de cálculo" e "Acréscimo (%)" viviam AQUI e na aba Geral (§Valores da
    // linha) ao mesmo tempo; a aba Preço existe pra falar de alçada, não pra repetir.
    expect(screen.queryByText('Base de cálculo')).toBeNull();
    expect(screen.queryByText('Acréscimo (%)')).toBeNull();
  });

  it('preço na tabela → LIBERADO, com a folga em reais e o desconto zerado', () => {
    abrirPreco('68,90');
    expect(screen.getByText('preço liberado')).toBeTruthy();
    // piso = 68,90 × 0,85 = 58,565 → folga 10,34 (conferido à mão em item-alcada)
    expect(screen.getByText(/R\$ 10,34 acima do menor preço permitido/)).toBeTruthy();
    expect(screen.getByText('0,0%')).toBeTruthy();
  });

  it('abaixo do piso → PRECISA LIBERAÇÃO, dizendo quanto falta e o que fazer', () => {
    abrirPreco('50,00');
    expect(screen.getByText('precisa liberação')).toBeTruthy();
    // 58,565 − 50,00 = 8,565 → 8,57
    expect(screen.getByText(/Faltam R\$ 8,57/)).toBeTruthy();
    expect(screen.getByText(/Chame o supervisor antes de fechar/)).toBeTruthy();
  });

  it('digitar um preço menor VIRA a faixa — o mecanismo é vivo, não um selo fixo', () => {
    abrirPreco('68,90');
    expect(screen.getByText('preço liberado')).toBeTruthy();

    fireEvent.change(screen.getByLabelText('Preço nesta venda'), { target: { value: '40,00' } });

    expect(screen.getByText('precisa liberação')).toBeTruthy();
    expect(screen.queryByText('preço liberado')).toBeNull();
  });

  it('diz, na própria aba, que custo/markup/margem não aparecem pro vendedor', () => {
    abrirPreco();
    expect(screen.getByText(/não aparecem para o vendedor/)).toBeTruthy();
  });
});

describe('UC-V378 · Anexos ensina o que fazer com a área tracejada', () => {
  const abrirAnexos = () =>
    render(
      <ItemDetalhe linha={linha} indice={0} total={2} onFechar={() => {}} abaInicial="anexos" />,
    );

  it('traz a instrução de arrastar e o botão da âncora', () => {
    abrirAnexos();
    expect(screen.getByText(/Arraste o arquivo de arte/)).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Escolher arquivo' })).toBeTruthy();
  });

  it('o botão está DESABILITADO e diz por quê — o preview não grava arquivo', () => {
    // afordância que promete o que não cumpre é pior que ausência: o botão existe
    // pela fidelidade do layout, e o `disabled` + `title` impedem a promessa falsa.
    abrirAnexos();
    const botao = screen.getByRole('button', { name: 'Escolher arquivo' });
    expect((botao as HTMLButtonElement).disabled).toBe(true);
    expect(botao.getAttribute('title')).toMatch(/não grava/);
  });
});
