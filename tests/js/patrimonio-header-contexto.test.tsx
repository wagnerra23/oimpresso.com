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
//   5. ler o nome SÓ da sessão (`nomeDaSessao`, sem o shell)  → 1 caso vermelho
//   6. pôr default `?? 'Oimpresso'` no nome                   → 1 caso vermelho
// A mutação 4 é a que importa mais no selo: sem ela ele ficaria verde mostrando uma hora que
// não tem nada a ver com a apuração — fácil de cometer, difícil de ver.
// As 5 e 6 nasceram DEPOIS, de um defeito REAL visto no render (ver UC-PAT-06): a 5 é o
// estado que estava em produção neste PR até a imagem mostrar o eyebrow sem a empresa; a 6
// é o "conserto" tentador que afirmaria um tenant que não é o do usuário (ADR 0093).
//
// ── FUSO: por que nenhum assert crava "09:42" ────────────────────────────────
// `toLocaleTimeString` resolve no fuso do RUNNER. Cravar a string cria o teste que passa na
// minha máquina (BRT) e falha no CI (UTC) — é a lápide §5 2026-08-07, no eixo timezone.
// Então o contrato é medido em duas partes, ambas imunes ao fuso: o FORMATO (`HH:MM`) e a
// LIGAÇÃO com a prop (dois `apurado_em` distintos ⇒ dois selos distintos; o mesmo ⇒ o mesmo).
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada de GEOMETRIA: jsdom não faz layout, então `-mb-5` e o alinhamento do eyebrow com
//     o h1 não são medidos aqui — e NÃO há baseline de pixel pra cobrir isso: ela foi
//     descontinuada por apodrecer ([W], 2026-09-09). Sobra o olho no render (gate F1.5).
//   - Nada sobre `router.reload()` REAPURAR de fato as props deferidas — aqui o `router` é
//     mock e o assert é que a tela o chama. Que o servidor devolva `apurado_em` novo é
//     contrato do controller (`AssetController:643`).
//   - Nada sobre os LOCAIS, o 3º pedaço do `contexto` do protótipo: ele não desce, de
//     propósito, e o motivo está no comentário do `.tsx` (os KPIs não filtram por
//     `permitted_locations`, então nomeá-los mentiria sobre o escopo dos números).
//
// @see resources/js/Pages/Patrimonio/Index.casos.md (UC-PAT-05, UC-PAT-06)
// @see prototipo-ui/cowork/cli-pagehead.jsx (o desenho que estes casos defendem)

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const reload = vi.fn();

vi.mock('@/Layouts/AppShellV2', () => ({ default: ({ children }: any) => <div>{children}</div> }));

/** Props compartilhadas do render — mutável porque um caso mede a PRECEDÊNCIA entre as duas
 *  fontes do nome da empresa (ver o `describe` de UC-PAT-06). */
let sharedProps: Record<string, unknown> = {};

vi.mock('@inertiajs/react', () => ({
  Deferred: ({ children }: any) => <>{children}</>,
  // `usePageProps()`/`useBusiness()` (`Hooks/usePageProps.ts:4,12`) leem daqui — é por onde
  // as DUAS fontes do nome chegam: `shell.cockpit.businessNome` e `business.name`.
  usePage: () => ({ props: sharedProps }),
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

/** O eyebrow: o `<p>` dentro da âncora do cabeçalho, acima do `<header>` do PageHeader.
 *  Buscar por TEXTO aqui não serve — "11 bens" também aparece na descrição do KPI
 *  "Patrimônio bruto" ("21 unidades em 11 bens"), e o `getByText` acha os dois. Ancorar no
 *  `data-contract` mede o elemento certo e sobrevive à copy mudar. */
const eyebrow = () =>
  document.querySelector<HTMLParagraphElement>('[data-contract="cabecalho"] > p');

/** O estado normal: a sidebar (shell) e a sessão concordam. */
const SHARED_PADRAO = {
  shell: { cockpit: { businessNome: 'ROTA LIVRE' } },
  business: { id: 98, name: 'ROTA LIVRE' },
};

beforeEach(() => {
  sharedProps = structuredClone(SHARED_PADRAO);
});

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
    expect(eyebrow()?.textContent).toBe('ROTA LIVRE · 11 bens');
  });

  it('vem ANTES do título — é eyebrow, não subtítulo', () => {
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    const h1 = screen.getByRole('heading', { level: 1 });
    expect(
      eyebrow()!.compareDocumentPosition(h1) & Node.DOCUMENT_POSITION_FOLLOWING,
      'o contexto precede o h1',
    ).toBeTruthy();
  });

  it('o nome vem do SHELL quando a sessão chega vazia — defeito visto em render real', () => {
    // Regressão REAL, não hipotética, e o recibo é histórico: a última baseline de pixel
    // gerada pra esta tela (antes de a prática ser descontinuada) renderizou o
    // eyebrow como "0 BENS", sem o negócio, enquanto a sidebar do MESMO render mostrava o
    // nome. Causa já registrada em `Produto/Unificado/Index.tsx:235` — `business.name` vem
    // da SESSÃO e chega vazio em ambiente de teste; `shell.cockpit.businessNome` sai de uma
    // query. Sem esta precedência, a linha nasce pela metade e ninguém percebe.
    sharedProps = { shell: { cockpit: { businessNome: 'ROTA LIVRE' } }, business: null };
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    expect(eyebrow()?.textContent).toBe('ROTA LIVRE · 11 bens');
  });

  it('cai pra sessão quando o shell não traz o nome (fallback, não default inventado)', () => {
    sharedProps = { shell: { cockpit: {} }, business: { id: 98, name: 'ROTA LIVRE' } };
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    expect(screen.getByText(/ROTA LIVRE/).textContent).toBe('ROTA LIVRE · 11 bens');
  });

  it('sem nenhuma das duas fontes, mostra só a contagem — NUNCA um tenant inventado', () => {
    // Imprimir "Oimpresso" aqui afirmaria um tenant que não é o do usuário (ADR 0093).
    sharedProps = { shell: { cockpit: {} }, business: null };
    render(<PainelPatrimonio {...BASE} kpis={KPIS} />);
    expect(eyebrow()?.textContent).toBe('11 bens');
    expect(eyebrow()?.textContent, 'tenant inventado').not.toMatch(/Oimpresso|Matriz/);
  });

  it('sem `kpis` (prop deferida ainda no ar), mostra só o negócio — sem separador órfão', () => {
    // O `filter` que o protótipo já tem (`cli-pagehead.jsx:79`) é o que sustenta isto: a
    // contagem chega depois, e enquanto não chega o pedaço SAI do join. Um " · " pendurado
    // no fim seria a marca de que alguém concatenou à mão.
    render(<PainelPatrimonio {...BASE} kpis={undefined} />);
    expect(eyebrow()?.textContent).toBe('ROTA LIVRE');
  });
});
