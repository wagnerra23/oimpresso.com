// UC-BENS-10 · Drawer de detalhe do bem — a aba Alocações lista TODAS as devoluções.
//
// O protótipo (`patrimonio-page.jsx` :723-729) modela uma revogação por alocação; o nosso
// modelo é 1 : N (devolução parcial grava vários `revoke` com o mesmo `parent_id` —
// `_saida-16.md`). Este teste é o caminho de TELA do contrato: uma alocação com duas
// devoluções parciais mostra as duas, com código, quantidade, autor e motivo de cada uma.
// O caminho de DADO (escopo por business na devolução) é o Pest UC-BENS-10.
//
// Controle positivo: a alocação sem devolução também está no DOM, com o selo "Ativa" —
// um `getAllByTestId('devolucao')` com 2 itens não passaria se a lista viesse vazia.
//
// @see resources/js/Pages/Patrimonio/Bens.casos.md (UC-BENS-10)

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent, within } from '@testing-library/react';
import { vi } from 'vitest';

// O `<SubNav>` do DS chama `usePage()` pra marcar aba por URL; aqui as abas são controladas.
vi.mock('@inertiajs/react', () => ({
  usePage: () => ({ url: '/asset/assets?bem=7', props: {} }),
  Link: ({ href, children, ...rest }: any) => <a href={href} {...rest}>{children}</a>,
}));
import DetalheBemDrawer, { type BemDetalhe } from '@/Pages/Patrimonio/_shared/DetalheBemDrawer';

afterEach(() => cleanup());

const detalhe: BemDetalhe = {
  id: 7,
  asset_code: 'PAT-0007',
  nome: 'Notebook Dell',
  modelo: 'Latitude 5440',
  serie: 'SN-123',
  categoria: 'Informática',
  local: 'Matriz',
  tipo_compra: 'owned',
  compra_em: '01/02/2026',
  alocavel: true,
  quantidade: 5,
  valor_unitario: 4200,
  descricao: null,
  alocacoes: [
    {
      id: 1,
      ref_no: 'ALO-0001',
      para: 'Ana Souza',
      por: 'Admin',
      quantidade: 3,
      em: '02/02/2026 09:00',
      ate: null,
      motivo: null,
      devolvido: 2,
      devolucoes: [
        { id: 11, ref_no: 'REV-0001', quantidade: 1, em: '10/02/2026 10:00', por: 'Admin', motivo: 'Tela quebrada' },
        { id: 12, ref_no: 'REV-0002', quantidade: 1, em: '15/02/2026 11:00', por: 'Gerente', motivo: 'Fim do projeto' },
      ],
    },
    {
      id: 2,
      ref_no: 'ALO-0002',
      para: 'Bruno Lima',
      por: 'Admin',
      quantidade: 1,
      em: '03/02/2026 09:00',
      ate: '03/03/2026',
      motivo: null,
      devolvido: 0,
      devolucoes: [],
    },
  ],
};

const abrirAlocacoes = () => fireEvent.click(screen.getByRole('tab', { name: /Alocações/ }));

describe('UC-BENS-10 · drawer de detalhe do bem', () => {
  it('UC-BENS-10: a aba Alocações lista as N devoluções de cada alocação (1 : N, não 1 : 1)', () => {
    render(<DetalheBemDrawer aberto detalhe={detalhe} tiposCompra={{ owned: 'Próprio' }} onClose={() => {}} />);
    abrirAlocacoes();

    const alocacoes = screen.getAllByTestId('alocacao-do-bem');
    expect(alocacoes).toHaveLength(2);

    const primeira = within(alocacoes[0]);
    const devolucoes = primeira.getAllByTestId('devolucao');
    expect(devolucoes).toHaveLength(2);
    expect(devolucoes[0].textContent).toContain('REV-0001');
    expect(devolucoes[0].textContent).toContain('Tela quebrada');
    expect(devolucoes[1].textContent).toContain('REV-0002');
    expect(devolucoes[1].textContent).toContain('Gerente');
    expect(primeira.getByText('Devolvida em parte')).toBeTruthy();

    // Controle positivo: a alocação sem devolução chegou, com o selo certo.
    const segunda = within(alocacoes[1]);
    expect(segunda.getByText('Bruno Lima')).toBeTruthy();
    expect(segunda.getByText('Ativa')).toBeTruthy();
    expect(segunda.queryAllByTestId('devolucao')).toHaveLength(0);
  });

  it('UC-BENS-10: é só leitura — o drawer não oferece botão de excluir devolução nem de revogar', () => {
    render(<DetalheBemDrawer aberto detalhe={detalhe} tiposCompra={{}} onClose={() => {}} />);
    abrirAlocacoes();

    const nomes = screen.queryAllByRole('button').map((b) => b.textContent ?? '');
    expect(nomes.some((n) => /excluir|revogar|alocar/i.test(n))).toBe(false);
    // Controle positivo: a aba renderizou de fato.
    expect(screen.getAllByTestId('devolucao')).toHaveLength(2);
  });

  it('UC-BENS-10: bem de outra empresa (detalhe null) mostra "não encontrado", sem dado', () => {
    render(<DetalheBemDrawer aberto detalhe={null} tiposCompra={{}} onClose={() => {}} />);
    expect(screen.getByText('Bem não encontrado')).toBeTruthy();
    expect(screen.queryByRole('tab')).toBeNull();
  });
});

// UC-BENS-12 · o rodapé do drawer oferece "Enviar pra manutenção" — só quando a Page passa o
// handler (é ela que sabe a permissão), e o clique entrega o id DESTE bem.
describe('UC-BENS-12 · rodapé do drawer de detalhe', () => {
  it('UC-BENS-12: com o handler, o rodapé tem "Enviar pra manutenção" e o clique entrega o id do bem', () => {
    const enviar = vi.fn();
    render(<DetalheBemDrawer aberto detalhe={detalhe} tiposCompra={{ owned: 'Próprio' }} onClose={() => {}} onEnviarManutencao={enviar} />);

    const botao = screen.getByRole('button', { name: 'Enviar pra manutenção' });
    expect(botao.className).toContain('min-h-11');
    fireEvent.click(botao);
    expect(enviar).toHaveBeenCalledWith(7);
  });

  it('UC-BENS-12: sem o handler (sem permissão), o botão não existe — e o drawer renderizou', () => {
    render(<DetalheBemDrawer aberto detalhe={detalhe} tiposCompra={{ owned: 'Próprio' }} onClose={() => {}} />);

    expect(screen.getByTestId('detalhe-bem').textContent).toContain('Notebook Dell');
    expect(screen.queryByRole('button', { name: 'Enviar pra manutenção' })).toBeNull();
  });
});
