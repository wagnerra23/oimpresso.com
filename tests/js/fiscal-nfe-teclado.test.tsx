/**
 * Teclado na lista de NF-e/NFC-e — a linha é FOCÁVEL, e não virou botão.
 *
 * @covers-us UC-FNFE-10 UC-FNFE-11
 *
 * POR QUE ESTE TESTE É DE RENDER (e não estático nem de domínio): o contrato é
 * "o operador chega na linha pelo Tab e a abre pelo teclado". Um assert sobre o
 * texto do `.tsx` provaria que o atributo foi ESCRITO — presença, não
 * comportamento (LC-11). Aqui a focabilidade é medida pelo `document.activeElement`
 * depois de um `.focus()` real: uma `<tr>` SEM `tabIndex` não é focável no DOM, o
 * foco cai no `<body>`, e o caso fica vermelho (mordida provada abaixo).
 *
 * O QUE JÁ EXISTIA: o handler J/K global em `window` (o `useEffect` do cursor em
 * `Nfe.tsx`). Ele move um índice e pinta `.fx-row-focus` SEM mover o foco do DOM —
 * por isso Tab e leitor de tela nunca alcançavam as linhas, apesar de a tela "ter
 * teclado". Os dois passam a conviver: o `onFocus` da linha sincroniza o cursor.
 *
 * ⚠️ LIMITE MEDIDO, não suposto (contrafactual de 2026-09-03): remover o
 * `onKeyDown` da linha derruba o caso do Space, mas NÃO o do Enter — com o cursor
 * sincronizado pelo `onFocus`, o handler global de `window` abre a mesma nota. Ou
 * seja: o Enter é servido por DOIS caminhos, e este arquivo não os distingue. O que
 * ele prova do Enter é o que o operador observa — abre a nota da linha FOCADA, não
 * sempre a primeira —, e isso vale para qualquer um dos dois caminhos. O valor
 * próprio do handler da linha é não depender do listener de `window` (e o
 * `stopPropagation` é o que evita a abertura dupla quando os dois veem a tecla).
 *
 * LIMITE HONESTO: o jsdom não implementa a navegação por Tab do browser, então
 * "Tab alcança todas" é medido como "toda linha é de fato focável, na ordem do
 * DOM" — a condição que torna a travessia possível. A travessia física, o anel
 * pintado e o leitor de tela são olho humano no smoke (R1), não este arquivo.
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

import Nfe from '@/Pages/Fiscal/Nfe';

const sefazCodes = {
  100: { tone: 'ok' as const, label: 'Autorizada', hint: 'Uso autorizado' },
  539: { tone: 'bad' as const, label: 'Duplicidade', hint: 'Chave já usada' },
};

const nota = (over: Record<string, unknown>) => ({
  id: 1, num: 8425, serie: 1, modelo: 55, key: '3526' + '0'.repeat(40),
  status: 'autorizada', cstat: 100, motivo: null, value: 1250.4,
  emittedAtIso: '2026-09-02T10:00:00-03:00', when: '02/09 10:00',
  transactionId: null, dest: 'Rota Livre Comercio', cnpj: '12345678000199',
  cpf: null, uf: 'SC', items: 3, cancelavel: true,
  ...over,
});

const cena = {
  filters: { search: '', status: 'todas' as const, tab: 'saida_nfe' as const },
  counts: {
    total: 3, nfe: 2, nfce: 1, autorizadas: 2,
    rejeitadas: 1, processando: 0, canceladas: 0, cancelaveis: 1,
  },
  sefazCodes,
  rows: {
    data: [
      nota({ id: 1, num: 8425, dest: 'Rota Livre Comercio' }),
      nota({ id: 2, num: 8426, modelo: 65, dest: 'Consumidor final' }),
      nota({ id: 3, num: 8427, cstat: 539, status: 'rejeitada', dest: '' }),
    ],
    meta: { current_page: 1, last_page: 1, total: 3, per_page: 50 },
  },
};

const linhas = () =>
  Array.from(document.querySelectorAll<HTMLTableRowElement>('.fx-table tbody tr'));

const focar = (tr: HTMLTableRowElement) => act(() => { tr.focus(); });

describe('UC-FNFE-10 · a lista de notas é operável só pelo teclado', () => {
  it('toda linha da lista é REALMENTE focável, na ordem do DOM', () => {
    render(<Nfe {...cena} />);
    const trs = linhas();
    expect(trs).toHaveLength(3);

    trs.forEach((tr, i) => {
      focar(tr);
      expect(document.activeElement, `linha ${i} não recebeu foco`).toBe(tr);
    });
  });

  it('Enter abre a nota da linha FOCADA — não a primeira da lista', () => {
    render(<Nfe {...cena} />);
    expect(screen.queryByRole('dialog')).toBeNull();

    const terceira = linhas()[2];
    focar(terceira);
    fireEvent.keyDown(terceira, { key: 'Enter' });

    const drawer = screen.getByRole('dialog');
    // 8427 é a 3ª nota. Se o drawer abrisse sempre a do cursor inicial, viria 8425.
    expect(within(drawer).getByRole('heading', { level: 2 }).textContent).toBe('NFe 8427');
  });

  it('Space abre o drawer E cancela o default (senão a página rola)', () => {
    render(<Nfe {...cena} />);
    const primeira = linhas()[0];
    focar(primeira);

    // fireEvent devolve false quando o handler chamou preventDefault. É a medida do
    // cancelamento, não a leitura do código: sem ele o browser rolaria a página um
    // viewport e o operador perderia de vista a linha que acabou de abrir.
    const naoCancelado = fireEvent.keyDown(primeira, { key: ' ' });

    expect(naoCancelado, 'Space NÃO teve o default cancelado — a página rolaria').toBe(false);
    expect(screen.getByRole('dialog')).toBeTruthy();
  });

  it('o cursor J/K e o foco do Tab são o MESMO cursor (um anel, não dois)', () => {
    render(<Nfe {...cena} />);

    // Chega na 2ª linha por foco (o que o Tab faria) e então usa o atalho global.
    focar(linhas()[1]);
    act(() => { fireEvent.keyDown(window, { key: 'j' }); });

    // Sem o onFocus sincronizando, o cursor teria ficado em 0 e o `j` acenderia a 2ª.
    const comAnel = linhas().findIndex(tr => tr.className.includes('fx-row-focus'));
    expect(comAnel, 'o anel do cursor não seguiu o foco do teclado').toBe(2);
  });

  it('a linha se anuncia com tipo, número e destinatário — e continua sendo linha', () => {
    render(<Nfe {...cena} />);
    const [nfe, nfce, semDest] = linhas();

    expect(nfe.getAttribute('aria-label')).toBe('Abrir NF-e 8425 · Rota Livre Comercio');
    expect(nfce.getAttribute('aria-label')).toBe('Abrir NFC-e 8426 · Consumidor final');
    // Sem fonte de dado no destinatário ⇒ travessão, nunca string vazia (charter R2).
    expect(semDest.getAttribute('aria-label')).toBe('Abrir NF-e 8427 · —');

    // Semântica de tabela preservada: a linha NÃO virou role="button".
    linhas().forEach(tr => expect(tr.getAttribute('role')).toBeNull());
  });

  it('o clique segue abrindo o drawer (não-regressão do que já funcionava)', () => {
    render(<Nfe {...cena} />);
    fireEvent.click(linhas()[1]);
    expect(screen.getByRole('dialog')).toBeTruthy();
  });
});

describe('UC-FNFE-11 · nenhum ícone decorativo chega ao leitor de tela', () => {
  it('todo ícone decorativo fica FORA da árvore de acessibilidade', () => {
    render(<Nfe {...cena} />);

    // Medido no DOM, não no fonte: o lucide NÃO emite `aria-hidden` sozinho —
    // sonda de 2026-09-03 renderizou <RefreshCw/> e leu os atributos do <svg>:
    // aria-hidden=null, role=null, focusable=null. Então cada ícone decorativo
    // precisa declarar o seu, e é isso que este caso trava.
    // ESCOPO: a `.fx-page` — o que ESTA tela desenha. O shell (`FxShell`) e a paleta
    // ⌘K (`CmdKPalette`) são superfície compartilhada pelas 7 telas do Fiscal e têm
    // casos que exigem cuidado próprio (ícone sozinho em botão precisa de rótulo no
    // botão, não de aria-hidden no ícone). Medi e declarei o inventário deles; corrigi-
    // los aqui seria mexer em superfície de outro dono a partir da onda desta tela.
    const pagina = document.querySelector('.fx-page');
    expect(pagina, 'a tela não renderizou .fx-page — sonda cega').toBeTruthy();
    const svgs = [...pagina!.querySelectorAll('svg')];
    expect(svgs.length, 'a tela não renderizou ícone nenhum — sonda cega').toBeGreaterThan(0);

    const expostos = svgs.filter(svg => {
      // um ícone está "escondido" se ele ou um ancestral declara aria-hidden
      let el: Element | null = svg;
      while (el) {
        if (el.getAttribute('aria-hidden') === 'true') return false;
        el = el.parentElement;
      }
      return true;
    });

    expect(expostos.map(s => s.getAttribute('class')), 'ícone decorativo no leitor de tela').toEqual([]);
  });
});

describe('UC-FNFE-12 · a lista não promete o que não tem', () => {
  // Achado de produção 2026-09-04 ([W], screenshot de /fiscal/nfe em biz=1).
  //
  // Os dois casos abaixo são a mesma doença em superfícies diferentes: a tela ocupando espaço
  // com informação que não existe. Um separador entre dois nadas, e duas teclas que o `keydown`
  // desta tela nunca vai ver.

  it('o rodapé de atalhos não anuncia tecla sem handler', () => {
    render(<Nfe {...cena} />);

    const barra = document.querySelector('.fx-cheatsheet');
    expect(barra).not.toBeNull();

    // A regressão exata: `{ keys: ['R'], label: 'reconsultar SEFAZ (em breve)' }`. O rótulo era
    // honesto, mas a barra de atalhos é onde o operador APRENDE as teclas — ele aperta, nada
    // acontece, e conclui que a tela travou.
    expect(barra?.textContent ?? '').not.toContain('em breve');
    expect(barra?.textContent ?? '').not.toMatch(/\bR\b/);
    expect(barra?.textContent ?? '').not.toMatch(/\bX\b/);
  });

  it('R e X de fato não fazem nada — remover da barra não tirou função de ninguém', () => {
    // Controle: prova que a remoção descreve a realidade, em vez de esconder um atalho vivo.
    // As duas AÇÕES existem e seguem no drawer (US-FISCAL-012/014) — o que não existe é a tecla.
    render(<Nfe {...cena} />);

    const antes = document.body.innerHTML;
    act(() => {
      fireEvent.keyDown(window, { key: 'r' });
      fireEvent.keyDown(window, { key: 'R' });
      fireEvent.keyDown(window, { key: 'x' });
      fireEvent.keyDown(window, { key: 'X' });
    });

    expect(document.body.innerHTML).toBe(antes);
  });

  // Cena das notas rejeitadas de biz=1: sem destinatário e sem documento gravados. O Controller
  // manda `dest: '—'` (o fallback dele) e o `formatDoc` devolve `'—'` — dois fallbacks certos que
  // somavam um terceiro errado, `— · —`.
  const cenaSemDest = {
    ...cena,
    rows: {
      ...cena.rows,
      data: [
        nota({ id: 10, num: 1, dest: '—', cnpj: null, cpf: null }),
        nota({ id: 11, num: 2, dest: 'Gráfica Ribeirão', cnpj: null, cpf: null }),
        nota({ id: 12, num: 3, dest: '—', cnpj: '22641309000188', cpf: null }),
      ],
    },
  };

  const infoDe = (i: number) =>
    linhas()[i]?.querySelectorAll('td')[1]?.querySelector('small')?.textContent ?? '';

  it('sem destinatário e sem documento, a célula não vira "— · —"', () => {
    render(<Nfe {...cenaSemDest} />);

    expect(infoDe(0)).toBe('—');
  });

  it('com só um dos dois, mostra o que existe — sem separador solto', () => {
    render(<Nfe {...cenaSemDest} />);

    expect(infoDe(1)).toBe('Gráfica Ribeirão');
    expect(infoDe(2)).toBe('22641309000188');

    for (let i = 0; i < 3; i++) {
      const t = infoDe(i);
      expect(t.startsWith('·')).toBe(false);
      expect(t.endsWith('·')).toBe(false);
      expect(t).not.toContain('— ·');
      expect(t).not.toContain('· —');
    }
  });

  it('com os dois presentes, nada muda — não-regressão', () => {
    render(<Nfe {...cena} />);

    expect(infoDe(0)).toBe('Rota Livre Comercio · 12345678000199');
  });
});
