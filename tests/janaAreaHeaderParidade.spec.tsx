// UC-JPAIN-19 — a barra de abas da área Jana é FAIXA PRÓPRIA abaixo do header, e o
// header não tem primary "Conversar" (paridade com `prototipo-ui/cowork/Wagner/jana-merge.jsx`
// §JanaPage: `<JanaHeader/>` → `{tabs}`; UI-0029 "protótipo soberano").
//
// Mede o DOM que o componente RENDERIZA (posição relativa, zona, ícone), não o texto do
// arquivo — é comportamento, não presença (LC-11). Medição de origem, 2026-09-03, mesma
// sonda nos dois lados: âncora tablist `left=284 w=2237` numa faixa 14px abaixo do
// header; produção tablist `left=1654 w=451` INLINE no mesmo `top` do h1.
//
// Roda em jsdom via vitest (`npm test`). Inertia é mockado: `usePage` devolve o
// `shell.menu` que o `DataController` da Jana publica (6 ghosts + primary), `router.reload`
// é no-op e `Link` vira `<a>`.
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';

const GHOSTS = [
  { key: 'dashboard', label: 'Painel', href: '/ia' },
  { key: 'copiloto', label: 'Conversa', href: '/ia/conversa' },
  { key: 'alertas', label: 'Alertas', href: '/ia/alertas' },
  { key: 'acoes', label: 'Ações', href: '/ia/acoes' },
  { key: 'memorias', label: 'Memória', href: '/ia/memoria' },
  { key: 'plataforma', label: 'Plataforma', href: '/ia/superadmin/metas' },
];

vi.mock('@inertiajs/react', () => ({
  usePage: () => ({
    url: '/ia',
    props: {
      shell: {
        menu: [
          {
            label: 'Jana',
            group: 'ia',
            // O primary EXISTE no shell (é da sidebar) — o header tem que IGNORÁ-LO.
            primary: { label: 'Conversar', href: '/ia/conversa' },
            ghosts: GHOSTS,
          },
        ],
      },
    },
  }),
  router: { reload: () => {}, visit: () => {} },
  Link: ({ href, children, ...rest }: any) => (
    <a href={href} {...rest}>
      {children}
    </a>
  ),
}));

import { JanaAreaHeader } from '@/Pages/Jana/_components/JanaAreaHeader';

afterEach(cleanup);

function renderHeader() {
  const { container } = render(
    <JanaAreaHeader active="dashboard" businessName="WR2 Sistemas" businessId={1} actions={<button type="button">Configurar</button>} />,
  );
  const header = container.querySelector('header');
  if (!header) throw new Error('JanaAreaHeader não renderizou <header>');
  const tablist = header.querySelector<HTMLElement>('[role="tablist"]');
  if (!tablist) throw new Error('barra de abas (role=tablist) não renderizou');
  const h1 = header.querySelector('h1');
  if (!h1) throw new Error('h1 não renderizou');
  return { container, header, tablist, h1 };
}

describe('UC-JPAIN-19 · barra de abas em faixa própria abaixo do header (paridade jana-merge.jsx)', () => {
  it('UC-JPAIN-19: a tablist NÃO está na linha do título — é filha direta do <header>, DEPOIS da linha título/ações', () => {
    const { header, tablist, h1 } = renderHeader();
    const linhaTitulo = h1.closest('header > div');
    expect(linhaTitulo).not.toBeNull();
    // Não está dentro da linha do título (Zona C inline era o defeito).
    expect(linhaTitulo!.contains(tablist)).toBe(false);
    // Vem DEPOIS do h1 na ordem do documento (header em cima, abas abaixo).
    // eslint-disable-next-line no-bitwise
    expect(h1.compareDocumentPosition(tablist) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    // E a faixa é filha do próprio <header> (herda sticky + border-b como linha de base).
    expect(tablist.closest('header')).toBe(header);
  });

  it('UC-JPAIN-19: as 6 abas da âncora estão na barra, na ordem, cada uma com ícone', () => {
    const { tablist } = renderHeader();
    const tabs = [...tablist.querySelectorAll<HTMLElement>('[role="tab"]')];
    expect(tabs.map((t) => t.textContent?.trim())).toEqual([
      'Painel', 'Conversa', 'Alertas', 'Ações', 'Memória', 'Plataforma',
    ]);
    // Ícone por aba (âncora: `icon: "chart" | "sparkles" | "alert" | "bulb" | "database" | "shield"`).
    expect(tabs.every((t) => t.querySelector('svg') !== null)).toBe(true);
    expect(tabs[0].getAttribute('aria-selected')).toBe('true');
  });

  it('UC-JPAIN-19: o header NÃO tem primary "Conversar" — a Conversa é uma aba', () => {
    const { header, tablist } = renderHeader();
    const foraDaBarra = [...header.querySelectorAll<HTMLElement>('a, button')].filter(
      (el) => !tablist.contains(el),
    );
    expect(foraDaBarra.map((el) => el.textContent?.trim())).not.toContain('Conversar');
  });

  it('UC-JPAIN-19: "Atualizado HH:MM" vive na ZONA DIREITA (antes das ações), não no subtítulo', () => {
    const { header, h1 } = renderHeader();
    const atualizado = [...header.querySelectorAll<HTMLElement>('button')].find((b) =>
      /^Atualizado \d{2}:\d{2}$/.test(b.textContent?.trim() ?? ''),
    );
    expect(atualizado, 'botão "Atualizado HH:MM" ausente').toBeTruthy();
    // Não é descendente do bloco de identidade (h1 + subtítulo).
    const identidade = h1.parentElement!;
    expect(identidade.contains(atualizado!)).toBe(false);
    // Está no MESMO container das ações da tela, e antes delas.
    const configurar = [...header.querySelectorAll<HTMLElement>('button')].find(
      (b) => b.textContent?.trim() === 'Configurar',
    )!;
    expect(atualizado!.parentElement).toBe(configurar.parentElement);
    // eslint-disable-next-line no-bitwise
    expect(atualizado!.compareDocumentPosition(configurar) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    // Subtítulo é a identidade do tenant em mono, sem o "Atualizado".
    const sub = identidade.querySelector('p');
    expect(sub?.textContent).toContain('WR2 SISTEMAS');
    expect(sub?.textContent).not.toContain('Atualizado');
  });
});

// UC-JPAIN-30 — o h1 da área Jana tem peso 600, e o opt-in NÃO vaza pro canon.
//
// A âncora da Jana (`jana-merge.jsx` → `CliPageHead`) não declara peso e herda o token
// do DS (`colors_and_type.css:373` `h1 { font-weight: 600 }`, `--fs-7: 22px`). O default
// do `PageHeader` canon ERA 700 até 2026-09-23 (desde então 600, D-PH-0923) e seguia OUTRA âncora — a de Vendas, que declara 700 em
// `financeiro.css:1727` (regra que vence por especificidade, 0-3-1). Duas âncoras que
// discordam; o componente serve as duas por opt-in, não por mudança de default.
//
// ⚠️ LIMITE DESTE TESTE, declarado: jsdom não carrega o CSS do Tailwind, então
// `getComputedStyle(h1).fontWeight` aqui NÃO resolve a classe — devolveria o default do
// user-agent, e assertar sobre ele seria medir a propriedade errada (§5 2026-07-16). O que
// este teste prova é que o componente RENDERIZA a classe certa no DOM (comportamento, não
// texto do arquivo — LC-11). Que o browser COMPUTA 600 é prova de runtime, e mora no
// `governance/design/targets/jana--index.alvo.json` §`header_titulo` + no visreg.
describe('UC-JPAIN-30 · peso do h1: 600 (token do DS) na Jana e no canon', () => {
  it('UC-JPAIN-30: o h1 da área Jana renderiza font-semibold, nunca font-bold', () => {
    const { h1 } = renderHeader();
    expect(h1.classList.contains('font-semibold'), 'h1 da Jana deveria ser 600 (token do DS)').toBe(true);
    expect(h1.classList.contains('font-bold'), 'h1 da Jana não pode carregar o 700 da âncora de Vendas').toBe(false);
  });

  // Reescrito em 2026-09-23 (thread PageHeader/04): até ali esta perna travava o default
  // em 700 (âncora de Vendas, PR #1477). [W] decidiu o 600 para todas as telas (D-PH-0923),
  // e pela cadeia do eixo FORMA (UI-0029) o teste que fixou a forma antiga é reescrito,
  // nunca desabilitado. Agora ela protege as DUAS pontas do contrato novo.
  it('UC-JPAIN-30 (canon): sem a prop o h1 é 600; `titleWeight="bold"` é o opt-in do 700', async () => {
    const { PageHeader } = await import('@/Components/PageHeader');
    const semProp = render(<PageHeader title="Clientes" />).container.querySelector('h1');
    if (!semProp) throw new Error('PageHeader não renderizou h1');
    expect(semProp.classList.contains('font-semibold'), 'default do canon deveria ser 600 (D-PH-0923)').toBe(true);
    expect(semProp.classList.contains('font-bold')).toBe(false);
    expect(semProp.classList.contains('tracking-[-0.015em]'), 'tracking do h1 deveria ser -0.015em').toBe(true);
    expect(semProp.classList.contains('tracking-tight')).toBe(false);

    const optIn = render(<PageHeader title="Vendas" titleWeight="bold" />).container.querySelector('h1');
    if (!optIn) throw new Error('PageHeader não renderizou h1 com opt-in');
    expect(optIn.classList.contains('font-bold'), 'opt-in bold deveria render 700').toBe(true);
    expect(optIn.classList.contains('font-semibold')).toBe(false);
  });
});
