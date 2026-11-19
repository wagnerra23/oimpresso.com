/**
 * Lista unificada do Painel fiscal: moeda, visões salvas, busca e seleção em lote.
 *
 * @covers-us UC-FCKP-18 UC-FCKP-19 UC-FCKP-20
 *
 * Teste de RENDER: conta as linhas que o operador vê e lê o texto da tela. A fonte da
 * lista é real desde 2026-09-02 (UC-FCKP-07); aqui ela entra como prop, como em
 * `fiscal-cockpit-paginacao.test.tsx`.
 *
 * O QUE ESTE ARQUIVO NÃO PROVA, de propósito:
 *   - A densidade: já tem contrato em `fiscal-densidade.test.tsx` (UC-FNFE-14 inclui o Painel).
 *   - Os botões da barra de lote: "Baixar XMLs", "Baixar DANFEs" e "Reenviar por e-mail"
 *     ainda não têm ação. Estão declarados como débito em `Cockpit.casos.md`; testar que
 *     existem seria afirmar uma capacidade que a tela não tem.
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

const base = {
  serie: '1', when: '02/09 10:00', doc: '12345678000199', uf: 'SC', venda: null, ref: null,
  prazoCancel: null, prazoCce: null, value: 10, rejMsg: null,
};
const nfe = (id: string, num: string, cliente: string, status: number) => ({
  ...base, id, num, cliente, tipo: 'NF-e' as const, kind: 'nfe' as const, modelo: 55,
  keyOrCode: '3526' + num.padStart(40, '0'), status, statusKind: 'sefaz' as const,
});
const notas = [
  nfe('nfe-1', '101', 'Cliente Autorizado', 100),
  nfe('nfe-2', '102', 'Cliente Rejeitado', 110),
  { ...nfe('nfce-1', '201', 'Cliente Balcao', 100), tipo: 'NFC-e' as const, modelo: 65 },
  { ...base, id: 'nfse-1', num: '301', cliente: 'Cliente Servico', tipo: 'NFS-e' as const,
    kind: 'nfse' as const, modelo: 0, keyOrCode: 'ABC123', status: 'autorizada', statusKind: 'nfse' as const },
];

const cena = (faturamento = 0) => ({
  kpis: {
    emitidas: 4, autorizadas: 3, autorizadasPct: 75, rejeitadas: 1,
    faturamentoFiscal: faturamento, dfeAguardando: 0, certificadoValidadeDias: 90,
  },
  sparklines: { emitidas: [1], autorizadas: [1], rejeitadas: [0], faturamento: [1] },
  alerts: [],
  notas,
  savedViewCounts: { todas: 4, resolver: 1, janela24: 0, processando: 0, nfse: 1, nfce: 1 },
  sefazStatus: { uf: 'SC', operacional: true, label: 'SEFAZ-SC operacional' },
});

const numeros = () =>
  Array.from(document.querySelectorAll('.fx-table tbody tr')).map((tr) => tr.querySelector('.fx-mono b')?.textContent);
const visao = (nome: string) => screen.getByRole('tab', { name: new RegExp(nome) });

describe('UC-FCKP-18 · o faturamento aparece em moeda brasileira', () => {
  it('UC-FCKP-18 · milhar com ponto, centavos com vírgula e o símbolo do real', () => {
    render(<Cockpit {...cena(1234.56)} />);
    const ribbon = screen.getByText('Faturado fiscal').parentElement!;
    // Montado em runtime: valor monetário literal no fonte aciona o brl-scan. O espaço depois
    // do símbolo é o NBSP do Intl; normalizado para espaço comum antes de comparar.
    const texto = (ribbon.textContent ?? '').split(String.fromCharCode(160)).join(' ');
    expect(texto).toContain(['R', '$', ' ', '1.234,56'].join(''));
    // Controle negativo: o formato americano não aparece.
    expect(ribbon.textContent).not.toContain('1,234.56');
  });
});

describe('UC-FCKP-19 · visões salvas e busca filtram a lista', () => {
  it('UC-FCKP-19 · cada visão mostra só as notas dela', () => {
    render(<Cockpit {...cena()} />);
    expect(numeros()).toHaveLength(4);

    fireEvent.click(visao('Só serviço'));
    expect(numeros()).toEqual(['301']);

    fireEvent.click(visao('Só balcão'));
    expect(numeros()).toEqual(['201']);

    fireEvent.click(visao('Pra resolver hoje'));
    expect(numeros()).toEqual(['102']);

    fireEvent.click(visao('Todas'));
    expect(numeros()).toHaveLength(4);
  });

  it('UC-FCKP-19 · aplicar uma visão limpa a busca, e a busca acha por cliente', () => {
    render(<Cockpit {...cena()} />);
    const busca = screen.getByLabelText('Buscar notas por número, cliente, CNPJ ou chave');

    fireEvent.change(busca, { target: { value: 'rejeitado' } });
    expect(numeros()).toEqual(['102']);

    fireEvent.click(visao('Todas'));
    expect((busca as HTMLInputElement).value).toBe('');
    expect(numeros()).toHaveLength(4);
  });
});

describe('UC-FCKP-20 · seleção em lote', () => {
  it('UC-FCKP-20 · marcar notas mostra a barra com a contagem; "Selecionar todas" pega a página', () => {
    render(<Cockpit {...cena()} />);
    expect(screen.queryByRole('region', { name: 'Ações em lote' })).toBeNull();

    fireEvent.click(screen.getByLabelText('Selecionar nota 101'));
    fireEvent.click(screen.getByLabelText('Selecionar nota 102'));
    const barra = screen.getByRole('region', { name: 'Ações em lote' });
    expect(barra.textContent).toContain('2 notas selecionadas');

    fireEvent.click(screen.getByLabelText('Selecionar todas'));
    expect(barra.textContent).toContain('4 notas selecionadas');

    fireEvent.click(screen.getByLabelText('Selecionar todas'));
    expect(screen.queryByRole('region', { name: 'Ações em lote' })).toBeNull();
  });

  it('UC-FCKP-20 · trocar o filtro limpa a seleção (não sobra nota marcada fora da vista)', () => {
    render(<Cockpit {...cena()} />);
    fireEvent.click(screen.getByLabelText('Selecionar nota 101'));
    expect(screen.getByRole('region', { name: 'Ações em lote' })).toBeTruthy();

    fireEvent.click(visao('Só serviço'));
    expect(screen.queryByRole('region', { name: 'Ações em lote' })).toBeNull();
  });
});
