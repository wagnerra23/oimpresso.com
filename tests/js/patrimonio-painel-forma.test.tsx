// UC-PAT-07 / UC-PAT-08 · Patrimonio/Index — os dois detalhes de FORMA que o painel porta do
// protótipo: o selo da categoria dominante e o ícone que abre o "Resumo de hoje".
//
// ── POR QUE ESTE ARQUIVO EXISTE (e é separado do irmão) ──────────────────────
// O `patrimonio-painel-sem-fonte.test.tsx` defende um contrato de DADO (número sem fonte mostra
// `—`). Os dois casos aqui defendem FORMA, e cada um tem uma maneira própria e silenciosa de
// morrer — nenhuma delas deixa o CI vermelho sozinha:
//
//   • **o selo** some inteiro se alguém trocar o `reduce` por `porCategoria[0]` e o servidor
//     deixar de ordenar por valor: o selo continua aparecendo, com a categoria ERRADA. Um
//     percentual errado é pior que percentual nenhum, porque tem cara de apurado.
//   • **o ícone** é o caso LC-30 clássico: `<Icon name="...">` aceita qualquer string e cai no
//     fallback `Circle` quando o nome não existe no lucide (`Components/Icon.tsx:29`). O TS não
//     valida — o componente faz cast. tsc verde, eslint verde, build verde, e a tela mostra uma
//     bola vazia. Foi exatamente o bug de 2026-05-07 (PR #184), que atingiu TODAS as telas.
//
// ── O QUE ELES MEDEM (componente REAL) ───────────────────────────────────────
// Importam a Page de produção; os `vi.mock` cobrem só a casca (shell, subnav, header), como no
// arquivo irmão. O `Icon`, o `Badge` e o cálculo do percentual são os de verdade —
// reimplementar qualquer um mediria a minha cópia, não a tela (§5 2026-06-05, tautológico).
//
// ── A MORDIDA (provada por mutação, não afirmada) ────────────────────────────
// Aplicadas ao código real em 2026-09-09, uma de cada vez, com o `.tsx` restaurado depois:
//   1. `porCategoria.reduce(...)` → `porCategoria[0]`  ⇒ UC-PAT-07 caso 2 VERMELHO
//      (o selo passa a dizer a categoria errada, porque a fixture põe a dominante no MEIO).
//   2. `name="calendar"` → `name="calendario"` (nome que não existe no lucide)
//      ⇒ UC-PAT-08 VERMELHO (`lucide-circle` no lugar de `lucide-calendar`).
//   3. remover o `&& kpis?.bruto` da guarda ⇒ UC-PAT-07 caso 3 VERMELHO. E o que a mutação
//      imprimiu não foi `0% em ...` e sim **`Infinity% em impressão`** — divisão por um
//      denominador ausente, que é pior que zero: o zero ao menos parece um número.
// Sem esses três pares o arquivo seria carimbo: verde que não sabe ficar vermelho.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre CÁLCULO DE VALOR: nenhum assert soma dinheiro nem confere total. O percentual
//     aqui é razão entre dois números da própria fixture (REGRA MESTRE, memory/proibicoes.md).
//   - Nada sobre a ORDENAÇÃO do servidor. Que `painelPorCategoria` devolva por valor desc é
//     contrato do controller; aqui a ordem da fixture é deliberadamente OUTRA, justamente pra
//     que o teste não dependa dela.
//   - Nada sobre COR ou posição do selo — isso é a baseline visual, não este arquivo.
//
// @see resources/js/Pages/Patrimonio/Index.casos.md (UC-PAT-07, UC-PAT-08)
// @see prototipo-ui/cowork/Wagner/patrimonio-page.jsx:176 (o `pill`) e modulo-padrao.jsx:52 (o ícone)

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, screen, cleanup } from '@testing-library/react';

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  usePage: () => ({ props: {} }),
}));
vi.mock('@/Pages/Patrimonio/_shared/PatrimonioSubNav', () => ({ default: () => null }));
vi.mock('@/Components/PageHeader', () => ({ PageHeader: () => null }));

import PainelPatrimonio from '@/Pages/Patrimonio/Index';

const KPIS = {
  bruto: 400000,
  valorResidual: null as number | null,
  unidades: 21,
  totalBens: 11,
  alocados: 4,
  alocaveis: 13,
  garantiaCritica: 5,
};

/** A dominante está no MEIO de propósito: assim o caso separa "maior valor" de "primeiro do
 *  array". Com `porCategoria[0]` o selo diria "20% em mobiliário" — e o assert cai. */
const POR_CATEGORIA = [
  { categoria: 'Mobiliário', unidades: 8, valor: 80000 },
  { categoria: 'Impressão', unidades: 3, valor: 240000 },
  { categoria: 'Informática', unidades: 10, valor: 80000 },
];

const BASE = {
  is_admin: true,
  pode: { ver: true, criar: true },
  apurado_em: '2026-09-08T09:42:00-03:00',
  garantia: [],
  manutencoes: [],
};

afterEach(cleanup);

describe('UC-PAT-07 · selo da categoria dominante no card de análise', () => {
  it('CONTROLE POSITIVO: o card "Patrimônio por categoria" existe (o harness renderiza)', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} porCategoria={POR_CATEGORIA} />);
    expect(screen.getByText('Patrimônio por categoria')).toBeDefined();
  });

  it('o selo mostra o percentual da categoria de MAIOR valor, não a primeira do array', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} porCategoria={POR_CATEGORIA} />);
    // 240000 de 400000 = 60%. A fórmula é a do protótipo (`pct` arredondado) sobre o MESMO
    // denominador que as barras deste card usam.
    const selo = screen.getByText('60% em impressão');
    // E ele mora no card certo — um selo no card vizinho seria outro defeito, também verde.
    const card = screen.getByText('Patrimônio por categoria').closest('div[data-slot="card"]');
    expect(card, 'card de categoria').not.toBeNull();
    expect(card!.contains(selo), 'o selo está dentro do card de categoria').toBe(true);
  });

  // Sem a guarda o selo sai `Infinity% em impressão` (medido na mutação 3) — o assert é sobre
  // a AUSÊNCIA do selo, não sobre um valor específico, pra pegar as duas formas de errar.
  it('sem denominador (`kpis` ainda deferido) NÃO inventa selo', () => {
    const { container } = render(
      <PainelPatrimonio {...BASE} kpis={null} porCategoria={POR_CATEGORIA} />,
    );
    expect(container.textContent).not.toContain('% em ');
  });

  it('sem categoria nenhuma o card não ganha selo (e o estado vazio segue de pé)', () => {
    const { container } = render(<PainelPatrimonio {...BASE} kpis={KPIS} porCategoria={[]} />);
    expect(container.textContent).not.toContain('% em ');
    expect(screen.getByText('Nenhum bem cadastrado')).toBeDefined();
  });
});

describe('UC-PAT-08 · ícone do "Resumo de hoje" resolve no lucide, não no fallback', () => {
  it('o bloco abre com o ícone de calendário — `lucide-calendar`, nunca `lucide-circle`', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} porCategoria={[]} />);
    const resumo = document.querySelector('[data-contract="resumo"]');
    expect(resumo, 'bloco do resumo').not.toBeNull();
    // `Icon` resolve por string e cai em `Circle` quando o nome não existe — é a classe que o
    // lucide carimba no `<svg>` que separa o acerto do fallback silencioso.
    expect(resumo!.querySelector('svg.lucide-calendar'), 'ícone de calendário').not.toBeNull();
    expect(resumo!.querySelector('svg.lucide-circle'), 'fallback Circle no resumo').toBeNull();
  });
});
