/**
 * As mini-séries de 14 dias do ribbon do cockpit fiscal.
 *
 * @covers-us UC-FCKP-12
 *
 * POR QUE ESTE TESTE É DE RENDER (e não estático): o defeito que ele defende não era
 * código ausente — era código ESCRITO e INERTE. A prop `sparklines` viajava do
 * controller até a tela desde o primeiro PR, tinha `interface` própria e já era
 * assertada no `CockpitControllerTest`; ela só nunca foi desestruturada. Um assert
 * sobre o texto do `.tsx`, ou sobre o payload do controller, ficaria VERDE com a tela
 * exatamente como estava (LC-11 presença × comportamento; LC-30 passa no CI e é inerte
 * no runtime). Por isso cada caso aqui conta `<polyline>` de fato renderizada.
 *
 * FONTE DO CONTRATO: `FxSpark` de `fiscal-page.jsx:80-84` (protótipo Cowork), que é a
 * âncora declarada no `Cockpit.charter.md` e foi lida nos DOIS donos do inventário em
 * 2026-09-04 — do vivo por `DesignSync` (`truncated: false`) e do espelho
 * `prototipo-ui/cowork/`, que concordam neste trecho. De lá vêm, e não de palpite: o
 * viewBox 56×15, a base em y=14, a amplitude 12, `strokeWidth` 1.2, `currentColor`,
 * `aria-hidden` — e QUAIS KPIs recebem a série (`:114-116`: emitidas, autorizadas,
 * rejeitadas; os outros três não têm `FxSpark`).
 *
 * O QUE ESTE ARQUIVO NÃO PROVA, de propósito:
 *   - Que a série tenha 14 pontos. Isso é do backend e é garantido por CONSTRUÇÃO —
 *     `computeSparklines()` tem um `for ($i = 0; $i < 14; $i++)` e um único `return`,
 *     sem caminho de saída antecipada. Re-assertar aqui mediria o meu próprio mock.
 *   - Que a cor de cada série esteja certa. `currentColor` resolve na cascata do CSS,
 *     e jsdom não faz cascata — afirmar cor aqui seria medir o que eu mandei, não o que
 *     o browser resolveu (§5 2026-07-16). O contrato testável é que a cor NÃO está
 *     fixada no SVG; a tinta real é do smoke em produção.
 */
import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';

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

/** 14 pontos, como o controller emite. Valores distintos para a curva não ser reta. */
const serie = (base: number) => Array.from({ length: 14 }, (_, i) => base + (i % 5));

const cena = {
  kpis: {
    emitidas: 42, autorizadas: 40, autorizadasPct: 95.2, rejeitadas: 2,
    faturamentoFiscal: 18400, dfeAguardando: 3, certificadoValidadeDias: 45,
  },
  sparklines: {
    emitidas: serie(3),
    autorizadas: serie(2),
    rejeitadas: serie(0),
    faturamento: serie(9),
  },
  alerts: [],
  notas: [],
  savedViewCounts: { todas: 0, resolver: 0, janela24: 0, processando: 0, nfse: 0, nfce: 0 },
  sefazStatus: { uf: 'SC', operacional: true, label: 'SEFAZ-SC operacional' },
};

const ribbon = () => document.querySelector('[data-contract="fiscal-cockpit-kpis"]')!;
const itens = () => Array.from(ribbon().querySelectorAll('.fx-ribbon-item'));
const sparks = () => Array.from(ribbon().querySelectorAll('polyline'));

describe('UC-FCKP-12 · o ribbon desenha as séries que o controller já mandava', () => {
  it('as séries são DESENHADAS — antes deste caso, `sparklines` chegava e morria na prop', () => {
    render(<Cockpit {...cena} />);
    // Com a prop não-desestruturada (o estado de origin/main até 2026-09-04), este
    // número era 0 e o caso fica vermelho.
    expect(sparks()).toHaveLength(3);
  });

  it('recebem série exatamente os 3 KPIs que o protótipo marca — nem mais, nem menos', () => {
    render(<Cockpit {...cena} />);
    const comSpark = itens()
      .filter((it) => it.querySelector('polyline'))
      .map((it) => it.querySelector('small')?.textContent);

    // `fiscal-page.jsx:114-116`. Os outros 3 (DF-e, Certif. A1, Faturado fiscal) não
    // têm FxSpark no protótipo — desenhá-los seria divergir da fonte de FORMA.
    expect(comSpark).toEqual(['Emitidas', 'Autorizadas', 'Rejeitadas']);
  });

  it('a geometria é a do protótipo: viewBox 56×15, base em 14, amplitude 12', () => {
    render(<Cockpit {...cena} />);
    const svg = sparks()[0]!.closest('svg')!;
    expect(svg.getAttribute('viewBox')).toBe('0 0 56 15');
    expect(sparks()[0]!.getAttribute('stroke-width')).toBe('1.2');

    // A série de "Emitidas" é serie(3) => min 3, max 7. O ponto de máximo tem de
    // pousar no topo (14 - 12 = 2) e o de mínimo em 14 - (3/7)*12 ≈ 8.86.
    const ys = sparks()[0]!.getAttribute('points')!
      .split(' ')
      .map((par) => Number(par.split(',')[1]));
    expect(Math.min(...ys)).toBeCloseTo(2, 5);
    expect(Math.max(...ys)).toBeCloseTo(14 - (3 / 7) * 12, 5);
  });

  it('o gráfico é redundância visual: some do leitor de tela e não fixa cor', () => {
    render(<Cockpit {...cena} />);
    const svg = sparks()[0]!.closest('svg')!;
    expect(svg.getAttribute('aria-hidden')).toBe('true');
    // `currentColor` faz a série herdar a tinta do KPI — a de "Rejeitadas" sai em
    // alerta sem nenhum mapa de cor no componente. Um literal aqui quebraria isso.
    expect(sparks()[0]!.getAttribute('stroke')).toBe('currentColor');
  });

  it('série com 1 ponto não desenha — sem o guarda, `length - 1` zera e sai points="NaN,NaN"', () => {
    // Não é hipótese: o `cena` de fiscal-cockpit-paginacao.test.tsx passa exatamente [1].
    render(<Cockpit {...cena} sparklines={{ emitidas: [1], autorizadas: [1], rejeitadas: [1], faturamento: [1] }} />);
    expect(sparks()).toHaveLength(0);
    expect(ribbon().innerHTML).not.toContain('NaN');
  });

  it('série toda-zero DESENHA na base — "nada aconteceu" é leitura honesta, não ausência', () => {
    const zeros = Array.from({ length: 14 }, () => 0);
    render(<Cockpit {...cena} sparklines={{ ...cena.sparklines, rejeitadas: zeros }} />);

    const daRejeitada = itens().find((it) => it.querySelector('small')?.textContent === 'Rejeitadas')!;
    const ys = daRejeitada.querySelector('polyline')!.getAttribute('points')!
      .split(' ')
      .map((par) => Number(par.split(',')[1]));

    // Todos na base: 14 - (0/1)*12 = 14. Esconder aqui seria divergir do protótipo.
    expect(new Set(ys)).toEqual(new Set([14]));
  });
});
