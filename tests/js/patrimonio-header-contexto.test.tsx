// UC-PAT-05 / UC-PAT-06 · Patrimonio/Index — os dois elementos do header que vêm do SHELL
// do protótipo, não do corpo da página.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// A tela foi alinhada ao protótipo nos PRs #7133/#7139 (chips, seções, busca, botões,
// glyph). Dois itens do header ficaram de fora, e o motivo é estrutural: eles não moram
// no `patrimonio-page.jsx` — moram no `MP.Header` (`modulo-padrao.jsx:18`), que delega pro
// `CliPageHead`. Quem lê só a página do módulo não os vê, e foi o que aconteceu.
//   • **selo de frescor** — `atualizadoAs` + `onRefresh` (`patrimonio-page.jsx:821-822`),
//     renderizado como PRIMEIRO item de `actions` (`cli-pagehead.jsx:159`).
//   • **linha de contexto** — `contexto={[...]}` (`:820`), renderizada como `<p>` irmão
//     ACIMA do header quando `contextoWrap` (`cli-pagehead.jsx:89`).
//
// ── O QUE ELE MEDE (componente REAL, header INCLUSO) ─────────────────────────
// Diferente do irmão `patrimonio-painel-sem-fonte.test.tsx`, aqui o `@/Components/PageHeader`
// **não é mockado**: o canon é um componente puro (só React, sem hook nem Inertia) e roda no
// jsdom — medido antes de escrever este arquivo, com os 6 casos do irmão seguindo verdes com
// o header real no lugar do mock. Mockar o header aqui mediria a MINHA cópia do slot
// `actions`, não a tela (§5 2026-08-14: selftest que exercita a cópia, não o chokepoint).
//
// ── A MORDIDA (provada, não afirmada) ────────────────────────────────────────
// Mutações aplicadas ao código real em 2026-09-09, uma de cada vez, com restauração e
// reconfirmação do verde entre elas — os recibos estão no corpo do PR:
//   1. remover `<PilulaFrescor …/>` de `actions`            → 4 casos vermelhos
//   2. mover a pílula pra DEPOIS do `<form role=search>`    → 1 caso vermelho (o da ordem)
//   3. remover `<LinhaDeContexto …/>`                       → 3 casos vermelhos
//   4. trocar `apuradoHora` por `new Date()` (hora do relógio, não da apuração)
//                                                            → 1 caso vermelho
// A mutação 4 é a que importa mais: sem ela o selo ficaria verde mostrando uma hora que não
// tem nada a ver com a apuração — o defeito mais fácil de cometer e o mais difícil de ver.
//
// ── FUSO: por que nenhum assert crava "09:42" ────────────────────────────────
// `toLocaleTimeString` resolve no fuso do RUNNER. Cravar a string cria o teste que passa na
// minha máquina (BRT) e falha no CI (UTC) — é a lápide §5 2026-08-07, no eixo timezone.
// Então o contrato é medido em duas partes, ambas imunes ao fuso: o FORMATO (`HH:MM`) e a
// LIGAÇÃO com a prop (dois `apurado_em` distintos ⇒ dois selos distintos; o mesmo ⇒ o mesmo).
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada de GEOMETRIA: jsdom não faz layout, então `-mb-5` e o alinhamento do eyebrow com
//     o h1 não são medidos aqui. Isso é olho + baseline visual (gate F1.5), não vitest.
//   - Nada sobre `router.reload()` REAPURAR de fato as props deferidas — aqui o `router` é
//     mock e o assert é que a tela o chama. Que o servidor devolva `apurado_em` novo é
//     contrato do controller (`AssetController:643`).
//   - Nada sobre os LOCAIS, o 3º pedaço do `contexto` do protótipo: ele não desce, de
//     propósito, e o motivo está no comentário do `.tsx` (os KPIs não filtram por
//     `permitted_locations`, então nomeá-los mentiria sobre o escopo dos números).
//
// @see resources/js/Pages/Patrimonio/Index.casos.md (UC-PAT-05, UC-PAT-06)
// @see prototipo-ui/cowork/cli-pagehead.jsx (o desenho que estes casos defendem)

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const reload = vi.fn();

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  // `useBusiness()` (`Hooks/usePageProps.ts:12`) lê daqui — é o canal do nome do negócio.
  usePage: () => ({ props: { business: { id: 98, name: 'ROTA LIVRE' } } }),
  router: { reload: (...args: unknown[]) => reload(...args) },
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));
// O `@/Components/PageHeader` NÃO entra aqui — ver o docblock.

import PainelPatrimonio from '@/Pages/Patrimonio/Index';

const BASE = {
  is_admin: true,
  pode: { ver: true, criar: true },
  apurado_em: '2026-09-08T09:42:00-03:00',
  porCategoria: [],
  garantia: [],
  manutencoes: [],
};

const KPIS = {
  bruto: 305230,
  valorResidual: null as number | null,
  unidades: 21,
  totalBens: 11,
  alocados: 4,
  alocaveis: 13,
  garantiaCritica: 5,
};

/** O selo, achado pelo título literal do protótipo (`modulo-padrao.jsx:34` `refreshTitle`). */
const selo = () => document.querySelector<HTMLButtonElement>('button[title="Reapurar agora"]');

afterEach(() => {
  reload.mockClear();
  cleanup();
});

describe('UC-PAT-05 · selo de frescor no header', () => {
  it('CONTROLE POSITIVO: o header REAL renderiza (o h1 da tela existe)', () => {
    // Se este cair, o harness quebrou e nenhum assert abaixo significa nada — os outros
    // casos deste describe medem coisas DENTRO do header.
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('Patrimônio');
  });

  it('mostra "Atualizado HH:MM" — o formato do protótipo, sem cravar fuso', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    const botao = selo();
    expect(botao, 'selo de frescor no header').not.toBeNull();
    expect(botao!.textContent?.trim()).toMatch(/^Atualizado \d{2}:\d{2}$/);
  });

  it('a hora vem de `apurado_em`, não do relógio: prop diferente ⇒ selo diferente', () => {
    // Esta é a ligação que o formato sozinho não prova. Duas apurações a uma hora de
    // distância têm de produzir dois selos — em QUALQUER fuso.
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    const primeiro = selo()!.textContent;
    cleanup();

    render(<PainelPatrimonio {...BASE} apurado_em="2026-09-08T10:42:00-03:00" kpis={KPIS} />);
    const segundo = selo()!.textContent;
    expect(segundo, 'apuração uma hora depois ⇒ selo diferente').not.toBe(primeiro);

    cleanup();
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    expect(selo()!.textContent, 'mesma apuração ⇒ mesmo selo (determinístico)').toBe(primeiro);
  });

  it('é o PRIMEIRO item das ações, antes da busca (ordem do `CliPageHead:159`)', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    const botao = selo()!;
    const busca = document.querySelector('form[role="search"]')!;
    expect(busca, 'a busca do contrato de tela').not.toBeNull();
    // `DOCUMENT_POSITION_FOLLOWING`: a busca vem DEPOIS do selo na ordem do documento.
    // Medir a posição relativa é imune a quantos wrappers o `Inline` interpõe.
    expect(
      botao.compareDocumentPosition(busca) & Node.DOCUMENT_POSITION_FOLLOWING,
      'o selo precede a busca',
    ).toBeTruthy();
  });

  it('clicar reapura de verdade — chama `router.reload()`', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    fireEvent.click(selo()!);
    expect(reload).toHaveBeenCalledTimes(1);
  });
});

describe('UC-PAT-06 · linha de contexto acima do título', () => {
  it('junta negócio e contagem de bens com " · ", como o protótipo', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    const eyebrow = screen.getByText(/ROTA LIVRE/);
    expect(eyebrow.textContent).toBe('ROTA LIVRE · 11 bens');
  });

  it('vem ANTES do título — é eyebrow, não subtítulo', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    const eyebrow = screen.getByText(/ROTA LIVRE/);
    const h1 = screen.getByRole('heading', { level: 1 });
    expect(
      eyebrow.compareDocumentPosition(h1) & Node.DOCUMENT_POSITION_FOLLOWING,
      'o contexto precede o h1',
    ).toBeTruthy();
  });

  it('sem `kpis` (prop deferida ainda no ar), mostra só o negócio — sem separador órfão', () => {
    // O `filter` que o protótipo já tem (`cli-pagehead.jsx:79`) é o que sustenta isto: a
    // contagem chega depois, e enquanto não chega o pedaço SAI do join. Um " · " pendurado
    // no fim seria a marca de que alguém concatenou à mão.
    render(<PainelPatrimonio {...BASE} kpis={undefined} />);
    const eyebrow = screen.getByText(/ROTA LIVRE/);
    expect(eyebrow.textContent).toBe('ROTA LIVRE');
  });
});
