/**
 * Paginação da lista unificada do cockpit Fiscal.
 *
 * @covers-us UC-FCKP-09
 *
 * POR QUE ESTE TESTE É DE RENDER (e não estático): o contrato é "a tela mostra uma
 * PÁGINA, e trocar o filtro não deixa o operador preso numa página que não existe
 * mais". Um assert sobre o texto do `.tsx` provaria que o `slice` foi ESCRITO —
 * presença, não comportamento (LC-11). Aqui cada caso conta as `<tr>` de fato
 * renderizadas e lê a numeração que o operador vê.
 *
 * FONTE DO CONTRATO: `fiscal-page.jsx` §FxNotasPage do protótipo Cowork, baixado do
 * vivo por DesignSync em 2026-09-03 (projeto das telas, `truncated: false`) — não do
 * espelho `prototipo-ui/cowork/`, que mediu 1 de 258 arquivos e cuja própria máquina
 * declara "qualquer comparação contra este espelho é INCONCLUSIVA". De lá vêm, e não
 * de palpite: o default 8, as opções 8/25/50, a copy `Anterior`/`Próxima`, o contador
 * `{pagina} / {paginas}` e o reset ao filtrar.
 *
 * O QUE ESTE ARQUIVO NÃO PROVA, de propósito:
 *   - Que "de N" seja o total do negócio. NÃO é, e a tela não diz que é: o universo
 *     paginado é a lista JÁ carregada, cortada em 50 por `NotasUnifiedService::LIMITE`
 *     antes de chegar ao componente. Por isso a copy é "N carregadas".
 *   - Os atalhos J/K. Eles não existem NESTA tela — a Onda 2 (#6707) os entregou no
 *     `Nfe.tsx`, e por isso o hint do protótipo foi deliberadamente omitido do rodapé.
 */
import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';

vi.mock('@inertiajs/react', () => ({
  Head: () => null,
  Link: ({ children }: { children: React.ReactNode }) => <a href="#">{children}</a>,
  Deferred: ({ children }: { children: React.ReactNode }) => children,
  router: { visit: vi.fn(), get: vi.fn(), post: vi.fn() },
}));
vi.mock('@/Layouts/AppShellV2', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}));

import Cockpit from '@/Pages/Fiscal/Cockpit';

/** 20 notas => 3 páginas de 8 (8 + 8 + 4). */
const notas = Array.from({ length: 20 }, (_, i) => ({
  id: 'nfe-' + (i + 1),
  tipo: 'NF-e' as const,
  kind: 'nfe' as const,
  num: String(9000 + i),
  serie: '1',
  when: '02/09 10:00',
  cliente: i === 19 ? 'Alvo Unico Ltda' : 'Cliente ' + (i + 1),
  doc: '12345678000199',
  uf: 'SC',
  venda: null,
  ref: null,
  keyOrCode: '3526' + String(i).padStart(40, '0'),
  status: 100,
  statusKind: 'sefaz' as const,
  rejMsg: null,
  modelo: 55,
  value: 100 + i,
  prazoCancel: null,
  prazoCce: null,
}));

const cena = {
  kpis: {
    emitidas: 20, autorizadas: 20, autorizadasPct: 100, rejeitadas: 0,
    faturamentoFiscal: 2000, dfeAguardando: 0, certificadoValidadeDias: 90,
  },
  sparklines: { emitidas: [1], autorizadas: [1], rejeitadas: [0], faturamento: [1] },
  alerts: [],
  notas,
  savedViewCounts: { todas: 20, resolver: 0, janela24: 0, processando: 0, nfse: 0, nfce: 0 },
  sefazStatus: { uf: 'SC', operacional: true, label: 'SEFAZ-SC operacional' },
};

const linhas = () => Array.from(document.querySelectorAll('.fx-table tbody tr'));
const numeros = () => linhas().map((tr) => tr.querySelector('.fx-mono b')?.textContent);
const botao = (nome: RegExp) => screen.getByRole('button', { name: nome });

describe('UC-FCKP-09 · o cockpit serve uma PÁGINA da lista, não a lista inteira', () => {
  it('com 20 notas carregadas, a tabela renderiza só as 8 da primeira página', () => {
    render(<Cockpit {...cena} />);
    // Sem o slice, as 20 <tr> viriam de uma vez e este caso fica vermelho.
    expect(linhas()).toHaveLength(8);
    expect(screen.getByText(/1–8 de 20 carregadas/)).toBeTruthy();
    expect(screen.getByText('1 / 3')).toBeTruthy();
  });

  it('"Próxima" troca as linhas — não repete a mesma página', () => {
    render(<Cockpit {...cena} />);
    const primeiraPagina = numeros();

    fireEvent.click(botao(/Próxima/));

    expect(numeros()).not.toEqual(primeiraPagina);
    expect(numeros()[0]).toBe('9008'); // 9ª nota
    expect(screen.getByText('2 / 3')).toBeTruthy();
    expect(screen.getByText(/9–16 de 20 carregadas/)).toBeTruthy();
  });

  it('Anterior e Próxima ficam desabilitados nos extremos', () => {
    render(<Cockpit {...cena} />);
    expect(botao(/Anterior/).hasAttribute('disabled')).toBe(true);

    fireEvent.click(botao(/Próxima/));
    fireEvent.click(botao(/Próxima/));

    expect(screen.getByText('3 / 3')).toBeTruthy();
    expect(botao(/Próxima/).hasAttribute('disabled')).toBe(true);
    expect(botao(/Anterior/).hasAttribute('disabled')).toBe(false);
    expect(linhas()).toHaveLength(4); // 20 - 16
  });

  it('filtrar volta pra página 1 — o operador não fica preso numa página vazia', () => {
    render(<Cockpit {...cena} />);
    fireEvent.click(botao(/Próxima/));
    fireEvent.click(botao(/Próxima/));
    expect(screen.getByText('3 / 3')).toBeTruthy();

    // "Alvo Unico" só casa a 20ª nota => 1 resultado, 1 página.
    fireEvent.change(
      screen.getByLabelText('Buscar notas por número, cliente, CNPJ ou chave'),
      { target: { value: 'alvo unico' } },
    );

    // Sem o setPagina(1) do useEffect, `pagina` seguiria 3 e o slice(16,24) de uma
    // lista de 1 item devolveria VAZIO: tabela em branco com o filtro achando algo.
    expect(linhas()).toHaveLength(1);
    expect(screen.getByText('1 / 1')).toBeTruthy();
    expect(screen.getByText(/1–1 de 1 carregadas/)).toBeTruthy();
  });
});
