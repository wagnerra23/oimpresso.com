/**
 * Aba Tributação — a PONTE entre o domínio fiscal e a tela.
 *
 * @covers-us UC-V373 UC-V374
 *
 * Por que RENDER: `item-fiscal-dominio.test.ts` (18) e `item-fiscal-calculo.test.ts`
 * (9) provam validação e cálculo, e passariam inteiros com a aba mostrando uma
 * tabela estática de 3 colunas — que era exatamente o estado antes deste PR.
 * Domínio certo, tela muda; o mesmo vão do grid de colunas (UC-V367) e do resumo
 * de parcelas (UC-V369). Só o render prova que a aba EXPÕE o que o domínio sabe.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { post: vi.fn(), get: vi.fn() },
  // o `SubNav` das abas lê `usePage().url` — sem isto o drawer inteiro morre no
  // primeiro render e os 8 testes falham por motivo que não é o que se mede aqui
  usePage: () => ({ url: '/sells/create-v3', props: {} }),
  Link: ({ children }: { children: React.ReactNode }) => children,
}));

import ItemDetalhe, { type LinhaDoItem } from '@/Pages/Sells/_components/v3/ItemDetalhe';

/** A linha da cena do controller: 12,50 m² × 68,90 = 861,25 de base. */
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

function abrir() {
  return render(
    <ItemDetalhe linha={linha} indice={0} total={2} onFechar={() => {}} abaInicial="tributacao" />,
  );
}

describe('UC-V373 · a aba Tributação tem os campos da âncora', () => {
  it('classificação fiscal: os 8 campos, não 5', () => {
    abrir();
    for (const rotulo of [
      'Grupo do produto',
      'NCM',
      'CEST',
      'CFOP',
      'Origem da mercadoria',
      'Cód. de fábrica',
      'Cód. EAN / GTIN',
      'cBenef',
    ]) {
      expect(screen.getByText(rotulo)).toBeTruthy();
    }
  });

  it('o acordeão tem a coluna VALOR e o total do item — as duas que faltavam', () => {
    abrir();
    expect(screen.getByText('Valor')).toBeTruthy();
    expect(screen.getByText('Total de impostos do item')).toBeTruthy();
  });

  it('lista os 9 impostos, incluindo os da reforma', () => {
    abrir();
    for (const imp of ['ICMS', 'IPI', 'PIS', 'COFINS', 'ISSQN', 'II', 'IS', 'IBS', 'CBS']) {
      expect(screen.getAllByText(imp).length).toBeGreaterThan(0);
    }
  });

  it('as seções Importação e Descrição na NF-e existem', () => {
    abrir();
    expect(screen.getByText('Importação')).toBeTruthy();
    expect(screen.getByText('Descrição na NF-e')).toBeTruthy();
    expect(screen.getByLabelText('Descrição do produto como sai na NF-e')).toBeTruthy();
  });

  it('o ICMS anuncia que tem DIFAL antes de alguém abrir', () => {
    abrir();
    expect(screen.getByText('tem DIFAL')).toBeTruthy();
  });
});

describe('UC-V374 · abrir um imposto revela os campos DELE', () => {
  it('fechado, os campos do ICMS não estão na tela', () => {
    abrir();
    expect(screen.queryByText('Redução de base (%)')).toBeNull();
    expect(screen.queryByText('MVA / margem ST (%)')).toBeNull();
  });

  it('aberto, o ICMS traz redução, MVA, ST e o bloco DIFAL', () => {
    abrir();
    fireEvent.click(screen.getByRole('button', { expanded: false, name: /ICMS/ }));

    for (const rotulo of ['Redução de base (%)', 'MVA / margem ST (%)', 'Base ST', 'ICMS ST']) {
      expect(screen.getByText(rotulo)).toBeTruthy();
    }
    expect(screen.getByText('DIFAL — diferencial de alíquota')).toBeTruthy();
  });

  it('cada imposto oferece o CST DELE — o ICMS não empresta o seu aos outros', () => {
    abrir();
    const linhaIbs = screen.getAllByRole('button').find((b) => within(b).queryByText('IBS'));
    expect(linhaIbs).toBeTruthy();
    fireEvent.click(linhaIbs!);

    // campos exclusivos da reforma, que a tabela estática não tinha como mostrar
    expect(screen.getByText('Classificação tributária (cClassTrib)')).toBeTruthy();
    expect(screen.getByText('Crédito presumido')).toBeTruthy();
    expect(screen.getByText('CST / situação — IBS')).toBeTruthy();
  });
});
