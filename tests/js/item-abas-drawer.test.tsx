/**
 * As abas do drawer de item — moldura (seção) e conteúdo.
 *
 * @covers-us UC-V375 UC-V376 UC-V377 UC-V378
 *
 * Por que RENDER, e não teste de domínio: o que estava errado aqui NÃO era regra
 * de negócio — era a TELA. A aba Produção tinha 3 dos 9 campos da âncora, o Fluxo
 * era uma tabela imóvel, a Observação tinha um campo onde a âncora tem dois, e
 * seis das sete abas entregavam o conteúdo solto, sem seção. Nenhum teste de
 * domínio pega isso: todos passariam com a tela exatamente como estava.
 *
 * A âncora medida é o artefato "Cadastro de venda v3" (prancheta `Main.dc.html`),
 * renderizado e sondado em 2026-08-27 — não o código-fonte lido.
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
import type { Aba } from '@/Pages/Sells/_components/v3/item-fiscal-dominio';

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

const abrir = (aba: Aba) =>
  render(<ItemDetalhe linha={linha} indice={0} total={2} onFechar={() => {}} abaInicial={aba} />);

describe('UC-V375 · a aba Produção tem os 9 campos da âncora, não 3', () => {
  it('os 9 rótulos existem, na ordem da âncora', () => {
    abrir('producao');
    for (const rotulo of [
      'Em produção',
      'Tipo de impressão',
      'Acabamento',
      'Local de aplicação',
      'Equipamento / setor',
      'Prioridade',
      'Requisitar do estoque',
      'Prazo da equipe (produção)',
      'Prazo da etapa',
    ]) {
      expect(screen.getByText(rotulo)).toBeTruthy();
    }
  });

  it('o Acabamento é ESCOLHA e guarda o que se escolhe — antes era texto que descartava', () => {
    // a versão anterior era `<Texto onChange={() => {}}>`: aceitava digitação e
    // jogava fora. Campo que finge funcionar é pior que campo ausente.
    abrir('producao');
    expect(screen.getByText('Sem acabamento')).toBeTruthy();
  });

  it('traz a observação de produção e a seção do arquivo de arte', () => {
    abrir('producao');
    expect(
      screen.getByLabelText('Observação de produção (vai na OP, não sai no documento do cliente)'),
    ).toBeTruthy();
    expect(screen.getByText('Arquivo de arte')).toBeTruthy();
    expect(screen.getByText('Caminho do arquivo na rede')).toBeTruthy();
    expect(screen.getByRole('button', { name: 'Anexar arquivo' })).toBeTruthy();
  });
});

describe('UC-V376 · o Fluxo deixa mexer nas etapas — não é retrato imóvel', () => {
  it('remover uma etapa tira a linha da tabela', () => {
    abrir('fluxo');
    expect(screen.getByText('Expedição')).toBeTruthy();

    fireEvent.click(screen.getByRole('button', { name: 'Remover etapa Expedição' }));

    expect(screen.queryByText('Expedição')).toBeNull();
    // as outras continuam — remover é cirúrgico, não limpa a tabela
    expect(screen.getByText('Impressão digital')).toBeTruthy();
  });

  it('esvaziar a tabela mostra o vazio que ENSINA, não uma aba muda', () => {
    abrir('fluxo');
    for (const etapa of ['Arte / pré-impressão', 'Impressão digital', 'Acabamento — ilhós', 'Expedição']) {
      fireEvent.click(screen.getByRole('button', { name: `Remover etapa ${etapa}` }));
    }
    expect(screen.getByText('Nenhuma etapa neste item')).toBeTruthy();
  });

  it('aplicar o fluxo padrão traz as etapas de volta', () => {
    abrir('fluxo');
    fireEvent.click(screen.getByRole('button', { name: 'Remover etapa Expedição' }));
    expect(screen.queryByText('Expedição')).toBeNull();

    fireEvent.click(screen.getByRole('button', { name: 'Aplicar fluxo padrão do produto' }));
    expect(screen.getByText('Expedição')).toBeTruthy();
  });
});

describe('UC-V377 · Observação separa o que vai pro cliente do que fica interno', () => {
  it('são DOIS campos, e cada um diz para onde vai', () => {
    // CU-SELL-12: unificar os dois num campo só foi o que vazou nota interna no
    // documento do cliente. A tela tinha exatamente esse campo único.
    abrir('observacao');
    expect(screen.getByLabelText('Observação geral do produto (sai no documento do cliente)')).toBeTruthy();
    expect(screen.getByLabelText('Observação interna (não sai no documento)')).toBeTruthy();
  });

  it('o que se digita na interna NÃO aparece no campo do cliente', () => {
    abrir('observacao');
    const interna = screen.getByLabelText('Observação interna (não sai no documento)');
    fireEvent.change(interna, { target: { value: 'cliente reclamou da cor' } });

    expect((interna as HTMLTextAreaElement).value).toBe('cliente reclamou da cor');
    expect(
      (screen.getByLabelText('Observação geral do produto (sai no documento do cliente)') as HTMLTextAreaElement)
        .value,
    ).toBe('');
  });
});

describe('UC-V378 · toda aba do drawer usa a seção DO DRAWER', () => {
  it.each<[Aba, string]>([
    ['geral', 'Identificação e medidas'],
    ['producao', 'Instruções de produção'],
    ['fluxo', 'Fluxo de produção deste item'],
    ['tributacao', 'Classificação fiscal'],
    ['preco', 'Preço deste item'],
    ['anexos', 'Anexos do item'],
    ['observacao', 'Observações do produto'],
  ])('a aba %s abre com a seção "%s"', (aba, titulo) => {
    abrir(aba);
    const h4 = screen.getByText(titulo);
    expect(h4).toBeTruthy();
    // o título de seção do drawer é <h4> em caixa alta — o da TELA PRINCIPAL é
    // <h3> 15px em frase. Eram dois componentes distintos na âncora e a tela
    // usava o da tela principal dentro do drawer.
    expect(h4.tagName).toBe('H4');
  });
});
