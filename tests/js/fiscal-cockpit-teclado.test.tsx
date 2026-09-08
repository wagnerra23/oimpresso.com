/**
 * Teclado na lista unificada do cockpit Fiscal — a linha é FOCÁVEL, e não virou botão.
 *
 * @covers-us UC-FCKP-11
 *
 * POR QUE ESTE TESTE É DE RENDER (e não estático nem de domínio): o contrato é "o
 * operador chega na linha pelo Tab e a abre pelo teclado". Um assert sobre o texto do
 * `.tsx` provaria que o atributo foi ESCRITO — presença, não comportamento (LC-11).
 * Aqui a focabilidade é medida pelo `document.activeElement` depois de um `.focus()`
 * real: uma `<tr>` SEM `tabIndex` não é focável no DOM, o foco cai no `<body>`, e o
 * caso fica vermelho (mordida provada no PR).
 *
 * O QUE ESTA TELA **NÃO** TEM, e por isso não é espelhado do `fiscal-nfe-teclado`:
 * o cursor J/K. Ele existe no `Nfe.tsx` (Onda 2, #6707) e NÃO nesta tela — o
 * `Cockpit.charter.md` declara a omissão do hint por escrito e diz que "quem
 * implementar J/K no cockpit deve trazer o hint junto". Logo o caso "um anel, não
 * dois" do irmão não tem sujeito aqui: existe um anel só, o `:focus-visible`, e a
 * classe `.fx-row-focus` desta tela significa outra coisa (a nota ABERTA no drawer,
 * `openedId === n.id`), não um cursor. O caso 5 abaixo trava exatamente isso, para
 * que a próxima onda não confunda os dois papéis.
 *
 * LIMITE HONESTO: o jsdom não implementa a navegação por Tab do browser, então
 * "Tab alcança todas" é medido como "toda linha é de fato focável, na ordem do DOM"
 * — a condição que torna a travessia possível. A travessia física, o anel pintado
 * (`.fx-table tbody tr:focus-visible`, em `fiscal-cockpit.css`) e o leitor de tela
 * são olho humano no smoke (R1), não este arquivo.
 */
import { describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen, within } from '@testing-library/react';

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

/** 3 notas: NF-e, NFC-e e uma sem cliente (para o travessão do rótulo). */
const nota = (over: Record<string, unknown>) => ({
  id: 'nfe-1',
  tipo: 'NF-e' as const,
  kind: 'nfe' as const,
  num: '9001',
  serie: '1',
  when: '02/09 10:00',
  cliente: 'Rota Livre Comercio',
  doc: '12345678000199',
  uf: 'SC',
  venda: null,
  ref: null,
  keyOrCode: '3526' + '0'.repeat(40),
  status: 100,
  statusKind: 'sefaz' as const,
  rejMsg: null,
  modelo: 55,
  value: 1250.4,
  prazoCancel: null,
  prazoCce: null,
  ...over,
});

const cena = {
  kpis: {
    emitidas: 3, autorizadas: 3, autorizadasPct: 100, rejeitadas: 0,
    faturamentoFiscal: 3000, dfeAguardando: 0, certificadoValidadeDias: 90,
  },
  sparklines: { emitidas: [1], autorizadas: [1], rejeitadas: [0], faturamento: [1] },
  alerts: [],
  notas: [
    nota({ id: 'nfe-1', num: '9001', cliente: 'Rota Livre Comercio' }),
    nota({ id: 'nfe-2', num: '9002', tipo: 'NFC-e' as const, modelo: 65, cliente: 'Consumidor final' }),
    nota({ id: 'nfe-3', num: '9003', cliente: '' }),
  ],
  savedViewCounts: { todas: 3, resolver: 0, janela24: 0, processando: 0, nfse: 0, nfce: 1 },
  sefazStatus: { uf: 'SC', operacional: true, label: 'SEFAZ-SC operacional' },
};

const linhas = () =>
  Array.from(document.querySelectorAll<HTMLTableRowElement>('.fx-table tbody tr'));

const focar = (tr: HTMLTableRowElement) => act(() => { tr.focus(); });

describe('UC-FCKP-11 · a lista unificada é operável só pelo teclado', () => {
  it('toda linha da lista é REALMENTE focável, na ordem do DOM', () => {
    render(<Cockpit {...cena} />);
    const trs = linhas();
    expect(trs).toHaveLength(3);

    trs.forEach((tr, i) => {
      focar(tr);
      expect(document.activeElement, `linha ${i} não recebeu foco`).toBe(tr);
    });
  });

  it('Enter abre a nota da linha FOCADA — não a primeira da lista', () => {
    render(<Cockpit {...cena} />);
    expect(screen.queryByRole('dialog')).toBeNull();

    const terceira = linhas()[2];
    focar(terceira);
    fireEvent.keyDown(terceira, { key: 'Enter' });

    const drawer = screen.getByRole('dialog');
    // 9003 é a 3ª nota. Se o drawer abrisse sempre a primeira, viria 9001.
    expect(within(drawer).getByRole('heading', { level: 2 }).textContent).toBe('NFe 9003');
  });

  it('Space abre o drawer E cancela o default (senão a página rola)', () => {
    render(<Cockpit {...cena} />);
    const primeira = linhas()[0];
    focar(primeira);

    // fireEvent devolve false quando o handler chamou preventDefault. É a medida do
    // cancelamento, não a leitura do código: sem ele o browser rolaria a página um
    // viewport e o operador perderia de vista a linha que acabou de abrir.
    const naoCancelado = fireEvent.keyDown(primeira, { key: ' ' });

    expect(naoCancelado, 'Space NÃO teve o default cancelado — a página rolaria').toBe(false);
    expect(screen.getByRole('dialog')).toBeTruthy();
  });

  it('a linha se anuncia com tipo, número e cliente — e continua sendo linha', () => {
    render(<Cockpit {...cena} />);
    const [nfe, nfce, semCliente] = linhas();

    expect(nfe.getAttribute('aria-label')).toBe('Abrir NF-e 9001 · Rota Livre Comercio');
    expect(nfce.getAttribute('aria-label')).toBe('Abrir NFC-e 9002 · Consumidor final');
    // Sem fonte de dado no cliente ⇒ travessão, nunca string vazia.
    expect(semCliente.getAttribute('aria-label')).toBe('Abrir NF-e 9003 · —');

    // Semântica de tabela preservada: a linha NÃO virou role="button".
    linhas().forEach(tr => expect(tr.getAttribute('role')).toBeNull());
  });

  it('`.fx-row-focus` marca a nota ABERTA, não a focada — são papéis diferentes', () => {
    render(<Cockpit {...cena} />);

    // Só focar não acende a classe: nesta tela o anel do teclado é o `:focus-visible`
    // do CSS, e `.fx-row-focus` é reservado à nota que está no drawer.
    focar(linhas()[1]);
    expect(linhas().filter(tr => tr.className.includes('fx-row-focus'))).toHaveLength(0);

    // Abrir pelo teclado acende — e só na linha aberta.
    fireEvent.keyDown(linhas()[1], { key: 'Enter' });
    const comClasse = linhas().findIndex(tr => tr.className.includes('fx-row-focus'));
    expect(comClasse, 'a nota aberta no drawer não ficou marcada na lista').toBe(1);
  });

  it('o clique segue abrindo o drawer (não-regressão do que já funcionava)', () => {
    render(<Cockpit {...cena} />);
    fireEvent.click(linhas()[1]);
    expect(screen.getByRole('dialog')).toBeTruthy();
  });

  it('as células de checkbox e de ações NÃO abrem o drawer', () => {
    render(<Cockpit {...cena} />);

    // Selecionar em lote e baixar XML/PDF são ações próprias da linha: se o clique
    // subisse, o operador abriria o drawer toda vez que marcasse uma nota.
    fireEvent.click(screen.getByLabelText('Selecionar nota 9001'));
    expect(screen.queryByRole('dialog'), 'o checkbox abriu o drawer').toBeNull();

    fireEvent.click(within(linhas()[0]).getByTitle('Baixar XML'));
    expect(screen.queryByRole('dialog'), 'a ação da linha abriu o drawer').toBeNull();
  });
});
